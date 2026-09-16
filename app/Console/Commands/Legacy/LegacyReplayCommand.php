<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\Replay\LegacyReplayAborted;
use App\Services\Onboarding\Replay\LegacyReplayRunner;
use App\Console\Commands\Legacy\Concerns\GuardsLegacyScope;
use Illuminate\Console\Command;

/**
 * legacy-ledger-pilot LP3 -- `php artisan legacy:replay`.
 *
 * Chronological, idempotent, resumable replay of the staged 2025 legacy ledger
 * through {@see \App\Services\Accounting\PostingSeam} (PLAN.md §LP3).
 *
 * GATE ON THE OUTPUT, NEVER THE EXIT CODE is the standing rule for this phase
 * (PLAN §3.5, R9 "vacuous green") -- so this command does the opposite of the
 * seeders it sits next to: it exits NON-ZERO whenever anything at all was
 * refused or any type was stopped, and prints the reason. A green exit here
 * means `refused = 0` and no type stop, which is O5's own pass line.
 */
class LegacyReplayCommand extends Command
{
    use GuardsLegacyScope;

    protected $signature = 'legacy:replay
        {--year= : The DocDt year to replay (defaults to the configured window)}
        {--type= : Replay one SubType only (INV, FRV, BPV, ...)}
        {--from-doc= : Resume from this legacy DocID onwards}
        {--dry-run : Map every document and report, writing nothing at all}
        {--limit= : Stop after this many documents}
        {--company= : Company id (defaults to legacy_pilot.default_company_id)}
        {--user= : The staging replay user id stamped on every document}';

    protected $description = 'Replay the staged legacy 2025 ledger through the posting seam (legacy-ledger-pilot LP3).';

    public function handle(LegacyReplayRunner $runner): int
    {
        $options = [
            'company_id' => $this->option('company') !== null ? (int) $this->option('company') : (int) config('legacy_pilot.default_company_id'),
            'user_id' => $this->option('user') !== null ? (int) $this->option('user') : 0,
            'year' => $this->option('year') !== null ? (int) $this->option('year') : null,
            'type' => $this->option('type'),
            'from_doc' => $this->option('from-doc') !== null ? (int) $this->option('from-doc') : null,
            'limit' => $this->option('limit') !== null ? (int) $this->option('limit') : null,
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        // ── CD-PORT ──────────────────────────────────────────────────────────────────────────
        // Gated even for --dry-run. A dry run reads staged rows and resolves accounts for the
        // TARGET COMPANY; pointing that at company 1 would read City Travelers' chart and report
        // a mapping built from it, which is a wrong answer delivered confidently rather than a
        // harmless no-op. The gate is on the command, not on the write.
        if ($this->legacyScopeOrFail((int) $options['company_id']) === null) {
            return self::FAILURE;
        }

        $this->warnIfVerifyGroupNamesAreNotOverridden();

        $bar = null;

        try {
            $summary = $runner->run($options, function (array $outcome) use (&$bar): void {
                $bar ??= $this->output->createProgressBar();
                $bar->advance();
            });
        } catch (LegacyReplayAborted $e) {
            $this->newLine();
            $this->error('RUN ABORTED: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar?->finish();
        $this->newLine(2);

        $this->printSummary($summary);

        if (! $summary['passed']) {
            $this->error('legacy:replay finished with refusals and/or type stops — see legacy_pilot.map_document.');

            return self::FAILURE;
        }

        $this->info('legacy:replay complete: zero refusals, zero type stops.');

        return self::SUCCESS;
    }

    /**
     * O7-verify (MAPPING-RULES §1.7 / §11). `RvPvInvariantChecker` matches
     * account group names EXACTLY against config('accounting.engine.*'), and the
     * legacy chart's groups are named differently, so on an un-overridden
     * instance every replayed RV/PV document is later reported as a violation.
     * The replay does not mutate global accounting config (a staging override
     * belongs in staging's config, not in a command's side effects) -- it says so
     * loudly instead.
     */
    private function warnIfVerifyGroupNamesAreNotOverridden(): void
    {
        $expected = [
            'accounting.engine.bank_group_name' => (string) config('legacy_pilot.replay.verify.bank_group_name'),
            'accounting.engine.cash_group_name' => (string) config('legacy_pilot.replay.verify.cash_group_name'),
        ];

        foreach ($expected as $key => $want) {
            $have = (string) config($key);

            if ($have !== $want) {
                $this->warn(sprintf(
                    'O7-verify: %s is "%s" but the legacy chart uses "%s". accounting:verify\'s cash/bank rule will report every replayed RV/PV document until this is overridden on the pilot instance.',
                    $key,
                    $have,
                    $want
                ));
            }
        }
    }

    /** @param array<string, mixed> $summary */
    private function printSummary(array $summary): void
    {
        $this->line(sprintf('run_id            : %s%s', $summary['run_id'], $summary['dry_run'] ? '  (DRY RUN — nothing was written)' : ''));
        $this->line(sprintf('selected          : %d', $summary['selected']));
        $this->line(sprintf('posted            : %d', $summary['posted']));

        if ($summary['dry_run']) {
            $this->line(sprintf('mappable          : %d', $summary['mappable']));
        }

        $this->line(sprintf('skipped by rule   : %d', $summary['skipped']));
        $this->line(sprintf('already posted    : %d', $summary['already_posted']));
        $this->line(sprintf('refused           : %d', $summary['refused']));
        $this->line(sprintf('skipped after stop: %d', $summary['skipped_after_type_stop']));

        if ($summary['by_type'] !== []) {
            $this->newLine();
            $rows = [];

            foreach ($summary['by_type'] as $type => $statuses) {
                ksort($statuses);
                $parts = [];

                foreach ($statuses as $status => $count) {
                    $parts[] = $status.'='.$count;
                }

                $rows[] = [$type, implode(' ', $parts), $summary['type_stop_reasons'][$type] ?? ''];
            }

            $this->table(['SubType', 'Outcomes', 'TYPE STOP reason'], $rows);
        }

        if ($summary['refusal_codes'] !== []) {
            $this->newLine();
            $rows = [];

            foreach ($summary['refusal_codes'] as $code => $count) {
                $rows[] = [$code, $count];
            }

            $this->table(['Refusal code', 'Count'], $rows);
        }
    }
}
