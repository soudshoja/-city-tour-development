<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'auth_method',
        'has_hotel',
        'has_flight',
        'has_visa',         
        'has_insurance',
        'has_tour',
        'has_cruise',
        'has_car',
        'has_rail',
        'has_esim',
        'has_event',
        'has_lounge',
        'has_ferry',
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
            ->using(SupplierCompany::class)
            ->withPivot('is_active');
    }

    public function credentials()
    {
        return $this->hasMany(SupplierCredential::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
    public function exchangeRates()
    {
        return $this->hasMany(SupplierExchangeRate::class);
    }

    public function isMergeSupplier(): bool
    {
        if (in_array($this->name, ['TBO Air', 'TBO Car'])) {
            return true;
        }

        if ($this->has_hotel == 1 && $this->name !== 'Amadeus') {
            return true;
        }

        return false;
    }
}