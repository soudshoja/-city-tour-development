<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResailaiCredential extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'api_key',
        'api_secret',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
