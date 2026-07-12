<?php

namespace App\Application\Services;

use App\Domain\Enums\OrderChannel;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\StockMovementType;
use App\Domain\Enums\UserRole;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\PaymentRepositoryInterface;
use App\Domain\Repositories\StockMovementRepositoryInterface;
use App\Infrastructure\Payments\MidtransClient;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly OrderRepositoryInterface $orders,
        private readonly StockMovementRepositoryInterface $stockMovements,
        private readonly MidtransClient $midtrans,
        private readonly OrderService $orderService,
    ) {
    }

    public function createOrReuse(User $actor, Order $order): Payment
    {
        $this->orderService->ensureCanView($actor, $order);

        if ($order->channel !== OrderChannel::Online) {
            throw ValidationException::withMessages([
                'order' => ['Midtrans hanya digunakan untuk pesanan online.'],
            ]);
        }

        $latestPayment = $this->payments->latestForOrder($order);

        if ($order->isPaid()) {
            if ($latestPayment === null) {
                throw ValidationException::withMessages([
                    'payment' => ['Data pembayaran berhasil tidak ditemukan.'],
                ]);
            }

            return $latestPayment->load('order.items');
        }

        if ($latestPayment?->isReusable()) {
            return $latestPayment->load('order.items');
        }

        return DB::transaction(function () use ($order, $latestPayment): Payment {
            if (
                $latestPayment !== null
                && $latestPayment->status === PaymentStatus::Pending
                && $latestPayment->expiry_time?->isPast()
            ) {
                $this->payments->update($latestPayment, [
                    'status' => PaymentStatus::Expired,
                ]);
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
            $payload = $this->buildSnapPayload($order, $midtransOrderId, $expiryHours);
            $response = $this->midtrans->createSnapTransaction($payload);

            $payment = $this->payments->create([
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

            $this->orders->update($order, [
                'payment_status' => PaymentStatus::Pending,
            ]);

            return $payment->load('order.items');
        }, 3);
    }

    /**
     * @return array{payment: Payment, midtrans_status: string, changed: bool}
     */
    public function checkStatus(User $actor, Order $order): array
    {
        $this->orderService->ensureCanView($actor, $order);

        $payment = $this->payments->latestForOrder($order);

        if ($payment === null) {
            throw ValidationException::withMessages([
                'payment' => ['Pembayaran belum dibuat untuk pesanan ini.'],
            ]);
        }

        if ($payment->status === PaymentStatus::Paid) {
            return [
                'payment' => $payment->load('order.items'),
                'midtrans_status' => 'settlement',
                'changed' => false,
            ];
        }

        $response = $this->midtrans->getTransactionStatus($payment->midtrans_order_id);
        $transactionStatus = (string) ($response['transaction_status'] ?? 'unknown');

        if ($transactionStatus === 'not_found') {
            $payment = $this->payments->update($payment, [
                'raw_response' => $response,
            ]);

            return [
                'payment' => $payment->load('order.items'),
                'midtrans_status' => $transactionStatus,
                'changed' => false,
            ];
        }

        $mappedStatus = $this->mapMidtransStatus(
            $transactionStatus,
            $response['fraud_status'] ?? null,
        );

        $changed = $payment->status !== $mappedStatus;

        $payment = DB::transaction(function () use (
            $payment,
            $order,
            $response,
            $mappedStatus,
        ): Payment {
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

            $updatedPayment = $this->payments->update($payment, $paymentData);

            $orderData = [
                'payment_status' => $mappedStatus,
            ];

            if ($mappedStatus === PaymentStatus::Paid) {
                $orderData['paid_at'] = $updatedPayment->paid_at ?? now();

                if ($order->order_status === OrderStatus::PendingPayment) {
                    $orderData['order_status'] = OrderStatus::Confirmed;
                }

                $this->stockMovements->markOrderReservationsAsSale($order->id);
            }

            $this->orders->update($order, $orderData);

            return $updatedPayment;
        }, 3);

        return [
            'payment' => $payment->load('order.items'),
            'midtrans_status' => $transactionStatus,
            'changed' => $changed,
        ];
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
