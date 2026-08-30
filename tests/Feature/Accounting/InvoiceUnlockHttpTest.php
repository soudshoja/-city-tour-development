<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Transaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.E (p2_5-brief.md §P2.5.E): the HTTP surface — GET invoice.unlock-blockers and
 * POST invoice.unlock, on the PRE-EXISTING InvoiceController (HF-8's lockInvoice/unlockInvoice),
 * upgraded in place rather than duplicated behind a second controller/route (see
 * InvoiceController::unlockInvoice()'s own docblock for why). See
 * {@see InvoiceUnlockDependencyTest} for the dependency-chain logic itself; this suite proves the
 * ROUTE/permission-layering/response-shape contract: "the JSON refusal response carries the same
 * blockers[] so the API/UI stay in sync," and that the pre-existing `manageLocks` gate + tenant
 * check are UNCHANGED while `accounting.record.unlock` is layered underneath them.
 *
 * Fixtures use {@see CreatesTenantFixtures} (the SAME trait
 * tests/Feature/Security/InvoiceLockTenantIsolationTest.php uses for these exact routes) rather
 * than hand-rolled Company/Branch/Agent wiring, so `getCompanyId()`/`Gate::authorize('manageLocks',
 * ...)` resolve through the identical, already-proven path — including that trait's own documented
 * workaround for the pre-existing `show` route-shadow bug on 2-segment GET paths under `/invoice/`
 * (this wave's OWN new `invoice.unlock-blockers` route is registered ahead of that shadow in
 * routes/web.php specifically so it does NOT need the same workaround; see that registration's own
 * comment).
 */
class InvoiceUnlockHttpTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private const MANAGE_LOCKS = ['manage locks'];

    private function invoiceFor(array $tenant): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'is_locked' => true,
            'locked_at' => now(),
        ]);
    }

    public function test_unlock_blockers_endpoint_requires_manage_locks_permission(): void
    {
        $tenant = $this->createTenant(); // no 'manage locks'
        $invoice = $this->invoiceFor($tenant);

        $this->actingAs($tenant['user'])
            ->getJson(route('invoice.unlock-blockers', ['invoice' => $invoice->id]))
            ->assertStatus(403);
    }

    public function test_unlock_blockers_endpoint_rejects_cross_tenant_invoice(): void
    {
        $tenantA = $this->createTenant(self::MANAGE_LOCKS);
        $tenantB = $this->createTenant();
        $foreignInvoice = $this->invoiceFor($tenantB);

        $this->actingAs($tenantA['user'])
            ->getJson(route('invoice.unlock-blockers', ['invoice' => $foreignInvoice->id]))
            ->assertStatus(403);
    }

    public function test_unlock_blockers_endpoint_returns_empty_for_a_clear_invoice(): void
    {
        $tenant = $this->createTenant(self::MANAGE_LOCKS);
        $invoice = $this->invoiceFor($tenant);

        $this->actingAs($tenant['user'])
            ->getJson(route('invoice.unlock-blockers', ['invoice' => $invoice->id]))
            ->assertStatus(200)
            ->assertJson(['success' => true, 'is_locked' => true, 'blockers' => []]);
    }

    public function test_unlock_endpoint_requires_reason(): void
    {
        $tenant = $this->createTenant(self::MANAGE_LOCKS);
        $invoice = $this->invoiceFor($tenant);

        $this->actingAs($tenant['user'])
            ->postJson(route('invoice.unlock', ['invoice' => $invoice->id]), [])
            ->assertStatus(422);
    }

    public function test_unlock_endpoint_succeeds_for_a_clear_invoice(): void
    {
        $tenant = $this->createTenant(self::MANAGE_LOCKS);
        $invoice = $this->invoiceFor($tenant);
        $this->trackCompanyForInvariants($tenant['company']->id);

        $this->actingAs($tenant['user'])
            ->postJson(route('invoice.unlock', ['invoice' => $invoice->id]), [
                'reason' => 'data entry correction, caught before any payment',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertFalse($invoice->fresh()->is_locked);
    }

    public function test_unlock_endpoint_returns_409_with_blockers_when_dependency_chain_is_not_clear(): void
    {
        $tenant = $this->createTenant(self::MANAGE_LOCKS);
        $this->trackCompanyForInvariants($tenant['company']->id);
        $invoice = $this->invoiceFor($tenant);
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

        $response = $this->actingAs($tenant['user'])
            ->postJson(route('invoice.unlock', ['invoice' => $invoice->id]), ['reason' => 'attempted anyway']);

        $response->assertStatus(409)->assertJson(['success' => false]);
        $blockers = $response->json('blockers');
        $this->assertNotEmpty($blockers);
        $this->assertContains('reconciled_line', array_column($blockers, 'type'));

        // The GET blockers endpoint returns the identical shape (API/UI stay in sync).
        $getResponse = $this->getJson(route('invoice.unlock-blockers', ['invoice' => $invoice->id]));
        $getResponse->assertStatus(200);
        $this->assertSame($blockers, $getResponse->json('blockers'));

        $this->assertTrue($invoice->fresh()->is_locked);
    }

    // ── The new permission layer, underneath the pre-existing manageLocks gate ────────────────

    /**
     * A user with ONLY the pre-existing `manage locks` Spatie permission (not admin/accountant/
     * company tier, not `accounting.record.unlock`) is exactly the population this sub-wave's
     * "layered underneath" tightening targets (InvoiceController::unlockInvoice()'s own docblock,
     * point 3): they could unlock an invoice before P2.5.E and can no longer, until also granted
     * the new permission. Asserted directly against Lockable::assertUnlockAuthorized() -- the same
     * check InvoiceController::unlockInvoice() runs via Invoice::unlock() -- because
     * createAgentUserFor()'s Role::AGENT tier is exactly the "not admin/accountant/company" case
     * the model-level check itself is keyed on; proving it once here (rather than duplicating the
     * full permission matrix already covered in InvoiceUnlockDependencyTest) is enough to pin the
     * HTTP-relevant claim: manageLocks passing is NOT sufficient on its own.
     */
    public function test_manage_locks_alone_is_no_longer_sufficient_to_unlock(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createAgentUserFor($tenant, self::MANAGE_LOCKS);

        $this->assertTrue($user->can('manage locks'));
        $this->assertFalse($user->can('accounting.record.unlock'));

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        (new Invoice)->assertUnlockAuthorized($user);
    }

    public function test_unlock_via_the_explicit_new_permission_for_a_non_privileged_role(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->invoiceFor($tenant);

        Permission::firstOrCreate(['name' => 'accounting.record.unlock', 'guard_name' => 'web']);
        $user = $this->createAgentUserFor($tenant, [...self::MANAGE_LOCKS, 'accounting.record.unlock']);

        $this->actingAs($user)
            ->postJson(route('invoice.unlock', ['invoice' => $invoice->id]), [
                'reason' => 'agent-specific correction',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
