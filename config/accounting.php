<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Posting engine kill-switch
    |--------------------------------------------------------------------------
    |
    | Global gate for App\Services\Accounting\PostingService. In P1 this is
    | inert — nothing reads it yet, since no feeder calls the engine (that is
    | P2). It exists so P2's per-company strangler cutover has a global
    | backstop ready on day one, alongside the per-company
    | companies.posting_engine_enabled column (2026_08_24_120005 migration).
    |
    | See Accounting Gap/11-technical-implementation-plan.md §C2.
    |
    */
    'engine' => [
        'enabled' => env('ACCOUNTING_ENGINE_ENABLED', false),

        // R2 / BUG-C1 check 1.1 — abs(Σdebit − Σcredit) tolerance for a single
        // posted document in PostingService::post(). Deliberately tighter than
        // TrialBalanceService's aggregate is_balanced threshold (< 0.001,
        // app/Services/TrialBalanceService.php:57) — the two are independently
        // specified at different granularities; do not homogenize them.
        'balance_tolerance' => 0.0005,

        // Money precision this build's new/widened columns use. KWD is a
        // 3-decimal-fils currency. Widening the *existing* 15,3/10,2 money
        // columns to 18,3 is the separate, explicitly deferred
        // widen_money_columns migration — not done here.
        'base_currency' => 'KWD',
        'base_decimals' => 3,
        'rate_decimals' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Account-creation observer gate (INDEPENDENT of 'engine.enabled' above)
    |--------------------------------------------------------------------------
    |
    | Round-4 fix (P1 blocker #3): App\Observers\AccountObserver's `creating`
    | backstop used to key off 'engine.enabled', i.e. the SAME flag that turns
    | the posting engine on for a company. That coupling meant the day P2
    | flips the engine flag on to wire its first feeder, the observer would
    | simultaneously start policing EVERY Account::create() app-wide —
    | including the ~10 legacy call sites (AgentController, BranchController,
    | ChargeController, SupplierCompanyController, TaskController,
    | InvoiceController, ChatController, ImportChartOfAccounts, CoaController)
    | whose refactor onto App\Services\Accounting\AccountService is explicitly
    | deferred to P2. Flipping 'engine.enabled' would have been an app-wide
    | outage trigger.
    |
    | This flag decouples the two concerns. AccountObserver checks ONLY this
    | key — 'engine.enabled' is irrelevant to it. Leave this false (the
    | default) until every legacy Account::create() call site above has been
    | migrated onto AccountService; only then is it safe to turn this on,
    | independently of whatever 'engine.enabled' happens to be at the time.
    |
    */
    'account_observer' => [
        'enabled' => env('ACCOUNTING_ACCOUNT_OBSERVER_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document types
    |--------------------------------------------------------------------------
    |
    | Fixed vocabulary for transactions.doc_type / serial_schemas.doc_type.
    | Keys are the persisted codes (max 8 chars per the migrations); values are
    | human labels for UI/report use.
    |
    */
    'doc_types' => [
        'INV' => 'Invoice',
        'RV' => 'Receipt Voucher',
        'PV' => 'Payment Voucher',
        'JV' => 'Journal Voucher',
        'CRN' => 'Credit Note',
        'DBN' => 'Debit Note',
        'OJV' => 'Opening Journal Voucher',
        'REV' => 'Reversal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purpose codes (system_accounts.purpose_code vocabulary)
    |--------------------------------------------------------------------------
    |
    | The fixed vocabulary database/seeders/SystemAccountsSeeder.php maps for
    | every company, and that App\Services\Accounting\AccountResolver resolves
    | against. Centralized here as the single source of truth so the seeder,
    | the resolver, and any future feeder agree on the exact strings.
    |
    | - global: purpose codes with service_type always NULL.
    | - gateways: GATEWAY_CLEARING is minted per payment gateway as
    |   GATEWAY_CLEARING_{key} (service_type NULL) — see file 11's Open
    |   Questions on GATEWAY_CLEARING disambiguation. Keys/labels mirror
    |   CLAUDE.md's payment gateway list.
    | - per_service × service_types: the three purpose codes multiplied
    |   across the 12 task types (CLAUDE.md's task-type list), e.g.
    |   purpose_code=SERVICE_COST, service_type=hotel.
    |
    */
    'purpose_codes' => [
        'global' => [
            'RECEIVABLE_CONTROL',
            'PAYABLE_CONTROL',
            'RETAINED_EARNINGS',
            'FX_GAIN_LOSS',
            'VAT_OUTPUT',
            'SUSPENSE',
        ],

        'gateways' => [
            'MYFATOORAH' => 'MyFatoorah',
            'KNET' => 'Knet',
            'UPAYMENT' => 'uPayment',
            'HESABE' => 'Hesabe',
            'TAP' => 'Tap',
        ],

        'per_service' => [
            'SERVICE_REVENUE',
            'SERVICE_PAYABLE',
            'SERVICE_COST',
        ],

        'service_types' => [
            'flight', 'hotel', 'visa', 'insurance', 'tour', 'cruise',
            'car', 'rail', 'esim', 'event', 'lounge', 'ferry',
        ],
    ],

];
