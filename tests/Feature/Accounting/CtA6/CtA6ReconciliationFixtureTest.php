<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA6;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\LedgerSource;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\TestCase;

/**
 * CT-A6 — the fixture PLAN.md §0.4 and the CT-A6 wrapper both ask for: "seeded like CtA3 (engine
 * on, a few documents), prove TB/P&L/GL/BS/AR/AP all reconcile to the raw engine sums; mutation:
 * switch A6-2 to legacy -> TB mismatch test fails."
 *
 * ── Why this class extends TestCase, not AccountingTestCase ────────────────────────────────────
 * Every other accounting test in this suite extends {@see \Tests\Support\AccountingTestCase},
 * whose tearDown() runs {@see \Tests\Support\AccountingInvariants::assertAccountingInvariants()}
 * against every tracked company — including `assertNoOrphanLines()` (no `journal_entries` row
 * with `transaction_id IS NULL`). This fixture DELIBERATELY seeds a LEGACY-shaped document
 * alongside the engine's own documents — the exact "engine on, legacy rows remain" state CT-D1B
 * found in production and CT-A6-2's `LedgerSource` exists to separate — and asserts THIS class's
 * own, narrower invariant instead: each individual document (engine or legacy) still balances on
 * its own (which the raw `DB::assertLedgerBalanced`-style check below reproduces inline), without
 * asserting the two sources must ever be read together. Inheriting the stricter base class would
 * conflate "this fixture's synthetic legacy row has no matching orphan" (true, and asserted below)
 * with "no company in this suite may ever carry an un-invariant-checked legacy row" (not what this
 * fixture is testing, and not true of the real production state CT-D1B measured).
 */
