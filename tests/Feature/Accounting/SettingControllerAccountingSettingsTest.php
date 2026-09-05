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
use App\Models\Setting;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w4u-settings. W4.U §a — the Accounting settings tab
 * (SettingController::getAccountingSettings()/storeAccountingSettings(), gated by
 * SettingPolicy::viewAccountingSettings()/manageAccountingSettings()). Verify criterion 1
 * (w4-brief.md §W4.U): "Submitting the settings form persists each option ... and the very next
 * posting in a feature test honours it."
 */
class SettingControllerAccountingSettingsTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeCompanyWithAdmin(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);

        return [$company, $branch, $admin];
    }

    /**
     * A Role::AGENT user actually attached (via a real Agent row) to $branch so
     * getCompanyId()/EnsureModuleEnabled's own moduleEnabled() resolves a company at all --
     * an agent with no Agent row resolves to NO company and the module-gate middleware 404s the
     * request before SettingPolicy's own ability check ever runs, which would test the wrong layer.
     */
    private function makeUnauthorizedAgentUser(Branch $branch): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    private function fullSettingsPayload(): array
    {
        return [
            'invoice_overpay_cancel_policy' => 'refund_out',
            'unclaimed_writeback_months' => 9,
            'commissionable_fee_types' => ['flight'],
            'refund_send_on_post' => false,
            'agent_unearn_notice' => false,
            'fee_schedule' => [
                'flight' => ['amount' => 5, 'percent' => 0, 'override' => 'free'],
            ],
            'posting_basis' => [
                'flight' => 'principal',
            ],
            // W4.U verify-fix (MEDIUM): 'adm'/'gateway_fee' rows removed from the bearer matrix
            // entirely -- see SettingController::getAccountingSettings()'s own comment. Only
            // 'commission_clawback' has a real, documented consumer.
            'bearer' => [
                'commission_clawback' => ['value' => 'agent', 'split_percent' => 60],
            ],
        ];
    }

    public function test_store_persists_each_option(): void
    {
        [$company, , $admin] = $this->makeCompanyWithAdmin();

        $this->actingAs($admin)
            ->postJson(route('settings.accounting-settings.store'), $this->fullSettingsPayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(
            'refund_out',
            Setting::getByKey($company->id, 'accounting.refund.invoice_overpay_cancel_policy')
        );
        $this->assertSame(
            9,
            (int) Setting::getByKey($company->id, 'accounting.refund.unclaimed_writeback_months')
        );
        $this->assertSame(
            ['flight'],
            json_decode((string) Setting::getByKey($company->id, 'accounting.commissionable_fee_types'), true)
        );
        $this->assertFalse(
            filter_var(Setting::getByKey($company->id, 'accounting.refund.refund_send_on_post'), FILTER_VALIDATE_BOOLEAN)
        );
        $this->assertFalse(
            filter_var(Setting::getByKey($company->id, 'accounting.refund.agent_unearn_notice'), FILTER_VALIDATE_BOOLEAN)
        );
        $this->assertEqualsWithDelta(
            5.0,
            (float) Setting::getByKey($company->id, 'accounting.refund.fee_schedule.flight.amount'),
            0.0005
        );
        $this->assertSame('free', Setting::getByKey($company->id, 'accounting.refund.fee_schedule.flight.override'));
        $this->assertSame('principal', Setting::getByKey($company->id, 'accounting.posting_basis.flight'));
        $this->assertSame('agent', Setting::getByKey($company->id, 'bearer.commission_clawback'));
        $this->assertEqualsWithDelta(
            60.0,
            (float) Setting::getByKey($company->id, 'bearer.commission_clawback.split_percent'),
            0.0005
        );
    }

    public function test_get_returns_the_persisted_values_back(): void
    {
        [$company, , $admin] = $this->makeCompanyWithAdmin();

        $this->actingAs($admin)
            ->postJson(route('settings.accounting-settings.store'), $this->fullSettingsPayload())
            ->assertOk();

        $response = $this->actingAs($admin)->getJson(route('settings.accounting-settings'));

        $response->assertOk();
        $response->assertJsonPath('settings.invoice_overpay_cancel_policy', 'refund_out');
        $response->assertJsonPath('settings.unclaimed_writeback_months', 9);
        $response->assertJsonPath('settings.commissionable_fee_types', ['flight']);
        $response->assertJsonPath('settings.posting_basis.flight', 'principal');
        $response->assertJsonPath('settings.bearer.commission_clawback.value', 'agent');
    }

    public function test_store_is_403_for_a_role_without_the_ability(): void
    {
        [, $branch] = $this->makeCompanyWithAdmin();
        $unauthorized = $this->makeUnauthorizedAgentUser($branch);

        $this->actingAs($unauthorized)
            ->postJson(route('settings.accounting-settings.store'), $this->fullSettingsPayload())
            ->assertForbidden();
    }

    public function test_get_is_403_for_a_role_without_the_ability(): void
    {
        [, $branch] = $this->makeCompanyWithAdmin();
        $unauthorized = $this->makeUnauthorizedAgentUser($branch);

        $this->actingAs($unauthorized)
            ->getJson(route('settings.accounting-settings'))
            ->assertForbidden();
    }

    /**
     * W4.U §a — the tab actually renders inside settings/index.blade.php (not just the AJAX
     * endpoints in isolation). Catches Blade runtime errors (undefined variables etc.) that
     * `Blade::compileString()` alone cannot.
     */
    public function test_settings_index_renders_the_accounting_tab(): void
    {
        [, , $admin] = $this->makeCompanyWithAdmin();
        session(['settings_active_tab' => 'accounting']);

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('accountingSettingsTab', false);
        $response->assertSee(route('settings.accounting-settings.store'), false);
    }

    /**
     * Verify criterion 1 (w4-brief.md §W4.U): "the very next posting in a feature test honours
     * it (e.g. flip invoice_overpay_cancel_policy to refund_out, post a refund via the screen's
     * controller action, assert a PV draft was created instead of a 2632 credit)."
     */
    public function test_flipping_invoice_overpay_cancel_policy_changes_the_next_refunds_disposition(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();

        // 1) Submit the settings form -- flip the company default to refund_out.
        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), array_merge(
            $this->fullSettingsPayload(),
            ['invoice_overpay_cancel_policy' => 'refund_out']
        ))->assertOk();

        // 2) Build a paid-invoice refund and post it WITHOUT the refund's own method/disposition
        //    set -- so it must fall back to the company default just persisted above.
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        // REFUND_PAYOUT_CASH_BANK must be mapped for refund_out to resolve (RefundPostingService's
        // own docblock: this purpose code is never auto-seeded, company-configured only).
        $bank = Account::factory()->create(['company_id' => $company->id]);
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'REFUND_PAYOUT_CASH_BANK',
            'service_type' => null,
            'account_id' => $bank->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refund = Refund::create([
            'refund_number' => 'REF-SETTINGS-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            // No method/disposition set at all -- must fall back to the company default.
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

        $this->actingAs($admin)
            ->post(route('refunds.complete_process', $refund->id))
            ->assertRedirect();

        // A PV must have posted the disposition leg -- never a JV/2632 credit.
        $dispositionTransaction = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'refund:'.$refund->id.':disposition')
            ->first();
        $this->assertNotNull($dispositionTransaction, 'The disposition leg must have posted.');
        $this->assertSame('PV', $dispositionTransaction->doc_type, 'Company default refund_out must drive a PV, not a JV.');
        $this->assertSame(
            1,
            DB::table('journal_entries')->where('transaction_id', $dispositionTransaction->id)->where('account_id', $bank->id)->count(),
            'The payout leg must hit the mapped bank/cash leaf.'
        );
        $this->assertSame(
            0,
            Credit::where('refund_id', $refund->id)->count(),
            'refund_out must never dual-write a 2632 Credit row.'
        );
    }

    /**
     * W4.U verify-fix (MEDIUM): 'adm' and 'gateway_fee' were persisted here but read by no
     * posting/business logic anywhere -- 'adm' has no consumer or hook at all, 'gateway_fee'
     * duplicated the unrelated, already-shipped Charge::paid_by mechanism (W4.D). Both rows were
     * removed from the bearer matrix entirely rather than left as misleading dead config. Proves
     * they are never persisted even if a client still submits them (e.g. a stale cached page).
     */
    public function test_removed_bearer_kinds_are_never_persisted_even_if_submitted(): void
    {
        [$company, , $admin] = $this->makeCompanyWithAdmin();

        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), array_merge(
            $this->fullSettingsPayload(),
            ['bearer' => [
                'commission_clawback' => ['value' => 'agent', 'split_percent' => 60],
                'adm' => ['value' => 'company', 'split_percent' => 50],
                'gateway_fee' => ['value' => 'split', 'split_percent' => 40],
            ]]
        ))->assertOk()->assertJson(['success' => true]);

        $this->assertNull(Setting::where('company_id', $company->id)->where('key', 'bearer.adm')->first());
        $this->assertNull(Setting::where('company_id', $company->id)->where('key', 'bearer.gateway_fee')->first());

        $response = $this->actingAs($admin)->getJson(route('settings.accounting-settings'));
        $response->assertOk();
        $response->assertJsonMissingPath('settings.bearer.adm');
        $response->assertJsonMissingPath('settings.bearer.gateway_fee');
    }

    /**
     * W4.U verify-fix (MEDIUM): `commissionable_fee_types` (persisted by
     * storeAccountingSettings()) previously had NO consumer anywhere -- the "fresh JV/
     * AGENT_COMMISSION for the refund event's own commissionable margin" half of w4-brief.md §4d
     * was never implemented. Mirrors this file's own
     * test_flipping_invoice_overpay_cancel_policy_changes_the_next_refunds_disposition() pattern
     * for verify criterion 1: submit the settings form, then prove the VERY NEXT posting honours
     * it (RefundPostingService::postCommissionEarnForRefundDetail()).
     */
    public function test_flipping_commissionable_fee_types_causes_the_next_refund_to_earn_commission(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();

        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), array_merge(
            $this->fullSettingsPayload(),
            [
                'commissionable_fee_types' => ['flight'],
                // Keep disposition at the 'credit' default (Cr 2632, no extra account mapping
                // needed) -- fullSettingsPayload()'s own 'refund_out' default would otherwise
                // make postDisposition() throw for an unmapped REFUND_PAYOUT_CASH_BANK purpose
                // code, rolling back the WHOLE transaction (including the commission-earn JV
                // this test means to prove) since post() runs in one DB::transaction().
                'invoice_overpay_cancel_policy' => 'credit',
            ]
        ))->assertOk();

        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        $refund = Refund::create([
            'refund_number' => 'REF-COMM-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 20,
            'total_refund_charge' => 0,
            'total_nett_refund' => 80,
        ]);
        $detail = RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 20.000,
            'supplier_charge' => 0,
            'total_refund_to_client' => 80.000,
        ]);

        $this->actingAs($admin)
            ->post(route('refunds.complete_process', $refund->id))
            ->assertRedirect();

        $commissionEarn = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':commission-earn:'.$detail->id)
            ->first();

        $this->assertNotNull($commissionEarn, 'The commissionable_fee_types option just submitted must be honoured by the very next posting.');
        $this->assertEqualsWithDelta(3.000, (float) $commissionEarn->total_debit, 0.0005, '0.15 rate x 20.000 fee = 3.000.');
    }
}
