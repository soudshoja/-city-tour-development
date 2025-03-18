<?php


// app/Models/Agent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'country_id',
        'status',
        'code',
        'email',
        'address',
        'phone',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function agents()
    {
        return $this->hasManyThrough(Agent::class, Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function nationality()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'supplier_companies')
            ->using(SupplierCompany::class);
    }
}
