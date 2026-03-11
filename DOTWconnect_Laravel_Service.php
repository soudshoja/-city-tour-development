<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * DOTWconnect Reference Data Service
 *
 * Handles all reference/lookup API calls to DOTWconnect
 * Manages caching of country, city, amenity, chain, and preference data
 *
 * @category Services
 * @package App\Services
 */
class DOTWconnectReferenceService
{
    /**
     * API Client instance
     */
    protected $client;

    /**
     * Cache expiration time (30 days in seconds)
     */
    protected int $cacheExpiry = 2592000;

    /**
     * Cache key prefix
     */
    protected string $cachePrefix = 'dotw_ref_';

    public function __construct()
    {
        // Initialize API client (inject from container)
        // $this->client = app('dotw.client');
    }

    /**
     * Initialize all reference data
     * Call this once at application startup or via scheduler
     *
     * @return array Status of each initialization
     */
    public function initializeAllReferences(): array
    {
        $results = [];

        try {
            $results['countries'] = $this->cacheCountries();
            Log::info('DOTWconnect: Countries cached successfully', ['count' => count($results['countries'])]);
        } catch (Exception $e) {
            Log::error('DOTWconnect: Failed to cache countries', ['error' => $e->getMessage()]);
            $results['countries'] = [];
        }

        try {
            $results['amenities'] = $this->cacheAmenities();
            Log::info('DOTWconnect: Amenities cached successfully', ['count' => count($results['amenities'])]);
        } catch (Exception $e) {
            Log::error('DOTWconnect: Failed to cache amenities', ['error' => $e->getMessage()]);
            $results['amenities'] = [];
        }

        try {
            $results['chains'] = $this->cacheChains();
            Log::info('DOTWconnect: Chains cached successfully', ['count' => count($results['chains'])]);
        } catch (Exception $e) {
            Log::error('DOTWconnect: Failed to cache chains', ['error' => $e->getMessage()]);
            $results['chains'] = [];
        }

        try {
            $results['classifications'] = $this->cacheClassifications();
            Log::info('DOTWconnect: Classifications cached successfully', ['count' => count($results['classifications'])]);
        } catch (Exception $e) {
            Log::error('DOTWconnect: Failed to cache classifications', ['error' => $e->getMessage()]);
            $results['classifications'] = [];
        }

        try {
            $results['leisure'] = $this->cacheLeisureTypes();
            Log::info('DOTWconnect: Leisure types cached successfully', ['count' => count($results['leisure'])]);
        } catch (Exception $e) {
            Log::error('DOTWconnect: Failed to cache leisure types', ['error' => $e->getMessage()]);
            $results['leisure'] = [];
        }

        try {
            $results['locations'] = $this->cacheLocations();
            Log::info('DOTWconnect: Locations cached successfully', ['count' => count($results['locations'])]);
        } catch (Exception $e) {
            Log::error('DOTWconnect: Failed to cache locations', ['error' => $e->getMessage()]);
            $results['locations'] = [];
        }

        return $results;
    }

    /**
     * Cache all countries with their codes and regions
     *
     * @return array Countries list
     */
    private function cacheCountries(): array
    {
        $response = $this->client->request('getallcountries', [
            'return' => [
                'fields' => [
                    'field' => ['regionName', 'regionCode']
                ]
            ]
        ]);

        $countries = $response['countries']['country'] ?? [];

        // Store full list
        Cache::put(
            $this->cachePrefix . 'countries',
            $countries,
            $this->cacheExpiry
        );

        // Store indexed by code for quick lookup
        $byCode = collect($countries)
            ->keyBy('code')
            ->toArray();
        Cache::put(
            $this->cachePrefix . 'countries_by_code',
            $byCode,
            $this->cacheExpiry
        );

        // Store indexed by name for reverse lookup
        $byName = collect($countries)
            ->keyBy('name')
            ->toArray();
        Cache::put(
            $this->cachePrefix . 'countries_by_name',
            $byName,
            $this->cacheExpiry
        );

        return $countries;
    }

    /**
     * Get all countries (from cache)
     *
     * @return array Countries list
     */
    public function getCountries(): array
    {
        return Cache::get($this->cachePrefix . 'countries', []);
    }

    /**
     * Get country by code
     *
     * @param string $code Country code
     * @return array|null Country data or null if not found
     */
    public function getCountryByCode(string $code): ?array
    {
        $countries = Cache::get($this->cachePrefix . 'countries_by_code', []);
        return $countries[$code] ?? null;
    }

    /**
     * Get country by name
     *
     * @param string $name Country name
     * @return array|null Country data or null if not found
     */
    public function getCountryByName(string $name): ?array
    {
        $countries = Cache::get($this->cachePrefix . 'countries_by_name', []);
        return $countries[$name] ?? null;
    }

