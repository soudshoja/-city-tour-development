<?php


// app/Models/Agent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'agent_id',
        'address',
        'passport_no',
        'old_passport_no',
        'status',
        'civil_no',
        'date_of_birth',
        'phone',
        'country_code',
    ];

    public function getNameAttribute()
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->join(' '));
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'client_agents', 'client_id', 'agent_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function subClients()
    {
        return $this->hasMany(ClientGroup::class, 'parent_client_id');
    }

    public function parentClients()
    {
        return $this->hasMany(ClientGroup::class, 'child_client_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function refunds()
    {
        return $this->hasMany(RefundClient::class);
    }

}
