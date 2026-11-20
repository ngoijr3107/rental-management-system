<?php

namespace App\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Terminated = 'terminated';
    case Expired = 'expired';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
