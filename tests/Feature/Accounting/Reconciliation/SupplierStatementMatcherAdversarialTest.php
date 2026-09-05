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
 * Independent adversarial-verifier tests for T8, p3e lane. Probes scenarios not pinned by the
 * builder's own suite: two ledger lines sharing one booking ref (aggregation correctness),
 * duplicate statement rows against a single ledger line (double-proposal risk), reversed/void
 * ledger lines (must be excluded), and cross-company type_reference_id collision (party leak).
 */
class SupplierStatementMatcherAdversarialTest extends AccountingTestCase
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
            'prebook_key' => 'DOTWAI-'.uniqid('', true),
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

    private function makeTransaction(Company $company, Branch $branch, Carbon $date, float $amount, string $postingStatus = 'posted'): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => $amount, 'description' => 'Test DOTW charge',
            'reference_type' => 'Invoice', 'reference_number' => 'SSMX-'.substr(uniqid('', true), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'INV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => $postingStatus,
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:', true),
        ]);
    }

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
        string $postingStatus = 'posted',
    ): JournalEntry {
        $txn = $this->makeTransaction($company, $branch, $date, $amountKwd, $postingStatus);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $debitAccount->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'cost', 'debit' => $amountKwd, 'credit' => 0, 'name' => $debitAccount->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amountKwd,
            'voucher_number' => 'SSMX', 'invoice_id' => $invoiceId,
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $creditAccount->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'payable', 'debit' => 0, 'credit' => $amountKwd, 'name' => $creditAccount->name,
            'type' => 'test', 'currency' => $originalCurrency ?? 'KWD', 'exchange_rate' => 1,
            'amount' => $amountKwd, 'voucher_number' => 'SSMX', 'type_reference_id' => $partyId,
            'invoice_id' => $invoiceId, 'original_currency' => $originalCurrency, 'original_amount' => $originalAmount,
        ]);
    }

    private function makeImport(Company $company, Supplier $supplier, string $statementCurrency = 'KWD'): SupplierStatementImport
    {
        return SupplierStatementImport::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'file_name' => 'test.csv',
            'statement_currency' => $statementCurrency,
            'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => ['booking_ref' => 'Booking Reference'],
            'status' => SupplierStatementImport::STATUS_STAGED,
        ]);
    }

    private function addLine(SupplierStatementImport $import, int $rowNo, array $lineAttrs, string $currency = 'KWD'): SupplierStatementImportLine
    {
        return SupplierStatementImportLine::create(array_merge([
            'import_id' => $import->id,
            'row_no' => $rowNo,
            'currency' => $currency,
            'state' => SupplierStatementImportLine::STATE_UNMATCHED,
        ], $lineAttrs));
    }

    // ── AV-1: two ledger lines, same invoice+party (e.g. room + tax as separate JE lines), two
    // separate statement rows each meant to match ONE of them individually ──────────────────────
    public function test_two_ledger_lines_on_one_invoice_do_not_falsely_dispute_individually_matching_statement_rows(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $roomLine = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 50.000, Carbon::create(2026, 8, 5));
        $taxLine = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 30.000, Carbon::create(2026, 8, 5));

        $this->makeBooking($company->id, $invoiceId, 'DTW-AV1');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-AV1', 'amount' => 50.000]);
        $this->addLine($import, 2, ['booking_ref' => 'DTW-AV1', 'amount' => 30.000]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        // DEFECT PROBE: the matcher aggregates ALL candidate lines for the invoice+party
        // (50+30=80) and compares EACH statement row against that same 80, not against its own
        // individual counterpart. Both rows would be wrongly "disputed" (80 vs 50, 80 vs 30)
        // instead of one clean match each.
        $this->assertSame(
            2,
            $result->matched,
            'Two legitimate, individually-matching ledger lines on one invoice must each match their own statement row, not be summed into one false dispute.'
        );
        $this->assertSame(0, $result->disputed);

        $lines = $import->lines()->orderBy('row_no')->get();
        $matchedJeIds = $lines->pluck('matched_journal_entry_id')->all();
        $this->assertContains($roomLine->id, $matchedJeIds);
        $this->assertContains($taxLine->id, $matchedJeIds);
    }

    // ── AV-2: duplicate statement rows (same booking_ref+amount) against ONE ledger line must not
    // create two proposals against the same underlying JE ──────────────────────────────────────
    public function test_duplicate_statement_rows_for_one_booking_do_not_create_two_proposals_on_the_same_ledger_line(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $bookLine = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 45.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-AV2');

        $import = $this->makeImport($company, $supplier);
        // Same booking ref, same amount, twice — an operator's duplicate paste, or DOTW listing
        // the same line item twice on the statement export.
        $this->addLine($import, 1, ['booking_ref' => 'DTW-AV2', 'amount' => 45.000]);
        $this->addLine($import, 2, ['booking_ref' => 'DTW-AV2', 'amount' => 45.000]);
        $import = $import->fresh(['lines']);

        $this->matcher()->match($import);

        $proposalsOnThisJe = ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->count();

        $this->assertSame(
            1,
            $proposalsOnThisJe,
            'Two statement rows resolving to the SAME ledger line must not create two pending proposals against it (double-count risk on approval).'
        );
    }

    // ── AV-3: a reversed transaction's payable line must never be a match candidate ────────────
    public function test_reversed_ledger_line_is_excluded_from_matching_and_from_unmatched_ledger(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $reversedLine = $this->postCharge(
            $company, $branch, $debit, $credit, $supplier->id, $invoiceId, 70.000,
            Carbon::create(2026, 8, 5), null, null, 'reversed'
        );
        $this->makeBooking($company->id, $invoiceId, 'DTW-AV3');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-AV3', 'amount' => 70.000]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(0, $result->matched, 'A reversed transaction must never be treated as a live payable candidate.');
        $this->assertSame(0, $result->disputed);
        $this->assertSame(1, $result->unmatchedStatement, 'With no live candidate, the statement line is unmatched-statement.');

        $ledgerGap = $this->matcher()->unmatchedLedgerLines($import->fresh());
        $this->assertFalse($ledgerGap->pluck('id')->contains($reversedLine->id), 'A reversed line must not appear as an open ledger exception either.');
    }

    // ── AV-4: void transaction excluded too ─────────────────────────────────────────────────────
    public function test_void_ledger_line_is_excluded_from_matching(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $this->postCharge(
            $company, $branch, $debit, $credit, $supplier->id, $invoiceId, 33.000,
            Carbon::create(2026, 8, 5), null, null, 'void'
        );
        $this->makeBooking($company->id, $invoiceId, 'DTW-AV4');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-AV4', 'amount' => 33.000]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(0, $result->matched);
        $this->assertSame(1, $result->unmatchedStatement);
    }

    // ── AV-5: cross-company leak — same supplier id, same invoice/amount coincidence across two
    // different companies must never cross-match ────────────────────────────────────────────────
    public function test_cross_company_type_reference_id_collision_never_cross_matches(): void
    {
        [$companyA, $branchA] = $this->makeCompany();
        [$companyB, $branchB] = $this->makeCompany();
        $supplier = $this->makeSupplier(); // one shared Supplier row, used by both companies' JEs
        $debitA = $this->anyLeaf($companyA->id, 0);
        $creditA = $this->anyLeaf($companyA->id, 1);
        $debitB = $this->anyLeaf($companyB->id, 0);
        $creditB = $this->anyLeaf($companyB->id, 1);

        // ONE shared invoice id used by BOTH companies' journal_entries rows — Invoice itself
        // carries no company_id column (derived via agent->branch), so this is the true leakage
        // vector: only journal_entries.company_id can stop company A's statement from matching
        // company B's payable line when the invoice id AND type_reference_id both collide.
        $sharedInvoiceId = $this->makeInvoice($companyA, $branchA)->id;

        // Company B posts a payable line for the SAME supplier id and the SAME (shared) invoice
        // id as company A's booking.
        $bLine = $this->postCharge($companyB, $branchB, $debitB, $creditB, $supplier->id, $sharedInvoiceId, 88.000, Carbon::create(2026, 8, 5));

        // Company A has a booking resolving to that SAME shared invoice id — the statement import
        // belongs to company A. No payable line at all posted for company A on this invoice/supplier.
        $this->makeBooking($companyA->id, $sharedInvoiceId, 'DTW-AV5');

        $import = $this->makeImport($companyA, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-AV5', 'amount' => 88.000]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(0, $result->matched, 'Company A statement must never match company B\'s ledger line even with the same supplier id, invoice id, and amount.');
        $line = $import->lines()->first()->fresh();
        $this->assertNotSame($bLine->id, $line->matched_journal_entry_id);
    }

    // ── AV-6: tolerance boundary just past the boundary at 3-decimal precision (0.0011) ─────────
    public function test_tolerance_boundary_at_sub_thousandth_difference_rounds_to_matched(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-AV6');

        // 100.000 - 99.9989 = 0.0011 -> rounds to 0.001 at 3dp, which the matcher's own round()
        // call will also do -> must land on the matched side of the boundary (<=0.001), not a
        // silently-different disputed outcome due to float noise.
        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-AV6', 'amount' => 99.9989]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched, 'A 0.0011 raw difference rounds to 0.001 at 3dp and must match.');
    }

    // ── AV-7: idempotent re-import by explicit statement_reference silently discards a corrected
    // file's different amounts — probing whether this is at least surfaced, not corrupting data ──
    public function test_reimport_same_reference_different_amount_keeps_the_first_imports_lines_untouched(): void
    {
        [$company] = $this->makeCompany();
        $supplier = $this->makeSupplier();

        $importer = app(\App\Services\Accounting\Reconciliation\SupplierStatementImporter::class);

        $csv1 = "Booking Reference,Amount,Currency\nDTW-AV7,50.000,KWD\n";
        $csv2 = "Booking Reference,Amount,Currency\nDTW-AV7,999.000,KWD\n";

        $path1 = tempnam(sys_get_temp_dir(), 'ssi1').'.csv';
        $path2 = tempnam(sys_get_temp_dir(), 'ssi2').'.csv';
        file_put_contents($path1, $csv1);
        file_put_contents($path2, $csv2);

        $input1 = new \App\Services\Accounting\Reconciliation\SupplierStatementImportInput(
            companyId: $company->id, supplierId: $supplier->id, absoluteFilePath: $path1, fileName: 'stmt.csv',
            statementCurrency: 'KWD', statementReference: 'REF-AV7',
        );
        $input2 = new \App\Services\Accounting\Reconciliation\SupplierStatementImportInput(
            companyId: $company->id, supplierId: $supplier->id, absoluteFilePath: $path2, fileName: 'stmt.csv',
            statementCurrency: 'KWD', statementReference: 'REF-AV7',
        );

        $import1 = $importer->import($input1);
        $import2 = $importer->import($input2);

        $this->assertSame($import1->id, $import2->id, 'Same explicit statement_reference must resolve to the same import row.');
        $this->assertSame(1, $import1->fresh(['lines'])->lines->count());
        $this->assertEqualsWithDelta(50.000, (float) $import1->fresh(['lines'])->lines->first()->amount, 0.0001, 'The second file\'s different amount must never silently overwrite the first import\'s stored lines.');

        @unlink($path1);
        @unlink($path2);
    }
}
