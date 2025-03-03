<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country_id',
        'website',
        'payment_terms',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'supplier_companies')
            ->using(SupplierCompany::class);
    }

    public function credentials()
    {
        return $this->hasMany(SupplierCredential::class);
    }
}