<?php
namespace App\Enums;

enum TransactionType: string
{
    case ONEOFF = 'one-off';
    case RECURRING = 'recurring';
    case TIP = 'tip';
    case REFUND = 'refund';


    public static function getValues(): array
    {
        return [
            self::ONEOFF,
            self::RECURRING,
            self::TIP,
            self::REFUND,
        ];
    }
}
