<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Statuses that still owe money. */
    public static function outstanding(): array
    {
        return [self::Unpaid, self::PartiallyPaid];
    }
}
