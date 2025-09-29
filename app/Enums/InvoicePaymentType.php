<?php

namespace App\Enums;

enum InvoicePaymentType: string
{
    case FULL = 'full';
    case PARTIAL = 'partial';
    case SPLIT = 'split';
    case CREDIT = 'credit';
    case CASH = 'cash';

    public static function labels(): array
    {
        return [
            self::FULL->value => 'Full Payment',
            self::PARTIAL->value => 'Partial Payment',
            self::SPLIT->value => 'Split Payment',
            self::CREDIT->value => 'Credit Payment',
            self::CASH->value => 'Cash Payment',
        ];
    }
}