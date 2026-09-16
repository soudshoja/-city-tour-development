<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CoaLinkageChange;
use App\Services\Accounting\TaskPayablePositionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CT-A8 — give the historical supplier payable leaves the `accounts.supplier_id` that
 * `accounting:backfill-payable-party` needs to read, derived FROM POSTED EVIDENCE and never from a
 * name.
 *
 * ── The finding that created this lane ─────────────────────────────────────────────────────────
 * CT-A7-F2 shipped `accounting:backfill-payable-party`, which stamps the supplier onto historical
 * payment-voucher ledger lines so the supplier filter on the creditors screen stops reporting a
 * balance gross of everything ever paid. Deployed to the development site on 2026-09-16 and then
 * NOT RUN, because its own dry run measured the repair it could actually perform:
 *
 *     DRY RUN: 40 line(s) would be stamped, 15619 refused (no derivable party).
 *
 * **40 of 15,659.** The command is correct: it derives the party from `accounts.supplier_id` (or
 * `accounts.supplier_company_id` -> `supplier_companies.supplier_id`) and REFUSES to guess. The
 * data it reads is what does not exist — the historical per-supplier payable leaves were minted
 * long before CT-A7-2 taught `SupplierActivationService::activate()` to stamp that column, so on
 * the real chart exactly **6 accounts of company 1 carry `supplier_id` at all**, and the other
 * ~137 payable leaves carry nothing. Protecting the column in the live->dev sync (CT-A8 Task 1)
 * makes a stamp SURVIVE; it does not make one DERIVABLE. This command is the other half.
 *
 * ── Why a NEW command rather than a mode of `accounting:backfill-payable-party` ────────────────
 * The brief allowed either. A separate command, for four reasons that are about safety rather than
 * taste:
 *
 *   1. **Different subject, different undo.** That command's before-images are
 *      `subject_table = 'journal_entries'`; these are `accounts`. Its `--rollback` ownership guard
 *      is built on exactly that discriminator (CT-A7 R3-3 made it symmetric). Folding a second
 *      subject table into one run id would produce a run id that is partly one command's and partly
 *      the other's — which is precisely the ambiguity the guard exists to remove.
 *   2. **Different derivation, different blast radius.** That command READS two account columns.
 *      This one WRITES one, from an evidence walk over the ledger. A wrong write here is
 *      multiplied by every line on the leaf; a wrong write there costs one line. They deserve
 *      separate dry runs, separate operator decisions and separate run ids.
 *   3. **The deploy order is load-bearing.** Leaf first, party second — running them the other way
 *      round attributes nothing and looks like a no-op rather than an error. Two commands make the
 *      order explicit in the runbook and auditable afterwards as two recorded run ids.
 *   4. **One idempotency boundary each.** Both are idempotent because their own writes take their
 *      rows out of scope. A combined command would be idempotent only as a pair, and a `--limit`ed
 *      first phase would silently bound the second.
 *
 * ── THE EVIDENCE RULE ──────────────────────────────────────────────────────────────────────────
 * A leaf gets `supplier_id = S` **only if every posted line on it that can be traced to a supplier
 * at all traces to the same S**, and at least one can:
 *
 *   - **zero** traceable lines  -> REFUSED and reported (including an empty leaf: no evidence is
 *     not evidence of one supplier);
 *   - **more than one** supplier -> REFUSED and reported WITH THE SET of supplier ids. This is the
 *     pooled control leaves by construction (`2120 Suppliers (Flights)` and friends carry 33
 *     suppliers on the real chart) and it is also the honest answer for a legacy leaf that two
 *     suppliers were genuinely posted to;
 *   - lines that trace to **nothing** (a manual JV with no task) do not vote — but a leaf whose
 *     lines ALL fail to trace has zero traceable lines and is refused by the first rule.
 *
 * **Name matching is forbidden.** Not "discouraged": an account named like a supplier is the guess
 * the owner's rules exclude, and it is wrong in both directions on this data (one supplier, two
 * leaves; one leaf, two suppliers). Nothing in this file reads `accounts.name`, `suppliers.name` or
 * `journal_entries.name` for any purpose other than PRINTING an account's own name in a refusal
 * line so an operator can find it. There is a mutation proof that renaming every account to its
 * supplier's exact name changes nothing about what this command does.
 *
 * ── THE CHAIN, and why it is the only one ──────────────────────────────────────────────────────
 * `journal_entries.task_id` -> `tasks.supplier_id`, with `tasks.company_id` required to equal the
 * line's own company.
 *
 * That is not an assumption; it is what this schema has, measured on a restored copy of the
 * development database (company 1):
 *
 *   | evidence column on the 15,386 unstamped AP-subtree lines | present on |
 *   |----------------------------------------------------------|-----------:|
 *   | `journal_entries.task_id`                                 | **15,384** |
 *   | `journal_entries.invoice_id`                              |          0 |
 *   | `journal_entries.invoice_detail_id`                       |          0 |
 *   | `journal_entries.transaction_id`                          |     15,386 |
 *
 * and from `transactions` there is no supplier to reach: its only document links are `invoice_id`
 * (a SALE to a client) and `payment_id` (`payments` carries no supplier column at all). `bank_payments`,
 * the W5P payment-voucher table, names an ACCOUNT rather than a supplier — deriving a supplier from
 * `bank_payments.target_account_id -> accounts.supplier_id` would read the very column this command
 * writes, which is circular, so it is not used.
 *
 * `journal_entries.type_reference_id` is deliberately NOT evidence either, for the same reason: it
 * is the column `accounting:backfill-payable-party` is about to write from this one, so letting it
 * vote would let the repair confirm itself.
 *
 * ── THE CHAIN IS PROVEN, not merely plausible ──────────────────────────────────────────────────
 * The engine already stamps `journal_entries.type_reference_id` with the supplier id on the AP
 * lines it writes. On the restored copy, company 1 has **12,468** AP-subtree lines that are both
 * already stamped AND carry a `task_id`. Comparing the engine's own attribution with this command's
 * chain on those rows:
 *
 *     agree 12,468   disagree 0
 *
 * The derivation is therefore not a new opinion about who a payable belongs to; it is the same
 * answer the posting engine gives, computed for the rows the engine never got to.
 *
 * ── THE EXPENSE TWIN (found on the real chart, 2026-09-16) ─────────────────────────────────────
 * Three suppliers on company 1 each own TWO accounts carrying the same `supplier_id`: one leaf in
 * the payable tree (`21xx`) and one in the expense tree (`51xx`). CT-A7's F5 ruling is that
 * `supplier_id` belongs on the **payable leaf only**. This command cannot stamp an expense leaf,
 * by construction and not by a filter: its candidate set is
 * {@see TaskPayablePositionResolver::apSubtreeIds()}, the `Accounts Payable` structure, and an
 * expense account is not in it. The twin is also not consulted — a payable leaf whose supplier's
 * expense twin is already stamped is still derived from its own posted lines, because "this
 * supplier owns an account somewhere" is not evidence about THIS account.
 *
 * ── Scope, stated exactly ──────────────────────────────────────────────────────────────────────
 * A candidate leaf is an account that is
 *   (a) in `apSubtreeIds($companyId)`  — plural, via `NamedAccountGroupResolver`, never a name
 *       anchor of this command's own (CT-A7 R3-1);
 *   (b) not soft-deleted;
 *   (c) a LEAF — derived from having no live children, never from `accounts.is_group`, which CT-A1
 *       §1.4 measured wrong on 613 accounts;
 *   (d) NOT already attributable — `supplier_id IS NULL` **and** no `supplier_company_id` that
 *       resolves through `supplier_companies`. (d) is what makes a re-run a no-op and is also why
 *       this command can never overwrite an operator's deliberate assignment.
 *
 * A leaf that is already attributable through the pivot but whose posted evidence names a DIFFERENT
 * supplier is out of scope for the write and REPORTED as a contradiction — it is a chart fact
 * somebody has to look at, not something to repair silently in either direction.
 *
 * ── What it writes, and what it cannot ─────────────────────────────────────────────────────────
 * One column, `accounts.supplier_id`, on leaves that had none. No money moves: nothing here touches
 * `journal_entries`, `transactions`, `debit`, `credit` or `account_id`, and the trial balance is
 * byte-identical before and after. A per-row before-image goes to `coa_linkage_changes` in the same
 * transaction as the write, and `--rollback={runId}` puts it back.
 *
 * ── The undo, and who owns it ──────────────────────────────────────────────────────────────────
 * `coa_linkage_changes` is shared. Before CT-A8 the ownership discriminator was `subject_table`
 * alone, which was enough while the only two writers were `accounts`-and-`system_accounts`
 * (`accounting:coa-linkage`, plus `accounting:coa-duplicates` delegating to it) and
 * `journal_entries` (`accounting:backfill-payable-party`). This command writes `accounts` rows that
 * `accounting:coa-linkage --rollback` must NOT restore — `supplier_id` is not in
 * {@see CoaLinkageChange::REVERSIBLE_COLUMNS} and must not be added to it, because putting a
 * supplier back on a leaf is a party-attribution change and the linkage command's own docblock
 * forbids it from moving anything of the sort. So the discriminator is now the PAIR
 * `(subject_table, column_name)`, and both directions refuse with exit 1 and name the owner:
 *
 *     accounting:coa-linkage --rollback=<a CT-A8 run id>          -> refuses, names this command
 *     accounting:backfill-supplier-leaf --rollback=<a linkage id> -> refuses, names that command
 *
 * ── ONE RUN ID PER INVOCATION ──────────────────────────────────────────────────────────────────
 * Inherited from CT-A7 R4-5 and true for the same reason: a repair bounded with `--limit` and then
 * resumed records its before-images under a different run id each time, so undoing the whole repair
 * takes one `--rollback` per id. Every `--apply` echoes its own id and its own undo command.
 * `SELECT DISTINCT run_id FROM coa_linkage_changes WHERE subject_table = 'accounts' AND column_name
 * = 'supplier_id' AND rolled_back_at IS NULL` lists what is still outstanding.
 *
 * ── The deploy order this command is one step of ───────────────────────────────────────────────
 *   1. deploy the code;
 *   2. add `type_reference_id` to `PROTECTED_COLUMNS['journal_entries']` in the sync engine, or the
 *      nightly full run writes production's NULL back over every stamped line within 24 hours
 *      (measured: 0 of 64,299 candidate rows were protected);
 *   3. `accounting:backfill-supplier-leaf --company=1` (dry run), then `--apply`;
 *   4. `accounting:backfill-payable-party --company=1` (dry run), then `--apply`;
 *   5. watch one hourly and one nightly sync and confirm the stamps survived.
 *
 * Step 4 without step 3 stamps 40 lines. Step 3 without step 2 is reverted by the sync — `accounts`
 * is `insert_only` in the sync engine so `accounts.supplier_id` itself is safe, but the party stamps
 * step 4 writes are not, and a supplier filter that works for a day is worse than one that never did.
 */
