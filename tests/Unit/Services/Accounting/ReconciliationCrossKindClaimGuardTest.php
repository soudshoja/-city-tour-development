<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
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
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * PHASE GATE pinning test (accounting-builds, cross-lane defect GATE-1).
 *
 * The phase merged two independently-built lanes that both feed the ONE reconciliation proposal
 * queue: lane D's gateway-payout detector (kind = gateway_settlement) and lane E's DOTW supplier
 * statement matcher (kind = supplier_statement) — with lane H's bank_statement kind arriving next.
 * Every kind shares one id space, one status machine, and one approval path
 * ({@see ReconciliationProposalService::approve()}), and `reconciliation_proposals` has no unique
 * index on `book_journal_entry_id`. Neither lane's own verification ladder could see the other, so
 * neither test suite ever staged the case these tests stage: TWO proposals of DIFFERENT kinds
 * standing against the SAME ledger line.
 *
 * Before the fix, approving the second one silently overwrote `reconciled_ref_id`,
 * `reconciled_date` and `reconciled_amount` written by the first — one ledger line settled by two
 * unrelated external documents, with only the later one traceable and an audit row that read as a
 * perfectly normal reconcile. These tests pin the refusal, in both directions, and pin that the
 * refusal did not cost the ordinary single-claim path anything.
 */
