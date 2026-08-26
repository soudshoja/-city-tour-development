<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An issued voucher document (plan §3.3 + §13-BIS). This is an EVENT, not a
 * live query: `snapshot` is the resolved variable payload frozen at issue
 * time, so a voucher never silently changes because someone edited the
 * underlying task later. `token` (64 random chars) is the ONLY public
 * handle for the tokenised voucher route — never the id (§11.1).
 *
 * Lifecycle (§13-BIS, owner-decided, not symmetrical):
 *  - reissue/refund: the ORIGINAL survives, annotated (status reissued|
 *    refunded), and a NEW voucher is issued and linked via
 *    superseded_by_id. History deliberately visible.
 *  - void -> same details re-issued: the EXISTING row is updated in place,
 *    keeping the same voucher_number and token. The prior snapshot moves
 *    into snapshot_history (operator-only, never client-facing) rather
 *    than being shown or superseded.
 *  - void -> nothing after (7-day grace window): status cancelled
 *    ("Cancel V" internally only — never the client-facing label). Public
 *    link 404s.
 *  - Immediately on void: status void_pending, public link killed right
 *    away (safe for both eventual outcomes) until one of the above
 *    resolves it.
 */
class TravelVoucher extends Model
{
    use HasFactory;

    // Not a DB enum: this codebase's own migration history
    // (2025_04_25 / 2025_06_30 / 2025_07_11 / 2025_08_11 on tasks.status)
    // shows enum churn is expensive. Enforced in code instead.
    public const STATUS_ISSUED = 'issued';

    public const STATUS_REISSUED = 'reissued';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_VOID_PENDING = 'void_pending';

    public const STATUS_CANCELLED = 'cancelled'; // "Cancel V", internal label only — §13-BIS.C

    public const STATUS_SUPERSEDED = 'superseded';

    public const LANGUAGE_EN = 'EN';

    public const LANGUAGE_AR = 'ARB';

    // Statuses whose public link must not resolve (§11.1 + §13-BIS: void
    // kills the link immediately, before the grace window even resolves
    // which of B/C it becomes).
    public const PUBLICLY_DEAD_STATUSES = [
        self::STATUS_VOID_PENDING,
        self::STATUS_CANCELLED,
        self::STATUS_SUPERSEDED,
    ];

    public const VOID_GRACE_WINDOW_DAYS = 7; // §13-BIS, verified against live void->replacement pairs (91% within 7 days)

    protected $fillable = [
        'company_id',
        'voucher_number',
        'voucher_template_id',
        'language',
        'token',
        'snapshot',
        'snapshot_history',
        'version',
        'status',
        'superseded_by_id',
        'pdf_path',
        'resayil_file_id',
        'sent_to_phone',
        'sent_at',
        'sent_by',
        'created_by',
    ];
    // subject_type/subject_id intentionally excluded from $fillable — set
    // via subject()->associate($task) or subject()->associate($package) so
    // the relation is always the source of truth, never a raw mass-assign.

    protected $casts = [
        'snapshot' => 'array',
        'snapshot_history' => 'array',
        'version' => 'integer',
        'sent_at' => 'datetime',
    ];

    protected $hidden = [
        // Operator-only audit trail — never client-facing (§13-BIS.B). The
        // public voucher route must render from `snapshot` alone; hiding
        // this by default is a second guard, not the only one.
        'snapshot_history',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $voucher) {
            if (empty($voucher->token)) {
                $voucher->token = Str::random(64);
            }
        });
    }

    // Deliberately NOT using App\Traits\BelongsToCompany — the public
    // tokenised voucher route, the void-grace-window sweep, and any queued
    // render have no authenticated user. Every query against this model
    // carries company_id explicitly, sourced from the voucher/subject
    // record itself (plan §2.4).

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function voucherTemplate()
    {
        return $this->belongsTo(VoucherTemplate::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    /** The newer voucher that replaced this one (reissue/refund, §13-BIS.A). */
    public function supersededBy()
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /** The prior voucher this one replaced, if any. */
    public function previousVersion()
    {
        return $this->hasOne(self::class, 'superseded_by_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPubliclyAvailable(): bool
    {
        return ! in_array($this->status, self::PUBLICLY_DEAD_STATUSES, true);
    }

    /**
     * The public token route's only allowed lookup shape: exact
     * company_id + token match, and still in a publicly-resolvable status.
     * The companyId segment double-scopes the token (plan §11.1) — never
     * look a voucher up by token alone.
     */
    public function scopeForPublicToken(Builder $query, int $companyId, string $token): Builder
    {
        return $query->where('company_id', $companyId)
            ->where('token', $token)
            ->whereNotIn('status', self::PUBLICLY_DEAD_STATUSES);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /** Vouchers stuck in void_pending past the grace window — for the sweep job. */
    public function scopeVoidGraceExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VOID_PENDING)
            ->where('updated_at', '<=', now()->subDays(self::VOID_GRACE_WINDOW_DAYS));
    }
}
