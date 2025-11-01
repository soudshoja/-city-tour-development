<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'iata_number',
        'currency',
        'wallet_balance',
        'opening_balance',
        'task_amount',
        'closing_balance',
    ];
}