<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class CoaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public static function run(int $companyId = 1): void
    {
        $accounts = [
            // Top-Level (Level 1)
            ['code' => '1000', 'name' => 'Assets',     'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2000', 'name' => 'Liabilities', 'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3000', 'name' => 'Equity',     'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '4000', 'name' => 'Income',     'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5000', 'name' => 'Expenses',   'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            // Assets (Level 2 and deeper)
            ['code' => '1100', 'name' => 'Cash In Hand', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1110', 'name' => 'Petty Cash', 'level' => 3, 'parent' => 'Cash In Hand', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1120', 'name' => 'Receipt Voucher Cash', 'level' => 3, 'parent' => 'Cash In Hand', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1200', 'name' => 'Bank Accounts', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1201', 'name' => 'Kuwait International Bank', 'level' => 3, 'parent' => 'Bank Accounts', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1204', 'name' => 'Ahli United Bank Kuwait', 'level' => 3, 'parent' => 'Bank Accounts', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            // W5.L: new leaf for the CHEQUES_IN_HAND purpose code (config
            // 'accounting.purpose_codes') — a peer of 'Cash In Hand' (1100) / 'Bank Accounts'
            // (1200) directly under 'Assets', not a child of either: the PDC-received asset a
            // cheque-instrument RV debits until it clears (w5-brief.md §W5.R). See
            // SystemAccountsSeeder's mapByCode('CHEQUES_IN_HAND', ..., '1215', ...) mapping.
            ['code' => '1215', 'name' => 'Cheques In Hand', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1300', 'name' => 'Payment Gateway', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1350', 'name' => 'Accounts Receivable', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1351', 'name' => 'Clients', 'level' => 3, 'parent' => 'Accounts Receivable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1400', 'name' => 'Supplier Advances/Prepayments', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1410', 'name' => 'Prepaid Flights', 'level' => 3, 'parent' => 'Supplier Advances/Prepayments', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1420', 'name' => 'Prepaid Hotels', 'level' => 3, 'parent' => 'Supplier Advances/Prepayments', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6): the PREPAID_SUPPLIER_COST purpose
            // code's target leaf — the asset a PRINCIPAL-basis `at_travel` service debits at sale
            // INSTEAD OF SERVICE_COST, released to SERVICE_COST/{type} on the travel/check-in date
            // by `accounting:recognize-revenue`. A peer of Prepaid Flights (1410)/Prepaid Hotels
            // (1420) above but deliberately GENERIC across every principal-basis service type
            // (tour/cruise/car/event — this build's `at_travel` defaults), not per-type like those
            // two pre-existing, still-unused leaves — see config('accounting.purpose_codes')'s own
            // comment on this purpose code for the full reasoning.
            // CODE 1431, NOT 1430 (COA BLOCKER FIX, 2026-08-31): a real-data audit against
            // akeed_verify_snapshot (production-shaped, all 3 companies) found every company's
            // chart already carries a REAL, in-use account at code 1430 — "Unbilled Supplier
            // Cost", also a direct child of this same "Supplier Advances/Prepayments" (1400)
            // parent — that predates this leaf and must not be touched or renumbered.
            // EnsureSystemLeaves::processCompany() runs every CORE leaf in ONE transaction with
            // this one last, so the code collision (createSystemLeaf()'s own codeOwner check)
            // aborted the ENTIRE company run, not just this leaf. Owner-ratified decision: keep
            // the real "Unbilled Supplier Cost" account untouched and move OUR leaf to the next
            // free code under the same parent — 1431 was verified free (no account of any
            // company owns it, and no CoaSeeder/EnsureSystemLeaves/SystemAccountsSeeder row
            // claims it) both in akeed_verify_snapshot and in this code-space.
            ['code' => '1431', 'name' => 'Prepaid Supplier Cost', 'level' => 3, 'parent' => 'Supplier Advances/Prepayments', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            // CT-A3 wave 1 (owner ruling R-CT3, 2026-09-09) — the UNBILLED_SUPPLIER_COST purpose
            // code's target leaf: the asset TaskIssuancePayableService debits when a task's
            // supplier cost becomes a guaranteed liability BEFORE any invoice exists, against a
            // credit to SERVICE_PAYABLE. Owner: "anything comes into task where its been
            // issued/vouchered and needs to be paid to supplier we want to automatically add it
            // to the right account so we know how much we need to pay regardless of them being
            // invoiced". Released by reversing the whole accrual document once the sale posts its
            // own cost pair (see that service's docblock).
            //
            // CODE 1430 AND THIS NAME ARE NOT A NEW INVENTION: every real chart already carries
            // this exact account under this exact parent (see the 1431 comment immediately above,
            // and CT-A1 §3.1 measuring KWD 660,888.716 sitting on it). It was simply never in
            // CoaSeeder, so a FRESHLY seeded company had no leaf for the purpose code to resolve.
            // Account::updateOrCreate() keys on (name, parent_id, company_id, root_id), so on an
            // existing chart this row ADOPTS the account already there rather than minting a
            // second one — the 1430 collision the comment above describes cannot recur, because
            // this row deliberately claims the SAME identity.
            ['code' => '1430', 'name' => 'Unbilled Supplier Cost', 'level' => 3, 'parent' => 'Supplier Advances/Prepayments', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1500', 'name' => 'Stock Assets', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1510', 'name' => 'Stock In Hand', 'level' => 3, 'parent' => 'Stock Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1600', 'name' => 'Tax Assets', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1700', 'name' => 'Loans and Advances (Assets)', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1710', 'name' => 'Employee Advances', 'level' => 3, 'parent' => 'Loans and Advances (Assets)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1720', 'name' => 'Securities and Deposits', 'level' => 3, 'parent' => 'Loans and Advances (Assets)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1721', 'name' => 'Earnest Money', 'level' => 4, 'parent' => 'Securities and Deposits', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1800', 'name' => 'Fixed Assets', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1810', 'name' => 'Capital Equipments', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1820', 'name' => 'Electronic Equipments', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1830', 'name' => 'Furniture and Fixtures', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1840', 'name' => 'Office Equipments', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1850', 'name' => 'Plants and Machineries', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1860', 'name' => 'Buildings', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1870', 'name' => 'Softwares', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1880', 'name' => 'Accumulated Depreciation', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            // accounting-builds T0a (L7): per-class accumulated-depreciation contras, minted as
            // CHILDREN of 1880 (converting it from a leaf to a group by having children) --
            // guarded: SystemAccountsSeeder::resolveFixedAssetClasses() and EnsureSystemLeaves both
            // refuse (report a gap, mint nothing) for a company whose 1880 already carries
            // journal_entries lines, per L7. Confirmed clear on akeed_verify_snapshot (T13/T0a
            // dry-run, 2026-09-02: 0 journal lines on account code 1880 across all 3 snapshot
            // companies) -- the sibling-under-1800 fallback (Q3) was NOT needed. One contra per
            // cost leaf 1810-1870 directly above, same order, config('accounting.purpose_codes.
            // fixed_asset_classes') is the single source of truth for the code/class pairing.
            ['code' => '1881', 'name' => 'Accumulated Depreciation — Capital Equipments', 'level' => 4, 'parent' => 'Accumulated Depreciation', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1882', 'name' => 'Accumulated Depreciation — Electronic Equipments', 'level' => 4, 'parent' => 'Accumulated Depreciation', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1883', 'name' => 'Accumulated Depreciation — Furniture and Fixtures', 'level' => 4, 'parent' => 'Accumulated Depreciation', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1884', 'name' => 'Accumulated Depreciation — Office Equipments', 'level' => 4, 'parent' => 'Accumulated Depreciation', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1885', 'name' => 'Accumulated Depreciation — Plants and Machineries', 'level' => 4, 'parent' => 'Accumulated Depreciation', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1886', 'name' => 'Accumulated Depreciation — Buildings', 'level' => 4, 'parent' => 'Accumulated Depreciation', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1887', 'name' => 'Accumulated Depreciation — Softwares', 'level' => 4, 'parent' => 'Accumulated Depreciation', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1890', 'name' => 'CWIP Account (Construction Work in Progress)', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1900', 'name' => 'Investments', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '1950', 'name' => 'Temporary Accounts', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1951', 'name' => 'Temporary Opening', 'level' => 3, 'parent' => 'Temporary Accounts', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            // P2-exit purpose-mapping gap fix (residual register §8 pre-flip checklist, 2026-09-01):
            // the SUSPENSE purpose code (config('accounting.purpose_codes.global')) had NO leaf
            // anywhere in the seed COA -- SystemAccountsSeeder::resolveControls()'s own
            // mapByName($companyId, ..., 'SUSPENSE', null, 'Suspense') call was permanently
            // unreachable for every company, old or new, since 'Suspense' never existed to find.
            // A peer of 'Temporary Opening' (1951) under the existing 'Temporary Accounts' (1950)
            // pool -- the pool's own existing semantic ("temporary/clearing" balances) fits a
            // suspense account exactly, and re-uses an anchor every CoaSeeder chart, old or new,
            // already seeds rather than minting a new top-level group for one leaf.
            // CODE 1953, NOT 1952: 1952 is config('accounting.period_close.airline_memo_control_code')
            // -- a DIFFERENT, already-reserved-but-not-yet-seeded code (see that config's own
            // docblock) -- 1953 is the next free code in this family, verified unused by any
            // account of any company in akeed_verify_snapshot (companies 1/2/3) and unused
            // anywhere in this code-space. See App\Console\Commands\EnsureSystemLeaves::LEAVES
            // (purposeCode SUSPENSE) for the matching backfill on an EXISTING company, and
            // SystemAccountsSeeder::resolveControls()'s own SUSPENSE mapByName() call this leaf
            // now finally resolves.
            ['code' => '1953', 'name' => 'Suspense', 'level' => 3, 'parent' => 'Temporary Accounts', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            // Liabilities (Level 2 and deeper)
            ['code' => '2100', 'name' => 'Accounts Payable', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2110', 'name' => 'Creditors', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2120', 'name' => 'Suppliers (Flights)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2130', 'name' => 'Suppliers (Hotels)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2121', 'name' => 'Suppliers (Visas)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2122', 'name' => 'Suppliers (Insurance)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2123', 'name' => 'Suppliers (Tour)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2124', 'name' => 'Suppliers (Cruise)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2125', 'name' => 'Suppliers (Car)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2126', 'name' => 'Suppliers (Rail)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2127', 'name' => 'Suppliers (Esim)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2128', 'name' => 'Suppliers (Event)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2129', 'name' => 'Suppliers (Lounge)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2130', 'name' => 'Suppliers (Ferry)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2200', 'name' => 'Accrued Expenses', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2210', 'name' => 'Commissions (Agents)', 'level' => 3, 'parent' => 'Accrued Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2220', 'name' => 'Expenses (General)', 'level' => 3, 'parent' => 'Accrued Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2230', 'name' => 'Agent Profit Payable', 'level' => 3, 'parent' => 'Accrued Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            // USER DECISION 2026-08-27: new leaf for the SALARY_PAYABLE purpose code (config
            // 'accounting.purpose_codes') — AgentController::update()'s salary-accrual credit leg
            // (previously kept on PAYABLE_CONTROL/2110 pending a user-assigned account name). See
            // SystemAccountsSeeder's mapByChain('Salaries & Wages Payable', [...]) mapping.
            // USER DECISION 2026-08-27 (residual 6, W2.1): code is 2201, NOT 2240. 2240 is taken
            // on City Travelers by an auto-numbered agent-profit leaf — AgentController::update()'s
            // max(sibling code)+1 generator creates children of "Agent Profit Payable" (2230)
            // starting at 2231 and increasing by one per agent (2231, 2232, ... 2240 for the 10th
            // agent), so any code in that increasing range can eventually collide. 2201 sits BELOW
            // "Accrued Expenses"'s own children (2210/2220/2230/2240...) and is never touched by
            // that generator, which only ever grows upward from 2230 — permanently unreachable by
            // it regardless of how many agents a company has.
            ['code' => '2201', 'name' => 'Salaries & Wages Payable', 'level' => 3, 'parent' => 'Accrued Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            // W5.L: new leaf for the CHEQUES_ISSUED_NOT_CLEARED purpose code (config
            // 'accounting.purpose_codes') — a direct child of 'Liabilities' (not nested inside
            // 'Accrued Expenses', which auto-numbers agent-profit leaves upward from 2230 the same
            // way 2201's own comment above already documents avoiding): the mirror liability a
            // cheque-instrument PV credits until it clears (w5-brief.md §W5.P). See
            // SystemAccountsSeeder's mapByCode('CHEQUES_ISSUED_NOT_CLEARED', ..., '2215', ...)
            // mapping.
            ['code' => '2215', 'name' => 'Cheques Issued Not Cleared', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '2300', 'name' => 'Stock Liabilities', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2310', 'name' => 'Stock Received But Not Billed', 'level' => 3, 'parent' => 'Stock Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2320', 'name' => 'Asset Received But Not Billed', 'level' => 3, 'parent' => 'Stock Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '2400', 'name' => 'Duties and Taxes', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2410', 'name' => 'TDS Payable', 'level' => 3, 'parent' => 'Duties and Taxes', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2420', 'name' => 'GST Payable', 'level' => 3, 'parent' => 'Duties and Taxes', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '2500', 'name' => 'Loans (Liabilities)', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2510', 'name' => 'Secured Loans', 'level' => 3, 'parent' => 'Loans (Liabilities)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2520', 'name' => 'Unsecured Loans', 'level' => 3, 'parent' => 'Loans (Liabilities)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2530', 'name' => 'Bank Overdraft Account', 'level' => 3, 'parent' => 'Loans (Liabilities)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '2600', 'name' => 'Refund Payable', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2610', 'name' => 'Clients', 'level' => 3, 'parent' => 'Refund Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '2620', 'name' => 'Advances', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2630', 'name' => 'Client', 'level' => 3, 'parent' => 'Advances', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2631', 'name' => 'Cash', 'level' => 4, 'parent' => 'Client', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2632', 'name' => 'Payment Gateway', 'level' => 4, 'parent' => 'Client', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6): the DEFERRED_REVENUE purpose code's
            // target leaf — the liability an `at_travel` service credits at sale INSTEAD OF
            // SERVICE_REVENUE, released to SERVICE_REVENUE/{type} on the travel/check-in date by
            // `accounting:recognize-revenue`. A direct child of 'Liabilities' (its own level-2
            // group, code chosen as the next free slot in the 2600 family after 'Refund Payable'
            // 2600/2610 and 'Advances' 2620/2630-2632 above) rather than nested under either of
            // those two existing 2600-family groups — a deferred sale is neither a refund payable
            // nor a client advance, and both existing groups' own children (2610/2631/2632) are
            // resolved by other purpose codes (CLIENT_ADVANCE at 2632 — see
            // SystemAccountsSeeder::resolveControls()) this leaf must stay distinct from.
            ['code' => '2650', 'name' => 'Deferred Revenue', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            // Equity (Level 2)
            ['code' => '3100', 'name' => 'Capital Stock', 'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3200', 'name' => 'Dividends Paid', 'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3300', 'name' => 'Opening Balance Equity', 'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3400', 'name' => 'Retained Earnings', 'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            // Income (Level 2 and deeper)
            ['code' => '4100', 'name' => 'Direct Income', 'level' => 2, 'parent' => 'Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4110', 'name' => 'Flight Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4120', 'name' => 'Hotel Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W3-prereq lane A (USER RULING 2026-08-27): the other 10 task types' own dedicated
            // SERVICE_REVENUE leaves, same '{Type} Booking Revenue' name pattern as Flight/Hotel
            // above. Numbered following the SAME convention the sibling Cost family below already
            // uses one leaf family over (Flights Cost=5110, Hotels Cost=5120, the other ten =
            // 5111-5119 then 5121 for Ferry, spilling PAST Hotels' own 5120 slot rather than
            // wrapping back onto it) — NOT the Suppliers/Payable family's convention two leaf
            // families over, which wraps Ferry (2130) back onto Hotels' own code and is the
            // known, explicitly deferred CoaSeeder duplicate-code bug (see
            // SystemAccountsSeeder::mapByCode()'s own docblock and
            // AccountingInvariants::assertNoDuplicateAccountCodes()'s tolerated-pair comment) —
            // deliberately not repeated here.
            ['code' => '4111', 'name' => 'Visa Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4112', 'name' => 'Insurance Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4113', 'name' => 'Tour Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4114', 'name' => 'Cruise Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4115', 'name' => 'Car Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4116', 'name' => 'Rail Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4117', 'name' => 'Esim Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4118', 'name' => 'Event Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4119', 'name' => 'Lounge Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4121', 'name' => 'Ferry Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4130', 'name' => 'Commission & Service Fee Income', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // USER DECISION 2026-08-27: was a duplicate '4130' (same code as its own parent) —
            // renumbered to 4131. Still the same leaf, same parent, same position.
            ['code' => '4131', 'name' => 'Gateway Fee Recovery', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // USER DECISION 2026-08-27: new leaf for the MARKUP_INCOME purpose code (config
            // 'accounting.purpose_codes') — the agency's margin on a task-based sale, distinct
            // from the full-sell-value SERVICE_REVENUE leaves. See SystemAccountsSeeder's
            // mapByChain('Markup Income', [...]) mapping and config/accounting.php's
            // MARKUP_INCOME docblock.
            ['code' => '4132', 'name' => 'Markup Income', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W4.0: new leaf for the SERVICE_FEE_INCOME purpose code (config
            // 'accounting.purpose_codes') — the flat `invoices.invoice_charge` add-on, routed
            // through the seam as an INV/FEE document. Same parent ("Commission & Service Fee
            // Income", 4130) as 4131/4132, next free code in that family.
            ['code' => '4133', 'name' => 'Service Fee Income', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W4.R (w4-brief.md §4b): the client-facing recharge of an airline/consolidator
            // penalty on a refund ("Cr penalty pass-through recovery"), separate from the refund
            // fee income (4133) charged on the same recharge document. Same parent family
            // (4131/4132/4133) as the other Commission & Service Fee Income leaves.
            ['code' => '4136', 'name' => 'Penalty Pass-Through Recovery', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W6.S "New leaves" (w6-brief.md): void-with-fee's client-facing fee income (DBN Dr
            // AR / Cr this leaf) -- same parent family (4131/4132/4133/4136) as the other
            // Commission & Service Fee Income leaves. Next free codes in the family (4134/4135
            // were unclaimed; 4137 is reserved by W6.C's own Supplier Charge Recharge Income leaf,
            // NOT this one -- see w6-brief.md's own "Code correction applied here" note).
            ['code' => '4134', 'name' => 'Void Fee Income', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W6.S "New leaves": reissue's client-facing fee income (DBN Dr AR / Cr this leaf),
            // distinct from Void Fee Income above so a P&L reader can tell the two apart.
            ['code' => '4135', 'name' => 'Reissue Fee Income', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W6.C (w6-brief.md "W6.C — Supplier-side charges"): the client recharge of a
            // supplier-charge-rule fee (SUPPLIER_CHARGE_RECHARGE_INCOME purpose code). Same parent
            // family (4131/4132/4133/4134/4135/4136) as the other Commission & Service Fee Income
            // leaves -- 4137 is the first free code in that family (4134/4135 are W6.S's own Void/
            // Reissue Fee Income; supplier-charges-design.md's own Table 4 text originally named
            // this leaf '4134', superseded per w6-brief.md's own "Code correction applied here").
            ['code' => '4137', 'name' => 'Supplier Charge Recharge Income', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W6.I (w6-brief.md "Importer contract" item 2): EMD ancillary sale line, posted on
            // the parent ticket's existing invoice via TaskStatusService::postEmdAncillary().
            // Next free code in the 4131/4132/4133/4134/4135/4136/4137 family.
            ['code' => '4138', 'name' => 'EMD Ancillary Revenue', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // accounting-builds T0a (L3): new leaf for the FX_GAIN_REALISED purpose code (config
            // 'accounting.purpose_codes') -- the credit leg RealisedFxService (Lane A / T1) posts
            // for a debit-sourced apply with D<0 or a credit-sourced apply with D>0. Same parent
            // family (4131-4138) as the other Commission & Service Fee Income leaves -- 4139 is
            // the next free code in that family.
            ['code' => '4139', 'name' => 'Realised Exchange Gain', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // accounting-builds T0a (L7): new leaf for the ASSET_DISPOSAL_GAIN purpose code --
            // the balancing credit FixedAssetService::dispose() (Lane B / T4) posts when
            // proceeds > NBV. CODE 4141, NOT 4140 (COA BLOCKER, same class of collision as the
            // 1430->1431 fix above): a real-data audit against akeed_verify_snapshot (all 3
            // companies, 2026-09-02) found code 4140 already occupied by a REAL, pre-existing
            // 'Sales' account (level 3, direct child of 'Direct Income', NOT a child of
            // 'Commission & Service Fee Income' -- a peer of 4130, not one of its family) --
            // predates this wave and must not be touched or renumbered. 4141 was verified free
            // on the same bench and unused anywhere in this code-space; same parent chain as
            // 4139 directly above (a family member, not a peer of 'Sales').
            ['code' => '4141', 'name' => 'Gain on Asset Disposal', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4140', 'name' => 'Sales', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4150', 'name' => 'Services (other)', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4170', 'name' => 'Loss Recovery Income', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            ['code' => '4200', 'name' => 'Indirect Income', 'level' => 2, 'parent' => 'Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            // Expenses (Level 2 and deeper)
            ['code' => '5100', 'name' => 'Direct Expenses (Cost of Sales)', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5110', 'name' => 'Flights Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5120', 'name' => 'Hotels Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5111', 'name' => 'Visa Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5112', 'name' => 'Insurance Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5113', 'name' => 'Tour Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5114', 'name' => 'Cruise Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5115', 'name' => 'Car Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5116', 'name' => 'Rail Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5117', 'name' => 'Esim Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5118', 'name' => 'Event Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5119', 'name' => 'Lounge Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5121', 'name' => 'Ferry Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5130', 'name' => 'Commissions Expense (Agents)', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // CT-A3 wave 2 (W2-3, CT-F11) - the SUPPLIER_REFUND_LOSS purpose code's target leaf.
            // When a booking is refunded to the client but the supplier does NOT give the cost
            // back, that cost is no longer the cost OF A SALE (the sale has been reversed) - it is
            // a loss on a refunded booking, and it belongs on its own line where the owner can see
            // it, not buried in COGS and not silently erased as the pre-wave-2 refund feeder did.
            //
            // CODE 5131, NOT 5126. The 5122-5129 run under this parent is full, and 5126 in
            // particular is RESERVED for P5.13's not-yet-built 'Loss Recovery (Agents)' leaf --
            // reserved in PostingService's resolved-gap #9 note, in
            // InvoiceController::postAgentLossRecoveryHook()'s docblock, in this seeder's own
            // 5125/5127 comments, and guarded by SystemAccountsSeederVoucherAnchorsTest's
            // "5126 is reserved for P5.13 and must remain unused by this wave". The first cut of
            // this wave took 5126 and that test caught it. 5131 is the next free code after
            // Commissions Expense (Agents) 5130 and before Payment Gateway Charges 5140.
            ['code' => '5131', 'name' => 'Supplier Refund Loss', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5140', 'name' => 'Payment Gateway Charges', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5141', 'name' => 'TAP Charges', 'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5142', 'name' => 'MyFatoorah Charges', 'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5143', 'name' => 'Hesabe Charges', 'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // PATTERN NAME (residual 1, W2.1, USER-AUTHORISED): following the existing 'TAP
            // Charges' (5141) / 'MyFatoorah Charges' (5142) / 'Hesabe Charges' (5143) convention
            // under 'Payment Gateway Charges' (5140) — the two remaining gateways
            // config('accounting.purpose_codes.gateways') names (KNET, uPayment) previously had no
            // dedicated child here, so SystemAccountsSeeder::resolveGatewayFeeExpense() could never
            // map GATEWAY_FEE_EXPENSE_KNET / _UPAYMENT once the pool already had ANY child (see
            // that method's own docblock) — an engine-ON KNET/uPayment invoice payment carrying a
            // fee threw UnmappedPurposeException where HEAD succeeded. Fixed going forward for
            // every new company by seeding these two leaves like the other three gateways;
            // EnsureSystemLeaves backfills them for an existing company.
            ['code' => '5144', 'name' => 'KNET Charges', 'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5145', 'name' => 'uPayment Charges', 'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5160', 'name' => 'Agent Salaries', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5122', 'name' => 'Agent Bonus', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5123', 'name' => 'Fee Loss Provision', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W4.R (w4-brief.md §4c): the penalty amount WE incur on a refund (airline/consolidator
            // penalty deducted from the supplier's refund) — a real cost distinct from the reversed
            // COGS on the same line. Never 5126 (reserved for P5.13's not-yet-built agent-loss-
            // recovery leaf per InvoiceController::postAgentLossRecoveryHook()'s own docblock).
            ['code' => '5124', 'name' => 'Refund Penalty Cost', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W4.R (w4-brief.md §4e, target-spec.md §B "three-event airline clawback model", step
            // (a) — "always: Dr 5125 Airline Refund Clawback / Cr airline payable"). Unconditional,
            // independent of bearer; the bearer-gated agent recovery leg (step b) stays behind the
            // SAME P5.13 flag InvoiceController::postAgentLossRecoveryHook() gates, per the brief.
            ['code' => '5125', 'name' => 'Airline Refund Clawback', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W5.L: new leaf for the CASH_OVER_SHORT purpose code (config
            // 'accounting.purpose_codes') — the variance line a cash-drawer reconciliation posts
            // (deferred build, P5.18's "daily cash close"; this wave only registers the anchor).
            // Never 5126 (reserved for P5.13's not-yet-built agent-loss-recovery leaf — see
            // PostingService's own resolved-gap #9 and InvoiceController::
            // postAgentLossRecoveryHook()'s docblock). See SystemAccountsSeeder's
            // mapByCode('CASH_OVER_SHORT', ..., '5127', ...) mapping.
            ['code' => '5127', 'name' => 'Cash Over/Short', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W6.C (w6-brief.md "W6.C — Supplier-side charges"; supplier-charges-design.md Table
            // 4): the agent-basis cost leg of a supplier-charge-rule fee (SUPPLIER_CHARGE_EXPENSE
            // purpose code) -- agent-basis sales have no existing SERVICE_COST leg for this fee
            // family to join, so it gets its own leaf. Next free direct child of 'Direct Expenses
            // (Cost of Sales)' after 5121-5125/5127 -- NOT 5126, reserved for P5.13's not-yet-built
            // agent-loss-recovery leaf (same reservation CASH_OVER_SHORT's own comment above notes).
            ['code' => '5128', 'name' => 'Supplier Fees & Surcharges', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // CT-A3 E5 (CT-F37) — the COST_OF_SALES_CONTROL purpose code's target leaf: the ONE
            // cost-of-sales control account an unmapped per-service SERVICE_COST/{type} falls back
            // to (AccountResolver::resolveViaFallback()). This is the "single COGS leaf" the owner
            // ruling names — deliberately ONE account, not the 13 per-service control leaves CT-A2
            // had to hand-create just to make its replay run and recorded as scaffolding, "not a
            // recommendation". A company that DOES map SERVICE_COST per service type keeps using
            // its own leaves; nothing here changes that path.
            // CODE 5129: the next free code under this parent (5110-5128 are all taken above),
            // verified against CoaSeeder, EnsureSystemLeaves and SystemAccountsSeeder.
            ['code' => '5129', 'name' => 'Cost of Sales Control', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            ['code' => '5150', 'name' => 'Stock Expenses', 'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5151', 'name' => 'Cost of Goods Sold', 'level' => 4, 'parent' => 'Stock Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5152', 'name' => 'Expenses Included in Asset Valuation', 'level' => 4, 'parent' => 'Stock Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5159', 'name' => 'Stock Adjustment', 'level' => 4, 'parent' => 'Stock Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            ['code' => '5200', 'name' => 'Indirect Expenses (Operating Expenses)', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5201', 'name' => 'Administrative Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5202', 'name' => 'Commission on Sales', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5203', 'name' => 'Depreciation', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5204', 'name' => 'Entertainment Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5205', 'name' => 'Freight and Forwarding Charges', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5206', 'name' => 'Legal Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5207', 'name' => 'Marketing Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5208', 'name' => 'Office Maintenance Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5209', 'name' => 'Office Rent', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5210', 'name' => 'Postal Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5211', 'name' => 'Print and Stationery', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5212', 'name' => 'Round Off', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5213', 'name' => 'Salary', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5214', 'name' => 'Sales Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5215', 'name' => 'Telephone Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5216', 'name' => 'Travel Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5217', 'name' => 'Utility Expenses', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5218', 'name' => 'Write Off', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5219', 'name' => 'Exchange Gain/Loss', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5220', 'name' => 'Gain/Loss on Asset Disposal', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5221', 'name' => 'Company Loss on Sales', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            // W5.L: new leaf for the BANK_CHARGES_EXPENSE purpose code (config
            // 'accounting.purpose_codes') — w5-brief.md's own text expected an EXISTING leaf
            // "under the 5140 family"; a repo-wide search (CoaSeeder, every migration, every
            // controller) found none anywhere named 'Bank Charges' or equivalent, so a new leaf is
            // created. NOT placed under 'Payment Gateway Charges' (5140, first attempt): proven by
            // execution (EnsureSystemLeavesTest's own R-4 regression tests) that
            // SystemAccountsSeeder::resolveGatewayFeeExpense()'s "neutral (non-gateway-named) leaf
            // child of the pool" fallback would silently adopt a Bank Charges sibling there as the
            // fallback target for KNET/uPayment/any gateway missing its own dedicated child —
            // exactly the wrong-account-guessing that method's own docblock says it must never do.
            // Placed instead under 'Indirect Expenses (Operating Expenses)' (5200) — an ordinary
            // administrative/operating expense, which a manual bank-transfer fee genuinely is, and
            // structurally isolated from the gateway-fee-expense resolver entirely. See
            // SystemAccountsSeeder's mapByCode('BANK_CHARGES_EXPENSE', ..., '5222', ...) mapping.
            ['code' => '5222', 'name' => 'Bank Charges', 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            ['code' => '5300', 'name' => 'Refund Clearing / Payable Allocation', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
        ];

        $parentMap = [];
        foreach ($accounts as $account) {
            $parentId = $account['parent'] && isset($parentMap[$account['parent']]) ? $parentMap[$account['parent']]->id : null;

            // Determine root_id
            $rootId = $parentId ? $parentMap[$account['parent']]->root_id ?? $parentMap[$account['parent']]->id : null;

            $newAccount = Account::updateOrCreate([
                'name' => $account['name'],
                'parent_id' => $parentId,
                'company_id' => $companyId,
                'parent_id' => $parentId,
                'root_id' => $rootId,
            ], [
                'serial_number' => null,
                'account_type' => $account['account_type'],
                'report_type' => $account['report_type'],
                'level' => $account['level'],
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'branch_id' => null,
                'agent_id' => null,
                'client_id' => null,
                'supplier_id' => null,
                'reference_id' => null,
                'code' => $account['code'],
            ]);

            $parentMap[$account['name']] = $newAccount;
        }
    }
}
