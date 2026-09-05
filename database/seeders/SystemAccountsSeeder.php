<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SystemAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * For every existing company, map every purpose_code in the P1 vocabulary
 * (config('accounting.purpose_codes')) to its leaf Account and upsert a
 * system_accounts row. This is what lets P2 resolve accounts by role instead
 * of by name string (R7.3 / BUG-H6).
 *
 * Resolution is always structural, never a guess:
 *   - RECEIVABLE_CONTROL / PAYABLE_CONTROL are resolved by NAME + ANCESTOR
 *     CHAIN (not bare name — file 11 warns two accounts are both named
 *     "Clients": one pooled under Accounts Receivable/Assets, one under
 *     Refund Payable/Liabilities. Only the Accounts Receivable one is the
 *     receivable control).
 *   - RETAINED_EARNINGS / FX_GAIN_LOSS are resolved by the CODE file 11 gives
 *     verbatim (3400 / 5219).
 *   - GATEWAY_CLEARING_* is resolved under the "Payment Gateway" account
 *     rooted under Assets (there is also an unrelated "Payment Gateway" leaf
 *     rooted under Liabilities/Advances/Client in the seed COA — excluded by
 *     the same root-chain check).
 *   - SERVICE_PAYABLE / SERVICE_COST are resolved by the "Suppliers (X)" /
 *     "X Cost" leaf naming convention CoaSeeder already uses for all 12 task
 *     types.
 *   - SERVICE_REVENUE (W3-prereq lane A, USER RULING 2026-08-27): every one of the 12 task types
 *     config('accounting.purpose_codes.service_types') names now has a dedicated revenue leaf,
 *     '{Type} Booking Revenue' under 'Direct Income' — the SAME name pattern (ucfirst of the task
 *     type, exactly as legacy `InvoiceController::addJournalEntry()` books it) flight/hotel
 *     already used, extended to the other ten (visa, insurance, tour, cruise, car, rail, esim,
 *     event, lounge, ferry). CoaSeeder now seeds all 12 for a fresh company; an EXISTING company
 *     missing one has it backfilled by `accounting:ensure-system-leaves`
 *     (App\Console\Commands\EnsureSystemLeaves) via AccountService::createSystemLeaf(), following
 *     the SAME "highest existing 'Direct Income' sibling code + 5" rule legacy already uses to
 *     auto-create this exact leaf on demand — see that command's own docblock. Resolved here by
 *     mapByName(), same as every other per-service leaf below — a company with more than one
 *     account sharing a revenue leaf's name is SKIPPED and reported (never pooled, never guessed),
 *     exactly like any other mapByName() ambiguity.
 *   - VAT_OUTPUT and SUSPENSE have no dedicated leaf anywhere in the current
 *     COA (Kuwait v1 has no VAT — GCC VAT is P9), so every company reports a
 *     gap for these rather than being mapped onto an unrelated account.
 *
 * Every candidate must also be a true structural leaf
 * (children()->doesntExist()) at the moment the seeder runs — is_group is
 * documented as unreliable (file 11) and is never trusted here.
 *
 * Idempotent: every write is a SystemAccount::updateOrCreate() keyed on the
 * table's unique(company_id, purpose_code, service_type) index. Never
 * guesses and never creates an Account — a company that lacks a mappable
 * leaf for a purpose code is SKIPPED and REPORTED (console + log), not
 * force-mapped.
 */
