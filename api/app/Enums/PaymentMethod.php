<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Cheque = 'cheque';
    case Card = 'card';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