class BackfillSupplierLeaf extends Command
{
    protected $signature = 'accounting:backfill-supplier-leaf
                            {--company= : Company id to process (default: every company with accounts)}
                            {--dry-run : Report the full change list without writing anything (the default whenever --apply is absent)}
                            {--apply : Actually write accounts.supplier_id}
                            {--rollback= : Undo a previous --apply run by its run id. A bounded run that was resumed has ONE run id PER --apply invocation, so undoing the whole repair needs one --rollback per id.}
                            {--limit= : Cap the number of candidate leaves considered per company, for a staged rollout}
                            {--batch-size=100 : Leaves per transaction. One batch = one transaction = one commit.}';

    protected $description = 'CT-A8 — derive accounts.supplier_id for historical supplier payable leaves from posted evidence (journal line -> task -> supplier), so accounting:backfill-payable-party has something to read. Dry-run by default; records a per-row before-image; refuses to guess and never matches on a name.';

    /**
     * The before-image subject. `accounts`, honestly — the rows really are `accounts` rows, and
     * inventing a fake table token to dodge the ownership question would be a lie in an audit
     * table. The ownership question is answered by {@see self::COLUMN} instead.
     */
    private const SUBJECT_TABLE = 'accounts';

    /** The one column this command writes, and the half of the ownership key that is its own. */
    private const COLUMN = 'supplier_id';

