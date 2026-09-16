<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\LegacyBranchImporter;
use App\Services\Onboarding\LegacyCurrencyMapper;
use App\Services\Onboarding\LegacyVerifyConfigReporter;
use App\Console\Commands\Legacy\Concerns\GuardsLegacyScope;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * legacy-ledger-pilot LP1.4 / LP1e — the masters `legacy:import-coa` does not
 * import: BRANCHES, CURRENCIES, and the O7-verify config report.
 *
 * ── Why this is its own command and not part of import-coa ──────────────────
 * It runs AFTER `legacy:import-coa`, and it depends on it. Branch control-
 * account FKs resolve through `legacy_acc_map`, and the verify report checks
 * group names against the IMPORTED chart — neither exists until the COA
 * import has committed. Folding it into `import-coa` would either run it too
 * early or make one command's exit code mean two unrelated things. Keeping it
 * separate also means a currency re-derivation (the part most likely to need
 * a second look) does not require re-importing 1,351 accounts.
 *
 * ── What staging run #5 proved ──────────────────────────────────────────────
 * `map_branch` and `map_currency` are READ by
 * {@see \App\Services\Onboarding\Replay\LegacyDocumentMapper} and WRITTEN by
 * nothing in production code — only by a test fixture. With both empty, the
 * replay refused 100% of the 2025 population on `legacy.branch_unmapped` and
 * all 13 SubTypes type-stopped on their first document. This command is the
 * missing populator.
 *
 * ── Run order ───────────────────────────────────────────────────────────────
 *   legacy:load  ->  legacy:audit-masters  ->  legacy:import-coa
 *     ->  legacy:import-masters  ->  (set the two env keys, config:clear)
 *     ->  legacy:replay --dry-run
 *
 * Idempotent throughout: a second run updates the same four branches, the
 * same four map_branch rows and the same map_currency rows in place.
 */
class LegacyImportMastersCommand extends Command
{
    use GuardsLegacyScope;

