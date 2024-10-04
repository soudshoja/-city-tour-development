<?php


// app/Models/Agent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'user_id', 'type', 'company_id', 'phone_number', 'description'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
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
}
