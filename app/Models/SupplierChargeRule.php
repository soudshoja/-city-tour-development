<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * W6.C (w6-brief.md; supplier-charges-design.md Table 4). A config row describing one fee a
 * supplier charges the agency. See
 * database/migrations/2026_08_29_150000_w6c_create_supplier_charge_rules_table.php for the full
 * field contract and resolution-order docblock, and
 * App\Services\Accounting\SupplierChargeRuleResolver for the resolver that reads this table.
 */
class SupplierChargeRule extends Model
{
    public const BASIS_FIXED = 'fixed';

    public const BASIS_PERCENT_OF_FARE = 'percent_of_fare';

    public const BASIS_PERCENT_OF_TOTAL = 'percent_of_total';

    public const BASIS_PER_PASSENGER = 'per_passenger';

    public const BASIS_PER_SEGMENT = 'per_segment';

    public const RECHARGE_ABSORB = 'absorb';

    public const RECHARGE_CLIENT = 'recharge_client';

    public const RECHARGE_AGENT = 'recharge_agent';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'service_type',
        'channel',
        'charge_kind',
        'basis',
        'amount',
        'currency',
        'cost_account',
        'recharge_policy',
        'commissionable',
        'tax_code',
        'rounding_rule',
        'active',
        'effective_from',
        'effective_to',
        'once_per_reference',
        'label',
        'legacy_supplier_surcharge_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'commissionable' => 'boolean',
        'active' => 'boolean',
        'once_per_reference' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function firings()
    {
        return $this->hasMany(SupplierChargeRuleFiring::class);
    }

    /**
     * Structural specificity rank used by SupplierChargeRuleResolver's precedence ordering
     * (supplier+service_type > supplier-only > service_type-only > company-wide). Channel is
     * deliberately NOT part of this rank -- see the migration's own docblock.
     */
    public function specificityRank(): int
    {
        return ($this->supplier_id !== null ? 2 : 0) + ($this->service_type !== null ? 1 : 0);
    }

    public function isEffectiveOn(\DateTimeInterface $date): bool
    {
        $carbon = \Illuminate\Support\Carbon::instance($date)->startOfDay();

        if ($this->effective_from !== null && $carbon->lt($this->effective_from->copy()->startOfDay())) {
            return false;
        }

        if ($this->effective_to !== null && $carbon->gt($this->effective_to->copy()->endOfDay())) {
            return false;
        }

        return true;
    }
}
