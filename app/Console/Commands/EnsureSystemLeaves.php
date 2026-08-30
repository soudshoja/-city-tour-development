<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Accounting\AccountValidationException;
use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountService;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One-off backfill for EXISTING companies (USER DECISION 2026-08-27): a company seeded before
 * this build's leaves existed in CoaSeeder — 4132 "Markup Income" (under "Commission & Service
 * Fee Income", 4130), 2201 "Salaries & Wages Payable" (under "Accrued Expenses", 2200), and (W2.1,
 * residual 1) 5144 "KNET Charges" / 5145 "uPayment Charges" (under "Payment Gateway Charges",
 * 5140) — never gets them retrofitted just by re-running CoaSeeder.
 *
 * The REAL reason CoaSeeder cannot simply be re-run on an existing company is NOT that
 * `Account::updateOrCreate()` "never adds a row that was never in that older array to begin
 * with" — `updateOrCreate($attributes, $values)` creates on a miss exactly like any other
 * company, and W1.3's own lead probe proved it: re-running the CURRENT CoaSeeder against a real
 * old chart lands the user-decided rows exactly (4131 Gateway Fee Recovery, 4132 Markup Income,
 * 2201 Salaries & Wages Payable, 5144/5145 gateway charge leaves — the codes this command's own
 * AccountService::create() path can never produce for an existing company, see below). The real
 * reason is collateral damage: CoaSeeder's `$values` payload unconditionally resets
 * `serial_number`, `actual_balance`, `budget_balance`, `variance`, `branch_id`, `agent_id`,
 * `client_id`, `supplier_id`, and `reference_id` on EVERY seeded account it touches, wiping real
 * operational data — reproduced on a real company: `'Clients' actual_balance=0.00
 * serial_number=NULL (was 1234.567 / SER-1)`. That is what makes CoaSeeder a fresh-company-only
 * tool and this narrow, additive command necessary for a company that already has history.
 *
 * This command creates the missing leaves (if missing) via
 * {@see AccountService::createSystemLeaf()} — the ONLY rule-enforcing account-creation path that
 * can land an EXPLICIT, USER-DECIDED code (4132 / 2201 / 5144 / 5145) on an existing company's
 * drifted chart; {@see AccountService::create()} cannot do this (rule 7: code is always generated
 * by AccountCodeGenerator, max(numeric sibling)+1 — which lands on 2231 for "Salaries & Wages
 * Payable" on any company whose "Accrued Expenses" already has 2210/2220/2230, never the decided
 * 2201, which is deliberately chosen to be unreachable by that generator — see CoaSeeder's own
 * comment on the 2201 row). It then re-runs {@see SystemAccountsSeeder}'s mapping for the purpose
 * codes those leaves back (MARKUP_INCOME, SALARY_PAYABLE, GATEWAY_FEE_EXPENSE_KNET,
 * GATEWAY_FEE_EXPENSE_UPAYMENT), for the same company/companies.
 *
 * Idempotent: an existing leaf found by (company, immediate parent, name) already carrying the
 * decided code is returned unchanged, not recreated — see createSystemLeaf()'s own idempotency
 * contract. Re-running after every leaf already exists at its decided code creates nothing and
 * reports so.
 *
 * Per-company isolation: each company's leaf-creation (and, when requested, duplicate-code
 * renumber) runs inside its own DB transaction. A failure on one company — an
 * AccountValidationException from createSystemLeaf() (most commonly a chain link that does not
 * exist for that company's particular chart shape, or a decided code already occupied by a
 * DIFFERENT account — createSystemLeaf()'s own codeOwner check names that account's id and name in
 * the exception message), or ANY OTHER Throwable (residual 11 fix, W2.1: a transient QueryException
 * must not abort every company after it, and must not be misreported as a silent success) — rolls
 * back ONLY that company's changes, is logged and reported by name/id and exception class, and the
 * loop continues to the next company. It does not abort the whole run and does not leave a
 * partially-applied company behind. handle() itself returns FAILURE (residual 11 fix) when any
 * company failed, so a caller checking the exit code — not just grepping console text — can tell.
 *
 * Tenant-explicit throughout: company id always comes from the `--company` option or the
 * Company::query() loop, never from Auth — this command has no HTTP request/session to resolve a
 * tenant from (AccountService::resolveCompanyId() would otherwise fall back to
 * Auth::check()+getCompanyId(), which is never true in a console context).
 *
 * --dry-run: previews what would be created/renumbered without writing anything. The preview
 * runs the SAME code path as a real run (createSystemLeaf(), fixDuplicateGatewayFeeCode()'s
 * collision check) inside a transaction that is always rolled back at the end, rather than a
 * separate read-only re-implementation that could silently drift from what a real run does.
 * --fix-duplicate-code: separately renumbers an existing "Gateway Fee Recovery" child of
 * "Commission & Service Fee Income" that still carries the pre-fix duplicate code '4130' (the
 * same code as its own parent — the CoaSeeder bug this build's task A fixed going forward for new
 * companies) to '4131', refusing with no change if '4131' is already used by a different account
 * for that company. OFF by default — this is a plain UPDATE on accounts.code with no
 * AccountService rule to route it through (renumbering an existing leaf is not "creating" one),
 * so it is opt-in and always previewed under --dry-run before it is ever applied for real.
 *
 * Registered by auto-discovery: App\Console\Kernel::commands() calls
 * $this->load(__DIR__.'/Commands') — the same mechanism that registers
 * App\Console\Commands\AccountingEngine and App\Console\Commands\SeedAccountingSerialSchemas, no
 * separate registration step needed.
 *
 * ── W3-prereq lane A ADDITION (USER RULING 2026-08-27) ──────────────────────────────────────────
 * Alongside self::LEAVES' four fixed-code leaves, this command also backfills a per-service
 * SERVICE_REVENUE leaf — "{Type} Booking Revenue" under "Direct Income" — for whichever of the 12
 * task types config('accounting.purpose_codes.service_types') names does not already have one for
 * this company (in practice: the ten non-flight/hotel types; every CoaSeeder chart, old or new,
 * already has Flight/Hotel Booking Revenue). See backfillServiceRevenueLeaves()'s own docblock for
 * why these ten cannot live in self::LEAVES: unlike Markup Income/Salaries & Wages Payable/KNET/
 * uPayment Charges, there is no single USER-DECIDED code shared by every company — a company's
 * "Direct Income" group drifts differently over time, so the code is computed PER COMPANY, at
 * backfill time, following the exact "highest existing sibling code + 5" rule legacy
 * `InvoiceController::addJournalEntry()` (app/Http/Controllers/InvoiceController.php, ~line
 * 1538-1551) already uses to auto-create this exact leaf on demand today — never a second,
 * invented convention.
 */
