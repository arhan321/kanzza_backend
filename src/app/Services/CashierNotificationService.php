<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CashierNotification;
use App\Models\Order;
use App\Models\User;

class CashierNotificationService
{
    public function orderCreated(Order $order): void
    {
        if ($order->channel !== OrderChannel::Online) {
            return;
        }

        $amount = number_format($order->grand_total, 0, ',', '.');
        $customerName = $order->customer?->name ?? 'Customer';

        $this->recordForActiveCashiers(
            order: $order,
            event: 'cashier_order_created',
            title: 'Pesanan online baru',
            message: "{$customerName} membuat pesanan {$order->order_number} senilai Rp {$amount}.",
            data: [
                'order_number' => $order->order_number,
                'customer_name' => $customerName,
                'order_status' => $order->order_status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method->value,
                'grand_total' => $order->grand_total,
            ],
        );
    }

    public function paymentConfirmed(Order $order): void
    {
        if ($order->channel !== OrderChannel::Online) {
            return;
        }

        $amount = number_format($order->grand_total, 0, ',', '.');

        $this->recordForActiveCashiers(
            order: $order,
            event: 'cashier_payment_confirmed',
            title: 'Pembayaran customer berhasil',
            message: "Pembayaran Rp {$amount} untuk pesanan {$order->order_number} telah dikonfirmasi.",
            data: [
                'order_number' => $order->order_number,
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
    private function recordForActiveCashiers(
        Order $order,
        string $event,
        string $title,
        string $message,
        array $data,
    ): void {
        User::query()
            ->where('role', UserRole::Cashier->value)
            ->where('status', UserStatus::Active->value)
            ->select('id')
            ->eachById(function (User $cashier) use (
                $order,
                $event,
                $title,
                $message,
                $data,
            ): void {
                CashierNotification::query()->firstOrCreate(
                    [
                        'user_id' => $cashier->id,
                        'order_id' => $order->id,
                        'event' => $event,
                    ],
                    [
                        'title' => $title,
                        'message' => $message,
                        'data' => $data,
                    ],
                );
            });
    }
}
