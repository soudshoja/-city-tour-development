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
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * VERIFY CT-A3 STACK R2 — throwaway adversarial probes, part 3: second-order sequences and the
 * R1 regression variants.
 */
class Rv2Probe3Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice, 6: InvoiceDetail, 7: Branch, 8: User} */
    private function makeFixture(array $supplierAttributes = [], string $taskStatus = 'issued', float $taskTotal = 100.0): array
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
        $supplier = Supplier::factory()->create($supplierAttributes);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => $taskStatus,
            'total' => $taskTotal,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        return [$company, $agent, $client, $supplier, $task->fresh(), $invoice, $invoiceDetail, $branch, $admin];
    }

    private function netForPurpose(int $companyId, string $purpose, ?string $serviceType = null): float
    {
        $account = app(AccountResolver::class)->resolve($purpose, $companyId, $serviceType);
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    private function netDebit(int $accountId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $accountId);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    private function saleDraft(Company $c, Agent $a, Client $cl, Supplier $s, Task $t, Invoice $i, InvoiceDetail $d, float $sell, float $cost, string $key): DocumentDraft
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $t->type, sellAmount: $sell, costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $cl->id, clientName: $cl->full_name,
            supplierId: $s->id, supplierName: $s->name,
            agentId: $a->id, agentName: $a->name,
            invoiceId: $i->id, invoiceDetailId: $d->id, taskId: $t->id,
        ));

        return new DocumentDraft(
            companyId: $c->id, branchId: (int) $a->branch_id, docType: 'INV', subType: 'SALE',
            docDate: now()->subDays(4), narration: 'Sale', lines: $lines,
            idempotencyKey: $key, invoiceId: $i->id,
        );
    }

    private function makeRefund(Company $c, Agent $a, Invoice $i, Task $t, Client $cl, array $overrides = [], string $suffix = 'a'): Refund
    {
        $refund = Refund::create([
            'refund_number' => 'REF-RV3-'.$suffix.'-'.uniqid(),
            'company_id' => $c->id, 'branch_id' => $a->branch_id, 'agent_id' => $a->id,
            'invoice_id' => $i->id, 'method' => 'Credit', 'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(), 'total_refund_amount' => 0, 'total_refund_charge' => 0, 'total_nett_refund' => 0,
        ]);

        RefundDetail::create(array_merge([
            'refund_id' => $refund->id, 'task_id' => $t->id, 'client_id' => $cl->id,
            'original_invoice_price' => 120.000, 'original_task_cost' => 100.000, 'original_task_profit' => 20.000,
            'refund_fee_to_client' => 0, 'supplier_charge' => 0, 'supplier_refund_amount' => null,
            'new_task_profit' => 0, 'total_refund_to_client' => 120.000,
        ], $overrides));

        return $refund->fresh();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 10 — D1 VARIANT: confirm-after-unconfirmed-refund WITH a PARTIAL recovery.
    // R1 fixed D1 (full) and D2 (partial) separately; the brief asks for the combination.
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe10_unconfirmed_refund_then_confirmation_with_a_partial_recovery(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture([], 'issued', 100.0);

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task->fresh());

        $this->assertSame(100.0, $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'));

        // The supplier has NOT confirmed: status -> refund posts the refund-loss document.
        $task->status = 'refund';
        $task->save();
        $accrual->postIfDue($task->fresh());

        $loss1430 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $lossLeaf = $this->netForPurpose($company->id, 'SUPPLIER_REFUND_LOSS');
        $pay1 = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');

        // NOW the supplier confirms, and keeps 40 of the 100 as a penalty. THE COMBINATION:
        // D1 (the double relief) and D2 (the partial recovery) on the SAME task.
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);
        $this->makeRefund($company, $agent, $invoice, $task, $client, [
            'supplier_charge' => 40.000,
            'original_task_cost' => 100.000,
        ], 'p10');

        $task->status = 'refunded';
        $task->save();
        $accrual->postIfDue($task->fresh());

        $end1430 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $endLoss = $this->netForPurpose($company->id, 'SUPPLIER_REFUND_LOSS');
        $endPay = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        $endPenalty = $this->netForPurpose($company->id, 'PENALTY_COST_EXPENSE');

        fwrite(STDERR, sprintf(
            '
[PROBE10] after loss: 1430=%s loss=%s pay=%s | after confirm(keeps 40): 1430=%s loss=%s pay=%s penalty=%s
',
            $loss1430, $lossLeaf, $pay1, $end1430, $endLoss, $endPay, $endPenalty
        ));

        $this->assertSame(0.0, $end1430, 'D1: 1430 must land on zero, never negative');
        $this->assertSame(0.0, $endLoss, 'the refund loss must be reversed once the supplier confirms');
        $this->assertSame(-40.0, $endPay, 'D2: the 40 the supplier kept is still owed');
        $this->assertSame(40.0, $endPenalty, 'and it is a penalty expense, not a refund loss');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 11 — second-order: reassign the payee, THEN refund, THEN the supplier confirms.
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe11_reassign_then_refund_then_supplier_confirm(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail, $branch, $admin] = $this->makeFixture([], 'issued', 100.0);

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $payableBefore = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        $this->assertSame(-100.0, $payableBefore);

        // Move the payable to a nominated payee account.
        $payee = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2110')->first()
            ?? Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        $response = app(\App\Http\Controllers\TaskController::class)
            ->updateJournalPaymentMethod($task->fresh(), (int) $payee->id);

        fwrite(STDERR, "\n[PROBE11 reassign] status=".$response->getStatusCode().' body='.$response->getContent()."\n");

        $payableAfterReassign = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        $payeeAfterReassign = $this->netDebit($payee->id);

        // Now refund the (uninvoiced) task with the supplier keeping nothing.
        $task->status = 'refunded';
        $task->save();
        $this->makeRefund($company, $agent, $invoice, $task->fresh(), $client, [], 'r11');
        $accrual->postIfDue($task->fresh());

        $end1430 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $endPayableControl = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        $endPayee = $this->netDebit($payee->id);

        fwrite(STDERR, sprintf(
            "[PROBE11] payable(control) %s -> %s -> %s | payee leaf %s -> %s | 1430 end=%s\n",
            $payableBefore, $payableAfterReassign, $endPayableControl, $payeeAfterReassign, $endPayee, $end1430
        ));

        $this->assertSame(0.0, $end1430, 'the accrual asset must end relieved');

        // ── FINDING V2, INVERTED BY CT-A3 R3-1 (owner ruling R-CT8) ─────────────────────────────
        // This case was committed by the verify-R2 lane as a DEFECT WITNESS: it asserted the
        // measured wrong behaviour — control +100.000 DEBIT, payee −100.000 — and was labelled to
        // be INVERTED, not deleted, when the ruling landed. R-CT8 landed:
        //
        //   "A payee nomination (who-to-pay reassignment) persists until explicitly changed. Every
        //    later posting on that task — refund reversal, cancellation/void reversal, cancellation
        //    fee, and the invoice-time reclassification 1430→COGS — follows the supplier payable to
        //    wherever it CURRENTLY sits (party + leaf), never back to the purpose-resolved control."
        //
        // The refund's accrual reversal now debits the NOMINATED PAYEE, so both leaves land on
        // zero. The AP group still nets to zero — it always did, which is why no aggregate check in
        // either wave report could see the defect — but now each LEAF is right as well.
        $this->assertSame(0.0, round($endPayableControl + $endPayee, 3), 'the AP GROUP nets to zero');
        $this->assertSame(0.0, $endPayableControl, 'R-CT8: the AP control leaf is flat — the refund never posted back to it');
        $this->assertSame(0.0, $endPayee, 'R-CT8: the nominated payee is no longer owed for a refunded booking');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 13 — the INVOICED mirror of probe 11: does the CRN path relieve the reassigned payee?
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe13_invoiced_refund_after_a_reassignment(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture([], 'issued', 100.0);

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $payee = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2110')->first()
            ?? Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        app(\App\Http\Controllers\TaskController::class)->updateJournalPaymentMethod($task->fresh(), (int) $payee->id);

        // Now INVOICE it (the sale reclassifies 1430 to COGS) and refund it through the CRN path.
        app(PostingService::class)->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 150.0, 100.0, 'invoice-detail:'.$detail->id.':sale')
        );

        $task->status = 'refunded';
        $task->save();

        $refund = $this->makeRefund($company, $agent, $invoice, $task->fresh(), $client, [
            'original_invoice_price' => 150.000, 'total_refund_to_client' => 150.000, 'original_task_cost' => 100.000,
        ], 'p13');

        app(RefundPostingService::class)->post($refund, null);

        $control = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        $payeeLeaf = $this->netDebit($payee->id);
        $cogs = $this->netForPurpose($company->id, 'SERVICE_COST', 'flight');

        $unbilled = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $rev = $this->netForPurpose($company->id, 'SERVICE_REVENUE', 'flight');
        $ar = $this->netForPurpose($company->id, 'RECEIVABLE_CONTROL');

        fwrite(STDERR, sprintf(
            '
[PROBE13 invoiced] payable control=%s payee leaf=%s cogs=%s 1430=%s revenue=%s AR=%s
',
            $control, $payeeLeaf, $cogs, $unbilled, $rev, $ar
        ));

        // ── FINDING V2, invoiced half, INVERTED BY CT-A3 R3-1 (owner ruling R-CT8) ──────────────
        // The committed witness asserted the measured wrong end state: the ledger carrying KWD 100
        // of accounts payable to the nominated payee with no cost, no revenue and no asset behind
        // it, PERMANENTLY — the shape an AP payment run pays out and an income-statement review
        // never sees. Two independently reasonable halves lost the money: the sale's payable leg
        // resolved by PURPOSE (back to the control) while the CRN's supplier credit MEASURED the
        // control, found it already at zero, and correctly concluded there was nothing to relieve.
        //
        // Under R-CT8 both halves follow the nomination: the sale credits the payee, the credit
        // note debits it back, and the supplier credit measures the leaf the money is actually on.
        $this->assertSame(0.0, round($control + $payeeLeaf, 3), 'R-CT8: AP owes nothing after a full refund');
        $this->assertSame(0.0, $payeeLeaf, 'R-CT8: the nominated payee leaf is relieved, not left owed');
        $this->assertSame(0.0, $control, 'R-CT8: and nothing landed back on the purpose-resolved control');
        $this->assertSame(0.0, $cogs, 'no cost stands against it');
        $this->assertSame(0.0, $unbilled, 'the accrual asset is relieved');
        $this->assertSame(0.0, $rev, 'and the revenue is reversed in full');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 12 — D4 VARIANT: void posts a supplier cancellation fee, un-void must take it back,
    // and a SECOND void must re-charge it (the report's per-task-key RISK).
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe12_void_unvoid_void_again(): void
    {
        [$company, , , $supplier, $task] = $this->makeFixture([], 'issued', 100.0);

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $base = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');

        $accrual->reverseForTask($task);
        $accrual->restoreForTask($task);
        $accrual->reverseForTask($task);
        $accrual->restoreForTask($task);

        $end = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $endPay = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');

        fwrite(STDERR, "\n[PROBE12] 1430 base={$base} after two void/un-void cycles={$end} pay={$endPay}\n");

        $this->assertSame($base, $end, 'arbitrary void/un-void cycles must land back on the accrual exactly');
        $this->assertSame(-$base, $endPay);
    }
}
