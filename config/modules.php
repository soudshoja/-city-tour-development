<?php

use App\Support\Modules;

return [

    /*
    |--------------------------------------------------------------------------
    | Package module preset
    |--------------------------------------------------------------------------
    |
    | TravelERP is sold as a 5-module package: Task Uploader, Payment
    | Gateway, Customer CRM, Agent Profit Calculation, and Resayil WhatsApp
    | CRM. Accounting is NOT part of the package — the ledger keeps posting
    | journal entries silently underneath every task/invoice/payment
    | regardless of this flag, but the accounting UI and reports stay
    | hidden from package clients until their `module.accounting` flag is
    | switched on.
    |
    | App\Support\Entitlements\ApplyCompanyModulePreset::apply() writes one
    | `settings` row per key below (company-scoped, type=boolean) for a
    | single company. It is never run automatically — see that class's
    | docblock for when to use it.
    |
    | This preset is ONLY the write-side default for a company that
    | explicitly gets it applied. A company nobody ever applies it to (all
    | 3 pre-Phase-1 companies, today) has no `module.*` rows at all, and
    | Company::hasModule() treats an absent row as "on" for every module —
    | so those companies are completely unaffected by this file existing.
    |
    */
    'package_preset' => [
        Modules::TASK_UPLOADER => true,
        Modules::PAYMENT_GATEWAY => true,
        Modules::CRM => true,
        Modules::AGENT_PROFIT => true,
        Modules::RESAYIL => true,
        Modules::ACCOUNTING => false,
    ],

];
