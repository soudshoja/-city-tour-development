<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\ReconciliationRun;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\ReconciliationAutoMatchService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §9): "the nightly job only PROPOSES ...
 * it never posts money." Exercises the clearing-rollforward (cheque-clearance) detector directly —
 * the only detector whose trigger condition (a passed `cheque_clearance_date`) is deterministic
 * and needs no multi-line fixture wiring to prove.
 */
class ReconciliationAutoMatchServiceTest extends AccountingTestCase
{
    private function service(): ReconciliationAutoMatchService
    {
        return app(ReconciliationAutoMatchService::class);
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

    public function test_run_never_posts_a_ledger_document_and_only_proposes(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1215'); // Cheques in Hand
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 75, 'description' => 'Cheque received',
            'reference_type' => 'Invoice', 'reference_number' => 'RAM-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => 75, 'total_credit' => 75, 'idempotency_key' => uniqid('key:'),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bank->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Cheque', 'debit' => 75, 'credit' => 0, 'name' => $bank->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 75,
            'reconciled' => 0, 'cheque_no' => 'CHQ-1', 'cheque_clearance_date' => Carbon::yesterday()->toDateString(),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $income->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Cheque', 'debit' => 0, 'credit' => 75, 'name' => $income->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 75,
        ]);

        $txnCountBefore = Transaction::withoutGlobalScopes()->count();

        $run = $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $this->assertSame(ReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertGreaterThan(0, $run->proposals_created);
        $this->assertSame($txnCountBefore, Transaction::withoutGlobalScopes()->count(), 'auto-match must never post a ledger document.');

        $proposal = ReconciliationProposal::forCompany($company->id)->where('kind', ReconciliationProposal::KIND_CLEARING_ROLLFORWARD)->first();
        $this->assertNotNull($proposal);
        $this->assertSame(ReconciliationProposal::CONFIDENCE_EXACT, $proposal->confidence);
        $this->assertSame(ReconciliationProposal::STATUS_PENDING, $proposal->status);
    }

