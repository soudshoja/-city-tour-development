<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\RvPvInvariantChecker;
use Illuminate\Console\Command;

/**
 * W5.X (w5-brief.md §W5.X item 4): "accounting:verify invariant checker gains RV/PV checks: every
 * RV/PV doc balanced, no RV/PV line on a non-cash/bank leaf without a counter-leg, no
 * journal_entries row with doc_type RV/PV lacking a serial."
 *
 * No earlier wave shipped an `accounting:verify` command (repo-wide search, 2026-08-29: no file
 * named `*Verify*` under app/Console/Commands, no `accounting:verify` signature anywhere) --
 * "gains" describes the checker's own intended lifecycle (a fixed point later waves add more
 * invariants to), not a rewrite of an existing one. This build creates it fresh, scoped to exactly
 * the three RV/PV checks the brief names — no other invariant is added here. The actual check
 * logic lives in {@see RvPvInvariantChecker} (this command is a thin CLI wrapper around it) so it
 * is directly testable against its return value rather than console output text — see that class's
 * own docblock for why.
 *
 * Read-only: this command never writes. `--company=` narrows to one tenant; omitted, every
 * company's RV/PV documents are checked. Exit code is `self::FAILURE` (1) the moment any violation
 * is found, `self::SUCCESS` (0) otherwise — scriptable for CI/cron (Accounting Gap/22-plan-
 * amendments.md §11 rev 5 "accounting:reconcile --auto ... runs the accounting:verify self-checks"
 * names this exact command as that future caller).
 */
class AccountingVerify extends Command
{
    protected $signature = 'accounting:verify
                            {--company= : Restrict to one company id; omitted checks every company}';

    protected $description = 'Read-only RV/PV posting invariant checker (balanced, cash/bank counter-leg, serial present).';

    public function handle(RvPvInvariantChecker $checker): int
    {
        $companyId = $this->option('company') !== null ? (int) $this->option('company') : null;

        $result = $checker->check($companyId);
        $checked = $result['checked'];
        $violations = $result['violations'];

        if ($violations === []) {
            $this->info("accounting:verify — {$checked} RV/PV document(s) checked, 0 violations.");

            return self::SUCCESS;
        }

        $this->error(sprintf('accounting:verify — %d RV/PV document(s) checked, %d violation(s):', $checked, count($violations)));
        foreach ($violations as $violation) {
            $this->line('  - '.$violation);
        }

        return self::FAILURE;
    }
}
