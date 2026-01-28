<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'iso_code',
        'iso3_code',
        'dialing_code',
        'nationality',
        'nationality_ar',
        'currency_code',
        'continent',
        'is_active',
    ];
    
    public function currencies()
    {
        return $this->hasMany(Currency::class);
    }

    // public function companies()
    // {
    //     return $this->hasMany(Company::class, 'country_id');
    // }
}
