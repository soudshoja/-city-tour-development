<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskVisaDetail extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'task_id',
        'visa_type',
        'application_number',
        'expiry_date',
        'num_of_entry',
        'stay_duration',
        'issuing_country',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
    use HasFactory;
}
