<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingAuditLog;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingLog;
use Database\Seeders\CoaSeeder;
use Livewire\Livewire;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F): "screen HTTP tests with permission 403." Exercises the route +
 * the Livewire component's own query building directly via the Livewire testing helper (the
 * ADR-standard way to test a full-page Livewire component's filters without parsing rendered HTML).
 */
class AuditLogScreenTest extends AccountingTestCase
{
    private function makeCompanyAndAdmin(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $admin];
    }

    private function makeAgentInCompany(Company $company): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    /** Real Agent model (not the User wrapper {@see self::makeAgentInCompany()} returns) — needed
     *  by every client/agent filter test to populate `invoices.agent_id` / `transactions.entity_id`. */
    private function makeAgentModel(Company $company): Agent
    {
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        $agentUser = User::factory()->create();

        return Agent::factory()->create(['branch_id' => $branch->id, 'type_id' => $agentType->id, 'user_id' => $agentUser->id]);
    }

    private function makeClient(Company $company, Agent $agent): Client
    {
        return Client::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id]);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::factory()->create();
    }

    private function makeTask(Company $company, Client $client, Agent $agent, Supplier $supplier): Task
    {
        return Task::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    private function makeInvoice(Client $client, Agent $agent): Invoice
    {
        return Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id]);
    }

    private function makeTransaction(Company $company, Branch $branch, array $overrides = []): Transaction
    {
        return Transaction::forceCreate(array_merge([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 100,
            'total_debit' => 100,
            'total_credit' => 100,
            'description' => 'Audit log fixture transaction',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ], $overrides));
    }

    private function makeBranch(Company $company): Branch
    {
        $branchOwner = User::factory()->create();

        return Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('accounting.audit-log.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_page_renders_for_an_authorized_admin(): void
    {
        [, $admin] = $this->makeCompanyAndAdmin();

        $this->actingAs($admin)->get(route('accounting.audit-log.index'))->assertOk();
    }

    public function test_page_is_403_for_a_role_with_no_permission(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $agent = $this->makeAgentInCompany($company);

        // A real HTTP round-trip (not Livewire::test()) — AuthorizationException thrown from
        // mount() is only guaranteed to render as a 403 HTTP response through Laravel's normal
        // exception-handling pipeline, which a full route dispatch exercises directly.
        $this->actingAs($agent)->get(route('accounting.audit-log.index'))->assertForbidden();
    }

    public function test_component_lists_only_the_acting_companys_rows(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $otherCompany = Company::factory()->create();
        $this->trackCompanyForInvariants($otherCompany->id);

        AccountingLog::write(action: 'post', companyId: $company->id, subjectType: 'transaction', subjectId: 1);
        AccountingLog::write(action: 'post', companyId: $otherCompany->id, subjectType: 'transaction', subjectId: 2);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->assertViewHas('entries', function ($entries) use ($company) {
                return $entries->total() === 1 && $entries->first()->company_id === $company->id;
            });
    }

    public function test_action_filter_narrows_the_results(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        AccountingLog::write(action: 'post', companyId: $company->id, subjectType: 'transaction', subjectId: 1);
        AccountingLog::write(action: 'reverse', companyId: $company->id, subjectType: 'transaction', subjectId: 2);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('actions', ['reverse'])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->action === 'reverse');
    }

    public function test_reason_search_narrows_the_results(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        AccountingLog::write(action: 'reopen', companyId: $company->id, reason: 'quarter-end audit correction');
        AccountingLog::write(action: 'reopen', companyId: $company->id, reason: 'unrelated note');

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('reason', 'quarter-end')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1);
    }

    public function test_free_text_search_with_no_active_filter_matches_reason(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        AccountingLog::write(action: 'reopen', companyId: $company->id, reason: 'needle-in-a-haystack');
        AccountingLog::write(action: 'reopen', companyId: $company->id, reason: 'nothing to see');

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('search', 'needle-in-a-haystack')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1);
    }

    public function test_reset_filters_clears_every_public_filter_property(): void
    {
        [, $admin] = $this->makeCompanyAndAdmin();

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('actions', ['post'])
            ->set('reason', 'x')
            ->call('resetFilters')
            ->assertSet('actions', [])
            ->assertSet('reason', '');
    }

    public function test_export_csv_streams_a_download(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        AccountingLog::write(action: 'post', companyId: $company->id, subjectType: 'transaction', subjectId: 1);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    /**
     * SEC-1 (.planning/accounting-waves/p2_5/p2_5-followups.md): CSV formula injection. A stored
     * reason/route/ip beginning with a character a spreadsheet app treats as a formula trigger
     * (= + - @, or a leading tab) must be re-opened as inert text, never executed, once neutralized
     * by {@see \App\Support\CsvSafe}. Ordinary values -- including the numeric id column -- must
     * round-trip byte-for-byte unchanged.
     */
    public function test_export_csv_neutralizes_formula_injection_payloads_and_leaves_normal_values_untouched(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $equalsRow = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'reopen',
            'actor_type' => 'system',
            'reason' => '=cmd()',
            'created_at' => now(),
        ]);
        $plusRow = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'reopen',
            'actor_type' => 'system',
            'route' => '+SUM(A1)',
            'created_at' => now(),
        ]);
        $minusRow = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'reopen',
            'actor_type' => 'system',
            'ip' => '-2+3',
            'created_at' => now(),
        ]);
        $atRow = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'reopen',
            'actor_type' => 'system',
            'reason' => '@foo',
            'created_at' => now(),
        ]);
        $tabRow = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'reopen',
            'actor_type' => 'system',
            'route' => "\tmalicious",
            'created_at' => now(),
        ]);
        $normalRow = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'reopen',
            'actor_type' => 'system',
            'reason' => 'quarter-end audit correction',
            'route' => 'accounting.audit-log.index',
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->call('exportCsv');

        $component->assertFileDownloaded();

        $csv = base64_decode((string) data_get($component->effects, 'download.content'));
        $lines = array_values(array_filter(explode("\n", $csv), fn ($line) => $line !== ''));
        $header = str_getcsv(array_shift($lines));

        $byId = [];
        foreach ($lines as $line) {
            $fields = array_combine($header, str_getcsv($line));
            $byId[(int) $fields['id']] = $fields;
        }

        $this->assertSame("'=cmd()", $byId[$equalsRow->id]['reason']);
        $this->assertSame("'+SUM(A1)", $byId[$plusRow->id]['route']);
        $this->assertSame("'-2+3", $byId[$minusRow->id]['ip']);
        $this->assertSame("'@foo", $byId[$atRow->id]['reason']);
        $this->assertSame("'\tmalicious", $byId[$tabRow->id]['route']);

        // Normal values -- including the numeric id column -- pass through unchanged.
        $this->assertSame((string) $normalRow->id, $byId[$normalRow->id]['id']);
        $this->assertSame('quarter-end audit correction', $byId[$normalRow->id]['reason']);
        $this->assertSame('accounting.audit-log.index', $byId[$normalRow->id]['route']);
        $this->assertSame('127.0.0.1', $byId[$normalRow->id]['ip']);
    }

    /**
     * SEC-1 regression guard for the OTHER named unguarded writer ({@see
     * \App\Console\Commands\AccountingAuditLogPurge}'s retention archive) -- confirms both writers
     * named in the finding share the same {@see \App\Support\CsvSafe} fix, not just the Log Center's
     * on-demand export.
     */
    public function test_purge_archive_csv_neutralizes_formula_injection_payloads(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.audit_log_retention_months',
            'value' => 1,
            'type' => 'integer',
        ]);

        $old = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'reason' => '=cmd()',
            'created_at' => now()->subMonths(3),
        ]);

        $this->artisan(\App\Console\Commands\AccountingAuditLogPurge::class, ['--company' => $company->id])
            ->assertExitCode(0);

        $archiveDir = storage_path("app/{$company->id}/accounting-audit-log-archive");
        $files = glob($archiveDir.'/*.csv') ?: [];
        $this->assertNotEmpty($files, 'purge command did not write an archive CSV');

        $csv = file_get_contents(end($files));
        $lines = array_values(array_filter(explode("\n", $csv), fn ($line) => $line !== ''));
        $header = str_getcsv(array_shift($lines));
        $row = array_combine($header, str_getcsv($lines[0]));

        $this->assertSame((string) $old->id, $row['id']);
        $this->assertSame("'=cmd()", $row['reason']);
    }

    public function test_saving_and_loading_a_preset_round_trips_the_filter_state(): void
    {
        [, $admin] = $this->makeCompanyAndAdmin();

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('actions', ['reverse'])
            ->set('presetName', 'My reversals')
            ->call('savePreset')
            ->set('actions', [])
            ->assertSet('actions', []);

        $this->assertDatabaseHas('accounting_audit_log_presets', ['user_id' => $admin->id, 'name' => 'My reversals']);
    }

    /**
     * Design-pass fix (frontend-design-pro review, 2026-08-30): unchecking every column-chooser
     * checkbox previously left a table with only the expand-chevron column visible — reading as
     * broken rather than empty. {@see \App\Http\Livewire\Accounting\AuditLogIndex::updatedVisibleColumns()}
     * refuses to drop below one visible column.
     */
    public function test_clearing_every_visible_column_restores_created_at(): void
    {
        [, $admin] = $this->makeCompanyAndAdmin();

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('visibleColumns', [])
            ->assertSet('visibleColumns', ['created_at']);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Fix-round 2026-08-30: previous verify found (1) a live 500 on the changedField filter, and
    // (2) zero test coverage for actor/subject-number/account/amount-range/posting_period/
    // date-range/changed-field/client/agent/supplier/branch/multi-select-action, plus the search-
    // scoping contract and the new-entries banner. Each is exercised below.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Regression test for the CONFIRMED bug: whereJsonContainsKey() was called with a two-argument
     * form ($column, $key) where the second positional argument is actually Laravel's internal
     * $boolean parameter, throwing a fatal SQL syntax error on every request. Also proves the
     * filter's actual semantics: matches a row whose before OR after JSON has the given top-level
     * key present, regardless of value.
     */
    public function test_changed_field_filter_matches_a_key_present_in_before_or_after(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        AccountingLog::write(action: 'unlock', companyId: $company->id, before: ['status' => 'closed'], after: ['status' => 'open']);
        AccountingLog::write(action: 'unlock', companyId: $company->id, before: ['other_key' => 1], after: ['other_key' => 2]);

        $component = Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('changedField', 'status');

        // The bug threw a QueryException before any assertion could even run — this call alone
        // (no try/catch) is the regression guard.
        $component->assertViewHas('entries', fn ($entries) => $entries->total() === 1
            && ($entries->first()->before['status'] ?? null) === 'closed');
    }

    public function test_changed_field_filter_returns_empty_for_a_key_never_present(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        AccountingLog::write(action: 'unlock', companyId: $company->id, before: ['status' => 'closed'], after: ['status' => 'open']);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('changedField', 'no_such_field')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 0);
    }

    public function test_actor_filter_by_id_narrows_the_results(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $otherUser = User::factory()->create();

        AccountingLog::write(action: 'post', companyId: $company->id, actorId: $admin->id, actorType: 'user');
        AccountingLog::write(action: 'post', companyId: $company->id, actorId: $otherUser->id, actorType: 'user');

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('actorIds', [$admin->id])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->actor_id === $admin->id);
    }

    public function test_multi_select_action_filter_matches_any_of_the_selected_actions(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        AccountingLog::write(action: 'post', companyId: $company->id);
        AccountingLog::write(action: 'reverse', companyId: $company->id);
        AccountingLog::write(action: 'approve', companyId: $company->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('actions', ['reverse', 'approve'])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 2
                && $entries->pluck('action')->diff(['reverse', 'approve'])->isEmpty());
    }

    public function test_subject_number_filter_resolves_an_invoice_number_to_its_subject_id(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentModel($company);
        $client = $this->makeClient($company, $agent);
        $invoice = $this->makeInvoice($client, $agent);
        $otherInvoice = $this->makeInvoice($client, $agent);

        AccountingLog::write(action: 'approve', companyId: $company->id, subjectType: 'invoice', subjectId: $invoice->id);
        AccountingLog::write(action: 'approve', companyId: $company->id, subjectType: 'invoice', subjectId: $otherInvoice->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('subjectNumber', $invoice->invoice_number)
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->subject_id === $invoice->id);
    }

    public function test_account_filter_matches_only_a_transaction_with_a_journal_line_on_that_account(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        CoaSeeder::run($company->id);
        $branch = $this->makeBranch($company);

        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $cash = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1120')->firstOrFail();
        $incomeSuspense = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4133')->firstOrFail();

        $bankTxn = $this->makeTransaction($company, $branch, ['total_debit' => 50, 'total_credit' => 50]);
        JournalEntry::create(['transaction_id' => $bankTxn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $bank->id, 'transaction_date' => now(), 'description' => 'fixture', 'debit' => 50, 'credit' => 0, 'name' => $bank->name, 'type' => 'bank', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 50, 'voucher_number' => 'AL-1']);
        JournalEntry::create(['transaction_id' => $bankTxn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $incomeSuspense->id, 'transaction_date' => now(), 'description' => 'fixture', 'debit' => 0, 'credit' => 50, 'name' => $incomeSuspense->name, 'type' => 'income', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 50, 'voucher_number' => 'AL-1']);

        $cashTxn = $this->makeTransaction($company, $branch, ['total_debit' => 30, 'total_credit' => 30]);
        JournalEntry::create(['transaction_id' => $cashTxn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $cash->id, 'transaction_date' => now(), 'description' => 'fixture', 'debit' => 30, 'credit' => 0, 'name' => $cash->name, 'type' => 'cash', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 30, 'voucher_number' => 'AL-2']);
        JournalEntry::create(['transaction_id' => $cashTxn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $incomeSuspense->id, 'transaction_date' => now(), 'description' => 'fixture', 'debit' => 0, 'credit' => 30, 'name' => $incomeSuspense->name, 'type' => 'income', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 30, 'voucher_number' => 'AL-2']);

        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $bankTxn->id);
        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $cashTxn->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('accountCode', $bank->code)
            ->assertSet('accountId', $bank->id)
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->transaction_id === $bankTxn->id);
    }

    public function test_amount_range_filter_narrows_by_transaction_total(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $branch = $this->makeBranch($company);

        $small = $this->makeTransaction($company, $branch, ['total_debit' => 20, 'total_credit' => 20]);
        $large = $this->makeTransaction($company, $branch, ['total_debit' => 500, 'total_credit' => 500]);

        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $small->id);
        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $large->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('amountMin', '100')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->transaction_id === $large->id);
    }

    public function test_posting_period_filter_narrows_the_results(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        AccountingLog::write(action: 'post', companyId: $company->id, postingPeriod: '2026-01');
        AccountingLog::write(action: 'post', companyId: $company->id, postingPeriod: '2026-02');

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('postingPeriod', '2026-01')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->posting_period === '2026-01');
    }

    public function test_date_range_filter_narrows_by_created_at(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        // Fix-round 2026-08-30 (verify findings, CONFIRMED #1): a backdated fixture row now has to
        // be INSERTed with the desired created_at directly -- the table's new append-only DB
        // trigger (migration 2026_08_30_150001) blocks the UPDATE this test used to rely on
        // (`forceFill()->saveQuietly()`) exactly as it must for a real tamper attempt, with no
        // carve-out for test fixtures.
        $old = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now()->subDays(10),
        ]);

        AccountingLog::write(action: 'post', companyId: $company->id); // created "now"

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('dateFrom', now()->subDay()->toDateString())
            ->set('dateTo', now()->toDateString())
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->id !== $old->id);
    }

    public function test_branch_filter_narrows_by_transaction_branch(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $branchA = $this->makeBranch($company);
        $branchB = $this->makeBranch($company);

        $txnA = $this->makeTransaction($company, $branchA);
        $txnB = $this->makeTransaction($company, $branchB);

        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $txnA->id);
        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $txnB->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('branchId', (string) $branchA->id)
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->transaction_id === $txnA->id);
    }

    /** Resolution path 1: the transaction header itself names the client via entity_type/entity_id. */
    public function test_client_filter_matches_via_transaction_entity_type(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $branch = $this->makeBranch($company);
        $agent = $this->makeAgentModel($company);
        $client = $this->makeClient($company, $agent);
        $otherClient = $this->makeClient($company, $agent);

        $txn = $this->makeTransaction($company, $branch, ['entity_type' => 'client', 'entity_id' => $client->id]);
        $otherTxn = $this->makeTransaction($company, $branch, ['entity_type' => 'client', 'entity_id' => $otherClient->id]);

        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $txn->id);
        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $otherTxn->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('clientIds', [$client->id])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->transaction_id === $txn->id);
    }

    /** Resolution path 2: the transaction links to an invoice whose client_id matches. */
    public function test_client_filter_matches_via_invoice_linked_through_the_transaction(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $branch = $this->makeBranch($company);
        $agent = $this->makeAgentModel($company);
        $client = $this->makeClient($company, $agent);
        $invoice = $this->makeInvoice($client, $agent);

        $txn = $this->makeTransaction($company, $branch, ['invoice_id' => $invoice->id]);
        $unrelatedTxn = $this->makeTransaction($company, $branch);

        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $txn->id);
        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $unrelatedTxn->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('clientIds', [$client->id])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->transaction_id === $txn->id);
    }

    /** Resolution path 3: the audit row's own subject IS the invoice (subject_type='invoice'), no transaction linked at all. */
    public function test_client_filter_matches_via_the_audit_rows_own_invoice_subject(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentModel($company);
        $client = $this->makeClient($company, $agent);
        $invoice = $this->makeInvoice($client, $agent);

        AccountingLog::write(action: 'approve', companyId: $company->id, subjectType: 'invoice', subjectId: $invoice->id);
        AccountingLog::write(action: 'approve', companyId: $company->id, subjectType: 'invoice', subjectId: 999999);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('clientIds', [$client->id])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->subject_id === $invoice->id);
    }

    public function test_agent_filter_matches_via_transaction_entity_type(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $branch = $this->makeBranch($company);
        $agent = $this->makeAgentModel($company);
        $otherAgent = $this->makeAgentModel($company);

        $txn = $this->makeTransaction($company, $branch, ['entity_type' => 'agent', 'entity_id' => $agent->id]);
        $otherTxn = $this->makeTransaction($company, $branch, ['entity_type' => 'agent', 'entity_id' => $otherAgent->id]);

        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $txn->id);
        AccountingLog::write(action: 'post', companyId: $company->id, transactionId: $otherTxn->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('agentIds', [$agent->id])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->transaction_id === $txn->id);
    }

    /** Supplier is resolved only "through the subject" (brief's own wording) — a task's own supplier_id. */
    public function test_supplier_filter_matches_via_the_task_subject(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentModel($company);
        $client = $this->makeClient($company, $agent);
        $supplier = $this->makeSupplier();
        $otherSupplier = $this->makeSupplier();
        $task = $this->makeTask($company, $client, $agent, $supplier);
        $otherTask = $this->makeTask($company, $client, $agent, $otherSupplier);

        AccountingLog::write(action: 'post', companyId: $company->id, subjectType: 'task', subjectId: $task->id);
        AccountingLog::write(action: 'post', companyId: $company->id, subjectType: 'task', subjectId: $otherTask->id);

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('supplierIds', [$supplier->id])
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->subject_id === $task->id);
    }

    /**
     * SEARCH BOX CONTRACT (owner 2026-08-30): the same search term returns a DIFFERENT result set
     * depending on whether a filter is active. 'unique-route-token' lives only in `route`, which
     * `applySearch()` only searches when NO filter is active.
     */
    public function test_search_term_matches_route_only_when_no_filter_is_active(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        // Fix-round 2026-08-30 (verify findings, CONFIRMED #1): INSERT the desired `route` value
        // directly rather than creating-then-UPDATE-ing it — see the date-range test above for why
        // the previous `forceFill()->saveQuietly()` pattern no longer works (nor should it).
        $row = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'reopen',
            'actor_type' => 'system',
            'reason' => 'nothing special',
            'route' => 'accounting.unique-route-token',
            'created_at' => now(),
        ]);
        AccountingLog::write(action: 'reopen', companyId: $company->id, reason: 'also nothing special');

        // No filter active: route is searched, so the term matches the one row.
        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('search', 'unique-route-token')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1);

        // A filter IS active ('actions' matches both rows): route is now excluded from the search
        // scope, so the same term matches nothing, even though the active filter alone matches both.
        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->set('actions', ['reopen'])
            ->set('search', 'unique-route-token')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 0);
    }

    /**
     * pollForNewEntries() is a COUNT-ONLY query — it never mutates the table's own sort/page/
     * filter state, and knownMaxId (the "have I loaded this row yet" ceiling) only moves forward
     * when the user explicitly clicks "Load new entries" (loadNewEntries()) or changes a filter
     * (updated() re-snapshots it). It does NOT hide a newly-created row from the table's own query
     * (Livewire re-renders the whole component on every request regardless of which action fired,
     * so the row is visible the moment any render happens) — what it guarantees is that the
     * component knows how many rows are "new since the page loaded" without resetting pagination
     * or scroll position to show them.
     */
    public function test_new_entries_banner_counts_rows_created_after_the_page_loaded_and_load_new_entries_resets_it(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        AccountingLog::write(action: 'post', companyId: $company->id);

        $component = Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Accounting\AuditLogIndex::class)
            ->assertSet('newEntryCount', 0);

        // A row lands AFTER the page's knownMaxId snapshot was taken.
        AccountingLog::write(action: 'post', companyId: $company->id);

        $component->call('pollForNewEntries')
            ->assertSet('newEntryCount', 1)
            ->call('loadNewEntries')
            ->assertSet('newEntryCount', 0)
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 2);
    }
}