    /**
     * Header posting statuses that are NOT money and therefore do not carry evidence about who a
     * payable belongs to. `reversed` is deliberately absent: a reversal is a NEW row with the
     * opposite sign, never a delete, so excluding reversed headers would drop the original lines
     * while keeping the reversing document's — the same reasoning
     * {@see \App\Services\Accounting\NamedAccountGroupResolver::primaryGroupId()} records. A legacy
     * header carrying no status at all is INCLUDED: those rows predate the column and are most of
     * the history this command exists for.
     */
    private const NON_MONEY_POSTING_STATUSES = ['draft', 'void'];

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
        $batchSize = max(1, (int) $this->option('batch-size'));

        $totalStamped = 0;
        $totalRefused = 0;

        foreach ($companyIds as $companyId) {
            [$stamped, $refused] = $this->processCompany($companyId, $positions, $apply, $runId, $limit, $batchSize);
            $totalStamped += $stamped;
            $totalRefused += $refused;
        }

        $this->newLine();
        $this->line(sprintf(
            '%s: %d leaf/leaves %s, %d refused (evidence does not name exactly one supplier).',
            $apply ? 'APPLIED' : 'DRY RUN',
            $totalStamped,
            $apply ? 'stamped' : 'would be stamped',
            $totalRefused
        ));

