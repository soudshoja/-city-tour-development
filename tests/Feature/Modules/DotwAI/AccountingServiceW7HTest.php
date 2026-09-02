<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\DotwAI;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\AccountingService;
use App\Services\Accounting\AccountResolver;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\AccountingTestCase;

/**
 * W7.H (.planning/accounting-waves/w7/w7-brief.md §W7.H) --
 * app/Modules/DotwAI/Services/AccountingService.php through the seam.
 *
 * Covers:
 *  - ON sale (createAutoInvoiceForDeadline): one balanced INV document, Dr RECEIVABLE_CONTROL /
 *    Cr SERVICE_PAYABLE/hotel / Cr SERVICE_REVENUE/hotel, keyed dotw:{booking_id}:sale;
 *  - ON sale retry: idempotent, no double-post;
 *  - OFF sale: byte-identical to the pre-W7.H legacy body (raw JournalEntry pair, no Transaction
 *    row, no idempotency_key -- matching the exact shape the legacy code always produced);
 *  - ON cancellation, non-Task, prior sale exists: reverse()s the stale sale + posts a fee DBN
 *    (Dr RECEIVABLE_CONTROL / Cr VOID_FEE_INCOME), keyed dotw:{booking_id}:cancel;
 *  - ON cancellation, non-Task, no prior sale (pre-deadline cancel -- the common case): fee DBN
 *    only, reversal is a genuine no-op;
 *  - ON cancellation retry: idempotent (both the reversal and the fee DBN);
 *  - ON cancellation, Task-linked: delegates entirely to TaskStatusService::void() -- no
 *    standalone penalty Invoice, the task's own sale document is reversed and a VOID_FEE_INCOME
 *    DBN lands on the SAME carrying invoice;
 *  - OFF cancellation: byte-identical to the pre-W7.H legacy body (standalone penalty Invoice +
 *    raw JournalEntry pair).
 */
