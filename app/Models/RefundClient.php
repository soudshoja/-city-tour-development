<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundClient extends Model
{
    protected $fillable = [
        'client_id',
        'agent_id',
        'status',
        'amount',
        'currency',
        'remark',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
