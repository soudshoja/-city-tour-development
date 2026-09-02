<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class CreditPolicy
{
    use RequiresCompanyModule;

    public function __construct()
    {
        //
    }

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view credit');
    }

    /**
     * W7.K addition: gates CreditController::creditTopup() -- a real money-movement action
     * (posts Dr instrument / Cr CLIENT_ADVANCE 2632 through the engine, or the equivalent raw
     * JE pair on the legacy path) that had ZERO authorization before this wave (see the
     * `credits` route group comment in routes/web.php: "creditTopup() posts real Transaction +
     * JournalEntry rows against COA accounts with zero authorization today"). Mirrors
     * `viewAny()`'s module-gate-first shape.
     */
    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('create credit');
    }
}
