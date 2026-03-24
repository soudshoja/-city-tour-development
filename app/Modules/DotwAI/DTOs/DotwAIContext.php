<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\DTOs;

use App\Models\Agent;
use App\Models\CompanyDotwCredential;

/**
 * Typed DTO representing the fully resolved context for a DotwAI request.
 *
 * Created by PhoneResolverService and attached to the request by
 * the ResolveDotwAIContext middleware. Contains the agent, company,
 * DOTW credentials, booking track, and markup configuration.
 *
 * Immutable by design (readonly properties).
 */
final class DotwAIContext
{
    /**
     * @param Agent                  $agent        The resolved agent from the phone number
     * @param int                    $companyId    The company ID (from agent->branch->company_id)
     * @param CompanyDotwCredential  $credentials  The active DOTW credentials for this company
     * @param string                 $track        Booking track: 'b2b' or 'b2c'
     * @param float                  $markupPercent Markup percentage (0 for B2B, >0 for B2C)
     * @param bool                   $b2bEnabled   Whether B2B track is enabled for this company
     * @param bool                   $b2cEnabled   Whether B2C track is enabled for this company
     */
    public function __construct(
        public readonly Agent $agent,
        public readonly int $companyId,
        public readonly CompanyDotwCredential $credentials,
        public readonly string $track,
        public readonly float $markupPercent,
        public readonly bool $b2bEnabled,
        public readonly bool $b2cEnabled,
    ) {}

    /**
     * Check if the current track is B2B.
     */
    public function isB2B(): bool
    {
        return $this->track === 'b2b';
    }

    /**
     * Check if the current track is B2C.
     */
    public function isB2C(): bool
    {
        return $this->track === 'b2c';
    }

    /**
     * Get the markup multiplier for price calculations.
     *
     * Example: 20% markup returns 1.20
     */
    public function getMarkupMultiplier(): float
    {
        return 1 + ($this->markupPercent / 100);
    }
}
