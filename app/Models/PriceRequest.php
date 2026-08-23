<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRequest extends Model
{
    const STATUS_PENDING   = 'pending';
    const STATUS_ASKED     = 'asked';
    const STATUS_ANSWERED  = 'answered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'task_id',
        'company_id',
        'agent_id',
        'pnr',
        'phone',
        'country_code',
        'amount',
        'status',
        'asked_at',
        'answered_at',
        'reminded_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:3',
        'asked_at'    => 'datetime',
        'answered_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
