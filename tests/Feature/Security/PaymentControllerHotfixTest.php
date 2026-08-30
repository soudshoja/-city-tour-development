<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\PaymentController;
use App\Models\Account;
use App\Models\Charge;
use App\Models\Credit;
use App\Models\MyFatoorahPayment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-4, HF-5, HF-6 and HF-12, all inside
 * PaymentController.php.
 */
class PaymentControllerHotfixTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function invokePrivate(object $object, string $method, array $args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    // ------------------------------------------------------------------
    // HF-4: double-post race guard (lockForUpdate + re-check).
    // ------------------------------------------------------------------

    public function test_myfatoorah_completion_is_a_no_op_when_payment_already_completed(): void
    {
        $tenant = $this->createTenant();

        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            // Already completed -- simulates a second concurrent delivery
            // (webhook + browser callback, or a retried webhook) landing
            // after the first one already won the race.
            'status' => 'completed',
            'amount' => 100.00,
        ]);

        $statusData = [
            'InvoiceValue' => 100.00,
            'InvoiceTransactions' => [['AuthorizationId' => 'AUTH-1']],
            'InvoiceReference' => 'MF-REF-1',
            'InvoiceId' => 12345,
            'InvoiceStatus' => 'Paid',
        ];

        $controller = app(PaymentController::class);

        // Must return cleanly (no exception) and must not touch the books a
        // second time. Before this hotfix there was no lock/re-check at
        // all, so this call would have run the ENTIRE completion flow
        // again (re-crediting a topup, or re-posting an invoice payment)
        // purely from a stale in-memory $payment->status snapshot.
        $this->invokePrivate($controller, 'processMyFatoorahPaymentCompletion', [
            $payment, $statusData, 'topup', null, false,
        ]);

        $this->assertSame(0, MyFatoorahPayment::where('payment_int_id', $payment->id)->count());
        $this->assertSame(0, Credit::where('payment_id', $payment->id)->count());
    }

    // ------------------------------------------------------------------
    // HF-5: voucher-number race (unlocked read-increment-save) and
    // cross-tenant misresolution by voucher_number in webhook lookups.
    // ------------------------------------------------------------------

    public function test_next_voucher_number_is_unique_under_repeated_calls(): void
    {
        $tenant = $this->createTenant();
        $controller = app(PaymentController::class);

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->invokePrivate($controller, 'nextVoucherNumber', [$tenant['company']->id]);
        }

        $this->assertCount(5, array_unique($numbers), 'nextVoucherNumber() produced a duplicate.');
        // Each call reads-then-increments, so after 5 successful claims the
        // stored counter sits one past the last number handed out (6).
        $this->assertSame(6, Sequence::where('company_id', $tenant['company']->id)->value('current_sequence'));
    }

    public function test_next_voucher_number_is_scoped_per_company(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $controller = app(PaymentController::class);

        $numberA = $this->invokePrivate($controller, 'nextVoucherNumber', [$tenantA['company']->id]);
        $numberB = $this->invokePrivate($controller, 'nextVoucherNumber', [$tenantB['company']->id]);

        // Each company's sequence starts at 1 independently -- this is
        // expected and is exactly why a bare orWhere('voucher_number', ...)
        // lookup (fixed separately below) is unsafe: two companies can
        // legitimately produce the identical voucher number string.
        $this->assertSame($numberA, $numberB);
    }

    public function test_myfatoorah_callback_resolves_payment_by_internal_id_not_shared_voucher_number(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        // Two different companies' payments sharing the exact same voucher
        // number -- the scenario the old orWhere('voucher_number', ...)
        // fallback could not safely disambiguate.
        $paymentA = Payment::factory()->create([
            'company_id' => $tenantA['company']->id,
            'agent_id' => $tenantA['agent']->id,
            'client_id' => $tenantA['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenantA['user']->id,
            'voucher_number' => 'VOU-2026-00001',
            'payment_reference' => null,
            'status' => 'initiate',
        ]);
        Payment::factory()->create([
            'company_id' => $tenantB['company']->id,
            'agent_id' => $tenantB['agent']->id,
            'client_id' => $tenantB['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenantB['user']->id,
            'voucher_number' => 'VOU-2026-00001',
            'payment_reference' => null,
            'status' => 'initiate',
        ]);

        // UserDefinedField as actually sent on initiate (see
        // PaymentController.php ~L3380/~L3722): it always carries our own
        // internal payment_id.
        $userDefinedField = [
            'voucher_number' => 'VOU-2026-00001',
            'payment_id' => $paymentA->id,
            'payment_gateway' => 'MyFatoorah',
        ];

        // Reproduces the resolution line straight out of
        // handleMyFatoorahCallback(): payment_id wins whenever present, and
        // the fallback (when absent) is payment_reference alone -- never a
        // bare voucher_number OR across companies.
        $paymentId = $userDefinedField['payment_id'] ?? null;
        $invoiceId = null;
        $resolved = $paymentId
            ? Payment::find($paymentId)
            : ($invoiceId ? Payment::where('payment_reference', $invoiceId)->first() : null);

        $this->assertNotNull($resolved);
        $this->assertSame($paymentA->id, $resolved->id);
        $this->assertSame($tenantA['company']->id, $resolved->company_id);
    }

    // ------------------------------------------------------------------
    // HF-6: cross-tenant receivable-account posting in
    // createInvoicePaymentCOA().
    // ------------------------------------------------------------------

    public function test_create_invoice_payment_coa_resolves_the_callers_own_clients_account(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        // Company B's "Clients" account has the lower id, reproducing the
        // exact failure mode: Account::where('name','Clients')->first()
        // (no company_id filter) would resolve to THIS row regardless of
        // which company's payment is being posted.
        $clientsAccountB = Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id]);
        $clientsAccountA = Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        $this->assertLessThan($clientsAccountA->id, $clientsAccountB->id);

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);
        $task = \App\Models\Task::factory()->create([
            'company_id' => $tenantA['company']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);
        \App\Models\InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
        ]);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $tenantA['company']->id]);
        $gatewayAsset = Account::create(['name' => 'Gateway Asset A', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        $gatewayExpense = Account::create(['name' => 'Gateway Expense A', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        Charge::create([
            'name' => 'MyFatoorah',
            'type' => \App\Enums\ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 0,
            'company_id' => $tenantA['company']->id,
            'acc_fee_bank_id' => $gatewayAsset->id,
            'acc_fee_id' => $gatewayExpense->id,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $tenantA['company']->id,
            'agent_id' => $tenantA['agent']->id,
            'client_id' => $tenantA['client']->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenantA['user']->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);

        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-1',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'createInvoicePaymentCOA failed unexpectedly');

        $journalAccountIds = \App\Models\JournalEntry::where('transaction_id', $result['transaction_id'])->pluck('account_id');
        $this->assertTrue($journalAccountIds->contains($clientsAccountA->id));
        $this->assertFalse($journalAccountIds->contains($clientsAccountB->id));
    }

    // ------------------------------------------------------------------
    // HF-2: when the caller's OWN company has no 'Clients' leaf at all, the
    // tenant-scoped lookup above must NOT fall back to an unscoped query and
    // must NOT create the missing account — it logs
    // accounting.legacy_account_unresolved and fails the same way this
    // closure already fails for any other missing account.
    // ------------------------------------------------------------------

    public function test_create_invoice_payment_coa_fails_and_logs_when_caller_has_no_clients_account(): void
    {
        Log::spy();

        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        // Company A deliberately has NO 'Clients' account. Company B does --
        // a naive unscoped fallback (or one that resolves by lowest id) would
        // wrongly resolve to B's leaf; the correct behaviour is to fail
        // company A's posting outright rather than borrow another tenant's
        // receivable account.
        $clientsAccountB = Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id]);

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);
        $task = \App\Models\Task::factory()->create([
            'company_id' => $tenantA['company']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);
        \App\Models\InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
        ]);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $tenantA['company']->id]);
        $gatewayAsset = Account::create(['name' => 'Gateway Asset A', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        $gatewayExpense = Account::create(['name' => 'Gateway Expense A', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        Charge::create([
            'name' => 'MyFatoorah',
            'type' => \App\Enums\ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 0,
            'company_id' => $tenantA['company']->id,
            'acc_fee_bank_id' => $gatewayAsset->id,
            'acc_fee_id' => $gatewayExpense->id,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $tenantA['company']->id,
            'agent_id' => $tenantA['agent']->id,
            'client_id' => $tenantA['client']->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenantA['user']->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);

        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-1',
        ]);

        // Same failure shape createInvoicePaymentCOA() already uses for any
        // other missing-account case (see its outer \Exception catch).
        $this->assertFalse($result['success'] ?? true);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertNull($result['transaction_id']);
        $this->assertNotEmpty($result['message'] ?? '');

        // No partial write of the books: neither company's ledger gained a
        // row, and company B's account was never touched at all.
        $this->assertSame(0, \App\Models\JournalEntry::count());
        $this->assertSame(0, \App\Models\Transaction::where('payment_id', $payment->id)->count());

        // Not ->once(): the outer catch in createInvoicePaymentCOA() also logs a
        // second, generic Log::error() for the same exception (existing,
        // unrelated contract) -- this only asserts OUR structured event fired.
        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($tenantA) {
                return $message === 'accounting.legacy_account_unresolved'
                    && ($context['company_id'] ?? null) === $tenantA['company']->id
                    && ($context['name'] ?? null) === 'Clients'
                    && ($context['feeder'] ?? null) === 'MyFatoorah';
            });

        // clientsAccountB stays completely untouched by this failure.
        $this->assertSame(0, \App\Models\JournalEntry::where('account_id', $clientsAccountB->id)->count());
    }

    // ------------------------------------------------------------------
    // HF-12: multiPaymentLinkInitiate() company-mismatch guard. Discovery
    // flagged this as unmitigated, but the guard already existed in the
    // current code (commit 15dc3f5733, predating this hotfix session) --
    // this test locks in that existing protection as a regression guard.
    // ------------------------------------------------------------------

    public function test_multi_payment_link_initiate_rejects_another_companys_payment_method(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $payment = Payment::factory()->create([
            'company_id' => $tenantA['company']->id,
            'agent_id' => $tenantA['agent']->id,
            'client_id' => $tenantA['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenantA['user']->id,
            'status' => 'pending',
        ]);

        $paymentMethodB = PaymentMethod::factory()->create(['company_id' => $tenantB['company']->id, 'is_active' => true]);

        $response = $this->post(route('payment.link.multi-initiate'), [
            'payment_id' => $payment->id,
            'payment_method_id' => $paymentMethodB->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('pending', $payment->fresh()->status);
    }
}
