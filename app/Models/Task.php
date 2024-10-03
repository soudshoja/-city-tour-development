<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'status',
        'contract_id',
        'ext_id',
        'agent_email',
        'client_email',
        'task_type',
        'item_id',
        'agent_id',
        'client_id',
        'client_name',
        'client_phone'
    ];

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
