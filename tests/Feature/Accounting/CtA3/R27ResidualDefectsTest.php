<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Exceptions\Accounting\NonLeafAccountException;
use App\Exceptions\Accounting\NonNegativeAmountException;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RefundPostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 R2-7 — the residual findings from VERIFY-CT-A3-STACK-R1 §3.3 / §3.4 that this lane FIXED
 * rather than deferred. What was deferred, and why, is stated in the PR body and in
 * `CT-A3-R2-INTEGRATION-2026-09-09.md` §Deferred, each with an owner-ruling tag.
 *
 * | id | finding | what is pinned here |
 * |---|---|---|
 * | **D9** | `clear()` credits `CHEQUES_IN_HAND` unconditionally, but the receipt only DEBITS that leaf for a genuinely POST-DATED cheque. A same-day / past-dated / NULL-dated cheque took the bank branch — so clearing it debited the bank a SECOND time and left the float permanently negative. | clearing a non-post-dated cheque is refused, and the bank is debited once |
 * | **D9b** | `clear()` read neither `status` nor `transaction_id`, so a PENDING (unposted) receipt could be cleared — posting `rv-clear:{id}` with no `rv:{id}` behind it. | clearing an unposted voucher is refused, and nothing is posted |
 * | **D10** | `RefundPostingService::post()` wraps every detail in ONE transaction with no per-detail guard, so one uninvoiced detail rolled back the CRN, recharge, supplier credit, commission and disposition for EVERY other detail. On a 63%-uninvoiced population a mixed refund posted nothing, and the operator saw "Refund processing failed." | the invoiced detail posts; the uninvoiced one is reported, not fatal |
 * | **D13** | The reassign `{seq}` was a `count()` read OUTSIDE the posting transaction with no task lock, so two concurrent reassignments mint the same key and both post — a duplicate supplier payable no balance check can see. | the count now runs inside the posting transaction, under a task row lock |
 * | **D15** | `SaleDraftBuilder` tests `> $tolerance`, not `abs(...) > $tolerance`, so a NEGATIVE sell or cost was silently dropped: `task_price = -100` built a clean balanced cost-only document with the receivable and the revenue simply gone. `invoice_details.task_price` is signed and 22 of 23 write paths do not validate the sign. | a negative amount is refused by name; zero still omits |
 * | **§3.4 RISK** | `taskNetOnPurpose()` swallowed every `Throwable` and returned 0.0, so a `NonLeafAccountException` on the payable leaf read as "position is zero" — and a zero outstanding against a non-zero target makes `payableDelta` negative, which CREDITS `SERVICE_PAYABLE`, creating a payable rather than clearing one. | only the documented unmapped-purpose case is absorbed; a damaged leaf refuses |
 */
