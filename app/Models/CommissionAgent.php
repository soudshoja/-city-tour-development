<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionAgent extends Model
{
    protected $fillable = [
        'agent_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function scopeWithAgent($query)
    {
        return $query->with('agent');
    }
}
