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
    | explicitly gets it applied. What happens to a company nobody ever
    | applies it to is decided by `default_disabled` below, not by this
    | array.
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

    /*
    |--------------------------------------------------------------------------
    | Modules that fail CLOSED when a company has no explicit flag
    |--------------------------------------------------------------------------
    |
    | Company::hasModule() treats a missing `module.{name}` settings row as
    | "not migrated to the entitlement system yet, so don't restrict it" and
    | returns true — which is the right default for a module every company
    | is entitled to. Any module listed here inverts that: a missing row
    | means OFF, and access requires an explicit `module.{name} = 1` row.
    |
    | `accounting` is listed because it is the one module TravelERP does not
    | sell. No tenant is entitled to it by default, so leaving it fail-open
    | would expose the whole accounting surface (chart of accounts, journal
    | entries, trial balance, vouchers, reconciliation) to every company
    | that predates the entitlement layer and therefore has no `module.*`
    | rows at all. Failing closed here is what makes "hidden unless granted"
    | true for accounting without having to backfill a row for every company
    | that will ever exist.
    |
    | Grant it to a specific company with:
    |
    |     php artisan company:set-module {company} accounting --on
    |
    | Removing a module from this list re-opens it to every company with no
    | explicit row. Do that only for a module the product genuinely ships to
    | everyone.
    |
    */
    'default_disabled' => [
        Modules::ACCOUNTING,
    ],

];