    public function test_run_is_idempotent_and_does_not_duplicate_a_pending_proposal(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1215');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 40, 'description' => 'Cheque received',
            'reference_type' => 'Invoice', 'reference_number' => 'RAM2-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => 40, 'total_credit' => 40, 'idempotency_key' => uniqid('key:'),
        ]);
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bank->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Cheque', 'debit' => 40, 'credit' => 0, 'name' => $bank->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 40,
            'reconciled' => 0, 'cheque_no' => 'CHQ-2', 'cheque_clearance_date' => Carbon::yesterday()->toDateString(),
        ]);
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $income->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Cheque', 'debit' => 0, 'credit' => 40, 'name' => $income->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 40,
        ]);

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);
        $countAfterFirstRun = ReconciliationProposal::forCompany($company->id)->count();

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);
        $countAfterSecondRun = ReconciliationProposal::forCompany($company->id)->count();

        $this->assertSame($countAfterFirstRun, $countAfterSecondRun, 'Re-running the same day must not duplicate a still-pending proposal.');
    }

    // ── detectSubLedgerVsControl ────────────────────────────────────────────────────────────────

    public function test_detects_an_unattributed_control_line_against_its_sole_attributed_sibling_at_exact_confidence(): void
    {
        [$company, $branch] = $this->makeCompany();
        $ar = app(\App\Services\Accounting\AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 90, 'description' => 'Sale',
            'reference_type' => 'Invoice', 'reference_number' => 'SLC-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => 90, 'total_credit' => 90, 'idempotency_key' => uniqid('key:'),
        ]);

        // AR leg with NO type_reference_id (unattributed) -- the control-account gap.
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $ar->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Sale', 'debit' => 90, 'credit' => 0, 'name' => $ar->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 90,
            'type_reference_id' => null,
        ]);

        // Sibling leg IS attributed (party = the company id, standing in for a client id).
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $income->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Sale', 'debit' => 0, 'credit' => 90, 'name' => $income->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 90,
            'type_reference_id' => $company->id,
        ]);

        $run = $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $proposal = ReconciliationProposal::forCompany($company->id)->where('kind', ReconciliationProposal::KIND_SUB_LEDGER_VS_CONTROL)->first();
        $this->assertNotNull($proposal, 'A sole attributed sibling must produce a sub_ledger_vs_control proposal.');
        $this->assertSame(ReconciliationProposal::CONFIDENCE_EXACT, $proposal->confidence);
        $this->assertSame('party:'.$company->id, $proposal->matched_reference);
        $this->assertGreaterThan(0, $run->proposals_created);
    }

    public function test_skips_an_unattributed_control_line_with_no_attributed_sibling(): void
    {
        [$company, $branch] = $this->makeCompany();
        $ar = app(\App\Services\Accounting\AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 70, 'description' => 'Sale',
            'reference_type' => 'Invoice', 'reference_number' => 'SLC2-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => 70, 'total_credit' => 70, 'idempotency_key' => uniqid('key:'),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $ar->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Sale', 'debit' => 70, 'credit' => 0, 'name' => $ar->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 70,
            'type_reference_id' => null,
        ]);

        // Sibling leg is ALSO unattributed -- nothing to propose an attribution against.
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $income->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Sale', 'debit' => 0, 'credit' => 70, 'name' => $income->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 70,
            'type_reference_id' => null,
        ]);

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $proposal = ReconciliationProposal::forCompany($company->id)->where('kind', ReconciliationProposal::KIND_SUB_LEDGER_VS_CONTROL)->first();
        $this->assertNull($proposal, 'With no attributed sibling leg, nothing should be proposed -- left as a plain unmatched item.');
    }

    // ── detectReceiptInvoiceConsistency ─────────────────────────────────────────────────────────

    public function test_detects_a_possible_duplicate_receipt_for_the_same_party_amount_and_account_within_the_window(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $firstDate = Carbon::create(2026, 3, 1);
        $secondDate = Carbon::create(2026, 3, 5); // 4 days apart -- within the 14-day window

        foreach ([$firstDate, $secondDate] as $date) {
            $txn = Transaction::forceCreate([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'entity_id' => $company->id, 'entity_type' => 'company',
                'transaction_type' => 'JV', 'amount' => 55, 'description' => 'Receipt',
                'reference_type' => 'Invoice', 'reference_number' => 'DUP-'.substr(uniqid(), -8),
                'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
                'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
                'total_debit' => 55, 'total_credit' => 55, 'idempotency_key' => uniqid('key:'),
            ]);

            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
                'account_id' => $bank->id, 'transaction_date' => $date, 'posting_date' => $date,
                'description' => 'Receipt', 'debit' => 55, 'credit' => 0, 'name' => $bank->name,
                'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 55,
                'reconciled' => 0, 'type_reference_id' => $company->id,
            ]);
            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
                'account_id' => $income->id, 'transaction_date' => $date, 'posting_date' => $date,
                'description' => 'Receipt', 'debit' => 0, 'credit' => 55, 'name' => $income->name,
                'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 55,
            ]);
        }

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $proposal = ReconciliationProposal::forCompany($company->id)->where('kind', ReconciliationProposal::KIND_RECEIPT_INVOICE_CONSISTENCY)->first();
        $this->assertNotNull($proposal, 'Two same-account/same-party/same-amount receipts within the window must be flagged as a possible duplicate.');
        $this->assertSame(ReconciliationProposal::CONFIDENCE_SUGGESTED, $proposal->confidence);
        $this->assertNotNull($proposal->matched_journal_entry_id, 'Must reference the earlier line as the candidate duplicate.');
    }

    public function test_does_not_flag_two_same_amount_receipts_far_outside_the_duplicate_window(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $firstDate = Carbon::create(2026, 1, 1);
        $secondDate = Carbon::create(2026, 3, 1); // far more than 14 days apart

        foreach ([$firstDate, $secondDate] as $date) {
            $txn = Transaction::forceCreate([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'entity_id' => $company->id, 'entity_type' => 'company',
                'transaction_type' => 'JV', 'amount' => 33, 'description' => 'Receipt',
                'reference_type' => 'Invoice', 'reference_number' => 'FAR-'.substr(uniqid(), -8),
                'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
                'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
                'total_debit' => 33, 'total_credit' => 33, 'idempotency_key' => uniqid('key:'),
            ]);

            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
                'account_id' => $bank->id, 'transaction_date' => $date, 'posting_date' => $date,
                'description' => 'Receipt', 'debit' => 33, 'credit' => 0, 'name' => $bank->name,
                'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 33,
                'reconciled' => 0, 'type_reference_id' => $company->id,
            ]);
            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
                'account_id' => $income->id, 'transaction_date' => $date, 'posting_date' => $date,
                'description' => 'Receipt', 'debit' => 0, 'credit' => 33, 'name' => $income->name,
                'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 33,
            ]);
        }

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $proposal = ReconciliationProposal::forCompany($company->id)->where('kind', ReconciliationProposal::KIND_RECEIPT_INVOICE_CONSISTENCY)->first();
        $this->assertNull($proposal, 'Two same-amount receipts more than 14 days apart are too far apart to be a plausible duplicate.');
    }
}
