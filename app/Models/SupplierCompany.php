<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierCompany extends Pivot
{
    use HasFactory;

    protected $table = 'supplier_companies';

    protected $fillable = [
        'supplier_id',
        'company_id',
        'account_id',
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

    public function account()
    {
        return $this->belongsTo(Account::class, 'supplier_company_id');
    }

    public function supplierCredential()
    {
        return $this->hasMany(SupplierCredential::class);
    }

}
