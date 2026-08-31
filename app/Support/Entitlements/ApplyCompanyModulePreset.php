<?php

namespace App\Support\Entitlements;

use App\Models\Company;
use App\Models\Setting;
use App\Support\Modules;

/**
 * Writes an EXPLICIT `module.*` settings row for a company, one per key in
 * config('modules.package_preset') (5 package modules on, `accounting`
 * off) — or a custom override.
 *
 * This is the only place that should ever turn Company::hasModule()'s
 * fail-safe "unset = on" default into a real, on-purpose per-module
 * decision. Use it to:
 *
 *   - Onboard a brand-new package company (call apply() once, right after
 *     the company is created).
 *   - Re-apply the configured preset to an existing company after a
 *     manual settings change, or to upgrade a legacy company onto the
 *     module system deliberately (rather than leaving it to the default).
 *
 * Not invoked automatically anywhere — a company only gets rows when a
 * human decides to move it onto the package. The
 * `php artisan company:set-module` command (App\Console\Commands * SetCompanyModule) calls apply() with a one-key override to flip a
 * single module without disturbing the other five.
 */
class ApplyCompanyModulePreset
{
    /**
     * Apply a module preset to $company, one settings row per module.
     *
     * Idempotent: re-running updates the existing rows in place rather
     * than duplicating them — the (key, company_id) unique index on
     * `settings` already guarantees at most one row per module per
     * company, and Setting::updateOrCreate() matches on exactly that pair.
     *
     * @param  array<string, bool>|null  $preset  Module key => enabled.
     *         Defaults to config('modules.package_preset'). Pass an
     *         override for a non-standard tier or in a test; unknown keys
     *         (not in Modules::ALL) are still written as-is, since
     *         hasModule() itself never validates the module name either.
     */
    public function apply(Company $company, ?array $preset = null): void
    {
        $preset ??= config('modules.package_preset', []);

        foreach ($preset as $module => $enabled) {
            Setting::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'key' => Modules::settingKey($module),
                ],
                [
                    // 'type' must be set before 'value' so Setting's
                    // setValueAttribute() mutator casts correctly.
                    'type' => 'boolean',
                    'value' => (bool) $enabled,
                    'description' => sprintf(
                        'Package module entitlement flag (%s), managed by %s.',
                        Modules::settingKey($module),
                        static::class
                    ),
                ]
            );
        }

        // The rows just written may already be memoized (e.g. a caller
        // that checked hasModule() earlier in the same request/test) —
        // drop the memo so the next hasModule() call re-reads them.
        Company::forgetModuleCache();
    }
}
