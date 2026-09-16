<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- the replay has to finish 30,612 documents /
 * 179,421 lines in ONE UNATTENDED RUN (PLAN.md §LP3 acceptance). That is an
 * acceptance criterion about SHAPE, not about wall-clock: a per-line lookup of
 * accounts, parties, branches or currencies turns a 30-minute run into an
 * overnight one and, worse, makes the cost superlinear in exactly the dimension
 * the real dataset is biggest in.
 *
 * {@see \App\Services\Onboarding\Replay\LegacyDocumentMapper::prepare()} loads
 * `legacy_acc_map` (1,351 rows), `map_branch` (4) and `map_currency` (21) into
 * memory ONCE per run and never re-reads them. This file is what stops that
 * from being quietly undone: it counts the queries the replay actually issues
 * and asserts the per-document cost does not grow when the LINE count grows.
 *
 * The oracle is deliberately a DIFFERENCE rather than an absolute budget. An
 * absolute number would have to be re-tuned on every unrelated engine change
 * and would end up loosened until it proved nothing; the difference between a
 * 2-line document and a 10-line document is zero if and only if nothing is
 * looked up per line, which is the actual invariant.
 *
 * It counts READS ONLY, and that distinction is the whole point. The replay
 * necessarily issues one `insert into journal_entries` per line -- that is
 * PostingService step 8 writing the ledger, the work itself, and it is exactly
 * linear in lines by definition. A first version of this test counted every
 * query and "caught" that insert, which is the classic way a performance oracle
 * ends up measuring the workload instead of the defect. What must NOT grow with
 * line count is the number of SELECTs.
 */
class LegacyReplayThroughputTest extends LegacyReplayTestCase
{
    /**
     * Stage `$documents` documents of `$linesPerDocument` lines each
     * (balanced: half debit, half credit), in one bulk insert per table so the
     * fixture cost does not dominate the measurement.
     */
    private function stageBulk(int $documents, int $linesPerDocument, int $firstDocId): void
    {
        $headers = [];
        $lines = [];
        $detailId = $firstDocId * 1000;

        for ($d = 0; $d < $documents; $d++) {
            $docId = $firstDocId + $d;
            $docDate = sprintf('2025-%02d-%02d', ($d % 12) + 1, ($d % 28) + 1);

            $headers[] = [
                'CompanyID' => '1', 'BranchID_FK' => '1', 'DocID' => (string) $docId,
                'DocNo' => 'INV/CO/25/'.$docId, 'DocType' => 'INV', 'SubType' => 'INV',
                'DocDt' => $docDate, 'RefNo' => '', 'RefCode' => '', 'RefType' => 'SINV',
                'Narration' => 'INV-'.$docId, 'Posted' => 'True', 'DocYear' => '2025',
            ];

            $pairs = intdiv($linesPerDocument, 2);

            for ($p = 0; $p < $pairs; $p++) {
                foreach ([['Debit', self::LEGACY_CUSTOMER_ACC, 'D'], ['Credit', self::LEGACY_INCOME_ACC, 'C']] as [$column, $account, $dc]) {
                    $lines[] = [
                        'CompanyID' => '1', 'AccDetailID' => (string) ++$detailId,
                        'DocID_FK' => (string) $docId, 'DocDt' => $docDate, 'BranchID_FK' => '1',
                        'FCDebit' => '0.000', 'FCCredit' => '0.000', 'FcCurrID_FK' => '',
                        'FcExchRate' => '1.000000000000',
                        'Debit' => $column === 'Debit' ? '1.000' : '0.000',
                        'Credit' => $column === 'Credit' ? '1.000' : '0.000',
                        'DC' => $dc, 'DebitAdj' => '0.000', 'CreditAdj' => '0.000',
                        'AccID_FK' => (string) $account, 'Narration' => 'L'.$detailId,
                    ];
                }
            }
        }

        foreach (array_chunk($headers, 200) as $chunk) {
            DB::connection('legacy_pilot')->table('stg_acc_header')->insert($chunk);
        }

        foreach (array_chunk($lines, 200) as $chunk) {
            DB::connection('legacy_pilot')->table('stg_acc_detail')->insert($chunk);
        }
    }

