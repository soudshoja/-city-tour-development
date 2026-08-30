<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\PeriodDependencyBlockedException;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.C: close()/reopen() orchestration -- permission gates, the checklist gate wired into
 * close(), and the dependency-aware ("never leapfrogged") reopen ordering rule (period-lock-
 * design.md §8.2, applied to a whole Layer-2 period per that section's own closing paragraph).
 */
class PeriodCloseServiceTest extends AccountingTestCase
{
    private function service(): PeriodCloseService
    {
        return app(PeriodCloseService::class);
    }

    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    // ── Permissions ─────────────────────────────────────────────────────────────────────────────

    public function test_close_refuses_a_user_with_no_permission_and_no_admin_tier(): void
    {
        $company = $this->makeCompany();
        $agent = User::factory()->create(['role_id' => Role::AGENT]);

        $this->expectException(AuthorizationException::class);
        $this->service()->close($company->id, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED, $agent->id);
    }

    public function test_close_refuses_a_null_actor(): void
    {
        $company = $this->makeCompany();

        $this->expectException(AuthorizationException::class);
        $this->service()->close($company->id, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED, null);
    }

    public function test_close_allows_admin_role_tier_with_no_explicit_permission(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $result = $this->service()->close($company->id, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED, $admin->id);

        $this->assertTrue($result['applied']);
    }

    public function test_close_allows_explicit_permission_for_a_non_privileged_role(): void
    {
        $company = $this->makeCompany();
        Permission::firstOrCreate(['name' => 'accounting.period.close', 'guard_name' => 'web']);
        $agent = User::factory()->create(['role_id' => Role::AGENT]);
        $agent->givePermissionTo('accounting.period.close');

        $result = $this->service()->close($company->id, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED, $agent->id);

        $this->assertTrue($result['applied']);
    }

    public function test_reopen_refuses_without_accounting_period_reopen(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::AGENT]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $this->expectException(AuthorizationException::class);
        $this->service()->reopen($company->id, 2026, 3, $admin->id, 'test reason');
    }

    // ── close() wires the checklist gate ───────────────────────────────────────────────────────

    public function test_close_is_blocked_when_checklist_has_blocking_issues(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        // A draft transaction dated inside the target period is a documented BLOCKING checklist
        // condition (PeriodCloseChecklistServiceTest covers the check itself in isolation).
        \App\Models\Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => 1, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 10, 'description' => 'draft', 'reference_type' => 'Invoice',
            'reference_number' => 'D-1', 'name' => 'draft', 'transaction_date' => '2026-03-05',
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'draft',
            'total_debit' => 10, 'total_credit' => 10, 'idempotency_key' => uniqid('draft:'),
        ]);

        $result = $this->service()->close($company->id, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED, $admin->id);

        $this->assertFalse($result['applied']);
        $this->assertFalse($result['checklist']['can_close']);
        $this->assertSame(AccountingPeriod::STATUS_OPEN, $result['period']->status);
    }

    public function test_close_applies_and_persists_status_when_checklist_passes(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $result = $this->service()->close($company->id, 2026, 3, AccountingPeriod::STATUS_LOCKED, $admin->id);

        $this->assertTrue($result['applied']);
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $result['period']->status);
        $this->assertSame($admin->id, $result['period']->closed_by);
        $this->assertNotNull($result['period']->closed_at);

        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
    }

    // ── reopen(): audit fields + basic validation ──────────────────────────────────────────────

    public function test_reopen_writes_audit_columns(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $period = $this->service()->reopen($company->id, 2026, 3, $admin->id, 'audit correction');

        $this->assertSame(AccountingPeriod::STATUS_OPEN, $period->status);
        $this->assertSame($admin->id, $period->reopened_by);
        $this->assertNotNull($period->reopened_at);
        $this->assertSame('audit correction', $period->reopen_reason);
    }

    public function test_reopen_rejects_a_blank_reason(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->reopen($company->id, 2026, 3, $admin->id, '   ');
    }

    public function test_reopen_rejects_an_already_open_period(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->reopen($company->id, 2026, 3, $admin->id, 'reason');
    }

    // ── Dependency-aware reopen ordering ("never leapfrogged") ─────────────────────────────────

    public function test_reopen_is_blocked_when_a_later_month_is_still_locked(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 4, 'status' => AccountingPeriod::STATUS_LOCKED]);

        try {
            $this->service()->reopen($company->id, 2026, 3, $admin->id, 'reason');
            $this->fail('Expected PeriodDependencyBlockedException.');
        } catch (PeriodDependencyBlockedException $e) {
            $this->assertSame(2026, $e->blockingYear);
            $this->assertSame(4, $e->blockingMonth);
        }
    }

    public function test_reopen_is_blocked_when_a_later_year_is_still_soft_closed(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 12, 'status' => AccountingPeriod::STATUS_LOCKED]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2027, 'month' => 1, 'status' => AccountingPeriod::STATUS_SOFT_CLOSED]);

        $this->expectException(PeriodDependencyBlockedException::class);
        $this->service()->reopen($company->id, 2026, 12, $admin->id, 'reason');
    }

    public function test_reopen_succeeds_when_it_is_the_most_recent_closed_period(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);
        // April is OPEN (already reopened / never closed) -- nothing later is still closed.
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 4, 'status' => AccountingPeriod::STATUS_OPEN]);

        $period = $this->service()->reopen($company->id, 2026, 3, $admin->id, 'reason');

        $this->assertSame(AccountingPeriod::STATUS_OPEN, $period->status);
    }

    public function test_reopen_does_not_block_on_an_earlier_locked_month(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 2, 'status' => AccountingPeriod::STATUS_LOCKED]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        // Reopening March must not be blocked by an EARLIER (Feb) locked period.
        $period = $this->service()->reopen($company->id, 2026, 3, $admin->id, 'reason');

        $this->assertSame(AccountingPeriod::STATUS_OPEN, $period->status);
    }
}
