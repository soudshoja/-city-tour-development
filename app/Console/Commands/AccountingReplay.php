<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\Replay\CommissionReplaySource;
use App\Services\Accounting\Replay\IssuanceReplaySource;
use App\Services\Accounting\Replay\ReassignReplaySource;
use App\Services\Accounting\Replay\ReceiptReplaySource;
use App\Services\Accounting\Replay\RefundReplaySource;
use App\Services\Accounting\Replay\ReplayOutcome;
use App\Services\Accounting\Replay\ReplaySource;
use App\Services\Accounting\Replay\SaleReplaySource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CT-A3 wave 2, item W2-1 — the cutover backfill command the branch did not have.
 *
 * CT-A2 §1.4 recorded it plainly: *"There is no replay/backfill command on this branch."* That
 * lane had to write one by hand, outside the repo, twice; CT-A3 wave 1 had to extend it a third
 * time for the issuance feeder, and its §7 left this as the wave-2 item. The phase plan's E9 calls
 * its blast radius "the whole migration" — without it, a company cannot be moved onto the engine
 * at all, because 75,221 legacy journal rows have no engine counterpart and nothing can create
 * them.
 *
 * ── What it is ──────────────────────────────────────────────────────────────────────────────────
 * A driver, not a second posting engine. Every document class is a {@see ReplaySource} that calls
 * the production feeder (or, where the feeder is a controller method, that method itself) under
 * the production idempotency key. It never writes a journal row itself; it never touches a legacy
 * row (`posting_date IS NULL` / `idempotency_key IS NULL` rows are not selected, updated or
 * deleted by any source).
 *
 * ── Idempotency ─────────────────────────────────────────────────────────────────────────────────
 * A second run posts ZERO documents. That is not a check this command performs — it is a property
 * of the keys: {@see \App\Services\Accounting\PostingService::post()} step 1 returns the existing
 * document for a duplicate `(company_id, idempotency_key)` pair rather than posting again, and
 * every source reports that case as `already_posted`. The regression test asserts it end to end.
 *
 * ── --dry-run ───────────────────────────────────────────────────────────────────────────────────
 * Wraps the WHOLE run in one database transaction and rolls it back. So a dry run exercises the
 * real feeders, the real account resolution and all twelve PostingService guards, and therefore
 * reports the refusals a real run would actually hit, with their real exception classes — rather
 * than the subset someone remembered to re-implement in a simulator. Nothing is written. (Auto-
 * increment counters and the serial sequence do advance, as they do for any rolled-back
 * transaction; no ledger row survives.)
 *
 * ── The engine gate ─────────────────────────────────────────────────────────────────────────────
 * Refuses to run unless BOTH halves of the kill switch are on for the company, and names both in
 * the message. CT-A2 §1.1 measured exactly this trap on the dev box: the global half was absent
 * from `.env` while the per-company column was the one everybody looked at, so "the engine is on"
 * was true and false at the same time. A backfill that started under a half-open gate would post
 * some classes and silently no-op others.
 */
class AccountingReplay extends Command
{
    protected $signature = 'accounting:replay
        {--company= : Company id to replay (required)}
        {--class=all : sale|commission|receipt|issuance|reassign|refund|all}
        {--from= : Only documents dated on/after this date (Y-m-d)}
        {--to= : Only documents dated on/before this date (Y-m-d)}
        {--dry-run : Post inside a transaction and roll it back — reports exactly what a real run would do, writes nothing}
        {--limit= : Cap the rows walked per class (smoke-testing a big backfill)}
        {--receipt-statuses= : FOR THIS RUN ONLY, the invoice_receipts statuses the receipt class treats as posting (e.g. approved,pending). Overrides config(accounting.receipt.posting_statuses); never persisted, never changes live behaviour}';

    protected $description = 'Replay historical documents through the posting engine under each feeder\'s own idempotency key (cutover backfill).';

    /**
     * The order classes run in, whatever order the operator types them in.
     *
     * `sale` FIRST, then `issuance`. Both orders are correct -- SaleReplaySource reverses any
     * accrual it supersedes, exactly as the live feeder does -- but this one produces the ledger
     * the live system would have produced, with no churn. Wave 1's ruling is that a task which
     * auto-invoices never accrues at all (its cost goes straight to COGS on the sale); running
     * issuance first instead accrues 3,357 already-invoiced tasks and then immediately reverses
     * every one of them, which is 6,714 audited documents saying nothing. Measured on the City
     * Travelers scratch database: issuance-first posts 9,033 accruals worth KWD 1,378,062.288,
     * sale-first posts 5,676 worth 897,356.597 -- the same figure CT-A3 wave 1 recorded.
     *
     * `refund` LAST: it reverses the sale and the commission, so both must exist first.
     */
    private const CLASS_ORDER = ['sale', 'commission', 'issuance', 'receipt', 'reassign', 'refund'];

