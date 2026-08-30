<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\RefundClient;
use App\Models\RefundDetail;
use App\Models\Role;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w4r-controller. W4.R bundled fixes (w4-brief.md §5) — RefundPolicy enforcement, the
 * refunds.show signed route split, RefundClient read-only guard, and
 * ClientController::refundProcess's Dr/Cr fix. Complements RefundPostingServiceTest.php, which
 * covers the (a)-(f) posting composition itself.
 */
class RefundControllerW4RTest extends AccountingTestCase
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

        // getCompanyId() resolves an ADMIN user's company from session('company_id', 1) -- an
        // ADMIN row carries no company relation of its own (app/Helper/helper.php). Every policy
        // gate here runs RequiresCompanyModule::moduleEnabled() first, which needs THIS company
        // resolved, not the session default of company_id=1.
        session(['company_id' => $company->id]);

        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);

        return [$company, $branch, $admin];
    }

    private function makeRefundFixture(Company $company, Branch $branch): array
    {
        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'type' => 'flight',
        ]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);
        $invoiceDetail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $refund = Refund::create([
            'refund_number' => 'REF-CTRL-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(),
            'total_refund_amount' => 100,
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

        return [$agent, $client, $task, $invoice, $invoiceDetail, $refund];
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // RefundPolicy enforcement
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_approve_is_403_for_unauthorized_role(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);

        $agentRoleUser = User::factory()->create(['role_id' => Role::AGENT]);

        $this->actingAs($agentRoleUser)
            ->post(route('refunds.approve', $refund->id))
            ->assertForbidden();
    }

    public function test_approve_succeeds_for_admin_and_transitions_status(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);

        $this->actingAs($admin)
            ->post(route('refunds.approve', $refund->id))
            ->assertRedirect();

        $this->assertSame(Refund::STATUS_APPROVED, $refund->fresh()->status);
    }

    public function test_approve_refuses_a_refund_that_already_left_the_mutable_state(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);
        $refund->update(['status' => Refund::STATUS_COMPLETED]);

        $this->actingAs($admin)
            ->post(route('refunds.approve', $refund->id))
            ->assertForbidden();
    }

    public function test_reject_voids_the_draft_never_deletes(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);

        $this->actingAs($admin)
            ->post(route('refunds.reject', $refund->id))
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame(Refund::STATUS_REJECTED, $refund->status);
        $this->assertDatabaseHas('refunds', ['id' => $refund->id]); // never deleted
    }

    public function test_completeRefundClient_is_403_without_the_ability(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $refundClient = RefundClient::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'KWD',
        ]);

        $unauthorized = User::factory()->create(['role_id' => 99]);

        $this->actingAs($unauthorized)
            ->get(route('refunds.refund-client.complete', $refundClient->id))
            ->assertForbidden();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // RefundClient read-only guard (folded into the refund doc)
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_refund_client_cannot_be_updated_or_deleted_once_created(): void
    {
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $refundClient = RefundClient::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'KWD',
        ]);

        $this->expectException(\RuntimeException::class);
        $refundClient->update(['status' => 'completed']);
    }

    public function test_refund_client_cannot_be_deleted(): void
    {
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $refundClient = RefundClient::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'KWD',
        ]);

        $this->expectException(\RuntimeException::class);
        $refundClient->delete();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // refunds.show: auth-only internal route vs. signed public route
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_show_route_requires_authentication_for_a_guest(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);

        $this->get(route('refunds.show', ['companyId' => $company->id, 'refundNumber' => $refund->refund_number]))
            ->assertRedirect(route('login'));
    }

    public function test_show_route_authorizes_the_authenticated_staff_variant(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);

        // RefundPolicy::view() falls back to $user->can('view refund') for a non-agent role --
        // grant it via a real Spatie role, same pattern ChargeControllerW4DBearerClientTest uses.
        $role = \App\Models\Role::create(['name' => 'admin-role', 'guard_name' => 'web', 'company_id' => $company->id]);
        $admin->assignRole($role);
        $role->givePermissionTo(['view refund']);

        $this->actingAs($admin)
            ->get(route('refunds.show', ['companyId' => $company->id, 'refundNumber' => $refund->refund_number]))
            ->assertOk();
    }

    public function test_public_signed_url_works_without_authentication(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);

        $this->get($refund->publicUrl())->assertOk();
    }

    public function test_unsigned_request_to_the_public_route_is_rejected(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        [, , , , , $refund] = $this->makeRefundFixture($company, $branch);

        $this->get(route('refunds.show.public', ['companyId' => $company->id, 'refundNumber' => $refund->refund_number]))
            ->assertForbidden();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ClientController::refundProcess Dr/Cr fix
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_client_refund_process_off_path_matches_legacy_exactly(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id, 'credit' => 100]);
        // refundProcess()'s own precondition reads Credit::getTotalCreditsByClient() (the ledger
        // sum), NOT clients.credit -- a real Credit row is required or the "Insufficient credit"
        // guard refuses the request before any posting code runs.
        \App\Models\Credit::create(['client_id' => $client->id, 'company_id' => $company->id, 'type' => \App\Models\Credit::TOPUP, 'amount' => 100]);

        config(['accounting.engine.enabled' => false]);

        // NOTE (incidental finding, out of W4.R's declared scope -- flagged in the build report,
        // not fixed here): the pre-existing legacy body this OFF path preserves byte-for-byte
        // creates a Credit row with type 'Refund Credit', which is NOT one of the four types
        // App\Models\Credit::booted()'s own `creating` guard allows (INVOICE/TOPUP/INVOICE_REFUND/
        // REFUND) -- that guard post-dates this legacy code and now throws on every real call to
        // this endpoint, caught by an inner try/catch that returns a raw response()->json(...)
        // instead of the array shape refund() expects, itself a SEPARATE pre-existing bug
        // (ClientController.php ~line 1424-1427/1272). This test asserts OFF-path BYTE-PARITY,
        // which includes reproducing this exact pre-existing failure -- not silently working
        // around it.
        $this->actingAs($admin)->post(route('clients.refund', $client->id), [
            'amount' => 30,
            'agent_id' => $agent->id,
        ]);

        // OFF path: byte-parity -- the two JournalEntry writes (the pre-existing, still-buggy
        // liability-to-liability pair this fix does NOT touch on the OFF path) already happened,
        // inside the still-open transaction, before the Credit::create() crash above.
        $refundPayableClients = Account::where('company_id', $company->id)
            ->where('name', 'Clients')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Refund Payable'))
            ->first();
        $this->assertNotNull($refundPayableClients);
        $this->assertSame(1, DB::table('journal_entries')->where('account_id', $refundPayableClients->id)->count());
    }

    public function test_client_refund_process_on_path_posts_balanced_pv_via_seam(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id, 'credit' => 100]);
        // refundProcess()'s own precondition reads Credit::getTotalCreditsByClient() (the ledger
        // sum), NOT clients.credit -- a real Credit row is required or the "Insufficient credit"
        // guard refuses the request before any posting code runs.
        \App\Models\Credit::create(['client_id' => $client->id, 'company_id' => $company->id, 'type' => \App\Models\Credit::TOPUP, 'amount' => 100]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        // REFUND_PAYOUT_CASH_BANK is intentionally company-configured, never auto-mapped -- map a
        // leaf for it here so the ON path can resolve it (see RefundPostingService's own docblock
        // for why this purpose code is never auto-seeded).
        $bank = Account::factory()->create(['company_id' => $company->id]);
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'REFUND_PAYOUT_CASH_BANK',
            'service_type' => null,
            'account_id' => $bank->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('clients.refund', $client->id), [
            'amount' => 30,
            'agent_id' => $agent->id,
        ]);

        $response->assertRedirect();

        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('sub_type', 'CLIENT_REFUND')->first();
        $this->assertNotNull($posted, 'ON path must post a real engine document.');
        $this->assertEqualsWithDelta(0.0, (float) $posted->total_debit - (float) $posted->total_credit, 0.0005);
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $bank->id)->count(), 'Money-out leg must credit the bank/cash leaf, never another liability.');

        // W4.R verify-fix (finding #4, HIGH): no orphan RefundClient row -- it is a dead-end
        // (completeRefundClient()/deleteRefundClient() refuse to ever touch it) -- and the
        // Credit ledger is dual-written instead so this method's own balance gate stays accurate.
        $this->assertSame(0, RefundClient::where('client_id', $client->id)->count(), 'ON path must not create a new orphan RefundClient row any more.');
        $decrement = \App\Models\Credit::where('client_id', $client->id)->where('amount', '<', 0)->first();
        $this->assertNotNull($decrement, 'A negative Credit row must decrement the client credit balance.');
        $this->assertEqualsWithDelta(-30.0, (float) $decrement->amount, 0.0005);
    }

    /**
     * W4.R verify-fix round 3 (finding #2, MEDIUM): the idempotency key used to bake in
     * `now()->format('YmdHis')` (wall-clock), so a genuine retry (double-click, network retry) of
     * the SAME logical payout landed a different key every time and both the PV and the Credit
     * row were double-posted. Fixed via PaymentIdempotencyKey::forClientRefundOut() (stable,
     * client+agent+amount) plus a pre-check-before-post Credit guard, both wrapped in one
     * DB::transaction(). Verify criterion: "double submission -> one PV, one Credit row."
     */
    public function test_client_refund_process_on_path_double_submission_posts_exactly_one_pv_and_one_credit(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id, 'credit' => 100]);
        \App\Models\Credit::create(['client_id' => $client->id, 'company_id' => $company->id, 'type' => \App\Models\Credit::TOPUP, 'amount' => 100]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        $bank = Account::factory()->create(['company_id' => $company->id]);
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'REFUND_PAYOUT_CASH_BANK',
            'service_type' => null,
            'account_id' => $bank->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = ['amount' => 30, 'agent_id' => $agent->id];

        // First submission.
        $this->actingAs($admin)->post(route('clients.refund', $client->id), $payload)->assertRedirect();
        // Retry of the IDENTICAL logical request (double-click / network retry) -- must be a no-op,
        // not a second PV/Credit pair.
        $this->actingAs($admin)->post(route('clients.refund', $client->id), $payload)->assertRedirect();

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('sub_type', 'CLIENT_REFUND')->count(),
            'A stable idempotency key must dedupe the retry to exactly one PV.'
        );
        $this->assertSame(
            1,
            \App\Models\Credit::where('client_id', $client->id)->where('amount', '<', 0)->count(),
            'The pre-check-before-post guard must dedupe the retry to exactly one negative Credit row.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W4.R verify-fix (finding #6, LOW) -- unauthorized-user coverage for the remaining mutating
    // actions the build report claimed were Gate::authorize()-guarded but never tested.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_is_403_for_unauthorized_role(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        [$agent, $client, $task, $invoice, $invoiceDetail, ] = $this->makeRefundFixture($company, $branch);

        $unauthorized = User::factory()->create(['role_id' => 99]);

        $this->actingAs($unauthorized)
            ->post(route('refunds.store'), [
                'date' => now()->toDateString(),
                'method' => 'Credit',
                'tasks' => [[
                    'task_id' => $task->id,
                    'original_invoice_price' => 100,
                    'original_task_cost' => 60,
                    'original_task_profit' => 40,
                    'refund_fee_to_client' => 0,
                    'supplier_charge' => 0,
                    'new_task_profit' => 0,
                    'total_refund_to_client' => 100,
                ]],
            ])
            ->assertForbidden();
    }

    public function test_delete_refund_client_is_403_without_the_ability(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $refundClient = RefundClient::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'KWD',
        ]);

        $unauthorized = User::factory()->create(['role_id' => 99]);

        $this->actingAs($unauthorized)
            ->delete(route('refunds.refund-client.delete', $refundClient->id))
            ->assertForbidden();
    }

    public function test_update_is_403_for_unauthorized_role(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        [, , $task, , , $refund] = $this->makeRefundFixture($company, $branch);

        $unauthorized = User::factory()->create(['role_id' => 99]);

        $this->actingAs($unauthorized)
            ->put(route('refunds.update', $refund->id), [
                'date' => now()->toDateString(),
                'method' => 'Credit',
                'tasks' => [[
                    'task_id' => $task->id,
                    'original_invoice_price' => 100,
                    'original_task_cost' => 60,
                    'original_task_profit' => 40,
                    'refund_fee_to_client' => 0,
                    'supplier_charge' => 0,
                    'new_task_profit' => 0,
                    'total_refund_to_client' => 100,
                ]],
            ])
            ->assertForbidden();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W4.R verify-fix (finding #5, MEDIUM) -- store() must land the refund in DRAFT and stop, on
    // the ON path, for the single most common case (a paid-invoice refund) rather than posting
    // synchronously through handlePaidRefund() before the draft -> approve step is ever reached.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_leaves_a_paid_invoice_refund_in_draft_on_the_engine_on_path(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        // RefundPolicy::create() only auto-grants role_id === Role::COMPANY -- an ADMIN row needs
        // the real permission instead (same pattern test_show_route_authorizes_the_authenticated_
        // staff_variant() above uses for 'view refund'; 'create refund' is not seeded by
        // PermissionSeeder, so it is created here first).
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create refund', 'guard_name' => 'web']);
        $role = \App\Models\Role::create(['name' => 'refund-creator', 'guard_name' => 'web', 'company_id' => $company->id]);
        $admin->assignRole($role);
        $role->givePermissionTo(['create refund']);

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertRedirect(route('refunds.index'));

        $refund = Refund::latest()->first();
        $this->assertNotNull($refund);
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status, 'store() must not auto-post an engine-ON refund -- it must wait for approve() then complete().');
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'Nothing may be posted before the refund is approved.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W4.R verify-fix round 2 (finding A, HIGH) -- update()'s 'paid' branch used to call
    // handlePaidRefund() unconditionally, which posts via RefundPostingService::post() on the ON
    // path. RefundPolicy::update() only ever allows reaching this method while the refund is
    // still 'draft', but post()'s own status guard refuses anything not yet 'approved' -- so
    // EVERY ON-path call to update() on a paid-invoice refund threw and rolled back. This is the
    // single most common case at cutover (every historical paid invoice), not a narrow edge case.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_leaves_a_paid_invoice_refund_in_draft_on_the_engine_on_path(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $refund = Refund::create([
            'refund_number' => 'REF-CTRL-UPD-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(),
            'total_refund_amount' => 100,
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

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        // RefundPolicy::update() auto-grants role_id === Role::ADMIN -- no extra permission needed.
        $response = $this->actingAs($admin)->put(route('refunds.update', $refund->id), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ]);

        $response->assertRedirect(route('refunds.edit', [$refund->id]));
        $response->assertSessionDoesntHaveErrors();
        $response->assertSessionHas('success');

        $refund->refresh();
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status, 'update() must not auto-post an engine-ON refund -- it must wait for approve() then complete(), same as store().');
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'Nothing may be posted before the refund is approved.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W4.R verify-fix round 3 (finding #1, HIGH) -- handleUnpaidInvoice()/handlePartialRefund()/
    // createRefundInvoiceUnpaid()/createRefundInvoicePartial()/handleRefundCOA() had ZERO engine
    // awareness and were called unconditionally from store()'s/update()'s 'unpaid'/'partial'
    // branches. Required: engine ON -> same draft-deferral as the 'paid' branch, no new invoice
    // ever created; engine OFF -> legacy bodies run byte-identical to HEAD.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function grantCreateRefundPermission(Company $company, User $admin): void
    {
        // RefundPolicy::create() only auto-grants role_id === Role::COMPANY -- an ADMIN row needs
        // the real permission instead (mirrors test_store_leaves_a_paid_invoice_refund_in_draft_
        // on_the_engine_on_path() above).
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create refund', 'guard_name' => 'web']);
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'refund-creator', 'company_id' => $company->id],
            ['guard_name' => 'web']
        );
        $admin->assignRole($role);
        $role->givePermissionTo(['create refund']);
    }

    public function test_store_leaves_an_unpaid_invoice_refund_in_draft_on_the_engine_on_path_with_no_new_invoice(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'unpaid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        $invoiceCountBefore = Invoice::count();

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertRedirect(route('refunds.index'));

        $refund = Refund::latest()->first();
        $this->assertNotNull($refund);
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status, 'An unpaid-invoice refund must also stop at draft on the ON path -- same as paid.');
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'Nothing may be posted before the refund is approved.');
        $this->assertSame(
            $invoiceCountBefore,
            Invoice::count(),
            'w4-brief.md §4: "NO new invoice" -- createRefundInvoiceUnpaid() must never run on the ON path.'
        );
    }

    public function test_store_leaves_a_partial_invoice_refund_in_draft_on_the_engine_on_path_with_no_new_invoice(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'partial']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        $invoiceCountBefore = Invoice::count();

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertRedirect(route('refunds.index'));

        $refund = Refund::latest()->first();
        $this->assertNotNull($refund);
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status, 'A partial-invoice refund must also stop at draft on the ON path -- same as paid.');
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'Nothing may be posted before the refund is approved.');
        $this->assertSame(
            $invoiceCountBefore,
            Invoice::count(),
            'w4-brief.md §4: "NO new invoice" -- createRefundInvoicePartial()/handleRefundCOA() must never run on the ON path.'
        );
    }

    public function test_update_leaves_an_unpaid_invoice_refund_in_draft_on_the_engine_on_path(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'unpaid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100]);

        $refund = Refund::create([
            'refund_number' => 'REF-CTRL-UPD-UNPAID-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(),
            'total_refund_amount' => 100,
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

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $invoiceCountBefore = Invoice::count();

        $response = $this->actingAs($admin)->put(route('refunds.update', $refund->id), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ]);

        $response->assertRedirect(route('refunds.edit', [$refund->id]));
        $response->assertSessionDoesntHaveErrors();

        $refund->refresh();
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame($invoiceCountBefore, Invoice::count(), 'No new invoice on the ON path for update() either.');
    }

    public function test_update_leaves_a_partial_invoice_refund_in_draft_on_the_engine_on_path(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'partial']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100]);

        $refund = Refund::create([
            'refund_number' => 'REF-CTRL-UPD-PARTIAL-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(),
            'total_refund_amount' => 100,
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

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $invoiceCountBefore = Invoice::count();

        $response = $this->actingAs($admin)->put(route('refunds.update', $refund->id), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ]);

        $response->assertRedirect(route('refunds.edit', [$refund->id]));
        $response->assertSessionDoesntHaveErrors();

        $refund->refresh();
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame($invoiceCountBefore, Invoice::count(), 'No new invoice on the ON path for update() either.');
    }

    /**
     * OFF-path byte parity: an unpaid-invoice refund submitted through store() while the engine
     * is OFF must still run the pre-existing legacy re-invoice flow unchanged --
     * createRefundInvoiceUnpaid() creates a brand-new Invoice/InvoiceDetail row set and the
     * response redirects to invoice.edit(), exactly as it did before this fix-wave touched
     * store()'s branching.
     */
    public function test_store_off_path_unpaid_invoice_refund_still_creates_a_new_refund_invoice(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'unpaid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100]);

        // Engine OFF (config default). Same $admin + grantCreateRefundPermission() pattern as the
        // ON-path tests above -- a bare COMPANY-role user unrelated to $company fails RefundPolicy
        // ::create()'s moduleEnabled() company resolution and gets a silent 403 (no session error,
        // but nothing is ever created), which is why this must resolve to the SAME company.
        $this->grantCreateRefundPermission($company, $admin);

        $invoiceCountBefore = Invoice::count();

        $response = $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ]);

        $response->assertRedirect(route('refunds.index'));
        $response->assertSessionDoesntHaveErrors();
        $this->assertGreaterThan($invoiceCountBefore, Invoice::count(), 'OFF path: createRefundInvoiceUnpaid() must still run unchanged and create a new invoice.');
    }

    /**
     * End-to-end proof that engine-ON unpaid/partial refunds actually post via
     * RefundPostingService once approved -- not merely "deferred forever". No new Invoice/
     * InvoiceDetail row is ever created; the CRN reduces the SAME carrying invoice via a real
     * reverse()/standalone-legacy-CRN document.
     */
    public function test_approve_then_complete_posts_an_unpaid_invoice_refund_via_refund_posting_service_with_no_new_invoice(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'unpaid']);
        $invoiceDetail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'task_price' => 100]);

        $refund = Refund::create([
            'refund_number' => 'REF-E2E-UNPAID-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT,
            'refund_date' => now(),
            'total_refund_amount' => 100,
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

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $invoiceCountBefore = Invoice::count();

        $this->actingAs($admin)->post(route('refunds.approve', $refund->id))->assertRedirect();
        $this->assertSame(Refund::STATUS_APPROVED, $refund->fresh()->status);

        $this->actingAs($admin)->post(route('refunds.complete_process', $refund->id))->assertRedirect();

        $refund->refresh();
        $this->assertContains($refund->status, [Refund::STATUS_COMPLETED, Refund::STATUS_POSTED]);
        $this->assertSame($invoiceCountBefore, Invoice::count(), 'RefundPostingService must never create a new Invoice for an unpaid-invoice refund.');
        $this->assertGreaterThan(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'A real document set must have posted.');

        // The CRN reversed/standalone-posted against invoice_detail rows carrying THIS SAME
        // invoice_detail_id -- never a new invoice.
        $crnLine = \App\Models\JournalEntry::withoutGlobalScopes()
            ->where('invoice_detail_id', $invoiceDetail->id)
            ->where('company_id', $company->id)
            ->exists();
        $this->assertTrue($crnLine, 'CRN must reference the SAME carrying invoice_detail, never a new invoice.');
    }

    /**
     * w4-brief.md §4: handleRefundCOA()'s raw Transaction::create()/JournalEntry writes via
     * Account::where('name', 'like', ...) are legacy, OFF-path-only behaviour. Direct
     * defence-in-depth proof (not merely "unreachable via the two call sites both gating on
     * $engineOn") -- the method itself refuses outright when the engine is ON for the refund's
     * company.
     */
    public function test_handle_refund_coa_refuses_to_run_when_the_engine_is_on(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        [$agent, $client, $task, $invoice, , $refund] = $this->makeRefundFixture($company, $branch);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $controller = app(\App\Http\Controllers\RefundController::class);
        $method = new \ReflectionMethod($controller, 'handleRefundCOA');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('posting engine ON');

        $method->invoke($controller, $refund, 10.0, 20.0);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W4.U verify-fix (HIGH) -- disposition='apply' (w4-brief.md §4f "apply to open invoice") had
    // no `applied_invoice_id` field anywhere in store()/update() or the two views, so
    // RefundPostingService::postDisposition() always threw when it was selected. Covers the
    // validation guard (validateAppliedInvoiceId()) and the full store() -> approve() ->
    // complete_process() flow actually posting the disposition against the picked invoice.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_disposition_apply_requires_applied_invoice_id(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        $refundCountBefore = Refund::count();

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'disposition' => 'apply',
            // applied_invoice_id deliberately omitted.
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertRedirect()->assertSessionHasErrors('error');

        $this->assertSame($refundCountBefore, Refund::count(), 'No refund may be created when disposition=apply has no target invoice.');
    }

    public function test_store_rejects_applied_invoice_id_not_belonging_to_the_refunds_client(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $otherClient = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $otherClientsInvoice = Invoice::factory()->create(['client_id' => $otherClient->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'unpaid']);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        $refundCountBefore = Refund::count();

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'disposition' => 'apply',
            'applied_invoice_id' => $otherClientsInvoice->id,
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertRedirect()->assertSessionHasErrors('error');

        $this->assertSame($refundCountBefore, Refund::count(), "An invoice belonging to a different client must be rejected as the 'apply' target.");
    }

    public function test_store_persists_applied_invoice_id_and_completing_posts_disposition_against_it(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $this->trackCompanyForInvariants($company->id);
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        // The OPEN invoice the client net will be applied against -- must not be the one being
        // refunded (validateAppliedInvoiceId() refuses that as circular).
        $targetInvoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'unpaid']);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'disposition' => 'apply',
            'applied_invoice_id' => $targetInvoice->id,
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertRedirect(route('refunds.index'))->assertSessionDoesntHaveErrors();

        $refund = Refund::latest()->first();
        $this->assertNotNull($refund);
        $this->assertSame('apply', $refund->disposition);
        $this->assertSame($targetInvoice->id, $refund->applied_invoice_id);

        $this->actingAs($admin)->post(route('refunds.approve', $refund->id))->assertRedirect();
        $this->actingAs($admin)->post(route('refunds.complete_process', $refund->id))->assertRedirect();

        $refund->refresh();
        $this->assertContains($refund->status, [Refund::STATUS_COMPLETED, Refund::STATUS_POSTED]);

        $dispositionDoc = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':disposition')
            ->first();
        $this->assertNotNull($dispositionDoc, 'disposition=apply must actually post once applied_invoice_id is set -- it must not throw.');

        // The credit-purpose (debit) leg of postDisposition() carries `invoiceId:
        // $refund->applied_invoice_id` per-LINE (RefundPostingService.php ~L850) -- the header's
        // own transactions.invoice_id is not set for this document type, so the per-line
        // journal_entries.invoice_id is the real proof the applied invoice was used.
        $appliedInvoiceIdOnLine = \App\Models\JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $dispositionDoc->id)
            ->whereNotNull('invoice_id')
            ->value('invoice_id');
        $this->assertSame($targetInvoice->id, (int) $appliedInvoiceIdOnLine, 'The disposition document must reference the APPLIED invoice, not the one being refunded.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W4.U verify-fix (MEDIUM) -- fee_schedule.{type}.override/amount/percent
    // (SettingController::storeAccountingSettings()) were persisted but never read by any posting
    // logic. applyRefundFeeSchedule() is the read side; 'free' forces the fee to zero and adjusts
    // total_refund_to_client by the same delta so the client-net figure stays consistent.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_applies_fee_schedule_free_override_and_adjusts_client_net(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.fee_schedule.flight.override',
            'value' => 'free',
            'type' => 'string',
        ]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        // Staff types a 10.000 fee; the company has waived flight refund fees entirely.
        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 10,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 90,
            ]],
        ])->assertRedirect(route('refunds.index'))->assertSessionDoesntHaveErrors();

        $refund = Refund::latest()->first();
        $this->assertNotNull($refund);
        $detail = $refund->refundDetails()->first();
        $this->assertEqualsWithDelta(0.0, (float) $detail->refund_fee_to_client, 0.0005, "override='free' must force the fee to zero regardless of what was submitted.");
        $this->assertEqualsWithDelta(100.0, (float) $detail->total_refund_to_client, 0.0005, 'total_refund_to_client must be adjusted by the same delta so the client net stays consistent.');
        $this->assertEqualsWithDelta(0.0, (float) $refund->total_refund_amount, 0.0005);
        $this->assertEqualsWithDelta(100.0, (float) $refund->total_nett_refund, 0.0005);
    }

    public function test_store_honours_configured_fee_schedule_amount_when_no_override_is_set(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.fee_schedule.flight.amount',
            'value' => 15,
            'type' => 'string',
        ]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        // Staff types a 10.000 fee; the company's configured flat fee for this service type is 15.
        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 10,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 90,
            ]],
        ])->assertRedirect(route('refunds.index'))->assertSessionDoesntHaveErrors();

        $refund = Refund::latest()->first();
        $detail = $refund->refundDetails()->first();
        $this->assertEqualsWithDelta(15.0, (float) $detail->refund_fee_to_client, 0.0005, 'The configured fee_schedule amount is authoritative over whatever staff typed.');
        $this->assertEqualsWithDelta(85.0, (float) $detail->total_refund_to_client, 0.0005);
    }

    public function test_store_persists_airline_clawback_amount(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->grantCreateRefundPermission($company, $admin);

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'airline_clawback_amount' => 12.5,
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertRedirect(route('refunds.index'))->assertSessionDoesntHaveErrors();

        $refund = Refund::latest()->first();
        $this->assertEqualsWithDelta(12.5, (float) $refund->airline_clawback_amount, 0.0005, 'w4-brief.md §4e — the screen was previously missing this field entirely.');
    }
}
