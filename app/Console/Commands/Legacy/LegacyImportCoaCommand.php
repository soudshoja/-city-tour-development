<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyPartyMapper;
use App\Services\Onboarding\SeededDefaultChartGuard;
use App\Services\Onboarding\SystemPurposeMapper;
use App\Console\Commands\Legacy\Concerns\GuardsLegacyScope;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * legacy-ledger-pilot LP1.1/LP1.2 — import the legacy 1,351-account tree
 * into `accounts` for one company and map tblSystemParameters control
 * pointers into `map_purpose`.
 *
 * IMPORTANT — staging is not empty. LP0's stock DatabaseSeeder already ran
 * CoaSeeder (a default chart, ~184 accounts) and SystemAccountsSeeder
 * (purpose mappings pointed at them) on "Akeed 2" staging; prod, by
 * contrast, starts with an EMPTY `accounts` table. This command therefore:
 *
 *   - REFUSES outright if `accounts` already has rows for the target
 *     company and --replace-seeded was not passed.
 *   - With --replace-seeded: REFUSES if the chart is in use (see R1 below).
 *   - On an empty `accounts` table, imports directly — no replace needed.
 *
 * Idempotent: re-running after a successful import updates legacy_acc_map/
 * map_purpose in place (updateOrInsert) rather than duplicating rows; it
 * does NOT re-import Account rows a second time (LegacyCoaImporter always
 * creates fresh Account rows, so a genuine re-run from scratch requires
 * --replace-seeded again, or a fresh company).
 *
 * ─────────────────────────────────────────────────────────────────────────
 * COORDINATOR RULINGS R1/R2/R3 (2026-09-07, LP1c) — recorded here because
 * this command is where an operator meets them.
 *
 * R1 — --replace-seeded removes the WHOLE chart, not an allow-list.
 *   Staging run #3 proved the old "recognised defaults" rule too narrow:
 *   the stock DatabaseSeeder also mints 9 party/gateway accounts CoaSeeder
 *   never declares (suppliers Amadeus / Magic Holiday / TBO Holiday,
 *   gateways Tap / MyFatoorah / Hesabe / Knet / uPayment, and one agent
 *   party leaf), so the guard refused every fresh staging company.
 *   Now: with --replace-seeded, APP_ENV in local/staging/testing, and
 *   PROVABLY ZERO ledger activity — no journal_entries, no transactions,
 *   and no rows in any configured activity table (payments, invoices, …)
 *   scoped to the company or referencing one of its accounts — the entire
 *   chart is treated as seeder-born and removed. Every removed row is
 *   first recorded in legacy_pilot.seeded_chart_removed, and every
 *   dependent FK pointing at a removed account is NULLED (never left
 *   dangling) and listed in legacy_pilot.seeded_chart_fk_nulled as "needs
 *   re-linking at go-live". Re-pointing those automatically by NAME is
 *   FORBIDDEN — the legacy export is pseudonymised, so a name match would
 *   be a fabricated mapping — so they are only re-pointed where a
 *   map_party row positively identifies the row. If ANY ledger activity
 *   exists the refusal is unchanged.
 *   Reported, NOT fixed here: the three supplier leaves share one code
 *   (2131) because SupplierCompanyController::activateSupplierProcess()
 *   derives a new leaf's code from its PARENT's code rather than from its
 *   siblings. That is a pre-existing defect in that controller; fixing it
 *   is not this pilot's job.
 *
 * R2 — control purposes.
 *   PAYABLE_CONTROL has no legacy parameter and its only structural anchor
 *   (the AP group named by payable_group_prefixes) is a group, so the
 *   importer MINTS a synthetic pooled control LEAF under that group named
 *   "Suppliers Control (legacy pooled)", coded from
 *   config('legacy_pilot.import.synthetic_code_range'), records it in
 *   legacy_acc_map as resolution='synthetic', and pools every AP party
 *   leaf onto it. RECEIVABLE_CONTROL takes the same path only if its own
 *   parameter target is not a leaf (on the real export it IS a leaf).
 *   RETAINED_EARNINGS instead descends from the group its parameter names
 *   to that group's single LEAF child when exactly one exists, and refuses
 *   with a clear message on zero or several — in which case name the leaf's
 *   legacy Acc_ID directly in --purpose-map, which now accepts a bare
 *   numeric Acc_ID as well as a parameter name. SUSPENSE's target is absent
 *   from the export entirely and stays unmapped: --allow-unmapped exists to
 *   accept exactly that kind of non-control gap. The LP1.5 gate requires
 *   only RECEIVABLE_CONTROL, PAYABLE_CONTROL, RETAINED_EARNINGS and
 *   FX_GAIN_LOSS.
 *
 * R3 — `legacy:audit-masters`'s census_2025 check reports posted and
 *   unposted separately and passes when posted + unposted equals the
 *   manifest count (the manifest counts ALL headers). See MastersAuditor.
 */
