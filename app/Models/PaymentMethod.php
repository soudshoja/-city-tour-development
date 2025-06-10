<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'arabic_name',
        'english_name',
        'code',
        'type',
        'is_active',
        'currency',
        'service_charge',
        'self_charge',
        'paid_by',
        'charge_type',
        'description',
        'image',
    ];

    public function gateways()
    {
        return $this->belongsTo(Charge::class, 'name');
    }
}
