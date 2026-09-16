<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\Replay\LegacyReplayRunner;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLegacyReplayFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\Support\AccountingTestCase;

/**
 * legacy-ledger-pilot LP3 test base.
 *
 * Extends {@see AccountingTestCase} rather than Tests\TestCase on purpose: its
 * tearDown runs the C1 global invariant ("after any operation exercised by any
 * test in the accounting suite, the acting company's trial balance still
 * balances") against the replay company. A replay that posts an unbalanced
 * ledger therefore fails even in a test that only asserted on a count.
 *
 * The two databases in play are different animals. The APP schema is handled by
 * RefreshDatabase (a per-test transaction). The `legacy_pilot` fence is a
 * SECOND connection that RefreshDatabase does not wrap, so
 * {@see PreparesLegacyPilotFence} truncates the map_* tables and drops the stg_*
 * tables in setUp instead.
 */
abstract class LegacyReplayTestCase extends AccountingTestCase
{
    use BuildsLegacyReplayFixtures, PreparesLegacyPilotFence;

    // Synthetic legacy ids. None of these came from the real export.
    protected const LEGACY_CUSTOMER_ACC = 4001;

    protected const LEGACY_SUPPLIER_ACC = 4002;

    protected const LEGACY_INCOME_ACC = 4003;

    protected const LEGACY_BANK_ACC = 4004;

    protected const LEGACY_ORPHAN_POOL_ACC = 4005;

    protected const LEGACY_BRANCH_SH = 10;

    protected const LEGACY_USD_CURR = 2;

    protected const LEGACY_POISON_CURR = 99;

    protected const PARTY_ID = 777;

    protected Company $company;

    protected Branch $branch;

    protected User $user;

    protected Account $receivableControl;

    protected Account $payableControl;

    protected Account $incomeAccount;

    protected Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Both halves of the W0 kill-switch. MAPPING-RULES §1.5 #1: the replay's
        // legacy closure THROWS, so an accidentally-OFF engine aborts loudly on
        // document 1 rather than silently doing nothing.
        config(['accounting.engine.enabled' => true]);

        $this->setUpLegacyPilotFence();
        $this->createLegacyReplayStagingTables();

        $country = Country::factory()->create();
        $this->user = User::factory()->create();
        $this->company = tap(
            Company::factory()->create(['user_id' => $this->user->id, 'country_id' => $country->id]),
            fn (Company $c) => $c->forceFill(['posting_engine_enabled' => true])->save()
        );
        $this->trackCompanyForInvariants($this->company->id);

        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->receivableControl = Account::factory()->create(['company_id' => $this->company->id, 'name' => 'RECEIVABLE CONTROL']);
        $this->payableControl = Account::factory()->create(['company_id' => $this->company->id, 'name' => 'PAYABLE CONTROL']);
        $this->incomeAccount = Account::factory()->create(['company_id' => $this->company->id, 'name' => 'MAIN TRAVEL INCOME']);
        $this->bankAccount = Account::factory()->create(['company_id' => $this->company->id, 'name' => 'BANK ACCOUNTS']);

        DB::connection('legacy_pilot')->table('map_party')->insert([
            'company_id' => $this->company->id,
            'partner_id_fk' => self::PARTY_ID,
            'is_customer' => true,
            'is_supplier' => true,
            'cust_acc_id_fk' => self::LEGACY_CUSTOMER_ACC,
            'supp_acc_id_fk' => self::LEGACY_SUPPLIER_ACC,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mapLegacyAccount($this->company->id, self::LEGACY_CUSTOMER_ACC, $this->receivableControl->id, 'pooled_receivable', self::PARTY_ID);
        $this->mapLegacyAccount($this->company->id, self::LEGACY_SUPPLIER_ACC, $this->payableControl->id, 'pooled_payable', self::PARTY_ID);
        $this->mapLegacyAccount($this->company->id, self::LEGACY_INCOME_ACC, $this->incomeAccount->id, 'direct');
        $this->mapLegacyAccount($this->company->id, self::LEGACY_BANK_ACC, $this->bankAccount->id, 'direct');

        $this->mapLegacyBranch($this->company->id, 1, $this->branch->id);
        // Base currency is derived from actual line usage, never a master.
        $this->mapLegacyCurrency($this->company->id, 1, 'KWD');
        $this->mapLegacyCurrency($this->company->id, self::LEGACY_USD_CURR, 'USD');
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function replay(array $options = []): array
    {
        return app(LegacyReplayRunner::class)->run(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'year' => 2025,
        ], $options));
    }

    protected function auditFor(int $legacyDocId): ?object
    {
        return DB::connection('legacy_pilot')->table('map_document')
            ->where('company_id', $this->company->id)
            ->where('legacy_doc_id', $legacyDocId)
            ->first();
    }
}
