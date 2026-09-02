<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w7k. W7.K (.planning/accounting-waves/w7/w7-brief.md §W7.K) --
 * CreditController::creditTopup() through the seam. The only reachable raw ledger writer this
 * controller had per w6-final-gate.md's sole-writer audit -- store() has no route
 * (routes/web.php's `credits` group only registers index/filter/useCreditNow/topup) and never
 * touches the ledger even if it were reachable; useCreditNow() is a pre-existing dead route
 * (its controller method does not exist -- see `Accounting Gap/16-phase1-verification-
 * findings-2026-08.md` item C.4, "only credits.useCreditNow is dead"). See this class's own
 * report (.planning/accounting-waves/w7/w7k-build.md) for the full map.
 */
class CreditControllerW7KTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Branch, 2: User}
     */
    private function makeCompanyWithAdmin(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        // getCompanyId() resolves an ADMIN user's company from session('company_id', 1) --
        // RequiresCompanyModule::moduleEnabled() (CreditPolicy::create()'s first check) needs
        // THIS company resolved, not the session default of company_id=1. Mirrors
        // RefundControllerW4RTest::makeCompanyWithAdmin() exactly.
        session(['company_id' => $company->id]);
        $admin->givePermissionTo('create credit');
        // Pre-existing, unrelated-to-W7.K requirement: creditTopup()'s legacy body writes
        // `credits.topup_by`, a strict `ENUM('Client','Branch','Company')` column
        // (database/migrations/*_add_columns_to_credits_table.php), from
        // `ucfirst(auth()->user()->getRoleNames()->first())` -- a Spatie role NAME (via
        // HasRoles::assignRole()), completely independent of the numeric `users.role_id`
        // column CreatesTenantFixtures/App\Models\Role::ADMIN use. A test user with no Spatie
        // role assigned makes that expression ucfirst(null) === '', which MySQL's strict mode
        // refuses to truncate silently into the enum -- an SQLSTATE[01000] on every real
        // request, on BOTH the OFF and ON paths (the failure is in code this cutover does not
        // touch). Assigning a real 'company' role is fixture setup, not a behavior change.
        $companyRole = Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $company->id]);
        $admin->assignRole($companyRole);

        AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);

        return [$company, $branch, $admin];
    }

    /**
     * @return array{0: Agent, 1: Client}
     */
    private function makeAgentAndClient(Branch $branch): array
    {
        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return [$agent, $client];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Authorization -- CreditPolicy::create(), previously nonexistent ("zero authorization
    // today" per the credits route group's own comment).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_credit_topup_is_403_without_the_create_credit_ability(): void
    {
        [$company, $branch] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        $userWithoutAbility = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $response = $this->actingAs($userWithoutAbility)->post(route('credits.topup'), [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 50,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Credit::where('client_id', $client->id)->count(), 'A 403 must refuse before any write.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path -- byte parity vs the pre-W7.K legacy body.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_matches_legacy_exactly(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        // buildCreditTopupDraft() resolves CASH_IN_HAND unconditionally (mirrors
        // ReceiptVoucherController::buildVoucherDraft()'s own unconditional AccountResolver
        // call, see e.g. ReceiptVoucherControllerW5RTest::test_off_path_still_posts_a_
        // balanced_document_to_the_same_accounts() seeding SystemAccountsSeeder for its own
        // "off path" test) -- company_id -> posting_engine_enabled stays false, so
        // PostingSeam::isEnabledFor() still routes to $legacy() below; only the mapping lookup
        // itself needs to resolve.
        (new SystemAccountsSeeder)->run();
        config(['accounting.engine.enabled' => false]);

        $response = $this->actingAs($admin)->post(route('credits.topup'), [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 40,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Legacy shape, verbatim: Credit row + 2 raw Transactions + 2 raw JournalEntry rows,
        // NEITHER carrying an idempotency_key (the engine never ran).
        $this->assertSame(1, Credit::where('client_id', $client->id)->where('type', 'Topup')->count());
        $this->assertSame(2, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('idempotency_key')->count(),
            'OFF path must never populate idempotency_key -- that column is an engine-only concept.'
        );

        // 'Payment Gateway' also exists twice in the seeded COA (1300 under Assets, and 2632
        // under Liabilities > Advances > Client) -- disambiguate the same way the legacy closure
        // does: `parent_id` under the 'Client' (2630) leaf, which is what CLIENT_ADVANCE (2632)
        // actually is.
        $paymentGateway = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Client'))->first();
        // 'Clients' exists twice in the seeded COA (1351 under Assets > Accounts Receivable, and
        // 2610 under Liabilities > Refund Payable) -- disambiguate the same way the LEGACY closure
        // itself does: by `root_id` (the top-of-tree ancestor, a separate column/relation from the
        // immediate `parent_id` -- see Account::root()), not by immediate parent. 1351's `root_id`
        // is 'Assets' even though its direct parent is 'Accounts Receivable'.
        $clientReceivable = Account::where('company_id', $company->id)->where('name', 'Clients')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->first();
        $this->assertNotNull($paymentGateway);
        $this->assertNotNull($clientReceivable);

        $gatewayLine = JournalEntry::where('account_id', $paymentGateway->id)->where('company_id', $company->id)->first();
        $receivableLine = JournalEntry::where('account_id', $clientReceivable->id)->where('company_id', $company->id)->first();
        $this->assertNotNull($gatewayLine);
        $this->assertNotNull($receivableLine);
        $this->assertEqualsWithDelta(40.0, (float) $gatewayLine->credit, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $gatewayLine->debit, 0.0005);
        $this->assertEqualsWithDelta(40.0, (float) $receivableLine->debit, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $receivableLine->credit, 0.0005);

        // Legacy actual_balance mutation, preserved byte-for-byte.
        $this->assertEqualsWithDelta(-40.0, (float) $paymentGateway->fresh()->actual_balance, 0.0005);
        $this->assertEqualsWithDelta(40.0, (float) $clientReceivable->fresh()->actual_balance, 0.0005);
    }

    public function test_off_path_refuses_and_rolls_back_when_payment_gateway_account_is_missing(): void
    {
        // Reproduces the pre-existing legacy guard (`if (!$paymentGateway) { throw ... }`)
        // verbatim: a company with no CoaSeeder run has no 'Payment Gateway' leaf at all.
        $company = Company::factory()->create();
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $admin->givePermissionTo('create credit');
        $companyRole = Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $company->id]);
        $admin->assignRole($companyRole);
        AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        [$agent, $client] = $this->makeAgentAndClient($branch);

        config(['accounting.engine.enabled' => false]);

        $response = $this->actingAs($admin)->post(route('credits.topup'), [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 40,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        // Whole request rolled back -- including the Credit row created before the account
        // lookup failed, matching legacy's single enclosing DB transaction.
        $this->assertSame(0, Credit::where('client_id', $client->id)->count());
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path -- Dr instrument / Cr CLIENT_ADVANCE (2632) via the RV/TOPUP shape.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_posts_one_balanced_rv_document_defaulting_to_cash_in_hand(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->actingAs($admin)->post(route('credits.topup'), [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 55.5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $key = PaymentIdempotencyKey::forManualClientCreditTopup($client->id, $agent->id, 55.5);
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($posted, 'ON path must post a real engine document under the stable request-identity key.');
        $this->assertSame('RV', $posted->doc_type);
        $this->assertEqualsWithDelta(0.0, (float) $posted->total_debit - (float) $posted->total_credit, 0.0005, 'Document must balance.');

        $cashInHand = app(\App\Services\Accounting\AccountResolver::class)->resolve('CASH_IN_HAND', $company->id);
        $clientAdvance = app(\App\Services\Accounting\AccountResolver::class)->resolve('CLIENT_ADVANCE', $company->id);

        $debitLine = JournalEntry::where('transaction_id', $posted->id)->where('account_id', $cashInHand->id)->first();
        $creditLine = JournalEntry::where('transaction_id', $posted->id)->where('account_id', $clientAdvance->id)->first();
        $this->assertNotNull($debitLine, 'Instrument leg must debit CASH_IN_HAND when no account_id is supplied.');
        $this->assertNotNull($creditLine, 'The other leg must credit CLIENT_ADVANCE (2632).');
        $this->assertEqualsWithDelta(55.5, (float) $debitLine->debit, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $debitLine->credit, 0.0005);
        $this->assertEqualsWithDelta(55.5, (float) $creditLine->credit, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $creditLine->debit, 0.0005);

        $this->assertSame(2, JournalEntry::where('transaction_id', $posted->id)->count(), 'Exactly one balanced two-line document per action.');
        $this->assertSame(1, Credit::where('client_id', $client->id)->where('type', 'Topup')->count());

        // Engine-sole-writer: no raw legacy JournalEntry against the name-resolved legacy
        // accounts on the ON path.
        $paymentGateway = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')->first();
        if ($paymentGateway) {
            $this->assertSame(0, JournalEntry::where('account_id', $paymentGateway->id)->where('company_id', $company->id)->count());
        }
    }

    public function test_on_path_honours_an_explicit_bank_account_id_as_the_instrument_leg(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $bankLeaf = Account::where('company_id', $company->id)->where('name', 'Kuwait International Bank')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('credits.topup'), [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 20,
            'account_id' => $bankLeaf->id,
        ]);

        $response->assertRedirect();

        $key = PaymentIdempotencyKey::forManualClientCreditTopup($client->id, $agent->id, 20.0);
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->firstOrFail();

        $this->assertSame(
            1,
            JournalEntry::where('transaction_id', $posted->id)->where('account_id', $bankLeaf->id)->where('debit', 20)->count(),
            'An explicit account_id under the Bank Accounts group must be debited instead of the CASH_IN_HAND default.'
        );
    }

    public function test_on_path_rejects_an_account_id_not_under_the_bank_group(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // A real, valid leaf for this company, but not under 'Bank Accounts' -- e.g. an expense
        // leaf. assertUnderBankGroup() must refuse it rather than silently posting the wrong
        // instrument.
        $notABankLeaf = Account::where('company_id', $company->id)->where('name', 'Cash Over/Short')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('credits.topup'), [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 20,
            'account_id' => $notABankLeaf->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $key = PaymentIdempotencyKey::forManualClientCreditTopup($client->id, $agent->id, 20.0);
        $this->assertNull(Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first());
    }

    /**
     * Mirrors RefundControllerW4RTest::test_client_refund_process_on_path_double_submission_
     * posts_exactly_one_pv_and_one_credit() -- same class of fix (w4-brief.md verify-fix round
     * 3, finding #2), applied one lifecycle step earlier. See PaymentIdempotencyKey::
     * forManualClientCreditTopup()'s own docblock for why credit_id could never have supported this.
     */
    public function test_on_path_double_submission_posts_exactly_one_document(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payload = ['client_id' => $client->id, 'agent_id' => $agent->id, 'amount' => 33];

        $this->actingAs($admin)->post(route('credits.topup'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('credits.topup'), $payload)->assertRedirect();

        $key = PaymentIdempotencyKey::forManualClientCreditTopup($client->id, $agent->id, 33.0);
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->count(),
            'A stable idempotency key must dedupe the retry to exactly one RV document.'
        );
    }

    public function test_build_credit_topup_draft_posted_twice_through_the_engine_directly_is_idempotent(): void
    {
        [$company, $branch] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $credit = Credit::create(['company_id' => $company->id, 'client_id' => $client->id, 'branch_id' => $branch->id, 'type' => Credit::TOPUP, 'amount' => 10]);

        $controller = app(\App\Http\Controllers\CreditController::class);
        $key = PaymentIdempotencyKey::forManualClientCreditTopup($client->id, $agent->id, 10.0);

        $draft = $controller->buildCreditTopupDraft($credit, $company->id, $branch->id, $client, 10.0, null, $key);
        app(\App\Services\Accounting\PostingSeam::class)->post($draft, fn () => null, 'credit.create.test');

        $draft2 = $controller->buildCreditTopupDraft($credit, $company->id, $branch->id, $client, 10.0, null, $key);
        app(\App\Services\Accounting\PostingSeam::class)->post($draft2, fn () => null, 'credit.create.test');

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->count(),
            'PostingService::post()\'s own idempotency-key short-circuit must dedupe two identical drafts.'
        );
    }

    /**
     * Demonstrates the "reversal/void via engine reverse() by key" requirement (w7-brief.md
     * §W7.K) at the engine level: no dedicated void endpoint exists for a credit top-up today
     * (CreditController ships no such action -- see this class's own docblock), but the document
     * this cutover posts is a normal engine document, reversible generically by anyone who holds
     * its idempotency key, exactly like every other W1-W6 cutover document.
     */
    public function test_the_posted_document_is_reversible_by_its_idempotency_key(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        [$agent, $client] = $this->makeAgentAndClient($branch);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $this->actingAs($admin)->post(route('credits.topup'), [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 15,
        ])->assertRedirect();

        $key = PaymentIdempotencyKey::forManualClientCreditTopup($client->id, $agent->id, 15.0);
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->firstOrFail();

        $reversal = app(PostingService::class)->reverse($posted, now(), $admin->id);

        $this->assertNotSame($posted->id, $reversal->transaction->id);
        $clientAdvance = app(\App\Services\Accounting\AccountResolver::class)->resolve('CLIENT_ADVANCE', $company->id);
        $net = (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('debit')
            - (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('credit');
        $this->assertEqualsWithDelta(0.0, $net, 0.0005, 'Post + reverse must net CLIENT_ADVANCE back to zero.');
    }
}
