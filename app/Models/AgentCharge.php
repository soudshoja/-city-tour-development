<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCharge extends Model
{
    use HasFactory;

    protected $table = 'agent_charge';

    /**
     * Charge bearer options
     */
    const BEARER_COMPANY = 'company';
    const BEARER_AGENT = 'agent';
    const BEARER_SPLIT = 'split';

    protected $fillable = [
        'agent_id',
        'company_id',
        'charge_bearer',
        'agent_percentage',
        'company_percentage',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'agent_percentage' => 'decimal:2',
        'company_percentage' => 'decimal:2',
    ];

    /**
     * Get the agent that owns this setting.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the company that owns this setting.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created this setting.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this setting.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get setting for a specific agent.
     * Returns default (company bears all) if no setting exists.
     */
    public static function getForAgent(int $agentId, int $companyId): self
    {
        $setting = self::where('agent_id', $agentId)
            ->where('company_id', $companyId)
            ->first();

        if (!$setting) {
            // Return default setting (company bears all charges)
            return new self([
                'agent_id' => $agentId,
                'company_id' => $companyId,
                'charge_bearer' => self::BEARER_COMPANY,
                'agent_percentage' => 0,
                'company_percentage' => 100,
            ]);
        }

        return $setting;
    }

    /**
     * Calculate how much of extra charges the agent bears.
     * 
     * Extra charges = Gateway fees + Supplier surcharges
     * 
     * @param float $extraCharge Total extra charge amount
     * @return float Amount to deduct from agent's profit
     */
    public function calculateAgentChargeDeduction(float $extraCharge): float
    {
        return match ($this->charge_bearer) {
            self::BEARER_COMPANY => 0,
            self::BEARER_AGENT => $extraCharge,
            self::BEARER_SPLIT => round($extraCharge * ($this->agent_percentage / 100), 3),
            default => 0,
        };
    }

    /**
     * Get the percentage agent bears based on bearer setting.
     */
    public function getAgentPercentageToApply(): float
    {
        return match ($this->charge_bearer) {
            self::BEARER_COMPANY => 0,
            self::BEARER_AGENT => 100,
            self::BEARER_SPLIT => (float) $this->agent_percentage,
            default => 0,
        };
    }

    /**
     * Get bearer options for dropdown.
     */
    public static function getBearerOptions(): array
    {
        return [
            self::BEARER_COMPANY => 'Company Bears All',
            self::BEARER_AGENT => 'Agent Bears All',
            self::BEARER_SPLIT => 'Split Between Company & Agent',
        ];
    }

    /**
     * Get bearer label for display.
     */
    public function getBearerLabel(): string
    {
        return self::getBearerOptions()[$this->charge_bearer] ?? 'Unknown';
    }
}
