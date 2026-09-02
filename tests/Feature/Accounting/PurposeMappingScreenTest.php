<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Http\Livewire\Accounting\PurposeMappingIndex;
use App\Models\Account;
use App\Models\AccountingAuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\SystemAccount;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Livewire\Livewire;
use Tests\Support\AccountingTestCase;

/**
 * COA UI lane (2026-08-31, scope item 1): HTTP + Livewire tests for the purpose-mapping repair
 * screen — view renders, a gap is flagged, "map to existing account" repairs it and writes an
 * accounting_audit_log row, and a non-entitled request 404s (module:accounting middleware's own
 * documented fail-closed contract — see App\Http\Middleware\EnsureModuleEnabled).
 */
class PurposeMappingScreenTest extends AccountingTestCase
{
    private function makeCompanyAndAdmin(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        // COAPolicy::viewAny()/update() bypass on Spatie's hasRole('admin'), NOT role_id ===
        // Role::ADMIN (unlike AccountingAuditLogPolicy/AccountingPeriodPolicy, which check both) --
        // a bare role_id=ADMIN user still falls through to $user->can('view coa')/'update coa'
        // with no permission granted, and gets a real 403. Assign the Spatie role so this fixture
        // matches every other COA-gated test in this suite (see
        // tests/Feature/Security/AccountingRouteGateTest.php's own createCompanyOwner()).
        $spatieRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole($spatieRole);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        return [$company, $admin];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('accounting.purpose-mapping.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_page_renders_for_an_authorized_admin(): void
    {
        [, $admin] = $this->makeCompanyAndAdmin();

        $this->actingAs($admin)->get(route('accounting.purpose-mapping.index'))->assertOk();
    }

    /**
     * "non-entitled" per this lane's own brief == the module:accounting middleware's documented
     * fail-closed 404 (EnsureModuleEnabled::handle()'s own docblock: "Aborts with 404 (never 403)
     * ... A 403 would confirm to the caller that the route exists"). An ADMIN with no
     * session('company_id') at all resolves to NO company, so moduleEnabled() fails closed.
     */
    public function test_non_entitled_request_is_404(): void
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session()->forget('company_id');

        $this->actingAs($admin)->get(route('accounting.purpose-mapping.index'))->assertNotFound();
    }

    public function test_unmapped_purpose_code_is_flagged_as_a_gap(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        SystemAccount::where('company_id', $company->id)->where('purpose_code', 'SUSPENSE')->delete();

        // gapCount is a view variable computed inside render(), not a public Livewire property —
        // read it via viewData(), not assertSet() (which only reads component properties).
        $test = Livewire::actingAs($admin)
            ->test(PurposeMappingIndex::class)
            ->assertSee('SUSPENSE')
            ->assertSee('Unmapped');

        $this->assertGreaterThanOrEqual(1, $test->viewData('gapCount'));
    }

    public function test_map_to_existing_account_repairs_the_gap_and_writes_an_audit_row(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        SystemAccount::where('company_id', $company->id)->where('purpose_code', 'SUSPENSE')->delete();

        $target = Account::where('company_id', $company->id)->where('name', 'Markup Income')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(PurposeMappingIndex::class)
            ->call('startRepair', 'SUSPENSE', null)
            ->set('accountSearch', $target->code)
            ->call('mapToAccount', $target->id)
            ->assertSet('flashType', 'success');

        $this->assertDatabaseHas('system_accounts', [
            'company_id' => $company->id,
            'purpose_code' => 'SUSPENSE',
            'account_id' => $target->id,
        ]);

        $this->assertTrue(
            AccountingAuditLog::where('company_id', $company->id)
                ->where('action', 'purpose_mapping_repaired')
                ->where('subject_type', 'system_account')
                ->exists(),
            'Expected a purpose_mapping_repaired accounting_audit_log row.'
        );
    }

    public function test_mapping_to_a_non_leaf_account_is_refused(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        SystemAccount::where('company_id', $company->id)->where('purpose_code', 'SUSPENSE')->delete();

        $group = Account::where('company_id', $company->id)->where('name', 'Commission & Service Fee Income')->firstOrFail();
        $this->assertTrue($group->children()->exists(), 'Precondition: this account must have children (not a leaf).');

        Livewire::actingAs($admin)
            ->test(PurposeMappingIndex::class)
            ->call('startRepair', 'SUSPENSE', null)
            ->call('mapToAccount', $group->id)
            ->assertSet('flashType', 'error');

        $this->assertDatabaseMissing('system_accounts', [
            'company_id' => $company->id,
            'purpose_code' => 'SUSPENSE',
            'account_id' => $group->id,
        ]);
    }

    public function test_create_leaf_creates_the_decided_account_and_maps_it(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        // Simulate an "old company" gap: the decided 4132 leaf and its mapping never existed.
        $markup = Account::where('company_id', $company->id)->where('code', '4132')->first();
        if ($markup !== null) {
            SystemAccount::where('company_id', $company->id)->where('account_id', $markup->id)->delete();
            $markup->delete();
        }

        Livewire::actingAs($admin)
            ->test(PurposeMappingIndex::class)
            ->call('createLeaf', 'MARKUP_INCOME')
            ->assertSet('flashType', 'success');

        $created = Account::where('company_id', $company->id)->where('code', '4132')->first();
        $this->assertNotNull($created, 'Expected the 4132 Markup Income leaf to be created.');
        $this->assertSame('Markup Income', $created->name);

        $this->assertDatabaseHas('system_accounts', [
            'company_id' => $company->id,
            'purpose_code' => 'MARKUP_INCOME',
            'account_id' => $created->id,
        ]);
    }
}
