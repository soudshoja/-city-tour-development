<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA6;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * CT-A6-3 — {@see GeneralLedgerService}: opening balance (all history strictly before the
 * period), period lines with a running balance, closing balance, and — the CT-specific departure
 * from the Akeed port this class's own docblock documents — engine-only rows on a leaf that also
 * carries a legacy line, per {@see \App\Services\Accounting\LedgerSource}.
 */
class GeneralLedgerServiceTest extends AccountingTestCase
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

    private function postFixtureDocument(Company $company, Branch $branch, int $debitAccountId, int $payableAccountId, float $amount, int $supplierId, Carbon $date): void
    {
        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: $date,
            narration: 'GeneralLedgerServiceTest fixture',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccountId,
                    side: 'debit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $payableAccountId,
                    side: 'credit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                    partyAccountRef: $supplierId,
                ),
            ],
        );

        app(PostingService::class)->post($draft);
    }

    public function test_opening_balance_period_lines_and_closing_balance_reconcile(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $supplier = Supplier::factory()->create();
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $payable = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id);

        // Opening: one document dated well before the reporting period.
        $this->postFixtureDocument($company, $branch, $debitAccount->id, $payable->id, 40.000, $supplier->id, Carbon::parse('2024-01-01'));

        // Period: two documents inside [2025-01-01, 2025-01-31].
        $this->postFixtureDocument($company, $branch, $debitAccount->id, $payable->id, 25.000, $supplier->id, Carbon::parse('2025-01-10'));
        $this->postFixtureDocument($company, $branch, $debitAccount->id, $payable->id, 15.000, $supplier->id, Carbon::parse('2025-01-20'));

        $ledger = app(GeneralLedgerService::class)->generate(
            $company->id,
            $payable->id,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-31')
        );

        $this->assertEqualsWithDelta(40.000, $ledger['opening_balance'], 0.001, 'Opening balance must carry the pre-period document forward.');
        $this->assertEqualsWithDelta(40.000, $ledger['totals']['credit'], 0.001, 'Period credit must be exactly the two in-period documents (25 + 15).');
        $this->assertEqualsWithDelta(0.0, $ledger['totals']['debit'], 0.001);
        $this->assertEqualsWithDelta(80.000, $ledger['totals']['closing_balance'], 0.001, 'Closing = opening (40) + period movement (40).');
        $this->assertCount(2, $ledger['lines'], 'Only the two in-period lines are listed; the pre-period document is opening-only.');

        // Running balance is cumulative from the opening balance.
        $rows = $ledger['lines']->getCollection()->values();
        $this->assertEqualsWithDelta(65.000, (float) $rows[0]->running_balance, 0.001);
        $this->assertEqualsWithDelta(80.000, (float) $rows[1]->running_balance, 0.001);
    }

    public function test_period_lines_show_only_engine_rows_on_a_mixed_leaf(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $supplier = Supplier::factory()->create();
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $payable = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id);

        $this->postFixtureDocument($company, $branch, $debitAccount->id, $payable->id, 100.000, $supplier->id, now());

        \Illuminate\Support\Facades\DB::table('transactions')->insert([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => 50.000,
            'description' => 'legacy fixture',
            'reference_type' => 'Payment',
            'transaction_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $legacyTransactionId = \Illuminate\Support\Facades\DB::getPdo()->lastInsertId();

        \Illuminate\Support\Facades\DB::table('journal_entries')->insert([
            ['name' => 'd', 'transaction_id' => $legacyTransactionId, 'company_id' => $company->id, 'account_id' => $debitAccount->id, 'branch_id' => $branch->id, 'transaction_date' => now(), 'description' => 'legacy debit', 'debit' => 50, 'credit' => 0, 'type_reference_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'c', 'transaction_id' => $legacyTransactionId, 'company_id' => $company->id, 'account_id' => $payable->id, 'branch_id' => $branch->id, 'transaction_date' => now(), 'description' => 'legacy credit', 'debit' => 0, 'credit' => 50, 'type_reference_id' => $supplier->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $ledger = app(GeneralLedgerService::class)->generate($company->id, $payable->id, now()->startOfYear(), now()->endOfYear());

        $this->assertEqualsWithDelta(100.000, $ledger['totals']['credit'], 0.001, 'Engine mode must show only the engine credit (100), never the mixed 150.');
        $this->assertCount(1, $ledger['lines'], 'Only the engine-posted line appears; the legacy line is excluded.');
    }
}
