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
use Tests\Support\AccountingTestCase;

/**
 * POST-FIX RE-VERIFICATION of T8's AV-1/AV-2 fix (commit d237e83e). Independent of both the
 * builder's suite and the first verifier's SupplierStatementMatcherAdversarialTest: these probe
 * the behaviour of the NEW code paths that fix introduced — the single-candidate ("solo") loop,
 * its candidate-selection rule, the surviving legacy aggregate fallback, and the per-run consumed
 * set's boundaries (what happens ACROSS runs / imports, where an in-memory set cannot reach).
 */
class SupplierStatementMatcherReverifyTest extends AccountingTestCase
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
            'reference_type' => 'Invoice', 'reference_number' => 'SSMR-'.substr(uniqid('', true), -8),
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
            'voucher_number' => 'SSMR', 'invoice_id' => $invoiceId,
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $creditAccount->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'payable', 'debit' => 0, 'credit' => $amountKwd, 'name' => $creditAccount->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amountKwd, 'voucher_number' => 'SSMR', 'type_reference_id' => $partyId,
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

    // ── RV-1: the surviving legacy aggregate fallback — one statement row summarising TWO ledger
    // lines must still match, AND must not leave its non-primary ledger line showing up as an
    // "unmatched-ledger" exception (the 4th state means "our open payable absent from the
    // statement"; this line is not absent, it is inside the row that matched). ─────────────────
    public function test_aggregate_fallback_covers_every_line_it_consumed_not_only_the_primary(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $room = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 50.000, Carbon::create(2026, 8, 5));
        $tax = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 30.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-RV1');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-RV1', 'amount' => 80.000]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched, 'One statement row summing two ledger lines must still match through the aggregate fallback.');
        $this->assertSame(0, $result->disputed);

        $gap = $this->matcher()->unmatchedLedgerLines($import->fresh());
        $this->assertFalse(
            $gap->pluck('id')->contains($tax->id),
            'The non-primary ledger line consumed by an aggregate match is NOT an open payable absent from the statement — it must not be reported as an unmatched-ledger exception.'
        );
        $this->assertFalse($gap->pluck('id')->contains($room->id));
        $this->assertSame(0, (int) ($import->fresh()->counts['unmatched_ledger'] ?? -1), 'counts.unmatched_ledger must not be inflated by aggregate-consumed lines.');
    }

    // ── RV-2: two ledger lines, statement rows in REVERSE order — each row must still claim its
    // own counterpart, never the same one twice. ────────────────────────────────────────────────
    public function test_statement_rows_in_reverse_order_each_claim_their_own_ledger_line(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $first = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 50.000, Carbon::create(2026, 8, 5));
        $second = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 30.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-RV2');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-RV2', 'amount' => 30.000]); // matches the SECOND ledger line
        $this->addLine($import, 2, ['booking_ref' => 'DTW-RV2', 'amount' => 50.000]); // matches the FIRST
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(2, $result->matched);
        $this->assertSame(0, $result->disputed);

        $lines = $import->lines()->orderBy('row_no')->get();
        $this->assertSame($second->id, (int) $lines[0]->matched_journal_entry_id);
        $this->assertSame($first->id, (int) $lines[1]->matched_journal_entry_id);
        $this->assertSame(2, ReconciliationProposal::whereIn('book_journal_entry_id', [$first->id, $second->id])->count());
    }

    // ── RV-3: three identical-amount ledger lines, two statement rows — which two get consumed
    // must be deterministic (lowest ids, in order), and the survivor must be the ledger gap. ────
    public function test_three_identical_ledger_lines_two_rows_consume_deterministically(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $a = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 40.000, Carbon::create(2026, 8, 5));
        $b = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 40.000, Carbon::create(2026, 8, 5));
        $c = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 40.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-RV3');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-RV3', 'amount' => 40.000]);
        $this->addLine($import, 2, ['booking_ref' => 'DTW-RV3', 'amount' => 40.000]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(2, $result->matched);
        $claimed = $import->lines()->orderBy('row_no')->pluck('matched_journal_entry_id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([$a->id, $b->id], $claimed, 'Consumption order must be deterministic: lowest journal_entries.id first.');

        $gap = $this->matcher()->unmatchedLedgerLines($import->fresh());
        $this->assertSame([$c->id], $gap->pluck('id')->map(fn ($v) => (int) $v)->all(), 'The third, unclaimed identical line is the real unmatched-ledger exception.');

        // Re-running the same import must land on the SAME two lines (no ordering drift).
        $this->matcher()->match($import->fresh(['lines']));
        $reclaimed = $import->lines()->orderBy('row_no')->pluck('matched_journal_entry_id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([$a->id, $b->id], $reclaimed);
    }

    // ── RV-4: two candidates BOTH inside tolerance — the new solo loop must take the closest
    // (exact) one, not merely the first by id, or it consumes another row's exact counterpart and
    // silently downgrades an exact match to 'tolerance' confidence. ─────────────────────────────
    public function test_solo_loop_prefers_the_closest_candidate_not_the_first_within_tolerance(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        // Lower id is only NEAR the statement amount; the higher id is EXACT.
        $near = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.001, Carbon::create(2026, 8, 5));
        $exact = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 100.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-RV4');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-RV4', 'amount' => 100.000]);
        $this->addLine($import, 2, ['booking_ref' => 'DTW-RV4', 'amount' => 100.001]);
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(2, $result->matched);
        $lines = $import->lines()->orderBy('row_no')->get();
        $this->assertSame($exact->id, (int) $lines[0]->matched_journal_entry_id, 'Row 100.000 must claim the exact 100.000 ledger line, not the merely-within-tolerance 100.001 one.');
        $this->assertSame($near->id, (int) $lines[1]->matched_journal_entry_id);

        $this->assertSame(
            ReconciliationProposal::CONFIDENCE_EXACT,
            ReconciliationProposal::where('book_journal_entry_id', $exact->id)->value('confidence'),
            'An exact counterpart must be proposed with exact confidence, not downgraded to tolerance by a first-wins pick.'
        );
    }

    // ── RV-5: aggregate fallback AFTER partial consumption must not manufacture a false match —
    // a duplicated summary row landing on the leftovers has to stay disputed. ───────────────────
    public function test_aggregate_fallback_after_partial_consumption_does_not_false_match(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $a = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 60.000, Carbon::create(2026, 8, 5));
        $b = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 40.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-RV5');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-RV5', 'amount' => 60.000]);  // solo-matches A
        $this->addLine($import, 2, ['booking_ref' => 'DTW-RV5', 'amount' => 100.000]); // duplicate summary of A+B
        $import = $import->fresh(['lines']);

        $result = $this->matcher()->match($import);

        $this->assertSame(1, $result->matched);
        $this->assertSame(1, $result->disputed, 'The summary row overlapping an already-consumed line must be an exception, never a second match.');
        $this->assertSame(1, ReconciliationProposal::whereIn('book_journal_entry_id', [$a->id, $b->id])->count());
    }

    // ── RV-6: the consumed set is per-run and in memory only. A LATER import (corrected/extended
    // statement, different content hash) covering an already-approved ledger line must not raise
    // a second approvable proposal against it. ─────────────────────────────────────────────────
    public function test_second_import_never_raises_a_second_proposal_against_an_already_approved_ledger_line(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $je = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 45.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-RV6');

        $first = $this->makeImport($company, $supplier);
        $this->addLine($first, 1, ['booking_ref' => 'DTW-RV6', 'amount' => 45.000]);
        $this->matcher()->match($first->fresh(['lines']));

        $proposal = ReconciliationProposal::where('book_journal_entry_id', $je->id)->firstOrFail();
        app(ReconciliationProposalService::class)->approve($proposal, User::factory()->create(['role_id' => Role::ADMIN]));
        $this->assertSame(1, (int) $je->fresh()->reconciled, 'Guard on the premise: approving locks the ledger line with reconciled=1.');

        // The supplier re-sends a corrected/extended statement — a DIFFERENT content hash, so the
        // importer's idempotency does not apply, but the same booking row is on it again.
        $second = $this->makeImport($company, $supplier);
        $this->addLine($second, 1, ['booking_ref' => 'DTW-RV6', 'amount' => 45.000]);
        $this->matcher()->match($second->fresh(['lines']));

        $this->assertSame(
            1,
            ReconciliationProposal::where('book_journal_entry_id', $je->id)->count(),
            'A ledger line already carrying an approved supplier-statement proposal must never get a second, separately approvable one from a later import.'
        );
        $this->assertSame(
            0,
            ReconciliationProposal::where('book_journal_entry_id', $je->id)->where('status', ReconciliationProposal::STATUS_PENDING)->count()
        );
    }

    // ── RV-7: re-matching the SAME import after its proposal was approved must not flip a matched
    // line into a false exception (regression guard on any reconciled-aware candidate filtering).
    public function test_rematching_the_same_import_after_approval_keeps_the_line_matched(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = $this->makeSupplier();
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoiceId = $this->makeInvoice($company, $branch)->id;

        $je = $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoiceId, 77.000, Carbon::create(2026, 8, 5));
        $this->makeBooking($company->id, $invoiceId, 'DTW-RV7');

        $import = $this->makeImport($company, $supplier);
        $this->addLine($import, 1, ['booking_ref' => 'DTW-RV7', 'amount' => 77.000]);
        $this->matcher()->match($import->fresh(['lines']));

        $proposal = ReconciliationProposal::where('book_journal_entry_id', $je->id)->firstOrFail();
        app(ReconciliationProposalService::class)->approve($proposal, User::factory()->create(['role_id' => Role::ADMIN]));

        $result = $this->matcher()->match($import->fresh(['lines']));

        $this->assertSame(1, $result->matched, 'Re-matching an import whose proposal was approved must keep the line matched, not demote it to an exception.');
        $this->assertSame(0, $result->unmatchedStatement);
        $this->assertSame($je->id, (int) $import->lines()->first()->matched_journal_entry_id);
        $this->assertSame(1, ReconciliationProposal::where('book_journal_entry_id', $je->id)->count());
    }
}