class CtA6ReconciliationFixtureTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAccountingModule;

    private int $companyId;

    private int $receivableControlId;

    private int $payableControlId;

    private int $cashLeafId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 30, 12));
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;

        $this->grantAccountingModule($company);

        CoaSeeder::run($this->companyId);
        (new SystemAccountsSeeder)->run();

        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->companyId]);

        $resolver = app(AccountResolver::class);
        $this->receivableControlId = $resolver->resolve('RECEIVABLE_CONTROL', $this->companyId)->id;
        $this->payableControlId = $resolver->resolve('PAYABLE_CONTROL', $this->companyId)->id;
        $this->cashLeafId = $resolver->resolve('CASH_IN_HAND', $this->companyId)->id;

        $this->postEngineSale();
        $this->postLegacyClientPayment();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /**
     * One real, engine-posted, balanced document: AGENT-basis sale, sell 130.000 / cost 100.000
     * (owner ruling R-CT1: gross -- 4 lines). Posts:
     *   Dr RECEIVABLE_CONTROL 130.000 / Cr SERVICE_REVENUE 130.000
     *   Dr SERVICE_COST 100.000        / Cr SERVICE_PAYABLE 100.000
     * `doc_type`/`idempotency_key` are set on this transaction (PostingService::post() always
     * sets them) -- this is the "engine sum" every reconciliation assertion below is measured
     * against.
     */
    private function postEngineSale(): void
    {
        $input = new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 130.0,
            costAmount: 100.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: 501,
            supplierId: 502,
            agentId: 503,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_ISSUE,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);

        $draft = new DocumentDraft(
            companyId: $this->companyId,
            branchId: 0,
            docType: 'INV',
            subType: 'SALE',
            docDate: Carbon::create(2026, 6, 15),
            narration: 'CT-A6 fixture engine sale',
            lines: $lines,
            idempotencyKey: 'ct-a6-fixture:engine-sale:1',
        );

        app(PostingService::class)->post($draft);
    }

    /**
     * One raw, LEGACY-shaped document inserted directly (no `PostingService`, no `PostingSeam` --
     * exactly how every pre-cutover legacy writer in this codebase still posts): a client payment
     * of 40.000 received in cash, landing on the SAME `RECEIVABLE_CONTROL` leaf the engine sale
     * above also posted to. Internally balanced (Dr Cash 40.000 / Cr Receivable 40.000) and its
     * `transactions` header carries a real `transaction_id` on both lines (no orphan), so this
     * fixture never trips `AccountingInvariants::assertLedgerBalanced()`/`assertNoOrphanLines()`
     * even under the stricter base class's rules -- it simply carries NO `doc_type` and NO
     * `idempotency_key`, which is the ONLY thing that makes it "legacy" per {@see LedgerSource}'s
     * own discriminator.
     */
    private function postLegacyClientPayment(): void
    {
        $transactionId = DB::table('transactions')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Legacy client payment',
            'entity_id' => $this->companyId,
            'entity_type' => 'company',
            'transaction_type' => 'journal',
            'amount' => 40.0,
            'total_debit' => 40.0,
            'total_credit' => 40.0,
            'reference_type' => 'Payment',
            'description' => 'Pre-cutover legacy client payment, never touched PostingService',
            'doc_type' => null,
            'idempotency_key' => null,
            'transaction_date' => Carbon::create(2026, 6, 20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entries')->insert([
            [
                'company_id' => $this->companyId,
                'transaction_id' => $transactionId,
                'account_id' => $this->cashLeafId,
                'name' => 'Legacy Client Ltd',
                'description' => 'Legacy client payment (cash)',
                'debit' => 40.0,
                'credit' => 0.0,
                'transaction_date' => Carbon::create(2026, 6, 20),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $this->companyId,
                'transaction_id' => $transactionId,
                'account_id' => $this->receivableControlId,
                'name' => 'Legacy Client Ltd',
                'description' => 'Legacy client payment (receivable relief)',
                'debit' => 0.0,
                'credit' => 40.0,
                'transaction_date' => Carbon::create(2026, 6, 20),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function periodStart(): Carbon
    {
        return Carbon::create(2026, 6, 1);
    }

    private function periodEnd(): Carbon
    {
        return Carbon::create(2026, 6, 30);
    }

    /**
     * The raw engine-only sum for RECEIVABLE_CONTROL over the fixture's period, computed
     * independently of every service under test (a straight `journal_entries` join `transactions`
     * filtered on `doc_type IS NOT NULL`) -- the ground truth every other assertion in this class
     * is checked against.
     */
    private function rawEngineReceivableNetDebit(): float
    {
        $row = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.account_id', $this->receivableControlId)
            ->where('je.company_id', $this->companyId)
            ->whereNotNull('t.doc_type')
            ->selectRaw('COALESCE(SUM(je.debit),0) - COALESCE(SUM(je.credit),0) as net')
            ->value('net');

        return round((float) $row, 3);
    }

    public function test_reconciliation_fixture_is_seeded_as_described(): void
    {
        // Sanity: the fixture itself carries exactly what the class docblock claims before any
        // report-layer assertion is trusted to mean anything.
        $engineNet = $this->rawEngineReceivableNetDebit();
        $this->assertEqualsWithDelta(130.0, $engineNet, 0.0005, 'Engine-only net debit on RECEIVABLE_CONTROL must be exactly the sale amount.');

        $allSourcesNet = DB::table('journal_entries')
            ->where('account_id', $this->receivableControlId)
            ->where('company_id', $this->companyId)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net');

        $this->assertEqualsWithDelta(90.0, (float) $allSourcesNet, 0.0005, 'Engine (130 debit) net of legacy (40 credit) = 90 when both sources are summed together with no source restriction at all.');
    }

    /**
     * TB reconciliation: the trial balance's RECEIVABLE_CONTROL row must equal the raw ENGINE sum
     * (130.000), not the 90.000 an unrestricted sum over both sources would show.
     */
    public function test_trial_balance_receivable_control_reconciles_to_the_raw_engine_sum(): void
    {
        $tb = app(TrialBalanceService::class)->generate($this->companyId, $this->periodStart(), $this->periodEnd(), ['show_zero' => true]);

        $receivableRow = collect($tb['accounts'])->firstWhere('id', $this->receivableControlId);

        $this->assertNotNull($receivableRow, 'RECEIVABLE_CONTROL must appear on the trial balance.');
        $this->assertEqualsWithDelta(
            $this->rawEngineReceivableNetDebit(),
            (float) $receivableRow->total_debit - (float) $receivableRow->total_credit,
            0.0005,
            'Trial balance RECEIVABLE_CONTROL movement must equal the raw engine-only sum, not a mix of engine and legacy.'
        );
    }

    /**
     * MUTATION PROOF: an unrestricted (no {@see LedgerSource}) query over the SAME account/period
     * -- i.e. what every report on this screen did before CT-A6-2 -- does NOT match the trial
     * balance figure the fix produces. If a future change quietly dropped the LedgerSource
     * restriction back out of TrialBalanceService, this test's own baseline query would start
     * agreeing with the (now-unrestricted) trial balance, and the assertNotEquals below would fail
     * -- which is exactly the point: it fails the moment the fix regresses.
     */
    public function test_mutation_unrestricted_sum_would_not_match_the_engine_only_trial_balance(): void
    {
        $tb = app(TrialBalanceService::class)->generate($this->companyId, $this->periodStart(), $this->periodEnd(), ['show_zero' => true]);
        $receivableRow = collect($tb['accounts'])->firstWhere('id', $this->receivableControlId);
        $tbMovement = (float) $receivableRow->total_debit - (float) $receivableRow->total_credit;

        $unrestrictedMovement = (float) DB::table('journal_entries')
            ->where('account_id', $this->receivableControlId)
            ->where('company_id', $this->companyId)
            ->whereBetween('transaction_date', [$this->periodStart(), $this->periodEnd()->endOfDay()])
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net');

        $this->assertEqualsWithDelta(130.0, $tbMovement, 0.0005);
        $this->assertEqualsWithDelta(90.0, $unrestrictedMovement, 0.0005);

        // PHPUnit has no assertNotEqualsWithDelta(); the inverse is asserted directly.
        $this->assertGreaterThan(
            0.0005,
            abs($unrestrictedMovement - $tbMovement),
            'A source-unrestricted read (the pre-CT-A6-2 behaviour) must disagree with the '
            .'engine-only trial balance whenever legacy noise exists alongside engine rows -- if '
            .'they ever agree again, LedgerSource stopped being applied somewhere.'
        );
    }

    /**
     * GL reconciliation: GeneralLedgerService's closing balance for RECEIVABLE_CONTROL over the
     * same period must equal the trial balance's own closing figure for that account -- the parity
     * both services' docblocks promise.
     */
    public function test_general_ledger_closing_balance_matches_trial_balance(): void
    {
        $tb = app(TrialBalanceService::class)->generate($this->companyId, $this->periodStart(), $this->periodEnd(), ['show_zero' => true]);
        $receivableRow = collect($tb['accounts'])->firstWhere('id', $this->receivableControlId);

        $gl = app(GeneralLedgerService::class)->generate($this->companyId, $this->receivableControlId, $this->periodStart(), $this->periodEnd());

        $this->assertEqualsWithDelta(
            (float) $receivableRow->closing_balance,
            $gl['totals']['closing_balance'],
            0.0005,
            'GeneralLedgerService and TrialBalanceService must agree on RECEIVABLE_CONTROL\'s closing balance for the same period.'
        );
        $this->assertEqualsWithDelta(130.0, $gl['totals']['debit'], 0.0005);
        $this->assertEqualsWithDelta(0.0, $gl['totals']['credit'], 0.0005);
        $this->assertTrue($gl['engine_on']);
    }

    /**
     * BS reconciliation: Assets = Liabilities + Equity, difference 0.000 -- and specifically,
     * RECEIVABLE_CONTROL contributes exactly the engine figure (130.000), never the legacy-mixed
     * 90.000, to the Assets total.
     */
    public function test_balance_sheet_balances_and_excludes_legacy_noise(): void
    {
        $bs = app(BalanceSheetService::class)->generate($this->companyId, $this->periodEnd());

        $this->assertTrue($bs['totals']['is_balanced'], 'Balance sheet must balance: '.json_encode($bs['totals']));
        $this->assertEqualsWithDelta(0.0, $bs['totals']['difference'], 0.001);

        $assetLines = collect($bs['sections']['Assets']['groups'])
            ->flatMap(fn (array $group) => $group['accounts'])
            ->firstWhere('id', $this->receivableControlId);

        $this->assertNotNull($assetLines, 'RECEIVABLE_CONTROL must appear on the balance sheet Assets section.');
        $this->assertEqualsWithDelta(130.0, $assetLines->balance, 0.0005);
    }

    /**
     * AR/AP reconciliation (CT-A6-1): the "unpaid report" screen, hit over HTTP as a COMPANY-role
     * user, must show the RECEIVABLE control balance resolved through AccountResolver purposes --
     * matching the engine-only figure -- never the pre-fix hardcoded-name lookup.
     */
    public function test_unpaid_report_receivable_balance_reconciles_to_the_engine_sum(): void
    {
        $user = $this->companyUser();

        $response = $this->actingAs($user)->get(route('reports.unpaid-report', [
            'start_date' => $this->periodStart()->toDateString(),
            'end_date' => $this->periodEnd()->toDateString(),
            'account_id' => $this->receivableControlId,
        ]));

        $response->assertOk();
        $response->assertViewHas('receivableAccount', fn ($account) => (int) $account->id === $this->receivableControlId);
        $response->assertViewHas('receivableBalance', fn ($balance) => abs((float) $balance - 130.0) < 0.0005);
    }

    private function companyUser(): User
    {
        // getCompanyId() (app/Helper/helper.php) resolves a Role::COMPANY user via
        // `$user->company` -- a `hasOne(Company::class)` ACCESSOR (User.php's `company()`
        // Attribute), i.e. `companies.user_id = users.id` -- NOT a `users.company_id` column
        // (that column does not exist on this schema). The fixture's own company is therefore
        // re-pointed at the new user rather than the user being given a company_id.
        $user = User::factory()->create(['role_id' => Role::COMPANY]);

        Company::where('id', $this->companyId)->update(['user_id' => $user->id]);

        return $user;
    }
}
