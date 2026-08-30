<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\RvPvInvariantChecker;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * W5.X (w5-brief.md §W5.X item 4): "accounting:verify invariant checker gains RV/PV checks: every
 * RV/PV doc balanced, no RV/PV line on a non-cash/bank leaf without a counter-leg, no
 * journal_entries row with doc_type RV/PV lacking a serial."
 *
 * No earlier wave shipped `accounting:verify` (see {@see \App\Console\Commands\AccountingVerify}'s
 * own docblock) -- this suite is therefore this command's ONLY existing test coverage, built fresh
 * against exactly the three checks the brief names. Every fixture here writes `transactions`/
 * `journal_entries` directly (not through PostingSeam) so each scenario can deliberately construct
 * the one violation shape under test -- these are the exact malformed documents the command exists
 * to catch, so hand-building them is the correct fixture strategy, not a shortcut.
 *
 * Detail assertions ("which exact violation fired") go through {@see RvPvInvariantChecker} DIRECTLY
 * -- this repo's own established convention for command-backed logic (see
 * `EnsureSystemLeavesTest`'s own docblock: `Tests\TestCase::setUp()`'s `$this->artisan('db:seed',
 * ...)` permanently rebinds the console output mock for the rest of any RefreshDatabase test, so
 * `Artisan::output()` reads empty regardless of what the command actually printed). The exit-code-
 * only assertions still go through `Artisan::call()` as a smoke test of the CLI wiring itself.
 */
