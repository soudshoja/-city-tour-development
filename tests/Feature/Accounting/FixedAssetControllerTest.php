<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T10 (Lane G): HTTP feature tests for
 * {@see \App\Http\Controllers\Accounting\FixedAssetController} — every route's authorization
 * (view/manage via {@see \App\Policies\FixedAssetPolicy}), tenant isolation (MP-10-1), the three
 * hard constraints carried over from the Lane B sign-off (status never accepted, frozen fields
 * rejected server-side, NBV always live), the dedicated disposal endpoint, and the
 * depreciation-run dry-run/real/engine-off matrix.
 */
class FixedAssetControllerTest extends AccountingTestCase
{
    private function assets(): FixedAssetService
    {
        return app(FixedAssetService::class);
    }

    private function runner(): DepreciationRunService
    {
        return app(DepreciationRunService::class);
    }

    /** @return array{0: Company, 1: User} */
    private function makeEngineOnCompanyWithAdmin(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $admin];
    }

    /** @return array{0: Company, 1: User} an engine-registered company with the ENGINE FLAG OFF */
    private function makeEngineOffCompanyWithAdmin(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        config(['accounting.engine.enabled' => false]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $admin];
    }

    private function makeAgentInCompany(Company $company): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    private function makeDraftAsset(Company $company, array $overrides = []): FixedAsset
    {
        return $this->assets()->create(array_merge([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Warehouse Forklift',
            'code' => 'FA-CTRL-001',
            'cost' => 1200.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 12,
        ], $overrides));
    }

    private function makeActiveAsset(Company $company, array $overrides = []): FixedAsset
    {
        return $this->makeDraftAsset($company, array_merge(['status' => FixedAsset::STATUS_ACTIVE], $overrides));
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    // ── guest / authorization ───────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $response = $this->get(route('accounting.fixed-assets.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_403s_for_an_unauthorized_agent(): void
    {
        $company = Company::factory()->create();
        $agent = $this->makeAgentInCompany($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->actingAs($agent)->get(route('accounting.fixed-assets.index'));

        $response->assertStatus(403);
    }

    public function test_create_403s_for_an_unauthorized_agent(): void
    {
        $company = Company::factory()->create();
        $agent = $this->makeAgentInCompany($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->actingAs($agent)->get(route('accounting.fixed-assets.create'));

        $response->assertStatus(403);
    }

    public function test_store_403s_for_an_unauthorized_agent(): void
    {
        $company = Company::factory()->create();
        $agent = $this->makeAgentInCompany($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->actingAs($agent)->post(route('accounting.fixed-assets.store'), [
            'asset_class' => 'CAPITAL_EQUIPMENT', 'name' => 'X', 'cost' => 100,
            'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 12,
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, FixedAsset::query()->where('company_id', $company->id)->count());
    }

    public function test_depreciate_form_403s_for_an_unauthorized_agent(): void
    {
        $company = Company::factory()->create();
        $agent = $this->makeAgentInCompany($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->actingAs($agent)->get(route('accounting.fixed-assets.depreciate'));

        $response->assertStatus(403);
    }

    // ── render smoke tests ──────────────────────────────────────────────────────────────────

    public function test_index_renders_with_nbv_column_and_totals(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 4, 'cost' => 400, 'salvage' => 0]);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $response = $this->actingAs($admin)->get(route('accounting.fixed-assets.index'));

        $response->assertOk();
        $response->assertViewIs('accounting.fixed-assets.index');
        $items = $response->viewData('items');
        $row = collect($items)->firstWhere('asset.id', $asset->id);
        $this->assertNotNull($row);
        // 400/4 = 100 per month, one month posted -> NBV must be the LIVE derivation (300), not cost.
        $this->assertEqualsWithDelta(300.0, $row['nbv'], 0.0005);
        $totals = $response->viewData('totals');
        $this->assertEqualsWithDelta(300.0, $totals['nbv'], 0.0005);
    }

    public function test_create_form_renders_for_an_authorized_admin(): void
    {
        [, $admin] = $this->makeEngineOnCompanyWithAdmin();

        $response = $this->actingAs($admin)->get(route('accounting.fixed-assets.create'));

        $response->assertOk();
        $response->assertViewIs('accounting.fixed-assets.create');
    }

    public function test_edit_form_renders_and_flags_frozen_basis_once_depreciation_has_posted(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 6, 'cost' => 600, 'salvage' => 0]);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $response = $this->actingAs($admin)->get(route('accounting.fixed-assets.edit', $asset));

        $response->assertOk();
        $response->assertViewIs('accounting.fixed-assets.edit');
        $this->assertTrue($response->viewData('basisFrozen'));
    }

    public function test_edit_form_shows_basis_not_frozen_before_any_depreciation_posts(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeDraftAsset($company);

        $response = $this->actingAs($admin)->get(route('accounting.fixed-assets.edit', $asset));

        $response->assertOk();
        $this->assertFalse($response->viewData('basisFrozen'));
    }

    public function test_show_renders_schedule_with_posted_state_and_document_link(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 3, 'cost' => 300, 'salvage' => 0]);
        $this->runner()->runForMonth($company->id, 2026, 1);
        $asset->refresh();
        $depreciation = FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->first();

        $response = $this->actingAs($admin)->get(route('accounting.fixed-assets.show', $asset));

        $response->assertOk();
        $response->assertViewIs('accounting.fixed-assets.show');
        $schedule = $response->viewData('schedule');
        $this->assertCount(3, $schedule);
        $this->assertTrue($schedule[0]['posted']);
        $this->assertSame($depreciation->transaction_id, $schedule[0]['transaction_id']);
        $this->assertFalse($schedule[1]['posted']);
        $response->assertSee('#'.$depreciation->transaction_id, false);
    }

    public function test_depreciate_form_renders_a_dry_run_preview_without_posting(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $this->makeActiveAsset($company, ['useful_life_months' => 5, 'cost' => 500, 'salvage' => 0]);

        $response = $this->actingAs($admin)->get(route('accounting.fixed-assets.depreciate', ['year' => 2026, 'month' => 1]));

        $response->assertOk();
        $preview = $response->viewData('preview');
        $this->assertTrue($preview['dry_run']);
        $this->assertCount(1, $preview['lines']);
        $this->assertSame(0, FixedAssetDepreciation::query()->count());
    }

    // ── hard constraint 1: status is never accepted ─────────────────────────────────────────

    public function test_store_rejects_a_posted_status_field(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.store'), [
            'asset_class' => 'CAPITAL_EQUIPMENT', 'name' => 'Smuggled Active Asset', 'cost' => 500,
            'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 12,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame(0, FixedAsset::query()->where('company_id', $company->id)->count());
    }

    public function test_store_without_a_status_field_always_creates_a_draft_asset(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.store'), [
            'asset_class' => 'CAPITAL_EQUIPMENT', 'name' => 'Plain Register Row', 'cost' => 500,
            'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 12,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $asset = FixedAsset::where('company_id', $company->id)->where('name', 'Plain Register Row')->firstOrFail();
        $this->assertSame(FixedAsset::STATUS_DRAFT, $asset->status);
    }

    public function test_update_rejects_a_posted_status_field(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeDraftAsset($company);

        $response = $this->actingAs($admin)->put(route('accounting.fixed-assets.update', $asset), [
            'asset_class' => $asset->asset_class, 'name' => $asset->name, 'cost' => (float) $asset->cost,
            'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 12,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame(FixedAsset::STATUS_DRAFT, $asset->fresh()->status);
    }

    // ── hard constraint 2: frozen basis fields ──────────────────────────────────────────────

    public function test_update_rejects_a_frozen_field_change_once_depreciation_has_posted(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 5, 'cost' => 1000, 'salvage' => 0]);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $response = $this->actingAs($admin)->put(route('accounting.fixed-assets.update', $asset), [
            'asset_class' => $asset->asset_class, 'name' => $asset->name, 'cost' => 500.000,
            'acquisition_date' => (string) $asset->acquisition_date, 'in_service_date' => (string) $asset->in_service_date,
            'useful_life_months' => $asset->useful_life_months,
        ]);

        $response->assertSessionHasErrors('cost');
        $this->assertEqualsWithDelta(1000.0, (float) $asset->fresh()->cost, 0.0005);
    }

    public function test_update_allows_a_basis_change_before_any_depreciation_has_posted(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeDraftAsset($company, ['cost' => 1000, 'salvage' => 0]);

        $response = $this->actingAs($admin)->put(route('accounting.fixed-assets.update', $asset), [
            'asset_class' => $asset->asset_class, 'name' => $asset->name, 'cost' => 850.000,
            'acquisition_date' => (string) $asset->acquisition_date, 'in_service_date' => (string) $asset->in_service_date,
            'useful_life_months' => $asset->useful_life_months,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertEqualsWithDelta(850.0, (float) $asset->fresh()->cost, 0.0005);
    }

    public function test_update_rejects_an_unknown_asset_class(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeDraftAsset($company);

        $response = $this->actingAs($admin)->put(route('accounting.fixed-assets.update', $asset), [
            'asset_class' => 'NOT_A_REAL_CLASS', 'name' => $asset->name, 'cost' => (float) $asset->cost,
            'acquisition_date' => (string) $asset->acquisition_date, 'in_service_date' => (string) $asset->in_service_date,
            'useful_life_months' => $asset->useful_life_months,
        ]);

        $response->assertSessionHasErrors('asset_class');
    }

    public function test_store_validation_rejects_salvage_greater_than_cost(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.store'), [
            'asset_class' => 'CAPITAL_EQUIPMENT', 'name' => 'Bad Asset', 'cost' => 100, 'salvage' => 500,
            'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 12,
        ]);

        $response->assertSessionHasErrors('cost');
        $this->assertSame(0, FixedAsset::query()->where('company_id', $company->id)->where('name', 'Bad Asset')->count());
    }

    // ── tenant isolation (MP-10-1) ───────────────────────────────────────────────────────────

    public function test_show_404s_for_a_different_companys_asset(): void
    {
        [$companyA] = $this->makeEngineOnCompanyWithAdmin();
        $foreignAsset = $this->makeDraftAsset($companyA);

        $companyB = Company::factory()->create();
        CoaSeeder::run($companyB->id);
        (new SystemAccountsSeeder)->run();
        $adminB = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $companyB->id]);
        $this->trackCompanyForInvariants($companyB->id);

        $response = $this->actingAs($adminB)->get(route('accounting.fixed-assets.show', $foreignAsset));

        $response->assertStatus(404);
    }

    public function test_edit_404s_for_a_different_companys_asset(): void
    {
        [$companyA] = $this->makeEngineOnCompanyWithAdmin();
        $foreignAsset = $this->makeDraftAsset($companyA);

        $companyB = Company::factory()->create();
        CoaSeeder::run($companyB->id);
        (new SystemAccountsSeeder)->run();
        $adminB = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $companyB->id]);
        $this->trackCompanyForInvariants($companyB->id);

        $response = $this->actingAs($adminB)->get(route('accounting.fixed-assets.edit', $foreignAsset));

        $response->assertStatus(404);
    }

    public function test_dispose_404s_for_a_different_companys_asset(): void
    {
        [$companyA] = $this->makeEngineOnCompanyWithAdmin();
        $foreignAsset = $this->makeActiveAsset($companyA);

        $companyB = Company::factory()->create();
        CoaSeeder::run($companyB->id);
        (new SystemAccountsSeeder)->run();
        $adminB = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $companyB->id]);
        $this->trackCompanyForInvariants($companyB->id);

        $response = $this->actingAs($adminB)->post(route('accounting.fixed-assets.dispose', $foreignAsset), [
            'disposal_date' => '2026-02-01', 'proceeds' => 0,
        ]);

        $response->assertStatus(404);
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $foreignAsset->fresh()->status);
    }

    public function test_index_only_lists_the_resolved_companys_assets(): void
    {
        [$companyA, $adminA] = $this->makeEngineOnCompanyWithAdmin();
        $this->makeDraftAsset($companyA, ['name' => 'Company A Asset']);

        $companyB = Company::factory()->create();
        CoaSeeder::run($companyB->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($companyB->id);
        $assetsService = $this->assets();
        $assetsService->create([
            'company_id' => $companyB->id, 'asset_class' => 'CAPITAL_EQUIPMENT', 'name' => 'Company B Asset',
            'cost' => 200, 'salvage' => 0, 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01',
            'useful_life_months' => 12,
        ]);

        $response = $this->actingAs($adminA)->get(route('accounting.fixed-assets.index'));

        $names = collect($response->viewData('items'))->map(fn ($row) => $row['asset']->name);
        $this->assertTrue($names->contains('Company A Asset'));
        $this->assertFalse($names->contains('Company B Asset'));
    }

    // ── capitalise ───────────────────────────────────────────────────────────────────────────

    /**
     * Deliberately NOT AccountResolver::bankCashLeafIds()[0] — that set mixes bank AND cash
     * leaves, but capitalise()/dispose()'s explicit-account path validates with
     * assertUnderBankGroup(), which rejects a cash leaf (e.g. Petty Cash). This filters through
     * assertUnderBankGroup() itself, the same way FixedAssetController::bankOnlyLeaves() does for
     * the real dropdown, so the fixture picks an id the endpoint will actually accept.
     */
    private function resolveBankOnlyLeafId(int $companyId): int
    {
        $resolver = app(\App\Services\Accounting\AccountResolver::class);

        foreach ($resolver->bankCashLeafIds($companyId) as $id) {
            try {
                $resolver->assertUnderBankGroup($id, $companyId);

                return $id;
            } catch (\App\Exceptions\Accounting\PostingException) {
                continue;
            }
        }

        $this->fail('Fixture company must have at least one leaf under the Bank Accounts group.');
    }

    public function test_capitalise_posts_and_activates_the_asset_when_engine_is_on(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeDraftAsset($company);
        $bankLeafId = $this->resolveBankOnlyLeafId($company->id);

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.capitalise', $asset), [
            'bank_account_id' => $bankLeafId,
        ]);

        $response->assertRedirect(route('accounting.fixed-assets.show', $asset));
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->status);
        $this->assertNotNull($asset->acquisition_transaction_id);
    }

    public function test_capitalise_engine_off_shows_honest_message_and_does_not_post(): void
    {
        [$company, $admin] = $this->makeEngineOffCompanyWithAdmin();
        $asset = $this->makeDraftAsset($company);
        $bankLeafId = $this->resolveBankOnlyLeafId($company->id);

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.capitalise', $asset), [
            'bank_account_id' => $bankLeafId,
        ]);

        $response->assertRedirect(route('accounting.fixed-assets.show', $asset));
        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, 'Engine disabled');
        });
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_DRAFT, $asset->status);
        $this->assertNull($asset->acquisition_transaction_id);
    }

    public function test_capitalise_403s_for_an_unauthorized_agent(): void
    {
        [$company] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeDraftAsset($company);
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->post(route('accounting.fixed-assets.capitalise', $asset), [
            'bank_account_id' => 1,
        ]);

        $response->assertStatus(403);
        $this->assertSame(FixedAsset::STATUS_DRAFT, $asset->fresh()->status);
    }

    // ── dispose (hard constraint 3: dedicated, guarded endpoint) ────────────────────────────

    public function test_dispose_posts_a_balanced_document_and_shows_the_gain_or_loss(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 4, 'cost' => 400, 'salvage' => 0]);
        $this->runner()->runForMonth($company->id, 2026, 1); // NBV -> 300

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.dispose', $asset), [
            'disposal_date' => '2026-02-15', 'proceeds' => 350.000,
        ]);

        $response->assertRedirect(route('accounting.fixed-assets.show', $asset));
        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, 'gain of 50.000 KWD');
        });
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_DISPOSED, $asset->status);
        $this->assertNotNull($asset->disposal_transaction_id);
    }

    /**
     * Verifier pinning test (adversarial pass, T10): before the fix, a disposal dated into a
     * locked period posted silently into the next open period (the engine's own documented
     * "shift, not refuse" mechanic — T2-T4 packet §12) with the flash message giving no hint the
     * ledger date differed from what the user typed. Locks 2026-01, leaves 2026-02 open, disposes
     * with a 2026-01-15 date and asserts the message names both months.
     */
    public function test_dispose_into_a_locked_period_tells_the_user_it_shifted(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 4, 'cost' => 400, 'salvage' => 0]);
        $this->runner()->runForMonth($company->id, 2026, 1); // NBV -> 300

        AccountingPeriod::updateOrCreate(
            ['company_id' => $company->id, 'year' => 2026, 'month' => 1],
            ['status' => AccountingPeriod::STATUS_LOCKED]
        );
        AccountingPeriod::updateOrCreate(
            ['company_id' => $company->id, 'year' => 2026, 'month' => 2],
            ['status' => AccountingPeriod::STATUS_OPEN]
        );

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.dispose', $asset), [
            'disposal_date' => '2026-01-15', 'proceeds' => 300.000,
        ]);

        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, '2026-01') && str_contains($message, '2026-02') && str_contains($message, 'locked or closed');
        });
        $this->assertSame(FixedAsset::STATUS_DISPOSED, $asset->fresh()->status);
    }

    /**
     * Verifier pinning test (adversarial pass, T10): the same honesty gap for the depreciation
     * run screen — a real post into a locked month previously said only "N posted, M skipped"
     * with no indication the documents actually landed a month later in the ledger.
     */
    public function test_depreciate_run_into_a_locked_period_tells_the_user_it_shifted(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $this->makeActiveAsset($company, ['useful_life_months' => 4, 'cost' => 400, 'salvage' => 0]);

        AccountingPeriod::updateOrCreate(
            ['company_id' => $company->id, 'year' => 2026, 'month' => 1],
            ['status' => AccountingPeriod::STATUS_LOCKED]
        );
        AccountingPeriod::updateOrCreate(
            ['company_id' => $company->id, 'year' => 2026, 'month' => 2],
            ['status' => AccountingPeriod::STATUS_OPEN]
        );

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.depreciate.run'), [
            'year' => 2026, 'month' => 1,
        ]);

        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, '2026-01') && str_contains($message, 'locked');
        });
        $this->assertTrue(FixedAssetDepreciation::where('period_year', 2026)->where('period_month', 1)->exists());
    }

    public function test_disposing_twice_via_the_endpoint_is_idempotent(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 4, 'cost' => 400, 'salvage' => 0]);

        $this->actingAs($admin)->post(route('accounting.fixed-assets.dispose', $asset), [
            'disposal_date' => '2026-02-15', 'proceeds' => 400.000,
        ]);
        $firstTransactionId = $asset->fresh()->disposal_transaction_id;

        $this->actingAs($admin)->post(route('accounting.fixed-assets.dispose', $asset), [
            'disposal_date' => '2026-03-01', 'proceeds' => 999.000,
        ]);

        $asset->refresh();
        $this->assertSame($firstTransactionId, $asset->disposal_transaction_id);
        $this->assertEqualsWithDelta(400.0, (float) $asset->disposal_proceeds, 0.0005);
    }

    public function test_dispose_engine_off_shows_honest_message_and_does_not_post(): void
    {
        [$company, $admin] = $this->makeEngineOffCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company);

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.dispose', $asset), [
            'disposal_date' => '2026-02-15', 'proceeds' => 100.000,
        ]);

        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, 'Engine disabled');
        });
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->fresh()->status);
    }

    public function test_dispose_403s_for_an_unauthorized_agent(): void
    {
        [$company] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company);
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->post(route('accounting.fixed-assets.dispose', $asset), [
            'disposal_date' => '2026-02-15', 'proceeds' => 0,
        ]);

        $response->assertStatus(403);
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->fresh()->status);
    }

    // ── depreciation run: dry-run preview / real post / engine-off ─────────────────────────

    public function test_depreciate_run_posts_the_previewed_documents(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $this->makeActiveAsset($company, ['useful_life_months' => 6, 'cost' => 600, 'salvage' => 0]);

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.depreciate.run'), [
            'year' => 2026, 'month' => 1,
        ]);

        $response->assertRedirect(route('accounting.fixed-assets.depreciate', ['year' => 2026, 'month' => 1]));
        $this->assertSame(1, FixedAssetDepreciation::query()->count());
    }

    public function test_depreciate_run_engine_off_posts_nothing_and_says_so(): void
    {
        [$company, $admin] = $this->makeEngineOffCompanyWithAdmin();
        $this->makeActiveAsset($company, ['useful_life_months' => 6, 'cost' => 600, 'salvage' => 0]);

        $response = $this->actingAs($admin)->post(route('accounting.fixed-assets.depreciate.run'), [
            'year' => 2026, 'month' => 1,
        ]);

        $response->assertSessionHas('success', function (string $message) {
            return str_contains($message, 'Engine disabled');
        });
        $this->assertSame(0, FixedAssetDepreciation::query()->count());
    }

    public function test_depreciate_run_403s_for_an_unauthorized_agent(): void
    {
        [$company] = $this->makeEngineOnCompanyWithAdmin();
        $this->makeActiveAsset($company);
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->post(route('accounting.fixed-assets.depreciate.run'), [
            'year' => 2026, 'month' => 1,
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, FixedAssetDepreciation::query()->count());
    }

    // ── NBV is always derived, never cached (mutation target) ──────────────────────────────

    public function test_nbv_shown_on_show_page_reflects_each_newly_posted_month_live(): void
    {
        [$company, $admin] = $this->makeEngineOnCompanyWithAdmin();
        $asset = $this->makeActiveAsset($company, ['useful_life_months' => 4, 'cost' => 400, 'salvage' => 0]);

        $before = $this->actingAs($admin)->get(route('accounting.fixed-assets.show', $asset));
        $this->assertEqualsWithDelta(400.0, $before->viewData('nbv'), 0.0005);

        $this->runner()->runForMonth($company->id, 2026, 1);

        $after = $this->actingAs($admin)->get(route('accounting.fixed-assets.show', $asset));
        $this->assertEqualsWithDelta(300.0, $after->viewData('nbv'), 0.0005);
    }
}
