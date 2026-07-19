<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Models\CustomerNotification;
use App\Models\Order;

class CustomerNotificationService
{
    public function orderCreated(Order $order): ?CustomerNotification
    {
        if ($order->customer_id === null || $order->channel !== OrderChannel::Online) {
            return null;
        }

        $isCod = $order->payment_method === PaymentMethod::Cash;

        return $this->record(
            order: $order,
            event: 'order_created',
            title: 'Pesanan berhasil dibuat',
            message: $isCod
                ? "Pesanan {$order->order_number} berhasil dibuat. Siapkan pembayaran tunai saat pesanan tiba."
                : "Pesanan {$order->order_number} berhasil dibuat. Silakan selesaikan pembayaran agar pesanan dapat diproses.",
            data: [
                'order_status' => $order->order_status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method->value,
                'grand_total' => $order->grand_total,
            ],
        );
    }

    public function paymentConfirmed(Order $order): ?CustomerNotification
    {
        if ($order->customer_id === null || $order->channel !== OrderChannel::Online) {
            return null;
        }

        $amount = number_format($order->grand_total, 0, ',', '.');

        return $this->record(
            order: $order,
            event: 'payment_confirmed',
            title: 'Pembayaran berhasil',
            message: "Pembayaran Rp {$amount} untuk pesanan {$order->order_number} telah dikonfirmasi.",
            data: [
                'order_status' => $order->order_status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method->value,
                'grand_total' => $order->grand_total,
                'paid_at' => $order->paid_at?->toISOString(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(
        Order $order,
        string $event,
        string $title,
        string $message,
        array $data,
    ): CustomerNotification {
        return CustomerNotification::query()->firstOrCreate(
            [
                'user_id' => $order->customer_id,
                'order_id' => $order->id,
                'event' => $event,
            ],
            [
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ],
        );
    }
}
