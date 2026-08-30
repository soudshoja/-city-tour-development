<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\SystemAccount;
use Illuminate\Database\Seeder;
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
        $this->mapByCode($companyId, $companyLabel, 'PREPAID_SUPPLIER_COST', null, '1430', 'Prepaid Supplier Cost');
    }

    /**
     * GATEWAY_CLEARING_{gateway} for every gateway config('accounting.purpose_codes.gateways')
     * names. All resolve under the single "Payment Gateway" leaf rooted under Assets unless a
     * company has already split it into per-gateway children (matched by substring on the
     * gateway's label), in which case each matched gateway maps to its own child leaf.
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
                    $this->upsert($companyId, $companyLabel, "GATEWAY_CLEARING_{$code}", null, $child);
                }

                unset($unmatched[$code]);
            }
        }

        if (empty($unmatched)) {
            return;
        }

        // No per-gateway split found for the remaining gateways — fall back to the pooled
        // leaf itself, but only if it is genuinely a leaf (no children of its own at all).
        if ($pool->children()->exists()) {
            foreach ($unmatched as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_CLEARING_{$code}",
                    null,
                    "'Payment Gateway' has children but none match '{$label}', and the pooled account itself is a group account, not a leaf"
                );
            }

            return;
        }

        foreach ($unmatched as $code => $label) {
            $this->upsert($companyId, $companyLabel, "GATEWAY_CLEARING_{$code}", null, $pool);
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
                $this->mapByName($companyId, $companyLabel, 'SERVICE_PAYABLE', $serviceType, $payableNames[$serviceType]);
            } else {
                $this->skip($companyId, $companyLabel, 'SERVICE_PAYABLE', $serviceType, "no naming convention known for service_type '{$serviceType}'");
            }

            if (isset($costNames[$serviceType])) {
                $this->mapByName($companyId, $companyLabel, 'SERVICE_COST', $serviceType, $costNames[$serviceType]);
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
