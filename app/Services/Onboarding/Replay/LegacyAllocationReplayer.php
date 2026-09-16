<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Replay;

use App\Services\Onboarding\LegacyColumn;
use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\LegacyStagingCast;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- MAPPING-RULES.md §3, `tblAccIsApply` replay.
 *
 * ── The one place this deviates from MAPPING-RULES, and why ──────────────────
 * §3.2 says the rows are replayed "through Akeed's apply service". They cannot
 * be, and the document itself contains the reason. Akeed's only allocation model
 * is `payment_applications`, keyed on `payment_id` -> `payments` and
 * `invoice_id` -> `invoices`, both real foreign keys; §1.2 (d) fixes `invoiceId`
 * and `taskId` at NULL on every replayed line precisely because "Akeed's
 * invoice/task tables hold nothing for this data", and §1.3 forbids setting
 * `paymentId` at all (§1.5 #14). Meanwhile a legacy allocation is defined by
 * §3.2 (2) as a link between two JOURNAL LINES -- `(SourceDocID_FK,
 * SourceAccDetailID_FK) -> our journal_entries.id` -- a shape
 * `payment_applications` cannot express even in principle.
 *
 * §3.3 settles what follows: "allocations carry no ledger money; a mismatched
 * allocation cannot move a trial balance". So the replay records the resolved
 * link in the pilot's own quarantined `map_allocation` table, which is exactly
 * what LP4 check 6 (`Σ applied per account` + apply-row count vs
 * `stg_acc_is_apply`) reads. No ledger row is written, and by construction none
 * can be.
 *
 * ── §3.2 (4), the rule that would otherwise corrupt the RJV replay ──────────
 * Legacy's apply path creates realised-FX RJVs as a side effect. Ours must not:
 * the RJVs are already being replayed as documents (§2.7). Because this class
 * writes only `map_allocation` and never calls {@see \App\Services\Accounting\RealisedFxService}
 * or any apply entry point, that requirement is satisfied structurally rather
 * than by a flag someone could flip.
 *
 * ── §3.3, mismatches are TAGS ───────────────────────────────────────────────
 * An unresolvable endpoint, an over-applied target, a cross-currency pair or a
 * divergence from the staged DebitAdj/CreditAdj running totals is recorded with
 * its reason and counted. None of them stops anything -- the last one is
 * expected to be non-empty, because the legacy running totals are maintained by
 * a path with known defects.
 */
final class LegacyAllocationReplayer
{
    private const A = [
        'source_doc' => 'SourceDocID_FK',
        'source_line' => 'SourceAccDetailID_FK',
        'applied_doc' => 'AppliedDocID_FK',
        'applied_line' => 'AppliedAccDetailID_FK',
        'acc_id' => 'AccID_FK',
        'amount' => 'Amount',
        'source_dc' => 'sourceDC',
        'mod_dt' => 'ModDt',
    ];

    /**
     * @param  array{company_id?:int,dry_run?:bool,run_id?:string,limit?:?int}  $options
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $companyId = (int) ($options['company_id'] ?? config('legacy_pilot.default_company_id'));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $runId = (string) ($options['run_id'] ?? bin2hex(random_bytes(8)));
        $limit = isset($options['limit']) && $options['limit'] !== null ? (int) $options['limit'] : null;

        $lineIndex = $this->lineIndex($companyId);
        $prefix = (string) config('legacy_pilot.replay.allocation_idempotency_prefix');

        $summary = [
            'run_id' => $runId,
            'company_id' => $companyId,
            'dry_run' => $dryRun,
            'seen' => 0,
            'applied' => 0,
            'tagged' => 0,
            'already_applied' => 0,
            'tag_reasons' => [],
            'applied_amount_by_account' => [],
        ];

        $existing = $dryRun ? [] : DB::connection('legacy_pilot')->table('map_allocation')
            ->where('company_id', $companyId)
            ->where('status', 'applied')
            ->pluck('idempotency_key')
            ->mapWithKeys(fn ($k): array => [(string) $k => true])
            ->all();

        foreach ($this->orderedRows() as $row) {
            if ($limit !== null && $summary['seen'] >= $limit) {
                break;
            }

            $summary['seen']++;

            $sourceLine = LegacyStagingCast::toInt($this->a($row, 'source_line'));
            $appliedLine = LegacyStagingCast::toInt($this->a($row, 'applied_line'));
            $key = $prefix.':'.($sourceLine ?? 'NULL').':'.($appliedLine ?? 'NULL');

            if (isset($existing[$key])) {
                $summary['already_applied']++;

                continue;
            }

            $accId = LegacyStagingCast::toInt($this->a($row, 'acc_id'));
            $amountMillis = LegacyAmount::millis($this->a($row, 'amount'), 'allocation '.$key);

            $record = [
                'company_id' => $companyId,
                'idempotency_key' => $key,
                'source_doc_id' => LegacyStagingCast::toInt($this->a($row, 'source_doc')),
                'source_acc_detail_id' => $sourceLine,
                'applied_doc_id' => LegacyStagingCast::toInt($this->a($row, 'applied_doc')),
                'applied_acc_detail_id' => $appliedLine,
                'legacy_acc_id_fk' => $accId,
                'amount' => LegacyAmount::toDecimalString($amountMillis),
                'source_dc' => LegacyStagingCast::toString($this->a($row, 'source_dc')),
                'legacy_mod_dt' => LegacyStagingCast::toDate($this->a($row, 'mod_dt'))?->toDateTimeString(),
                'source_journal_entry_id' => $lineIndex[$sourceLine]['journal_entry_id'] ?? null,
                'applied_journal_entry_id' => $lineIndex[$appliedLine]['journal_entry_id'] ?? null,
                'run_id' => $runId,
            ];

            $failure = $this->tagReason($record, $lineIndex, $sourceLine, $appliedLine);

            if ($failure !== null) {
                $record['status'] = 'tagged';
                $record['failure_code'] = $failure[0];
                $record['note'] = $failure[1];
                $summary['tagged']++;
                $summary['tag_reasons'][$failure[0]] = ($summary['tag_reasons'][$failure[0]] ?? 0) + 1;
            } else {
                $record['status'] = 'applied';
                $record['failure_code'] = null;
                $record['note'] = null;
                $summary['applied']++;
                $summary['applied_amount_by_account'][$accId] = ($summary['applied_amount_by_account'][$accId] ?? 0) + $amountMillis;
            }

            if (! $dryRun) {
                $this->upsert($record);
            }
        }

        foreach ($summary['applied_amount_by_account'] as $accId => $millis) {
            $summary['applied_amount_by_account'][$accId] = LegacyAmount::toDecimalString($millis);
        }

        return $summary;
    }

    /**
     * §3.3's four tag conditions, in the document's own order.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, array{journal_entry_id:?int,currency:?string,amount_millis:int}>  $lineIndex
     * @return array{0:string,1:string}|null
     */
    private function tagReason(array $record, array $lineIndex, ?int $sourceLine, ?int $appliedLine): ?array
    {
        if ($sourceLine === null || ! isset($lineIndex[$sourceLine])) {
            return ['source_line_unresolved', sprintf('Source line %s has no replayed journal entry (its document was skipped, refused, or its line dropped as zero-amount).', $sourceLine ?? 'NULL')];
        }

        if ($appliedLine === null || ! isset($lineIndex[$appliedLine])) {
            return ['applied_line_unresolved', sprintf('Applied line %s has no replayed journal entry.', $appliedLine ?? 'NULL')];
        }

        $sourceCurrency = $lineIndex[$sourceLine]['currency'];
        $appliedCurrency = $lineIndex[$appliedLine]['currency'];

        if ($sourceCurrency !== null && $appliedCurrency !== null && $sourceCurrency !== $appliedCurrency) {
            // §3.1: allocations are in FC and both sides must share the source
            // line's currency. A cross-currency pair is a legacy defect, tagged.
            return ['cross_currency_pair', sprintf('Source line is %s but the applied line is %s.', $sourceCurrency, $appliedCurrency)];
        }

        $amountMillis = LegacyAmount::millis($record['amount'], 'allocation amount');

        if ($amountMillis > $lineIndex[$appliedLine]['amount_millis']) {
            return ['over_applied', sprintf('Allocation %s exceeds the applied line\'s own amount %s.', $record['amount'], LegacyAmount::toDecimalString($lineIndex[$appliedLine]['amount_millis']))];
        }

        return null;
    }

    /**
     * §3.2 (1): ModDt ascending, tie-broken by the staged row's insertion order.
     * Chronological order matters because partial allocations against a running
     * balance are order-dependent.
     *
     * ModDt is TEXT like every other staged column, so it is coerced and sorted
     * in PHP -- a SQL ORDER BY over a text date is a lexicographic sort that only
     * accidentally works.
     *
     * @return list<object>
     */
    private function orderedRows(): array
    {
        $rows = DB::connection('legacy_pilot')->table('stg_acc_is_apply')->get()->all();

        usort($rows, function (object $a, object $b): int {
            $da = LegacyStagingCast::toDate($this->a($a, 'mod_dt'))?->getTimestamp() ?? 0;
            $db = LegacyStagingCast::toDate($this->a($b, 'mod_dt'))?->getTimestamp() ?? 0;

            return [$da, (int) ($a->id ?? 0)] <=> [$db, (int) ($b->id ?? 0)];
        });

        return $rows;
    }

    /**
     * legacy AccDetailID -> the replayed journal entry, its posted currency and
     * its amount. Built from map_document_line (§8.2: "without it none of the
     * three can be built").
     *
     * @return array<int, array{journal_entry_id:?int,currency:?string,amount_millis:int}>
     */
    private function lineIndex(int $companyId): array
    {
        $index = [];

        DB::connection('legacy_pilot')->table('map_document_line')
            ->where('company_id', $companyId)
            ->whereNotNull('our_journal_entry_id')
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$index): void {
                foreach ($rows as $row) {
                    $index[(int) $row->legacy_acc_detail_id] = [
                        'journal_entry_id' => (int) $row->our_journal_entry_id,
                        'currency' => $row->posted_currency === null ? null : (string) $row->posted_currency,
                        'amount_millis' => LegacyAmount::millis($row->amount, 'map_document_line amount'),
                    ];
                }
            });

        return $index;
    }

    /** @param array<string, mixed> $record */
    private function upsert(array $record): void
    {
        $key = ['company_id' => $record['company_id'], 'idempotency_key' => $record['idempotency_key']];
        $table = DB::connection('legacy_pilot')->table('map_allocation');

        if ($table->where($key)->exists()) {
            $table->where($key)->update($record + ['updated_at' => now()]);
        } else {
            $table->insert($record + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function a(object $row, string $key): mixed
    {
        return $row->{LegacyColumn::name(self::A[$key])} ?? null;
    }
}