class AccountingVerifyCommandTest extends AccountingTestCase
{
    /** @return array{0: Company, 1: Branch} */
    private function makeCompanyAndBranch(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        return [$company, $branch];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /**
     * @param  array<int, array{account: Account, side: string, amount: float, voucherNumber: ?string}>  $lines
     */
    private function makeDocument(Company $company, Branch $branch, string $docType, string $subType, array $lines): Transaction
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            if ($line['side'] === 'debit') {
                $totalDebit += $line['amount'];
            } else {
                $totalCredit += $line['amount'];
            }
        }

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => $docType, 'amount' => $totalDebit,
            'description' => 'accounting:verify fixture',
            'reference_type' => $docType === 'RV' ? 'Receipt' : 'Payment',
            'reference_number' => $docType.'-'.substr(uniqid(), -8),
            'name' => 'accounting:verify fixture', 'transaction_date' => now(),
            'doc_type' => $docType, 'sub_type' => $subType, 'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted', 'total_debit' => $totalDebit, 'total_credit' => $totalCredit,
            'idempotency_key' => 'verify-fixture:'.uniqid(),
        ]);

        foreach ($lines as $line) {
            /** @var Account $account */
            $account = $line['account'];
            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
                'account_id' => $account->id, 'transaction_date' => now(), 'description' => 'accounting:verify fixture',
                'debit' => $line['side'] === 'debit' ? $line['amount'] : 0,
                'credit' => $line['side'] === 'credit' ? $line['amount'] : 0,
                'name' => $account->name, 'type' => $docType, 'currency' => 'KWD', 'exchange_rate' => 1,
                'amount' => $line['amount'],
                // array_key_exists(), NOT `??` -- `??` treats an explicit `null` (the "missing
                // serial" fixture shape) the same as "key not given at all" and would silently
                // fall back to $txn->reference_number, defeating the one fixture this helper must
                // be able to build.
                'voucher_number' => array_key_exists('voucherNumber', $line) ? $line['voucherNumber'] : $txn->reference_number,
            ]);
        }

        return $txn;
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // RvPvInvariantChecker -- detail assertions.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_checker_reports_zero_violations_for_a_well_formed_rv(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $cash = $this->accountByCode($company->id, '1120');
        $account = $this->accountByCode($company->id, '2110');

        $this->makeDocument($company, $branch, 'RV', 'ACCOUNT', [
            ['account' => $cash, 'side' => 'debit', 'amount' => 50.000, 'voucherNumber' => 'RV-0001'],
            ['account' => $account, 'side' => 'credit', 'amount' => 50.000, 'voucherNumber' => 'RV-0001'],
        ]);

        $result = app(RvPvInvariantChecker::class)->check($company->id);

        $this->assertSame(1, $result['checked']);
        $this->assertSame([], $result['violations']);
    }

    public function test_checker_reports_zero_violations_for_a_well_formed_pv(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $bank = $this->accountByCode($company->id, '1201');
        $supplier = $this->accountByCode($company->id, '2110');

        $this->makeDocument($company, $branch, 'PV', 'SUPPLIER', [
            ['account' => $supplier, 'side' => 'debit', 'amount' => 30.000, 'voucherNumber' => 'PV-0001'],
            ['account' => $bank, 'side' => 'credit', 'amount' => 30.000, 'voucherNumber' => 'PV-0001'],
        ]);

        $result = app(RvPvInvariantChecker::class)->check($company->id);

        $this->assertSame([], $result['violations']);
    }

    public function test_checker_flags_an_unbalanced_rv(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $cash = $this->accountByCode($company->id, '1120');
        $account = $this->accountByCode($company->id, '2110');

        // forceCreate()/JournalEntry::create() bypass the engine's own balance check entirely
        // (that check lives in PostingService::post(), which this fixture deliberately bypasses)
        // -- exactly the malformed row accounting:verify exists to catch downstream of a raw write.
        $this->makeDocument($company, $branch, 'RV', 'ACCOUNT', [
            ['account' => $cash, 'side' => 'debit', 'amount' => 50.000, 'voucherNumber' => 'RV-0002'],
            ['account' => $account, 'side' => 'credit', 'amount' => 40.000, 'voucherNumber' => 'RV-0002'],
        ]);

        $result = app(RvPvInvariantChecker::class)->check($company->id);

        $this->assertCount(1, $result['violations']);
        $this->assertStringContainsString('NOT balanced', $result['violations'][0]);
    }

    public function test_checker_flags_a_pv_with_no_cash_or_bank_counter_leg(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $supplierA = $this->accountByCode($company->id, '2110');
        $supplierB = $this->accountByCode($company->id, '2201');

        // Both legs on non-cash/bank leaves -- a PV that never actually touches cash/bank at all.
        $this->makeDocument($company, $branch, 'PV', 'ACCOUNT', [
            ['account' => $supplierA, 'side' => 'debit', 'amount' => 20.000, 'voucherNumber' => 'PV-0003'],
            ['account' => $supplierB, 'side' => 'credit', 'amount' => 20.000, 'voucherNumber' => 'PV-0003'],
        ]);

        $result = app(RvPvInvariantChecker::class)->check($company->id);

        $this->assertCount(1, $result['violations']);
        $this->assertStringContainsString('no cash/bank counter-leg', $result['violations'][0]);
    }

    public function test_checker_flags_an_rv_line_missing_its_serial(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $cash = $this->accountByCode($company->id, '1120');
        $account = $this->accountByCode($company->id, '2110');

        $this->makeDocument($company, $branch, 'RV', 'ACCOUNT', [
            ['account' => $cash, 'side' => 'debit', 'amount' => 50.000, 'voucherNumber' => null],
            ['account' => $account, 'side' => 'credit', 'amount' => 50.000, 'voucherNumber' => null],
        ]);

        $result = app(RvPvInvariantChecker::class)->check($company->id);

        // Both lines lack a serial -- two distinct violations, one per journal_entries row.
        $this->assertCount(2, $result['violations']);
        foreach ($result['violations'] as $violation) {
            $this->assertStringContainsString('no voucher_number (serial)', $violation);
        }
    }

    public function test_checker_company_filter_isolates_violations_to_the_named_company(): void
    {
        [$companyA, $branchA] = $this->makeCompanyAndBranch();
        [$companyB, $branchB] = $this->makeCompanyAndBranch();

        $cashA = $this->accountByCode($companyA->id, '1120');
        $accountA = $this->accountByCode($companyA->id, '2110');
        // Unbalanced document in company A.
        $this->makeDocument($companyA, $branchA, 'RV', 'ACCOUNT', [
            ['account' => $cashA, 'side' => 'debit', 'amount' => 50.000, 'voucherNumber' => 'RV-A'],
            ['account' => $accountA, 'side' => 'credit', 'amount' => 45.000, 'voucherNumber' => 'RV-A'],
        ]);

        $cashB = $this->accountByCode($companyB->id, '1120');
        $accountB = $this->accountByCode($companyB->id, '2110');
        // Well-formed document in company B.
        $this->makeDocument($companyB, $branchB, 'RV', 'ACCOUNT', [
            ['account' => $cashB, 'side' => 'debit', 'amount' => 50.000, 'voucherNumber' => 'RV-B'],
            ['account' => $accountB, 'side' => 'credit', 'amount' => 50.000, 'voucherNumber' => 'RV-B'],
        ]);

        $checker = app(RvPvInvariantChecker::class);
        $this->assertSame([], $checker->check($companyB->id)['violations']);
        $this->assertCount(1, $checker->check($companyA->id)['violations']);
        // Omitting --company checks every company -- both violations (well-formed B contributes
        // none, unbalanced A contributes one) are visible in the unfiltered run.
        $this->assertGreaterThanOrEqual(1, count($checker->check(null)['violations']));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // `accounting:verify` CLI wiring -- exit code only (see class docblock for why not output text).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_command_exit_code_is_zero_for_a_well_formed_document_and_one_for_a_violation(): void
    {
        [$companyGood, $branchGood] = $this->makeCompanyAndBranch();
        $cash = $this->accountByCode($companyGood->id, '1120');
        $account = $this->accountByCode($companyGood->id, '2110');
        $this->makeDocument($companyGood, $branchGood, 'RV', 'ACCOUNT', [
            ['account' => $cash, 'side' => 'debit', 'amount' => 50.000, 'voucherNumber' => 'RV-OK'],
            ['account' => $account, 'side' => 'credit', 'amount' => 50.000, 'voucherNumber' => 'RV-OK'],
        ]);
        $this->assertSame(0, Artisan::call('accounting:verify', ['--company' => $companyGood->id]));

        [$companyBad, $branchBad] = $this->makeCompanyAndBranch();
        $cashBad = $this->accountByCode($companyBad->id, '1120');
        $accountBad = $this->accountByCode($companyBad->id, '2110');
        $this->makeDocument($companyBad, $branchBad, 'RV', 'ACCOUNT', [
            ['account' => $cashBad, 'side' => 'debit', 'amount' => 50.000, 'voucherNumber' => 'RV-BAD'],
            ['account' => $accountBad, 'side' => 'credit', 'amount' => 45.000, 'voucherNumber' => 'RV-BAD'],
        ]);
        $this->assertSame(1, Artisan::call('accounting:verify', ['--company' => $companyBad->id]));
    }
}
