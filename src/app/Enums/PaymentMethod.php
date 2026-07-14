<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Midtrans = 'midtrans';
    case Cash = 'cash';

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
