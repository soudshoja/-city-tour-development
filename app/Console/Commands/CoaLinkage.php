<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\CoaLinkageChange;
use App\Models\CoaLinkageFinding;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\AccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * CT-A4 — chart-of-accounts LINKAGE: make every purpose code the posting engine can request
 * resolve to the right leaf on a real, already-populated chart, and record — never silently
 * repair — everything that needs an owner ruling.
 *
 * ── Why this exists when accounting:ensure-system-leaves already does ──────────────────────────
 * {@see EnsureSystemLeaves} mints ~37 NAMED leaves and re-runs {@see \Database\Seeders\
 * SystemAccountsSeeder} to map the purpose codes they back. It is the right tool and this command
 * DELEGATES to it rather than reimplementing it. But CT-A4's measurement of the City Travelers
 * dev chart (`.planning/phases/citytravelers-accounting-audit/CT-A4-COA-GAP-2026-09-09.md`) found
 * four things it cannot do, and one of them is fatal:
 *
 *   G1. PAYABLE_CONTROL is UNMAPPABLE and stays unmappable after ensure-system-leaves runs.
 *       SystemAccountsSeeder::resolveControls() maps it with
 *       mapByChain('Creditors', ['Accounts Payable','Liabilities']) — and mapByChain SKIPS an
 *       account that has children. On this chart `2110 Creditors` acquired six company payment
 *       instruments as children (the accounts `tasks.payment_method_account_id` points at), so
 *       the chain finds it, sees children, and skips. EnsureSystemLeaves has no entry for the
 *       purpose either — its own comment asserts "PAYABLE_CONTROL is already a seeded, mapped
 *       purpose on every chart", which is true of a CoaSeeder-fresh chart and false of every
 *       chart that has been used. The consequence is not cosmetic: wave 1's E5 fallback chain
 *       resolves SERVICE_PAYABLE/{type} through PAYABLE_CONTROL, so with PAYABLE_CONTROL
 *       unmapped every flight and hotel sale — ~97% of the population — dies on
 *       UnmappedPurposeException, uncaught, out of postSaleJournalEntries().
 *       This command closes it in one of two ways (see repairControlPools()).
 *
 *   G6/G7/G8. `report_type` contradicts the account's own root on 89 accounts (87 Expenses and
 *       2 Income filed as `balance sheet` — a P&L that selects on that column drops them);
 *       `account_type_id` is NULL on 1,489 of 1,489 accounts against a fully seeded 30-row
 *       `account_types` table; `is_group` contradicts the real child count on 216. None of the
 *       three is derived from anything the seeders own, so none of them is theirs to fix.
 *
 *   G9-G14. Duplicate codes (28 groups, 290 accounts), unused leaves, accounts carrying BOTH
 *       children and journal activity, cross-company journal lines, rootless accounts. Every one
 *       of these needs either an owner ruling or a money-moving data migration, so this command
 *       WRITES THEM DOWN (`coa_linkage_findings`) and changes nothing.
 *
 * ── The safety contract ────────────────────────────────────────────────────────────────────────
 * Every APPLIED change in this command is provably balance-neutral, and the test suite pins that
 * with a whole-chart trial balance taken before and after:
 *   - minting a leaf creates an account with no journal lines;
 *   - mapping a purpose writes a `system_accounts` row, which no report reads;
 *   - `account_type_id` / `report_type` / `is_group` are classification columns —
 *     TrialBalanceService groups by ROOT, not by any of them.
 * NOTHING here writes, updates or deletes a `journal_entries` or `transactions` row, ever.
 *
 * The one structural change that is NOT balance-neutral-by-construction is relocating an
 * account. It is refused outright unless --allow-move is passed, and when it is permitted the
 * before/after ancestor path of every moved account is logged (channel
 * `accounting.coa_linkage.move`) and echoed to the console. An account is never DELETED by this
 * command under any flag.
 *
 * ── Idempotency ────────────────────────────────────────────────────────────────────────────────
 * A second --apply run is a no-op: leaf creation is keyed on (company, parent, name) by
 * {@see AccountService::createSystemLeaf()}; purpose mapping goes through SystemAccountsSeeder's
 * own updateOrCreate; the three column backfills only touch rows that are still wrong; the
 * findings table is rewritten per company from the current measurement. The tests assert a
 * literal zero-change second run.
 */
class CoaLinkage extends Command
{
    protected $signature = 'accounting:coa-linkage
                            {--company= : Company id to process (default: every company that has accounts)}
                            {--dry-run : Report the full change list without writing anything (the default whenever --apply is absent)}
                            {--apply : Actually write the changes}
                            {--allow-move : Permit relocating an account that carries journal activity; without it every move is refused and reported}
                            {--rollback= : Undo one previous --apply run by its run id, restoring every report_type / is_group / account_type_id this command changed}';

    protected $description = 'CT-A4 — verify and repair chart-of-accounts linkage: mint the control leaves a used chart needs, map every engine purpose code, backfill account_type_id/report_type/is_group, and record every duplicate, unused leaf and cross-company row as a flag-only finding.';

    /**
     * CONTROL POOLS — the G1 mechanism, expressed as data so a second pool that suffers the same
     * fate never needs new code.
     *
     * A "control pool" is an account that the purpose-code seeder maps a GLOBAL control purpose
     * onto by name-and-chain, and that a live system can turn into a group behind the seeder's
     * back by minting children under it. `2110 Creditors` is the one this chart has: six payment
     * instruments were created under it, and `mapByChain()`'s `$account->children()->exists()`
     * skip then leaves PAYABLE_CONTROL permanently unmapped.
     *
     * `RECEIVABLE_CONTROL` is deliberately NOT listed. Its target (`1351 Clients`) has zero
     * children on this chart and there is no call site that mints one — client positions are
     * carried on `journal_entries.type_reference_id`, not on per-client leaves (CT-A4 §3.4).
     * Adding it here "just in case" would mint a leaf nobody needs on every chart.
     *
     * `controlSuffix` is the '{pool code}9' convention CT-A2 established and CT-A3's replay
     * verified: 2120 -> 21209, 2130 -> 21309, and therefore 2110 -> 21109. It is a PREFERENCE,
     * not a requirement — nextControlCode() falls through to max(numeric sibling)+1 if the
     * preferred code is already taken anywhere in the company.
     *
     * The suffix is '9' and not '09': the first server dry run against a faithful copy of the
     * City Travelers dev chart produced '211009' from a '09' suffix, which is neither CT-A2's
     * convention nor a code any sibling would recognise. Caught by reading the change list, not
     * by a test — the unit fixture asserted `poolCode . suffix` and so agreed with whatever the
     * suffix happened to be. The test now pins the literal '21109'.
     *
     * @var array<int, array{poolName: string, poolChain: array<int, string>, purposeCode: string, controlName: string, controlSuffix: string}>
     */
    private const CONTROL_POOLS = [
        [
            'poolName' => 'Creditors',
            'poolChain' => ['Accounts Payable', 'Liabilities'],
            'purposeCode' => 'PAYABLE_CONTROL',
            'controlName' => 'Creditors Control',
            'controlSuffix' => '9',
        ],
    ];

    /**
     * account_type_id derivation, most specific first.
     *
     * KEY IS AN ANCESTOR (or self) ACCOUNT NAME; value is the `account_types.name` to use. The
     * walk starts at the account and climbs; the first name that matches wins. This is why the
     * order of this array does not matter but the DIRECTION of the walk does — a leaf under
     * 'Bank Accounts' under 'Assets' must resolve to 'Bank', not 'Current Asset'.
     *
     * Deliberately keyed on NAME and not on code: this chart's codes collide (28 duplicate-code
     * groups, CT-A4 §1.6) and a code-keyed rule would classify 36 per-supplier flight-cost
     * leaves as whatever `5111 Visa Cost` is.
     */
    private const TYPE_BY_ANCESTOR_NAME = [
        'Bank Accounts' => 'Bank',
        'Cash In Hand' => 'Cash',
        'Accumulated Depreciation' => 'Accumulated Depreciation',
        'Fixed Assets' => 'Fixed Asset',
        'Capital Work in Progress' => 'Capital Work in Progress',
        'Accounts Receivable' => 'Receivable',
        'Accounts Payable' => 'Payable',
        'Duties and Taxes' => 'Tax',
        'Stock Assets' => 'Stock',
        'Stock Liabilities' => 'Stock',
        'Temporary Accounts' => 'Temporary',
        'Direct Expenses (Cost of Sales)' => 'Cost of Goods Sold',
        'Indirect Expenses (Operating Expenses)' => 'Indirect Expense',
        'Direct Income' => 'Direct Income',
        'Depreciation' => 'Depreciation',
        'Round Off' => 'Round Off',
    ];

