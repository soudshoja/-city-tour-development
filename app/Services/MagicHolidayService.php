<?php

namespace App\Services;

use App\Models\SupplierCredential;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MagicHolidayService
{
    protected $baseUrl;
    protected $tokenUrl;
    protected $clientId;
    protected $clientSecret;
    protected $logger;

    public function __construct($companyId = null)
    {
        $this->baseUrl = config('services.magic-holiday.url');
        $this->tokenUrl = config('services.magic-holiday.token-url');
        $this->clientId = config('services.magic-holiday.client-id');
        $this->clientSecret = config('services.magic-holiday.client-secret');
        $this->logger = Log::channel('magic_holidays');

        if($companyId){
            $supplierCredential = SupplierCredential::where('company_id', $companyId)
                ->whereHas('supplier', function($query) {
                    $query->where('name', 'Magic Holiday');
                })
                ->first();

            if($supplierCredential){
                $this->clientId = $supplierCredential->client_id;
                $this->clientSecret = $supplierCredential->client_secret;
            }
        }
    }

    protected function getAccessToken(array $scopes = [])
    {
        return Cache::remember('magic_holiday_access_token', 10, function () use ($scopes) {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $scopes,
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            $this->logger->error('Failed to retrieve access token from Magic Holiday API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception('Unable to retrieve access token.');
        });
    }

    protected function request($scopes = [], $method, $endpoint, $params = [], $payload = [])
    {
        $token = $this->getAccessToken($scopes);

        $response = Http::withToken($token)
            ->{$method}($this->baseUrl . $endpoint, $method === 'get' ? $params : $payload);

        if ($response->successful()) {
            return [
                'status' => $response->status(),
                'data' => $response->json(),
                'headers' => $response->headers(),
            ];
        }

        throw new Exception("API request failed: " . $response->body());
    }

    public function getSingleReservation(string $ref){

        $key = 'magic_single_reservation';
        $url = '/reservationsApi/v1/reservations/' . $ref;
        $scopes = ['read:reservations'];

        if(Cache::has($key)){
            $rateLimitReset = Cache::get($key);

            $this->logger->info('Rate limit reset time: ' . date('Y-m-d H:i:s', $rateLimitReset));

            if($rateLimitReset !== null){
               $this->logger->info('Waiting for rate limit reset at: ' . date('Y-m-d H:i:s', $rateLimitReset));
               
               // Calculate wait time using current timestamps (not UTC conversion due to API issues)
               $currentTimestamp = time();
               $waitTime = max(0, $rateLimitReset - $currentTimestamp);

               $this->logger->info('Current timestamp: ' . $currentTimestamp . ', Reset timestamp: ' . $rateLimitReset . ', Wait time: ' . $waitTime . ' seconds');

               if($waitTime > 0){
                   $this->logger->info('Sleeping for ' . $waitTime . ' seconds due to Magic Holiday API rate limit...');
                   sleep($waitTime);
               } else {
                   $this->logger->info('No wait needed - reset time has passed');
               }

               $this->logger->info('Resuming requests after rate limit reset.');
               Cache::forget($key);
            }
        }

        $this->applyRequestSpacing();

        $response = $this->request($scopes,'get', $url);

        if(!isset($response['status']) ?? $response['status'] !== 200){
            $this->logger->error('Failed to fetch single reservation from Magic Holiday API', [
                'status' => $response['status'],
                'body' => $response,
            ]);

            return $response;
        }

        if(isset($response['headers'])){
            $rateLimitRemaining = $response['headers']['X-RateLimit-Remaining'][0] ?? null;
            $rateLimitReset = $response['headers']['X-RateLimit-Reset'][0] ?? null;

            $this->logger->info('Magic Holiday API Rate Limit Remaining: ' . $rateLimitRemaining);

            $this->updateRateLimitInfo($rateLimitRemaining, $rateLimitReset);

            if($rateLimitRemaining !== null && $rateLimitRemaining <= 5 && $rateLimitReset !== null){
                $this->logger->warning('Rate limit reached for Magic Holiday API. Next reset at: ' . date('Y-m-d H:i:s', $rateLimitReset));
                $this->logger->info('Raw reset timestamp: ' . $rateLimitReset . ', Current timestamp: ' . time());
                
                // Magic Holiday API has broken reset times, use fixed delay based on remaining requests
                $delaySeconds = match(true) {
                    $rateLimitRemaining <= 1 => 60,  
                    $rateLimitRemaining <= 3 => 30,  
                    default => 10                    
                };
                
                $this->logger->warning("API rate limit broken. Using fixed delay of {$delaySeconds} seconds instead.");
                Cache::put($key, time() + $delaySeconds, Carbon::now()->addMinutes(2));
            }
        }

        return $response;
    }

    protected function mapping(string $mapping, array $params = [])
    {
        if(!isset($params['perPage'])) {
            $params['perPage'] = 100; // Default perPage value
        }

        $this->applyRequestSpacing();

        $response = $this->request(['read:mapping'],'get', '/mapping/v1' . $mapping, $params);

        if (isset($response['headers'])) {
            $rateLimitRemaining = $response['headers']['X-RateLimit-Remaining'][0] ?? null;
            $rateLimitReset = $response['headers']['X-RateLimit-Reset'][0] ?? null;
            $this->updateRateLimitInfo($rateLimitRemaining, $rateLimitReset);
        }

        Log::channel('mapping')->info('Mapping API response', [
            'endpoint' => $mapping,
            'params' => $params,
            'response' => $response
        ]);

        return $response;
    }

    public function getCountries(array $params = [])
    {
        return $this->mapping('/countries', $params);
    }

    public function getCountryDetails(string $countryId)
    {
        return $this->mapping('/countries/' .$countryId);
    }

    public function getNationalities(array $params = [])
    {
        $response =  $this->mapping('/nationalities' , $params);
    }

    public function getNationalitiesDetails(string $nationalityId)
    {
        return $this->mapping('/nationalities/' . $nationalityId);
    }

    public function getCities(array $params = [])
    {
        return $this->mapping('/cities', $params);
    }

    public function getCityDetails(string $cityId)
    {
        return $this->mapping('/cities/' . $cityId);
    }

    public function getHotels(int $cityId, int $page = 1, int $perPage = 100)
    {
        return $this->mapping('/hotels', ['cityId' => $cityId, 'perPage' => $perPage, 'page' => $page]);
    }

    public function getHotelImages(int $hotelId)
    {
        return $this->mapping('/hotels/' . $hotelId . '/mainImage');
    }

    public function getHotelDescriptions(string $hotelId, string $language = 'en')
    {
        return $this->mapping('/hotels/' . $hotelId . '/descriptions', ['language' => $language]);
    }


    public function searchHotels(array $params)
    {
        return $this->mapping('/hotels/search', $params);
    }

    // Example method: Get Hotel Details
    public function getHotelDetails(string $hotelId)
    {
        return $this->mapping('/hotels/' . $hotelId);
    }

    // Example method: Book Hotel
    public function bookHotel(array $bookingData)
    {
        return $this->request('post', '/bookings', [], $bookingData);
    }

    protected function applyRequestSpacing(): void
    {
        $rateLimitInfo = Cache::get('magic_rate_limit_info');
        
        if (!$rateLimitInfo) {
            sleep(2);
            return;
        }

        $remaining = $rateLimitInfo['remaining'] ?? null;
        $resetTimestamp = $rateLimitInfo['reset_timestamp'] ?? null;
        $lastUpdated = $rateLimitInfo['updated_at'] ?? null;

        if ($lastUpdated && (time() - $lastUpdated) > 300) {
            sleep(2);
            return;
        }

        if ($remaining === null || $resetTimestamp === null) {
            sleep(2);
            return;
        }

        $currentTime = time();
        $timeUntilReset = max(0, $resetTimestamp - $currentTime);

        $optimalDelay = $this->calculateOptimalDelay($remaining, $timeUntilReset);

        if ($optimalDelay > 0) {
            $this->logger->info("Applying request spacing: {$optimalDelay} seconds (remaining: {$remaining}, time until reset: {$timeUntilReset}s)");
            sleep($optimalDelay);
        }
    }

    protected function calculateOptimalDelay(int $remaining, int $timeUntilReset): int
    {
        if ($timeUntilReset <= 5) {
            return 0;
        }

        if ($remaining > 20) {
            return 1; // Just 1 second to be respectful
        }

        if ($remaining > 0) {
            $calculatedDelay = intval($timeUntilReset / $remaining);
            
            $maxDelay = match(true) {
                $remaining <= 2 => 120,  // Max 2 minutes when very low
                $remaining <= 5 => 60,   // Max 1 minute when low
                $remaining <= 10 => 30,  // Max 30 seconds when moderate
                default => 10            // Max 10 seconds when comfortable
            };

            $optimalDelay = min($calculatedDelay, $maxDelay);
            
            $minDelay = match(true) {
                $remaining <= 2 => 10,   // At least 10 seconds when very low
                $remaining <= 5 => 5,    // At least 5 seconds when low
                default => 1             // At least 1 second otherwise
            };

            return max($optimalDelay, $minDelay);
        }

        return 60;
    }

    protected function updateRateLimitInfo(?int $remaining, ?int $resetTimestamp): void
    {
        if ($remaining === null || $resetTimestamp === null) {
            return;
        }

        $rateLimitInfo = [
            'remaining' => (int)$remaining,
            'reset_timestamp' => (int)$resetTimestamp,
            'updated_at' => time(),
        ];

        $ttl = min(600, max(60, $resetTimestamp - time() + 60));
        
        Cache::put('magic_rate_limit_info', $rateLimitInfo, $ttl);
        
        $this->logger->debug('Updated rate limit info', $rateLimitInfo);
    }
}
