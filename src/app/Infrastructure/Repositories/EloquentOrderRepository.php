<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Enums\UserRole;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function paginateForUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['customer', 'cashier', 'items', 'latestPayment', 'delivery.driver']);

        if ($user->isRole(UserRole::Customer)) {
            $query->where('customer_id', $user->id);
        } elseif ($user->isRole(UserRole::Driver)) {
            $query->whereHas(
                'delivery',
                fn ($deliveryQuery) => $deliveryQuery->where('driver_id', $user->id),
            );
        }

        return $query
            ->when(
                $filters['order_status'] ?? null,
                fn ($builder, string $status) => $builder->where('order_status', $status),
            )
            ->when(
                $filters['payment_status'] ?? null,
                fn ($builder, string $status) => $builder->where('payment_status', $status),
            )
            ->when(
                $filters['channel'] ?? null,
                fn ($builder, string $channel) => $builder->where('channel', $channel),
            )
            ->when(
                $filters['search'] ?? null,
                fn ($builder, string $search) => $builder->where('order_number', 'like', "%{$search}%"),
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Order
    {
        return Order::query()->create($data);
    }

    public function update(Order $order, array $data): Order
    {
        $order->fill($data);
        $order->save();

        return $order->refresh();
    }
}
