<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RefundPostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w4r. W4.R (.planning/accounting-waves/w4/w4-brief.md §4) — RefundPostingService, the
 * refund document's own posting composition (a)-(f).
 */
class RefundPostingServiceTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config([
            'accounting.engine.enabled' => false,
            'accounting.engine.agent_loss_recovery_enabled' => false,
        ]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice,
     *               6: InvoiceDetail}
     */
    private function makeFixture(string $serviceType = 'flight'): array
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
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => $serviceType,
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

        return [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    /** Posts a real sale, exactly the way InvoiceController::postSaleJournalEntries() does. */
    private function postRealSale(
        Company $company,
        Agent $agent,
        Client $client,
        Supplier $supplier,
        Task $task,
        Invoice $invoice,
        InvoiceDetail $invoiceDetail,
        float $sellAmount,
        float $costAmount
    ): Transaction {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $task->type,
            sellAmount: $sellAmount,
            costAmount: $costAmount,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $client->id,
            clientName: $client->full_name,
            supplierId: $supplier->id,
            supplierName: $supplier->name,
            agentId: $agent->id,
            agentName: $agent->name,
            invoiceId: $invoice->id,
            invoiceDetailId: $invoiceDetail->id,
            taskId: $task->id,
        ));

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: now(),
            narration: 'Sale',
            lines: $lines,
            idempotencyKey: 'invoice-detail:'.$invoiceDetail->id.':sale',
            invoiceId: $invoice->id,
        );

        return app(PostingService::class)->post($draft)->transaction;
    }

    /** Posts a real agent-commission JV, exactly the shape InvoiceController::addJournalEntry() posts. */
    private function postRealCommission(
        Company $company,
        Agent $agent,
        Invoice $invoice,
        InvoiceDetail $invoiceDetail,
        Task $task,
        float $commission
    ): Transaction {
        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'JV',
            subType: 'AGENT_COMMISSION',
            docDate: now(),
            narration: 'Agent commission',
            lines: [
                new LineDraft(
                    purposeCode: 'SALARY_EXPENSE',
                    accountId: null,
                    side: 'debit',
                    amount: $commission,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $commission,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_COMMISSION_EXPENSE',
                    partyAccountRef: $agent->id,
                    invoiceId: $invoice->id,
                    invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id,
                ),
                new LineDraft(
                    purposeCode: 'SALARY_PAYABLE',
                    accountId: null,
                    side: 'credit',
                    amount: $commission,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $commission,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_COMMISSION_PAYABLE',
                    partyAccountRef: $agent->id,
                    invoiceId: $invoice->id,
                    invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id,
                ),
            ],
            idempotencyKey: 'invoice-detail:'.$invoiceDetail->id.':agent-commission',
            invoiceId: $invoice->id,
        );

        return app(PostingService::class)->post($draft)->transaction;
    }

    private function makeRefund(Company $company, Agent $agent, Invoice $invoice, array $overrides = []): Refund
    {
        return Refund::create(array_merge([
            'refund_number' => 'REF-TEST-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ], $overrides));
    }

    /**
     * Posts a real client cash payment against the invoice, exactly the Dr instrument / Cr
     * RECEIVABLE_CONTROL shape ReceiptVoucherController posts for a Cash/Bank receipt allocated
     * to an invoice -- used by the W7.P net-balance tests below to put the refund/disposition
     * scenario through the SAME running-balance shape the polarity audit's own worked example
     * used (sale -> full cash payment -> refund), rather than the disposition-only tests above
     * (which never model a payment at all and so cannot distinguish correct from backwards
     * polarity by looking at AR's final net).
     */
    private function postRealPayment(Company $company, Agent $agent, Invoice $invoice, InvoiceDetail $invoiceDetail, Task $task, float $amount): Transaction
    {
        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'RV',
            subType: 'INVOICE',
            docDate: now(),
            narration: 'Client payment',
            lines: [
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND',
                    accountId: null,
                    side: 'debit',
                    amount: $amount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'RECEIPT',
                    partyAccountRef: null,
                    invoiceId: $invoice->id,
                ),
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL',
                    accountId: null,
                    side: 'credit',
                    amount: $amount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'CUSTOMERCREDITED',
                    partyAccountRef: $task->client_id,
                    invoiceId: $invoice->id,
                    invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id,
                ),
            ],
            idempotencyKey: 'refund-test:payment:'.$invoiceDetail->id,
            invoiceId: $invoice->id,
        );

        return app(PostingService::class)->post($draft)->transaction;
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /** Dr-Cr net -- for an asset-normal account (e.g. 1351 Accounts Receivable Control). */
    private function netDebit(int $companyId, string $code): float
    {
        $account = $this->accountByCode($companyId, $code);
        $debit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('account_id', $account->id)->sum('debit');
        $credit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('account_id', $account->id)->sum('credit');

        return round($debit - $credit, 3);
    }

    /** Cr-Dr net -- for a liability/income-normal account (e.g. 2632 Client Advance). */
    private function netCredit(int $companyId, string $code): float
    {
        return round(-1 * $this->netDebit($companyId, $code), 3);
    }

    /** Dr-Cr / Cr-Dr net for an arbitrary Account instance (used for a company-configured payout leaf with no fixed code, e.g. REFUND_PAYOUT_CASH_BANK). */
    private function netDebitForAccount(Account $account): float
    {
        $debit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('account_id', $account->id)->sum('debit');
        $credit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('account_id', $account->id)->sum('credit');

        return round($debit - $credit, 3);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (a) CRN
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_crn_reverses_engine_posted_sale(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $saleTransaction = $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $saleTransaction->refresh();
        $this->assertSame('reversed', $saleTransaction->posting_status, 'The original sale must be marked reversed, never deleted.');

        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->first();
        $this->assertNotNull($reversal, 'A reversal document must exist for the sale.');
        // PostingService::reverse() always mints doc_type='REV' (its own fixed convention, shared
        // by every other feeder's reversal in this codebase) -- NOT a dedicated 'CRN' series. Only
        // the standalone legacy-sale path (see the next test) posts under docType 'CRN' — see this
        // class's own report for this documented deviation from the brief's literal "CRN own
        // serial series" wording.
        $this->assertSame('REV', $reversal->doc_type);

        $this->assertEqualsWithDelta(0.0, (float) $reversal->total_debit - (float) $reversal->total_credit, 0.0005, 'CRN must balance.');
    }

    public function test_crn_posts_standalone_document_for_legacy_sale_with_no_idempotency_key(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // No sale posted through the engine at all -- simulates a pre-engine (legacy) sale. Only
        // a bare legacy Transaction+JournalEntry pair exists (no idempotency_key), matching
        // ct-refund-map.md's description of the pre-cutover shape.
        $legacyTransaction = Transaction::create([
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'Legacy invoice',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);
        DB::table('journal_entries')->insert([
            [
                'transaction_id' => $legacyTransaction->id,
                'company_id' => $company->id,
                'branch_id' => $agent->branch_id,
                'account_id' => Account::where('company_id', $company->id)->where('name', 'Clients')->firstOrFail()->id,
                'invoice_detail_id' => $invoiceDetail->id,
                'transaction_date' => now(),
                'description' => 'Legacy sale',
                'debit' => 100,
                'credit' => 0,
                'name' => 'Clients',
                'type' => 'receivable',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'transaction_id' => $legacyTransaction->id,
                'company_id' => $company->id,
                'branch_id' => $agent->branch_id,
                'account_id' => Account::where('company_id', $company->id)->where('name', 'Flight Booking Revenue')->firstOrFail()->id,
                'invoice_detail_id' => $invoiceDetail->id,
                'transaction_date' => now(),
                'description' => 'Legacy sale',
                'debit' => 0,
                'credit' => 100,
                'name' => 'Flight Booking Revenue',
                'type' => 'income',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $legacyTransaction->refresh();
        $this->assertSame('posted', $legacyTransaction->posting_status, 'The legacy row must never be mutated/reversed structurally -- it has no engine posting_status field.');

        $crn = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'like', 'refund:%:crn-legacy:%')
            ->first();

        $this->assertNotNull($crn, 'A standalone legacy CRN must be posted.');
        $this->assertSame((int) $legacyTransaction->id, (int) $crn->reversal_of_transaction_id, 'Must reference the legacy transaction structurally.');
        $this->assertEqualsWithDelta(0.0, (float) $crn->total_debit - (float) $crn->total_credit, 0.0005);
    }

    public function test_crn_is_idempotent_on_retry(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);
        $countAfterFirst = DB::table('transactions')->count();

        app(RefundPostingService::class)->post($refund->fresh(), null);
        $countAfterSecond = DB::table('transactions')->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Retrying post() must not create any new documents.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) Recharge lines
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_recharge_lines_post_receivable_and_recovery_plus_fee(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 5.000, // our fee -> SERVICE_FEE_INCOME
            'supplier_charge' => 10.000, // penalty recharge -> PENALTY_PASSTHROUGH_RECOVERY
            'total_refund_to_client' => 85.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $recharge = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':recharge')
            ->first();

        $this->assertNotNull($recharge, 'A recharge document must post when either quantity is chargeable.');
        $this->assertEqualsWithDelta(15.000, (float) $recharge->total_debit, 0.0005, 'Dr AR = penalty recharge (10) + fee (5).');
        $this->assertEqualsWithDelta(0.0, (float) $recharge->total_debit - (float) $recharge->total_credit, 0.0005);

        $penaltyAccount = Account::where('company_id', $company->id)->where('name', 'Penalty Pass-Through Recovery')->firstOrFail();
        $feeAccount = Account::where('company_id', $company->id)->where('name', 'Service Fee Income')->firstOrFail();

        $penaltyLine = DB::table('journal_entries')->where('transaction_id', $recharge->id)->where('account_id', $penaltyAccount->id)->first();
        $feeLine = DB::table('journal_entries')->where('transaction_id', $recharge->id)->where('account_id', $feeAccount->id)->first();

        $this->assertNotNull($penaltyLine, 'PENALTY_PASSTHROUGH_RECOVERY leg must exist as its own line.');
        $this->assertEqualsWithDelta(10.000, (float) $penaltyLine->credit, 0.0005);

        $this->assertNotNull($feeLine, 'SERVICE_FEE_INCOME leg must exist as its own line -- the agency\'s own fee is income, not pass-through recovery.');
        $this->assertEqualsWithDelta(5.000, (float) $feeLine->credit, 0.0005);
    }

    /**
     * W4.R verify-fix (finding #2, HIGH). Uses the brief/verify item's own worked example
     * verbatim: sale 100 / cost 90 / penalty 20 / fee 5 -> Dr AR 25 / Cr PENALTY_PASSTHROUGH_
     * RECOVERY 20 / Cr SERVICE_FEE_INCOME 5. client net = 100 - 20 - 5 = 75.
     */
    public function test_recharge_lines_worked_example_from_brief(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 90.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 90.000,
            'refund_fee_to_client' => 5.000,
            'supplier_charge' => 20.000,
            'total_refund_to_client' => 75.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $recharge = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':recharge')
            ->first();

        $this->assertEqualsWithDelta(25.000, (float) $recharge->total_debit, 0.0005);

        $penaltyAccount = Account::where('company_id', $company->id)->where('name', 'Penalty Pass-Through Recovery')->firstOrFail();
        $feeAccount = Account::where('company_id', $company->id)->where('name', 'Service Fee Income')->firstOrFail();

        $this->assertEqualsWithDelta(20.000, (float) DB::table('journal_entries')->where('transaction_id', $recharge->id)->where('account_id', $penaltyAccount->id)->value('credit'), 0.0005);
        $this->assertEqualsWithDelta(5.000, (float) DB::table('journal_entries')->where('transaction_id', $recharge->id)->where('account_id', $feeAccount->id)->value('credit'), 0.0005);
    }

    /** Only the fee is chargeable (no airline penalty recharge) -- single credit leg. */
    public function test_recharge_lines_fee_only_no_penalty(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 5.000,
            'supplier_charge' => 0,
            'total_refund_to_client' => 95.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $recharge = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':recharge')
            ->first();

        $this->assertNotNull($recharge);
        $this->assertEqualsWithDelta(5.000, (float) $recharge->total_debit, 0.0005);

        $penaltyAccount = Account::where('company_id', $company->id)->where('name', 'Penalty Pass-Through Recovery')->first();
        $this->assertNull(
            DB::table('journal_entries')->where('transaction_id', $recharge->id)->where('account_id', $penaltyAccount?->id)->first(),
            'No pass-through-recovery leg when no penalty is being recharged.'
        );
    }

    public function test_recharge_lines_are_a_noop_when_nothing_chargeable(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $recharge = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':recharge')
            ->first();

        $this->assertNull($recharge, 'No recharge document when nothing is chargeable.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (c) Supplier credit item
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_supplier_credit_item_balances_and_tags_bsptype_refund(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        $detail = RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 10.000, // penalty
            'total_refund_to_client' => 90.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $supplierCredit = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':supplier-credit:'.$detail->id)
            ->first();

        $this->assertNotNull($supplierCredit);
        $this->assertSame('REFUND', $supplierCredit->bsptype, 'transactions.bsptype must be REFUND for the supplier credit item.');
        $this->assertEqualsWithDelta(0.0, (float) $supplierCredit->total_debit - (float) $supplierCredit->total_credit, 0.0005, 'Must balance: net (50) + penalty (10) = full cost (60).');
        $this->assertEqualsWithDelta(60.000, (float) $supplierCredit->total_credit, 0.0005, 'Cr COGS must equal the FULL original cost.');
    }

    public function test_supplier_refund_amount_override_is_honoured_and_document_still_balances(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        $detail = RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 10.000,
            'supplier_refund_amount' => 45.000, // operator override, differs from cost - penalty (50)
            'total_refund_to_client' => 90.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $supplierCredit = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':supplier-credit:'.$detail->id)
            ->first();

        // net (45, overridden) + derived penalty-cost (60 - 45 = 15) = full cost (60).
        $this->assertEqualsWithDelta(60.000, (float) $supplierCredit->total_debit, 0.0005);
        $this->assertEqualsWithDelta(60.000, (float) $supplierCredit->total_credit, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $supplierCredit->total_debit - (float) $supplierCredit->total_credit, 0.0005, 'Must still balance even with an overridden supplier_refund_amount.');

        $penaltyAccount = Account::where('company_id', $company->id)->where('name', 'Refund Penalty Cost')->firstOrFail();
        $penaltyLine = DB::table('journal_entries')->where('transaction_id', $supplierCredit->id)->where('account_id', $penaltyAccount->id)->first();
        $this->assertNotNull($penaltyLine);
        $this->assertEqualsWithDelta(15.000, (float) $penaltyLine->debit, 0.0005, 'Derived penalty-cost (60 - 45) must be 15, not the client-facing supplier_charge (10).');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (d) Commission un-earn
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_commission_unearn_reverses_the_live_commission_jv(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        $commissionTransaction = $this->postRealCommission($company, $agent, $invoice, $invoiceDetail, $task, 6.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $commissionTransaction->refresh();
        $this->assertSame('reversed', $commissionTransaction->posting_status, 'The commission JV must be reversed (un-earned) by default.');
    }

    public function test_commission_unearn_is_noop_when_no_commission_was_ever_earned(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        // No commission JV posted at all -- e.g. agent-purchased ticket, Q-20.5 default off.

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        // Must not throw -- a no-op, not an error.
        app(RefundPostingService::class)->post($refund->fresh(), null);

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'like', '%agent-commission%')->count(),
            'No commission document may exist or be created when none was ever earned.'
        );
    }

    public function test_commission_unearn_skipped_when_option_is_not_un_earn(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.commission_on_refunded_sale',
            'value' => 'keep',
            'type' => 'string',
        ]);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        $commissionTransaction = $this->postRealCommission($company, $agent, $invoice, $invoiceDetail, $task, 6.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $commissionTransaction->refresh();
        $this->assertSame('posted', $commissionTransaction->posting_status, 'commission_on_refunded_sale=keep must leave the live commission JV untouched.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (d) second half -- fresh JV/AGENT_COMMISSION on the refund event's OWN margin
    // (postCommissionEarnForRefundDetail()), gated by `commissionable_fee_types` (W4.U verify-fix,
    // MEDIUM: this company option was persisted by SettingController but never read by any
    // posting logic until this fix).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_commission_earn_is_noop_by_default_when_service_type_is_not_commissionable(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $agent->update(['commission' => 0.15]);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        // commissionable_fee_types defaults to [] (empty) -- no Setting row created.

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 20.000,
            'supplier_charge' => 0,
            'total_refund_to_client' => 80.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'refund:'.$refund->id.':commission-earn:'.$refund->refundDetails()->first()->id)->count(),
            'w4-brief.md §4d: fees are NOT commissionable by default -- commissionable_fee_types=[] must be a true no-op.'
        );
    }

    public function test_commission_earn_posts_fresh_jv_when_service_type_is_commissionable(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture('flight');
        $agent->update(['commission' => 0.15]);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.commissionable_fee_types',
            'value' => json_encode(['flight']),
            'type' => 'json',
        ]);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        $detail = RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 20.000,
            'supplier_charge' => 0,
            'total_refund_to_client' => 80.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $commissionEarn = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':commission-earn:'.$detail->id)
            ->first();

        $this->assertNotNull($commissionEarn, 'A commissionable service type must post a fresh JV/AGENT_COMMISSION on the refund fee.');
        $this->assertEqualsWithDelta(3.000, (float) $commissionEarn->total_debit, 0.0005, '0.15 rate x 20.000 fee = 3.000.');
        $this->assertSame('AGENT_COMMISSION', $commissionEarn->sub_type);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (e) Clawback
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_clawback_posts_unconditional_leg_when_amount_entered(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['airline_clawback_amount' => 12.000]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $clawback = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':clawback')
            ->first();

        $this->assertNotNull($clawback, 'Clawback must post unconditionally when an amount is entered, independent of bearer.');
        $this->assertEqualsWithDelta(12.000, (float) $clawback->total_debit, 0.0005);

        $clawbackAccount = Account::where('company_id', $company->id)->where('name', 'Airline Refund Clawback')->firstOrFail();
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $clawback->id)->where('account_id', $clawbackAccount->id)->count());
    }

    public function test_clawback_bearer_recovery_hook_noop_by_default_and_throws_when_flipped_on(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['airline_clawback_amount' => 12.000]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $this->assertFalse((bool) config('accounting.engine.agent_loss_recovery_enabled'), 'Fixture sanity: the hook must default OFF.');

        // Disabled: succeeds, no throw.
        app(RefundPostingService::class)->post($refund->fresh(), null);

        config(['accounting.engine.agent_loss_recovery_enabled' => true]);

        $refund2 = $this->makeRefund($company, $agent, $invoice, ['airline_clawback_amount' => 5.000]);
        RefundDetail::create([
            'refund_id' => $refund2->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $this->expectException(\RuntimeException::class);
        app(RefundPostingService::class)->post($refund2->fresh(), null);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (f) Disposition
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_disposition_defaults_to_credit_2632(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        // W7.P: full cash payment BEFORE the refund -- the exact running-balance scenario the
        // polarity audit's own worked example and this fix's docblock use. Without this step AR
        // never carries a genuine credit balance for the disposition to clear, so a net-balance
        // assertion could not distinguish correct polarity from the backwards shape (see this
        // test's own net assertions below).
        $this->postRealPayment($company, $agent, $invoice, $invoiceDetail, $task, 100.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['method' => 'Credit']);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $refund->refresh();
        $this->assertSame(Refund::DISPOSITION_CREDIT, $refund->disposition);
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->status);

        $disposition = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':disposition')
            ->first();
        $this->assertNotNull($disposition);

        $clientAdvance = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Client'))
            ->first();
        $this->assertNotNull($clientAdvance, 'CLIENT_ADVANCE (2632) leaf must exist.');
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $disposition->id)->where('account_id', $clientAdvance->id)->count());

        // W7.P net-balance assertions (refund-disposition-polarity-audit.md): after
        // sale -> full payment -> credit-disposition refund, AR must net to exactly 0 (the sale
        // was fully paid then fully reversed) and 2632 must net to a CREDIT of the full client
        // amount (the correct liability-side balance for "client is owed 100 in credit"). Before
        // the W7.P fix these were -200 (AR doubly wrong) and +100 debit (2632 wrongly a debit
        // balance) respectively -- see this method's own docblock and
        // refund-disposition-polarity-audit.md §5 for the measured pre-fix numbers.
        $this->assertEqualsWithDelta(0.0, $this->netDebit($company->id, '1351'), 0.0005, 'AR (1351) must net to 0 after sale -> full payment -> credit-disposition refund.');
        $this->assertEqualsWithDelta(100.0, $this->netCredit($company->id, '2632'), 0.0005, 'CLIENT_ADVANCE (2632) must net to a CREDIT of the full refunded amount.');

        // W4.R verify-fix (finding #1, HIGH): the 'credit' disposition must dual-write
        // App\Models\Credit alongside the 2632 JV -- w4-brief.md §4 "Credit row is a VIEW of 2632
        // movements, never a second source of truth (write both in one txn)". This is what
        // Credit::getAvailableBalanceByRefund() / the client credit-statement views actually read.
        $credit = \App\Models\Credit::where('refund_id', $refund->id)->first();
        $this->assertNotNull($credit, 'A Credit row must be written for the default credit disposition.');
        $this->assertSame($client->id, $credit->client_id);
        $this->assertSame(\App\Models\Credit::REFUND, $credit->type);
        $this->assertEqualsWithDelta(100.000, (float) $credit->amount, 0.0005);
    }

    /**
     * W4.R verify-fix round 2 (finding B, HIGH): a prior build's Credit::create() dual-write in
     * postDisposition() had no idempotency guard at all -- calling RefundPostingService::post()
     * twice on an already-completed CREDIT-disposition refund (post()'s own status guard
     * deliberately keeps STATUS_COMPLETED postable so a retry is "never refused") wrote a SECOND
     * Credit row for the same 2632 movement while the JV itself stayed correctly deduplicated,
     * silently double-crediting the client's balance. Proves the fix: post() twice -> exactly one
     * Credit row, one disposition transaction, and the client's available balance is unchanged.
     */
    public function test_disposition_credit_dual_write_is_idempotent_on_retry(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['method' => 'Credit']);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        // First post(): creates the JV + exactly one Credit row.
        app(RefundPostingService::class)->post($refund->fresh(), null);

        $this->assertSame(1, \App\Models\Credit::where('refund_id', $refund->id)->count());
        $transactionCountAfterFirst = DB::table('transactions')->count();

        // Retry: refund is now STATUS_COMPLETED, which post()'s own status guard deliberately
        // keeps postable (documented as "never refused"). This must be a true no-op.
        app(RefundPostingService::class)->post($refund->fresh(), null);

        $this->assertSame(
            1,
            \App\Models\Credit::where('refund_id', $refund->id)->count(),
            'Retrying post() must not write a second Credit row -- it silently double-credits the client.'
        );
        $this->assertSame(
            $transactionCountAfterFirst,
            DB::table('transactions')->count(),
            'Retrying post() must not create any new ledger documents either.'
        );
        $this->assertEqualsWithDelta(
            100.000,
            (float) \App\Models\Credit::getAvailableBalanceByRefund($refund->id),
            0.0005,
            "The client's available refund-credit balance must still read exactly the original amount after a retry."
        );
    }

    /**
     * W4.R verify-fix round 3 (finding #3, MEDIUM): the sequential-retry guard above only closes
     * that ONE race -- two genuinely CONCURRENT callers could both read `$dispositionAlreadyPosted
     * = false` before either transaction commits. Fixed by locking the refund row
     * (`Refund::lockForUpdate()`) and making the existence check itself a locking read, so a
     * second caller serializes behind the first (see postDisposition()'s own docblock for the
     * full mechanism and why this choice, not a unique index, was made).
     *
     * True concurrent transactions cannot be exercised in-process here: PHPUnit runs this suite
     * single-threaded/synchronously on Windows (no pcntl, no real second connection racing this
     * one mid-transaction), so a genuine two-connection race cannot be reproduced inside one test
     * method. What IS proven instead, precisely per the verify criterion's own fallback
     * ("document precisely what was proven — SQL contains FOR UPDATE"): the actual SQL
     * postDisposition() executes against BOTH the `refunds` row lock and the idempotency-key
     * existence check carries a `for update` locking clause — captured via a real query listener,
     * not asserted from reading the source.
     */
    public function test_disposition_uses_locking_reads_for_the_refund_row_and_existence_check(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['method' => 'Credit']);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $capturedSql = [];
        DB::listen(function ($query) use (&$capturedSql) {
            $capturedSql[] = $query->sql;
        });

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $refundLockQueries = array_filter(
            $capturedSql,
            fn (string $sql) => str_contains(strtolower($sql), 'from `refunds`') && str_contains(strtolower($sql), 'for update')
        );
        $this->assertNotEmpty($refundLockQueries, 'postDisposition() must lock the refund row with a real "for update" query.');

        $transactionLockQueries = array_filter(
            $capturedSql,
            fn (string $sql) => str_contains(strtolower($sql), 'from `transactions`')
                && str_contains(strtolower($sql), 'idempotency_key')
                && str_contains(strtolower($sql), 'for update')
        );
        $this->assertNotEmpty($transactionLockQueries, 'The disposition-already-posted existence check must also be a locking read.');
    }

    /**
     * W4.R verify-fix round 3 (finding #4, LOW). Verify item 6: "credits vs 2632 cannot diverge —
     * try a partial failure inside the txn." Forces a genuine failure INSIDE
     * RefundPostingService::post()'s outer DB::transaction() at the exact point named by the
     * finding — after every other document (CRN, recharge, supplier credit, unearn, clawback, and
     * the disposition's own 2632 JV) has already posted, and while the Credit row is about to be
     * written — via a real Eloquent `Credit::creating` listener, not a mock. Asserts the ENTIRE
     * document set rolls back together: no JV, no Credit, refund status unchanged.
     */
    public function test_a_failure_between_the_jv_and_the_credit_write_rolls_back_the_whole_post(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['method' => 'Credit']);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $transactionCountBefore = DB::table('transactions')->count();
        $statusBefore = $refund->status;

        \App\Models\Credit::creating(function () {
            throw new \RuntimeException('W4.R verify-fix round 3 (finding #4): simulated failure between the JV post and the Credit write.');
        });

        try {
            $this->expectException(\RuntimeException::class);
            app(RefundPostingService::class)->post($refund->fresh(), null);
        } finally {
            // Never leak this listener into any other test in the process.
            \App\Models\Credit::flushEventListeners();
            \App\Models\Credit::boot();
        }

        $this->assertSame(
            $transactionCountBefore,
            DB::table('transactions')->count(),
            'The CRN/recharge/supplier-credit/disposition JVs posted earlier in the SAME outer transaction must all roll back too -- none of them may survive.'
        );
        $this->assertSame(0, \App\Models\Credit::where('refund_id', $refund->id)->count(), 'No Credit row may survive a rolled-back post().');
        $this->assertSame($statusBefore, $refund->fresh()->status, 'The refund status write (forceFill()->save()) inside the same transaction must also roll back.');
    }

    /**
     * W4.R verify-fix (finding #6, LOW — verify item 4 "flip each company option value in tests
     * and confirm posting changes accordingly"). Flips accounting.refund.invoice_overpay_
     * cancel_policy to refund_out and confirms (a) a PV posts to the payout leaf instead of 2632,
     * and (b) no Credit row is written for a disposition that never touches 2632.
     */
    public function test_disposition_honours_company_option_override_to_refund_out(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.invoice_overpay_cancel_policy',
            'value' => Refund::DISPOSITION_REFUND_OUT,
            'type' => 'string',
        ]);

        $payoutLeaf = Account::factory()->create(['company_id' => $company->id]);
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'REFUND_PAYOUT_CASH_BANK',
            'service_type' => null,
            'account_id' => $payoutLeaf->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        // W7.P: full cash payment BEFORE the refund -- see test_disposition_defaults_to_credit_2632's
        // own comment for why the net-balance assertions below need this step to mean anything.
        $this->postRealPayment($company, $agent, $invoice, $invoiceDetail, $task, 100.000);

        // method left null -> falls through to the company-option default, not a method-driven
        // branch (Cash/Bank/Online each shortcut the company option -- see postDisposition()).
        $refund = $this->makeRefund($company, $agent, $invoice, ['method' => null]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $refund->refresh();
        $this->assertSame(Refund::DISPOSITION_REFUND_OUT, $refund->disposition, 'The company option override must drive the disposition, not the built-in default.');

        $disposition = Transaction::withoutGlobalScopes()->where('idempotency_key', 'refund:'.$refund->id.':disposition')->first();
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $disposition->id)->where('account_id', $payoutLeaf->id)->count(), 'Money must move through the configured payout leaf.');

        // W7.P net-balance assertions: refund_out must clear AR back to 0 (same as the credit
        // case) and CREDIT the payout leaf by the paid-out amount (money leaving the company,
        // Cr cash/bank) -- never the reverse (a debit to the payout leaf, which would mean the
        // company received money it is supposedly paying out).
        $this->assertEqualsWithDelta(0.0, $this->netDebit($company->id, '1351'), 0.0005, 'AR (1351) must net to 0 for the refund_out disposition too.');
        $this->assertEqualsWithDelta(-100.0, $this->netDebitForAccount($payoutLeaf->fresh()), 0.0005, 'The payout leaf must net to a CREDIT of 100 (money paid out), not a debit.');

        $this->assertNull(\App\Models\Credit::where('refund_id', $refund->id)->first(), 'refund_out never touches 2632 -- no Credit row should be written.');
    }

    /**
     * W4.R verify-fix (finding #5, MEDIUM). RefundPostingService::post() must refuse to compose
     * any document for a refund that has not been approved -- the draft -> approved -> posted ->
     * completed workflow (w4-brief.md §4) is otherwise trivially bypassable by any caller that
     * reaches this method directly.
     */
    public function test_post_refuses_a_draft_refund(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['status' => Refund::STATUS_DRAFT]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $this->expectException(\RuntimeException::class);
        app(RefundPostingService::class)->post($refund->fresh(), null);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Whole-document composition
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_full_post_is_balanced_across_every_sub_document(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        $this->postRealCommission($company, $agent, $invoice, $invoiceDetail, $task, 6.000);

        $refund = $this->makeRefund($company, $agent, $invoice, ['airline_clawback_amount' => 8.000]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 15.000,
            'supplier_charge' => 10.000,
            'total_refund_to_client' => 75.000,
        ]);

        $result = app(RefundPostingService::class)->post($refund->fresh(), null);

        $this->assertNotEmpty($result['crn']);
        $this->assertNotNull($result['recharge']);
        $this->assertNotEmpty($result['supplier_credit']);
        $this->assertNotEmpty($result['commission_unearn']);
        $this->assertNotNull($result['clawback']);
        $this->assertNotNull($result['disposition']);

        // trackCompanyForInvariants()'s tearDown hook asserts the WHOLE company's trial balance
        // still balances after every one of these documents -- the strongest cross-document
        // correctness check available.
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // P2.5.I (p2_5-brief.md §P2.5.I): "commission_unearned event-driven from W4.R's un-earn
    // post" -- additive event dispatch appended to postCommissionUnearnForDetail()'s call site
    // (see this class's OWN test_commission_unearn_reverses_the_live_commission_jv() above,
    // which proves the reversal itself; this test proves the NEW listener side-effect on top of
    // that same reversal, reusing the identical fixture helpers).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_commission_unearn_creates_a_reminder_via_the_event_listener(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        $commissionTransaction = $this->postRealCommission($company, $agent, $invoice, $invoiceDetail, $task, 6.000);

        $refund = $this->makeRefund($company, $agent, $invoice);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        app(RefundPostingService::class)->post($refund->fresh(), null);

        $reminder = \App\Models\Reminder::where('reminder_kind', \App\Models\Reminder::KIND_COMMISSION_UNEARNED)
            ->where('agent_id', $agent->id)
            ->first();

        $this->assertNotNull($reminder, 'The event listener must create a commission_unearned reminder row.');
        $this->assertSame('agent', $reminder->target_type);
        $this->assertSame($client->id, $reminder->client_id);
        $this->assertSame($invoice->id, $reminder->invoice_id);
        $this->assertSame('pending', $reminder->status);
        $this->assertNotNull($reminder->dedupe_key);
        $this->assertStringStartsWith('commission_unearned:', $reminder->dedupe_key);
        $this->assertTrue((bool) $reminder->send_to_agent);

        // Idempotent: re-dispatching the SAME (reversal) transaction id's event must not
        // duplicate the row. reverse() posts a NEW reversal transaction distinct from
        // $commissionTransaction (linked via reversal_of_transaction_id) -- that reversal's own
        // id is what the real dispatch site uses as CommissionUnearned::$transactionId.
        $reversalTransactionId = (int) Transaction::withoutGlobalScopes()
            ->where('reversal_of_transaction_id', $commissionTransaction->id)
            ->value('id');
        event(new \App\Events\Accounting\CommissionUnearned(
            companyId: $company->id,
            agentId: $agent->id,
            clientId: $client->id,
            invoiceId: $invoice->id,
            transactionId: $reversalTransactionId,
            amount: 6.000,
        ));

        $this->assertSame(
            1,
            \App\Models\Reminder::where('reminder_kind', \App\Models\Reminder::KIND_COMMISSION_UNEARNED)->count(),
            'A second event for the same transaction id must not create a duplicate reminder (dedupe_key).'
        );
    }
}
