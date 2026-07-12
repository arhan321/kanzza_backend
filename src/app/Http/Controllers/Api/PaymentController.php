<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\PaymentService;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function createOrReuse(Request $request, Order $order): JsonResponse
    {
        $payment = $this->paymentService->createOrReuse(
            $request->user(),
            $order,
        );

        return $this->success(
            new PaymentResource($payment),
            'Halaman pembayaran Midtrans tersedia.',
        );
    }

    public function checkStatus(Request $request, Order $order): JsonResponse
    {
        $result = $this->paymentService->checkStatus(
            $request->user(),
            $order,
        );

        $message = match ($result['payment']->status->value) {
            'paid' => 'Pembayaran berhasil dikonfirmasi.',
            'pending' => 'Pembayaran masih menunggu.',
            'expired' => 'Pembayaran telah kedaluwarsa. Silakan buat pembayaran baru.',
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
}
