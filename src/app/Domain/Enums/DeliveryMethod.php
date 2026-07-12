<?php

namespace App\Domain\Enums;

enum DeliveryMethod: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

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
