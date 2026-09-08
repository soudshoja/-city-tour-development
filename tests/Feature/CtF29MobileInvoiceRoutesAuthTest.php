<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CT-F29: routes/api.php registered MobileController's invoice/transaction
 * routes (including `DELETE /invoice/delete/{id}`) outside of any auth
 * middleware. The handlers delete ledger rows (Transaction, JournalEntry)
 * scoped only by `invoice_id`, so anyone who could reach the endpoint could
 * erase another company's accounting history.
 *
 * The fix moves those routes into an `auth:sanctum` group (mirroring the
 * only existing precedent for protecting a route in this file, the
 * payments/{id}/* group) and adds a company-ownership check in
 * MobileController::deleteInvoice()/updateInvoice() before any ledger row
 * is touched.
 *
 * This test covers the delete path end to end:
 *   (a) unauthenticated request -> rejected, nothing deleted
 *   (b) authenticated request from a *different* company -> rejected (403),
 *       nothing deleted
 *   (c) authenticated request from the *owning* company -> succeeds,
 *       existing behaviour preserved
 */
class CtF29MobileInvoiceRoutesAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a fully-linked company -> branch -> agent -> invoice chain, plus
     * one Transaction and one JournalEntry row pointing at the invoice, the
     * same shape MobileController itself writes (see updateInvoice()).
     *
     * @return array{owner: User, company: Company, invoice: Invoice, transaction: Transaction, journalEntry: JournalEntry}
     */
    private function makeInvoiceWithLedgerRows(): array
    {
        $owner = User::factory()->create();

        $company = Company::factory()->create([
            'user_id' => $owner->id,
        ]);

        $branch = Branch::factory()->create([
            'user_id' => $owner->id,
            'company_id' => $company->id,
        ]);

        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'CT-F29 test agent type']);

        $agent = Agent::factory()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
        ]);

        $client = Client::factory()->create([
            'agent_id' => $agent->id,
        ]);

        $invoice = Invoice::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
        ]);

        $account = Account::create([
            'name' => 'CT-F29 test account',
            'level' => 1,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $company->id,
        ]);

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 100,
            'transaction_date' => now(),
            'description' => 'CT-F29 test transaction',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
        ]);

        $journalEntry = JournalEntry::create([
            'name' => 'CT-F29 test entry',
            'company_id' => $company->id,
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'transaction_date' => now(),
            'description' => 'CT-F29 test journal entry',
            'debit' => 100,
            'credit' => 0,
            'balance' => 100,
            'type' => 'payable',
        ]);

        return compact('owner', 'company', 'invoice', 'transaction', 'journalEntry');
    }

    private function assertLedgerRowsIntact(Transaction $transaction, JournalEntry $journalEntry): void
    {
        $this->assertNull($transaction->fresh()->deleted_at, 'Transaction row must not be deleted.');
        $this->assertNull($journalEntry->fresh()->deleted_at, 'JournalEntry row must not be deleted.');
    }

    public function test_unauthenticated_delete_is_rejected_and_nothing_is_deleted(): void
    {
        $fixture = $this->makeInvoiceWithLedgerRows();

        $response = $this->deleteJson("/api/invoice/delete/{$fixture['invoice']->id}");

        $this->assertContains($response->getStatusCode(), [401, 403]);
        $this->assertNotSoftDeleted('invoices', ['id' => $fixture['invoice']->id]);
        $this->assertLedgerRowsIntact($fixture['transaction'], $fixture['journalEntry']);
    }

    public function test_authenticated_user_from_another_company_cannot_delete_invoice(): void
    {
        $fixture = $this->makeInvoiceWithLedgerRows();

        // A second, unrelated company/user -- no agent/branch relationship
        // to the invoice under test at all.
        $otherOwner = User::factory()->create();
        Company::factory()->create([
            'user_id' => $otherOwner->id,
        ]);

        Sanctum::actingAs($otherOwner);

        $response = $this->deleteJson("/api/invoice/delete/{$fixture['invoice']->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('invoices', ['id' => $fixture['invoice']->id]);
        $this->assertLedgerRowsIntact($fixture['transaction'], $fixture['journalEntry']);
    }

    public function test_authenticated_user_from_the_owning_company_can_delete_invoice(): void
    {
        $fixture = $this->makeInvoiceWithLedgerRows();

        Sanctum::actingAs($fixture['owner']);

        $response = $this->deleteJson("/api/invoice/delete/{$fixture['invoice']->id}");

        // Existing (pre-CT-F29) behaviour: deleteInvoice() redirects rather
        // than returning JSON. We only assert it is not an auth failure and
        // that the deletion actually happened.
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());

        $this->assertSoftDeleted('invoices', ['id' => $fixture['invoice']->id]);
        $this->assertSoftDeleted('transactions', ['id' => $fixture['transaction']->id]);
        $this->assertSoftDeleted('journal_entries', ['id' => $fixture['journalEntry']->id]);
    }

    public function test_invoice_create_route_now_requires_authentication(): void
    {
        // Sanity check on the route-level part of the fix: any MobileController
        // invoice/transaction route moved into the auth:sanctum group must
        // reject unauthenticated callers, not just deleteInvoice().
        $response = $this->getJson('/api/invoice/create');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }
}