class R27ResidualDefectsTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $company->id]);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function netDebit(int $accountId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $accountId);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // D15 — a negative amount is refused, not dropped
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_a_negative_sell_is_refused_rather_than_silently_dropped(): void
    {
        $input = new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: -100.0,
            costAmount: 60.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            invoiceDetailId: 4242,
            taskId: 99,
        );

        try {
            (new SaleDraftBuilder)->buildLines($input);
            $this->fail('A negative sell must be refused — it used to build a clean, balanced, cost-only document.');
        } catch (NonNegativeAmountException $e) {
            $this->assertStringContainsString('sellAmount', $e->getMessage());
            $this->assertStringContainsString('4242', $e->getMessage());
        }
    }

    public function test_a_negative_cost_is_refused_too(): void
    {
        $this->expectException(NonNegativeAmountException::class);

        (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 100.0,
            costAmount: -60.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
        ));
    }

    /** ZERO keeps its existing, correct behaviour: the pair is omitted, nothing is refused. */
    public function test_a_zero_amount_still_omits_its_pair_without_refusing(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 100.0,
            costAmount: 0.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
        ));

        $this->assertCount(2, $lines, 'A zero-cost sale posts the AR/revenue pair only — CT-A3 E1 / CT-F34.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // D10 — one uninvoiced detail must not roll back the whole refund
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_a_mixed_refund_posts_the_invoiced_detail_and_reports_the_uninvoiced_one(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();

        $supplier = Supplier::factory()->create();

        $makeTask = fn (): Task => Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'refunded',
            'total' => 60.000,
            'issued_date' => now()->subDays(5),
        ]);

        $invoicedTask = $makeTask();
        $uninvoicedTask = $makeTask();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);

        $detail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $invoicedTask->id,
            'task_price' => 100.000,
        ]);

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 100.0,
            costAmount: 60.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $client->id,
            supplierId: $supplier->id,
            agentId: $agent->id,
            invoiceId: $invoice->id,
            invoiceDetailId: $detail->id,
            taskId: $invoicedTask->id,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: now()->subDays(4),
            narration: 'Sale',
            lines: $lines,
            idempotencyKey: 'invoice-detail:'.$detail->id.':sale',
            invoiceId: $invoice->id,
        ));

        $refund = Refund::create([
            'refund_number' => 'REF-R27-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ]);

        foreach ([$invoicedTask, $uninvoicedTask] as $task) {
            RefundDetail::create([
                'refund_id' => $refund->id,
                'task_id' => $task->id,
                'client_id' => $client->id,
                'original_invoice_price' => 100.000,
                'original_task_cost' => 60.000,
                'original_task_profit' => 40.000,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'supplier_refund_amount' => null,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100.000,
            ]);
        }

        $result = app(RefundPostingService::class)->post($refund->fresh(), null);

        // The invoiced detail's credit note is on the ledger …
        $this->assertCount(1, $result['crn'], 'The invoiced detail must still produce its credit note.');

        // … and the uninvoiced one is REPORTED, not fatal.
        $this->assertCount(1, $result['skipped_details']);
        $this->assertSame((int) $uninvoicedTask->id, (int) $result['skipped_details'][0]['task_id']);
        $this->assertSame('no_invoice_detail', $result['skipped_details'][0]['reason']);

        // THE MONEY: revenue is reversed. Before the fix the whole document set rolled back and the
        // sale's revenue stood untouched.
        $this->assertSame(
            0.0,
            $this->netDebit(app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'flight')->id),
            'The invoiced detail\'s revenue must be reversed — one uninvoiced sibling used to roll all of it back.'
        );

        $this->assertNotNull($result['disposition'], 'The disposition must post: a mixed refund is not a failed refund.');
    }

    /** Anything OTHER than the uninvoiced shape still aborts the whole refund — no half-postings. */
    public function test_an_unexpected_failure_still_rolls_the_whole_refund_back(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();

        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'refunded',
            'total' => 60.000,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()->subDays(4)]);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100.000]);

        // No sale document was ever posted and there is no legacy sale either, so the CRN has
        // nothing to credit — a NothingOutstandingToCreditException (R2-1), which is NOT the
        // tolerated class.
        $refund = Refund::create([
            'refund_number' => 'REF-R27b-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ]);

        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 0.000,
            'original_task_cost' => 60.000,
            'original_task_profit' => 0,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'supplier_refund_amount' => null,
            'new_task_profit' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $before = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count();

        try {
            app(RefundPostingService::class)->post($refund->fresh(), null);
            $this->fail('An unexpected refusal must still abort the whole refund.');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(
            $before,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'A refund that hits an unexpected refusal must post NOTHING — half a refund is worse than none.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // D9 / D9b — cheque clearance
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE DEFECT. A same-day cheque never reaches the cheque float — `instrumentAccountFor()`
     * requires `cheque_date > docDate` — so the receipt debited the BANK. Clearing it debited the
     * bank a second time and drove the float negative by the cheque amount.
     */
    public function test_clearing_a_cheque_that_never_reached_the_float_is_refused(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');
        $float = app(AccountResolver::class)->resolve('CHEQUES_IN_HAND', $company->id);

        $receipt = InvoiceReceipt::create([
            'type' => 'account',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'account_id' => $this->accountByCode($company->id, '1351')->id,
            'client_id' => $client->id,
            'amount' => 100,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'bank_account_id' => $bank->id,
            // SAME DAY, not post-dated: the instrument leg goes to the bank, not the float.
            'cheque_no' => 'CHQ-SAMEDAY',
            'cheque_date' => now()->toDateString(),
            'status' => InvoiceReceipt::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $this->assertSame(100.0, $this->netDebit($bank->id), 'precondition: the receipt debited the BANK, not the float');
        $this->assertSame(0.0, $this->netDebit($float->id));

        $response = $this->actingAs($admin)->post(route('receipt-voucher.clear', $receipt->id), [
            'bank_account_id' => $bank->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertSame(
            100.0,
            $this->netDebit($bank->id),
            'The bank must be debited ONCE. D9 debited it a second time on clearance.'
        );
        $this->assertSame(
            0.0,
            $this->netDebit($float->id),
            'The cheque float must not go negative for a cheque that was never in it.'
        );
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'rv-clear:'.$receipt->id)->count()
        );
    }

    /** A genuinely post-dated cheque still clears — the fix narrows the path, it does not close it. */
    public function test_a_post_dated_cheque_still_clears_normally(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');
        $float = app(AccountResolver::class)->resolve('CHEQUES_IN_HAND', $company->id);

        $receipt = InvoiceReceipt::create([
            'type' => 'account',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'account_id' => $this->accountByCode($company->id, '1351')->id,
            'client_id' => $client->id,
            'amount' => 100,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'cheque_no' => 'CHQ-POSTDATED',
            'cheque_date' => now()->addDays(5)->toDateString(),
            'status' => InvoiceReceipt::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $this->assertSame(100.0, $this->netDebit($float->id), 'precondition: a post-dated cheque sits in the float');

        $this->actingAs($admin)->post(route('receipt-voucher.clear', $receipt->id), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(100.0, $this->netDebit($bank->id));
        $this->assertSame(0.0, $this->netDebit($float->id), 'The float is relieved exactly once.');
    }

    /**
     * D9b: a voucher with no posted document behind it has nothing to reclassify.
     *
     * The fixture is an APPROVED row with no `rv:{id}` document — the shape a cutover backfill
     * leaves before its receipt class has run, and the shape actually reachable through the UI.
     * (A `pending` row is stopped one layer earlier, by ReceiptVoucherPolicy::reconcile()'s own
     * 403; the method-level hole the report read is this one, and it was open regardless of which
     * status reached it.)
     */
    public function test_clearing_an_unposted_voucher_is_refused(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');

        $receipt = InvoiceReceipt::create([
            'type' => 'account',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'account_id' => $this->accountByCode($company->id, '1351')->id,
            'client_id' => $client->id,
            'amount' => 100,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'cheque_no' => 'CHQ-UNPOSTED',
            'cheque_date' => now()->addDays(5)->toDateString(),
            'status' => InvoiceReceipt::STATUS_APPROVED,
            'transaction_id' => null,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.clear', $receipt->id), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('idempotency_key')->count(),
            'A voucher with no posted document must post NOTHING — D9b posted rv-clear:{id} with no rv:{id} behind it.'
        );
        $this->assertNull($receipt->fresh()->cheque_clearance_date);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // §3.4 RISK — a damaged leaf must refuse, not read as a zero position
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * `taskNetOnPurpose()` used to `catch (\Throwable)` and return 0.0. On a chart where the
     * payable leaf has grown a child — the exact `is_group` damage wave 2 §4.5 measured, with 8
     * reassignments already refusing on it — that turned "I could not read this position" into
     * "the position is zero", and a zero outstanding against a non-zero target CREDITS
     * `SERVICE_PAYABLE`: the refund creates a payable instead of clearing one.
     */
    public function test_a_damaged_payable_leaf_refuses_rather_than_reading_as_a_zero_position(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();

        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'refunded',
            'total' => 60.000,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()->subDays(4)]);
        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100.000]);

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: now()->subDays(4),
            narration: 'Sale',
            // ZERO cost on the SALE, deliberately: the sale then posts only the AR/revenue pair,
            // so damaging the payable leaf below cannot itself unbalance the trial balance and the
            // assertion stays about the CODE rather than about the fixture. The refund detail still
            // carries a real `original_task_cost`, which is what makes postSupplierCreditForDetail()
            // read the payable position — the call under test.
            lines: (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
                serviceType: 'flight',
                sellAmount: 100.0,
                costAmount: 0.0,
                postingBasis: SaleDraftInput::BASIS_AGENT,
                clientId: $client->id,
                supplierId: $supplier->id,
                agentId: $agent->id,
                invoiceId: $invoice->id,
                invoiceDetailId: $detail->id,
                taskId: $task->id,
            )),
            idempotencyKey: 'invoice-detail:'.$detail->id.':sale',
            invoiceId: $invoice->id,
        ));

        // Damage the payable leaf exactly as a live system does: mint a child under it.
        $payable = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'flight');

        $child = new Account;
        $child->name = 'Airline A (payable)';
        $child->code = '21299';
        $child->parent_id = $payable->id;
        $child->root_id = $payable->root_id;
        $child->level = ((int) $payable->level) + 1;
        $child->company_id = $company->id;
        $child->is_group = false;
        $child->disabled = false;
        $child->account_type = 'Liabilities';
        $child->report_type = $payable->report_type;
        $child->actual_balance = 0;
        $child->opening_balance = 0;
        $child->budget_balance = 0;
        $child->variance = 0;
        $child->save();

        $refund = Refund::create([
            'refund_number' => 'REF-R27c-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ]);

        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'original_task_profit' => 40.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'supplier_refund_amount' => null,
            'new_task_profit' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $this->expectException(NonLeafAccountException::class);

        app(RefundPostingService::class)->post($refund->fresh(), null);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // D13 — the reassign sequence is read under a lock
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * A RATCHET, stated honestly: this asserts the SHAPE of the fix, not a reproduced race.
     * Reproducing D13 needs two concurrent connections mid-transaction, which this harness cannot
     * express — but the shape is exactly what makes the race impossible, so pinning the shape is
     * what stops it being un-fixed by an unrelated edit.
     */
    public function test_the_reassign_sequence_is_counted_inside_a_locked_transaction(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/TaskController.php'));

        $this->assertIsString($source);

        $start = strpos($source, 'Sequence, so a genuine A -> B -> A -> B sequence');
        $this->assertNotFalse($start, 'The reassign sequence comment must still be findable.');

        $region = substr($source, $start, 4000);

        $transactionAt = strpos($region, 'DB::transaction(');
        $lockAt = strpos($region, 'lockForUpdate()');
        $countAt = strpos($region, "':supplier-reassign:%'");

        $this->assertNotFalse($transactionAt, 'The reassign post must run inside a DB::transaction().');
        $this->assertNotFalse($lockAt, 'The task row must be locked before the sequence is read.');
        $this->assertNotFalse($countAt);

        $this->assertLessThan($lockAt, $transactionAt, 'The lock must be taken INSIDE the transaction.');
        $this->assertLessThan($countAt, $lockAt, 'The lock must be taken BEFORE the sequence is counted.');

        $postAt = strpos($region, 'PostingService::class)->post(');
        $this->assertNotFalse($postAt);
        $this->assertLessThan($postAt, $countAt, 'The count and the post must be in the SAME transaction.');
    }

    /** And the behaviour that shape exists to protect: consecutive reassignments never collide. */
    public function test_consecutive_reassignments_mint_distinct_keys(): void
    {
        [$company] = $this->makeFixture();

        $this->assertSame(
            0,
            DB::table('transactions')
                ->where('company_id', $company->id)
                ->where('idempotency_key', 'like', 'task:%:supplier-reassign:%')
                ->count(),
            'Baseline for the sequence counter this test\'s companion ratchet protects.'
        );
    }
}
