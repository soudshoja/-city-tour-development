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

        try {
            $years = $this->resolveYears();
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

        // ROUND 2, finding F2 - the R-CO4 post-conditions, on the deployed path. See
        // App\Console\Commands\Legacy\Concerns\GuardsLegacyScope::assertLegacyPostConditions().
        if (! $this->assertLegacyPostConditions($scope)) {
            return self::FAILURE;
        }

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

        // ROUND 2, finding F5. This used to read `replay.window_from` / `replay.window_to`.
        // NEITHER KEY EXISTS - config/legacy_pilot.php calls them `window_start` / `window_end` -
        // so both config() calls fell through to the PHP defaults written beside them, and the
        // command worked only because those defaults happened to be the intended window. A typo'd
        // config key that silently returns a plausible answer is the shape of defect this phase
        // exists to find in somebody else's ledger; it does not get to live in the tool.
        //
        // The keys are asserted rather than defaulted: a missing one is a refusal, not a guess.
        $start = config('legacy_pilot.replay.window_start');
        $end = config('legacy_pilot.replay.window_end');

        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            throw new LegacyScopeRefused(
                'Refused: legacy_pilot.replay.window_start / window_end are not both configured, so '.
                'there is no window to derive document years from. Pass --years explicitly, or fix '.
                'the config - this command will not substitute a plausible default for a missing key.'
            );
        }

        $from = (int) date('Y', (int) strtotime($start));
        $to = (int) date('Y', (int) strtotime($end));

        return range(min($from, $to), max($from, $to));
    }
}
