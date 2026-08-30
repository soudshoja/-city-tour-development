<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operator gesture for the W0 posting-engine kill switch.
 *
 * Before this command existed, the ONLY way to flip companies.posting_engine_enabled was
 * $company->update([...]) — which silently no-op'd (column absent from Company::$fillable,
 * no $casts entry), so an emergency rollback issued that way reported success while the engine
 * kept writing (the "fake lever" failure mode). Company::$fillable/$casts now include the
 * column (see the 2026_08_24_120005 migration's docblock), and THIS command is the supported
 * way an operator drives it — it uses mass-assignment ($company->update()) precisely so a
 * regression in the model's mass-assignment guard would break this command's own test, not
 * just a silent write.
 *
 * Prints the value read BACK FROM THE DB (fresh query, not the mutated in-memory model) for
 * both before and after, plus the independent global config('accounting.engine.enabled')
 * flag, so an operator can see both halves of the gate that
 * PostingService::post() checks (PostingService.php, W0 kill-switch gate) in one place.
 *
 * Registered by auto-discovery: App\Console\Kernel::commands() calls
 * $this->load(__DIR__.'/Commands'), the same mechanism that registers
 * App\Console\Commands\SeedAccountingSerialSchemas — no separate registration step needed.
 */
class AccountingEngine extends Command
{
    protected $signature = 'accounting:engine
                            {company : Company id}
                            {--enable : Turn the per-company posting engine flag ON}
                            {--disable : Turn the per-company posting engine flag OFF}
                            {--status : Only print the current state; write nothing}';

    protected $description = 'Read or flip companies.posting_engine_enabled for one company (the per-company half of the W0 posting-engine kill switch).';

    public function handle(): int
    {
        $enable = (bool) $this->option('enable');
        $disable = (bool) $this->option('disable');
        $statusOnly = (bool) $this->option('status');

        $modeCount = ($enable ? 1 : 0) + ($disable ? 1 : 0) + ($statusOnly ? 1 : 0);

        if ($modeCount === 0) {
            $this->error('Specify exactly one of --enable, --disable, or --status.');

            return self::INVALID;
        }

        if ($modeCount > 1) {
            $this->error('--enable, --disable, and --status are mutually exclusive; specify exactly one.');

            return self::INVALID;
        }

        $companyId = (int) $this->argument('company');
        $company = Company::find($companyId);

        if ($company === null) {
            $this->error("No company found with id #{$companyId}.");

            return self::FAILURE;
        }

        $before = (bool) DB::table('companies')->where('id', $companyId)->value('posting_engine_enabled');

        if (! $statusOnly) {
            $company->update(['posting_engine_enabled' => $enable]);
        }

        $after = (bool) DB::table('companies')->where('id', $companyId)->value('posting_engine_enabled');

        $globalEnabled = (bool) config('accounting.engine.enabled');

        $this->info("Company #{$companyId} ({$company->name})");
        $this->line('  posting_engine_enabled (DB, before): '.($before ? 'true' : 'false'));

        if ($statusOnly) {
            $this->line('  posting_engine_enabled (DB, current): '.($after ? 'true' : 'false'));
        } else {
            $this->line('  posting_engine_enabled (DB, after):  '.($after ? 'true' : 'false'));
        }

        $this->line('  config(accounting.engine.enabled):   '.($globalEnabled ? 'true' : 'false').' (global, unaffected by this command)');

        if (! $statusOnly && $before === $after) {
            $this->warn('  Value unchanged — company was already '.($after ? 'enabled' : 'disabled').'.');
        }

        $effectivelyPosting = $globalEnabled && $after;
        $this->line('  Effective (both flags true): '.($effectivelyPosting ? 'YES — engine will post for this company' : 'no — PostingService::post() will refuse'));

        return self::SUCCESS;
    }
}
