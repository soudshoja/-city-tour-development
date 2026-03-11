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

    public function supplierCompany()
    {
        return $this->belongsTo(SupplierCompany::class);
    }
}
