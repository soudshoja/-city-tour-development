<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\InvoiceController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentCharge;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\AccountingTestCase;

/**
 * KEY: invoice-w4a. W4.A (.planning/accounting-waves/w4/w4-brief.md item 2; target-spec.md §B;
 * Accounting Gap/22-plan-amendments.md rev 4 §2.2 W4.A row):
 * InvoiceController::createSupplierLossEntries()/createFeeLossEntries() no longer post the
 * "Dr 5221 Company Loss on Sales / Cr {supplier cost}" or "Dr 5221 / Cr 5123 Fee Loss Provision"
 * offsets on the engine-ON path for the company-borne share of a negative-margin loss — the loss
 * already sits in COGS via the sale document itself (W3d's SaleDraftBuilder), so re-booking it
 * here would double-count it and erase the real cost balance.
 *
 * The agent-borne share, which used to credit the now-frozen `4170 Loss Recovery Income` leaf on
 * BOTH paths, is routed on the ON path to a new named extension point
 * (InvoiceController::postAgentLossRecoveryHook()) that is a pure no-op until P5.13 flips
 * `config('accounting.engine.agent_loss_recovery_enabled')` and supplies the real Cr 5126
 * implementation.
 *
 * The OFF path (either flag off) is untouched in every scenario below — same JournalEntry rows,
 * same accounts, same amounts as pre-W4.A HEAD.
 */