class AccountingServiceW7HTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client} */
    private function makeCompanyAgentClient(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'w7h-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $agent->clients()->attach($client->id);

        return [$company, $agent, $client];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function makeContext(Company $company, Agent $agent, string $track = DotwAIBooking::TRACK_B2B): DotwAIContext
    {
        $credentials = CompanyDotwCredential::create([
            'company_id' => $company->id,
            'dotw_username' => Crypt::encrypt('testuser'),
            'dotw_password' => Crypt::encrypt('testpass'),
            'dotw_company_code' => 'TEST',
            'markup_percent' => 0,
            'is_active' => true,
            'b2b_enabled' => true,
            'b2c_enabled' => false,
        ]);

        return new DotwAIContext(
            agent: $agent,
            companyId: $company->id,
            credentials: $credentials,
            track: $track,
            markupPercent: 0,
            b2bEnabled: true,
            b2cEnabled: false,
        );
    }

    private function makeBooking(Company $company, array $overrides = []): DotwAIBooking
    {
        return DotwAIBooking::create(array_merge([
            'prebook_key' => 'DOTWAI-W7H-'.uniqid(),
            'booking_ref' => 'DOTW-REF-'.uniqid(),
            'company_id' => $company->id,
            'agent_phone' => '96599800027',
            'hotel_id' => 'H001',
            'hotel_name' => 'W7H Test Hotel',
            'city_code' => 'DXB',
            'check_in' => now()->addDays(30),
            'check_out' => now()->addDays(35),
            'original_total_fare' => 100.00,
            'original_currency' => 'KWD',
            'display_total_fare' => 140.00,
            'display_currency' => 'KWD',
            'track' => DotwAIBooking::TRACK_B2B,
            'status' => DotwAIBooking::STATUS_CONFIRMED,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // ON -- sale (createAutoInvoiceForDeadline)
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_sale_posts_one_balanced_document_with_supplier_payable_and_margin(): void
    {
        [$company, $agent] = $this->makeCompanyAgentClient();
        Supplier::factory()->create(['name' => 'DOTW']);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $booking = $this->makeBooking($company);

        (new AccountingService)->createAutoInvoiceForDeadline($booking);

        $key = 'dotw:'.$booking->id.':sale';
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first();

        $this->assertNotNull($posted, 'ON path must post a real engine document under the stable dotw:{id}:sale key.');
        $this->assertSame('INV', $posted->doc_type);
        $this->assertEqualsWithDelta(0.0, (float) $posted->total_debit - (float) $posted->total_credit, 0.0005, 'Document must balance.');

        $receivable = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        $payable = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');
        $revenue = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'hotel');

        $receivableLine = JournalEntry::where('transaction_id', $posted->id)->where('account_id', $receivable->id)->first();
        $payableLine = JournalEntry::where('transaction_id', $posted->id)->where('account_id', $payable->id)->first();
        $revenueLine = JournalEntry::where('transaction_id', $posted->id)->where('account_id', $revenue->id)->first();

        $this->assertNotNull($receivableLine, 'Dr RECEIVABLE_CONTROL must be posted for the full sell.');
        $this->assertNotNull($payableLine, 'Cr SERVICE_PAYABLE/hotel must be posted for the real supplier cost -- the exact gap legacy left open.');
        $this->assertNotNull($revenueLine, 'Cr SERVICE_REVENUE/hotel must carry the margin.');

        $this->assertEqualsWithDelta(140.0, (float) $receivableLine->debit, 0.0005);
        $this->assertEqualsWithDelta(100.0, (float) $payableLine->credit, 0.0005);
        $this->assertEqualsWithDelta(40.0, (float) $revenueLine->credit, 0.0005);

        $this->assertSame(3, JournalEntry::where('transaction_id', $posted->id)->count(), 'Exactly one balanced 3-line document.');

        $booking->refresh();
        $invoiceDetail = InvoiceDetail::where('invoice_id', $booking->invoice_id)->first();
        $this->assertNotNull($invoiceDetail);
        $this->assertEqualsWithDelta(100.0, (float) $invoiceDetail->supplier_price, 0.0005, 'InvoiceDetail must carry the real supplier cost.');
    }

    public function test_on_sale_retry_is_idempotent(): void
    {
        [$company] = $this->makeCompanyAgentClient();
        Supplier::factory()->create(['name' => 'DOTW']);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $booking = $this->makeBooking($company);

        $service = new AccountingService;
        $service->createAutoInvoiceForDeadline($booking);
        $service->createAutoInvoiceForDeadline($booking->fresh());

        $key = 'dotw:'.$booking->id.':sale';
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->count(),
            'A retried sale attempt must resolve to the SAME engine document, never a second one.'
        );
    }

    public function test_off_sale_matches_legacy_exactly(): void
    {
        [$company] = $this->makeCompanyAgentClient();
        config(['accounting.engine.enabled' => false]);

        // Legacy name-LIKE lookup targets -- mirrors CancellationServiceTest's own OFF-path seed,
        // via the factory (accounts.level/actual_balance/budget_balance/variance are all NOT NULL
        // with no DB default -- CancellationServiceTest's own raw Account::create() call, with
        // only company_id/name/code set, could never have satisfied that constraint either).
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Client Receivable', 'code' => 'RECV-001']);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Revenue Account', 'code' => 'REV-001']);

        $booking = $this->makeBooking($company);

        (new AccountingService)->createAutoInvoiceForDeadline($booking);

        // Legacy shape, verbatim: 2 raw JournalEntry rows, NO Transaction row at all (the legacy
        // body never touched the transactions table), NEITHER row carrying an idempotency_key.
        $entries = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->where('type', 'booking')->get();
        $this->assertCount(2, $entries);
        $this->assertEqualsWithDelta(140.0, (float) $entries->firstWhere('debit', '>', 0)->debit, 0.0005);
        $this->assertEqualsWithDelta(140.0, (float) $entries->firstWhere('credit', '>', 0)->credit, 0.0005);
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'OFF path must never create a Transaction row -- that is an engine-only concept.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // ON -- cancellation (createCancellationEntries), non-Task
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_cancellation_reverses_the_stale_sale_and_posts_the_fee(): void
    {
        [$company, $agent] = $this->makeCompanyAgentClient();
        Supplier::factory()->create(['name' => 'DOTW']);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $booking = $this->makeBooking($company);
        $service = new AccountingService;
        $service->createAutoInvoiceForDeadline($booking);
        $booking->refresh();

        $saleKey = 'dotw:'.$booking->id.':sale';
        $saleTransaction = Transaction::withoutGlobalScopes()->where('idempotency_key', $saleKey)->first();
        $this->assertNotNull($saleTransaction);

        $context = $this->makeContext($company, $agent);
        $service->createCancellationEntries($booking, 20.0, $context);

        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->first();
        $this->assertNotNull($reversal, 'The stale raw sale must have been reversed via PostingService::reverse().');
        $this->assertSame('VOID', $reversal->bsptype);

        $cancelKey = 'dotw:'.$booking->id.':cancel';
        $feeDoc = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $cancelKey)->first();
        $this->assertNotNull($feeDoc, 'The fee DBN must be posted under the stable dotw:{id}:cancel key.');
        $this->assertSame('DBN', $feeDoc->doc_type);
        $this->assertEqualsWithDelta(0.0, (float) $feeDoc->total_debit - (float) $feeDoc->total_credit, 0.0005);

        $receivable = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        $voidFeeIncome = app(AccountResolver::class)->resolve('VOID_FEE_INCOME', $company->id);
        $receivableLine = JournalEntry::where('transaction_id', $feeDoc->id)->where('account_id', $receivable->id)->first();
        $feeLine = JournalEntry::where('transaction_id', $feeDoc->id)->where('account_id', $voidFeeIncome->id)->first();
        $this->assertNotNull($receivableLine);
        $this->assertNotNull($feeLine);
        $this->assertEqualsWithDelta(20.0, (float) $receivableLine->debit, 0.0005);
        $this->assertEqualsWithDelta(20.0, (float) $feeLine->credit, 0.0005);
    }

    public function test_on_cancellation_before_any_sale_only_posts_the_fee(): void
    {
        [$company, $agent] = $this->makeCompanyAgentClient();
        Supplier::factory()->create(['name' => 'DOTW']);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // Pre-deadline cancellation: createAutoInvoiceForDeadline() never ran, so dotw:{id}:sale
        // was never posted -- the common case per the class docblock.
        $booking = $this->makeBooking($company);
        $context = $this->makeContext($company, $agent);

        (new AccountingService)->createCancellationEntries($booking, 15.0, $context);

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('reversal_of_transaction_id')->count(),
            'Nothing existed to reverse -- reversal must be a genuine no-op, not an error.'
        );

        $cancelKey = 'dotw:'.$booking->id.':cancel';
        $feeDoc = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $cancelKey)->first();
        $this->assertNotNull($feeDoc);
        $this->assertEqualsWithDelta(0.0, (float) $feeDoc->total_debit - (float) $feeDoc->total_credit, 0.0005);
    }

    public function test_on_cancellation_retry_is_idempotent(): void
    {
        [$company, $agent] = $this->makeCompanyAgentClient();
        Supplier::factory()->create(['name' => 'DOTW']);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $booking = $this->makeBooking($company);
        $service = new AccountingService;
        $service->createAutoInvoiceForDeadline($booking);
        $booking->refresh();

        $saleKey = 'dotw:'.$booking->id.':sale';
        $saleTransaction = Transaction::withoutGlobalScopes()->where('idempotency_key', $saleKey)->first();

        $context = $this->makeContext($company, $agent);
        $service->createCancellationEntries($booking, 20.0, $context);
        $service->createCancellationEntries($booking->fresh(), 20.0, $context);

        $cancelKey = 'dotw:'.$booking->id.':cancel';
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $cancelKey)->count(),
            'A retried cancellation must resolve to the SAME fee document, never a second one.'
        );
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->count(),
            'reverse() is idempotent by construction -- a retried cancellation must never produce a second reversal.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // ON -- cancellation, Task-linked: delegates to TaskStatusService::void()
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_cancellation_delegates_to_task_status_service_void_when_task_linked(): void
    {
        [$company, $agent, $client] = $this->makeCompanyAgentClient();
        $supplier = Supplier::factory()->create(['name' => 'W7H Task Supplier']);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
            'status' => 'issued',
            'reference' => 'DOTW-CONF-'.uniqid(),
            'price' => 140.0,
            'total' => 100.0,
        ]);

        $result = (new TaskStatusService)->issue($task);
        $this->assertTrue($result['success'] ?? false, json_encode($result));

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $this->assertNotNull($invoiceDetail);

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':sale')
            ->first();
        $this->assertNotNull($saleTransaction, 'Precondition: the task must have a real engine-posted sale to reverse.');

        $booking = $this->makeBooking($company, ['task_id' => $task->id]);
        $context = $this->makeContext($company, $agent);

        (new AccountingService)->createCancellationEntries($booking, 30.0, $context);

        $task->refresh();
        $this->assertSame('void', $task->ticket_status);

        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->first();
        $this->assertNotNull($reversal, 'Task-linked cancellation must reverse the TASK\'s own sale document.');
        $this->assertSame('VOID', $reversal->bsptype);

        $voidFeeIncome = app(AccountResolver::class)->resolve('VOID_FEE_INCOME', $company->id);
        $feeLine = JournalEntry::where('invoice_id', $invoiceDetail->invoice_id)->where('account_id', $voidFeeIncome->id)->first();
        $this->assertNotNull($feeLine, 'The void fee DBN must land on the SAME carrying invoice the task\'s sale used.');
        $this->assertEqualsWithDelta(30.0, (float) $feeLine->credit, 0.0005);

        $this->assertSame(
            0,
            Invoice::where('label', 'LIKE', '%Cancellation Penalty%')->count(),
            'The Task-linked branch must not also create a standalone penalty Invoice -- void() already owns the fee.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // OFF -- cancellation
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_cancellation_matches_legacy_exactly(): void
    {
        [$company, $agent] = $this->makeCompanyAgentClient();
        config(['accounting.engine.enabled' => false]);

        Account::factory()->create(['company_id' => $company->id, 'name' => 'Client Receivable', 'code' => 'RECV-002']);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Revenue Account', 'code' => 'REV-002']);

        $booking = $this->makeBooking($company, ['track' => DotwAIBooking::TRACK_B2B]);
        $context = $this->makeContext($company, $agent, DotwAIBooking::TRACK_B2B);

        (new AccountingService)->createCancellationEntries($booking, 25.0, $context);

        $invoice = Invoice::where('label', 'LIKE', '%'.$booking->prebook_key.'%')->first();
        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(25.0, (float) $invoice->amount, 0.0005);
        // Legacy B2B behaviour preserved: the penalty invoice is stamped PAID (credit already
        // deducted elsewhere by CancellationService's own caller-side credit refund logic).
        $this->assertSame(\App\Enums\InvoiceStatus::PAID->value, $invoice->status);

        $entries = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->where('type', 'cancellation')->get();
        $this->assertCount(2, $entries);
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'OFF path must never create a Transaction row.'
        );
    }
}
