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
     *
     * soud amendment: akeed gated this on Modules::ACCOUNTING, which is never sold and stays
     * default-disabled for every soud company (accounting is invisible infrastructure, not a
     * package module -- see the routes/web.php `credits` group's own "Ruling R1" comment a few
     * lines above the `/topup` route: "Neither is module:accounting: accounting is never sold
     * and must stay invisible regardless of which of these two sellable modules a company
     * bought"). Gating create() on ACCOUNTING would 403 this action for every package client
     * regardless of what they actually purchased. creditTopup() moves money through a payment
     * instrument, exactly like the route's own module:payment_gateway middleware already
     * decides -- gate the ability the same way the route gates reachability.
     */
    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->can('create credit');
    }
}
