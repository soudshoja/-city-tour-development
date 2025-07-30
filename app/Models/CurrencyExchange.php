<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyExchange extends Model
{
    protected $fillable = [
        'company_id',
        'base_currency',
        'exchange_currency',
        'exchange_rate',
        'is_manual',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function histories()
{
    return $this->hasMany(\App\Models\ExchangeRateHistory::class, 'currency_exchange_id');
}
}
