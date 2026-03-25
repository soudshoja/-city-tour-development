<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;
    protected $table = 'agents'; // Explicitly define the table name

    public const AI_AGENT = 'AI Agent';

    protected $fillable = [
        'user_id',
        'name',
        'tbo_reference',
        'amadeus_id',
        'email',
        'type_id',
        'phone_number',
        'country_code',
        'branch_id',
        'commission',
        'salary',
        'target',
        'profit_account_id',
        'loss_account_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function agentType()
    {
        return $this->belongsTo(AgentType::class, 'type_id');
    }
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_agents', 'agent_id', 'client_id');
    }

    public function clientQuery()
    {
        return Client::where(function ($q) {
            $q->where('agent_id', $this->id)
                ->orWhereHas('agents', function ($query) {
                    $query->where('agent_id', $this->id);
                });
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function account()
    {
        return $this->hasOne(Account::class, 'agent_id');
    }

    public function refundClients()
    {
        return $this->hasMany(RefundClient::class);
    }

    public function profitAccount()
    {
        return $this->belongsTo(Account::class, 'profit_account_id');
    }

    public function lossAccount()
    {
        return $this->belongsTo(Account::class, 'loss_account_id');
    }

    /**
     * Get the charge setting for this agent.
     */
    public function chargeSetting()
    {
        return $this->hasOne(AgentCharge::class);
    }

    /**
     * Get the charge settings relationship scoped to a specific company.
     * Use this when you need company-specific settings.
     */
    public function chargeSettingForCompany($companyId)
    {
        return $this->hasOne(AgentCharge::class)
            ->where('company_id', $companyId);
    }

    /**
     * Get the effective charge setting for this agent.
     * Returns default if no custom setting exists.
     * 
     * @param int|null $companyId Optional company ID override
     * @return AgentCharge
     */
    public function getEffectiveChargeSetting(?int $companyId = null): AgentCharge
    {
        $companyId = $companyId ?? $this->branch?->company_id;

        if (!$companyId) {
            // Return default setting
            return new AgentCharge([
                'agent_id' => $this->id,
                'gateway_charge_bearer' => 'company',
                'gateway_agent_percentage' => 0,
                'gateway_company_percentage' => 100,
                'supplier_charge_bearer' => 'company',
                'supplier_agent_percentage' => 0,
                'supplier_company_percentage' => 100,
                'is_active' => true,
            ]);
        }

        return AgentCharge::getForAgent($this->id, $companyId);
    }
}
