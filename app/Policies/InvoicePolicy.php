<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    use RequiresCompanyModule;

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

       return $user->can('view invoice');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->can('view invoice') || $user->id == $invoice->user_id;
    }

    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->role_id == Role::COMPANY) return true;

        return $user->can('create invoice');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->roles('admin')) return true;

        return $user->can('update invoice');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        //
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        //
    }

    public function pickAgent(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY;
    }

    public function accountantEdit(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->role_id == Role::ACCOUNTANT) return true;

        return false;
    }
}
