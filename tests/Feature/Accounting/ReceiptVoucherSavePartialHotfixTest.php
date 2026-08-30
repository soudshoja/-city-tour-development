<?php

namespace Tests\Feature\Accounting;

use App\Enums\InvoiceReceiptStatus;
use App\Http\Controllers\ReceiptVoucherController;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\PaymentIdempotencyKey;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * Hotfix suite for InvoiceController::savePartial()'s "gateway requires receipt voucher" branch
 * (`Charge.is_system_default === false`) -> ReceiptVoucherController::createReceiptVoucher().
 *
 * W5.R shipped this feeder creating a `pending` `invoice_receipts` row with NO `transaction_id`
 * at all -- InvoiceUpdateTest::test_full_payment_cash_creates_receipt_voucher and
 * test_partial_payment_cash_installment_creates_receipt_voucher (both engine OFF, the default for
 * their fixture) cover the legacy/OFF-path shape end-to-end through the real HTTP route. This
 * file covers what those two do not: the ENGINE-ON posting shape (real seeders, per this suite's
 * own convention), and the "no double receipt for the same payment" idempotency guard documented
 * on createReceiptVoucher()'s own docblock.
 */
class ReceiptVoucherSavePartialHotfixTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Invoice, 2: Client} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 150.000,
            'status' => 'unpaid',
            'invoice_date' => now(),
        ]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $invoice, $client];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function makePartial(Invoice $invoice, Client $client, float $amount = 150.000): InvoicePartial
    {
        return InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'invoice_charge' => 0,
            'service_charge' => 0,
            'gateway_fee' => 0,
            'amount' => $amount,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'Cash',
            'payment_method' => null,
        ]);
    }

    public function test_engine_on_posts_a_single_balanced_rv_document(): void
    {
        [$company, $invoice, $client] = $this->makeFixture();
        $this->enableEngine($company);

        $partial = $this->makePartial($invoice, $client);

        $result = app(ReceiptVoucherController::class)->createReceiptVoucher($invoice, $partial, request(), 'Cash');

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['transaction_id']);

        $receipt = InvoiceReceipt::where('invoice_partial_id', $partial->id)->firstOrFail();
        $this->assertSame($result['transaction_id'], $receipt->transaction_id);
        $this->assertSame(InvoiceReceiptStatus::APPROVED->value, $receipt->status);
        $this->assertNull($partial->refresh()->receipt_voucher_id);

        $transaction = Transaction::withoutGlobalScopes()->findOrFail($receipt->transaction_id);
        $this->assertSame('RV', $transaction->doc_type);
        $this->assertSame('INVOICE', $transaction->sub_type);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines, 'One debit + one credit line, resolved by purpose code.');

        $cash = app(AccountResolver::class)->resolve('CASH_IN_HAND', $company->id);
        $receivable = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);

        $debit = $lines->firstWhere('account_id', $cash->id);
        $credit = $lines->firstWhere('account_id', $receivable->id);

        $this->assertNotNull($debit, 'Instrument leg must land on the CASH_IN_HAND purpose-code account.');
        $this->assertEquals(150.0, (float) $debit->debit);
        $this->assertNotNull($credit, 'Allocation leg must land on the RECEIVABLE_CONTROL purpose-code account.');
        $this->assertEquals(150.0, (float) $credit->credit);
    }

    public function test_no_double_receipt_when_gateway_payment_already_posted_this_event(): void
    {
        [$company, $invoice, $client] = $this->makeFixture();
        $this->enableEngine($company);

        $partial = $this->makePartial($invoice, $client);

        $payment = Payment::factory()->create([
            'agent_id' => $invoice->agent_id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $invoice->agent->user_id,
            'amount' => 150.000,
            'status' => 'completed',
        ]);
        $partial->update(['payment_id' => $payment->id]);

        // Simulate PaymentController::createInvoicePaymentCOA() (the W2-seam gateway-payment
        // feeder) having ALREADY posted this exact payment's receipt -- same gateway, same real
        // payment id, same partial -- keyed via the SAME shared factory this hotfix reuses.
        $existingKey = PaymentIdempotencyKey::forGatewayPayment('Cash', $payment->id, [$partial->id]);
        $existingTransaction = Transaction::forceCreate([
            'company_id' => $company->id,
            'branch_id' => $invoice->agent->branch_id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 150.000,
            'description' => 'Pre-existing gateway receipt',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Receipt',
            'reference_number' => 'RV-EXISTING',
            'transaction_date' => now(),
            'idempotency_key' => $existingKey,
            'posting_status' => 'posted',
        ]);

        $result = app(ReceiptVoucherController::class)->createReceiptVoucher($invoice, $partial, request(), 'Cash');

        $this->assertTrue($result['ok']);
        $this->assertSame(
            $existingTransaction->id,
            $result['transaction_id'],
            'Must reuse the existing gateway-payment transaction, never post a second document.'
        );

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('invoice_id', $invoice->id)->count(),
            'Exactly one Transaction must exist for this payment event.'
        );

        $receipt = InvoiceReceipt::where('invoice_partial_id', $partial->id)->firstOrFail();
        $this->assertSame($existingTransaction->id, $receipt->transaction_id);
        $this->assertSame(InvoiceReceiptStatus::APPROVED->value, $receipt->status);
    }

    public function test_no_double_receipt_on_retry_when_engine_off(): void
    {
        [$company, $invoice, $client] = $this->makeFixture();
        // Engine deliberately left OFF -- this is the OFF-path S1 short-circuit, not the
        // ON-path idempotency backstop the test above exercises.

        $partial = $this->makePartial($invoice, $client);

        $first = app(ReceiptVoucherController::class)->createReceiptVoucher($invoice, $partial, request(), 'Cash');
        $second = app(ReceiptVoucherController::class)->createReceiptVoucher($invoice, $partial, request(), 'Cash');

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertSame(
            $first['transaction_id'],
            $second['transaction_id'],
            'A retried call for the SAME InvoicePartial must resolve to the SAME transaction, never post a second one.'
        );

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('invoice_id', $invoice->id)->count()
        );
    }

    public function test_off_path_transaction_date_uses_request_time_not_docdate_midnight(): void
    {
        [$company, $invoice, $client] = $this->makeFixture();
        // Engine deliberately left OFF -- OFF-path legacy writer must stamp `now()` (full
        // timestamp) at the moment of the request, exactly like HEAD's original
        // createReceiptVoucher() did, NOT InvoiceReceipt.doc_date (cast 'date', i.e. midnight).

        $frozen = Carbon::create(2026, 8, 27, 14, 35, 22);
        Carbon::setTestNow($frozen);

        try {
            $partial = $this->makePartial($invoice, $client);

            $result = app(ReceiptVoucherController::class)->createReceiptVoucher($invoice, $partial, request(), 'Cash');

            $this->assertTrue($result['ok']);

            $transaction = Transaction::withoutGlobalScopes()->findOrFail($result['transaction_id']);

            $this->assertTrue(
                $transaction->transaction_date->equalTo($frozen),
                "Expected transaction_date {$frozen->toDateTimeString()} (now() at request time), got {$transaction->transaction_date->toDateTimeString()}."
            );
            $this->assertNotSame('00:00:00', $transaction->transaction_date->format('H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
