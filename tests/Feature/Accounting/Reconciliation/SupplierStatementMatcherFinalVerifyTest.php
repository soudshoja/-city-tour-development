<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reconciliation;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierStatementImport;
use App\Models\SupplierStatementImportLine;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\Accounting\Reconciliation\SupplierStatementMatcher;
use App\Services\Accounting\ReconciliationProposalService;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * FINAL (loop 3) re-verification of T8. Scope: the loop-2 fix (98918057) only — the
 * closest-candidate-wins solo selection (RV-4), the aggregate covered-id column (RV-1), and the
 * cross-import proposal claim guard (RV-6). Scenarios derived independently before reading the
 * fix; independent of the builder's suite, the AV suite and the RV suite.
 */
class SupplierStatementMatcherFinalVerifyTest extends AccountingTestCase
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

    private function makeBooking(int $companyId, ?int $invoiceId, string $bookingRef): DotwAIBooking
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
            'confirmation_no' => null,
            'invoice_id' => $invoiceId,
        ]);
    }

    private function makeTransaction(Company $company, Branch $branch, Carbon $date, float $amount): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => $amount, 'description' => 'Test DOTW charge',
            'reference_type' => 'Invoice', 'reference_number' => 'SSMF-'.substr(uniqid('', true), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'INV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
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
    ): JournalEntry {
        $txn = $this->makeTransaction($company, $branch, $date, $amountKwd);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $debitAccount->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'cost', 'debit' => $amountKwd, 'credit' => 0, 'name' => $debitAccount->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amountKwd,
            'voucher_number' => 'SSMF', 'invoice_id' => $invoiceId,
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $creditAccount->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'payable', 'debit' => 0, 'credit' => $amountKwd, 'name' => $creditAccount->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amountKwd, 'voucher_number' => 'SSMF', 'type_reference_id' => $partyId,
            'invoice_id' => $invoiceId,
        ]);
    }

    private function makeImport(Company $company, Supplier $supplier): SupplierStatementImport
    {
        return SupplierStatementImport::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'file_name' => 'test.csv',
            'statement_currency' => 'KWD',
            'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => ['booking_ref' => 'Booking Reference'],
            'status' => SupplierStatementImport::STATUS_STAGED,
        ]);
    }

    private function addLine(SupplierStatementImport $import, int $rowNo, array $lineAttrs): SupplierStatementImportLine
    {
        return SupplierStatementImportLine::create(array_merge([
            'import_id' => $import->id,
            'row_no' => $rowNo,
            'currency' => 'KWD',
            'state' => SupplierStatementImportLine::STATE_UNMATCHED,
        ], $lineAttrs));
    }

    private function proposalFor(int $journalEntryId): ?ReconciliationProposal
    {
        return ReconciliationProposal::where('book_journal_entry_id', $journalEntryId)->first();
    }

    // ── FV-1 (RV-4): three candidates at distances 0.001 / 0.000 / 0.001 — the EXACT one must win
    // regardless of its position in the id-ordered candidate list, and the proposal must be
    // labelled 'exact', not 'tolerance'. ────────────────────────────────────────────────────────
    public function test_closest_candidate_wins_over_two_equidistant_in_tolerance_neighbours(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        // Posted in id order: high, exact, low.
        $high = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.001, Carbon::create(2026, 8, 5));
        $exact = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $low = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 99.999, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-FV1');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-FV1', 'amount' => 100.000, 'statement_date' => '2026-08-05']);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched);
        $line = $import->lines()->first();
        $this->assertSame(
            $exact->id,
            (int) $line->matched_journal_entry_id,
            'With three candidates inside tolerance the CLOSEST (distance 0.000) must win, not the first by id.'
        );
        $this->assertSame(0.0, (float) $line->difference);
        $this->assertSame(
            ReconciliationProposal::CONFIDENCE_EXACT,
            $this->proposalFor($exact->id)?->confidence,
            "A zero-difference match must be labelled 'exact'."
        );
        $this->assertNull($this->proposalFor($high->id));
        $this->assertNull($this->proposalFor($low->id));
    }

    // ── FV-2 (RV-4): a genuine TIE (0.001 either side) must resolve to the lower id, and the
    // SECOND statement row must then take the REMAINING candidate — never re-pick a consumed id.
    public function test_equidistant_tie_takes_lower_id_and_second_row_takes_the_remaining_candidate(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $lowerId = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.001, Carbon::create(2026, 8, 5));
        $higherId = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 99.999, Carbon::create(2026, 8, 5));
        $this->assertTrue($lowerId->id < $higherId->id, 'Fixture guard: the +0.001 line must carry the lower id.');
        $this->makeBooking($company->id, $invoiceId, 'DTW-FV2');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-FV2', 'amount' => 100.000, 'statement_date' => '2026-08-05']); // tie: 0.001 / 0.001
        $this->addLine($import, 2, ['booking_ref' => 'DTW-FV2', 'amount' => 99.999, 'statement_date' => '2026-08-05']);  // exact on the survivor
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(2, $result->matched);
        $this->assertSame(0, $result->disputed);

        $lines = $import->lines()->orderBy('row_no')->get();
        $this->assertSame($lowerId->id, (int) $lines[0]->matched_journal_entry_id, 'A tie must resolve deterministically to the lower id.');
        $this->assertSame($higherId->id, (int) $lines[1]->matched_journal_entry_id, 'The second row must take the remaining candidate, not the consumed one.');
        $this->assertSame(ReconciliationProposal::CONFIDENCE_TOLERANCE, $this->proposalFor($lowerId->id)?->confidence);
        $this->assertSame(ReconciliationProposal::CONFIDENCE_EXACT, $this->proposalFor($higherId->id)?->confidence);

        // Determinism: an identical statement imported again picks the identical pair.
        $repeat = $this->makeImport($company, $supplier);
        $this->addLine($repeat, 1, ['booking_ref' => 'DTW-FV2', 'amount' => 100.000, 'statement_date' => '2026-08-05']);
        $this->addLine($repeat, 2, ['booking_ref' => 'DTW-FV2', 'amount' => 99.999, 'statement_date' => '2026-08-05']);
        $this->matcher()->match($repeat->fresh(['lines']));
        $repeatLines = $repeat->lines()->orderBy('row_no')->get();
        $this->assertSame($lowerId->id, (int) $repeatLines[0]->matched_journal_entry_id);
        $this->assertSame($higherId->id, (int) $repeatLines[1]->matched_journal_entry_id);
    }

    // ── FV-3 (RV-4): selection is closest-INSIDE-tolerance. A far-off candidate sitting FIRST in
    // the id order must never be taken, and must never block the in-tolerance one behind it. ────
    public function test_out_of_tolerance_first_candidate_never_wins_over_an_in_tolerance_later_one(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $far = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $near = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 105.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-FV3');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-FV3', 'amount' => 105.000, 'statement_date' => '2026-08-05']);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched);
        $this->assertSame($near->id, (int) $import->lines()->first()->matched_journal_entry_id);
        $this->assertSame(ReconciliationProposal::CONFIDENCE_EXACT, $this->proposalFor($near->id)?->confidence);
        $this->assertNull($this->proposalFor($far->id), 'An out-of-tolerance candidate must never be consumed by the solo loop.');

        // The untouched 100.000 line is a genuine ledger gap and must still be reported as one.
        $this->assertTrue($this->matcher()->unmatchedLedgerLines($import->fresh())->pluck('id')->contains($far->id));
    }

    // ── FV-4 (RV-4/RV-1): when NO candidate is inside tolerance the solo loop must consume
    // nothing and record no covered ids; the aggregate fallback then disputes cleanly. ──────────
    public function test_no_candidate_inside_tolerance_consumes_nothing_and_records_no_covered_ids(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $a = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $b = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 105.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-FV4');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-FV4', 'amount' => 60.000, 'statement_date' => '2026-08-05']);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->disputed);
        $this->assertSame(0, $result->matched);
        $line = $import->lines()->first();
        $this->assertSame($a->id, (int) $line->matched_journal_entry_id, 'A disputed line still names the primary candidate.');
        $this->assertNull($line->matched_journal_entry_ids, 'A disputed line covers nothing — the covered-id column must stay null.');
        $this->assertSame(0, ReconciliationProposal::whereIn('book_journal_entry_id', [$a->id, $b->id])->count());
    }

    // ── FV-5 (RV-1): the covered-id column round-trips as a JSON array of INTS, and every covered
    // id is excluded from the unmatched-ledger read. ────────────────────────────────────────────
    public function test_covered_journal_entry_ids_round_trip_as_ints_and_exclude_every_covered_line(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $one = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 20.000, Carbon::create(2026, 8, 5));
        $two = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 30.000, Carbon::create(2026, 8, 5));
        $three = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 40.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-FV5');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-FV5', 'amount' => 90.000, 'statement_date' => '2026-08-05']);
        $import = $import->fresh(['lines']);

        $this->matcher()->match($import);

        $line = $import->lines()->first();
        $this->assertSame(
            [$one->id, $two->id, $three->id],
            $line->matched_journal_entry_ids,
            'The covered set must round-trip as a list of ints, in candidate id order.'
        );
        foreach ($line->matched_journal_entry_ids as $coveredId) {
            $this->assertIsInt($coveredId);
        }

        $raw = DB::table('supplier_statement_import_lines')->where('id', $line->id)->value('matched_journal_entry_ids');
        $this->assertSame([$one->id, $two->id, $three->id], json_decode((string) $raw, true), 'The stored column must be a plain JSON array.');

        $gap = $this->matcher()->unmatchedLedgerLines($import->fresh())->pluck('id');
        $this->assertFalse($gap->contains($one->id));
        $this->assertFalse($gap->contains($two->id));
        $this->assertFalse($gap->contains($three->id));
        $this->assertSame(0, (int) ($import->fresh()->counts['unmatched_ledger'] ?? -1));
    }

    // ── FV-6 (RV-6 × RV-1): a later statement that SPLITS an already-claimed aggregate must not
    // raise a second approvable claim on any ledger line the aggregate already covered. The
    // aggregate's proposal names only its PRIMARY, so a guard keyed on book_journal_entry_id
    // alone leaves every non-primary covered line open to a duplicate claim. ────────────────────
    public function test_later_statement_splitting_an_approved_aggregate_raises_no_second_claim(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $room = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 50.000, Carbon::create(2026, 8, 5));
        $tax = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 30.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-FV6');

        // Statement 1: one summary row of 80 → aggregate match covering BOTH ledger lines.
        $first = $this->makeImport($company, $supplier);
        $this->addLine($first, 1, ['booking_ref' => 'DTW-FV6', 'amount' => 80.000, 'statement_date' => '2026-08-05']);
        $this->matcher()->match($first->fresh(['lines']));

        $aggregate = $this->proposalFor($room->id);
        $this->assertNotNull($aggregate, 'Guard on the premise: the aggregate match proposes against its primary.');
        app(ReconciliationProposalService::class)->approve($aggregate, User::factory()->create(['role_id' => Role::ADMIN]));

        // Statement 2 (corrected, different content hash): the supplier re-sends the SAME charge
        // split into its two components. Both rows correspond to ledger lines already settled by
        // the approved summary row.
        $second = $this->makeImport($company, $supplier);
        $this->addLine($second, 1, ['booking_ref' => 'DTW-FV6', 'amount' => 50.000, 'statement_date' => '2026-08-05']);
        $this->addLine($second, 2, ['booking_ref' => 'DTW-FV6', 'amount' => 30.000, 'statement_date' => '2026-08-05']);
        $this->matcher()->match($second->fresh(['lines']));

        $live = ReconciliationProposal::whereIn('book_journal_entry_id', [$room->id, $tax->id])
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])
            ->get();

        $this->assertSame(
            1,
            $live->count(),
            'The 80.000 aggregate already claimed BOTH ledger lines; a later split statement must not add a second approvable claim against either of them.'
        );
        $this->assertSame($aggregate->id, (int) $live->first()->id);
    }

    // ── FV-7 (RV-6): pending refuses, rejected does not — and a refused row stays MATCHED with an
    // explanatory note, never demoted to an exception, with counts to match. ────────────────────
    public function test_pending_claim_refuses_rejected_claim_allows_and_the_row_stays_matched(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $je = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 62.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-FV7');

        $first = $this->makeImport($company, $supplier);
        $this->addLine($first, 1, ['booking_ref' => 'DTW-FV7', 'amount' => 62.000, 'statement_date' => '2026-08-05']);
        $this->matcher()->match($first->fresh(['lines']));
        $original = $this->proposalFor($je->id);
        $this->assertSame(ReconciliationProposal::STATUS_PENDING, $original?->status);

        // (a) PENDING is a claim — a later statement must not add a second one.
        $second = $this->makeImport($company, $supplier);
        $this->addLine($second, 1, ['booking_ref' => 'DTW-FV7', 'amount' => 62.000, 'statement_date' => '2026-08-05']);
        $result = $this->matcher()->match($second->fresh(['lines']));

        $this->assertSame(1, ReconciliationProposal::where('book_journal_entry_id', $je->id)->count(), 'A pending claim must block a second proposal.');
        $this->assertSame(1, $result->matched, 'The refused row still corresponds to that ledger line — it stays matched.');
        $this->assertSame(0, $result->disputed);
        $this->assertSame(0, $result->unmatchedStatement);

        $refused = $second->lines()->first();
        $this->assertSame(SupplierStatementImportLine::STATE_MATCHED, $refused->state);
        $this->assertNotNull($refused->note);
        $this->assertStringContainsString('already reconciled', (string) $refused->note);
        $this->assertSame($je->id, (int) $refused->matched_journal_entry_id);

        // (b) REJECTED is NOT a claim — a third statement may legitimately propose again.
        app(ReconciliationProposalService::class)->reject($original, 'not ours', User::factory()->create(['role_id' => Role::ADMIN]));
        $this->assertSame(ReconciliationProposal::STATUS_REJECTED, $original->fresh()->status);

        $third = $this->makeImport($company, $supplier);
        $this->addLine($third, 1, ['booking_ref' => 'DTW-FV7', 'amount' => 62.000, 'statement_date' => '2026-08-05']);
        $this->matcher()->match($third->fresh(['lines']));

        $this->assertSame(
            1,
            ReconciliationProposal::where('book_journal_entry_id', $je->id)->where('status', ReconciliationProposal::STATUS_PENDING)->count(),
            'A rejected proposal is not a claim — a later statement must be able to raise a fresh one.'
        );
        $this->assertNull($third->lines()->first()->note);
    }
}
