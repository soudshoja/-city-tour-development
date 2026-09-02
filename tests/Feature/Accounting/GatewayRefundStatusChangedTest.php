<?php

namespace Tests\Feature\Accounting;

use App\Events\Accounting\GatewayRefundStatusChanged;
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
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w4r-gateway-refund. W4.R verify-fix (finding #3, HIGH) — GatewayRefundStatusChanged was
 * never registered to its listener anywhere in the codebase (no EventServiceProvider, no explicit
 * Event::listen() call, zero tests). Fixed by wiring it in AccountingServiceProvider::boot(),
 * following the same explicit-registration convention AppServiceProvider already uses for
 * CheckConfirmedOrIssuedTask -> ProcessTaskFinancials. This test proves the wiring actually fires
 * end-to-end (dispatch the real event, assert the real ledger/status side effects), not merely
 * that the classes exist.
 *
 * Fix-loop (Opus review, AR-leg finding): the listener used to unconditionally debit CLIENT_ADVANCE
 * (2632) with no matching prior credit, while the CRN's own AR credit balance was left uncleared
 * forever. The row-existence check this originally shipped with ("a transaction posted under the
 * idempotency key") could not have caught that — it never inspected which account the debit leg
 * landed on, or what either account's balance netted to. Replaced below with NET-BALANCE tests
 * (journal_entries sums via the same netDebit()/netCredit() convention RefundPostingServiceTest
 * already uses for its own W7.P polarity proof) covering both branches HandleGatewayRefundStatus
 * Changed now has to choose between: AR (a plain Online refund settling the CRN's receivable-
 * parked credit) and 2632 (an existing wallet/credit balance being cashed out through the gateway).
 */
class GatewayRefundStatusChangedTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: \App\Models\Supplier, 4: Task,
     *               5: Invoice, 6: InvoiceDetail}
     */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = \App\Models\Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
        ]);

        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);
        $invoiceDetail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

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
        \App\Models\Supplier $supplier,
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

    /** Posts a real client cash payment against the invoice (Dr CASH_IN_HAND / Cr RECEIVABLE_CONTROL). */
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
            idempotencyKey: 'gw-refund-test:payment:'.$invoiceDetail->id,
            invoiceId: $invoice->id,
        );

        return app(PostingService::class)->post($draft)->transaction;
    }

    /** Posts a standalone Dr CASH_IN_HAND / Cr CLIENT_ADVANCE credit JV -- mimics an already-completed 'credit' disposition parking a wallet balance in 2632. */
    private function postExistingWalletCredit(Company $company, Client $client, float $amount, string $key): Transaction
    {
        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: 0,
            docType: 'JV',
            subType: 'CLIENT_TOPUP',
            docDate: now(),
            narration: 'Pre-existing wallet credit',
            lines: [
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND',
                    accountId: null,
                    side: 'debit',
                    amount: $amount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'CLIENT_TOPUP_CASH',
                ),
                new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE',
                    accountId: null,
                    side: 'credit',
                    amount: $amount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'CLIENT_TOPUP_ADVANCE',
                    partyAccountRef: $client->id,
                ),
            ],
            idempotencyKey: $key,
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

    /**
     * Registration-level check: Laravel actually knows to call the listener for this event --
     * would have failed on the prior build (nothing registered it anywhere).
     */
    public function test_listener_is_registered_for_the_event(): void
    {
        $this->assertTrue(
            Event::hasListeners(GatewayRefundStatusChanged::class),
            'AccountingServiceProvider must register HandleGatewayRefundStatusChanged for this event.'
        );
    }

    /**
     * AR branch: a plain Online refund (no disposition override). RefundPostingService's own CRN
     * leg (postCrnForDetail(), reverse() of the sale) already parked AR in a credit balance before
     * this event ever fires -- reproduced here directly (sale -> full payment -> reverse) rather
     * than going through the full RefundPostingService::post() pipeline, since that pipeline is
     * covered by its own test file; this test isolates exactly what the listener itself must do
     * with the balance the CRN leaves behind. Asserts AR (1351) nets back to exactly 0 once the
     * gateway payout posts -- before this fix AR stayed stuck at -100 forever (the finding this
     * fix closes) while 2632 was spuriously debited instead.
     */
    public function test_online_completion_with_no_disposition_override_clears_the_parked_ar_balance(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $sale = $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, 100.000, 60.000);
        $this->postRealPayment($company, $agent, $invoice, $invoiceDetail, $task, 100.000);
        // The CRN itself: reverse() of the sale, exactly what RefundPostingService::
        // postCrnForDetail() does for an engine-posted sale. AR nets to -100 after this (a credit
        // balance -- "the company now owes this client back"), matching postDisposition()'s own
        // worked-example docblock.
        app(PostingService::class)->reverse($sale, now(), null);

        $this->assertEqualsWithDelta(-100.0, $this->netDebit($company->id, '1351'), 0.0005, 'Fixture sanity: AR must be parked in a credit balance by the CRN before the gateway completion fires.');

        $refund = Refund::create([
            'refund_number' => 'REF-GW-AR-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Online',
            'disposition' => null,
            'status' => Refund::STATUS_POSTED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 100,
            'gateway_refund_id' => 'GWREF-AR-1',
        ]);

        GatewayRefundStatusChanged::dispatch('myfatoorah', 'GWREF-AR-1', 100.000, $refund->id, GatewayRefundStatusChanged::STATUS_COMPLETED);

        $refund->refresh();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->status);
        $this->assertSame(Refund::DISPOSITION_REFUND_OUT, $refund->disposition);

        $key = PaymentIdempotencyKey::forGatewayRefund('myfatoorah', 'GWREF-AR-1');
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($posted, 'The listener must actually post a document.');
        $this->assertEqualsWithDelta(0.0, (float) $posted->total_debit - (float) $posted->total_credit, 0.0005);

        // AR must net to exactly 0 -- the finding this fix closes ("AR stuck").
        $this->assertEqualsWithDelta(0.0, $this->netDebit($company->id, '1351'), 0.0005, 'AR (1351) must net to 0 after CRN + online gateway payout.');
        // 2632 must never have been touched at all by the AR-branch leg.
        $this->assertEqualsWithDelta(0.0, $this->netCredit($company->id, '2632'), 0.0005, 'CLIENT_ADVANCE (2632) must be untouched by the AR branch.');

        $arAccount = $this->accountByCode($company->id, '1351');
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $arAccount->id)->where('debit', 100.000)->count(), 'The debit leg must land on AR (RECEIVABLE_CONTROL), not 2632.');
    }

    /**
     * Wallet branch: refund->disposition is already 'credit' when the gateway completion arrives
     * -- postDisposition()'s own docblock: an explicit disposition override is read BEFORE the
     * method branch, so an Online refund with disposition='credit' would already have credited
     * 2632 synchronously. This event represents that EXISTING 2632 balance now being cashed out
     * through the gateway (refunds.gateway_refund_id reused against the same refund row), not a
     * fresh CRN. Asserts 2632 nets back to 0 once the payout posts, and that AR (never credited in
     * this scenario) is untouched.
     */
    public function test_online_completion_with_credit_disposition_pays_out_the_wallet_balance(): void
    {
        [$company, $agent, $client, , , $invoice] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // Pre-existing wallet balance: 2632 credited by 50, as if an earlier 'credit' disposition
        // (or top-up) already ran.
        $this->postExistingWalletCredit($company, $client, 50.000, 'gw-refund-test:wallet-topup:'.$client->id);
        $this->assertEqualsWithDelta(50.0, $this->netCredit($company->id, '2632'), 0.0005, 'Fixture sanity: 2632 must already carry the wallet credit before the gateway completion fires.');

        $refund = Refund::create([
            'refund_number' => 'REF-GW-WALLET-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Online',
            'disposition' => Refund::DISPOSITION_CREDIT,
            'status' => Refund::STATUS_COMPLETED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 50,
            'gateway_refund_id' => 'GWREF-WALLET-1',
        ]);

        GatewayRefundStatusChanged::dispatch('myfatoorah', 'GWREF-WALLET-1', 50.000, $refund->id, GatewayRefundStatusChanged::STATUS_COMPLETED);

        $refund->refresh();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->status);
        $this->assertSame(Refund::DISPOSITION_CREDIT, $refund->disposition, 'The wallet branch must NOT overwrite a pre-existing credit disposition.');

        $key = PaymentIdempotencyKey::forGatewayRefund('myfatoorah', 'GWREF-WALLET-1');
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($posted, 'The listener must actually post a document.');
        $this->assertEqualsWithDelta(0.0, (float) $posted->total_debit - (float) $posted->total_credit, 0.0005);

        // 2632 must net back to exactly 0 -- the wallet balance has now been fully paid out.
        $this->assertEqualsWithDelta(0.0, $this->netCredit($company->id, '2632'), 0.0005, 'CLIENT_ADVANCE (2632) must net to 0 once the wallet balance is paid out via the gateway.');
        // AR must never have been touched by the wallet-branch leg.
        $this->assertEqualsWithDelta(0.0, $this->netDebit($company->id, '1351'), 0.0005, 'AR (1351) must be untouched by the wallet branch.');

        $advanceAccount = $this->accountByCode($company->id, '2632');
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $advanceAccount->id)->where('debit', 50.000)->count(), 'The debit leg must land on CLIENT_ADVANCE (2632), not AR.');
    }

    public function test_dispatching_rejected_voids_the_draft_and_posts_nothing(): void
    {
        [$company, $agent, , , , $invoice] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $refund = Refund::create([
            'refund_number' => 'REF-GW-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Online',
            'status' => Refund::STATUS_POSTED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 50,
            'gateway_refund_id' => 'GWREF-REJ-1',
        ]);

        GatewayRefundStatusChanged::dispatch('myfatoorah', 'GWREF-REJ-1', 50.000, $refund->id, GatewayRefundStatusChanged::STATUS_REJECTED);

        $refund->refresh();
        $this->assertSame(Refund::STATUS_REJECTED, $refund->status);

        $key = PaymentIdempotencyKey::forGatewayRefund('myfatoorah', 'GWREF-REJ-1');
        $this->assertNull(Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first());
    }
}