class EnsureSystemLeaves extends Command
{
    protected $signature = 'accounting:ensure-system-leaves
                            {--company= : Company id to process (default: every company)}
                            {--dry-run : Preview what would happen without writing anything}
                            {--fix-duplicate-code : Also renumber a duplicate-code (4130) "Gateway Fee Recovery" child to 4131}';

    protected $description = 'Backfill the 4132 "Markup Income" / 2201 "Salaries & Wages Payable" / 5144 "KNET Charges" / 5145 "uPayment Charges" / 1215 "Cheques In Hand" / 2215 "Cheques Issued Not Cleared" / 5127 "Cash Over/Short" / 5222 "Bank Charges" leaves, plus any missing per-service "{Type} Booking Revenue" (SERVICE_REVENUE) leaf, for existing companies, and re-map every purpose code they back.';

    /**
     * W3-prereq lane A: base/step for the "highest existing 'Direct Income' sibling code + 5"
     * rule backfillServiceRevenueLeaves()/nextDirectIncomeRevenueCode() mirror from legacy
     * InvoiceController::addJournalEntry() — see this class's own docblock addition above. 4110
     * is Flight Booking Revenue's own seeded code (CoaSeeder), the same default legacy falls back
     * to when 'Direct Income' has no numeric-coded children at all.
     */
    private const DIRECT_INCOME_REVENUE_BASE_CODE = 4110;

    private const DIRECT_INCOME_REVENUE_CODE_STEP = 5;

