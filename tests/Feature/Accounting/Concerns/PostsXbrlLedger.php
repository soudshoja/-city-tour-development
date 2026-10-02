<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Concerns;

use App\Services\Accounting\ClosingDocuments;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CT port (U1): copied unchanged from Akeed's tests/Feature/Modules/XbrlKuwait/Concerns (the
 * module's test tree lands with U3); only the namespace differs.
 *
 * XBRL X4 test helper: writes synthetic ledger documents straight into `transactions` and
 * `journal_entries` (the sole-writer ratchet scans `app/`, not `tests/`), and reads the
 * independent oracle.
 *
 * Amounts are DECIMAL STRINGS with exactly three decimals ('250.125'), never floats, so a fixture
 * can never carry a rounding artefact into the oracle.
 *
 * The ORACLE is written here, in the test, and never imported: it re-states the engine
 * discriminator itself (`transactions.doc_type IS NOT NULL AND transactions.posting_date IS NOT
 * NULL`, PLAN.md L2), `journal_entries.deleted_at IS NULL`, and the date basis as
 * `DATE(COALESCE(posting_date, transaction_date))` compared with `<=`/`>=` on whole dates, a
 * different formulation from the code under test (which uses `< next day 00:00:00`). It sums in
 * SQL DECIMAL and converts the decimal string to fils by string surgery, not arithmetic. The
 * one thing it does NOT re-state is which documents form the YEC and PPA families: that has ONE
 * definition, `ClosingDocuments` (X9r), which every reader and oracle uses, and its own ratchet.
 */
trait PostsXbrlLedger
{
    private int $xbrlDocSeq = 0;

