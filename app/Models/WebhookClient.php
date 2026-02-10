<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'company_id',
        'webhook_url',
        'rate_limit',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rate_limit' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function secrets(): HasMany
    {
        return $this->hasMany(WebhookSecret::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(WebhookAuditLog::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Helpers
    public function getActiveSecret(): ?WebhookSecret
    {
        return $this->secrets()
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get secrets that should still be accepted (active + grace period)
     */
    public function getValidSecrets()
    {
        return $this->secrets()
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('grace_period_until')
                          ->where('grace_period_until', '>', now());
                    });
            })
            ->get();
    }
}
