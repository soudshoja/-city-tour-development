<?php

declare(strict_types=1);

/**
 * Cross-module dependency: imports DotwAICity / DotwAICountry from the B2B
 * DotwAI module since they own the dotwai_cities / dotwai_countries static-data
 * tables. Recreating them here would duplicate ~80 lines for zero benefit.
 * Phase 31 ships this; Phase 36+ may decouple.
 */

namespace App\Modules\AkeedDotwAI\Services;

use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;

class FuzzyMatcherService
{
    public function resolveCityCode(string $cityName): ?string
    {
        $cityName = trim($cityName);
        if ($cityName === '') {
            return null;
        }

        $city = DotwAICity::where('name', 'LIKE', "%{$cityName}%")->first();
        if ($city) {
            return $city->code;
        }

        $threshold = (int) config('akeed_dotwai.fuzzy_match_threshold', 3);
        $lower = strtolower($cityName);
        $best = DotwAICity::all()
            ->sortBy(fn (DotwAICity $c) => levenshtein($lower, strtolower($c->name)))
            ->first();

        if ($best && levenshtein($lower, strtolower($best->name)) <= $threshold) {
            return $best->code;
        }

        return null;
    }

    public function resolveCountryCode(string $countryName): ?string
    {
        $countryName = trim($countryName);
        if ($countryName === '') {
            return null;
        }

        $row = DotwAICountry::where('name', 'LIKE', "%{$countryName}%")
            ->orWhere('nationality_name', 'LIKE', "%{$countryName}%")
            ->first();
        if ($row) {
            return $row->code;
        }

        $threshold = (int) config('akeed_dotwai.fuzzy_match_threshold', 3);
        $lower = strtolower($countryName);
        $best = DotwAICountry::all()
            ->sortBy(fn (DotwAICountry $c) => min(
                levenshtein($lower, strtolower($c->name)),
                levenshtein($lower, strtolower($c->nationality_name ?? ''))
            ))
            ->first();

        if (! $best) {
            return null;
        }

        $dist = min(
            levenshtein($lower, strtolower($best->name)),
            levenshtein($lower, strtolower($best->nationality_name ?? ''))
        );

        return $dist <= $threshold ? $best->code : null;
    }
}
