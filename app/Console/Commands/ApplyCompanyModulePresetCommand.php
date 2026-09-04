<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Setting;
use App\Support\Entitlements\ApplyCompanyModulePreset;
use App\Support\Modules;
use Illuminate\Console\Command;

/**
 * Retrofit — or make a one-off correction to — a company's module.*
 * entitlement rows.
 *
 * Companion to the two automatic write paths (AdminUsersController::store
 * for operator-entered companies, CompanyProvisioner::provision() for
 * invite-token self-registration). Use this command for a company that
 * predates both of those, or to deliberately override a single module on
 * an already-provisioned company (e.g. turning module.accounting on for
 * one client as a paid upgrade).
 *
 * Safety posture, by design:
 *   - --dry-run defaults ON. You must pass --dry-run=0 to write anything.
 *   - Even with --dry-run=0, a write still requires --force or an
 *     interactive "yes" at the confirmation prompt — there is no path
 *     that writes silently.
 *   - The write itself (ApplyCompanyModulePreset::apply) is idempotent —
 *     running this command twice with the same preset updates the same
 *     rows in place, never duplicates them.
 *
 * See also: `modules:show` — read-only, no --dry-run/--force gymnastics,
 * for just inspecting a company's current state.
 */
class ApplyCompanyModulePresetCommand extends Command
{
    protected $signature = 'modules:apply-preset
        {company : Company ID or company code}
        {--dry-run=1 : Preview only (default). Pass --dry-run=0 to actually write.}
        {--set=* : Override one module for this run, e.g. --set=accounting=true (repeatable)}
        {--force : Skip the confirmation prompt when writing (required for non-interactive/scripted use)}';

    protected $description = 'Apply (or preview) the package module preset for one company — retrofit an existing company or correct a single module';

    public function handle(): int
    {
        $identifier = (string) $this->argument('company');
        $company = $this->resolveCompany($identifier);

        if (! $company) {
            $this->error("No company found matching '{$identifier}' (tried numeric id, then exact code).");

            return self::FAILURE;
        }

        $preset = config('modules.package_preset', []);

        foreach ((array) $this->option('set') as $raw) {
            if (! str_contains($raw, '=')) {
                $this->error("Invalid --set value '{$raw}'. Expected key=bool, e.g. --set=accounting=true.");

                return self::FAILURE;
            }

            [$key, $value] = explode('=', $raw, 2);
            $key = trim($key);
            $bool = $this->parseBool($value);

            if (is_null($bool)) {
                $this->error("Invalid boolean for --set={$key}=... : '{$value}'. Use true/false, 1/0, on/off, or yes/no.");

                return self::FAILURE;
            }

            if (! in_array($key, Modules::ALL, true)) {
                $this->warn("'{$key}' is not a recognized module key (".implode(', ', Modules::ALL).'), writing it anyway.');
            }

            $preset[$key] = $bool;
        }

        $dryRun = ! in_array(strtolower((string) $this->option('dry-run')), ['0', 'false', 'no'], true);

        $diff = $this->buildDiff($company, $preset);

        $this->info("Company #{$company->id} — {$company->name} (code: {$company->code})");
        $this->newLine();
        $this->table(['Module', 'Current', 'New', 'Changes?'], $diff['rows']);

        if ($dryRun) {
            $this->newLine();
            $this->comment("Dry run — nothing written. {$diff['changeCount']} of ".count($preset).' row(s) would change. Pass --dry-run=0 to apply.');

            return self::SUCCESS;
        }

        // Merge fixup (MERGE-PLAN-DEV-INTO-LAUNCH-2026-09-04.md §3, follow-up to the
        // fail-closed buildDiff() fixup above): changeCount now measures EFFECTIVE
        // behavioral difference, not row existence. For a brand-new company with zero
        // rows, config('modules.package_preset') is deliberately set to already match
        // the fail-closed defaults (see 72e05ec67), so changeCount is legitimately 0 —
        // but this command's whole purpose is retrofitting explicit rows onto exactly
        // that kind of company (see class docblock), and skipping here would silently
        // leave it with none, at the mercy of default_disabled changing later. --force
        // is an explicit "just do it" from a human or script, so let it materialize the
        // preset even with nothing effective to change; only the interactive path (no
        // --force) keeps the no-op skip, so a human isn't confirmed into a needless
        // write.
        if ($diff['changeCount'] === 0 && ! $this->option('force')) {
            $this->comment('Nothing to change — every row already matches this preset. No write performed.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                "Write {$diff['changeCount']} settings row(s) for company #{$company->id} ({$company->name}) now? This changes what that company's users can see.",
                false
            );

            if (! $confirmed) {
                $this->warn('Aborted — no changes written. Pass --force to skip this confirmation (e.g. for scripted/non-interactive use).');

                return self::FAILURE;
            }
        }

        app(ApplyCompanyModulePreset::class)->apply($company, $preset);

        $this->info("Applied. {$diff['changeCount']} row(s) written for company #{$company->id}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, bool>  $preset
     * @return array{rows: array<int, array<int, string>>, changeCount: int}
     */
    private function buildDiff(Company $company, array $preset): array
    {
        $rows = [];
        $changeCount = 0;

        $defaultDisabled = (array) config('modules.default_disabled', []);

        foreach ($preset as $module => $newValue) {
            $rawCurrent = Setting::getByKey($company->id, Modules::settingKey($module), null);
            // Merge fixup (MERGE-PLAN-DEV-INTO-LAUNCH-2026-09-04.md §3): this command predates
            // ours' fail-closed default_disabled config and always treated an unset row as
            // "fail-open ON", same bug Company::hasModule() had before that fix. A module listed
            // in default_disabled (accounting) reads OFF with no explicit row, not ON — mirror
            // Company::hasModule()'s own logic here so buildDiff() (and therefore changeCount,
            // and therefore whether a write happens at all) agrees with what the app actually
            // enforces, instead of silently skipping the write for an unset accounting row.
            $unsetIsEnabled = ! in_array($module, $defaultDisabled, true);
            $currentLabel = is_null($rawCurrent) ? ('unset (fail-'.($unsetIsEnabled ? 'open ON' : 'closed OFF').')') : ((bool) $rawCurrent ? 'true' : 'false');
            $newLabel = $newValue ? 'true' : 'false';

            $currentEffective = is_null($rawCurrent) ? $unsetIsEnabled : (bool) $rawCurrent;
            $changed = $currentEffective !== (bool) $newValue;

            if ($changed) {
                $changeCount++;
            }

            $rows[] = [$module, $currentLabel, $newLabel, $changed ? 'yes' : 'no'];
        }

        return ['rows' => $rows, 'changeCount' => $changeCount];
    }

    private function parseBool(string $value): ?bool
    {
        $value = strtolower(trim($value));

        return match ($value) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => null,
        };
    }

    private function resolveCompany(string $identifier): ?Company
    {
        if (ctype_digit($identifier)) {
            return Company::find((int) $identifier);
        }

        return Company::where('code', $identifier)->first();
    }
}
