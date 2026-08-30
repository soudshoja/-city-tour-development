<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * W6.C dedup ledger row -- see
 * database/migrations/2026_08_29_150001_w6c_create_supplier_charge_rule_firings_table.php and
 * App\Services\Accounting\SupplierChargeRuleResolver for the full contract.
 */
class SupplierChargeRuleFiring extends Model
{
    protected $fillable = [
        'supplier_charge_rule_id',
        'company_id',
        'reference',
        'task_id',
        'fired_at',
    ];

    protected $casts = [
        'fired_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(SupplierChargeRule::class, 'supplier_charge_rule_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
