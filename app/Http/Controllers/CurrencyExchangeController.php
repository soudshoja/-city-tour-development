<?php

namespace App\Http\Controllers;

use App\Models\CurrencyExchange;
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
            'exchange_rate.*' => 'required',
        ]);

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
