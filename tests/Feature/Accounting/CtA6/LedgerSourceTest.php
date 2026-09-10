<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA6;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LedgerSource;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A6-2 — {@see LedgerSource}: the discriminator (`transactions.doc_type IS NOT NULL` =
 * engine), the transition banner, and the AR/AP control-account resolution helpers. Every test
 * builds ONE leaf carrying both an engine-posted line and a hand-inserted legacy line — the exact
 * CT-D1B "GL 1430 mixes both" shape — and asserts the two are never summed together.
 */
class LedgerSourceTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function makeCompany(): Company
    {
        $company = tap(Company::factory()->create(), fn (Company $c) => $c->forceFill(['posting_engine_enabled' => true])->save());
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
     * Posts ONE engine document: Dr $debitAccountId 100.000 / Cr PAYABLE_CONTROL 100.000, party
     * = $supplierId. Returns the resolved PAYABLE_CONTROL account.
     */
    private function postEngineDocument(Company $company, Branch $branch, int $debitAccountId, int $supplierId): Account
    {
        $payable = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'LedgerSourceTest engine fixture',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccountId,
                    side: 'debit',
                    amount: 100.000,
                    currency: 'KWD',
                    originalAmount: 100.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: 'PAYABLE_CONTROL',
                    accountId: null,
                    side: 'credit',
                    amount: 100.000,
                    currency: 'KWD',
                    originalAmount: 100.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                    partyAccountRef: $supplierId,
                ),
            ],
        );

        app(PostingService::class)->post($draft);

        return $payable;
    }

    /**
     * Hand-inserts ONE legacy transaction + two balanced journal_entries lines on the SAME
     * PAYABLE_CONTROL leaf $debitAccountId is paired against — a real `transactions` header row
     * with `doc_type` left NULL (never PostingService::post(); that is what makes it "legacy" per
     * the discriminator), so it satisfies AccountingInvariants::assertNoOrphanLines() (a real
     * transaction_id) and assertLedgerBalanced() (dr == cr on that transaction_id) without ever
     * being reachable through the engine.
     */
    private function insertLegacyDocument(Company $company, Branch $branch, int $debitAccountId, int $payableAccountId, int $supplierId, float $amount = 50.000): void
    {
        // Same header shape PostingService::createTransactionHeader() writes for a REAL engine
        // document (company_id/branch_id/entity_id/entity_type/transaction_type/amount/
        // description/reference_type/transaction_date), minus every engine-only column
        // (doc_type, idempotency_key, posting_status left at their column defaults) — a real
        // pre-engine legacy header, not a synthetic shape this schema would never actually hold.
        $transactionId = DB::table('transactions')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => $amount,
            'description' => 'LedgerSourceTest legacy fixture',
            'reference_type' => 'Payment',
            'transaction_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            // doc_type deliberately NULL — the LEGACY discriminator (CT-A3-R3 §6.6).
        ]);

        DB::table('journal_entries')->insert([
            [
                'name' => 'legacy debit',
                'transaction_id' => $transactionId,
                'company_id' => $company->id,
                'account_id' => $debitAccountId,
                'branch_id' => $branch->id,
                'transaction_date' => now(),
                'description' => 'LedgerSourceTest legacy fixture debit',
                'debit' => $amount,
                'credit' => 0,
                // Present on both rows -- DB::table()->insert() with a multi-row array requires
                // every row to carry the same column set (a mismatch produces a MySQL 1136
                // "column count doesn't match value count" error, not a Laravel-level one).
                'type_reference_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'legacy credit',
                'transaction_id' => $transactionId,
                'company_id' => $company->id,
                'account_id' => $payableAccountId,
                'branch_id' => $branch->id,
                'transaction_date' => now(),
                'description' => 'LedgerSourceTest legacy fixture credit',
                'debit' => 0,
                'credit' => $amount,
                'type_reference_id' => $supplierId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_engine_on_reflects_the_company_flags(): void
    {
        $company = $this->makeCompany();

        $this->assertTrue(app(LedgerSource::class)->engineOn($company->id));

        $company->forceFill(['posting_engine_enabled' => false])->save();
        $this->assertFalse(app(LedgerSource::class)->engineOn($company->id));

        $company->forceFill(['posting_engine_enabled' => true])->save();
        config(['accounting.engine.enabled' => false]);
        $this->assertFalse(
            app(LedgerSource::class)->engineOn($company->id),
            'The global config flag must gate engineOn() too, not only the per-company column.'
        );
    }

    /**
     * The core CT-D1B fix, and the mutation proof the task asks for in one test: a leaf with BOTH
     * an engine line (100.000) and a legacy line (50.000) reports the ENGINE figure while the
     * engine is on, and the DIFFERENT legacy figure once it is switched off — never the mixed
     * 150.000 CT-D1B measured on `1430 Unbilled Supplier Cost`. If LedgerSource's restriction were
     * ever removed from TrialBalanceService (the mutation), both assertions below would instead
     * see 150.000 and fail.
     */
    public function test_trial_balance_reads_one_source_never_both_on_a_mixed_leaf(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $supplier = \App\Models\Supplier::factory()->create();
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);

        $payable = $this->postEngineDocument($company, $branch, $debitAccount->id, $supplier->id);
        $this->insertLegacyDocument($company, $branch, $debitAccount->id, $payable->id, $supplier->id, 50.000);

        $tb = app(TrialBalanceService::class);

        $engineRow = $tb->generate($company->id, Carbon::create(2000, 1, 1), now()->addYear())['accounts']
            ->firstWhere('id', $payable->id);
        $this->assertNotNull($engineRow, 'PAYABLE_CONTROL leaf must appear in the engine-mode trial balance.');
        $this->assertEqualsWithDelta(100.000, (float) $engineRow->total_credit, 0.001, 'Engine mode must show the engine-only credit (100), never the mixed 150.');

        $company->forceFill(['posting_engine_enabled' => false])->save();

        $legacyRow = $tb->generate($company->id, Carbon::create(2000, 1, 1), now()->addYear())['accounts']
            ->firstWhere('id', $payable->id);
        $this->assertNotNull($legacyRow, 'PAYABLE_CONTROL leaf must appear in the legacy-mode trial balance.');
        $this->assertEqualsWithDelta(50.000, (float) $legacyRow->total_credit, 0.001, 'Legacy mode must show the legacy-only credit (50), never the mixed 150 nor the engine-only 100.');

        // Restore engine mode before tearDown's own invariant pass, matching every other test.
        $company->forceFill(['posting_engine_enabled' => true])->save();
    }

    public function test_transition_banner_reports_legacy_rows_while_engine_is_on(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $supplier = \App\Models\Supplier::factory()->create();
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);

        $this->assertNull(app(LedgerSource::class)->transitionBanner($company->id), 'No legacy rows yet — nothing to show a banner about.');

        $payable = $this->postEngineDocument($company, $branch, $debitAccount->id, $supplier->id);
        $this->insertLegacyDocument($company, $branch, $debitAccount->id, $payable->id, $supplier->id, 50.000);

        $banner = app(LedgerSource::class)->transitionBanner($company->id);

        $this->assertNotNull($banner);
        $this->assertSame(2, $banner['legacy_lines']);
        $this->assertEqualsWithDelta(50.000, $banner['legacy_debit'], 0.001);
        $this->assertEqualsWithDelta(50.000, $banner['legacy_credit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $banner['legacy_diff'], 0.001);
    }

    public function test_payable_account_ids_includes_control_and_distinct_per_service_leaves(): void
    {
        $company = $this->makeCompany();

        $ids = app(LedgerSource::class)->payableAccountIds($company->id, app(AccountResolver::class));
        $controlId = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id)->id;

        $this->assertContains($controlId, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)), 'A service type falling back to PAYABLE_CONTROL must not duplicate it in the list.');
    }

    public function test_receivable_account_ids_resolves_the_control_leaf(): void
    {
        $company = $this->makeCompany();

        $ids = app(LedgerSource::class)->receivableAccountIds($company->id, app(AccountResolver::class));
        $controlId = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id)->id;

        $this->assertSame([$controlId], $ids);
    }
}
