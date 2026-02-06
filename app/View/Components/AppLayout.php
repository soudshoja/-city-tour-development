<?php

namespace App\View\Components;

use App\Models\Company;
use App\Models\Currency;
use App\Models\CurrencyExchange;
use App\Models\Role;
use App\Services\IataEasyPayService;
use Exception;
use Illuminate\View\Component;
use Illuminate\View\View;
use Throwable;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $user = auth()->user();
        $color = null;
        $companyId = null;

        if($user->role_id == Role::ADMIN) {
            $color = 'border-koromiko-300';
        } elseif($user->role_id == Role::COMPANY) {
            $color = 'border-blue-500';
            $companyId = $user->company->id;
        } elseif($user->role_id == Role::BRANCH) {
            $color = 'border-brown-500';
            $companyId = $user->branch->company->id;
        } elseif($user->role_id == Role::AGENT) {
            $color = 'border-purple-500';
            $companyId = $user->agent->branch->company->id;
        } elseif($user->role_id == Role::ACCOUNTANT) {
            $color = 'border-red-500';
            $companyId = $user->accountant->branch->company->id;
        } else {
            $color = 'border-gray-500';
            $companyId = 1;
        }

        $currencyExchange = $this->currencySidebar();

        // $walletData = $this->getCompanyWallets($user->company);
        // extract($walletData);

        $companyName = Company::find($companyId)?->name ?? env('APP_NAME', 'CityTour');

        return view('components.layouts.app', [
            'color' => $color,
            'allIso' => $currencyExchange['all_iso'],
            'base' => $currencyExchange['base'],
            'exchange' => $currencyExchange['exchange'],
            'currencies' => $currencyExchange['currencies'],
            'companyId' => $companyId,
            'companyName' => $companyName,
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

    private function getCompanyWallets(Company $company)
    {
        $wallets = collect();
        $iataBalance = 0;
        $walletName = 'N/A';
        $error = null;

        try {
            if (!$company || !$company->iata_code || !$company->iata_client_id || !$company->iata_client_secret) {
                throw new Exception('Missing IATA credentials. Please update your company profile with the IATA Code, Client ID, and Client Secret.');
            }

            $service = new IataEasyPayService(
                $company->iata_client_id,
                $company->iata_client_secret
            );

            $data = $service->getWalletBalanceByCompany($company->iata_code, 'KWD');
            $wallets = collect($data['wallets'] ?? [])->where('status', 'OPEN')->values();
            $iataBalance = $wallets->sum('balance');
            $walletName = $wallets->pluck('name')->join(', ');

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'wallets' => $wallets,
            'iataBalance' => $iataBalance,
            'iataWalletName' => $walletName,
            'iataErrorMessage' => $error,
        ];
    }
}
