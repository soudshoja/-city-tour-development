<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\PostingException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Http\Controllers\ChatController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\AccountingTestCase;

/**
 * W1 acceptance test (Accounting Gap/11-technical-implementation-plan.md §"Acceptance tests
 * (P2, per wave)"): "ChatControllerPostingTest::whatsapp_invoice_is_balanced — the exact R2.2a
 * case (markup) now nets to zero (was -2xmarkup)."
 *
 * Exercises ChatController::postChatInvoiceTaskEntries() directly (extracted from
 * createInvoice()'s per-task loop specifically so it can be called here without a full
 * chat()/AI-classification HTTP round trip) rather than routing through PostingService or the
 * seam's own mock-free proof technique a second time — PostingSeamTest already proves the seam's
 * routing contract generically; this suite proves THIS FEEDER's specific business event (what it
 * books, on which accounts, with which idempotency key) on both sides of the flag.
 */
class ChatControllerPostingTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Supplier, 5: Invoice}
     */
    private function makeChatFixtures(?Company $company = null): array
    {
        $company = $company ?? Company::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'Sales']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();
        $invoice = Invoice::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
        ]);

        return [$company, $branch, $agent, $client, $supplier, $invoice];
    }

    private function makeTask(Company $company, Agent $agent, Client $client, Supplier $supplier, float $total, string $type = 'flight'): Task
    {
        return Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => $type,
            'total' => $total,
        ]);
    }

    private function makeInvoiceDetail(Invoice $invoice, Task $task, float $invprice, float $total): InvoiceDetail
    {
        return InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'task_price' => $invprice,
            'supplier_price' => $total,
            'markup_price' => $invprice - $total,
        ]);
    }

    /** Legacy-shaped $task array-access value, exactly what createInvoice()'s loop iterates over. */
    private function taskPayload(Task $task, float $invprice): Task
    {
        $task->invprice = $invprice;

        return $task;
    }

    /**
     * (1) OFF: byte-identical to HEAD — same Transaction + 3 JournalEntry rows, same (buggy,
     * inverted, unbalanced) shape. Deliberately does NOT track this test's company for the C1
     * invariant tearDown hook: reproducing HEAD's known imbalance is the entire point of this
     * test, exactly like AccountingInvariantsTest's own unbalanced-fixture tests.
     */
    public function test_off_path_reproduces_head_journal_entries_byte_identical(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$company, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000);
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $payableAccount = Account::factory()->create(['company_id' => $company->id]);
        $receivableAccount = Account::factory()->create(['company_id' => $company->id]);
        $incomeAccount = Account::factory()->create(['company_id' => $company->id]);

        $legacyCalled = false;
        Log::spy();

        $controller = app(ChatController::class);
        $result = $controller->postChatInvoiceTaskEntries(
            $taskPayload,
            $task->fresh(),
            $supplier,
            $client,
            $agent,
            $invoice,
            $invoice->invoice_number,
            $invoiceDetail,
            $payableAccount,
            $receivableAccount,
            $incomeAccount,
            $company->id,
            $branch->id
        );

        $this->assertNotNull($result, 'The legacy closure returns the created Transaction.');

        $lines = DB::table('journal_entries')->where('company_id', $company->id)->orderBy('id')->get();
        $this->assertCount(3, $lines, 'HEAD writes exactly 3 JournalEntry rows for one task.');

        $payableLine = $lines->firstWhere('type', 'payable');
        $receivableLine = $lines->firstWhere('type', 'receivable');
        $incomeLine = $lines->firstWhere('type', 'income');

        $this->assertNotNull($payableLine);
        $this->assertNotNull($receivableLine);
        $this->assertNotNull($incomeLine);

        // Reproduces HEAD's inverted signs verbatim: payable is DEBITED with cost, receivable is
        // CREDITED with sell price, income is CREDITED with markup.
        $this->assertEquals(100.000, (float) $payableLine->debit);
        $this->assertEquals(0.0, (float) $payableLine->credit);
        $this->assertEquals(130.000, (float) $receivableLine->credit);
        $this->assertEquals(30.000, (float) $incomeLine->credit);

        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $this->assertEqualsWithDelta(
            2 * 30.000,
            $totalCredit - $totalDebit,
            0.0005,
            'R2.2a: HEAD nets off by exactly 2x markup — this proves the bug is still reproduced '
                .'byte-for-byte on the OFF path, not silently fixed underneath the flag.'
        );

        Log::shouldHaveReceived('info')->with('accounting.legacy_path', Mockery::type('array'));
    }

    /**
     * (2) ON: one balanced 3-line INV document — the R2.2a case now nets to zero.
     */
    public function test_whatsapp_invoice_is_balanced(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures($company);

        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000, type: 'flight');
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        // Legacy-path accounts are irrelevant here (the closure never runs) but the method
        // signature still needs non-null Account instances to remain call-compatible.
        $legacyPayable = Account::factory()->create(['company_id' => $company->id]);
        $legacyReceivable = Account::factory()->create(['company_id' => $company->id]);
        $legacyIncome = Account::factory()->create(['company_id' => $company->id]);

        $controller = app(ChatController::class);
        $result = $controller->postChatInvoiceTaskEntries(
            $taskPayload,
            $task->fresh(),
            $supplier,
            $client,
            $agent,
            $invoice,
            $invoice->invoice_number,
            $invoiceDetail,
            $legacyPayable,
            $legacyReceivable,
            $legacyIncome,
            $company->id,
            $branch->id
        );

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);
        $this->assertSame('chat:invoice_task:'.$invoiceDetail->id, $result->transaction->idempotency_key);
        $this->assertSame('INV', $result->transaction->doc_type);
        $this->assertEqualsWithDelta(
            0.0,
            (float) $result->transaction->total_debit - (float) $result->transaction->total_credit,
            0.0005,
            'The R2.2a case must now net to zero (was off by 2x markup on the legacy path).'
        );

        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(
            3,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            'W3d: with the real seeders now mapping SERVICE_REVENUE(flight) (the margin leg\'s '.
            'purpose code as of w3d-brief.md decision 3 — was MARKUP_INCOME before this lane), a '.
            'positive markup posts all 3 lines — receivable, payable, and margin.'
        );

        $lines = DB::table('journal_entries')->where('company_id', $company->id)->get();
        $this->assertEqualsWithDelta(130.000, (float) $lines->sum('debit'), 0.0005);
        $this->assertEqualsWithDelta(130.000, (float) $lines->sum('credit'), 0.0005);

        $receivableLine = $lines->firstWhere('type', 'receivable');
        $payableLine = $lines->firstWhere('type', 'payable');
        $marginLine = $lines->firstWhere('type', 'income');

        $this->assertNotNull($receivableLine);
        $this->assertNotNull($payableLine);
        $this->assertNotNull($marginLine);
        $this->assertEqualsWithDelta(130.000, (float) $receivableLine->debit, 0.0005);
        $this->assertEqualsWithDelta(100.000, (float) $payableLine->credit, 0.0005);
        $this->assertEqualsWithDelta(30.000, (float) $marginLine->credit, 0.0005);

        // W3d proof: the margin line resolves to the dedicated per-service 'Flight Booking
        // Revenue' (4110) leaf via SERVICE_REVENUE(flight) — NOT 'Markup Income' (4132), which
        // w3d-brief.md decision 3 reserves for a distinct, not-yet-modeled event (an explicit
        // markup on top of a fare the invoice already separates from cost).
        $marginAccount = Account::withoutGlobalScopes()->find($marginLine->account_id);
        $this->assertSame('4110', $marginAccount->code);
        $this->assertSame('Flight Booking Revenue', $marginAccount->name);

        $markupIncomeId = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'MARKUP_INCOME')
            ->value('account_id');
        $this->assertNotEquals($markupIncomeId, $marginAccount->id, 'The margin must never resolve to the MARKUP_INCOME (4132) leaf.');

        $this->assertSame(
            'income',
            $marginLine->type,
            "Positive markup must persist type='income' — matches AccountingController's ".
            "whereIn('type', ['receivable','income']) screen filter."
        );
    }

    /**
     * Task B (W1.2): a NEGATIVE markup (sold below cost) still uses the margin account — the sign
     * is carried by the DEBIT side, not by switching to a different ledger `type` label. Before
     * this fix the debit leg persisted `type='expense'`, which matches neither of
     * AccountingController's screen filters (`whereIn('type', ['payable','expenses'])` — plural
     * "expenses", line 535/906 — nor `whereIn('type', ['receivable','income'])`, line 721), so
     * every below-cost sale was invisible on both accounting screens even though it posted a
     * perfectly balanced document. W3d: the margin leg's purpose code moved from MARKUP_INCOME to
     * SERVICE_REVENUE(flight) (w3d-brief.md decision 3) — this test now resolves to the dedicated
     * per-service 'Flight Booking Revenue' (4110) leaf, not 'Markup Income' (4132) — and still
     * proves the 3-line balanced document, not just the persisted `type` column.
     */
    public function test_on_path_negative_markup_persists_income_type_not_expense(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures($company);

        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000, type: 'flight');
        $invprice = 70.000; // negative markup: sold below cost
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $legacyPayable = Account::factory()->create(['company_id' => $company->id]);
        $legacyReceivable = Account::factory()->create(['company_id' => $company->id]);
        $legacyIncome = Account::factory()->create(['company_id' => $company->id]);

        $controller = app(ChatController::class);
        $result = $controller->postChatInvoiceTaskEntries(
            $taskPayload,
            $task->fresh(),
            $supplier,
            $client,
            $agent,
            $invoice,
            $invoice->invoice_number,
            $invoiceDetail,
            $legacyPayable,
            $legacyReceivable,
            $legacyIncome,
            $company->id,
            $branch->id
        );

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);
        $this->assertEqualsWithDelta(
            0.0,
            (float) $result->transaction->total_debit - (float) $result->transaction->total_credit,
            0.0005,
            'Below-cost sale must still balance: Dr receivable + Dr markup(abs) = Cr payable.'
        );

        $this->assertSame(3, DB::table('journal_entries')->where('company_id', $company->id)->count());

        $lines = DB::table('journal_entries')->where('company_id', $company->id)->get();
        $markupLine = $lines->firstWhere('type', 'income');

        $this->assertNotNull($markupLine);
        $this->assertEqualsWithDelta(30.000, (float) $markupLine->debit, 0.0005);

        $markupAccount = Account::withoutGlobalScopes()->find($markupLine->account_id);
        $this->assertSame('4110', $markupAccount->code, 'W3d: the margin leg resolves to SERVICE_REVENUE(flight) — "Flight Booking Revenue" (4110) — not MARKUP_INCOME (4132).');

        $this->assertSame(
            'income',
            $markupLine->type,
            "Negative markup must ALSO persist type='income' (a contra-income debit, sign carried ".
            "by 'debit' vs 'credit', not by the type label) — 'expense' matched neither ".
            "AccountingController screen filter and made every below-cost sale invisible."
        );
    }

    /**
     * (3) ON twice with the SAME InvoiceDetail (the natural retry shape — e.g. a queued retry of
     * this exact posting step after a transient failure): the idempotency key is derived solely
     * from invoiceDetail->id, so the second call must return the SAME PostedDocument and must not
     * write a second transaction/journal-entry set.
     */
    public function test_on_path_posted_twice_for_the_same_invoice_detail_does_not_double_post(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures($company);

        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000, type: 'flight');
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $legacyPayable = Account::factory()->create(['company_id' => $company->id]);
        $legacyReceivable = Account::factory()->create(['company_id' => $company->id]);
        $legacyIncome = Account::factory()->create(['company_id' => $company->id]);

        $controller = app(ChatController::class);

        $call = fn () => $controller->postChatInvoiceTaskEntries(
            $taskPayload,
            $task->fresh(),
            $supplier,
            $client,
            $agent,
            $invoice,
            $invoice->invoice_number,
            $invoiceDetail,
            $legacyPayable,
            $legacyReceivable,
            $legacyIncome,
            $company->id,
            $branch->id
        );

        $result1 = $call();
        $result2 = $call();

        $this->assertSame($result1->transaction->id, $result2->transaction->id, 'A retry with the same invoiceDetail must return the SAME posted transaction.');
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(3, DB::table('journal_entries')->where('company_id', $company->id)->count());
    }

    /**
     * (4) ON, engine PostingException (MARKUP_INCOME deliberately left unmapped ->
     * UnmappedPurposeException): LOUD (Log::critical) and RETHROWN, with NO partial rows — the
     * whole document rolls back, not just the failing line.
     */
    public function test_engine_posting_exception_is_loud_and_leaves_no_partial_rows(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$company, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $receivableLeaf = Account::factory()->create(['company_id' => $company->id]);
        $payableLeaf = Account::factory()->create(['company_id' => $company->id]);
        // MARKUP_INCOME deliberately NOT mapped in system_accounts for this company.
        DB::table('system_accounts')->insert([
            [
                'company_id' => $company->id,
                'purpose_code' => 'RECEIVABLE_CONTROL',
                'service_type' => null,
                'account_id' => $receivableLeaf->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'purpose_code' => 'SERVICE_PAYABLE',
                'service_type' => 'flight',
                'account_id' => $payableLeaf->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000);
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $legacyPayable = Account::factory()->create(['company_id' => $company->id]);
        $legacyReceivable = Account::factory()->create(['company_id' => $company->id]);
        $legacyIncome = Account::factory()->create(['company_id' => $company->id]);

        Log::spy();

        $controller = app(ChatController::class);

        $this->expectException(UnmappedPurposeException::class);

        try {
            $controller->postChatInvoiceTaskEntries(
                $taskPayload,
                $task->fresh(),
                $supplier,
                $client,
                $agent,
                $invoice,
                $invoice->invoice_number,
                $invoiceDetail,
                $legacyPayable,
                $legacyReceivable,
                $legacyIncome,
                $company->id,
                $branch->id
            );
        } finally {
            $this->assertSame(
                0,
                DB::table('transactions')->where('company_id', $company->id)->count(),
                'No partial rows: the whole document must roll back, not just the failing line.'
            );
            $this->assertSame(0, DB::table('journal_entries')->where('company_id', $company->id)->count());

            Log::shouldHaveReceived('critical')->once()->with(
                'accounting.engine_failure',
                Mockery::on(fn (array $context) => $context['feeder'] === 'chat.invoice_task'
                    && $context['company_id'] === $company->id
                    && $context['idempotency_key'] === 'chat:invoice_task:'.$invoiceDetail->id
                    && $context['exception_class'] === UnmappedPurposeException::class)
            );
        }
    }

    /**
     * Guard for the createInvoice() per-task catch block fix: a PostingException must propagate
     * out of createInvoice() (via handleTaskPricing()) rather than being swallowed into the
     * generic "Failed to create InvoiceDetails" / "Invoice creation failed!" JSON responses. This
     * is a class check (extends PostingException) proven directly rather than re-running the
     * whole AI-classification chat() pipeline just to reach handleTaskPricing().
     */
    public function test_posting_exception_is_an_instance_the_catch_block_reorder_actually_targets(): void
    {
        $this->assertTrue(is_subclass_of(UnmappedPurposeException::class, PostingException::class));
        $this->assertTrue(is_subclass_of(PostingException::class, \RuntimeException::class));
    }

    /**
     * (5) W1.1 fix, C3 — corrected (W1.2 retarget): `tasks.supplier_id` carries no FK constraint
     * (unlike client_id/agent_id, which do) and `Supplier` has no `SoftDeletes`, so a
     * hard-deleted supplier genuinely leaves `Supplier::where('id', $task['supplier_id'])
     * ->first()` returning null at the createInvoice() call site. Before this fix the method's
     * `Supplier $supplier` parameter was non-nullable, so passing null raised a `TypeError` —
     * which extends `\Error`, not `\Exception`, escaping BOTH `catch (PostingException)` and
     * `catch (Exception)` in createInvoice() as an uncaught 500. That is what `?Supplier
     * $supplier` in the signature fixes, and it is ALL it fixes.
     *
     * HEAD did NOT tolerate a null $supplier: its own local was untyped, so `$supplier->name`
     * emitted PHP's "Attempt to read property on null" warning — and this app's
     * HandleExceptions bootstrapper converts that warning into a thrown `ErrorException`
     * (`ErrorException extends \Exception`, so it IS caught by createInvoice()'s
     * `catch (Exception $e)`, never reaching the uncaught-500 path). The result at HEAD: the
     * loop's current iteration aborts after the `Transaction::create()` for this task has
     * already run but before any `JournalEntry::create()` for it succeeds, `Log::error()` fires,
     * and the controller returns the generic 'Failed to create InvoiceDetails for task: …' JSON
     * response — a loud, clean abort, not a completed invoice.
     *
     * This test proves the closure itself reproduces that exact abort (it is called directly,
     * bypassing createInvoice()'s try/catch — see this class's own docblock for why — so the
     * ErrorException is asserted here rather than the JSON response it would produce one frame
     * up): the closure throws `ErrorException` at the first `$supplier->name` read (the
     * 'payable' JournalEntry), zero `journal_entries` rows are written, and the exception class
     * itself proves the HTTP boundary never sees an uncaught 500 (`ErrorException extends
     * Exception`, so `catch (Exception $e)` in createInvoice() — proven reachable by
     * `test_posting_exception_is_an_instance_the_catch_block_reorder_actually_targets`'s sibling
     * check on `PostingException` — necessarily catches this too).
     *
     * Add back `$supplier?->name` / `?? 'Unknown Supplier'` / `$supplier?->id` in the closure to
     * see this go red: those null-safe reads turn HEAD's loud abort into a silent success that
     * writes 3 legacy rows with `type_reference_id` NULL on a `payable` line.
     */
    public function test_off_path_null_supplier_throws_error_exception_as_at_head(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$company, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000);
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $payableAccount = Account::factory()->create(['company_id' => $company->id]);
        $receivableAccount = Account::factory()->create(['company_id' => $company->id]);
        $incomeAccount = Account::factory()->create(['company_id' => $company->id]);

        Log::spy();

        $controller = app(ChatController::class);

        $caught = null;

        try {
            $controller->postChatInvoiceTaskEntries(
                $taskPayload,
                $task->fresh(),
                null, // dangling supplier_id: Supplier::where(...)->first() genuinely returns null
                $client,
                $agent,
                $invoice,
                $invoice->invoice_number,
                $invoiceDetail,
                $payableAccount,
                $receivableAccount,
                $incomeAccount,
                $company->id,
                $branch->id
            );
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(
            \ErrorException::class,
            $caught,
            'A null supplier must abort loudly, exactly as at HEAD — not complete silently.'
        );
        $this->assertSame('Attempt to read property "name" on null', $caught->getMessage());

        // ErrorException extends \Exception, never \Error: createInvoice()'s real
        // catch (Exception $e) — one frame up from this direct call — necessarily catches this,
        // so the HTTP boundary this closure ultimately serves never sees an uncaught 500 for a
        // null supplier; it returns the generic 'Failed to create InvoiceDetails for task: …'
        // JSON response instead, exactly as at HEAD.
        $this->assertInstanceOf(\Exception::class, $caught);
        $this->assertNotInstanceOf(\Error::class, $caught);

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            "A null supplier must abort before any journal_entries row is written, exactly as at HEAD — the closure throws on the FIRST JournalEntry::create()'s \$supplier->name read."
        );

        Log::shouldHaveReceived('info')->with('accounting.legacy_path', Mockery::type('array'));
    }

    /**
     * W1.3 fix, chat-nullable — `tasks.client_id` carries no FK constraint (unlike
     * `tasks.supplier_id`, which also has none, but see that test's own docblock) and `Client`
     * has no `SoftDeletes`, so a hard-deleted client leaves `Client::where('id',
     * $task['client_id'])->first()` genuinely returning null at the createInvoice() call site.
     * Before this fix `Client $client` was non-nullable, so passing null raised a `TypeError` —
     * which extends `\Error`, not `\Exception`, escaping BOTH `catch (PostingException)` and
     * `catch (Exception)` in createInvoice() as an uncaught 500. That is what `?Client $client`
     * in the signature fixes, and it is ALL it fixes.
     *
     * Unlike the null-supplier case, this is NOT reached inside the OFF-path `$legacy` closure
     * at all — that closure is only ever invoked (by PostingSeam::post()) after $lines/$draft are
     * built further down, and this method builds the ON-path `$lines` array UNCONDITIONALLY,
     * regardless of the engine flag, before ever calling postingSeam->post(). The very first
     * LineDraft (`purposeCode: 'RECEIVABLE_CONTROL'`) passes `partyAccountRef: $client->id`
     * as a NAMED argument BEFORE `description: '... ' . $client->first_name` in source order —
     * PHP evaluates named-argument value expressions left-to-right regardless of the
     * constructor's own parameter order — so `$client->id` is the first (and only) property
     * read attempted on the null client, throwing before `$client->first_name` is ever reached,
     * before postingSeam->post() is called, and therefore before $legacy() (and its
     * Transaction::create() / JournalEntry::create() calls) ever runs. Zero journal_entries
     * rows exist after the abort — not one.
     *
     * Revert the signature to `Client $client` to see this go red (TypeError instead of
     * ErrorException).
     */
    public function test_off_path_null_client_throws_error_exception_as_at_head(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$company, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000);
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $payableAccount = Account::factory()->create(['company_id' => $company->id]);
        $receivableAccount = Account::factory()->create(['company_id' => $company->id]);
        $incomeAccount = Account::factory()->create(['company_id' => $company->id]);

        $controller = app(ChatController::class);

        $caught = null;

        try {
            $controller->postChatInvoiceTaskEntries(
                $taskPayload,
                $task->fresh(),
                $supplier,
                null, // dangling client_id: Client::where(...)->first() genuinely returns null
                $agent,
                $invoice,
                $invoice->invoice_number,
                $invoiceDetail,
                $payableAccount,
                $receivableAccount,
                $incomeAccount,
                $company->id,
                $branch->id
            );
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(
            \ErrorException::class,
            $caught,
            'A null client must abort loudly, exactly as at HEAD — not complete silently.'
        );
        $this->assertSame('Attempt to read property "id" on null', $caught->getMessage());

        // ErrorException extends \Exception, never \Error: createInvoice()'s real
        // catch (Exception $e) necessarily catches this, so the HTTP boundary never sees an
        // uncaught 500 for a null client either.
        $this->assertInstanceOf(\Exception::class, $caught);
        $this->assertNotInstanceOf(\Error::class, $caught);

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            'A null client aborts building the ON-path $lines array (RECEIVABLE_CONTROL LineDraft, $client->id) before postingSeam->post() — and therefore $legacy() — is ever called, so no journal_entries row is written for either path.'
        );
    }

    /**
     * W1.3 fix, chat-nullable — `tasks.agent_id` carries no FK constraint and `Agent` has no
     * `SoftDeletes`, so a hard-deleted agent leaves `Agent::where('id', $task['agent_id'])
     * ->first()` genuinely returning null at the createInvoice() call site. Before this fix
     * `Agent $agent` was non-nullable, so passing null raised a `TypeError` for the same
     * uncaught-500 reason as the null-client and null-supplier cases above. `?Agent $agent` in
     * the signature fixes only that call-boundary crash.
     *
     * The first unguarded read of $agent in this method is even earlier than the $lines array
     * that trips up the null-client case: `$taskBranch = $agent->branch;` runs right after the
     * $legacy closure is DEFINED (defining a closure does not execute its body) but well before
     * $lines/$draft are built or postingSeam->post() (and therefore $legacy()) is ever called.
     * A null $agent throws there first, so — like the null-client case — zero journal_entries
     * rows exist after the abort; neither the legacy JournalEntry::create() calls nor the
     * ON-path MARKUP_INCOME LineDraft's own `$agent->id`/`$agent->name` reads are ever reached.
     *
     * Revert the signature to `Agent $agent` to see this go red (TypeError instead of
     * ErrorException).
     */
    public function test_off_path_null_agent_throws_error_exception_as_at_head(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$company, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000);
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $payableAccount = Account::factory()->create(['company_id' => $company->id]);
        $receivableAccount = Account::factory()->create(['company_id' => $company->id]);
        $incomeAccount = Account::factory()->create(['company_id' => $company->id]);

        $controller = app(ChatController::class);

        $caught = null;

        try {
            $controller->postChatInvoiceTaskEntries(
                $taskPayload,
                $task->fresh(),
                $supplier,
                $client,
                null, // dangling agent_id: Agent::where(...)->first() genuinely returns null
                $invoice,
                $invoice->invoice_number,
                $invoiceDetail,
                $payableAccount,
                $receivableAccount,
                $incomeAccount,
                $company->id,
                $branch->id
            );
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(
            \ErrorException::class,
            $caught,
            'A null agent must abort loudly, exactly as at HEAD — not complete silently.'
        );
        $this->assertSame('Attempt to read property "branch" on null', $caught->getMessage());

        $this->assertInstanceOf(\Exception::class, $caught);
        $this->assertNotInstanceOf(\Error::class, $caught);

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            'A null agent aborts at $taskBranch = $agent->branch, before $lines/$draft are built and before postingSeam->post() — and therefore $legacy() — is ever called, so no journal_entries row is written for either path.'
        );
    }

    /**
     * (6) W1.1 fix, C4 — chat-feeder-specific reproduction: an agent whose branch_id points at no
     * real Branch row (agents.branch_id, like tasks.supplier_id, carries no FK constraint) makes
     * `(int) $agent->branch?->company_id` cast to 0 for the ON-path draft. PostingSeam::post()
     * (hardened in the prior W1.1 engine round) must log `accounting.company_unresolvable` at
     * ERROR — distinct from an ordinary flag-disabled decision — and still route to legacy. This
     * test does not add any new handling of its own; it only proves the existing seam guard fires
     * for THIS feeder's own companyId resolution path, not just PostingSeamTest's synthetic
     * fixture.
     */
    public function test_companyid_zero_from_a_branchless_task_agent_logs_error_and_routes_to_legacy(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$company, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        // Detach the TASK's own agent from any real branch — reproduces the exact W1 defect:
        // (int) $taskBranch?->company_id casts a null branch's company id to 0. The invoice's own
        // $company/$branch (passed below as the legacy-path args) are untouched.
        $agent->branch_id = 999999999;
        $agent->save();

        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000);
        $invprice = 130.000;
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $payableAccount = Account::factory()->create(['company_id' => $company->id]);
        $receivableAccount = Account::factory()->create(['company_id' => $company->id]);
        $incomeAccount = Account::factory()->create(['company_id' => $company->id]);

        Log::spy();

        $controller = app(ChatController::class);
        $result = $controller->postChatInvoiceTaskEntries(
            $taskPayload,
            $task->fresh(),
            $supplier,
            $client,
            $agent->fresh(),
            $invoice,
            $invoice->invoice_number,
            $invoiceDetail,
            $payableAccount,
            $receivableAccount,
            $incomeAccount,
            $company->id,
            $branch->id
        );

        $this->assertNotNull($result, 'C4: an unresolvable draft company must still fall back to legacy — routing is unchanged.');
        $this->assertSame(3, DB::table('journal_entries')->where('company_id', $company->id)->count(), 'Legacy still runs, using the invoice-level company/branch, not the unresolved task-agent one.');

        Log::shouldHaveReceived('error')->once()->with(
            'accounting.company_unresolvable',
            Mockery::on(fn (array $context) => $context['feeder'] === 'chat.invoice_task'
                && $context['idempotency_key'] === 'chat:invoice_task:'.$invoiceDetail->id)
        );
        Log::shouldHaveReceived('info')->with('accounting.legacy_path', Mockery::type('array'));
    }

    /**
     * (7) W1.1 fix, C2 — REAL seeders (CoaSeeder + SystemAccountsSeeder, per this build's
     * SEEDERS-IN-TESTS rule — never a hand-inserted system_accounts row), ZERO markup: the
     * receivable/payable legs already balance on their own when the agent sells exactly at cost,
     * so the MARKUP_INCOME leg must be OMITTED entirely rather than rejected by PostingService's
     * own `amount > 0` rule. Proven WITHOUT MARKUP_INCOME needing to resolve to anything: a
     * zero-markup line never reaches PostingService's account resolver at all (the `amount > 0`
     * guard in the feeder omits the whole line before that), independent of whether MARKUP_INCOME
     * is mapped on this company's chart (it is, as of W1.3 — see
     * test_whatsapp_invoice_is_balanced() and test_on_path_negative_markup_persists_income_type_
     * not_expense() above for the nonzero positive/negative cases that DO exercise that mapping).
     */
    public function test_on_path_zero_markup_with_real_seeders_omits_markup_line(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures($company);

        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000, type: 'flight');
        $invprice = 100.000; // exactly at cost: markup == 0
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $legacyPayable = Account::factory()->create(['company_id' => $company->id]);
        $legacyReceivable = Account::factory()->create(['company_id' => $company->id]);
        $legacyIncome = Account::factory()->create(['company_id' => $company->id]);

        $result = app(ChatController::class)->postChatInvoiceTaskEntries(
            $taskPayload,
            $task->fresh(),
            $supplier,
            $client,
            $agent,
            $invoice,
            $invoice->invoice_number,
            $invoiceDetail,
            $legacyPayable,
            $legacyReceivable,
            $legacyIncome,
            $company->id,
            $branch->id
        );

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);

        $lines = DB::table('journal_entries')->where('company_id', $company->id)->get();
        $this->assertCount(2, $lines, 'Zero markup: only receivable + payable — the MARKUP_INCOME leg is omitted before account resolution ever runs.');
        $this->assertEqualsWithDelta(100.000, (float) $lines->sum('debit'), 0.0005);
        $this->assertEqualsWithDelta(100.000, (float) $lines->sum('credit'), 0.0005);
    }

    /**
     * (10) W1.1 fix, C5 — line attribution, ON vs OFF column sets. Legacy already writes
     * invoice_id/type/type_reference_id/name correctly (their fillable key names are right), but
     * its own 'invoiceDetail_id' array key is a dead-lettered typo against JournalEntry's real
     * `invoice_detail_id` fillable name (silently dropped by mass assignment — always NULL), and
     * legacy never writes task_id at all. The ON path (LineDraft's W1.1 attribution fields) closes
     * both gaps. Uses the REAL seeders and a zero-markup task (C2's OMIT branch) purely to keep
     * this test's own point (line attribution) independent of the markup amount.
     */
    public function test_on_path_line_attribution_closes_the_off_path_gaps(): void
    {
        // OFF: legacy's own column set.
        config(['accounting.engine.enabled' => false]);

        [$offCompany, $offBranch, $offAgent, $offClient, $offSupplier, $offInvoice] = $this->makeChatFixtures();
        $offTask = $this->makeTask($offCompany, $offAgent, $offClient, $offSupplier, total: 100.000);
        $offInvprice = 130.000;
        $offInvoiceDetail = $this->makeInvoiceDetail($offInvoice, $offTask, $offInvprice, 100.000);
        $offTaskPayload = $this->taskPayload($offTask, $offInvprice);

        $offPayable = Account::factory()->create(['company_id' => $offCompany->id]);
        $offReceivable = Account::factory()->create(['company_id' => $offCompany->id]);
        $offIncome = Account::factory()->create(['company_id' => $offCompany->id]);

        app(ChatController::class)->postChatInvoiceTaskEntries(
            $offTaskPayload,
            $offTask->fresh(),
            $offSupplier,
            $offClient,
            $offAgent,
            $offInvoice,
            $offInvoice->invoice_number,
            $offInvoiceDetail,
            $offPayable,
            $offReceivable,
            $offIncome,
            $offCompany->id,
            $offBranch->id
        );

        $offReceivableLine = DB::table('journal_entries')->where('company_id', $offCompany->id)->where('type', 'receivable')->first();
        $this->assertNotNull($offReceivableLine);
        $this->assertSame($offInvoice->id, (int) $offReceivableLine->invoice_id, 'Legacy writes invoice_id under its correct fillable name.');
        $this->assertSame($offClient->id, (int) $offReceivableLine->type_reference_id);
        $this->assertSame($offClient->first_name, $offReceivableLine->name);
        $this->assertNull($offReceivableLine->invoice_detail_id, "Legacy's own 'invoiceDetail_id' array key is a dead-lettered typo against the real fillable name — always NULL.");
        $this->assertNull($offReceivableLine->task_id, 'Legacy never writes task_id at all.');

        // ON: engine's column set, closing both OFF-path gaps.
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $branch, $agent, $client, $supplier, $invoice] = $this->makeChatFixtures($company);

        $task = $this->makeTask($company, $agent, $client, $supplier, total: 100.000, type: 'flight');
        $invprice = 100.000; // zero markup — keeps this test's own point (line attribution) independent of the markup amount
        $invoiceDetail = $this->makeInvoiceDetail($invoice, $task, $invprice, 100.000);
        $taskPayload = $this->taskPayload($task, $invprice);

        $legacyPayable = Account::factory()->create(['company_id' => $company->id]);
        $legacyReceivable = Account::factory()->create(['company_id' => $company->id]);
        $legacyIncome = Account::factory()->create(['company_id' => $company->id]);

        $result = app(ChatController::class)->postChatInvoiceTaskEntries(
            $taskPayload,
            $task->fresh(),
            $supplier,
            $client,
            $agent,
            $invoice,
            $invoice->invoice_number,
            $invoiceDetail,
            $legacyPayable,
            $legacyReceivable,
            $legacyIncome,
            $company->id,
            $branch->id
        );

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);

        $onLines = DB::table('journal_entries')->where('company_id', $company->id)->get();
        $this->assertCount(2, $onLines);

        // Paired by 'type' (not type_reference_id): client and supplier ids are independent
        // auto-increment counters and can coincidentally collide across their own tables.
        $onReceivableLine = $onLines->firstWhere('type', 'receivable');
        $onPayableLine = $onLines->firstWhere('type', 'payable');

        $this->assertNotNull($onReceivableLine);
        $this->assertNotNull($onPayableLine);

        $this->assertSame($invoice->id, (int) $onReceivableLine->invoice_id);
        $this->assertSame($invoiceDetail->id, (int) $onReceivableLine->invoice_detail_id, 'C5 fix: engine writes the CORRECT fillable name, unlike legacy.');
        $this->assertSame($task->id, (int) $onReceivableLine->task_id, 'C5 fix: engine writes task_id at all, unlike legacy.');
        $this->assertSame($client->id, (int) $onReceivableLine->type_reference_id);
        $this->assertSame($client->first_name, $onReceivableLine->name);

        $this->assertSame($invoice->id, (int) $onPayableLine->invoice_id);
        $this->assertSame($invoiceDetail->id, (int) $onPayableLine->invoice_detail_id);
        $this->assertSame($task->id, (int) $onPayableLine->task_id);
        $this->assertSame($supplier->id, (int) $onPayableLine->type_reference_id);
        $this->assertSame($supplier->name, $onPayableLine->name);
    }
}
