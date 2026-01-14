<?php

use App\Models\Role;

if(!function_exists('getCompanyId')){
    function getCompanyId($user): ?int
    {
        if ($user->role_id == Role::ADMIN) {
            return session('company_id', 1);
        } elseif ($user->role_id == Role::COMPANY && $user->company) {
            return $user->company->id;
        } elseif ($user->role_id == Role::BRANCH && $user->branch) {
            return $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT && $user->agent) {
            return $user->agent->branch->company->id;
        } elseif ($user->role_id == Role::ACCOUNTANT && $user->accountant) {
            return $user->accountant->branch->company->id;
        }

        return null;
    }
}