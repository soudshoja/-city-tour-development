<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Setting;
use App\Support\Entitlements\ApplyCompanyModulePreset;
use App\Support\Modules;
use Illuminate\Console\Command;

/**
 * Reads or writes ONE company's entitlement flag for ONE module.
 *
 * This is the operator-facing counterpart to ApplyCompanyModulePreset,
 * which writes the whole 6-module preset at once. Use this when a single
 * company needs a single module turned on or off without touching its
 * other five flags — most commonly granting `accounting`, which
 * config('modules.default_disabled') otherwise keeps off for every
 * company that has no explicit row.
 *
 * With neither --on nor --off the command only REPORTS, so it is safe to
 * run against production to find out what a company currently has:
 *
 *     php artisan company:set-module 1 accounting
 *     php artisan company:set-module 1 accounting --on
 *     php artisan company:set-module 1 accounting --off
 *
 * Writes go through ApplyCompanyModulePreset::apply() with a one-key
 * override rather than touching `settings` directly, so this command
 * inherits that class's idempotent updateOrCreate and its
 * Company::forgetModuleCache() call for free.
 */
class SetCompanyModule extends Command
{
    protected $signature = 'company:set-module
                            {company : Company id}
                            {module : Module key, one of: task_uploader, payment_gateway, crm, agent_profit, resayil, accounting}
                            {--on : Grant the module - writes an explicit module setting row set to 1}
                            {--off : Revoke the module - writes an explicit module setting row set to 0}';

    protected $description = 'Show or set one company\'s entitlement flag for one module';

    public function handle(ApplyCompanyModulePreset $preset): int
    {
        $module = (string) $this->argument('module');

        if (! in_array($module, Modules::ALL, true)) {
            $this->error(sprintf(
                'Unknown module "%s". Known modules: %s',
                $module,
                implode(', ', Modules::ALL)
            ));

            return self::FAILURE;
        }

        $company = Company::find($this->argument('company'));

        if (! $company) {
            $this->error(sprintf('No company with id %s.', $this->argument('company')));

            return self::FAILURE;
        }

        if ($this->option('on') && $this->option('off')) {
            $this->error('Pass either --on or --off, not both.');

            return self::FAILURE;
        }

        // Read the raw row rather than hasModule(), so the report can tell
        // "explicitly set" apart from "falling back to the default" — the
        // whole point of this command for an operator auditing a tenant.
        $raw = Setting::getByKey($company->id, Modules::settingKey($module), null);

        $this->line(sprintf('Company %d (%s), module "%s":', $company->id, $company->name, $module));
        $this->line(sprintf(
            '  current: %s (%s)',
            $company->hasModule($module) ? 'ON' : 'OFF',
            is_null($raw)
                ? 'no explicit row — falling back to the default in config/modules.php'
                : 'explicit row'
        ));

        if (! $this->option('on') && ! $this->option('off')) {
            $this->line('  (read-only — pass --on or --off to change it)');

            return self::SUCCESS;
        }

        $enabled = (bool) $this->option('on');

        $preset->apply($company, [$module => $enabled]);

        $this->info(sprintf('  written: %s is now %s for company %d.', $module, $enabled ? 'ON' : 'OFF', $company->id));

        return self::SUCCESS;
    }
}
