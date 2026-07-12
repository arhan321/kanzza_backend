<?php

namespace App\Domain\Repositories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function paginateForUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Order;

    public function update(Order $order, array $data): Order;
}
