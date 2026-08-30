<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use ReflectionClass;
use Tests\Support\AccountingTestCase;

/**
 * W5.X (w5-brief.md §W5.X item 1: "invariant test A21 -- cash/bank/Day Book/receipts report
 * queries select rows by line movement on CASH/BANK leaves, never by doc_type").
 *
 * Repo-wide search (2026-08-29, ReportController.php's ~35 report methods, TrialBalanceService.php)
 * found no report anywhere in this codebase that filters `journal_entries`/`transactions` by
 * `doc_type`/`reference_type` equal to `RV`/`PV` to compute a cash/bank/receipts figure -- the two
 * existing bank-shaped reports ({@see \App\Http\Controllers\ReportController::getTotalBank()} and
 * {@see TrialBalanceService}) already select by ACCOUNT (the "Bank Accounts" subtree / a leaf's own
 * id), which is already what A21 requires. This test suite therefore does two things:
 *
 *   1. A static ratchet (matching this repo's own established ArchitectureTest convention) pinning
 *      that fact so a FUTURE report cannot reintroduce the exact defect A21 forbids: selecting a
 *      cash/bank movement by `doc_type IN ('RV','PV')` instead of by which leaf a line posted to
 *      (which would silently drop an `AST` settlement, a `JV` cash correction, or any future
 *      cash-moving doc_type from the report).
 *   2. A functional proof, against the one CANONICAL balance path this codebase's own accounting
 *      boundary rule names (`TrialBalanceService` -- feedback_accounting_boundary: never read
 *      `accounts.actual_balance`/`journal_entries.balance`, balances via TrialBalanceService only):
 *      a bank-leaf movement posted under `doc_type = AST` (not RV/PV) is fully reflected in that
 *      leaf's trial-balance figure, proving the balance computation is genuinely by line movement,
 *      never by doc_type.
 */
class A21CashBankLineMovementTest extends AccountingTestCase
{
    private const SCANNED_FILES = [
        'app/Http/Controllers/ReportController.php',
        'app/Services/TrialBalanceService.php',
    ];

    /**
     * Grep-style static scan, same convention as {@see \Tests\Feature\Accounting\ArchitectureTest}.
     * Fails if either file ever compares `doc_type`/`reference_type` against a literal `'RV'` or
     * `'PV'` -- the substitution A21 forbids ("select by doc_type instead of by line movement").
     */
    public function test_report_code_never_selects_cash_or_bank_rows_by_rv_pv_doc_type(): void
    {
        $offenders = [];

        foreach (self::SCANNED_FILES as $relativePath) {
            $absolutePath = base_path($relativePath);
            $this->assertFileExists($absolutePath);
            $contents = file_get_contents($absolutePath);
            $this->assertNotFalse($contents);

            if (preg_match("/(doc_type|reference_type)['\"]?\\s*,\\s*['\"](RV|PV)['\"]/", $contents)
                || preg_match("/whereIn\\(\\s*['\"](doc_type|reference_type)['\"]\\s*,\\s*\\[[^\\]]*['\"](RV|PV)['\"]/", $contents)) {
                $offenders[] = $relativePath;
            }
        }

        $this->assertSame([], $offenders, 'These report files select cash/bank rows by RV/PV doc_type instead of by line movement: '.implode(', ', $offenders));
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeCompanyAndBranch(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    public function test_account_resolver_classifies_bank_and_cash_leaves_but_not_an_unrelated_liability_leaf(): void
    {
        [$company] = $this->makeCompanyAndBranch();
        $resolver = app(AccountResolver::class);

        $bank = $this->accountByCode($company->id, '1201'); // Kuwait International Bank
        $cash = $this->accountByCode($company->id, '1120'); // Receipt Voucher Cash
        $unrelated = $this->accountByCode($company->id, '2110'); // Creditors -- Liabilities, not cash/bank.

        $this->assertTrue($resolver->isCashOrBankLeaf($bank));
        $this->assertTrue($resolver->isCashOrBankLeaf($cash));
        $this->assertFalse($resolver->isCashOrBankLeaf($unrelated));
    }

    /**
     * A bank-leaf movement posted under doc_type=AST (an agent settlement, NOT RV/PV) must still
     * show up in TrialBalanceService's own figure for that leaf -- proving the canonical balance
     * path reflects the movement by ACCOUNT, regardless of which doc_type carried it. If
     * TrialBalanceService (or any future report built the same way) ever special-cased "only
     * doc_type IN ('RV','PV') counts as a cash/bank movement", this line would silently vanish
     * from the figure and this assertion would fail.
     */
    public function test_trial_balance_reflects_a_bank_movement_posted_under_a_non_rv_pv_doc_type(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();

        $bank = $this->accountByCode($company->id, '1201');
        $incomeSuspense = $this->accountByCode($company->id, '4133');

        $amount = 77.250;

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'AST', 'amount' => $amount, 'description' => 'Agent settlement (non-RV/PV cash movement)',
            'reference_type' => 'Payment', 'reference_number' => 'AST-A21-'.substr(uniqid(), -8),
            'name' => 'A21 fixture', 'transaction_date' => now(),
            'doc_type' => 'AST', 'sub_type' => 'LEGACY', 'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted', 'total_debit' => $amount, 'total_credit' => $amount,
            'idempotency_key' => 'a21-ast:'.uniqid(),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bank->id, 'transaction_date' => now(), 'description' => 'A21 fixture',
            'debit' => $amount, 'credit' => 0, 'name' => $bank->name, 'type' => 'bank',
            'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'AST-A21',
        ]);
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $incomeSuspense->id, 'transaction_date' => now(), 'description' => 'A21 fixture',
            'debit' => 0, 'credit' => $amount, 'name' => $incomeSuspense->name, 'type' => 'income',
            'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'AST-A21',
        ]);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $bank->id);

        $this->assertEqualsWithDelta($amount, $balance, 0.0005, 'The AST-doc_type bank movement is missing from TrialBalanceService::getCurrentAccountBalance() — it appears to be filtering by doc_type instead of by line movement.');
    }
}
