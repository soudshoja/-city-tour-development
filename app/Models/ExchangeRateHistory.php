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

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function currencyExchange()
    {
        return $this->belongsTo(CurrencyExchange::class);
    }
}