<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1.1 — tree integrity, positional classification,
 * canonical roots and frozen-status preservation.
 *
 * The tree below is shaped like the real 1,351-account export: SIX roots
 * (ASSETS / LIABILITIES / INCOMES / EXPENSES / APPROPRIATIONS / EQUITY,
 * the last two both declared AccType 'L'), a customer control group with
 * party leaves, a payable control group with party leaves, an
 * EQUITY > RESERVES & SURPLUS > RETAINED EARNINGS > "Profit & Loss Account
 * <year>" spine, one frozen leaf, and one declared-vs-position type
 * conflict ("TRANSFER TO RESERVES", declared E, sitting under the
 * L-rooted APPROPRIATIONS tree — the real export carries exactly this
 * row).
 */
class LegacyCoaTreeIntegrityTest extends TestCase
{
    use BuildsLegacyCsvFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->makeLegacyFixtureRoot();

        $country = Country::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'country_id' => $country->id]);
        $this->companyId = $company->id;
    }

    protected function tearDown(): void
    {
        $this->cleanupLegacyFixtureRoot();

        parent::tearDown();
    }

    /**
     * columns: Company_ID, AccGroup, Acc_ID, AccCode, Group_ID, AccName,
     * AccName_FL, AccType, HasSubAcc, AllowMultiCurr, AccTransType,
     * IsApply, CurrID_FK, ParentAccID_FK, AccLevel, AccStatus, IsFreeze, ...
     */
    private function tree(): array
    {
        return [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 'True', '', '5', 'True', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '109010000', 2, '109010000', 1, 'CASH IN HAND', '', 'A', '', '', '5', '', 1025, 1, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 3, '10904', 1, 'CUSTOMER CONTROL', '', 'A', 'True', '', 'bank', '', 1025, 1, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 4, '1090401', 1, 'PARTY-ACC-4', '', 'A', '', '', '', '', 1025, 3, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 5, '1090402', 1, 'PARTY-ACC-5', '', 'A', '', '', '', '', 1025, 3, 3, '', 'True', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '200000000', 6, '20000', 1, 'LIABILITIES', '', 'L', 'True', '', '5', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206', 7, '206', 1, 'SUPPLIER CONTROL', '', 'L', 'True', '', '', '', 1025, 6, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206', 8, '20601', 1, 'PARTY-ACC-8', '', 'L', '', '', '', '', 1025, 7, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '202050000', 9, '20205', 1, 'REFUNDS PAYABLE (CLOSED)', '', 'L', '', '', '', '', 1025, 6, 2, '', 'True', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '300000000', 10, '30000', 1, 'INCOMES', '', 'I', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '304020000', 11, '304020000', 1, 'RECOVERED CHARGES', '', 'I', '', '', '', '', 1025, 10, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '400000000', 12, '40000', 1, 'EXPENSES', '', 'E', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '428000000', 13, '428000000', 1, 'CARD FEES', '', 'E', '', '', 'bank', '', 1025, 12, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '500000000', 14, '50000', 1, 'APPROPRIATIONS', '', 'L', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            // THE DIRT: declared 'E' (expense) but its position is under the
            // 'L'-rooted APPROPRIATIONS tree. The real export carries this
            // exact row ("TRANSFER TO RESERVES").
            [1, '501000000', 15, '10142', 1, 'TRANSFER TO RESERVES', '', 'E', '', '', '', '', 1025, 14, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '600000000', 16, '60000', 1, 'EQUITY', '', 'L', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601000000', 17, '601000000', 1, 'RESERVES & SURPLUS', '', 'L', 'True', '', '', '', 1025, 16, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601010000', 18, '601010000', 1, 'RETAINED EARNINGS', '', 'L', 'True', '', '', '', 1025, 17, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601010001', 19, '212478', 1, 'PROFIT & LOSS ACCOUNT 2024', '', 'L', '', '', '', '', 1025, 18, 4, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601010002', 20, '212479', 1, 'Profit & Loss Account 2025', '', 'L', '', '', '', '', 1025, 18, 4, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];
    }

    private function load(array $rows): void
    {
        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $rows);

        app(LegacyCsvLoader::class)->load(
            'tblAccount',
            ['table' => 'stg_account', 'rows' => count($rows)],
            $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv'
        );
    }

    /**
     * THE MOST IMPORTANT ASSERTION IN LP1.
     *
     * TrialBalanceService derives debit-normal vs credit-normal from the
     * NAME of an account's root, matched CASE-SENSITIVELY against
     * ['Assets','Expenses','Liabilities','Equity','Income']. Importing the
     * legacy roots verbatim (ASSETS / INCOMES / ...) makes every asset and
     * expense leaf fall through to the credit-normal default, silently
     * inverting the sign of every such balance.
     *
     * MUTATION PROOF: make the importer keep the legacy root NAME (drop the
     * config('legacy_pilot.root_canonical_names') rename) and this test
     * fails on the very first assertion.
     */
    public function test_every_root_carries_a_name_the_trial_balance_recognises(): void
    {
        $this->load($this->tree());

        app(LegacyCoaImporter::class)->import($this->companyId);

        $roots = Account::where('company_id', $this->companyId)->whereNull('parent_id')->pluck('name')->sort()->values()->all();

        // The canonical five, and ONLY the canonical five -- six legacy
        // roots became five because APPROPRIATIONS and EQUITY share the
        // canonical name 'Equity'.
        $this->assertSame(['Assets', 'Equity', 'Expenses', 'Income', 'Liabilities'], $roots);

        foreach (Account::where('company_id', $this->companyId)->whereNotNull('parent_id')->get() as $account) {
            $rootName = Account::where('id', $account->root_id)->value('name');

            $this->assertContains(
                $rootName,
                ['Assets', 'Expenses', 'Liabilities', 'Equity', 'Income'],
                "account {$account->code} resolves to root '{$rootName}', which TrialBalanceService does not recognise ".
                '— every balance under it would silently default to credit-normal.'
            );
        }
    }

    public function test_the_second_root_sharing_a_canonical_name_is_reparented_not_dropped(): void
    {
        $this->load($this->tree());

        app(LegacyCoaImporter::class)->import($this->companyId);

        // Both legacy roots still exist as accounts; one of them is now a
        // level-2 group under the other, and its subtree came with it.
        $appropriations = Account::where('company_id', $this->companyId)->where('code', '50000')->first();
        $equity = Account::where('company_id', $this->companyId)->where('code', '60000')->first();

        $this->assertNotNull($appropriations);
        $this->assertNotNull($equity);

        $rootCount = (int) ($appropriations->parent_id === null) + (int) ($equity->parent_id === null);
        $this->assertSame(1, $rootCount, 'exactly one of the two Equity-canonical legacy roots stays a root');

        $transfer = Account::where('company_id', $this->companyId)->where('code', '10142')->first();
        $this->assertNotNull($transfer);
        $this->assertSame('Equity', Account::where('id', $transfer->root_id)->value('name'));
    }

    /**
     * MUTATION PROOF for positional classification: 10142 is declared 'E'
     * but sits under an 'L' root. Classify by the declared type and it gets
     * report_type 'profit loss' inside a balance-sheet subtree, and this
     * test fails.
     */
    public function test_a_row_whose_declared_type_contradicts_its_position_is_classified_by_position_and_flagged(): void
    {
        $this->load($this->tree());

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        $transfer = Account::where('company_id', $this->companyId)->where('code', '10142')->first();

        $this->assertSame('Liabilities', $transfer->account_type, 'classified by position, not by the declared AccType');
        $this->assertSame(Account::REPORT_TYPES['BALANCE_SHEET'], $transfer->report_type);

        $map = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)->where('acc_id', 15)->first();

        // The DECLARED type is still recorded (so the plan's 429/454/332/136
        // split reconciles), the POSITION type drives classification, and
        // the conflict is flagged rather than absorbed.
        $this->assertSame('E', $map->account_type_code);
        $this->assertSame('L', $map->position_type_code);
        $this->assertTrue((bool) $map->type_dirt);
        $this->assertSame(1, $stats['type_dirt']);
    }

    /**
     * MUTATION PROOF: delete the IsFreeze read (hardcode disabled => 0) and
     * this fails. A frozen account is imported WITH its status, never
     * dropped and never silently reactivated.
     */
    public function test_frozen_accounts_are_imported_with_their_status_preserved(): void
    {
        $this->load($this->tree());

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        $closed = Account::where('company_id', $this->companyId)->where('code', '20205')->first();
        $this->assertNotNull($closed, 'a frozen account is imported, never dropped');
        $this->assertSame(1, (int) $closed->disabled);

        $open = Account::where('company_id', $this->companyId)->where('code', '109010000')->first();
        $this->assertSame(0, (int) $open->disabled);

        // acc_id 5 is a frozen PARTY leaf: it pools (no Account of its own)
        // but the flag survives in the map.
        $map5 = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)->where('acc_id', 5)->first();
        $this->assertTrue((bool) $map5->legacy_is_freeze);

        $this->assertSame(2, $stats['frozen']);
    }

    /**
     * MUTATION PROOF: remove the missing-parent branch from
     * assertTreeIntegrity() and the import silently re-roots the orphan (and
     * its whole subtree) — this test fails on both assertions.
     */
    public function test_a_missing_parent_refuses_the_whole_import(): void
    {
        $rows = $this->tree();
        $rows[] = [1, '109010000', 99, '99999', 1, 'ORPHAN', '', 'A', '', '', '', '', 1025, 4242, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'];
        $this->load($rows);

        try {
            app(LegacyCoaImporter::class)->import($this->companyId);
            $this->fail('expected the importer to refuse an account whose ParentAccID_FK names no staged row');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ParentAccID_FK', $e->getMessage());
        }

        $this->assertSame(0, Account::where('company_id', $this->companyId)->count(), 'no partial import');
    }

    public function test_a_duplicate_acccode_refuses_the_whole_import(): void
    {
        $rows = $this->tree();
        $rows[] = [1, '109010000', 98, '109010000', 1, 'CASH IN HAND (DUPE)', '', 'A', '', '', '', '', 1025, 1, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'];
        $this->load($rows);

        try {
            app(LegacyCoaImporter::class)->import($this->companyId);
            $this->fail('expected the importer to refuse a duplicate AccCode');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('duplicate AccCode', $e->getMessage());
        }

        $this->assertSame(0, Account::where('company_id', $this->companyId)->count());
    }

    public function test_an_acclevel_inconsistent_with_the_parent_chain_refuses_the_whole_import(): void
    {
        $rows = $this->tree();
        // parent 1 is AccLevel 1, so this child must be AccLevel 2 -- not 4.
        $rows[] = [1, '109010000', 97, '97777', 1, 'BAD LEVEL', '', 'A', '', '', '', '', 1025, 1, 4, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'];
        $this->load($rows);

        try {
            app(LegacyCoaImporter::class)->import($this->companyId);
            $this->fail('expected the importer to refuse an AccLevel inconsistent with the parent chain');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('AccLevel', $e->getMessage());
        }

        $this->assertSame(0, Account::where('company_id', $this->companyId)->count());
    }

    /**
     * MUTATION PROOF for the "never guess a canonical root name" rule:
     * empty the config map and the import must refuse rather than import a
     * root whose name the trial balance cannot classify.
     */
    public function test_an_unmapped_root_refuses_rather_than_guessing_a_canonical_name(): void
    {
        $this->load($this->tree());

        config(['legacy_pilot.root_canonical_names' => ['10000' => 'Assets']]);

        try {
            app(LegacyCoaImporter::class)->import($this->companyId);
            $this->fail('expected the importer to refuse a root with no canonical-name mapping');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('root_canonical_names', $e->getMessage());
        }

        $this->assertSame(0, Account::where('company_id', $this->companyId)->count());
    }

    public function test_levels_are_derived_from_our_own_parent_chain(): void
    {
        $this->load($this->tree());

        app(LegacyCoaImporter::class)->import($this->companyId);

        $accounts = Account::where('company_id', $this->companyId)->get()->keyBy('id');

        foreach ($accounts as $account) {
            if ($account->parent_id === null) {
                $this->assertSame(1, (int) $account->level);
                $this->assertNull($account->root_id);

                continue;
            }

            $parent = $accounts->get($account->parent_id);
            $this->assertNotNull($parent, "parent {$account->parent_id} missing for {$account->code}");
            $this->assertSame((int) $parent->level + 1, (int) $account->level);
            $this->assertSame((int) ($parent->root_id ?? $parent->id), (int) $account->root_id);
        }
    }
}
