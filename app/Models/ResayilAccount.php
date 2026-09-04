<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * Security fix (2026-08-26, blocker 2b): a Resayil customer was found
     * under this user's email, but ownership was never proven — email
     * equality against a live customer of a THIRD-PARTY system is not
     * proof this TravelERP company controls that account. This status
     * means "candidate recorded, nothing linked, nothing captured" — see
     * ResayilProvisioningService::recordAdoptionCandidate() /
     * confirmAdoption(). No workspace card, subscription, payment history
     * or account key is ever rendered from a row in this status: unlike
     * every other status, `resayil_customer_id` is deliberately left NULL
     * until a human confirms the match via
     * `resayil:provision-company --confirm-adoption`.
     */
    public const STATUS_ADOPTION_PENDING = 'adoption_pending';

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
        'webhook_secret',
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

    /**
     * Security fix (sec/resayil-webhook): Resayil's register-webhook body
     * (`{name, device, url, events}`) carries no signature/secret of its
     * own — see the resayil-whatsapp-api skill's webhooks reference.
     * Security is entirely ours: a random per-company secret is embedded
     * in the webhook URL path we register with Resayil
     * (`/webhook/resayil/media/{secret}`, `/webhook/resayil/{secret}`) and
     * VerifyResayilWebhookSecret resolves the company from it.
     *
     * Only the SHA-256 digest is ever persisted (`webhook_secret`). This
     * method is idempotent: if a secret already exists it returns null
     * (the plaintext cannot be recovered from the digest) rather than
     * silently rotating it and breaking an already-registered webhook.
     * Call it once per admin row and persist the returned plaintext into
     * the URL registered with Resayil at that moment — it will not be
     * retrievable again.
     */
    public function ensureWebhookSecret(): ?string
    {
        if (! empty($this->webhook_secret)) {
            return null;
        }

        $plain = Str::random(48);
        $this->webhook_secret = hash('sha256', $plain);
        $this->save();

        return $plain;
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
        // orderBy('id') is not cosmetic. Two concurrent provisioning runs
        // for the same company can, in the worst case, both insert an admin
        // row (the unique key is on company_id + user_id, not on role). With
        // no ordering, which workspace the panel shows would then be
        // whatever the storage engine returned first, and could differ
        // between two page loads. Oldest row wins, always: it is the one
        // whose customer id every other row already copied.
        return static::query()
            ->forCompany($companyId)
            ->where('role', self::ROLE_ADMIN)
            ->orderBy('id')
            ->first();
    }
}
