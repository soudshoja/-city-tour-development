<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed data root
    |--------------------------------------------------------------------------
    |
    | The legacy-ledger pilot loaders (LP0.2 fence) refuse to open any CSV
    | whose real, resolved path does not sit under this root. Never widen
    | this in a way that could admit a repo or OneDrive path -- see
    | App\Services\Onboarding\LegacyPathGuard.
    |
    */
    'allowed_root' => env('LEGACY_PILOT_DATA_ROOT', 'D:\\akeedac'),

    /*
    |--------------------------------------------------------------------------
    | Loader chunking
    |--------------------------------------------------------------------------
    |
    | LegacyCsvLoader batches CSV rows into multi-row INSERTs. A fixed
    | row-count chunk size overruns MySQL/MariaDB's 65,535-placeholder-per-
    | prepared-statement ceiling on wide tables (e.g. tblTrDetail has 353
    | columns; a fixed 500-row chunk needs 176,500 placeholders and fails
    | with error 1390). `max_placeholders` caps the TOTAL placeholder count
    | per INSERT (rows * column_count); the loader derives its actual
    | per-file row-chunk size from this and the CSV's own discovered column
    | count, never a hardcoded row count. Left well under the real 65,535
    | ceiling as headroom.
    |--------------------------------------------------------------------------
    */
    'loader' => [
        'max_placeholders' => env('LEGACY_PILOT_MAX_PLACEHOLDERS', 60000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manifest -- one entry per file in README-MANIFEST.md's "Files and row
    | counts" table. `table` is the stg_* table name (created dynamically by
    | the loader from the CSV's own header row -- see LegacyCsvLoader). Row
    | counts are copied verbatim from
    | D:\akeedac\ledger-export-2025-2026Q1\README-MANIFEST.md and must never
    | be "rounded" or estimated -- an exact mismatch is a hard failure
    | (LP0.3 acceptance).
    |--------------------------------------------------------------------------
    */
    'export_dir' => 'ledger-export-2025-2026Q1',

    'tables' => [
        'tblAccDetail' => ['file' => 'tblAccDetail.csv', 'table' => 'stg_acc_detail', 'rows' => 179421],
        'tblAccHeader' => ['file' => 'tblAccHeader.csv', 'table' => 'stg_acc_header', 'rows' => 37134],
        'tblAccIsApply' => ['file' => 'tblAccIsApply.csv', 'table' => 'stg_acc_is_apply', 'rows' => 27736],
        'tblAccMonthlyBalance' => ['file' => 'tblAccMonthlyBalance.csv', 'table' => 'stg_acc_monthly_balance', 'rows' => 12292],
        'tblAccount' => ['file' => 'tblAccount.csv', 'table' => 'stg_account', 'rows' => 1351],
        'tblAccRateAdj' => ['file' => 'tblAccRateAdj.csv', 'table' => 'stg_acc_rate_adj', 'rows' => 1029],
        'tblAccYear' => ['file' => 'tblAccYear.csv', 'table' => 'stg_acc_year', 'rows' => 10],
        'tblBranch' => ['file' => 'tblBranch.csv', 'table' => 'stg_branch', 'rows' => 4],
        'tblCurrency' => ['file' => 'tblCurrency.csv', 'table' => 'stg_currency', 'rows' => 21],
        'tblEmployee' => ['file' => 'tblEmployee.csv', 'table' => 'stg_employee', 'rows' => 86],
        'tblFORCTDetail' => ['file' => 'tblFORCTDetail.csv', 'table' => 'stg_forct_detail', 'rows' => 6418],
        'tblFoRctHeader' => ['file' => 'tblFoRctHeader.csv', 'table' => 'stg_forct_header', 'rows' => 6285],
        'tblLookup' => ['file' => 'tblLookup.csv', 'table' => 'stg_lookup', 'rows' => 472],
        'tblMemoDetail' => ['file' => 'tblMemoDetail.csv', 'table' => 'stg_memo_detail', 'rows' => 1011],
        'tblMemoHeader' => ['file' => 'tblMemoHeader.csv', 'table' => 'stg_memo_header', 'rows' => 507],
        'tblPartner' => ['file' => 'tblPartner.csv', 'table' => 'stg_partner', 'rows' => 718],
        'tblSystemParameters' => ['file' => 'tblSystemParameters.csv', 'table' => 'stg_system_parameters', 'rows' => 263],
        'tblTrDetail' => ['file' => 'tblTrDetail.csv', 'table' => 'stg_tr_detail', 'rows' => 44701],
        'tblTrMaster' => ['file' => 'tblTrMaster.csv', 'table' => 'stg_tr_master', 'rows' => 22145],
        'tblTrPayment' => ['file' => 'tblTrPayment.csv', 'table' => 'stg_tr_payment', 'rows' => 22796],
        'tblTrPaymentAllocation' => ['file' => 'tblTrPaymentAllocation.csv', 'table' => 'stg_tr_payment_allocation', 'rows' => 45565],
        'trialbalance_20241231_bybranch' => ['file' => 'trialbalance_20241231_bybranch.csv', 'table' => 'stg_tb_20241231_bybranch', 'rows' => 309],
        'trialbalance_20241231_consolidated' => ['file' => 'trialbalance_20241231_consolidated.csv', 'table' => 'stg_tb_20241231_consolidated', 'rows' => 205],
        'trialbalance_20251231_bybranch' => ['file' => 'trialbalance_20251231_bybranch.csv', 'table' => 'stg_tb_20251231_bybranch', 'rows' => 1074],
        'trialbalance_20251231_consolidated' => ['file' => 'trialbalance_20251231_consolidated.csv', 'table' => 'stg_tb_20251231_consolidated', 'rows' => 532],
        'trialbalance_20260331_bybranch' => ['file' => 'trialbalance_20260331_bybranch.csv', 'table' => 'stg_tb_20260331_bybranch', 'rows' => 1160],
        'trialbalance_20260331_consolidated' => ['file' => 'trialbalance_20260331_consolidated.csv', 'table' => 'stg_tb_20260331_consolidated', 'rows' => 563],
        'vw_CostCenter' => ['file' => 'vw_CostCenter.csv', 'table' => 'stg_cost_center', 'rows' => 1],
        'vw_CreditCard' => ['file' => 'vw_CreditCard.csv', 'table' => 'stg_credit_card', 'rows' => 8],
        'vw_paymode' => ['file' => 'vw_paymode.csv', 'table' => 'stg_paymode', 'rows' => 10],

        /*
        | LP4 secondary parity anchors. These three files sit in the same
        | export directory and are named in PLAN.md §1.1 ("Parity anchors
        | (secondary)") and MAPPING-RULES.md §9.2/§9.3, but LP0.3's manifest
        | stopped at the trial balances -- so `legacy:load` never staged
        | them and `legacy:parity` had nothing to read. Row counts are the
        | plan's own verified figures (198 / 109 / 75) and are asserted
        | exactly, like every other manifest entry.
        |
        | The 2026-Q1 siblings (profit_loss_2026Q1.csv, ar/ap_balances_
        | 20260331.csv) are deliberately NOT added: 2026 Q1 is parked
        | (PLAN.md §1.2) and staging a file nothing compares against would
        | only invite an out-of-scope anchor to be quoted as a result.
        */
        'profit_loss_2025' => ['file' => 'profit_loss_2025.csv', 'table' => 'stg_pl_2025', 'rows' => 198],
        'ar_balances_20251231' => ['file' => 'ar_balances_20251231.csv', 'table' => 'stg_ar_20251231', 'rows' => 109],
        'ap_balances_20251231' => ['file' => 'ap_balances_20251231.csv', 'table' => 'stg_ap_20251231', 'rows' => 75],
    ],

    /*
    |--------------------------------------------------------------------------
    | 2025 posted document census (LP0.4 / LP1 acceptance). SubType => count.
    | Verified against the export; the audit command reproduces this from
    | stg_acc_header and fails loudly on any drift.
    |--------------------------------------------------------------------------
    */
    'census_2025' => [
        'INV' => 18071,
        'FRV' => 4325,
        'BPV' => 1934,
        'CRV' => 1802,
        'BDS' => 1581,
        'CRN' => 931,
        'RJV' => 834,
        'CPV' => 659,
        'ADM' => 302,
        'JV' => 151,
        'ACM' => 11,
        'BRV' => 10,
        'OJV' => 1,
    ],

    'posted_true_count' => 37097,
    'posted_false_count' => 37,

    /*
    |--------------------------------------------------------------------------
    | Known currency poison rows (R3 / LP1.4). Any in-window line referencing
    | one of these ids must halt the phase -- never silently import a 2500x
    | rate. Matched case-sensitively on CurrCode, plus a blank-code and a
    | bare "2" code.
    |--------------------------------------------------------------------------
    */
    'currency_poison_codes' => ['XYZ', 'xyz', 'abc', '2', ''],

    /*
    |--------------------------------------------------------------------------
    | Party account groups (LP1.1/1.3). Leaves under these AccGroup prefixes
    | -- or referenced by a tblPartner account FK -- pool to
    | RECEIVABLE_CONTROL / PAYABLE_CONTROL rather than becoming per-party GL
    | leaves.
    |--------------------------------------------------------------------------
    */
    'customer_group_prefixes' => ['10904'],
    'payable_group_prefixes' => ['206'],

    /*
    |--------------------------------------------------------------------------
    | Group prefixes that must NEVER pool, whatever else matches
    | (MAPPING-RULES.md decision O8-refunds).
    |
    | 206010300 holds the per-branch REFUNDS PAYABLE leaves. They start with
    | '206' and so matched payable_group_prefixes, but they are NOT party
    | leaves -- no tblPartner FK points at any of them -- and pooling them
    | into PAYABLE_CONTROL would (a) break AP-per-leaf parity against the
    | 75-leaf ap_balances_*.csv anchor and (b) destroy the two-step refund
    | model (CRN credits the refunds-payable liability; a later BPV/CPV
    | settles it) that MAPPING-RULES.md §2.5 builds the CRN rule on. They
    | import as ordinary `direct` leaves.
    |--------------------------------------------------------------------------
    */
    'pool_excluded_group_prefixes' => ['206010300'],

    /*
    |--------------------------------------------------------------------------
    | O8-refunds, general form: a payable-side leaf pools ONLY if a
    | tblPartner role FK actually points at it. 83 of the 350 '206%' leaves
    | in the real export have no partner FK -- named airlines, cargo
    | payables, advances, the refunds-payable set -- and every one of them is
    | a structural GL leaf that must keep its own account.
    |
    | LP1e (ruling R-arleaves) applies the SAME rule to the receivable side.
    | It used to be deliberately one-sided, on the reasoning that the 6
    | customer-prefix leaves with no partner FK were "genuine party leaves
    | whose tblPartner row simply is not in the export" and that requiring an
    | FK there would mint six phantom GL leaves. Staging run #5 disproved the
    | harmless half of that: a pooled leaf with no party_id cannot be
    | decomposed back per party (LP4 check 3), so `legacy:import-coa` failed
    | on exactly those 6 (Acc_IDs 1302024, 1302030, 1401048, 1403007,
    | 1403042, 1406005) while the import itself committed. They are not
    | phantom leaves: they are ordinary GL leaves the pooling should never
    | have claimed. Importing them `direct` is the exact mirror of O8 on the
    | payable side, and it keeps their balance visible at their own account
    | instead of inside a control they cannot be split out of.
    |--------------------------------------------------------------------------
    */
    'payable_pooling_requires_partner_fk' => true,
    'receivable_pooling_requires_partner_fk' => true,

    /*
    |--------------------------------------------------------------------------
    | AccType -> Akeed account_types.code split to reconcile against
    | (LP1.1 acceptance: A 429 / L 454 / I 332 / E 136).
    |--------------------------------------------------------------------------
    */
    'account_type_split' => [
        'A' => 429,
        'L' => 454,
        'I' => 332,
        'E' => 136,
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy root AccCode -> the CANONICAL Akeed root account NAME the tree
    | must hang under.
    |
    | THIS IS NOT COSMETIC. App\Services\TrialBalanceService decides whether
    | an account is debit-normal or credit-normal by looking at the NAME of
    | the root its root_id points at, matched CASE-SENSITIVELY against
    | ['Assets','Expenses','Liabilities','Equity','Income'] -- see
    | resolveAccountNormalSide()/getNormalBalance() in that file. The legacy
    | tree's own six roots are named ASSETS / LIABILITIES / INCOMES /
    | EXPENSES / APPROPRIATIONS / EQUITY, none of which matches that list, so
    | importing them verbatim makes EVERY asset and expense leaf fall through
    | to the credit-normal default -- silently inverting the sign of every
    | such balance in the trial balance and the P&L, and guaranteeing an LP4
    | parity failure that looks like a posting bug.
    |
    | Two legacy roots (APPROPRIATIONS 50000, EQUITY 60000) map to the same
    | canonical name: the FIRST one imported becomes the real root and the
    | second is re-parented under it as an ordinary level-2 group, keeping
    | its own legacy name and code and its whole subtree. That is also what
    | produces the "five roots" the owner walkthrough (PLAN.md 5.0 row 1)
    | expects from a six-root legacy tree.
    |
    | The importer REFUSES the whole import on any root whose AccCode is not
    | in this map -- it never guesses a canonical name.
    |--------------------------------------------------------------------------
    */
    'root_canonical_names' => [
        '10000' => 'Assets',
        '20000' => 'Liabilities',
        '30000' => 'Income',
        '40000' => 'Expenses',
        '50000' => 'Equity',
        '60000' => 'Equity',
    ],

    'account_type_map' => [
        'A' => 'ASSET',
        'L' => 'LIABILITY',
        'I' => 'INCOME',
        'E' => 'EXPENSE',
    ],

    'default_company_id' => env('LEGACY_PILOT_COMPANY_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | LP1c import controls (coordinator rulings R1/R2, 2026-09-07)
    |--------------------------------------------------------------------------
    |
    | synthetic_code_range
    |   R2. Two of the four LP1.5 control purposes cannot be reached by the
    |   legacy parameter mechanism at all: PAYABLE_CONTROL has NO legacy
    |   parameter (PayableControlAcc is blank in the export) and the only
    |   structural candidate the pooling can hang on -- the legacy AP group
    |   named by config('legacy_pilot.payable_group_prefixes') -- is a GROUP,
    |   and a group is not postable. Rather than pool every supplier line onto
    |   an unpostable node (or, worse, guess a leaf by name), LegacyCoaImporter
    |   MINTS one synthetic pooled control LEAF under that legacy group. Its
    |   code is allocated from this range -- never a literal in the source, so
    |   an installation whose legacy chart already uses these numbers can move
    |   the range in config instead of patching code. The importer refuses if
    |   the range is exhausted; it never reuses or overwrites an existing code.
    |
    | activity_tables
    |   R1. Tables whose rows mean "this chart is IN USE". --replace-seeded
    |   refuses outright if any of them holds a row for the company (via a
    |   company_id column) or a row referencing one of the company's accounts
    |   (via a real FK constraint to accounts.id). Every OTHER table that has
    |   an FK to accounts.id is treated as a re-linkable owner reference: its
    |   column is NULLED and the row is listed for re-linking at go-live.
    |   `invoices` carries no account FK in this schema, so it can only ever
    |   match on a company_id column -- it is listed here so that the day an
    |   invoice->account FK is added, it counts as activity rather than as
    |   something to null.
    |--------------------------------------------------------------------------
    */
    'import' => [
        'synthetic_code_range' => [
            'start' => env('LEGACY_PILOT_SYNTHETIC_CODE_START', 990000),
            'end' => env('LEGACY_PILOT_SYNTHETIC_CODE_END', 990999),
        ],

        'activity_tables' => [
            'journal_entries',
            'transactions',
            'payments',
            'invoices',
            'invoice_partials',
            'general_ledgers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LP1.2 purpose map. purpose_code => legacy tblSystemParameters
    | ParameterName. Deliberately EMPTY by default -- see
    | App\Services\Onboarding\SystemPurposeMapper's docblock for why real
    | legacy key names are never hardcoded here. Populate at bring-up time
    | (LP0 staging .env / config override) after inspecting the real,
    | never-committed export.
    |--------------------------------------------------------------------------
    */
    'parameter_purpose_map' => [],

    /*
    |--------------------------------------------------------------------------
    | LP1e masters (`legacy:import-masters`) -- branches and currencies
    |--------------------------------------------------------------------------
    |
    | Staging run #5 proved that `map_branch` and `map_currency` are read by
    | LegacyDocumentMapper and written by NOTHING in production code, so the
    | replay refused 100% of the 2025 population on `legacy.branch_unmapped`.
    | `legacy:import-masters` is the populator; this block is its policy.
    |
    | branch_name_format
    |   R-branch creates the four legacy branches as real Akeed branches.
    |   Their NAMES ARE STRUCTURAL -- derived from the legacy BranchCode
    |   (CO/SH/MN/BY), never copied from BranchName. A legacy branch name is
    |   business data, and this pilot republishes structure, not data.
    |
    | branch_email_domain
    |   `branches.email` is nullable and carries no unique index; a branch
    |   minted by this importer gets `<lowercased code>@<domain>` when a
    |   domain is configured and NULL when it is not. Never a real address.
    |
    | currency.rate_match_decimals
    |   R-currency derives a currency code from LINE USAGE, because the
    |   currency master these FKs point at (`tblMaster`) was never exported
    |   (PLAN.md §1.1: "currencies derive from actual line usage, not a
    |   master"). A line's FcExchRate is compared to `stg_currency`'s rate
    |   history as a STRING normalised to this many decimals -- exact
    |   equality, not a tolerance. 12 is the width of the legacy
    |   `decimal(18,12)` rate column, so a master rate stored at 7 decimals
    |   is zero-padded to 12 and a line rate that differs in the 12th place
    |   is correctly NOT a match. Loosening this would invent mappings.
    |
    | currency.identity_rate / currency.identity_code
    |   The second derivation rule: a line whose FC total equals its LC total
    |   and whose rate is exactly 1 IS a base-currency line, whatever FK it
    |   carries. This is the rule that resolves the 170,577-line bulk of the
    |   export, whose FK (a tblMaster id) has no counterpart in stg_currency
    |   at all. It is checked FIRST and requires EVERY line of that FK to
    |   have the shape -- one non-identity line and the FK falls through to
    |   the rate-history match.
    |--------------------------------------------------------------------------
    */
    'masters' => [
        'branch_name_format' => env('LEGACY_PILOT_BRANCH_NAME_FORMAT') ?: 'Legacy Branch %s',
        'branch_email_domain' => env('LEGACY_PILOT_BRANCH_EMAIL_DOMAIN') ?: null,

        'currency' => [
            'rate_match_decimals' => 12,
            'identity_rate' => '1',
            'identity_code' => 'KWD',
            // |(FCDebit + FCCredit) - (Debit + Credit)| <= this, i.e. the
            // legacy kernel's own rule-3 tolerance (MAPPING-RULES §1.2 (c)).
            'identity_tolerance' => 0.0005,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LP3 replay -- frozen accounts
    |--------------------------------------------------------------------------
    |
    | Ruling (coordinator, engineering call, LP1b): in the legacy system,
    | IsFreeze is a FORWARD-LOOKING posting lock applied at export time --
    | not a historical invalidation. 2025 lines already posted against an
    | account that was later frozen are legitimate history and MUST replay
    | through the LP3 seam; `legacy:audit-masters`'s
    | `frozen_account_activity` check is therefore INFO-only (it reports the
    | count and ids, it never fails the phase).
    |
    | The importer still preserves the frozen/disabled status on the
    | account itself (LegacyCoaImporter -- accounts.disabled), so a NEW
    | (non-legacy, non-replay) posting to that account after cutover is
    | correctly refused by PostingService's FrozenAccountException. This
    | flag exists so the LP3 replay path can tell "an in-window legacy
    | document being replayed" apart from "a brand-new posting attempt" and
    | is consulted ONLY there -- it does not change PostingService, and it
    | does not touch this loader/audit engine. Default true per this
    | ruling; flip to false only if a future owner decision reverses it.
    |
    | LP3 ADDENDUM (build note, not a reversal). LegacyCoaImporter now imports
    | an IsFreeze leaf with `accounts.disabled = 1`, and PostingService step 3e
    | refuses a disabled account with FrozenAccountException. So on the replay
    | path this flag alone is not sufficient: the engine has to be told, per
    | document, that THIS post is a legacy replay. That is
    | `frozen_leaf_bypass_sub_type_like` below -- NULL by default (off
    | everywhere but a pilot instance), and consulted by PostingService only in
    | conjunction with this flag. The runner asserts the pair is coherent at
    | preflight and ABORTS rather than tagging, because a frozen-account
    | refusal on a legacy document is a defect, not data.
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | LP3 replay (MAPPING-RULES.md §1-§8)
    |--------------------------------------------------------------------------
    |
    | EVERY threshold, class list and doc-type decision the replay makes lives
    | here. App\Services\Onboarding\Replay\* contains no numeric literal and no
    | SubType literal at all -- MAPPING-RULES.md is the spec and this block is
    | its machine-readable form, so a rule change is a config diff a reviewer
    | can read against the document.
    |
    */
    'replay' => [

        // The replay window. OJV is selected by DocYear instead (see below),
        // because the 2025 opening document is dated 2025-01-01 and the
        // earlier-year OJVs are staged for reference only (§2.10).
        'window_start' => '2025-01-01',
        'window_end' => '2025-12-31',

        // §2.10 (a): DocYear of the OJV that IS the opening document.
        'opening_ojv_doc_year' => 2025,
        // §2.10 (b) / PLAN O10: the 2026 OJV is deliberately withheld as the
        // expected output of LP6's own year-end close. It is excluded by the
        // selector AND refused by the mapper with class `withheld_ojv`.
        'withheld_ojv_doc_years' => [2026],

        // §1.3 -- the feeder key every PostingSeam::post() call carries.
        'feeder_key' => 'onboarding.replay',
        // §8.1 -- 'legacy:' . SubType . ':' . DocID. NEVER a run id, batch or
        // timestamp: a key that varies between runs turns the re-run test
        // green while double-posting.
        'idempotency_prefix' => 'legacy',
        'allocation_idempotency_prefix' => 'legacy:apply',

        // §1.2 (c) / §1.5 #8 -- base-currency lines force exchangeRate 1.0 and
        // originalAmount == amount. The tolerances the mapper asserts BEFORE
        // forcing them, so a line that was never FC == LC is refused rather
        // than normalised into a lie.
        'fc_lc_tolerance' => 0.0005,
        'kwd_rate_tolerance' => 0.000001,
        // §1.5 #4 / §5 -- O6: a document out of balance is REFUSED, never
        // plugged. Staged sums are compared as exact 3-dp integers, so this
        // threshold is the engine's own and exists only to keep the two
        // numbers in one place.
        'balance_tolerance' => 0.0005,

        // §1.4 -- the closed doc-type vocabulary, one entry per in-window
        // SubType. An unmapped SubType is refused (`legacy.doctype_unmapped`),
        // never defaulted.
        'doc_type_map' => [
            'INV' => ['doc_type' => 'INV', 'sub_type' => 'LEGACY_INV', 'source_type' => 'Invoice'],
            'CRN' => ['doc_type' => 'CRN', 'sub_type' => 'LEGACY_CRN', 'source_type' => 'Refund'],
            'ACM' => ['doc_type' => 'CRN', 'sub_type' => 'LEGACY_ACM', 'source_type' => 'Refund'],
            'ADM' => ['doc_type' => 'DBN', 'sub_type' => 'LEGACY_ADM', 'source_type' => 'Payment'],
            'JV' => ['doc_type' => 'JV', 'sub_type' => 'LEGACY_JV', 'source_type' => null],
            'RJV' => ['doc_type' => 'JV', 'sub_type' => 'LEGACY_RJV', 'source_type' => null],
            'OJV' => ['doc_type' => 'OJV', 'sub_type' => 'LEGACY_OJV', 'source_type' => 'Invoice'],
            'CRV' => ['doc_type' => 'RV', 'sub_type' => 'LEGACY_CRV', 'source_type' => 'Receipt'],
            'BRV' => ['doc_type' => 'RV', 'sub_type' => 'LEGACY_BRV', 'source_type' => 'Receipt'],
            'FRV' => ['doc_type' => 'RV', 'sub_type' => 'LEGACY_FRV', 'source_type' => 'Receipt'],
            'BPV' => ['doc_type' => 'PV', 'sub_type' => 'LEGACY_BPV', 'source_type' => 'Payment'],
            'CPV' => ['doc_type' => 'PV', 'sub_type' => 'LEGACY_CPV', 'source_type' => 'Payment'],
            // §2.8: BDS is a JV, not an RV -- mapping it to RV would drag 1,581
            // documents under the RV/PV cash-or-bank invariant for no gain.
            'BDS' => ['doc_type' => 'JV', 'sub_type' => 'LEGACY_BDS', 'source_type' => null],
        ],

        // §2.11 -- lifetime-wide but never in the 2025 window. Their presence
        // in-window is a census defect: refuse and escalate, never improvise.
        'out_of_scope_sub_types' => ['MAN_INV', 'MAN_CRN'],

        // §1.2 (d) -- every replayed line is an explicit-account line, so
        // `ledgerType` falls back to this prefix + the SubType when the legacy
        // line carries no TransactionType, and `settlementChannel` to the
        // second prefix (journal_entries.settlement_channel is 24 chars).
        'ledger_type_prefix' => 'LEGACY_',
        'settlement_channel_prefix' => 'legacy:',

        // §1.8 -- O4 in operational form.
        'o4' => [
            // A refusal whose class is in this list describes the MAPPING, not
            // one document: it stops the whole type immediately.
            'structural_failure_codes' => [
                'legacy.account_unmapped',
                'legacy.currency_unmapped',
                'legacy.currency_poison',
                'legacy.doctype_unmapped',
                'legacy.branch_unmapped',
                'legacy.subtype_out_of_scope',
                'legacy.ojv_out_of_window',
                'UnmappedPurposeException',
                'CrossTenantAccountException',
                'NonLeafAccountException',
                'FrozenAccountException',
                'MissingIdempotencyKeyException',
                'PostingEngineDisabledException',
                'InvalidCurrencyCodeException',
                'SupersededIdempotencyKeyException',
            ],
            // The type's golden set: any refusal among the first N documents of
            // a type stops the type (LP2 acceptance made executable).
            'golden_set_size' => 20,
            // The task brief's own rule: the first N documents of a type all
            // refusing with the SAME failure code stops the type, even when no
            // individual code is structural.
            'same_class_streak' => 3,
            // §1.8: refusals in a type exceeding max(floor, percent x census).
            'threshold_floor' => 5,
            'threshold_percent' => 0.01,
        ],

        // §1.6 / §2.14 -- what is skipped by rule rather than refused. Listed
        // so the runner never hard-codes a status string.
        'withhold_failure_codes' => ['withheld_ojv'],

        /*
        | Coordinator ruling, 2026-09-08 (first staging run): 12 of the 21
        | `tblAccount.IsFreeze = 1` accounts carry 2025 lines. A legacy freeze is
        | a FORWARD-LOOKING data-entry control, not a statement about history --
        | MAPPING-RULES §1.5 #6 already says as much ("freezing them would refuse
        | valid 2025 history") and LegacyCoaImporter accordingly imports them
        | with disabled = 0. Those lines MUST replay.
        |
        | With this flag TRUE (the default), the runner:
        |   (a) asserts at preflight that no account behind a legacy IsFreeze
        |       leaf is soft-deleted on the Akeed side -- the only condition
        |       PostingService actually raises FrozenAccountException on; and
        |   (b) treats a FrozenAccountException on a legacy document as a DEFECT
        |       that aborts the run, never as a per-document tag.
        | With it FALSE, a frozen-account refusal falls through to ordinary O4
        | structural handling (whole-type stop).
        |
        | Reconcile with LP1b (`fix/lp1b-loader-audit`), which is adding the same
        | key, when that branch lands.
        */
        'ignore_frozen_for_legacy_docs' => env('LEGACY_PILOT_IGNORE_FROZEN_FOR_LEGACY_DOCS', true),

        /*
        | The engine half of the frozen-leaf ruling, and the ONLY pilot flag that
        | changes PostingService's own behaviour. NULL by default -- so on every
        | non-pilot instance PostingService step 3e refuses a disabled account
        | exactly as it always has (pinned by
        | tests/Feature/Legacy/LegacyFrozenLeafBypassTest.php). A pilot instance
        | sets LEGACY_PILOT_FROZEN_BYPASS_SUB_TYPE_LIKE=LEGACY_% so that, AND ONLY
        | THEN, a draft whose sub_type matches may post to a leaf carrying the
        | legacy freeze -- which MAPPING-RULES §1.5 #6 requires ("freezing them
        | would refuse valid 2025 history"). It cannot affect a non-legacy
        | document even on the pilot instance, because no non-replay draft
        | carries a LEGACY_* sub_type.
        */
        'frozen_leaf_bypass_sub_type_like' => env('LEGACY_PILOT_FROZEN_BYPASS_SUB_TYPE_LIKE'),

        // §5.3 / §8.4 -- how many headers one chunk of the chronological walk
        // pulls. Purely a memory knob; the order (DocDt, DocID) is fixed.
        'batch_size' => 250,

        // §3 -- allocation replay.
        'allocations' => [
            // §3.2 (1): ModDt ascending, tie-broken by staged insertion order.
            'order_columns' => ['moddt', 'id'],
            'batch_size' => 500,
        ],

        /*
        | O7-verify (MAPPING-RULES §1.7 / §11, PLAN §5.3).
        |
        | `RvPvInvariantChecker` decides "cash/bank" by EXACT account-name match
        | against config('accounting.engine.bank_group_name') = 'Bank Accounts'
        | and cash_group_name = 'Cash In Hand'. The legacy chart's groups are
        | named BANK ACCOUNTS / CASH ACCOUNTS / PETTY CASH, so on the pilot
        | instance every one of the 8,730 replayed RV/PV documents would be
        | reported as a violation.
        |
        | `bank_group_name` / `cash_group_name` below are the values the PILOT
        | INSTANCE must set config('accounting.engine.*') to -- `legacy:replay`
        | asserts the running config matches and warns loudly when it does not.
        | The replay deliberately does NOT mutate global accounting config
        | itself: a staging override belongs in staging's config, not in a
        | command's side effects.
        |
        | `exclude_sub_type_like` is the ONE pilot flag that reaches engine-
        | adjacent code (RvPvInvariantChecker). It is NULL by default, i.e. off
        | on every non-pilot instance, and only ever suppresses the cash/bank
        | rule -- the balance and voucher-number rules stay in force. Proven off
        | by default in tests/Feature/Legacy/LegacyVerifyPilotFlagTest.php.
        */
        'verify' => [
            'bank_group_name' => 'BANK ACCOUNTS',
            'cash_group_name' => 'CASH ACCOUNTS',
            'extra_cash_group_names' => ['PETTY CASH'],
            'exclude_sub_type_like' => env('LEGACY_PILOT_VERIFY_EXCLUDE_SUB_TYPE_LIKE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LP4 -- parity harness (`legacy:parity`)
    |--------------------------------------------------------------------------
    |
    | Every date, table and tolerance the harness compares on. Nothing here
    | is a magic number invented by LP4: each value is traceable to
    | README-MANIFEST.md's own stated definitions (staged verbatim, never
    | recomputed) or to a ratified decision in PLAN.md §5.2 / MAPPING-RULES
    | §9.
    |
    */
    'parity' => [

        // MAPPING-RULES §9.1. Their OpeningNet is the 2025 OJV alone, so
        // "opening" is the position strictly BEFORE period_start, and the
        // 2025 OJV (dated 2025-01-01, i.e. INSIDE the period range) must be
        // excluded from period movement by sub_type or the whole trial
        // balance double-counts the opening position.
        'period_start' => '2025-01-01',
        'as_of' => '2025-12-31',
        'opening_as_of' => '2024-12-31',
        'opening_sub_type' => 'LEGACY_OJV',
        'legacy_sub_type_prefix' => 'LEGACY_',

        // PLAN.md §5.2 O5, RATIFIED: exact at 3 decimal places, 0.001 KWD
        // per account. `tolerance` is the comparison epsilon applied AFTER
        // both sides are rounded to `decimals` -- it exists only to absorb
        // IEEE-754 representation noise in the rounded values themselves
        // (0.0005 would already be a wider gate than O5 allows), NOT to
        // grant slack. Widening it past 0.0005 silently relaxes the
        // ratified pass line.
        'decimals' => 3,
        'tolerance' => 0.0005,

        // The staged anchors, with the row counts PLAN.md §1.1 verified.
        // `rows` is asserted on read: an anchor that lost or gained rows
        // between load and compare is a staging defect, not a parity result.
        'anchors' => [
            'opening' => ['table' => 'stg_tb_20241231_consolidated', 'rows' => 205, 'kind' => 'tb'],
            'closing' => ['table' => 'stg_tb_20251231_consolidated', 'rows' => 532, 'kind' => 'tb'],
            'pl' => ['table' => 'stg_pl_2025', 'rows' => 198, 'kind' => 'pl'],
            'ar' => ['table' => 'stg_ar_20251231', 'rows' => 109, 'kind' => 'party', 'role' => 'customer'],
            'ap' => ['table' => 'stg_ap_20251231', 'rows' => 75, 'kind' => 'party', 'role' => 'supplier'],
        ],

        // PLAN.md §5.0 row 4 / MAPPING-RULES §9.1: the consolidated closing
        // anchor's own totals. Asserted against the STAGED file as a
        // staging-integrity check before any Akeed-side figure is compared,
        // so "the anchor changed" can never be mistaken for "the replay
        // drifted". NULL disables the assertion (e.g. a synthetic fixture).
        'expected_closing_total' => 35859419.537,

        // MAPPING-RULES §11 O7-verify. accounting:verify's RV/PV cash-or-
        // bank invariant matches account GROUP NAMES exactly against
        // 'Bank Accounts' / 'Cash In Hand'; the legacy chart's groups are
        // BANK ACCOUNTS / CASH ACCOUNTS / PETTY CASH, so ~8,730 replayed
        // documents would be flagged for a naming difference. These
        // overrides are applied for the duration of the harness's verify
        // run ONLY (never persisted, never applied to a posting path).
        'verify_overrides' => [
            'accounting.engine.bank_group_name' => 'BANK ACCOUNTS',
            'accounting.engine.cash_group_name' => 'CASH ACCOUNTS',
        ],

        // The second half of O7-verify: the cash/bank counter-leg rule is
        // SCOPED OUT for legacy-replayed documents (a third legacy group,
        // PETTY CASH, has no config slot, and genuine no-cash-leg mirrors
        // exist by design -- MAPPING-RULES §1.7). The balance and
        // voucher-number rules stay in force for every document, legacy or
        // not. Residuals are reported per sub_type, never suppressed.
        'verify_scope_out_cash_bank_for_legacy' => true,

        // Report output. A relative path resolves under storage/app.
        'report_dir' => 'legacy-parity',
    ],
    /*
    |--------------------------------------------------------------------------
    | CD-PORT — City Travelers scope: the target company and the reserved id band
    |--------------------------------------------------------------------------
    |
    | Everything in this section is NET-NEW for the City Travelers port and has no counterpart in
    | the Akeed-Ai original. The pilot ran on a throwaway instance where company 1 WAS the legacy
    | company and every id in the database belonged to the pilot. Owner decision O-1 (2026-09-16)
    | instead puts Como INSIDE `citycomm_city-tour-test`, the database behind BOTH
    | development.citycommerce.group and test.citycommerce.group, as a new company alongside City
    | Travelers' live-mirrored companies 1, 2 and 3 — which are read-only for the whole phase
    | (ruling R-CO4).
    |
    | See App\Services\Onboarding\Scope\LegacyLoadScope for why the band is 10,000,001–19,999,999
    | and why the AUTO_INCREMENT-restore clause is waived (coordinator ruling S-A: proved
    | impossible on MariaDB 10.11.19 with rows present — the ALTER returns success and moves
    | nothing).
    |
    */
    'ct_scope' => [

        // ── THE SANDBOX GATE (owner decision 2026-09-16, superseding O-1) ────────────────────
        //
        // Como runs in its OWN COPY of the development database, not inside it. Two adversarial
        // verification rounds found two different routes by which this pipeline's row attribution
        // reached City Travelers data on a shared schema — an id band that the pipeline itself
        // filled with other people's rows, and reachability rules that claimed a supplier linked
        // to two companies. The fix is not a third attribution scheme; it is not sharing.
        //
        // `LEGACY_SANDBOX_DATABASE` must name the database this application actually connects to,
        // and that database must carry a `ct_legacy_sandbox` marker stamped for its own name. Both
        // are required, both are deliberate acts, and neither can happen by accident. See
        // App\Services\Onboarding\Scope\LegacySandboxGuard.
        'sandbox_database' => env('LEGACY_SANDBOX_DATABASE'),

        // Names that are never a sandbox, whatever any env var says. A belt-and-braces list: the
        // marker check above is the real gate, and this is here so that the single most damaging
        // typo is refused by name rather than by mechanism.
        'never_sandbox_databases' => [
            'citycomm_city-tour',       // LIVE
            'citycomm_city-tour-test',  // the working development + test site
        ],

        'id_floor' => (int) env('LEGACY_PILOT_ID_FLOOR', 10000001),
        'id_ceiling' => (int) env('LEGACY_PILOT_ID_CEILING', 19999999),

        // Ruling R-CO4 made a list. These company ids are never a legal load target, whatever the
        // operator types. 1 = City Travelers (497 accounts, 43,183 transactions, 112,662 journal
        // lines on the dev mirror); 2 and 3 are its siblings.
        'protected_company_ids' => [1, 2, 3],

        // ── The declared write set ───────────────────────────────────────────────────────────
        //
        // MEASURED, not guessed. Derived by arming every AUTO_INCREMENT counter in a fresh
        // `city_tour_test_cdport` fence to the floor, running CompanyProvisioner + the full
        // legacy:* chain, and then listing every table holding a row with `id >= 10,000,001`.
        // Anything that grows and is NOT on this list is a refusal, not a warning — see
        // LegacyIdBandGuard::assertNoUndeclaredTableGrew().
        //
        // Three entries are CORRECTIONS to CD0-REVERSAL-MANIFEST-2026-09-16 §2, which was written
        // from the Akeed provisioner and the plan rather than from a measured City Travelers run:
        //   • `coa_linkage_findings` — CT-only table (CT-A4), written by the provisioner's
        //     purpose-mapping step. 152 rows on the fence. Absent from the manifest entirely.
        //   • `settings` — the provisioner copies 6 default settings per company. The manifest
        //     lists `settings` only as "excluded from the sync", which is true and is why it is
        //     harmless, but it is still written and still has to be reversed.
        //   • `jobs` — the provisioner queues ProvisionResayilWorkspace after commit.
        //
        'tables' => [
            'companies',
            'users',
            'branches',
            'accounts',
            'roles',
            'sequences',
            'settings',
            'agents',
            'suppliers',
            'charges',
            'company_gds_pccs',
            'system_accounts',
            'accounting_periods',
            'serial_schemas',
            'coa_linkage_changes',
            'coa_linkage_findings',
            'cost_centers',
            'clients',
            'supplier_companies',
            'transactions',
            'journal_entries',
            'accounting_audit_log',
        ],

        // Growth here is NOT an undeclared write, and NOT reversible either.
        //
        // `jobs` is written by the provisioning step (it queues ProvisionResayilWorkspace on the
        // database queue driver), so an undeclared-growth check that did not know about it would
        // refuse a perfectly normal load. But it is deliberately NOT in `tables`:
        //
        //   * it carries nothing but a serialised payload, so no attribution rule could decide
        //     whether a given row is this load's without parsing that payload -- and parsing a
        //     payload to decide whether a DELETE is safe is not a decision this lane should make;
        //   * a queued job is transient application state the worker consumes; it is not ledger
        //     data and a reversal has no business deleting one;
        //   * and leaving it out of `tables` also leaves it out of the ARMING set, which is the
        //     point: raising `jobs`.AUTO_INCREMENT to the floor would drop every job City
        //     Travelers' own dev app queues from then on into the reserved band, for no benefit.
        'ignored_growth_tables' => [
            'jobs',
        ],

        // ── Tables with NO `company_id` column ───────────────────────────────────────────────
        //
        // For these the id band is not a belt-and-braces extra, it is the ENTIRE scoping
        // mechanism, and it is why the floor is load-bearing rather than cosmetic.
        //
        // `agents` is on this list and was NOT on the manifest's equivalent: CT's `agents` table
        // has no `company_id` column at all (measured — it scopes via `user_id`/`account_id`),
        // which the manifest's §2 assumed otherwise.
        'global_tables' => [
            'companies',
            'users',
            'agents',
            'suppliers',
        ],

        // ── Tables with NO `id` column at all ────────────────────────────────────────────────
        //
        // The Spatie permission pivots. The reversal manifest's `DELETE … WHERE id BETWEEN` CANNOT
        // REACH THESE — they have no `id` — which is a real gap in CD0-REVERSAL-MANIFEST §3 as
        // written, and it is closed here: each is reversed through a FK that IS in the band.
        // `model_has_roles` grew by 1 row on the measured fence run (the company owner's role
        // assignment); `role_has_permissions` grows by one row per (new role x existing
        // permission) pair on the dev database, where 8 permissions exist — on the empty fence
        // there are none, so that table stays at 0 there and the dev figure is a projection, said
        // so explicitly rather than reported as measured.
        // ── Tables the unload may NOT delete from ────────────────────────────────────────────
        //
        // MEASURED, not inferred. `accounting_audit_log` carries a BEFORE DELETE trigger
        // (`accounting_audit_log_no_delete`, migration
        // 2026_08_30_150001_p25f_add_append_only_triggers_to_accounting_audit_log) that SIGNALs
        // SQLSTATE 45000 'accounting_audit_log is append-only: rows may never be deleted.' unless
        // the session variable `@accounting_audit_log_allow_delete` is set to 1. A `DELETE … WHERE
        // id >= 10000001` against it was run on the fence and the row survived.
        //
        // This is a HARD LIMIT ON REVERSIBILITY that CD0-REVERSAL-MANIFEST-2026-09-16 does not
        // mention: the manifest's §3 cannot make this table's rows go away, and a reversal that
        // claimed "0 residual rows" while these remained would be reporting something untrue.
        // `legacy:unload` therefore leaves them, names them, and counts them — and will remove them
        // only under an explicit `--purge-audit-log`, which sets the same escape-hatch variable
        // `accounting:audit-log:purge` already uses for retention. The rows are inside the reserved
        // band and carry `company_id`, so they are identifiable either way.
        'unload_exempt_tables' => [
            'accounting_audit_log' => 'append-only: BEFORE DELETE trigger accounting_audit_log_no_delete refuses the delete unless @accounting_audit_log_allow_delete = 1 (use --purge-audit-log)',
        ],

        // Tables with NO `id` column at all -- the Spatie permission pivots. They cannot be
        // reached by id, so they are reversed through a FK that points at a row this load OWNS
        // (per LegacyRowLedger), never through an id range.
        //
        // ROUND 2 CORRECTION. Round 1 deleted `model_has_roles` by `model_id BETWEEN band`, which
        // (a) is the same unsound band-as-ownership reasoning finding F1 is about, and (b) left a
        // Como role attached to an out-of-band user silently in place -- a row pointing at a role
        // that was about to be deleted. Each clause below names an OWNING TABLE instead, and the
        // delete is the UNION of the clauses: a pivot row goes when EITHER side of it is a row
        // this load owns, because either way it is about to dangle.
        'pivot_tables' => [
            'model_has_roles' => [
                ['column' => 'role_id', 'owner' => 'roles'],
                ['column' => 'model_id', 'owner' => 'users', 'where' => ['model_type' => 'App\\Models\\User']],
            ],
            'model_has_permissions' => [
                ['column' => 'model_id', 'owner' => 'users', 'where' => ['model_type' => 'App\\Models\\User']],
            ],
            'role_has_permissions' => [
                ['column' => 'role_id', 'owner' => 'roles'],
            ],
        ],
    ],
];
