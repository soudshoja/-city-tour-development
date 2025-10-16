<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IataEasyPayService
{
protected $baseUrl;
    protected $tokenUrl;
    protected $clientId;
    protected $clientSecret;

    public function __construct(string $clientId, string $clientSecret)
    {
        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Missing IATA API credentials (Client ID or Secret).');
        }

        $this->baseUrl = config('services.iata.base_url');
        $this->tokenUrl = config('services.iata.token_url');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Get OAuth2 token from Microsoft Identity endpoint (cached for 58 mins)
     */
    private function getAccessToken()
    {
        $key = 'iata_access_token_' . $this->clientId;
        $ttl = 3500; // ~58 minutes

        return Cache::remember($key, $ttl, function () {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to get access token: ' . $response->body());
            }

            $token = $response->json('access_token');

            if (!$token) {
                throw new \Exception('Access token missing from response.');
            }

            return $token;
        });
    }

    /**
     * Get wallet balance for a specific IATA code and optional currency
     */
    public function getWalletBalanceByCompany($iataCode, $currency = null)
    {
        if (empty($iataCode)) {
            throw new \Exception('No IATA code found. Please add your IATA code in the company profile.');
        }

        $token = $this->getAccessToken();

        $query = ['iataCode' => $iataCode];
        if ($currency) {
            $query['currency'] = $currency;
        }

        $response = Http::withToken($token)
            ->get($this->baseUrl . '/iep-experience/easypay_integration/v1/wallet-balances', $query);

        if ($response->failed()) {
            throw new \Exception('Failed to get wallet balance: ' . $response->body());
        }

        return $response->json();
    }
}
