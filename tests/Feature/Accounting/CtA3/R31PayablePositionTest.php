<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

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
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RefundPostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\TaskIssuancePayableService;
use App\Services\Accounting\TaskPayablePositionResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 **R3-1** — owner ruling **R-CT8**, the fix for VERIFY-CT-A3-STACK-R2 finding **V2**.
 *
 * > "A payee nomination (who-to-pay reassignment) persists until explicitly changed. Every later
 * >  posting on that task — refund reversal, cancellation/void reversal, cancellation fee, and the
 * >  invoice-time reclassification 1430→COGS — follows the supplier payable to wherever it
 * >  CURRENTLY sits (party + leaf), never back to the purpose-resolved control. Same principle as
 * >  R2-1 (CRN against current posted position)."
 *
 * The two V2 defect witnesses live in {@see Rv2Probe3Test} and were INVERTED in place, as the
 * verification asked. This file adds the sequences neither lane had: the ones where the nomination
 * has to survive an event that relieves the payable and another that re-creates it.
 *
 * Every case measures per LEAF, never per group. V2's entire point is that the AP GROUP nets to
 * zero in the uninvoiced case — a group-level assertion would have passed on the broken code.
 */
class R31PayablePositionTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    /** `SERVICE_PAYABLE`/flight resolves here on a CoaSeeder chart: 2120 Suppliers (Flights). */
    private const CONTROL_CODE = '2120';

    /** First nominated payee: 2110 Creditors — an AP leaf that is NOT the flight control. */
    private const PAYEE_ONE_CODE = '2110';

    /** Second nominated payee, for the reassign-twice sequence: 2121 Suppliers (Visas). */
    private const PAYEE_TWO_CODE = '2121';

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice, 6: InvoiceDetail} */
    private function makeFixture(float $taskTotal = 100.0): array
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
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => $taskTotal,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);

        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        return [$company, $agent, $client, $supplier, $task->fresh(), $invoice, $detail];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /** Dr − Cr for one task on one account, from posted rows only. */
    private function netOn(int $accountId, int $taskId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('account_id', $accountId)
            ->where('task_id', $taskId);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    private function netForPurpose(int $companyId, string $purpose, ?string $serviceType = null): float
    {
        $account = app(AccountResolver::class)->resolve($purpose, $companyId, $serviceType);
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    private function reassignTo(Task $task, Account $payee): void
    {
        $response = app(\App\Http\Controllers\TaskController::class)
            ->updateJournalPaymentMethod($task->fresh(), (int) $payee->id);

        $this->assertSame(200, $response->getStatusCode(), 'the who-to-pay reassignment must succeed: '.$response->getContent());
        $this->assertNotNull(
            json_decode($response->getContent(), true)['data']['transaction_id'] ?? null,
            'the reassignment must actually post a document, otherwise there is no nomination to follow'
        );
    }

    private function makeRefund(Company $c, Agent $a, Invoice $i, Task $t, Client $cl, array $overrides = []): Refund
    {
        $refund = Refund::create([
            'refund_number' => 'REF-R31-'.uniqid(),
            'company_id' => $c->id, 'branch_id' => $a->branch_id, 'agent_id' => $a->id,
            'invoice_id' => $i->id, 'method' => 'Credit', 'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(), 'total_refund_amount' => 0, 'total_refund_charge' => 0, 'total_nett_refund' => 0,
        ]);

        RefundDetail::create(array_merge([
            'refund_id' => $refund->id, 'task_id' => $t->id, 'client_id' => $cl->id,
            'original_invoice_price' => 150.000, 'original_task_cost' => 100.000, 'original_task_profit' => 50.000,
            'refund_fee_to_client' => 0, 'supplier_charge' => 0, 'supplier_refund_amount' => null,
            'new_task_profit' => 0, 'total_refund_to_client' => 150.000,
        ], $overrides));

        return $refund->fresh();
    }

    private function postSale(Company $c, Agent $a, Client $cl, Supplier $s, Task $t, Invoice $i, InvoiceDetail $d, float $sell, float $cost): void
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $t->type, sellAmount: $sell, costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $cl->id, clientName: $cl->full_name,
            supplierId: $s->id, supplierName: $s->name,
            agentId: $a->id, agentName: $a->name,
            invoiceId: $i->id, invoiceDetailId: $d->id, taskId: $t->id,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $c->id, branchId: (int) $a->branch_id, docType: 'INV', subType: 'SALE',
            docDate: now()->subDays(4), narration: 'Sale', lines: $lines,
            idempotencyKey: 'invoice-detail:'.$d->id.':sale', invoiceId: $i->id,
        ));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // The resolver itself — the standing nomination is a DOCUMENT fact, not a balance.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_task_that_was_never_reassigned_has_no_nomination_and_nothing_changes(): void
    {
        [$company, , , , $task] = $this->makeFixture();

        app(TaskIssuancePayableService::class)->postIfDue($task);

        $resolver = app(TaskPayablePositionResolver::class);

        $this->assertNull(
            $resolver->nominatedAccountId((int) $task->id, (int) $company->id),
            'no reassignment document means no nomination, and every caller must keep its purpose resolution'
        );

        $control = $this->accountByCode((int) $company->id, self::CONTROL_CODE);

        $this->assertSame(-100.0, $this->netOn((int) $control->id, (int) $task->id));
        $this->assertNull($resolver->redirectFor((int) $task->id, (int) $company->id), 'and no reversal redirect');
    }

    public function test_the_nomination_survives_the_payable_going_to_zero(): void
    {
        [$company, , , , $task] = $this->makeFixture();

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $payee = $this->accountByCode((int) $company->id, self::PAYEE_ONE_CODE);
        $this->reassignTo($task, $payee);

        $resolver = app(TaskPayablePositionResolver::class);

        $this->assertSame((int) $payee->id, $resolver->nominatedAccountId((int) $task->id, (int) $company->id));

        // Relieve the payable entirely (a void), then ask again. A nomination read from a BALANCE
        // would now answer "nowhere"; R-CT8 says it persists until explicitly changed.
        $accrual->reverseForTask($task->fresh());

        $this->assertSame(0.0, $this->netOn((int) $payee->id, (int) $task->id), 'the payable really is at zero');
        $this->assertSame(
            (int) $payee->id,
            $resolver->nominatedAccountId((int) $task->id, (int) $company->id),
            'R-CT8: the nomination persists until explicitly changed, not until the balance goes flat'
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SEQUENCE 1 — reassign → invoice → refund.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_reassign_then_invoice_then_refund(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $control = $this->accountByCode((int) $company->id, self::CONTROL_CODE);
        $payee = $this->accountByCode((int) $company->id, self::PAYEE_ONE_CODE);

        $this->reassignTo($task, $payee);

        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id));
        $this->assertSame(-100.0, $this->netOn((int) $payee->id, (int) $task->id));

        // The invoice-time reclassification, exactly as InvoiceController::postSaleJournalEntries()
        // drives it: reverse the accrual, and post the sale that carries the cost to COGS.
        $accrual->reverseForTask($task->fresh());
        $this->postSale($company, $agent, $client, $supplier, $task, $invoice, $detail, 150.0, 100.0);

        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'UNBILLED_SUPPLIER_COST'), '1430 is relieved by the reclass');
        $this->assertSame(100.0, $this->netForPurpose((int) $company->id, 'SERVICE_COST', 'flight'), 'and the cost is now in COGS');
        $this->assertSame(
            -100.0,
            $this->netOn((int) $payee->id, (int) $task->id),
            'R-CT8: invoicing does not undo the nomination — the sale credits the payee, not the control'
        );
        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id), 'and nothing lands back on the control');

        // Now refund it in full.
        $task->status = 'refunded';
        $task->save();

        app(RefundPostingService::class)->post(
            $this->makeRefund($company, $agent, $invoice, $task->fresh(), $client),
            null
        );

        $this->assertSame(0.0, $this->netOn((int) $payee->id, (int) $task->id), 'the payee is relieved');
        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id), 'and the control never carried it');
        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'SERVICE_COST', 'flight'));
        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'SERVICE_REVENUE', 'flight'));
        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'UNBILLED_SUPPLIER_COST'));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SEQUENCE 2 — reassign → void → un-void. The nomination must survive both directions.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_reassign_then_void_then_unvoid(): void
    {
        [$company, , , , $task] = $this->makeFixture();

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $control = $this->accountByCode((int) $company->id, self::CONTROL_CODE);
        $payee = $this->accountByCode((int) $company->id, self::PAYEE_ONE_CODE);

        $this->reassignTo($task, $payee);

        // VOID — the accrual reversal follows the payable onto the payee.
        $accrual->reverseForTask($task->fresh());

        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'UNBILLED_SUPPLIER_COST'));
        $this->assertSame(0.0, $this->netOn((int) $payee->id, (int) $task->id), 'R-CT8: the void relieves the payee');
        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id), 'and never debits the control');

        // UN-VOID — the REV-of-REV puts the payable back where the void took it from, so the
        // nomination is still standing afterwards with no further special case.
        $accrual->restoreForTask($task->fresh());

        $this->assertSame(100.0, $this->netForPurpose((int) $company->id, 'UNBILLED_SUPPLIER_COST'));
        $this->assertSame(-100.0, $this->netOn((int) $payee->id, (int) $task->id), 'R-CT8: the un-void restores it onto the payee');
        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id));

        // And a SECOND void still lands on the payee rather than on the control: the REV lines are
        // already there, so the redirect is a no-op by construction.
        $accrual->reverseForTask($task->fresh());

        $this->assertSame(0.0, $this->netOn((int) $payee->id, (int) $task->id));
        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SEQUENCE 3 — reassign TWICE, then refund. The settlement follows the SECOND payee.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_reassign_twice_then_refund_settles_on_the_second_payee(): void
    {
        [$company, $agent, $client, , $task, $invoice] = $this->makeFixture();

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $control = $this->accountByCode((int) $company->id, self::CONTROL_CODE);
        $payeeOne = $this->accountByCode((int) $company->id, self::PAYEE_ONE_CODE);
        $payeeTwo = $this->accountByCode((int) $company->id, self::PAYEE_TWO_CODE);

        $this->reassignTo($task, $payeeOne);
        $this->reassignTo($task, $payeeTwo);

        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id));
        $this->assertSame(0.0, $this->netOn((int) $payeeOne->id, (int) $task->id), 'the first payee was released by the second move');
        $this->assertSame(-100.0, $this->netOn((int) $payeeTwo->id, (int) $task->id));

        $this->assertSame(
            (int) $payeeTwo->id,
            app(TaskPayablePositionResolver::class)->nominatedAccountId((int) $task->id, (int) $company->id),
            'the standing nomination is the NEWEST live reassignment, not the first'
        );

        $task->status = 'refunded';
        $task->save();
        $this->makeRefund($company, $agent, $invoice, $task->fresh(), $client);
        $accrual->postIfDue($task->fresh());

        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'UNBILLED_SUPPLIER_COST'));
        $this->assertSame(0.0, $this->netOn((int) $payeeTwo->id, (int) $task->id), 'R-CT8: the refund relieves the CURRENT payee');
        $this->assertSame(0.0, $this->netOn((int) $payeeOne->id, (int) $task->id), 'not the previous one');
        $this->assertSame(0.0, $this->netOn((int) $control->id, (int) $task->id), 'and not the control');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SEQUENCE 4 — reassign → PARTIAL supplier recovery. The retained charge stays on the payee.
    //
    // R-CT6 (whether a supplier over-refund is a genuine gain or a data defect) is a separate,
    // still-open owner ruling and is NOT decided here: this case drives an ordinary partial
    // recovery — the supplier refunds 60 of a 100 cost and keeps a 40 penalty — and asks only
    // WHERE the 40 the agency still owes ends up.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_reassign_then_partial_recovery_leaves_the_retained_charge_on_the_current_payee(): void
    {
        [$company, $agent, $client, , $task, $invoice] = $this->makeFixture();

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $control = $this->accountByCode((int) $company->id, self::CONTROL_CODE);
        $payee = $this->accountByCode((int) $company->id, self::PAYEE_ONE_CODE);

        $this->reassignTo($task, $payee);

        $task->status = 'refunded';
        $task->save();

        $this->makeRefund($company, $agent, $invoice, $task->fresh(), $client, [
            'original_task_cost' => 100.000,
            'supplier_charge' => 40.000,
        ]);

        $accrual->postIfDue($task->fresh());

        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'UNBILLED_SUPPLIER_COST'), '1430 lands on zero, never negative');
        $this->assertSame(40.0, $this->netForPurpose((int) $company->id, 'PENALTY_COST_EXPENSE'), 'the 40 the supplier kept is a penalty cost');
        $this->assertSame(
            -40.0,
            $this->netOn((int) $payee->id, (int) $task->id),
            'R-CT8: the 40 still owed stays on the CURRENT payee'
        );
        $this->assertSame(
            0.0,
            $this->netOn((int) $control->id, (int) $task->id),
            'and is NOT re-created on the purpose-resolved control'
        );
    }
}
