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
use Illuminate\Support\Facades\Auth;
use Throwable;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $color = match ($user->role_id) {
            Role::ADMIN => 'border-koromiko-300',
            Role::COMPANY => 'border-blue-500',
            Role::BRANCH => 'border-brown-500',
            Role::AGENT => 'border-purple-500',
            Role::ACCOUNTANT => 'border-red-500',
            default => 'border-gray-500',
        };

        $currencyExchange = $this->currencySidebar();

        // $walletData = $this->getCompanyWallets($user->company);
        // extract($walletData);

        $companyName = $companyId ? Company::find($companyId)->name : env('APP_NAME', 'City Tour');

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
