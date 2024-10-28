<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'agent_id',
        'currency',
        'amount',
        'status',
    ];
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class);
    }

    public function payment()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