class ReconciliationCrossKindClaimGuardTest extends AccountingTestCase
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

    /**
     * A balanced two-line JV. The returned line is the one proposals will compete over; the
     * counter line keeps the company's trial balance balanced so AccountingTestCase's own
     * invariant sweep in tearDown() stays meaningful rather than failing for an unrelated reason.
     */
    private function writeClaimableLine(
        Company $company,
        Branch $branch,
        Account $target,
        Account $counter,
        float $amount,
        Carbon $date,
        int $reconciled = 0,
    ): JournalEntry {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Gate claim test',
            'reference_type' => 'Invoice', 'reference_number' => 'GATE-'.substr(uniqid(), -8),
            'name' => 'Gate claim test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('gate:'),
        ]);

        $line = JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $target->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Gate claim line', 'debit' => $amount, 'credit' => 0, 'name' => $target->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'GATE', 'type_reference_id' => $company->id, 'reconciled' => $reconciled,
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $counter->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Gate claim counter', 'debit' => 0, 'credit' => $amount, 'name' => $counter->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'GATE', 'type_reference_id' => $company->id,
        ]);

        return $line;
    }

    private function proposalOn(Company $company, Account $account, JournalEntry $book, string $kind, float $amount): ReconciliationProposal
    {
        return ReconciliationProposal::create([
            'company_id' => $company->id,
            'account_id' => $account->id,
            'source' => 'external',
            'kind' => $kind,
            'confidence' => ReconciliationProposal::CONFIDENCE_EXACT,
            'book_journal_entry_id' => $book->id,
            'matched_journal_entry_id' => null,
            'amount' => $amount,
            'difference_amount' => 0,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);
    }

    public function test_a_supplier_statement_proposal_cannot_claim_a_line_already_claimed_by_a_gateway_settlement(): void
    {
        [$company, $branch] = $this->makeCompany();
        $actor = $this->admin();
        $date = Carbon::create(2026, 6, 15);

        $target = $this->accountByCode($company->id, '1201');
        $counter = $this->accountByCode($company->id, '4110');
        $book = $this->writeClaimableLine($company, $branch, $target, $counter, 250.000, $date);

        $gateway = $this->proposalOn($company, $target, $book, ReconciliationProposal::KIND_GATEWAY_SETTLEMENT, 250.000);
        $this->service()->approve($gateway, $actor);

        $book->refresh();
        $this->assertSame(1, (int) $book->reconciled, 'The first (gateway) claim should have taken the line.');
        $firstRef = (int) $book->reconciled_ref_id;
        $firstAmount = (float) $book->reconciled_amount;
        $this->assertSame($gateway->id, $firstRef);

        $supplier = $this->proposalOn($company, $target, $book, ReconciliationProposal::KIND_SUPPLIER_STATEMENT, 250.000);

        try {
            $this->service()->approve($supplier, $actor);
            $this->fail('Approving a second-kind proposal on an already-claimed line must be refused, not silently applied.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already reconciled', $e->getMessage());
            $this->assertStringContainsString((string) $book->id, $e->getMessage());
        }

        // The refusal must leave the FIRST claim completely intact — the whole point of the guard
        // is that the winning claim stays traceable, not merely that an exception was raised.
        $book->refresh();
        $this->assertSame(1, (int) $book->reconciled);
        $this->assertSame($firstRef, (int) $book->reconciled_ref_id, 'The losing approval overwrote the winning claim reference.');
        $this->assertEqualsWithDelta($firstAmount, (float) $book->reconciled_amount, 0.0005);

        // ...and the refused proposal must stay pending, so it surfaces as an unresolved exception
        // rather than disappearing as "approved" with nothing to show for it.
        $supplier->refresh();
        $this->assertSame(ReconciliationProposal::STATUS_PENDING, $supplier->status);
    }

    public function test_the_guard_bites_in_the_other_direction_too(): void
    {
        [$company, $branch] = $this->makeCompany();
        $actor = $this->admin();
        $date = Carbon::create(2026, 6, 16);

        $target = $this->accountByCode($company->id, '1201');
        $counter = $this->accountByCode($company->id, '4110');
        $book = $this->writeClaimableLine($company, $branch, $target, $counter, 120.500, $date);

        $supplier = $this->proposalOn($company, $target, $book, ReconciliationProposal::KIND_SUPPLIER_STATEMENT, 120.500);
        $this->service()->approve($supplier, $actor);

        $gateway = $this->proposalOn($company, $target, $book, ReconciliationProposal::KIND_GATEWAY_SETTLEMENT, 120.500);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already reconciled/');
        $this->service()->approve($gateway, $actor);
    }

    /**
     * `reconciled = 2` is BankPaymentController's own fast-path marker for a line that was born
     * settled — ReconciliationService already refuses to touch one (see its docblock). Approval
     * used to flip it to 1 regardless, quietly demoting a line that was never a candidate.
     */
    public function test_a_line_born_settled_at_state_two_cannot_be_claimed_by_a_proposal(): void
    {
        [$company, $branch] = $this->makeCompany();
        $actor = $this->admin();
        $date = Carbon::create(2026, 6, 17);

        $target = $this->accountByCode($company->id, '1201');
        $counter = $this->accountByCode($company->id, '4110');
        $book = $this->writeClaimableLine($company, $branch, $target, $counter, 75.000, $date, reconciled: 2);

        $proposal = $this->proposalOn($company, $target, $book, ReconciliationProposal::KIND_GATEWAY_SETTLEMENT, 75.000);

        try {
            $this->service()->approve($proposal, $actor);
            $this->fail('A line already settled at reconciled = 2 must not be claimable.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already reconciled', $e->getMessage());
        }

        $book->refresh();
        $this->assertSame(2, (int) $book->reconciled, 'The fast-path marker was demoted by a refused approval.');
    }

    /**
     * The guard must not have cost the ordinary path anything: a single proposal against a clean,
     * unclaimed line still approves and still locks the line. Without this, the two refusal tests
     * above would pass just as happily against an approve() that refused everything.
     */
    public function test_a_single_claim_on_a_clean_line_still_approves_normally(): void
    {
        [$company, $branch] = $this->makeCompany();
        $actor = $this->admin();
        $date = Carbon::create(2026, 6, 18);

        $target = $this->accountByCode($company->id, '1201');
        $counter = $this->accountByCode($company->id, '4110');
        $book = $this->writeClaimableLine($company, $branch, $target, $counter, 330.250, $date);

        $proposal = $this->proposalOn($company, $target, $book, ReconciliationProposal::KIND_GATEWAY_SETTLEMENT, 330.250);
        $approved = $this->service()->approve($proposal, $actor);

        $this->assertSame(ReconciliationProposal::STATUS_APPROVED, $approved->status);

        $book->refresh();
        $this->assertSame(1, (int) $book->reconciled);
        $this->assertSame($proposal->id, (int) $book->reconciled_ref_id);
        $this->assertEqualsWithDelta(330.250, (float) $book->reconciled_amount, 0.0005);
    }
}
