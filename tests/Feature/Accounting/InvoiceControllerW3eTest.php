<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\LegacyAccountUnresolved;
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
use App\Models\InvoiceSequence;
use App\Models\JournalEntry;
use App\Models\Payment;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\Support\AccountingTestCase;

/**
 * KEY: invoice-w3e. Covers the W3e brief's 5 items on InvoiceController:
 *   (1) update() — checkLocked + edit-after-issue fire before mutation; ON path reverses via
 *       reverseInvoiceLedger() and reposts via the shared SaleDraftBuilder; OFF path stays
 *       byte-identical (parity).
 *   (2) updateAmount()/updateDetailsAmount() — ON path targets the live sale document
 *       structurally (idempotency_key), proven against a decoy transaction sharing the SAME
 *       description text; OFF path parity (raw reversal-copy behaviour unchanged).
 *   (3) getInvoiceNumberGenerated()/autoGenerateInvoice() — row-locked sequence + dup-guard,
 *       proven via captured SQL (DB::listen) and via two simulated sequential callers each
 *       inside their own transaction. Honest limit: this process cannot fork real concurrent
 *       connections against a RefreshDatabase-wrapped test transaction — see each test's own
 *       docblock.
 *   (4) postSaleJournalEntries()'s legacy closure — a missing "Direct Income" account now throws
 *       LegacyAccountUnresolved (logged as legacy_account_unresolved) instead of silently
 *       inserting an orphaned COA leaf.
 */
