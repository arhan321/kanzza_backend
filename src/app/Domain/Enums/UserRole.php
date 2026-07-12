<?php

namespace App\Domain\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Cashier = 'cashier';
    case Driver = 'driver';
    case Owner = 'owner';

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
