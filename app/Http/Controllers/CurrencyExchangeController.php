<?php

namespace App\Http\Controllers;

use App\Models\CurrencyExchange;
use Illuminate\Http\Request;

class CurrencyExchangeController extends Controller
{
    public function index()
    {
        $currencyExchanges = CurrencyExchange::all();

        return view('currency-exchange.index', compact('currencyExchanges'));
    }
}
