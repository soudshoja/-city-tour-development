<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CoaLinkageChange;
use App\Services\Accounting\TaskPayablePositionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CT-A7 ROUND 2, finding **F2** — stamp the PARTY on historical accounts-payable journal lines
 * that were written without one.
 *
 * ── The gap this closes, and why the round-1 deferral was not enough ────────────────────────────
 * CT-A7-2 fixed `BankPaymentController::buildVoucherDraft()`, which wrote every supplier payment
 * voucher's payable debit with `journal_entries.type_reference_id = NULL`. But `voucherPartyRef()`
 * only stamps vouchers posted from now on, and the supplier filter on the creditors and unpaid-AP
 * screens is `where('type_reference_id', $supplierId)`. So **every pre-deploy payment voucher still
 * carries NULL**: filter by a supplier and you still see their invoices and not their payments, and
 * the balance on screen is still gross of everything ever paid. That is R3-10a's exact symptom,
 * still live for all history. Round 1 deferred this and named the shape it had to take; this is
 * that command.
 *
 * ── What it will and will not do ───────────────────────────────────────────────────────────────
 * It derives the party the same way `voucherPartyRef()` does, from the line's own ACCOUNT, using
 * exactly the two columns a supplier's payable leaf is minted with:
 *
 *   - `accounts.supplier_id`, and
 *   - `accounts.supplier_company_id` -> `supplier_companies.supplier_id`.
 *
 * **It refuses to guess.** A line whose account carries neither column is REPORTED and left
 * untouched — never matched by name, never attributed to "the only supplier on the document",
 * never inferred from a sibling line. That matters more here than anywhere else in this lane: the
 * pooled control leaves (`2120 Suppliers (Flights)` and friends) name no supplier at all by
 * construction, so the derivation is self-limiting to leaves that genuinely identify one party.
 * A wrong party is worse than a null one — a null line is invisible to the supplier filter, a
 * wrongly-stamped line appears on the WRONG supplier's statement as a real payment.
 *
 * Scope is `journal_entries` rows that are (a) inside the company's Accounts Payable subtree,
 * (b) not soft-deleted, and (c) currently NULL on `type_reference_id`. Rows that already carry a
 * party are out of scope by definition, which is what makes re-running a no-op.
 *
 * ── Why this is a COMMAND and not a data migration ─────────────────────────────────────────────
 * It must run AFTER the CT-A7-2 code is deployed, not during a deploy: before CT-A7-2 the target
 * leaves themselves could lack both supplier columns (`SupplierActivationService::activate()` never
 * stamped `accounts.supplier_id`, and the `supplier_company_id` it did stamp was NULL on every
 * first activation), so a migration bundled with the deploy would read the same broken leaves it
 * is meant to be fixed by and stamp nothing — or worse, be re-run blind on a later chart. An
 * operator-invoked, dry-runnable, per-row-reversible command is the right shape, and it is the
 * shape `accounting:coa-linkage` and `accounting:coa-duplicates` already established here.
 *
 * ── The before-image, and why it needs no migration ────────────────────────────────────────────
 * Written to `coa_linkage_changes` with `subject_table = 'journal_entries'`. That table's own
 * migration anticipated exactly this: *"Always 'accounts' today. Present because the alternative —
 * encoding the table in the column name — is what makes a second subject type need a migration
 * later."* `accounting:coa-duplicates` already reuses it the same way for `accounts.code`.
 *
 * `--rollback={runId}` is owned by THIS command, not by `accounting:coa-linkage --rollback`. That
 * is deliberate: the linkage command's own docblock says it is *forbidden from moving money* and
 * its restore phases only understand `accounts` and `system_accounts`. Rather than teach it to
 * write `journal_entries`, `CoaLinkage::rollbackRun()` REFUSES a run id whose rows belong to
 * another command and names the command that owns them — so an operator who types the wrong one
 * gets told, instead of a silent partial undo reported as complete.
 *
 * CT-A7 ROUND 3 (R3-3): that guard is now SYMMETRIC. This command used to answer a run id it did
 * not own with a warning and exit **0**, so typing the two commands the wrong way round produced a
 * success code in one direction and a refusal in the other. It exits **1** now, naming
 * `accounting:coa-linkage --rollback`, and it also exits 1 on a run id nobody recorded — an undo
 * that undid nothing is not a success. A repeat undo of a run this command HAS fully rolled back
 * still exits 0, because there really is nothing left to do.
 *
 * Nothing here touches `debit`, `credit`, `account_id` or any header column: no money moves, no
 * document changes, and the trial balance is byte-identical before and after. `type_reference_id`
 * is a party-attribution column.
 */
