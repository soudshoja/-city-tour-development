<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\JournalEntry;
use App\Services\Onboarding\Replay\LegacyDocumentMapper;
use App\Services\Onboarding\Replay\LegacyDocumentRefused;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * legacy-ledger-pilot LP1e, ruling R-currency — an FC currency that could not
 * be DERIVED is METADATA, not a refusal.
 *
 * Why the rule changed. `map_currency` is built from line usage
 * ({@see \App\Services\Onboarding\LegacyCurrencyMapper}) because the master
 * those line FKs point at (`tblMaster`) was never exported — the audit's own
 * `key_space_overlap: 0` says so. On the real export 25 of the 27 distinct
 * `FcCurrID_FK` values legitimately derive to nothing. The pre-LP1e mapper
 * refused every document carrying such a line, which would have refused
 * documents whose KWD double entry is complete, balanced, and exactly what
 * every trial-balance anchor measures — to protect a descriptive FC pair.
 *
 * So the line posts in the base currency and the FK is recorded as
 * `legacy_curr_<fk>`. TWO things still refuse: a POISON currency (R3, covered
 * in {@see LegacyDocumentMapperTest}) and a line with no LC amount at all.
 */
class LegacyUnresolvedCurrencyTest extends LegacyReplayTestCase
{
    private const LEGACY_UNRESOLVED_CURR = 1026;

    private const LEGACY_ABSENT_CURR = 4337;

