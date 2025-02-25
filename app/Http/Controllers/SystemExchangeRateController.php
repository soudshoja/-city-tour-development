<?php

namespace App\Http\Controllers;

use App\Models\SystemExchangeRate;
use Exception;
use Illuminate\Http\Request;

class SystemExchangeRateController extends Controller
{
    protected string $apiKey;
    protected array $settings;
    protected string $url;
    protected int $timeout;

    public function __construct( ?array $settings = [])
    {
        $this->apiKey = config('services.currency-api.key');
        $this->settings = $settings;
        $this->url = config('services.currency-api.url');
        $this->timeout = config('services.currency-api.timeout', 60);    
    }

    /**
     * @throws CurrencyApiException
     */
    private function call(string $endpoint, ?array $query = [])
    {
        $url = $this->url . '/' . $endpoint;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($this->apiKey));
        curl_setopt($ch, CURLOPT_CAINFO, base_path('cacert.pem'));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new CurrencyApiException(curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new CurrencyApiException("HTTP error code: $httpCode");
        }

        return json_decode($response, true);
    }

    /**
     * @throws CurrencyApiException
     */
    public function status()
    {
        return $this->call('status');
    }

    /**
     * @throws CurrencyApiException
     */
    public function currencies(?array $query = [])
    {
        return $this->call('currencies', $query);
    }

    /**
     * @throws CurrencyApiException
     */
    public function latest(?array $query = [])
    {
        return $this->call('latest', $query);
    }

    /**
     * @throws CurrencyApiException
     */
    public function historical($query)
    {
        return $this->call('historical', $query);
    }

    /**
     * @throws CurrencyApiException
     */
    public function convert($query)
    {
        return $this->call('convert', $query);
    }

    /**
     * @throws CurrencyApiException
     */
    public function range($query)
    {
        return $this->call('range', $query);
    }

    /**
     * Build headers for API request.
     * @return array Headers for API request.
     */
    private function buildHeaders($apiKey)
    {
        return [
            'Accept:' . 'application/json',
            'Content-Type:' . 'application/json',
            'apiKey:' . $apiKey,
        ];
    }

    public function index()
    {
        $exchangeRates = SystemExchangeRate::all();
        return view('exchange-rates.index', compact('exchangeRates'));
    }

    public function updateExchangeRate()
    {
        $systemCurrencies = SystemExchangeRate::select('base_currency')->distinct()->get();

        foreach( $systemCurrencies as $systemCurrency ) {
            // dump($systemCurrency);
            $exchangeRate = new SystemExchangeRateController();
            $response = $exchangeRate->latest([
                'base_currency' => $systemCurrency->base_currency,
            ]);

            if(!isset($response['data']) || $response['data'] == []) return false;

            $currenciesFromApi = $response['data'];

            foreach($currenciesFromApi as $currency){
                
                SystemExchangeRate::updateOrCreate([
                    'base_currency' => $systemCurrency->base_currency,
                    'exchange_currency' => $currency['code'],
                ], [
                    'exchange_rate' =>  $currency['value']
                ]);
            }
        }

        return redirect()->back()->with('success', 'Exchange rate updated successfully');
    }

    public function updateBaseRate(string $baseCurrency )
    {
        $response = $this->latest([
            'base_currency' => $baseCurrency,
        ]);

        if(!isset($response['data']) || $response['data'] == []) 
            return response()->json(['message' => 'No data found'], 404);

        $currenciesFromApi = $response['data'];

        foreach($currenciesFromApi as $currency){
            
            SystemExchangeRate::updateOrCreate([
                'base_currency' => $baseCurrency,
                'exchange_currency' => $currency['code'],
            ], [
                'exchange_rate' =>  $currency['value']
            ]);
        }

        return response()->json(['message' => 'Exchange rate updated successfully'], 200);
    }
}

class CurrencyApiException extends Exception
{
}   
