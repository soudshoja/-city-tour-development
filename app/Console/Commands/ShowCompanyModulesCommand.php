<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Setting;
use App\Support\Modules;
use Illuminate\Console\Command;

/**
 * Read-only companion to modules:apply-preset. Shows a company's actual
 * module.* settings rows (if any) alongside the effective value
 * Company::hasModule() would return for each — so an operator can tell
 * "no row, fail-open ON" apart from "explicit row, ON" before touching
 * anything.
 */
class ShowCompanyModulesCommand extends Command
{
    protected $signature = 'modules:show
        {company : Company ID or company code}
        {--json : Output machine-readable JSON instead of a table}';

    protected $description = "Show a company's current module entitlement state (explicit settings rows vs the fail-open default)";

    public function handle(): int
    {
        $identifier = (string) $this->argument('company');
        $company = $this->resolveCompany($identifier);

        if (! $company) {
            $this->error("No company found matching '{$identifier}' (tried numeric id, then exact code).");

            return self::FAILURE;
        }

        // Union of the known package keys and any stray/legacy module.* rows
        // that exist for this company but aren't in Modules::ALL — so a
        // custom or leftover key is never silently hidden from this report.
        $existingKeys = Setting::where('company_id', $company->id)
            ->where('key', 'like', 'module.%')
            ->pluck('key')
            ->map(fn ($key) => substr($key, strlen('module.')))
            ->all();

        $moduleKeys = array_values(array_unique(array_merge(Modules::ALL, $existingKeys)));
        sort($moduleKeys);

        $rows = [];
        $explicitCount = 0;

        foreach ($moduleKeys as $module) {
            $raw = Setting::getByKey($company->id, Modules::settingKey($module), null);
            $hasRow = ! is_null($raw);
            $effective = $company->hasModule($module);

            if ($hasRow) {
                $explicitCount++;
            }

            $rows[] = [
                'module' => $module,
                'has_row' => $hasRow,
                'explicit_value' => $hasRow ? (bool) $raw : null,
                'effective' => $effective,
                'known' => in_array($module, Modules::ALL, true),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'company_id' => $company->id,
                'company_name' => $company->name,
                'company_code' => $company->code,
                'preset_applied' => $explicitCount === count(Modules::ALL) && $explicitCount === count($moduleKeys),
                'modules' => $rows,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("Company #{$company->id} — {$company->name} (code: {$company->code})");
        $this->newLine();

        $this->table(
            ['Module', 'Row exists?', 'Explicit value', 'Effective (hasModule)', 'Known key?'],
            array_map(fn ($r) => [
                $r['module'],
                $r['has_row'] ? 'yes' : 'no',
                is_null($r['explicit_value']) ? '—' : ($r['explicit_value'] ? 'true' : 'false'),
                $r['effective'] ? 'ON' : 'OFF',
                $r['known'] ? 'yes' : 'unknown key',
            ], $rows)
        );

        if ($explicitCount === 0) {
            $this->comment('No module.* settings rows exist for this company — every module reads as ON via the fail-open default (Company::hasModule()).');
        } elseif ($explicitCount < count(Modules::ALL)) {
            $this->comment("Partial entitlement state: {$explicitCount} of ".count(Modules::ALL).' package modules have an explicit row. Modules without a row still fail open to ON.');
        } else {
            $this->comment('Full package preset shape present: every package module has an explicit row.');
        }

        return self::SUCCESS;
    }

    private function resolveCompany(string $identifier): ?Company
    {
        if (ctype_digit($identifier)) {
            return Company::find((int) $identifier);
        }

        return Company::where('code', $identifier)->first();
    }
}