    /**
     * @var array<int, array{leafName: string, code: string, parentChain: array<int, string>, purposeCode: string, core: bool}>
     */
    private const LEAVES = [
        [
            'leafName' => 'Markup Income',
            'code' => '4132',
            'parentChain' => ['Commission & Service Fee Income', 'Direct Income', 'Income'],
            'purposeCode' => 'MARKUP_INCOME',
            // CORE (W2.1 residual R-3 fix): this company's chart is expected to already have
            // 'Commission & Service Fee Income' — every CoaSeeder chart, old or new, seeds it.
            // A failure to resolve this chain is a genuine chart-shape problem for the company,
            // not an absent optional pool, so it still rolls back the whole company (see below).
            'core' => true,
        ],
        [
            // USER DECISION 2026-08-27 (residual 6, W2.1): code is 2201, NOT 2240 — see
            // CoaSeeder's own comment on this row for why 2240 collides with
            // AgentController::update()'s auto-numbered agent-profit leaves.
            'leafName' => 'Salaries & Wages Payable',
            'code' => '2201',
            'parentChain' => ['Accrued Expenses', 'Liabilities'],
            'purposeCode' => 'SALARY_PAYABLE',
            'core' => true,
        ],
        // RESIDUAL R-3 FIX (W2.2): the two gateway fee-expense leaves are OPTIONAL/BEST-EFFORT,
        // not core. Unlike 'Commission & Service Fee Income'/'Accrued Expenses' above, their
        // parent — 'Payment Gateway Charges' (5140) — is NOT guaranteed to exist on a legacy
        // company's chart (exactly the company this command exists to backfill: one seeded before
        // 5140 was part of CoaSeeder at all). Before this fix, a missing 5140 pool made
        // createSystemLeaf() throw for EITHER of these two leaves, which propagated straight out
        // of processCompany()'s single try/catch and rolled back BOTH already-created core leaves
        // (Markup Income, Salaries & Wages Payable) in the SAME transaction — the exact company
        // HARD GATE 1 runs this command against lost both core leaves because of an unrelated,
        // genuinely-optional gateway pool it never had. See processCompany()'s per-leaf handling:
        // a core leaf's exception still rolls back and aborts the company (unchanged); an optional
        // leaf's exception is caught, reported as SKIPPED, and the loop continues — the pool being
        // absent is an expected, non-fatal outcome for a leaf that is best-effort by design, same
        // PATTERN NAME convention as CoaSeeder's 5141-5143.
        [
            // W4.0: SERVICE_FEE_INCOME purpose code — InvoiceController::
            // addInvoiceChargeJournalEntries() feeder. CORE, same reasoning as 'Markup Income'
            // above: this company's chart is expected to already have 'Commission & Service Fee
            // Income' (every CoaSeeder chart, old or new, seeds it).
            'leafName' => 'Service Fee Income',
            'code' => '4133',
            'parentChain' => ['Commission & Service Fee Income', 'Direct Income', 'Income'],
            'purposeCode' => 'SERVICE_FEE_INCOME',
            'core' => true,
        ],
        [
            // W6.S "New leaves" (w6-brief.md): CORE, same reasoning as 'Markup Income'/'Service
            // Fee Income' above -- 'Commission & Service Fee Income' is expected to already exist
            // on every CoaSeeder chart, old or new.
            'leafName' => 'Void Fee Income',
            'code' => '4134',
            'parentChain' => ['Commission & Service Fee Income', 'Direct Income', 'Income'],
            'purposeCode' => 'VOID_FEE_INCOME',
            'core' => true,
        ],
        [
            'leafName' => 'Reissue Fee Income',
            'code' => '4135',
            'parentChain' => ['Commission & Service Fee Income', 'Direct Income', 'Income'],
            'purposeCode' => 'REISSUE_FEE_INCOME',
            'core' => true,
        ],
        [
            // W6.C (w6-brief.md "W6.C — Supplier-side charges"): CORE, same reasoning as
            // 'Void Fee Income'/'Reissue Fee Income' above -- 'Commission & Service Fee Income' is
            // expected to already exist on every CoaSeeder chart, old or new.
            'leafName' => 'Supplier Charge Recharge Income',
            'code' => '4137',
            'parentChain' => ['Commission & Service Fee Income', 'Direct Income', 'Income'],
            'purposeCode' => 'SUPPLIER_CHARGE_RECHARGE_INCOME',
            'core' => true,
        ],
        [
            // W6.I (w6-brief.md "Importer contract" item 2): CORE, same reasoning as 'Supplier
            // Charge Recharge Income'/'Void Fee Income' above -- 'Commission & Service Fee Income'
            // is expected to already exist on every CoaSeeder chart, old or new.
            'leafName' => 'EMD Ancillary Revenue',
            'code' => '4138',
            'parentChain' => ['Commission & Service Fee Income', 'Direct Income', 'Income'],
            'purposeCode' => 'EMD_ANCILLARY_REVENUE',
            'core' => true,
        ],
        [
            // W6.C: CORE, same reasoning as 'Cash Over/Short' above -- 'Direct Expenses (Cost of
            // Sales)' is expected to already exist on every CoaSeeder chart, old or new.
            'leafName' => 'Supplier Fees & Surcharges',
            'code' => '5128',
            'parentChain' => ['Direct Expenses (Cost of Sales)', 'Expenses'],
            'purposeCode' => 'SUPPLIER_CHARGE_EXPENSE',
            'core' => true,
        ],
        [
            'leafName' => 'KNET Charges',
            'code' => '5144',
            'parentChain' => ['Payment Gateway Charges', 'Direct Expenses (Cost of Sales)', 'Expenses'],
            'purposeCode' => 'GATEWAY_FEE_EXPENSE_KNET',
            'core' => false,
        ],
        [
            'leafName' => 'uPayment Charges',
            'code' => '5145',
            'parentChain' => ['Payment Gateway Charges', 'Direct Expenses (Cost of Sales)', 'Expenses'],
            'purposeCode' => 'GATEWAY_FEE_EXPENSE_UPAYMENT',
            'core' => false,
        ],
        // W5.L (w5-brief.md §W5.L item 4) — four voucher/instrument anchor leaves. See
        // config('accounting.purpose_codes')'s own docblock note for the full rationale behind
        // each leaf choice; SystemAccountsSeeder::resolveControls() maps the purpose codes back.
        [
            // CORE: 'Assets' is a level-1 root every CoaSeeder chart, old or new, seeds.
            'leafName' => 'Cheques In Hand',
            'code' => '1215',
            'parentChain' => ['Assets'],
            'purposeCode' => 'CHEQUES_IN_HAND',
            'core' => true,
        ],
        [
            // CORE: 'Liabilities' is a level-1 root every CoaSeeder chart, old or new, seeds.
            'leafName' => 'Cheques Issued Not Cleared',
            'code' => '2215',
            'parentChain' => ['Liabilities'],
            'purposeCode' => 'CHEQUES_ISSUED_NOT_CLEARED',
            'core' => true,
        ],
        [
            // CORE: 'Direct Expenses (Cost of Sales)' is expected to already exist for every
            // CoaSeeder chart, same reasoning as 'Markup Income' above for 'Commission & Service
            // Fee Income'.
            'leafName' => 'Cash Over/Short',
            'code' => '5127',
            'parentChain' => ['Direct Expenses (Cost of Sales)', 'Expenses'],
            'purposeCode' => 'CASH_OVER_SHORT',
            'core' => true,
        ],
        [
            // CORE: 'Indirect Expenses (Operating Expenses)' (5200) is expected to already exist
            // on every CoaSeeder chart, old or new — NOT placed under 'Payment Gateway Charges'
            // (5140); see CoaSeeder's own comment on code 5222 for why (proven by execution:
            // SystemAccountsSeeder::resolveGatewayFeeExpense()'s neutral-leaf fallback would
            // silently adopt a Bank Charges sibling there).
            'leafName' => 'Bank Charges',
            'code' => '5222',
            'parentChain' => ['Indirect Expenses (Operating Expenses)', 'Expenses'],
            'purposeCode' => 'BANK_CHARGES_EXPENSE',
            'core' => true,
        ],
        // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6) — the two `at_travel` revenue-recognition
        // leaves. Both CORE: 'Liabilities' and 'Supplier Advances/Prepayments' > 'Assets' are
        // expected to already exist on every CoaSeeder chart, old or new (the former is a
        // level-1 root; the latter's parent leaf, 'Supplier Advances/Prepayments', predates this
        // wave and is unused by any feeder before it — see CoaSeeder's own comment on row 1430).
        [
            'leafName' => 'Deferred Revenue',
            'code' => '2650',
            'parentChain' => ['Liabilities'],
            'purposeCode' => 'DEFERRED_REVENUE',
            'core' => true,
        ],
        [
            'leafName' => 'Prepaid Supplier Cost',
            'code' => '1430',
            'parentChain' => ['Supplier Advances/Prepayments', 'Assets'],
            'purposeCode' => 'PREPAID_SUPPLIER_COST',
            'core' => true,
        ],
    ];

