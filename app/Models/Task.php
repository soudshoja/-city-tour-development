<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'description',
        'reference',
        'status',
    ];

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class);
    }
}
