<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FixedAsset;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * accounting-builds T3 (Lane B): `fixed-assets:depreciate {company} {month} [--dry-run]
 * [--all-companies]` — thin CLI wrapper, all logic lives in {@see DepreciationRunService}, same
 * convention {@see YearClose} already follows for its own service.
 *
 * `{month}` accepts `yyyy-mm`; when omitted, defaults to the PREVIOUS calendar month (the
 * scheduled run in routes/console.php fires on the 1st of the month, so "last month, now
 * closed" is the natural default for an unattended run).
 */
class FixedAssetsDepreciate extends Command
{
    protected $signature = 'fixed-assets:depreciate
                            {company? : Company id (omit with --all-companies)}
                            {month? : Period, yyyy-mm (default: previous calendar month)}
                            {--dry-run : Preview what would post, without posting anything}
                            {--all-companies : Run for every company that has at least one fixed asset}
                            {--user= : Acting user id, recorded on each posted DEP document}';

    protected $description = 'Post monthly straight-line depreciation for every due, active fixed asset.';

    public function handle(DepreciationRunService $service): int
    {
        $monthArg = $this->argument('month');

        if ($monthArg === null) {
            $period = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        } else {
            try {
                $period = Carbon::createFromFormat('Y-m', (string) $monthArg)->startOfMonth();
            } catch (\Throwable) {
                $this->error("Invalid month '{$monthArg}' — expected yyyy-mm.");

                return self::FAILURE;
            }
        }

        $year = $period->year;
        $month = $period->month;
        $dryRun = (bool) $this->option('dry-run');
        $userOption = $this->option('user');
        $userId = $userOption !== null ? (int) $userOption : null;

        if ((bool) $this->option('all-companies')) {
            // whereNull('deleted_at'): withoutGlobalScopes() drops SoftDeletingScope too — a company
            // whose only assets are archived has nothing to run (verifier fix, defect D2).
            $companyIds = FixedAsset::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('company_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            $companyArg = $this->argument('company');

            if ($companyArg === null) {
                $this->error('Provide {company} or pass --all-companies.');

                return self::FAILURE;
            }

            $companyIds = [(int) $companyArg];
        }

        if ($companyIds === []) {
            $this->info('No companies with fixed assets to process.');

            return self::SUCCESS;
        }

        foreach ($companyIds as $companyId) {
            $result = $service->runForMonth($companyId, $year, $month, $dryRun, $userId);

            if (! $result['dry_run'] && ! $result['engine_enabled']) {
                $this->info("Company #{$companyId}: engine off — nothing posted.");

                continue;
            }

            if ($dryRun) {
                $this->info(sprintf(
                    'Company #%d, %04d-%02d (dry-run): %d asset(s) would be posted.',
                    $companyId,
                    $year,
                    $month,
                    count($result['lines'])
                ));

                foreach ($result['lines'] as $line) {
                    $this->line(sprintf('  - Asset #%d: %s', $line['fixed_asset_id'], number_format($line['amount'], 3)));
                }

                continue;
            }

            $this->info(sprintf(
                'Company #%d, %04d-%02d: %d posted, %d skipped.',
                $companyId,
                $year,
                $month,
                $result['posted'],
                $result['skipped']
            ));

            foreach ($result['blocked'] as $blockedLine) {
                $this->warn("  - {$blockedLine}");
            }
        }

        return self::SUCCESS;
    }
}
