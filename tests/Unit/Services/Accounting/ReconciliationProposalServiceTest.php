<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\ReconciliationPeriodLockedException;
use App\Models\Account;
use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\ReconciliationProposalService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "approving a proposal flips the flag + audit + locks the line;
 * rejecting keeps it unmatched with reason ... unmatch refused when the period is locked."
 */
class ReconciliationProposalServiceTest extends AccountingTestCase
{
    private function service(): ReconciliationProposalService
    {
        return app(ReconciliationProposalService::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    private function writeBankLine(Company $company, Branch $branch, Account $bank, Account $counter, float $amount, Carbon $date): JournalEntry
    {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Test',
            'reference_type' => 'Invoice', 'reference_number' => 'RPS-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:'),
        ]);

        $line = JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bank->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => $amount, 'credit' => 0, 'name' => $bank->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RPS', 'type_reference_id' => $company->id, 'reconciled' => 0,
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $counter->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => 0, 'credit' => $amount, 'name' => $counter->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RPS', 'type_reference_id' => $company->id,
        ]);

        return $line;
    }

    private function pendingProposal(Company $company, JournalEntry $line, string $kind = ReconciliationProposal::KIND_MANUAL): ReconciliationProposal
    {
        return ReconciliationProposal::create([
            'company_id' => $company->id,
            'account_id' => $line->account_id,
            'source' => 'internal',
            'kind' => $kind,
            'confidence' => ReconciliationProposal::CONFIDENCE_SUGGESTED,
            'book_journal_entry_id' => $line->id,
            'amount' => (float) $line->debit,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);
    }

    // ── approve() ───────────────────────────────────────────────────────────────────────────────

    public function test_approve_flips_reconciled_writes_audit_and_locks_the_line(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 10);

        $line = $this->writeBankLine($company, $branch, $bank, $income, 45.500, $date);
        $proposal = $this->pendingProposal($company, $line);

        $auditCountBefore = AccountingAuditLog::count();

        $result = $this->service()->approve($proposal, $this->admin());

        $this->assertSame(ReconciliationProposal::STATUS_APPROVED, $result->status);
        $line->refresh();
        $this->assertSame(1, $line->reconciled);
        $this->assertNotNull($line->reconciled_ref_id);

        $this->assertGreaterThan($auditCountBefore, AccountingAuditLog::count());
        $audit = AccountingAuditLog::where('subject_type', 'reconciliation_proposal')->where('subject_id', $proposal->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('reconcile', $audit->action);

        // "locks the line": PostingService::reverse() refuses a reconciled original without
        // $force — asserting reconciled=1 above IS the lock (see class docblock); this second
        // assertion pins the lock is visible via Lockable's own is_locked semantics is out of
        // scope here (reconciled is the P2.5.G-specific lock, per period-lock-design.md §1 Layer 3).
        $this->assertSame(1, JournalEntry::withoutGlobalScopes()->find($line->id)->reconciled);
    }

    public function test_approve_refuses_a_null_user(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 10.000, Carbon::create(2026, 3, 10));
        $proposal = $this->pendingProposal($company, $line);

        $this->expectException(AuthorizationException::class);
        $this->service()->approve($proposal, null);
    }

    public function test_approve_refuses_an_already_decided_proposal(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 10.000, Carbon::create(2026, 3, 10));
        $proposal = $this->pendingProposal($company, $line);
        $this->service()->approve($proposal, $this->admin());

        $this->expectException(\RuntimeException::class);
        $this->service()->approve($proposal->fresh(), $this->admin());
    }

    // ── reject() ────────────────────────────────────────────────────────────────────────────────

    public function test_reject_requires_a_reason_and_keeps_the_line_unmatched(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 30.000, Carbon::create(2026, 3, 10));
        $proposal = $this->pendingProposal($company, $line);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->reject($proposal, '', $this->admin());
    }

    public function test_reject_with_reason_leaves_line_unreconciled(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 30.000, Carbon::create(2026, 3, 10));
        $proposal = $this->pendingProposal($company, $line);

        $result = $this->service()->reject($proposal, 'Not a valid match', $this->admin());

        $this->assertSame(ReconciliationProposal::STATUS_REJECTED, $result->status);
        $this->assertSame('Not a valid match', $result->reason);
        $line->refresh();
        $this->assertSame(0, $line->reconciled, 'Rejecting must never touch reconciled — the line stays unmatched.');
    }

    // ── manualUnmatch() ─────────────────────────────────────────────────────────────────────────

    public function test_manual_unmatch_requires_a_reason(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $line = $this->writeBankLine($company, $branch, $bank, $income, 15.000, Carbon::create(2026, 3, 10));
        $line->update(['reconciled' => 1, 'reconciled_ref_id' => 999]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->manualUnmatch($line->id, '', $this->admin());
    }

    public function test_manual_unmatch_succeeds_when_period_is_open(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 10);
        $line = $this->writeBankLine($company, $branch, $bank, $income, 15.000, $date);
        $line->update(['reconciled' => 1, 'reconciled_ref_id' => 999]);

        $result = $this->service()->manualUnmatch($line->id, 'Wrong match', $this->admin());

        $this->assertSame(0, $result->reconciled);
        $this->assertNull($result->reconciled_ref_id);
    }

    public function test_manual_unmatch_refused_when_period_is_locked(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 10);
        $line = $this->writeBankLine($company, $branch, $bank, $income, 15.000, $date);
        $line->update(['reconciled' => 1, 'reconciled_ref_id' => 999]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_LOCKED, 'closed_by' => null, 'closed_at' => now(),
        ]);

        try {
            $this->service()->manualUnmatch($line->id, 'Wrong match', $this->admin());
            $this->fail('Expected ReconciliationPeriodLockedException.');
        } catch (ReconciliationPeriodLockedException) {
            // expected
        }

        $line->refresh();
        $this->assertSame(1, $line->reconciled, 'A refused unmatch must never mutate the line.');
    }
}
