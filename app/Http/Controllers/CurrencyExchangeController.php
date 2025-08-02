<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CurrencyExchange;
use App\Models\SystemExchangeRate;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\ExchangeRateHistory;

class CurrencyExchangeController extends Controller
{
    public function index()
    {
        $currencyExchanges = CurrencyExchange::orderBy('company_id', 'asc')->get();
        $companies = Company::select('id', 'name')->get();

        if (auth()->user()->hasRole('company')) {
            if (auth()->user()->company == null) {

                logger()->error('User company not found', ['user_id' => auth()->id()]);
                return redirect()->back()->with('error', 'Something went wrong');
            }

            $companies = array_filter($companies->toArray(), function ($company) {
                return $company['id'] == auth()->user()->company->id;
            });
        }

        $currenciesAvailable = cache()->remember('exchangeRates', 3600, function () {
            $systemExchangeRateController = new SystemExchangeRateController();
            $response = $systemExchangeRateController->currencies();

            if (!isset($response['data']) || $response['data'] == null) {
                throw new Exception('Failed to fetch currency exchange rates');
            }

            return $response['data'];
        });

        $currenciesAvailable = array_filter($currenciesAvailable, function ($currency) {
            return in_array($currency['code'], ['USD', 'SAR', 'QAR', 'GBP', 'AED', 'EUR', 'EGP', 'BHD', 'KWD', 'LKR', 'JOD', 'INR']);
        });

        if (!$currenciesAvailable) {
            return redirect()->back()->with('error', 'Failed to fetch currency exchange rates');
        }

        $user = Auth::user();
        if ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        }

        return view('currency-exchange.index', compact(
            'currencyExchanges',
            'currenciesAvailable',
            'companies'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'base_currency' => 'required',
            'exchange_currency' => 'required',
            'exchange_rate' => 'required_if:is_manual,1',
            'is_manual' => 'required'
        ]);

        $currencyExchange = CurrencyExchange::where([
            'company_id' => $request->company_id,
            'base_currency' => $request->base_currency,
            'exchange_currency' => $request->exchange_currency
        ])->first();

        if ($currencyExchange) {
            return redirect()->back()->with('error', 'Currency exchange rate already exists');
        }

        if ($request->is_manual == 0) {
            $systemExchangeRate = SystemExchangeRate::where([
                'base_currency' => $request->base_currency,
                'exchange_currency' => $request->exchange_currency
            ])->first();

            if (!$systemExchangeRate) {
                $systemExchangeRateController = new SystemExchangeRateController();
                $response = $systemExchangeRateController->updateBaseRate($request->base_currency);

                if ($response->status() !== 200) {
                    return redirect()->back()->with('error', 'Failed to update currency exchange rate');
                }

                $systemExchangeRate = SystemExchangeRate::where([
                    'base_currency' => $request->base_currency,
                    'exchange_currency' => $request->exchange_currency
                ])->first();
            }

            $request->merge(['exchange_rate' => $systemExchangeRate->exchange_rate]);
        }

        try {
            CurrencyExchange::create($request->all());
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Currency exchange rate added successfully');
    }

    public function updateAuto(Request $request)
    {
        $request->validate([
            'id.*' => 'required',
        ]);

        $currencyExchange = CurrencyExchange::find($request->id);

        $systemExchangeRate = SystemExchangeRate::where([
            'base_currency' => $currencyExchange->base_currency,
            'exchange_currency' => $currencyExchange->exchange_currency
        ])->first();

        if (!$systemExchangeRate) {

            $systemExchangeRateController = new SystemExchangeRateController();
            $response = $systemExchangeRateController->updateBaseRate($currencyExchange->base_currency);

            if ($response->status() !== 200) {
                return response()->json(['message' => 'Failed to update currency exchange rate'], 500);
            }

            $systemExchangeRate = SystemExchangeRate::where([
                'base_currency' => $currencyExchange->base_currency,
                'exchange_currency' => $currencyExchange->exchange_currency
            ])->first();
        }

        try {
            $currencyExchange->exchange_rate = $systemExchangeRate->exchange_rate;
            $currencyExchange->updated_at = now();
            $currencyExchange->save();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Currency exchange rate updated successfully'], 200);
    }

    public function updateManual(Request $request)
    {
        $data = $request->all();

        foreach ($data as $rateData) {
            $exchange = CurrencyExchange::findOrFail($rateData['id']);
            $oldRate = $exchange->exchange_rate;
            $exchange->exchange_rate = $rateData['exchange_rate'];
            $exchange->is_manual = true;
            $exchange->save();

            ExchangeRateHistory::create([
                'currency_exchange_id' => $exchange->id,
                'base_currency' => $exchange->base_currency,
                'exchange_currency' => $exchange->exchange_currency,
                'old_rate' => $oldRate,
                'new_rate' => $rateData['exchange_rate'],
                'method' => 'manual',
                'changed_by' => Auth::id(),
                'changed_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Exchange rates updated and history recorded.']);
    }

    public function updateMethod($id)
    {
        try {
            $currencyExchange = CurrencyExchange::find($id);
            $currencyExchange->is_manual = !$currencyExchange->is_manual;
            $currencyExchange->save();
        } catch (Exception $e) {

            logger()->error('Failed to update currency exchange rate method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something Went Wrong'], 500);
        }

        return response()->json([
            'currencyExchange' => $currencyExchange,
            'message' => 'Currency exchange rate method updated successfully'
        ], 200);
    }

    public function histories()
    {
        return $this->hasMany(ExchangeRateHistory::class);
    }

    public function allHistories()
    {
        $currencyExchanges = \App\Models\CurrencyExchange::with(['company', 'histories.user'])
            ->orderBy('base_currency')
            ->get();

        return view('currency-exchange.all-histories', compact('currencyExchanges'));
    }
}
