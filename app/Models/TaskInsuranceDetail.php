<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskInsuranceDetail extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'task_id',
        'date',
        'paid_leaves',
        'document_reference',
        'insurance_type',
        'destination',
        'plan_type',
        'duration',
        'package',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
    use HasFactory;
}