    /** Fallback by root name when no ancestor matches TYPE_BY_ANCESTOR_NAME. */
    private const TYPE_BY_ROOT_NAME = [
        'Assets' => 'Current Asset',
        'Liabilities' => 'Current Liability',
        'Equity' => 'Equity',
        'Income' => 'Income Account',
        'Expenses' => 'Expense Account',
    ];

    private const REPORT_TYPE_BY_ROOT_NAME = [
        'Assets' => Account::REPORT_TYPES['BALANCE_SHEET'],
        'Liabilities' => Account::REPORT_TYPES['BALANCE_SHEET'],
        'Equity' => Account::REPORT_TYPES['BALANCE_SHEET'],
        'Income' => Account::REPORT_TYPES['PROFIT_LOSS'],
        'Expenses' => Account::REPORT_TYPES['PROFIT_LOSS'],
    ];

    /**
     * Purpose codes whose absence is NOT a blocking defect, with the reason recorded on the
     * finding so nobody "fixes" one of them later without reading why.
     *
     * SUSPENSE is the important one. This chart carries `3900 Suspense / Adjustments` with 3,676
     * legacy rows and a net of KWD 22,766.017, and `mapByName('Suspense')` misses it because of
     * the ' / Adjustments'. That miss is CORRECT and must stay: the wave-1 engine posts nothing
     * to suspense (CT-A3 §3.3 — Equity nets to exactly 0.000 at both cutoffs against the legacy
     * ledger's 208,579.698 plug), and mapping the purpose would hand a future feeder a plug
     * account to hide an imbalance in. Disposing of the legacy 3900 balance is owner ruling
     * R-CT2, not a linkage repair.
     *
     * @var array<string, string>
     */
    private const NON_BLOCKING_PURPOSES = [
        'SUSPENSE' => 'deliberate: the engine posts nothing to suspense (CT-A3 §3.3). Mapping this would hand a feeder a plug account. Disposing of the legacy 3900 balance is owner ruling R-CT2.',
        'VAT_OUTPUT' => 'deliberate: Kuwait v1 has no VAT; GCC VAT is P9. SystemAccountsSeeder reports the gap rather than guessing a leaf.',
    ];

    /**
     * CT-A3 R2-5. Purpose FAMILIES (matched by prefix) whose absence is a RULING, not a blocker.
     *
     * `GATEWAY_CLEARING_` / `GATEWAY_FEE_EXPENSE_`: `SystemAccountsSeeder::resolveGatewayClearing()`
     * deliberately refuses to fall back onto an unrelated sibling once the `1300 Payment Gateway`
     * pool has any children, because real production data proved that pool holds genuinely
     * non-gateway instruments ('Cash', 'Cheques', 'Deema', 'Tabby'). A company that does not
     * transact on a given gateway has no leaf for it and needs none; a company that DOES will be
     * refused loudly by AccountResolver at posting time. Either way it is a leaf an operator NAMES
     * on the Purpose Mapping screen — this command must never guess it.
     *
     * Classified `ruling` rather than `hygiene` so it still stands out in the findings table, and
     * so this command's own exit code (R2-5: non-zero while any BLOCKING finding remains) means
     * "something is genuinely unpostable", not "you have not configured a gateway you do not use".
     * CT-A4's own CoaLinkageCommandTest already carved this family out of its assertion by hand;
     * the carve-out now lives in the command, where the exit code can honour it.
     *
     * @var array<string, string>
     */
    private const NON_BLOCKING_PURPOSE_PREFIXES = [
        'GATEWAY_CLEARING_' => 'deliberate: a per-gateway clearing leaf exists only for a gateway the company actually transacts on. SystemAccountsSeeder refuses to guess one from the 1300 pool, which holds non-gateway instruments. An operator names it on the Purpose Mapping screen.',
        'GATEWAY_FEE_EXPENSE_' => 'deliberate: same as GATEWAY_CLEARING_ — a fee leaf for a gateway the company does not use is not a defect.',
    ];

    /**
     * CT-A3 R2-5 / MERGE FIX — the purpose the merge of wave 2 and CT-A4 left BLOCKING on day one.
     *
     * Wave 2 §4.7 REGISTERED `REFUND_PAYOUT_CASH_BANK` so it could be mapped, and deliberately did
     * not auto-map it in the SEEDER: *"which account client money leaves from is the company's own
     * choice, and seeding a guess would put real money in an account nobody chose."* That reasoning
     * is right for a seeder, which runs on every chart and knows nothing about the company. It is
     * NOT right for THIS command, which is an explicit, operator-invoked, dry-runnable,
     * NOW-REVERSIBLE repair that reads the company's own configured master data.
     *
     * Left as it was, the merged stack shipped a day-one operator task: `--apply` reported a
     * BLOCKING unresolved purpose and EVERY `refund_out` disposition threw UnmappedPurposeException
     * until someone picked an account by hand. So this command resolves the company's DEFAULT
     * REFUND-PAYOUT INSTRUMENT the same way the receipt instrument leg does (R-CT3: configured
     * payment-method account, `charges.acc_bank_id`, asserted under the bank group — never a code
     * constant, never a name), in this precedence:
     *
     *   1. The company's SYSTEM-DEFAULT active payment method (`charges.is_system_default`) whose
     *      `acc_bank_id` resolves under the bank group. This is the operator's own recorded choice
     *      of default instrument, so mapping onto it is reading configuration, not guessing.
     *   2. If there is no system default: the ONE distinct bank account shared by every active
     *      payment method that has one. Unambiguous by construction — and FLAGGED as a `ruling`
     *      finding, because it was inferred rather than chosen.
     *   3. Otherwise the cash/bank CONTROL leaf the configured receipt fallback purpose resolves to
     *      (`config('accounting.receipt.instrument.fallback_purpose')`, default CASH_IN_HAND) —
     *      FLAGGED, loudly, as a `ruling` finding naming the account, so refunds work on day one
     *      and the operator can still re-point them.
     *
     * Never overwrites an existing mapping: if the purpose already resolves, this does nothing.
     */
    private const REFUND_PAYOUT_PURPOSE = 'REFUND_PAYOUT_CASH_BANK';

    private bool $apply = false;

    private bool $allowMove = false;

    /** CT-A3 R2-5: one id per --apply INVOCATION, echoed for --rollback. */
    private string $runId = '';

    /** @var array<int, array{company_id:int, subject_id:int, column_name:string, before:?string, after:?string}> */
    private array $columnChanges = [];

    /** CT-A3 R2-5: set when any BLOCKING finding survives the repair — drives the exit code. */
    private bool $blockingRemains = false;

    /** @var array<int, array{company: int, action: string, subject: string, detail: string}> */
    private array $changeLog = [];

