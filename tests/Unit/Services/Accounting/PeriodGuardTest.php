<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\NoOpenPeriodFoundException;
use App\Exceptions\Accounting\PeriodLockedException;
use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\PeriodGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.A (p2_5-brief.md §P2.5.A): "PeriodGuard::assertOpen replaces the P1 stub ... Tests: full
 * matrix (open/soft/locked x permission x reason x allowLocked)".
 *
 * Exercises {@see PeriodGuard} directly — no PostingService/PostingSeam involved — so every branch
 * of the resolution rule is pinned in isolation. Engine ON/OFF integration (proving the guard is
 * actually reached on both paths) lives in PostingSeamPeriodGuardTest.
 */
class PeriodGuardTest extends AccountingTestCase
{
    private function guard(): PeriodGuard
    {
        return app(PeriodGuard::class);
    }

    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function period(Company $company, int $year, int $month, string $status): AccountingPeriod
    {
        return AccountingPeriod::create([
            'company_id' => $company->id,
            'year' => $year,
            'month' => $month,
            'status' => $status,
        ]);
    }

    /** A date inside 2026-03, the month every fixture in this test targets. */
    private function dateInMarch(): Carbon
    {
        return Carbon::create(2026, 3, 15, 12, 0, 0);
    }

    // ── No row / open ──────────────────────────────────────────────────────────────────────────

    public function test_no_period_row_is_treated_as_open(): void
    {
        $company = $this->makeCompany();

        // No AccountingPeriod row created at all.
        $this->guard()->assertOpen($company->id, $this->dateInMarch());

        $this->addToAssertionCount(1); // Reaching here without an exception IS the assertion.
    }

    public function test_status_open_passes(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_OPEN);

        $this->guard()->assertOpen($company->id, $this->dateInMarch());

