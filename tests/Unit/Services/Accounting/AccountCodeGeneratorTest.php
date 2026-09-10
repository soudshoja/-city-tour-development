<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountCodeGenerator;
use Tests\Support\AccountingTestCase;

/**
 * CT-A4b — unit tests for the single account-code allocator that
 * SupplierActivationService, CoaSeeder's runtime minting paths, TaskController's
 * currency/issued-by child accounts, and accounting:coa-duplicates --renumber all now share.
 *
 * See app/Services/Accounting/AccountCodeGenerator.php's own class docblock (BUG-H1) for the
 * algorithm this proves: numeric max among siblings + 1, padded to sibling width, skip anything
 * already taken ANYWHERE in the company (active or not), null (→ row-id fallback) when there is
 * no numeric sibling to extend from.
 */
class AccountCodeGeneratorTest extends AccountingTestCase
{
    private function generator(): AccountCodeGenerator
    {
        return app(AccountCodeGenerator::class);
    }

    public function test_returns_next_code_after_the_max_numeric_sibling(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $parent = Account::factory()->create(['company_id' => $company->id, 'code' => '2120']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => '2121']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => '2122']);

        $this->assertSame('2123', $this->generator()->generate($parent, $company->id));
    }

    public function test_pads_the_new_code_to_sibling_width(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $parent = Account::factory()->create(['company_id' => $company->id, 'code' => '5000']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => '0099']);

        $this->assertSame('0100', $this->generator()->generate($parent, $company->id));
    }

    public function test_a_gap_among_sibling_codes_does_not_change_the_max_plus_one_result(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $parent = Account::factory()->create(['company_id' => $company->id, 'code' => '2120']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => '2121']);
        // gap: no '2122' sibling
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => '2123']);

        // Algorithm is documented as "numeric max among siblings + 1" — it does not backfill
        // gaps (that would risk handing out a code a moved/renamed account used to hold).
        $this->assertSame('2124', $this->generator()->generate($parent, $company->id));
    }

    public function test_skips_a_code_already_taken_anywhere_in_the_company_even_under_a_different_parent(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $flightsPool = Account::factory()->create(['company_id' => $company->id, 'code' => '2120']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $flightsPool->id, 'code' => '2121']);

        // Cross-service collision: a completely unrelated group elsewhere in the SAME company
        // already holds '2122' — the naive "max sibling + 1" would hand that straight back out.
        Account::factory()->create(['company_id' => $company->id, 'code' => '2122']);

        $this->assertSame('2123', $this->generator()->generate($flightsPool, $company->id));
    }

    public function test_a_soft_deleted_sibling_still_blocks_its_code_from_being_reused(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $parent = Account::factory()->create(['company_id' => $company->id, 'code' => '2120']);
        $inactive = Account::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => '2121']);
        // Account has no SoftDeletes trait — deleted_at is a plain column other accounting code
        // (AccountCodeGenerator::codeExists(), AccountingInvariants) filters on manually. Set it
        // directly rather than via ->delete(), which would hard-DELETE the row here.
        $inactive->forceFill(['deleted_at' => now()])->saveQuietly();

        // The sibling scan and codeExists() both run withoutGlobalScopes() with no deleted_at
        // filter, so a soft-deleted account's code still counts toward max(siblings) AND still
        // blocks a collision — "active or not". If a future change started excluding deleted_at
        // rows, this would regress to '2121' (the code the deleted row still occupies) instead.
        $this->assertSame('2122', $this->generator()->generate($parent, $company->id));
    }

    public function test_two_different_service_pools_allocate_independently(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $flightsPool = Account::factory()->create(['company_id' => $company->id, 'code' => '2120']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $flightsPool->id, 'code' => '2151']);

        $hotelsPool = Account::factory()->create(['company_id' => $company->id, 'code' => '2130']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $hotelsPool->id, 'code' => '2160']);

        // Each pool's next code is computed from ITS OWN children only.
        $this->assertSame('2152', $this->generator()->generate($flightsPool, $company->id));
        $this->assertSame('2161', $this->generator()->generate($hotelsPool, $company->id));
    }

    public function test_returns_null_when_the_parent_has_no_numeric_sibling_to_extend_from(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $parent = Account::factory()->create(['company_id' => $company->id, 'code' => '2120']);
        // No children at all yet.

        $this->assertNull($this->generator()->generate($parent, $company->id));

        // BUG-H1's documented row-id fallback.
        $child = Account::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => 'PENDING']);
        $this->assertSame((string) $child->id, $this->generator()->fallbackCode($child));
    }

    public function test_generate_for_a_root_looks_at_the_other_roots_not_a_parents_children(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Account::factory()->create(['company_id' => $company->id, 'parent_id' => null, 'code' => '1000']);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => null, 'code' => '2000']);

        $this->assertSame('2001', $this->generator()->generate(null, $company->id));
    }
}
