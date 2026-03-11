<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PasswordUpdateToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
    ];

    protected $dates = [
        'expires_at',
    ];

    /**
     * Relationship: token belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