class InvoiceControllerW4ALossPostingTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config([
            'accounting.engine.enabled' => false,
            'accounting.engine.agent_loss_recovery_enabled' => false,
        ]);
        parent::tearDown();
    }

    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice, 6: InvoiceDetail, 7: Transaction}
     */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
        ]);

        // Agent's own loss-receivable leaf -- a plain leaf account is enough here; this lane
        // doesn't need AgentController::update()'s real placement logic, only a resolvable
        // loss_account_id (createSupplierLossEntries()/createFeeLossEntries() both gate the
        // agent branch on `$agent->loss_account_id` being non-null).
        $agentLossAccount = Account::factory()->create(['company_id' => $company->id]);
        $agent->update(['loss_account_id' => $agentLossAccount->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now(),
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
        ]);

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);

        return [$company, $agent, $client, $supplier, $task, $invoice, $invoiceDetail, $transaction];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    /**
     * Builds the "supplier cost" leaf createSupplierLossEntries()'s company branch resolves via
     * `Account::where('name', $task->supplier->name)->where('root_id', $expenses->id)` — the SAME
     * resolution the SUT performs, not an assumption about seeding order.
     */
    private function makeSupplierCostAccount(Company $company, Supplier $supplier): Account
    {
        $expenses = Account::where('company_id', $company->id)->where('name', 'like', '%Expenses%')->firstOrFail();

        return Account::factory()->create([
            'company_id' => $company->id,
            'name' => $supplier->name,
            'root_id' => $expenses->id,
            'account_type' => 'expense',
        ]);
    }

    private function journalLinesFor(int $invoiceDetailId)
    {
        return DB::table('journal_entries')->where('invoice_detail_id', $invoiceDetailId)->get();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (a) ON path -- company-borne posts NOTHING extra.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_company_borne_supplier_loss_posts_nothing(): void
    {
        [$company, $agent, , $supplier, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->makeSupplierCostAccount($company, $supplier);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // Default AgentLoss::getForAgent() fallback = company bears 100% (no agent_loss row, no
        // invoice-level override) -- see Invoice::getEffectiveLossSettings().
        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createSupplierLossEntries', [
            $transaction->id, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 40.000,
        ]);

        $this->assertCount(0, $this->journalLinesFor($invoiceDetail->id), 'Company-borne negative margin must post NOTHING extra on the ON path -- the loss already sits in COGS.');

        $companyLossAccount = Account::where('company_id', $company->id)->where('name', 'Company Loss on Sales')->firstOrFail();
        $this->assertSame(0, DB::table('journal_entries')->where('account_id', $companyLossAccount->id)->count(), 'No 5221 line anywhere for this company.');
    }

    public function test_on_path_company_borne_fee_loss_posts_nothing(): void
    {
        [$company, $agent, , , $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $chargeSettings = new AgentCharge(['charge_bearer' => AgentCharge::BEARER_COMPANY]);

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createFeeLossEntries', [
            $transaction->id, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 20.000, $chargeSettings,
        ]);

        $this->assertCount(0, $this->journalLinesFor($invoiceDetail->id), 'Company-borne fee loss must post NOTHING extra on the ON path.');

        $companyLossAccount = Account::where('company_id', $company->id)->where('name', 'Company Loss on Sales')->firstOrFail();
        $this->assertSame(0, DB::table('journal_entries')->where('account_id', $companyLossAccount->id)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) ON path -- agent share routed to the named P5.13 hook, no-op by default.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_agent_borne_supplier_loss_hook_is_noop_by_default(): void
    {
        [$company, $agent, , $supplier, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->makeSupplierCostAccount($company, $supplier);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $invoice->update(['agent_loss' => 100, 'company_loss' => 0]); // 100% agent-borne override

        $this->assertFalse((bool) config('accounting.engine.agent_loss_recovery_enabled'), 'Fixture sanity: the hook must default OFF.');

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createSupplierLossEntries', [
            $transaction->id, $invoice->fresh(), $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 40.000,
        ]);

        $this->assertCount(0, $this->journalLinesFor($invoiceDetail->id), 'Agent-share hook must be a true no-op by default -- no JournalEntry at all, not even to the frozen 4170 leaf.');

        $lossRecoveryIncome = Account::where('company_id', $company->id)->where('name', 'Loss Recovery Income')->firstOrFail();
        $this->assertSame(0, DB::table('journal_entries')->where('account_id', $lossRecoveryIncome->id)->count(), 'The frozen 4170 leaf must never be credited on the ON path from W4.A on.');
    }

    public function test_on_path_agent_borne_fee_loss_hook_is_noop_by_default(): void
    {
        [$company, $agent, , , $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $chargeSettings = new AgentCharge(['charge_bearer' => AgentCharge::BEARER_AGENT]);

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createFeeLossEntries', [
            $transaction->id, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 20.000, $chargeSettings,
        ]);

        $this->assertCount(0, $this->journalLinesFor($invoiceDetail->id), 'Agent-share fee-loss hook must also be a no-op by default.');

        $lossRecoveryIncome = Account::where('company_id', $company->id)->where('name', 'Loss Recovery Income')->firstOrFail();
        $this->assertSame(0, DB::table('journal_entries')->where('account_id', $lossRecoveryIncome->id)->count());
    }

    public function test_agent_loss_recovery_hook_noop_when_disabled_and_throws_when_flipped_on_without_implementation(): void
    {
        [$company, $agent, , , $task, $invoice, $invoiceDetail] = $this->makeFixture();

        $controller = app(InvoiceController::class);

        // Disabled (the shipped default): calling the hook directly does nothing and never throws.
        $this->callPrivate($controller, 'postAgentLossRecoveryHook', [
            $company->id, $agent, $invoice, $invoice->id, $invoiceDetail->id, $task, 10.000, 'supplier-loss',
        ]);
        $this->assertSame(0, DB::table('journal_entries')->count(), 'No JournalEntry of any kind may be written by the disabled hook.');

        // Flag ON without P5.13's real implementation: must throw loudly rather than silently
        // posting nothing (indistinguishable from "working as designed") or guessing at a shape
        // this wave was never asked to design.
        config(['accounting.engine.agent_loss_recovery_enabled' => true]);
        $this->expectException(\RuntimeException::class);
        $this->callPrivate($controller, 'postAgentLossRecoveryHook', [
            $company->id, $agent, $invoice, $invoice->id, $invoiceDetail->id, $task, 10.000, 'supplier-loss',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (c) OFF path -- byte-identical to pre-W4.A HEAD in every scenario above.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_company_borne_supplier_loss_matches_legacy_exactly(): void
    {
        [$company, $agent, , $supplier, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $costAccount = $this->makeSupplierCostAccount($company, $supplier);
        config(['accounting.engine.enabled' => false]);

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createSupplierLossEntries', [
            $transaction->id, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 40.000,
        ]);

        $lines = $this->journalLinesFor($invoiceDetail->id);
        $this->assertCount(2, $lines, 'OFF path must still post the legacy Dr 5221 / Cr {supplier cost} pair -- byte-parity with pre-W4.A HEAD.');

        $companyLossAccount = Account::where('company_id', $company->id)->where('name', 'Company Loss on Sales')->firstOrFail();
        $debitLine = $lines->firstWhere('account_id', $companyLossAccount->id);
        $creditLine = $lines->firstWhere('account_id', $costAccount->id);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(40.000, (float) $debitLine->debit);
        $this->assertEquals(0.0, (float) $debitLine->credit);
        $this->assertEquals(40.000, (float) $creditLine->credit);
        $this->assertEquals(0.0, (float) $creditLine->debit);
    }

    public function test_off_path_agent_borne_supplier_loss_matches_legacy_exactly(): void
    {
        [$company, $agent, , $supplier, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->makeSupplierCostAccount($company, $supplier);
        config(['accounting.engine.enabled' => false]);

        $invoice->update(['agent_loss' => 100, 'company_loss' => 0]);

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createSupplierLossEntries', [
            $transaction->id, $invoice->fresh(), $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 40.000,
        ]);

        $lines = $this->journalLinesFor($invoiceDetail->id);
        $this->assertCount(2, $lines, 'OFF path must still credit the legacy 4170 Loss Recovery Income leaf for the agent share -- byte-parity with pre-W4.A HEAD.');

        $lossRecoveryIncome = Account::where('company_id', $company->id)->where('name', 'Loss Recovery Income')->firstOrFail();
        $debitLine = $lines->firstWhere('account_id', $agent->loss_account_id);
        $creditLine = $lines->firstWhere('account_id', $lossRecoveryIncome->id);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(40.000, (float) $debitLine->debit);
        $this->assertEquals(40.000, (float) $creditLine->credit);
    }

    public function test_off_path_company_borne_fee_loss_matches_legacy_exactly(): void
    {
        [$company, $agent, , , $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        config(['accounting.engine.enabled' => false]);

        $chargeSettings = new AgentCharge(['charge_bearer' => AgentCharge::BEARER_COMPANY]);

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createFeeLossEntries', [
            $transaction->id, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 20.000, $chargeSettings,
        ]);

        $lines = $this->journalLinesFor($invoiceDetail->id);
        $this->assertCount(2, $lines, 'OFF path must still post the legacy Dr 5221 / Cr 5123 pair for company-borne fee loss.');

        $companyLossAccount = Account::where('company_id', $company->id)->where('name', 'Company Loss on Sales')->firstOrFail();
        $feeLossProvisionAccount = Account::where('company_id', $company->id)->where('name', 'Fee Loss Provision')->firstOrFail();

        $debitLine = $lines->firstWhere('account_id', $companyLossAccount->id);
        $creditLine = $lines->firstWhere('account_id', $feeLossProvisionAccount->id);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(20.000, (float) $debitLine->debit);
        $this->assertEquals(20.000, (float) $creditLine->credit);
    }

    public function test_off_path_agent_borne_fee_loss_matches_legacy_exactly(): void
    {
        [$company, $agent, , , $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        config(['accounting.engine.enabled' => false]);

        $chargeSettings = new AgentCharge(['charge_bearer' => AgentCharge::BEARER_AGENT]);

        $controller = app(InvoiceController::class);
        $this->callPrivate($controller, 'createFeeLossEntries', [
            $transaction->id, $invoice, $invoice->id, $invoiceDetail->id, $task, $agent, $company->id, 20.000, $chargeSettings,
        ]);

        $lines = $this->journalLinesFor($invoiceDetail->id);
        $this->assertCount(2, $lines, 'OFF path must still credit the legacy 4170 leaf for agent-borne fee loss.');

        $lossRecoveryIncome = Account::where('company_id', $company->id)->where('name', 'Loss Recovery Income')->firstOrFail();
        $creditLine = $lines->firstWhere('account_id', $lossRecoveryIncome->id);
        $debitLine = $lines->firstWhere('account_id', $agent->loss_account_id);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(20.000, (float) $debitLine->debit);
        $this->assertEquals(20.000, (float) $creditLine->credit);
    }
}