    /**
     * The ordinary case: `LegacyCurrencyMapper` wrote a row, derivation found
     * nothing, `status = 'unresolved'`. The document POSTS.
     */
    public function test_a_line_with_an_unresolved_currency_posts_in_the_base_currency_and_records_the_fk(): void
    {
        $this->mapLegacyCurrency($this->company->id, self::LEGACY_UNRESOLVED_CURR, null, false, 'unresolved');

        $docId = $this->stageHeader(['SubType' => 'BPV', 'DocType' => 'PV', 'DocNo' => 'BPV/CO/25/unres']);

        $fcLine = $this->stageLine($docId, [
            'AccID_FK' => self::LEGACY_SUPPLIER_ACC,
            'Debit' => '30.750',
            'DC' => 'D',
            'FCDebit' => '100.000',
            'FCCredit' => '0.000',
            'FcCurrID_FK' => (string) self::LEGACY_UNRESOLVED_CURR,
            'FcExchRate' => '0.307500000000',
        ]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Credit' => '30.750', 'DC' => 'C']);

        $summary = $this->replay();

        $this->assertSame(1, $summary['posted'], 'an unresolved FC currency must not refuse a document whose LC entry is complete');
        $this->assertSame(0, $summary['refused']);

        $audit = DB::connection('legacy_pilot')->table('map_document_line')
            ->where('legacy_acc_detail_id', $fcLine)->first();

        $this->assertSame('legacy_curr_'.self::LEGACY_UNRESOLVED_CURR, $audit->metadata_currency);
        $this->assertSame(self::LEGACY_UNRESOLVED_CURR, (int) $audit->legacy_fc_curr_id_fk, 'the FK itself is still recorded');
        $this->assertSame('0.307500000000', (string) $audit->legacy_fc_exch_rate, 'so is the rate the line carried');
        $this->assertSame('KWD', $audit->posted_currency);
        $this->assertFalse((bool) $audit->fc_rebased_to_kwd, 'this is not the RJV zero-FC re-base; it is a currency that could not be named');

        $this->assertSame(1, (int) $this->auditFor($docId)->currency_unresolved_line_count, 'the document counts its unresolved-currency lines');

        // The posting itself is an ordinary base-currency line.
        $line = JournalEntry::withoutGlobalScopes()->findOrFail((int) $audit->our_journal_entry_id);
        $this->assertSame('KWD', $line->currency);
        $this->assertSame('30.750', (string) $line->debit);
        $this->assertSame('1.000000', (string) $line->exchange_rate);
    }

    /**
     * An FK with NO map_currency row at all behaves identically. Before LP1e
     * this was the refusal `legacy.currency_unmapped`; a missing row and an
     * `unresolved` row mean the same thing to a replay — nobody could name the
     * currency — and the ruling is about the FC pair, not about which of the
     * two shapes the map is in.
     */
    public function test_an_absent_map_currency_row_behaves_the_same_as_an_unresolved_one(): void
    {
        $docId = $this->stageHeader(['SubType' => 'BPV', 'DocType' => 'PV', 'DocNo' => 'BPV/CO/25/absent']);

        $fcLine = $this->stageLine($docId, [
            'AccID_FK' => self::LEGACY_SUPPLIER_ACC,
            'Debit' => '12.000',
            'DC' => 'D',
            'FCDebit' => '40.000',
            'FcCurrID_FK' => (string) self::LEGACY_ABSENT_CURR,
            'FcExchRate' => '0.300000000000',
        ]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Credit' => '12.000', 'DC' => 'C']);

        $this->assertSame(1, $this->replay()['posted']);

        $audit = DB::connection('legacy_pilot')->table('map_document_line')
            ->where('legacy_acc_detail_id', $fcLine)->first();

        $this->assertSame('legacy_curr_'.self::LEGACY_ABSENT_CURR, $audit->metadata_currency);
        $this->assertSame('KWD', $audit->posted_currency);
    }

    /** A line whose currency DID resolve is untouched by any of this. */
    public function test_a_resolved_currency_still_posts_as_a_real_fc_line(): void
    {
        $docId = $this->stageHeader(['SubType' => 'BPV', 'DocType' => 'PV', 'DocNo' => 'BPV/CO/25/usd']);

        $fcLine = $this->stageLine($docId, [
            'AccID_FK' => self::LEGACY_SUPPLIER_ACC,
            'Debit' => '30.750',
            'DC' => 'D',
            'FCDebit' => '100.000',
            'FcCurrID_FK' => (string) self::LEGACY_USD_CURR,
            'FcExchRate' => '0.307500000000',
        ]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Credit' => '30.750', 'DC' => 'C']);

        $this->assertSame(1, $this->replay()['posted']);

        $audit = DB::connection('legacy_pilot')->table('map_document_line')
            ->where('legacy_acc_detail_id', $fcLine)->first();

        $this->assertNull($audit->metadata_currency, 'a derived currency leaves no metadata trace — it IS the posted currency');
        $this->assertSame('USD', $audit->posted_currency);
        $this->assertSame(0, (int) $this->auditFor($docId)->currency_unresolved_line_count);
    }

    /**
     * THE OTHER HALF OF THE RULING — refuse when the LC amount is missing,
     * because then the FC pair was the only money on the line and posting a
     * zero base-currency line would silently lose it.
     *
     * Exercised through the private method directly, and deliberately so:
     * `mapLine()` drops a both-columns-zero line as a §1.6 zero-amount
     * placeholder BEFORE currency resolution is reached, so this guard is not
     * reachable through the public path today. That is exactly why it is
     * tested here rather than assumed — a later change to the zero-line rule
     * must not silently turn "no LC" into "posts 0.000 KWD".
     */
    public function test_an_unresolved_currency_with_no_lc_amount_is_refused(): void
    {
        $this->mapLegacyCurrency($this->company->id, self::LEGACY_UNRESOLVED_CURR, null, false, 'unresolved');

        $mapper = app(LegacyDocumentMapper::class);
        $mapper->prepare($this->company->id);

        $resolve = new ReflectionMethod($mapper, 'resolveCurrency');
        $resolve->setAccessible(true);

        $line = (object) [
            'fccurrid_fk' => (string) self::LEGACY_UNRESOLVED_CURR,
            'fcexchrate' => '0.307500000000',
            'fcdebit' => '100.000',
            'fccredit' => '0.000',
        ];

        // Same line, an LC amount present: metadata, no refusal.
        $resolved = $resolve->invoke($mapper, $line, 4242, 'doc 4242 line 1', 30750);
        $this->assertSame('legacy_curr_'.self::LEGACY_UNRESOLVED_CURR, $resolved['metadata_currency']);
        $this->assertSame('KWD', $resolved['currency']);

        // No LC amount: refused.
        $this->expectException(LegacyDocumentRefused::class);
        $this->expectExceptionMessageMatches('/no LC amount/');

        $resolve->invoke($mapper, $line, 4242, 'doc 4242 line 1', 0);
    }
}
