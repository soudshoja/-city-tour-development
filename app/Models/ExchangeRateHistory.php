<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRateHistory extends Model
{
    protected $fillable = [
        'currency_exchange_id',
        'base_currency',
        'exchange_currency',
        'old_rate',
        'new_rate',
        'method',
        'changed_by',
        'changed_at',
    ];

    public function setOldRateAttribute($value)
    {
        // Round to 6 decimal places to match database precision
        $this->attributes['old_rate'] = round((float) $value, 6);
    }

    public function setNewRateAttribute($value)
    {
        // Round to 6 decimal places to match database precision
        $this->attributes['new_rate'] = round((float) $value, 6);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function currencyExchange()
    {
        return $this->belongsTo(CurrencyExchange::class);
    }
}