<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SupplierSurcharge extends Model
{
    protected $fillable = [
        'supplier_company_id',
        'label',
        'amount',
        'charge_mode',
        'is_refund',
        'is_issued',
        'is_reissued',
        'is_void',
        'is_confirmed',
    ];

    protected $casts = [
        'is_refund' => 'boolean',
        'is_issued' => 'boolean',
        'is_reissued' => 'boolean',
        'is_void' => 'boolean',
        'is_confirmed' => 'boolean',
    ];

    public function supplierCompany()
    {
        return $this->belongsTo(SupplierCompany::class);
    }

    public function references()
    {
        return $this->hasMany(SupplierSurchargeReference::class, 'supplier_surcharge_id', 'id');
    }

    // Smart helper: no need to prepend "is_", for checking inside code or loops
    public function canChargeForStatus(string $status): bool
    {
        $normalized = strtolower($status);
        $statusField = str_starts_with($normalized, 'is_') ? $normalized : 'is_' . $normalized;
        return $this->{$statusField} ?? false;
    }

    // Query scope: clean filtering directly in queries or Blade
    public function scopeForStatus(Builder $query, string $status): Builder
    {
        $normalized = strtolower($status);
        $statusField = str_starts_with($normalized, 'is_') ? $normalized : 'is_' . $normalized;

        return $query->where($statusField, true);
    }
}
