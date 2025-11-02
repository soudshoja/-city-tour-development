<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoSurcharge extends Model
{
    protected $fillable = ['supplier_company_id', 'label', 'amount'];

    public function supplierCompany()
    {
        return $this->belongsTo(SupplierCompany::class);
    }
}