    protected $signature = 'legacy:import-masters
                            {--company= : Target company id (default: config(legacy_pilot.default_company_id, 1))}
                            {--user= : The user id branches.user_id points at (default: the company\'s first user)}
                            {--write-env : Write ACCOUNTING_BANK_GROUP_NAME/ACCOUNTING_CASH_GROUP_NAME into this instance\'s .env (O7-verify). Off by default — the report alone changes nothing}';

    protected $description = 'LP1.4/LP1e — create the legacy branches as Akeed branches, populate map_branch/map_currency from line usage, and report the O7-verify group-name env keys. Run AFTER legacy:import-coa.';

    public function handle(
        LegacyBranchImporter $branches,
        LegacyCurrencyMapper $currencies,
        LegacyVerifyConfigReporter $verify,
    ): int {
        $companyId = (int) ($this->option('company') ?? config('legacy_pilot.default_company_id', 1));
        $userId = $this->option('user') === null ? null : (int) $this->option('user');

        // ── CD-PORT ──────────────────────────────────────────────────────────────────────────
        // The only edit this command carries relative to the Akeed-Ai original. Three gates, all
        // before the first write: quarantined staging connection, target company (R-CO4), reserved
        // id band. See App\Console\Commands\Legacy\Concerns\GuardsLegacyScope.
        $scope = $this->legacyScopeOrFail($companyId);

        if ($scope === null) {
            return self::FAILURE;
        }

        try {
            $branchStats = $branches->import($companyId, $userId);
        } catch (RuntimeException $e) {
            $this->error('LP1e branch import FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('LP1e branches (R-branch):');
        $this->line("  staged legacy branches : {$branchStats['staged']}");
        $this->line("  Akeed branches created : {$branchStats['branches_created']}");
        $this->line("  Akeed branches updated : {$branchStats['branches_updated']}");
        $this->line("  map_branch rows        : {$branchStats['mapped']}");
        $this->line("  control FKs recorded   : {$branchStats['control_fk_recorded']} ".
            "({$branchStats['control_fk_resolved']} resolved to an imported account)");
        $this->line('  NOTE: branches.* has no control-account columns in this schema (the relationship runs the '.
            'other way, accounts.branch_id), so the legacy BranchAcc/CashAcc/CashControlAcc/BankAcc/DiscountAcc '.
            'pointers are recorded on the map_branch row instead of attached to the Branch model.');

        try {
            $currencyStats = $currencies->map($companyId);
        } catch (RuntimeException $e) {
            $this->error('LP1e currency map FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('LP1e currencies (R-currency — derived from LINE USAGE; the currency master tblMaster is not in the export):');
        $this->line("  distinct FcCurrID_FK   : {$currencyStats['distinct_fks']}");
        $this->line("  mapped                 : {$currencyStats['mapped']} ".
            "(by identity {$currencyStats['by_identity']}, by rate history {$currencyStats['by_rate_history']})");
        $this->line("  unresolved             : {$currencyStats['unresolved']}");
        $this->line("  poison (quarantined)   : {$currencyStats['poison']}");

        foreach ($currencyStats['rows'] as $row) {
            $this->line(sprintf(
                '    FcCurrID_FK %-8s %-11s %-13s lines=%-7d %s%s',
                $row['curr_id_fk'],
                $row['curr_code'] ?? '-',
                $row['status'].($row['is_poison'] ? '/POISON' : ''),
                $row['line_count'],
                $row['derivation'],
                $row['notes'] === null ? '' : '  — '.$row['notes'],
            ));
        }

        if ($currencyStats['unresolved'] > 0) {
            $this->warn("{$currencyStats['unresolved']} FcCurrID_FK value(s) could not be derived. This is NOT a blocker: ".
                'R-currency rules that an unresolved FC currency is METADATA — the posting is in KWD (the LC columns) '.
                'and the line records `legacy_curr_<fk>` in map_document_line.metadata_currency. Only a POISON match, '.
                'or a line with no LC amount at all, refuses.');
        }

        if ($currencyStats['poison'] > 0) {
            $this->warn("{$currencyStats['poison']} FcCurrID_FK value(s) derive to a QUARANTINED tblCurrency code ".
                '(XYZ 2500 / xyz 3500 / abc / blank / "2"). Every document with such a line WILL refuse at replay — '.
                'that is R3 working, not a defect to route around.');
        }

        $report = $verify->report($companyId);

        $this->info('LP1e O7-verify config (accounting:verify\'s RV/PV cash-or-bank invariant matches group names EXACTLY):');

        foreach ($report['entries'] as $entry) {
            $this->line(sprintf(
                '  %-30s current="%s" required="%s" %s',
                $entry['env_key'],
                $entry['current'],
                $entry['required'],
                $entry['ok'] ? 'OK' : 'NEEDS SETTING',
            ));

            if ($entry['present_in_chart'] === false) {
                $this->warn("    \"{$entry['required']}\" is NOT the name of any group in company {$companyId}'s chart — ".
                    'setting the key to it would not help. Candidates below.');
            }
        }

        foreach ($report['candidates'] as $token => $names) {
            $this->line('  chart group candidates ('.$token.'): '.($names === [] ? '(none)' : implode(' | ', $names)));
        }

        if (! $report['ok']) {
            $this->warn('Set these on the PILOT INSTANCE ONLY, then run `php artisan config:clear`:');

            foreach ($report['entries'] as $entry) {
                if (! $entry['ok'] && $entry['required'] !== '') {
                    $this->line('  '.$entry['env_key'].'="'.$entry['required'].'"');
                }
            }
        }

        if ($this->option('write-env')) {
            try {
                $written = $verify->writeEnv($companyId, base_path('.env'));
            } catch (RuntimeException $e) {
                $this->error('--write-env FAILED: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->info('--write-env: wrote '.implode(', ', $written).' into '.base_path('.env').
                ' — run `php artisan config:clear` for it to take effect.');
        }

        if ($branchStats['mapped'] === 0) {
            $this->error('legacy:import-masters FAILED: map_branch is still empty, so every document would refuse on legacy.branch_unmapped.');

            return self::FAILURE;
        }

        $this->info('legacy:import-masters complete. Next: `php artisan legacy:replay --year=2025 --dry-run`.');

        // ROUND 2, finding F2 - the R-CO4 post-conditions, on the deployed path. See
        // App\Console\Commands\Legacy\Concerns\GuardsLegacyScope::assertLegacyPostConditions().
        if (! $this->assertLegacyPostConditions($scope)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
