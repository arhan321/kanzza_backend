<?php

namespace App\Domain\Repositories;

use App\Models\Order;
use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function latestForOrder(Order $order): ?Payment;

    public function create(array $data): Payment;

    public function update(Payment $payment, array $data): Payment;
}
