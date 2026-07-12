<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Enums\StockMovementType;
use App\Domain\Repositories\StockMovementRepositoryInterface;
use App\Models\Order;
use App\Models\StockMovement;

class EloquentStockMovementRepository implements StockMovementRepositoryInterface
{
    public function create(array $data): StockMovement
    {
        return StockMovement::query()->create($data);
    }

    public function markOrderReservationsAsSale(int $orderId): void
    {
        StockMovement::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $orderId)
            ->where('type', StockMovementType::Reservation->value)
            ->update(['type' => StockMovementType::Sale->value]);
    }
}
