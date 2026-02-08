<?php

namespace App\Http\Traits;

use App\Models\Role;
use App\Models\User;

trait Lockable
{
    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function lock(): void
    {
        $this->update([
            'is_locked' => true,
            'locked_by' => auth()->id(),
            'locked_at' => now(),
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);
    }

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Check if current user can modify this record
     * Returns true if: not locked, OR user is Accountant/Admin
     */
    public function canModify(): bool
    {
        if (!$this->isLocked()) {
            return true;
        }

        $user = auth()->user();
        return $user && in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT]);
    }
}