<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\TaskController;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CoaSeeder;
use Tests\Support\AccountingTestCase;

/**
 * R4 (P2-EXIT-REPORT.md §7 residual register): {@see TaskController::ReverseUnpaidVoidedTask()}
 * read the undefined property `$originalTask->supplier_date` instead of `supplier_pay_date` when
 * computing the reversal's `transaction_date` -- a pre-existing typo carried byte-for-byte since
 * HEAD, harmless in the sense that `TaskStatusService::dispatchFinancial()` intercepts
 * `status='void'` before this method is ever reached once the posting engine is ON for a company
 * (see that class's own `$engineOn && $status === 'void'` short-circuit), but a real defect on the
 * OFF/legacy path this method still serves: every reversal transaction/journal-entry silently
 * dated itself to "now" instead of the original task's actual supplier pay date. This test proves
 * the fix by calling the (public) method directly -- the same pattern
 * {@see CreditControllerW7KTest::test_build_credit_topup_draft_posted_twice_through_the_engine_directly_is_idempotent()}
 * uses to exercise a controller method without a full HTTP round trip.
 */
class TaskControllerReverseUnpaidVoidedTaskR4Test extends AccountingTestCase
{
    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier} */
    private function makeCompanyAgentClientSupplier(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'r4-reverse-unpaid-void-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'R4 Reverse Unpaid Void Test Supplier']);

        return [$company, $agent, $client, $supplier];
    }

    public function test_reversal_transaction_date_uses_supplier_pay_date_not_the_undefined_property(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();

        $supplierPayDate = Carbon::parse('2026-03-14');

        $originalTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'PNR-R4-' . uniqid(),
            'price' => 500.0,
            'total' => 350.0,
            'supplier_pay_date' => $supplierPayDate,
        ]);

        $originalTransaction = Transaction::create([
            'branch_id' => $agent->branch_id,
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 350.0,
            'description' => 'Original supplier payable for ' . $originalTask->reference,
            'transaction_date' => $supplierPayDate,
        ]);

        $payableAccount = \App\Models\Account::where('company_id', $company->id)
            ->where('name', 'like', '%Payable%')
            ->firstOrFail();

        JournalEntry::create([
            'transaction_id' => $originalTransaction->id,
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'account_id' => $payableAccount->id,
            'task_id' => $originalTask->id,
            'transaction_date' => $supplierPayDate,
            'description' => 'Supplier payable for ' . $originalTask->reference,
            'name' => $supplier->name,
            'debit' => 0,
            'credit' => 350.0,
            'balance' => 350.0,
            'type' => 'payable',
        ]);

        $voidTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'void',
            'reference' => $originalTask->reference,
            'original_task_id' => $originalTask->id,
            'price' => 0.0,
            'total' => 0.0,
        ]);

        // Freeze "now" far away from the fixture's supplier_pay_date so a fallback-to-now
        // regression is unambiguous.
        Carbon::setTestNow(Carbon::parse('2026-09-01 12:00:00'));

        try {
            $controller = app(TaskController::class);
            $controller->ReverseUnpaidVoidedTask($voidTask, $originalTask);
        } finally {
            Carbon::setTestNow();
        }

        $reversal = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('description', 'Void reversal: ' . $originalTask->reference)
            ->firstOrFail();

        $this->assertTrue(
            Carbon::parse($reversal->transaction_date)->isSameDay($supplierPayDate),
            'The reversal Transaction must date itself to the original task\'s supplier_pay_date, '
            . 'not silently fall back to "now" because of the undefined-property typo.'
        );

        $reversalLine = JournalEntry::where('transaction_id', $reversal->id)->firstOrFail();
        $this->assertTrue(
            Carbon::parse($reversalLine->transaction_date)->isSameDay($supplierPayDate),
            'The reversal JournalEntry line must carry the same supplier_pay_date-derived date.'
        );
    }
}
