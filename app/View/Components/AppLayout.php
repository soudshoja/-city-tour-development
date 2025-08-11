<?php

namespace App\View\Components;

use App\Models\Currency;
use App\Models\CurrencyExchange;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $user = auth()->user();
        $color = null;

        if($user->hasRole('admin')){
            $color = 'bg-koromiko-300';
        } elseif($user->hasRole('company')) {
            $color = 'bg-blue-500';
        } elseif($user->hasRole('branch')) {
            $color = 'bg-brown-500';
        } elseif($user->hasRole('agent')) {
            $color = 'bg-purple-500';
        }

        $currencyExchange = $this->currencySidebar();

        return view('components.layouts.app', [
            'color' => $color,
            'allIso' => $currencyExchange['all_iso'],
            'base' => $currencyExchange['base'],
            'exchange' => $currencyExchange['exchange'],
            'currencies' => $currencyExchange['currencies']
        ]);
    }

    public function currencySidebar()
    {
        $base = CurrencyExchange::pluck('base_currency')
            ->filter()
            ->map(fn($c) => strtoupper($c))
            ->unique()
            ->values();

        $exchange = CurrencyExchange::pluck('exchange_currency')
            ->filter()
            ->map(fn($c) => strtoupper($c))
            ->unique()
            ->values();

        $allIso = $base->merge($exchange)
            ->unique()
            ->sort()
            ->values();

        $currencies = Currency::whereIn('iso_code', $allIso)
            ->get(['iso_code', 'name', 'symbol'])
            ->keyBy('iso_code');

        // return view('layouts.sidebar', compact('base', 'exchange', 'allIso', 'currencies'));
        return [
            'base' => $base,
            'exchange' => $exchange,
            'all_iso' => $allIso,
            'currencies' => $currencies
        ];
    }
}
