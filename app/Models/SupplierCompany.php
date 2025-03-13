<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SupplierCompany extends Pivot
{
    protected $table = 'supplier_companies';

    protected $fillable = [
        'supplier_id',
        'company_id',
        'account_id'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function supplierCredential()
    {
        return $this->hasMany(SupplierCredential::class);
    }

}
