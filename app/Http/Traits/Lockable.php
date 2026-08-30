<?php

namespace App\Http\Traits;

use App\Exceptions\Accounting\UnlockDependencyBlockedException;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

/**
 * Lockable Trait — adds lock/unlock with automatic cascade.
 *
 * USAGE IN ANY MODEL:
 *
 *   use Lockable;
 *
 *   // Define what gets locked/unlocked with this model
 *   public static function getLockCascadeMap(): array
 *   {
 *       return [
 *           [Transaction::class,  'invoice_id'],
 *           [JournalEntry::class, 'invoice_id'],
 *       ];
 *   }
 *
 * That's it. lock(), unlock(), bulkLock(), bulkUnlock() all cascade automatically.
 *
 * For Payment later, just add:
 *   public static function getLockCascadeMap(): array {
 *       return [
 *           [Transaction::class,  'payment_id'],
 *           [JournalEntry::class, 'payment_id'],  // if JE has payment_id
 *       ];
 *   }
 */
trait Lockable
{
    // ─── Single Record ───────────────────────────────────────

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Check if current user can modify this record.
     * Returns true if: not locked
     */
    public function canModify(): bool
    {
        if (!$this->isLocked()) {
            return true;
        }

        $user = auth()->user();

        return $user && Gate::authorize('manageLocks', User::class);
    }

    // ─── Lock / Unlock (single record + cascade) ────────────

    /**
     * Lock this record and all its cascading relations.
     */
    public function lock(?int $userId = null): void
    {
        $userId = $userId ?? auth()->id();

        $this->update([
            'is_locked' => true,
            'locked_by' => $userId,
            'locked_at' => now(),
        ]);

        $this->applyCascade(true, $userId);
    }

    /**
     * P2.5.E (p2_5-brief.md §P2.5.E; period-lock-design.md §8.2's dependency-aware unlock): every
     * downstream node this record's unlock must be refused for -- a locked/reconciled descendant,
     * a closed accounting period, or an existing reversal/repost document -- as a structured list
     * `[{type, id, number, status, url, hint, log_center_url}, ...]`. An EMPTY array means "safe to
     * unlock"; anything else means {@see self::unlock()} refuses and the caller (the unlock modal,
     * or the JSON refusal response) renders this list verbatim as the dependency tree.
     *
     * Default: no known chain (no blockers). Any model adopting this trait keeps today's
     * unconditional-unlock behaviour unless it overrides this method -- {@see \App\Models\Invoice}
     * is the one override this wave ships, delegating to
     * {@see \App\Services\Accounting\UnlockDependencyResolver} (the full invoice -> applications/
     * allocations -> receipts -> reconciled lines -> period walk lives there, not here, so a
     * non-Invoice Lockable model never pays for logic it cannot use).
     *
     * @return array<int, array{type: string, id: int, number: ?string, status: string, url: ?string, hint: string, log_center_url: ?string}>
     */
    public function unlockBlockers(): array
    {
        return [];
    }

    /**
     * P2.5.E: dual-check convention every `accounting.*` ability in this codebase already uses
     * ({@see \App\Services\Accounting\ReconciliationService::assertCanReconcile()},
     * {@see \App\Services\Accounting\PeriodGuard::actorMayOverrideSoftClosed()},
     * {@see \App\Services\Accounting\PeriodCloseService}'s own `assertHasTier()`) -- an
     * admin/accountant/company role tier OR the explicit `accounting.record.unlock` Spatie
     * permission, never only one. Kept as a trait method (not a per-model Policy) because
     * `Lockable` is mixed into models with no Policy of their own registered today
     * ({@see \App\Models\Transaction}, {@see \App\Models\JournalEntry}) -- see
     * {@see self::unlock()}'s own docblock for why this check runs there rather than being left to
     * every controller call site to remember.
     *
     * @throws AuthorizationException
     */
    public function assertUnlockAuthorized(?User $user): void
    {
        $message = 'This action requires the accounting.record.unlock permission.';

        if ($user === null) {
            throw new AuthorizationException($message);
        }

        $allowed = $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('accounting.record.unlock');

        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }

    /**
     * Unlock this record and all its cascading relations.
     *
     * P2.5.E (p2_5-brief.md §P2.5.E): three gates, in this order, ALL new to this build (the
     * pre-P2.5.E method took no arguments and unlocked unconditionally):
     *   1. {@see self::assertUnlockAuthorized()} -- `accounting.record.unlock` (or the admin/
     *      accountant tier). Checked first: an unauthorized caller should not learn anything about
     *      the record's dependency chain via the exception this throws before reaching it.
     *   2. A non-empty `$reason` is mandatory (design doc §8.2: "a mandatory reason"). Checked
     *      before the dependency walk so a caller who forgot the reason gets that (cheaper) error
     *      first, before this method does the (potentially multi-query) chain walk for nothing.
     *   3. {@see self::unlockBlockers()} must return `[]` -- otherwise
     *      {@see UnlockDependencyBlockedException} is thrown carrying the SAME structured list the
     *      caller can hand straight to the modal / JSON response (design doc §8.2: "refuses if any
     *      downstream descendant is itself locked or reconciled, or sits inside a closed
     *      accounting period").
     *
     * Every attempt -- refused or granted -- is logged under the `accounting.*` namespace so
     * P2.5.F's Monolog handler (once it exists; see
     * {@see \App\Services\Accounting\AuditLogLinker}'s own docblock for the identical "log now, F
     * mirrors it into `accounting_audit_log` later" convention already
     * established by {@see \App\Services\Accounting\PeriodGuard} and
     * {@see \App\Services\Accounting\PeriodCloseService::reopen()}) picks this up as a real,
     * queryable audit row without this wave needing that table to exist yet.
     *
     * @throws AuthorizationException
     * @throws UnlockDependencyBlockedException
     * @throws \InvalidArgumentException  when `$reason` is empty
     */
    public function unlock(?string $reason = null, ?int $userId = null): void
    {
        $userId = $userId ?? Auth::id();
        $user = $userId !== null ? User::find($userId) : null;

        $this->assertUnlockAuthorized($user);

        if ($reason === null || trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to unlock this record.');
        }

        $blockers = $this->unlockBlockers();

        if ($blockers !== []) {
            Log::warning('accounting.record_unlock_blocked', [
                'subject_type' => static::class,
                'subject_id' => $this->getKey(),
                'actor_id' => $userId,
                'reason' => $reason,
                'blockers' => $blockers,
            ]);

            // P2.5.F writer (b): "unlock" is named explicitly as a writer path beyond the generic
            // Gate::authorize sweep — a REFUSED unlock is recorded too (before === after === the
            // still-locked state), so the Log Center shows every attempt, not only successes.
            AccountingLog::write(
                action: 'unlock_blocked',
                companyId: $this->getAttribute('company_id') !== null ? (int) $this->getAttribute('company_id') : null,
                subjectType: AccountingLog::normalizeSubjectTypePublic(static::class),
                subjectId: (int) $this->getKey(),
                before: ['is_locked' => true],
                after: ['is_locked' => true, 'blockers' => $blockers],
                reason: $reason,
                actorId: $userId,
            );

            throw new UnlockDependencyBlockedException(static::class, (int) $this->getKey(), $blockers);
        }

        $this->update([
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $this->applyCascade(false);

        AccountingLog::write(
            action: 'unlock',
            companyId: $this->getAttribute('company_id') !== null ? (int) $this->getAttribute('company_id') : null,
            subjectType: AccountingLog::normalizeSubjectTypePublic(static::class),
            subjectId: (int) $this->getKey(),
            before: ['is_locked' => true],
            after: ['is_locked' => false],
            reason: $reason,
            actorId: $userId,
        );

        Log::info('accounting.record_unlocked', [
            'action' => 'unlock',
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'actor_id' => $userId,
            'reason' => $reason,
        ]);
    }

    // ─── Bulk Lock / Unlock (for LockManagementController) ──

    /**
     * Bulk lock a query of records + cascade to related tables.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Scoped query (unlocked records)
     * @param  int  $userId
     * @return int  Number of primary records locked
     */
    public static function bulkLock($query, int $userId): int
    {
        $ids = (clone $query)->pluck('id')->toArray();

        if (empty($ids)) {
            return 0;
        }

        $count = $query->update([
            'is_locked' => true,
            'locked_by' => $userId,
            'locked_at' => now(),
        ]);

        static::applyCascadeForIds($ids, true, $userId);

        return $count;
    }

    /**
     * Bulk unlock a query of records + cascade to related tables.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Scoped query (locked records)
     * @return int  Number of primary records unlocked
     */
    public static function bulkUnlock($query): int
    {
        $ids = (clone $query)->pluck('id')->toArray();

        if (empty($ids)) {
            return 0;
        }

        $count = $query->update([
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        static::applyCascadeForIds($ids, false);

        return $count;
    }

    // ─── Cascade Configuration ───────────────────────────────

    /**
     * Override in each model to define cascade targets.
     * Returns array of [TargetModel::class, 'foreign_key_column'] pairs.
     *
     * Example (Invoice):
     *   return [
     *       [Transaction::class,  'invoice_id'],
     *       [JournalEntry::class, 'invoice_id'],
     *   ];
     *
     * Example (Payment — when you add it later):
     *   return [
     *       [Transaction::class,  'payment_id'],
     *   ];
     *
     * Return empty array if no cascade needed (e.g. Transaction, JournalEntry themselves).
     */
    public static function getLockCascadeMap(): array
    {
        return [];
    }

    // ─── Internal ────────────────────────────────────────────

    /**
     * Apply cascade for a single record (used by lock/unlock).
     */
    private function applyCascade(bool $lock, ?int $userId = null): void
    {
        $cascadeMap = static::getLockCascadeMap();

        if (empty($cascadeMap)) {
            return;
        }

        $lockData = $lock
            ? ['is_locked' => true, 'locked_by' => $userId, 'locked_at' => now()]
            : ['is_locked' => false, 'locked_by' => null, 'locked_at' => null];

        foreach ($cascadeMap as [$targetModel, $foreignKey]) {
            try {
                $affected = $targetModel::where($foreignKey, $this->id)->update($lockData);

                Log::info('Lock cascade applied', [
                    'source' => static::class,
                    'source_id' => $this->id,
                    'target' => $targetModel,
                    'foreign_key' => $foreignKey,
                    'lock' => $lock,
                    'affected' => $affected,
                ]);
            } catch (\Exception $e) {
                Log::warning('Lock cascade failed', [
                    'source' => static::class,
                    'source_id' => $this->id,
                    'target' => $targetModel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Apply cascade for a batch of IDs (used by bulkLock/bulkUnlock).
     */
    private static function applyCascadeForIds(array $ids, bool $lock, ?int $userId = null): void
    {
        $cascadeMap = static::getLockCascadeMap();

        if (empty($cascadeMap) || empty($ids)) {
            return;
        }

        $lockData = $lock
            ? ['is_locked' => true, 'locked_by' => $userId, 'locked_at' => now()]
            : ['is_locked' => false, 'locked_by' => null, 'locked_at' => null];

        foreach ($cascadeMap as [$targetModel, $foreignKey]) {
            try {
                $affected = $targetModel::whereIn($foreignKey, $ids)->update($lockData);

                Log::info('Bulk lock cascade applied', [
                    'source' => static::class,
                    'target' => $targetModel,
                    'foreign_key' => $foreignKey,
                    'source_ids_count' => count($ids),
                    'lock' => $lock,
                    'affected' => $affected,
                ]);
            } catch (\Exception $e) {
                Log::warning('Bulk lock cascade failed', [
                    'source' => static::class,
                    'target' => $targetModel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}