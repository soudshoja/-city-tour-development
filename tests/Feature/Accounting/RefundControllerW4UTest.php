<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w4u-refund-screen. W4.U §b — the refund document screen (multi-invoice batch grouping,
 * method-drives-disposition, the full posted document set, agent-scoped visibility). Complements
 * RefundControllerW4RTest.php (policy/route plumbing) and RefundPostingServiceTest.php (the (a)-(f)
 * posting composition itself) — this file exercises the SAME engine end to end through the HTTP
 * screen actions the brief's verify criteria name explicitly.
 */
class RefundControllerW4UTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeCompanyWithAdmin(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);

        return [$company, $branch, $admin];
    }

    private function makeAgent(Branch $branch): Agent
    {
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);

        // role_id => Role::AGENT so RefundPolicy::view()'s own-agent-scope branch actually
        // triggers for this user (the plain User::factory() default role is not AGENT).
        return Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'type_id' => $agentType->id,
        ]);
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);
    }

    private function grantCreateRefundPermission(Company $company, User $admin): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create refund', 'guard_name' => 'web']);
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'refund-creator', 'company_id' => $company->id],
            ['guard_name' => 'web']
        );
        $admin->assignRole($role);
        $role->givePermissionTo(['create refund']);
    }

    /** RefundPolicy::viewAny() has no role bypass — even ADMIN needs the real permission. */
    private function grantViewRefundPermission(Company $company, User $user): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view refund', 'guard_name' => 'web']);
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'refund-viewer', 'company_id' => $company->id],
            ['guard_name' => 'web']
        );
        $user->assignRole($role);
        $role->givePermissionTo(['view refund']);
    }

    private function taskPayload(Task $task, float $sell = 100, float $cost = 60, float $fee = 0, float $charge = 0): array
    {
        return [
            'task_id' => $task->id,
            'original_invoice_price' => $sell,
            'original_task_cost' => $cost,
            'original_task_profit' => $sell - $cost,
            'refund_fee_to_client' => $fee,
            'supplier_charge' => $charge,
            'new_task_profit' => $fee - $charge,
            'total_refund_to_client' => $sell - $charge - $fee,
        ];
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Multi-invoice picker: groups by refund_batch_id
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_batches_a_multi_invoice_selection_by_refund_batch_id(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $taskA = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoiceA = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceA->id, 'task_id' => $taskA->id]);

        $taskB = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'hotel', 'status' => 'issued']);
        $invoiceB = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceB->id, 'task_id' => $taskB->id]);

        $this->enableEngine($company);
        $this->grantCreateRefundPermission($company, $admin);

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [
                $this->taskPayload($taskA),
                $this->taskPayload($taskB),
            ],
        ])->assertRedirect(route('refunds.index'));

        $refunds = Refund::where('company_id', $company->id)->orderBy('id')->get();
        $this->assertCount(2, $refunds, 'A multi-invoice selection must create ONE refund document per carrying invoice.');
        $this->assertSame([$invoiceA->id, $invoiceB->id], $refunds->pluck('invoice_id')->sort()->values()->toArray());
        $this->assertNotNull($refunds->first()->refund_batch_id, 'Batched refunds must share a refund_batch_id.');
        $this->assertSame(
            $refunds->first()->refund_batch_id,
            $refunds->last()->refund_batch_id,
            'Every refund in the batch must carry the SAME refund_batch_id.'
        );
        $this->assertTrue($refunds->every(fn ($r) => $r->status === Refund::STATUS_DRAFT), 'Batched refunds must land in draft, same as a single-invoice refund.');
        $this->assertSame(1, RefundDetail::where('refund_id', $refunds->first()->id)->count());
        $this->assertSame(1, RefundDetail::where('refund_id', $refunds->last()->id)->count());
    }

    public function test_create_picker_allows_a_multi_invoice_selection_when_engine_on_and_every_invoice_paid(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $taskA = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoiceA = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceA->id, 'task_id' => $taskA->id]);

        $taskB = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'hotel', 'status' => 'issued']);
        $invoiceB = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceB->id, 'task_id' => $taskB->id]);

        $this->enableEngine($company);

        $response = $this->actingAs($admin)->get(route('refunds.create', ['task_ids' => $taskA->id.','.$taskB->id]));

        $response->assertOk();
        $response->assertViewIs('refunds.create-multi');
        $response->assertViewHas('invoiceGroups', fn ($groups) => $groups->count() === 2);
    }

    public function test_create_picker_still_blocks_multi_invoice_selection_when_engine_off(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $taskA = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoiceA = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceA->id, 'task_id' => $taskA->id]);

        $taskB = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'hotel', 'status' => 'issued']);
        $invoiceB = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceB->id, 'task_id' => $taskB->id]);

        // Engine OFF (default) -- byte parity with the pre-existing legacy restriction.
        $response = $this->actingAs($admin)->get(route('refunds.create', ['task_ids' => $taskA->id.','.$taskB->id]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Method selector drives disposition
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function mapRefundPayoutLeaf(Company $company): Account
    {
        $bank = Account::factory()->create(['company_id' => $company->id]);
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'REFUND_PAYOUT_CASH_BANK',
            'service_type' => null,
            'account_id' => $bank->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $bank;
    }

    public function test_method_cash_drives_a_pv_payout_never_a_2632_credit(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $this->enableEngine($company);
        $bank = $this->mapRefundPayoutLeaf($company);

        $refund = Refund::create([
            'refund_number' => 'REF-METHOD-CASH-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Cash',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 100,
        ]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $this->actingAs($admin)->post(route('refunds.complete_process', $refund->id))->assertRedirect();

        $disposition = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'refund:'.$refund->id.':disposition')
            ->first();
        $this->assertNotNull($disposition);
        $this->assertSame('PV', $disposition->doc_type, 'method=Cash must drive a PV payout.');
        $this->assertSame('refund_out', $refund->fresh()->disposition);
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $disposition->id)->where('account_id', $bank->id)->count());
        $this->assertSame(0, Credit::where('refund_id', $refund->id)->count(), 'Cash payout must never dual-write a 2632 credit.');
    }

    public function test_method_credit_drives_a_2632_credit_dual_write(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $this->enableEngine($company);

        $refund = Refund::create([
            'refund_number' => 'REF-METHOD-CREDIT-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 100,
        ]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $this->actingAs($admin)->post(route('refunds.complete_process', $refund->id))->assertRedirect();

        $disposition = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'refund:'.$refund->id.':disposition')
            ->first();
        $this->assertNotNull($disposition);
        $this->assertSame('JV', $disposition->doc_type, 'method=Credit must drive a JV against 2632.');
        $this->assertSame('credit', $refund->fresh()->disposition);
        $this->assertSame(1, Credit::where('refund_id', $refund->id)->count(), 'Credit disposition must dual-write the app-ledger Credit row.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Posting from the screen yields exactly the brief's document set
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_posting_from_the_screen_yields_the_full_brief_document_set(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
        ]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);
        $invoiceDetail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $this->enableEngine($company);

        // A real, engine-posted sale + agent commission -- same shape InvoiceController's own
        // feeders post -- so the CRN (reverse()) and commission un-earn legs have something real
        // to target structurally, never by description.
        $saleLines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $task->type,
            sellAmount: 100.000,
            costAmount: 60.000,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $client->id,
            clientName: $client->full_name,
            supplierId: $supplier->id,
            supplierName: $supplier->name,
            agentId: $agent->id,
            agentName: $agent->name,
            invoiceId: $invoice->id,
            invoiceDetailId: $invoiceDetail->id,
            taskId: $task->id,
        ));
        app(PostingService::class)->post(new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: now(),
            narration: 'Sale',
            lines: $saleLines,
            idempotencyKey: 'invoice-detail:'.$invoiceDetail->id.':sale',
            invoiceId: $invoice->id,
        ));
        app(PostingService::class)->post(new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'JV',
            subType: 'AGENT_COMMISSION',
            docDate: now(),
            narration: 'Agent commission',
            lines: [
                new LineDraft(purposeCode: 'SALARY_EXPENSE', accountId: null, side: 'debit', amount: 20.0, currency: config('accounting.engine.base_currency'), originalAmount: 20.0, exchangeRate: 1.0, transactionType: 'AGENT_COMMISSION_EXPENSE', partyAccountRef: $agent->id, invoiceId: $invoice->id, invoiceDetailId: $invoiceDetail->id, taskId: $task->id),
                new LineDraft(purposeCode: 'SALARY_PAYABLE', accountId: null, side: 'credit', amount: 20.0, currency: config('accounting.engine.base_currency'), originalAmount: 20.0, exchangeRate: 1.0, transactionType: 'AGENT_COMMISSION_PAYABLE', partyAccountRef: $agent->id, invoiceId: $invoice->id, invoiceDetailId: $invoiceDetail->id, taskId: $task->id),
            ],
            idempotencyKey: 'invoice-detail:'.$invoiceDetail->id.':agent-commission',
            invoiceId: $invoice->id,
        ));

        $refund = Refund::create([
            'refund_number' => 'REF-FULLSET-'.uniqid(),
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
        // penalty (supplier_charge) = 20, our fee = 5 -- both recharge legs fire; supplier net
        // defaults to cost(60) - penalty(20) = 40.
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 5.000,
            'supplier_charge' => 20.000,
            'total_refund_to_client' => 75.000,
        ]);

        // The screen's own "post" action.
        $this->actingAs($admin)->post(route('refunds.complete_process', $refund->id))->assertRedirect();

        $companyId = $company->id;
        $byKey = fn (string $key) => Transaction::withoutGlobalScopes()->where('company_id', $companyId)->where('idempotency_key', $key)->first();

        // (a) CRN -- reverse() of the sale.
        $saleTransaction = $byKey('invoice-detail:'.$invoiceDetail->id.':sale');
        $this->assertNotNull($saleTransaction);
        $this->assertSame('reversed', $saleTransaction->fresh()->posting_status, '(a) CRN: the sale must be reversed.');

        // (b) Recharge -- one document, both legs (penalty pass-through + fee income).
        $recharge = $byKey('refund:'.$refund->id.':recharge');
        $this->assertNotNull($recharge, '(b) Recharge document must post.');
        $this->assertEqualsWithDelta(25.0, (float) $recharge->total_debit, 0.0005, 'Recharge Dr AR must be penalty(20) + fee(5).');

        // (c) Supplier credit item, bsptype=REFUND.
        $supplierCredit = $byKey('refund:'.$refund->id.':supplier-credit:'.RefundDetail::where('refund_id', $refund->id)->value('id'));
        $this->assertNotNull($supplierCredit, '(c) Supplier credit item must post.');
        $this->assertSame('REFUND', $supplierCredit->bsptype);

        // (d) Commission un-earn -- reverse() of the original commission JV.
        $commissionTransaction = $byKey('invoice-detail:'.$invoiceDetail->id.':agent-commission');
        $this->assertNotNull($commissionTransaction);
        $this->assertSame('reversed', $commissionTransaction->fresh()->posting_status, '(d) Commission must be un-earned (reversed).');

        // (f) Disposition -- Credit method -> JV against 2632, dual-written to the app Credit ledger.
        $disposition = $byKey('refund:'.$refund->id.':disposition');
        $this->assertNotNull($disposition, '(f) Disposition must post.');
        $this->assertSame(1, Credit::where('refund_id', $refund->id)->count());

        $refund->refresh();
        $this->assertContains($refund->status, [Refund::STATUS_COMPLETED, Refund::STATUS_POSTED]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Agent-scoped visibility
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_agent_viewing_another_agents_refund_gets_403(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        $ownerAgent = $this->makeAgent($branch);
        $otherAgent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $ownerAgent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $ownerAgent->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $ownerAgent->id, 'invoice_date' => now()]);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $refund = Refund::create([
            'refund_number' => 'REF-AGENTSCOPE-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $ownerAgent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 100,
        ]);

        $otherAgentUser = $otherAgent->user;
        $this->assertNotNull($otherAgentUser);

        $this->actingAs($otherAgentUser)
            ->get(route('refunds.show', ['companyId' => $company->id, 'refundNumber' => $refund->refund_number]))
            ->assertForbidden();
    }

    public function test_agent_viewing_their_own_refund_succeeds(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $refund = Refund::create([
            'refund_number' => 'REF-AGENTOWN-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 100,
        ]);

        $agentUser = $agent->user;
        $this->assertNotNull($agentUser);

        $this->actingAs($agentUser)
            ->get(route('refunds.show', ['companyId' => $company->id, 'refundNumber' => $refund->refund_number]))
            ->assertOk();
    }

    public function test_index_agent_filter_excludes_other_agents_refunds(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentA = $this->makeAgent($branch);
        $agentB = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agentA->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agentA->id, 'client_id' => $client->id, 'type' => 'flight', 'status' => 'issued']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agentA->id, 'invoice_date' => now()]);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $refundA = Refund::create([
            'refund_number' => 'REF-IDX-A-'.uniqid(), 'company_id' => $company->id, 'branch_id' => $branch->id,
            'agent_id' => $agentA->id, 'invoice_id' => $invoice->id, 'method' => 'Credit', 'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(), 'total_refund_amount' => 0, 'total_refund_charge' => 0, 'total_nett_refund' => 100,
        ]);
        $refundB = Refund::create([
            'refund_number' => 'REF-IDX-B-'.uniqid(), 'company_id' => $company->id, 'branch_id' => $branch->id,
            'agent_id' => $agentB->id, 'invoice_id' => $invoice->id, 'method' => 'Credit', 'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(), 'total_refund_amount' => 0, 'total_refund_charge' => 0, 'total_nett_refund' => 100,
        ]);

        $this->grantViewRefundPermission($company, $admin);

        $response = $this->actingAs($admin)->get(route('refunds.index', ['agent_id' => $agentA->id]));

        $response->assertOk();
        $ids = $response->viewData('refunds')->pluck('id')->all();
        $this->assertContains($refundA->id, $ids);
        $this->assertNotContains($refundB->id, $ids);
    }
}
