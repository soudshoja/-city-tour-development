<?php


// app/Models/Agent.php

namespace App\Models;

use App\Support\Modules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'country_id',
        'gds_office_id',
        'status',
        'code',
        'email',
        'logo',
        'iata_code',
        'iata_client_id',
        'iata_client_secret',
        'address',
        'phone',
        'facebook',
        'instagram',
        'snapchat',
        'tiktok',
        'whatsapp',
        'posting_engine_enabled',
    ];

    protected $casts = [
        'posting_engine_enabled' => 'boolean',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function agents()
    {
        return $this->hasManyThrough(Agent::class, Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function nationality()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'supplier_companies')
            ->using(SupplierCompany::class)
            ->withPivot('is_active');
    }

    public function taskRules()
    {
        return $this->hasMany(TaskRules::class);
    }

    /**
     * Get the main/default branch for this company
     * Returns the first branch or creates one if none exists
     */
    public function getMainBranch()
    {
        $mainBranch = $this->branches()->first();
        
        if (!$mainBranch) {
            // Create a default main branch if none exists
            $mainBranch = $this->branches()->create([
                'name' => $this->name . ' - Main Branch',
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'user_id' => $this->user_id,
            ]);
        }
        
        return $mainBranch;
    }

    public function paymentMethodChoses()
    {
        return $this->hasMany(PaymentMethodChose::class);
    }

    /**
     * Per-request memo of hasModule() lookups, keyed by "{companyId}:{module}".
     * Laravel boots a fresh Application per HTTP request under classic
     * php-fpm/Nginx, so this resets naturally between real requests without
     * any explicit invalidation. See forgetModuleCache() for when a single
     * request/test needs to force a re-read (e.g. right after writing a
     * `module.*` setting).
     *
     * @var array<string, bool>
     */
    protected static array $moduleCache = [];

    /**
     * Whether this company has the given package module switched on.
     *
     * Reads the company-scoped `settings` table via the existing
     * Setting::getByKey() accessor, using the `module.{$module}` key
     * convention (see App\Support\Modules::settingKey()).
     *
     * A module with NO settings row at all resolves one of two ways,
     * decided by config('modules.default_disabled'):
     *
     * - NOT in that list (the common case): treated as ON. This is
     *   deliberate — every company that predates this entitlement layer
     *   has zero `module.*` rows, and must keep working exactly as it did
     *   before this flag existed. "Unset" means "not migrated to the
     *   module system yet, so don't restrict it" — not "this company's
     *   preset says on".
     *
     * - IN that list (`accounting` today): treated as OFF. These are the
     *   modules the product does not sell, so no company is entitled to
     *   one without an explicit grant. Failing open for those would expose
     *   the module to exactly the pre-entitlement companies that were
     *   never sold it. See config/modules.php for the reasoning and for
     *   how to grant one.
     *
     * Either way, a brand-new package company is never left to this
     * default: App\Support\Entitlements\ApplyCompanyModulePreset writes an
     * EXPLICIT row for every module (5 on, `accounting` off) so its access
     * never depends on which branch above applies.
     */
    public function hasModule(string $module): bool
    {
        $cacheKey = "{$this->id}:{$module}";

        if (array_key_exists($cacheKey, static::$moduleCache)) {
            return static::$moduleCache[$cacheKey];
        }

        $raw = Setting::getByKey($this->id, Modules::settingKey($module), null);

        $enabled = is_null($raw)
            ? ! in_array($module, (array) config('modules.default_disabled', []), true)
            : (bool) $raw;

        return static::$moduleCache[$cacheKey] = $enabled;
    }

    /**
     * Clear the per-request hasModule() memo. Call this after writing to a
     * company's `module.*` settings within the same request/process that
     * will immediately re-check entitlements — most notably in tests,
     * which reuse one PHP process across many "requests".
     */
    public static function forgetModuleCache(): void
    {
        static::$moduleCache = [];
    }
}