    /**
     * Cache cities for a specific country
     *
     * @param string $countryCode Country code from getallcountries
     * @param array $filters Optional filters (topDeals, luxury, specialDeals)
     * @return array Cities list
     */
    public function cacheCitiesByCountry(string $countryCode, array $filters = []): array
    {
        $requestParams = [
            'return' => [
                'filters' => array_merge(['countryCode' => $countryCode], $filters),
                'fields' => [
                    'field' => ['name', 'code']
                ]
            ]
        ];

        $response = $this->client->request('getservingcities', $requestParams);
        $cities = $response['cities']['city'] ?? [];

        // Cache by country
        Cache::put(
            $this->cachePrefix . "cities_{$countryCode}",
            $cities,
            3600 // 1 hour cache for cities
        );

        // Index by code
        $byCode = collect($cities)
            ->keyBy('code')
            ->toArray();
        Cache::put(
            $this->cachePrefix . "cities_{$countryCode}_by_code",
            $byCode,
            3600
        );

        return $cities;
    }

    /**
     * Get cities for a country (from cache)
     *
     * @param string $countryCode Country code
     * @return array Cities list
     */
    public function getCitiesByCountry(string $countryCode): array
    {
        $cities = Cache::get($this->cachePrefix . "cities_{$countryCode}");

        if (is_null($cities)) {
            $cities = $this->cacheCitiesByCountry($countryCode);
        }

        return $cities;
    }

    /**
     * Get city by code
     *
     * @param string $countryCode Country code
     * @param string $cityCode City code
     * @return array|null City data or null if not found
     */
    public function getCityByCode(string $countryCode, string $cityCode): ?array
    {
        $cities = Cache::get($this->cachePrefix . "cities_{$countryCode}_by_code", []);
        return $cities[$cityCode] ?? null;
    }

    /**
     * Cache hotel amenities
     *
     * @return array Amenities list
     */
    private function cacheAmenities(): array
    {
        $response = $this->client->request('getamenitieids');

        $amenities = $response['amenities']['option'] ?? [];

        // Normalize to array of arrays if single result
        if (isset($amenities['runno']) && isset($amenities['value'])) {
            $amenities = [$amenities];
        }

        Cache::put(
            $this->cachePrefix . 'amenities',
            $amenities,
            $this->cacheExpiry
        );

        // Index by value code
        $byValue = collect($amenities)
            ->keyBy('value')
            ->toArray();
        Cache::put(
            $this->cachePrefix . 'amenities_by_value',
            $byValue,
            $this->cacheExpiry
        );

        return $amenities;
    }

    /**
     * Get all amenities (from cache)
     *
     * @return array Amenities list
     */
    public function getAmenities(): array
    {
        return Cache::get($this->cachePrefix . 'amenities', []);
    }

    /**
     * Get amenity by value code
     *
     * @param string $value Amenity code
     * @return array|null Amenity data or null if not found
     */
    public function getAmenityByValue(string $value): ?array
    {
        $amenities = Cache::get($this->cachePrefix . 'amenities_by_value', []);
        return $amenities[$value] ?? null;
    }

    /**
     * Cache hotel chains
     *
     * @return array Chains list
     */
    private function cacheChains(): array
    {
        $response = $this->client->request('getchainids');

        $chains = $response['chains']['option'] ?? [];

        // Normalize to array of arrays
        if (isset($chains['runno']) && isset($chains['value'])) {
            $chains = [$chains];
        }

        Cache::put(
            $this->cachePrefix . 'chains',
            $chains,
            $this->cacheExpiry
        );

        // Index by value code
        $byValue = collect($chains)
            ->keyBy('value')
            ->toArray();
        Cache::put(
            $this->cachePrefix . 'chains_by_value',
            $byValue,
            $this->cacheExpiry
        );

        return $chains;
    }

    /**
     * Get all hotel chains (from cache)
     *
     * @return array Chains list
     */
    public function getChains(): array
    {
        return Cache::get($this->cachePrefix . 'chains', []);
    }

    /**
     * Get chain by value code
     *
     * @param string $value Chain code
     * @return array|null Chain data or null if not found
     */
    public function getChainByValue(string $value): ?array
    {
        $chains = Cache::get($this->cachePrefix . 'chains_by_value', []);
        return $chains[$value] ?? null;
    }

    /**
     * Cache hotel classifications (star ratings, quality levels)
     *
     * @return array Classifications list
     */
    private function cacheClassifications(): array
    {
        $response = $this->client->request('gethotelclassificationids');

        $classifications = $response['hotelclassifications']['option'] ?? [];

        // Normalize to array of arrays
        if (isset($classifications['runno']) && isset($classifications['value'])) {
            $classifications = [$classifications];
        }

        Cache::put(
            $this->cachePrefix . 'classifications',
            $classifications,
            $this->cacheExpiry
        );

        // Index by value
        $byValue = collect($classifications)
            ->keyBy('value')
            ->toArray();
        Cache::put(
            $this->cachePrefix . 'classifications_by_value',
            $byValue,
            $this->cacheExpiry
        );

        return $classifications;
    }

