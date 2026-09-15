<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7-4 — finding **R3-12** (VERIFY-CT-A56-R3 §5), verbatim:
 *
 * > "`BalanceSheetService::netProfit()` groups by `root_id`, `ReportController::profitLoss()`
 * >  selects on `report_type`; two profit figures on screen, derived differently, unreconciled."
 *
 * ── What this lane fixed, and what it deliberately did not ─────────────────────────────────────
 * R3-12 names two independent differences that the one sentence runs together:
 *
 *   1. **SOURCE.** `netProfit()` reads through `TrialBalanceService`, which applies
 *      {@see \App\Services\Accounting\LedgerSource::restrict()}. `profitLoss()` applied **no source
 *      restriction at all** — it summed engine rows and their mirrored legacy twins together, the
 *      same defect R3-5 found on the dashboard tiles and R3-11 on the paid report, and on this
 *      ledger (CT-A5a: 2,081 dual-posted transactions) it is the DOMINANT term. Unambiguous, and
 *      already ruled on twice. **Fixed in CT-A7-4**, and the first two cases below are its proof.
 *
 *   2. **TAXONOMY.** Which accounts are P&L accounts: `root_id` -> the roots literally named
 *      'Income' and 'Expenses' (`netProfit()`), versus the per-account `accounts.report_type`
 *      flag plus a level-3 walk plus a `code` first-character test for the income/expense SIGN
 *      (`profitLoss()`). **NOT fixed, deliberately** — see the last case below, which measures
 *      whether the two agree on a standard chart rather than asserting that they do, and pins the
 *      remaining gap so it cannot be lost.
 */