class BackfillPayablePartyReference extends Command
{
    protected $signature = 'accounting:backfill-payable-party
                            {--company= : Company id to process (default: every company with accounts)}
                            {--dry-run : Report the full change list without writing anything (the default whenever --apply is absent)}
                            {--apply : Actually write the party references}
                            {--rollback= : Undo a previous --apply run by its run id}
                            {--limit= : Cap the number of rows considered per company, for a staged rollout}
                            {--batch-size=500 : Rows per transaction. One batch = one transaction = one commit.}';

    protected $description = 'CT-A7 F2 — stamp journal_entries.type_reference_id on historical accounts-payable lines written without a party (R3-10a), derived from the line\'s own account. Dry-run by default; records a per-row before-image; refuses to guess.';

    private const SUBJECT_TABLE = 'journal_entries';

    private const COLUMN = 'type_reference_id';

    public function handle(TaskPayablePositionResolver $positions): int
    {
        $rollbackRunId = $this->option('rollback');

        if ($rollbackRunId !== null && $rollbackRunId !== '') {
            if ($this->option('apply') || $this->option('dry-run')) {
                $this->error('--rollback cannot be combined with --apply or --dry-run.');

                return self::FAILURE;
            }

            return $this->rollback((string) $rollbackRunId);
        }

        $apply = (bool) $this->option('apply');

        if ($apply && $this->option('dry-run')) {
            $this->error('--dry-run and --apply are mutually exclusive. Omit both to dry-run.');

            return self::FAILURE;
        }

        $companyIds = $this->companyIds();

        if ($companyIds === []) {
            $this->warn('No companies with accounts found.');

            return self::SUCCESS;
        }

        $runId = (string) Str::ulid();
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $totalStamped = 0;
        $totalRefused = 0;

        $batchSize = max(1, (int) $this->option('batch-size'));

        foreach ($companyIds as $companyId) {
            [$stamped, $refused] = $this->processCompany($companyId, $positions, $apply, $runId, $limit, $batchSize);
            $totalStamped += $stamped;
            $totalRefused += $refused;
        }

        $this->newLine();
        $this->line(sprintf(
            '%s: %d line(s) %s, %d refused (no derivable party).',
            $apply ? 'APPLIED' : 'DRY RUN',
            $totalStamped,
            $apply ? 'stamped' : 'would be stamped',
            $totalRefused
        ));

        if ($apply && $totalStamped > 0) {
            $this->line("  run id: {$runId}");
            $this->line("  undo with: php artisan accounting:backfill-payable-party --rollback={$runId}");
        }

        if (! $apply) {
            $this->line('  Nothing was written. Re-run with --apply to write.');
        }

        // A refusal is a REPORT, not a failure: "this account names no supplier" is the expected
        // outcome for every pooled control leaf, and exiting non-zero on it would make the command
        // useless in a scheduled check. Exit status is reserved for the command failing.
        return self::SUCCESS;
    }

