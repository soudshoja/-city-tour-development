<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Console\Commands\Legacy\Concerns\GuardsLegacyScope;
use App\Services\Onboarding\Scope\LegacyRowLedger;
use App\Services\Onboarding\Scope\LegacyScopeRefused;
use Illuminate\Console\Command;

/**
 * CD-PORT round 2 — record which rows the load owns, for the one step that is not a `legacy:*`
 * command.
 *
 * Every `legacy:*` write command claims ownership on completion (see
 * {@see \App\Console\Commands\Legacy\Concerns\GuardsLegacyScope::assertLegacyPostConditions()}).
 * The **provisioning** step does not — it is `CompanyProvisioner`, reached through the
 * registration wizard or `company:provision --repair`, and this lane does not own that code and
 * will not reach into it. Yet provisioning is what creates the company, its owner user, its main
 * branch, its roles, its `settings`, its seeded chart, and (on a database with suppliers
 * configured) its `supplier_companies` and `suppliers` rows.
 *
 * `legacy:adopt` closes that gap: run it immediately after provisioning, and the ledger knows
 * about everything provisioning created, by the same attribution rules every other step uses.
 *
 * It is NOT a rubber stamp. It claims a row only when an attribution rule reaches it —
 * `companies.id`, `company_id`, `branches.user_id`, `supplier_companies.company_id`,
 * `agents.branch_id`, … — so a City Travelers row minted into the reserved band while provisioning
 * ran satisfies nothing and is never adopted. That is the whole point of it existing rather than
 * "everything in the band is now ours".
 */
class LegacyAdoptCommand extends Command
{
    use GuardsLegacyScope;

    protected $signature = 'legacy:adopt
                            {--company= : The legacy company id (required)}
                            {--apply : Actually record the ownership. Without this the command reports what it would claim and writes nothing}';

    protected $description = 'CD-PORT — record which application rows the legacy load owns, for the provisioning step that is not a legacy:* command. Claims only rows an attribution rule reaches; never "everything in the band".';

    public function handle(LegacyRowLedger $ledger): int
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
            $attribution = $ledger->attribute($scope);
        } catch (LegacyScopeRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($attribution as $table => $claims) {
            if ($claims === []) {
                continue;
            }

            $rules = array_unique(array_values($claims));
            $rows[] = [$table, count($claims), implode(' | ', $rules)];
        }

        $this->table(['table', 'rows claimed', 'by which rule'], $rows);

        // Computed from the attribution just derived, NOT from the persisted ledger: before
        // --apply nothing has been written, and reading the stale ledger would report every row
        // provisioning had just created as unowned.
        $ownedNow = [];

        foreach ($attribution as $table => $claims) {
            $ownedNow[$table] = array_map('intval', array_keys($claims));
        }

        $unowned = $ledger->unownedInBand($scope, $ownedNow);

        foreach ($unowned as $table => $ids) {
            $this->warn(sprintf(
                'NOT CLAIMED: %d row(s) in `%s` sit inside the reserved band but no attribution '.
                'rule reaches them [%s]. They are City Travelers\' — nothing in this pipeline will '.
                'ever delete them.',
                count($ids),
                $table,
                implode(', ', array_slice($ids, 0, 10)).(count($ids) > 10 ? ', …' : '')
            ));
        }

        if (! $this->option('apply')) {
            $this->warn('DRY RUN — nothing was written. Re-run with --apply.');

            return self::SUCCESS;
        }

        $summary = $ledger->claim($scope);

        $this->info(sprintf(
            'Ownership recorded for company %d: %d row(s) across %d table(s) in '.
            'legacy_pilot.ct_scope_row. legacy:unload deletes from that ledger and nothing else.',
            $scope->companyId,
            array_sum($summary),
            count(array_filter($summary))
        ));

        // The provisioning step ran outside this pipeline, so the post-conditions are asserted here
        // rather than at the end of whatever created the rows.
        if (! $this->assertLegacyPostConditions($scope)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
