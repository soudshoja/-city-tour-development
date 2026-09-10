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
use App\Services\Accounting\TaskIssuancePayableService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * VERIFY CT-A3 STACK R2 — throwaway adversarial probes. NOT part of the shipped suite unless a
 * case here exposes a defect.
 */
class Rv2ProbeTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice, 6: InvoiceDetail, 7: Branch, 8: User} */
    private function makeFixture(array $supplierAttributes = [], string $taskStatus = 'refunded', float $taskTotal = 60.0): array
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

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
        ]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        return [$company, $agent, $client, $supplier, $task->fresh(), $invoice, $invoiceDetail, $branch, $admin];
    }

    private function saleDraft(Company $c, Agent $a, Client $cl, Supplier $s, Task $t, Invoice $i, InvoiceDetail $d, float $sell, float $cost, string $key): DocumentDraft
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $t->type,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $cl->id,
            clientName: $cl->full_name,
            supplierId: $s->id,
            supplierName: $s->name,
            agentId: $a->id,
            agentName: $a->name,
            invoiceId: $i->id,
            invoiceDetailId: $d->id,
            taskId: $t->id,
        ));

        return new DocumentDraft(
            companyId: $c->id,
            branchId: (int) $a->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: now()->subDays(4),
            narration: 'Sale',
            lines: $lines,
            idempotencyKey: $key,
            invoiceId: $i->id,
        );
    }

    private function makeRefund(Company $c, Agent $a, Invoice $i, Task $t, Client $cl, array $detailOverrides = [], string $suffix = 'a'): Refund
    {
        $refund = Refund::create([
            'refund_number' => 'REF-RV2-'.$suffix.'-'.uniqid(),
            'company_id' => $c->id,
            'branch_id' => $a->branch_id,
            'agent_id' => $a->id,
            'invoice_id' => $i->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ]);

        RefundDetail::create(array_merge([
            'refund_id' => $refund->id,
            'task_id' => $t->id,
            'client_id' => $cl->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'original_task_profit' => 40.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'supplier_refund_amount' => null,
            'new_task_profit' => 0,
            'total_refund_to_client' => 100.000,
        ], $detailOverrides));

        return $refund->fresh();
    }

    private function netForPurpose(int $companyId, string $purpose, ?string $serviceType = null): float
    {
        $account = app(AccountResolver::class)->resolve($purpose, $companyId, $serviceType);

        return $this->netDebit($account->id);
    }

    private function netDebit(int $accountId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $accountId);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 1 — R2-1. The CRN's money, measured as a DELTA rather than an end state.
    // ════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The brief's shape: sale -> price correction -> CRN. AR must drop by EXACTLY the live sell,
     * revenue reversed by exactly the live sell, and the cost pair relieved exactly once.
     * A pure disposition-free refund isolates the CRN from the recharge/disposition legs.
     */
    public function test_probe1_crn_delta_equals_the_live_sale_exactly(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        $posting = app(PostingService::class);
        $saleKey = 'invoice-detail:'.$detail->id.':sale';

        $original = $posting->post($this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 90.0, 60.0, $saleKey))->transaction;

        $posting->repost(
            Transaction::withoutGlobalScopes()->findOrFail($original->id),
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 155.0, 60.0, $saleKey),
            now()->subDays(3),
            null
        );

        $arBefore = $this->netForPurpose($company->id, 'RECEIVABLE_CONTROL');
        $revBefore = $this->netForPurpose($company->id, 'SERVICE_REVENUE', 'flight');
        $cogsBefore = $this->netForPurpose($company->id, 'SERVICE_COST', 'flight');
        $payBefore = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');

        // A refund that credits exactly the live sell, no fee, no supplier charge, Credit method.
        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client, [
            'original_invoice_price' => 155.000,
            'total_refund_to_client' => 155.000,
        ]);

        app(RefundPostingService::class)->post($refund, null);

        $arAfter = $this->netForPurpose($company->id, 'RECEIVABLE_CONTROL');
        $revAfter = $this->netForPurpose($company->id, 'SERVICE_REVENUE', 'flight');
        $cogsAfter = $this->netForPurpose($company->id, 'SERVICE_COST', 'flight');
        $payAfter = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');

        fwrite(STDERR, "\n[PROBE1] AR {$arBefore} -> {$arAfter} | REV {$revBefore} -> {$revAfter} | COGS {$cogsBefore} -> {$cogsAfter} | PAY {$payBefore} -> {$payAfter}\n");

        $this->assertSame(155.0, round($revAfter - $revBefore, 3), 'revenue must be reversed by exactly the LIVE sell');
        $this->assertSame(0.0, $revAfter, 'revenue must end at zero on the detail');
        $this->assertSame(0.0, $cogsAfter, 'COGS must end at zero');
        $this->assertSame(0.0, $payAfter, 'the supplier payable must end at zero');
    }

    /**
     * OVER-CREDIT. The live sale is 100; the refund detail asks to credit the client 500.
     * R2-1 logs `partial_credit_requested` and does not enforce. The question this asks is what
     * reaches the LEDGER: if the client is credited 500 against 100 of reversed revenue, the
     * agency has given away 400 with a balanced trial balance.
     */
    public function test_probe2_a_refund_that_credits_more_than_the_sale(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        app(PostingService::class)->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, 'invoice-detail:'.$detail->id.':sale')
        );

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client, [
            'original_invoice_price' => 500.000,
            'total_refund_to_client' => 500.000,
        ]);

        app(RefundPostingService::class)->post($refund, null);

        $ar = $this->netForPurpose($company->id, 'RECEIVABLE_CONTROL');
        $rev = $this->netForPurpose($company->id, 'SERVICE_REVENUE', 'flight');
        $advance = $this->netForPurpose($company->id, 'CLIENT_ADVANCE');

        fwrite(STDERR, "\n[PROBE2 over-credit] AR={$ar} REV={$rev} CLIENT_ADVANCE={$advance}\n");

        // MEASURED: the disposition is a pure reclass, so the CLIENT'S NET position (AR + advance)
        // is still 0 — no P&L loss. What IS wrong is that BOTH control leaves are inflated by the
        // over-credit (400 here), which is what AR ageing, the client statement and any process
        // that reads one leaf without the other will report.
        $this->assertSame(0.0, round($ar + $advance, 3), "the client NET position must be zero");
        $this->assertSame(500.0, $ar, 'AR control inflated to the credited figure, not the sale');
        $this->assertSame(-500.0, $advance, 'CLIENT_ADVANCE credited the whole requested figure');
    }

    /**
     * TWO refunds each crediting half. The first is a FULL reversal of the live sale, so the
     * second must refuse; the brief's "two CRNs summing to the sale" is not expressible.
     */
    public function test_probe3_two_half_refunds(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        app(PostingService::class)->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, 'invoice-detail:'.$detail->id.':sale')
        );

        $first = $this->makeRefund($company, $agent, $invoice, $task, $client, [
            'original_invoice_price' => 50.000, 'total_refund_to_client' => 50.000,
        ], 'half1');

        app(RefundPostingService::class)->post($first, null);

        $rev = $this->netForPurpose($company->id, 'SERVICE_REVENUE', 'flight');
        $advance = $this->netForPurpose($company->id, 'CLIENT_ADVANCE');
        fwrite(STDERR, "\n[PROBE3 after first half refund] REV={$rev} CLIENT_ADVANCE={$advance}\n");

        $second = $this->makeRefund($company, $agent, $invoice, $task, $client, [
            'original_invoice_price' => 50.000, 'total_refund_to_client' => 50.000,
        ], 'half2');

        $refused = false;
        try {
            app(RefundPostingService::class)->post($second, null);
        } catch (\App\Exceptions\Accounting\NothingOutstandingToCreditException $e) {
            $refused = true;
        }

        fwrite(STDERR, "[PROBE3] second half refund refused=".($refused ? 'yes' : 'NO')."\n");
        $this->assertTrue($refused, 'the second half refund must refuse — the first already reversed the whole sale');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 4 — R2-2. THREE edits, 100 / 80 / 120, through the HTTP path.
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe4_three_receipt_edits_100_80_120(): void
    {
        [$company, $agent, $client, , , , , $branch, $admin] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');
        $clientsLeaf = $this->accountByCode($company->id, '1351');

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 120.000,
            'status' => 'unpaid',
            'invoice_date' => now(),
        ]);

        $receipt = InvoiceReceipt::create([
            'type' => 'invoice',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]],
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'bank_account_id' => $bank->id,
            'status' => InvoiceReceipt::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $payload = fn (float $amount): array => [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'invoice',
            'client_id' => $client->id,
            'amount' => $amount,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
            'bank_account_id' => $bank->id,
        ];

        $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), $payload(80.0))->assertRedirect();
        $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), $payload(120.0))->assertRedirect();
        $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), $payload(120.0))->assertRedirect();

        $receipt->refresh();
        $invoice->refresh();

        $keys = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'like', 'rv:'.$receipt->id.'%')
            ->orderBy('id')
            ->pluck('idempotency_key')
            ->all();

        fwrite(STDERR, "\n[PROBE4] keys=".implode(',', $keys)." bank=".$this->netDebit($bank->id)." clients=".$this->netDebit($clientsLeaf->id)." invoice.status={$invoice->status}\n");

        $this->assertSame(120.0, $this->netDebit($bank->id), 'the bank must carry the FINAL amount after three edits');
        $this->assertSame(-120.0, $this->netDebit($clientsLeaf->id));

        // rev1..rev3 distinct, base occupied by the dead original.
        $this->assertSame(4, count($keys));
        $this->assertSame(count($keys), count(array_unique($keys)));
        $this->assertContains('rv:'.$receipt->id.':rev3', $keys);

        // No ORPHAN reversal: every reversal names a live target and every reversed document has
        // exactly one reversal.
        $family = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'like', 'rv:'.$receipt->id.'%')
            ->get();

        foreach ($family as $doc) {
            $reversals = Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('reversal_of_transaction_id', $doc->id)
                ->count();

            if ($doc->posting_status === 'reversed') {
                $this->assertSame(1, $reversals, "reversed document #{$doc->id} must have exactly one reversal");
            } else {
                $this->assertSame(0, $reversals, "live document #{$doc->id} must have no reversal");
            }
        }

        $live = Transaction::withoutGlobalScopes()->findOrFail($receipt->transaction_id);
        $this->assertSame('posted', (string) $live->posting_status);
        $this->assertSame(120.0, round((float) $live->total_debit, 3));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 5 — second-order: VOID -> UN-VOID -> REFUND on an accrued, invoiced task.
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe5_void_unvoid_then_refund(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture([], 'issued', 60.0);

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $unbilled = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $payable = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        fwrite(STDERR, "\n[PROBE5 after accrual] 1430={$unbilled} payable={$payable}\n");

        // VOID: the service's own reversal of the accrual.
        $accrual->reverseForTask($task);
        $afterVoid1430 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $afterVoidPay = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');

        // UN-VOID: the restore primitive TaskController::revertFinancialsForVoid() calls.
        $accrual->restoreForTask($task);
        $afterRestore1430 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $afterRestorePay = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');

        fwrite(STDERR, "[PROBE5 void] 1430={$afterVoid1430} pay={$afterVoidPay} | [restore] 1430={$afterRestore1430} pay={$afterRestorePay}\n");

        $this->assertSame(0.0, $afterVoid1430, 'void must relieve the accrual');
        $this->assertSame($unbilled, $afterRestore1430, 'un-void must put the accrual back at exactly its pre-void value');
        $this->assertSame($payable, $afterRestorePay, 'un-void must put the payable back at exactly its pre-void value');

        // Now REFUND the un-voided booking. It is invoiced, so the CRN path runs and the accrual
        // must be settled exactly once.
        app(PostingService::class)->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, 'invoice-detail:'.$detail->id.':sale')
        );

        $task->status = 'refunded';
        $task->save();

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);
        app(RefundPostingService::class)->post($refund, null);

        $end1430 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');
        $endPay = $this->netForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        $endCogs = $this->netForPurpose($company->id, 'SERVICE_COST', 'flight');
        fwrite(STDERR, "[PROBE5 after refund] 1430={$end1430} pay={$endPay} cogs={$endCogs}\n");

        $this->assertGreaterThanOrEqual(-0.0005, $end1430, '1430 must never go negative — that is D1\'s signature');
        $this->assertGreaterThanOrEqual(-0.0005, -$endPay, 'the payable must never go DEBIT — a supplier we owe cannot owe us');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 6 — second-order: a reissue AFTER the accrual. Does the accrual double?
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe6_reissue_after_accrual_does_not_double_the_accrual(): void
    {
        [$company, , , $supplier, $task] = $this->makeFixture([], 'issued', 60.0);

        $accrual = app(TaskIssuancePayableService::class);
        $accrual->postIfDue($task);

        $after1 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');

        // A reissue keeps the same task row on this codebase's reissue path, so a second dispatch
        // on the same task is the shape to probe.
        $task->status = 'reissued';
        $task->save();
        $accrual->postIfDue($task->fresh());

        $after2 = $this->netForPurpose($company->id, 'UNBILLED_SUPPLIER_COST');

        fwrite(STDERR, "\n[PROBE6] 1430 after first accrual={$after1} after reissue dispatch={$after2}\n");

        $this->assertSame($after1, $after2, 'a second dispatch after a reissue must not accrue the cost twice');
    }
}