    public function handle(AccountService $accountService, AccountResolver $resolver): int
    {
        $this->apply = (bool) $this->option('apply');
        $this->allowMove = (bool) $this->option('allow-move');

        // CT-A3 R2-5 (verify-R1 D14): the way back. Runs before every other branch because it is
        // its own mode, not a variant of the repair.
        if (($rollbackRunId = (string) ($this->option('rollback') ?? '')) !== '') {
            if ($this->apply || $this->option('dry-run')) {
                $this->error('Pass --rollback on its own, not with --apply or --dry-run.');

                return self::FAILURE;
            }

            return $this->rollbackRun($rollbackRunId);
        }

        if ($this->apply && $this->option('dry-run')) {
            $this->error('Pass --dry-run OR --apply, not both.');

            return self::FAILURE;
        }

        $this->runId = (string) Str::ulid();

        $companyIds = $this->resolveCompanyIds();

        if ($companyIds === []) {
            $this->warn('No company with any account row — nothing to do.');

            return self::SUCCESS;
        }

        $this->line($this->apply
            ? '<fg=yellow>APPLY</> — changes will be written.'
            : '<fg=cyan>DRY RUN</> — nothing will be written. Re-run with --apply to commit.');

        if (! $this->allowMove) {
            $this->line('Moves are REFUSED (no --allow-move). Any relocation this run would want is reported, not performed.');
        }

        $exit = self::SUCCESS;

        foreach ($companyIds as $companyId) {
            $this->newLine();
            $this->info("═══ company #{$companyId} ═══");

            try {
                $this->processCompany($companyId, $accountService, $resolver);
            } catch (Throwable $e) {
                $this->error("company #{$companyId}: {$e->getMessage()}");
                $exit = self::FAILURE;
            }
        }

        $this->newLine();
        $this->renderChangeLog();
        $this->flushColumnChanges();

        // CT-A3 R2-5 (verify-R1 D14, second half): *"--apply exits 0 even with BLOCKING findings —
        // failure is set only on a thrown exception. Do not gate a deploy on this command's exit
        // status."* A command whose exit code cannot be gated on is a command every runbook will
        // gate on anyway. It now means what an operator assumes it means.
        // Gated on --apply, not on every mode. A DRY RUN's findings describe the chart AS IT
        // STANDS -- nothing has been repaired yet, so of course purposes are unresolved; making a
        // dry run exit non-zero would mean "this command has work to do", which is not a failure
        // and would train every operator to ignore the code. An APPLY's findings describe the chart
        // AFTER the repair, and a purpose still unresolved there is a document the engine will
        // refuse to post.
        if ($this->apply && $this->blockingRemains) {
            $this->newLine();
            $this->error('BLOCKING findings remain — see coa_linkage_findings WHERE severity = \'blocking\'.');
            $this->line('  These are purposes the engine will REFUSE to post on. Nothing here needs a guess:');
            $this->line('  each names the leaf an operator must pick on the Purpose Mapping screen.');

            return self::FAILURE;
        }

        return $exit;
    }

