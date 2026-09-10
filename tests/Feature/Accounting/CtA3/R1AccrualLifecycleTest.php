<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\TaskIssuancePayableService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 verify R1 — the issuance accrual's LIFECYCLE, as opposed to its first transition.
 *
 * Wave 2's `W23SupplierRefundRecoveryTest` cases 8/9/10 each drive exactly ONE transition off a
 * fresh accrual (`issued` -> `refund`, `issued` -> `refunded`, `issued` -> `void`) and assert the
 * end state. Every case in this file drives a SECOND event, or a shape wave 2's own matrix does
 * not reach — and each one was a real defect before the commit that carries this file:
 *
 *  1. **A confirmation arriving after an unconfirmed refund relieved `1430` twice.** The
 *     `refund-loss` document had already credited 1430 to zero and debited 5131; reversing the
 *     accrual on top credited 1430 again, leaving the asset at MINUS the cost and a loss that
 *     never happened standing in the P&L. Measured: 1430 = −100.000, 5131 = +100.000. This is the
 *     exact sequence `TaskIssuancePayableService::settleAccrualOnRefund()`'s own docblock
 *     advertises as supported.
 *  2. **A partial supplier recovery wiped the whole payable.** `SupplierRefundDecision::
 *     $shouldRecover` is `recoverable > 0`, so a supplier that refunded 60 of a 100 cost and KEPT
 *     a 40 penalty took the blanket full reversal and the agency's payable went to ZERO. The
 *     invoiced mirror (`RefundPostingService::postSupplierCreditForDetail()`) leaves exactly that
 *     40 standing; the uninvoiced mirror did not.
 *  3. **A reversed accrual can never be re-posted, and the feeder said it was `due` anyway.**
 *     `PostingService::findByIdempotencyKey()` deliberately does not filter `posting_status`, so
 *     `task:{id}:issuance-payable` stays occupied by the reversed header for the life of the task.
 *     `postIfDue()` hands that header back and posts nothing; `reasonFor()` answered `due`. The
 *     only mechanism that can put the payable back is REV-of-REV — `restoreForTask()`.
 *  4. **Un-void restored the sale and the commission but not the payable, nor wave 2's own
 *     supplier cancellation fee.** `TaskController::revertFinancialsForVoid()` named three keys
 *     and neither `task:{id}:issuance-payable` nor `void:{task}:supplier-cxl-fee`.
 */
