<?php

namespace App\Domain\Enums;

enum OrderChannel: string
{
    case Online = 'online';
    case Cashier = 'cashier';

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