class LegacyImportCoaCommand extends Command
{
    use GuardsLegacyScope;

    protected $signature = 'legacy:import-coa
                            {--company= : Target company id (default: config(legacy_pilot.default_company_id, 1))}
                            {--replace-seeded : R1 — remove the WHOLE seeder-born chart before importing. Refuses outright on ANY ledger activity; records every removed account and every nulled dependent FK in legacy_pilot}
                            {--purpose-map= : Optional JSON string {"PURPOSE_CODE":"LegacyParameterName"|"<legacy Acc_ID>", ...} for LP1.2 — R2 allows a bare numeric Acc_ID where no parameter names the target leaf}
                            {--allow-unmapped : Exit 0 even though purposes remain unmapped. WITHOUT this flag an unmapped purpose is a FAILURE (exit 1), because a purpose that resolves to nothing is a posting leg the engine cannot place}';

    protected $description = 'Import the legacy chart of accounts + system purposes (LP1.1/LP1.2). Refuses on a non-empty chart unless --replace-seeded and zero ledger activity (R1); mints synthetic pooled control leaves where no legacy leaf exists (R2).';

    public function handle(SeededDefaultChartGuard $guard, LegacyCoaImporter $importer, SystemPurposeMapper $purposeMapper, LegacyPartyMapper $partyMapper): int
    {
        $companyId = (int) ($this->option('company') ?? config('legacy_pilot.default_company_id', 1));

        // ── CD-PORT ──────────────────────────────────────────────────────────────────────────
        // The only edit this command carries relative to the Akeed-Ai original. Three gates, all
        // before the first write: quarantined staging connection, target company (R-CO4), reserved
        // id band. See App\Console\Commands\Legacy\Concerns\GuardsLegacyScope.
        if ($this->legacyScopeOrFail($companyId) === null) {
            return self::FAILURE;
        }

        if ($guard->hasExistingAccounts($companyId)) {
            if (! $this->option('replace-seeded')) {
                $this->error(
                    "Refusing: company {$companyId} already has accounts. Pass --replace-seeded to remove ONLY ".
                    'the recognised seeder-created default chart (refused outright if any ledger activity exists) '.
                    'before importing the legacy chart.'
                );

                return self::FAILURE;
            }

            try {
                $removed = $guard->replaceSeededChart($companyId);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info("R1: removed the whole seeder-born chart — {$removed} account(s) for company {$companyId}, ".
                'each recorded in legacy_pilot.seeded_chart_removed under run_key '.
                ((string) $guard->lastRunKey()).' (LP1d).');

            // LP1d: the delete order no longer trusts accounts.level, but a
            // level that contradicts its own parent_id is still a defect in
            // whatever wrote the row, so it is REPORTED rather than absorbed.
            $levelProblems = $guard->lastLevelInconsistencies();

            if ($levelProblems !== []) {
                $this->warn(count($levelProblems).' removed account(s) carried an accounts.level inconsistent with their own '.
                    'parent_id — the delete order ignored `level` entirely (topological, from parent_id), but whatever '.
                    'auto-provisioned these rows is writing a wrong level:');

                foreach (array_slice($levelProblems, 0, 20) as $problem) {
                    $this->line('  '.$problem);
                }
            }

            foreach ($guard->incompleteRemovalRuns($companyId) as $stale) {
                $this->warn("legacy_pilot.seeded_chart_removal_run {$stale->run_key} never recorded a completion — its audit ".
                    'rows describe an attempt that may not have finished. Purge it once reviewed.');
            }

            $duplicates = $guard->duplicateRemovedCodes($companyId);

            if ($duplicates !== []) {
                foreach ($duplicates as $code => $count) {
                    $this->warn("  duplicate code {$code} carried by {$count} removed accounts — pre-existing seeder ".
                        'defect (SupplierCompanyController derives a leaf code from its PARENT, not its siblings). '.
                        'Reported, not fixed here.');
                }
            }
        }

        $purposeMapArg = $this->option('purpose-map');
        $parameterPurposeMap = (array) config('legacy_pilot.parameter_purpose_map', []);

        if ($purposeMapArg) {
            $decoded = json_decode($purposeMapArg, true);

            if (! is_array($decoded)) {
                $this->error('--purpose-map is not valid JSON.');

                return self::FAILURE;
            }

            $parameterPurposeMap = $decoded;
        }

        // LP1.2 runs BETWEEN the importer's two phases (O12): the structural
        // accounts exist, so tblSystemParameters pointers resolve and land in
        // system_accounts, and phase 2's pooling then resolves its control
        // accounts through AccountResolver -- a real purpose mapping -- rather
        // than through the structural fallback.
        $purposeStats = null;

        try {
            $stats = $importer->import($companyId, function (int $cid) use ($purposeMapper, $parameterPurposeMap, &$purposeStats) {
                $purposeStats = $purposeMapper->map($cid, $parameterPurposeMap);
            });
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('LP1.1 import complete:');

        foreach ($stats as $key => $count) {
            $this->line("  {$key}: {$count}");
        }

        $typeSplit = $importer->typeSplit($companyId);
        $this->info('Declared A/L/I/E split over every mapped row: '.json_encode($typeSplit));

        if ($stats['type_dirt'] > 0) {
            $this->warn("  {$stats['type_dirt']} account(s) whose DECLARED AccType contradicts their position in the tree ".
                '— classified by position, flagged in legacy_acc_map.type_dirt.');
        }

        if (($stats['synthetic_controls'] ?? 0) > 0) {
            $this->info("  {$stats['synthetic_controls']} synthetic pooled control leaf/leaves minted (R2) — recorded in ".
                "legacy_acc_map with resolution='synthetic'.");
        }

        if ($stats['frozen'] > 0) {
            $this->line("  {$stats['frozen']} account(s) carry the legacy IsFreeze flag — preserved (accounts.disabled), never dropped.");
        }

        $expectedSplit = (array) config('legacy_pilot.account_type_split', []);
        $expectedTotal = array_sum($expectedSplit);
        $mappedTotal = array_sum(array_map('intval', $typeSplit));

        // Reconcile only when this run mapped the whole tree the plan's
        // split describes -- a synthetic/partial run legitimately has a
        // different total, but a FULL run whose per-type split disagrees is
        // a classification defect and must go red, not print a warning.
        if ($mappedTotal === $expectedTotal) {
            $splitMismatch = [];

            foreach ($expectedSplit as $type => $expectedCount) {
                $actual = (int) ($typeSplit[$type] ?? 0);

                if ($actual !== $expectedCount) {
                    $splitMismatch[] = "{$type}: expected {$expectedCount}, got {$actual}";
                }
            }

            if ($splitMismatch !== []) {
                $this->error('A/L/I/E split does not reconcile — '.implode('; ', $splitMismatch));

                return self::FAILURE;
            }

            $this->info('  A/L/I/E split reconciles against config(legacy_pilot.account_type_split).');
        } else {
            $this->line("  split reconciliation skipped: mapped {$mappedTotal} row(s), the configured split describes {$expectedTotal}.");
        }

        // LP1d. The unclassified gate is EVALUATED HERE and ENFORCED AT THE
        // END. Before LP1d it returned FAILURE on the spot, which is why
        // staging run #4a produced no purpose-mapping report, no party
        // mapping and no LP1.5 gate output at all -- the one number it did
        // print ("274 unclassified") was the only thing an operator got, and
        // diagnosing it needed a second run. A run that has something to say
        // should say all of it once.
        $failures = [];

        if ($stats['unclassified'] > 0) {
            $poolFailures = $importer->assertPoolPurposesResolve($companyId);

            if ($poolFailures !== []) {
                // Pooling itself did not resolve, so the unclassified rows ARE
                // the pooling failure. Party mapping over a half-pooled chart
                // would only add noise on top of a cause already known.
                $this->reportUnclassified($importer, $companyId);
                $this->error('LP1.5 control-purpose gate FAILED — this is the cause of the unclassified rows above:');

                foreach ($poolFailures as $failure) {
                    $this->line('  '.$failure);
                }

                return self::FAILURE;
            }

            $failures[] = "{$stats['unclassified']} account(s) are UNCLASSIFIED";
            $this->warn("{$stats['unclassified']} account(s) are UNCLASSIFIED — the four control purposes DO resolve, so the ".
                'rest of LP1 is reported below and the failure is raised at the end of this run.');
        }

        // LP1.3 -- pooling party leaves onto a control account is only a
        // legitimate fold if it stays reversible, so the party registry is
        // built in the same run that does the pooling.
        try {
            $partyStats = $partyMapper->map($companyId);
            $this->info('LP1.3 party mapping complete: '.
                "parties={$partyStats['parties']}, customers={$partyStats['customers']}, ".
                "suppliers={$partyStats['suppliers']}, dual_role={$partyStats['dual_role']}");

            if ($partyStats['unlinked_leaves'] > 0) {
                $this->error("{$partyStats['unlinked_leaves']} pooled party leaf/leaves carry no party_id — their per-party ".
                    'AR/AP position could not be decomposed back out of the control account (LP4 check 3).');

                $failures[] = "{$partyStats['unlinked_leaves']} pooled party leaf/leaves carry no party_id";
            }
        } catch (RuntimeException $e) {
            $this->warn('LP1.3 party mapping skipped: '.$e->getMessage());
        }

        // R1, after the import: re-point what CAN be positively identified,
        // and REPORT the rest. A nulled FK is never left silently nulled --
        // "needs re-linking at go-live" is an owner-visible line, not a row
        // buried in a table nobody reads.
        $relink = $guard->relinkNulledReferences($companyId);

        if ($relink['relinked'] > 0) {
            $this->info("R1 re-link: {$relink['relinked']} dependent FK(s) re-pointed at their imported legacy leaf via map_party.");
        }

        if ($relink['pending'] > 0) {
            $this->warn("R1: {$relink['pending']} dependent FK(s) (supplier/agent/gateway/user rows that pointed at a removed ".
                'account) are NULLED and NEED RE-LINKING AT GO-LIVE — listed in legacy_pilot.seeded_chart_fk_nulled. '.
                'They are not re-pointed automatically: the legacy export is pseudonymised, so matching them by name '.
                'would be a fabricated mapping.');

            foreach (array_slice($guard->pendingRelinks($companyId), 0, 20) as $row) {
                $this->line("  {$row->table_name}#{$row->row_id}.{$row->column_name} — was account ".
                    "{$row->old_account_code} ({$row->old_account_name})");
            }
        }

        $purposeStats = $purposeStats ?? $purposeMapper->map($companyId, $parameterPurposeMap);

        $this->info('LP1.2 purpose mapping complete: '.
            "mapped={$purposeStats['mapped']}, unmapped={$purposeStats['unmapped']}, skipped_non_leaf={$purposeStats['skipped_non_leaf']}");

        $unresolved = $purposeStats['unmapped'] + $purposeStats['skipped_non_leaf'];

        if ($unresolved > 0) {
            $this->warn("{$unresolved} purpose(s) unresolved ({$purposeStats['unmapped']} unmapped, ".
                "{$purposeStats['skipped_non_leaf']} pointed at a non-leaf) — reported in legacy_pilot.map_purpose, never invented.");

            if (! $this->option('allow-unmapped')) {
                $this->error(
                    'legacy:import-coa FAILED: a purpose that resolves to nothing is a posting leg the engine cannot place. '.
                    'PLAN.md §4 LP1.2 accepts this step only at "0 unexplained skips". Re-run with --allow-unmapped to '.
                    'acknowledge the gap deliberately (LP1.5 still gates on it).'
                );

                $failures[] = "{$unresolved} purpose(s) unresolved and --allow-unmapped was not given";
            } else {

                $this->warn('--allow-unmapped given: exiting 0 despite the gap above.');
            }
        }

        // LP1.5 gate (O12, membership fixed by R2): EXACTLY four purposes --
        // RECEIVABLE_CONTROL, PAYABLE_CONTROL, RETAINED_EARNINGS and
        // FX_GAIN_LOSS -- must resolve, through AccountResolver, to a
        // structural leaf. Otherwise every pooled party line in LP3 resolves
        // to nothing and LP1's own pool targets were picked by the fallback.
        // No other purpose is gated here: SUSPENSE in particular is expected
        // to stay unmapped (its legacy target Acc_ID is absent from the
        // export) and O6 forbids plugging it.
        $poolFailures = $importer->assertPoolPurposesResolve($companyId);

        if ($poolFailures !== []) {
            $this->error('LP1.5 control-purpose gate FAILED — the pooling design depends on these resolving:');

            foreach ($poolFailures as $failure) {
                $this->line('  '.$failure);
            }

            if (! $this->option('allow-unmapped')) {
                $failures[] = 'LP1.5 control-purpose gate failed';
            } else {
                $this->warn('--allow-unmapped given: exiting 0 despite the pool-purpose gate above.');
            }
        }

        // LP1d. The unclassified list is printed LAST, after everything else
        // this run has to say, and it is what makes the exit code red. LP1.5
        // requires zero unclassified leaves before LP2 may start; --allow-
        // unmapped deliberately does NOT waive it (it acknowledges an unmapped
        // PURPOSE, not an unclassified ACCOUNT).
        if ($stats['unclassified'] > 0) {
            $this->reportUnclassified($importer, $companyId);
        }

        if ($failures !== []) {
            $this->error('legacy:import-coa FAILED: '.implode('; ', $failures).'.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * LP1d. Print the unclassified accounts — grouped by the reason recorded
     * on each row, then the rows themselves. Codes and structural flags only;
     * never an account NAME, because a legacy party leaf's name is party data.
     */
    private function reportUnclassified(LegacyCoaImporter $importer, int $companyId): void
    {
        $report = $importer->unclassifiedReport($companyId);

        $this->error("{$report['total']} account(s) are UNCLASSIFIED — listed in legacy_pilot.legacy_acc_map with ".
            "resolution='unclassified'. LP1.5 requires zero unclassified leaves before LP2 may start.");

        foreach ($report['by_note'] as $note => $count) {
            $this->line("  {$count} x {$note}");
        }

        foreach ($report['rows'] as $row) {
            $this->line("    Acc_ID {$row->acc_id} (AccCode {$row->acc_code}) position=".
                ($row->position_type_code ?? '?').' role='.($row->party_role ?? 'none'));
        }

        if (count($report['rows']) < $report['total']) {
            $this->line('    ... '.($report['total'] - count($report['rows'])).' more — query legacy_acc_map for the full list.');
        }
    }
}
