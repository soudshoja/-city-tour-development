<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry entry for a shipped voucher design (plan §3.1). The design itself
 * is a Blade file in resources/views/vouchers/{view_key}.blade.php — this
 * row carries identity, per-company overrides and toggles only.
 *
 * company_id NULL = system template we ship, visible to every company.
 * company_id set  = one company's own override row for that design.
 */
class VoucherTemplate extends Model
{
    use HasFactory;

    public const TASK_TYPE_FLIGHT = 'flight';

    public const TASK_TYPE_HOTEL = 'hotel';

    public const TASK_TYPE_VISA = 'visa';

    public const TASK_TYPE_INSURANCE = 'insurance';

    public const TASK_TYPE_GENERIC = 'generic';

    public const TASK_TYPE_PACKAGE = 'package';

    public const LANGUAGE_EN = 'EN';

    public const LANGUAGE_AR = 'ARB';

    protected $fillable = [
        'company_id',
        'task_type',
        'name',
        'view_key',
        'language',
        'is_default',
        'is_active',
        'show_price',
        'show_payment_status',
        'term_id',
        'options',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'show_price' => 'boolean',
        'show_payment_status' => 'boolean',
        'options' => 'array',
    ];

    // Deliberately NOT using App\Traits\BelongsToCompany: its global scope
    // only applies when Auth::check() is true, but this model is read from
    // the public tokenised voucher route and from console/queue contexts
    // that have no authenticated user (plan §2.4). Every query against this
    // model — here and in every caller — must carry an explicit company_id.
    // Use the scopes below rather than relying on ambient auth state.

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vouchers()
    {
        return $this->hasMany(TravelVoucher::class);
    }

    public function isSystemTemplate(): bool
    {
        return $this->company_id === null;
    }

    /**
     * The effective template set for a company: shipped system rows
     * (company_id IS NULL) overlaid by that company's own rows (plan §3.1
     * resolution rule). $companyId must be sourced from the caller's own
     * resolved context (subject/task/auth company), never assumed.
     */
    public function scopeVisibleTo(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $q) use ($companyId) {
            $q->whereNull('company_id')->orWhere('company_id', $companyId);
        });
    }

    /**
     * Just one company's own override rows — never system rows. Template
     * management mutations must use this: system rows stay immutable
     * through the UI (plan §11.2).
     */
    public function scopeOwnedBy(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * The one active template that actually applies for a company +
     * task_type + language, following the §3.1 resolution rule: a
     * company's own override row wins over the shipped system row when
     * one exists (Step 4, plan §16 — reused from
     * VoucherTemplateController::preview()'s identical inline query).
     */
    public static function resolveEffective(int $companyId, string $taskType, string $language): ?self
    {
        return static::query()
            ->where('task_type', $taskType)
            ->where('language', $language)
            ->where('is_active', true)
            ->visibleTo($companyId)
            ->orderByRaw('company_id IS NOT NULL DESC')
            ->first();
    }
}
