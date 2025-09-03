<?php


// app/Models/Agent.php

namespace App\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;
    protected $table = 'agents'; // Explicitly define the table name


    protected $fillable = [
        'user_id',
        'name',
        'tbo_reference',
        'amadeus_id',
        'email',
        'type_id',
        'phone_number',
        'country_code',
        'company_id',
        'branch_id',
        'commission',
        'salary',
        'target',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function agentType()
    {
        return $this->belongsTo(AgentType::class, 'type_id');
    }
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_agents', 'agent_id', 'client_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function account()
    {
        return $this->hasOne(Account::class, 'agent_id');
    }

    public function refundClients()
    {
        return $this->hasMany(RefundClient::class);
    }
}
