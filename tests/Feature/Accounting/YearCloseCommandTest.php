<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.C: `accounting:year:close` CLI wiring around {@see \App\Services\Accounting\YearEndCloseService}.
 */
class YearCloseCommandTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    public function test_refuses_when_periods_are_not_locked(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $exit = Artisan::call('accounting:year:close', ['company' => $company->id, 'year' => 2026]);

        $this->assertSame(1, $exit);
    }

    public function test_posts_the_yec_document_when_every_month_is_locked_with_pl_activity(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        for ($m = 1; $m <= 12; $m++) {
            AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => $m, 'status' => AccountingPeriod::STATUS_LOCKED]);
        }

        $income = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4133')->firstOrFail();
        $ar = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        $date = Carbon::create(2026, 6, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 150, 'description' => 'sale', 'reference_type' => 'Invoice',
            'reference_number' => 'S-'.uniqid(), 'name' => 'sale', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'posted',
            'total_debit' => 150, 'total_credit' => 150, 'idempotency_key' => uniqid('sale:'),
        ]);
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $ar->id,
            'transaction_date' => $date, 'posting_date' => $date, 'description' => 'sale', 'debit' => 150, 'credit' => 0,
            'name' => $ar->name, 'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 150,
            'voucher_number' => 'S', 'type_reference_id' => $company->id,
        ]);
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $income->id,
            'transaction_date' => $date, 'posting_date' => $date, 'description' => 'sale', 'debit' => 0, 'credit' => 150,
            'name' => $income->name, 'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 150,
            'voucher_number' => 'S', 'type_reference_id' => $company->id,
        ]);

        $exit = Artisan::call('accounting:year:close', ['company' => $company->id, 'year' => 2026]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('transactions', ['company_id' => $company->id, 'doc_type' => 'YEC']);
    }

    public function test_second_invocation_is_idempotent(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $owner = User::factory()->create();
        Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        for ($m = 1; $m <= 12; $m++) {
            AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => $m, 'status' => AccountingPeriod::STATUS_LOCKED]);
        }

        // No P&L activity at all -- both runs should succeed as a clean no-op, never erroring on
        // a repeat invocation.
        $first = Artisan::call('accounting:year:close', ['company' => $company->id, 'year' => 2026]);
        $second = Artisan::call('accounting:year:close', ['company' => $company->id, 'year' => 2026]);

        $this->assertSame(0, $first);
        $this->assertSame(0, $second);
    }
}
