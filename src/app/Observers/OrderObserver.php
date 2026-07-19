<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\CustomerNotificationService;

class OrderObserver
{
    public function __construct(
        private readonly CustomerNotificationService $notifications,
    ) {}

    public function created(Order $order): void
    {
        $this->notifications->orderCreated($order);
    }

    public function updated(Order $order): void
    {
        if (
            $order->wasChanged('payment_status')
            && $order->payment_status === PaymentStatus::Paid
        ) {
            $this->notifications->paymentConfirmed($order);
        }
    }
}
