<?php

namespace App\Http\Controllers;

use App\Models\CurrencyExchange;
use App\Models\SystemExchangeRate;
use Exception;
use Illuminate\Http\Request;

class CurrencyExchangeController extends Controller
{
    public function index()
    {
        $currencyExchanges = CurrencyExchange::all();

        return view('currency-exchange.index', compact('currencyExchanges'));
    }

    public function update(Request $request){

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
