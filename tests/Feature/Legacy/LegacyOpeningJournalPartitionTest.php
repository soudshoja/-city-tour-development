<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Onboarding\Replay\LegacyReplayAborted;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- the opening/movement partition contract LP4's
 * parity harness depends on.
 *
 * ── The defect this pins (coordinator, 2026-09-08, found by LP4) ─────────────
 * MAPPING-RULES.md §9.1 defines the opening position as lines dated
 * `< 2025-01-01`, and period movement as 2025 lines with
 * `sub_type <> 'LEGACY_OJV'`. But the 2025 OJV is dated 2025-01-01 -- INSIDE
 * the range -- so taken literally the entire opening position falls into
 * NEITHER bucket and silently vanishes from every anchor.
 *
 * The corrected partition, implemented by LP4's `LedgerFigures::openingNet` on
 * feat/lp4-legacy-parity, is:
 *
 *     opening  = lines dated BEFORE the period start
 *                OR lines of an opening-journal document (sub_type LEGACY_OJV)
 *                   dated IN range
 *     movement = everything else in range
 *
 * That partition is only well-defined if the replay guarantees three things
 * about the opening document, which is what this file asserts:
 *   1. it posts with `transactions.sub_type` exactly `'LEGACY_OJV'` -- LP4 keys
 *      on that literal string, so a rename here would break the harness
 *      silently rather than loudly;
 *   2. `transaction_date` AND `posting_date` are both 2025-01-01 -- a shift on
 *      either moves the opening position into another period while
 *      TrialBalanceService buckets by COALESCE(posting_date, transaction_date);
 *   3. the 2026 OJV is never posted, because a second LEGACY_OJV document in
 *      the books would be counted as opening too and double the position.
 *
 * MUTATION PROOFS (both verified):
 *   - change config('legacy_pilot.replay.doc_type_map')['OJV']['sub_type'] away
 *     from 'LEGACY_OJV' and two tests here fail.
 *   - drop 2026 from `withheld_ojv_doc_years` and
 *     {@see self::test_a_2026_opening_journal_misdated_into_the_window_is_still_withheld()}
 *     fails. Note that the FIRST test below does NOT catch that mutation, and
 *     that is by design rather than a gap: a 2026 OJV correctly dated
 *     2026-01-01 is already outside the selector's window, so the withhold list
 *     is the SECOND of two independent defences. Only a MIS-DATED 2026 header —
 *     the shape that would actually slip through — exercises it, which is why
 *     that test exists as a separate case.
 */
