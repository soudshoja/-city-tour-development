<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCompany extends Model
{
    protected $fillable = [
        'supplier_id',
        'company_id',
        'is_active',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
