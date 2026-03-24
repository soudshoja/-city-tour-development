<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;
use App\Modules\DotwAI\Models\DotwAIHotel;
use Illuminate\Support\Collection;

/**
 * Fuzzy matching service for hotels, cities, and countries.
 *
 * Uses a two-tier approach:
 * 1. LIKE query (fast, uses database index)
 * 2. Levenshtein distance fallback (handles typos)
 *
 * The Levenshtein threshold is configurable via dotwai.fuzzy_match_threshold.
 *
 * @see FOUND-05
 */
class FuzzyMatcherService
{
    /**
     * Find hotels by name, optionally filtered by city.
     *
     * First tries a LIKE match (fast, indexed). If no results, falls back
     * to Levenshtein distance on a candidate set filtered by city (or
     * limited to 500 if no city filter).
     *
     * @param string      $query  Hotel name query (e.g., "Hilton Dubai")
     * @param string|null $city   Optional city filter
     * @param int         $limit  Maximum results to return
     * @return Collection<int, DotwAIHotel>
     */
    public function findHotels(string $query, ?string $city = null, int $limit = 10): Collection
    {
        $query = trim(strtolower($query));

        if (empty($query)) {
            return collect();
        }

        // Tier 1: LIKE match (fast, uses index)
        $likeQuery = DotwAIHotel::where('name', 'LIKE', "%{$query}%");

        if ($city !== null && $city !== '') {
            $likeQuery->where('city', 'LIKE', "%{$city}%");
        }

        $likeResults = $likeQuery->limit($limit)->get();

        if ($likeResults->isNotEmpty()) {
            return $likeResults;
        }

        // Tier 2: Levenshtein fallback
        $threshold = (int) config('dotwai.fuzzy_match_threshold', 3);

        $candidateQuery = DotwAIHotel::query();

        if ($city !== null && $city !== '') {
            $candidateQuery->where('city', 'LIKE', "%{$city}%");
        } else {
            $candidateQuery->limit(500);
        }

        $candidates = $candidateQuery->get();

        return $candidates
            ->map(function (DotwAIHotel $hotel) use ($query) {
                $hotel->setAttribute('levenshtein_distance', levenshtein(
                    $query,
                    strtolower($hotel->name)
                ));
                return $hotel;
            })
            ->filter(fn (DotwAIHotel $hotel) => $hotel->getAttribute('levenshtein_distance') <= $threshold)
            ->sortBy('levenshtein_distance')
            ->take($limit)
            ->values();
    }

    /**
     * Resolve a city name to a DotwAICity model.
     *
     * First tries LIKE match, then Levenshtein fallback on all cities
     * (typically <5000 records -- safe for in-memory sort).
     *
     * @param string $cityName City name to resolve
     * @return DotwAICity|null
     */
    public function resolveCity(string $cityName): ?DotwAICity
    {
        $cityName = trim($cityName);

        if (empty($cityName)) {
            return null;
        }

        // Tier 1: LIKE match
        $city = DotwAICity::where('name', 'LIKE', "%{$cityName}%")->first();

        if ($city) {
            return $city;
        }

        // Tier 2: Levenshtein fallback
        $threshold = (int) config('dotwai.fuzzy_match_threshold', 3);
        $lowerName = strtolower($cityName);

        $bestMatch = DotwAICity::all()
            ->sortBy(fn (DotwAICity $c) => levenshtein($lowerName, strtolower($c->name)))
            ->first();

        if ($bestMatch && levenshtein($lowerName, strtolower($bestMatch->name)) <= $threshold) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Resolve a country name to a DotwAICountry model.
     *
     * Checks both the `name` and `nationality_name` columns.
     * Uses LIKE match first, then Levenshtein fallback.
     *
     * @param string $countryName Country or nationality name to resolve
     * @return DotwAICountry|null
     */
    public function resolveCountry(string $countryName): ?DotwAICountry
    {
        $countryName = trim($countryName);

        if (empty($countryName)) {
            return null;
        }

        // Tier 1: LIKE match on name or nationality_name
        $country = DotwAICountry::where('name', 'LIKE', "%{$countryName}%")
            ->orWhere('nationality_name', 'LIKE', "%{$countryName}%")
            ->first();

        if ($country) {
            return $country;
        }

        // Tier 2: Levenshtein fallback on name
        $threshold = (int) config('dotwai.fuzzy_match_threshold', 3);
        $lowerName = strtolower($countryName);

        $bestMatch = DotwAICountry::all()
            ->sortBy(fn (DotwAICountry $c) => min(
                levenshtein($lowerName, strtolower($c->name)),
                levenshtein($lowerName, strtolower($c->nationality_name ?? ''))
            ))
            ->first();

        if (!$bestMatch) {
            return null;
        }

        $distance = min(
            levenshtein($lowerName, strtolower($bestMatch->name)),
            levenshtein($lowerName, strtolower($bestMatch->nationality_name ?? ''))
        );

        if ($distance <= $threshold) {
            return $bestMatch;
        }

        return null;
    }
}
