<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\SerialSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Thin CLI wrapper around App\Models\SerialSchema::seedFromLegacyMax() — see that
 * method's docblock for the full source-of-truth rationale and limitations.
 *
 * Fixes P1-VERIFICATION-FINDINGS.json HIGH finding "serial_schemas counters start
 * at zero and are never seeded from the legacy numbering — duplicate document
 * numbers at P2 cutover" (App\Services\Accounting\SequenceService).
 *
 * NOT scheduled, NOT called from anywhere else — this is a manual, one-shot,
 * pre-cutover step an operator runs (with --dry-run reviewed first) for one
 * company immediately before that company's posting_engine_enabled flag flips
 * on. Safe to re-run: SerialSchema::seedFromLegacyMax() only ever raises
 * last_serial, never lowers it.
 *
 * W3-prereq lane B addition (doc 17 §3.3/§4): this class is the ONLY file this build is
 * permitted to touch for the seed-serial-schemas behaviour — App\Models\SerialSchema itself is
 * off-limits (SAFETY file allowlist covers only App\Console\Commands\*SerialSchemas*.php, not
 * app/Models/SerialSchema.php). The ruling asks the seeded ceiling to also account for legacy
 * reference_numbers that don't match the engine's strict `{TYPE}-{YYYY}-{SEQ}` mask by falling
 * back to "parse the trailing integer", and to report a count of numbers that are unparseable
 * even by that fallback. `seedFromLegacyMax()`'s own scan (the value actually written to
 * `last_serial`) already SKIPS any reference_number failing its strict mask match — folding the
 * fallback INTO that seeded ceiling would mean changing that scan, which lives in the off-limits
 * model. What THIS class can do, and does below, is run its own independent, read-only diagnostic
 * scan alongside the real seeding call: it recomputes the SAME strict-vs-fallback-vs-unparseable
 * classification this ruling describes and prints it, so an operator can see whether any
 * fallback-only numbers exist (and how high they run) before deciding whether the true ceiling
 * needs raising further by hand. See this file's own PROPOSED NAMES note in the W3-prereq lane B
 * build report for the exact model-level change that would be needed to fold the fallback into
 * the seeded value itself.
 */
class SeedAccountingSerialSchemas extends Command
{
    protected $signature = 'accounting:seed-serial-schemas
                            {--company=* : Company id(s) to seed (default: every company)}
                            {--doc-type=* : Doc type code(s) to seed (default: all 8 engine types)}
                            {--year= : Target doc_year to seed (default: current year)}
                            {--dry-run : Compute and print without writing any row}';

    protected $description = 'Pre-cutover: raise serial_schemas.last_serial to the highest legacy document number already in use, per company/branch/doc_type/year.';

    /**
     * INV/RV/PV/JV/CRN/DBN/OJV/REV — the engine's full doc_type vocabulary (DocumentDraft
     * docblock) — plus AST (w5-brief.md §W5.S: agent settlement's temporary series,
     * config('accounting.doc_types')'s own docblock note). RV/PV were already members of this list
     * before W5 — this wave adds only AST; RV/PV's own serial-schema seeding was already exercised
     * by this command, just never previously asserted by a dedicated RV/PV/AST-focused test (see
     * SeedAccountingSerialSchemasVoucherSeriesTest).
     */
    private const ALL_DOC_TYPES = ['INV', 'RV', 'PV', 'JV', 'CRN', 'DBN', 'OJV', 'REV', 'AST'];