    /** @return array{queries:int,summary:array<string,mixed>,seconds:float} */
    private function measure(): array
    {
        // READS ONLY -- see the class docblock. The one INSERT per journal line
        // is the work, not a lookup.
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries++;
            }
        });

        $startedAt = microtime(true);
        $summary = $this->replay();
        $seconds = microtime(true) - $startedAt;

        return ['queries' => $queries, 'summary' => $summary, 'seconds' => $seconds];
    }

    /**
     * The invariant: the maps are preloaded, so widening a document from 2
     * lines to 10 must not add a single lookup. If someone replaces
     * `$this->accountMap[$id]` with a query, this is where it shows up --
     * as 8 extra queries per document, 179,421 extra queries over the real run.
     */
    public function test_document_cost_does_not_grow_with_line_count(): void
    {
        $documents = 25;

        $this->stageBulk($documents, 2, 100000);
        $narrow = $this->measure();
        $this->assertSame($documents, $narrow['summary']['posted']);

        // A second, disjoint population of the SAME document count but five
        // times the lines. Anything looked up per line scales with this.
        DB::connection('legacy_pilot')->table('map_document')->truncate();
        DB::connection('legacy_pilot')->table('map_document_line')->truncate();
        DB::connection('legacy_pilot')->table('stg_acc_header')->truncate();
        DB::connection('legacy_pilot')->table('stg_acc_detail')->truncate();

        $this->stageBulk($documents, 10, 200000);
        $wide = $this->measure();
        $this->assertSame($documents, $wide['summary']['posted']);

        $perDocumentNarrow = $narrow['queries'] / $documents;
        $perDocumentWide = $wide['queries'] / $documents;

        $this->assertLessThanOrEqual(
            $perDocumentNarrow + 1.0,
            $perDocumentWide,
            sprintf(
                'Per-document READ cost grew from %.1f (2 lines) to %.1f (10 lines): something is looked up PER LINE. '
                .'LegacyDocumentMapper::prepare() preloads legacy_acc_map/map_branch/map_currency precisely so it is not.',
                $perDocumentNarrow,
                $perDocumentWide
            )
        );
    }

    /**
     * The specific N+1 that would hurt most on the real dataset: 179,421 lines
     * each re-reading `accounts` to resolve the leaf the map already named.
     */
    public function test_accounts_are_not_re_read_per_line(): void
    {
        $accountReads = 0;
        DB::listen(function ($query) use (&$accountReads): void {
            if (str_contains($query->sql, '`accounts`') && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $accountReads++;
            }
        });

        // One document, twenty lines, two distinct accounts. A per-line lookup
        // would read `accounts` twenty times.
        $this->stageBulk(1, 20, 500000);

        $this->assertSame(1, $this->replay()['posted']);
        $this->assertLessThan(
            10,
            $accountReads,
            sprintf('`accounts` was read %d times for a single 20-line document — that is a per-line lookup.', $accountReads)
        );
    }

    /**
     * The mapping tables are read ONCE for the whole run, not once per
     * document. `prepare()` short-circuits on the second call, so a 25-document
     * run must not contain 25 reads of `legacy_acc_map`.
     */
    public function test_the_mapping_tables_are_read_once_per_run(): void
    {
        $mapReads = 0;
        DB::listen(function ($query) use (&$mapReads): void {
            if (str_contains($query->sql, 'legacy_acc_map') && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $mapReads++;
            }
        });

        $this->stageBulk(25, 4, 300000);

        $this->assertSame(25, $this->replay()['posted']);
        $this->assertLessThanOrEqual(
            2,
            $mapReads,
            'legacy_acc_map must be read once per run (plus at most the frozen-leaf preflight), never per document.'
        );
    }

    /**
     * A throughput floor, kept deliberately loose. Its job is to catch an
     * order-of-magnitude regression (a per-document full table scan, a lost
     * index) on a developer machine, not to police wall-clock on CI hardware.
     * The real figure for the run is reported by `legacy:replay` itself.
     */
    public function test_the_replay_reports_a_workable_throughput(): void
    {
        $documents = 50;
        $this->stageBulk($documents, 4, 400000);

        $measured = $this->measure();

        $this->assertSame($documents, $measured['summary']['posted']);

        $documentsPerSecond = $documents / max($measured['seconds'], 0.001);

        $this->assertGreaterThan(
            2.0,
            $documentsPerSecond,
            sprintf('Replay managed only %.1f documents/second; 30,612 documents would not finish in one unattended run.', $documentsPerSecond)
        );
    }
}
