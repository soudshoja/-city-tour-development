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
        'status',
        'address',
        'passport_no',
        'phone', 
             ];

             
    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    } 
}