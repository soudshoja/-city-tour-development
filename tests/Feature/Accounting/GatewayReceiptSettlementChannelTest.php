<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\ChargeType;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\PaymentController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T7 (Lane D — PLAN.md §5): "Stamp settlementChannel on the clearing/fee legs
 * of the three existing receipt feeders (PaymentController, ClientController::addCredit,
 * CheckMyFatoorahPayments) — ON path only, OFF closures untouched (byte parity)."
 *
 * This file covers PaymentController::createInvoicePaymentCOA() and ClientController::addCredit()
 * directly (both have a reasonably-sized, already-established fixture pattern to reuse — see
 * PaymentControllerCoaSeamTest / ClientControllerAddCreditW7XTest, mirrored here). OFF-path byte
 * parity for all three feeders — including CheckMyFatoorahPayments, whose own fixture is a
 * 1200+ line HTTP-mocked webhook harness — is proven by re-running the PRE-EXISTING regression
 * suites unmodified (PaymentControllerCoaSeamTest, ClientControllerAddCreditW7XTest,
 * CheckMyFatoorahPaymentsSeamTest, CheckMyFatoorahPaymentsLedgerBalanceTest): adding a new,
 * defaulted-null trailing LineDraft field cannot change what those suites already assert, and a
 * regression there would fail them exactly as it would fail a bespoke channel-specific test. The
 * review packet's Deviations section records this scoping choice explicitly. CheckMyFatoorahPayments'
 * own channel-derivation call site was verified by direct code read (this file's sibling packet).
 */
class GatewayReceiptSettlementChannelTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function invokePrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /** @return array{company: Company, branch: Branch, agent: Agent, client: Client} */
    private function makeOnPathTenant(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return compact('company', 'branch', 'agent', 'client');
    }

    private function resolvedAccountId(Company $company, string $purposeCode): ?int
    {
        return DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', $purposeCode)->value('account_id');
    }

    // ── PaymentController::createInvoicePaymentCOA() ───────────────────────────────────────

    public function test_payment_controller_on_path_stamps_channel_on_clearing_and_fee_not_receivable(): void
    {
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];

        $invoice = Invoice::factory()->create(['client_id' => $tenant['client']->id, 'agent_id' => $tenant['agent']->id, 'amount' => 100.00, 'sub_amount' => 100.00]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $tenant['agent']->id]);
        InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id, 'task_price' => 100.00, 'supplier_price' => 100.00, 'markup_price' => 0,
        ]);

        Charge::create([
            'name' => 'Tap', 'type' => ChargeType::PAYMENT_GATEWAY->value, 'amount' => 5.00,
            'charge_type' => 'Flat Rate', 'self_charge' => 5.00, 'extra_charge' => 0,
            'paid_by' => 'Company', 'company_id' => $company->id,
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'company_id' => $company->id, 'type' => 'tap', 'code' => 'knet',
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id, 'client_id' => $tenant['client']->id,
            'invoice_id' => $invoice->id, 'account_id' => null, 'created_by' => $tenant['agent']->user_id,
            'payment_method_id' => $paymentMethod->id, 'amount' => 100.00, 'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [$payment, 100.00, 'Tap', null, 'REF-CHANNEL-1']);
        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $entries = DB::table('journal_entries')->where('transaction_id', $result['transaction_id'])->get();

        $receivableLeaf = $this->resolvedAccountId($company, 'RECEIVABLE_CONTROL');
        $clearingLeaf = $this->resolvedAccountId($company, 'GATEWAY_CLEARING_TAP');
        $feeLeaf = $this->resolvedAccountId($company, 'GATEWAY_FEE_EXPENSE_TAP');

        $this->assertSame('tap:knet', $entries->firstWhere('account_id', $clearingLeaf)->settlement_channel);
        $this->assertSame('tap:knet', $entries->firstWhere('account_id', $feeLeaf)->settlement_channel);
        $this->assertNull($entries->firstWhere('account_id', $receivableLeaf)->settlement_channel, 'the party-side receivable line is not a settlement-side line — must stay null.');
    }

    // ── ClientController::addCredit() ───────────────────────────────────────────────────────

    private function makeTapCharge(int $companyId): Charge
    {
        $bankAccount = Account::where('company_id', $companyId)->where('name', 'Kuwait International Bank')->firstOrFail();
        $paymentGatewayAsset = Account::where('company_id', $companyId)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();
        $tapCharges = Account::where('company_id', $companyId)->where('name', 'TAP Charges')->firstOrFail();

        return Charge::factory()->create([
            'name' => 'Tap Gateway', 'company_id' => $companyId,
            'acc_bank_id' => $bankAccount->id, 'acc_fee_bank_id' => $paymentGatewayAsset->id, 'acc_fee_id' => $tapCharges->id,
        ]);
    }

    public function test_client_controller_add_credit_on_path_stamps_channel_on_clearing_and_fee_not_advance(): void
    {
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->makeTapCharge($company->id);

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id, 'client_id' => $tenant['client']->id,
            'invoice_id' => null, 'account_id' => null, 'created_by' => $tenant['agent']->user_id,
            'amount' => 100.0, 'gateway_fee' => 5.0, 'service_charge' => 0,
            'payment_gateway' => 'Tap', 'payment_method_id' => null, 'status' => 'completed', 'completed' => 0,
        ]);

        $response = app(ClientController::class)->addCredit($payment);
        $this->assertSame('success', $response['status'], $response['message'] ?? '');

        $clearingLeaf = $this->resolvedAccountId($company, 'GATEWAY_CLEARING_TAP');
        $feeLeaf = $this->resolvedAccountId($company, 'GATEWAY_FEE_EXPENSE_TAP');
        $advanceLeaf = $this->resolvedAccountId($company, 'CLIENT_ADVANCE');

        $clearingLine = DB::table('journal_entries')->where('account_id', $clearingLeaf)->where('company_id', $company->id)->first();
        $feeLine = DB::table('journal_entries')->where('account_id', $feeLeaf)->where('company_id', $company->id)->first();
        $advanceLine = DB::table('journal_entries')->where('account_id', $advanceLeaf)->where('company_id', $company->id)->first();

        $this->assertNotNull($clearingLine);
        $this->assertNotNull($feeLine);
        $this->assertNotNull($advanceLine);

        // No PaymentMethod on file for this payment -> channelFor() degrades to the bare
        // gateway key (no fabricated 'unknown' rail segment — see channelFor()'s own docblock).
        $this->assertSame('tap', $clearingLine->settlement_channel);
        $this->assertSame('tap', $feeLine->settlement_channel);
        $this->assertNull($advanceLine->settlement_channel, 'the party-side CLIENT_ADVANCE line is not a settlement-side line — must stay null.');
    }

    public function test_client_controller_add_credit_cash_topup_channel_is_cash(): void
    {
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id, 'client_id' => $tenant['client']->id,
            'invoice_id' => null, 'account_id' => null, 'created_by' => $tenant['agent']->user_id,
            'amount' => 40.0, 'gateway_fee' => 0, 'service_charge' => 0,
            'payment_gateway' => '', 'payment_method_id' => null, 'status' => 'completed', 'completed' => 0,
        ]);

        $response = app(ClientController::class)->addCredit($payment);
        $this->assertSame('success', $response['status'], $response['message'] ?? '');

        $cashLeaf = $this->resolvedAccountId($company, 'CASH_IN_HAND');
        $cashLine = DB::table('journal_entries')->where('account_id', $cashLeaf)->where('company_id', $company->id)->first();

        $this->assertNotNull($cashLine);
        $this->assertSame('cash', $cashLine->settlement_channel);
    }
}
