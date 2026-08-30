<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\PeriodDependencyBlockedException;
use App\Models\AccountingPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C; period-lock-design.md §5/§8.2): orchestrates the close/soft-close/
 * lock/reopen actions the command and the period-control screen both call — the single place that
 * runs {@see PeriodCloseChecklistService}'s gate, enforces the two permissions, and writes
 * {@see AccountingPeriod}'s status/audit columns. Both `App\Console\Commands\PeriodClose` and
 * `App\Http\Controllers\Accounting\PeriodController` call this class rather than duplicating any of
 * this logic — the console command has no `Auth::user()`, so every permission check here resolves
 * the actor via an explicit `?int $userId`, never `Auth::user()` (same caller-agnostic convention
 * {@see PeriodGuard}/{@see ReconciliationService} already establish for engine-layer classes).
 */
final class PeriodCloseService
{
    public function __construct(private readonly PeriodCloseChecklistService $checklist) {}

    /**
     * Idempotent lookup+create: a missing row is treated as `open` everywhere else in this engine
     * (PeriodGuard's own "no row = open" rule) — the close/reopen action needs a real row to write
     * a status onto, so it creates one (status `open`) rather than requiring a prior
     * `accounting:periods:init` run.
     */
    public function findOrCreatePeriod(int $companyId, int $year, int $month): AccountingPeriod
    {
        return AccountingPeriod::query()->firstOrCreate(
            ['company_id' => $companyId, 'year' => $year, 'month' => $month],
            ['status' => AccountingPeriod::STATUS_OPEN]
        );
    }

    /**
     * @param  'soft_closed'|'locked'  $targetStatus
     * @return array{period: AccountingPeriod, checklist: array, applied: bool}
     *
     * @throws AuthorizationException when $userId lacks `accounting.period.close`.
     */
    public function close(int $companyId, int $year, int $month, string $targetStatus, ?int $userId): array
    {
        if (! in_array($targetStatus, [AccountingPeriod::STATUS_SOFT_CLOSED, AccountingPeriod::STATUS_LOCKED], true)) {
            throw new \InvalidArgumentException("targetStatus must be 'soft_closed' or 'locked', got '{$targetStatus}'.");
        }

        $this->assertCanClose($userId);

        $period = $this->findOrCreatePeriod($companyId, $year, $month);
        $checklistResult = $this->checklist->run($companyId, $year, $month);

        if (! $checklistResult['can_close']) {
            return ['period' => $period, 'checklist' => $checklistResult, 'applied' => false];
        }

        $statusBefore = $period->status;

        $period->forceFill([
            'status' => $targetStatus,
            'closed_by' => $userId,
            'closed_at' => now(),
        ])->save();

        // P2.5.F writer (b) (p2_5-brief.md §P2.5.F): "period close/reopen ... write before/after."
        // subject_id is the accounting_periods row itself (not a bare company/year/month triple),
        // so the Log Center's subject-number search can jump straight to it.
        AccountingLog::write(
            action: $targetStatus === AccountingPeriod::STATUS_LOCKED ? 'lock' : 'soft_close',
            companyId: $companyId,
            subjectType: 'accounting_period',
            subjectId: (int) $period->id,
            before: ['status' => $statusBefore],
            after: ['status' => $targetStatus, 'warnings' => count($checklistResult['warnings'])],
            actorId: $userId,
            postingPeriod: sprintf('%04d-%02d', $year, $month),
        );

        Log::info('accounting.period_closed', [
            'company_id' => $companyId,
            'year' => $year,
            'month' => $month,
            'status' => $targetStatus,
            'user_id' => $userId,
            'warnings' => count($checklistResult['warnings']),
        ]);

        return ['period' => $period->fresh(), 'checklist' => $checklistResult, 'applied' => true];
    }

