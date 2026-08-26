<?php

use App\Models\Role;

if(!function_exists('getCompanyId')){
    function getCompanyId($user): ?int
    {
        switch ($user->role_id) {
            case Role::ADMIN:
            // A platform operator (role 1) owns no company, so this falls back
            // to the company they last selected in the sidebar switcher, and
            // to company 1 when they have selected none.
            //
            // 2026-08: this fallback was briefly changed to return NULL, to
            // stop an operator's page view acting on company 1's real data.
            // That was REVERTED: ~100 call sites across the app assume an
            // operator always resolves to a company, so returning null 404'd
            // or 403'd the entire gated surface for every operator on a fresh
            // login (the accounting menu vanished; /coa, the reports and the
            // Admin Center all broke), and two sibling fallbacks
            // (determineUserRole() below, BulkInvoiceController) were left
            // inconsistent with it.
            //
            // The real risk was never READING as company 1 — it was taking an
            // irreversible EXTERNAL action against a guessed company. That is
            // now guarded where it belongs: any Resayil action that creates or
            // links a live third-party workspace requires the operator to have
            // explicitly chosen a company (session('company_id') actually set),
            // and refuses otherwise. See ResayilAdminController /
            // ResayilEmbedController.
            return (int) session('company_id', 1);
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