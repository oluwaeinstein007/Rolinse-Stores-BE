<?php
namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case INESCROW = 'in-escrow';
    case COMPLETED = 'completed';
    case WITHDRAWN = 'withdrawn';


    public static function getValues(): array
    {
        return [
            self::PENDING,
            self::CANCELLED,
            self::REJECTED,
            self::INESCROW,
            self::COMPLETED,
            self::WITHDRAWN,
        ];
    }
}
