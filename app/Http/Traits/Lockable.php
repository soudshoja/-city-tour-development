<?php

namespace App\Http\Traits;

use App\Models\User;
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
     * Unlock this record and all its cascading relations.
     */
    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $this->applyCascade(false);
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