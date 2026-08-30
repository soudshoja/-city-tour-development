<?php

namespace Tests\Feature\Accounting;

use App\Enums\ChargeType;
use App\Http\Controllers\PaymentController;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PaymentGateway\Knet;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccountingTestCase;

/**
 * KEY: mf-error (B3). D6: transactions_payment_id_reference_type_unique (P0 hotfix migration
 * 2026_08_24_000001) allows at most one row per (payment_id, reference_type) pair. Three
 * gateway *failure*-recording sites in PaymentController write a Transaction row keyed
 * (payment_id, 'Payment') for the same $payment->id -- handleMyFatoorahError's topup branch,
 * handleTapCallback, handleKnetResponse -- and at HEAD every one of them is a plain
 * Transaction::create(), so a redelivered failure notification (or, pre-existing, a payment
 * that traverses two of these three handlers) 1062s with a raw, uncaught
 * UniqueConstraintViolationException. See PaymentController::firstOrCreateFailureTransaction()'s
 * own docblock (just above handleMyFatoorahError) for the fix and why these writes carry no
 * journal entries and are correctly NOT routed through PostingSeam.
 *
 * Tap is exercised only at the shared-helper level here, matching
 * PaymentControllerEntryPointsTest's own documented reasoning: handleTapCallback's
 * Tap::getCharge() is a raw curl call, not fakeable with Http::fake() without touching
 * App\Support\PaymentGateway\Tap.php (out of this lane's scope), and its failure-write shape is
 * byte-for-byte identical to handleKnetResponse's (both call the same
 * firstOrCreateFailureTransaction() helper with the same attribute shape) -- fully covered
 * below via KNET, plus the shared helper's own direct tests.
 */
class PaymentControllerTransactionDedupTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function invokePrivate(object $object, string $method, array $args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @return array{company: Company, branch: Branch, agent: Agent, client: Client}
     */
    private function makeTenant(): array
    {
        $company = Company::factory()->create();
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'phone_number' => '99999999',
            'country_code' => '+965',
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return compact('company', 'branch', 'agent', 'client');
    }

    /**
     * @return array{0: Invoice, 1: InvoiceDetail}
     */
    private function makeInvoice(array $tenant, float $taskPrice = 100.00): array
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => $taskPrice,
            'sub_amount' => $taskPrice,
        ]);
        $task = Task::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
        ]);
        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_price' => $taskPrice,
            // margin = 0 -- see PaymentControllerCoaSeamTest's own convention.
            'supplier_price' => $taskPrice,
            'markup_price' => 0,
        ]);

        return [$invoice, $invoiceDetail];
    }

    private function makePayment(array $tenant, Invoice $invoice, float $amount, string $status = 'completed'): Payment
    {
        return Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenant['agent']->user_id,
            'payment_method_id' => null,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 1. Shared helper (firstOrCreateFailureTransaction) — direct, no HTTP/gateway involved.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_helper_is_idempotent_on_repeat_call_with_head_row_shape_on_first_write(): void
    {
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');

        $controller = app(PaymentController::class);
        $attrs = [
            'branch_id' => $tenant['branch']->id,
            'company_id' => $tenant['company']->id,
            'entity_id' => $tenant['company']->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $payment->amount,
            'description' => 'KNET payment failed for '.$tenant['client']->full_name,
            'invoice_id' => $payment->invoice_id,
            'payment_reference' => 'FIRST-DELIVERY',
            'transaction_date' => now(),
        ];

        $first = $this->invokePrivate($controller, 'firstOrCreateFailureTransaction', [
            ['payment_id' => $payment->id, 'reference_type' => 'Payment'],
            $attrs,
        ]);
        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertSame('FIRST-DELIVERY', $first->payment_reference);

        // Redelivery: different payment_reference/description, as a real second gateway
        // delivery might carry a different gateway-side reference for the "same" failure.
        $second = $this->invokePrivate($controller, 'firstOrCreateFailureTransaction', [
            ['payment_id' => $payment->id, 'reference_type' => 'Payment'],
            array_merge($attrs, ['payment_reference' => 'REDELIVERY', 'description' => 'different']),
        ]);

        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame($first->id, $second->id);
        // No second insert: the FIRST call's row shape wins, untouched by the redelivery.
        $this->assertSame('FIRST-DELIVERY', $second->fresh()->payment_reference);
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count());
    }

    /**
     * Residual 10 fix (W2.1). `firstOrCreateFailureTransaction()` reusing an existing row
     * (trashed or not) instead of creating one must never be silent, and a trashed hit must
     * not be handed back to the caller still soft-deleted -- restore() is the only safe
     * remediation here (a fresh row is not an option: $searchAttributes IS the unique index
     * this method exists to respect).
     */
    public function test_helper_finds_a_soft_deleted_row_instead_of_colliding(): void
    {
        Log::spy();
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');

        $existing = Transaction::create([
            'payment_id' => $payment->id,
            'reference_type' => 'Payment',
            'branch_id' => $tenant['branch']->id,
            'company_id' => $tenant['company']->id,
            'entity_id' => $tenant['company']->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $payment->amount,
            'description' => 'original',
            'invoice_id' => $payment->invoice_id,
            'transaction_date' => now(),
        ]);
        // Mirrors InvoiceController's real cleanup shape: Transaction::where('invoice_id', ..)
        // ->delete() is a soft delete (SoftDeletes trait) — the unique index has no deleted_at
        // component, so the (payment_id, reference_type) slot stays occupied.
        $existing->delete();
        $this->assertSoftDeleted('transactions', ['id' => $existing->id]);

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'firstOrCreateFailureTransaction', [
            ['payment_id' => $payment->id, 'reference_type' => 'Payment'],
            [
                'branch_id' => $tenant['branch']->id,
                'company_id' => $tenant['company']->id,
                'entity_id' => $tenant['company']->id,
                'entity_type' => 'company',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'description' => 'new delivery',
                'invoice_id' => $payment->invoice_id,
                'transaction_date' => now(),
            ],
        ]);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(1, Transaction::withTrashed()->where('payment_id', $payment->id)->where('reference_type', 'Payment')->count());
        // The trashed hit must be restore()d, not handed back still soft-deleted.
        $this->assertFalse($result->trashed());
        $this->assertNotSoftDeleted('transactions', ['id' => $existing->id]);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($existing) {
                return $message === 'firstOrCreateFailureTransaction reused an existing row instead of creating one'
                    && ($context['transaction_id'] ?? null) === $existing->id
                    && ($context['was_trashed'] ?? null) === true;
            });
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 2. handleMyFatoorahError — real controller call, both rows it can write.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_myfatoorah_error_payment_branch_redelivery_is_idempotent(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');
        $payment->payment_reference = 'MF-ERR-REF-1';
        $payment->save();

        $controller = app(PaymentController::class);
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);

        $first = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($first);

        // Redelivery of the exact same error callback for the same payment.
        $second = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($second);

        $rows = Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->get();
        $this->assertCount(1, $rows, 'a redelivered MyFatoorah error callback must not 1062 nor double-insert');
        $this->assertSame('debit', $rows->first()->transaction_type);
        $this->assertSame($tenant['branch']->id, $rows->first()->branch_id);
        $this->assertSame($tenant['company']->id, $rows->first()->company_id);
        $this->assertEqualsWithDelta(100.00, (float) $rows->first()->amount, 0.001);
    }

    /**
     * Residual 9 fix (W2.1). A replayed `payments.error` notification for a TOPUP payment
     * that has ALREADY completed via ClientController::addCredit()'s own success write --
     * which shares this exact (payment_id, 'Payment') slot, see
     * firstOrCreateFailureTransaction()'s own docblock -- must not manufacture a second
     * failure Transaction, nor fire a false "payment failed" notification over a payment that
     * actually succeeded. The success document is simulated directly here (a genuine
     * addCredit() call needs a live Resayil/notification stack out of this test's scope); what
     * matters is the shape addCredit() leaves behind: `payment_id` + `reference_type` =>
     * 'Payment', on an already-'completed' payment.
     */
    public function test_myfatoorah_error_is_a_no_op_for_an_already_completed_topup(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['agent']->user_id,
            'payment_method_id' => null,
            'amount' => 25.00,
            'status' => 'completed',
        ]);

        $successDocument = Transaction::create([
            'payment_id' => $payment->id,
            'reference_type' => 'Payment',
            'branch_id' => $tenant['branch']->id,
            'company_id' => $tenant['company']->id,
            'entity_id' => $tenant['company']->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $payment->amount,
            'description' => 'Topup credit added by '.$tenant['client']->full_name,
            'invoice_id' => null,
            'transaction_date' => now(),
        ]);

        $controller = app(PaymentController::class);
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);
        $response = $controller->handleMyFatoorahError($request);

        $this->assertNotNull($response);
        $this->assertFalse($response->isRedirect(route('payment.failed')));
        $this->assertSame('success', $response->getSession()->get('success') !== null ? 'success' : 'other');

        // No second Transaction was manufactured -- the original success document is
        // untouched (still exactly 1 row for this payment/reference_type pair).
        $rows = Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($successDocument->id, $rows->first()->id);
        $this->assertSame('credit', $rows->first()->transaction_type, 'the ORIGINAL success document must be untouched, not overwritten by a failure write');
    }

    /**
     * R-2 fix (W2.2), REVISED by R-b fix (W2b). The residual-9 early return above is scoped to
     * `! $payment->invoice_id` (a genuine topup) on purpose -- it must not intercept an INVOICE
     * payment that completed via the engine's own 'Receipt' document, a different reference_type
     * entirely (see the cross-flip tests below), so the legacy 'Payment' ledger row is still
     * written here for an already-completed invoice payment exactly as before (asserted below).
     *
     * W2.2's OWN version of this test asserted the FIRST delivery still notified (keyed off
     * `! $failureTransaction->wasRecentlyCreated`, true only from the SECOND delivery onward).
     * R-b closed that as a regression: `wasRecentlyCreated` means "a (payment_id,'Payment') row
     * already existed", not "we already notified about THIS failure" -- see the two tests above
     * this one for the two shapes where that distinction silenced a genuinely NEW failure. The
     * fix keys $failureAlreadyRecorded off $payment->status === 'completed' instead, which is
     * correct here for a different reason: a payment already 'completed' (by any means, before
     * this method's first call even runs) should never surface a "payment failed" notification,
     * on ANY delivery -- not just redeliveries. That correctly suppresses BOTH calls here.
     */
    public function test_myfatoorah_error_on_an_already_completed_invoice_payment_never_notifies(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'completed');

        $controller = app(PaymentController::class);
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);

        $this->assertSame(0, Notification::count());

        $first = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($first);
        $this->assertSame(0, Notification::count(), 'a payment already completed before this method ever runs must never notify "failed", not even on the first delivery');

        // Redelivery of the exact same error callback for the same, already-completed payment.
        $second = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($second);

        $this->assertSame(0, Notification::count(), 'a redelivered error for an already-completed invoice payment must still not notify');
        $this->assertSame(
            1,
            Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count(),
            'the ledger side stays deduped to one row exactly as before this fix -- only the notification gate changed'
        );
    }

    /**
     * R-b fix (W2b). `wasRecentlyCreated` on `firstOrCreateFailureTransaction()`'s row means "a
     * (payment_id,'Payment') row already existed", which is a different proposition from "we
     * already notified about THIS failure" -- handleMyFatoorahError never updates
     * $payment->status, so a genuine second failure (e.g. after paymentLinkReinitiate(), which
     * only permits reinitiating a payment still 'initiate') hits the exact same
     * firstOrCreateFailureTransaction() row and, under the old `! $wasRecentlyCreated` guard,
     * was silently dropped. The fix keys off $payment->status === 'completed' instead, so a
     * payment still 'initiate' must notify on every genuine failure delivery.
     */
    public function test_myfatoorah_error_genuine_second_failure_on_a_still_initiate_payment_still_notifies(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');

        $controller = app(PaymentController::class);
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);

        $this->assertSame(0, Notification::count());

        $first = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($first);
        $this->assertSame(1, Notification::count(), 'the first failure must notify');
        $this->assertSame('initiate', $payment->fresh()->status, 'handleMyFatoorahError never advances payment status');

        // A genuine SECOND failure for the same payment -- reachable in production via
        // paymentLinkReinitiate() + a second gateway attempt that also fails -- hits the exact
        // same (payment_id,'Payment') row via firstOrCreateFailureTransaction() (wasRecentlyCreated
        // = false on this second call), but is a distinct failure event and must still notify.
        $second = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($second);

        $this->assertSame(2, Notification::count(), 'a genuine second failure on a still-initiate payment must notify again, not be silenced as a mere redelivery');
        $this->assertSame(
            1,
            Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count(),
            'the ledger side is still deduped to one row -- only the operator-facing notification differs'
        );
    }

    /**
     * R-b fix (W2b), second reachable shape. `firstOrCreateFailureTransaction()` `restore()`s a
     * soft-deleted hit on the (payment_id,'Payment') slot (residual 10, W2.1) -- so
     * `wasRecentlyCreated` is false even on a payment's FIRST-EVER live failure, if some other
     * event previously wrote and then soft-deleted a row in that same slot. The old guard
     * silenced this first-ever failure outright; the status-based guard must not.
     */
    public function test_myfatoorah_error_first_ever_failure_over_a_soft_deleted_row_still_notifies(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');

        $stale = Transaction::create([
            'payment_id' => $payment->id,
            'reference_type' => 'Payment',
            'branch_id' => $tenant['branch']->id,
            'company_id' => $tenant['company']->id,
            'entity_id' => $tenant['company']->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $payment->amount,
            'description' => 'stale, cleaned up by an unrelated prior event',
            'invoice_id' => $payment->invoice_id,
            'transaction_date' => now(),
        ]);
        $stale->delete();
        $this->assertSoftDeleted('transactions', ['id' => $stale->id]);

        $controller = app(PaymentController::class);
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);

        $this->assertSame(0, Notification::count());

        $response = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($response);

        $this->assertSame(1, Notification::count(), 'a first-ever live failure must notify even when firstOrCreateFailureTransaction() restore()s a stale trashed row');
        $this->assertNotSoftDeleted('transactions', ['id' => $stale->id]);
    }

    /**
     * R-b fix (W2b), the flash half. The `$failureAlreadyRecorded` early return had no
     * `->with('error', ...)`, where HEAD's own final redirect on this method still flashes
     * 'Payment was not completed or was cancelled.' -- invisible for an invoice (its details
     * view never prints session('error')), but for a topup the target is payment.link.show,
     * which does render it.
     */
    public function test_myfatoorah_error_redelivery_for_completed_payment_still_flashes_the_head_message(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'completed');

        $controller = app(PaymentController::class);
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);

        $controller->handleMyFatoorahError($request);
        $second = $controller->handleMyFatoorahError($request);

        $this->assertSame('Payment was not completed or was cancelled.', $second->getSession()->get('error'));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 3. handleKnetResponse failure branch — real controller call (real AES crypto, no mocking
    // needed for the gateway itself), redelivery idempotency.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function seedKnetCharge(Company $company, string $key): void
    {
        Charge::create([
            'name' => 'KNET',
            'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 0,
            'charge_type' => 'Flat Rate',
            'self_charge' => 0,
            'extra_charge' => 0,
            'paid_by' => 'Company',
            'company_id' => $company->id,
            'is_active' => true,
            'tran_portal_id' => 'TID-TEST',
            'tran_portal_password' => 'PWD-TEST',
            'terminal_resource_key' => $key,
        ]);
    }

    private function buildKnetRequest(Company $company, Payment $payment, Invoice $invoice, array $fields, string $knetKey): Request
    {
        $queryString = http_build_query(array_merge([
            'udf1' => (string) $payment->id,
            'udf2' => $payment->voucher_number,
            'udf3' => (string) $company->id,
            'udf4' => $invoice->invoice_number,
        ], $fields));

        $knet = new Knet($company->id);
        $reflection = new \ReflectionMethod($knet, 'encryptAES');
        $reflection->setAccessible(true);
        $trandata = $reflection->invoke($knet, $queryString, $knetKey);

        return Request::create('/knet-response', 'GET', [
            'trandata' => $trandata,
            'company_id' => $company->id,
        ]);
    }

    public function test_knet_response_failure_branch_redelivery_is_idempotent(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');
        $knetKey = 'KNETDEDUPTESTKEY';
        $this->seedKnetCharge($company, $knetKey);

        $request = $this->buildKnetRequest($company, $payment, $invoice, [
            'result' => 'DECLINED',
            'paymentid' => 'KPAY-DEDUP-1',
            'trackid' => 'KTRACK-DEDUP-1',
        ], $knetKey);

        $controller = app(PaymentController::class);
        $first = $controller->handleKnetResponse($request);
        $this->assertFalse($first->isRedirect(route('payment.failed')));

        // A resend of the exact same declined response (KNET can resend within milliseconds of
        // the browser return — see the D6 comment at the write site).
        $second = $controller->handleKnetResponse($request);
        $this->assertFalse($second->isRedirect(route('payment.failed')));

        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count());
        $this->assertSame('initiate', $payment->fresh()->status, 'a declined KNET result never completes the payment');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 4. Cross-flip — same payment, engine ON posts the 'Receipt' document, then (flag OFF)
    // handleMyFatoorahError posts the legacy 'Payment' failure row, and the reverse order.
    // handleMyFatoorahError is the one of the three sites with no "already completed" early
    // return, so it is the only one of the three that can be driven, end to end through the
    // real controller method, against a payment that has already gone through the OTHER write
    // — Tap/Knet's own early-return on $payment->status === 'completed' makes that scenario
    // unreachable through their real methods for an already-completed payment (unrelated,
    // pre-existing gate, not a product of this fix).
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @return array{company: Company, branch: Branch, agent: Agent, client: Client}
     */
    private function makeOnPathTenant(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'phone_number' => '99999999',
            'country_code' => '+965',
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return compact('company', 'branch', 'agent', 'client');
    }

    private function postEngineReceipt(PaymentController $controller, Payment $payment, float $amount): void
    {
        config(['accounting.engine.enabled' => true]);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, $amount, 'MyFatoorah', null, 'REF-CROSSFLIP',
        ]);
        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected engine failure');
    }

    public function test_engine_receipt_then_legacy_failure_row_coexist_without_collision(): void
    {
        Http::fake();
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$invoice] = $this->makeInvoice($tenant);
        Charge::create([
            'name' => 'MyFatoorah', 'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 0, 'charge_type' => 'Flat Rate', 'self_charge' => 0,
            'extra_charge' => 0, 'paid_by' => 'Company', 'company_id' => $company->id,
        ]);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'completed');

        $controller = app(PaymentController::class);

        // ON: posts the engine's own 'Receipt' document for this payment.
        $this->postEngineReceipt($controller, $payment, 100.00);
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Receipt')->count());

        // Flip OFF, then the SAME payment hits the legacy MyFatoorah-error 'Payment' write.
        config(['accounting.engine.enabled' => false]);
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);
        $response = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($response);

        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Receipt')->count());
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count());
        $this->assertSame(2, Transaction::where('payment_id', $payment->id)->count());
    }

    public function test_legacy_failure_row_then_engine_receipt_coexist_without_collision_reverse_order(): void
    {
        Http::fake();
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        // Engine NOT enabled for the company yet — the first write below is the plain legacy
        // 'Payment' write, exactly as it would happen with the kill-switch OFF.

        [$invoice] = $this->makeInvoice($tenant);
        Charge::create([
            'name' => 'MyFatoorah', 'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 0, 'charge_type' => 'Flat Rate', 'self_charge' => 0,
            'extra_charge' => 0, 'paid_by' => 'Company', 'company_id' => $company->id,
        ]);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'completed');

        $controller = app(PaymentController::class);

        // OFF: legacy 'Payment' failure write happens first.
        $request = Request::create('/myfatoorah-error', 'GET', ['payment_id' => $payment->id]);
        $response = $controller->handleMyFatoorahError($request);
        $this->assertNotNull($response);
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count());

        // Flip ON, enable the company, then post the engine's 'Receipt' for the SAME payment.
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->postEngineReceipt($controller, $payment, 100.00);

        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count());
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Receipt')->count());
        $this->assertSame(2, Transaction::where('payment_id', $payment->id)->count());
    }
}
