<?php

namespace App\Domain\Repositories;

use App\Models\StockMovement;

interface StockMovementRepositoryInterface
{
    public function create(array $data): StockMovement;

    public function markOrderReservationsAsSale(int $orderId): void;
}
