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
        'email',
        'agent_id',
        'address',
        'passport_file',
        'passport_no',
        'status_id',
        'civil_no',
        'date_of_birth',
        'phone',
    ];


    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
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

}
