<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** How many months one billing period covers. */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnual => 6,
            self::Annual => 12,
        };
    }

    /** What one invoice costs, given the agreed monthly rent. */
    public function amountFor(float $monthlyRent): float
    {
        return round($monthlyRent * $this->months(), 2);
    }
}