    /**
     * W3-prereq lane B addition (doc 17 §3.3/§4) — read-only diagnostic, deliberately independent
     * of SerialSchema::seedFromLegacyMax()'s own scan (see this class's docblock for why it lives
     * here rather than folded into that method). Re-scans the SAME `transactions.reference_number`
     * values that method looks at for this (companyId, docType), classifying each into exactly one
     * of three buckets using the SAME strict mask that method uses first:
     *
     *   - strict:      matches `{docType}-\d{4}-\d+` verbatim (SequenceService::DEFAULT_MASK's
     *                  branchless legacy shape) — these are the numbers seedFromLegacyMax() itself
     *                  already folds into `last_serial`.
     *   - fallback:    doesn't match the strict shape, but still ends in a digit group a trailing
     *                  `(\d+)$` can parse — e.g. a hand-entered or differently-formatted legacy
     *                  number. NOT currently included in the seeded ceiling (see class docblock).
     *   - unparseable: doesn't even end in a digit group — nothing safe to attribute a sequence
     *                  value to.
     *
     * @return array{strict: int, fallback: int, fallback_max: int|null, unparseable: int}
     */
    private function scanLegacyParseability(int $companyId, string $docType): array
    {
        $strict = 0;
        $fallback = 0;
        $fallbackMax = null;
        $unparseable = 0;

        DB::table('transactions')
            ->select(['reference_number'])
            ->where('company_id', $companyId)
            ->where('reference_number', 'like', $docType.'-%')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$strict, &$fallback, &$fallbackMax, &$unparseable, $docType) {
                // Same shape SerialSchema::seedFromLegacyMax() matches first — verified against
                // that method's own literal pattern, not guessed.
                $strictPattern = '/^'.preg_quote($docType, '/').'-\d{4}-0*(\d+)$/';

                foreach ($rows as $row) {
                    $value = (string) $row->reference_number;

                    if (preg_match($strictPattern, $value) === 1) {
                        $strict++;

                        continue;
                    }

                    if (preg_match('/(\d+)$/', $value, $m) === 1) {
                        $fallback++;
                        $seq = (int) $m[1];
                        $fallbackMax = $fallbackMax === null ? $seq : max($fallbackMax, $seq);

                        continue;
                    }

                    $unparseable++;
                }
            });

        return [
            'strict' => $strict,
            'fallback' => $fallback,
            'fallback_max' => $fallbackMax,
            'unparseable' => $unparseable,
        ];
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $year = $this->option('year') !== null ? (int) $this->option('year') : (int) now()->year;

        $companyIds = $this->option('company') !== []
            ? array_map('intval', $this->option('company'))
            : Company::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $docTypes = $this->option('doc-type') !== []
            ? array_map('strtoupper', $this->option('doc-type'))
            : self::ALL_DOC_TYPES;

        if ($companyIds === []) {
            $this->warn('No companies found — nothing to seed.');

            return self::SUCCESS;
        }

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Seed accounting serial_schemas from legacy numbering');
        $this->info('═══════════════════════════════════════════════════════');
        $this->line('  Target doc_year: '.$year);
        $this->line('  Companies:       '.count($companyIds));
        $this->line('  Doc types:       '.implode(', ', $docTypes));

        if ($dryRun) {
            $this->warn('  DRY RUN — no rows will be written.');
        }

        $this->newLine();

        $rows = [];
        $changedCount = 0;
        $diagnosticRows = [];
        $totalUnparseable = 0;

        foreach ($companyIds as $companyId) {
            foreach ($docTypes as $docType) {
                $report = SerialSchema::seedFromLegacyMax($companyId, $docType, $year, $dryRun);

                foreach ($report as $entry) {
                    if ($entry['changed']) {
                        $changedCount++;
                    }

                    $rows[] = [
                        $companyId,
                        $docType,
                        $entry['branch_id'],
                        $entry['previous_last_serial'],
                        $entry['legacy_max'],
                        $entry['seeded_last_serial'],
                        $entry['changed'] ? 'YES' : '-',
                    ];
                }

                // W3-prereq lane B addition — read-only, see scanLegacyParseability()'s own
                // docblock for exactly what this does and does not feed into the seeded value.
                $scan = $this->scanLegacyParseability($companyId, $docType);
                $totalUnparseable += $scan['unparseable'];

                if ($scan['strict'] + $scan['fallback'] + $scan['unparseable'] > 0) {
                    $diagnosticRows[] = [
                        $companyId,
                        $docType,
                        $scan['strict'],
                        $scan['fallback'],
                        $scan['fallback_max'] ?? '-',
                        $scan['unparseable'],
                    ];
                }
            }
        }

        $this->table(
            ['Company', 'Doc Type', 'Branch', 'Previous', 'Legacy Max', 'Seeded', 'Changed'],
            $rows
        );

        $this->newLine();
        $this->info(sprintf(
            '  %d row(s) inspected, %d row(s) %s.',
            count($rows),
            $changedCount,
            $dryRun ? 'would change' : 'changed'
        ));

        if ($dryRun) {
            $this->warn('  Re-run without --dry-run to write these values.');
        }

        if ($diagnosticRows !== []) {
            $this->newLine();
            $this->line('  Legacy reference_number parseability (read-only — NOT folded into the seeded ceiling above):');
            $this->table(
                ['Company', 'Doc Type', 'Strict-mask matches', 'Fallback-parsed', 'Fallback max', 'Unparseable'],
                $diagnosticRows
            );

            if ($totalUnparseable > 0) {
                $this->warn(sprintf(
                    '  %d legacy reference_number value(s) across all scanned company/doc_type pairs matched '
                        .'neither the engine mask nor a trailing integer — skipped entirely by both this scan '
                        .'and seedFromLegacyMax() itself. Review them by hand if this company/doc_type has real '
                        .'pre-engine volume.',
                    $totalUnparseable
                ));
            }
        }

        return self::SUCCESS;
    }
}
