<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA56R3;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\LedgerSource;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A56 R3 probe — {@see LedgerSource}'s discriminator against the ONE row shape neither PR #12
 * nor PR #13 had a fixture for: a document written by the seam's own **engine-OFF** writer.
 *
 * `BankPaymentController::writeLegacyTransaction()` (`:958`) and
 * `ReceiptVoucherController::writeLegacyTransaction()` (`:1338`) are the OFF-path legacy writers
 * CT-A3 waves 1/2 built for OFF/ON parity. Both `Transaction::forceCreate()` a header that
 * carries **`doc_type` = the draft's own doc type** (`'PV'`/`'RV'`/`'JV'`), `sub_type`,
 * `doc_year`, `posting_status='posted'`, `posted_at` and `idempotency_key` — everything the
 * engine header carries EXCEPT `posting_date`, which only {@see \App\Services\Accounting\
 * PostingService::post()} ever writes.
 *
 * {@see LedgerSource}'s class docblock asserts the opposite in so many words — *"only
 * PostingService::post() ever sets doc_type, unconditionally"* — and its discriminator
 * (`transactions.doc_type IS NOT NULL` = engine) is built on that claim. It is false: these two
 * writers set it too, on documents the engine did not write.
 *
 * Consequence, which these probes measure rather than argue: on an engine-OFF company —
 * companies 2 and 3 on the City Travelers dev site, and EVERY company between the code deploy and
 * the gate flip, which is the exact sequence CT-D1 §0.4i used — a payment voucher or receipt
 * voucher posted through the seam is classified as an ENGINE row and therefore
 * `whereNotExists`-ed straight out of the trial balance, the general ledger, the balance sheet and
 * both AR/AP screens. The money is on the ledger and off every report.
 */
class LedgerSourceOffPathSeamProbeTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The gate is ON globally (a deployed build), but this company's own switch is OFF —
        // the state every company is in between `ACCOUNTING_ENGINE_ENABLED=true` landing in
        // `.env` and `companies.posting_engine_enabled` being flipped for it.
        config(['accounting.engine.enabled' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function makeCompany(bool $engineOn): Company
    {
        $company = tap(Company::factory()->create(), fn (Company $c) => $c
            ->forceFill(['posting_engine_enabled' => $engineOn])->save());
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * The EXACT header + line shape `BankPaymentController::writeLegacyTransaction()` writes for a
     * supplier payment voucher on the engine-OFF path: `doc_type` SET, `posting_date` NULL on both
     * the header and every line. Column-for-column from that method, minus the columns a
     * `LineDraft` leaves null on this flow.
     *
     * @return int the legacy transaction id
     */
    private function insertOffPathSeamVoucher(
        Company $company,
        Branch $branch,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        ?int $partyAccountRef = null,
    ): int {
        $transactionId = DB::table('transactions')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'PV',
            'amount' => $amount,
            'description' => 'R3 probe — OFF-path seam payment voucher',
            'reference_type' => 'Payment',
            'reference_number' => 'PV-R3-'.uniqid(),
            'name' => 'R3 probe payee',
            'transaction_date' => now(),
            // ── the four engine-looking columns writeLegacyTransaction() sets ────────────────
            'doc_type' => 'PV',
            'sub_type' => 'SUPPLIER',
            'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted',
            // ── and the one it does NOT: posting_date stays NULL ─────────────────────────────
            'total_debit' => $amount,
            'total_credit' => $amount,
            'idempotency_key' => 'r3-probe-offpath:'.uniqid(),
            'posted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entries')->insert([
            [
                'name' => 'R3 probe payee',
                'transaction_id' => $transactionId,
                'company_id' => $company->id,
                'account_id' => $debitAccountId,
                'branch_id' => $branch->id,
                'transaction_date' => now(),
                'description' => 'R3 probe — OFF-path seam debit',
                'debit' => $amount,
                'credit' => 0,
                'type_reference_id' => $partyAccountRef,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'R3 probe payee',
                'transaction_id' => $transactionId,
                'company_id' => $company->id,
                'account_id' => $creditAccountId,
                'branch_id' => $branch->id,
                'transaction_date' => now(),
                'description' => 'R3 probe — OFF-path seam credit',
                'debit' => 0,
                'credit' => $amount,
                'type_reference_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return $transactionId;
    }

    private function payableLeafId(Company $company): int
    {
        return app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id)->id;
    }

    private function bankLeafId(Company $company): int
    {
        return app(AccountResolver::class)->resolve('CASH_IN_HAND', $company->id)->id;
    }

    /**
     * DEFECT R3-1. The row shape is not hypothetical — assert first that the production writer
     * really does leave this fingerprint, so this probe cannot silently rot into testing itself.
     */
    public function test_the_off_path_seam_writers_really_do_set_doc_type(): void
    {
        foreach ([
            'app/Http/Controllers/BankPaymentController.php',
            'app/Http/Controllers/ReceiptVoucherController.php',
        ] as $relative) {
            $source = file_get_contents(base_path($relative));

            $this->assertIsString($source);

            $start = strpos($source, 'private function writeLegacyTransaction(');
            $this->assertNotFalse($start, "{$relative} no longer has a writeLegacyTransaction() — re-derive this probe.");

            $body = substr($source, $start, 4000);

            $this->assertStringContainsString(
                "'doc_type' => \$draft->docType",
                $body,
                "{$relative}::writeLegacyTransaction() is the ENGINE-OFF writer and still stamps doc_type — ".
                'LedgerSource\'s "only PostingService ever sets doc_type" premise is false.'
            );
            $this->assertStringNotContainsString(
                "'posting_date'",
                $body,
                "{$relative}::writeLegacyTransaction() must NOT write posting_date — that column is the ".
                'one honest engine fingerprint left, and this probe (and the CT-A5a ratchet oracle) rest on it.'
            );
        }
    }

    /**
     * DEFECT R3-1, the money half. An engine-OFF company posts a supplier payment through the
     * seam. Its own trial balance must show it. Today it does not.
     */
    public function test_an_engine_off_company_sees_its_own_off_path_seam_voucher_on_the_trial_balance(): void
    {
        $company = $this->makeCompany(engineOn: false);
        $branch = $this->makeBranch($company);

        $payable = $this->payableLeafId($company);
        $bank = $this->bankLeafId($company);

        $this->insertOffPathSeamVoucher($company, $branch, $payable, $bank, 250.000);

        $this->assertFalse(app(LedgerSource::class)->engineOn($company->id));

        $rows = app(TrialBalanceService::class)
            ->generate($company->id, Carbon::now()->subYear(), Carbon::now()->addDay(), ['show_zero' => false])['accounts']
            ->keyBy('id');

        $this->assertTrue(
            $rows->has($payable),
            'The OFF-path seam voucher is missing from the trial balance entirely — LedgerSource '.
            'classified a legacy document as an engine document because its header carries a doc_type.'
        );
        $this->assertEqualsWithDelta(250.000, (float) $rows[$payable]->total_debit, 0.0005);
        $this->assertEqualsWithDelta(250.000, (float) $rows[$bank]->total_credit, 0.0005);
    }

    /**
     * DEFECT R3-1, the opposite direction: the same row must NOT be counted as engine money for a
     * company whose engine IS on. (A company that flips on mid-life carries OFF-path vouchers
     * written before the flip; counting them as engine rows double-counts them against the
     * replay's own document for the same event.)
     */
    public function test_an_engine_on_company_does_not_count_a_pre_flip_off_path_voucher_as_engine_money(): void
    {
        $company = $this->makeCompany(engineOn: true);
        $branch = $this->makeBranch($company);

        $payable = $this->payableLeafId($company);
        $bank = $this->bankLeafId($company);

        $this->insertOffPathSeamVoucher($company, $branch, $payable, $bank, 250.000);

        $rows = app(TrialBalanceService::class)
            ->generate($company->id, Carbon::now()->subYear(), Carbon::now()->addDay(), ['show_zero' => false])['accounts']
            ->keyBy('id');

        $this->assertFalse(
            $rows->has($payable) && (float) $rows[$payable]->total_debit > 0.0005,
            'A voucher written by the engine-OFF seam writer was counted as ENGINE money on an '.
            'engine-ON company — the replay posts its own document for the same event, so this is a '.
            'double count.'
        );
    }

    /**
     * DEFECT R3-1 as the transition banner sees it: an engine-ON company with OFF-path seam rows
     * left behind must be told they are there. Under the doc_type-only discriminator the banner
     * reports "nothing left to reconcile" while the rows sit on the ledger.
     */
    public function test_the_transition_banner_counts_a_pre_flip_off_path_voucher_as_legacy(): void
    {
        $company = $this->makeCompany(engineOn: true);
        $branch = $this->makeBranch($company);

        $this->insertOffPathSeamVoucher(
            $company,
            $branch,
            $this->payableLeafId($company),
            $this->bankLeafId($company),
            250.000
        );

        $banner = app(LedgerSource::class)->transitionBanner($company->id);

        $this->assertNotNull(
            $banner,
            'The ledger-in-transition banner reported nothing to reconcile while two un-replayed '.
            'OFF-path seam rows sat on the ledger.'
        );
        $this->assertSame(2, $banner['legacy_lines']);
    }

    /**
     * DEFECT R3-5 — the dashboard KPI tiles (`ReportController::getAccountBalance()`, reached from
     * `getDashboardStats()`) sum `journal_entries` with NO {@see LedgerSource} restriction at all,
     * so an engine-ON company with a legacy twin still standing double-counts it. That method is on
     * `ArchitectureTest::ALLOW_LISTED_ACCOUNT_NAME_LOOKUP_METHODS` for its hardcoded-name lookup,
     * which is what kept CT-A6's own audit from noticing the missing source restriction underneath.
     *
     * Measured through the real HTTP route so it is the tile the owner actually looks at.
     */
    public function test_the_dashboard_payable_tile_does_not_double_count_a_legacy_twin(): void
    {
        $company = $this->makeCompany(engineOn: true);
        $branch = $this->makeBranch($company);

        $payable = $this->payableLeafId($company);
        $bank = $this->bankLeafId($company);

        // The legacy half of a dual posting: doc_type NULL, posting_date NULL — the pre-cutover
        // writer shape, and the one the LIVE->DEV mirror keeps delivering hourly.
        $legacyTxId = DB::table('transactions')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => 400.000,
            'description' => 'R3 probe — legacy twin',
            'reference_type' => 'Payment',
            'transaction_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entries')->insert([
            [
                'name' => 'legacy twin credit',
                'transaction_id' => $legacyTxId,
                'company_id' => $company->id,
                'account_id' => $payable,
                'branch_id' => $branch->id,
                'transaction_date' => now(),
                'description' => 'R3 probe — legacy twin credit',
                'debit' => 0,
                'credit' => 400.000,
                'type_reference_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'legacy twin debit',
                'transaction_id' => $legacyTxId,
                'company_id' => $company->id,
                'account_id' => $bank,
                'branch_id' => $branch->id,
                'transaction_date' => now(),
                'description' => 'R3 probe — legacy twin debit',
                'debit' => 400.000,
                'credit' => 0,
                'type_reference_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $engineOnly = app(TrialBalanceService::class)
            ->generate($company->id, Carbon::now()->subYear(), Carbon::now()->addDay(), ['show_zero' => true])['accounts']
            ->keyBy('id');

        $trialBalancePayable = $engineOnly->has($payable)
            ? (float) $engineOnly[$payable]->total_credit - (float) $engineOnly[$payable]->total_debit
            : 0.0;

        // Reached by reflection rather than over HTTP: the `reports.dashboard-stats` route sits
        // behind `module:accounting`, and what is under test is the SUM, not the route wiring.
        $controller = app(\App\Http\Controllers\ReportController::class);
        $method = new \ReflectionMethod($controller, 'getAccountBalance');
        $method->setAccessible(true);

        $tile = (float) $method->invoke($controller, 'Accounts Payable', $company->id, true);

        $this->assertEqualsWithDelta(
            $trialBalancePayable,
            $tile,
            0.0005,
            'The dashboard "payable supplier" tile disagrees with the trial balance for the same '.
            'company: getAccountBalance() sums BOTH ledger sources while TrialBalanceService reads '.
            'only the engine one. On the dev site every replayed document has a mirrored legacy twin, '.
            'so this tile reads roughly double.'
        );
    }
}
