<?php

namespace App\Observers;

use App\Models\Order;
use App\Enums\PaymentStatus;
use App\Services\CashierNotificationService;
use App\Services\CustomerNotificationService;

class OrderObserver
{
    public function __construct(
        private readonly CustomerNotificationService $customerNotifications,
        private readonly CashierNotificationService $cashierNotifications,
    ) {}

    public function created(Order $order): void
    {
        $this->customerNotifications->orderCreated($order);
        $this->cashierNotifications->orderCreated($order);
    }

    public function updated(Order $order): void
    {
        if (
            $order->wasChanged('payment_status')
            && $order->payment_status === PaymentStatus::Paid
        ) {
            $this->customerNotifications->paymentConfirmed($order);
            $this->cashierNotifications->paymentConfirmed($order);
        }
    }
}
