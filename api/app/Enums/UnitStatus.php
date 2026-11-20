<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Vacant = 'vacant';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
