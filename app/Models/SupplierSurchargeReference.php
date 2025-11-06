<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierSurchargeReference extends Model
{
    protected $fillable = [
        'supplier_surcharge_id',
        'reference',
        'charge_behavior',
        'is_charged',
    ];

    protected $casts = [
        'is_charged' => 'boolean',
    ];

    public function supplierSurcharge()
    {
        return $this->belongsTo(SupplierSurcharge::class);
    }

    public function markAsCharged(): void
    {
        $this->update(['is_charged' => true]);
    }

    public function canBeCharged(): bool
    {
        return $this->charge_behavior === 'repetitive' || !$this->is_charged;
    }
}
