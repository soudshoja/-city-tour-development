<?php

use App\Models\Role;

if(!function_exists('getCompanyId')){
    function getCompanyId($user): ?int
    {
        switch ($user->role_id) {
            case Role::ADMIN:
            // A platform operator (role 1) has no company of their own —
            // they must explicitly choose one first (the sidebar company
            // switcher, AdminUsersController, writes session('company_id')).
            // Defaulting to company 1 here silently attributed every
            // not-yet-chosen operator action to company 1's REAL data —
            // this is exactly the bug that let an operator's page view
            // create a team member on a live customer's WhatsApp account
            // (Resayil Admin Center security fix, 2026-08). Refuse to
            // resolve rather than guess: callers must already treat this
            // as nullable (the return type is ?int), and every Resayil
            // action built since checks for null and asks the operator to
            // pick a company instead of acting on their behalf.
            return session()->has('company_id') ? (int) session('company_id') : null;
            case Role::COMPANY:
            return $user->company?->id;
            case Role::BRANCH:
            return $user->branch?->company?->id;
            case Role::AGENT:
            return $user->agent?->branch?->company?->id;
            case Role::ACCOUNTANT:
            return $user->accountant?->branch?->company?->id;
            default:
            return null;
        }
    }
}

if(!function_exists('determineUserRole')){
    function determineUserRole($user): array
    {
        if ($user->role_id == Role::ADMIN) {
            return [
                'agents_id' => null,
                'branches_id' => null,
                'company_id' => session('company_id', 1),
            ];

        } elseif ($user->role_id == Role::COMPANY) {
            return [
                'agents_id' => null,
                'branches_id' => null,
                'company_id' => $user->company->id,
            ];
        } elseif ($user->role_id == Role::BRANCH) {
            return [
                'agents_id' => null,
                'branches_id' => $user->branch->id,
                'company_id' => $user->branch->company->id,
            ];
        } elseif ($user->role_id == Role::AGENT) {

            return [
                'agents_id' => $user->agent->id,
                'branches_id' => $user->agent->branch->id,
                'company_id' => $user->agent->branch->company->id,
            ];

        } elseif ($user->role_id == Role::ACCOUNTANT) {
            return [
                'agents_id' => null,
                'branches_id' => $user->accountant->branch->id,
                'company_id' => $user->accountant->branch->company->id,
            ];
        }

        return [
            'agents_id' => null,
            'branches_id' => null,
            'company_id' => null,
        ];
    }
}