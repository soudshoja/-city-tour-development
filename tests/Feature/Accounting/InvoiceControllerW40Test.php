<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\InvoiceController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\AccountingTestCase;

/**
 * KEY: invoice-w40. Covers the W4.0 sub-wave (InvoiceController residual raw writers from the
 * W3e gate's inventory — .planning/accounting-waves/w3/w3e-gate.md):
 *   (1) updateTaskPrice() — in-place ->save() mutation of live JournalEntry/Transaction rows
 *       replaced by reverse()+repost() via SaleDraftBuilder on the ON path; OFF path parity.
 *   (2) updatePaymentType()/updatePaymentGateway() — raw JE/Transaction ->delete() on RV
 *       transactions replaced by PostingService::reverse() on the ON path; OFF path parity.
 *   (3) addInvoiceChargeJournalEntries()/agentCommissionForInvoiceCharge() — raw
 *       JournalEntry::create() routed through PostingSeam with dedicated idempotency keys.
 *   (4) updateDetailsAmount() — missing checkLocked()/edit-after-issue gates added.
 *   (5) createProfitEntries()/createFeeLossEntries() repost mode — a derived doc is corrected
 *       (not left stale) after an amount edit on the ON path.
 */
class InvoiceControllerW40Test extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * AgentType::$fillable = ['name'] only ('id' is deliberately NOT mass-assignable) -- a plain
     * `AgentType::firstOrCreate(['id' => 2], [...])` therefore silently DROPS the 'id' on create
     * and lands on whatever the table's next AUTO_INCREMENT slot happens to be (1, 2, 3, ... —
     * whichever prior test in this SAME process already advanced it, since InnoDB's counter is
     * NOT rolled back by RefreshDatabase's per-test transaction). Every commission calculation in
     * this lane gates on `in_array($agent->type_id, [2, 3, 4])`, so a test that needs a REAL
     * commission-eligible type must not depend on that race -- this bypasses mass assignment via
     * a raw insert to force the exact id, isolated the same way as everything else in this
     * transaction-wrapped test.
     */
    private function ensureAgentType(int $id, string $name): AgentType
    {
        if (! AgentType::find($id)) {
            DB::table('agent_type')->insert(['id' => $id, 'name' => $name]);
        }

        return AgentType::findOrFail($id);
    }

    /**
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Supplier, 5: Task, 6: Invoice, 7: InvoiceDetail}
     */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        $agentUser = User::factory()->create();
        $agentType = $this->ensureAgentType(2, 'type-2');
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'commission' => 0.15,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
            'total' => 100.00,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_number' => 'INV-W40-'.uniqid(),
            'invoice_date' => now(),
            'amount' => 150.00,
            'sub_amount' => 150.00,
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'task_price' => 150.00,
            'supplier_price' => 100.00,
            'markup_price' => 50.00,
        ]);

        return [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail];
    }

    /**
     * A fixture WITHOUT any Task/InvoiceDetail, for the updatePaymentType()/updatePaymentGateway()
     * tests -- those two methods unconditionally call recalculateInvoiceCOA() at the end, which
     * (on the OFF path only, since the re-verify fix below) loops over $invoice->invoiceDetails
     * and, for an agent with no profit_account_id configured, posts a genuinely ONE-SIDED legacy
     * JournalEntry pair -- a real, pre-existing HEAD gap on the OFF path, unrelated to this lane.
     * makeFixture()'s own InvoiceDetail would trip that unrelated OFF-path gap and fail this test
     * file's own AccountingTestCase invariant check for a reason that has nothing to do with what
     * this lane actually changed, so the receipt-focused OFF-path tests use this leaner fixture
     * instead. On the ON path this no longer matters at all: recalculateInvoiceCOA() is now an
     * explicit, unconditional no-op whenever `PostingSeam::isEnabledFor($companyId)` is true (see
     * that method's own top-of-body comment, the W4.0 re-verify fix) -- it never reaches the
     * InvoiceDetail loop, the raw updateOrCreateEntryByAccount() writer, or deleteLossEntries()'s
     * description-LIKE hard delete regardless of what InvoiceDetail rows exist. See
     * test_recalculate_invoice_coa_is_noop_on_engine_on_path_even_with_mixed_legacy_rows() below,
     * which uses makeFixture() (WITH a real InvoiceDetail) specifically to prove that.
     *
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Invoice}
     */
    private function makeBareInvoiceFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        $agentUser = User::factory()->create();
        $agentType = $this->ensureAgentType(2, 'type-2');
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'commission' => 0.15,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_number' => 'INV-W40-BARE-'.uniqid(),
            'invoice_date' => now(),
            'amount' => 150.00,
            'sub_amount' => 150.00,
            'status' => 'unpaid',
        ]);

        return [$company, $branch, $agent, $client, $invoice];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function makeAdmin(Company $company): User
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        return $admin;
    }

    private function postEngineSale(
        Company $company,
        Branch $branch,
        Agent $agent,
        Client $client,
        Task $task,
        Invoice $invoice,
        InvoiceDetail $invoiceDetail,
        float $selling = 150.00
    ): Transaction {
        $headerTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $selling,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);

        $controller = app(InvoiceController::class);

        /** @var \App\Services\Accounting\PostedDocument $result */
        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $headerTransaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            $selling,
            $client->full_name,
        ]);

        return $result->transaction;
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (1) updateTaskPrice()
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_task_price_checklocked_blocks_before_any_mutation(): void
    {
        [$company, , $agent, , , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $invoice->lock();
        $invoice->refresh();

        $priceBefore = $invoiceDetail->fresh()->task_price;

        $this->expectException(AuthorizationException::class);

        try {
            app(InvoiceController::class)->updateTaskPrice(new Request([
                'task_id' => $task->id,
                'new_price' => 200.00,
            ]));
        } finally {
            $this->assertEquals($priceBefore, $invoiceDetail->fresh()->task_price, 'checkLocked() must block before task_price is mutated.');
        }
    }

    public function test_update_task_price_edit_after_issue_gate_blocks_non_privileged_user(): void
    {
        [$company, , , , , $task, $invoice, $invoiceDetail] = $this->makeFixture();

        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        session(['company_id' => $company->id]);
        $this->actingAs($agentUser);

        $priceBefore = $invoiceDetail->fresh()->task_price;

        $this->expectException(AuthorizationException::class);

        try {
            app(InvoiceController::class)->updateTaskPrice(new Request([
                'task_id' => $task->id,
                'new_price' => 200.00,
            ]));
        } finally {
            $this->assertEquals($priceBefore, $invoiceDetail->fresh()->task_price, 'Gate::authorize(edit-after-issue) must block before any mutation.');
        }
    }

    public function test_update_task_price_off_path_is_byte_identical_legacy_behaviour(): void
    {
        [$company, , $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        // Legacy fixture: two description-matched JournalEntry rows the OFF path mutates in place.
        $transaction = Transaction::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 150.00,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);
        $assetEntry = JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'account_id' => Account::where('company_id', $company->id)->where('name', 'Clients')->first()->id,
            'task_id' => $task->id,
            'transaction_date' => now(),
            'description' => 'Invoice created for (Assets): '.$client->full_name,
            'debit' => 150.00,
            'credit' => 0,
            'name' => 'Clients',
            'type' => 'receivable',
        ]);

        $response = app(InvoiceController::class)->updateTaskPrice(new Request([
            'task_id' => $task->id,
            'new_price' => 200.00,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false);

        // OFF path (HEAD, byte-identical): the SAME row is mutated in place, not reversed.
        $this->assertEquals(200.00, (float) $assetEntry->fresh()->debit, 'OFF path must still mutate the entry in place, unchanged.');
        $this->assertEquals(200.00, (float) $invoiceDetail->fresh()->task_price);
        $this->assertEquals(200.00, (float) $invoice->fresh()->amount);
    }

    public function test_update_task_price_on_path_reverses_and_reposts_via_sale_draft_builder(): void
    {
        [$company, $branch, $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $oldSaleTransaction = $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, 150.00);
        $this->assertSame('posted', $oldSaleTransaction->fresh()->posting_status);

        // Snapshot the ORIGINAL line amounts (id => [debit, credit]) BEFORE the edit -- proving
        // afterwards that reverse()+repost() never overwrote them in place, whatever their real
        // per-line split happens to be (SaleDraftBuilder's shape, not necessarily one 150 line
        // each).
        $originalLineSnapshot = JournalEntry::where('transaction_id', $oldSaleTransaction->id)
            ->get(['id', 'debit', 'credit'])
            ->mapWithKeys(fn ($line) => [$line->id => [(float) $line->debit, (float) $line->credit]]);
        $this->assertNotEmpty($originalLineSnapshot, 'Fixture sanity: the original sale document must have lines.');

        $response = app(InvoiceController::class)->updateTaskPrice(new Request([
            'task_id' => $task->id,
            'new_price' => 220.00,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false, 'updateTaskPrice() must succeed on the ON path: '.$response->getContent());

        $this->assertSame(
            'reversed',
            $oldSaleTransaction->fresh()->posting_status,
            'The old sale document must be REVERSED via PostingService::repost(), never mutated in place.'
        );
        $this->assertNull($oldSaleTransaction->fresh()->deleted_at);

        // The ORIGINAL journal entry rows on the old transaction must be byte-identical to the
        // snapshot taken BEFORE the edit -- never overwritten in place.
        $afterLines = JournalEntry::where('transaction_id', $oldSaleTransaction->id)->get(['id', 'debit', 'credit']);
        $this->assertCount($originalLineSnapshot->count(), $afterLines, 'No original line may be added/removed.');
        foreach ($afterLines as $line) {
            [$originalDebit, $originalCredit] = $originalLineSnapshot[$line->id];
            $this->assertSame($originalDebit, (float) $line->debit, "Line #{$line->id} debit must be unchanged.");
            $this->assertSame($originalCredit, (float) $line->credit, "Line #{$line->id} credit must be unchanged.");
        }

        // PostingService::repost() suffixes the replacement's idempotency key with
        // ':repost:{old->id}' whenever it collides with $old's own key (repost()'s own
        // documented, deliberate behaviour) -- matching InvoiceControllerW3eTest's own decoy
        // tests, the replacement is found structurally (invoice_id + posting_status), never by
        // expecting the exact unsuffixed key to survive.
        $replacement = Transaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('posting_status', 'posted')
            ->where('doc_type', 'INV')
            ->where('sub_type', 'SALE')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($replacement, 'A live replacement sale document must exist under the SAME structural key.');
        $this->assertNotEquals($oldSaleTransaction->id, $replacement->id);
        $this->assertStringStartsWith('invoice-detail:'.$invoiceDetail->id.':sale', (string) $replacement->idempotency_key);

        $newLines = JournalEntry::where('transaction_id', $replacement->id)->get();
        $this->assertGreaterThanOrEqual(2, $newLines->count());
        $this->assertEqualsWithDelta(
            $newLines->sum('debit'),
            $newLines->sum('credit'),
            0.001,
            'The replacement must be a genuinely balanced document.'
        );

        $this->assertEquals(220.00, (float) $invoiceDetail->fresh()->task_price);
        $this->assertEquals(220.00, (float) $invoice->fresh()->amount);
    }

    public function test_update_task_price_on_path_corrects_stale_commission_doc(): void
    {
        [$company, $branch, $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, 150.00);

        // Post the INITIAL commission doc directly (margin 50 * 0.15 = 7.5), matching what a
        // real invoice-issue flow would already have posted before this edit.
        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createProfitEntries', [
            null, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 50.0, 7.5,
        ]);

        $originalCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':agent-commission')
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($originalCommissionDoc, 'Fixture sanity: the initial commission doc must exist.');
        $this->assertEqualsWithDelta(7.5, (float) JournalEntry::where('transaction_id', $originalCommissionDoc->id)->where('debit', '>', 0)->sum('debit'), 0.01);

        // Now raise the task price -- margin becomes 150 (250 - 100), commission becomes 22.5.
        $response = app(InvoiceController::class)->updateTaskPrice(new Request([
            'task_id' => $task->id,
            'new_price' => 250.00,
        ]));
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false, (string) $response->getContent());

        $this->assertSame(
            'reversed',
            $originalCommissionDoc->fresh()->posting_status,
            'The stale commission doc (7.5) must be reversed by the repost-mode fix, not left stale.'
        );

        $newCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('posting_status', 'posted')
            ->where('id', '!=', $originalCommissionDoc->id)
            ->whereLike('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':agent-commission%')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($newCommissionDoc, 'A corrected, LIVE commission doc must exist after the amount edit.');
        $newCommissionAmount = (float) JournalEntry::where('transaction_id', $newCommissionDoc->id)->where('debit', '>', 0)->sum('debit');
        $this->assertEqualsWithDelta(22.5, $newCommissionAmount, 0.01, 'The corrected commission doc must reflect the NEW margin (150 * 0.15 = 22.5), not the stale 7.5.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (2) updatePaymentType() / updatePaymentGateway()
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @return array{0: InvoicePartial, 1: Transaction, 2: InvoiceReceipt}
     */
    private function makeUnpaidPartialWithReceipt(Company $company, Branch $branch, Invoice $invoice, Client $client): array
    {
        $partial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'amount' => 150.00,
            'service_charge' => 0,
            'status' => 'pending',
            'type' => 'full',
            'payment_gateway' => 'Cash',
        ]);

        $receiptTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 150.00,
            'description' => 'Cash receipt for '.$invoice->invoice_number,
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);

        // A real receipt is a genuinely BALANCED 2-line document (Dr cash / Cr Clients) --
        // PostingService::reverse() posts the swapped reversal through the SAME balance check
        // any other document goes through, so a single-line fixture would make the ON-path
        // reversal itself throw UnbalancedDocumentException.
        JournalEntry::create([
            'transaction_id' => $receiptTransaction->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'account_id' => Account::where('company_id', $company->id)->where('name', 'Clients')->first()->id,
            'transaction_date' => now(),
            'description' => 'Client payment received',
            'debit' => 0,
            'credit' => 150.00,
            'name' => 'Clients',
            'type' => 'receivable',
        ]);
        JournalEntry::create([
            'transaction_id' => $receiptTransaction->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'account_id' => Account::where('company_id', $company->id)->where('name', 'Petty Cash')->first()->id,
            'transaction_date' => now(),
            'description' => 'Cash received',
            'debit' => 150.00,
            'credit' => 0,
            'name' => 'Petty Cash',
            'type' => 'bank',
        ]);

        $receipt = InvoiceReceipt::create([
            'type' => 'invoice',
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => $partial->id,
            'transaction_id' => $receiptTransaction->id,
            'amount' => 150.00,
            'status' => 'pending',
            'is_used' => false,
        ]);

        return [$partial, $receiptTransaction, $receipt];
    }

    public function test_update_payment_type_off_path_still_raw_deletes_legacy(): void
    {
        [$company, $branch, $agent, $client, $invoice] = $this->makeBareInvoiceFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        [, $receiptTransaction] = $this->makeUnpaidPartialWithReceipt($company, $branch, $invoice, $client);

        app(InvoiceController::class)->updatePaymentType(new Request(['invoice_id' => $invoice->id]));

        // Transaction uses SoftDeletes -- HEAD's own raw ->delete() call was ALWAYS a soft delete,
        // never a hard one. The default (scoped) find() honours that scope, matching a real
        // caller's view of "this receipt is gone" the same way HEAD always behaved.
        $this->assertNull(Transaction::find($receiptTransaction->id), 'OFF path must still soft-delete the receipt Transaction, unchanged.');
        $this->assertNotNull(Transaction::withoutGlobalScopes()->find($receiptTransaction->id)?->deleted_at);
        $this->assertSame(0, JournalEntry::where('transaction_id', $receiptTransaction->id)->count());
    }

    public function test_update_payment_type_on_path_reverses_instead_of_deleting(): void
    {
        [$company, $branch, $agent, $client, $invoice] = $this->makeBareInvoiceFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        [, $receiptTransaction] = $this->makeUnpaidPartialWithReceipt($company, $branch, $invoice, $client);

        app(InvoiceController::class)->updatePaymentType(new Request(['invoice_id' => $invoice->id]));

        $fresh = Transaction::withoutGlobalScopes()->find($receiptTransaction->id);
        $this->assertNotNull($fresh, 'ON path must NEVER hard-delete the live receipt Transaction.');
        $this->assertSame('reversed', $fresh->posting_status, 'The receipt document must be reverse()d, structurally.');
        $this->assertNull($fresh->deleted_at);

        // The original lines must be untouched (not overwritten by a raw delete/recreate).
        $originalReceivableLine = JournalEntry::where('transaction_id', $receiptTransaction->id)->where('type', 'receivable')->first();
        $this->assertNotNull($originalReceivableLine);
        $this->assertEquals(150.00, (float) $originalReceivableLine->credit);

        // A reversal document must exist, swapping the sides.
        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $receiptTransaction->id)->first();
        $this->assertNotNull($reversal, 'A real reversal document must be posted.');
    }

    public function test_update_payment_gateway_on_path_reverses_instead_of_deleting(): void
    {
        [$company, $branch, $agent, $client, $invoice] = $this->makeBareInvoiceFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        [, $receiptTransaction] = $this->makeUnpaidPartialWithReceipt($company, $branch, $invoice, $client);

        app(InvoiceController::class)->updatePaymentGateway(new Request([
            'invoiceId' => $invoice->id,
            'gateway' => 'Cash',
            'method' => null,
            'amount' => 150.00,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $fresh = Transaction::withoutGlobalScopes()->find($receiptTransaction->id);
        $this->assertNotNull($fresh, 'ON path must NEVER hard-delete the live receipt Transaction.');
        $this->assertSame('reversed', $fresh->posting_status);
    }

    /**
     * Verify-fix: the previous verify pass found no gate test for updatePaymentType()/
     * updatePaymentGateway() at all, even though the brief requires one per site ("a gate test
     * (403 unauthorized, refusal when is_locked)"). updatePaymentType()'s own checkLocked() call
     * sits INSIDE this method's `DB::transaction(...)` closure, itself inside a
     * `try { ... } catch (Exception $e)` -- AuthorizationException extends Exception, so the
     * throw from Lockable::canModify()'s own `Gate::authorize('manageLocks', ...)` (for a plain
     * role_id-only ADMIN fixture user with no Spatie 'manage locks' permission) is caught INSIDE
     * this method and surfaces as the method's own generic error redirect, not a raw exception to
     * the caller -- unlike updateTaskPrice()/updateDetailsAmount() where that same throw is
     * ordered outside any try/catch. The important assertion either way is that nothing below the
     * check ever executes -- the receipt Transaction is never touched.
     */
    public function test_update_payment_type_checklocked_blocks_before_any_mutation(): void
    {
        [$company, $branch, $agent, $client, $invoice] = $this->makeBareInvoiceFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        [, $receiptTransaction] = $this->makeUnpaidPartialWithReceipt($company, $branch, $invoice, $client);

        $invoice->lock();
        $invoice->refresh();

        app(InvoiceController::class)->updatePaymentType(new Request(['invoice_id' => $invoice->id]));

        $fresh = Transaction::withoutGlobalScopes()->find($receiptTransaction->id);
        $this->assertNotNull($fresh, 'checkLocked() must block updatePaymentType() before the receipt Transaction is touched at all.');
        $this->assertSame('posted', $fresh->posting_status);
        $this->assertNull($fresh->deleted_at);
        $this->assertSame('unpaid', $invoice->fresh()->status, 'Invoice must be untouched when the lock gate blocks.');
    }

    /**
     * Verify-fix: same gap as above, for updatePaymentGateway(). Unlike updatePaymentType(),
     * this method's checkLocked() call is NOT wrapped in any try/catch, so the
     * AuthorizationException from Lockable::canModify() propagates directly to the caller --
     * the SAME observable shape as updateTaskPrice()'s/updateDetailsAmount()'s own gate tests.
     */
    public function test_update_payment_gateway_checklocked_blocks_before_any_mutation(): void
    {
        [$company, $branch, $agent, $client, $invoice] = $this->makeBareInvoiceFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        [, $receiptTransaction] = $this->makeUnpaidPartialWithReceipt($company, $branch, $invoice, $client);

        $invoice->lock();
        $invoice->refresh();

        $this->expectException(AuthorizationException::class);

        try {
            app(InvoiceController::class)->updatePaymentGateway(new Request([
                'invoiceId' => $invoice->id,
                'gateway' => 'Cash',
                'method' => null,
                'amount' => 150.00,
                'invoiceNumber' => $invoice->invoice_number,
            ]));
        } finally {
            $fresh = Transaction::withoutGlobalScopes()->find($receiptTransaction->id);
            $this->assertNotNull($fresh, 'checkLocked() must block updatePaymentGateway() before the receipt Transaction is touched at all.');
            $this->assertSame('posted', $fresh->posting_status);
            $this->assertNull($fresh->deleted_at);
        }
    }

    /**
     * Verify-fix: the previous verify pass found updatePaymentType() had an OFF-path parity test
     * (test_update_payment_type_off_path_still_raw_deletes_legacy above) but updatePaymentGateway()
     * had none -- only its ON-path and grep-assert tests existed. Mirrors that same test's shape.
     */
    public function test_update_payment_gateway_off_path_still_raw_deletes_legacy(): void
    {
        [$company, $branch, $agent, $client, $invoice] = $this->makeBareInvoiceFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        [, $receiptTransaction] = $this->makeUnpaidPartialWithReceipt($company, $branch, $invoice, $client);

        app(InvoiceController::class)->updatePaymentGateway(new Request([
            'invoiceId' => $invoice->id,
            'gateway' => 'Cash',
            'method' => null,
            'amount' => 150.00,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        // Transaction uses SoftDeletes -- HEAD's own raw ->delete() call was ALWAYS a soft delete.
        $this->assertNull(Transaction::find($receiptTransaction->id), 'OFF path must still soft-delete the receipt Transaction, unchanged.');
        $this->assertNotNull(Transaction::withoutGlobalScopes()->find($receiptTransaction->id)?->deleted_at);
        $this->assertSame(0, JournalEntry::where('transaction_id', $receiptTransaction->id)->count());
    }

    /**
     * Verify-fix: the previous verify pass found recalculateInvoiceCOA() (called unconditionally
     * from updatePaymentType()/updatePaymentGateway()/updatePartialGateway()/savePartial()/
     * changeCashToCredit()/updateLossBearer()) relied ONLY on a fragile internal heuristic --
     * "does this invoice have a transaction+JournalEntry pair with a NULL idempotency_key?" -- to
     * decide whether to run its raw, name-resolved updateOrCreateEntryByAccount()/
     * deleteLossEntries() writers, rather than an explicit isEnabledFor() check. That heuristic is
     * NOT authoritative: an engine-ON invoice can still carry a non-keyed row (e.g. a
     * header-anchor Transaction::create(), or a pre-cutover legacy invoice touched again after
     * cutover), which the previous test suite deliberately avoided ever constructing (the
     * makeBareInvoiceFixture() docblock said so explicitly).
     *
     * This test constructs EXACTLY that mixed scenario deliberately -- an engine-ON company, a
     * real InvoiceDetail with both a positive margin (profit path) and a negative one (loss path,
     * covered by the second test below), AND a non-idempotency-keyed legacy JournalEntry/
     * Transaction pair already linked to the invoice (the exact condition that used to make the
     * old heuristic proceed) -- and asserts recalculateInvoiceCOA() is a true, unconditional
     * no-op: zero new JournalEntry rows, the legacy row itself untouched, and the InvoiceDetail's
     * profit/commission columns left exactly as they were.
     */
    public function test_recalculate_invoice_coa_is_noop_on_engine_on_path_even_with_mixed_legacy_rows(): void
    {
        [$company, $branch, $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        // Deliberately NOT tracked via trackCompanyForInvariants(): the fixture below injects a
        // synthetic single-sided legacy JournalEntry row on purpose (mirroring
        // test_update_task_price_off_path_is_byte_identical_legacy_behaviour's own untracked
        // single-line fixture above) specifically to reproduce the "mixed keyless row" condition,
        // not a real balanced posting -- the invariant check is orthogonal to what this test
        // proves (that recalculateInvoiceCOA() itself adds nothing).

        // The exact "mixed" condition the previous verify pass identified: a NON-idempotency-keyed
        // transaction+JournalEntry pair linked to this invoice, on an otherwise engine-ON company.
        $legacyTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 150.00,
            'description' => 'Header-anchor row with no idempotency_key',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);
        $this->assertNull($legacyTransaction->idempotency_key, 'Fixture sanity: this row must be the exact keyless shape the old heuristic keyed off.');

        $legacyEntry = JournalEntry::create([
            'transaction_id' => $legacyTransaction->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'account_id' => Account::where('company_id', $company->id)->where('name', 'Clients')->first()->id,
            'task_id' => $task->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => 'Invoice created for (Assets): '.$client->full_name,
            'debit' => 150.00,
            'credit' => 0,
            'name' => 'Clients',
            'type' => 'receivable',
        ]);

        $journalEntryCountBefore = JournalEntry::count();
        $profitBefore = $invoiceDetail->fresh()->profit;
        $commissionBefore = $invoiceDetail->fresh()->commission;

        app(InvoiceController::class)->recalculateInvoiceCOA($invoice->fresh());

        $this->assertSame(
            $journalEntryCountBefore,
            JournalEntry::count(),
            'recalculateInvoiceCOA() must post ZERO new JournalEntry rows once the engine owns this company, regardless of any keyless row already on the invoice.'
        );
        $this->assertEquals(150.00, (float) $legacyEntry->fresh()->debit, 'The pre-existing keyless row must be left completely untouched, never mutated in place.');
        $this->assertEquals($profitBefore, $invoiceDetail->fresh()->profit, 'ON path must not recompute/overwrite InvoiceDetail.profit either.');
        $this->assertEquals($commissionBefore, $invoiceDetail->fresh()->commission, 'ON path must not recompute/overwrite InvoiceDetail.commission either.');

        // No profit/commission/loss JournalEntry rows of any kind exist anywhere for this detail.
        $this->assertSame(0, JournalEntry::where('invoice_detail_id', $invoiceDetail->id)
            ->where('id', '!=', $legacyEntry->id)
            ->count());
    }

    /**
     * Sibling to the test above, covering the LOSS branch (negative margin) -- the same mixed
     * legacy-row scenario, but with supplier_price > task_price so recalculateInvoiceCOA()'s
     * $isSupplierLoss branch (and its own deleteLossEntries()/updateOrCreateEntryByAccount() raw
     * writers) would have fired under the old heuristic.
     */
    public function test_recalculate_invoice_coa_is_noop_on_engine_on_path_for_loss_branch(): void
    {
        [$company, $branch, $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $invoiceDetail->update(['task_price' => 80.00, 'supplier_price' => 100.00, 'markup_price' => -20.00]);
        $this->enableEngine($company);
        // Deliberately NOT tracked -- see the sibling test's comment above.

        $legacyTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 80.00,
            'description' => 'Header-anchor row with no idempotency_key',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);
        JournalEntry::create([
            'transaction_id' => $legacyTransaction->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'account_id' => Account::where('company_id', $company->id)->where('name', 'Clients')->first()->id,
            'task_id' => $task->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => 'Invoice created for (Assets): '.$client->full_name,
            'debit' => 80.00,
            'credit' => 0,
            'name' => 'Clients',
            'type' => 'receivable',
        ]);

        $journalEntryCountBefore = JournalEntry::count();

        app(InvoiceController::class)->recalculateInvoiceCOA($invoice->fresh());

        $this->assertSame(
            $journalEntryCountBefore,
            JournalEntry::count(),
            'recalculateInvoiceCOA() must not post any supplier-loss JournalEntry rows on the ON path either.'
        );
    }

    /**
     * Structural grep-assert companion: the explicit isEnabledFor() gate must appear in
     * recalculateInvoiceCOA()'s body BEFORE any of the raw writers it guards.
     */
    public function test_recalculate_invoice_coa_has_explicit_engine_gate_before_raw_writers(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));

        $start = strpos($source, 'public function recalculateInvoiceCOA(Invoice $invoice): void');
        $end = strpos($source, 'private function updateOrCreateEntryByAccount(int $detailId');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        $body = substr($source, $start, $end - $start);

        $gatePos = strpos($body, 'app(PostingSeam::class)->isEnabledFor($companyId)');
        $this->assertNotFalse($gatePos, 'recalculateInvoiceCOA() must consult PostingSeam::isEnabledFor() explicitly, not rely solely on the idempotency_key heuristic.');

        $firstWriterPos = strpos($body, '$this->updateOrCreateEntryByAccount(');
        $this->assertNotFalse($firstWriterPos);
        $this->assertLessThan($firstWriterPos, $gatePos, 'The explicit engine gate must be checked, and return early, BEFORE the first raw-writer call site.');

        $firstDeletePos = strpos($body, '$this->deleteLossEntries(');
        $this->assertNotFalse($firstDeletePos);
        $this->assertLessThan($firstDeletePos, $gatePos, 'The explicit engine gate must sit before deleteLossEntries() too.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (3) addInvoiceChargeJournalEntries() / agentCommissionForInvoiceCharge()
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_add_invoice_charge_journal_entries_off_path_parity(): void
    {
        [$company, , $agent, $client, , , $invoice] = $this->makeFixture();
        $invoice->invoice_charge = 10.00;
        $invoice->is_client_credit = 0;
        $invoice->save();

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 10.00,
            'description' => 'Invoice charge anchor',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);

        $controller = app(InvoiceController::class);
        $result = $this->callPrivate($controller, 'addInvoiceChargeJournalEntries', [$invoice->fresh(['agent.branch', 'client']), $transaction]);

        $this->assertSame('success', $result['status'] ?? null, json_encode($result));

        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $entries, 'OFF path must still post the legacy 2-entry pair.');
    }

    public function test_add_invoice_charge_journal_entries_on_path_posts_via_seam_with_idempotency_key(): void
    {
        [$company, $branch, $agent, $client, , , $invoice] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $invoice->invoice_charge = 10.00;
        $invoice->is_client_credit = 0;
        $invoice->save();

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 10.00,
            'description' => 'Invoice charge anchor',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);

        $controller = app(InvoiceController::class);
        $result = $this->callPrivate($controller, 'addInvoiceChargeJournalEntries', [$invoice->fresh(['agent.branch', 'client']), $transaction]);

        $this->assertSame('success', $result['status'] ?? null, json_encode($result));

        $posted = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-charge:'.$invoice->id.':invoice_charge')
            ->where('posting_status', 'posted')
            ->first();

        $this->assertNotNull($posted, 'A real ON-path document must exist under the fee idempotency key.');
        $this->assertSame('INV', $posted->doc_type);
        $this->assertSame('FEE', $posted->sub_type);

        $lines = JournalEntry::where('transaction_id', $posted->id)->get();
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001, 'Must be a genuinely balanced document.');
        $this->assertEqualsWithDelta(10.00, (float) $lines->sum('credit'), 0.001);

        // Calling it AGAIN with the same invoice/charge must not double-post (idempotency key).
        $secondTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 10.00,
            'description' => 'second call anchor',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);
        $this->callPrivate($controller, 'addInvoiceChargeJournalEntries', [$invoice->fresh(['agent.branch', 'client']), $secondTransaction]);

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'invoice-charge:'.$invoice->id.':invoice_charge')->count(),
            'A second call for the SAME invoice charge must not double-post under the same key.'
        );
    }

    public function test_agent_commission_for_invoice_charge_on_path_posts_separate_jv_document(): void
    {
        [$company, , $agent, , , , $invoice] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $invoice->invoice_charge = 10.00;
        $invoice->save();

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 10.00,
            'description' => 'anchor',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);

        $controller = app(InvoiceController::class);
        $result = $this->callPrivate($controller, 'agentCommissionForInvoiceCharge', [$invoice->fresh(['agent.branch']), 10.00, 'Invoice charge']);

        $this->assertSame('success', $result['status'] ?? null, json_encode($result));

        $commissionDoc = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-charge:'.$invoice->id.':invoice_charge:commission')
            ->where('posting_status', 'posted')
            ->first();

        $this->assertNotNull($commissionDoc, 'A SEPARATE JV/AGENT_COMMISSION document must exist under its own key.');
        $this->assertSame('JV', $commissionDoc->doc_type);
        $this->assertSame('AGENT_COMMISSION', $commissionDoc->sub_type);
        $this->assertNotEquals(
            'invoice-charge:'.$invoice->id.':invoice_charge',
            $commissionDoc->idempotency_key,
            'The commission document must NEVER share the FEE document\'s own idempotency key.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (4) updateDetailsAmount() gates
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_details_amount_checklocked_blocks_before_any_mutation(): void
    {
        [$company, , $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $invoice->lock();
        $invoice->refresh();

        $priceBefore = $invoiceDetail->fresh()->task_price;

        // Invoice::canModify() (App\Http\Traits\Lockable, untouched by this lane) calls
        // Gate::authorize('manageLocks', ...) internally, which THROWS on denial for a plain
        // role_id-only ADMIN fixture user with no Spatie 'admin'/'manage locks' permission --
        // the SAME behaviour InvoiceControllerW3eTest's own checkLocked test already documents
        // and relies on. The important assertion is that this fires BEFORE any mutation below.
        $this->expectException(AuthorizationException::class);

        try {
            $this->callPrivate(app(InvoiceController::class), 'updateDetailsAmount', [
                new Request([
                    'company_id' => $company->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tasks' => [$task->id => 999.00],
                ]),
            ]);
        } finally {
            $this->assertEquals($priceBefore, $invoiceDetail->fresh()->task_price, 'checkLocked() must block updateDetailsAmount() before any mutation.');
        }
    }

    public function test_update_details_amount_edit_after_issue_gate_blocks_non_privileged_user(): void
    {
        [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();

        // updateDetailsAmount() computes a $whoIsUser log label from Auth::user()->agent->name
        // BEFORE this lane's own checkLocked()/Gate::authorize() gate ever runs (pre-existing,
        // untouched code) -- a plain Role::AGENT user with no linked Agent row would crash there
        // with an unrelated ErrorException instead of ever reaching the gate under test, so this
        // fixture links a real Agent row, matching what a genuine agent-role user always has.
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = $this->ensureAgentType(2, 'type-2');
        Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
        ]);
        session(['company_id' => $company->id]);
        $this->actingAs($agentUser);

        $priceBefore = $invoiceDetail->fresh()->task_price;

        $this->expectException(AuthorizationException::class);

        try {
            $this->callPrivate(app(InvoiceController::class), 'updateDetailsAmount', [
                new Request([
                    'company_id' => $company->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tasks' => [$task->id => 999.00],
                ]),
            ]);
        } finally {
            $this->assertEquals($priceBefore, $invoiceDetail->fresh()->task_price, 'Gate::authorize(edit-after-issue) must block before any mutation.');
        }
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (5) createProfitEntries()/createFeeLossEntries() repost mode -- proof for the OTHER TWO
    // named edit paths (updateAmount()/updateDetailsAmount()). test_update_task_price_on_path_
    // corrects_stale_commission_doc above already covers updateTaskPrice(); the previous verify
    // pass found no equivalent proof for these two, despite the brief asking for it "after
    // updateAmount()/updateDetailsAmount()/updateTaskPrice()" explicitly, all three.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_amount_on_path_corrects_stale_commission_doc(): void
    {
        [$company, $branch, $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, 150.00);

        // Post the INITIAL commission doc directly (margin 50 * 0.15 = 7.5), matching what a
        // real invoice-issue flow would already have posted before this edit.
        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createProfitEntries', [
            null, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 50.0, 7.5,
        ]);

        $originalCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':agent-commission')
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($originalCommissionDoc, 'Fixture sanity: the initial commission doc must exist.');
        $this->assertEqualsWithDelta(7.5, (float) JournalEntry::where('transaction_id', $originalCommissionDoc->id)->where('debit', '>', 0)->sum('debit'), 0.01);

        // Raise the task price via the PUBLIC updateAmount() entry point (not updateTaskPrice()) --
        // margin becomes 150 (250 - 100), commission becomes 22.5.
        $response = app(InvoiceController::class)->updateAmount(
            new Request(['tasks' => [$task->id => 250.00]]),
            $company->id,
            $invoice->invoice_number
        );
        $this->assertNotNull($response, 'updateAmount() must succeed on the ON path.');

        $this->assertSame(
            'reversed',
            $originalCommissionDoc->fresh()->posting_status,
            'The stale commission doc (7.5) must be reversed by the repost-mode fix after updateAmount(), not left stale.'
        );

        $newCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('posting_status', 'posted')
            ->where('id', '!=', $originalCommissionDoc->id)
            ->whereLike('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':agent-commission%')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($newCommissionDoc, 'A corrected, LIVE commission doc must exist after updateAmount().');
        $newCommissionAmount = (float) JournalEntry::where('transaction_id', $newCommissionDoc->id)->where('debit', '>', 0)->sum('debit');
        $this->assertEqualsWithDelta(22.5, $newCommissionAmount, 0.01, 'The corrected commission doc must reflect the NEW margin (150 * 0.15 = 22.5), not the stale 7.5.');
    }

    public function test_update_details_amount_on_path_corrects_stale_commission_doc(): void
    {
        [$company, $branch, $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, 150.00);

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createProfitEntries', [
            null, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 50.0, 7.5,
        ]);

        $originalCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':agent-commission')
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($originalCommissionDoc, 'Fixture sanity: the initial commission doc must exist.');

        // Raise the task price via the PRIVATE updateDetailsAmount() entry point.
        $response = $this->callPrivate($controller, 'updateDetailsAmount', [
            new Request([
                'company_id' => $company->id,
                'invoice_number' => $invoice->invoice_number,
                'tasks' => [$task->id => 250.00],
            ]),
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data, 'updateDetailsAmount() must succeed: '.$response->getContent());

        $this->assertSame(
            'reversed',
            $originalCommissionDoc->fresh()->posting_status,
            'The stale commission doc (7.5) must be reversed by the repost-mode fix after updateDetailsAmount(), not left stale.'
        );

        $newCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('posting_status', 'posted')
            ->where('id', '!=', $originalCommissionDoc->id)
            ->whereLike('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':agent-commission%')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($newCommissionDoc, 'A corrected, LIVE commission doc must exist after updateDetailsAmount().');
        $newCommissionAmount = (float) JournalEntry::where('transaction_id', $newCommissionDoc->id)->where('debit', '>', 0)->sum('debit');
        $this->assertEqualsWithDelta(22.5, $newCommissionAmount, 0.01, 'The corrected commission doc must reflect the NEW margin (150 * 0.15 = 22.5), not the stale 7.5.');
    }

    /**
     * Verify-fix (W4.0 item 3): the previous verify pass found that updateDetailsAmountOnPath()
     * still guarded addInvoiceChargeJournalEntries()/agentCommissionForInvoiceCharge() with the
     * interim `JournalEntry.name LIKE '%Additional Invoice Charge'` existence check, so a SECOND
     * invoice_charge amount edit silently never reposted the fee/commission JV -- exactly the
     * staleness class item 5 closed for the sibling profit/loss docs. This drives the private
     * ON-path method directly with a changed `invoice_charge` between two calls and asserts the
     * FIRST fee/commission pair is reverse()d (never left live+stale) and a corrected pair exists
     * reflecting the NEW charge amount.
     */
    public function test_update_details_amount_on_path_reposts_invoice_charge_on_second_edit(): void
    {
        [$company, $branch, $agent, $client, , $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $invoice->invoice_charge = 10.00;
        $invoice->is_client_credit = 0;
        $invoice->save();

        $controller = app(InvoiceController::class);
        $request = new Request([
            'company_id' => $company->id,
            'invoice_number' => $invoice->invoice_number,
            'tasks' => [$task->id => 150.00],
        ]);

        $freshInvoice = fn () => $invoice->fresh([
            'invoiceDetails.task.supplier', 'agent.branch', 'invoicePartials.paymentMethod', 'invoicePartials.charge', 'client',
        ]);

        $this->callPrivate($controller, 'updateDetailsAmountOnPath', [$freshInvoice(), $request, $company->id]);

        $originalFeeDoc = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-charge:'.$invoice->id.':invoice_charge')
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($originalFeeDoc, 'Fixture sanity: the initial invoice-charge FEE doc must exist.');
        $originalFeeAmount = (float) JournalEntry::where('transaction_id', $originalFeeDoc->id)->where('credit', '>', 0)->sum('credit');
        $this->assertEqualsWithDelta(10.00, $originalFeeAmount, 0.01);

        $originalCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-charge:'.$invoice->id.':invoice_charge:commission')
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($originalCommissionDoc, 'Fixture sanity: the initial invoice-charge commission doc must exist.');

        // Second edit: the invoice_charge itself changes from 10.00 -> 25.00.
        $invoice->refresh();
        $invoice->invoice_charge = 25.00;
        $invoice->save();

        $this->callPrivate($controller, 'updateDetailsAmountOnPath', [$freshInvoice(), $request, $company->id]);

        $this->assertSame(
            'reversed',
            $originalFeeDoc->fresh()->posting_status,
            'The stale invoice-charge FEE doc (10.00) must be reversed on a repeat ON-path edit, not left live+stale.'
        );
        $this->assertSame(
            'reversed',
            $originalCommissionDoc->fresh()->posting_status,
            'The stale invoice-charge commission doc must be reversed on a repeat ON-path edit, not left live+stale.'
        );

        $newFeeDoc = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('posting_status', 'posted')
            ->where('sub_type', 'FEE')
            ->where('id', '!=', $originalFeeDoc->id)
            ->whereLike('idempotency_key', 'invoice-charge:'.$invoice->id.':invoice_charge%')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($newFeeDoc, 'A corrected, LIVE invoice-charge FEE doc must exist after the second edit.');
        $newFeeAmount = (float) JournalEntry::where('transaction_id', $newFeeDoc->id)->where('credit', '>', 0)->sum('credit');
        $this->assertEqualsWithDelta(25.00, $newFeeAmount, 0.01, 'The corrected FEE doc must reflect the NEW charge (25.00), not the stale 10.00.');

        $newCommissionDoc = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('posting_status', 'posted')
            ->where('id', '!=', $originalCommissionDoc->id)
            ->whereLike('idempotency_key', 'invoice-charge:'.$invoice->id.':invoice_charge:commission%')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($newCommissionDoc, 'A corrected, LIVE invoice-charge commission doc must exist after the second edit.');
    }

    /**
     * Verify-fix (W4.0 item 3), structural grep-assert companion to the behavioural test above:
     * the interim `JournalEntry.name LIKE '%Additional Invoice Charge'` existence guard must be
     * fully gone from updateDetailsAmountOnPath()'s body, replaced by unconditional `repost: true`
     * calls (idempotency-key-based repost, never a name/description match).
     */
    public function test_update_details_amount_on_path_no_longer_guards_invoice_charge_by_name_like(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));

        $start = strpos($source, 'private function updateDetailsAmountOnPath(');
        $end = strpos($source, 'public function updateDateProcess(Request $request): array');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        $body = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString(
            'ADDITIONAL_INVOICE_CHARGE',
            $body,
            'The interim name-LIKE existence guard must be fully removed from the ON path.'
        );
        $this->assertStringContainsString('addInvoiceChargeJournalEntries($invoice, $correctedTransaction, repost: true)', $body);
        $this->assertStringContainsString('agentCommissionForInvoiceCharge($invoice, $invoice->invoice_charge, \'Invoice charge\', repost: true)', $body);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Structural grep-assert: no ->save()/raw ->delete()/description-LIKE on the ON paths this
    // lane touched, outside $legacy closures.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_task_price_on_path_never_mutates_or_deletes_ledger_rows(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));

        $start = strpos($source, 'private function updateTaskPriceOnPath(');
        $end = strpos($source, 'private function repostDerivedDocsForDetail(');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        $body = substr($source, $start, $end - $start);

        // $invoiceDetail->save()/$invoice->save()/$partial->save() are ordinary Eloquent model
        // writes (InvoiceDetail/Invoice/InvoicePartial rows), not ledger tables -- the grep-assert
        // targets JournalEntry/Transaction mutation specifically, by variable-name convention this
        // codebase already uses for those instances ($entry/$cashEntry/$transaction), plus the
        // structural marker of the old bad pattern (a bare JournalEntry::where(...) fetch-to-mutate).
        $this->assertStringNotContainsString('$entry->save()', $body);
        $this->assertStringNotContainsString('$cashEntry->save()', $body);
        $this->assertStringNotContainsString('$transaction->save()', $body);
        $this->assertStringNotContainsString('JournalEntry::where(', $body, 'updateTaskPriceOnPath() must never fetch-then-mutate a JournalEntry row.');
        $this->assertStringNotContainsString('->delete()', $body, 'updateTaskPriceOnPath() must never raw-delete a ledger row.');
        $this->assertStringNotContainsStringIgnoringCase("description', 'like'", $body);
        $this->assertStringNotContainsString('str_contains(', $body);
    }

    public function test_update_payment_type_never_raw_deletes_outside_legacy_closure(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));

        $start = strpos($source, 'public function updatePaymentType(Request $request)');
        $end = strpos($source, 'public function updatePartialGateway(Request $request)');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $body = substr($source, $start, $end - $start);

        // The ON branch calls reverseLiveReceiptTransaction(); the raw delete lives ONLY inside
        // $legacyDeleteReceipt (OFF path). Assert the raw delete calls are gated behind the
        // $engineOnForReceiptReversal check, not unconditional.
        $this->assertStringContainsString('$legacyDeleteReceipt = function ()', $body);
        $this->assertStringContainsString('$this->reverseLiveReceiptTransaction(', $body);
        $this->assertStringContainsString('if ($engineOnForReceiptReversal)', $body);
    }

    public function test_update_payment_gateway_never_raw_deletes_outside_engine_gate(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));

        $start = strpos($source, 'public function updatePaymentGateway(Request $request): JsonResponse');
        $end = strpos($source, 'public function savePartial(Request $request): JsonResponse');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString('$this->reverseLiveReceiptTransaction(', $body);
        $this->assertStringContainsString('if ($engineOnForReceiptReversal)', $body);
    }
}
