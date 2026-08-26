<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Links one TravelERP (company, user) pair to its Resayil account identity.
 * See the create_resayil_accounts_table migration for the full field map
 * and the provisioning model this supports.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $role
 * @property string|null $resayil_customer_id
 * @property string|null $resayil_device_id
 * @property string|null $resayil_account_token
 * @property string|null $resayil_secret
 * @property string|null $resayil_user_id
 * @property string $status
 * @property string|null $resayil_email
 * @property \Illuminate\Support\Carbon|null $provisioned_at
 * @property array|null $meta
 */
class ResayilAccount extends Model
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_AGENT = 'agent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROVISIONED = 'provisioned';

    public const STATUS_LIMIT_REACHED = 'limit_reached';

    public const STATUS_AWAITING_ADMIN = 'awaiting_admin';

    public const STATUS_PENDING_DEVICE = 'pending_device';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_ERROR = 'error';

    protected $table = 'resayil_accounts';

    protected $fillable = [
        'company_id',
        'user_id',
        'role',
        'resayil_customer_id',
        'resayil_device_id',
        'resayil_account_token',
        'resayil_secret',
        'resayil_user_id',
        'resayil_webhook_id',
        'webhook_nonce',
        'key_source',
        'device_paired_at',
        'device_health',
        'health_checked_at',
        'subscription_cache',
        'status',
        'resayil_email',
        'provisioned_at',
        'meta',
    ];

    /**
     * Admin-row `key_source` values (plan §4.2): how this company's
     * account token was obtained. 'auto' = silently captured from
     * GET /resellers/customers/{id} apiKeys[] at provisioning time
     * (slice 2); 'pasted' = the owner pasted it into the Panel 2
     * recovery card. Slice 1 writes neither — it needs no account key.
     */
    public const KEY_SOURCE_AUTO = 'auto';

    public const KEY_SOURCE_PASTED = 'pasted';

    /**
     * Both Resayil secrets are credentials, not display data: never let them
     * ride along in an ->toArray()/->toJson() (e.g. an API response body,
     * a view dump, or a debug tool). They are surfaced only through an
     * explicit, authorized accessor — never through default serialization.
     */
    protected $hidden = [
        'resayil_account_token',
        'resayil_secret',
    ];

    protected $casts = [
        'provisioned_at' => 'datetime',
        'device_paired_at' => 'datetime',
        'health_checked_at' => 'datetime',
        'meta' => 'array',
        // Price-free, token-free projection only — see the §4.2 delta
        // migration's note and ResayilAdminService::project*().
        'subscription_cache' => 'array',
        'resayil_account_token' => 'encrypted',
        'resayil_secret' => 'encrypted',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isProvisioned(): bool
    {
        return $this->status === self::STATUS_PROVISIONED;
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeProvisioned($query)
    {
        return $query->where('status', self::STATUS_PROVISIONED);
    }

    /**
     * The single admin row that owns a company's Resayil workspace
     * identity (customer id, device id, account token).
     *
     * TENANT ISOLATION (plan §9.3): every Admin Center read resolves its
     * Resayil ids from THIS row, derived from the authenticated user's
     * company — never from a route parameter or request body. The
     * reseller token can see all 97 customers on the platform, so this
     * lookup is the only thing standing between one company and
     * another's data. Do not add an overload that takes an id from input.
     */
    public static function adminFor(int $companyId): ?self
    {
        return static::query()
            ->forCompany($companyId)
            ->where('role', self::ROLE_ADMIN)
            ->first();
    }
}