    public function handle(AccountService $accountService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixDuplicateCode = (bool) $this->option('fix-duplicate-code');

        $companyOption = $this->option('company');
        if ($companyOption !== null) {
            $companyId = (int) $companyOption;
            $company = Company::find($companyId);
            if ($company === null) {
                $this->error("No company found with id #{$companyId}.");

                return self::FAILURE;
            }
            $companies = collect([$company]);
        } else {
            $companies = Company::query()->select(['id', 'name'])->orderBy('id')->get();
        }

        if ($companies->isEmpty()) {
            $this->warn('No companies found — nothing to do.');

            return self::SUCCESS;
        }

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Ensure system leaves (4132 Markup Income / 2201 Salaries & Wages Payable / 5144 KNET Charges / 5145 uPayment Charges / per-service Booking Revenue leaves)');
        $this->info('═══════════════════════════════════════════════════════');
        $this->line('  Companies: '.$companies->count());
        if ($dryRun) {
            $this->warn('  DRY RUN — no rows will be written.');
        }
        if ($fixDuplicateCode) {
            $this->warn('  --fix-duplicate-code is ON: a duplicate-code 4130 "Gateway Fee Recovery" child will be renumbered to 4131.');
        }
        $this->newLine();

        $companiesNeedingRemap = [];
        $createdCount = 0;
        $skippedCount = 0;
        $optionalSkippedCount = 0;
        $renumberedCount = 0;
        $refusedCount = 0;
        $failedCount = 0;

        foreach ($companies as $company) {
            $companyLabel = (string) ($company->name ?: "#{$company->id}");

            try {
                $result = $this->processCompany((int) $company->id, $companyLabel, $accountService, $dryRun, $fixDuplicateCode);
            } catch (Throwable $e) {
                // RESIDUAL 11 FIX (W2.1): catch \Throwable, not just AccountValidationException.
                // processCompany() itself only ever throws after DB::rollBack() (see its own
                // `catch (Throwable $e) { DB::rollBack(); throw $e; }`), so ANY exception reaching
                // here — a genuine chart-shape failure from createSystemLeaf(), or a transient
                // QueryException/deadlock from the DB layer — already has this company's writes
                // rolled back. The old `catch (AccountValidationException $e)` let a QueryException
                // propagate straight out of handle() uncaught (aborting every company after this
                // one AND skipping the post-loop remap silently), which is the inverse of the
                // intended behaviour: a transient DB error should be logged and skipped over like
                // any other per-company failure, not allowed to abort the whole run.
                $failedCount++;
                Log::error('accounting.ensure_system_leaves_failed', [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
                $this->error(sprintf(
                    '  [%s] FAILED (%s): %s — this company\'s changes were rolled back; continuing with the next company.',
                    $companyLabel,
                    get_class($e),
                    $e->getMessage()
                ));

                continue;
            }

            $createdCount += $result['created'];
            $skippedCount += $result['skipped'];
            $optionalSkippedCount += $result['optionalSkipped'];
            $renumberedCount += $result['renumbered'];
            $refusedCount += $result['refused'];

            if (! $dryRun && $result['created'] > 0) {
                $companiesNeedingRemap[(int) $company->id] = true;
            }
        }

        if (! $dryRun && $companiesNeedingRemap !== []) {
            $this->newLine();
            $this->info('  Re-running SystemAccountsSeeder mapping (MARKUP_INCOME, SALARY_PAYABLE, GATEWAY_FEE_EXPENSE_KNET, GATEWAY_FEE_EXPENSE_UPAYMENT) for every processed company...');
            // SystemAccountsSeeder::run() maps EVERY company in one pass (file's own docblock) —
            // there is no per-company / per-purpose-code entry point. Safe and idempotent to run
            // for the whole set even when --company scoped this run to one, or when some
            // companies above failed and were skipped: every other company's rows are
            // `updateOrCreate`d back to the exact same values they already had.
            //
            // R-a fix (W2b): setCommand($this) is required -- Seeder::$command has no default
            // and SystemAccountsSeeder::info()/warn()/line() all call
            // $this->command?->getOutput()?->writeln(...), so every CHANGED/MAPPED/SKIPPED line
            // the seeder would report was being silently discarded (exit 0, remap invisible).
            // Deliberately un-silences the seeder's own report on this command; the extra
            // MAPPED/SKIPPED output below is the point of this fix, not a side effect.
            (new SystemAccountsSeeder())->setCommand($this)->run();
        }

        $this->newLine();
        $this->info(sprintf(
            '  %d leaf(s) %s, %d skipped (already present), %d optional gateway leaf(s) skipped (no Payment Gateway Charges pool), %d duplicate-code renumber(s) %s, %d renumber(s) refused (collision), %d compan%s failed.',
            $createdCount,
            $dryRun ? 'would be created' : 'created',
            $skippedCount,
            $optionalSkippedCount,
            $renumberedCount,
            $dryRun ? 'would apply' : 'applied',
            $refusedCount,
            $failedCount,
            $failedCount === 1 ? 'y' : 'ies'
        ));

        if ($dryRun) {
            $this->warn('  Re-run without --dry-run to write these values.');
        }

        if ($optionalSkippedCount > 0) {
            $this->warn("  {$optionalSkippedCount} optional gateway leaf(s) skipped — see SKIPPED (optional) lines above. This is not a failure: the core leaves for that company were still created/verified.");
        }

        if ($failedCount > 0) {
            $this->warn("  {$failedCount} company/companies failed — see errors above and the accounting.ensure_system_leaves_failed log channel.");
        }

        // RESIDUAL 11 FIX (W2.1): a failed company used to be invisible to anything checking the
        // exit code — only the printed text and the log channel said so, and `$?` (or any script
        // gating on it) read a clean 0 regardless. FAILURE is returned whenever ANY company
        // failed, even though every OTHER company in the same run still succeeded and this method
        // still printed their results and ran the remap for them above.
        //
        // RESIDUAL R-3 FIX (W2.2): an optional gateway leaf being skipped for lack of a 5140 pool
        // is deliberately NOT counted as a company failure and does NOT flip this to FAILURE —
        // see the LEAVES 'core' docblock above. A distinct 'partial' exit code was considered and
        // rejected: HARD GATE 1 runs this exact command against exactly this shape of legacy
        // company (core leaves present, no gateway pool) and expects a clean SUCCESS exit; a new
        // exit code would be an undocumented breaking change to that gate for a non-error outcome.
        // $optionalSkippedCount is surfaced above (console + summary line) for an operator or
        // script that greps output rather than $?.
        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Processes one company inside its own DB transaction: the duplicate-code renumber (if
     * requested) then every leaf in self::LEAVES, via AccountService::createSystemLeaf(). Any
     * AccountValidationException — a chain link missing for this company's particular chart
     * shape, a code collision, an existing account under the wrong code — propagates OUT of this
     * method after rolling back everything this method did for this company; the caller decides
     * whether to continue to the next company.
     *
     * A --dry-run always rolls back at the end regardless of outcome, so this method's real-write
     * path and its preview path are the same code, never two implementations that can drift.
     *
     * RESIDUAL 11 FIX (W2.1): every CREATED/exists/RENUMBERED/refused line is BUFFERED in
     * $messages while the transaction is open and only emitted to the console AFTER the commit
     * (or the dry-run's always-intentional rollback) below — never while this company's outcome
     * could still be rolled back. Before this fix, a leaf created mid-loop printed its "CREATED"
     * line immediately; if a LATER leaf in the same loop then failed the chain-walk, the whole
     * transaction rolled back (including that already-printed leaf) but the console had already
     * told the operator it succeeded — a committed console line for an uncommitted write. On the
     * throw path nothing in $messages is ever printed: the caller's own catch block prints the
     * single FAILED line instead, which is the only truthful summary of a rolled-back company.
     *
     * RESIDUAL R-3 FIX (W2.2): each leaf in self::LEAVES is now either CORE or OPTIONAL
     * ('core' flag). A core leaf's AccountValidationException still propagates unchanged —
     * rolls back this company's transaction and is the caller's per-company FAILED line, exactly
     * as before this fix. An OPTIONAL leaf's AccountValidationException (most commonly: this
     * company's chart has no 'Payment Gateway Charges' pool for createSystemLeaf()'s chain-walk to
     * resolve, the exact legacy-company shape this command exists for) is caught HERE, buffered as
     * a SKIPPED (optional) message, counted in $result['optionalSkipped'], and the loop continues
     * to the next leaf — it must never take the already-created/verified core leaves in the SAME
     * transaction down with it. A Throwable that is not an AccountValidationException (a transient
     * QueryException, a deadlock) is NOT caught here even for an optional leaf — that class of
     * failure still propagates to the caller's residual-11 Throwable handler, same as for a core
     * leaf, because it says nothing about whether the pool exists and swallowing it would hide a
     * real infrastructure problem behind "optional".
     *
     * @return array{created: int, skipped: int, optionalSkipped: int, renumbered: int, refused: int}
     */
    private function processCompany(
        int $companyId,
        string $companyLabel,
        AccountService $accountService,
        bool $dryRun,
        bool $fixDuplicateCode
    ): array {
        $result = ['created' => 0, 'skipped' => 0, 'optionalSkipped' => 0, 'renumbered' => 0, 'refused' => 0];

        /** @var array<int, array{0: string, 1: string}> $messages ['info'|'line'|'error', text] */
        $messages = [];

        DB::beginTransaction();

        try {
            if ($fixDuplicateCode) {
                [$outcome, $message] = $this->fixDuplicateGatewayFeeCode($companyId, $companyLabel, $dryRun);

                if ($message !== null) {
                    $messages[] = [$outcome === 'refused' ? 'error' : 'info', $message];
                }

                match ($outcome) {
                    'renumbered', 'would-renumber' => $result['renumbered']++,
                    'refused' => $result['refused']++,
                    default => null,
                };
            }

            foreach (self::LEAVES as $spec) {
                try {
                    $account = $accountService->createSystemLeaf(
                        $companyId,
                        $spec['parentChain'],
                        $spec['leafName'],
                        $spec['code']
                    );
                } catch (AccountValidationException $e) {
                    if ($spec['core']) {
                        // Core leaf: unchanged behaviour — propagate to processCompany()'s own
                        // catch below, which rolls back this company's transaction and rethrows
                        // to the caller's per-company FAILED handling.
                        throw $e;
                    }

                    // Optional leaf (residual R-3 fix): reported and skipped, NOT rethrown — the
                    // core leaves created earlier in this same loop/transaction (and any core
                    // leaf still to come) must survive this leaf being unresolvable.
                    $messages[] = ['line', "  [{$companyLabel}] SKIPPED (optional): '{$spec['leafName']}' (code={$spec['code']}) under '{$spec['parentChain'][0]}' — {$e->getMessage()}"];
                    $result['optionalSkipped']++;

                    continue;
                }

                if ($account->wasRecentlyCreated) {
                    $verb = $dryRun ? 'WOULD CREATE' : 'CREATED';
                    $messages[] = ['info', "  [{$companyLabel}] {$verb}: '{$account->name}' (code={$account->code}) under '{$spec['parentChain'][0]}'."];
                    $result['created']++;
                } else {
                    $messages[] = ['line', "  [{$companyLabel}] exists: '{$account->name}' (id={$account->id}, code={$account->code}) under '{$spec['parentChain'][0]}' — skip."];
                    $result['skipped']++;
                }
            }

            // W3-prereq lane A ADDITION: per-service SERVICE_REVENUE leaves, dynamically coded —
            // see this class's own docblock and backfillServiceRevenueLeaves()'s. Runs inside the
            // SAME per-company transaction as self::LEAVES above, so an ambiguous pre-existing
            // Booking Revenue name (the one case it refuses outright — see that method's
            // docblock) rolls back the core leaves created earlier in this same loop too, exactly
            // like any other genuine chart-shape problem this command refuses to guess through.
            $this->backfillServiceRevenueLeaves($companyId, $companyLabel, $accountService, $dryRun, $result, $messages);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($dryRun) {
            // Never persist a dry run, regardless of how far it got — this is the ONE place that
            // decides whether this company's work is kept, so a dry run is indistinguishable from
            // a real run right up until this line.
            DB::rollBack();
        } else {
            DB::commit();
        }

        foreach ($messages as [$level, $text]) {
            $this->{$level}($text);
        }

        return $result;
    }

    /**
     * W3-prereq lane A: backfills a missing per-service SERVICE_REVENUE leaf — "{Type} Booking
     * Revenue" under "Direct Income" — for every task type
     * config('accounting.purpose_codes.service_types') names that does not already have one for
     * this company. Called from processCompany() INSIDE the same per-company transaction as
     * self::LEAVES, buffering its own CREATED/exists/SKIPPED messages into the same $messages
     * array for the same residual-11 reason (never print a console line for a write that could
     * still be rolled back).
     *
     * Unlike self::LEAVES, there is no single USER-DECIDED code shared by every company here — a
     * company's "Direct Income" group has grown differently over time (renamed/reordered
     * accounts, real production drift), so the code is computed PER COMPANY at backfill time by
     * nextDirectIncomeRevenueCode() below, following the EXACT rule legacy
     * `InvoiceController::addJournalEntry()` already uses to auto-create this same leaf on demand
     * (see that method, ~line 1538-1551): highest existing code among "Direct Income"'s own
     * children, plus 5.
     *
     * Three outcomes per task type:
     *   - exactly one account already named "{Type} Booking Revenue" exists for this company:
     *     nothing to create — SystemAccountsSeeder::resolveServices()'s own mapByName() call
     *     (the post-loop remap below) owns mapping it. Reported as `exists`.
     *   - none exists: nextDirectIncomeRevenueCode() computes the next code and
     *     AccountService::createSystemLeaf() creates it. OPTIONAL, not core: a company whose
     *     chart has no (or an ambiguous) "Direct Income" account at all is a genuinely broken
     *     legacy chart this narrow backfill cannot fix on its own — the resulting
     *     AccountValidationException is caught HERE, reported as `SKIPPED (optional)`, and the
     *     loop continues to the next task type, same pattern as the gateway-fee leaves' own
     *     optional handling — it must never roll back leaves already created earlier in this same
     *     transaction (self::LEAVES, or an earlier task type in THIS loop).
     *   - MORE THAN ONE account already shares that exact name: refused OUTRIGHT, NOT caught as
     *     optional (P1 mission rule: "never pick one silently, never pool" — the exact rule
     *     SystemAccountsSeeder::mapByName() itself already enforces for every other per-service
     *     leaf). This propagates to processCompany()'s own Throwable handler, rolling back this
     *     company's WHOLE transaction and marking it FAILED — the same severity self::LEAVES'
     *     CORE leaves already carry for a genuine chart-shape problem, matching this command's
     *     existing exit-code convention (Command::FAILURE whenever any company's chain-walk or
     *     collision guard refuses to guess). Creating a third account here would make the
     *     ambiguity worse, not better.
     */
    private function backfillServiceRevenueLeaves(
        int $companyId,
        string $companyLabel,
        AccountService $accountService,
        bool $dryRun,
        array &$result,
        array &$messages
    ): void {
        /** @var array<int, string> $serviceTypes */
        $serviceTypes = config('accounting.purpose_codes.service_types', []);

        foreach ($serviceTypes as $serviceType) {
            $leafName = ucfirst($serviceType).' Booking Revenue';

            $existing = Account::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('name', $leafName)
                ->get(['id']);

            if ($existing->count() === 1) {
                $messages[] = ['line', "  [{$companyLabel}] exists: '{$leafName}' (id={$existing->first()->id}) under 'Direct Income' — skip."];
                $result['skipped']++;

                continue;
            }

            if ($existing->count() > 1) {
                throw new AccountValidationException(sprintf(
                    'accounting:ensure-system-leaves: %d accounts already named \'%s\' exist for company_id=%d '
                    .'(ids: %s) — refusing to guess which is the correct SERVICE_REVENUE leaf for service_type='
                    .'\'%s\', and refusing to create a third. Resolve the duplicate manually, then re-run.',
                    $existing->count(),
                    $leafName,
                    $companyId,
                    $existing->pluck('id')->implode(', '),
                    $serviceType
                ));
            }

            try {
                $code = $this->nextDirectIncomeRevenueCode($companyId);

                $account = $accountService->createSystemLeaf(
                    $companyId,
                    ['Direct Income', 'Income'],
                    $leafName,
                    $code
                );
            } catch (AccountValidationException $e) {
                $messages[] = ['line', "  [{$companyLabel}] SKIPPED (optional): '{$leafName}' under 'Direct Income' — {$e->getMessage()}"];
                $result['optionalSkipped']++;

                continue;
            }

            $verb = $dryRun ? 'WOULD CREATE' : 'CREATED';
            $messages[] = ['info', "  [{$companyLabel}] {$verb}: '{$account->name}' (code={$account->code}) under 'Direct Income' (SERVICE_REVENUE/{$serviceType})."];
            $result['created']++;
        }
    }

    /**
     * Mirrors the EXACT rule legacy `InvoiceController::addJournalEntry()` already uses to
     * auto-create a missing "{Type} Booking Revenue" leaf on demand (see that method, ~line
     * 1538-1551): the highest existing code among "Direct Income"'s own immediate children, plus
     * 5 — defaulting to self::DIRECT_INCOME_REVENUE_BASE_CODE (4110, Flight Booking Revenue's own
     * seeded code) when "Direct Income" has no children yet. Deliberately the SAME
     * `orderByDesc('code')` (string-ordered, not numeric-cast) comparison legacy uses, not a
     * "safer" reimplementation — this is "the existing code-picking rule for that group", not a
     * new one. `withoutGlobalScopes()` throughout: this command has no Auth/session context.
     *
     * @throws AccountValidationException when this company has no (or more than one) account
     *                                     named "Direct Income" to anchor a new Booking Revenue
     *                                     leaf under.
     */
    private function nextDirectIncomeRevenueCode(int $companyId): string
    {
        $directIncomeCandidates = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', 'Direct Income')
            ->get();

        if ($directIncomeCandidates->isEmpty()) {
            throw new AccountValidationException(
                "no account named 'Direct Income' found for company_id={$companyId} to anchor a new Booking Revenue leaf."
            );
        }

        if ($directIncomeCandidates->count() > 1) {
            throw new AccountValidationException(
                "ambiguous: {$directIncomeCandidates->count()} accounts named 'Direct Income' exist for company_id={$companyId}."
            );
        }

        $directIncomeParent = $directIncomeCandidates->first();

        $lastSibling = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('parent_id', $directIncomeParent->id)
            ->orderByDesc('code')
            ->first();

        $lastCode = (int) ($lastSibling?->code ?? self::DIRECT_INCOME_REVENUE_BASE_CODE);
        $candidate = $lastCode + self::DIRECT_INCOME_REVENUE_CODE_STEP;

        // Defensive collision retry — same idiom as AccountCodeGenerator::generate()'s own
        // `while (codeExists) { … }`: P1 ships no unique(company_id, code) constraint, so a
        // company-wide collision with an UNRELATED account (e.g. "Indirect Income" at 4200, two
        // "Direct Income" siblings over) becomes reachable once several of these leaves are
        // backfilled in the same run. createSystemLeaf() has no generator loop of its own to
        // protect it the way AccountService::create() does — never hand it a code that is
        // already taken.
        while ($this->directIncomeRevenueCodeTaken($companyId, $candidate)) {
            $candidate += self::DIRECT_INCOME_REVENUE_CODE_STEP;
        }

        return str_pad((string) $candidate, 4, '0', STR_PAD_LEFT);
    }

    private function directIncomeRevenueCodeTaken(int $companyId, int $candidate): bool
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', str_pad((string) $candidate, 4, '0', STR_PAD_LEFT))
            ->exists();
    }

    /**
     * Renumber an existing "Gateway Fee Recovery" child of "Commission & Service Fee Income"
     * that still carries the duplicate code '4130' (same code as its own parent — the pre-fix
     * CoaSeeder bug) to '4131'. Only acts on that exact name+parent+duplicate-code shape; never
     * touches a company whose "Gateway Fee Recovery" is already correctly coded (nothing to fix)
     * or that has no such account at all.
     *
     * HIGH #1 fix: checks for a collision on '4131' BEFORE writing, unconditionally (dry-run and
     * real run alike) — refuses with no change if '4131' already belongs to a different account
     * for this company, rather than blindly overwriting onto a taken code the way a bare
     * `DB::table('accounts')->update()` with no read-back would.
     *
     * RESIDUAL 11 FIX (W2.1): returns the outcome AND its message as a tuple instead of printing
     * directly — this method runs INSIDE processCompany()'s open transaction, so printing here
     * would be exactly the "console line for an uncommitted write" bug that fix's own docblock
     * describes (e.g. a REFUSED collision message printed before a LATER leaf in the same
     * transaction throws and rolls this renumber's own no-op back too — harmless for a refusal
     * with no write, but the wrong general pattern to leave in place next to the one that does
     * write). The caller buffers the message and emits it only after the transaction's outcome
     * (commit or dry-run rollback) is final.
     *
     * @return array{0: string, 1: ?string} [outcome, message] — outcome is one of 'renumbered'
     *                (written), 'would-renumber' (dry-run preview, would have written), 'refused'
     *                (collision — nothing written), 'not-applicable' (no such account, or it does
     *                not carry the duplicate code — nothing to do, no message).
     */
    private function fixDuplicateGatewayFeeCode(int $companyId, string $companyLabel, bool $dryRun): array
    {
        $parent = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', 'Commission & Service Fee Income')
            ->first();

        if ($parent === null) {
            return ['not-applicable', null];
        }

        $child = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('parent_id', $parent->id)
            ->where('name', 'Gateway Fee Recovery')
            ->where('code', '4130')
            ->first();

        if ($child === null) {
            return ['not-applicable', null];
        }

        $collision = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', '4131')
            ->where('id', '!=', $child->id)
            ->first();

        if ($collision !== null) {
            $message = sprintf(
                '  [%s] REFUSED to renumber \'Gateway Fee Recovery\' (id=%d) code 4130 -> 4131: code 4131 is '
                .'already used by account #%d (\'%s\') for this company. No change made.',
                $companyLabel,
                $child->id,
                $collision->id,
                $collision->name
            );

            return ['refused', $message];
        }

        if ($dryRun) {
            return ['would-renumber', "  [{$companyLabel}] WOULD RENUMBER: 'Gateway Fee Recovery' (id={$child->id}) code 4130 -> 4131."];
        }

        // Plain UPDATE, not AccountService::create()/createSystemLeaf() — this is a renumber of
        // an EXISTING leaf, not the creation of a new one; AccountService's contract has no
        // "renumber" operation. The collision check above is this method's own substitute for the
        // uniqueness guard createSystemLeaf() enforces on creation.
        DB::table('accounts')->where('id', $child->id)->update(['code' => '4131']);

        return ['renumbered', "  [{$companyLabel}] RENUMBERED: 'Gateway Fee Recovery' (id={$child->id}) code 4130 -> 4131."];
    }
}
