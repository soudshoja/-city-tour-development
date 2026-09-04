<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyInvite extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'token', 'email', 'monthly_fee', 'note', 'status',
        'expires_at', 'created_by', 'company_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'monthly_fee' => 'decimal:3',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isFuture();
    }

    public function markUsed(int $companyId): void
    {
        $this->update(['status' => self::STATUS_USED, 'company_id' => $companyId]);
    }
}
