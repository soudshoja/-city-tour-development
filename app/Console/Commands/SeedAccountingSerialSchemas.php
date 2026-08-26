<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\SerialSchema;
use Illuminate\Console\Command;

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
 */
class SeedAccountingSerialSchemas extends Command
{
    protected $signature = 'accounting:seed-serial-schemas
                            {--company=* : Company id(s) to seed (default: every company)}
                            {--doc-type=* : Doc type code(s) to seed (default: all 8 engine types)}
                            {--year= : Target doc_year to seed (default: current year)}
                            {--dry-run : Compute and print without writing any row}';

    protected $description = 'Pre-cutover: raise serial_schemas.last_serial to the highest legacy document number already in use, per company/branch/doc_type/year.';

    /** @var string[] INV/RV/PV/JV/CRN/DBN/OJV/REV — the engine's full doc_type vocabulary (DocumentDraft docblock). */
    private const ALL_DOC_TYPES = ['INV', 'RV', 'PV', 'JV', 'CRN', 'DBN', 'OJV', 'REV'];

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

        return self::SUCCESS;
    }
}
