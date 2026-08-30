<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P2.5.B (p2_5-brief.md §P2.5.B; BUG-C4, doc 08): pins {@see TrialBalanceService}'s three
 * date-ranged query methods (`generate()`/`getAccountBalances()`, `getOpeningBalances()`,
 * `findUnbalancedTransactions()`) to `posting_date`, not `transaction_date`/`created_at` — the
 * brief's own required scenario: "report grouping on a Feb-dated doc entered in March after Feb
 * close." Fixtures insert raw rows directly (same convention as
 * TrialBalanceServiceLedgerBalanceTest::insertJournalEntry()) since the point under test is the
 * read side, not the posting engine that would normally produce the posting_date/transaction_date
 * split — {@see \Tests\Feature\Accounting\PostingSeamPeriodGuardTest} already covers that the
 * engine itself produces this split correctly.
 */
class TrialBalanceServicePostingDateTest extends TestCase
{
    use RefreshDatabase;

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function makeRoot(Company $company, string $name): Account
    {
        return Account::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'parent_id' => null,
            'root_id' => null,
            'level' => 1,
        ]);
    }

    private function makeChild(Company $company, Account $root, string $name): Account
    {
        return Account::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'level' => $root->level + 1,
        ]);
    }

    private function insertJournalEntry(
        Company $company,
        Branch $branch,
        Account $account,
        \DateTimeInterface $transactionDate,
        \DateTimeInterface $postingDate,
        float $debit,
        float $credit,
        ?int $transactionId = null
    ): void {
        DB::table('journal_entries')->insert([
            'name' => $account->name,
            'transaction_id' => $transactionId,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'transaction_date' => $transactionDate,
            'posting_date' => $postingDate,
            'description' => 'TrialBalanceServicePostingDateTest fixture line',
            'debit' => $debit,
            'credit' => $credit,
            'balance' => null,
            'voucher_number' => null,
            'currency' => 'KWD',
            'exchange_rate' => 1.0,
            'amount' => max($debit, $credit),
            'reconciled' => 0,
            'original_currency' => 'KWD',
            'original_amount' => max($debit, $credit),
            // A THIRD, unrelated date -- if generate()/getOpeningBalances() ever regress back to
            // created_at, this makes that regression impossible to hide.
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => now(),
        ]);
    }

    /**
     * generate()'s core scenario, phrased exactly as the brief's own required test: a document
     * dated in February (already closed) but posted (shifted) into March must appear in MARCH's
     * trial balance, and be entirely absent from February's -- including from the OPENING balance
     * February would otherwise have inherited it into (getOpeningBalances() sums everything BEFORE
     * $dateFrom by posting_date, not transaction_date).
     */
    public function test_february_dated_entry_shifted_to_march_appears_in_march_not_february(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $assets = $this->makeRoot($company, 'Assets');
        $account = $this->makeChild($company, $assets, 'Cash');

        $this->insertJournalEntry(
            $company,
            $branch,
            $account,
            transactionDate: Carbon::create(2026, 2, 10),
            postingDate: Carbon::create(2026, 3, 15),
            debit: 500.00,
            credit: 0,
        );

        $service = app(TrialBalanceService::class);

        $february = $service->generate($company->id, Carbon::create(2026, 2, 1), Carbon::create(2026, 2, 28), ['show_zero' => true]);
        $februaryRow = collect($february['accounts'])->firstWhere('id', $account->id);
        $this->assertSame(0.0, (float) $februaryRow->total_debit);

        $march = $service->generate($company->id, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));
        $marchRow = collect($march['accounts'])->firstWhere('id', $account->id);
        $this->assertNotNull($marchRow, 'The shifted entry must appear in March.');
        $this->assertEqualsWithDelta(500.00, (float) $marchRow->total_debit, 0.001);

        // getOpeningBalances() for a period STARTING in April must include it too (posting_date
        // 2026-03-15 < 2026-04-01) -- proving the "< $dateFrom" opening-balance query also keys off
        // posting_date, not transaction_date (which would have made it "before Feb", not "before April").
        $openingForApril = $service->getOpeningBalances($company->id, Carbon::create(2026, 4, 1));
        $this->assertTrue($openingForApril->has($account->id));
        $this->assertEqualsWithDelta(500.00, $openingForApril[$account->id]['opening_debit'], 0.001);
    }

    /**
     * findUnbalancedTransactions()'s date range likewise filters by posting_date. Builds one
     * genuinely unbalanced transaction (two journal_entries lines under it that don't net to zero)
     * dated in February but posted into March.
     */
    public function test_find_unbalanced_transactions_filters_by_posting_date(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $assets = $this->makeRoot($company, 'Assets');
        $account = $this->makeChild($company, $assets, 'Cash');

        $transactionId = DB::table('transactions')->insertGetId([
            'name' => 'TrialBalanceServicePostingDateTest fixture',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => 100.00,
            'description' => 'fixture',
            'reference_type' => 'Invoice',
            'transaction_date' => Carbon::create(2026, 2, 10),
            'posting_date' => Carbon::create(2026, 3, 15),
            'total_debit' => 100.00,
            'total_credit' => 40.00, // deliberately unbalanced
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertJournalEntry(
            $company, $branch, $account,
            transactionDate: Carbon::create(2026, 2, 10),
            postingDate: Carbon::create(2026, 3, 15),
            debit: 100.00,
            credit: 0,
            transactionId: $transactionId,
        );
        $this->insertJournalEntry(
            $company, $branch, $account,
            transactionDate: Carbon::create(2026, 2, 10),
            postingDate: Carbon::create(2026, 3, 15),
            debit: 0,
            credit: 40.00,
            transactionId: $transactionId,
        );

        $service = app(TrialBalanceService::class);

        $february = $service->findUnbalancedTransactions($company->id, Carbon::create(2026, 2, 1), Carbon::create(2026, 2, 28));
        $this->assertCount(0, $february, 'Filtering by February must not surface a document posted into March.');

        $march = $service->findUnbalancedTransactions($company->id, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));
        $this->assertCount(1, $march);
        $this->assertEqualsWithDelta(60.00, (float) $march->first()->imbalance, 0.001);
    }
}