    public function handle(): int
    {
        $companyId = (int) $this->option('company');

        if ($companyId <= 0) {
            $this->error('--company is required (an integer company id).');

            return self::FAILURE;
        }

        if (! $this->assertEngineGate($companyId)) {
            return self::FAILURE;
        }

        $classes = $this->resolveClasses((string) $this->option('class'));

        if ($classes === null) {
            return self::FAILURE;
        }

        // ── --receipt-statuses, and why it exists ──────────────────────────────────────────
        // On the City Travelers data 104 of the 109 `invoice_receipts` rows sit at `pending`, and
        // W2-2's configured vocabulary correctly says a pending voucher has no ledger footprint --
        // that IS the W5.R lifecycle. But CT-A1 CT-F12 names those same 104 rows as a DEFECT
        // ("104 of 109 unposted ... posting on approve was never wired"): the money was received
        // and the approval path did not exist to record it. Both readings are defensible and only
        // the owner can settle which applies to a given row.
        //
        // So the command will not silently decide. The default is the live vocabulary (pending
        // does not post, and the run reports `status_is_draft` with an exact count); an operator
        // who has decided those receipts are real passes them explicitly on the command line,
        // where the decision is visible in the shell history and echoed in the run header. This
        // never writes to config and never changes live behaviour -- it is scoped to the process.
        $receiptStatusesBefore = config('accounting.receipt.posting_statuses');

        if ($this->option('receipt-statuses') !== null) {
            $statuses = array_values(array_filter(array_map(
                static fn ($s) => strtolower(trim((string) $s)),
                explode(',', (string) $this->option('receipt-statuses'))
            )));

            config(['accounting.receipt.posting_statuses' => $statuses]);

            $this->warn('Receipt posting statuses OVERRIDDEN for this run: '.implode(', ', $statuses));
            $this->warn('  (the config file is untouched, and the value is restored when this run ends.)');
        }

        $from = $this->option('from') ? Carbon::parse((string) $this->option('from')) : null;
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to')) : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->line(sprintf(
            'accounting:replay — company #%d, class=%s%s%s%s',
            $companyId,
            implode(',', $classes),
            $from ? ', from '.$from->toDateString() : '',
            $to ? ', to '.$to->toDateString() : '',
            $dryRun ? ' [DRY RUN — nothing will be written]' : ''
        ));

        $openedAt = null;

        if ($dryRun) {
            DB::beginTransaction();
            $openedAt = DB::transactionLevel();
        }

        try {
            $report = $this->runClasses($classes, $companyId, $from, $to, $limit, $dryRun);
        } finally {
            // Guarded rather than a bare rollBack(): a feeder whose own inner DB::transaction()
            // aborted can leave the connection at a lower transaction level than we opened at,
            // and an unguarded rollBack() then throws "There is no active transaction" -- which
            // would replace the run's real report with a driver error.
            if ($openedAt !== null && DB::transactionLevel() >= $openedAt) {
                DB::rollBack($openedAt - 1);
            }

            // Restored whether the run succeeded or threw, so the override cannot leak out of this
            // command into a long-lived process (a queue worker driving it through Artisan::call,
            // or a test suite). The claim "this never changes live behaviour" has to hold in
            // process, not only because the CLI happens to exit afterwards.
            config(['accounting.receipt.posting_statuses' => $receiptStatusesBefore]);
        }

        $this->printReport($report, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Both halves of the kill switch, named individually. Mirrors
     * {@see PostingSeam::isEnabledFor()} rather than re-deriving it, so this gate and the engine's
     * own can never disagree.
     */
    private function assertEngineGate(int $companyId): bool
    {
        $globally = (bool) config('accounting.engine.enabled');
        $company = Company::find($companyId);
        $perCompany = $company !== null && (bool) $company->posting_engine_enabled;

        if ($company === null) {
            $this->error(sprintf('Company #%d does not exist.', $companyId));

            return false;
        }

        if ($globally && $perCompany) {
            return true;
        }

        // Deliberately several short, separate lines rather than one wrapped block: the console
        // wraps a long error() body at the terminal width, which makes the message unreadable in
        // a narrow shell and unassertable in a test.
        $this->error(sprintf('The posting engine is NOT enabled for company #%d - refusing to replay.', $companyId));
        $this->line(sprintf("  global  : config('accounting.engine.enabled')  = %s", $globally ? 'true' : 'false'));
        $this->line('            (env ACCOUNTING_ENGINE_ENABLED)');
        $this->line(sprintf('  company : companies.posting_engine_enabled = %s', $perCompany ? 'true' : 'false'));
        $this->line(sprintf('            (php artisan accounting:engine %d --enable)', $companyId));
        $this->line('Both halves must be on. A backfill under a half-open gate posts some classes');
        $this->line('and silently no-ops others (CT-A2 Â§1.1).');

        return false;
    }

    /** @return string[]|null */
    private function resolveClasses(string $option): ?array
    {
        $option = strtolower(trim($option));

        if ($option === 'all' || $option === '') {
            return self::CLASS_ORDER;
        }

        $requested = array_values(array_filter(array_map('trim', explode(',', $option))));
        $unknown = array_diff($requested, self::CLASS_ORDER);

        if ($unknown !== []) {
            $this->error('Unknown --class value(s): '.implode(', ', $unknown).'. Valid: '.implode('|', self::CLASS_ORDER).'|all');

            return null;
        }

        // Always run in dependency order, whatever order the operator typed them in.
        return array_values(array_filter(self::CLASS_ORDER, fn ($c) => in_array($c, $requested, true)));
    }

    private function sourceFor(string $class): ReplaySource
    {
        return match ($class) {
            'sale' => app(SaleReplaySource::class),
            'commission' => app(CommissionReplaySource::class),
            'receipt' => app(ReceiptReplaySource::class),
            'issuance' => app(IssuanceReplaySource::class),
            'reassign' => app(ReassignReplaySource::class),
            'refund' => app(RefundReplaySource::class),
            default => throw new \InvalidArgumentException("Unknown replay class '{$class}'."),
        };
    }

    /**
     * @param  string[]  $classes
     * @return array<string, array{posted:int, already:int, skipped:int, refused:int, amount:float, skip_reasons:array<string,int>, refusals:array<string,array{count:int, ids:array<int,int|string>}>}>
     */
    private function runClasses(array $classes, int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit, bool $dryRun): array
    {
        $report = [];

        foreach ($classes as $class) {
            $source = $this->sourceFor($class);
            $tally = ['posted' => 0, 'already' => 0, 'skipped' => 0, 'refused' => 0, 'amount' => 0.0, 'skip_reasons' => [], 'refusals' => []];

            foreach ($source->rows($companyId, $from, $to, $limit) as $row) {
                try {
                    $outcome = $source->replay($row);
                } catch (\Throwable $e) {
                    // A source is contracted not to throw for an ordinary refusal; anything that
                    // escapes anyway is recorded against the row and the run continues, because a
                    // 12,000-document backfill must not die on row 4,001.
                    $outcome = ReplayOutcome::refused($row->getKey(), $e->getMessage(), $e);
                }

                match ($outcome->status) {
                    ReplayOutcome::POSTED => $outcome->alreadyPosted
                        ? $tally['already']++
                        : $tally['posted']++,
                    ReplayOutcome::SKIPPED => $tally['skipped']++,
                    ReplayOutcome::REFUSED => $tally['refused']++,
                    default => null,
                };

                if ($outcome->status === ReplayOutcome::POSTED && ! $outcome->alreadyPosted) {
                    $tally['amount'] += (float) ($outcome->amount ?? 0);
                }

                if ($outcome->status === ReplayOutcome::SKIPPED) {
                    $tally['skip_reasons'][$outcome->reason] = ($tally['skip_reasons'][$outcome->reason] ?? 0) + 1;
                }

                if ($outcome->isRefused()) {
                    $bucket = $outcome->bucket();
                    $tally['refusals'][$bucket] ??= ['count' => 0, 'ids' => [], 'message' => $outcome->reason];
                    $tally['refusals'][$bucket]['count']++;

                    // Ids are capped so one systemic refusal cannot produce a 3,000-line report;
                    // the count is always exact.
                    if (count($tally['refusals'][$bucket]['ids']) < 25) {
                        $tally['refusals'][$bucket]['ids'][] = $outcome->sourceId;
                    }
                }
            }

            $report[$class] = $tally;
        }

        return $report;
    }

    /** @param array<string, array<string, mixed>> $report */
    private function printReport(array $report, bool $dryRun): void
    {
        $postedLabel = $dryRun ? 'WOULD POST' : 'POSTED';

        $this->line('');
        $this->table(
            ['class', $postedLabel, 'ALREADY', 'SKIPPED', 'REFUSED', 'amount (base)'],
            array_map(
                fn (string $class, array $t) => [
                    $class,
                    (string) $t['posted'],
                    (string) $t['already'],
                    (string) $t['skipped'],
                    (string) $t['refused'],
                    number_format((float) $t['amount'], 3),
                ],
                array_keys($report),
                array_values($report)
            )
        );

        foreach ($report as $class => $tally) {
            if ($tally['skip_reasons'] !== []) {
                arsort($tally['skip_reasons']);
                $this->line('');
                $this->line("  {$class} — skipped, by reason:");

                foreach ($tally['skip_reasons'] as $reason => $count) {
                    $this->line(sprintf('    %-32s %d', $reason, $count));
                }
            }

            if ($tally['refusals'] !== []) {
                $this->line('');
                $this->warn("  {$class} — REFUSED, by reason:");

                foreach ($tally['refusals'] as $bucket => $info) {
                    $this->line(sprintf('    %-32s %d  ids: %s%s',
                        $bucket,
                        $info['count'],
                        implode(',', $info['ids']),
                        $info['count'] > count($info['ids']) ? ',…' : ''
                    ));
                    $this->line(sprintf('      %s', \Illuminate\Support\Str::limit((string) ($info['message'] ?? ''), 200)));
                }
            }
        }

        $totalPosted = array_sum(array_column($report, 'posted'));
        $totalRefused = array_sum(array_column($report, 'refused'));

        $this->line('');
        $this->line(sprintf(
            '%s %d document(s); %d refused.%s',
            $dryRun ? 'Would post' : 'Posted',
            $totalPosted,
            $totalRefused,
            $dryRun ? ' [dry run: rolled back, nothing written]' : ''
        ));
    }
}
