<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CurrencyExchange;
use App\Models\SystemExchangeRate;
use Exception;
use Illuminate\Http\Request;

class CurrencyExchangeController extends Controller
{
    public function index()
    {
        $currencyExchanges = CurrencyExchange::orderBy('company_id', 'asc')->get();
        $companies = Company::select('id', 'name')->get();

        $currenciesAvailable = cache()->remember('exchangeRates', 3600, function () {
            $systemExchangeRateController = new SystemExchangeRateController();
            $response = $systemExchangeRateController->currencies();

            if (!isset($response['data']) || $response['data'] == null) {
                throw new Exception('Failed to fetch currency exchange rates');
            }

            return $response['data'];
        });

        if (!$currenciesAvailable) {
            return redirect()->back()->with('error', 'Failed to fetch currency exchange rates');
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
            'base_currency' => $request->base_currency,
            'exchange_currency' => $request->exchange_currency
        ])->first();
        
        if($currencyExchange){
            return redirect()->back()->with('error', 'Currency exchange rate already exists');
        }

        if($request->is_manual == 0){
            $systemExchangeRate = SystemExchangeRate::where([
                'base_currency' => $request->base_currency,
                'exchange_currency' => $request->exchange_currency
            ])->first();
            
            if(!$systemExchangeRate){
                $systemExchangeRateController = new SystemExchangeRateController();
                $response = $systemExchangeRateController->updateBaseRate($request->base_currency);
                
                if($response->status() !== 200){
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

    public function update(Request $request)
    {
        $request->validate([
            'id.*' => 'required',
            'exchange_rate.*' => 'required_if:is_auto,0',
            'is_auto' => 'required'
        ]);
        
        if($request->is_auto == 1){
            
            $currencyExchange = CurrencyExchange::find($request->id);
            
            $systemExchangeRate = SystemExchangeRate::where([
                'base_currency' => $currencyExchange->base_currency,
                'exchange_currency' => $currencyExchange->exchange_currency
            ])->first();
            
            if(!$systemExchangeRate){
                
                $systemExchangeRateController = new SystemExchangeRateController();
                $response = $systemExchangeRateController->updateBaseRate($currencyExchange->base_currency);
                
                if($response->status() !== 200){
                    return response()->json(['message' => 'Failed to update currency exchange rate'], 500);
                }

                $systemExchangeRate = SystemExchangeRate::where([
                    'base_currency' => $currencyExchange->base_currency,
                    'exchange_currency' => $currencyExchange->exchange_currency
                ])->first();
            }

            try {
                $currencyExchange->exchange_rate = $systemExchangeRate->exchange_rate;
                $currencyExchange->is_manual = false;
                $currencyExchange->updated_at = now();
                $currencyExchange->save();
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            } 

            return response()->json(['message' => 'Currency exchange rate updated successfully'], 200);
        }

        foreach($request->all() as $exchange){
            try {
                $currencyExchange = CurrencyExchange::find($exchange['id']);
                $currencyExchange->exchange_rate = $exchange['exchange_rate'];
                $currencyExchange->updated_at = now();
                $currencyExchange->save();
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Currency exchange rate updated successfully'], 200);
    }

    
}