class ProfitDerivationsR12Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    private const SELL = 400.0;

    private const COST = 250.0;

    /** The legacy twin's revenue — deliberately unequal to the engine figure. */
    private const LEGACY_REVENUE = 77.0;

    private int $companyId;

    private int $branchId;

    private \Carbon\Carbon $docDate;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->companyId, 'user_id' => $branchOwner->id]);
        $this->branchId = (int) $branch->id;

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
        ]);

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->companyId]);

        // Mid-month so that [startOfMonth, endOfMonth] — the window profitLoss() uses — contains
        // the whole fixture with room either side, and the month never straddles "today".
        $this->docDate = now()->startOfMonth()->addDays(10);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $this->companyId]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => self::SELL,
            'issued_date' => $this->docDate,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => $this->docDate,
        ]);
        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: self::SELL,
            costAmount: self::COST,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: (int) $client->id, clientName: $client->full_name,
            supplierId: (int) $supplier->id, supplierName: $supplier->name,
            agentId: (int) $agent->id, agentName: $agent->name,
            invoiceId: (int) $invoice->id, invoiceDetailId: (int) $detail->id, taskId: (int) $task->id,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $this->companyId, branchId: (int) $agent->branch_id, docType: 'INV', subType: 'SALE',
            docDate: $this->docDate, narration: 'CT-A7-4 R3-12 fixture sale', lines: $lines,
            idempotencyKey: 'ct-a7-4:r312:'.$detail->id, invoiceId: (int) $invoice->id,
        ));

        $this->postLegacyRevenueTwin();
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Company::forgetModuleCache();
        parent::tearDown();
    }

    /** A legacy (doc_type NULL, posting_date NULL) revenue credit in the same month. */
    private function postLegacyRevenueTwin(): void
    {
        $revenue = Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', '4133')->firstOrFail();
        $receivable = Account::withoutGlobalScopes()->findOrFail(
            app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $this->companyId)->id
        );

        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'entity_id' => $this->companyId,
            'entity_type' => 'company',
            'transaction_type' => 'INV',
            'amount' => self::LEGACY_REVENUE,
            'description' => 'CT-A7-4 R3-12 legacy twin',
            'reference_type' => 'Invoice',
            'reference_number' => 'CTA74-R312-'.substr(uniqid(), -8),
            'name' => 'CT-A7-4 R3-12 legacy twin',
            'transaction_date' => $this->docDate,
            'total_debit' => self::LEGACY_REVENUE,
            'total_credit' => self::LEGACY_REVENUE,
        ]);

        $this->assertNull($txn->fresh()->doc_type, 'the twin must be LEGACY by the LedgerSource discriminator');

        foreach ([
            [$receivable, self::LEGACY_REVENUE, 0.0, 'receivable'],
            [$revenue, 0.0, self::LEGACY_REVENUE, 'income'],
        ] as [$account, $debit, $credit, $type]) {
            DB::table('journal_entries')->insert([
                'transaction_id' => $txn->id,
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'account_id' => $account->id,
                'transaction_date' => $this->docDate,
                'posting_date' => $this->docDate,
                'description' => 'CT-A7-4 R3-12 legacy twin',
                'debit' => $debit,
                'credit' => $credit,
                'name' => $account->name,
                'type' => $type,
                'currency' => 'KWD',
                'exchange_rate' => 1,
                'amount' => self::LEGACY_REVENUE,
                'voucher_number' => 'CTA74-R312',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * `ReportPolicy::viewProfitLoss()` gates on the accounting MODULE first and then on the
     * `view profit loss` permission — same construction as
     * {@see \Tests\Feature\Accounting\ReportControllerProfitLossPostingDateTest::makeAuthorizedAdmin()}.
     */
    private function companyUser(): User
    {
        Permission::firstOrCreate(['name' => 'view profit loss', 'group' => 'report']);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->givePermissionTo('view profit loss');

        session(['company_id' => $this->companyId]);

        return $user;
    }

    /** The P&L screen's own net profit, summed off the view data the blade prints. */
    private function screenNetProfit(): float
    {
        $response = $this->actingAs($this->companyUser())
            ->get(route('reports.profit-loss', ['month' => $this->docDate->format('Y-m'), 'year' => $this->docDate->format('Y')]));

        $response->assertOk();

        // `amount` is credit − debit on both collections: income is positive, expense negative, so
        // the NET is their plain sum. Not `income − expense`, which would double-negate expenses.
        $income = collect($response->viewData('incomeAccounts'))->sum(fn ($row) => (float) $row['amount']);
        $expense = collect($response->viewData('expenseAccounts'))->sum(fn ($row) => (float) $row['amount']);

        return round($income + $expense, 3);
    }

    private function balanceSheetNetProfit(): float
    {
        return round((float) app(BalanceSheetService::class)
            ->generate($this->companyId, $this->docDate->copy()->endOfMonth())['net_profit'], 3);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The fixture holds both row kinds in the P&L window before anything under test is consulted.
     */
    public function test_the_fixture_holds_an_engine_revenue_row_and_a_legacy_revenue_row(): void
    {
        $allRevenue = (float) DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('je.company_id', $this->companyId)
            ->where('root.name', 'Income')
            ->whereNull('je.deleted_at')
            ->sum('je.credit');

        // Gross, per R-CT1: an AGENT-basis sale credits the FULL sell price to Income and debits the
        // supplier cost to Expenses, so the Income root's credits are the sell price, not the
        // margin. The margin (SELL - COST = 150.000) is the NET the two profit figures report.
        $this->assertEqualsWithDelta(
            self::SELL + self::LEGACY_REVENUE,
            $allRevenue,
            0.0005,
            'unrestricted, the Income root holds BOTH rows — the legacy twin inflated every profit '
            .'figure this screen showed'
        );
    }

    /**
     * R3-12, the SOURCE half: the P&L screen reads engine rows only, like every other report.
     */
    public function test_the_profit_and_loss_screen_reads_engine_rows_only(): void
    {
        $engineProfit = round(self::SELL - self::COST, 3);

        $this->assertEqualsWithDelta(
            $engineProfit,
            $this->screenNetProfit(),
            0.0005,
            'the P&L screen must read the ENGINE figure'
        );
        $this->assertNotEqualsWithDelta(
            round($engineProfit + self::LEGACY_REVENUE, 3),
            $this->screenNetProfit(),
            0.0005,
            'and must NOT read the pre-fix figure — engine plus its mirrored legacy twin'
        );
    }

    /**
     * The twelve-month chart under the table reads the same rows as the table. Before CT-A7-4 the
     * two queries had the same (absent) restriction, so they agreed by both being wrong; the point
     * of asserting it now is that the fix touched both and they must still agree.
     */
    public function test_the_yearly_chart_agrees_with_the_table(): void
    {
        $response = $this->actingAs($this->companyUser())
            ->get(route('reports.profit-loss', ['month' => $this->docDate->format('Y-m'), 'year' => $this->docDate->format('Y')]));

        $response->assertOk();

        $monthIndex = ((int) $this->docDate->format('n')) - 1;
        $monthlyProfits = $response->viewData('monthlyProfits');

        $this->assertEqualsWithDelta(
            round(self::SELL - self::COST, 2),
            (float) $monthlyProfits[$monthIndex],
            0.005,
            'the chart bar for this month must be the engine profit, not engine + legacy twin'
        );
    }

    /**
     * R3-12, the TAXONOMY half — MEASURED, not assumed, and this is the case that pins what was
     * deferred.
     *
     * With the source difference removed, the two derivations are compared directly on a standard
     * `CoaSeeder` chart. If they agree here, the residual R3-12 risk is confined to charts where
     * `accounts.report_type` and the `Income`/`Expenses` root membership disagree — which is a
     * CHART-DATA question and an owner decision about which of the two is authoritative, not a
     * report bug this lane can settle. If they ever stop agreeing on a standard chart, this case
     * fails and says so, which is the whole point of writing it as an assertion rather than as a
     * paragraph in a report nobody re-runs.
     */
    public function test_the_two_profit_derivations_agree_on_a_standard_chart(): void
    {
        $this->assertEqualsWithDelta(
            $this->balanceSheetNetProfit(),
            $this->screenNetProfit(),
            0.0005,
            'R3-12: the balance sheet\'s Equity profit line and the P&L screen\'s net must be the same '
            .'number on a standard chart. They are derived differently — root membership vs '
            .'accounts.report_type + a level-3 walk + a code-prefix sign test — and CT-A7-4 removed only '
            .'the SOURCE difference between them. A failure here means the two TAXONOMIES have diverged '
            .'on this chart, which is the deferred half of R3-12.'
        );
    }
}
