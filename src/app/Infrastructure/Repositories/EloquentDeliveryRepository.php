<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\DeliveryRepositoryInterface;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentDeliveryRepository implements DeliveryRepositoryInterface
{
    public function findForOrder(Order $order): ?Delivery
    {
        return $order->delivery()->first();
    }

    public function createOrUpdateForOrder(Order $order, array $data): Delivery
    {
        return Delivery::query()->updateOrCreate(
            ['order_id' => $order->id],
            $data,
        )->load(['order.items', 'order.customer', 'driver']);
    }

    public function paginateForDriver(User $driver, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Delivery::query()
            ->with(['order.items', 'order.customer', 'driver'])
            ->where('driver_id', $driver->id)
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest('assigned_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function update(Delivery $delivery, array $data): Delivery
    {
        $delivery->fill($data);
        $delivery->save();

        return $delivery->refresh()->load(['order.items', 'order.customer', 'driver']);
    }
}
