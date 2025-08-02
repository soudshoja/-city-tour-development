<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class SystemExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'exchange_currency',
        'exchange_rate',
    ];

    public function setBaseCurrencyAttribute($value)
    {
        $this->attributes['base_currency'] = strtoupper($value);
    }

    public function setExchangeCurrencyAttribute($value)
    {
        $this->attributes['exchange_currency'] = strtoupper($value);
    }

    public function setExchangeRateAttribute($value)
    { 
        // Round to 6 decimal places to match database precision
        $this->attributes['exchange_rate'] = round((float) $value, 6);
    }

    public function getExchangeRateAttribute($value)
    {
        return (float) $value;
    }
   
}