class LegacyOpeningJournalPartitionTest extends LegacyReplayTestCase
{
    private function stageOpeningJournal(string $docDate, string $docYear, string $amount): int
    {
        $docId = $this->stageHeader([
            'SubType' => 'OJV',
            'DocType' => 'JV',
            'DocDt' => $docDate,
            'DocYear' => $docYear,
            'DocNo' => 'OJV/CO/'.$docYear,
            'Narration' => 'Being Opening Balance',
        ]);

        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => $amount, 'DC' => 'D', 'DocDt' => $docDate]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => $amount, 'DC' => 'C', 'DocDt' => $docDate]);

        return $docId;
    }

    /**
     * The whole contract in one test: the 2025 OJV is the ONLY LEGACY_OJV
     * document in the books, it is dated 2025-01-01 on both date columns, and
     * LP4's corrected partition puts all of it in `opening` and none of it in
     * `movement`.
     */
    public function test_the_2025_opening_journal_satisfies_lp4s_opening_movement_partition(): void
    {
        $opening = $this->stageOpeningJournal('2025-01-01', '2025', '500.000');
        $withheld = $this->stageOpeningJournal('2026-01-01', '2026', '900.000');

        // Ordinary 2025 movement, so the partition has something to separate.
        $movement = $this->stageHeader(['SubType' => 'INV', 'DocType' => 'INV', 'DocDt' => '2025-05-05', 'DocNo' => 'INV/CO/25/1']);
        $this->stageLine($movement, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '20.000', 'DC' => 'D', 'DocDt' => '2025-05-05']);
        $this->stageLine($movement, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '20.000', 'DC' => 'C', 'DocDt' => '2025-05-05']);

        $summary = $this->replay();

        $this->assertSame(2, $summary['posted']);
        $this->assertNull(
            $this->auditFor($withheld),
            'the 2026 OJV must never be posted — a second LEGACY_OJV would be counted as opening and double the position'
        );

        // (1) the engine sub_type LP4 keys on, exactly.
        $ojv = Transaction::withoutGlobalScopes()->where('idempotency_key', 'legacy:OJV:'.$opening)->firstOrFail();
        $this->assertSame('LEGACY_OJV', $ojv->sub_type);
        $this->assertSame('OJV', $ojv->doc_type);
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('sub_type', 'LEGACY_OJV')->count(),
            'exactly one opening-journal document may exist in the replayed books'
        );

        // (2) both date columns are 2025-01-01.
        $this->assertSame('2025-01-01', \Carbon\CarbonImmutable::parse((string) $ojv->transaction_date)->toDateString());
        $this->assertSame('2025-01-01', \Carbon\CarbonImmutable::parse((string) $ojv->posting_date)->toDateString());

        foreach (JournalEntry::withoutGlobalScopes()->where('transaction_id', $ojv->id)->get() as $line) {
            $this->assertSame('2025-01-01', \Carbon\CarbonImmutable::parse((string) $line->transaction_date)->toDateString());
            $this->assertSame('2025-01-01', \Carbon\CarbonImmutable::parse((string) $line->posting_date)->toDateString());
        }

        // (3) LP4's corrected partition, evaluated here exactly as
        // LedgerFigures::openingNet does it.
        $this->assertSame('500.000', $this->netFor('2025-01-01', '2025-12-31', opening: true), 'the whole opening position lands in the opening bucket');
        $this->assertSame('20.000', $this->netFor('2025-01-01', '2025-12-31', opening: false), 'and none of it leaks into period movement');

        // The literal §9.1 reading -- "dated before period start" alone -- is the
        // defect: it puts the opening position in NO bucket at all.
        $this->assertSame(
            '0.000',
            $this->sumSigned(fn ($q) => $q->whereRaw('COALESCE(je.posting_date, je.transaction_date) < ?', ['2025-01-01'])),
            'nothing is dated before 2025-01-01 — which is exactly why the literal §9.1 definition loses the opening position'
        );
    }

    /**
     * The shape the withhold list actually exists for. A 2026 opening journal
     * correctly dated 2026-01-01 is already outside the selector's window, so
     * the date check alone would keep it out. A header carrying `DocYear = 2026`
     * but a DocDt INSIDE the 2025 window is the one that would slip through --
     * and it must still never be posted, because a second LEGACY_OJV document
     * would be counted as opening by LP4's partition and double the position.
     *
     * The assertion is deliberately "never selected", not "refused": a refusal
     * would trip OJV's own type stop (population of one), which is a louder and
     * different failure from a deliberate withhold.
     */
    public function test_a_2026_opening_journal_misdated_into_the_window_is_still_withheld(): void
    {
        $opening = $this->stageOpeningJournal('2025-01-01', '2025', '500.000');
        $misdated = $this->stageHeader([
            'SubType' => 'OJV',
            'DocType' => 'JV',
            'DocDt' => '2025-01-01',   // inside the window
            'DocYear' => '2026',        // but it is the WITHHELD year's opening journal
            'DocNo' => 'OJV/CO/2026',
            'Narration' => 'Being Opening Balance',
        ]);
        $this->stageLine($misdated, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '900.000', 'DC' => 'D', 'DocDt' => '2025-01-01']);
        $this->stageLine($misdated, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '900.000', 'DC' => 'C', 'DocDt' => '2025-01-01']);

        $summary = $this->replay();

        $this->assertSame(1, $summary['selected'], 'the withheld OJV must not even be selected');
        $this->assertSame(1, $summary['posted']);
        $this->assertSame(0, $summary['refused']);
        $this->assertSame([], $summary['type_stops']);
        $this->assertNull($this->auditFor($misdated));

        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('sub_type', 'LEGACY_OJV')->count());
        $this->assertSame('legacy:OJV:'.$opening, Transaction::withoutGlobalScopes()->where('sub_type', 'LEGACY_OJV')->value('idempotency_key'));
        $this->assertSame('500.000', $this->netFor('2025-01-01', '2025-12-31', opening: true));
    }

    /**
     * The 2025 OJV posts on 2025-01-01, so January must be open. Were it not,
     * PostingService would SHIFT the posting date and the opening position would
     * silently relocate to February -- the §6 trap, aimed at the one document
     * every anchor is defined relative to.
     */
    public function test_a_closed_january_cannot_silently_move_the_opening_position(): void
    {
        $this->stageOpeningJournal('2025-01-01', '2025', '500.000');

        AccountingPeriod::create([
            'company_id' => $this->company->id,
            'year' => 2025,
            'month' => 1,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);

        $this->expectException(LegacyReplayAborted::class);
        $this->expectExceptionMessageMatches('/01=locked/');

        $this->replay();
    }

    /** map_document carries the column names LP4 reads it by. */
    public function test_map_document_exposes_the_columns_lp4_reads(): void
    {
        $opening = $this->stageOpeningJournal('2025-01-01', '2025', '500.000');

        $this->replay();

        $row = DB::connection('legacy_pilot')->table('map_document')->where('legacy_doc_id', $opening)->first();

        $this->assertSame('OJV', $row->sub_type, 'the LEGACY SubType token');
        $this->assertSame('LEGACY_OJV', $row->engine_sub_type, 'and the Akeed-side value LP4 partitions on');
        $this->assertSame($opening, (int) $row->legacy_doc_id);
        $this->assertSame('posted', $row->status);
        $this->assertSame('2025-01-01', substr((string) $row->doc_dt, 0, 10));
        $this->assertSame(
            (int) Transaction::withoutGlobalScopes()->where('sub_type', 'LEGACY_OJV')->value('id'),
            (int) $row->document_id
        );
    }

    /**
     * LP4's partition, expressed here independently of LP4's own code, so this
     * test fails if the replay ever stops satisfying it.
     */
    private function netFor(string $start, string $end, bool $opening): string
    {
        return $this->sumSigned(function ($q) use ($start, $end, $opening): void {
            if ($opening) {
                $q->where(function ($w) use ($start, $end): void {
                    $w->whereRaw('COALESCE(je.posting_date, je.transaction_date) < ?', [$start])
                        ->orWhere(function ($o) use ($start, $end): void {
                            $o->where('t.sub_type', 'LEGACY_OJV')
                                ->whereRaw('COALESCE(je.posting_date, je.transaction_date) BETWEEN ? AND ?', [$start, $end]);
                        });
                });

                return;
            }

            $q->whereRaw('COALESCE(je.posting_date, je.transaction_date) BETWEEN ? AND ?', [$start, $end])
                ->where(function ($w): void {
                    $w->whereNull('t.sub_type')->orWhere('t.sub_type', '!=', 'LEGACY_OJV');
                });
        });
    }

    private function sumSigned(callable $constrain): string
    {
        $query = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $this->company->id)
            ->where('je.account_id', $this->receivableControl->id)
            ->whereNull('je.deleted_at');

        $constrain($query);

        return number_format((float) $query->sum(DB::raw('je.debit - je.credit')), 3, '.', '');
    }
}
