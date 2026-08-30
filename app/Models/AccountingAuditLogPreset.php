<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2.5.F owner refinement (2026-08-30): "saved filter presets per user." `filters` mirrors the
 * exact set of Livewire public filter properties the Log Center's `queryString` already
 * serialises — see {@see \App\Http\Livewire\Accounting\AuditLogIndex::FILTER_KEYS}.
 */
class AccountingAuditLogPreset extends Model
{
    protected $table = 'accounting_audit_log_presets';

    protected $fillable = ['user_id', 'company_id', 'name', 'filters'];

    protected $casts = [
        'user_id' => 'integer',
        'company_id' => 'integer',
        'filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
