<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyExchangeTrait;
use App\Models\Company;
use App\Models\CurrencyExchange;
use App\Models\SystemExchangeRate;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\ExchangeRateHistory;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Currency;

class CurrencyExchangeController extends Controller
{
     use CurrencyExchangeTrait {
        convert as convertCurrencies; // alias the TRAIT method
    }
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
            return in_array($currency['code'], [
                'USD',
                'SAR',
                'QAR',
                'GBP',
                'AED',
                'EUR',
                'EGP',
                'BHD',
                'KWD',
                'LKR',
                'JOD',
                'INR',
                'MAD',
                'CNY',
                'SGD',
                'IDR',
                'NPR',
            ]);
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

    public function storeProcess(Request $request) : JsonResponse
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
            return response()->json([
                'status' => 'error',
                'message' => 'Currency exchange rate already exists'
            ]);
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
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to update currency exchange rate'
                    ]);
                    /* return redirect()->back()->with('error', 'Failed to update currency exchange rate'); */                
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
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            }

        return response()->json([
            'status' => 'success',
            'message' => 'Currency exchange rate added successfully'
        ]);
    }

    public function exchangeSidebar() : JsonResponse
    {
        // check what is the base currency
        // check exchange currecny

        
        //if exchange rate doesn't exist 
        $response = $this->storeProcess($request);

        // if exchange rate exist , get the exchange rate

        // return exchange rate

        return response()->json([
            'status' => $response['status'],
            'message' => $response['message']
        ]);
    }

    public function store(Request $request) : RedirectResponse
    {
       $response = $this->storeProcess($request)->getData(true);
    
       return redirect()->back()->with($response['status'], $response['message']);
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

    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'    => ['nullable', 'integer'],
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency'   => ['required', 'string', 'size:3'],
            'amount'        => ['required', 'numeric'],
        ]);

        $user = Auth::user();
        $companyId = $data['company_id']
            ?? Company::where('user_id', $user->id)->value('id')
            ?? 1;

        $from = strtoupper($data['from_currency']);
        $to   = strtoupper($data['to_currency']);
        $amt  = (float) $data['amount'];

        Log::info('Exchange Rate Conversion made by Company ', [
            'company_id' => $companyId,
            'user_id'    => $user->id
        ]);

        if ($from === $to) {
            return response()->json([
                'ok'               => true,
                'exchange_rate'    => 1,
                'converted_amount' => $amt,
                'inverse_rate'     => 1,
            ]);
        }

        // Forward rate
        $forward = CurrencyExchange::where('company_id', $companyId)
            ->where('base_currency', $from)
            ->where('exchange_currency', $to)
            ->first();

        if (!$forward) {
            return response()->json([
                'ok'      => false,
                'message' => "No rate found for {$from} → {$to}.",
            ], 422);
        }

        $rate      = (float) $forward->exchange_rate;
        $converted = round($amt * $rate, 6);

        // Inverse rate
        $inverseRow = CurrencyExchange::where('company_id', $companyId)
            ->where('base_currency', $to)
            ->where('exchange_currency', $from)
            ->first();

        if (!$inverseRow) {
            return response()->json([
                'ok'      => false,
                'message' => "No inverse rate found for {$to} → {$from}.",
            ], 422);
        }

        $inverse = (float) $inverseRow->exchange_rate;

        Log::info('Result:', [
            'From Currency' => $from,
            'To Currency'   => $to,
            'Result'        => [
                'exchange_rate'    => $rate,
                'converted_amount' => $converted
            ],
            'Inverse Rate'  => $inverse,
        ]);

        return response()->json([
            'ok'               => true,
            'exchange_rate'    => $rate,
            'converted_amount' => $converted,
            'inverse_rate'     => $inverse,
        ]);
    }
}
