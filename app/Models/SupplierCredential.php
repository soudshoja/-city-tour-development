<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCredential extends Model
{
    protected $fillable = [
        'supplier_id',
        'company_id',
        'environment',
        'type',
        'username',
        'password',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'expires_at',
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