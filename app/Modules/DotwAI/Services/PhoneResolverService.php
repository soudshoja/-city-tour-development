<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Models\Agent;
use App\Models\CompanyDotwCredential;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a phone number to a full DotwAIContext.
 *
 * Resolution chain:
 * 1. Phone -> Agent (via agents table, multiple lookup strategies)
 * 2. Agent -> Company (via branch relationship)
 * 3. Company -> CompanyDotwCredential (active credentials)
 * 4. Credentials -> Track (B2B or B2C based on markup)
 *
 * Adapted from the existing HotelSearchService::findCompanyIdByPhone() pattern.
 *
 * @see FOUND-03
 */
class PhoneResolverService
{
    /**
     * Resolve a phone number to a DotwAIContext.
     *
     * Attempts multiple lookup strategies to find the agent, then resolves
     * the full context chain: agent -> company -> credentials -> track.
     *
     * @param string $phone The phone number (may include +, country code, etc.)
     * @return DotwAIContext|null Null if any part of the chain fails
     */
    public function resolve(string $phone): ?DotwAIContext
    {
        // Normalize: strip +, leading zeros, keep digits only
        $normalized = preg_replace('/[^0-9]/', '', $phone) ?? '';

        // Try multiple lookup strategies against Agent model
        $agent = $this->findAgent($phone, $normalized);

        if (!$agent) {
            Log::channel('dotw')->info('[DotwAI] Phone not found', ['phone' => $phone]);
            return null;
        }

        // Resolve company via branch
        $companyId = $agent->branch?->company_id;

        if (!$companyId) {
            Log::channel('dotw')->warning('[DotwAI] Agent has no company', [
                'phone' => $phone,
                'agent_id' => $agent->id,
            ]);
            return null;
        }

        // Resolve DOTW credentials
        $credentials = CompanyDotwCredential::forCompany($companyId)->first();

        if (!$credentials) {
            Log::channel('dotw')->warning('[DotwAI] No active DOTW credentials', [
                'phone' => $phone,
                'company_id' => $companyId,
            ]);
            return null;
        }

        // Determine track: markup > 0 means B2C, otherwise B2B
        $track = $credentials->markup_percent > 0 ? 'b2c' : 'b2b';

        // B2B/B2C enabled: per-company first, config fallback
        $b2bEnabled = $credentials->b2b_enabled ?? config('dotwai.b2b_enabled', true);
        $b2cEnabled = $credentials->b2c_enabled ?? config('dotwai.b2c_enabled', true);

        Log::channel('dotw')->info('[DotwAI] Context resolved', [
            'phone' => $phone,
            'company_id' => $companyId,
            'track' => $track,
            'b2b_enabled' => $b2bEnabled,
            'b2c_enabled' => $b2cEnabled,
        ]);

        return new DotwAIContext(
            agent: $agent,
            companyId: $companyId,
            credentials: $credentials,
            track: $track,
            markupPercent: $credentials->markup_percent,
            b2bEnabled: $b2bEnabled,
            b2cEnabled: $b2cEnabled,
        );
    }

    /**
     * Find an agent by phone number using multiple strategies.
     *
     * Tries raw phone, normalized phone, and CONCAT(country_code, phone_number)
     * variations to handle different phone number formats.
     *
     * @param string $rawPhone     Original phone input
     * @param string $normalized   Digits-only version
     * @return Agent|null
     */
    private function findAgent(string $rawPhone, string $normalized): ?Agent
    {
        // Strategy 1: Direct match on phone_number
        $agent = Agent::where('phone_number', $rawPhone)->first();
        if ($agent) {
            return $agent;
        }

        // Strategy 2: Normalized match on phone_number
        if ($normalized !== $rawPhone) {
            $agent = Agent::where('phone_number', $normalized)->first();
            if ($agent) {
                return $agent;
            }
        }

        // Strategy 3: CONCAT(country_code, phone_number) = raw phone
        $agent = Agent::where(
            DB::raw("CONCAT(country_code, phone_number)"),
            $rawPhone
        )->first();
        if ($agent) {
            return $agent;
        }

        // Strategy 4: CONCAT(country_code, phone_number) = normalized phone
        if ($normalized !== $rawPhone) {
            $agent = Agent::where(
                DB::raw("CONCAT(country_code, phone_number)"),
                $normalized
            )->first();
            if ($agent) {
                return $agent;
            }
        }

        return null;
    }
}
