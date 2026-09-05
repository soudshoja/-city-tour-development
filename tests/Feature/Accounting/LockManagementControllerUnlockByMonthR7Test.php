<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Transaction;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * R7 (P2-EXIT-REPORT.md §7 residual register; `p2_5/p25e-verify.md` finding 1):
 * {@see \App\Http\Controllers\LockManagementController::unlockByMonth()} used to call
 * {@see \App\Http\Traits\Lockable::bulkUnlock()} directly -- a raw `is_locked=false` mass-update
 * with NO dependency-chain check and NO `accounting.record.unlock` permission check, gated only
 * by the pre-existing `manageLocks` ability. That routed around every gate P2.5.E built
 * ({@see InvoiceUnlockHttpTest}) for every invoice locked in the chosen month, in one click,
 * including invoices whose receipts are bank-reconciled. This suite proves the fix: `unlockByMonth`
 * now walks each matching record through the SAME per-record `Lockable::unlock()` path the
 * single-record `invoice.unlock` action uses.
 */
class LockManagementControllerUnlockByMonthR7Test extends AccountingTestCase
{
    use CreatesTenantFixtures {
        createTenant as private createTenantFixture;
    }
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private const MANAGE_LOCKS = ['manage locks'];

    /**
     * B7a: CreatesTenantFixtures::createTenant() is shared with the Security suite, whose
     * fixtures resolve against modules that default ON -- see that trait's own docblock. This
     * suite hits `module:accounting`-gated routes, which now fail CLOSED, so the tenant built
     * here needs the grant every time; overriding (not editing) the shared trait method keeps
     * that grant local to this file.
     *
     * @return array{user: User, company: Company, branch: Branch, agent: Agent, client: Client}
     */
    private function createTenant(array $permissions = []): array
    {
        $tenant = $this->createTenantFixture($permissions);
        $this->grantAccountingModule($tenant['company']);

        return $tenant;
    }

    private function lockedInvoiceFor(array $tenant, string $invoiceDate): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'is_locked' => true,
            'locked_at' => now(),
            'invoice_date' => $invoiceDate,
        ]);
    }

    /**
     * Same fixture shape as {@see InvoiceUnlockHttpTest::test_unlock_endpoint_returns_409_with_blockers_when_dependency_chain_is_not_clear()}
     * -- a reconciled receipt line makes this invoice's dependency chain not clear.
     */
    private function makeReconciledDependency(array $tenant, Invoice $invoice): void
    {
        $branch = $tenant['branch'];
        $company = $tenant['company'];
        $client = $tenant['client'];
        $agent = $tenant['agent'];

        $account = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'created_by' => $agent->user_id,
        ]);
        $paymentTxn = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'payment_id' => $payment->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'reference_type' => 'Payment',
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'fixture payment transaction',
            'transaction_date' => now(),
            'posting_date' => now(),
        ]);

        JournalEntry::create([
            'transaction_id' => $paymentTxn->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'transaction_date' => now(),
            'posting_date' => now(),
            'name' => $account->name,
            'description' => 'fixture receipt line',
            'debit' => 0,
            'credit' => 100,
            'reconciled' => 1,
        ]);

        $contraAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        JournalEntry::create([
            'transaction_id' => $paymentTxn->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $contraAccount->id,
            'transaction_date' => now(),
            'posting_date' => now(),
            'name' => $contraAccount->name,
            'description' => 'fixture receipt line (contra)',
            'debit' => 100,
            'credit' => 0,
        ]);

        PaymentApplication::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'applied_at' => now(),
        ]);
    }

    public function test_unlock_by_month_unlocks_clear_invoices_and_leaves_blocked_ones_locked(): void
    {
        $tenant = $this->createTenant(self::MANAGE_LOCKS);
        $this->trackCompanyForInvariants($tenant['company']->id);

        $clearInvoice = $this->lockedInvoiceFor($tenant, '2026-03-10');
        $blockedInvoice = $this->lockedInvoiceFor($tenant, '2026-03-20');
        $this->makeReconciledDependency($tenant, $blockedInvoice);

        $response = $this->actingAs($tenant['user'])->post(route('lock-management.unlock-by-month'), [
            'month' => '2026-03',
            'record_type' => 'invoices',
            'reason' => 'month-end review',
        ]);

        $response->assertRedirect();

        // The clear invoice is unlocked ...
        $this->assertFalse($clearInvoice->fresh()->is_locked, 'A clear invoice in the batch must still be unlocked.');
        // ... but the blocked one is NOT -- proving the dependency-chain gate now applies to the
        // bulk-by-month path, not just the single-record invoice.unlock action.
        $this->assertTrue($blockedInvoice->fresh()->is_locked, 'An invoice with an unresolved dependency (reconciled receipt) must stay locked.');

        $response->assertSessionHas('warning');
        $this->assertStringContainsString($blockedInvoice->invoice_number, session('warning'));
    }

    public function test_unlock_by_month_refuses_a_caller_without_the_unlock_authorization_tier(): void
    {
        $tenant = $this->createTenant();
        // Grants ONLY the pre-existing 'manage locks' permission this action already gated on --
        // exactly the population the pre-fix bug let bulk-unlock every invoice in the month
        // regardless of the P2.5.E accounting.record.unlock/role-tier requirement.
        $user = $this->createAgentUserFor($tenant, self::MANAGE_LOCKS);
        $this->assertTrue($user->can('manage locks'));
        $this->assertFalse($user->can('accounting.record.unlock'));

        $invoice = $this->lockedInvoiceFor($tenant, '2026-04-05');

        $response = $this->actingAs($user)->post(route('lock-management.unlock-by-month'), [
            'month' => '2026-04',
            'record_type' => 'invoices',
            'reason' => 'attempted anyway',
        ]);

        $response->assertStatus(403);
        $this->assertTrue($invoice->fresh()->is_locked, 'No invoice may be unlocked when the caller lacks the unlock-authorization tier, even with manageLocks alone.');
    }

    public function test_unlock_by_month_succeeds_via_the_explicit_new_permission_for_a_non_privileged_role(): void
    {
        $tenant = $this->createTenant();
        Permission::firstOrCreate(['name' => 'accounting.record.unlock', 'guard_name' => 'web']);
        $user = $this->createAgentUserFor($tenant, [...self::MANAGE_LOCKS, 'accounting.record.unlock']);

        $invoice = $this->lockedInvoiceFor($tenant, '2026-05-12');
        $this->trackCompanyForInvariants($tenant['company']->id);

        $response = $this->actingAs($user)->post(route('lock-management.unlock-by-month'), [
            'month' => '2026-05',
            'record_type' => 'invoices',
            'reason' => 'agent-specific correction',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertFalse($invoice->fresh()->is_locked);
    }
}
