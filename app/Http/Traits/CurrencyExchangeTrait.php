<?php

namespace App\Http\Traits;

use App\Models\CurrencyExchange;
use App\Models\ExchangeRateHistory;

trait CurrencyExchangeTrait{

    public function convert(int $companyId, string $fromCurrency, string $toCurrency, float $amount): array
    {
        $exchangeRate = 1;

        if ($fromCurrency !== $toCurrency) {
            $exchangeRate = $this->getExchangeRate($companyId, $fromCurrency, $toCurrency);
        }

        if ($exchangeRate === null) {
            return [
                'status' => 'error',
                'message' => 'Exchange rate not found for the specified currencies.',
                'exchange_rate' => null,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Exchange rate retrieved successfully.',
            'exchange_rate' => $exchangeRate,
            'converted_amount' => round($amount * $exchangeRate, 3),
        ];
    }

    public function getExchangeRate(int $companyId, string $fromCurrency, string $toCurrency): ?float
    {
        $exchangeRate = CurrencyExchange::where('base_currency', $fromCurrency)
            ->where('exchange_currency', $toCurrency)
            ->where('company_id', $companyId)
            ->latest()
            ->first();

        if ($exchangeRate) {
            return $exchangeRate->exchange_rate;
        }    
     
        return null;
    }
}