<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\Scope\LegacySandboxGuard;
use App\Services\Onboarding\Scope\LegacyScopeRefused;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CD-PORT — declare this database a legacy sandbox, or report whether it is one.
 *
 * Owner decision 2026-09-16, superseding O-1: Como runs in its **own copy** of the development
 * database, not inside it. Two adversarial verification rounds found two different routes by which
 * this pipeline's row attribution reached City Travelers data on a shared schema. The fix is not a
 * third attribution scheme — it is not sharing. {@see LegacySandboxGuard} holds the full account.
 *
 * `--status` is the default and writes nothing. `--mark` is the deliberate act, and it refuses
 * unless `LEGACY_SANDBOX_DATABASE` already names the database this application is connected to, so
 * this command cannot be the thing that accidentally declares a shared one.
 */
class LegacySandboxCommand extends Command
{
    protected $signature = 'legacy:sandbox
                            {--mark : Declare this database a legacy sandbox by stamping the ct_legacy_sandbox marker. Refuses unless LEGACY_SANDBOX_DATABASE already names this exact database}
                            {--note= : A short note stored with the marker, e.g. the dump sha256 it was restored from}
                            {--status : Report only (the default)}';

    protected $description = 'CD-PORT — declare this database a legacy sandbox, or report whether it is one. The legacy:* pipeline refuses to run anywhere else.';

    public function handle(LegacySandboxGuard $guard): int
    {
        $live = (string) DB::connection()->getDatabaseName();
        $declared = config('legacy_pilot.ct_scope.sandbox_database');

        $this->line(sprintf('connected database        : %s', $live));
        $this->line(sprintf('LEGACY_SANDBOX_DATABASE   : %s', is_string($declared) && $declared !== '' ? $declared : '(unset)'));

        if (! $this->option('mark')) {
            try {
                $guard->assertSandbox();
                $this->info('This database IS a declared legacy sandbox. legacy:* commands will run here.');
            } catch (LegacyScopeRefused $e) {
                $this->warn('This database is NOT a legacy sandbox.');
                $this->line($e->getMessage());
            }

            return self::SUCCESS;
        }

        try {
            $result = $guard->mark((string) ($this->option('note') ?? ''));
        } catch (LegacyScopeRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Marked `%s` as a legacy sandbox. Token %s. The legacy:* pipeline will now run here '.
            'and nowhere else.',
            $result['database'],
            $result['token']
        ));

        $this->warn(
            'Reminder: this marker travels with the data. A dump of this schema restored under '.
            'another name will refuse until it is deliberately re-stamped — which is the point.'
        );

        return self::SUCCESS;
    }
}
