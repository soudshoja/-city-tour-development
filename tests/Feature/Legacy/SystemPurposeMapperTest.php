<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\SystemPurposeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1.2 — SystemPurposeMapper. Never invents a mapping:
 * left unconfigured, every purpose reports 'unmapped' honestly.
 */
class SystemPurposeMapperTest extends TestCase
{
    use PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();

        $country = Country::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'country_id' => $country->id]);
        $this->companyId = $company->id;

        config(['accounting.purpose_codes.global' => ['RECEIVABLE_CONTROL', 'PAYABLE_CONTROL', 'SUSPENSE']]);

        // Normally created dynamically by LegacyCsvLoader from the CSV's
        // own header row -- created directly here since these tests only
        // exercise SystemPurposeMapper, not the loader.
        \Illuminate\Support\Facades\Schema::connection('legacy_pilot')->create('stg_system_parameters', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id('stg_row_id');
            $table->text('parametername')->nullable();
            $table->text('parametervalue')->nullable();
        });
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Schema::connection('legacy_pilot')->dropIfExists('stg_system_parameters');

        parent::tearDown();
    }

    /**
     * MUTATION PROOF: hardcoding a mapping (or defaulting an unmatched
     * purpose to "mapped") instead of reporting 'unmapped' would make this
     * test fail — with an empty parameter_purpose_map, EVERY purpose must
     * report unmapped.
     */
    public function test_with_no_configured_mapping_every_purpose_reports_unmapped(): void
    {
        $stats = app(SystemPurposeMapper::class)->map($this->companyId, []);

        $this->assertSame(0, $stats['mapped']);
        $this->assertSame(3, $stats['unmapped']);
        $this->assertSame(0, $stats['skipped_non_leaf']);
    }

    public function test_a_configured_mapping_resolves_to_the_mapped_leaf_account(): void
    {
        DB::connection('legacy_pilot')->table('stg_system_parameters')->insert([
            'parametername' => 'DefaultCustomerControl',
            'parametervalue' => '501',
        ]);

        $leaf = Account::create([
            'name' => 'Clients', 'code' => '1350', 'level' => 2,
            'company_id' => $this->companyId, 'account_type' => 'Assets',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => false, 'disabled' => 0,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);

        DB::connection('legacy_pilot')->table('legacy_acc_map')->insert([
            'acc_id' => 501, 'acc_code' => '1350', 'company_id' => $this->companyId,
            'account_id' => $leaf->id, 'resolution' => 'direct',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $stats = app(SystemPurposeMapper::class)->map($this->companyId, ['RECEIVABLE_CONTROL' => 'DefaultCustomerControl']);

        $this->assertSame(1, $stats['mapped']);
        $this->assertSame(2, $stats['unmapped']);

        $row = DB::connection('legacy_pilot')->table('map_purpose')->where('company_id', $this->companyId)->where('purpose_code', 'RECEIVABLE_CONTROL')->first();
        $this->assertSame('mapped', $row->status);
        $this->assertSame($leaf->id, $row->account_id);
    }

    /**
     * A group with SEVERAL leaf children is still refused. Under LP1c
     * ruling R2 a group is no longer a flat refusal — the mapper descends to
     * a group's single leaf child when exactly one exists — but picking one
     * of several would be a guess, and this phase never guesses a posting
     * target.
     *
     * MUTATION PROOF: drop the `count() !== 1` guard and let the descent
     * take the FIRST leaf child instead, and this test fails ('mapped'
     * instead of 'skipped_non_leaf').
     */
    public function test_a_mapping_pointed_at_a_group_with_several_leaf_children_is_refused(): void
    {
        $group = $this->groupWithLeafChildren(['Clients' => '1351', 'Staff Receivables' => '1352']);

        $stats = app(SystemPurposeMapper::class)->map($this->companyId, ['RECEIVABLE_CONTROL' => 'DefaultCustomerControl']);

        $this->assertSame(0, $stats['mapped']);
        $this->assertSame(1, $stats['skipped_non_leaf']);

        $row = DB::connection('legacy_pilot')->table('map_purpose')->where('company_id', $this->companyId)->where('purpose_code', 'RECEIVABLE_CONTROL')->first();
        $this->assertSame('skipped_non_leaf', $row->status);
        $this->assertStringContainsString('2 leaf child', (string) $row->reason);
        $this->assertStringContainsString($group->code, (string) $row->reason);
    }

    /**
     * R2: a group with EXACTLY ONE leaf child descends to it. This is the
     * RETAINED_EARNINGS shape — `ProfitLossAccount` names group 6301, whose
     * real posting target is the leaf one level below it.
     */
    public function test_a_mapping_pointed_at_a_group_with_one_leaf_child_descends_to_that_leaf(): void
    {
        $this->groupWithLeafChildren(['Retained Earnings' => '1351']);

        $stats = app(SystemPurposeMapper::class)->map($this->companyId, ['RECEIVABLE_CONTROL' => 'DefaultCustomerControl']);

        $this->assertSame(1, $stats['mapped']);
        $this->assertSame(0, $stats['skipped_non_leaf']);

        $leaf = Account::where('company_id', $this->companyId)->where('code', '1351')->first();
        $row = DB::connection('legacy_pilot')->table('map_purpose')->where('company_id', $this->companyId)->where('purpose_code', 'RECEIVABLE_CONTROL')->first();

        $this->assertSame('mapped', $row->status);
        $this->assertSame($leaf->id, $row->account_id);
        $this->assertStringContainsString('descended from group', (string) $row->reason);

        // The row the ENGINE reads must point at the LEAF, not the group.
        $this->assertSame($leaf->id, (int) DB::table('system_accounts')
            ->where('company_id', $this->companyId)
            ->where('purpose_code', 'RECEIVABLE_CONTROL')
            ->value('account_id'));
    }

    /**
     * R2: a group with NO leaf children at all (every child is itself a
     * group) is refused, not descended into recursively.
     */
    public function test_a_mapping_pointed_at_a_group_with_no_leaf_children_is_refused(): void
    {
        $group = $this->groupWithLeafChildren(['Sub Group' => '1351']);
        $child = Account::where('company_id', $this->companyId)->where('code', '1351')->first();

        Account::create([
            'name' => 'Grandchild', 'code' => '1351001', 'level' => 4, 'parent_id' => $child->id,
            'company_id' => $this->companyId, 'account_type' => 'Assets',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => false, 'disabled' => 0,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);

        $stats = app(SystemPurposeMapper::class)->map($this->companyId, ['RECEIVABLE_CONTROL' => 'DefaultCustomerControl']);

        $this->assertSame(0, $stats['mapped']);
        $this->assertSame(1, $stats['skipped_non_leaf']);

        $row = DB::connection('legacy_pilot')->table('map_purpose')->where('company_id', $this->companyId)->where('purpose_code', 'RECEIVABLE_CONTROL')->first();
        $this->assertStringContainsString('0 leaf child', (string) $row->reason);
        $this->assertStringContainsString($group->code, (string) $row->reason);
    }

    /**
     * R2 extension 1: a --purpose-map VALUE may be a bare numeric legacy
     * Acc_ID, for the case where no tblSystemParameters key names the real
     * target leaf (RETAINED_EARNINGS -> 6301012 on the real export). No
     * parameter row exists here at all — if the mapper still required one,
     * this would report 'unmapped'.
     */
    public function test_a_bare_numeric_acc_id_maps_without_any_legacy_parameter(): void
    {
        $leaf = Account::create([
            'name' => 'Retained Earnings', 'code' => '60011', 'level' => 3,
            'company_id' => $this->companyId, 'account_type' => 'Liabilities',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => false, 'disabled' => 0,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);

        DB::connection('legacy_pilot')->table('legacy_acc_map')->insert([
            'acc_id' => 6301012, 'acc_code' => '60011', 'company_id' => $this->companyId,
            'account_id' => $leaf->id, 'resolution' => 'direct',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $stats = app(SystemPurposeMapper::class)->map($this->companyId, ['RECEIVABLE_CONTROL' => '6301012']);

        $this->assertSame(1, $stats['mapped']);

        $row = DB::connection('legacy_pilot')->table('map_purpose')->where('company_id', $this->companyId)->where('purpose_code', 'RECEIVABLE_CONTROL')->first();
        $this->assertSame('mapped', $row->status);
        $this->assertSame($leaf->id, $row->account_id);
        // No parameter was consulted, so none is recorded — the report must
        // not imply a legacy key that was never read.
        $this->assertNull($row->legacy_parameter_name);
    }

    /**
     * Builds the group `DefaultCustomerControl` points at, with the given
     * `name => code` children.
     */
    private function groupWithLeafChildren(array $children): Account
    {
        DB::connection('legacy_pilot')->table('stg_system_parameters')->insert([
            'parametername' => 'DefaultCustomerControl',
            'parametervalue' => '501',
        ]);

        $group = Account::create([
            'name' => 'Accounts Receivable', 'code' => '1350', 'level' => 2,
            'company_id' => $this->companyId, 'account_type' => 'Assets',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => true, 'disabled' => 0,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);

        foreach ($children as $name => $code) {
            Account::create([
                'name' => $name, 'code' => $code, 'level' => 3, 'parent_id' => $group->id,
                'company_id' => $this->companyId, 'account_type' => 'Assets',
                'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
                'is_group' => false, 'disabled' => 0,
                'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
            ]);
        }

        DB::connection('legacy_pilot')->table('legacy_acc_map')->insert([
            'acc_id' => 501, 'acc_code' => '1350', 'company_id' => $this->companyId,
            'account_id' => $group->id, 'resolution' => 'direct',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $group;
    }

    public function test_it_never_hardcodes_a_real_account_id_when_the_legacy_parameter_is_missing(): void
    {
        $stats = app(SystemPurposeMapper::class)->map($this->companyId, ['RECEIVABLE_CONTROL' => 'SomeParameterThatDoesNotExist']);

        $this->assertSame(0, $stats['mapped']);

        $row = DB::connection('legacy_pilot')->table('map_purpose')->where('company_id', $this->companyId)->where('purpose_code', 'RECEIVABLE_CONTROL')->first();
        $this->assertSame('unmapped', $row->status);
        $this->assertNull($row->account_id);
    }
}
