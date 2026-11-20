<?php

namespace App\Enums;

enum PropertyType: string
{
    case Apartment = 'apartment';
    case House = 'house';
    case Commercial = 'commercial';
    case Mixed = 'mixed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
