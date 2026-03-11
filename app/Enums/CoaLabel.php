<?php

namespace App\Enums;

enum CoaLabel: string
{
    case BONUS = 'bonus';
    case CASH = 'cash';
    case PAYABLE = 'payable';
    case RECEIVABLE = 'receivable';
    case PROFIT = 'profit';
    case EXPENSE = 'expense';
    case LIABILITY = 'liability';
    case ASSET = 'asset';
    case EQUITY = 'equity';
    case INCOME = 'income';
    case BANK = 'bank';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}