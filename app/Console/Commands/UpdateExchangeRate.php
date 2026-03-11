<?php

namespace App\Console\Commands;

use App\Http\Controllers\SystemExchangeRateController;
use App\Models\SystemExchangeRate;
use Illuminate\Console\Command;

class UpdateExchangeRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-exchange-rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rate from API daily';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $systemCurrencies = SystemExchangeRate::select('base_currency')->distinct()->get();

        foreach( $systemCurrencies as $systemCurrency ) {
            $exchangeRate = new SystemExchangeRateController();
            $response = $exchangeRate->latest([
                'base_currency' => $systemCurrency->base_currency,
            ]);

            if(!isset($response['data']) || $response['data'] == []) return false;

            $currenciesFromApi = $response['data'];

            foreach($currenciesFromApi as $currency){
                $exchangeRate = $currency['value'];
                SystemExchangeRate::updateOrCreate([
                    'base_currency' => $systemCurrency->base_currency,
                    'exchange_currency' => $currency['currency'],
                ], [
                    'exchange_rate' => $exchangeRate,
                ]);
            }
        }
    }

    // $exchangeRate = $currencyFromApi[strtoupper($systemCurrency->exchange_rate)]['value'];
    // SystemExchangeRate::where('base_currency', $systemCurrency->base_currency)
    //     ->update(['exchange_rate' => $exchangeRate]);
    
    // $exchangeRate = $exchangeRate['rates'][$systemCurrency->base_currency];
    // SystemExchangeRate::where('base_currency', $systemCurrency->base_currency)
    //     ->update(['exchange_rate' => $exchangeRate]);
}
