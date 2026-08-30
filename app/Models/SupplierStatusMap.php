<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * W6.S "Per-supplier status map" (w6-brief.md, owner addition 2026-08-28). See
 * database/migrations/2026_08_29_140003_w6s_create_supplier_status_maps_table.php for the schema
 * and resolution-order contract, and App\Services\TaskStatusService::mapStatus() for the resolver
 * that reads this table.
 */
class SupplierStatusMap extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'channel',
        'raw_status',
        'canonical_status',
        'deadline_source',
        'priority',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'priority' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