    /**
     * Posts one document. `$lines` are [account_id, debit, credit, party_id|null, ledger type|null].
     * `$subType` defaults to the doc type (a reversal passes the reversed doc type, as
     * PostingService::reverse() stamps it). `$shape` is 'engine' (doc_type and posting_date set),
     * 'legacy' (neither) or 'seam_off' (the
     * engine-OFF seam writer's shape: doc_type set, header posting_date NULL; LedgerSource calls
     * it legacy). `$lineTime` is appended to transaction_date (a time of day must not move a line
     * out of its day). `$linePostingDate` overrides the header's posting_date on the lines ('' =
     * NULL on the lines only).
     *
     * @param  list<array{0:int,1:string,2:string,3?:int|null,4?:string|null}>  $lines
     */
    protected function postDoc(
        int $companyId,
        string $docType,
        string $date,
        array $lines,
        ?string $postingDate = null,
        string $shape = 'engine',
        string $lineTime = '00:00:00',
        ?string $linePostingDate = null,
        ?string $subType = null,
    ): int {
        $debit = '0.000';
        $credit = '0.000';
        foreach ($lines as $line) {
            self::assertDecimal($line[1]);
            self::assertDecimal($line[2]);
            $debit = bcadd($debit, $line[1], 3);
            $credit = bcadd($credit, $line[2], 3);
        }
        if ($debit !== $credit) {
            throw new InvalidArgumentException("fixture document does not balance: Dr {$debit} Cr {$credit}");
        }

        $seq = ++$this->xbrlDocSeq;
        $headerPosting = $postingDate ?? $date;
        $transactionId = (int) DB::table('transactions')->insertGetId([
            'company_id' => $companyId,
            'entity_id' => $companyId,
            'entity_type' => 'company',
            'transaction_type' => 'journal',
            'amount' => $debit,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'description' => 'xbrl x4 fixture',
            'reference_type' => 'Invoice',
            'doc_type' => $shape === 'legacy' ? null : $docType,
            'sub_type' => $subType ?? $docType,
            'reference_number' => "X4-{$docType}-{$seq}",
            'idempotency_key' => "x4:{$companyId}:{$seq}",
            'posting_status' => 'posted',
            'transaction_date' => $date.' 00:00:00',
            'posting_date' => $shape === 'engine' ? $headerPosting : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jePosting = match (true) {
            $linePostingDate === '' => null,
            $linePostingDate !== null => $linePostingDate,
            $shape === 'engine' => $headerPosting,
            default => null,
        };

        foreach ($lines as $line) {
            DB::table('journal_entries')->insert([
                'company_id' => $companyId,
                'transaction_id' => $transactionId,
                'account_id' => $line[0],
                'type_reference_id' => $line[3] ?? null,
                'type' => $line[4] ?? null,
                'debit' => $line[1],
                'credit' => $line[2],
                'amount' => $line[1] !== '0.000' ? $line[1] : $line[2],
                'exchange_rate' => 1,
                'currency' => 'KWD',
                'name' => 'x4 fixture',
                'description' => 'x4 fixture line',
                'transaction_date' => $date.' '.$lineTime,
                'posting_date' => $jePosting,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $transactionId;
    }

    private static function assertDecimal(string $amount): void
    {
        if (preg_match('/\A\d+\.\d{3}\z/', $amount) !== 1) {
            throw new InvalidArgumentException("fixture amounts are non-negative decimal strings with 3 places, got '{$amount}'");
        }
    }

    /** A DECIMAL string from SQL ('-12.345', '0.000', '12.34500') as integer fils, by string surgery. */
    protected static function filsOf(?string $decimal): int
    {
        $decimal ??= '0';
        if (preg_match('/\A(-?)(\d+)(?:\.(\d+))?\z/', $decimal, $m) !== 1) {
            throw new InvalidArgumentException("not a decimal: '{$decimal}'");
        }
        $fraction = str_pad($m[3] ?? '', 3, '0');
        if (strlen(rtrim(substr($fraction, 3), '0')) > 0) {
            throw new InvalidArgumentException("more than 3 significant decimals: '{$decimal}'");
        }

        return (int) ($m[1].ltrim($m[2].substr($fraction, 0, 3), '0') ?: '0');
    }

    /**
     * ORACLE: signed (debit - credit) per account in fils, engine rows only, on or before the
     * whole date `$asOf`. `$connection` lets the CT-copy tests read the SELECT-only copy.
     *
     * @return array<int, int>
     */
    protected function oracleBalances(int $companyId, string $asOf, ?string $connection = null, bool $engine = true): array
    {
        $discriminator = $engine
            ? 't.doc_type IS NOT NULL AND t.posting_date IS NOT NULL'
            : 'NOT (t.id IS NOT NULL AND t.doc_type IS NOT NULL AND t.posting_date IS NOT NULL)';
        $rows = DB::connection($connection)->select(
            "SELECT je.account_id, CAST(SUM(je.debit) - SUM(je.credit) AS CHAR) AS net
               FROM journal_entries je
               LEFT JOIN transactions t ON t.id = je.transaction_id
              WHERE je.company_id = ? AND je.deleted_at IS NULL AND {$discriminator}
                AND DATE(COALESCE(je.posting_date, je.transaction_date)) <= ?
              GROUP BY je.account_id",
            [$companyId, $asOf],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->account_id] = self::filsOf($row->net);
        }
        ksort($out);

        return $out;
    }

    /**
     * ORACLE: signed movement per account in fils, engine rows only (the L2 conjunction, re-written
     * here), whole dates `$from` to `$to` inclusive, with the two closing families (the YEC family
     * and the PPA family) excluded whole-document through the ONE shared definition,
     * {@see ClosingDocuments} (X9r: no reader, oracle included, spells its own family test).
     *
     * @return array<int, int>
     */
    protected function oracleMovements(int $companyId, string $from, string $to, ?string $connection = null): array
    {
        $query = DB::connection($connection)->table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereNotNull('t.doc_type')
            ->whereNotNull('t.posting_date')
            ->whereRaw('DATE(COALESCE(je.posting_date, je.transaction_date)) >= ?', [$from])
            ->whereRaw('DATE(COALESCE(je.posting_date, je.transaction_date)) <= ?', [$to]);
        ClosingDocuments::excludeYearEndCloseFamily($query, 'je.transaction_id');
        ClosingDocuments::excludePriorPeriodAdjustmentFamily($query, 'je.transaction_id');
        $rows = $query->groupBy('je.account_id')
            ->selectRaw('je.account_id, CAST(SUM(je.debit) - SUM(je.credit) AS CHAR) AS net')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->account_id] = self::filsOf($row->net);
        }
        ksort($out);

        return $out;
    }
}