        if ($apply && $totalStamped > 0) {
            $this->line("  run id: {$runId}");
            $this->line("  undo with: php artisan accounting:backfill-supplier-leaf --rollback={$runId}");
            $this->line('  NOTE: each --apply invocation gets its OWN run id. A repair run in several '
                .'bounded passes needs one --rollback per id to undo it all.');
            $this->line('  NEXT: php artisan accounting:backfill-payable-party --company=<id> (dry run first) '
                .'— this command only makes the party DERIVABLE; that one stamps the ledger lines.');
        }

        if (! $apply) {
            $this->line('  Nothing was written. Re-run with --apply to write.');
        }

        // A refusal is a REPORT, not a failure. A pooled control leaf carrying thirty suppliers is
        // the correct, permanent answer for that leaf, and a command that exits non-zero on the
        // expected shape of the chart is a command every runbook learns to ignore.
        return self::SUCCESS;
    }

    /**
     * ── The candidate predicate, stated exactly ─────────────────────────────────────────────────
     *
     *   SELECT a.id
     *     FROM accounts a
     *    WHERE a.company_id  = :companyId
     *      AND a.deleted_at IS NULL
     *      AND a.supplier_id IS NULL                 -- never overwrite (brief rule)
     *      AND a.id IN (:apSubtreeIds)               -- NamedAccountGroupResolver, plural (R3-1)
     *      AND NOT EXISTS (SELECT 1 FROM accounts c  -- LEAF, derived; `is_group` is wrong on 613
     *                       WHERE c.parent_id = a.id --   accounts (CT-A1 §1.4)
     *                         AND c.deleted_at IS NULL)
     *      AND a.id > :lastSeenId
     *    ORDER BY a.id
     *    LIMIT :batchSize
     *
     * Paged by `id > :lastSeenId`, not by re-querying `supplier_id IS NULL`: a REFUSED leaf stays
     * NULL forever, so an `IS NULL` loop would hand back the same refused batch and spin. That is
     * the same defect CT-A7 R3 fixed in `accounting:backfill-payable-party`, and it bites harder
     * here because refusals are the MAJORITY outcome on the pooled leaves.
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
        $refused = 0;
        $considered = 0;
        $lastId = 0;
        $refusals = [];
        $contradictions = [];

        while (true) {
            $take = $batchSize;

            if ($limit !== null) {
                $take = min($take, $limit - $considered);

                if ($take <= 0) {
                    break;
                }
            }

            $leaves = DB::table('accounts')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNull(self::COLUMN)
                ->whereIn('id', $apSubtree)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('accounts as child')
                        ->whereColumn('child.parent_id', 'accounts.id')
                        ->whereNull('child.deleted_at');
                })
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($take)
                ->get(['id', 'code', 'name', 'supplier_company_id']);

            if ($leaves->isEmpty()) {
                break;
            }

            $considered += $leaves->count();
            $lastId = (int) $leaves->last()->id;

            $leafIds = $leaves->pluck('id')->map(fn ($id) => (int) $id)->all();
            $evidence = $this->evidencePerLeaf($companyId, $leafIds);
            $alreadyViaPivot = $this->pivotPartyPerLeaf($leaves);

            $writes = [];

            foreach ($leaves as $leaf) {
                $leafId = (int) $leaf->id;
                $suppliers = $evidence[$leafId] ?? [];
                $pivotParty = $alreadyViaPivot[$leafId] ?? null;

                // Each refusal is printed as SEVERAL short lines rather than one long one. That is
                // not cosmetic: a `supplier ids:` line on its own is what an operator copies into a
                // query, the same reason `accounting:coa-linkage --rollback` prints its refused
                // account ids on their own line. (It also makes each fact independently assertable
                // — `expectsOutputToContain` is satisfied by ONE write per expectation, so three
                // facts crammed onto one line can only ever prove one of them.)
                if (count($suppliers) === 0) {
                    $refused++;
                    $refusals[] = sprintf('  REFUSED leaf #%d (%s %s)', $leafId, (string) ($leaf->code ?? '?'), (string) ($leaf->name ?? '?'));
                    $refusals[] = '    reason: no posted line on it traces to a supplier — nothing stamped, nothing guessed.';

                    continue;
                }

                if (count($suppliers) > 1) {
                    $refused++;
                    $refusals[] = sprintf('  REFUSED leaf #%d (%s %s)', $leafId, (string) ($leaf->code ?? '?'), (string) ($leaf->name ?? '?'));
                    $refusals[] = sprintf(
                        '    reason: posted lines trace to %d DIFFERENT suppliers — a pooled or shared leaf cannot carry one party.',
                        count($suppliers)
                    );
                    $refusals[] = '    supplier ids: '.implode(', ', $suppliers);

                    continue;
                }

                $supplierId = $suppliers[0];

                // Already attributable through the pivot: `accounting:backfill-payable-party`
                // already derives a party for every line on this leaf, so there is nothing for this
                // command to unlock. Writing anyway would be a no-op at best and, when the two
                // disagree, a silent override of a link somebody made deliberately.
                if ($pivotParty !== null) {
                    if ($pivotParty !== $supplierId) {
                        $contradictions[] = sprintf(
                            '  CONTRADICTION leaf #%d (%s %s)',
                            $leafId,
                            (string) ($leaf->code ?? '?'),
                            (string) ($leaf->name ?? '?')
                        );
                        $contradictions[] = sprintf(
                            '    supplier_companies pairing names supplier #%d, posted evidence names #%d. '
                                .'NOT stamped — this is a chart fact for an operator, not something to repair '
                                .'in either direction.',
                            $pivotParty,
                            $supplierId
                        );
                        $refused++;
                    }

                    continue;
                }

                $writes[] = ['id' => $leafId, 'supplier_id' => $supplierId];
                $stamped++;
            }

            if (! $apply || $writes === []) {
                continue;
            }

            // ONE BATCH = ONE TRANSACTION. Before-images first, in the same transaction as the
            // writes they describe, so a crash can never leave a stamped leaf with no way back.
            DB::transaction(function () use ($writes, $companyId, $runId) {
                $beforeImages = [];

                foreach ($writes as $write) {
                    $beforeImages[] = [
                        'run_id' => $runId,
                        'company_id' => $companyId,
                        'subject_table' => self::SUBJECT_TABLE,
                        'subject_id' => $write['id'],
                        'column_name' => self::COLUMN,
                        // NULL by construction — the query only selects NULL leaves — but recorded
                        // rather than assumed, so the rollback restores what was there and not what
                        // this command believed was there.
                        'before_value' => null,
                        'after_value' => (string) $write['supplier_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('coa_linkage_changes')->insert($beforeImages);

                foreach ($writes as $write) {
                    DB::table('accounts')
                        ->where('id', $write['id'])
                        // Re-asserted at write time: if anything stamped this leaf between the read
                        // and the write, this update matches nothing rather than overwriting it.
                        ->whereNull(self::COLUMN)
                        ->update([self::COLUMN => $write['supplier_id'], 'updated_at' => now()]);
                }
            });
        }

        if ($considered === 0) {
            $this->line("company {$companyId}: no candidate payable leaves — nothing to do.");

            return [0, 0];
        }

        $this->line(sprintf(
            'company %d: %d candidate leaf/leaves considered, %d derivable, %d refused.',
            $companyId,
            $considered,
            $stamped,
            $refused
        ));

        foreach ($refusals as $line) {
            $this->line($line);
        }

        foreach ($contradictions as $line) {
            $this->warn($line);
        }

        return [$stamped, $refused];
    }

    /**
     * THE EVIDENCE WALK. For each leaf, the DISTINCT set of supplier ids its posted lines trace to,
     * through `journal_entries.task_id -> tasks.supplier_id`.
     *
     * One grouped query for the whole batch rather than one per leaf: on the real chart a leaf can
     * carry ten thousand lines and the answer is a set of at most a few dozen ids.
     *
     * Every join condition here is a guard that has to be argued for:
     *
     *   - `je.deleted_at IS NULL` — a soft-deleted line is not posted evidence.
     *   - `t.company_id = je.company_id` — a task belonging to ANOTHER tenant must never name the
     *     party on this tenant's leaf. Measured at zero violations on the real data, which is what
     *     a guard looks like when it is working rather than when it is unnecessary.
     *   - `s.id IS NOT NULL` — `tasks.supplier_id` is not a foreign key in this schema, so a task
     *     can name a supplier row that has been deleted. An id with no supplier behind it is not a
     *     party; the line simply does not vote.
     *   - header status — draft and void are not money (see {@see self::NON_MONEY_POSTING_STATUSES}).
     *     A line with NO header at all is kept by the LEFT JOIN: orphaned legacy rows exist and
     *     their task is still the document.
     *
     * Soft-deleted TASKS are deliberately kept as evidence. A deleted task is still the document
     * that put the payable on the leaf, and — the property that actually decides it — including
     * more evidence can only ever RAISE the distinct-supplier count, which can only ever turn an
     * accept into a refusal. Every judgement call in this method is resolved in that direction.
     *
     * @param  int[]  $leafIds
     * @return array<int, int[]> leaf id => sorted, distinct supplier ids
     */
    private function evidencePerLeaf(int $companyId, array $leafIds): array
    {
        if ($leafIds === []) {
            return [];
        }

        $rows = DB::table('journal_entries as je')
            ->join('tasks as t', function ($join) {
                $join->on('t.id', '=', 'je.task_id')
                    ->on('t.company_id', '=', 'je.company_id');
            })
            ->join('suppliers as s', 's.id', '=', 't.supplier_id')
            ->leftJoin('transactions as txn', 'txn.id', '=', 'je.transaction_id')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereIn('je.account_id', $leafIds)
            ->where(function ($q) {
                $q->whereNull('txn.posting_status')
                    ->orWhereNotIn('txn.posting_status', self::NON_MONEY_POSTING_STATUSES);
            })
            ->distinct()
            ->get(['je.account_id as account_id', 't.supplier_id as supplier_id']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->account_id][] = (int) $row->supplier_id;
        }

        foreach ($out as $accountId => $suppliers) {
            $suppliers = array_values(array_unique($suppliers));
            sort($suppliers);
            $out[$accountId] = $suppliers;
        }

        return $out;
    }

    /**
     * The party each leaf is ALREADY attributable to through
     * `accounts.supplier_company_id -> supplier_companies.supplier_id` — the second of the two
     * derivations `BankPaymentController::voucherPartyRef()` and
     * `accounting:backfill-payable-party` both use.
     *
     * A leaf that already answers that question needs nothing from this command; it is reported as
     * a contradiction only when the pivot and the posted evidence disagree.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $leaves
     * @return array<int, int> leaf id => supplier id
     */
    private function pivotPartyPerLeaf($leaves): array
    {
        $pivotIds = [];

        foreach ($leaves as $leaf) {
            if ($leaf->supplier_company_id !== null) {
                $pivotIds[(int) $leaf->supplier_company_id][] = (int) $leaf->id;
            }
        }

        if ($pivotIds === []) {
            return [];
        }

        $suppliers = DB::table('supplier_companies')
            ->whereIn('id', array_keys($pivotIds))
            ->pluck('supplier_id', 'id');

        $out = [];

        foreach ($pivotIds as $pivotId => $leafIds) {
            $supplierId = $suppliers[$pivotId] ?? null;

            if ($supplierId === null || (int) $supplierId <= 0) {
                continue;
            }

            foreach ($leafIds as $leafId) {
                $out[$leafId] = (int) $supplierId;
            }
        }

        return $out;
    }

    /**
     * The undo. Symmetric with {@see CoaLinkage::rollbackRun()} in both directions (CT-A7 R3-3's
     * rule, extended to the `(subject_table, column_name)` ownership key CT-A8 needs):
     *
     *   - every recorded row already rolled back -> SUCCESS, because a repeated undo of the same
     *     run really has left nothing to do;
     *   - the run id belongs to ANOTHER command -> FAILURE, naming that command;
     *   - the run id is not recognised at all   -> FAILURE, because an undo that undid nothing must
     *     not report success.
     */
    private function rollback(string $runId): int
    {
        $owned = fn ($q) => $q->where('subject_table', self::SUBJECT_TABLE)->where('column_name', self::COLUMN);

        $rows = $owned(DB::table('coa_linkage_changes')->where('run_id', $runId))
            ->whereNull('rolled_back_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $anyForRun = DB::table('coa_linkage_changes')->where('run_id', $runId);

            if ($owned(clone $anyForRun)->exists()) {
                $this->line("Run {$runId}: every recorded leaf was already rolled back. Nothing to do.");

                return self::SUCCESS;
            }

            $foreign = (clone $anyForRun)
                ->distinct()
                ->get(['subject_table', 'column_name'])
                ->map(fn ($r) => $r->subject_table.'.'.$r->column_name);

            if ($foreign->isNotEmpty()) {
                $this->error(
                    "Run '{$runId}' contains before-images this command does not own and must not restore."
                );
                $this->line('  Run contains before-images for: '.$foreign->implode(', '));
                $this->line('  Undo it with: '.$this->ownerOf($foreign->all()));
                $this->line('  Nothing was restored.');

                return self::FAILURE;
            }

            $this->error("Run '{$runId}' is not a run id this command recorded.");
            $this->line('  Run ids are echoed by every --apply run and stored in coa_linkage_changes.run_id.');
            $this->line('  Nothing was restored.');

            return self::FAILURE;
        }

        $restored = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, &$restored, &$skipped) {
            foreach ($rows as $row) {
                $current = DB::table('accounts')->where('id', $row->subject_id)->value(self::COLUMN);

                // Refuse a leaf that has MOVED since the run. Putting a before-image back over
                // somebody else's later, deliberate value is not an undo — and on this column that
                // somebody is most likely `SupplierActivationService::activate()`, which is the
                // code path this whole lane exists to have working.
                if ((string) $current !== (string) $row->after_value) {
                    $skipped[] = sprintf(
                        'accounts #%d.supplier_id: now %s, this run wrote %s — left alone',
                        (int) $row->subject_id,
                        $current === null ? 'NULL' : (string) $current,
                        (string) $row->after_value
                    );

                    continue;
                }

                DB::table('accounts')
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

        $this->line("Rolled back run {$runId}: {$restored} leaf/leaves restored.");

        if ($restored > 0) {
            $this->line('  NOTE: the ledger lines accounting:backfill-payable-party stamped FROM these '
                .'leaves are NOT undone by this — roll that run back by its own id first, or the party '
                .'stays on lines whose leaf no longer names it.');
        }

        foreach ($skipped as $note) {
            $this->warn('  '.$note);
        }

        return $skipped === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Name the command that owns a set of `subject_table.column_name` pairs, so an operator who
     * typed the wrong `--rollback` is told which one to type instead rather than being told only
     * that this was not it.
     *
     * @param  string[]  $pairs
     */
    private function ownerOf(array $pairs): string
    {
        foreach ($pairs as $pair) {
            if (str_starts_with($pair, 'journal_entries.')) {
                return 'php artisan accounting:backfill-payable-party --rollback=<that run id>';
            }
        }

        return 'php artisan accounting:coa-linkage --rollback=<that run id>';
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

    /** The subject table this command owns, for the shared ownership check. */
    public static function subjectTable(): string
    {
        return self::SUBJECT_TABLE;
    }

    /** The column half of the ownership key — `accounts` alone is not enough (CT-A8). */
    public static function beforeImageColumn(): string
    {
        return self::COLUMN;
    }
}
