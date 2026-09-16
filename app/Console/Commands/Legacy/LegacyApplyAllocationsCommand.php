<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\Replay\LegacyAllocationReplayer;
use App\Console\Commands\Legacy\Concerns\GuardsLegacyScope;
use Illuminate\Console\Command;

/**
 * legacy-ledger-pilot LP3 -- `php artisan legacy:apply-allocations`.
 *
 * MAPPING-RULES.md §3: replay `tblAccIsApply` in ModDt order AFTER both sides of
 * each pair are posted. See {@see LegacyAllocationReplayer}'s docblock for the
 * one documented deviation (the pilot's own `map_allocation` register instead of
 * `payment_applications`) and why §3.3 makes that deviation harmless to parity.
 *
 * A mismatched allocation is a TAG, never a stop (§3.3), so this command exits
 * zero with tags reported -- unlike `legacy:replay`, where a refusal is a
 * failure. Tags are LP4 check 6 input, not a gate.
 */
class LegacyApplyAllocationsCommand extends Command
{
    use GuardsLegacyScope;

    protected $signature = 'legacy:apply-allocations
        {--dry-run : Resolve and report every allocation, writing nothing}
        {--limit= : Stop after this many staged allocation rows}
        {--company= : Company id (defaults to legacy_pilot.default_company_id)}';

    protected $description = 'Replay staged legacy allocations (tblAccIsApply) against the replayed journal lines (legacy-ledger-pilot LP3).';

    public function handle(LegacyAllocationReplayer $replayer): int
    {
        $companyId = $this->option('company') !== null
            ? (int) $this->option('company')
            : (int) config('legacy_pilot.default_company_id');

        // ── CD-PORT ──────────────────────────────────────────────────────────────────────────
        // The only edit this command carries relative to the Akeed-Ai original. Three gates, all
        // before the first write: quarantined staging connection, target company (R-CO4), reserved
        // id band. See App\Console\Commands\Legacy\Concerns\GuardsLegacyScope.
        if ($this->legacyScopeOrFail($companyId) === null) {
            return self::FAILURE;
        }

        $summary = $replayer->run([
            'company_id' => $companyId,
            'dry_run' => (bool) $this->option('dry-run'),
            'limit' => $this->option('limit') !== null ? (int) $this->option('limit') : null,
        ]);

        $this->line(sprintf('run_id          : %s%s', $summary['run_id'], $summary['dry_run'] ? '  (DRY RUN — nothing was written)' : ''));
        $this->line(sprintf('staged rows seen: %d', $summary['seen']));
        $this->line(sprintf('applied         : %d', $summary['applied']));
        $this->line(sprintf('already applied : %d', $summary['already_applied']));
        $this->line(sprintf('tagged          : %d', $summary['tagged']));

        if ($summary['tag_reasons'] !== []) {
            $rows = [];

            foreach ($summary['tag_reasons'] as $code => $count) {
                $rows[] = [$code, $count];
            }

            $this->table(['Tag reason', 'Count'], $rows);
        }

        $this->info('legacy:apply-allocations complete. Tags are LP4 check 6 input, never a ledger adjustment — an allocation carries no money.');

        return self::SUCCESS;
    }
}