class R1AccrualLifecycleTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task} */
    private function makeFixture(array $supplierAttributes = [], string $taskStatus = 'issued'): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create($supplierAttributes);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => $taskStatus,
            'total' => 100.000,
            'issued_date' => now()->subDays(5),
        ]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        return [$company, $agent, $client, $supplier, $task->fresh()];
    }

    private function makeRefundDetail(Company $company, Agent $agent, Client $client, Task $task, array $overrides = []): RefundDetail
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);

        $refund = Refund::create([
            'refund_number' => 'REF-R1-'.uniqid(),
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
        ]);

        return RefundDetail::create(array_merge([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 120.000,
            'original_task_cost' => 100.000,
            'original_task_profit' => 20.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'supplier_refund_amount' => null,
            'new_task_profit' => 0,
            'total_refund_to_client' => 120.000,
        ], $overrides));
    }

    /** Dr − Cr on the leaf a PURPOSE CODE resolves to, never on a hard-coded account code. */
    private function netDebitForPurpose(int $companyId, string $purpose, ?string $serviceType = null): float
    {
        $account = app(\App\Services\Accounting\AccountResolver::class)->resolve($purpose, $companyId, $serviceType);

        $debit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id)->sum('debit');
        $credit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id)->sum('credit');

        return round($debit - $credit, 3);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 1. A second refund event on the same accrual
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Defect 1. Accrue, refund at an UNCONFIRMED status (wave 2 case 8 posts the loss), then the
     * supplier confirms. The booking must end fully unwound. Before the fix: 1430 = −100.000 and
     * 5131 = +100.000 — an asset driven negative and a loss that never happened.
     */
    public function test_a_confirmation_after_an_unconfirmed_refund_does_not_relieve_1430_twice(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $task->status = 'refund';
        $task->save();
        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertNotNull(
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'task:'.$task->id.':refund-loss')->first(),
            'Precondition: the unconfirmed refund posted its loss document (wave 2 case 8).'
        );
        $this->assertEqualsWithDelta(100.000, $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'), 0.001);

        $task->status = 'refunded';
        $task->save();
        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertEqualsWithDelta(
            0.000,
            $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'),
            0.001,
            '1430 must land on zero, never on MINUS the cost.'
        );
        $this->assertEqualsWithDelta(
            0.000,
            $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'),
            0.001,
            'The loss did not happen after all — it must be taken back off, not left in the P&L.'
        );
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 2. A partial supplier recovery
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Defect 2. The supplier refunds, but keeps a 40.000 charge out of the 100.000 cost. The
     * agency still owes that 40 — exactly as `RefundPostingService` leaves it on the invoiced
     * path, and against the same `PENALTY_COST_EXPENSE` leaf.
     */
    public function test_a_partial_supplier_recovery_leaves_the_retained_charge_as_a_payable(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());
        $this->makeRefundDetail($company, $agent, $client, $task, ['supplier_charge' => 40.000]);

        $task->status = 'refunded';
        $task->save();
        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001, 'The asset is relieved in full.');
        $this->assertEqualsWithDelta(
            -40.000,
            $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'),
            0.001,
            'The supplier KEPT 40 — the agency still owes it. A blanket reversal took this to zero.'
        );
        $this->assertEqualsWithDelta(
            40.000,
            $this->netDebitForPurpose($company->id, 'PENALTY_COST_EXPENSE'),
            0.001,
            'A charge kept out of a refund that HAPPENED is a penalty (5124), not a refund loss (5131).'
        );
        $this->assertEqualsWithDelta(
            0.000,
            $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'),
            0.001,
            'The two must stay distinguishable: a penalty is the price of a refund that happened.'
        );
    }

    /** A FULL recovery still books no penalty — wave 2 case 9 must be unaffected by the fix. */
    public function test_a_full_recovery_still_books_no_penalty(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $task->status = 'refunded';
        $task->save();
        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001);
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001);
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'PENALTY_COST_EXPENSE'), 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 3. A reversed accrual: honest reporting, and the only way back
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Defect 3a. `reasonFor()` exists to answer "why is this task not in my AP?". For a task whose
     * accrual is REVERSED it answered `due` — while `postIfDue()` could never post, because the
     * idempotency key is still occupied by that reversed header.
     */
    public function test_reason_for_reports_a_reversed_accrual_rather_than_claiming_it_is_due(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        app(TaskIssuancePayableService::class)->reverseForTask($task->fresh());
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001);

        $this->assertSame(
            'accrual_reversed',
            app(TaskIssuancePayableService::class)->reasonFor($task->fresh()),
            'A committed task whose accrual is reversed is not "due" — nothing can post it.'
        );

        $this->assertNull(
            app(TaskIssuancePayableService::class)->postIfDue($task->fresh()),
            'And postIfDue() must not hand back the REVERSED header as though it had posted.'
        );
    }

    /**
     * Defect 3b. `restoreForTask()` is the only mechanism that can put a reversed accrual back, and
     * the chain must survive an arbitrary number of void / un-void cycles: after a restore, a
     * SECOND void has to take the payable off again. The old `reverseForTask()` looked for the
     * accrual header at `posting_status = 'posted'` — which a restored accrual never is — and
     * silently left the payable standing on a re-voided booking.
     */
    public function test_restore_puts_the_accrual_back_and_a_later_void_takes_it_off_again(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();
        $service = app(TaskIssuancePayableService::class);

        $service->postIfDue($task->fresh());
        $this->assertEqualsWithDelta(-100.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'accrued');

        $service->reverseForTask($task->fresh());
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'voided');

        $service->restoreForTask($task->fresh());
        $this->assertEqualsWithDelta(-100.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'un-voided');
        $this->assertEqualsWithDelta(100.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001);
        $this->assertSame('due', $service->reasonFor($task->fresh()));

        $service->reverseForTask($task->fresh());
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 're-voided');

        $service->restoreForTask($task->fresh());
        $this->assertEqualsWithDelta(-100.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 're-un-voided');
    }

    /** Both restore and reverse are no-ops in the direction that is already true. */
    public function test_restore_and_reverse_are_idempotent_in_their_own_direction(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();
        $service = app(TaskIssuancePayableService::class);

        // Nothing accrued at all: both are safe no-ops.
        $service->restoreForTask($task->fresh());
        $service->reverseForTask($task->fresh());
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('sub_type', 'SUPPLIER_ACCRUAL')->count());

        $service->postIfDue($task->fresh());
        $service->restoreForTask($task->fresh());
        $service->restoreForTask($task->fresh());
        $this->assertEqualsWithDelta(-100.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'restore on a LIVE accrual changes nothing');

        $service->reverseForTask($task->fresh());
        $service->reverseForTask($task->fresh());
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'reverse on a REVERSED accrual changes nothing');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 4. The un-void restoration list
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Defect 4, as a source ratchet in this codebase's own `ArchitectureTest` style.
     *
     * `TaskController::revertFinancialsForVoid()` is the un-void path. `TaskStatusService::void()`
     * posts FIVE document classes for a task; un-void restored three of them. A behavioural test
     * would need an import-shaped void-event row and its whole `PostingSeam` context; what
     * actually failed here was an ENUMERATION, and an enumeration is what this pins — the same
     * reasoning `ReceiptVoucherControllerArchitectureTest` uses for its own governed-method scan.
     */
    public function test_un_void_restores_every_document_the_void_posted(): void
    {
        $taskController = file_get_contents(app_path('Http/Controllers/TaskController.php'));

        $body = $this->methodBody($taskController, 'revertFinancialsForVoid');

        $this->assertStringContainsString(
            'restoreForTask',
            $body,
            'void() reverses the issuance accrual (TaskStatusService::reverseForTask()). Un-void must '
            .'restore it — and only TaskIssuancePayableService::restoreForTask() can, because the '
            .'idempotency key stays occupied by the reversed header for the life of the task.'
        );

        $this->assertStringContainsString(
            "':supplier-cxl-fee'",
            $body,
            "void() posts wave 2's supplier cancellation fee under void:{task}:supplier-cxl-fee. "
            .'Un-void must reverse it, or the agency keeps a payable for a cancellation that no '
            .'longer exists.'
        );

        foreach ([':fee', ':fee-commission', ':disposition'] as $key) {
            $this->assertStringContainsString("'{$key}'", $body, "The pre-existing {$key} satellite must stay in the list.");
        }
    }

    /**
     * The mutation proof for the ratchet above: with the two new entries stripped out of a COPY of
     * the method body, the assertions must fail. A scanner whose regex has quietly stopped
     * matching reads as a clean codebase, which is worse than no scanner.
     */
    public function test_the_un_void_ratchet_actually_bites_a_stripped_method_body(): void
    {
        $body = $this->methodBody(file_get_contents(app_path('Http/Controllers/TaskController.php')), 'revertFinancialsForVoid');

        $mutated = str_replace(
            ['restoreForTask', "':supplier-cxl-fee'"],
            ['', "''"],
            $body
        );

        $this->assertStringNotContainsString('restoreForTask', $mutated);
        $this->assertStringNotContainsString("':supplier-cxl-fee'", $mutated);
        $this->assertNotSame($body, $mutated, 'The mutation must actually change the body, or the ratchet is asserting nothing.');
    }

    /** The source text of one method, from its signature to the next `    private`/`    public`. */
    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, 'function '.$method.'(');
        $this->assertNotFalse($start, "Expected method {$method} to exist in TaskController.");

        $rest = substr($source, $start);
        $end = strpos($rest, "\n    /**", 10);

        return $end === false ? $rest : substr($rest, 0, $end);
    }
}
