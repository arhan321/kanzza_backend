<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\PaymentRepositoryInterface;
use App\Models\Order;
use App\Models\Payment;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function latestForOrder(Order $order): ?Payment
    {
        return $order->payments()->latest('attempt_number')->first();
    }

    public function create(array $data): Payment
    {
        return Payment::query()->create($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->fill($data);
        $payment->save();

        return $payment->refresh();
    }
}
