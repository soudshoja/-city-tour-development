<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Role;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PeriodCloseService;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ReconciliationService;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F): "Tests: every writer path produces a row with correct
 * before/after." One test per named writer path: (a) the engine (PostingService::post/reverse),
 * and (b) every gated mutation the brief names explicitly beyond the generic Gate::authorize sweep
 * (period close/reopen, unlock, reconcile/unreconcile, refund approve/reject, company option
 * changes). Writer (c) — the 15 accounting.* mirrored events — is pinned separately and more
 * exhaustively at the unit level in AccountingLogTest; this file only needs to prove the mechanism
 * end-to-end once (see test_the_15_mirrored_events_reach_the_table_end_to_end below).
 */
class AccountingAuditLogWritersTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeCompany(): Company
    {
        $company = tap(Company::factory()->create(), fn (Company $c) => $c->forceFill(['posting_engine_enabled' => true])->save());
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        return $company;
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create(['company_id' => $company->id, 'user_id' => User::factory()->create()->id]);
    }

    private function balancedDraft(Company $company, Branch $branch, Account $debit, Account $credit, float $amount, string $docType = 'JV', ?string $key = null): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: $docType,
            subType: null,
            docDate: now(),
            narration: 'AccountingAuditLogWritersTest fixture',
            lines: [
                new LineDraft(purposeCode: '', accountId: $debit->id, side: 'debit', amount: $amount, currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0, transactionType: 'TEST_DEBIT'),
                new LineDraft(purposeCode: '', accountId: $credit->id, side: 'credit', amount: $amount, currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0, transactionType: 'TEST_CREDIT'),
            ],
            idempotencyKey: $key,
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (a) Engine: PostingService::post / reverse
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_post_writes_one_audit_row_per_new_document(): void
    {
        config(['accounting.engine.enabled' => true]);
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $debit = Account::factory()->create(['company_id' => $company->id]);
        $credit = Account::factory()->create(['company_id' => $company->id]);

        $posted = app(PostingService::class)->post(
            $this->balancedDraft($company, $branch, $debit, $credit, 50.000, 'JV', 'audit-post-'.uniqid()),
            userId: null,
        );

        $row = AccountingAuditLog::where('transaction_id', $posted->transaction->id)->where('action', 'post')->first();
        $this->assertNotNull($row, 'Expected a post() audit row.');
        $this->assertSame($company->id, $row->company_id);
        $this->assertSame('transaction', $row->subject_type);
        $this->assertSame((int) $posted->transaction->id, $row->subject_id);
        $this->assertSame('JV', $row->after['doc_type']);
        $this->assertNotNull($row->posting_period);
    }

    public function test_post_does_not_write_a_second_row_on_an_idempotent_retry(): void
    {
        config(['accounting.engine.enabled' => true]);
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $debit = Account::factory()->create(['company_id' => $company->id]);
        $credit = Account::factory()->create(['company_id' => $company->id]);
        $key = 'audit-idempotent-'.uniqid();

        $service = app(PostingService::class);
        $first = $service->post($this->balancedDraft($company, $branch, $debit, $credit, 20.000, 'JV', $key));
        $service->post($this->balancedDraft($company, $branch, $debit, $credit, 20.000, 'JV', $key));

        $this->assertSame(
            1,
            AccountingAuditLog::where('transaction_id', $first->transaction->id)->where('action', 'post')->count(),
            'A retried post() with the same idempotency key must not burn a second audit row.'
        );
    }

    public function test_reverse_writes_an_audit_row_naming_both_documents(): void
    {
        config(['accounting.engine.enabled' => true]);
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $debit = Account::factory()->create(['company_id' => $company->id]);
        $credit = Account::factory()->create(['company_id' => $company->id]);

        $service = app(PostingService::class);
        $posted = $service->post($this->balancedDraft($company, $branch, $debit, $credit, 80.000, 'INV', 'audit-reverse-'.uniqid()));
        $reversed = $service->reverse($posted->transaction, now(), null);

        $row = AccountingAuditLog::where('action', 'reverse')->where('subject_id', $posted->transaction->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('transaction', $row->subject_type);
        $this->assertSame((int) $reversed->transaction->id, $row->transaction_id);
        $this->assertSame('posted', $row->before['posting_status']);
        $this->assertSame('reversed', $row->after['posting_status']);
        $this->assertSame((int) $reversed->transaction->id, $row->after['reversal_transaction_id']);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) Period close / reopen
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_period_close_writes_a_row_with_before_and_after_status(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $result = app(PeriodCloseService::class)->close($company->id, 2026, 3, AccountingPeriod::STATUS_LOCKED, $admin->id);
        $this->assertTrue($result['applied']);

        $row = AccountingAuditLog::where('action', 'lock')->where('subject_type', 'accounting_period')->first();
        $this->assertNotNull($row);
        $this->assertSame($company->id, $row->company_id);
        $this->assertSame($result['period']->id, $row->subject_id);
        $this->assertSame('open', $row->before['status']);
        $this->assertSame('locked', $row->after['status']);
        $this->assertSame($admin->id, $row->actor_id);
        $this->assertSame('2026-03', $row->posting_period);
    }

    public function test_period_reopen_writes_a_row_carrying_the_reason(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $period = app(PeriodCloseService::class)->reopen($company->id, 2026, 3, $admin->id, 'owner-authorized correction');

        $row = AccountingAuditLog::where('action', 'reopen')->where('subject_id', $period->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('owner-authorized correction', $row->reason);
        $this->assertSame('locked', $row->before['status']);
        $this->assertSame('open', $row->after['status']);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) Unlock (Lockable trait)
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_unlock_writes_an_audit_row(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $transactionId = DB::table('transactions')->insertGetId([
            'entity_id' => $company->id, 'entity_type' => 'company', 'branch_id' => null,
            'company_id' => $company->id, 'transaction_type' => 'JV', 'amount' => 10,
            'description' => 'lock fixture', 'reference_type' => 'Invoice', 'reference_number' => 'LOCK-'.uniqid(),
            'transaction_date' => now(), 'doc_type' => 'JV', 'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted', 'total_debit' => 10, 'total_credit' => 10,
            'is_locked' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $transaction = Transaction::withoutGlobalScopes()->findOrFail($transactionId);
        $transaction->unlock('data-entry correction, caught before any consumption', $admin->id);

        $row = AccountingAuditLog::where('action', 'unlock')->where('subject_id', $transactionId)->first();
        $this->assertNotNull($row);
        $this->assertSame('transaction', $row->subject_type);
        $this->assertSame($admin->id, $row->actor_id);
        $this->assertSame('data-entry correction, caught before any consumption', $row->reason);
        $this->assertTrue($row->before['is_locked']);
        $this->assertFalse($row->after['is_locked']);
    }

    public function test_blocked_unlock_writes_an_audit_row_with_the_blockers(): void
    {
        // Deliberately NOT $this->makeCompany() / trackCompanyForInvariants(): this fixture
        // creates a synthetic single-sided journal_entries row purely to exercise
        // Transaction::unlockBlockers()'s reconciled-line signal, not a real balanced document —
        // running the suite's C1 trial-balance invariant against it would fail for a reason
        // unrelated to what this test actually pins.
        $company = Company::factory()->create();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $account = Account::factory()->create(['company_id' => $company->id]);

        $transactionId = DB::table('transactions')->insertGetId([
            'entity_id' => $company->id, 'entity_type' => 'company', 'branch_id' => null,
            'company_id' => $company->id, 'transaction_type' => 'JV', 'amount' => 10,
            'description' => 'blocked-lock fixture', 'reference_type' => 'Invoice', 'reference_number' => 'BLOCK-'.uniqid(),
            'transaction_date' => now(), 'doc_type' => 'JV', 'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted', 'total_debit' => 10, 'total_credit' => 10,
            'is_locked' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // A real, unmocked blocker: Transaction::unlockBlockers() (P2.5.E) reports any of its own
        // journal lines that are bank-reconciled — a reconciled != 0 line is exactly that signal.
        DB::table('journal_entries')->insert([
            'name' => $account->name, 'transaction_id' => $transactionId, 'company_id' => $company->id,
            'account_id' => $account->id, 'branch_id' => null, 'transaction_date' => now(),
            'description' => 'reconciled leg', 'debit' => 10, 'credit' => 0,
            'voucher_number' => 'BLOCK-'.uniqid(), 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => 10, 'reconciled' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $transaction = Transaction::withoutGlobalScopes()->findOrFail($transactionId);

        try {
            $transaction->unlock('trying anyway', $admin->id);
            $this->fail('Expected UnlockDependencyBlockedException.');
        } catch (\App\Exceptions\Accounting\UnlockDependencyBlockedException) {
            // expected
        }

        $row = AccountingAuditLog::where('action', 'unlock_blocked')->where('subject_id', $transactionId)->first();
        $this->assertNotNull($row);
        $this->assertSame('trying anyway', $row->reason);
        $this->assertNotEmpty($row->after['blockers']);
        $this->assertSame('reconciled_line', $row->after['blockers'][0]['type']);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) Reconcile / unreconcile
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_reconcile_writes_an_audit_row(): void
    {
        // Deliberately NOT $this->makeCompany(): a synthetic unattached (transaction_id IS NULL)
        // journal_entries row, purely to exercise ReconciliationService::reconcile()'s own write —
        // not a real posted document, so it must not be run through the suite's C1
        // orphaned-journal-entries invariant.
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $account = Account::factory()->create(['company_id' => $company->id]);

        $lineId = DB::table('journal_entries')->insertGetId([
            'name' => $account->name, 'transaction_id' => null, 'company_id' => $company->id,
            'account_id' => $account->id, 'branch_id' => $branch->id, 'transaction_date' => now(),
            'description' => 'reconcile fixture', 'debit' => 5, 'credit' => 0,
            'voucher_number' => 'REC-'.uniqid(), 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => 5, 'reconciled' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(ReconciliationService::class)->reconcile($company->id, $branch->id, [$lineId], 999);

        $row = AccountingAuditLog::where('action', 'reconcile')->where('subject_id', 999)->first();
        $this->assertNotNull($row);
        $this->assertContains($lineId, $row->after['journal_entry_ids']);
    }

    public function test_decline_reconcile_writes_an_audit_row(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $account = Account::factory()->create(['company_id' => $company->id]);

        $lineId = DB::table('journal_entries')->insertGetId([
            'name' => $account->name, 'transaction_id' => null, 'company_id' => $company->id,
            'account_id' => $account->id, 'branch_id' => $branch->id, 'transaction_date' => now(),
            'description' => 'unreconcile fixture', 'debit' => 5, 'credit' => 0,
            'voucher_number' => 'UNREC-'.uniqid(), 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => 5, 'reconciled' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(ReconciliationService::class)->declineReconcile($lineId);

        $row = AccountingAuditLog::where('action', 'unreconcile')->where('subject_id', $lineId)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row->before['reconciled']);
        $this->assertSame(0, $row->after['reconciled']);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) Refund approve / reject (also (c) — see AccountingLog's own docblock)
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function makeRefundFixture(Company $company, Branch $branch): Refund
    {
        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);
        $invoiceDetail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $refund = Refund::create([
            'refund_number' => 'REF-AUDIT-'.uniqid(), 'company_id' => $company->id, 'branch_id' => $branch->id,
            'agent_id' => $agent->id, 'invoice_id' => $invoice->id, 'method' => 'Credit',
            'status' => Refund::STATUS_DRAFT, 'refund_date' => now(), 'total_refund_amount' => 100,
            'total_refund_charge' => 0, 'total_nett_refund' => 100,
        ]);
        RefundDetail::create([
            'refund_id' => $refund->id, 'task_id' => $task->id, 'client_id' => $client->id,
            'original_invoice_price' => 100.000, 'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0, 'supplier_charge' => 0, 'total_refund_to_client' => 100.000,
        ]);

        return $refund;
    }

    public function test_refund_approve_writes_an_audit_row_with_before_after_status(): void
    {
        $company = $this->makeCompany();
        CoaSeeder::run($company->id);
        $branch = $this->makeBranch($company);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $refund = $this->makeRefundFixture($company, $branch);

        $this->actingAs($admin)->post(route('refunds.approve', $refund->id))->assertRedirect();

        $row = AccountingAuditLog::where('action', 'approve')->where('subject_type', 'refund')->where('subject_id', $refund->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(Refund::STATUS_DRAFT, $row->before['status']);
        $this->assertSame(Refund::STATUS_APPROVED, $row->after['status']);
        $this->assertSame($admin->id, $row->actor_id);
    }

    public function test_refund_reject_writes_an_audit_row(): void
    {
        $company = $this->makeCompany();
        CoaSeeder::run($company->id);
        $branch = $this->makeBranch($company);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $refund = $this->makeRefundFixture($company, $branch);

        $this->actingAs($admin)->post(route('refunds.reject', $refund->id))->assertRedirect();

        $row = AccountingAuditLog::where('action', 'reject')->where('subject_id', $refund->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(Refund::STATUS_REJECTED, $row->after['status']);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (c) Mirrored accounting.* events — end-to-end proof via a real feeder
    // ────────────────────────────────────────────────────────────────────────────────────────

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) Company option changes (SettingController::storeAccountingSettings)
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_saving_accounting_settings_writes_an_option_change_audit_row(): void
    {
        $company = $this->makeCompany();
        CoaSeeder::run($company->id);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), [
            'invoice_overpay_cancel_policy' => 'refund_out',
            'unclaimed_writeback_months' => 9,
            'refund_send_on_post' => false,
            'agent_unearn_notice' => false,
        ])->assertOk();

        $row = AccountingAuditLog::where('action', 'option_change')->where('company_id', $company->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('company_setting', $row->subject_type);
        $this->assertSame($admin->id, $row->actor_id);
        $this->assertSame('refund_out', $row->after['accounting.refund.invoice_overpay_cancel_policy']);
    }

    public function test_a_mirrored_event_reaches_the_table_end_to_end_via_period_locked_override(): void
    {
        config(['accounting.engine.enabled' => true]);
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $debit = Account::factory()->create(['company_id' => $company->id]);
        $credit = Account::factory()->create(['company_id' => $company->id]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => now()->year, 'month' => now()->month, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $draft = $this->balancedDraft($company, $branch, $debit, $credit, 30.000, 'OJV', 'audit-yec-'.uniqid());
        $draft = new DocumentDraft(
            companyId: $draft->companyId, branchId: $draft->branchId, docType: $draft->docType, subType: $draft->subType,
            docDate: $draft->docDate, narration: $draft->narration, lines: $draft->lines,
            idempotencyKey: $draft->idempotencyKey, allowLockedPeriods: true,
        );

        app(PostingService::class)->post($draft);

        $row = AccountingAuditLog::where('action', 'period_locked_override')->where('company_id', $company->id)->first();
        $this->assertNotNull($row, 'Expected the mirrored period_locked_override event to reach accounting_audit_log.');
    }

    /**
     * Regression test for a verified gap (2026-08-30): `accounting.legacy_path`
     * ({@see PostingSeam::post()}'s engine-OFF branch) was one of the 15 accounting.* events
     * {@see \App\Services\Accounting\AccountingLog}'s own class docblock already claimed were
     * mirrored into this table, but no write()/event() call for it actually existed anywhere —
     * a document posted via the legacy path left NO Log Center row at all, unlike every engine-ON
     * post()/reverse(). Fixed at the PostingSeam::post() legacy-branch call site; this pins it.
     */
    public function test_legacy_path_writes_an_audit_row(): void
    {
        config(['accounting.engine.enabled' => false]);
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $debit = Account::factory()->create(['company_id' => $company->id]);
        $credit = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->balancedDraft($company, $branch, $debit, $credit, 15.000, 'JV', 'audit-legacy-'.uniqid());

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.legacy-audit-row');

        $this->assertTrue($legacyCalled);
        $this->assertSame('legacy-ran', $result);

        $row = AccountingAuditLog::where('action', 'legacy_path')->where('company_id', $company->id)->first();
        $this->assertNotNull($row, 'Expected a legacy_path audit row — this is the fixed gap.');
        $this->assertSame('transaction', $row->subject_type);
        $this->assertSame('test.feeder.legacy-audit-row', $row->after['feeder']);
        $this->assertSame($draft->idempotencyKey, $row->after['idempotency_key']);
        $this->assertNotNull($row->posting_period);
    }
}
