<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentClient extends Model
{
    protected $fillable = [
        'agent_id',
        'client_id',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
