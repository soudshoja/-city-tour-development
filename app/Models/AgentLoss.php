<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLoss extends Model
{
    protected $table = 'agent_loss';

    /**
     * Charge bearer options
     */
    const BEARER_COMPANY = 'company';
    const BEARER_AGENT = 'agent';
    const BEARER_SPLIT = 'split';

    protected $fillable = [
        'agent_id',
        'company_id',
        'loss_bearer',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
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
     * Get loss setting for a specific agent.
     * Returns default (company bears) if no setting exists.
     */
    public static function getForAgent(int $agentId, int $companyId): self
    {
        $setting = self::where('agent_id', $agentId)
            ->where('company_id', $companyId)
            ->first();

        if (!$setting) {
            return new self([
                'agent_id' => $agentId,
                'company_id' => $companyId,
                'loss_bearer' => self::BEARER_COMPANY,
                'agent_percentage' => 0,
                'company_percentage' => 100,
            ]);
        }

        return $setting;
    }

    /**
     * Calculate how much loss the agent and company each bear.
     *
     * @param float $lossAmount The absolute loss amount (positive number)
     * @return array ['agent_loss' => float, 'company_loss' => float, 'loss_bearer' => string]
     */
    public function calculateLossDistribution(float $lossAmount): array
    {
        $lossAmount = abs($lossAmount); // ensure positive

        switch ($this->loss_bearer) {
            case 'company':
                return [
                    'agent_loss' => 0,
                    'company_loss' => round($lossAmount, 3),
                    'loss_bearer' => 'company',
                ];

            case 'agent':
                return [
                    'agent_loss' => round($lossAmount, 3),
                    'company_loss' => 0,
                    'loss_bearer' => 'agent',
                ];

            case 'split':
                $agentShare = round($lossAmount * ($this->agent_percentage / 100), 3);
                $companyShare = round($lossAmount - $agentShare, 3); // remainder to avoid rounding issues
                return [
                    'agent_loss' => $agentShare,
                    'company_loss' => $companyShare,
                    'loss_bearer' => 'split',
                ];

            default:
                return [
                    'agent_loss' => 0,
                    'company_loss' => round($lossAmount, 3),
                    'loss_bearer' => 'company',
                ];
        }
    }

    /**
     * Get bearer options for dropdown.
     */
    public static function getBearerOptions(): array
    {
        return [
            self::BEARER_COMPANY => 'Company Bears Loss',
            self::BEARER_AGENT => 'Agent Bears Loss',
            self::BEARER_SPLIT => 'Split Between Company & Agent',
        ];
    }
}
