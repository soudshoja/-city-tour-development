<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Transaction;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RealisedFxService;
use App\Services\PaymentApplicationService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T1 (Lane A). End-to-end test through the REAL public entry point,
 * {@see PaymentApplicationService::applyPaymentsToInvoice()}, with a USD invoice and a USD
 * TOPUP payment posted at DIFFERENT rates — exactly the scenario PLAN.md §5 Lane A names.
 *
 * Fixture shape: a TOPUP payment's own `Cr CLIENT_ADVANCE` line is posted directly through
 * {@see PostingService} with `DocumentDraft::$paymentId` set (mirroring
 * `PaymentController`/`CheckMyFatoorahPayments`' own convention — L5's "found via
 * transactions.payment_id"), at rate 0.310; the invoice's own `Dr RECEIVABLE_CONTROL` `INV` line
 * is posted the same way at rate 0.300. `applyPaymentsToInvoice()` then draws down that TOPUP
 * credit against the invoice through the real public API — no internal method is called directly
 * except where noted (idempotency probe, mirroring `PaymentApplicationServiceCreditCoaSeamTest`'s
 * own established pattern for that one case).
 */
class PaymentApplicationRealisedFxTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function makeOnPathTenant(): array
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        return $tenant;
    }

    private function makeInvoice(array $tenant, float $amount, string $currency): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => $amount,
            'sub_amount' => $amount,
            'currency' => $currency,
        ]);
    }

    /** Posts the invoice's own `INV` receivable line directly, at $rate. */
    private function postInvoiceReceivable(array $tenant, Invoice $invoice, float $amount, string $currency, float $rate): void
    {
        $companyId = $tenant['company']->id;
        $branchId = $tenant['branch']->id;
        $base = round($amount * $rate, 3);

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'INV',
            subType: null,
            docDate: now(),
            narration: 'FX fixture invoice',
            lines: [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL',
                    accountId: null,
                    side: 'debit',
                    amount: $base,
                    currency: $currency,
                    originalAmount: $amount,
                    exchangeRate: $rate,
                    transactionType: 'CUSTOMERDEBITED',
                    partyAccountRef: $tenant['client']->id,
                    invoiceId: $invoice->id,
                    ledgerType: 'receivable',
                ),
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND',
                    accountId: null,
                    side: 'credit',
                    amount: $base,
                    currency: (string) config('accounting.engine.base_currency'),
                    originalAmount: $base,
                    exchangeRate: 1.0,
                    transactionType: 'FIXTURE',
                    invoiceId: $invoice->id,
                ),
            ],
            idempotencyKey: 'fx-fixture-inv:'.$invoice->id,
            invoiceId: $invoice->id,
        );

        app(PostingService::class)->post($draft);
    }

    /** Posts a TOPUP payment's own `Cr CLIENT_ADVANCE` line, `paymentId` set, at $rate. */
    private function makeTopupCreditAtRate(array $tenant, float $amount, string $currency, float $rate): Credit
    {
        $companyId = $tenant['company']->id;
        $branchId = $tenant['branch']->id;
        $base = round($amount * $rate, 3);

        $payment = Payment::factory()->create([
            'company_id' => $companyId,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'status' => 'completed',
        ]);

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'FX fixture TOPUP receipt',
            lines: [
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND',
                    accountId: null,
                    side: 'debit',
                    amount: $base,
                    currency: (string) config('accounting.engine.base_currency'),
                    originalAmount: $base,
                    exchangeRate: 1.0,
                    transactionType: 'FIXTURE',
                ),
                new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE',
                    accountId: null,
                    side: 'credit',
                    amount: $base,
                    currency: $currency,
                    originalAmount: $amount,
                    exchangeRate: $rate,
                    transactionType: 'CUSTOMERCREDITED',
                    partyAccountRef: $tenant['client']->id,
                    ledgerType: 'advance',
                ),
            ],
            idempotencyKey: 'fx-fixture-topup:'.$payment->id,
            paymentId: $payment->id, // L5 — this is what makes `transactions.payment_id` linkage resolvable.
        );

        app(PostingService::class)->post($draft);

        return Credit::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'client_id' => $tenant['client']->id,
            'payment_id' => $payment->id,
            'type' => Credit::TOPUP,
            'amount' => $amount,
            'description' => 'FX fixture TOPUP credit',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_apply_at_differing_rates_posts_a_realised_fx_document(): void
    {
        $tenant = $this->makeOnPathTenant();
        $invoice = $this->makeInvoice($tenant, 100.0, 'USD');
        $this->postInvoiceReceivable($tenant, $invoice, 100.0, 'USD', 0.300);
        $credit = $this->makeTopupCreditAtRate($tenant, 100.0, 'USD', 0.310);

        $this->actingAs($tenant['user']);
        $result = (new PaymentApplicationService())->applyPaymentsToInvoice(
            $invoice->id,
            [['credit_id' => $credit->id, 'amount' => 100.0]],
            'full'
        );

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $paymentApplicationId = PaymentApplication::where('invoice_id', $invoice->id)->value('id');
        $this->assertNotNull($paymentApplicationId);

        $fxKey = RealisedFxService::idempotencyKeyFor('pa', $paymentApplicationId);
        $fxTransaction = Transaction::where('company_id', $tenant['company']->id)
            ->where('idempotency_key', $fxKey)
            ->first();

        $this->assertNotNull($fxTransaction, 'a realised-FX document must be posted for this apply event');
        $this->assertSame('FXR', $fxTransaction->doc_type);
        $this->assertSame($invoice->id, $fxTransaction->invoice_id);
        $this->assertSame('posted', $fxTransaction->posting_status);

        // Credit-sourced (TOPUP -> Cr CLIENT_ADVANCE), source rate 0.310 > applied rate 0.300 ->
        // D = 100*(0.310-0.300) = 1.000 > 0 -> credit-sourced GAIN: party Dr / FX_GAIN Cr (4139).
        $entries = JournalEntry::where('transaction_id', $fxTransaction->id)->get();
        $this->assertCount(2, $entries);
        $this->assertEqualsWithDelta((float) $entries->sum('debit'), (float) $entries->sum('credit'), 0.0005);
        $this->assertEqualsWithDelta(1.000, (float) $entries->sum('debit'), 0.0005);

        $gainLeaf = Account::withoutGlobalScopes()->where('company_id', $tenant['company']->id)->where('code', '4139')->first();
        $this->assertNotNull($gainLeaf);
        $gainLine = $entries->firstWhere('account_id', $gainLeaf->id);
        $this->assertNotNull($gainLine, 'FX_GAIN_REALISED (4139) must be hit');
        $this->assertEqualsWithDelta(1.000, (float) $gainLine->credit, 0.0005);

        $partyLine = $entries->firstWhere('account_id', '!=', $gainLeaf->id);
        $this->assertEqualsWithDelta(1.000, (float) $partyLine->debit, 0.0005, 'party line must be debited on a gain');
        $this->assertSame($tenant['client']->id, $partyLine->type_reference_id);
    }

    /**
     * Idempotency: retrying the identical apply event must never double-post the FXR document —
     * PostingSeam/PostingService's own idempotency-key dedup (MP-1-4's target).
     */
    public function test_repeating_the_same_application_posts_only_one_fx_document(): void
    {
        $tenant = $this->makeOnPathTenant();
        $invoice = $this->makeInvoice($tenant, 50.0, 'USD');
        $this->postInvoiceReceivable($tenant, $invoice, 50.0, 'USD', 0.300);
        $credit = $this->makeTopupCreditAtRate($tenant, 50.0, 'USD', 0.320);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $paymentApplication = PaymentApplication::create([
            'payment_id' => $credit->payment_id,
            'credit_id' => $credit->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => null,
            'amount' => 50.0,
            'applied_by' => $tenant['user']->id,
            'applied_at' => now(),
            'notes' => 'FX idempotency probe',
        ]);

        $appliedPayments = [[
            'payment_application_id' => $paymentApplication->id,
            'credit_id' => $credit->id,
            'payment_id' => $credit->payment_id,
            'refund_id' => null,
            'voucher_number' => 'TOPUP',
            'amount_applied' => 50.0,
            'remaining_balance' => 0.0,
            'invoice_partial_id' => null,
        ]];

        $reflection = new \ReflectionMethod($service, 'createCreditPaymentCOA');
        $reflection->setAccessible(true);

        $reflection->invokeArgs($service, [$invoice, $appliedPayments, 50.0]);
        $reflection->invokeArgs($service, [$invoice, $appliedPayments, 50.0]);

        $fxKey = RealisedFxService::idempotencyKeyFor('pa', $paymentApplication->id);
        $this->assertSame(
            1,
            Transaction::where('company_id', $tenant['company']->id)->where('idempotency_key', $fxKey)->count(),
            'retrying the identical apply event must never double-post the FXR document'
        );
    }

    /**
     * Un-apply (L5): reversing the FXR document via {@see RealisedFxService::reverseForApply()}
     * must net the ledger back to zero and be idempotent itself.
     */
    public function test_un_apply_reverses_the_fx_document(): void
    {
        $tenant = $this->makeOnPathTenant();
        $invoice = $this->makeInvoice($tenant, 100.0, 'USD');
        $this->postInvoiceReceivable($tenant, $invoice, 100.0, 'USD', 0.300);
        $credit = $this->makeTopupCreditAtRate($tenant, 100.0, 'USD', 0.310);

        $this->actingAs($tenant['user']);
        (new PaymentApplicationService())->applyPaymentsToInvoice(
            $invoice->id,
            [['credit_id' => $credit->id, 'amount' => 100.0]],
            'full'
        );

        $paymentApplicationId = PaymentApplication::where('invoice_id', $invoice->id)->value('id');
        $fxKey = RealisedFxService::idempotencyKeyFor('pa', $paymentApplicationId);
        $fxTransaction = Transaction::where('company_id', $tenant['company']->id)->where('idempotency_key', $fxKey)->firstOrFail();

        $reversed = app(RealisedFxService::class)->reverseForApply(
            $tenant['company']->id,
            'pa',
            $paymentApplicationId,
            now(),
            $tenant['user']->id
        );

        $this->assertNotNull($reversed);
        $this->assertSame('reversed', $fxTransaction->fresh()->posting_status);

        $netDebit = JournalEntry::where('company_id', $tenant['company']->id)
            ->whereIn('transaction_id', [$fxTransaction->id, $reversed->transaction->id])
            ->sum('debit');
        $netCredit = JournalEntry::where('company_id', $tenant['company']->id)
            ->whereIn('transaction_id', [$fxTransaction->id, $reversed->transaction->id])
            ->sum('credit');
        $this->assertEqualsWithDelta((float) $netDebit, (float) $netCredit, 0.0005);

        // Idempotent: a second call finds no live ('posted') FXR document left to reverse.
        $second = app(RealisedFxService::class)->reverseForApply(
            $tenant['company']->id,
            'pa',
            $paymentApplicationId,
            now(),
            $tenant['user']->id
        );
        $this->assertNull($second, 'a second reversal attempt must find nothing live left to reverse');
    }

    /** L2 — engine OFF: the credit-apply JV itself routes legacy, so no engine document exists to
     *  compute FX against; no FXR row, no throw, and the credit-apply document is unaffected. */
    public function test_engine_off_apply_produces_no_fx_document(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 100.0, 'USD');
        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'status' => 'completed',
        ]);
        $credit = Credit::create([
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'client_id' => $tenant['client']->id,
            'payment_id' => $payment->id,
            'type' => Credit::TOPUP,
            'amount' => 100.0,
            'description' => 'OFF-path fixture credit',
        ]);

        $this->actingAs($tenant['user']);
        $result = (new PaymentApplicationService())->applyPaymentsToInvoice(
            $invoice->id,
            [['credit_id' => $credit->id, 'amount' => 100.0]],
            'full'
        );

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $this->assertSame(
            0,
            Transaction::where('company_id', $tenant['company']->id)->where('doc_type', 'FXR')->count(),
            'engine OFF must never produce an FXR document'
        );

        $creditApplyTransaction = Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->first();
        $this->assertNotNull($creditApplyTransaction);
        $this->assertNull($creditApplyTransaction->idempotency_key, 'the credit-apply document itself must stay on the OFF path, unaffected by the FX wiring');
    }
}
