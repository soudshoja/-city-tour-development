<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Console\Commands\Legacy\Concerns\GuardsLegacyScope;
use App\Services\Onboarding\Scope\LegacyScopeRefused;
use App\Services\Onboarding\Scope\LegacySerialSchemaPlanner;
use Illuminate\Console\Command;

/**
 * CD-PORT — pre-create the legacy company's `serial_schemas` rows so every replayed document
 * number fits `transactions.reference_number`.
 *
 * Runs after `legacy:import-masters` (which is what puts the branch codes in `map_branch`) and
 * before `legacy:replay`. See {@see LegacySerialSchemaPlanner} for why this step exists at all —
 * short version: the reserved id band makes branch ids eight digits, and the engine's default
 * document-number mask renders the branch id.
 *
 * `--dry-run` is the default.
 */
class LegacySerialsCommand extends Command
{
    use GuardsLegacyScope;

    protected $signature = 'legacy:serials
                            {--company= : The legacy company id (required)}
                            {--years=* : Document years to seed (default: legacy_pilot.replay window years)}
                            {--apply : Actually insert the rows. Without this the command plans and reports only}';

    protected $description = 'CD-PORT — plan and seed serial_schemas masks for the legacy company so replayed document numbers fit transactions.reference_number (varchar(20)) despite eight-digit branch ids.';

    public function handle(LegacySerialSchemaPlanner $planner): int
    {
        $companyOption = $this->option('company');

        if ($companyOption === null || $companyOption === '') {
            $this->error('Refused: --company is required. There is deliberately no default.');

            return self::FAILURE;
        }

        $scope = $this->legacyScopeOrFail((int) $companyOption);

        if ($scope === null) {
            return self::FAILURE;
        }

        $years = $this->resolveYears();

        try {
            $plan = $planner->plan($scope, $years);
        } catch (LegacyScopeRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['branch id', 'tag'],
            array_map(static fn ($id, $tag) => [$id, $tag], array_keys($plan['tags']), $plan['tags'])
        );

        $this->line(sprintf(
            'years           : %s',
            implode(', ', $years)
        ));
        $this->line(sprintf('planned rows    : %d', count($plan['rows'])));
        $this->line(sprintf(
            'longest number  : %s (%d chars); transactions.reference_number holds %d (read from information_schema)',
            $plan['sample'],
            strlen($plan['sample']),
            $plan['limit']
        ));

        if (! $this->option('apply')) {
            $this->warn('DRY RUN — nothing was written. Re-run with --apply.');

            return self::SUCCESS;
        }

        $result = $planner->apply($plan['rows']);

        $this->info(sprintf(
            'serial_schemas seeded for company %d: %d inserted, %d already present.',
            $scope->companyId,
            $result['inserted'],
            $result['existing']
        ));

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function resolveYears(): array
    {
        /** @var list<string> $given */
        $given = (array) $this->option('years');

        if ($given !== []) {
            return array_values(array_map('intval', $given));
        }

        $from = (int) date('Y', strtotime((string) config('legacy_pilot.replay.window_from', '2025-01-01')));
        $to = (int) date('Y', strtotime((string) config('legacy_pilot.replay.window_to', '2026-03-31')));

        return range(min($from, $to), max($from, $to));
    }
}