    /**
     * P2.5.E / design doc §8.2's dependency-aware unlock, applied to a whole period (Layer 2): a
     * later period that is still `soft_closed`/`locked` must be reopened first — reopening is never
     * allowed to leapfrog a still-closed later period. Writes the audit columns
     * (`reopened_by`/`reopened_at`/`reopen_reason`) already on {@see AccountingPeriod} — P2.5.F's
     * separate `accounting_audit_log` table is a later sub-wave; these columns ARE this sub-wave's
     * audit row for a reopen, per the brief's own text ("`--reopen --reason=` ... with audit row").
     *
     * @throws AuthorizationException when $userId lacks `accounting.period.reopen`.
     * @throws PeriodDependencyBlockedException when a chronologically later period for this company
     *                                          is still soft_closed/locked.
     * @throws \InvalidArgumentException when $reason is blank, or the period is not currently
     *                                   soft_closed/locked (nothing to reopen).
     */
    public function reopen(int $companyId, int $year, int $month, int $userId, string $reason): AccountingPeriod
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reopen reason is required.');
        }

        $this->assertCanReopen($userId);

        $period = $this->findOrCreatePeriod($companyId, $year, $month);

        if ($period->isOpen()) {
            throw new \InvalidArgumentException("Period {$year}-{$month} is already open; nothing to reopen.");
        }

        $this->assertNoLaterPeriodIsStillClosed($companyId, $year, $month);

        $statusBefore = $period->status;

        $period->forceFill([
            'status' => AccountingPeriod::STATUS_OPEN,
            'reopened_by' => $userId,
            'reopened_at' => now(),
            'reopen_reason' => $reason,
        ])->save();

        // P2.5.F writer (b): "period-override and unlock flows carry the reason" (test
        // requirement) — $reason lands on the row's own `reason` column, not just buried in `after`.
        AccountingLog::write(
            action: 'reopen',
            companyId: $companyId,
            subjectType: 'accounting_period',
            subjectId: (int) $period->id,
            before: ['status' => $statusBefore],
            after: ['status' => AccountingPeriod::STATUS_OPEN],
            reason: $reason,
            actorId: $userId,
            postingPeriod: sprintf('%04d-%02d', $year, $month),
        );

        Log::info('accounting.period_reopened', [
            'company_id' => $companyId,
            'year' => $year,
            'month' => $month,
            'user_id' => $userId,
            'reason' => $reason,
        ]);

        return $period->fresh();
    }

    /**
     * @throws PeriodDependencyBlockedException
     */
    private function assertNoLaterPeriodIsStillClosed(int $companyId, int $year, int $month): void
    {
        $isAnnual = (string) config('accounting.period.length', 'monthly') === 'annual';

        $laterQuery = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [AccountingPeriod::STATUS_SOFT_CLOSED, AccountingPeriod::STATUS_LOCKED]);

        $laterQuery = $isAnnual
            ? $laterQuery->where('year', '>', $year)
            : $laterQuery->where(fn ($q) => $q->where('year', '>', $year)
                ->orWhere(fn ($q2) => $q2->where('year', $year)->where('month', '>', $month)));

        $blocker = $laterQuery->orderBy('year')->orderBy('month')->first();

        if ($blocker !== null) {
            throw new PeriodDependencyBlockedException($companyId, $year, $month, $blocker->year, $blocker->month, $blocker->status);
        }
    }

    /**
     * Same dual-check convention {@see ReconciliationService::assertCanReconcile()} /
     * {@see PeriodGuard::actorMayOverrideSoftClosed()} already establish: admin/accountant role
     * tier OR the explicit Spatie permission, never only one.
     *
     * @throws AuthorizationException
     */
    public function assertCanClose(?int $userId): void
    {
        $this->assertHasTier($userId, 'accounting.period.close');
    }

    /** @throws AuthorizationException */
    public function assertCanReopen(?int $userId): void
    {
        $this->assertHasTier($userId, 'accounting.period.reopen');
    }

    private function assertHasTier(?int $userId, string $permission): void
    {
        $message = "This action requires the {$permission} permission.";

        if ($userId === null) {
            throw new AuthorizationException($message);
        }

        $user = User::find($userId);

        if ($user === null) {
            throw new AuthorizationException($message);
        }

        $allowed = $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can($permission);

        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }
}
