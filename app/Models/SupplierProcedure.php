<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProcedure extends Model
{
    protected $fillable = [
        'supplier_company_id',
        'name',
        'procedure',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // Automatically manage the active_flag when creating/updating
        static::creating(function ($procedure) {
            $procedure->active_flag = $procedure->is_active ? 1 : null;
        });

        static::updating(function ($procedure) {
            $procedure->active_flag = $procedure->is_active ? 1 : null;
        });
    }

    public function supplierCompany()
    {
        return $this->belongsTo(SupplierCompany::class);
    }
}
