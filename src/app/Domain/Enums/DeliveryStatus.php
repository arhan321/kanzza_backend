<?php

namespace App\Domain\Enums;

enum DeliveryStatus: string
{
    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case OnDelivery = 'on_delivery';
    case Delivered = 'delivered';

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