class SystemAccountsSeeder extends Seeder
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $report = [
        'mapped' => [],
        'skipped' => [],
    ];

    /**
     * account_id => Account, memoized per company run to avoid re-walking the
     * parent chain for every purpose code.
     *
     * @var array<int, Account>
     */
    private array $accountCache = [];

    public function run(): void
    {
        $companies = Company::query()->select(['id', 'name'])->orderBy('id')->get();

        if ($companies->isEmpty()) {
            $this->line('SystemAccountsSeeder: no companies found — nothing to map.');

            return;
        }

        foreach ($companies as $company) {
            $this->accountCache = [];
            $this->seedCompany((int) $company->id, (string) ($company->name ?: "#{$company->id}"));
        }

        $this->printReport();
    }

    private function seedCompany(int $companyId, string $companyLabel): void
    {
        $this->resolveControls($companyId, $companyLabel);
        $this->resolveGatewayClearing($companyId, $companyLabel);
        $this->resolveGatewayFeeExpense($companyId, $companyLabel);
        $this->resolveServices($companyId, $companyLabel);
        $this->resolveAccountingBuildsGlobals($companyId, $companyLabel);
        $this->resolveFixedAssetClasses($companyId, $companyLabel);
    }

    /**
     * accounting-builds T0a: the six new GLOBAL (service_type NULL) purpose codes for realised
     * FX (L3), the dividend sweep (L9), depreciation (L7), and asset disposal (L7). All six
     * resolve by CODE (mapByCode()), same convention as RETAINED_EARNINGS/FX_GAIN_LOSS above --
     * four to EXISTING leaves (5219/3200/5203/5220), two to the NEW 4139/4141 leaves CoaSeeder
     * now seeds (see that seeder's own comment on 4141 for why NOT 4140).
     */
    private function resolveAccountingBuildsGlobals(int $companyId, string $companyLabel): void
    {
        $this->mapByCode($companyId, $companyLabel, 'FX_GAIN_REALISED', null, '4139', 'Realised Exchange Gain');
        $this->mapByCode($companyId, $companyLabel, 'FX_LOSS_REALISED', null, '5219', 'Exchange Gain/Loss');
        $this->mapByCode($companyId, $companyLabel, 'DIVIDENDS_PAID', null, '3200', 'Dividends Paid');
        $this->mapByCode($companyId, $companyLabel, 'DEPRECIATION_EXPENSE', null, '5203', 'Depreciation');
        $this->mapByCode($companyId, $companyLabel, 'ASSET_DISPOSAL_LOSS', null, '5220', 'Gain/Loss on Asset Disposal');
        $this->mapByCode($companyId, $companyLabel, 'ASSET_DISPOSAL_GAIN', null, '4141', 'Gain on Asset Disposal');
    }

    /**
     * accounting-builds T0a (L7): FA_COST_{class} (existing 1810-1870 leaves, always mappable)
     * and FA_ACCUM_DEP_{class} (new 1881-1887 leaves, GUARDED) for every class in
     * config('accounting.purpose_codes.fixed_asset_classes') -- the same key-expansion pattern
     * resolveGatewayClearing()/resolveGatewayFeeExpense() already establish for the 'gateways'
     * map.
     *
     * The guard (fixedAssetContraGuardReason()) is evaluated ONCE per company, before the class
     * loop: if this company's 1880 'Accumulated Depreciation' account already carries
     * journal_entries lines, EVERY FA_ACCUM_DEP_{class} purpose is skipped/reported (never
     * mapped) for this company -- FA_COST_{class} is unaffected (it targets the pre-existing
     * 1810-1870 leaves, never 1880 or its children).
     */
    private function resolveFixedAssetClasses(int $companyId, string $companyLabel): void
    {
        $classes = config('accounting.purpose_codes.fixed_asset_classes', []);

        $guardReason = $this->fixedAssetContraGuardReason($companyId);

        foreach ($classes as $classKey => $spec) {
            $this->mapByCode(
                $companyId,
                $companyLabel,
                "FA_COST_{$classKey}",
                null,
                $spec['cost_code'],
                $spec['label']
            );

            if ($guardReason !== null) {
                $this->skip($companyId, $companyLabel, "FA_ACCUM_DEP_{$classKey}", null, $guardReason);

                continue;
            }

            $this->mapByCode(
                $companyId,
                $companyLabel,
                "FA_ACCUM_DEP_{$classKey}",
                null,
                $spec['accum_dep_code'],
                'Accumulated Depreciation'
            );
        }
    }

    /**
     * L7 guard: null when this company's 1880 'Accumulated Depreciation' account has no posted
     * journal_entries lines (safe to mint/map 1881-1887 as its children) -- a reason string when
     * it does (refuse, report a gap; MP-0a-2). Read-only: never writes, never touches 1880 itself.
     * A company with no 1880 account at all (should never happen on a CoaSeeder-seeded chart) is
     * treated as unguarded -- mapByCode()'s own "no account with code X" skip already reports
     * that gap for FA_ACCUM_DEP_{class} without this method's help.
     */
    private function fixedAssetContraGuardReason(int $companyId): ?string
    {
        $accumulatedDepreciation = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', '1880')
            ->first();

        if ($accumulatedDepreciation === null) {
            return null;
        }

        $hasPostedLines = DB::table('journal_entries')
            ->where('account_id', $accumulatedDepreciation->id)
            ->exists();

        if (! $hasPostedLines) {
            return null;
        }

        return sprintf(
            "account 1880 'Accumulated Depreciation' (id=%d) already carries journal_entries lines for this "
                .'company — refusing to mint per-class contra children under it (L7 guard); fallback is minting '
                .'1881-1887 as siblings under 1800 instead (Q3, owner decision pending).',
            $accumulatedDepreciation->id
        );
    }

    /**
     * The six global (service_type NULL) control purpose codes.
     */
    private function resolveControls(int $companyId, string $companyLabel): void
    {
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'RECEIVABLE_CONTROL',
            null,
            'Clients',
            ['Accounts Receivable', 'Assets']
        );

        $this->mapByChain(
            $companyId,
            $companyLabel,
            'PAYABLE_CONTROL',
            null,
            'Creditors',
            ['Accounts Payable', 'Liabilities']
        );

        $this->mapByCode($companyId, $companyLabel, 'RETAINED_EARNINGS', null, '3400', 'Retained Earnings');
        $this->mapByCode($companyId, $companyLabel, 'FX_GAIN_LOSS', null, '5219', 'Exchange Gain/Loss');

        // No dedicated leaf exists anywhere in the current COA for either of these —
        // Kuwait v1 has no VAT (GCC VAT is P9), and no "Suspense" account is seeded.
        // Report the gap rather than guessing an unrelated account or creating one.
        $this->skip(
            $companyId,
            $companyLabel,
            'VAT_OUTPUT',
            null,
            'no VAT leaf exists in the current chart of accounts (Kuwait v1 has no VAT; GCC VAT is P9)'
        );

        $this->mapByName($companyId, $companyLabel, 'SUSPENSE', null, 'Suspense');

        // Debit leg of AgentController::update()'s salary-posting feeder (R3 seam cutover).
        // 'Agent Salaries' (5160) is unique in the seed COA, same naming-convention resolution
        // as Suspense above. The credit leg reuses PAYABLE_CONTROL (already mapped above) — the
        // feeder never learns which cash/bank account actually pays the agent.
        $this->mapByName($companyId, $companyLabel, 'SALARY_EXPENSE', null, 'Agent Salaries');

        // Third leg of ChatController's task-invoice feeder (R3 seam cutover, W1 "unbalanced by
        // construction" fix — Accounting Gap/11-technical-implementation-plan.md R2.2a): the
        // agency's margin (sell price − supplier cost). W1.3 (USER DECISION 2026-08-27): maps by
        // CHAIN, not by bare name, to the dedicated 'Markup Income' leaf (CoaSeeder code 4132)
        // under 'Commission & Service Fee Income' — NOT the 'Commission & Service Fee Income'
        // account itself, which is a GROUP (it also parents 'Gateway Fee Recovery', code 4131)
        // and would always fail the leaf guard mapByName's own single-name lookup would hit. See
        // config('accounting.purpose_codes')'s own docblock for why this is a distinct code from
        // SERVICE_REVENUE (gross sell value) rather than reusing it.
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'MARKUP_INCOME',
            null,
            'Markup Income',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // Credit leg of AgentController::update()'s salary-posting feeder (W1.3, USER DECISION
        // 2026-08-27) — was temporarily kept on PAYABLE_CONTROL (2110 'Creditors') pending a
        // user-assigned account name (see AgentController::update()'s own P3 comment, now
        // resolved). Maps by CHAIN to the dedicated 'Salaries & Wages Payable' leaf (CoaSeeder
        // code 2201 — USER DECISION 2026-08-27, residual 6: NOT 2240, which collides with
        // AgentController::update()'s own auto-numbered agent-profit leaves under 'Agent Profit
        // Payable', 2230) under 'Accrued Expenses' (2200) — chain-based for the same reason as
        // MARKUP_INCOME above: 'Accrued Expenses' also parents 'Commissions (Agents)' (2210),
        // 'Expenses (General)' (2220), and 'Agent Profit Payable' (2230), so it is a GROUP, not a
        // candidate leaf itself.
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'SALARY_PAYABLE',
            null,
            'Salaries & Wages Payable',
            ['Accrued Expenses', 'Liabilities']
        );

        // Credit leg of CheckMyFatoorahPayments' invoice_id-NULL case (R3 seam cutover, W1.1
        // fix — P1 policy call, 2026-08-26): a MyFatoorah advance with no invoice attached is
        // money held FOR the client, not a receivable. Resolved by CHAIN, not bare name — the
        // seed COA has TWO accounts named 'Payment Gateway' (1300 under Assets, used by
        // GATEWAY_CLEARING_*; 2632 under Liabilities > Advances > Client, this one) — this is
        // exactly the same ambiguity RECEIVABLE_CONTROL's 'Clients' mapping above guards
        // against, and it is the EXACT tree the legacy closure in
        // CheckMyFatoorahPayments::handle() has always walked by name/root_id/parent_id
        // (Account::where('name','like','%Liabilities%') -> where('name','Client')->where
        // ('root_id', ...) -> where('name','Payment Gateway')->where('parent_id', ...)).
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'CLIENT_ADVANCE',
            null,
            'Payment Gateway',
            ['Client', 'Advances', 'Liabilities']
        );

        // W4.0: InvoiceController::addInvoiceChargeJournalEntries() feeder (the flat
        // `invoices.invoice_charge` add-on). Maps by CHAIN, not bare name, to the dedicated
        // 'Service Fee Income' leaf (CoaSeeder code 4133) under 'Commission & Service Fee
        // Income' — same GROUP-not-leaf reasoning as MARKUP_INCOME above (that parent also
        // parents 'Gateway Fee Recovery' 4131 and 'Markup Income' 4132).
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'SERVICE_FEE_INCOME',
            null,
            'Service Fee Income',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // W4.D: InvoiceController::createGatewayFeeRecoveryEntries() feeder (replaces the deleted
        // createGatewayProfitEntries(), which resolved this same leaf by an explicit accountId
        // since Charge has no purpose-code-mappable field pointing at it). Maps by CHAIN, not
        // bare name, to the pre-existing 'Gateway Fee Recovery' leaf (CoaSeeder code 4131) under
        // 'Commission & Service Fee Income' — same GROUP-not-leaf reasoning as MARKUP_INCOME/
        // SERVICE_FEE_INCOME above (that parent also parents 'Markup Income' 4132 and 'Service
        // Fee Income' 4133).
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'GATEWAY_FEE_RECOVERY',
            null,
            'Gateway Fee Recovery',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // W4.R (w4-brief.md §4b): RefundPostingService's recharge-line feeder — the client-facing
        // penalty pass-through. Maps by CHAIN to the dedicated 'Penalty Pass-Through Recovery' leaf
        // (CoaSeeder code 4136) under 'Commission & Service Fee Income' — same GROUP-not-leaf
        // reasoning as MARKUP_INCOME/SERVICE_FEE_INCOME/GATEWAY_FEE_RECOVERY above.
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'PENALTY_PASSTHROUGH_RECOVERY',
            null,
            'Penalty Pass-Through Recovery',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // W6.S "New leaves" (w6-brief.md): VOID's client-facing fee income (DBN Dr AR / Cr this
        // leaf, service_type 'fee') -- maps by CHAIN to the dedicated 'Void Fee Income' leaf
        // (CoaSeeder code 4134) under 'Commission & Service Fee Income', same GROUP-not-leaf
        // reasoning as every other leaf under this parent above. Not consumed by any feeder in
        // this sub-wave (the void kind's own posting is W6.V) -- registered now so the leaf is
        // seeded/verifiable ahead of that feeder landing.
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'VOID_FEE_INCOME',
            null,
            'Void Fee Income',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // W6.S "New leaves": REISSUE's client-facing fee income, same shape as VOID_FEE_INCOME
        // above but its own distinct leaf (CoaSeeder code 4135) so a P&L reader can tell void fees
        // and reissue fees apart. Not consumed by any feeder in this sub-wave (W6.R's own scope).
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'REISSUE_FEE_INCOME',
            null,
            'Reissue Fee Income',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // W6.C (w6-brief.md "W6.C — Supplier-side charges"): the client recharge of a
        // supplier-charge-rule fee. Maps by CHAIN to the dedicated 'Supplier Charge Recharge
        // Income' leaf (CoaSeeder code 4137) under 'Commission & Service Fee Income', same
        // GROUP-not-leaf reasoning as every other leaf under this parent above.
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'SUPPLIER_CHARGE_RECHARGE_INCOME',
            null,
            'Supplier Charge Recharge Income',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // W6.I (w6-brief.md "Importer contract" item 2): the EMD ancillary sale line's income leg
        // (TaskStatusService::postEmdAncillary()). Maps by CHAIN to the dedicated 'EMD Ancillary
        // Revenue' leaf (CoaSeeder code 4138) under 'Commission & Service Fee Income', same
        // GROUP-not-leaf reasoning as every other leaf under this parent above.
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'EMD_ANCILLARY_REVENUE',
            null,
            'EMD Ancillary Revenue',
            ['Commission & Service Fee Income', 'Direct Income', 'Income']
        );

        // W4.R (w4-brief.md §4c): RefundPostingService's supplier-credit-item feeder — the penalty
        // amount the agency actually incurs on a refund. 'Refund Penalty Cost' (CoaSeeder code
        // 5124) is unique in the seed COA, same bare-name resolution as SALARY_EXPENSE above.
        $this->mapByName($companyId, $companyLabel, 'PENALTY_COST_EXPENSE', null, 'Refund Penalty Cost');

        // W4.R (w4-brief.md §4e, target-spec.md §B three-event clawback model, step (a)):
        // RefundPostingService's unconditional clawback feeder. 'Airline Refund Clawback'
        // (CoaSeeder code 5125) is unique in the seed COA, same bare-name resolution as
        // SALARY_EXPENSE/PENALTY_COST_EXPENSE above.
        $this->mapByName($companyId, $companyLabel, 'AIRLINE_CLAWBACK_EXPENSE', null, 'Airline Refund Clawback');

        // W5.L (w5-brief.md §W5.L item 4) — five voucher/instrument anchor purpose codes. See
        // config('accounting.purpose_codes')'s own docblock note on this array for the full
        // rationale behind each leaf choice.
        //
        // CASH_IN_HAND replaces ReceiptVoucherController's/BankPaymentController's existing
        // Account::where('name','Receipt Voucher Cash') lookup (w5-state.md row "Account
        // resolution") — same pre-existing leaf, mapped by CHAIN (not bare name) since 'Cash In
        // Hand' (1100) is itself a GROUP with two children (Petty Cash 1110, Receipt Voucher Cash
        // 1120) — the identical GROUP-not-leaf reasoning MARKUP_INCOME above already documents for
        // 'Commission & Service Fee Income'.
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'CASH_IN_HAND',
            null,
            'Receipt Voucher Cash',
            ['Cash In Hand', 'Assets']
        );

        // CHEQUES_IN_HAND / CHEQUES_ISSUED_NOT_CLEARED / CASH_OVER_SHORT are all NEW, USER-DECIDED
        // codes (CoaSeeder's own comments on rows 1215/2215/5127) — resolved by CODE, same
        // authoritative-code convention RETAINED_EARNINGS/FX_GAIN_LOSS above already use, not by
        // name (a company could rename the leaf without breaking the mapping).
        $this->mapByCode($companyId, $companyLabel, 'CHEQUES_IN_HAND', null, '1215', 'Cheques In Hand');
        $this->mapByCode($companyId, $companyLabel, 'CHEQUES_ISSUED_NOT_CLEARED', null, '2215', 'Cheques Issued Not Cleared');
        $this->mapByCode($companyId, $companyLabel, 'CASH_OVER_SHORT', null, '5127', 'Cash Over/Short');
        // W6.C: agent-basis cost leg of a supplier-charge-rule fee. NEW/USER-DECIDED code (CoaSeeder's
        // own comment on row 5128) -- mapped by CODE like CHEQUES_IN_HAND/CHEQUES_ISSUED_NOT_CLEARED/
        // CASH_OVER_SHORT above, not by bare name.
        $this->mapByCode($companyId, $companyLabel, 'SUPPLIER_CHARGE_EXPENSE', null, '5128', 'Supplier Fees & Surcharges');
        // BANK_CHARGES_EXPENSE: NOT placed under 'Payment Gateway Charges' (5140) — see CoaSeeder's
        // own comment on code 5222 for why (SystemAccountsSeeder::resolveGatewayFeeExpense()'s
        // neutral-leaf fallback would silently adopt it). Under 'Indirect Expenses (Operating
        // Expenses)' (5200) instead, resolved by CODE like the other three new leaves above.
        $this->mapByCode($companyId, $companyLabel, 'BANK_CHARGES_EXPENSE', null, '5222', 'Bank Charges');

        // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6): the two `at_travel` revenue-recognition
        // leaves — NEW/USER-DECIDED codes (CoaSeeder's own comments on rows 2650/1430), resolved
        // by CODE like every other new leaf above, not by bare name.
        $this->mapByCode($companyId, $companyLabel, 'DEFERRED_REVENUE', null, '2650', 'Deferred Revenue');
        // COA BLOCKER FIX (2026-08-31): code is 1431, NOT 1430 — see CoaSeeder's own comment on
        // this row (a real "Unbilled Supplier Cost" account already occupies 1430 in production
        // data for every company).
        $this->mapByCode($companyId, $companyLabel, 'PREPAID_SUPPLIER_COST', null, '1431', 'Prepaid Supplier Cost');
    }

    /**
     * GATEWAY_CLEARING_{gateway} for every gateway config('accounting.purpose_codes.gateways')
     * names. All resolve under the single "Payment Gateway" leaf rooted under Assets unless a
     * company has already split it into per-gateway children (matched by substring on the
     * gateway's label), in which case each matched gateway maps to its own child leaf.
     *
     * TASK 3 FIX (COA blocker, 2026-08-31): a gateway still unmatched after the per-name loop
     * below used to be skipped-and-reported outright the instant the pool had ANY children at
     * all, even a single unrelated one — the exact bug class residual-1/R-4 already fixed one
     * pool family over, on resolveGatewayFeeExpense(). Once EnsureSystemLeaves backfills
     * dedicated 'Knet'/'uPayment' children under this pool (App\Console\Commands\
     * EnsureSystemLeaves::LEAVES, GATEWAY_CLEARING_KNET/GATEWAY_CLEARING_UPAYMENT), the per-name
     * loop below matches them exactly like 'Tap'/'MyFatoorah'/'Hesabe' already do — no change
     * needed there. What DOES need fixing is the fallback for a gateway that STILL has no
     * dedicated child (e.g. a company EnsureSystemLeaves has not yet been run against, or one
     * missing MyFatoorah/Hesabe/Tap's own child): see the fallback branch below for why this
     * method deliberately does NOT copy resolveGatewayFeeExpense()'s "neutral leaf" fallback
     * verbatim.
     */
    private function resolveGatewayClearing(int $companyId, string $companyLabel): void
    {
        /** @var array<string, string> $gateways */
        $gateways = config('accounting.purpose_codes.gateways', []);

        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('name', 'Payment Gateway')
            ->get()
            ->filter(fn (Account $account) => $this->rootNameOf($account) === 'Assets')
            ->values();

        if ($candidates->isEmpty()) {
            foreach ($gateways as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_CLEARING_{$code}",
                    null,
                    "no 'Payment Gateway' account rooted under Assets found for {$label}"
                );
            }

            return;
        }

        if ($candidates->count() > 1) {
            foreach ($gateways as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_CLEARING_{$code}",
                    null,
                    "ambiguous: {$candidates->count()} 'Payment Gateway' accounts rooted under Assets for {$label}"
                );
            }

            return;
        }

        $pool = $candidates->first();
        $children = $pool->children()->get();

        $unmatched = $gateways;

        foreach ($children as $child) {
            foreach ($gateways as $code => $label) {
                if (! array_key_exists($code, $unmatched)) {
                    continue;
                }

                if (stripos((string) $child->name, $label) === false) {
                    continue;
                }

                if ($child->children()->exists()) {
                    $this->skip(
                        $companyId,
                        $companyLabel,
                        "GATEWAY_CLEARING_{$code}",
                        null,
                        "'{$child->name}' (id={$child->id}) is a group account, not a leaf"
                    );
                } else {
                    $this->upsertGatewayClearing($companyId, $companyLabel, $code, $child);
                }

                unset($unmatched[$code]);
            }
        }

        if (empty($unmatched)) {
            return;
        }

        if (! $pool->children()->exists()) {
            // No per-gateway split ever happened for this company — the pool itself is the leaf.
            foreach ($unmatched as $code => $label) {
                $this->upsertGatewayClearing($companyId, $companyLabel, $code, $pool);
            }

            return;
        }

        // TASK 3 FIX (COA blocker, 2026-08-31): the pool has children but at least one gateway
        // still has no dedicated child of its own. Unlike resolveGatewayFeeExpense()'s own
        // "neutral (non-gateway-named) leaf child of the pool" fallback (rule 2 of that method's
        // docblock), this method does NOT fall back onto an arbitrary non-gateway-named sibling
        // here. Real production data (akeed_verify_snapshot, company_id=1) proved this exact
        // 'Payment Gateway' pool can hold GENUINELY unrelated payment-instrument leaves that are
        // not any of the five configured gateways at all — 'Cash', 'Cheques', 'Bank Transfer KFH
        // CO', 'Deema', 'Tabby', 'Taly' all sit as direct children of that company's pool
        // alongside 'Tap'/'MyFatoorah'/'Hesabe'. A "neutral leaf, oldest id first" fallback here
        // would silently point a gateway's CLEARING account at one of those unrelated leaves
        // (e.g. 'Tabby', the oldest-id neutral child) — a real mis-mapping risk the 5140/Expenses
        // pool one family over never runs, because CoaSeeder guarantees that pool ONLY ever holds
        // gateway-named children by construction. The only safe fallback left is: preserve an
        // EXISTING mapping already validly pointing at the pool itself (set by the bare-pool
        // branch above on an earlier run, before this pool grew any children — the exact
        // "validly mapped to the pooled fallback leaf" state R-4 protects one pool family over);
        // otherwise, report the gap. Never guessed onto an unrelated account.
        foreach ($unmatched as $code => $label) {
            $purposeCode = "GATEWAY_CLEARING_{$code}";

            $existing = SystemAccount::query()
                ->where('company_id', $companyId)
                ->where('purpose_code', $purposeCode)
                ->where('service_type', null)
                ->first();

            if ($existing !== null && (int) $existing->account_id === (int) $pool->id) {
                // Re-upserts the SAME account_id — a no-op write, and upsertGatewayClearing()
                // prints no CHANGED line for it, since old === new. This IS "stays on the pool".
                $this->upsertGatewayClearing($companyId, $companyLabel, $code, $pool);

                continue;
            }

            $this->skip(
                $companyId,
                $companyLabel,
                $purposeCode,
                null,
                "'Payment Gateway' has children but none match '{$label}', none of the remaining children "
                .'are an eligible fallback (this pool may hold unrelated, non-gateway payment-instrument '
                .'leaves), and this purpose code is not already mapped to the pool itself — refusing to guess'
            );
        }
    }

    /**
     * Wraps upsert() for GATEWAY_CLEARING_{gateway} specifically, mirroring
     * upsertGatewayFeeExpense() below one pool family over (task 3, COA blocker fix,
     * 2026-08-31): reads this purpose code's CURRENT mapped account_id (if any) BEFORE writing,
     * then prints a CHANGED line naming the old and new account whenever the write actually moves
     * the mapping to a different account_id — most notably the day EnsureSystemLeaves backfills
     * dedicated 'Knet'/'uPayment' children under a company's pool and a gateway that used to
     * resolve to the bare pool itself (the "already mapped to pool" preserve rule above) moves
     * onto its own brand-new dedicated leaf instead. Silent when there was no prior mapping (a
     * fresh mapping is not a "change") or when the new target is the same account as before (the
     * preserve rule's own no-op re-upsert).
     */
    private function upsertGatewayClearing(int $companyId, string $companyLabel, string $code, Account $account): void
    {
        $purposeCode = "GATEWAY_CLEARING_{$code}";

        $oldAccountId = SystemAccount::query()
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->where('service_type', null)
            ->value('account_id');

        $this->upsert($companyId, $companyLabel, $purposeCode, null, $account);

        if ($oldAccountId !== null && (int) $oldAccountId !== (int) $account->id) {
            $oldAccount = Account::withoutGlobalScopes()->find($oldAccountId);

            $this->info(sprintf(
                '  CHANGED [%s] %s: #%d (%s / %s) -> #%d (%s / %s)',
                $companyLabel,
                $purposeCode,
                $oldAccountId,
                $oldAccount->code ?? '—',
                $oldAccount->name ?? 'unknown (deleted)',
                $account->id,
                $account->code ?? '—',
                $account->name
            ));
        }
    }

    /**
     * GATEWAY_FEE_EXPENSE_{gateway} for every gateway config('accounting.purpose_codes.gateways')
     * names (W2, PaymentController::createInvoicePaymentCOA() feeder cutover, KEY: coa-seam / B1
     * — PROPOSED purpose code, see config('accounting.purpose_codes') docblock). The gateway
     * processing-fee expense leg the legacy closure books via Charge::acc_fee_id — an
     * admin-configured, per-company, per-gateway expense account created on demand by
     * ChargeController::store() as a child of "Payment Gateway Charges" (5140, Expenses), named
     * after the gateway (same convention resolveGatewayClearing() above already relies on one
     * leaf family over: "Payment Gateway", 1300, Assets).
     *
     * RESIDUAL 1 FIX (W2.1, BLOCKER): unlike resolveGatewayClearing() above, the fallback for a
     * gateway with no matching per-gateway child is now UNCONDITIONAL per gateway, not gated on
     * "the pool has no children at all". CoaSeeder always seeds Tap/MyFatoorah/Hesabe children
     * under 5140 (KNET/uPayment were, until this build, the only two gateways with none), so the
     * old `if ($pool->children()->exists())` guard meant that guard was permanently true and the
     * pooled fallback was DEAD CODE for any company on the current CoaSeeder — KNET and uPayment
     * were permanently unmappable, and an engine-ON invoice payment through either gateway carrying
     * a fee threw UnmappedPurposeException where HEAD succeeded.
     *
     * RESIDUAL R-4 FIX (W2.2): the "first leaf child of the pool" fallback (used when a chart has
     * a PARTIAL per-gateway split — some children matched by name above, some did not) used to
     * accept ANY leaf child, oldest id first, with no check on what that child was named. The
     * moment EnsureSystemLeaves backfills 'KNET Charges' (5144) and 'uPayment Charges' (5145) as
     * new children of a pool that used to be a bare leaf holding ALL FIVE gateways' mappings, that
     * fallback started silently re-pointing MyFatoorah/Hesabe/Tap — gateways that were VALIDLY
     * mapped onto the pool itself — onto whichever of the two new children happened to have the
     * lowest id (in practice, always 'KNET Charges'), because a gateway-named leaf is exactly as
     * eligible as any other leaf under the old "any leaf" test. The resolution order per unmatched
     * gateway is now:
     *   1. a per-gateway child (name contains the gateway's label) — matched in the loop above;
     *   2. else a NEUTRAL leaf child of the pool — a leaf whose name does not identify ANY of
     *      config('accounting.purpose_codes.gateways')'s labels, not just the gateways still
     *      unmatched at this point (a child already claimed by KNET/uPayment above is still a
     *      "gateway child", and must stay excluded from every OTHER gateway's fallback) — oldest
     *      id first, reported by name so the mapped-report row shows exactly which account
     *      absorbed the fee;
     *   3. else, if the pool itself has NO children at all, the pool leaf itself (no split ever
     *      happened for this company — unchanged from before this fix);
     *   4. else, if this gateway's EXISTING system_accounts mapping already points at the pool
     *      itself (the exact "validly mapped to the pooled fallback leaf" state R-4 protects —
     *      set by rule 3 on some earlier run, before the pool grew gateway-named children), leave
     *      it exactly there: re-upserts the SAME account_id, which is a no-op write and prints no
     *      CHANGED line, rather than silently moving it onto an unrelated gateway's child;
     *   5. else (no dedicated child, no neutral child, not already safely on the pool — e.g. a
     *      gateway that was never mapped at all on a chart whose pool already has only
     *      gateway-named children) skip and report the gap, same as every other resolver in this
     *      seeder. Never maps onto a group account, and never onto a different gateway's own leaf.
     *
     * Every write this method makes goes through upsertGatewayFeeExpense() below, which prints a
     * CHANGED line (old account -> new account) whenever a purpose code's mapping's account_id
     * actually differs from what it was before this run — including rule 1 genuinely moving a
     * gateway off the pool onto its own new dedicated child, which IS a reportable change, unlike
     * rule 4's deliberate no-op.
     */
    private function resolveGatewayFeeExpense(int $companyId, string $companyLabel): void
    {
        /** @var array<string, string> $gateways */
        $gateways = config('accounting.purpose_codes.gateways', []);

        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('name', 'Payment Gateway Charges')
            ->get()
            ->filter(fn (Account $account) => $this->rootNameOf($account) === 'Expenses')
            ->values();

        if ($candidates->isEmpty()) {
            foreach ($gateways as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_FEE_EXPENSE_{$code}",
                    null,
                    "no 'Payment Gateway Charges' account rooted under Expenses found for {$label}"
                );
            }

            return;
        }

        if ($candidates->count() > 1) {
            foreach ($gateways as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_FEE_EXPENSE_{$code}",
                    null,
                    "ambiguous: {$candidates->count()} 'Payment Gateway Charges' accounts rooted under Expenses for {$label}"
                );
            }

            return;
        }

        $pool = $candidates->first();
        $children = $pool->children()->get();

        $unmatched = $gateways;

        foreach ($children as $child) {
            foreach ($gateways as $code => $label) {
                if (! array_key_exists($code, $unmatched)) {
                    continue;
                }

                if (stripos((string) $child->name, $label) === false) {
                    continue;
                }

                if ($child->children()->exists()) {
                    $this->skip(
                        $companyId,
                        $companyLabel,
                        "GATEWAY_FEE_EXPENSE_{$code}",
                        null,
                        "'{$child->name}' (id={$child->id}) is a group account, not a leaf"
                    );
                } else {
                    $this->upsertGatewayFeeExpense($companyId, $companyLabel, $code, $child);
                }

                unset($unmatched[$code]);
            }
        }

        if (empty($unmatched)) {
            return;
        }

        if (! $pool->children()->exists()) {
            // No per-gateway split ever happened for this company — the pool itself is the leaf.
            foreach ($unmatched as $code => $label) {
                $this->upsertGatewayFeeExpense($companyId, $companyLabel, $code, $pool);
            }

            return;
        }

        // RESIDUAL R-4 FIX (W2.2): the fallback candidate must be a LEAF *and* must not be named
        // after ANY gateway in the full config('accounting.purpose_codes.gateways') list — not
        // just the gateways still unmatched at this point. A child already claimed by one gateway
        // above (e.g. 'KNET Charges') is exactly as wrong a fallback for a DIFFERENT gateway
        // (MyFatoorah/Hesabe/Tap) as a child claimed by nobody yet; both identify a specific
        // gateway that is not this one. Oldest id first, same determinism as before this fix.
        $neutralFallbackLeaf = $pool->children()
            ->orderBy('id')
            ->get()
            ->first(function (Account $child) use ($gateways) {
                if ($child->children()->exists()) {
                    return false;
                }

                foreach ($gateways as $gatewayLabel) {
                    if (stripos((string) $child->name, $gatewayLabel) !== false) {
                        return false;
                    }
                }

                return true;
            });

        if ($neutralFallbackLeaf !== null) {
            foreach ($unmatched as $code => $label) {
                Log::notice('SystemAccountsSeeder: GATEWAY_FEE_EXPENSE fell back to a neutral leaf child of the pool (no dedicated per-gateway child found)', [
                    'company_id' => $companyId,
                    'purpose_code' => "GATEWAY_FEE_EXPENSE_{$code}",
                    'gateway_label' => $label,
                    'fallback_account_id' => $neutralFallbackLeaf->id,
                    'fallback_account_name' => $neutralFallbackLeaf->name,
                ]);
                $this->upsertGatewayFeeExpense($companyId, $companyLabel, $code, $neutralFallbackLeaf);
            }

            return;
        }

        // RESIDUAL R-4 FIX (W2.2): no neutral child exists (the pool's only children are
        // themselves named after gateways — e.g. only 'KNET Charges' and 'uPayment Charges' exist
        // so far). For each gateway still unmatched, the ONLY safe thing left to do is check
        // whether it is ALREADY validly mapped onto the pool itself (set by the bare-pool branch
        // above on an earlier run, before the pool grew gateway-named children) — if so, leave it
        // exactly there rather than guessing at an unrelated gateway's child. Only a gateway with
        // no such existing mapping at all is a genuine, reportable gap.
        foreach ($unmatched as $code => $label) {
            $purposeCode = "GATEWAY_FEE_EXPENSE_{$code}";

            $existing = SystemAccount::query()
                ->where('company_id', $companyId)
                ->where('purpose_code', $purposeCode)
                ->where('service_type', null)
                ->first();

            if ($existing !== null && (int) $existing->account_id === (int) $pool->id) {
                // Re-upserts the SAME account_id — a no-op write, and upsertGatewayFeeExpense()
                // prints no CHANGED line for it, since old === new. This IS "stays on the pool".
                $this->upsertGatewayFeeExpense($companyId, $companyLabel, $code, $pool);

                continue;
            }

            $this->skip(
                $companyId,
                $companyLabel,
                $purposeCode,
                null,
                "'Payment Gateway Charges' has children but none match '{$label}', none of the remaining children "
                .'are a neutral (non-gateway-named) leaf, and this purpose code is not already mapped to the pool '
                .'itself — refusing to guess'
            );
        }
    }

    /**
     * Wraps upsert() for GATEWAY_FEE_EXPENSE_{gateway} specifically: reads this purpose code's
     * CURRENT mapped account_id (if any) BEFORE writing, then prints a CHANGED line naming the old
     * and new account whenever the write actually moves the mapping to a different account_id.
     * Silent when there was no prior mapping (a fresh mapping is not a "change") or when the new
     * target is the same account as before (rule 4 of resolveGatewayFeeExpense()'s "stays on the
     * pool" no-op). RESIDUAL R-4 FIX (W2.2): named per the LEAD instruction to "print EVERY mapping
     * change (purpose_code: old account -> new account)" for this method specifically — the other
     * three resolvers in this seeder do not carry the same silent-remap risk (fixed candidate
     * counts, no unconditional multi-gateway fallback), so their upsert() calls are unchanged.
     */
    private function upsertGatewayFeeExpense(int $companyId, string $companyLabel, string $code, Account $account): void
    {
        $purposeCode = "GATEWAY_FEE_EXPENSE_{$code}";

        $oldAccountId = SystemAccount::query()
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->where('service_type', null)
            ->value('account_id');

        $this->upsert($companyId, $companyLabel, $purposeCode, null, $account);

        if ($oldAccountId !== null && (int) $oldAccountId !== (int) $account->id) {
            $oldAccount = Account::withoutGlobalScopes()->find($oldAccountId);

            $this->info(sprintf(
                '  CHANGED [%s] %s: #%d (%s / %s) -> #%d (%s / %s)',
                $companyLabel,
                $purposeCode,
                $oldAccountId,
                $oldAccount->code ?? '—',
                $oldAccount->name ?? 'unknown (deleted)',
                $account->id,
                $account->code ?? '—',
                $account->name
            ));
        }
    }

    /**
     * SERVICE_PAYABLE / SERVICE_COST / SERVICE_REVENUE × the 12 task types.
     */
    private function resolveServices(int $companyId, string $companyLabel): void
    {
        $serviceTypes = config('accounting.purpose_codes.service_types', []);

        $payableNames = [
            'flight' => 'Suppliers (Flights)',
            'hotel' => 'Suppliers (Hotels)',
            'visa' => 'Suppliers (Visas)',
            'insurance' => 'Suppliers (Insurance)',
            'tour' => 'Suppliers (Tour)',
            'cruise' => 'Suppliers (Cruise)',
            'car' => 'Suppliers (Car)',
            'rail' => 'Suppliers (Rail)',
            'esim' => 'Suppliers (Esim)',
            'event' => 'Suppliers (Event)',
            'lounge' => 'Suppliers (Lounge)',
            'ferry' => 'Suppliers (Ferry)',
        ];

        $costNames = [
            'flight' => 'Flights Cost',
            'hotel' => 'Hotels Cost',
            'visa' => 'Visa Cost',
            'insurance' => 'Insurance Cost',
            'tour' => 'Tour Cost',
            'cruise' => 'Cruise Cost',
            'car' => 'Car Cost',
            'rail' => 'Rail Cost',
            'esim' => 'Esim Cost',
            'event' => 'Event Cost',
            'lounge' => 'Lounge Cost',
            'ferry' => 'Ferry Cost',
        ];

        // W3-prereq lane A (USER RULING 2026-08-27): all 12 task types now have a dedicated
        // revenue leaf under 'Direct Income', '{Type} Booking Revenue' — the SAME name pattern
        // (ucfirst of the task type) flight/hotel already used before this change, extended to
        // the other ten. CoaSeeder seeds all 12 for a fresh company; EnsureSystemLeaves backfills
        // a missing one for an existing company (see this file's class docblock, SERVICE_REVENUE
        // bullet, and EnsureSystemLeaves's own docblock for the exact code-picking rule it uses).
        $revenueNames = [
            'flight' => 'Flight Booking Revenue',
            'hotel' => 'Hotel Booking Revenue',
            'visa' => 'Visa Booking Revenue',
            'insurance' => 'Insurance Booking Revenue',
            'tour' => 'Tour Booking Revenue',
            'cruise' => 'Cruise Booking Revenue',
            'car' => 'Car Booking Revenue',
            'rail' => 'Rail Booking Revenue',
            'esim' => 'Esim Booking Revenue',
            'event' => 'Event Booking Revenue',
            'lounge' => 'Lounge Booking Revenue',
            'ferry' => 'Ferry Booking Revenue',
        ];

        foreach ($serviceTypes as $serviceType) {
            if (isset($payableNames[$serviceType])) {
                $this->mapSupplierPoolLeaf($companyId, $companyLabel, 'SERVICE_PAYABLE', $serviceType, $payableNames[$serviceType], true);
            } else {
                $this->skip($companyId, $companyLabel, 'SERVICE_PAYABLE', $serviceType, "no naming convention known for service_type '{$serviceType}'");
            }

            if (isset($costNames[$serviceType])) {
                $this->mapSupplierPoolLeaf($companyId, $companyLabel, 'SERVICE_COST', $serviceType, $costNames[$serviceType], false);
            } else {
                $this->skip($companyId, $companyLabel, 'SERVICE_COST', $serviceType, "no naming convention known for service_type '{$serviceType}'");
            }

            if (isset($revenueNames[$serviceType])) {
                $this->mapByName($companyId, $companyLabel, 'SERVICE_REVENUE', $serviceType, $revenueNames[$serviceType]);
            } else {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    'SERVICE_REVENUE',
                    $serviceType,
                    "no dedicated revenue leaf in the current COA for '{$serviceType}' (only flight/hotel have one)"
                );
            }
        }
    }

    /**
     * R3 / purpose-mapping gap fix (P2-exit residual register §8 pre-flip checklist, 2026-09-01):
     * SERVICE_PAYABLE/SERVICE_COST resolver for the "Suppliers (X)"/"X Cost" pool leaf, aware that
     * {@see \App\Http\Controllers\SupplierCompanyController::activateSupplierProcess()} mints a
     * PER-SUPPLIER child under that pool the moment any supplier is activated for this
     * service_type — turning what used to be a bare leaf into a GROUP account. A bare mapByName()
     * call (this method's predecessor) could therefore only ever map this purpose code up until
     * the FIRST supplier activation for that type; every activation after that made the pool fail
     * the leaf test and the purpose code permanently unmapped (UnmappedPurposeException on the
     * engine's very first flight/hotel sale) — the gap this method closes.
     *
     * Resolution order, never guessing:
     *   1. Pool leaf not found at all -> skip (unchanged from mapByName()'s own "no account named"
     *      report).
     *   2. Pool leaf has NO children yet -> map onto the pool itself, byte-identical to the old
     *      mapByName() behaviour (the common case for every service_type with no supplier
     *      activated yet).
     *   3. Pool leaf HAS children (>=1 supplier activated) -> resolve the ONE supplier both (a)
     *      flagged `has_{$serviceType}` on `suppliers` and (b) actively linked to this company via
     *      `supplier_companies.is_active` — {@see \App\Models\Supplier::scopeActiveForCompany()},
     *      the SAME two facts SupplierCompanyController itself reads before minting the child
     *      account in the first place. Exactly one such supplier is required: more than one is a
     *      genuine, unresolvable business ambiguity (which supplier's own sub-ledger is "the"
     *      engine posting target for this service_type?) — reported as a gap, same as every other
     *      ambiguous mapByChain()/mapByName() call in this file, never silently picking a winner.
     *      Real-data check (akeed_verify_snapshot, company_id=1, 2026-09-01): 19 active 'hotel'
     *      suppliers and 40 active 'flight' suppliers exist for that company today — this method
     *      correctly reports the gap there rather than guessing; closing it for that company's
     *      real data is a supplier-data remediation decision for the owner (already flagged in
     *      the P2-exit report's pre-flip checklist §8), not an engineering judgment call this
     *      method can make.
     *   4. Exactly one active supplier resolved -> find ITS OWN child account under the pool
     *      (`Account::where('parent_id', $pool->id)->where('name', $supplier->name)`, i.e. the
     *      exact row SupplierCompanyController created). If that leaf itself has no children, map
     *      onto it directly (today's real shape for company_id=1's DOTW hotel account, which has
     *      zero children in akeed_verify_snapshot).
     *   5. $descendToCurrencyChild (SERVICE_PAYABLE only — see R3): if the supplier's own account
     *      DOES have children, they are
     *      {@see \App\Http\Controllers\TaskController::getOrCreateCurrencySpecificAccount()}'s own
     *      per-currency split, named "{$supplier->name} ({$currency})". Every engine-posted sale
     *      document books its supplier-payable line in `config('accounting.engine.base_currency')`
     *      (KWD) — {@see \App\Services\Accounting\SaleDraftBuilder}, always — matching legacy's own
     *      `processIssuedTask()`, which converts every original-currency liability line to the
     *      task's already-converted KWD total before posting it (see that method's own "Using
     *      converted amount for accounting balance" log line) — so the ONE reachable child that
     *      matters for the engine path is the leaf carrying `accounts.currency = base_currency`.
     *      Exactly one such child is required; zero or more than one is reported as a gap, never
     *      guessed. SERVICE_COST never reaches this branch — legacy's cost-side pool leaf
     *      (`getOrCreateCurrencySpecificAccount()` is only ever called with the PAYABLE leaf) never
     *      grows currency children, so an unexpected non-leaf cost-side supplier account is an
     *      unmodelled chart-shape surprise, reported as its own distinct gap instead.
     */
    private function mapSupplierPoolLeaf(
        int $companyId,
        string $companyLabel,
        string $purposeCode,
        string $serviceType,
        string $poolName,
        bool $descendToCurrencyChild
    ): void {
        $pool = Account::query()
            ->where('company_id', $companyId)
            ->where('name', $poolName)
            ->first();

        if ($pool === null) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "no account named '{$poolName}' found");

            return;
        }

        if (! $pool->children()->exists()) {
            // No per-supplier split has happened yet for this company/service_type — unchanged
            // behaviour, the pool leaf itself is still the correct (and only) mappable target.
            $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $pool);

            return;
        }

        $activeSuppliers = Supplier::query()
            ->where("has_{$serviceType}", true)
            ->activeForCompany($companyId)
            ->get();

        if ($activeSuppliers->count() !== 1) {
            $this->skip(
                $companyId,
                $companyLabel,
                $purposeCode,
                $serviceType,
                sprintf(
                    "'%s' (id=%d) has %d child account(s) (per-supplier sub-ledger, ".
                    'App\Http\Controllers\SupplierCompanyController) and %d active supplier(s) '.
                    "flagged has_%s for this company — refusing to guess which supplier's leaf is ".
                    'the engine posting target; needs exactly one active supplier to resolve '.
                    'unambiguously (owner supplier-data remediation, P2-exit pre-flip checklist §8)',
                    $poolName,
                    $pool->id,
                    $pool->children()->count(),
                    $activeSuppliers->count(),
                    $serviceType
                )
            );

            return;
        }

        $supplier = $activeSuppliers->first();

        $supplierAccounts = Account::query()
            ->where('company_id', $companyId)
            ->where('parent_id', $pool->id)
            ->where('name', $supplier->name)
            ->get();

        if ($supplierAccounts->count() !== 1) {
            $this->skip(
                $companyId,
                $companyLabel,
                $purposeCode,
                $serviceType,
                sprintf(
                    "expected exactly one child of '%s' named '%s' (the sole active supplier), found %d",
                    $poolName,
                    $supplier->name,
                    $supplierAccounts->count()
                )
            );

            return;
        }

        $supplierLeaf = $supplierAccounts->first();

        if (! $supplierLeaf->children()->exists()) {
            $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $supplierLeaf);

            return;
        }

        if (! $descendToCurrencyChild) {
            $this->skip(
                $companyId,
                $companyLabel,
                $purposeCode,
                $serviceType,
                "supplier leaf '{$supplier->name}' (id={$supplierLeaf->id}) under '{$poolName}' unexpectedly ".
                'has children — not a currency split this seeder knows how to descend into on the cost side'
            );

            return;
        }

        $baseCurrency = (string) config('accounting.engine.base_currency');

        $currencyChildren = Account::query()
            ->where('company_id', $companyId)
            ->where('parent_id', $supplierLeaf->id)
            ->where('currency', $baseCurrency)
            ->get();

        if ($currencyChildren->count() !== 1) {
            $this->skip(
                $companyId,
                $companyLabel,
                $purposeCode,
                $serviceType,
                sprintf(
                    "supplier leaf '%s' (id=%d) has %d child account(s) but %d carrying currency='%s' — refusing to guess",
                    $supplier->name,
                    $supplierLeaf->id,
                    $supplierLeaf->children()->count(),
                    $currencyChildren->count(),
                    $baseCurrency
                )
            );

            return;
        }

        $currencyLeaf = $currencyChildren->first();

        if ($currencyLeaf->children()->exists()) {
            $this->skip(
                $companyId,
                $companyLabel,
                $purposeCode,
                $serviceType,
                "'{$currencyLeaf->name}' (id={$currencyLeaf->id}) is a group account, not a leaf"
            );

            return;
        }

        $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $currencyLeaf);
    }

    /**
     * Resolve a leaf by exact name AND an exact chain of ancestor names (immediate parent
     * first). Used for the two purpose codes where the same account name is known to be
     * ambiguous (Clients, and — defensively — anything else sharing a name across branches).
     *
     * @param  string[]  $ancestorChain  immediate parent name first, then grandparent, etc.
     */
    private function mapByChain(
        int $companyId,
        string $companyLabel,
        string $purposeCode,
        ?string $serviceType,
        string $leafName,
        array $ancestorChain
    ): void {
        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('name', $leafName)
            ->get()
            ->filter(fn (Account $account) => $this->ancestorChainMatches($account, $ancestorChain))
            ->values();

        $chainLabel = implode(' > ', $ancestorChain);

        if ($candidates->isEmpty()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "no account named '{$leafName}' found under ancestor chain [{$chainLabel}]");

            return;
        }

        if ($candidates->count() > 1) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "ambiguous: {$candidates->count()} accounts named '{$leafName}' match ancestor chain [{$chainLabel}]");

            return;
        }

        $account = $candidates->first();

        if ($account->children()->exists()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "'{$leafName}' (id={$account->id}, code={$account->code}) is a group account (has children), not a leaf");

            return;
        }

        $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $account);
    }

    private function ancestorChainMatches(Account $account, array $ancestorChain): bool
    {
        $current = $account->parent;

        foreach ($ancestorChain as $expectedName) {
            if (! $current || $current->name !== $expectedName) {
                return false;
            }

            $current = $current->parent;
        }

        return true;
    }

    /**
     * Resolve a leaf by its account code (a structural fact file 11 gives explicitly for
     * RETAINED_EARNINGS/FX_GAIN_LOSS) rather than by name. Codes are the authoritative
     * anchor here, so a name mismatch is logged as a note but does not block the mapping;
     * a duplicate code (the known 2130 CoaSeeder dedup bug — 'Suppliers (Hotels)' / 'Suppliers
     * (Ferry)', explicitly deferred; the sibling '4130' pair was fixed by task A's Gateway-Fee-
     * Recovery renumber to '4131') does block it, since the seeder cannot know which duplicate is
     * correct.
     */
    private function mapByCode(
        int $companyId,
        string $companyLabel,
        string $purposeCode,
        ?string $serviceType,
        string $code,
        string $expectedNameHint
    ): void {
        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->get();

        if ($candidates->isEmpty()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "no account with code '{$code}' found");

            return;
        }

        if ($candidates->count() > 1) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "ambiguous: {$candidates->count()} accounts share code '{$code}' (duplicate-code bug, de-dup deferred)");

            return;
        }

        $account = $candidates->first();

        if ($account->children()->exists()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "account code '{$code}' (name={$account->name}) is a group account, not a leaf");

            return;
        }

        if (stripos((string) $account->name, $expectedNameHint) === false) {
            Log::notice('SystemAccountsSeeder: mapped account name does not match the expected hint (code is authoritative, mapped anyway)', [
                'company_id' => $companyId,
                'purpose_code' => $purposeCode,
                'code' => $code,
                'expected_name_hint' => $expectedNameHint,
                'actual_name' => $account->name,
            ]);
        }

        $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $account);
    }

    /**
     * Resolve a leaf by exact name only, anywhere in the company's tree. Used where the
     * name is already known to be unique in the seeded COA (per-service Suppliers/Cost
     * leaves, Suspense).
     */
    private function mapByName(int $companyId, string $companyLabel, string $purposeCode, ?string $serviceType, string $name): void
    {
        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->get();

        if ($candidates->isEmpty()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "no account named '{$name}' found");

            return;
        }

        if ($candidates->count() > 1) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "ambiguous: {$candidates->count()} accounts named '{$name}'");

            return;
        }

        $account = $candidates->first();

        if ($account->children()->exists()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "'{$name}' (id={$account->id}) is a group account, not a leaf");

            return;
        }

        $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $account);
    }

    /**
     * Walk account_id -> root_id -> Account::name, scoped to the same company, so a
     * "Payment Gateway" rooted under Liabilities (via Advances > Client) never gets
     * mistaken for the Assets-rooted clearing account of the same name.
     */
    private function rootNameOf(Account $account): ?string
    {
        if ($account->root_id === null) {
            // The account itself is a root only when it has no parent.
            return $account->parent_id === null ? $account->name : null;
        }

        if (! isset($this->accountCache[$account->root_id])) {
            $root = Account::query()
                ->where('id', $account->root_id)
                ->where('company_id', $account->company_id)
                ->first();

            if ($root) {
                $this->accountCache[$account->root_id] = $root;
            }
        }

        return $this->accountCache[$account->root_id]->name ?? null;
    }

    private function upsert(int $companyId, string $companyLabel, string $purposeCode, ?string $serviceType, Account $account): void
    {
        SystemAccount::updateOrCreate(
            [
                'company_id' => $companyId,
                'purpose_code' => $purposeCode,
                'service_type' => $serviceType,
            ],
            [
                'account_id' => $account->id,
            ]
        );

        $this->report['mapped'][] = [
            'company' => $companyLabel,
            'company_id' => $companyId,
            'purpose_code' => $purposeCode,
            'service_type' => $serviceType,
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
        ];
    }

    private function skip(int $companyId, string $companyLabel, string $purposeCode, ?string $serviceType, string $reason): void
    {
        $this->report['skipped'][] = [
            'company' => $companyLabel,
            'company_id' => $companyId,
            'purpose_code' => $purposeCode,
            'service_type' => $serviceType,
            'reason' => $reason,
        ];

        Log::warning('SystemAccountsSeeder: skipped purpose mapping', [
            'company_id' => $companyId,
            'purpose_code' => $purposeCode,
            'service_type' => $serviceType,
            'reason' => $reason,
        ]);
    }

    private function printReport(): void
    {
        $mapped = count($this->report['mapped']);
        $skipped = count($this->report['skipped']);

        $this->info("SystemAccountsSeeder: {$mapped} purpose(s) mapped, {$skipped} skipped.");

        foreach ($this->report['mapped'] as $row) {
            $this->line(sprintf(
                '  MAPPED  [%s] %s%s -> account #%d (%s / %s)',
                $row['company'],
                $row['purpose_code'],
                $row['service_type'] ? "/{$row['service_type']}" : '',
                $row['account_id'],
                $row['account_code'] ?? '—',
                $row['account_name']
            ));
        }

        foreach ($this->report['skipped'] as $row) {
            $this->warn(sprintf(
                '  SKIPPED [%s] %s%s -> %s',
                $row['company'],
                $row['purpose_code'],
                $row['service_type'] ? "/{$row['service_type']}" : '',
                $row['reason']
            ));
        }
    }

    private function info(string $message): void
    {
        $this->command?->getOutput()?->writeln("<info>{$message}</info>");
    }

    private function warn(string $message): void
    {
        $this->command?->getOutput()?->writeln("<comment>{$message}</comment>");
    }

    private function line(string $message): void
    {
        $this->command?->getOutput()?->writeln($message);
    }
}