class InvoiceControllerW3eTest extends AccountingTestCase
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
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Supplier, 5: Task, 6: Invoice, 7: InvoiceDetail, 8: User}
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
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
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
            'invoice_number' => 'INV-W3E-'.uniqid(),
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

        return [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, $branchOwner];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    /**
     * Role::ADMIN resolves its "current company" via session('company_id', 1) (see
     * app/Helper/helper.php's getCompanyId()) -- both InvoicePolicy::editAfterIssue()'s own
     * moduleEnabled() gate AND every Account lookup this test performs directly depend on this
     * resolving to the SAME company the fixture actually uses, not the default of 1.
     */
    private function makeAdmin(Company $company): User
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        return $admin;
    }

    /**
     * Posts a real ON-path sale document (idempotency_key set) for $invoiceDetail via the
     * existing seam-routed postSaleJournalEntries(), matching InvoiceControllerW3bTest's own
     * pattern. Returns the resulting live Transaction.
     */
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

    private function updateRequest(Task $task, Agent $agent, Client $client, Supplier $supplier, Invoice $invoice, float $newPrice): Request
    {
        return new Request([
            'invoiceNumber' => $invoice->invoice_number,
            'invdate' => now()->format('Y-m-d'),
            'duedate' => now()->addDays(7)->format('Y-m-d'),
            'currency' => 'KWD',
            'subTotal' => $newPrice,
            'clientId' => $client->id,
            'agentId' => $agent->id,
            'tasks' => [
                [
                    'id' => $task->id,
                    'description' => $task->reference ?? 'task-ref',
                    'invprice' => $newPrice,
                    'supplier_id' => $supplier->id,
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                ],
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (1) update() — checkLocked + edit-after-issue fire before mutation; ON = reverse + repost
    //     via SaleDraftBuilder; OFF = byte-identical parity.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_checklocked_blocks_before_any_mutation(): void
    {
        [$company, , $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $invoice->lock();
        $invoice->refresh();

        $detailCountBefore = InvoiceDetail::where('invoice_id', $invoice->id)->count();

        // Invoice::canModify() (App\Http\Traits\Lockable, untouched by this lane) calls
        // Gate::authorize('manageLocks', ...) -- which THROWS on denial rather than returning
        // false -- for any user without the Spatie 'admin' role or 'manage locks' permission
        // (a plain role_id-only ADMIN fixture user, same as
        // InvoiceControllerW3bTest::test_check_locked_fires_on_update_date(), which asserts the
        // SAME exception surfaces as an HTTP 403 through Laravel's own exception handler on a
        // real request). Calling the controller method directly here means that HTTP-layer
        // conversion never happens -- the important assertion is that checkLocked() blocks
        // BEFORE any mutation, not the exception's eventual HTTP status.
        $this->expectException(AuthorizationException::class);

        try {
            app(InvoiceController::class)->update($this->updateRequest($task, $agent, $client, $supplier, $invoice, 200.00));
        } finally {
            $this->assertSame(
                $detailCountBefore,
                InvoiceDetail::where('invoice_id', $invoice->id)->count(),
                'checkLocked() must block update() before InvoiceDetail is even deleted, let alone recreated.'
            );
        }
    }

    public function test_update_edit_after_issue_gate_blocks_a_non_privileged_user_before_any_mutation(): void
    {
        [$company, , $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();

        // A plain agent user (not admin/accountant) — InvoicePolicy::editAfterIssue() must deny.
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $this->actingAs($agentUser);

        $detailCountBefore = InvoiceDetail::where('invoice_id', $invoice->id)->count();

        $this->expectException(AuthorizationException::class);

        try {
            app(InvoiceController::class)->update($this->updateRequest($task, $agent, $client, $supplier, $invoice, 200.00));
        } finally {
            $this->assertSame(
                $detailCountBefore,
                InvoiceDetail::where('invoice_id', $invoice->id)->count(),
                'Gate::authorize(edit-after-issue) must block BEFORE any mutation -- InvoiceDetail must be untouched.'
            );
        }
    }

    public function test_update_off_path_is_byte_identical_legacy_behaviour(): void
    {
        [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        // OFF path: engine disabled (default in this test's tearDown/setUp).
        $response = app(InvoiceController::class)->update($this->updateRequest($task, $agent, $client, $supplier, $invoice, 200.00));
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false, 'update() must succeed: '.$response->getContent());

        // Old InvoiceDetail gone, a fresh one created for the same task.
        $newDetail = InvoiceDetail::where('invoice_id', $invoice->id)->where('task_id', $task->id)->first();
        $this->assertNotNull($newDetail);
        $this->assertNotEquals($invoiceDetail->id, $newDetail->id, 'A brand new InvoiceDetail row must replace the old one.');

        // OFF path (HEAD, byte-identical): raw Creditors/Clients pair, UNBALANCED by construction
        // (debit = supplier cost, credit = sell price) -- this is the documented pre-existing
        // legacy shape this lane deliberately preserves on the OFF path.
        $entries = JournalEntry::where('invoice_detail_id', $newDetail->id)->get();
        $this->assertCount(2, $entries, 'OFF path must still post exactly the 2 legacy entries.');

        $payable = $entries->firstWhere('type', 'payable');
        $receivable = $entries->firstWhere('type', 'receivable');
        $this->assertNotNull($payable);
        $this->assertNotNull($receivable);
        $this->assertEquals(100.00, (float) $payable->debit, 'Legacy payable leg = supplier cost, unchanged.');
        $this->assertEquals(200.00, (float) $receivable->credit, 'Legacy receivable leg = new sell price, unchanged.');

        $payableAccount = Account::find($payable->account_id);
        $this->assertSame('Creditors', $payableAccount->name);
        $receivableAccount = Account::find($receivable->account_id);
        $this->assertSame('Clients', $receivableAccount->name);
    }

    public function test_update_on_path_reverses_old_ledger_and_reposts_via_sale_draft_builder(): void
    {
        [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $oldSaleTransaction = $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, 150.00);
        $this->assertSame('posted', $oldSaleTransaction->fresh()->posting_status);

        $response = app(InvoiceController::class)->update($this->updateRequest($task, $agent, $client, $supplier, $invoice, 200.00));
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false, 'update() must succeed on the ON path: '.$response->getContent());

        // The OLD document must be reversed -- never raw-deleted (reverseInvoiceLedger(), W3b).
        $this->assertSame(
            'reversed',
            $oldSaleTransaction->fresh()->posting_status,
            'The old sale transaction must be REVERSED via reverseInvoiceLedger(), never hard-deleted.'
        );
        $this->assertNull($oldSaleTransaction->fresh()->deleted_at);

        // A brand new InvoiceDetail must exist with its OWN fresh live sale document, built via
        // the shared SaleDraftBuilder (balanced: Dr Receivable / Cr Payable / Cr-or-Dr Revenue).
        $newDetail = InvoiceDetail::where('invoice_id', $invoice->id)->where('task_id', $task->id)->first();
        $this->assertNotNull($newDetail);
        $this->assertNotEquals($invoiceDetail->id, $newDetail->id);

        $newTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$newDetail->id.':sale')
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($newTransaction, 'A fresh ON-path sale document must exist for the new invoice detail.');

        $newLines = JournalEntry::where('transaction_id', $newTransaction->id)->get();
        $this->assertGreaterThanOrEqual(2, $newLines->count());
        $this->assertEqualsWithDelta(
            $newLines->sum('debit'),
            $newLines->sum('credit'),
            0.001,
            'The ON-path repost must be a genuinely BALANCED document (unlike the legacy OFF-path pair).'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (2) updateAmount()/updateDetailsAmount() — structural targeting, decoy-description proof.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_amount_off_path_parity_unchanged(): void
    {
        [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        // OFF-path fixture: a plain legacy Transaction/JournalEntry pair (no idempotency_key),
        // matching what HEAD's own updateAmount() expects to find and reverse.
        $legacyTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 150.00,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
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
            'agent_id' => $agent->id,
            'transaction_date' => now(),
            'description' => 'Invoice created for (Assets): '.$client->full_name,
            'debit' => 150.00,
            'credit' => 0,
            'balance' => 0,
            'name' => 'Clients',
            'type' => 'receivable',
            'currency' => 'KWD',
            'exchange_rate' => 1,
            'amount' => 150.00,
        ]);

        $response = app(InvoiceController::class)->updateAmount(
            new Request(['tasks' => [$task->id => 200.00]]),
            $company->id,
            $invoice->invoice_number
        );

        // OFF path (HEAD, byte-identical): a NEW reversal Transaction whose description matches
        // the legacy 'Invoice reversal for%' convention this lane's brief explicitly names.
        // withoutGlobalScopes(): HEAD's own reversal/corrected Transaction::create() calls never
        // set `company_id` (a pre-existing gap, unrelated to this lane) -- Transaction's
        // company-authorization global scope excludes a NULL company_id even for Role::ADMIN, so
        // a scoped query would find nothing despite the row genuinely existing.
        $reversal = Transaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('description', 'LIKE', 'Invoice reversal for%')
            ->first();
        $this->assertNotNull($reversal, 'OFF path must still create the legacy reversal Transaction, unchanged.');

        $this->assertNotNull($response);
    }

    public function test_update_amount_on_path_reposts_by_idempotency_key_not_description_with_decoy(): void
    {
        [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $saleTransaction = $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, 150.00);

        // DECOY: a second invoice under the SAME company whose transaction carries the IDENTICAL
        // description text -- proving the ON-path targets by idempotency_key, never description.
        $decoyClient = Client::factory()->create(['agent_id' => $agent->id]);
        $decoyTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $decoyClient->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
            'total' => 100.00,
        ]);
        $decoyInvoice = Invoice::factory()->create([
            'client_id' => $decoyClient->id,
            'agent_id' => $agent->id,
            'invoice_number' => 'INV-W3E-DECOY-'.uniqid(),
            'invoice_date' => $invoice->invoice_date,
        ]);
        $decoyDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $decoyInvoice->id,
            'task_id' => $decoyTask->id,
            'task_price' => 150.00,
            'supplier_price' => 100.00,
        ]);
        $decoyTransaction = $this->postEngineSale($company, $branch, $agent, $decoyClient, $decoyTask, $decoyInvoice, $decoyDetail, 150.00);

        Transaction::withoutGlobalScopes()->whereKey($saleTransaction->id)->update(['description' => 'SAME TEXT ON PURPOSE']);
        Transaction::withoutGlobalScopes()->whereKey($decoyTransaction->id)->update(['description' => 'SAME TEXT ON PURPOSE']);

        $response = app(InvoiceController::class)->updateAmount(
            new Request(['tasks' => [$task->id => 220.00]]),
            $company->id,
            $invoice->invoice_number
        );

        $this->assertSame('reversed', $saleTransaction->fresh()->posting_status, "This invoice's own sale document must be reversed.");

        $decoyStillLive = $decoyTransaction->fresh();
        $this->assertSame('posted', $decoyStillLive->posting_status, 'The decoy transaction (same description, different invoice) must be untouched.');
        $this->assertEqualsWithDelta(150.00, (float) JournalEntry::where('transaction_id', $decoyTransaction->id)->where('debit', '>', 0)->sum('debit'), 0.01);

        // PostingService::repost() suffixes the replacement's idempotency key with
        // ':repost:{old->id}' whenever it collides with $old's own key (its own documented,
        // deliberate behaviour -- see PostingService::repost()'s docblock) -- so the replacement
        // is found structurally, by invoice_id + posting_status, excluding the decoy, exactly as
        // InvoiceControllerW3bTest's own updateDateProcess() repost test already does, never by
        // expecting an unchanged idempotency key.
        $replacement = Transaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('posting_status', 'posted')
            ->where('id', '!=', $decoyTransaction->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($replacement, 'A live replacement document must exist for this invoice after the amount change.');
        $this->assertNotEquals($saleTransaction->id, $replacement->id);
        $this->assertStringStartsWith('invoice-detail:'.$invoiceDetail->id.':sale', (string) $replacement->idempotency_key);
    }

    public function test_update_details_amount_on_path_reposts_by_idempotency_key_not_description_with_decoy(): void
    {
        [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = $this->makeAdmin($company);
        $this->actingAs($admin);

        $saleTransaction = $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, 150.00);

        $decoyClient = Client::factory()->create(['agent_id' => $agent->id]);
        $decoyTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $decoyClient->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
            'total' => 100.00,
        ]);
        $decoyInvoice = Invoice::factory()->create([
            'client_id' => $decoyClient->id,
            'agent_id' => $agent->id,
            'invoice_number' => 'INV-W3E-DECOY2-'.uniqid(),
            'invoice_date' => $invoice->invoice_date,
        ]);
        $decoyDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $decoyInvoice->id,
            'task_id' => $decoyTask->id,
            'task_price' => 150.00,
            'supplier_price' => 100.00,
        ]);
        $decoyTransaction = $this->postEngineSale($company, $branch, $agent, $decoyClient, $decoyTask, $decoyInvoice, $decoyDetail, 150.00);

        Transaction::withoutGlobalScopes()->whereKey($saleTransaction->id)->update(['description' => 'SAME TEXT ON PURPOSE']);
        Transaction::withoutGlobalScopes()->whereKey($decoyTransaction->id)->update(['description' => 'SAME TEXT ON PURPOSE']);

        $response = $this->callPrivate(app(InvoiceController::class), 'updateDetailsAmount', [
            new Request([
                'company_id' => $company->id,
                'invoice_number' => $invoice->invoice_number,
                'tasks' => [$task->id => 210.00],
            ]),
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data, 'updateDetailsAmount() must succeed: '.$response->getContent());

        $this->assertSame('reversed', $saleTransaction->fresh()->posting_status);
        $this->assertSame('posted', $decoyTransaction->fresh()->posting_status, 'Decoy must be untouched.');

        // See the analogous updateAmount() decoy test's own comment: repost() suffixes the
        // replacement's idempotency key, so it is found structurally by invoice_id, not by an
        // unchanged idempotency key.
        $replacement = Transaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('posting_status', 'posted')
            ->where('id', '!=', $decoyTransaction->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($replacement);
        $this->assertNotEquals($saleTransaction->id, $replacement->id);
        $this->assertStringStartsWith('invoice-detail:'.$invoiceDetail->id.':sale', (string) $replacement->idempotency_key);
    }

    public function test_no_new_description_like_reversal_targeting_in_update_amount_on_path(): void
    {
        // Structural grep-style proof, run from PHPUnit so it's part of the same verified suite:
        // the ON-path branches this lane added to updateAmount()/updateDetailsAmount() must never
        // introduce a NEW `description`/`'LIKE'` reversal target. The pre-existing OFF-path LIKE
        // clauses (HEAD's own, kept byte-identical) are the only ones allowed to remain.
        $source = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));

        // Precise boundaries: each *OnPath() method's own body only, ending exactly at the NEXT
        // method declaration that immediately follows it in the file (verified directly against
        // the current file layout -- update() immediately follows updateAmountOnPath();
        // updateDateProcess() immediately follows updateDetailsAmountOnPath()). A loose
        // string-distance slice would otherwise swallow unrelated methods (including
        // updateDetailsAmount()'s own byte-identical, pre-existing LIKE clauses) sitting between
        // the two *OnPath() methods.
        $onPathStart = strpos($source, 'private function updateAmountOnPath(');
        $onPathEnd = strpos($source, 'public function update(Request $request)');
        $this->assertNotFalse($onPathStart);
        $this->assertNotFalse($onPathEnd);
        $this->assertGreaterThan($onPathStart, $onPathEnd);
        $updateAmountOnPathBody = substr($source, $onPathStart, $onPathEnd - $onPathStart);

        $detailsOnPathStart = strpos($source, 'private function updateDetailsAmountOnPath(');
        $detailsOnPathEnd = strpos($source, 'public function updateDateProcess(Request $request): array');
        $this->assertNotFalse($detailsOnPathStart);
        $this->assertNotFalse($detailsOnPathEnd);
        $this->assertGreaterThan($detailsOnPathStart, $detailsOnPathEnd);
        $detailsOnPathBody = substr($source, $detailsOnPathStart, $detailsOnPathEnd - $detailsOnPathStart);

        $this->assertStringNotContainsStringIgnoringCase("description', 'like'", $updateAmountOnPathBody);
        $this->assertStringNotContainsStringIgnoringCase("description', 'like'", $detailsOnPathBody);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (3) Row-locked sequence + dup-guard.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_get_invoice_number_generated_issues_a_select_for_update_on_invoice_sequence(): void
    {
        [$company] = $this->makeFixture();

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        DB::transaction(function () use ($company) {
            $this->callPrivate(app(InvoiceController::class), 'getInvoiceNumberGenerated', [$company->id]);
        });

        $lockingQueries = array_filter($queries, function ($sql) {
            $sql = strtolower($sql);

            return str_contains($sql, 'invoice_sequence') && str_contains($sql, 'for update');
        });

        $this->assertNotEmpty(
            $lockingQueries,
            'getInvoiceNumberGenerated() must issue a real SELECT ... FOR UPDATE against invoice_sequence -- not merely document one.'
        );
    }

    public function test_auto_generate_invoice_dup_guard_issues_a_select_for_update_on_invoice_details(): void
    {
        [$company, $branch, $agent, $client, $supplier, $task] = $this->makeFixture();

        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'created_by' => $branch->user_id,
            'invoice_id' => null,
            'account_id' => null,
            'amount' => 150.00,
            'service_charge' => 0,
            'currency' => 'KWD',
            'payment_gateway' => 'nonexistent-test-gateway',
            'payment_method_id' => null,
            'payment_date' => now(),
            'status' => 'completed',
        ]);

        $freshTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
            'total' => 100.00,
            'supplier_pay_date' => now(),
        ]);

        InvoiceSequence::firstOrCreate(['company_id' => $company->id], ['current_sequence' => 1]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        putenv('N8N_WEBHOOK_TEST_URL=https://n8n.test/w3e-hook');
        $_ENV['N8N_WEBHOOK_TEST_URL'] = 'https://n8n.test/w3e-hook';
        $_SERVER['N8N_WEBHOOK_TEST_URL'] = 'https://n8n.test/w3e-hook';

        Http::fake();

        app(InvoiceController::class)->autoGenerateInvoice($freshTask, $payment);

        $lockingQueries = array_filter($queries, function ($sql) {
            $sql = strtolower($sql);

            return str_contains($sql, 'invoice_details') && str_contains($sql, 'for update');
        });

        $this->assertNotEmpty(
            $lockingQueries,
            'autoGenerateInvoice() must issue a real SELECT ... FOR UPDATE against invoice_details for its dup-guard.'
        );
    }

    /**
     * Simulated concurrency (per the brief's own explicit allowance: "use DB transactions in
     * test; document if true parallelism cannot be simulated"). This process cannot fork a
     * genuinely separate, blocking OS connection against RefreshDatabase's own wrapping
     * transaction (a second real connection cannot see this test's uncommitted rows under
     * REPEATABLE READ, so it would simply find nothing to lock rather than block) -- so this
     * test instead proves the SAFE, provable half: two callers, each behaving exactly like a
     * real caller (its own DB::transaction()), for the SAME company, in sequence, never produce
     * a duplicate or skipped sequence number. Combined with the SQL-level FOR UPDATE proof above
     * (which shows the row lock these callers would contend on is genuinely requested), this is
     * the honest limit of what a single-process PHPUnit run can demonstrate for this mechanism.
     */
    public function test_get_invoice_number_generated_two_simulated_callers_never_collide(): void
    {
        [$company] = $this->makeFixture();

        $numbers = [];
        for ($i = 0; $i < 2; $i++) {
            $numbers[] = DB::transaction(function () use ($company) {
                return $this->callPrivate(app(InvoiceController::class), 'getInvoiceNumberGenerated', [$company->id]);
            });
        }

        $this->assertNotEquals($numbers[0], $numbers[1], 'Two sequential callers must never receive the same invoice number.');
        $this->assertSame(3, InvoiceSequence::where('company_id', $company->id)->value('current_sequence'), 'Exactly two increments must be recorded -- no lost update.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (4) Legacy null-deref -> LegacyAccountUnresolved.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_missing_direct_income_account_throws_legacy_account_unresolved_and_creates_no_orphan(): void
    {
        // Deliberately NOT seeding CoaSeeder -- Direct Income does not exist for this company.
        $company = Company::factory()->create();

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'type' => 'hotel',
        ]);
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now(),
        ]);
        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
        ]);

        config(['accounting.engine.enabled' => false]);

        $accountCountBefore = Account::where('company_id', $company->id)->count();
        $this->assertSame(0, $accountCountBefore, 'Fixture sanity: no accounts at all exist for this company.');

        Log::spy();

        $result = $this->callPrivate(app(InvoiceController::class), 'postSaleJournalEntries', [
            null,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            150.0,
            $client->full_name,
        ]);

        $this->assertSame(
            'Failed to create revenue entry',
            $result,
            'The caller-visible failure string must stay unchanged -- only the logging/orphan-creation behaviour improved.'
        );

        $this->assertSame(
            0,
            Account::where('company_id', $company->id)->count(),
            'No orphaned account (parent_id/root_id both NULL) may be created when Direct Income is missing.'
        );

        Log::shouldHaveReceived('error')
            ->with('legacy_account_unresolved', \Mockery::type('array'))
            ->once();
    }

    public function test_legacy_account_unresolved_exception_carries_a_clear_message(): void
    {
        $exception = new LegacyAccountUnresolved('Direct Income', 42);

        $this->assertStringContainsString('Direct Income', $exception->getMessage());
        $this->assertStringContainsString('42', $exception->getMessage());
        $this->assertStringNotContainsString('property', $exception->getMessage(), 'The message must be a clear, business-level explanation -- never a raw PHP null-property warning.');
    }

    public function test_off_path_parity_still_passes_for_a_seeded_company(): void
    {
        // Control for the guard above: a company WITH CoaSeeder (the realistic case) must still
        // post the booking-revenue entry exactly as before -- the guard only fires for the
        // genuinely missing-account case.
        [$company, $branch, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();

        config(['accounting.engine.enabled' => false]);

        $result = $this->callPrivate(app(InvoiceController::class), 'postSaleJournalEntries', [
            null,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            150.0,
            $client->full_name,
        ]);

        $this->assertNull($result, 'A seeded company must still succeed (both legacy entries created, no failure string).');

        $revenueEntry = JournalEntry::where('invoice_detail_id', $invoiceDetail->id)
            ->where('description', 'like', 'Invoice created for (Income)%')
            ->first();
        $this->assertNotNull($revenueEntry);
        $this->assertSame('Hotel Booking Revenue', Account::find($revenueEntry->account_id)->name);
    }
}