    /**
     * Get all hotel classifications (from cache)
     *
     * @return array Classifications list
     */
    public function getClassifications(): array
    {
        return Cache::get($this->cachePrefix . 'classifications', []);
    }

    /**
     * Cache leisure/preference types
     *
     * @return array Leisure types list
     */
    private function cacheLeisureTypes(): array
    {
        $response = $this->client->request('getleisureids');

        $leisures = $response['leisures']['option'] ?? [];

        // Normalize to array of arrays
        if (isset($leisures['runno']) && isset($leisures['value'])) {
            $leisures = [$leisures];
        }

        Cache::put(
            $this->cachePrefix . 'leisures',
            $leisures,
            $this->cacheExpiry
        );

        return $leisures;
    }

    /**
     * Get all leisure types (from cache)
     *
     * @return array Leisure types list
     */
    public function getLeisureTypes(): array
    {
        return Cache::get($this->cachePrefix . 'leisures', []);
    }

    /**
     * Cache locations (city regions/neighborhoods)
     *
     * @return array Locations list
     */
    private function cacheLocations(): array
    {
        $response = $this->client->request('getlocationids');

        $locations = $response['locations']['option'] ?? [];

        // Normalize to array of arrays
        if (isset($locations['runno']) && isset($locations['value'])) {
            $locations = [$locations];
        }

        Cache::put(
            $this->cachePrefix . 'locations',
            $locations,
            $this->cacheExpiry
        );

        return $locations;
    }

    /**
     * Get all locations (from cache)
     *
     * @return array Locations list
     */
    public function getLocations(): array
    {
        return Cache::get($this->cachePrefix . 'locations', []);
    }

    /**
     * Clear all cached reference data
     *
     * @return bool Success
     */
    public function clearCache(): bool
    {
        Cache::forget($this->cachePrefix . 'countries');
        Cache::forget($this->cachePrefix . 'countries_by_code');
        Cache::forget($this->cachePrefix . 'countries_by_name');
        Cache::forget($this->cachePrefix . 'amenities');
        Cache::forget($this->cachePrefix . 'amenities_by_value');
        Cache::forget($this->cachePrefix . 'chains');
        Cache::forget($this->cachePrefix . 'chains_by_value');
        Cache::forget($this->cachePrefix . 'classifications');
        Cache::forget($this->cachePrefix . 'classifications_by_value');
        Cache::forget($this->cachePrefix . 'leisures');
        Cache::forget($this->cachePrefix . 'locations');

        Log::info('DOTWconnect: Reference data cache cleared');
        return true;
    }

    /**
     * Validate country code
     *
     * @param string $code Country code to validate
     * @return bool True if valid
     */
    public function isValidCountryCode(string $code): bool
    {
        return !is_null($this->getCountryByCode($code));
    }

    /**
     * Validate city code for a country
     *
     * @param string $countryCode Country code
     * @param string $cityCode City code to validate
     * @return bool True if valid
     */
    public function isValidCityCode(string $countryCode, string $cityCode): bool
    {
        return !is_null($this->getCityByCode($countryCode, $cityCode));
    }

    /**
     * Validate amenity code
     *
     * @param string $code Amenity code to validate
     * @return bool True if valid
     */
    public function isValidAmenityCode(string $code): bool
    {
        return !is_null($this->getAmenityByValue($code));
    }

    /**
     * Validate chain code
     *
     * @param string $code Chain code to validate
     * @return bool True if valid
     */
    public function isValidChainCode(string $code): bool
    {
        return !is_null($this->getChainByValue($code));
    }

    /**
     * Get cache status
     *
     * @return array Status of each cache key
     */
    public function getCacheStatus(): array
    {
        return [
            'countries' => !is_null(Cache::get($this->cachePrefix . 'countries')),
            'amenities' => !is_null(Cache::get($this->cachePrefix . 'amenities')),
            'chains' => !is_null(Cache::get($this->cachePrefix . 'chains')),
            'classifications' => !is_null(Cache::get($this->cachePrefix . 'classifications')),
            'leisures' => !is_null(Cache::get($this->cachePrefix . 'leisures')),
            'locations' => !is_null(Cache::get($this->cachePrefix . 'locations')),
        ];
    }
}

/**
 * Usage Examples
 *
 * // Initialize all reference data (call once at startup)
 * app(DOTWconnectReferenceService::class)->initializeAllReferences();
 *
 * // Get countries
 * $countries = app(DOTWconnectReferenceService::class)->getCountries();
 *
 * // Get cities for a country
 * $cities = app(DOTWconnectReferenceService::class)->getCitiesByCountry('US_CODE_12345');
 *
 * // Get amenities
 * $amenities = app(DOTWconnectReferenceService::class)->getAmenities();
 *
 * // Validate user input
 * $isValid = app(DOTWconnectReferenceService::class)->isValidCountryCode('US_CODE_12345');
 *
 * // Clear cache when needed
 * app(DOTWconnectReferenceService::class)->clearCache();
 */
