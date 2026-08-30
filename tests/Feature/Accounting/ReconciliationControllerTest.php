<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReconciliationFixDraft;
use App\Models\ReconciliationProposal;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "HTTP tests drive the screen" + "403 paths" — the
 * Reconciliation Center's HTTP surface ({@see \App\Http\Controllers\Accounting\ReconciliationController}
 * + {@see \App\Policies\ReconciliationProposalPolicy}), mirroring
 * {@see \Tests\Feature\Accounting\PeriodControllerTest}'s own fixture conventions.
 */
class ReconciliationControllerTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeCompanyAndAdmin(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $admin];
    }

    /** Same convention {@see PeriodControllerTest::makeAgentInCompany()} already established. */
    private function makeAgentInCompany(Company $company): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function writeBankLine(Company $company, Branch $branch, Account $bank, Account $counter, float $amount, Carbon $date): JournalEntry
    {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Test',
            'reference_type' => 'Invoice', 'reference_number' => 'RCT-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:'),
        ]);

        $line = JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bank->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => $amount, 'credit' => 0, 'name' => $bank->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RCT', 'type_reference_id' => $company->id, 'reconciled' => 0,
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $counter->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => 0, 'credit' => $amount, 'name' => $counter->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RCT', 'type_reference_id' => $company->id,
        ]);

        return $line;
    }

    // ── 403 paths ───────────────────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $response = $this->get(route('accounting.reconciliation.index', ['company_id' => $company->id]));

        $response->assertRedirect(route('login'));
    }

    public function test_index_403s_for_an_unauthorized_agent(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->get(route('accounting.reconciliation.index'));

        $response->assertStatus(403);
    }

    public function test_grid_403s_for_an_unauthorized_agent(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->getJson(route('accounting.reconciliation.grid', ['company_id' => $company->id]));

        $response->assertStatus(403);
    }

    public function test_approve_proposal_403s_for_an_unauthorized_agent(): void
    {
        [$company, $branch] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 20.000, Carbon::create(2026, 3, 5));
        $proposal = ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id, 'source' => 'internal',
            'kind' => 'manual', 'confidence' => 'manual', 'book_journal_entry_id' => $line->id,
            'amount' => 20.000, 'status' => 'pending',
        ]);

        $response = $this->actingAs($agent)->postJson(route('accounting.reconciliation.proposals.approve', $proposal->id));

        $response->assertStatus(403);
        $this->assertSame(0, $line->fresh()->reconciled);
    }

    // ── Index / grid ────────────────────────────────────────────────────────────────────────────

    public function test_index_renders_for_an_authorized_admin(): void
    {
        [, , $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->get(route('accounting.reconciliation.index'));

        $response->assertOk();
    }

    public function test_grid_returns_every_account_group_for_an_authorized_admin(): void
    {
        [$company, , $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->getJson(route('accounting.reconciliation.grid', ['company_id' => $company->id, 'date' => '2026-03-15']));

        $response->assertOk()->assertJsonPath('success', true);
        $groups = collect($response->json('grid.rows'))->pluck('group')->unique()->values()->all();
        $this->assertContains('bank_cash', $groups);
        $this->assertContains('control', $groups);
        $this->assertContains('clearing', $groups);
        $this->assertContains('review_only', $groups);
    }

    // ── Approve / reject ────────────────────────────────────────────────────────────────────────

    public function test_approve_proposal_route_locks_the_line(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 20.000, Carbon::create(2026, 3, 5));
        $proposal = ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id, 'source' => 'internal',
            'kind' => 'manual', 'confidence' => 'manual', 'book_journal_entry_id' => $line->id,
            'amount' => 20.000, 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.proposals.approve', $proposal->id));

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, $line->fresh()->reconciled);
    }

    public function test_reject_proposal_route_requires_a_reason(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 20.000, Carbon::create(2026, 3, 5));
        $proposal = ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id, 'source' => 'internal',
            'kind' => 'manual', 'confidence' => 'manual', 'book_journal_entry_id' => $line->id,
            'amount' => 20.000, 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.proposals.reject', $proposal->id), []);

        $response->assertStatus(422);
        $this->assertSame('pending', $proposal->fresh()->status);
    }

    public function test_reject_proposal_route_with_reason_succeeds(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 20.000, Carbon::create(2026, 3, 5));
        $proposal = ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id, 'source' => 'internal',
            'kind' => 'manual', 'confidence' => 'manual', 'book_journal_entry_id' => $line->id,
            'amount' => 20.000, 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.proposals.reject', $proposal->id), ['reason' => 'wrong candidate']);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame(0, $line->fresh()->reconciled);
    }

    // ── Fix-now ─────────────────────────────────────────────────────────────────────────────────

    public function test_create_fix_draft_route_never_posts(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.fix-drafts.create'), [
            'account_id' => $bank->id,
            'branch_id' => $branch->id,
            'kind' => 'bank_charge_pv',
            'amount' => 3.250,
            'narration' => 'Unrecorded bank fee',
        ]);

        $response->assertCreated()->assertJsonPath('success', true)->assertJsonPath('fix_draft.status', 'draft');
        $this->assertDatabaseHas('reconciliation_fix_drafts', ['id' => $response->json('fix_draft.id'), 'status' => 'draft']);
    }

    public function test_post_fix_draft_route_turns_the_draft_into_a_posted_document(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');

        // post() actually calls PostingService, unlike every other action in this controller — the
        // engine must be ON for this company, same convention ReconciliationFixDraftServiceTest's
        // own makeCompany() already establishes.
        config(['accounting.engine.enabled' => true]);
        \Illuminate\Support\Facades\Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $create = $this->actingAs($admin)->postJson(route('accounting.reconciliation.fix-drafts.create'), [
            'account_id' => $bank->id,
            'branch_id' => $branch->id,
            'kind' => 'bank_charge_pv',
            'amount' => 2.500,
            'narration' => 'Unrecorded bank fee',
        ]);
        $draftId = $create->json('fix_draft.id');

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.fix-drafts.post', $draftId));

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('fix_draft.status', 'posted');
        $this->assertDatabaseHas('reconciliation_fix_drafts', ['id' => $draftId, 'status' => 'posted']);
    }

    public function test_discard_fix_draft_route_requires_a_reason(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');

        $create = $this->actingAs($admin)->postJson(route('accounting.reconciliation.fix-drafts.create'), [
            'account_id' => $bank->id,
            'branch_id' => $branch->id,
            'kind' => 'bank_charge_pv',
            'amount' => 2.500,
            'narration' => 'Unrecorded bank fee',
        ]);
        $draftId = $create->json('fix_draft.id');

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.fix-drafts.discard', $draftId), []);

        $response->assertStatus(422);
        $this->assertDatabaseHas('reconciliation_fix_drafts', ['id' => $draftId, 'status' => 'draft']);
    }

    public function test_discard_fix_draft_route_with_reason_succeeds(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');

        $create = $this->actingAs($admin)->postJson(route('accounting.reconciliation.fix-drafts.create'), [
            'account_id' => $bank->id,
            'branch_id' => $branch->id,
            'kind' => 'bank_charge_pv',
            'amount' => 2.500,
            'narration' => 'Unrecorded bank fee',
        ]);
        $draftId = $create->json('fix_draft.id');

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.fix-drafts.discard', $draftId), ['reason' => 'wrong amount']);

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('fix_draft.status', 'discarded');
    }

    // ── Manual match / unmatch ──────────────────────────────────────────────────────────────────

    public function test_manual_match_route_locks_the_line_and_requires_a_reason(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 12.000, Carbon::create(2026, 3, 5));

        $missingReason = $this->actingAs($admin)->postJson(route('accounting.reconciliation.match'), [
            'account_id' => $bank->id,
            'journal_entry_id' => $line->id,
        ]);
        $missingReason->assertStatus(422);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.match'), [
            'account_id' => $bank->id,
            'journal_entry_id' => $line->id,
            'reason' => 'manual bank statement tie-out',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, $line->fresh()->reconciled);
    }

    public function test_manual_unmatch_route_reopens_the_line_when_the_period_is_open(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 8.000, Carbon::create(2026, 3, 5));

        $this->actingAs($admin)->postJson(route('accounting.reconciliation.match'), [
            'account_id' => $bank->id,
            'journal_entry_id' => $line->id,
            'reason' => 'matched by mistake',
        ]);
        $this->assertSame(1, $line->fresh()->reconciled);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.unmatch'), [
            'journal_entry_id' => $line->id,
            'reason' => 'wrong line matched',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(0, $line->fresh()->reconciled);
    }

    public function test_manual_unmatch_route_403s_for_an_unauthorized_agent(): void
    {
        [$company, $branch] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 8.000, Carbon::create(2026, 3, 5));

        $response = $this->actingAs($agent)->postJson(route('accounting.reconciliation.unmatch'), [
            'journal_entry_id' => $line->id,
            'reason' => 'should not be allowed',
        ]);

        $response->assertStatus(403);
    }

    // ── Row detail (drill-down: proposals + unmatched + history + gap_explanation) ─────────────────

    public function test_row_detail_route_returns_every_drill_down_panel(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $this->writeBankLine($company, $branch, $bank, $income, 33.000, Carbon::create(2026, 3, 5));

        $response = $this->actingAs($admin)->getJson(route('accounting.reconciliation.row-detail', 'bank:'.$bank->id).'?date=2026-03-15');

        $response->assertOk()->assertJsonPath('success', true);
        $response->assertJsonStructure(['row', 'proposals', 'recently_matched', 'unmatched' => ['items', 'buckets'], 'history', 'gap_explanation' => ['book', 'confirmed', 'gap', 'components', 'residual', 'exception', 'advice']]);
    }

    public function test_row_detail_route_lists_a_recently_approved_proposal_for_the_unmatch_action(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyAndAdmin();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 18.000, Carbon::create(2026, 3, 5));
        $proposal = ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id, 'source' => 'internal',
            'kind' => 'manual', 'confidence' => 'manual', 'book_journal_entry_id' => $line->id,
            'amount' => 18.000, 'status' => 'pending',
        ]);
        $this->actingAs($admin)->postJson(route('accounting.reconciliation.proposals.approve', $proposal->id));

        $response = $this->actingAs($admin)->getJson(route('accounting.reconciliation.row-detail', 'bank:'.$bank->id).'?date=2026-03-15');

        $response->assertOk();
        $matched = collect($response->json('recently_matched'));
        $this->assertTrue($matched->contains(fn (array $p) => (int) $p['id'] === $proposal->id && $p['status'] === 'approved'));
    }

    public function test_row_detail_route_returns_404_for_an_unknown_row_key(): void
    {
        [$company, , $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->getJson(route('accounting.reconciliation.row-detail', 'bank:999999').'?date=2026-03-15&company_id='.$company->id);

        $response->assertStatus(404)->assertJsonPath('success', false);
    }

    // ── Run-now / run-status ────────────────────────────────────────────────────────────────────

    public function test_run_now_route_queues_the_auto_match_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        [$company, , $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.run'), ['company_id' => $company->id]);

        $response->assertOk()->assertJsonPath('success', true);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RunReconciliationAutoMatchJob::class);
    }

    public function test_run_now_route_403s_for_an_unauthorized_agent(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        [$company] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->postJson(route('accounting.reconciliation.run'), ['company_id' => $company->id]);

        $response->assertStatus(403);
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\RunReconciliationAutoMatchJob::class);
    }

    public function test_run_status_route_returns_the_last_run_summary(): void
    {
        [$company, , $admin] = $this->makeCompanyAndAdmin();
        \App\Models\ReconciliationRun::create([
            'company_id' => $company->id, 'status' => 'completed', 'trigger' => 'nightly',
            'started_at' => now(), 'finished_at' => now(), 'proposals_created' => 4,
            'auto_matched_pending' => 2, 'exceptions_count' => 1, 'duration_ms' => 1000,
        ]);

        $response = $this->actingAs($admin)->getJson(route('accounting.reconciliation.run-status', ['company_id' => $company->id]));

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('run_status.last_nightly_run.proposals_created', 4);
    }
}
