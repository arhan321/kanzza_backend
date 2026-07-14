<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Services\MidtransClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends ApiController
{
    public function __construct(
        private readonly MidtransClient $midtrans,
    ) {}

    public function createOrReuse(Request $request, Order $order): JsonResponse
    {
        $order->ensureVisibleTo($request->user());

        if ($order->channel !== OrderChannel::Online) {
            throw ValidationException::withMessages([
                'order' => ['Midtrans hanya digunakan untuk pesanan online.'],
            ]);
        }

        if ($order->payment_method !== PaymentMethod::Midtrans) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'Pesanan COD dibayar kepada driver dan tidak menggunakan Midtrans.',
                ],
            ]);
        }

        if ($order->order_status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan yang sudah dibatalkan tidak dapat dibayar kembali.'],
            ]);
        }

        $latestPayment = $order->payments()->latest('attempt_number')->first();

        if ($order->isPaid()) {
            if ($latestPayment === null) {
                throw ValidationException::withMessages([
                    'payment' => ['Data pembayaran berhasil tidak ditemukan.'],
                ]);
            }

            $payment = $latestPayment->load('order.items');
        } elseif ($latestPayment?->isReusable()) {
            $payment = $latestPayment->load('order.items');
        } else {
            $payment = $this->createPayment($order, $latestPayment);
        }

        return $this->success(
            new PaymentResource($payment),
            'Halaman pembayaran Midtrans tersedia.',
        );
    }

    public function checkStatus(Request $request, Order $order): JsonResponse
    {
        $order->ensureVisibleTo($request->user());

        if ($order->payment_method !== PaymentMethod::Midtrans) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'Status pembayaran COD dikonfirmasi oleh driver saat pesanan diterima.',
                ],
            ]);
        }

        $payment = $order->payments()->latest('attempt_number')->first();

        if ($payment === null) {
            throw ValidationException::withMessages([
                'payment' => ['Pembayaran belum dibuat untuk pesanan ini.'],
            ]);
        }

        if ($payment->status === PaymentStatus::Paid) {
            $result = [
                'payment' => $payment->load('order.items'),
                'midtrans_status' => 'settlement',
                'changed' => false,
            ];
        } else {
            $result = $this->refreshPaymentStatus($order, $payment);
        }

        $message = match ($result['payment']->status->value) {
            'paid' => 'Pembayaran berhasil dikonfirmasi.',
            'pending' => 'Pembayaran masih menunggu.',
            'expired' => 'Pembayaran kedaluwarsa dan stok telah dikembalikan. Silakan buat pesanan baru.',
            'failed' => 'Pembayaran gagal.',
            'cancelled' => 'Pembayaran dibatalkan.',
            default => 'Status pembayaran berhasil diperiksa.',
        };

        return $this->success([
            'payment' => new PaymentResource($result['payment']),
            'midtrans_status' => $result['midtrans_status'],
            'status_changed' => $result['changed'],
            'order_payment_status' => $result['payment']->order->payment_status->value,
            'order_status' => $result['payment']->order->order_status->value,
        ], $message);
    }

    public function notification(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'string', 'max:100'],
            'status_code' => ['required', 'string', 'max:10'],
            'gross_amount' => ['required', 'numeric'],
            'signature_key' => ['required', 'string', 'size:128'],
            'transaction_status' => ['required', 'string', 'max:30'],
            'fraud_status' => ['nullable', 'string', 'max:30'],
        ]);

        $serverKey = (string) config('midtrans.server_key');
        $midtransOrderId = (string) $request->string('order_id');
        $statusCode = (string) $request->string('status_code');
        $grossAmount = (string) $request->string('gross_amount');
        $signature = (string) $request->string('signature_key');
        $expectedSignature = hash(
            'sha512',
            $midtransOrderId.$statusCode.$grossAmount.$serverKey,
        );

        if ($serverKey === '' || ! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Signature notification Midtrans tidak valid.');
        }

        $payment = Payment::query()
            ->with('order')
            ->where('midtrans_order_id', $midtransOrderId)
            ->firstOrFail();

        if ((int) round((float) $request->input('gross_amount')) !== $payment->gross_amount) {
            throw ValidationException::withMessages([
                'gross_amount' => ['Nominal notification tidak sesuai dengan pembayaran.'],
            ]);
        }

        $transactionStatus = (string) $request->input('transaction_status');
        $payment = $this->applyPaymentResponse(
            $payment->order,
            $payment,
            $request->all(),
        );

        return $this->success([
            'payment_status' => $payment->status->value,
            'order_status' => $payment->order->order_status->value,
        ], "Notification Midtrans {$transactionStatus} berhasil diproses.");
    }

    private function createPayment(Order $order, ?Payment $latestPayment): Payment
    {
        return DB::transaction(function () use ($order, $latestPayment): Payment {
            if (
                $latestPayment !== null
                && $latestPayment->status === PaymentStatus::Pending
                && $latestPayment->expiry_time?->isPast()
            ) {
                $latestPayment->update(['status' => PaymentStatus::Expired]);
            }

            $order->loadMissing(['customer', 'items']);
            $attemptNumber = ($latestPayment?->attempt_number ?? 0) + 1;
            $midtransOrderId = sprintf(
                'KZ-%d-P%d-%s',
                $order->id,
                $attemptNumber,
                now()->format('YmdHis'),
            );
            $expiryHours = (int) config('midtrans.snap_expiry_hours', 24);
            $response = $this->midtrans->createSnapTransaction(
                $this->buildSnapPayload($order, $midtransOrderId, $expiryHours),
            );

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'attempt_number' => $attemptNumber,
                'provider' => 'midtrans',
                'midtrans_order_id' => $midtransOrderId,
                'snap_token' => $response['token'] ?? null,
                'snap_redirect_url' => $response['redirect_url'] ?? null,
                'gross_amount' => $order->grand_total,
                'status' => PaymentStatus::Pending,
                'expiry_time' => now()->addHours($expiryHours),
                'raw_response' => $response,
            ]);

            $order->update(['payment_status' => PaymentStatus::Pending]);

            return $payment->load('order.items');
        }, 3);
    }

    /**
     * @return array{payment: Payment, midtrans_status: string, changed: bool}
     */
    private function refreshPaymentStatus(Order $order, Payment $payment): array
    {
        $response = $this->midtrans->getTransactionStatus($payment->midtrans_order_id);
        $transactionStatus = (string) ($response['transaction_status'] ?? 'unknown');

        if ($transactionStatus === 'not_found') {
            $payment->update(['raw_response' => $response]);

            return [
                'payment' => $payment->refresh()->load('order.items'),
                'midtrans_status' => $transactionStatus,
                'changed' => false,
            ];
        }

        $mappedStatus = $this->mapMidtransStatus($transactionStatus, $response['fraud_status'] ?? null);
        $changed = $payment->status !== $mappedStatus;
        $payment = $this->applyPaymentResponse($order, $payment, $response);

        return [
            'payment' => $payment,
            'midtrans_status' => $transactionStatus,
            'changed' => $changed,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function applyPaymentResponse(
        Order $order,
        Payment $payment,
        array $response,
    ): Payment {
        return DB::transaction(function () use ($payment, $order, $response): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $mappedStatus = $this->mapMidtransStatus(
                (string) ($response['transaction_status'] ?? 'unknown'),
                $response['fraud_status'] ?? null,
            );

            if (
                $payment->status === PaymentStatus::Paid
                && ! in_array($mappedStatus, [PaymentStatus::Paid, PaymentStatus::Refunded], true)
            ) {
                return $payment->load('order.items');
            }

            $paymentData = [
                'midtrans_transaction_id' => $response['transaction_id']
                    ?? $payment->midtrans_transaction_id,
                'payment_type' => $response['payment_type'] ?? $payment->payment_type,
                'status' => $mappedStatus,
                'fraud_status' => $response['fraud_status'] ?? null,
                'transaction_time' => $this->parseMidtransDate($response['transaction_time'] ?? null),
                'settlement_time' => $this->parseMidtransDate($response['settlement_time'] ?? null),
                'expiry_time' => $this->parseMidtransDate($response['expiry_time'] ?? null)
                    ?? $payment->expiry_time,
                'raw_response' => $response,
            ];

            if ($mappedStatus === PaymentStatus::Paid) {
                $paymentData['paid_at'] = $this->parseMidtransDate(
                    $response['settlement_time']
                    ?? $response['transaction_time']
                    ?? null,
                ) ?? now();
            }

            $payment->update($paymentData);
            $payment->refresh();

            $orderData = ['payment_status' => $mappedStatus];

            if ($mappedStatus === PaymentStatus::Paid) {
                $orderData['paid_at'] = $payment->paid_at ?? now();

                if ($order->order_status === OrderStatus::PendingPayment) {
                    $orderData['order_status'] = OrderStatus::Confirmed;
                }

                StockMovement::query()
                    ->where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', StockMovementType::Reservation->value)
                    ->update(['type' => StockMovementType::Sale->value]);
            }

            if (in_array(
                $mappedStatus,
                [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired],
                true,
            )) {
                $order->restoreReservedStock(
                    userId: $order->customer_id,
                    notes: "Pengembalian stok otomatis karena pembayaran Midtrans {$mappedStatus->value}.",
                );
                $orderData['order_status'] = OrderStatus::Cancelled;
            }

            $order->update($orderData);

            return $payment->load('order.items');
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapPayload(
        Order $order,
        string $midtransOrderId,
        int $expiryHours,
    ): array {
        $itemDetails = $order->items->map(
            static fn ($item): array => [
                'id' => (string) ($item->product_id ?? $item->product_sku),
                'price' => $item->price,
                'quantity' => $item->quantity,
                'name' => mb_substr($item->product_name, 0, 50),
            ],
        )->values()->all();

        if ($order->shipping_cost > 0) {
            $itemDetails[] = [
                'id' => 'SHIPPING',
                'price' => $order->shipping_cost,
                'quantity' => 1,
                'name' => 'Ongkos Kirim',
            ];
        }

        return [
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => $order->grand_total,
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $order->customer?->name ?? 'Customer Kanzza',
                'email' => $order->customer?->email,
                'phone' => $order->customer?->phone,
            ],
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'hour',
                'duration' => $expiryHours,
            ],
            'custom_field1' => $order->order_number,
        ];
    }

    private function mapMidtransStatus(
        string $transactionStatus,
        ?string $fraudStatus,
    ): PaymentStatus {
        return match ($transactionStatus) {
            'settlement' => PaymentStatus::Paid,
            'capture' => $fraudStatus === 'accept'
                ? PaymentStatus::Paid
                : ($fraudStatus === 'deny' ? PaymentStatus::Failed : PaymentStatus::Pending),
            'pending', 'authorize' => PaymentStatus::Pending,
            'deny', 'failure' => PaymentStatus::Failed,
            'cancel' => PaymentStatus::Cancelled,
            'expire' => PaymentStatus::Expired,
            'refund', 'partial_refund' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };
    }

    private function parseMidtransDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
