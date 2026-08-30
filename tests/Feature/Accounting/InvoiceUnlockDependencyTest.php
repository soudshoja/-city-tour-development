<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\UnlockDependencyBlockedException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.E (p2_5-brief.md §P2.5.E; period-lock-design.md §8.2's dependency-aware unlock; owner
 * refinement 2026-08-30). Exercises {@see \App\Http\Traits\Lockable::unlock()} /
 * {@see \App\Models\Invoice::unlockBlockers()} (delegating to
 * {@see \App\Services\Accounting\UnlockDependencyResolver}) directly against real rows -- no HTTP
 * layer here (that is {@see InvoiceUnlockHttpTest}).
 *
 * Every fixture is a real, hand-built row via ::create() (matching this suite's own established
 * convention, e.g. AgentSettlementServiceW5STest -- the model factories for Transaction/
 * JournalEntry in database/factories are stale/unrelated shapes and are not used here).
 */
class InvoiceUnlockDependencyTest extends AccountingTestCase
{
    /**
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client}
     */
    private function makeTenant(): array
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return [$company, $branch, $agent, $client];
    }

    private function makeInvoice(Agent $agent, Client $client, ?\DateTimeInterface $invoiceDate = null): Invoice
    {
        return Invoice::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_date' => $invoiceDate ?? now(),
            'is_locked' => true,
            'locked_at' => now(),
        ]);
    }

    private function makeTransactionForInvoice(Company $company, Branch $branch, Invoice $invoice, ?\DateTimeInterface $date = null): Transaction
    {
        return Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'entity_id' => $invoice->client_id,
            'entity_type' => 'client',
            'reference_type' => 'Invoice',
            'transaction_type' => 'debit',
            'amount' => 100,
            'description' => 'fixture invoice transaction',
            'transaction_date' => $date ?? $invoice->invoice_date,
            'posting_date' => $date ?? $invoice->invoice_date,
        ]);
    }

    private function makePaymentTransaction(Company $company, Branch $branch, Payment $payment, ?\DateTimeInterface $date = null): Transaction
    {
        return Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'payment_id' => $payment->id,
            'entity_id' => $payment->client_id,
            'entity_type' => 'client',
            'reference_type' => 'Payment',
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'fixture payment transaction',
            'transaction_date' => $date ?? now(),
            'posting_date' => $date ?? now(),
        ]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    private function unprivilegedUser(): User
    {
        return User::factory()->create(['role_id' => Role::AGENT]);
    }

    // ── Clear chain: unlock succeeds ────────────────────────────────────────────────────────────

    public function test_invoice_with_no_downstream_activity_has_no_blockers_and_unlocks(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);

        $this->assertSame([], $invoice->unlockBlockers());

        Log::spy();
        $admin = $this->adminUser();

        $invoice->unlock('data entry correction, caught before any payment', $admin->id);

        $this->assertFalse($invoice->fresh()->is_locked);

        // With() BEFORE once(): Lockable::applyCascade() also logs 'Lock cascade applied' at
        // 'info' for each of Invoice's two cascade targets (Transaction, JournalEntry) — three
        // 'info' calls happen in total, but only one matches this specific message/context, and
        // with()-before-once() is what scopes the count to the matching subset (once()-before-with()
        // would assert on ALL 'info' calls regardless of arguments).
        Log::shouldHaveReceived('info')->with(
            'accounting.record_unlocked',
            Mockery::on(fn (array $ctx) => $ctx['subject_type'] === Invoice::class
                && $ctx['subject_id'] === $invoice->id
                && $ctx['actor_id'] === $admin->id
                && $ctx['reason'] === 'data entry correction, caught before any payment')
        )->once();
    }

    // ── Permission + reason gates ───────────────────────────────────────────────────────────────

    public function test_unlock_refuses_without_the_permission(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);
        $user = $this->unprivilegedUser();

        $this->expectException(AuthorizationException::class);

        $invoice->unlock('any reason', $user->id);
    }

    public function test_unlock_passes_permission_via_explicit_ability_for_a_non_privileged_role(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);

        Permission::firstOrCreate(['name' => 'accounting.record.unlock', 'guard_name' => 'web']);
        $user = $this->unprivilegedUser();
        $user->givePermissionTo('accounting.record.unlock');

        $invoice->unlock('agent-specific correction', $user->id);

        $this->assertFalse($invoice->fresh()->is_locked);
    }

    public function test_unlock_refuses_with_no_reason(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);
        $admin = $this->adminUser();

        $this->expectException(\InvalidArgumentException::class);

        $invoice->unlock(null, $admin->id);
    }

    public function test_unlock_refuses_with_blank_reason(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);
        $admin = $this->adminUser();

        $this->expectException(\InvalidArgumentException::class);

        $invoice->unlock('   ', $admin->id);
    }

    // ── Chain link: period ──────────────────────────────────────────────────────────────────────

    public function test_own_closed_period_blocks_unlock(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client, \Illuminate\Support\Carbon::create(2026, 3, 15));
        $this->makeTransactionForInvoice($company, $branch, $invoice);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);

        $blockers = $invoice->unlockBlockers();

        $this->assertNotEmpty(array_filter($blockers, fn ($b) => $b['type'] === 'period' && $b['status'] === 'period_closed'));

        $admin = $this->adminUser();
        $this->expectException(UnlockDependencyBlockedException::class);
        $invoice->unlock('should be blocked', $admin->id);
    }

    // ── Chain link: reversal ────────────────────────────────────────────────────────────────────

    public function test_existing_reversal_document_blocks_unlock(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);
        $original = $this->makeTransactionForInvoice($company, $branch, $invoice);

        // reversal_of_transaction_id is deliberately NOT in Transaction::$fillable (the engine
        // itself writes it via a raw ->update(), never mass-assignment -- see PostingService's own
        // reverse() docblock) -- set it directly after create() rather than in the create() array.
        $reversal = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $invoice->client_id,
            'entity_type' => 'client',
            'reference_type' => 'Invoice',
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'fixture reversal',
            'transaction_date' => now(),
            'posting_date' => now(),
        ]);
        $reversal->reversal_of_transaction_id = $original->id;
        $reversal->save();

        $blockers = $invoice->unlockBlockers();

        $this->assertNotEmpty(array_filter($blockers, fn ($b) => $b['type'] === 'reversal' && $b['status'] === 'posted'));

        $admin = $this->adminUser();
        $this->expectException(UnlockDependencyBlockedException::class);
        $invoice->unlock('should be blocked', $admin->id);
    }

    // ── Chain link: application -> receipt -> reconciled line (locked transaction) ────────────

    public function test_application_to_a_locked_payment_transaction_blocks_unlock(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);

        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'account_id' => Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id])->id,
            'created_by' => $agent->user_id,
        ]);
        $paymentTxn = $this->makePaymentTransaction($company, $branch, $payment);
        $paymentTxn->update(['is_locked' => true]);

        PaymentApplication::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'applied_at' => now(),
        ]);

        $blockers = $invoice->unlockBlockers();

        $types = array_column($blockers, 'type');
        $this->assertContains('application', $types);
        $this->assertContains('receipt', $types);
        $this->assertNotEmpty(array_filter($blockers, fn ($b) => $b['type'] === 'reconciled_line' && $b['status'] === 'locked'));

        $admin = $this->adminUser();
        $this->expectException(UnlockDependencyBlockedException::class);
        $invoice->unlock('should be blocked', $admin->id);
    }

    // ── Chain link: application -> receipt -> reconciled line (reconciled journal entry) ──────

    public function test_application_to_a_reconciled_payment_line_blocks_unlock(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);

        $account = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'created_by' => $agent->user_id,
        ]);
        $paymentTxn = $this->makePaymentTransaction($company, $branch, $payment);

        $line = JournalEntry::create([
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

        // Balanced (not one-sided) so the C1 trial-balance invariant tracked below still holds --
        // the contra leg's account identity is irrelevant to what's under test (the credit leg's
        // reconciled flag), only that a real posting is always two-sided.
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

        $blockers = $invoice->unlockBlockers();

        $reconciledLines = array_values(array_filter($blockers, fn ($b) => $b['type'] === 'reconciled_line' && $b['status'] === 'reconciled'));
        $this->assertNotEmpty($reconciledLines);
        $this->assertSame($line->id, $reconciledLines[0]['id']);

        $admin = $this->adminUser();
        $this->expectException(UnlockDependencyBlockedException::class);
        $invoice->unlock('should be blocked', $admin->id);
    }

    // ── Chain link: allocation (InvoicePartial-scoped application) ─────────────────────────────

    public function test_allocation_via_invoice_partial_application_blocks_unlock(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);

        $partial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'amount' => 50,
            'service_charge' => 0,
            'status' => 'unpaid',
            'type' => 'partial',
            'payment_gateway' => 'bank_transfer',
        ]);

        $account = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'created_by' => $agent->user_id,
        ]);
        $paymentTxn = $this->makePaymentTransaction($company, $branch, $payment);
        $paymentTxn->update(['is_locked' => true]);

        PaymentApplication::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => $partial->id,
            'amount' => 50,
            'applied_at' => now(),
        ]);

        $blockers = $invoice->unlockBlockers();
        $types = array_column($blockers, 'type');

        $this->assertContains('allocation', $types);

        $admin = $this->adminUser();
        $this->expectException(UnlockDependencyBlockedException::class);
        $invoice->unlock('should be blocked', $admin->id);
    }

    // ── Chain link: direct InvoiceReceipt ───────────────────────────────────────────────────────

    public function test_direct_invoice_receipt_with_reconciled_line_blocks_unlock(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client);

        $account = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $rvTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'reference_type' => 'Receipt',
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'fixture RV transaction',
            'transaction_date' => now(),
            'posting_date' => now(),
        ]);

        JournalEntry::create([
            'transaction_id' => $rvTransaction->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'transaction_date' => now(),
            'posting_date' => now(),
            'name' => $account->name,
            'description' => 'fixture RV line',
            'debit' => 0,
            'credit' => 100,
            'reconciled' => 2,
        ]);

        $contraAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        JournalEntry::create([
            'transaction_id' => $rvTransaction->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $contraAccount->id,
            'transaction_date' => now(),
            'posting_date' => now(),
            'name' => $contraAccount->name,
            'description' => 'fixture RV line (contra)',
            'debit' => 100,
            'credit' => 0,
        ]);

        InvoiceReceipt::create([
            'type' => 'invoice',
            'voucher_number' => 'RV-TEST-1',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now(),
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_id' => $rvTransaction->id,
            'amount' => 100,
            'status' => InvoiceReceipt::STATUS_APPROVED,
        ]);

        $blockers = $invoice->unlockBlockers();
        $types = array_column($blockers, 'type');

        $this->assertContains('receipt', $types);
        $this->assertNotEmpty(array_filter($blockers, fn ($b) => $b['type'] === 'reconciled_line' && $b['status'] === 'reconciled'));

        $admin = $this->adminUser();
        $this->expectException(UnlockDependencyBlockedException::class);
        $invoice->unlock('should be blocked', $admin->id);
    }

    // ── Blocked attempt is itself logged ────────────────────────────────────────────────────────

    public function test_blocked_unlock_attempt_is_logged(): void
    {
        [$company, $branch, $agent, $client] = $this->makeTenant();
        $invoice = $this->makeInvoice($agent, $client, \Illuminate\Support\Carbon::create(2026, 3, 15));
        $this->makeTransactionForInvoice($company, $branch, $invoice);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);

        Log::spy();
        $admin = $this->adminUser();

        try {
            $invoice->unlock('attempted anyway', $admin->id);
            $this->fail('Expected UnlockDependencyBlockedException.');
        } catch (UnlockDependencyBlockedException $e) {
            $this->assertNotEmpty($e->blockers);
        }

        Log::shouldHaveReceived('warning')->with(
            'accounting.record_unlock_blocked',
            Mockery::on(fn (array $ctx) => $ctx['subject_id'] === $invoice->id && $ctx['reason'] === 'attempted anyway')
        )->once();
    }
}