        $this->addToAssertionCount(1);
    }

    // ── Locked ──────────────────────────────────────────────────────────────────────────────────

    public function test_status_locked_throws_without_allow_locked(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        $this->expectException(PeriodLockedException::class);

        try {
            $this->guard()->assertOpen($company->id, $this->dateInMarch());
        } catch (PeriodLockedException $e) {
            $this->assertSame('locked', $e->status);
            $this->assertSame(2026, $e->year);
            $this->assertSame(3, $e->month);
            $this->assertSame($company->id, $e->companyId);

            throw $e;
        }
    }

    public function test_status_locked_passes_with_allow_locked_and_logs_override(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        Log::spy();

        $this->guard()->assertOpen($company->id, $this->dateInMarch(), allowLocked: true);

        Log::shouldHaveReceived('info')->once()->with(
            'accounting.period_locked_override',
            Mockery::on(fn (array $ctx) => $ctx['company_id'] === $company->id
                && $ctx['year'] === 2026
                && $ctx['month'] === 3)
        );
    }

    public function test_status_locked_ignores_soft_closed_permission_reason_and_still_throws(): void
    {
        // A permission/reason valid for soft_closed must NOT also bypass locked -- allowLocked is
        // the only lever for a locked period (design doc §14.2: "no exemptions").
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->expectException(PeriodLockedException::class);

        $this->guard()->assertOpen(
            $company->id,
            $this->dateInMarch(),
            allowLocked: false,
            userId: $user->id,
            overrideReason: 'attempted override',
        );
    }

    // ── Soft-closed ─────────────────────────────────────────────────────────────────────────────

    public function test_soft_closed_throws_with_no_actor(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        $this->expectException(PeriodLockedException::class);

        try {
            $this->guard()->assertOpen($company->id, $this->dateInMarch());
        } catch (PeriodLockedException $e) {
            $this->assertSame('soft_closed', $e->status);

            throw $e;
        }
    }

    public function test_soft_closed_throws_when_permission_present_but_reason_missing(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->expectException(PeriodLockedException::class);

        $this->guard()->assertOpen($company->id, $this->dateInMarch(), userId: $admin->id);
    }

    public function test_soft_closed_throws_when_reason_present_but_no_permission(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        // AGENT: not in the admin/accountant tier, and no accounting.period.post-soft-closed grant.
        $agent = User::factory()->create(['role_id' => Role::AGENT]);

        $this->expectException(PeriodLockedException::class);

        $this->guard()->assertOpen(
            $company->id,
            $this->dateInMarch(),
            userId: $agent->id,
            overrideReason: 'late audit adjustment',
        );
    }

    public function test_soft_closed_throws_when_reason_is_blank_string(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->expectException(PeriodLockedException::class);

        $this->guard()->assertOpen(
            $company->id,
            $this->dateInMarch(),
            userId: $admin->id,
            overrideReason: '   ',
        );
    }

    public function test_soft_closed_passes_with_admin_role_tier_and_reason_and_logs_audit(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        Log::spy();

        $this->guard()->assertOpen(
            $company->id,
            $this->dateInMarch(),
            userId: $admin->id,
            overrideReason: 'year-end audit adjustment',
        );

        Log::shouldHaveReceived('warning')->once()->with(
            'accounting.period_soft_closed_override',
            Mockery::on(fn (array $ctx) => $ctx['company_id'] === $company->id
                && $ctx['user_id'] === $admin->id
                && $ctx['reason'] === 'year-end audit adjustment')
        );

        // Fix-round 2026-08-30 (verify findings, CONFIRMED #2): this is the one caller-facing
        // period-override path that carries a reason -- it must land a permanent
        // accounting_audit_log row, not only the file-only Log::warning() above.
        $this->assertDatabaseHas('accounting_audit_log', [
            'company_id' => $company->id,
            'action' => 'period_soft_closed_override',
            'subject_type' => 'accounting_period',
            'actor_id' => $admin->id,
            'reason' => 'year-end audit adjustment',
            'posting_period' => '2026-03',
        ]);
    }

    public function test_soft_closed_passes_via_explicit_permission_for_a_non_privileged_role(): void
    {
        $company = $this->makeCompany();
        $period = $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        Permission::firstOrCreate(['name' => 'accounting.period.post-soft-closed', 'guard_name' => 'web']);
        $agent = User::factory()->create(['role_id' => Role::AGENT]);
        $agent->givePermissionTo('accounting.period.post-soft-closed');

        $this->guard()->assertOpen(
            $company->id,
            $this->dateInMarch(),
            userId: $agent->id,
            overrideReason: 'agent-specific correction',
        );

        $this->assertDatabaseHas('accounting_audit_log', [
            'company_id' => $company->id,
            'action' => 'period_soft_closed_override',
            'subject_id' => $period->id,
            'actor_id' => $agent->id,
            'reason' => 'agent-specific correction',
        ]);
    }

    /**
     * A refused soft_closed attempt (no valid override) must NOT write an audit row -- matches this
     * method's own "refuse (and log nothing) otherwise" contract; only a successful override is
     * audit-logged.
     */
    public function test_soft_closed_refusal_writes_no_audit_row(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        $agent = User::factory()->create(['role_id' => Role::AGENT]);

        try {
            $this->guard()->assertOpen(
                $company->id,
                $this->dateInMarch(),
                userId: $agent->id,
                overrideReason: 'no permission for this',
            );
        } catch (PeriodLockedException) {
            // expected
        }

        $this->assertSame(0, AccountingAuditLog::where('action', 'period_soft_closed_override')->count());
    }

    public function test_soft_closed_throws_for_unresolvable_user_id(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        $this->expectException(PeriodLockedException::class);

        $this->guard()->assertOpen(
            $company->id,
            $this->dateInMarch(),
            userId: 999999999,
            overrideReason: 'ghost user',
        );
    }

    // ── Isolation: a period on a DIFFERENT company/month never affects this one ───────────────────

    public function test_other_companys_locked_period_does_not_affect_this_company(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $this->period($companyB, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        // Company A has no row for 2026-03 at all -> open.
        $this->guard()->assertOpen($companyA->id, $this->dateInMarch());
        $this->addToAssertionCount(1);
    }

    public function test_adjacent_month_open_does_not_affect_locked_month(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 2, AccountingPeriod::STATUS_OPEN);
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        $this->expectException(PeriodLockedException::class);
        $this->guard()->assertOpen($company->id, $this->dateInMarch());
    }

    // ── accounting.period.length = annual ──────────────────────────────────────────────────────────

    public function test_annual_length_resolves_by_year_only_using_the_sentinel_month(): void
    {
        config(['accounting.period.length' => 'annual']);

        $company = $this->makeCompany();
        // Locked for the WHOLE year 2026, stored under the sentinel month.
        $this->period($company, 2026, AccountingPeriod::ANNUAL_MONTH, AccountingPeriod::STATUS_LOCKED);

        $this->expectException(PeriodLockedException::class);

        try {
            // Any month within 2026 must resolve to the same annual row.
            $this->guard()->assertOpen($company->id, Carbon::create(2026, 11, 1));
        } catch (PeriodLockedException $e) {
            $this->assertSame(0, $e->month);
            $this->assertSame(2026, $e->year);

            throw $e;
        }
    }

    public function test_annual_length_a_monthly_row_for_the_same_year_is_never_consulted(): void
    {
        config(['accounting.period.length' => 'annual']);

        $company = $this->makeCompany();
        // A stray monthly-shaped row (month=3, locked) must NOT be found once length=annual --
        // the guard looks up month=ANNUAL_MONTH (0), a row that does not exist here, so this
        // resolves to "no row" = open.
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        $this->guard()->assertOpen($company->id, $this->dateInMarch());
        $this->addToAssertionCount(1);
    }

    // ── P2.5.B: earliestOpenOnOrAfter() ────────────────────────────────────────────────────────

    /**
     * The exact "no row = open" case: $from's own period has no row, so the search returns
     * immediately -- the FIRST DAY of $from's own month (not $from's own day-of-month), per this
     * method's own "posting_date identifies a PERIOD, not a specific day" contract.
     */
    public function test_earliest_open_on_or_after_returns_from_own_period_when_it_has_no_row(): void
    {
        $company = $this->makeCompany();

        $result = $this->guard()->earliestOpenOnOrAfter($company->id, $this->dateInMarch());

        $this->assertSame('2026-03-01', $result->toDateString());
    }

    /** Same "returns immediately" shape, but the period genuinely IS open (a real row, not a missing one). */
    public function test_earliest_open_on_or_after_returns_from_own_period_when_explicitly_open(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_OPEN);

        $result = $this->guard()->earliestOpenOnOrAfter($company->id, $this->dateInMarch());

        $this->assertSame('2026-03-01', $result->toDateString());
    }

    /** The core shift shape: March locked, April has no row (open) -> returns 2026-04-01. */
    public function test_earliest_open_on_or_after_walks_forward_past_a_locked_month(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        $result = $this->guard()->earliestOpenOnOrAfter($company->id, $this->dateInMarch());

        $this->assertSame('2026-04-01', $result->toDateString());
    }

    /** Walks past MULTIPLE consecutive non-open months (locked, then soft_closed) to the first real gap. */
    public function test_earliest_open_on_or_after_walks_forward_past_several_consecutive_non_open_months(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 3, AccountingPeriod::STATUS_LOCKED);
        $this->period($company, 2026, 4, AccountingPeriod::STATUS_SOFT_CLOSED);
        $this->period($company, 2026, 5, AccountingPeriod::STATUS_LOCKED);
        // 2026-06: no row -> open.

        $result = $this->guard()->earliestOpenOnOrAfter($company->id, $this->dateInMarch());

        $this->assertSame('2026-06-01', $result->toDateString());
    }

    /** December 2026 (locked) rolls forward into January 2027 -- year-boundary arithmetic. */
    public function test_earliest_open_on_or_after_crosses_a_year_boundary(): void
    {
        $company = $this->makeCompany();
        $this->period($company, 2026, 12, AccountingPeriod::STATUS_LOCKED);

        $result = $this->guard()->earliestOpenOnOrAfter($company->id, Carbon::create(2026, 12, 20));

        $this->assertSame('2027-01-01', $result->toDateString());
    }

    /** annual length: the sentinel-month row for 2026 is locked -> returns 2027-01-01 (next year, sentinel). */
    public function test_earliest_open_on_or_after_under_annual_length_walks_forward_by_year(): void
    {
        config(['accounting.period.length' => 'annual']);

        $company = $this->makeCompany();
        $this->period($company, 2026, AccountingPeriod::ANNUAL_MONTH, AccountingPeriod::STATUS_LOCKED);

        $result = $this->guard()->earliestOpenOnOrAfter($company->id, Carbon::create(2026, 6, 15));

        $this->assertSame('2027-01-01', $result->toDateString());
    }

    /**
     * Isolation: a DIFFERENT company's row locking the same month never affects this company's own
     * search -- mirrors assertOpen()'s own isolation test above.
     */
    public function test_earliest_open_on_or_after_ignores_another_companys_locked_period(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $this->period($companyB, 2026, 3, AccountingPeriod::STATUS_LOCKED);

        $result = $this->guard()->earliestOpenOnOrAfter($companyA->id, $this->dateInMarch());

        $this->assertSame('2026-03-01', $result->toDateString());
    }

    /**
     * The genuinely-pathological, meant-to-be-unreachable-in-practice case (see
     * NoOpenPeriodFoundException's own docblock): every period this company has initialised, for
     * MAX_LOOKAHEAD_PERIODS (240) months running, is locked -- nothing open anywhere in the bounded
     * search window. Bulk-inserted via the query builder (not 240 individual factory calls) purely
     * for test speed; the fixture shape itself (one row per company/year/month, all locked) is
     * exactly what a real, if extreme, "every period ever initialised is locked" company would have.
     */
    public function test_earliest_open_on_or_after_throws_when_every_period_in_the_lookahead_window_is_locked(): void
    {
        $company = $this->makeCompany();

        $rows = [];
        $cursor = $this->dateInMarch()->copy()->startOfMonth();
        for ($i = 0; $i <= 240; $i++) {
            $rows[] = [
                'company_id' => $company->id,
                'year' => (int) $cursor->format('Y'),
                'month' => (int) $cursor->format('n'),
                'status' => AccountingPeriod::STATUS_LOCKED,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $cursor->addMonthNoOverflow();
        }
        AccountingPeriod::query()->insert($rows);

        $this->expectException(NoOpenPeriodFoundException::class);

        try {
            $this->guard()->earliestOpenOnOrAfter($company->id, $this->dateInMarch());
        } catch (NoOpenPeriodFoundException $e) {
            $this->assertSame($company->id, $e->companyId);
            $this->assertSame(240, $e->lookaheadPeriods);

            throw $e;
        }
    }
}
