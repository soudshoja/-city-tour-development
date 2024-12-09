<?php


// app/Models/Agent.php

namespace App\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'type',
        'phone_number',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function type()
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
        return $this->hasMany(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
