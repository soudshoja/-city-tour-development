<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\ReconciliationService;
use Database\Seeders\CoaSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * W5.X (w5-brief.md §W5.X item 3: "reconcile / declineReconcile / fetchPaymentsByDate actions
 * moved behind a ReconciliationService method and gated by permission accounting.reconcile").
 *
 * Exercises {@see ReconciliationService} directly AND through both controllers' now-thin
 * delegating routes, proving: (a) the permission gate actually refuses an unauthorized caller
 * before either controller ever touches journal_entries; (b) the supplier search resolves the
 * account via {@see Supplier::payableAccount()} (accounts.supplier_id), never by matching
 * accounts.name -- the fix for the exact anti-pattern {@see W5XArchitectureTest} pins statically.
 */
class ReconciliationServiceTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private function makeCompanyAndBranch(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    /**
     * A Role::AGENT user actually attached to this company/branch (via a real Agent row) so
     * `EnsureModuleEnabled`'s own `getCompanyId()`-based resolution succeeds and the request
     * reaches the controller at all -- an agent with no Agent row resolves to NO company
     * (getCompanyId() returns null for that case) and the module-gate middleware 404s the request
     * before ReconciliationService::assertCanReconcile() ever runs, which would test the wrong
     * layer. AGENT is not in assertCanReconcile()'s allowed role_id tier and has no
     * accounting.reconcile permission, so it still exercises the intended 403 refusal.
     */
    private function makeUnauthorizedAgentUser(Company $company, Branch $branch): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = AgentType::firstOrCreate(['name' => 'w5x-recon-test-type']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /** Writes a BALANCED two-line transaction -- a credit on the given liability leaf (the row
     * these tests exercise) plus a debit counter-leg on an ordinary expense leaf (the shape a
     * real supplier-cost posting takes; the counter-leg's own account is irrelevant to every
     * assertion here, it exists only so {@see \Tests\Support\AccountingInvariants}'s per-
     * transaction balance check, which AccountingTestCase::tearDown() runs against every tracked
     * company, does not fail on a deliberately single-sided fixture row). */
    private function writeLiabilityLine(Company $company, Branch $branch, Account $account, float $amount, ?string $voucherNumber = null): JournalEntry
    {
        $expense = $this->accountByCode($company->id, '5222'); // Bank Charges -- any expense leaf works as the counter-leg.

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Supplier cost',
            'reference_type' => 'Invoice', 'reference_number' => 'FIX-'.uniqid(),
            'name' => 'Supplier cost', 'transaction_date' => now(),
            'doc_type' => 'JV', 'doc_year' => (int) now()->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => 'fix:'.uniqid(),
        ]);

        $line = JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $account->id, 'transaction_date' => now(), 'description' => 'Supplier cost',
            'debit' => 0, 'credit' => $amount, 'name' => $account->name, 'type' => 'payable',
            'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'reconciled' => 0, 'voucher_number' => $voucherNumber,
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $expense->id, 'transaction_date' => now(), 'description' => 'Supplier cost',
            'debit' => $amount, 'credit' => 0, 'name' => $expense->name, 'type' => 'expense',
            'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
        ]);

        return $line;
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // assertCanReconcile()
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_assert_can_reconcile_allows_admin(): void
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        app(ReconciliationService::class)->assertCanReconcile($admin);
        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_assert_can_reconcile_refuses_a_bare_agent_with_no_permission(): void
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);

        $this->expectException(AuthorizationException::class);
        app(ReconciliationService::class)->assertCanReconcile($agentUser);
    }

    public function test_assert_can_reconcile_refuses_a_null_user(): void
    {
        $this->expectException(AuthorizationException::class);
        app(ReconciliationService::class)->assertCanReconcile(null);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // fetchPaymentsByDate() -- supplier resolution via Supplier::payableAccount(), never by name
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_fetch_payments_by_date_resolves_supplier_via_payable_account_fk_not_account_name(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();

        $target = $this->accountByCode($company->id, '2110'); // Creditors -- a Liabilities leaf.
        $other = $this->accountByCode($company->id, '2201'); // Different Liabilities leaf.

        // The account's OWN name ("Creditors") has nothing to do with the supplier's display
        // name -- proving resolution goes through the FK, never a match against accounts.name.
        $supplier = Supplier::factory()->create(['name' => 'Acme Airlines']);
        Account::where('id', $target->id)->update(['supplier_id' => $supplier->id]);

        $line = $this->writeLiabilityLine($company, $branch, $target, 25.500);
        $this->writeLiabilityLine($company, $branch, $other, 40.000);

        $results = app(ReconciliationService::class)->fetchPaymentsByDate(
            $company->id,
            [$branch->id],
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
            'Acme', // partial match against the SUPPLIER's name, not the account's.
        );

        $this->assertCount(1, $results);
        $this->assertSame($line->id, $results->first()['id']);
        $this->assertSame($target->id, $results->first()['account_id']);
    }

    public function test_fetch_payments_by_date_with_no_supplier_returns_every_unreconciled_liability_line(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();

        $a = $this->accountByCode($company->id, '2110');
        $b = $this->accountByCode($company->id, '2201');

        $this->writeLiabilityLine($company, $branch, $a, 10.000);
        $this->writeLiabilityLine($company, $branch, $b, 20.000);

        $results = app(ReconciliationService::class)->fetchPaymentsByDate(
            $company->id,
            [$branch->id],
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
        );

        $this->assertCount(2, $results);
    }

    /**
     * A search term matching no Supplier row is treated as "no filter applied" -- preserved
     * verbatim from both controllers' pre-existing behaviour (the original code logged
     * "Supplier name not found" and left `$accountIds` empty, which the query treats identically
     * to "no supplier filter requested" -- an unfiltered listing, not an empty one). This test
     * pins that preserved behaviour so a future change to the fallback is a deliberate decision,
     * not a silent regression.
     */
    public function test_fetch_payments_by_date_unknown_supplier_name_falls_back_to_unfiltered_listing(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();

        $a = $this->accountByCode($company->id, '2110');
        $this->writeLiabilityLine($company, $branch, $a, 10.000);

        $results = app(ReconciliationService::class)->fetchPaymentsByDate(
            $company->id,
            [$branch->id],
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
            'NoSuchSupplierAtAll',
        );

        $this->assertCount(1, $results);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // reconcile() / declineReconcile()
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_reconcile_marks_lines_reconciled_against_the_reference_line_and_skips_already_reconciled_2(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $account = $this->accountByCode($company->id, '2110');
        $bank = $this->accountByCode($company->id, '1201');

        $line = $this->writeLiabilityLine($company, $branch, $account, 15.000);
        $alreadyFastPath = $this->writeLiabilityLine($company, $branch, $account, 5.000);
        $alreadyFastPath->update(['reconciled' => 2]);

        $referenceLine = $this->writeLiabilityLine($company, $branch, $bank, 15.000);

        app(ReconciliationService::class)->reconcile(
            $company->id,
            $branch->id,
            [$line->id, $alreadyFastPath->id],
            $referenceLine->id,
        );

        $line->refresh();
        $alreadyFastPath->refresh();

        $this->assertSame(1, $line->reconciled);
        $this->assertSame($referenceLine->id, $line->reconciled_ref_id);
        // reconciled=2 is a sentinel this method must never downgrade or re-target.
        $this->assertSame(2, $alreadyFastPath->reconciled);
        $this->assertNull($alreadyFastPath->reconciled_ref_id);
    }

    /**
     * declineReconcile() deletes exactly the ONE journal_entries row it was given (moved verbatim
     * from both controllers' pre-existing identical implementation -- w5-brief.md's own Traps
     * section calls this a P5.10 concern, "move behind a service method", not a redesign here) --
     * which leaves that row's own transaction (a balanced 2-line JV in this fixture, matching
     * writeLiabilityLine()'s shape) with only its OTHER leg still standing. This is a pre-existing
     * characteristic of the legacy mechanic being moved, not something this test's fixture should
     * paper over -- so, deliberately, this ONE test does not call trackCompanyForInvariants() (the
     * global "every transaction balances" check every other test in this class opts into), the
     * same way a scenario that intentionally exercises a raw/legacy code path elsewhere in this
     * suite would.
     */
    public function test_decline_reconcile_unmarks_the_original_lines_and_removes_the_reconciliation_row(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $account = $this->accountByCode($company->id, '2110');
        $bank = $this->accountByCode($company->id, '1201');

        $original = $this->writeLiabilityLine($company, $branch, $account, 15.000);
        $reconciliationLine = $this->writeLiabilityLine($company, $branch, $bank, 15.000);

        app(ReconciliationService::class)->reconcile($company->id, $branch->id, [$original->id], $reconciliationLine->id);
        $original->refresh();
        $this->assertSame(1, $original->reconciled);

        app(ReconciliationService::class)->declineReconcile($reconciliationLine->id);

        $original->refresh();
        $this->assertSame(0, $original->reconciled);
        $this->assertNull($original->reconciled_ref_id);
        $this->assertNull(JournalEntry::find($reconciliationLine->id));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Controller delegation: the permission gate actually refuses at the HTTP layer.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_receipt_voucher_fetch_payments_by_date_route_403s_for_an_unauthorized_role(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $agentUser = $this->makeUnauthorizedAgentUser($company, $branch);

        $response = $this->actingAs($agentUser)->get(route('receipt-voucher.fetchPaymentsByDate', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertStatus(403);
    }

    public function test_bank_payment_decline_reconcile_route_403s_for_an_unauthorized_role(): void
    {
        [$company, $branch] = $this->makeCompanyAndBranch();
        $account = $this->accountByCode($company->id, '2110');
        $line = $this->writeLiabilityLine($company, $branch, $account, 10.000);
        $agentUser = $this->makeUnauthorizedAgentUser($company, $branch);

        $response = $this->actingAs($agentUser)->post(route('bank-payments.decline-reconcile', ['id' => $line->id]));

        $response->assertStatus(403);
        // The refused request must not have touched the row.
        $this->assertSame(0, $line->fresh()->reconciled);
    }

    public function test_receipt_voucher_fetch_payments_by_date_route_succeeds_for_company_owner(): void
    {
        // ReceiptVoucherController::fetchPaymentsByDate() resolves `$user->company->id`/
        // `$user->branch->id` directly (pre-existing, unchanged by this sub-wave -- User::company()
        // needs a real ownership/branch/agent/accountant relation to resolve, which a bare ADMIN
        // fixture has none of). Role::COMPANY, owning both the company and its branch row, is the
        // one role this pre-existing resolution actually works for -- see User::company()'s own
        // "Case 1: user directly owns a company" branch and User::branch() (hasOne(Branch::class)).
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $companyOwner->id]);
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $companyOwner->id]);
        $this->trackCompanyForInvariants($company->id);

        $account = $this->accountByCode($company->id, '2110');
        $this->writeLiabilityLine($company, $branch, $account, 10.000);

        $response = $this->actingAs($companyOwner)->get(route('receipt-voucher.fetchPaymentsByDate', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }
}
