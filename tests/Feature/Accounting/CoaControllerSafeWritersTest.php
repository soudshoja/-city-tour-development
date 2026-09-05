<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\FrozenAccountException;
use App\Models\Account;
use App\Models\AccountingAuditLog;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\SystemAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * COA UI lane (2026-08-31, scope item 2 + 3): CoaController's raw writers routed through
 * AccountService (leaf/code-uniqueness/parent-chain validation), dstry() refusal cases, and the
 * disable/enable toggle making FrozenAccountException operationally reachable.
 */
class CoaControllerSafeWritersTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private function makeCompanyAndAdmin(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        // COAPolicy's update()/delete() bypass on Spatie's hasRole('admin'), not role_id --
        // see PurposeMappingScreenTest's own makeCompanyAndAdmin() docblock for the same fixture.
        $spatieRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole($spatieRole);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        return [$company, $admin];
    }

    // ── createAccounts() ────────────────────────────────────────────────────────────────────

    public function test_create_accounts_routes_through_account_service_and_generates_a_real_code(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->postJson(route('coa.create'), [
            'accountName' => 'Test Leaf Under Assets',
            'type' => 'assets',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);

        $created = Account::where('company_id', $company->id)->where('name', 'Test Leaf Under Assets')->firstOrFail();

        // Previously the raw Account::create() never set a code at all -- this is the concrete
        // proof AccountService::create() now generates a real one via AccountCodeGenerator.
        $this->assertNotNull($created->code);
        $this->assertNotSame('', trim((string) $created->code));

        $assetsRoot = Account::where('company_id', $company->id)->where('name', 'Assets')->firstOrFail();
        $this->assertSame($assetsRoot->id, $created->parent_id);
        $this->assertTrue((bool) $assetsRoot->fresh()->is_group);
    }

    // ── addCategory() ───────────────────────────────────────────────────────────────────────

    public function test_add_category_creates_a_leaf_with_the_requested_code(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $assetsRoot = Account::where('company_id', $company->id)->where('name', 'Assets')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('coa.addCategory'), [
            'name' => 'Brand New Leaf',
            'code' => '9991',
            'level' => $assetsRoot->level + 1,
            'root_id' => $assetsRoot->id,
            'parent_id' => $assetsRoot->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('accounts', [
            'company_id' => $company->id,
            'name' => 'Brand New Leaf',
            'code' => '9991',
            'parent_id' => $assetsRoot->id,
        ]);
    }

    /**
     * Proves the fix: before this build, addCategory()'s only collision guard was a plain
     * SELECT-then-INSERT with no row lock (a genuine race) and no company scope. Now routed
     * through AccountService::create()'s explicit-code path, which checks company-scoped
     * uniqueness under a parent row lock and refuses with a friendly redirect+error instead of
     * a duplicate-code row landing in the table.
     */
    public function test_add_category_rejects_a_duplicate_code_with_a_friendly_error(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $assetsRoot = Account::where('company_id', $company->id)->where('name', 'Assets')->firstOrFail();
        $existing = Account::where('company_id', $company->id)->where('name', 'Cash In Hand')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('coa.addCategory'), [
            'name' => 'Colliding Leaf',
            'code' => $existing->code,
            'level' => $assetsRoot->level + 1,
            'root_id' => $assetsRoot->id,
            'parent_id' => $assetsRoot->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('accounts', [
            'company_id' => $company->id,
            'name' => 'Colliding Leaf',
        ]);
    }

    // ── updateCode() ────────────────────────────────────────────────────────────────────────

    public function test_update_code_rejects_a_collision(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $target = Account::where('company_id', $company->id)->where('name', 'Service Fee Income')->firstOrFail();
        $collidesWith = Account::where('company_id', $company->id)->where('name', 'Cash In Hand')->firstOrFail();
        $originalCode = $target->code;

        $response = $this->actingAs($admin)->postJson(route('coa.updateCode', $target->id), [
            'code' => $collidesWith->code,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        $this->assertSame($originalCode, $target->fresh()->code);
    }

    public function test_update_code_succeeds_and_writes_an_audit_row(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $target = Account::where('company_id', $company->id)->where('name', 'Service Fee Income')->firstOrFail();

        $response = $this->actingAs($admin)->postJson(route('coa.updateCode', $target->id), [
            'code' => 'ZZ-NEW',
        ]);

        $response->assertOk();
        $this->assertSame('ZZ-NEW', $target->fresh()->code);

        $this->assertTrue(
            AccountingAuditLog::where('company_id', $company->id)
                ->where('action', 'account_update_code')
                ->where('subject_id', $target->id)
                ->exists()
        );
    }

    // ── dstry() ─────────────────────────────────────────────────────────────────────────────

    public function test_dstry_refuses_when_account_has_journal_entries(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $target = Account::where('company_id', $company->id)->where('name', 'Service Fee Income')->firstOrFail();
        $contra = Account::where('company_id', $company->id)->where('name', 'Markup Income')->firstOrFail();

        $transaction = Transaction::forceCreate([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 10,
            'total_debit' => 10,
            'total_credit' => 10,
            'description' => 'fixture',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
        ]);

        // Both legs, balanced -- AccountingTestCase::tearDown() asserts every acting company's
        // trial balance still balances after the test, so a lone unmatched debit leg would fail
        // that invariant even though it is unrelated to what this test itself exercises.
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $target->id,
            'name' => 'fixture entry',
            'transaction_date' => now(),
            'description' => 'fixture entry',
            'debit' => 10,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $contra->id,
            'name' => 'fixture contra entry',
            'transaction_date' => now(),
            'description' => 'fixture contra entry',
            'debit' => 0,
            'credit' => 10,
        ]);

        $response = $this->actingAs($admin)->deleteJson(route('coa.destroy', $target->id));

        $response->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $target->id]);
    }

    public function test_dstry_refuses_when_account_is_a_system_accounts_mapping_target(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $target = Account::where('company_id', $company->id)->where('name', 'Markup Income')->firstOrFail();
        $this->assertTrue(SystemAccount::where('account_id', $target->id)->exists(), 'Precondition: Markup Income must already be a mapping target after SystemAccountsSeeder.');

        $response = $this->actingAs($admin)->deleteJson(route('coa.destroy', $target->id));

        $response->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $target->id]);
    }

    public function test_dstry_refuses_when_account_has_children(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $group = Account::where('company_id', $company->id)->where('name', 'Commission & Service Fee Income')->firstOrFail();
        $this->assertTrue($group->children()->exists());

        $response = $this->actingAs($admin)->deleteJson(route('coa.destroy', $group->id));

        $response->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $group->id]);
    }

    public function test_dstry_succeeds_for_an_unmapped_leaf_with_no_journal_entries_and_writes_an_audit_row(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $assetsRoot = Account::where('company_id', $company->id)->where('name', 'Assets')->firstOrFail();
        $leaf = Account::create([
            'name' => 'Disposable Leaf',
            'code' => '9992',
            'parent_id' => $assetsRoot->id,
            'company_id' => $company->id,
            'level' => $assetsRoot->level + 1,
            'is_group' => false,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);

        $response = $this->actingAs($admin)->deleteJson(route('coa.destroy', $leaf->id));

        $response->assertOk();
        $this->assertDatabaseMissing('accounts', ['id' => $leaf->id]);

        $this->assertTrue(
            AccountingAuditLog::where('company_id', $company->id)
                ->where('action', 'account_destroy')
                ->where('subject_id', $leaf->id)
                ->exists()
        );
    }

    // ── disable/enable toggle ───────────────────────────────────────────────────────────────

    public function test_toggle_disabled_disables_then_re_enables_and_writes_audit_rows(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        // 'Markup Income' is a real, mapped leaf CoaSeeder + SystemAccountsSeeder always produce
        // for a fresh company -- unlike 'Suspense' (SystemAccountsSeeder's own docblock: "VAT_OUTPUT
        // and SUSPENSE have no dedicated leaf anywhere in the current chart"), which is a genuine
        // gap by default and never actually exists to toggle.
        $target = Account::where('company_id', $company->id)->where('name', 'Markup Income')->firstOrFail();
        $this->assertFalse((bool) $target->disabled);

        $response = $this->actingAs($admin)->postJson(route('coa.toggle-disabled', $target->id));
        $response->assertOk();
        $response->assertJsonPath('disabled', true);
        $this->assertTrue((bool) $target->fresh()->disabled);

        $response = $this->actingAs($admin)->postJson(route('coa.toggle-disabled', $target->id));
        $response->assertOk();
        $response->assertJsonPath('disabled', false);
        $this->assertFalse((bool) $target->fresh()->disabled);

        $this->assertSame(
            2,
            AccountingAuditLog::where('company_id', $company->id)
                ->where('subject_id', $target->id)
                ->whereIn('action', ['account_disable', 'account_enable'])
                ->count()
        );
    }

    /**
     * The operational-reachability proof scope item 3 asks for: a disabled account's purpose-code
     * mapping now throws FrozenAccountException at resolve time, exactly as PostingService's own
     * step 3e already enforces -- this is the toggle actually taking effect on the live posting
     * path, not just flipping a column nobody reads.
     */
    public function test_disabling_a_mapped_account_makes_frozen_account_exception_reachable(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $target = Account::where('company_id', $company->id)->where('name', 'Markup Income')->firstOrFail();

        $this->actingAs($admin)->postJson(route('coa.toggle-disabled', $target->id))->assertOk();

        $this->expectException(FrozenAccountException::class);
        app(AccountResolver::class)->resolve('MARKUP_INCOME', $company->id);
    }
}
