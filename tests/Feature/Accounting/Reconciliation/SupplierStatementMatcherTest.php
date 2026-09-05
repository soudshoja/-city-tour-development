<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reconciliation;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Supplier;
use App\Models\SupplierStatementImport;
use App\Models\SupplierStatementImportLine;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\Accounting\Reconciliation\SupplierStatementMatcher;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T8 (Lane E): SupplierStatementMatcherTest — the four states, tolerance
 * boundary (0.001 passes, 0.002 fails), original-currency (FC) vs base comparison, and the
 * `type_reference_id` party discipline (a same-amount payable line belonging to a DIFFERENT
 * supplier is never matched) — PLAN.md §5 Lane E test list, MP-8-1/MP-8-2/MP-8-3.
 */
class SupplierStatementMatcherTest extends AccountingTestCase
{
    private function matcher(): SupplierStatementMatcher
    {
        return app(SupplierStatementMatcher::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    private function anyLeaf(int $companyId, int $skip = 0): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('id')->skip($skip)->take(1)->firstOrFail();
    }

    private function makeSupplier(string $name = 'DOTW'): Supplier
    {
        return Supplier::factory()->create(['name' => $name, 'has_hotel' => true]);
    }

    private ?\App\Models\Agent $sharedAgent = null;

    private function makeInvoice(Company $company, Branch $branch): Invoice
    {
        // Invoice carries no company_id column of its own (derived via agent->branch->company_id
        // — see Invoice::getCompanyIdAttribute()); this fixture only needs a real invoices.id to
        // satisfy journal_entries' FK, never Invoice's own attributes. client_id/agent_id are
        // real FKs (invoices.client_id/.agent_id both ->constrained()) — one agent is reused
        // across a test's invoices to avoid re-creating agent_type/user chains per invoice.
        if ($this->sharedAgent === null) {
            $type = \App\Models\AgentType::factory()->create();
            $this->sharedAgent = \App\Models\Agent::factory()->create([
                'user_id' => (int) $branch->user_id,
                'branch_id' => $branch->id,
                'type_id' => $type->id,
            ]);
        }

        return Invoice::factory()->create([
            'client_id' => \App\Models\Client::factory()->create()->id,
            'agent_id' => $this->sharedAgent->id,
        ]);
    }

    private function makeBooking(int $companyId, ?int $invoiceId, string $bookingRef, ?string $confirmationNo = null): DotwAIBooking
    {
        return DotwAIBooking::create([
            'prebook_key' => 'DOTWAI-'.uniqid(),
            'company_id' => $companyId,
            'agent_phone' => '96500000000',
            'hotel_id' => 'H-1',
            'hotel_name' => 'Test Hotel',
            'check_in' => '2026-08-01',
            'check_out' => '2026-08-03',
            'original_total_fare' => 100,
            'original_currency' => 'USD',
            'display_total_fare' => 100,
            'display_currency' => 'USD',
            'status' => DotwAIBooking::STATUS_CONFIRMED,
            'booking_ref' => $bookingRef,
            'confirmation_no' => $confirmationNo,
            'invoice_id' => $invoiceId,
        ]);
    }

    private function makeTransaction(Company $company, Branch $branch, Carbon $date, float $amount): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => $amount, 'description' => 'Test DOTW charge',
            'reference_type' => 'Invoice', 'reference_number' => 'SSM-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'INV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:'),
        ]);
    }

    /**
     * Posts a balanced two-line document: Dr $debitAccount, Cr $creditAccount (the payable line,
     * carrying type_reference_id = the party and, optionally, an FC pair). Returns the CREDIT
     * (payable) line — the one a statement line matches against.
     */
    private function postCharge(
        Company $company,
        Branch $branch,
        Account $debitAccount,
        Account $creditAccount,
        int $partyId,
        int $invoiceId,
        float $amountKwd,
        Carbon $date,
        ?string $originalCurrency = null,
        ?float $originalAmount = null,
    ): JournalEntry {
        $txn = $this->makeTransaction($company, $branch, $date, $amountKwd);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $debitAccount->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'cost', 'debit' => $amountKwd, 'credit' => 0, 'name' => $debitAccount->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amountKwd,
            'voucher_number' => 'SSM', 'invoice_id' => $invoiceId,
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $creditAccount->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'payable', 'debit' => 0, 'credit' => $amountKwd, 'name' => $creditAccount->name,
            'type' => 'test', 'currency' => $originalCurrency ?? 'KWD', 'exchange_rate' => 1,
            'amount' => $amountKwd, 'voucher_number' => 'SSM', 'type_reference_id' => $partyId,
            'invoice_id' => $invoiceId, 'original_currency' => $originalCurrency, 'original_amount' => $originalAmount,
        ]);
    }

    private function makeImportWithLine(Company $company, Supplier $supplier, array $lineAttrs, string $statementCurrency = 'KWD'): SupplierStatementImport
    {
        $import = SupplierStatementImport::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'file_name' => 'test.csv',
            'statement_currency' => $statementCurrency,
            'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => ['booking_ref' => 'Booking Reference'],
            'status' => SupplierStatementImport::STATUS_STAGED,
        ]);

        SupplierStatementImportLine::create(array_merge([
            'import_id' => $import->id,
            'row_no' => 1,
            'currency' => $statementCurrency,
            'state' => SupplierStatementImportLine::STATE_UNMATCHED,
        ], $lineAttrs));

        return $import->fresh(['lines']);
    }

    // ── matched ──────────────────────────────────────────────────────────────────────────────

    public function test_matches_a_clean_line_and_creates_a_pending_proposal(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $bookLine = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $booking = $this->makeBooking($company->id, $invoiceId, 'DTW-100');
        $import = $this->makeImportWithLine($company, $supplier, ['booking_ref' => 'DTW-100', 'amount' => 100.000]);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched);
        $this->assertSame(0, $result->disputed);
        $this->assertSame(0, $result->unmatchedStatement);

        $line = $import->lines->first()->fresh();
        $this->assertSame(SupplierStatementImportLine::STATE_MATCHED, $line->state);
        $this->assertSame($bookLine->id, $line->matched_journal_entry_id);
        $this->assertEqualsWithDelta(0.0, $line->difference, 0.0001);

        $proposal = ReconciliationProposal::where('matched_reference', 'supplier_stmt_line:'.$line->id)->first();
        $this->assertNotNull($proposal);
        $this->assertSame(ReconciliationProposal::KIND_SUPPLIER_STATEMENT, $proposal->kind);
        $this->assertSame('external', $proposal->source);
        $this->assertSame(ReconciliationProposal::STATUS_PENDING, $proposal->status);
        $this->assertSame($bookLine->id, $proposal->book_journal_entry_id);

        // MP-8-3 proof, positive half: the matcher never flips reconciled itself.
        $this->assertSame(0, (int) $bookLine->fresh()->reconciled);
    }

    // ── tolerance boundary ──────────────────────────────────────────────────────────────────────

    public function test_amount_difference_of_exactly_tolerance_matches(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-101');
        $import = $this->makeImportWithLine($company, $supplier, ['booking_ref' => 'DTW-101', 'amount' => 99.999]);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched, '0.001 KWD difference must pass the tolerance boundary.');
        $this->assertSame(0, $result->disputed);
    }

    public function test_amount_difference_beyond_tolerance_is_disputed(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-102');
        $import = $this->makeImportWithLine($company, $supplier, ['booking_ref' => 'DTW-102', 'amount' => 99.998]);

        $result = $this->matcher()->match($import);

        $this->assertSame(0, $result->matched, '0.002 KWD difference must fail the tolerance boundary.');
        $this->assertSame(1, $result->disputed);

        $line = $import->lines->first()->fresh();
        $this->assertSame(SupplierStatementImportLine::STATE_DISPUTED, $line->state);
        $this->assertEqualsWithDelta(0.002, $line->difference, 0.0001);

        // A disputed line never gets a proposal — nothing clean to approve.
        $this->assertNull(ReconciliationProposal::where('matched_reference', 'supplier_stmt_line:'.$line->id)->first());
    }

    // ── FC vs base ───────────────────────────────────────────────────────────────────────────

    public function test_matches_on_original_currency_amount_when_statement_currency_matches_the_fc_line(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        // Posted at base 92.000 KWD but the FC line was USD 300.000 — statement is in USD, so the
        // matcher must compare against original_amount (300.000), NOT the base 92.000.
        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 92.000, Carbon::create(2026, 8, 5), 'USD', 300.000);
        $this->makeBooking($company->id, $invoiceId, 'DTW-103');
        $import = $this->makeImportWithLine($company, $supplier, ['booking_ref' => 'DTW-103', 'amount' => 300.000], 'USD');

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched);
    }

    public function test_falls_back_to_base_currency_when_no_line_carries_the_statement_currency(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 150.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-104');
        $import = $this->makeImportWithLine($company, $supplier, ['booking_ref' => 'DTW-104', 'amount' => 150.000], 'KWD');

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched);
    }

    // ── unmatched-statement ──────────────────────────────────────────────────────────────────

    public function test_unmatched_statement_when_no_booking_resolves(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $import = $this->makeImportWithLine($company, $supplier, ['booking_ref' => 'NO-SUCH-BOOKING', 'amount' => 10.000]);

        $result = $this->matcher()->match($import);

        $this->assertSame(0, $result->matched);
        $this->assertSame(0, $result->disputed);
        $this->assertSame(1, $result->unmatchedStatement);
        $this->assertSame(SupplierStatementImportLine::STATE_UNMATCHED, $import->lines->first()->fresh()->state);
    }

    // ── unmatched-ledger (exceptions report) ────────────────────────────────────────────────────

    public function test_unmatched_ledger_lists_a_posted_payable_line_absent_from_the_statement(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);

        $invoiceId = $this->makeInvoice($company, $branch)->id;
        $unmatchedBookLine = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 40.000, Carbon::create(2026, 8, 1));

        $import = SupplierStatementImport::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'file_name' => 'empty.csv',
            'statement_currency' => 'KWD', 'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
            'content_hash' => hash('sha256', uniqid('', true)), 'column_map' => [], 'status' => SupplierStatementImport::STATUS_STAGED,
        ]);

        $this->matcher()->match($import);
        $ledgerGap = $this->matcher()->unmatchedLedgerLines($import);

        $this->assertTrue($ledgerGap->pluck('id')->contains($unmatchedBookLine->id));
    }

    // ── party discipline (MP-8-1) ────────────────────────────────────────────────────────────────

    public function test_a_payable_line_for_a_different_supplier_with_the_same_amount_is_never_matched(): void
    {
        [$company, $branch] = $this->makeCompany();
        $dotw = $this->makeSupplier('DOTW');
        $otherSupplier = $this->makeSupplier('Other Wholesaler');
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        // Same invoice_id, same amount, DIFFERENT party — the "cross-supplier leak" fixture.
        $this->postCharge($company, $branch, $debit, $credit, $otherSupplier->id, $invoiceId, 77.000, Carbon::create(2026, 8, 5));
        $dotwLine = $this->postCharge($company, $branch, $debit, $credit, $dotw->id, $invoiceId, 77.000, Carbon::create(2026, 8, 5));

        $this->makeBooking($company->id, $invoiceId, 'DTW-105');
        $import = $this->makeImportWithLine($company, $dotw, ['booking_ref' => 'DTW-105', 'amount' => 77.000]);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched);
        $line = $import->lines->first()->fresh();
        $this->assertSame($dotwLine->id, $line->matched_journal_entry_id, 'Must match the DOTW party line, never the other supplier\'s line.');
    }

    // ── idempotent re-match ──────────────────────────────────────────────────────────────────────

    public function test_rematching_the_same_import_does_not_duplicate_proposals(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 60.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-106');
        $import = $this->makeImportWithLine($company, $supplier, ['booking_ref' => 'DTW-106', 'amount' => 60.000]);

        $this->matcher()->match($import);
        $this->matcher()->match($import);

        $line = $import->lines->first()->fresh();
        $count = ReconciliationProposal::where('matched_reference', 'supplier_stmt_line:'.$line->id)->count();
        $this->assertSame(1, $count);
    }
}
