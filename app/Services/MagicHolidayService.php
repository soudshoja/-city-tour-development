<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MagicHolidayService
{
    protected $baseUrl;
    protected $tokenUrl;
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.magic-holiday.url');
        $this->tokenUrl = config('services.magic-holiday.token-url');
        $this->clientId = config('services.magic-holiday.client-id');
        $this->clientSecret = config('services.magic-holiday.client-secret');
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

            throw new \Exception('Unable to retrieve access token.');
        });
    }

    protected function request($scopes = [], $method, $endpoint, $params = [], $payload = [])
    {
        $token = $this->getAccessToken($scopes);

        $response = Http::withToken($token)
            ->{$method}($this->baseUrl . $endpoint, $method === 'get' ? $params : $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception("API request failed: " . $response->body());
    }

    protected function mapping(string $mapping, array $params = [])
    {
        return $this->request(['read:mapping'],'get', '/mapping/v1' . $mapping, $params);
    }

    public function getCountries()
    {
        return $this->mapping('/countries');
    }

    public function getCountryDetails(string $countryId)
    {
        return $this->mapping('/countries/' .$countryId);
    }

    public function getNationalities()
    {
        return $this->mapping('/nationalities');
    }

    public function getNationalitiesDetails(string $nationalityId)
    {
        return $this->mapping('/nationalities/' . $nationalityId);
    }

    public function getCities()
    {
        return $this->mapping('/cities');
    }

    public function getCityDetails(string $cityId)
    {
        return $this->mapping('/cities/' . $cityId);
    }

    public function getHotels(int $cityId, int $page = 1, int $perPage = 100)
    {
        return $this->mapping('/hotels', ['cityId' => $cityId, 'perPage' => $perPage, 'page' => $page]);
    }

    public function getHotelImages(string $hotelId)
    {
        return $this->mapping('/hotels/' . $hotelId . '/images');
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


    // Add more methods corresponding to other API endpoints as needed
}
