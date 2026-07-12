<?php

namespace App\Domain\Repositories;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeliveryRepositoryInterface
{
    public function findForOrder(Order $order): ?Delivery;

    public function createOrUpdateForOrder(Order $order, array $data): Delivery;

    public function paginateForDriver(User $driver, array $filters, int $perPage = 15): LengthAwarePaginator;

    public function update(Delivery $delivery, array $data): Delivery;
}
