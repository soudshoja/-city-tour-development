<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentFile extends Model
{
    protected $fillable = [
        'payment_id',
        'file_id',
        'expiry_date',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('notExpired', function ($query) {
            $query->where('expiry_date', '>', now());
        });
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