    /**
     * ── The SELECTION PREDICATE, stated exactly (CT-A7 R3, sizing) ──────────────────────────────
     * In SQL terms, for one company:
     *
     *   SELECT je.id, je.account_id
     *     FROM journal_entries je
     *    WHERE je.company_id        = :companyId
     *      AND je.deleted_at       IS NULL
     *      AND je.type_reference_id IS NULL
     *      AND je.account_id IN (:apSubtreeIds)     -- NamedAccountGroupResolver::subtreeIds(
     *                                               --   company, 'Accounts Payable'), plural since R3-1
     *      AND je.id > :lastSeenId
     *    ORDER BY je.id
     *    LIMIT :batchSize
     *
     * Note what is NOT in it. There is no `transactions.doc_type` filter and no
     * `reference_type = 'Payment'` filter: an audit sized the repair with
     * `journal_entries.type='payable' AND transactions.reference_type='Payment'` as a proxy for
     * "payment voucher" and got 24,143 unstamped rows / 3,863,850.577 of movement against 9,097
     * already stamped. This command's own predicate is WIDER in one direction (any AP-subtree row,
     * not only `type='payable'`, and not only `reference_type='Payment'`) and NARROWER in another
     * (only rows inside the AP subtree). That is deliberate: the repair is defined by WHERE THE
     * ROW SITS, not by what kind of document produced it, because the derivation reads the
     * ACCOUNT. `doc_type` could not be used as the filter in any case — the audit confirmed it
     * holds JV/INV/REV/DBN/RV/NULL and has no first-class payment-voucher value.
     *
     * ── Volume ──────────────────────────────────────────────────────────────────────────────────
     * At ~24k rows a single transaction wrapping the whole company would hold row locks on
     * `journal_entries` for the length of the run and make a mid-run failure all-or-nothing. It
     * does not: the work is paged by `id` in `--batch-size` batches (default 500), and EACH BATCH
     * IS ITS OWN TRANSACTION containing that batch's before-images and its writes together. A crash
     * leaves whole committed batches behind and the rest untouched, which is exactly the state a
     * re-run resumes from — stamped rows are out of scope by definition.
     *
     * Paging is by `id > :lastSeenId` rather than by re-querying `IS NULL`: REFUSED rows stay NULL
     * forever, so a pure `IS NULL` loop would hand back the same refused batch and spin.
     *
     * @return array{0: int, 1: int} [stamped, refused]
     */
    private function processCompany(int $companyId, TaskPayablePositionResolver $positions, bool $apply, string $runId, ?int $limit, int $batchSize): array
    {
        $apSubtree = $positions->apSubtreeIds($companyId);

        if ($apSubtree === []) {
            $this->line("company {$companyId}: no 'Accounts Payable' group — skipped.");

            return [0, 0];
        }

        $stamped = 0;
        $refusedRows = 0;
        $refusedAccounts = [];
        $partyByAccount = [];
        $lastId = 0;
        $considered = 0;

        while (true) {
            $take = $batchSize;

            if ($limit !== null) {
                $take = min($take, $limit - $considered);

                if ($take <= 0) {
                    break;
                }
            }

            $rows = DB::table('journal_entries')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNull(self::COLUMN)
                ->whereIn('account_id', $apSubtree)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($take)
                ->get(['id', 'account_id']);

            if ($rows->isEmpty()) {
                break;
            }

            $considered += $rows->count();
            $lastId = (int) $rows->last()->id;

            // Derived once per account for the whole run, not once per row: at 24k rows over a few
            // hundred supplier leaves this is the difference between a few hundred queries and
            // tens of thousands.
            $unknown = $rows->pluck('account_id')->unique()
                ->reject(fn ($id) => array_key_exists((int) $id, $partyByAccount))->all();

            if ($unknown !== []) {
                $partyByAccount += $this->derivePartyPerAccount($companyId, $unknown)
                    + array_fill_keys(array_map('intval', $unknown), null);
            }

            $writes = [];

            foreach ($rows as $row) {
                $party = $partyByAccount[(int) $row->account_id] ?? null;

                if ($party === null) {
                    $refusedRows++;
                    $refusedAccounts[(int) $row->account_id] = ($refusedAccounts[(int) $row->account_id] ?? 0) + 1;

                    continue;
                }

                $writes[] = ['id' => (int) $row->id, 'party' => $party];
                $stamped++;
            }

            if (! $apply || $writes === []) {
                continue;
            }

            // ONE BATCH = ONE TRANSACTION. Before-images first, in the same transaction as the
            // writes they describe, so a crash can never leave a stamped row with no way back.
            DB::transaction(function () use ($writes, $companyId, $runId) {
                $beforeImages = [];

                foreach ($writes as $write) {
                    $beforeImages[] = [
                        'run_id' => $runId,
                        'company_id' => $companyId,
                        'subject_table' => self::SUBJECT_TABLE,
                        'subject_id' => $write['id'],
                        'column_name' => self::COLUMN,
                        // Always NULL by construction — the query only selects NULL rows — but
                        // recorded rather than assumed, so the rollback restores what was there and
                        // not what this command believed was there.
                        'before_value' => null,
                        'after_value' => (string) $write['party'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('coa_linkage_changes')->insert($beforeImages);

                foreach ($writes as $write) {
                    DB::table('journal_entries')
                        ->where('id', $write['id'])
                        // Re-asserted at write time: if anything stamped this row between the read
                        // and the write, this update matches nothing rather than overwriting it.
                        ->whereNull(self::COLUMN)
                        ->update([self::COLUMN => $write['party'], 'updated_at' => now()]);
                }
            });
        }

        if ($considered === 0) {
            $this->line("company {$companyId}: nothing to do.");

            return [0, 0];
        }

        $this->line(sprintf(
            'company %d: %d line(s) considered, %d derivable, %d refused across %d account(s).',
            $companyId,
            $considered,
            $stamped,
            $refusedRows,
            count($refusedAccounts)
        ));

        foreach ($refusedAccounts as $accountId => $count) {
            $name = DB::table('accounts')->where('id', $accountId)->value('name');
            $this->line(sprintf(
                '  REFUSED account #%d (%s): %d line(s) — neither accounts.supplier_id nor a '
                    .'supplier_companies pairing names a supplier. Nothing stamped; nothing guessed.',
                $accountId,
                $name ?? 'unknown',
                $count
            ));
        }

        return [$stamped, $refusedRows];
    }

    /**
     * The party for each account, or null where it cannot be derived — the same two columns, in the
     * same order, as `BankPaymentController::voucherPartyRef()`. One query per column rather than
     * one per row.
     *
     * @param  int[]  $accountIds
     * @return array<int, int>
     */
    private function derivePartyPerAccount(int $companyId, array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $party = [];

        $accounts = DB::table('accounts')
            ->where('company_id', $companyId)
            ->whereIn('id', $accountIds)
            ->get(['id', 'supplier_id', 'supplier_company_id']);

        $pivotIds = [];

        foreach ($accounts as $account) {
            if ($account->supplier_id !== null && (int) $account->supplier_id > 0) {
                $party[(int) $account->id] = (int) $account->supplier_id;

                continue;
            }

            if ($account->supplier_company_id !== null) {
                $pivotIds[(int) $account->supplier_company_id][] = (int) $account->id;
            }
        }

        if ($pivotIds !== []) {
            $suppliers = DB::table('supplier_companies')
                ->whereIn('id', array_keys($pivotIds))
                ->pluck('supplier_id', 'id');

            foreach ($pivotIds as $pivotId => $accountIdsForPivot) {
                $supplierId = $suppliers[$pivotId] ?? null;

                if ($supplierId === null || (int) $supplierId <= 0) {
                    continue;
                }

                foreach ($accountIdsForPivot as $accountId) {
                    $party[$accountId] = (int) $supplierId;
                }
            }
        }

        return $party;
    }

    private function rollback(string $runId): int
    {
        $rows = DB::table('coa_linkage_changes')
            ->where('run_id', $runId)
            ->where('subject_table', self::SUBJECT_TABLE)
            ->whereNull('rolled_back_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            // ── CT-A7 ROUND 3, finding R3-3 — make the ownership guard SYMMETRIC ────────────────
            // `accounting:coa-linkage --rollback <a backfill run id>` already refuses with exit 1
            // and names this command. The reverse direction did not: it filtered on
            // subject_table='journal_entries', found nothing, printed "No un-rolled-back ...
            // recorded for this command" and returned exit 0. An operator who typed the wrong
            // direction got a SUCCESS code and could reasonably believe the undo ran.
            //
            // Three distinct outcomes now, and only one of them is success:
            //   - every recorded row is already rolled back -> SUCCESS, because a repeated undo of
            //     the same run really has left nothing to do (idempotent, same as re-running
            //     --apply);
            //   - the run id belongs to ANOTHER command -> FAILURE, naming that command;
            //   - the run id is not recognised at all -> FAILURE, because an undo that undid
            //     nothing must not report success.
            $anyForRun = DB::table('coa_linkage_changes')->where('run_id', $runId);

            if ((clone $anyForRun)->where('subject_table', self::SUBJECT_TABLE)->exists()) {
                $this->line("Run {$runId}: every recorded line was already rolled back. Nothing to do.");

                return self::SUCCESS;
            }

            $foreignTables = (clone $anyForRun)->distinct()->pluck('subject_table');

            if ($foreignTables->isNotEmpty()) {
                $this->error(
                    "Run '{$runId}' contains before-images this command does not own and must not restore."
                );
                $this->line('  Run contains before-images for: '.$foreignTables->implode(', '));
                $this->line('  Undo it with: php artisan accounting:coa-linkage --rollback='.$runId);
                $this->line('  Nothing was restored.');

                return self::FAILURE;
            }

            $this->error("Run '{$runId}' is not a run id this command recorded.");
            $this->line('  Run ids are echoed by every --apply run and stored in coa_linkage_changes.run_id.');
            $this->line('  If it came from the chart-repair command, undo it with: '
                .'php artisan accounting:coa-linkage --rollback='.$runId);
            $this->line('  Nothing was restored.');

            return self::FAILURE;
        }

        $restored = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, &$restored, &$skipped) {
            foreach ($rows as $row) {
                $current = DB::table('journal_entries')->where('id', $row->subject_id)->value(self::COLUMN);

                // Refuse a row that has MOVED since the run — the same rule
                // `accounting:coa-linkage --rollback` applies to an account column. Putting a
                // before-image back over someone else's later, deliberate value is not an undo.
                if ((string) $current !== (string) $row->after_value) {
                    $skipped[] = sprintf(
                        'journal_entries #%d: now %s, this run wrote %s — left alone',
                        (int) $row->subject_id,
                        $current === null ? 'NULL' : (string) $current,
                        (string) $row->after_value
                    );

                    continue;
                }

                DB::table('journal_entries')
                    ->where('id', $row->subject_id)
                    ->update([
                        self::COLUMN => $row->before_value === null ? null : (int) $row->before_value,
                        'updated_at' => now(),
                    ]);

                DB::table('coa_linkage_changes')->where('id', $row->id)->update([
                    'rolled_back_at' => now(),
                    'updated_at' => now(),
                ]);

                $restored++;
            }
        });

        $this->line("Rolled back run {$runId}: {$restored} line(s) restored.");

        foreach ($skipped as $note) {
            $this->warn('  '.$note);
        }

        return $skipped === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return int[] */
    private function companyIds(): array
    {
        $option = $this->option('company');

        if ($option !== null && $option !== '') {
            return [(int) $option];
        }

        return DB::table('accounts')
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** The subject table this command owns, for `CoaLinkage::rollbackRun()`'s ownership check. */
    public static function subjectTable(): string
    {
        return self::SUBJECT_TABLE;
    }

    /** @see CoaLinkageChange for the before-image contract this command writes into. */
    public static function beforeImageColumn(): string
    {
        return self::COLUMN;
    }
}
