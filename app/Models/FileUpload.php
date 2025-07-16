<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    protected $fillable = [
        'file_name',
        'destination_path',
        'user_id',
        'company_id',
        'supplier_id',
        'status',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->destination_path . '/' . $this->file_name);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
