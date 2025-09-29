<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
       return $user->can('view invoice');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('view invoice') || $user->id == $invoice->user_id;
    }

    public function create(User $user): bool
    {
        if($user->role_id == Role::COMPANY) return true;

        return $user->can('create invoice');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if($user->roles('admin')) return true;

        return $user->can('update invoice');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        //
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        //
    }

    public function pickAgent(User $user): bool
    {
        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY;
    }

    public function accountantEdit(User $user): bool
    {
        if($user->role_id == Role::ACCOUNTANT) return true;

        if($user->hasPermissionTo('edit full invoice')) return true;

        return false;
    }
}
