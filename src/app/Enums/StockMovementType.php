<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Reservation = 'reservation';
    case Sale = 'sale';
    case Restoration = 'restoration';
    case Adjustment = 'adjustment';
    case Inbound = 'inbound';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