    /**
     * CT-A3 R2-5 (verify-R1 D14) — undo one previous `--apply` run, exactly.
     *
     * Restores every `report_type` / `is_group` / `account_type_id` that run changed, to the value
     * that was there BEFORE it — never to a value re-derived from the rules, which would just be
     * the repair again. Rows already rolled back are skipped, so a second `--rollback` of the same
     * run is a no-op rather than a re-application of stale before-values.
     *
     * Refuses when the current value no longer matches what the run WROTE: something else has
     * changed that account since, and silently overwriting it would make this command a second
     * source of unexplained column edits. Those rows are named and left alone.
     */
    private function rollbackRun(string $runId): int
    {
        $rows = DB::table('coa_linkage_changes')
            ->where('run_id', $runId)
            ->whereNull('rolled_back_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("No un-rolled-back changes recorded for run '{$runId}'.");
            $this->line('  Run ids are echoed by every --apply run and stored in coa_linkage_changes.run_id.');

            return self::SUCCESS;
        }

        $restored = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, &$restored, &$skipped) {
            foreach ($rows as $row) {
                if (! in_array($row->column_name, CoaLinkageChange::REVERSIBLE_COLUMNS, true)) {
                    $skipped[] = "account #{$row->subject_id}: column '{$row->column_name}' is not reversible";

                    continue;
                }

                $current = DB::table('accounts')->where('id', $row->subject_id)->value($row->column_name);

                if ((string) $current !== (string) $row->after_value) {
                    $skipped[] = sprintf(
                        'account #%d.%s is now %s, not the %s this run wrote — left alone',
                        $row->subject_id,
                        $row->column_name,
                        $current === null ? 'NULL' : (string) $current,
                        $row->after_value === null ? 'NULL' : (string) $row->after_value
                    );

                    continue;
                }

                DB::table('accounts')->where('id', $row->subject_id)->update([
                    $row->column_name => $this->castBackTo($row->column_name, $row->before_value),
                    'updated_at' => now(),
                ]);

                DB::table('coa_linkage_changes')->where('id', $row->id)->update([
                    'rolled_back_at' => now(),
                    'updated_at' => now(),
                ]);

                $restored++;
            }
        });

        $this->info("Rolled back run '{$runId}': {$restored} column value(s) restored.");

        foreach ($skipped as $line) {
            $this->warn('  skipped — '.$line);
        }

        return self::SUCCESS;
    }

    /** The stored before-image is a string; put it back at the column's own type (NULL stays NULL). */
    private function castBackTo(string $column, ?string $value): int|string|null
    {
        if ($value === null) {
            return null;
        }

        return in_array($column, ['is_group', 'account_type_id'], true) ? (int) $value : $value;
    }

    /** @return array<int, int> */
    private function resolveCompanyIds(): array
    {
        $one = $this->option('company');

        if ($one !== null && $one !== '') {
            return [(int) $one];
        }

        return DB::table('accounts')
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    private function processCompany(int $companyId, AccountService $accountService, AccountResolver $resolver): void
    {
        $this->reportInventory($companyId);

        // ORDER MATTERS AND IS NOT ARBITRARY.
        //   1. The control leaf must exist BEFORE the seeder runs, or PAYABLE_CONTROL has
        //      nothing to map to and step 2 reports the same gap it just failed to fix.
        //   2. ensure-system-leaves mints/adopts every other named leaf and re-runs
        //      SystemAccountsSeeder for the whole company, which is what actually writes the
        //      system_accounts rows — including the one for the leaf step 1 just created.
        //   3-5. The three column backfills are independent of the mapping and of each other,
        //      but account_type_id inherits from ancestors, so it walks the tree level by level.
        //   6. Verification runs LAST, against the repaired state, so its output is the honest
        //      answer to "does every purpose resolve now".
        $this->repairControlPools($companyId, $accountService);
        $this->runEnsureSystemLeaves($companyId);
        $this->backfillAccountTypeId($companyId);
        $this->backfillReportType($companyId);
        $this->backfillIsGroup($companyId);

        $findings = [];

        // 5b. CT-A3 R2-5 / MERGE FIX. AFTER ensure-system-leaves (so it sees whatever that mapped)
        //     and BEFORE verifyPurposes (so the verification reports the repaired state, which is
        //     the whole point of running it last). See REFUND_PAYOUT_PURPOSE's own docblock.
        $this->mapRefundPayoutInstrument($companyId, $resolver, $findings);

        $this->verifyPurposes($companyId, $resolver, $findings);
        $this->collectStructuralFindings($companyId, $findings);
        $this->persistFindings($companyId, $findings);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Inventory
    // ─────────────────────────────────────────────────────────────────────────────────────────

    private function reportInventory(int $companyId): void
    {
        $total = $this->accounts($companyId)->count();

        if ($total === 0) {
            $this->warn('  no accounts — skipping');

            return;
        }

        $trueLeaves = $this->accounts($companyId)
            ->whereNotExists(fn ($q) => $q->from('accounts as c')->whereColumn('c.parent_id', 'accounts.id')->whereNull('c.deleted_at'))
            ->count();

        $mappings = DB::table('system_accounts')->where('company_id', $companyId)->count();

        $dangling = DB::table('system_accounts as sa')
            ->leftJoin('accounts as a', 'a.id', '=', 'sa.account_id')
            ->where('sa.company_id', $companyId)
            ->whereNull('a.id')
            ->count();

        $this->line(sprintf(
            '  accounts %d (leaves %d, groups %d) · system_accounts %d (dangling %d)',
            $total,
            $trueLeaves,
            $total - $trueLeaves,
            $mappings,
            $dangling
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // G1 — control pools
    // ─────────────────────────────────────────────────────────────────────────────────────────

    private function repairControlPools(int $companyId, AccountService $accountService): void
    {
        foreach (self::CONTROL_POOLS as $spec) {
            $pool = $this->resolveByChain($companyId, $spec['poolName'], $spec['poolChain']);

            if ($pool === null) {
                $this->line("  control pool '{$spec['poolName']}': not on this chart — nothing to do");

                continue;
            }

            $children = $this->childrenOf($pool->id);

            if ($children->isEmpty()) {
                // The pool is still a leaf, so the seeder's own mapByChain() maps the purpose
                // onto it directly, exactly as it does on a CoaSeeder-fresh chart. Minting a
                // control child here would BREAK that — it would turn the pool into a group and
                // move the purpose off an account that may already carry history.
                $this->line("  control pool '{$spec['poolName']}' (#{$pool->id}) is still a leaf — {$spec['purposeCode']} maps to it directly, no control leaf needed");

                continue;
            }

            $existingControl = $children->firstWhere('name', $spec['controlName']);

            if ($existingControl !== null) {
                $this->line("  control pool '{$spec['poolName']}': '{$spec['controlName']}' (#{$existingControl->id}, code {$existingControl->code}) already present");

                continue;
            }

            if ($this->allowMove) {
                $this->movePoolChildrenUp($companyId, $pool, $children, $spec);

                continue;
            }

            $code = $this->nextControlCode($companyId, (string) $pool->code, $spec['controlSuffix'], $children);

            $this->recordChange(
                $companyId,
                'CREATE_CONTROL_LEAF',
                "{$spec['controlName']} ({$code})",
                sprintf(
                    "under '%s' (#%d, code %s), which has %d children and therefore cannot itself back %s",
                    $pool->name,
                    $pool->id,
                    $pool->code,
                    $children->count(),
                    $spec['purposeCode']
                )
            );

            // The move alternative — relocating the pool's children up one level so the pool
            // becomes a leaf again — is the structurally cleaner answer and is what --allow-move
            // does. It is NOT the default because on this chart those six children carry 2,989
            // journal rows between them, and relocating a posted account changes which group
            // every historical report rolls it into. Minting a sibling control leaf changes
            // nothing that already exists.
            $this->line(sprintf(
                '    (alternative, needs --allow-move: relocate the %d children of #%d up to its parent so the pool is a leaf again)',
                $children->count(),
                $pool->id
            ));

            if (! $this->apply) {
                continue;
            }

            $leaf = $accountService->createSystemLeaf(
                $companyId,
                array_merge([$spec['poolName']], $spec['poolChain']),
                $spec['controlName'],
                $code
            );

            $this->line("    created account #{$leaf->id} '{$leaf->name}' code {$leaf->code} under #{$pool->id}");
        }
    }

    /**
     * --allow-move: relocate every child of the control pool up to the pool's own parent, so the
     * pool becomes a leaf again and the seeder maps the purpose onto it directly — no new
     * account at all.
     *
     * Logs the before/after ancestor PATH of every moved account, not just its parent id: the
     * whole point of the guard is that a human can read what the move did to the report tree.
     */
    private function movePoolChildrenUp(int $companyId, object $pool, $children, array $spec): void
    {
        if ($pool->parent_id === null) {
            $this->warn("  control pool '{$spec['poolName']}' (#{$pool->id}) is a root — refusing to move its children above a root");

            return;
        }

        foreach ($children as $child) {
            $before = $this->ancestorPath((int) $child->id);
            $lines = $this->journalRowCount((int) $child->id);

            $this->recordChange(
                $companyId,
                'MOVE_ACCOUNT',
                "#{$child->id} {$child->code} {$child->name}",
                sprintf('%s  →  parent #%d (%d journal row(s) follow it)', $before, $pool->parent_id, $lines)
            );

            if (! $this->apply) {
                continue;
            }

            DB::table('accounts')->where('id', $child->id)->update([
                'parent_id' => $pool->parent_id,
                'level' => DB::raw('GREATEST(1, `level` - 1)'),
                'updated_at' => now(),
            ]);

            $after = $this->ancestorPath((int) $child->id);

            Log::warning('accounting.coa_linkage.move', [
                'company_id' => $companyId,
                'account_id' => (int) $child->id,
                'code' => (string) $child->code,
                'journal_rows' => $lines,
                'path_before' => $before,
                'path_after' => $after,
            ]);

            $this->line("    moved #{$child->id}: {$before}  →  {$after}");
        }

        // The pool has just become a leaf; is_group is repaired by backfillIsGroup() below.
    }

    /**
     * Preferred '{pool code}09'; if that is taken anywhere in the company, the next free code
     * above the highest numeric sibling. Never returns a code any account in the company already
     * holds — createSystemLeaf() would refuse it, and on a chart with 28 duplicate-code groups
     * (CT-A4 §1.6) "the parent's code + 1" is exactly the arithmetic that produced them.
     */
    private function nextControlCode(int $companyId, string $poolCode, string $suffix, $children): string
    {
        $preferred = $poolCode.$suffix;

        if (! $this->codeTaken($companyId, $preferred)) {
            return $preferred;
        }

        $highest = 0;

        foreach ($children as $child) {
            if (preg_match('/^\d+$/', (string) $child->code) === 1) {
                $highest = max($highest, (int) $child->code);
            }
        }

        if ($highest === 0) {
            $highest = (int) preg_replace('/\D/', '', $poolCode);
        }

        $candidate = $highest + 1;

        while ($this->codeTaken($companyId, (string) $candidate)) {
            $candidate++;
        }

        return (string) $candidate;
    }

    private function codeTaken(int $companyId, string $code): bool
    {
        return DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->exists();
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // G2/G3/G4/G5 — delegate to the existing leaf/purpose backfill
    // ─────────────────────────────────────────────────────────────────────────────────────────

    private function runEnsureSystemLeaves(int $companyId): void
    {
        $args = ['--company' => (string) $companyId];

        if (! $this->apply) {
            $args['--dry-run'] = true;
        }

        $this->line('  → accounting:ensure-system-leaves '.($this->apply ? '--apply' : '--dry-run'));

        $code = Artisan::call('accounting:ensure-system-leaves', $args, $this->getOutput());

        $this->recordChange(
            $companyId,
            'ENSURE_SYSTEM_LEAVES',
            'named leaves + purpose remap',
            'delegated to accounting:ensure-system-leaves, exit '.$code
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // G7 — account_type_id
    // ─────────────────────────────────────────────────────────────────────────────────────────

    private function backfillAccountTypeId(int $companyId): void
    {
        $typeIdByName = DB::table('account_types')->pluck('id', 'name');

        if ($typeIdByName->isEmpty()) {
            $this->warn('  account_types is empty — skipping account_type_id backfill');

            return;
        }

        // Level order, so a child can inherit a value its parent was given moments ago in this
        // same pass rather than waiting for a second run.
        $rows = $this->accounts($companyId)
            ->select('id', 'name', 'parent_id', 'root_id', 'level', 'account_type_id')
            ->orderBy('level')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $resolved = [];
        $changed = 0;

        foreach ($rows as $row) {
            if ($row->account_type_id !== null) {
                $resolved[$row->id] = (int) $row->account_type_id;

                continue;
            }

            $typeName = $this->deriveTypeName($row, $rows, $resolved, $typeIdByName);

            if ($typeName === null) {
                continue;
            }

            $typeId = (int) $typeIdByName[$typeName];
            $resolved[$row->id] = $typeId;
            $changed++;

            $this->recordColumnChange($companyId, (int) $row->id, 'account_type_id', $row->account_type_id, (string) $typeId);

            if ($this->apply) {
                DB::table('accounts')->where('id', $row->id)->update([
                    'account_type_id' => $typeId,
                    'updated_at' => now(),
                ]);
            }
        }

        $stillNull = $rows->count() - count($resolved);

        $this->line(sprintf('  account_type_id: %d set, %d left NULL (no derivable type)', $changed, $stillNull));

        if ($changed > 0) {
            $this->recordChange($companyId, 'SET_ACCOUNT_TYPE_ID', "{$changed} account(s)", 'derived from the nearest ancestor family, else the root');
        }
    }

    /**
     * Walk self → ancestors. The FIRST name in TYPE_BY_ANCESTOR_NAME wins; a parent that already
     * has a concrete account_type_id (from a previous run, or from earlier in this pass) is
     * inherited verbatim; otherwise fall back to the root's own default.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array<int, int>  $resolved
     */
    private function deriveTypeName(object $row, $rows, array $resolved, $typeIdByName): ?string
    {
        $cursor = $row;
        $guard = 0;

        while ($cursor !== null && $guard++ < 32) {
            if (isset(self::TYPE_BY_ANCESTOR_NAME[$cursor->name])
                && $typeIdByName->has(self::TYPE_BY_ANCESTOR_NAME[$cursor->name])) {
                return self::TYPE_BY_ANCESTOR_NAME[$cursor->name];
            }

            if ($cursor->id !== $row->id && isset($resolved[$cursor->id])) {
                return $typeIdByName->search($resolved[$cursor->id]) ?: null;
            }

            $cursor = $cursor->parent_id !== null ? ($rows[$cursor->parent_id] ?? null) : null;
        }

        $rootName = $row->root_id !== null ? ($rows[$row->root_id]->name ?? null) : $row->name;

        if ($rootName !== null && isset(self::TYPE_BY_ROOT_NAME[$rootName]) && $typeIdByName->has(self::TYPE_BY_ROOT_NAME[$rootName])) {
            return self::TYPE_BY_ROOT_NAME[$rootName];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // G6 — report_type
    // ─────────────────────────────────────────────────────────────────────────────────────────

    private function backfillReportType(int $companyId): void
    {
        $rows = $this->accounts($companyId)
            ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.root_id', 'accounts.report_type')
            ->get();

        $rootNames = DB::table('accounts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNull('parent_id')
            ->pluck('name', 'id');

        $changed = 0;
        $byRoot = [];
        $intoProfitLoss = [];
        $outOfProfitLoss = [];

        foreach ($rows as $row) {
            // A root classifies itself; everything else classifies by its root.
            $rootName = $row->root_id !== null ? ($rootNames[$row->root_id] ?? null) : $row->name;

            if ($rootName === null || ! isset(self::REPORT_TYPE_BY_ROOT_NAME[$rootName])) {
                continue;
            }

            $want = self::REPORT_TYPE_BY_ROOT_NAME[$rootName];

            if ((string) $row->report_type === $want) {
                continue;
            }

            $changed++;
            $byRoot[$rootName] = ($byRoot[$rootName] ?? 0) + 1;

            // CT-A3 R2-5 (verify-R1 D14): the before-image. This is the one applied repair with a
            // visible REPORTING consequence and it was summarised as a bare count -- no ids, no
            // before-values, nothing in coa_linkage_findings -- so it could not be undone from the
            // command's own output. Recorded whether or not --apply is passed, so a dry run can
            // print the same delta a real run would produce; only --apply persists it.
            $this->recordColumnChange($companyId, (int) $row->id, 'report_type', $row->report_type, $want);

            // The P&L COMPOSITION DELTA. ReportController selects the P&L by this column, so an
            // account moving INTO profit_loss changes reported profit for every historical period
            // on the next render -- by exactly its own balance.
            $bucket = $want === Account::REPORT_TYPES['PROFIT_LOSS'] ? 'in' : 'out';
            $entry = [
                'id' => (int) $row->id,
                'code' => (string) ($row->code ?? ''),
                'name' => (string) $row->name,
                'from' => $row->report_type === null ? 'NULL' : (string) $row->report_type,
                'to' => $want,
                'balance' => $this->accountNetBalance((int) $row->id),
            ];

            if ($bucket === 'in') {
                $intoProfitLoss[] = $entry;
            } else {
                $outOfProfitLoss[] = $entry;
            }

            if ($this->apply) {
                DB::table('accounts')->where('id', $row->id)->update([
                    'report_type' => $want,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($changed === 0) {
            $this->line('  report_type: already consistent with every account\'s root');

            return;
        }

        $detail = implode(', ', array_map(static fn ($k, $v) => "{$k} {$v}", array_keys($byRoot), $byRoot));

        $this->line("  report_type: {$changed} account(s) contradict their root ({$detail})");

        // Worth a loud line: this is the one applied repair with a visible reporting consequence.
        // The trial balance groups by root and is unaffected (the tests pin that), but a P&L that
        // selects rows by report_type gains every expense account that was filed as a balance
        // sheet line.
        $this->warn("    → a P&L selecting on report_type will now include these {$changed} account(s). Review the P&L delta before deploying.");

        $this->recordChange($companyId, 'SET_REPORT_TYPE', "{$changed} account(s)", "derived from the root: {$detail}");

        $this->renderProfitLossDelta($intoProfitLoss, $outOfProfitLoss);
    }

    /**
     * CT-A3 R2-5 (verify-R1 D14) — WHICH accounts move into (or out of) the P&L, and what they
     * carry.
     *
     * *"Do not run `accounting:coa-linkage --apply` on the dev chart without snapshotting
     * `report_type` first, and do not read its exit code as a pass."* Both halves of that condition
     * are now closed in the command itself: the before-image is written to `coa_linkage_changes`
     * (and `--rollback` puts it back), and this is the half an operator has to READ BEFORE they
     * decide to run it — the 87 accounts CT-A4 measured, by id and code, with the balance each one
     * brings with it.
     *
     * Printed on BOTH dry-run and apply. A dry run that reported a count and an apply that reported
     * the same count gave an operator nothing to compare; the figure that matters is the net change
     * to reported profit, and it is stated here as a total.
     *
     * @param  array<int, array{id:int, code:string, name:string, from:string, to:string, balance:float}>  $into
     * @param  array<int, array{id:int, code:string, name:string, from:string, to:string, balance:float}>  $out
     */
    private function renderProfitLossDelta(array $into, array $out): void
    {
        if ($into === [] && $out === []) {
            return;
        }

        $this->newLine();
        $this->line('  P&L COMPOSITION DELTA — what a profit-and-loss selecting on report_type will show differently:');

        foreach ([['moving INTO the P&L', $into], ['moving OUT of the P&L', $out]] as [$label, $group]) {
            if ($group === []) {
                continue;
            }

            $net = round(array_sum(array_column($group, 'balance')), 3);

            $this->line(sprintf('    %d account(s) %s — net Dr−Cr %s', count($group), $label, number_format($net, 3)));

            // Capped: the point is the total and a representative list, not a 90-row wall. The
            // count and the net are always exact, and every id is in coa_linkage_changes.
            foreach (array_slice($group, 0, 25) as $row) {
                $this->line(sprintf(
                    '      #%-6d %-10s %-44s %s → %s   %s',
                    $row['id'],
                    $row['code'],
                    mb_substr($row['name'], 0, 44),
                    $row['from'],
                    $row['to'],
                    number_format($row['balance'], 3)
                ));
            }

            if (count($group) > 25) {
                $this->line(sprintf('      … and %d more (every id is in coa_linkage_changes for this run)', count($group) - 25));
            }
        }
    }

    /**
     * Dr − Cr on one account, from the posted rows. Never `accounts.actual_balance` or
     * `journal_entries.balance` — CT-A1 §4.1 measured Σ|drift| KWD 6,277,563.301 across 200 of the
     * 207 posted accounts on those two columns.
     */
    private function accountNetBalance(int $accountId): float
    {
        return round((float) (DB::table('journal_entries')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as net')
            ->value('net') ?? 0.0), 3);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // G8 — is_group
    // ─────────────────────────────────────────────────────────────────────────────────────────

    private function backfillIsGroup(int $companyId): void
    {
        $withChildren = DB::table('accounts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereExists(fn ($q) => $q->from('accounts as c')->whereColumn('c.parent_id', 'accounts.id')->whereNull('c.deleted_at'))
            ->pluck('id')
            ->all();

        $flaggedGroupNoChildren = $this->accounts($companyId)
            ->where('is_group', 1)
            ->whereNotIn('accounts.id', $withChildren ?: [0])
            ->pluck('id')
            ->all();

        $flaggedLeafWithChildren = $this->accounts($companyId)
            ->where(fn ($q) => $q->where('is_group', 0)->orWhereNull('is_group'))
            ->whereIn('accounts.id', $withChildren ?: [0])
            ->pluck('id')
            ->all();

        $total = count($flaggedGroupNoChildren) + count($flaggedLeafWithChildren);

        if ($total === 0) {
            $this->line('  is_group: already equals EXISTS(child) on every account');

            return;
        }

        $this->line(sprintf(
            '  is_group: %d wrong (%d leaves flagged group, %d groups flagged leaf)',
            $total,
            count($flaggedGroupNoChildren),
            count($flaggedLeafWithChildren)
        ));

        // AccountResolver::isLeaf() and PostingService step 3d both ignore this column entirely
        // ("a leaf is any account with zero child rows, full stop"), so this repair changes
        // nothing the engine does — only what the screens and reports that DO filter on it see.
        $this->recordChange($companyId, 'SET_IS_GROUP', "{$total} account(s)", 'is_group := EXISTS(child); the engine ignores this column, the UI does not');

        // CT-A3 R2-5: before-images, so --rollback can put this back too. Recorded before the
        // write and regardless of --apply, so a dry run records what it WOULD do and only --apply
        // persists it (see flushColumnChanges()).
        foreach ($flaggedGroupNoChildren as $id) {
            $this->recordColumnChange($companyId, (int) $id, 'is_group', '1', '0');
        }

        foreach ($flaggedLeafWithChildren as $id) {
            $current = DB::table('accounts')->where('id', $id)->value('is_group');
            $this->recordColumnChange($companyId, (int) $id, 'is_group', $current === null ? null : (string) (int) $current, '1');
        }

        if (! $this->apply) {
            return;
        }

        if ($flaggedGroupNoChildren !== []) {
            DB::table('accounts')->whereIn('id', $flaggedGroupNoChildren)->update(['is_group' => 0, 'updated_at' => now()]);
        }

        if ($flaggedLeafWithChildren !== []) {
            DB::table('accounts')->whereIn('id', $flaggedLeafWithChildren)->update(['is_group' => 1, 'updated_at' => now()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Verification
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Resolve EVERY purpose code the engine can request through the real
     * {@see AccountResolver} — including the wave-1 fallback chain, so a per-service purpose that
     * rides PAYABLE_CONTROL/COST_OF_SALES_CONTROL counts as resolved, which is exactly what the
     * engine will do at posting time.
     *
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function verifyPurposes(int $companyId, AccountResolver $resolver, array &$findings): void
    {
        $ok = 0;
        $bad = 0;

        foreach ($this->requiredPurposes() as [$purposeCode, $serviceType]) {
            try {
                $resolver->resolve($purposeCode, $companyId, $serviceType);
                $ok++;
            } catch (Throwable $e) {
                $bad++;

                $label = $serviceType === null ? $purposeCode : "{$purposeCode}/{$serviceType}";
                [$deliberate, $severity] = $this->deliberateGapFor($purposeCode);

                if ($severity === CoaLinkageFinding::SEVERITY_BLOCKING) {
                    $this->blockingRemains = true;
                }

                $findings[] = [
                    'code' => 'UNRESOLVED_PURPOSE',
                    'subject_type' => 'purpose',
                    'subject_id' => null,
                    'severity' => $severity,
                    'summary' => "purpose {$label} does not resolve",
                    'details' => [
                        'purpose_code' => $purposeCode,
                        'service_type' => $serviceType,
                        'exception' => class_basename($e),
                        'message' => $e->getMessage(),
                        'deliberate' => $deliberate,
                    ],
                ];
            }
        }

        $total = $ok + $bad;
        $style = $bad === 0 ? 'info' : 'warn';

        $this->{$style}(sprintf('  purposes: %d of %d resolve (%d unresolved)', $ok, $total, $bad));
    }

    /**
     * The engine's full purpose vocabulary, assembled from config exactly the way
     * SystemAccountsSeeder assembles it — never a second hand-copied list.
     *
     * `anchors` are excluded on purpose: they are resolved by resolveAnchor(), name a GROUP not a
     * leaf, and this build deliberately does not seed them (config/accounting.php, 'anchors').
     *
     * @return array<int, array{0: string, 1: string|null}>
     */
    private function requiredPurposes(): array
    {
        $out = [];

        foreach ((array) config('accounting.purpose_codes.global', []) as $code) {
            $out[] = [$code, null];
        }

        foreach (array_keys((array) config('accounting.purpose_codes.gateways', [])) as $key) {
            $out[] = ["GATEWAY_CLEARING_{$key}", null];
            $out[] = ["GATEWAY_FEE_EXPENSE_{$key}", null];
        }

        foreach (array_keys((array) config('accounting.purpose_codes.fixed_asset_classes', [])) as $key) {
            $out[] = ["FA_COST_{$key}", null];
            $out[] = ["FA_ACCUM_DEP_{$key}", null];
        }

        foreach ((array) config('accounting.purpose_codes.per_service', []) as $code) {
            foreach ((array) config('accounting.purpose_codes.service_types', []) as $serviceType) {
                $out[] = [$code, $serviceType];
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Flag-only findings
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /** @param array<int, array<string, mixed>> $findings */
    private function collectStructuralFindings(int $companyId, array &$findings): void
    {
        $this->findDuplicateCodes($companyId, $findings);
        $this->findUnusedLeaves($companyId, $findings);
        $this->findNonLeafPostings($companyId, $findings);
        $this->findCrossCompanyLines($companyId, $findings);
        $this->findRootlessAccounts($companyId, $findings);
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function findDuplicateCodes(int $companyId, array &$findings): void
    {
        $groups = DB::table('accounts')
            ->select('code', DB::raw('COUNT(*) AS n'), DB::raw('GROUP_CONCAT(id ORDER BY id) AS ids'))
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('n')
            ->get();

        foreach ($groups as $group) {
            $findings[] = [
                'code' => 'DUPLICATE_CODE',
                'subject_type' => 'account',
                'subject_id' => (int) explode(',', (string) $group->ids)[0],
                'severity' => CoaLinkageFinding::SEVERITY_RULING,
                'summary' => "code {$group->code} is used by {$group->n} accounts",
                'details' => [
                    'code' => (string) $group->code,
                    'count' => (int) $group->n,
                    'account_ids' => array_map('intval', explode(',', (string) $group->ids)),
                    // Deliberately NOT renumbered. An account code is what a printed statement,
                    // an export and a saved report filter identify the account by; renumbering
                    // is a reporting-identity change and needs its own owner-approved pass.
                    'note' => 'flag only — renumbering is an owner decision (CT-A4 G10)',
                ],
            ];
        }

        if ($groups->isNotEmpty()) {
            $this->line("  findings: {$groups->count()} duplicate-code group(s)");
        }
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function findUnusedLeaves(int $companyId, array &$findings): void
    {
        $rows = $this->accounts($companyId)
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->whereNotExists(fn ($q) => $q->from('accounts as c')->whereColumn('c.parent_id', 'accounts.id')->whereNull('c.deleted_at'))
            ->whereNotExists(fn ($q) => $q->from('journal_entries as j')->whereColumn('j.account_id', 'accounts.id')->whereNull('j.deleted_at'))
            ->get();

        foreach ($rows as $row) {
            $findings[] = [
                'code' => 'UNUSED_LEAF',
                'subject_type' => 'account',
                'subject_id' => (int) $row->id,
                'severity' => CoaLinkageFinding::SEVERITY_HYGIENE,
                'summary' => "leaf {$row->code} '{$row->name}' has never been posted to",
                'details' => ['code' => (string) $row->code, 'name' => (string) $row->name],
            ];
        }

        if ($rows->isNotEmpty()) {
            $this->line("  findings: {$rows->count()} leaf(s) with no journal activity");
        }
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function findNonLeafPostings(int $companyId, array &$findings): void
    {
        $rows = $this->accounts($companyId)
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->whereExists(fn ($q) => $q->from('accounts as c')->whereColumn('c.parent_id', 'accounts.id')->whereNull('c.deleted_at'))
            ->whereExists(fn ($q) => $q->from('journal_entries as j')->whereColumn('j.account_id', 'accounts.id')->whereNull('j.deleted_at'))
            ->get();

        foreach ($rows as $row) {
            $findings[] = [
                'code' => 'NON_LEAF_POSTING',
                'subject_type' => 'account',
                'subject_id' => (int) $row->id,
                'severity' => CoaLinkageFinding::SEVERITY_RULING,
                'summary' => "account {$row->code} '{$row->name}' has both children and journal activity",
                'details' => [
                    'code' => (string) $row->code,
                    'children' => $this->childrenOf((int) $row->id)->count(),
                    'journal_rows' => $this->journalRowCount((int) $row->id),
                    // AccountResolver::resolve() throws NonLeafAccountException on these. Fixing
                    // one means either moving posted rows onto the child or promoting the parent
                    // — both move money between accounts, so neither is done here.
                    'note' => 'flag only — the engine refuses these with NonLeafAccountException (CT-A4 G11)',
                ],
            ];
        }

        if ($rows->isNotEmpty()) {
            $this->warn("  findings: {$rows->count()} account(s) with children AND journal activity — the engine refuses these");
        }
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function findCrossCompanyLines(int $companyId, array &$findings): void
    {
        $rows = DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->select('je.id', 'je.account_id', 'a.company_id as account_company_id', 'a.code', 'je.debit', 'je.credit')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereColumn('je.company_id', '<>', 'a.company_id')
            ->get();

        foreach ($rows as $row) {
            $findings[] = [
                'code' => 'CROSS_COMPANY_LINE',
                'subject_type' => 'journal_entry',
                'subject_id' => (int) $row->id,
                'severity' => CoaLinkageFinding::SEVERITY_RULING,
                'summary' => "journal line #{$row->id} (company {$companyId}) posts to account #{$row->account_id} owned by company {$row->account_company_id}",
                'details' => [
                    'account_id' => (int) $row->account_id,
                    'account_company_id' => (int) $row->account_company_id,
                    'code' => (string) $row->code,
                    'debit' => (string) $row->debit,
                    'credit' => (string) $row->credit,
                    'note' => 'flag only — AccountResolver throws CrossTenantAccountException on this today (CT-A4 G12)',
                ],
            ];
        }

        if ($rows->isNotEmpty()) {
            $this->warn("  findings: {$rows->count()} cross-company journal line(s)");
        }
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function findRootlessAccounts(int $companyId, array &$findings): void
    {
        $rows = $this->accounts($companyId)
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->whereNull('accounts.root_id')
            ->whereNotNull('accounts.parent_id')
            ->get();

        foreach ($rows as $row) {
            $findings[] = [
                'code' => 'ROOTLESS_ACCOUNT',
                'subject_type' => 'account',
                'subject_id' => (int) $row->id,
                'severity' => CoaLinkageFinding::SEVERITY_RULING,
                'summary' => "account {$row->code} '{$row->name}' has a parent but no root_id",
                'details' => ['code' => (string) $row->code, 'note' => 'flag only — re-rooting changes which report section this rolls into (CT-A4 G13)'],
            ];
        }

        if ($rows->isNotEmpty()) {
            $this->warn("  findings: {$rows->count()} account(s) with a parent but no root_id");
        }
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function persistFindings(int $companyId, array $findings): void
    {
        $this->line(sprintf('  findings total: %d', count($findings)));

        if (! $this->apply) {
            return;
        }

        // Rewritten wholesale per company: this table is the latest measurement, not a ticket
        // queue. A finding that has been remediated must vanish on the next run.
        DB::table('coa_linkage_findings')->where('company_id', $companyId)->delete();

        foreach (array_chunk($findings, 200) as $chunk) {
            DB::table('coa_linkage_findings')->insert(array_map(static function (array $f) use ($companyId) {
                return [
                    'company_id' => $companyId,
                    'code' => $f['code'],
                    'subject_type' => $f['subject_type'],
                    'subject_id' => $f['subject_id'],
                    'severity' => $f['severity'],
                    'summary' => mb_substr((string) $f['summary'], 0, 255),
                    'details' => json_encode($f['details'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $chunk));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────────────────────

    private function accounts(int $companyId)
    {
        return DB::table('accounts')->where('company_id', $companyId)->whereNull('deleted_at');
    }

    private function childrenOf(int $accountId)
    {
        return DB::table('accounts')->where('parent_id', $accountId)->whereNull('deleted_at')->get();
    }

    private function journalRowCount(int $accountId): int
    {
        return DB::table('journal_entries')->where('account_id', $accountId)->whereNull('deleted_at')->count();
    }

    /**
     * Resolve one account by (name, ancestor chain) the same way SystemAccountsSeeder's
     * mapByChain() does — WITHOUT its leaf test, because the whole point here is to find the
     * pool precisely when it has stopped being a leaf.
     */
    private function resolveByChain(int $companyId, string $name, array $ancestorChain): ?object
    {
        $candidates = $this->accounts($companyId)->where('name', $name)->get();

        foreach ($candidates as $candidate) {
            $cursor = $candidate;

            foreach ($ancestorChain as $ancestorName) {
                if ($cursor->parent_id === null) {
                    $cursor = null;
                    break;
                }

                $cursor = DB::table('accounts')->where('id', $cursor->parent_id)->whereNull('deleted_at')->first();

                if ($cursor === null || $cursor->name !== $ancestorName) {
                    $cursor = null;
                    break;
                }
            }

            if ($cursor !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function ancestorPath(int $accountId): string
    {
        $parts = [];
        $cursor = DB::table('accounts')->where('id', $accountId)->first();
        $guard = 0;

        while ($cursor !== null && $guard++ < 32) {
            array_unshift($parts, "{$cursor->code} {$cursor->name}");
            $cursor = $cursor->parent_id !== null
                ? DB::table('accounts')->where('id', $cursor->parent_id)->first()
                : null;
        }

        return implode(' / ', $parts);
    }

    /**
     * CT-A3 R2-5. Is an unresolved purpose a DELIBERATE gap, and at what severity?
     *
     * Exact codes first ({@see self::NON_BLOCKING_PURPOSES} — SUSPENSE, VAT_OUTPUT: hygiene, a
     * mapping nobody should ever add), then FAMILIES by prefix
     * ({@see self::NON_BLOCKING_PURPOSE_PREFIXES} — the gateway families: a `ruling`, a leaf an
     * operator names when the company actually starts using that gateway). Anything else is
     * BLOCKING, and now drives a non-zero exit code.
     *
     * @return array{0: string|null, 1: string}
     */
    private function deliberateGapFor(string $purposeCode): array
    {
        if (isset(self::NON_BLOCKING_PURPOSES[$purposeCode])) {
            return [self::NON_BLOCKING_PURPOSES[$purposeCode], CoaLinkageFinding::SEVERITY_HYGIENE];
        }

        foreach (self::NON_BLOCKING_PURPOSE_PREFIXES as $prefix => $reason) {
            if (str_starts_with($purposeCode, $prefix)) {
                return [$reason, CoaLinkageFinding::SEVERITY_RULING];
            }
        }

        return [null, CoaLinkageFinding::SEVERITY_BLOCKING];
    }

    /**
     * CT-A3 R2-5 / MERGE FIX — map `REFUND_PAYOUT_CASH_BANK` onto the company's own default
     * refund-payout instrument, so a merged stack does not ship a day-one operator task that
     * refuses every `refund_out` refund. Full reasoning on {@see self::REFUND_PAYOUT_PURPOSE}.
     *
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function mapRefundPayoutInstrument(int $companyId, AccountResolver $resolver, array &$findings): void
    {
        $alreadyMapped = null;

        try {
            $alreadyMapped = $resolver->resolve(self::REFUND_PAYOUT_PURPOSE, $companyId);
        } catch (Throwable $e) {
            // Unmapped. That is what this method is for.
        }

        [$account, $how, $inferred] = $this->resolveRefundPayoutInstrument($companyId);

        if ($alreadyMapped !== null) {
            $this->line('  refund payout: '.self::REFUND_PAYOUT_PURPOSE.' already resolves — untouched');

            // The mapping stands, whoever made it. But if it is THIS command's own inference, the
            // flag has to be re-emitted on every run: `coa_linkage_findings` is documented as the
            // LATEST MEASUREMENT, and an inference nobody has confirmed is still true on the second
            // run. (Emitting it only on the run that made it would also make a second --apply
            // report one fewer finding than the first, which is exactly the "is a second run a
            // no-op?" property CT-A4 pins.)
            if ($inferred && $account !== null && (int) $account->id === (int) $alreadyMapped->id) {
                $findings[] = $this->refundPayoutInferredFinding($account, $how);
            }

            return;
        }

        if ($account === null) {
            // Nothing configured AND no fallback leaf: report it as the blocking gap it is and let
            // verifyPurposes() below say so too. Inventing an account here would be exactly the
            // guess wave 2 §4.7 refused to let the SEEDER make, and it would be wrong for the same
            // reason.
            $this->warn('  refund payout: no configured payment-method account and no cash/bank fallback leaf — '
                .self::REFUND_PAYOUT_PURPOSE.' stays unmapped');

            return;
        }

        $this->line(sprintf(
            '  refund payout: %s → #%d %s %s (%s)',
            self::REFUND_PAYOUT_PURPOSE,
            $account->id,
            (string) $account->code,
            (string) $account->name,
            $how
        ));

        if ($inferred) {
            // Mapped, and FLAGGED. The company gets working refunds on day one; the operator gets a
            // durable, queryable row telling them the account was inferred, not chosen.
            $findings[] = $this->refundPayoutInferredFinding($account, $how);
        }

        $this->recordChange(
            $companyId,
            'MAP_REFUND_PAYOUT',
            self::REFUND_PAYOUT_PURPOSE,
            sprintf('→ #%d %s %s (%s)', $account->id, (string) $account->code, (string) $account->name, $how)
        );

        if (! $this->apply) {
            return;
        }

        DB::table('system_accounts')->updateOrInsert(
            [
                'company_id' => $companyId,
                'purpose_code' => self::REFUND_PAYOUT_PURPOSE,
                'service_type' => null,
            ],
            [
                'account_id' => (int) $account->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * One `REFUND_PAYOUT_INFERRED` finding. Re-emitted on every run for as long as the mapping is
     * this command's own inference rather than the operator's choice — see the call sites.
     *
     * @return array<string, mixed>
     */
    private function refundPayoutInferredFinding(object $account, string $how): array
    {
        return [
            'code' => 'REFUND_PAYOUT_INFERRED',
            'subject_type' => 'account',
            'subject_id' => (int) $account->id,
            'severity' => CoaLinkageFinding::SEVERITY_RULING,
            'summary' => sprintf(
                '%s is mapped to #%d %s %s (%s) — confirm this is the account client refunds leave from',
                self::REFUND_PAYOUT_PURPOSE,
                $account->id,
                (string) $account->code,
                (string) $account->name,
                $how
            ),
            'details' => [
                'purpose_code' => self::REFUND_PAYOUT_PURPOSE,
                'account_id' => (int) $account->id,
                'account_code' => (string) $account->code,
                'account_name' => (string) $account->name,
                'resolution' => $how,
                'note' => 'CT-A3 wave 2 §4.7 deliberately does not let the SEEDER guess this leaf, because a '
                    .'seeder knows nothing about the company. accounting:coa-linkage is an explicit, '
                    ."dry-runnable, reversible repair that reads the company's own configured payment "
                    .'methods (R-CT3) — but where no default is configured it can only infer, and an '
                    .'inference an operator has not seen is a guess. Re-point it on the Purpose Mapping '
                    .'screen if this is not where refunds actually leave from.',
            ],
        ];
    }

    /**
     * The company's default refund-payout instrument, by the R-CT3 precedence
     * {@see \App\Services\Accounting\ReceiptPostingRule::instrumentAccountFor()} uses for money
     * coming IN — configured payment-method account (`charges.acc_bank_id`), asserted under the
     * bank group, never a code constant and never a name.
     *
     * @return array{0: object|null, 1: string, 2: bool} [account, how it was chosen, was it inferred]
     */
    private function resolveRefundPayoutInstrument(int $companyId): array
    {
        // No `deleted_at` predicate: `charges` carries no soft-delete column (unlike `accounts`),
        // and asking for one is an SQL error, not a stricter filter.
        $charges = DB::table('charges')
            ->where('company_id', $companyId)
            ->whereNotNull('acc_bank_id')
            ->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))
            ->get(['id', 'name', 'acc_bank_id', 'is_system_default']);

        // 1. The operator's own recorded default payment method.
        $default = $charges->firstWhere('is_system_default', 1);

        if ($default !== null && ($account = $this->bankLeaf($companyId, (int) $default->acc_bank_id)) !== null) {
            return [$account, "the company's default payment method '{$default->name}'", false];
        }

        // 2. One unambiguous bank account across every configured payment method.
        $distinct = array_values(array_unique($charges->pluck('acc_bank_id')->map(static fn ($id) => (int) $id)->all()));

        if (count($distinct) === 1 && ($account = $this->bankLeaf($companyId, $distinct[0])) !== null) {
            return [$account, 'the only bank account any configured payment method points at', true];
        }

        // 3. The configured cash/bank control leaf the receipt path already falls back to.
        $fallbackPurpose = (string) config('accounting.receipt.instrument.fallback_purpose', 'CASH_IN_HAND');

        try {
            $resolved = app(AccountResolver::class)->resolve($fallbackPurpose, $companyId);

            $row = DB::table('accounts')->where('id', $resolved->id)->first(['id', 'code', 'name']);

            if ($row !== null) {
                return [$row, "the configured receipt fallback purpose {$fallbackPurpose}", true];
            }
        } catch (Throwable $e) {
            // No fallback leaf either — the caller reports the gap.
        }

        return [null, 'unresolved', false];
    }

    /** One account id, confirmed to belong to this company and to sit under its bank group. */
    private function bankLeaf(int $companyId, int $accountId): ?object
    {
        try {
            app(AccountResolver::class)->assertUnderBankGroup($accountId, $companyId);
        } catch (Throwable $e) {
            Log::warning('accounting.coa_linkage.refund_payout_account_rejected', [
                'company_id' => $companyId,
                'account_id' => $accountId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return DB::table('accounts')->where('id', $accountId)->first(['id', 'code', 'name']);
    }

    /**
     * CT-A3 R2-5 (verify-R1 D14) — remember what a column held BEFORE this run changed it.
     *
     * Collected in memory and flushed once at the end of the whole invocation
     * ({@see self::flushColumnChanges()}) rather than written per account, because a run over three
     * companies is ONE run to the operator who has to undo it, and because a dry run must be able
     * to compute exactly what it WOULD record without writing a row.
     */
    private function recordColumnChange(int $companyId, int $accountId, string $column, mixed $before, mixed $after): void
    {
        $this->columnChanges[] = [
            'company_id' => $companyId,
            'subject_id' => $accountId,
            'column_name' => $column,
            'before' => $before === null ? null : mb_substr((string) $before, 0, 64),
            'after' => $after === null ? null : mb_substr((string) $after, 0, 64),
        ];
    }

    /**
     * Persist this run's before-images and tell the operator the id they undo it with. `--apply`
     * only: a dry run has changed nothing, so recording a way to undo nothing would be a lie in a
     * table whose whole value is that it is not one.
     */
    private function flushColumnChanges(): void
    {
        if (! $this->apply || $this->columnChanges === []) {
            return;
        }

        foreach (array_chunk($this->columnChanges, 200) as $chunk) {
            DB::table('coa_linkage_changes')->insert(array_map(function (array $c): array {
                return [
                    'run_id' => $this->runId,
                    'company_id' => $c['company_id'],
                    'subject_table' => 'accounts',
                    'subject_id' => $c['subject_id'],
                    'column_name' => $c['column_name'],
                    'before_value' => $c['before'],
                    'after_value' => $c['after'],
                    'rolled_back_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $chunk));
        }

        $this->newLine();
        $this->info(sprintf(
            'RUN ID %s — %d column change(s) recorded with their before-values.',
            $this->runId,
            count($this->columnChanges)
        ));
        $this->line("  Undo this run in full:  php artisan accounting:coa-linkage --rollback={$this->runId}");
    }

    private function recordChange(int $companyId, string $action, string $subject, string $detail): void
    {
        $this->changeLog[] = compact('action', 'subject', 'detail') + ['company' => $companyId];
    }

    private function renderChangeLog(): void
    {
        if ($this->changeLog === []) {
            $this->info('CHANGE LIST: nothing to change.');

            return;
        }

        $this->info($this->apply ? 'CHANGE LIST (applied):' : 'CHANGE LIST (would apply):');

        $this->table(
            ['company', 'action', 'subject', 'detail'],
            array_map(static fn (array $r) => [$r['company'], $r['action'], $r['subject'], $r['detail']], $this->changeLog)
        );
    }
}
