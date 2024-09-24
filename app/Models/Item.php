<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_ref',
        'description',
        'item_type',
        'client_id',
        'agent_id',
        'item_status',
        'created_at',
        'updated_at',
        'item_id',
        'item_code',
        'time_signed',
        'client_email',
        'agent_email',
        'total_price',
        'payment_date',
        'paid',
        'payment_time',
        'payment_amount',
        'refunded',
        'trip_name',
        'trip_code',
        'client_email',
    ];
}
