<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- MAPPING-RULES.md §4.2, the parity crux, closed
 * from the side the draft-vs-posted comparison cannot see.
 *
 * {@see \App\Services\Onboarding\Replay\LegacyReplayRunner::assertLineCountAndSums()}
 * originally compared the posted document against the DRAFT. That catches an
 * engine-generated EXTRA line, which is the failure §4.2 is written about. It
 * cannot catch the mirror-image failure: a MAPPER that silently drops a line it
 * should have mapped. The draft is the mapper's own output, so posted == draft
 * holds trivially while both are short of the staged truth -- and the mapper's
 * balance check does not save it either, because omitting a BALANCED PAIR
 * leaves Sigma-debit == Sigma-credit. The document then posts, balances,
 * satisfies every engine invariant, passes the C1 trial-balance tearDown, and
 * is silently missing money from the anchor.
 *
 * The guard now reads the staged side independently -- `stagedLineCount`
 * (a COUNT over stg_acc_detail) minus `droppedZeroLineCount` (the lines dropped
 * by rule, §1.6 (1)) -- so both directions abort.
 *
 * MUTATION PROOF (verified 2026-09-07): omit a balanced pair inside
 * LegacyDocumentMapper::map()'s line loop without counting it as dropped.
 * BEFORE the guard: the whole LP3 suite stays green and this file's first test
 * is the only thing that fails. AFTER the guard: the run aborts naming the
 * document, 4 staged lines against 2 posted.
 */
class LegacyLineCountParityTest extends LegacyReplayTestCase
{
    /**
     * Two balanced pairs. Dropping either pair leaves the remainder balanced --
     * exactly the shape no balance check can see.
     */
    private function fourLineDocument(string $docDate = '2025-04-01'): int
    {
        $docId = $this->stageHeader([
            'SubType' => 'INV',
            'DocType' => 'INV',
            'DocDt' => $docDate,
            'DocNo' => 'INV/CO/25/4LINE',
        ]);

        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '10.000', 'DC' => 'D', 'DocDt' => $docDate]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '10.000', 'DC' => 'C', 'DocDt' => $docDate]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Debit' => '7.500', 'DC' => 'D', 'DocDt' => $docDate]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '7.500', 'DC' => 'C', 'DocDt' => $docDate]);

        return $docId;
    }

    /**
     * The regression test proper: every non-zero staged line reaches the ledger,
     * asserted against `journal_entries` itself rather than against the audit's
     * own bookkeeping.
     */
    public function test_every_non_zero_staged_line_reaches_the_ledger(): void
    {
        $docId = $this->fourLineDocument();

        $this->assertSame(1, $this->replay()['posted']);

        $audit = $this->auditFor($docId);

        $this->assertSame('posted', (string) $audit->status);
        $this->assertSame(4, (int) $audit->staged_line_count);
        $this->assertSame(0, (int) $audit->dropped_zero_line_count);
        $this->assertSame(4, (int) $audit->posted_line_count);
        $this->assertSame(
            4,
            DB::table('journal_entries')->where('transaction_id', $audit->document_id)->count(),
            '§4.2: a four-line legacy document posts exactly four journal lines.'
        );
        $this->assertSame('17.500', (string) $audit->staged_debit_sum);
        $this->assertSame('17.500', (string) $audit->posted_debit_sum);
    }

    /**
     * Zero-amount lines are dropped by rule (§1.6 (1)), so the guard's
     * arithmetic must subtract them -- otherwise every BDS document (1,248
     * zero-amount placeholder lines export-wide) would abort the run.
     */
    public function test_the_guard_subtracts_rule_dropped_zero_lines(): void
    {
        $docDate = '2025-05-02';
        $docId = $this->stageHeader(['SubType' => 'BDS', 'DocType' => 'BDS', 'DocDt' => $docDate, 'DocNo' => 'BDS/CO/25/Z']);

        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Debit' => '4.000', 'DC' => 'D', 'DocDt' => $docDate]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '4.000', 'DC' => 'C', 'DocDt' => $docDate]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Debit' => '0.000', 'Credit' => '0.000', 'DC' => 'D', 'DocDt' => $docDate]);

        $this->assertSame(1, $this->replay()['posted']);

        $audit = $this->auditFor($docId);

        $this->assertSame(3, (int) $audit->staged_line_count);
        $this->assertSame(1, (int) $audit->dropped_zero_line_count);
        $this->assertSame(2, (int) $audit->posted_line_count);
    }
}
