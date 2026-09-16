<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use App\Services\Onboarding\LegacyStagingCast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * legacy-ledger-pilot LP4 -- `legacy:parity-diff --account=<AccCode>`.
 *
 * PLAN.md §LP4: "Drill-down from an account diff to the contributing
 * documents is part of the deliverable — a total that is off by 0.003 is
 * useless without the two documents that caused it."
 *
 * Given ONE legacy AccCode it lists, side by side:
 *
 *   LEGACY  every stg_acc_detail line on that legacy account, with its
 *           header's DocID / DocNo / SubType / DocDt;
 *   AKEED   every journal_entries row on the account legacy_acc_map folded
 *           it to, with journal id, transaction id, our reference_number
 *           and the idempotency key (`legacy:<SubType>:<DocID>`,
 *           MAPPING-RULES §8.1) that ties a line of ours back to a legacy
 *           DocID directly.
 *
 * ------------------------------------------------------------------------
 * WHY THE AKEED SIDE IS NOT FILTERED BY PARTY BY DEFAULT
 * ------------------------------------------------------------------------
 * A POOLED party leaf folds onto a control account carrying thousands of
 * other parties' lines. Listing the whole control account for a single
 * party's AccCode would bury the answer. So when the requested code is a
 * pooled leaf, this class narrows our side by that leaf's party id on
 * `type_reference_id` -- which is exactly the fold's inverse, the same one
 * ParityHarness uses for the AR/AP check. For a `direct` leaf no narrowing
 * applies and the whole account is listed.
 *
 * map_document_line (LP3's table) is joined OPPORTUNISTICALLY: when it
 * exists, each of our journal ids gains its originating legacy
 * AccDetail_ID, which turns a two-column comparison into a per-line
 * reconciliation. When it does not exist -- LP3 not landed on this database
 * -- the drill-down still works from the idempotency key, and says so.
 * LP3's classes are never imported (see LegacyMapReader's docblock).
 */
final class AccountDrillDown
{
    public function __construct(private readonly LedgerFigures $ledger) {}

    /**
     * @return array<string, mixed>
     */
    public function forAccountCode(int $companyId, string $accCode, CarbonImmutable $asOf, int $limit = 500): array
    {
        $mapping = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->where('acc_code', $accCode)
            ->first();

        if ($mapping === null) {
            throw new RuntimeException("No legacy_acc_map row for AccCode '{$accCode}' in company {$companyId}. Either the code is wrong or LP1 never imported that account.");
        }

        $legacyAccId = (int) $mapping->acc_id;
        $accountId = $mapping->account_id === null ? null : (int) $mapping->account_id;
        $partyId = $mapping->party_id === null ? null : (int) $mapping->party_id;
        $resolution = (string) $mapping->resolution;

        $legacyLines = $this->legacyLines($legacyAccId, $asOf, $limit);

        $akeedLines = [];
        $lineMap = [];

        if ($accountId !== null) {
            $akeedLines = $this->ledger->linesForAccount($companyId, $accountId, $asOf, $limit);

            // Pooled leaf: narrow to this party, otherwise the control
            // account's whole population is returned (see class docblock).
            if ($partyId !== null && str_starts_with($resolution, 'pooled_')) {
                $akeedLines = array_values(array_filter($akeedLines, fn ($line) => $line['party_id'] === $partyId));
            }

            $lineMap = $this->legacyDetailIdByJournalEntry(array_column($akeedLines, 'journal_entry_id'));

            foreach ($akeedLines as $index => $line) {
                $akeedLines[$index]['legacy_acc_detail_id'] = $lineMap[$line['journal_entry_id']] ?? null;
            }
        }

        $legacyNet = 0.0;

        foreach ($legacyLines as $line) {
            $legacyNet += $line['debit'] - $line['credit'];
        }

        $akeedNet = 0.0;

        foreach ($akeedLines as $line) {
            $akeedNet += $line['debit'] - $line['credit'];
        }

        return [
            'acc_code' => $accCode,
            'legacy_acc_id' => $legacyAccId,
            'account_id' => $accountId,
            'resolution' => $resolution,
            'party_id' => $partyId,
            'as_of' => $asOf->toDateString(),
            'map_document_line_available' => Schema::connection('legacy_pilot')->hasTable('map_document_line'),
            'legacy_lines' => $legacyLines,
            'akeed_lines' => $akeedLines,
            'legacy_net' => round($legacyNet, 3),
            'akeed_net' => round($akeedNet, 3),
            'delta' => round($akeedNet - $legacyNet, 3),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function legacyLines(int $legacyAccId, CarbonImmutable $asOf, int $limit): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_acc_detail')) {
            return [];
        }

        // stg_acc_detail carries DocNo/SubType/DocDt on the LINE itself
        // (the export denormalises them), so the header is joined for one
        // column only: Posted. That matters because PLAN.md §5.2 O11
        // excludes unposted headers from every anchor, and a drill-down
        // that silently mixed an unposted document's lines into the legacy
        // side would make a correct replay look short.
        $query = DB::connection('legacy_pilot')->table('stg_acc_detail as d')
            ->where('d.accid_fk', (string) $legacyAccId);

        if (Schema::connection('legacy_pilot')->hasTable('stg_acc_header')) {
            $query->leftJoin('stg_acc_header as h', 'h.docid', '=', 'd.docid_fk')
                ->selectRaw('d.accdetailid, d.docid_fk, d.docno, d.subtype, d.docdt, d.debit, d.credit, d.dc, h.posted');
        } else {
            $query->selectRaw('d.accdetailid, d.docid_fk, d.docno, d.subtype, d.docdt, d.debit, d.credit, d.dc');
        }

        return $query
            ->orderBy('d.docid_fk')
            ->orderBy('d.accdetailid')
            ->limit($limit)
            ->get()
            // Every stg_* column is TEXT: ids through LegacyStagingCast::
            // toInt(), amounts through toDecimal() (3 dp, refusing garbage
            // rather than casting it to 0.000), the boolean Posted flag
            // through toBool() -- a raw (float)/(int) on a staging value is
            // the LP1b defect this helper exists to stop.
            ->map(fn ($row) => [
                'legacy_acc_detail_id' => LegacyStagingCast::toInt($row->accdetailid ?? null),
                'legacy_doc_id' => LegacyStagingCast::toInt($row->docid_fk ?? null),
                'legacy_doc_no' => $row->docno ?? null,
                'sub_type' => $row->subtype ?? null,
                'doc_date' => $row->docdt ?? null,
                'posted' => property_exists($row, 'posted') && $row->posted !== null
                    ? LegacyStagingCast::toBool($row->posted)
                    : null,
                'dc' => $row->dc ?? null,
                'debit' => LegacyStagingCast::toDecimal($row->debit ?? null, 3, 'stg_acc_detail.Debit'),
                'credit' => LegacyStagingCast::toDecimal($row->credit ?? null, 3, 'stg_acc_detail.Credit'),
            ])->all();
    }

    /**
     * @param  list<int>  $journalEntryIds
     * @return array<int, int>
     */
    private function legacyDetailIdByJournalEntry(array $journalEntryIds): array
    {
        if ($journalEntryIds === [] || ! Schema::connection('legacy_pilot')->hasTable('map_document_line')) {
            return [];
        }

        $rows = DB::connection('legacy_pilot')->table('map_document_line')
            ->whereIn('our_journal_entry_id', $journalEntryIds)
            ->get(['our_journal_entry_id', 'legacy_acc_detail_id']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->our_journal_entry_id] = (int) $row->legacy_acc_detail_id;
        }

        return $out;
    }
}
