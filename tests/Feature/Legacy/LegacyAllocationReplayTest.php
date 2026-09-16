<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Onboarding\Replay\LegacyAllocationReplayer;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- MAPPING-RULES.md §3, `tblAccIsApply` replay.
 *
 * See {@see LegacyAllocationReplayer}'s docblock for the one documented
 * deviation (the pilot's own `map_allocation` register instead of Akeed's
 * `payment_applications`, which is keyed on payments/invoices that hold nothing
 * for this data) and why §3.3 makes it harmless: an allocation carries no ledger
 * money, so a mismatched one cannot move a trial balance.
 */
class LegacyAllocationReplayTest extends LegacyReplayTestCase
{
    /**
     * @return array{0:int,1:int,2:int} [invoice doc, invoice customer line, receipt customer line]
     */
    private function stageInvoiceAndReceipt(): array
    {
        $invoice = $this->stageHeader(['SubType' => 'INV', 'DocType' => 'INV', 'DocDt' => '2025-03-01', 'DocNo' => 'INV/CO/25/1']);
        $invoiceCustomerLine = $this->stageLine($invoice, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '100.000', 'DC' => 'D', 'DocDt' => '2025-03-01', 'DebitAdj' => '40.000']);
        $this->stageLine($invoice, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '100.000', 'DC' => 'C', 'DocDt' => '2025-03-01']);

        $receipt = $this->stageHeader(['SubType' => 'CRV', 'DocType' => 'RV', 'DocDt' => '2025-03-05', 'DocNo' => 'CRV/CO/25/1']);
        $this->stageLine($receipt, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Debit' => '40.000', 'DC' => 'D', 'DocDt' => '2025-03-05']);
        $receiptCustomerLine = $this->stageLine($receipt, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Credit' => '40.000', 'DC' => 'C', 'DocDt' => '2025-03-05', 'CreditAdj' => '40.000']);

        return [$invoice, $invoiceCustomerLine, $receiptCustomerLine];
    }

    /**
     * §3.2: after both sides are posted, each staged row resolves to a
     * journal-line pair, in ModDt order, with the amount replayed AS RECORDED
     * (never re-derived, never re-auto-allocated).
     */
    public function test_an_allocation_between_two_replayed_lines_is_applied(): void
    {
        [$invoice, $invoiceLine, $receiptLine] = $this->stageInvoiceAndReceipt();

        $this->stageAllocation([
            'SourceDocID_FK' => $receiptLine, // any doc id; the LINE ids are what resolve
            'SourceAccDetailID_FK' => $receiptLine,
            'AppliedDocID_FK' => $invoice,
            'AppliedAccDetailID_FK' => $invoiceLine,
            'AccID_FK' => (string) self::LEGACY_CUSTOMER_ACC,
            'Amount' => '40.000',
            'sourceDC' => 'D',
        ]);

        $this->assertSame(2, $this->replay()['posted']);

        $summary = app(LegacyAllocationReplayer::class)->run(['company_id' => $this->company->id]);

        $this->assertSame(1, $summary['seen']);
        $this->assertSame(1, $summary['applied']);
        $this->assertSame(0, $summary['tagged']);
        $this->assertSame(['40.000'], array_values($summary['applied_amount_by_account']));

        $row = DB::connection('legacy_pilot')->table('map_allocation')->first();
        $this->assertSame('applied', $row->status);
        $this->assertSame('legacy:apply:'.$receiptLine.':'.$invoiceLine, $row->idempotency_key);
        $this->assertNotNull($row->source_journal_entry_id);
        $this->assertNotNull($row->applied_journal_entry_id);
    }

    /**
     * §3.3 / §3.2 (4): "allocations carry no ledger money; a mismatched
     * allocation cannot move a trial balance" -- and legacy's own apply path
     * creates realised-FX RJVs as a side effect, which ours must NOT, because
     * the RJVs are already replayed as documents (§2.7).
     *
     * {@see LegacyAllocationReplayer} satisfies that structurally: it writes
     * only `map_allocation` and never reaches RealisedFxService or any apply
     * entry point. Structural guarantees are the ones worth pinning, because
     * nothing about the class's shape announces itself as load-bearing -- a
     * later "just post the FX difference here" edit would look reasonable and
     * would silently break every LP4 anchor.
     */
    public function test_applying_allocations_moves_no_ledger_money(): void
    {
        [$invoice, $invoiceLine, $receiptLine] = $this->stageInvoiceAndReceipt();

        $this->stageAllocation([
            'SourceDocID_FK' => $receiptLine,
            'SourceAccDetailID_FK' => $receiptLine,
            'AppliedDocID_FK' => $invoice,
            'AppliedAccDetailID_FK' => $invoiceLine,
            'AccID_FK' => (string) self::LEGACY_CUSTOMER_ACC,
            'Amount' => '40.000',
            'sourceDC' => 'D',
        ]);

        $this->assertSame(2, $this->replay()['posted']);

        $transactions = Transaction::withoutGlobalScopes()->count();
        $lines = JournalEntry::withoutGlobalScopes()->count();
        $debits = (string) JournalEntry::withoutGlobalScopes()->sum('debit');
        $credits = (string) JournalEntry::withoutGlobalScopes()->sum('credit');

        $summary = app(LegacyAllocationReplayer::class)->run(['company_id' => $this->company->id]);
        $this->assertSame(1, $summary['applied']);

        $this->assertSame($transactions, Transaction::withoutGlobalScopes()->count(), 'the apply pass must create no transaction — an RJV here would double-count §2.7');
        $this->assertSame($lines, JournalEntry::withoutGlobalScopes()->count(), 'the apply pass must create no journal line');
        $this->assertSame($debits, (string) JournalEntry::withoutGlobalScopes()->sum('debit'));
        $this->assertSame($credits, (string) JournalEntry::withoutGlobalScopes()->sum('credit'));
    }

    /**
     * §8.1: allocations get their own key namespace so LP3's resumability has the
     * same guarantee the seam gives documents -- a re-run applies nothing twice.
     */
    public function test_a_second_allocation_run_applies_nothing_twice(): void
    {
        [$invoice, $invoiceLine, $receiptLine] = $this->stageInvoiceAndReceipt();

        $this->stageAllocation([
            'SourceDocID_FK' => $receiptLine,
            'SourceAccDetailID_FK' => $receiptLine,
            'AppliedDocID_FK' => $invoice,
            'AppliedAccDetailID_FK' => $invoiceLine,
            'AccID_FK' => (string) self::LEGACY_CUSTOMER_ACC,
            'Amount' => '40.000',
        ]);

        $this->replay();

        app(LegacyAllocationReplayer::class)->run(['company_id' => $this->company->id]);
        $second = app(LegacyAllocationReplayer::class)->run(['company_id' => $this->company->id]);

        $this->assertSame(0, $second['applied']);
        $this->assertSame(1, $second['already_applied']);
        $this->assertSame(1, DB::connection('legacy_pilot')->table('map_allocation')->count());
    }

    /**
     * §3.3: "an endpoint that resolves to no posted line" is a TAG, not a stop
     * and not a guess. A dropped zero-amount line or a skipped document is
     * exactly how this arises in the real set.
     */
    public function test_an_unresolvable_endpoint_is_tagged_not_guessed(): void
    {
        [$invoice, $invoiceLine, $receiptLine] = $this->stageInvoiceAndReceipt();

        $this->stageAllocation([
            'SourceDocID_FK' => $receiptLine,
            'SourceAccDetailID_FK' => $receiptLine,
            'AppliedDocID_FK' => $invoice,
            'AppliedAccDetailID_FK' => '999999', // never replayed
            'AccID_FK' => (string) self::LEGACY_CUSTOMER_ACC,
            'Amount' => '40.000',
        ]);

        $this->replay();

        $summary = app(LegacyAllocationReplayer::class)->run(['company_id' => $this->company->id]);

        $this->assertSame(0, $summary['applied']);
        $this->assertSame(1, $summary['tagged']);
        $this->assertSame(['applied_line_unresolved' => 1], $summary['tag_reasons']);

        $row = DB::connection('legacy_pilot')->table('map_allocation')->first();
        $this->assertSame('tagged', $row->status);
        $this->assertSame('applied_line_unresolved', $row->failure_code);
    }

    /** §3.3: an allocation exceeding the applied line's own amount is tagged. */
    public function test_an_over_applied_allocation_is_tagged(): void
    {
        [$invoice, $invoiceLine, $receiptLine] = $this->stageInvoiceAndReceipt();

        $this->stageAllocation([
            'SourceDocID_FK' => $receiptLine,
            'SourceAccDetailID_FK' => $receiptLine,
            'AppliedDocID_FK' => $invoice,
            'AppliedAccDetailID_FK' => $invoiceLine,
            'AccID_FK' => (string) self::LEGACY_CUSTOMER_ACC,
            'Amount' => '500.000',
        ]);

        $this->replay();

        $summary = app(LegacyAllocationReplayer::class)->run(['company_id' => $this->company->id]);

        $this->assertSame(1, $summary['tagged']);
        $this->assertArrayHasKey('over_applied', $summary['tag_reasons']);
    }

    /**
     * §3.2 (1): ModDt ascending, tie-broken by staged insertion order.
     * Chronological order matters because partial allocations against a running
     * balance are order-dependent.
     */
    public function test_allocations_are_replayed_in_mod_dt_order(): void
    {
        [$invoice, $invoiceLine, $receiptLine] = $this->stageInvoiceAndReceipt();

        // Staged out of order on purpose.
        $this->stageAllocation([
            'SourceDocID_FK' => $receiptLine, 'SourceAccDetailID_FK' => $receiptLine,
            'AppliedDocID_FK' => $invoice, 'AppliedAccDetailID_FK' => $invoiceLine,
            'AccID_FK' => (string) self::LEGACY_CUSTOMER_ACC, 'Amount' => '25.000', 'ModDt' => '2025-06-01 10:00:00',
        ]);
        $this->stageAllocation([
            'SourceDocID_FK' => $receiptLine, 'SourceAccDetailID_FK' => $receiptLine,
            'AppliedDocID_FK' => $invoice, 'AppliedAccDetailID_FK' => '888888',
            'AccID_FK' => (string) self::LEGACY_CUSTOMER_ACC, 'Amount' => '15.000', 'ModDt' => '2025-05-01 10:00:00',
        ]);

        $this->replay();

        // limit=1 takes the FIRST row in ModDt order — the May one, which is the
        // one staged second.
        $summary = app(LegacyAllocationReplayer::class)->run(['company_id' => $this->company->id, 'limit' => 1]);

        $this->assertSame(1, $summary['seen']);
        $this->assertSame(1, DB::connection('legacy_pilot')->table('map_allocation')->count());
        $this->assertSame('15.000', (string) DB::connection('legacy_pilot')->table('map_allocation')->value('amount'));
    }
}
