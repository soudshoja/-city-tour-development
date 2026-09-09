<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Posting engine kill-switch
    |--------------------------------------------------------------------------
    |
    | Global gate for App\Services\Accounting\PostingService. LIVE as of the W0
    | kill-switch fix: PostingService::post() reads this via
    | `$globallyEnabled = (bool) config('accounting.engine.enabled');`, before
    | post()'s own DB::transaction() opens, and refuses
    | (PostingEngineDisabledException) if this is false OR the per-company
    | companies.posting_engine_enabled column (2026_08_24_120005 migration) is
    | false/unresolvable. PostingService::reverse() DOES own two writes of its
    | own (the `->update(['reversal_of_transaction_id' => ...])` and
    | `->update(['posting_status' => 'reversed'])` calls) that never go
    | through post() — but both run after reverse()'s internal
    | `$result = $this->post($reversalDraft, $userId);` call, inside
    | reverse()'s own transaction, so a refusal there still rolls them back
    | (see PostingEngineDisabledException's docblock for the full chain, incl.
    | repost()). This single read still covers every WRITE path through
    | post()/reverse()/repost(). The one thing it does NOT gate is reverse()'s
    | own already-reversed early return — `if ($existingReversal !== null) {
    | return $this->toPostedDocument($existingReversal); }` — which is
    | read-only and returns success without ever calling post(), so there is
    | no write for the gate to guard there. Operable via `php artisan
    | accounting:engine {company} --enable|--disable|--status`
    | (App\Console\Commands\AccountingEngine).
    |
    | See Accounting Gap/11-technical-implementation-plan.md §C2.
    |
    */
    'engine' => [
        'enabled' => env('ACCOUNTING_ENGINE_ENABLED', false),

        // R2 / BUG-C1 check 1.1 — abs(Σdebit − Σcredit) tolerance for a single
        // posted document in PostingService::post(). Deliberately tighter than
        // TrialBalanceService::generate()'s aggregate is_balanced threshold
        // (`'is_balanced' => $difference < 0.001,`) — the two are
        // independently specified at different granularities; do not
        // homogenize them.
        'balance_tolerance' => 0.0005,

        // Money precision this build's new/widened columns use. KWD is a
        // 3-decimal-fils currency. Widening the *existing* 15,3/10,2 money
        // columns to 18,3 is the separate, explicitly deferred
        // widen_money_columns migration — not done here.
        'base_currency' => 'KWD',
        'base_decimals' => 3,
        'rate_decimals' => 6,

        // W4.A hook gate (Accounting Gap/22-plan-amendments.md rev 4 §2.2 W4.A row;
        // target-spec.md §B; 20-agent-subledger.md §5.4/§5.5): the agent's share of a
        // negative-margin loss is meant to recover against `5126 Loss Recovery (Agents)` — a
        // contra-expense leaf under `5100` (orchestrator ruling A2) — instead of the frozen
        // `4170 Loss Recovery Income` leaf InvoiceController::createSupplierLossEntries()/
        // createFeeLossEntries() used to credit before W4.A. `5126` itself isn't minted yet
        // (P5.3.A's own COA census item) and the real per-agent bearer-split posting shape is
        // P5.13's design, not W4.A's — so this stays false in every environment until P5.13
        // ships both. While false, InvoiceController::postAgentLossRecoveryHook() is a pure
        // no-op (see that method's own docblock): a negative-margin sale's agent share posts
        // NOTHING extra on the engine-ON path, same as the company-borne side.
        'agent_loss_recovery_enabled' => env('ACCOUNTING_AGENT_LOSS_RECOVERY_ENABLED', false),

        // W5.L (w5-brief.md §W5.L item 4): the ancestor-group name
        // AccountResolver::assertUnderBankGroup() requires to appear somewhere above a bank leaf a
        // voucher names by account id — 'Bank Accounts' (code 1200) in the seed COA
        // (database/seeders/CoaSeeder.php). Configurable rather than hardcoded inline in
        // AccountResolver so a company whose chart genuinely renames that group is not permanently
        // unable to pass this check — no such override mechanism exists yet (P1 scope), this key
        // exists purely so the literal lives in one place.
        'bank_group_name' => 'Bank Accounts',

        // W5.X (w5-brief.md §W5.X item 1, invariant A21): the sibling ancestor-group name
        // AccountResolver::isCashOrBankLeaf() checks alongside 'bank_group_name' above — 'Cash In
        // Hand' (code 1100) in the seed COA, the group CASH_IN_HAND (code 1120, "Receipt Voucher
        // Cash") is minted under. Together the two names classify a leaf as "the kind of account a
        // cash/bank/Day Book/receipts report must select by line movement on, never by doc_type".
        'cash_group_name' => 'Cash In Hand',
    ],

    /*
    |--------------------------------------------------------------------------
    | Period control (P2.5.A — p2_5-brief.md, period-lock-design.md)
    |--------------------------------------------------------------------------
    |
    | Consulted by App\Services\Accounting\PeriodGuard::assertOpen() to decide
    | how accounting_periods rows are keyed for a given posting date:
    |   - 'monthly' (default; doc 22 §4.3's decided default, close_mode = lock):
    |     one row per (company_id, year, month), month 1-12.
    |   - 'annual': one row per (company_id, year), stored with
    |     App\Models\AccountingPeriod::ANNUAL_MONTH (0) as the sentinel month —
    |     the whole year is one lockable unit. Kept as a company-wide override
    |     per p2_5-brief.md §P2.5.A ("annual option kept"); the blueprint's own
    |     period-control model is annual-only (period-lock-design.md §6).
    |
    | Also read by App\Console\Commands\AccountingPeriodsInit (accounting:
    | periods:init) to decide the grain of the rows it generates.
    |
    */
    'period' => [
        'length' => env('ACCOUNTING_PERIOD_LENGTH', 'monthly'),
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
        // W5.L (w5-brief.md §W5.S): agent settlement's TEMPORARY series — every legacy
        // AgentSettlementService write routes through this doc_type/sub_type=LEGACY pair so
        // P5.13 inherits an already-numbered, already-engine-posted series instead of a fresh
        // private counter. Not one of file 11's original eight doc_types; added here, not there,
        // because it postdates that contract.
        'AST' => 'Agent Settlement (temporary series — P5.13 replaces)',
        // P2.5.C (p2_5-brief.md §P2.5.C; doc 11 §P5.2): the one document
        // App\Services\Accounting\YearEndCloseService posts once per fiscal year — sweeps every
        // Income/Expenses leaf's net-for-the-year balance to RETAINED_EARNINGS (3400). Not one of
        // file 11's original eight doc_types (that set predates this sub-wave); `transactions.
        // doc_type` is a plain varchar(8) with no DB enum constraint (see that migration's own
        // column def), so this is a pure additive vocabulary entry, same as 'AST' above.
        'YEC' => 'Year-End Closing',
        // accounting-builds T0a/L6: four new, sub-type-less document types for the phase's four
        // owner-approved capabilities. Each keeps its own doc_type (rather than a JV sub-type) so
        // it is separately countable/excludable in reports, matching the 'YEC' precedent above —
        // JV has no sub-type list and VoucherSubTypeGuard semantics would have to be widened to
        // give it one. Added to SeedAccountingSerialSchemas::ALL_DOC_TYPES so each gets its own
        // per-company/branch/year serial counter from day one.
        'FXR' => 'Realised FX (apply-time)',
        'DEP' => 'Depreciation',
        'DSP' => 'Asset Disposal',
        'GWS' => 'Gateway Settlement',
    ],

    /*
    |--------------------------------------------------------------------------
    | sub_type vocabularies (W5.L, w5-brief.md §W5.L item 3)
    |--------------------------------------------------------------------------
    |
    | Per-doc_type sub_type whitelist, enforced by App\Services\Accounting\VoucherSubTypeGuard
    | (a caller-side guard, NOT PostingService::post() itself — see that class's own docblock for
    | why: docType='PV'/'RV' are SHARED namespaces with already-shipped feeders carrying their own
    | unrelated sub_type vocabularies, so a PostingService-level enforcement point would break
    | them) for exactly the doc_types listed as a key here — RV, PV, and AST's own temporary LEGACY
    | sub_type. W5.R/W5.P/W5.S (not built in this sub-wave) are expected to call
    | VoucherSubTypeGuard::assertValid() themselves when building their own DocumentDraft.
    |
    | DEVIATION (documented, necessary): w5-brief.md's own text names RV's fifth sub_type
    | 'GATEWAY_SETTLEMENT' (19 characters) — `transactions.sub_type` is `varchar(16)`
    | (RefundPostingService's own subType comments confirm this width; proven by execution here
    | too: MySQL strict mode rejected the literal brief string with "Data too long for column
    | 'sub_type'"). Shortened to 'GATEWAY_SETTLE' (14 characters), the shortest unambiguous
    | truncation, rather than widening a shared column for one sub_type value or silently letting
    | MySQL truncate it. Every OTHER sub_type value below already fits within 16 characters. Every OTHER doc_type (INV/JV/CRN/DBN/OJV/REV) has no key
    | here and is therefore completely ungoverned, unchanged from before this config key existed —
    | see App\Exceptions\Accounting\InvalidSubTypeException's own docblock for why retroactively
    | fixing a vocabulary for those is deliberately out of this wave's scope.
    |
    |   - RV: {INVOICE, ACCOUNT, TOPUP, IMPORT, GATEWAY_SETTLE} — w5-brief.md §W5.L.
    |     INVOICE = cash applied against a specific unpaid invoice; ACCOUNT = a client-account
    |     payment with no single invoice target; TOPUP = a client-credit top-up (InvoiceReceipt.type
    |     = 'credit' today); IMPORT = bulk-imported historical receipts; GATEWAY_SETTLE = a
    |     payment-gateway settlement batch reconciled as one receipt (P7 territory to actually
    |     build, the sub_type is registered now so W5.R doesn't need a second migration for it).
    |   - PV: {SUPPLIER, ACCOUNT, BONUS, REFUND_OUT, BY_DATE} — w5-brief.md §W5.L. SUPPLIER = a
    |     payment to a supplier account; ACCOUNT = a generic company-initiated cash-out; BONUS = an
    |     agent bonus payment (BonusAgent side-record, w5-brief.md §W5.P); REFUND_OUT = a refund
    |     payment out to a client/party; BY_DATE = the existing bankpaymenttype='PaymentByDate' fast
    |     path (state doc row "PV kinds (today)": sets reconciled=2 at creation).
    |   - AST: {LEGACY} — the ONLY sub_type this temporary series ever uses (w5-brief.md §W5.S);
    |     every real settlement-kind distinction (SETTLE_OFFSET/SETTLE_SALARY/SETTLE_CASH/
    |     SETTLE_GATEWAY/COMM_PAYOUT — w5-state.md §3) is P5.13's own AST vocabulary once it
    |     replaces this series, not this wave's to define.
    |
    */
    'sub_types' => [
        'RV' => ['INVOICE', 'ACCOUNT', 'TOPUP', 'IMPORT', 'GATEWAY_SETTLE'],
        'PV' => ['SUPPLIER', 'ACCOUNT', 'BONUS', 'REFUND_OUT', 'BY_DATE'],
        'AST' => ['LEGACY'],
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
    |   CLAUDE.md's payment gateway list. Resolved by
    |   SystemAccountsSeeder::resolveGatewayClearing() under the "Payment Gateway" (1300, Assets)
    |   pool, matched by name to a per-gateway child when one exists — CoaSeeder seeds none of
    |   these by default; App\Console\Commands\EnsureSystemLeaves optionally backfills 'Knet'
    |   (1311) / 'uPayment' (1312) as OPTIONAL leaves (TASK 3, COA blocker fix, 2026-08-31; the
    |   other three gateways have no equivalent backfill leaf yet — a company missing their
    |   dedicated child still resolves via the bare-pool fallback below, or reports a gap).
    |   UNLIKE GATEWAY_FEE_EXPENSE below, an unmatched gateway here never falls back to an
    |   arbitrary "neutral" leaf child of the pool — real production data
    |   (akeed_verify_snapshot, company_id=1) proved this pool can hold genuinely unrelated
    |   payment-instrument leaves ('Cash', 'Cheques', 'Deema', 'Tabby', ...) that are not any
    |   configured gateway at all, so the only safe fallback once the pool has any children is (a)
    |   the bare-pool leaf itself, when the pool has NO children yet, or (b) preserving an
    |   existing mapping already validly pointing at the pool — never guessing onto an unrelated
    |   sibling. See resolveGatewayClearing()'s own docblock for the full rule.
    | - GATEWAY_FEE_EXPENSE (added W2, PaymentController::createInvoicePaymentCOA() feeder,
    |   KEY: coa-seam / B1 — PROPOSED, minted from this SAME 'gateways' map as
    |   GATEWAY_FEE_EXPENSE_{key}, service_type NULL): the gateway processing-fee expense leg
    |   the legacy closure books via Charge::acc_fee_id (an admin-configured, per-company,
    |   per-gateway expense account — NOT a fixed name AccountResolver could look up the same
    |   way as RECEIVABLE_CONTROL). Resolved by SystemAccountsSeeder::resolveGatewayFeeExpense()
    |   under the SAME structural convention ChargeController::store() itself uses to CREATE
    |   that account: a per-gateway child (named after the gateway) of the "Payment Gateway
    |   Charges" (5140) leaf/group rooted under Expenses — mirroring resolveGatewayClearing()'s
    |   own "Payment Gateway" (1300, Assets) pool-plus-per-gateway-children pattern one leaf
    |   family over. CoaSeeder now seeds a dedicated child for all five gateways ('TAP Charges'
    |   5141, 'MyFatoorah Charges' 5142, 'Hesabe Charges' 5143, 'KNET Charges' 5144, 'uPayment
    |   Charges' 5145 — the last two added W2.1, residual 1, after this exact gap made every
    |   engine-ON KNET/uPayment invoice payment with a fee throw UnmappedPurposeException where
    |   HEAD succeeded). For a company whose chart pre-dates that fix,
    |   resolveGatewayFeeExpense()'s fallback per unmatched gateway is UNCONDITIONAL, in this
    |   order: (1) a per-gateway child if one matches by name; (2) else the first LEAF child of
    |   the "Payment Gateway Charges" pool (reported by name in the mapped log/report row —
    |   never a group); (3) else, if the pool has no children at all, the pool leaf itself; (4)
    |   else (pool has children but none are leaves) skip and report the gap. Never guesses onto
    |   an unrelated account and never maps onto a group.
    | - per_service × service_types: the three purpose codes multiplied
    |   across the 12 task types (CLAUDE.md's task-type list), e.g.
    |   purpose_code=SERVICE_COST, service_type=hotel.
    | - CLIENT_ADVANCE (added W1.1, CheckMyFatoorahPayments feeder — P1 policy call,
    |   2026-08-26): a MyFatoorah payment with no invoice_id is money held FOR the client, not
    |   yet earned against a specific invoice — a liability, not a contra-receivable. Mapped by
    |   SystemAccountsSeeder::resolveControls() via mapByChain() to the SAME leaf the legacy
    |   closure in CheckMyFatoorahPayments::handle() has always resolved by name/root_id/
    |   parent_id: Liabilities -> Advances -> Client -> Payment Gateway (code 2632 in
    |   CoaSeeder). Distinct from RECEIVABLE_CONTROL (used when invoice_id IS set — the payment
    |   is genuinely settling a specific invoice) and from the unrelated Assets-rooted "Payment
    |   Gateway" (1300) GATEWAY_CLEARING_* resolves under.
    | - MARKUP_INCOME (added W1, ChatController feeder; leaf assigned W1.3
    |   per USER DECISION 2026-08-27; SEMANTICS SUPERSEDED W3d — see below):
    |   originally the agency's margin on a task-based sale (sell price −
    |   supplier cost), booked as a THIRD credit leg alongside
    |   RECEIVABLE_CONTROL (debit) and SERVICE_PAYABLE (credit).
    |
    |   W3d (sale-shape-audit.md / w3d-brief.md, binding decision
    |   2026-08-28) REDEFINES this vocabulary: the ordinary sale margin (sell
    |   − cost) now sits in SERVICE_REVENUE/{type} — see that purpose code's
    |   own note below and App\Services\Accounting\SaleDraftBuilder's class
    |   docblock (the single place both InvoiceController and ChatController
    |   now build a sale's LineDraft array from). MARKUP_INCOME is reserved
    |   for a genuinely DISTINCT event this build's feeders do not yet model:
    |   an explicit markup line added ON TOP OF a fare the invoice already
    |   separates from cost (e.g. IATA fare + a separately-tracked agency
    |   markup field) — Accounting Gap/03-transactions-ar-ap.md Finding 3's
    |   "Dr customer receivable = SellValue; Cr supplier payable = cost; Cr
    |   income = SellValue − Payable" pattern is the ordinary-sale case and is
    |   what SERVICE_REVENUE now books; MARKUP_INCOME would be a FOURTH leg on
    |   top of that pattern, never a substitute for it. The two purpose codes
    |   must never carry the same money on the same document — see
    |   SaleDraftBuilder's own docblock. Resolved by
    |   SystemAccountsSeeder::mapByChain() to the leaf "Markup Income"
    |   (CoaSeeder code 4132) under "Commission & Service Fee Income" — a
    |   distinct child leaf from "Gateway Fee Recovery" (4131), NOT the
    |   (now-a-group) "Commission & Service Fee Income" account itself, which
    |   is why mapByChain (leaf-by-name-and-ancestor-chain), not mapByName,
    |   is required here.
    | - SERVICE_REVENUE (per_service; W3d, sale-shape-audit.md): dual
    |   meaning by posting_basis (see 'posting_basis' config block below and
    |   SaleDraftBuilder's own docblock) — under `agent` (NET) basis this is
    |   the EARNED MARGIN on the service (sell − cost, sign-aware); under
    |   `principal` (GROSS) basis this is the FULL sell value (W1's original
    |   meaning), paired with a separate SERVICE_COST/SERVICE_PAYABLE
    |   cost-of-sales leg. Never both meanings on the same document — a given
    |   sale uses exactly one basis.
    | - SALARY_PAYABLE (added W1.3, AgentController::update() feeder, per
    |   USER DECISION 2026-08-27): the credit leg of the agent monthly-salary
    |   accrual — previously kept on PAYABLE_CONTROL (2110 "Creditors")
    |   pending a user-assigned account name (see AgentController::update()'s
    |   own P3 comment, now resolved). Resolved by
    |   SystemAccountsSeeder::mapByChain() to the leaf "Salaries & Wages
    |   Payable" (CoaSeeder code 2201 — USER DECISION 2026-08-27, residual 6,
    |   W2.1: NOT 2240, which collides with AgentController::update()'s own
    |   max(sibling code)+1 agent-profit leaves under "Agent Profit Payable",
    |   2230, once a company has 10+ agents) under "Accrued Expenses" (2200).
    | - SERVICE_FEE_INCOME (added W4.0, InvoiceController::addInvoiceChargeJournalEntries()
    |   feeder — the flat `invoices.invoice_charge` add-on, routed through the seam as an
    |   INV/FEE document): a GLOBAL purpose code, not one of the 12 per-service task types (a
    |   flat invoice charge is not tied to any one service_type) — deliberately NOT added to
    |   'service_types' below, which would also require payable/cost naming-convention entries
    |   in SystemAccountsSeeder::resolveServices() that make no sense for a fee with no supplier
    |   cost. Resolved by SystemAccountsSeeder::mapByChain() to a NEW dedicated leaf "Service Fee
    |   Income" (CoaSeeder code 4133) under "Commission & Service Fee Income" (4130) — same
    |   parent group as "Gateway Fee Recovery" (4131) / "Markup Income" (4132), the "4130 family"
    |   this purpose code's own W4.0 brief item names.
    | - GATEWAY_FEE_RECOVERY (added W4.D, InvoiceController::createGatewayFeeRecoveryEntries()
    |   feeder, replacing the deleted createGatewayProfitEntries()): the gross-up income leg for
    |   a CLIENT-borne gateway fee (`Charge::$paid_by === 'Client'`) — Dr RECEIVABLE_CONTROL / Cr
    |   this code, both for accountingFee + markupProfit + roundingProfit (the full amount the
    |   client is charged on top of the sell price, ChargeService::calculate()'s 'gatewayFee'/
    |   'finalAmount' fields). The deleted method's own ON path resolved the same leaf by an
    |   explicit `accountId` (Charge has no purpose-code-mappable field pointing at it); this
    |   feeder resolves it via AccountResolver instead, same "Gateway Fee Recovery" (CoaSeeder
    |   4131) leaf under "Commission & Service Fee Income" (4130) — the actual gateway processing
    |   cost stays on GATEWAY_FEE_EXPENSE_{gateway} (5141-5145), booked separately at collection
    |   time by PaymentController::createInvoicePaymentCOA(), untouched by this code.
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
            'MARKUP_INCOME',
            'CLIENT_ADVANCE',
            // Added for AgentController::update()'s salary-posting feeder (R3 seam cutover) —
            // the debit leg of the monthly salary accrual. Resolves to the 'Agent Salaries'
            // (5160) leaf CoaSeeder already seeds.
            'SALARY_EXPENSE',
            // W1.3 (USER DECISION 2026-08-27): the credit leg of that same salary accrual — was
            // temporarily posted to PAYABLE_CONTROL (2110 "Creditors") pending a user-assigned
            // account name. Resolves to the 'Salaries & Wages Payable' (2201 — W2.1 residual 6,
            // was 2240) leaf under 'Accrued Expenses' (2200).
            'SALARY_PAYABLE',
            // W4.0: flat invoice-charge income (invoices.invoice_charge), routed through the seam
            // as an INV/FEE document. Resolves to the 'Service Fee Income' (4133) leaf under
            // 'Commission & Service Fee Income' (4130).
            'SERVICE_FEE_INCOME',
            // W4.D: gross-up income for a client-borne gateway fee (InvoiceController::
            // createGatewayFeeRecoveryEntries(), replacing the deleted createGatewayProfitEntries()).
            // Resolves to the 'Gateway Fee Recovery' (4131) leaf under 'Commission & Service Fee
            // Income' (4130).
            'GATEWAY_FEE_RECOVERY',
            // W4.R (w4-brief.md §4b): the client recharge of an airline/consolidator penalty on a
            // refund. Resolves to the 'Penalty Pass-Through Recovery' (4136) leaf under 'Commission
            // & Service Fee Income' (4130). See RefundPostingService's own docblock for the exact
            // lines this feeds.
            'PENALTY_PASSTHROUGH_RECOVERY',
            // W4.R (w4-brief.md §4c): the penalty amount the AGENCY actually incurs on a refund
            // (deducted from the supplier's own refund). Resolves to the 'Refund Penalty Cost'
            // (5124) leaf under 'Direct Expenses (Cost of Sales)' (5100).
            'PENALTY_COST_EXPENSE',
            // W4.R (w4-brief.md §4e, target-spec.md §B three-event clawback model, step (a) —
            // "always: Dr 5125 Airline Refund Clawback / Cr airline payable"). Unconditional,
            // independent of bearer. Resolves to 'Airline Refund Clawback' (5125) under 'Direct
            // Expenses (Cost of Sales)' (5100). NOT to be confused with the still-unminted 5126
            // (P5.13's own agent-loss-recovery leaf, gated behind
            // 'accounting.engine.agent_loss_recovery_enabled' — see
            // InvoiceController::postAgentLossRecoveryHook()).
            'AIRLINE_CLAWBACK_EXPENSE',
            // W5.L (w5-brief.md §W5.L item 4) — five voucher/instrument anchor purpose codes.
            // Resolved the ORDINARY way (AccountResolver::resolve(), same as every other code in
            // this 'global' array) — NOT via resolveAnchor()/the 'anchors' sub-key below, which is
            // reserved for a purpose code naming a GROUP a per-party leaf mints under (see that
            // sub-key's own docblock); every one of these five names a single, ordinary,
            // per-company posting-target LEAF, exactly like RECEIVABLE_CONTROL/PAYABLE_CONTROL
            // above.
            //   - CASH_IN_HAND: replaces ReceiptVoucherController's/BankPaymentController's
            //     existing Account::where('name','Receipt Voucher Cash') lookup (w5-state.md row
            //     "Account resolution") — same pre-existing leaf (CoaSeeder code 1120, under 'Cash
            //     In Hand' 1100), resolved by mapByChain() since 'Cash In Hand' (1100) is itself a
            //     GROUP with two children (Petty Cash 1110, Receipt Voucher Cash 1120).
            //   - CHEQUES_IN_HAND: NEW leaf, code 1215, direct child of 'Assets' (a peer of 'Cash
            //     In Hand' 1100 / 'Bank Accounts' 1200, not a child of either) — the PDC-received
            //     asset a cheque-instrument RV debits until it clears (w5-brief.md §W5.R).
            //   - CHEQUES_ISSUED_NOT_CLEARED: NEW leaf, code 2215, direct child of 'Liabilities' —
            //     the mirror liability a cheque-instrument PV credits until it clears
            //     (w5-brief.md §W5.P).
            //   - BANK_CHARGES_EXPENSE: w5-brief.md's own text says "existing leaf under the 5140
            //     family — find it, report the code" — a repo-wide search (CoaSeeder, every
            //     migration, every controller) found NO account anywhere named 'Bank Charges' or
            //     equivalent; the brief's premise does not hold for this codebase. A NEW leaf is
            //     created instead — but NOT under 'Payment Gateway Charges' (5140), the brief's own
            //     suggested family: a first attempt placed it there (as a sixth sibling of TAP/
            //     MyFatoorah/Hesabe/KNET/uPayment Charges) and running the full accounting suite
            //     proved that wrong — SystemAccountsSeeder::resolveGatewayFeeExpense()'s "neutral
            //     (non-gateway-named) leaf child of the pool" fallback silently adopted it as the
            //     fallback target for KNET/uPayment (or any gateway missing its own dedicated
            //     child), breaking two existing EnsureSystemLeavesTest regression pins that assert
            //     exactly the opposite (a gap must be REPORTED, never guessed onto an unrelated
            //     leaf). Placed instead under 'Indirect Expenses (Operating Expenses)' (5200) —
            //     code 5222, name 'Bank Charges' — an ordinary administrative/operating expense
            //     (which a manual bank-transfer fee genuinely is) structurally isolated from the
            //     gateway-fee-expense resolver entirely. See CoaSeeder's own comment on that code
            //     for the full before/after.
            //   - CASH_OVER_SHORT: NEW leaf under 'Direct Expenses (Cost of Sales)' (5100) — the
            //     variance line a cash-drawer reconciliation posts (deferred build, P5.18's "daily
            //     cash close" — w5-brief.md's own Deferred list; this wave only registers the
            //     anchor). Code 5127, chosen as the next free code in the same direct-child
            //     sequence 5122 (Agent Bonus) / 5123 (Fee Loss Provision) / 5124 (Refund Penalty
            //     Cost) / 5125 (Airline Refund Clawback) already fills — NOT 5126, which
            //     PostingService's own resolved-gap #9 note and InvoiceController::
            //     postAgentLossRecoveryHook()'s docblock both already reserve for P5.13's
            //     not-yet-built agent-loss-recovery leaf.
            'CASH_IN_HAND',
            'CHEQUES_IN_HAND',
            'CHEQUES_ISSUED_NOT_CLEARED',
            'BANK_CHARGES_EXPENSE',
            'CASH_OVER_SHORT',
            // W6.S "New leaves" (w6-brief.md): void/reissue client-facing fee income. Registered
            // here (SystemAccountsSeeder::resolveControls()) so the leaves are seeded and
            // resolvable via AccountResolver ahead of W6.V/W6.R actually posting to them.
            'VOID_FEE_INCOME',
            'REISSUE_FEE_INCOME',
            // W6.C (w6-brief.md "W6.C — Supplier-side charges"; supplier-charges-design.md Table 4):
            // the cost leg of a supplier-charge-rule fee on AGENT-basis sales (a card surcharge,
            // IATA/BSP fee, etc. the supplier bills the agency). Deliberately GLOBAL, not one of
            // 'per_service' below: on agent basis there is no existing SERVICE_COST leg for this
            // fee family to join (agent-basis sales never post SERVICE_COST — see
            // SaleDraftBuilder::buildAgentBasisLines()), so a dedicated, non-per-type expense leaf
            // is needed. On PRINCIPAL basis, App\Services\Accounting\SupplierChargeLineBuilder
            // instead debits the existing per-service 'SERVICE_COST' purpose code (already in
            // 'per_service' below) — this code is used ONLY for the agent-basis case. Resolves to
            // the 'Supplier Fees & Surcharges' (5128) leaf under 'Direct Expenses (Cost of Sales)'
            // (5100) — next free direct-child code after 5121-5125/5127 (NOT 5126, reserved for
            // P5.13's agent-loss-recovery leaf).
            'SUPPLIER_CHARGE_EXPENSE',
            // W6.C: the client recharge of a supplier-charge-rule fee (recharge_policy=
            // recharge_client — "Dr RECEIVABLE_CONTROL / Cr this code"). Resolves to the
            // 'Supplier Charge Recharge Income' (4137) leaf under 'Commission & Service Fee
            // Income' (4130) — the first free code in that family (4131/4132/4133/4136 already
            // taken; 4134/4135 are W6.S's own Void/Reissue Fee Income — see w6-brief.md's own
            // "Code correction applied here" note; supplier-charges-design.md's original mention
            // of this leaf as '4134' is superseded by 4137).
            'SUPPLIER_CHARGE_RECHARGE_INCOME',
            // W6.I (w6-brief.md "Importer contract" item 2; Accounting Gap/22-plan-amendments.md
            // §16.1 "EMD" row): the ancillary sale line an EMD task posts on its PARENT ticket's
            // existing invoice (never a new invoice) -- IFRS 15 §22, a separate performance
            // obligation from the transportation the parent ticket already recognised. Deliberately
            // GLOBAL, not per_service: an EMD is not one of the 12 CLAUDE.md task types, and the
            // per-service SERVICE_REVENUE/{type} family would need its own dedicated
            // SERVICE_PAYABLE/emd leaf nobody else posts to for what is, in practice, almost always
            // a zero-supplier-cost pure fee (see TaskStatusService::postEmdAncillary()'s own
            // docblock for why the supplier-cost leg, when one exists, reuses the PARENT's own
            // per-service SERVICE_PAYABLE/{type} leaf instead). Resolves to the 'EMD Ancillary
            // Revenue' (4138) leaf under 'Commission & Service Fee Income' (4130) -- next free code
            // in that family after 4131/4132/4133/4134/4135/4136/4137.
            'EMD_ANCILLARY_REVENUE',
            // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6, IFRS 15): the two `at_travel` revenue-
            // recognition leaves — see the 'revenue_recognition' config block below for the full
            // mechanics. Deliberately GLOBAL (one shared leaf, not one per service_type): the
            // brief's own text names each as singular ("a new liability leaf" / "asset leaf"), and
            // unlike SERVICE_REVENUE/SERVICE_PAYABLE/SERVICE_COST there is no reporting need to
            // split a deferred balance by service type — App\Services\Accounting\
            // DeferredRevenueScheduleReport groups by task/release-month instead, from
            // journal_entries.task_id, not from a per-type account split.
            //   - DEFERRED_REVENUE: the sale-time credit an `at_travel` service posts INSTEAD OF
            //     SERVICE_REVENUE (agent basis: instead of the margin leg; principal basis: instead
            //     of the full-sell revenue leg) — a liability, released to SERVICE_REVENUE/{type}
            //     on the travel/check-in date by `accounting:recognize-revenue`
            //     (App\Services\Accounting\RevenueRecognitionService). Resolves to the 'Deferred
            //     Revenue' (2650) leaf, a direct child of 'Liabilities' — see CoaSeeder's own
            //     comment on that code for why it sits there and not nested under 'Refund Payable'
            //     (2600) or 'Advances' (2620), the two existing occupants of the 2600 family.
            'DEFERRED_REVENUE',
            //   - PREPAID_SUPPLIER_COST: the sale-time debit a PRINCIPAL-basis `at_travel` service
            //     posts INSTEAD OF SERVICE_COST (agent basis never posts a SERVICE_COST expense leg
            //     in the first place — see SaleDraftBuilder::buildAgentBasisLines() — so this code
            //     is only ever reached from buildPrincipalBasisLines()) — an asset, released to
            //     SERVICE_COST/{type} on the same schedule as DEFERRED_REVENUE above. Resolves to
            //     the 'Prepaid Supplier Cost' (1431 — COA BLOCKER FIX, 2026-08-31: NOT 1430, which
            //     a real-data audit found already occupied by a genuine, pre-existing "Unbilled
            //     Supplier Cost" account under the same parent in every akeed_verify_snapshot
            //     company; see CoaSeeder's own comment on this row) leaf under 'Supplier
            //     Advances/Prepayments' (1400) — a peer of the existing 'Prepaid Flights' (1410) /
            //     'Prepaid Hotels' (1420) leaves, generic across every principal-basis service type
            //     rather than per-type like those two (no feeder before this wave ever posted to
            //     either of them — a pre-existing, unused pair — and duplicating that per-type
            //     split for a brand new leaf was judged unnecessary complexity for a single shared
            //     "money paid to a supplier ahead of the service" concept).
            'PREPAID_SUPPLIER_COST',
            // accounting-builds T0a (L3 — two-purpose realised FX, not one differential): the
            // apply-time realised-FX pair RealisedFxService (Lane A / T1) posts. FX_LOSS_REALISED
            // resolves to the EXISTING 5219 'Exchange Gain/Loss' leaf (kept as-is, no rename —
            // the legacy FX_GAIN_LOSS purpose below stays registered, zero call sites, listed for
            // the stale sweep). FX_GAIN_REALISED resolves to a NEW income leaf, 4139 'Realised
            // Exchange Gain', the next free code in the 4131.../4138 'Commission & Service Fee
            // Income > Direct Income > Income' family — kept as its own leaf (not netted into
            // 5219) so the sign-matrix census test can assert *which leaf* is hit, a strictly
            // stronger oracle than a same-leaf balance check.
            'FX_GAIN_REALISED',
            'FX_LOSS_REALISED',
            // accounting-builds T0a (L9): the credit leg of YearEndCloseService's new dividend
            // sweep — Dr RETAINED_EARNINGS / Cr this code for the year's 3200 'Dividends Paid'
            // movement, posted as extra lines inside the SAME YEC document (never a separate
            // document — keeps the YEC whole-document TB-exclusion rule intact). Resolves to the
            // EXISTING 3200 leaf, mapByCode() same convention as RETAINED_EARNINGS/FX_GAIN_LOSS.
            'DIVIDENDS_PAID',
            // accounting-builds T0a (L7/L8): the monthly straight-line depreciation expense leg
            // DepreciationRunService (Lane B / T3) posts. Resolves to the EXISTING 5203
            // 'Depreciation' leaf.
            'DEPRECIATION_EXPENSE',
            // accounting-builds T0a (L7): the asset-disposal balancing pair FixedAssetService::
            // dispose() (Lane B / T4) posts for proceeds − NBV. ASSET_DISPOSAL_LOSS resolves to
            // the EXISTING 5220 'Gain/Loss on Asset Disposal' leaf; ASSET_DISPOSAL_GAIN resolves
            // to a NEW income leaf, 4141 'Gain on Asset Disposal' (NOT 4140 — a real-data COA
            // collision with a pre-existing 'Sales' leaf found on the akeed_verify_snapshot
            // bench; see CoaSeeder's own comment on code 4141 for the full before/after), same
            // chain as FX_GAIN_REALISED (4139) directly above.
            'ASSET_DISPOSAL_LOSS',
            'ASSET_DISPOSAL_GAIN',
            // CT-A3 wave 1 (owner ruling R-CT3, 2026-09-09): the asset leg of the task-issuance
            // supplier-payable accrual — Dr this / Cr SERVICE_PAYABLE/{type}, posted by
            // App\Services\Accounting\TaskIssuancePayableService when a task's supplier cost
            // becomes a guaranteed liability before any invoice exists. Resolves to the
            // 'Unbilled Supplier Cost' (1430) leaf under 'Supplier Advances/Prepayments' (1400) —
            // the account every real chart already carries (CT-A1 §3.1) and that CoaSeeder now
            // seeds for a fresh company too. NOT to be confused with PREPAID_SUPPLIER_COST
            // (1431), which is the revenue-recognition deferral of a cost on an ALREADY-INVOICED
            // at_travel sale; these two never carry the same money on the same task.
            'UNBILLED_SUPPLIER_COST',
            // CT-A3 E4 (CT-F38): a sales commission is not payroll. The engine's agent-commission
            // feeder used SALARY_EXPENSE (5160 Agent Salaries) / SALARY_PAYABLE (2201 Salaries &
            // Wages Payable) — a payroll pair — for what is a commission on a sale. These two
            // resolve to 'Commissions Expense (Agents)' (5130) and 'Commissions (Agents)' (2210),
            // the pair CoaSeeder already seeds and the legacy ledger already used. SALARY_EXPENSE
            // / SALARY_PAYABLE stay registered and keep their own, genuinely-payroll call site
            // (AgentController::update()'s monthly salary accrual).
            'COMMISSION_EXPENSE',
            'COMMISSION_PAYABLE',
            // CT-A3 E5 (CT-F37): the SERVICE_COST fallback target — see 'purpose_fallbacks' below.
            // On this chart every per-service cost group (Flights Cost/Hotels Cost/etc., 5110-
            // 5121) already has per-supplier children, so it fails AccountResolver::isLeaf() and
            // cannot itself be a SERVICE_COST leaf for a service type with no dedicated per-
            // service system_accounts row. Deliberately GLOBAL, not per_service: this is the one
            // shared "no per-service leaf mapped yet" landing spot, not a 13th per-service leaf —
            // minting one of those per service type is exactly the 13-orphan-leaf outcome CT-A2
            // had to hand-create and this fallback exists to avoid repeating. NOT registered in
            // SystemAccountsSeeder/EnsureSystemLeaves by this change (out of this build's file
            // scope) — a company with nothing mapped to this purpose code simply keeps hitting
            // UnmappedPurposeException on an unmapped service type, exactly as before this fix,
            // until an operator maps it via the Purpose Mapping screen.
            'COST_OF_SALES_CONTROL',
            // CT-A3 wave 2 (W2-3, CT-F11): the cost of a refunded booking that the supplier is
            // NOT giving back. Resolves to leaf 5126 'Supplier Refund Loss' under Direct Expenses
            // (Cost of Sales). Global, not per_service: a refund the agency ate is one number the
            // owner wants to see whole, not thirteen scattered across service types.
            'SUPPLIER_REFUND_LOSS',
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

        /*
        |--------------------------------------------------------------------------------------
        | Purpose fallback chains (CT-A3 E5, CT-F37)
        |--------------------------------------------------------------------------------------
        | AccountResolver::resolve() walks this chain, in order, ONLY when the direct
        | (company, purposeCode, serviceType) system_accounts lookup misses for a per_service
        | purpose code — a mapped per-service leaf still always wins, no behaviour change there.
        | Each key is one of 'per_service' above; each value is an ordinary GLOBAL purpose code
        | (service_type=null) resolved via the exact same resolve() safety checks (tenant, leaf,
        | disabled) as any direct hit. If a chain's own target purpose code is itself unmapped for
        | the company, resolve() falls through to the next entry and, when the chain is exhausted,
        | throws the same UnmappedPurposeException as an outright miss — naming both the original
        | per-service purpose code and every fallback code tried, never a silent skip.
        |
        | This is data, not code, so extending a chain (or adding one for SERVICE_REVENUE, should
        | that ever need one) never touches AccountResolver itself.
        */
        'purpose_fallbacks' => [
            'SERVICE_PAYABLE' => ['PAYABLE_CONTROL'],
            'SERVICE_COST' => ['COST_OF_SALES_CONTROL'],
        ],

        /*
        |--------------------------------------------------------------------------------------
        | Anchor purpose codes (W3.A2, Accounting Gap/22-plan-amendments.md §2.1a)
        |--------------------------------------------------------------------------------------
        | Resolved via AccountResolver::resolveAnchor(), NOT resolve() — these name a GROUP
        | account a per-party leaf mints under, not a direct posting-target leaf, so they are
        | kept in a dedicated sub-key rather than 'global' precisely so SystemAccountsSeeder's
        | existing `config('accounting.purpose_codes.global')` loop (which assumes every code it
        | iterates maps to a leaf-mappable, per-company `system_accounts` row it should seed
        | automatically) never sees them and never tries to auto-map them.
        |
        | Deliberately NOT seeded/mapped by this build (that is W3.A / P5.3.A territory, an
        | explicitly out-of-scope lane per this build's own brief) — listed here only so the
        | vocabulary is registered and `AccountResolver::resolveAnchor()` has named constants to
        | be called with, once a future lane populates the matching `system_accounts` rows:
        |   - AGENT_COMMISSION_PAYABLE_GROUP -> intended target account code 2230
        |     ("Agent Commission Payable" / legacy "Agent Profit Payable").
        |   - AGENT_RECEIVABLE_GROUP -> intended target account code 135900 ("Agent
        |     Receivables"), once P5.3.A mints it; resolves to the interim per-agent leaf before
        |     that lands.
        */
        'anchors' => [
            'AGENT_COMMISSION_PAYABLE_GROUP',
            'AGENT_RECEIVABLE_GROUP',
        ],

        /*
        |--------------------------------------------------------------------------------------
        | Fixed-asset classes (accounting-builds T0a, L7)
        |--------------------------------------------------------------------------------------
        | Expanded by SystemAccountsSeeder::resolveFixedAssetClasses() into FA_COST_{key} and
        | FA_ACCUM_DEP_{key} purpose codes, the SAME key-expansion pattern 'gateways' above already
        | establishes for GATEWAY_CLEARING_{key}/GATEWAY_FEE_EXPENSE_{key} — PurposeMappingIndex
        | expands this map the same way it expands 'gateways'.
        |
        |   - 'cost_code': the EXISTING 1810-1870 cost leaf (CoaSeeder already seeds these — no
        |     new leaf minted).
        |   - 'accum_dep_code': the NEW 1881-1887 per-class contra leaf, minted as a CHILD of 1880
        |     'Accumulated Depreciation' (converting 1880 from a leaf to a group) — GUARDED: both
        |     SystemAccountsSeeder::resolveFixedAssetClasses() and EnsureSystemLeaves refuse (report
        |     a gap, mint nothing) for a company whose 1880 already carries journal_entries lines,
        |     per L7. Confirmed clear on the akeed_verify_snapshot bench (T13/T0a dry-run, 2026-09-02:
        |     0 journal lines on account code 1880 across all 3 snapshot companies) — the fallback
        |     (siblings under 1800 instead of children of 1880, Q3) was NOT needed.
        */
        'fixed_asset_classes' => [
            'CAPITAL_EQUIPMENT' => ['label' => 'Capital Equipment', 'cost_code' => '1810', 'accum_dep_code' => '1881'],
            'ELECTRONIC_EQUIPMENT' => ['label' => 'Electronic Equipment', 'cost_code' => '1820', 'accum_dep_code' => '1882'],
            'FURNITURE_FIXTURES' => ['label' => 'Furniture & Fixtures', 'cost_code' => '1830', 'accum_dep_code' => '1883'],
            'OFFICE_EQUIPMENT' => ['label' => 'Office Equipment', 'cost_code' => '1840', 'accum_dep_code' => '1884'],
            'PLANT_MACHINERY' => ['label' => 'Plant & Machinery', 'cost_code' => '1850', 'accum_dep_code' => '1885'],
            'BUILDINGS' => ['label' => 'Buildings', 'cost_code' => '1860', 'accum_dep_code' => '1886'],
            'SOFTWARE' => ['label' => 'Software', 'cost_code' => '1870', 'accum_dep_code' => '1887'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sale posting basis, per service type (W3d, sale-shape-audit.md /
    | w3d-brief.md, binding decision 2026-08-28)
    |--------------------------------------------------------------------------
    |
    | Selects which of the two blueprint-consistent sale shapes
    | App\Services\Accounting\SaleDraftBuilder builds for a given service
    | type — see that class's own docblock for the exact lines each posts:
    |
    |   - 'agent' (NET): Dr RECEIVABLE_CONTROL / Cr SERVICE_PAYABLE / Cr-or-Dr
    |     SERVICE_REVENUE (margin, sign-aware). For services the agency
    |     arranges but does not control — IFRS 15 agent indicators: no
    |     inventory risk, no price-setting discretion, the supplier/airline/
    |     insurer is the obligor.
    |   - 'principal' (GROSS): Dr RECEIVABLE_CONTROL / Cr SERVICE_REVENUE
    |     (full sell) + Dr SERVICE_COST / Cr SERVICE_PAYABLE (cost-of-sales
    |     pair). For services the agency assembles/controls before handing to
    |     the client — IFRS 15 principal indicators: primarily responsible
    |     for fulfillment, bears inventory/pricing risk.
    |
    | 'default_by_service_type' is the LOCKED default per w3d-brief.md
    | decision 2. A company may override any one service type via the
    | per-company `settings` table (key convention
    | 'accounting.posting_basis.{service_type}', value 'agent'|'principal' —
    | see SaleDraftBuilder::resolvePostingBasis()'s own docblock), the SAME
    | table/key-namespacing convention App\Models\Company::hasModule() already
    | uses for 'module.*' overrides. 'hotel' defaults to 'agent' — the
    | brief's own carve-out: a company that holds its own hotel inventory
    | (own-inventory block allotments, not pass-through supplier bookings)
    | may flip it to 'principal' via that same override; this build does not
    | attempt to auto-detect "owns inventory" and never guesses 'principal'
    | for a company that hasn't explicitly asked for it.
    |
    | Gross turnover for BSP/segment/incentive reports is reconstructed from
    | the ticket's own statistical fields (fare, tax, BSP amount — already on
    | `tasks`/`invoice_details`), NEVER from a revenue account — see
    | Accounting Gap/19-report-data-contract.md. This option changes ONLY
    | which ledger accounts a sale hits; it never changes what those reports
    | compute, because they never read the ledger's revenue line to begin
    | with.
    |
    */
    'posting_basis' => [
        'default_by_service_type' => [
            'flight' => 'agent',
            'rail' => 'agent',
            'ferry' => 'agent',
            'esim' => 'agent',
            'insurance' => 'agent',
            'visa' => 'agent',
            'lounge' => 'agent',
            'hotel' => 'agent', // company may flip to 'principal' if it holds own inventory.
            'tour' => 'principal',
            'cruise' => 'principal',
            'car' => 'principal',
            'event' => 'principal',
        ],
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Revenue recognition timing, per service type (P2.5.D, p2_5-brief.md §P2.5.D; doc 22 §15.6,
    | IFRS 15)
    |--------------------------------------------------------------------------------------------
    |
    | Selects whether App\Services\Accounting\SaleDraftBuilder books a sale's revenue/cost
    | immediately or defers them — see that class's own docblock for the exact purpose-code
    | substitution ('at_travel' swaps SERVICE_REVENUE -> DEFERRED_REVENUE and, principal basis
    | only, SERVICE_COST -> PREPAID_SUPPLIER_COST):
    |
    |   - 'at_issue' (post at sale, unchanged from every pre-P2.5.D behaviour): the performance
    |     obligation (arranging the booking) is satisfied at ticketing — IFRS 15's agent-type
    |     recognition point.
    |   - 'at_travel' (defer at sale, release on the travel/check-in date via
    |     `accounting:recognize-revenue` — App\Services\Accounting\RevenueRecognitionService): the
    |     obligation (delivering the assembled service) is satisfied on the travel date — IFRS 15's
    |     principal-type recognition point.
    |
    | 'default_by_service_type' defaults THE SAME WAY 'posting_basis.default_by_service_type'
    | above does, per doc 22 §15.6's own words ("defaulting the same way §15.1's posting_basis
    | defaults do") — every agent-basis type below is 'at_issue', every principal-basis type is
    | 'at_travel'. A company may override any one service type via the same per-company `settings`
    | table / key-namespacing convention SaleDraftBuilder::resolvePostingBasis() already
    | established ('accounting.revenue_recognition.{service_type}', value 'at_issue'|'at_travel')
    | — see SaleDraftBuilder::resolveRecognitionTiming()'s own docblock. A company may therefore
    | choose 'agent' posting basis + 'at_travel' recognition for the same service type (e.g. a
    | hotel company that still treats itself as an agent for the supplier relationship but wants
    | to defer its margin to check-in) — the two options are independent axes, resolved and applied
    | independently by SaleDraftBuilder.
    |
    | WIRED CALL SITES (P2.5.D fix — verify finding): EVERY real `new SaleDraftInput(...)`
    | construction site in the codebase resolves `SaleDraftBuilder::resolveRecognitionTiming(
    | $companyId, $serviceType)` right alongside its existing `resolvePostingBasis()` call and
    | passes the result explicitly — the SAME convention as `$postingBasis`, so both the config
    | default AND a per-company override apply end-to-end with no further call-site change ever
    | needed. The original P2.5.D delivery wired only 6 of these (InvoiceController::
    | postSaleJournalEntries() and its four repost/correction siblings, ChatController::
    | postChatInvoiceTaskEntries()); a repo-wide grep found 2 more real, routed sites that were
    | left resolving ONLY the config default (silently dropping a per-company override) — now
    | also wired:
    |   - TaskController::reverseAndRepostSale() (private, shared by handleClientChange()/
    |     handleAmountChange() — reverses+reposts a task's live sale document on a client-name or
    |     total edit).
    |   - TaskStatusService::reissuePostNewSale() (private — posts a brand-new sale document on
    |     ticket reissue).
    | A SEPARATE, narrower gap in the same finding — App\Services\Accounting\
    | SupplierCostCorrectionDraftBuilder (the late/corrected-supplier-cost delta-document path,
    | not a SaleDraftInput construction site at all) unconditionally posting to SERVICE_COST/
    | SERVICE_REVENUE regardless of recognition timing — is fixed via
    | SupplierCostCorrectionInput::$recognitionTiming/$alreadyRecognized; see that class's own
    | "P2.5.D fix" docblock note.
    |
    | A test that specifically exercises the pre-P2.5.D GROSS/NET shape for a now-`at_travel`-by-
    | default service type (e.g. 'tour') and is not itself about recognition timing pins
    | `at_issue` via a `Setting` row on this same key — see
    | tests/Feature/Accounting/InvoiceControllerProfitLossPostingTest.php and
    | tests/Feature/Accounting/TaskControllerSupplierCostCorrectionTest.php for two such instances.
    |
    */
    'revenue_recognition' => [
        'default_by_service_type' => [
            'flight' => 'at_issue',
            'rail' => 'at_issue',
            'ferry' => 'at_issue',
            'esim' => 'at_issue',
            'insurance' => 'at_issue',
            'visa' => 'at_issue',
            'lounge' => 'at_issue',
            'hotel' => 'at_issue', // follows posting_basis.hotel; flip together if a company
            // holds its own inventory (see posting_basis's own comment on this key).
            'tour' => 'at_travel',
            'cruise' => 'at_travel',
            'car' => 'at_travel',
            'event' => 'at_travel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Voucher company options (W5.L, w5-brief.md §W5.L item 5)
    |--------------------------------------------------------------------------------------------
    |
    | Per-company overrides via the SAME `settings` table / Setting::getByKey() key-namespacing
    | convention SaleDraftBuilder::resolvePostingBasis() already established
    | ('accounting.posting_basis.{service_type}' — see that method's own docblock): a plain
    | Setting row keyed 'accounting.{name}' for company_id, read through
    | App\Services\Accounting\VoucherOptions rather than duplicating the fallback logic at every
    | RV/PV call site. These two keys are consumed by W5.R/W5.P's ReceiptVoucherController /
    | BankPaymentController (not built in this sub-wave) — W5.L only registers and resolves them.
    |
    |   - voucher_approval_threshold ('accounting.voucher_approval_threshold', Setting.type
    |     'string' holding a numeric value — the settings table's `type` enum
    |     (string|integer|boolean|json) has no 'float' member, and 'integer' would truncate a
    |     fils-level amount, so 'string' is the correct choice here; VoucherOptions casts it):
    |     nullable amount. NULL (no Setting row, or an explicit NULL value,
    |     which is this key's *_default below) means "always require a manual approve() step" —
    |     w5-brief.md §W5.R's existing single-tier behaviour, unchanged for a company that never
    |     sets this. A voucher amount <= the resolved threshold auto-approves on post; above it,
    |     the existing manual approve() gate applies.
    |   - pv_allow_overdraft ('accounting.pv_allow_overdraft', Setting.type 'boolean'): defaults to
    |     FALSE when unset (this key's own *_default below) — w5-brief.md §W5.P's PV bank-balance
    |     pre-check (TrialBalanceService, inside the same DB transaction as the post — replacing
    |     the raw-SUM TOCTOU race w5-state.md's as-is table names) refuses a payment that would take
    |     the named bank leaf negative unless a company has explicitly turned this on.
    |
    */
    'vouchers' => [
        'voucher_approval_threshold_default' => null,
        'pv_allow_overdraft_default' => false,

        // W5.R (w5-brief.md §W5.R "remainder -> Cr 2632 per company invoice_overpay_cancel_policy
        // (credit default | hold unapplied | block)"). Deliberately a SEPARATE key from
        // 'accounting.refund.invoice_overpay_cancel_policy' (RefundPostingService's own option,
        // vocabulary {credit, refund_out, manual}) even though both names describe "what happens
        // to money the company ends up holding beyond what a specific document needed" — they
        // govern two different real-world events (a REFUND's own overpay vs. an RV's overpay) with
        // two different vocabularies (this one has no 'refund_out'/'manual' member; RV never pays
        // cash back out as its own remainder disposition, and 'manual' doesn't apply since 'block'
        // already refuses the document outright rather than leaving something for a human to
        // decide later) and are resolved independently per company. See
        // App\Services\Accounting\VoucherOptions::overpayPolicy().
        //   - credit (default): the remainder posts Dr [instrument leaf] / Cr CLIENT_ADVANCE (2632)
        //     and is recorded as an ordinary, immediately spendable client credit.
        //   - hold: the SAME ledger posting (Dr instrument / Cr 2632) — the distinction from
        //     'credit' is a downstream/workflow one (an accountant must explicitly release it
        //     before it is applied to a future invoice), not a different account. See
        //     ReceiptVoucherController's own docblock on why this wave does not yet build that
        //     downstream release gate (P5.3/P5.18 territory — recorded on `invoice_receipts.
        //     remainder_policy` for a future consumer to honour).
        //   - block: the document is refused outright (no posting attempted at all) whenever
        //     applying every requested allocation would still leave a positive remainder.
        'rv_overpay_policy_default' => 'credit',

        // W5.R (w5-brief.md §W5.R "on approve call PaymentReceiptService::generateAndSendPdf()
        // when receipt_send_on_payment"). Defaults to FALSE — see
        // ReceiptVoucherController::sendReceiptPdfIfRequested()'s own docblock for the one real
        // integration gap this option runs into (PaymentReceiptService::generateAndSendPdf()
        // requires a real App\Models\Payment row; most RV flows have none).
        'rv_receipt_send_on_payment_default' => false,
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Supplier-side charges (W6.C, w6-brief.md "W6.C — Supplier-side charges")
    |--------------------------------------------------------------------------------------------
    |
    | - supplier_charge_override_policy ('accounting.supplier_charge_override_policy', Setting.type
    |   'string'): mirrors RefundController::applyRefundFeeSchedule()'s own
    |   'accounting.refund.fee_schedule.{type}.override' vocabulary ('free'|'needs_approval') for
    |   the supplier-charge-rule manual per-task override case — see
    |   App\Services\Accounting\SupplierChargeRuleResolver::applyManualOverride(). 'free' lets an
    |   operator's override amount take effect immediately, no approval step; 'needs_approval' (the
    |   shipped default, matching the refund fee schedule's own default) blocks a genuine (beyond
    |   the engine's balance tolerance) override from posting — throwing
    |   App\Exceptions\Accounting\SupplierChargeOverridePendingApprovalException — until the
    |   caller explicitly passes $approved=true (an approver has acted; the actual approve-step UI
    |   is W6.U's own scope, not built here).
    |
    */
    'supplier_charges' => [
        'override_policy_default' => 'needs_approval',
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Period close checklist gate (P2.5.C — p2_5-brief.md §P2.5.C, period-lock-design.md §5)
    |--------------------------------------------------------------------------------------------
    |
    | Consulted by App\Services\Accounting\PeriodCloseChecklistService — the month-end account
    | treatment table the brief names, per account class:
    |
    |   - 'control_purpose_codes': global AccountResolver purpose codes that name an AR/AP CONTROL
    |     leaf (class b — BLOCK on mismatch). Every company already has these mapped by
    |     SystemAccountsSeeder, so this check runs unconditionally for every company. Agent
    |     sub-ledger leaves (doc 11 §P5.3.A's still-unminted `135900 Agent Receivables` /
    |     `2230 Agent Commission Payable`) are NOT listed here — P5.3.A has not shipped
    |     (AGENT_RECEIVABLE_GROUP/AGENT_COMMISSION_PAYABLE_GROUP are registered anchors with no
    |     seeded target yet, per this file's own 'anchors' sub-key docblock) — the checklist
    |     resolves those two anchors defensively and SKIPS (not fails) a company that has no
    |     mapping yet, rather than block every close in the codebase on a leaf that doesn't exist.
    |   - 'clearing_rollforward_codes': class (c) accounts, by literal `accounts.code` (company-
    |     scoped lookup, never by name) — 2632 (client/agent advances), 1952 (Airline Memo
    |     Control), 2202 (payroll deduction clearing). Deferred-revenue / prepaid-supplier-cost
    |     leaves (P2.5.D, not this sub-wave) and inter-branch clearing (no such leaf exists yet in
    |     this codebase — repo-wide search, 2026-08-30) are deliberately absent; a company missing
    |     one of the codes below is skipped for that one row, not failed.
    |   - 'airline_memo_control_code': singled out because P2.5.C's monthly gate WARNS on a
    |     non-zero balance (design doc §5: "1952 non-zero is year-end-only, not monthly") while
    |     App\Services\Accounting\YearEndCloseService BLOCKS on the exact same code (doc 11 §P5.2:
    |     "close-year refuses while 1952 is non-zero") — one config value, two different gates at
    |     two different sub-wave call sites, so the code never drifts between them.
    |   - 'cash_bank_instrument_codes': class (a) accounts beyond the two named GROUPS
    |     ('bank_group_name'/'cash_group_name' above, already company-configurable) — the two
    |     PDC/instrument LEAVES W5.L minted (CHEQUES_IN_HAND 1215, CHEQUES_ISSUED_NOT_CLEARED
    |     2215), which sit as direct Assets/Liabilities children, outside either named group, and
    |     are therefore invisible to a "walk the bank/cash GROUP's children" query.
    |
    */
    'period_close' => [
        'control_purpose_codes' => [
            'RECEIVABLE_CONTROL' => 'Accounts Receivable (control)',
            'PAYABLE_CONTROL' => 'Accounts Payable (control)',
        ],
        'agent_control_anchors' => [
            'AGENT_RECEIVABLE_GROUP' => 'Agent Receivables',
            'AGENT_COMMISSION_PAYABLE_GROUP' => 'Agent Commission Payable',
        ],
        'clearing_rollforward_codes' => [
            '2632' => 'Client / Agent Advances',
            '1952' => 'Airline Memo Control',
            '2202' => 'Payroll Deduction Clearing',
        ],
        'airline_memo_control_code' => '1952',
        'cash_bank_instrument_codes' => [
            '1215' => 'Cheques in Hand',
            '2215' => 'Cheques Issued Not Cleared',
        ],
        // Tolerance for "control balance == sub-ledger sum" / "closing balance is zero" style
        // float comparisons — same value PostingService::post()'s own per-document balance check
        // uses (config('accounting.engine.balance_tolerance')), reused rather than inventing a
        // second tolerance constant for the same class of comparison.
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Reconciliation Center v0 (P2.5.G — p2_5-brief.md §P2.5.G; reconciliation-design.md §6/§9)
    |--------------------------------------------------------------------------------------------
    |
    | Consulted by App\Services\Accounting\ReconciliationCenterService (the grid + gap math),
    | App\Services\Accounting\ReconciliationAutoMatchService (the nightly `accounting:reconcile
    | --auto` internal proposal generator), and App\Services\Accounting\ReconciliationFixDraftService
    | (the FIX-NOW draft target-account resolution).
    |
    |   - 'ageing_over_days': the brief's own "ageing >30d" counter threshold.
    |   - 'fix_now_targets': per FIX-NOW kind, WHICH leaf the draft's non-gap side hits. A value
    |     with a leading '@' is an AccountResolver PURPOSE CODE (resolved via
    |     AccountResolver::resolve(), never by name); any other value is a literal, company-scoped
    |     `accounts.code` lookup (same convention 'clearing_rollforward_codes' above already uses)
    |     — '5147 Gateway Reconciliation Difference' has no purpose code registered in this
    |     codebase yet (doc 22 §A7 keeps it reserved, not yet seeded by CoaSeeder for every
    |     company), so it resolves by literal code and reports 'not_configured' for a company that
    |     hasn't minted it, exactly like 'clearing_rollforward_codes' already does for a missing
    |     leaf — never guessed onto an unrelated account.
    |
    */
    'reconciliation' => [
        'ageing_over_days' => 30,
        // P2.5.G verify fix — the owner refinement's "known timing differences" gap-explanation
        // component (App\Services\Accounting\ReconciliationCenterService::timingDifferenceLineIds()):
        // an unmatched line on a GATEWAY_CLEARING_* leaf, still inside this many days of its
        // posting date, is routine settlement lag, not an exception — a gateway line OLDER than
        // this stays a genuine unmatched/ageing item instead.
        'gateway_settlement_lag_days' => 5,
        'fix_now_targets' => [
            'bank_charge_pv' => '@BANK_CHARGES_EXPENSE',
            'gateway_timing_jv' => '5147',
            'writeoff_proposal' => '5218',
        ],
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Statement mode (P2.5.H — p2_5-brief.md §P2.5.H; doc 22 §16.2 "statement_mode option")
    |--------------------------------------------------------------------------------------------
    |
    | Consulted by App\Services\Accounting\StatementOptions (the company-option resolver) and
    | App\Services\Accounting\StatementService (the client/supplier/agent statement builder). Two
    | modes, per doc 22 §16.2: 'open_items' (default) shows only unsettled documents plus unapplied
    | receipts/credits, with ageing; 'full_activity' lists every document in the requested period.
    |
    |   - 'mode_default': the company-option default when no `Setting` row exists
    |     ('accounting.statement_mode', see StatementOptions::mode()).
    |   - 'ageing_buckets': the four bucket upper bounds (days since document date), per the
    |     brief's literal "30/60/90/120" — a 5th, open-ended "120+" bucket is implicit (anything
    |     older than the last bound).
    |   - 'unsettled_tolerance': float comparison tolerance for "settled_amount < amount" (an
    |     item within this of fully settled is treated as settled, not a fils-rounding artefact
    |     showing up forever as a stray open item) — same style of tolerance constant
    |     config('accounting.engine.balance_tolerance') already is for the posting engine, kept as
    |     its own separate value here (never homogenized with that one, same convention
    |     'engine.balance_tolerance' vs 'reconciliation.ageing_over_days' already established).
    |
    | Supplier statement data-source note (App\Services\Accounting\Statements\
    | SupplierLedgerStatementSource): as of P2.5.H, P5.3's ledger-level `journal_entries.
    | settled_amount` writer has not shipped (verified — see PeriodCloseChecklistService's own
    | docblock: the column is migrated but nothing populates it, and no party-master FK
    | (`clients.receivable_account_id` etc, doc 11 §P5.3) exists yet either). The supplier source
    | therefore derives open items directly from posted `journal_entries` on the PAYABLE_CONTROL
    | leaf (resolved via AccountResolver, never by name) filtered by `type_reference_id` =
    | supplier id, FIFO-matching settlement lines against charge lines at READ time — a read-only
    | projection, never a write to `settled_amount` or `accounts.actual_balance`. Once P5.3 ships
    | the real apply engine, only this one class's internals need to change; StatementItem/
    | StatementService's contract does not.
    |
    | INTERIM-DESIGN RATIFICATION (P2.5.H re-verify fix, prior verdict FAIL/MAJOR): the client and
    | agent sources read real, already-populated single-writer tables (PaymentApplication,
    | AgentSettlement) that predate this wave and are not P5.3 substitutes needing ratification —
    | only the supplier source is a genuinely new read-time projection built for this wave, because
    | no document-level supplier-bill model exists yet either (see SupplierLedgerStatementSource's
    | own docblock). P5.3 (party de-pool + open-item engine) is a separate, unscoped, later phase
    | (doc 22 §2.1 rows P5.3.A-F) — blocking P2.5.H's statements on it is not practical. This
    | interim projection is RATIFIED as this wave's design: it is isolated behind
    | PartyStatementSourceInterface (a one-class swap when P5.3 ships), it is disclosed to the
    | viewer on-screen and on the PDF (the "Derived from..." caption under the statement header —
    | see resources/views/accounting/statements/{show,pdf}.blade.php), and its internal arithmetic
    | is verified for self-consistency against the same posted journal_entries it reads (see
    | StatementServiceTest's supplier reconciliation test) — the strongest verification available
    | without a real P5.3 to compare against.
    |
    */
    'statements' => [
        'mode_default' => 'open_items',
        'ageing_buckets' => [30, 60, 90, 120],
        'unsettled_tolerance' => 0.001,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder engine v2 (P2.5.I)
    |--------------------------------------------------------------------------
    | p2_5-brief.md §P2.5.I; doc 22 §16.7. Config-level defaults for every reminder_kind this wave
    | ships a generator for, overridable per company via App\Services\Reminders\ReminderOptions
    | (Setting::getByKey(), same 'settings' table / key-namespacing convention StatementOptions and
    | VoucherOptions already use). `commission_unearned` has no cadence/offsets entry -- it is
    | event-driven (fired from RefundPostingService's un-earn post, see
    | App\Events\Accounting\CommissionUnearned), never swept by reminder:generate.
    | `task_unassigned`/`task_uninvoiced` are reserved target_type='task' kind labels only -- both
    | are already served today by standalone, already-scheduled direct-send commands
    | (reminder:unassigned-tasks / reminder:uninvoiced-tasks) that predate this wave and do not
    | route through the `reminders` table at all; P2.5.I does not duplicate them with a second,
    | table-backed generator (see this wave's own build report for the reasoning).
    */
    'reminders' => [
        'kinds' => [
            'overdue_invoice', 'statement_balance', 'ticketing_deadline', 'commission_unearned',
            'payment_link_uninvoiced', 'task_unassigned', 'task_uninvoiced', 'custom',
        ],
        // Kinds a company may toggle off individually; default enabled state per kind.
        // soud: all kinds ship OFF; opt in per company. See PLAN-ACCOUNTING-DELIVERY-V2-2026-09-01.md
        'default_enabled' => [
            'overdue_invoice' => false,
            'statement_balance' => false,
            'ticketing_deadline' => false,
            'commission_unearned' => false,
            'payment_link_uninvoiced' => false,
            'task_unassigned' => false,
            'task_uninvoiced' => false,
            'custom' => false,
        ],
        'default_channel' => 'whatsapp',
        // Daily-cadence generators run once, at this fixed time -- same "single fixed time for
        // every company" simplification P2.5.G's nightly reconciliation schedule already
        // documents (see routes/console.php's accounting:reconcile entry); a real per-company
        // timezone run is a later refinement, not a P2.5.I dependency.
        'daily_run_time' => '09:00',
        // Days-past-due-date at which `overdue_invoice` fires again for the same invoice (each
        // offset is its own dedupe_key, so re-running the generator on an off day is a no-op, not
        // a resend).
        'overdue_invoice_offsets_days' => [1, 3, 7, 14, 30],
        // Quiet hours: no reminder is ever scheduled inside this company-local window
        // (HH:MM-HH:MM); a generator that would land inside it shifts scheduled_at forward to the
        // window's end. Null (both empty) disables the shift entirely -- the P2.5.I default.
        'quiet_hours' => ['start' => null, 'end' => null],
        // Backlog safety fence (2026-09-02 hotfix): the reminder-engine-v2 migrations are pending
        // in production, so SendReminders' due-reminders query (no lower bound, no cap) would fire
        // the entire accumulated backlog to customers/staff in one minute the first time
        // process:reminder --proceed runs after they land. Guards live here, not in a migration.
        'send' => [
            // Global kill switch. FALSE by default -- process:reminder --proceed must be
            // explicitly re-armed (REMINDERS_SEND_ENABLED=true) before it will send anything.
            'enabled' => (bool) env('REMINDERS_SEND_ENABLED', false),
            // Pending rows scheduled further than this many hours in the past are treated as
            // expired backlog, not "still due" -- cancelled rather than sent.
            'max_age_hours' => (int) env('REMINDERS_SEND_MAX_AGE_HOURS', 48),
            // Hard per-run cap on how many reminders one --proceed invocation will send, oldest
            // eligible first. Independent of groupCapReached()'s own per-group number_of_reminder
            // cap.
            'max_per_run' => (int) env('REMINDERS_SEND_MAX_PER_RUN', 50),
        ],
        'generate' => [
            // GenerateHoldDeadlineReminders (reminder:generate-deadlines) scans ALL on-hold/
            // confirmed tasks with a deadline_at, with no lower bound -- a first run against
            // existing production data would backfill reminders for deadlines long past. A
            // computed scheduled_at older than now minus this many hours is skipped instead of
            // created.
            'stale_after_hours' => (int) env('REMINDERS_GENERATE_STALE_AFTER_HOURS', 24),
        ],
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Fixed assets (accounting-builds T0a-T4, L7/L8/Q4)
    |--------------------------------------------------------------------------------------------
    | Consulted by App\Services\Accounting\FixedAssets\FixedAssetService and DepreciationRunService
    | (Lane B). Straight-line only this phase — 'method' has one member, listed as a whitelist so a
    | future method addition is a config change, not a magic-string comparison.
    |   - 'depreciation_cadence': monthly, full month of depreciation in the in-service month (no
    |     pro-rata days) — Q4's assumed default, pending owner confirmation.
    */
    'fixed_assets' => [
        'methods' => ['straight_line'],
        'depreciation_cadence' => 'monthly',
        'pro_rata_days' => false,
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Supplier statement reconciliation — DOTW (accounting-builds T8, L14/L15/L16/Q5)
    |--------------------------------------------------------------------------------------------
    | Consulted by App\Services\Accounting\Reconciliation\SupplierStatementImporter/Matcher
    | (Lane E). Column map is CONFIG, with a per-import override in the UI (L15) — the real DOTW
    | column names are unknown until a real sample file is seen (Q5); this default is a guess,
    | not a fact, and MUST be overridden per-import until confirmed.
    */
    'supplier_statements' => [
        'dotw' => [
            'columns' => [
                'booking_ref' => 'Booking Reference',
                'confirmation_code' => 'Confirmation Code',
                'guest' => 'Guest Name',
                'checkin' => 'Check-in Date',
                'checkout' => 'Check-out Date',
                'amount' => 'Amount',
                'currency' => 'Currency',
                'statement_date' => 'Statement Date',
                'statement_line_reference' => 'Statement Line Ref',
                'description' => 'Description',
            ],
            // Required for a row to be importable at all — every other mapped column is optional
            // (missing/blank cells are tolerated per row; a required column missing from the FILE
            // itself rejects the whole import, see SupplierStatementImporter).
            'required_columns' => ['booking_ref', 'amount', 'currency'],
        ],
        // L16: DOTW match tolerance is base-currency 0.001 KWD (or original_amount when the
        // statement currency equals the line's original_currency), no date window — booking-ref
        // keyed, not date-windowed.
        'match_tolerance' => 0.001,
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Bank statement import + auto-match (accounting-builds T9, L15/L16)
    |--------------------------------------------------------------------------------------------
    | Consulted by App\Services\Accounting\Reconciliation\BankStatementImporter and
    | ReconciliationAutoMatchService::detectBankStatementMatches() (T9, Wave 2). Column map is
    | CONFIG with a per-import override in the UI (L15).
    */
    'bank_statements' => [
        'columns' => [
            'value_date' => 'Value Date',
            'posting_date' => 'Posting Date',
            'description' => 'Description',
            'reference' => 'Reference',
            'auth_no' => 'Auth No',
            'cheque_no' => 'Cheque No',
            'debit' => 'Debit',
            'credit' => 'Credit',
            'running_balance' => 'Balance',
        ],
        // Required for a row to be importable at all — every other mapped column is optional
        // (missing/blank cells tolerated per row; a required column missing from the FILE itself
        // rejects the whole import, see BankStatementImporter). 'debit'/'credit' are both
        // required as HEADER columns even though any given row only ever populates one of them.
        'required_columns' => ['value_date', 'debit', 'credit'],
        // Post-sign-off fix (T9 §12 note 2): a Kuwaiti bank export's date column is dd/mm/yyyy,
        // not the ISO shape `Carbon::parse()` guesses at. `date_format` is CONFIG with a
        // per-import override in the UI (same L15 convention as `columns` above); every candidate
        // format is tried strictly (`Carbon::createFromFormat`, no silent rollover/guessing) —
        // the primary format first, then each fallback in the order listed, first strict match
        // wins. A value that matches none of them rejects the whole import (BankStatementImportRejected,
        // 422 over HTTP — never a 500, never a silent misread). An XLSX date-formatted cell (a
        // numeric Excel serial, not a string) bypasses this list entirely and is converted via
        // PhpSpreadsheet's own Shared\Date — see BankStatementImporter::resolveDateCell().
        'date_format' => 'd/m/Y',
        'date_format_fallbacks' => ['Y-m-d', 'd-m-Y', 'd/m/Y H:i'],
        // L16: bank match tolerance 0.001 KWD on base amounts, date window ±3 days.
        'match_tolerance' => 0.001,
        'date_window_days' => 3,
    ],

    /*
    |--------------------------------------------------------------------------------------------
    | Supplier payable accrual — OWNER RULING R-CT3, 2026-09-09 (CT-A3 wave 1)
    |--------------------------------------------------------------------------------------------
    | Owner, verbatim: "need to pay are the one guaranteed to be paid not hold or some supplier
    | confirmed so this needs to be done based on the status of supplier which we set on supplier
    | aspect ... from there decide add or not add? need to be paid or not paid ... we need to do
    | the same as we go through the system."
    |
    | THE PATTERN, which every future automatic posting follows: the TRIGGER and the ACCOUNT come
    | from configured master data (the supplier record, the task's status, the payment's status),
    | never from a constant compiled into a feeder. This block is the vocabulary half — which task
    | statuses each configured `suppliers.payable_trigger` treats as "the money is now guaranteed".
    | The per-supplier CHOICE lives on the supplier row (migration
    | 2026_09_09_000001_add_payable_trigger_to_suppliers_table.php); the resolver that joins the two
    | is App\Services\Accounting\SupplierPayableRule. There is no supplier name, id or list
    | anywhere in either.
    |
    | Statuses are `tasks.status` values (the enum widened by migration
    | 2026_08_29_140002_w6s_widen_status_enum_in_tasks_table.php), which is itself the OUTPUT of
    | `TaskStatusService::mapStatus()` resolving the supplier's own raw status through
    | `supplier_status_maps`. So a supplier who calls its confirmed state "OK" or "RQ" is already
    | normalised before this table is consulted — this block never sees a raw supplier string.
    |
    | Every status NOT listed under a trigger is, by construction, not committed for that trigger:
    | 'on hold', 'needs_review', 'expired', 'cancelled', 'void', 'refund' and 'refunded' appear in
    | no list, so no trigger ever accrues on them. That is the "not hold" half of the ruling.
    |
    | 'on_voucher' carries the same statuses as 'on_issue' plus one extra condition the resolver
    | applies (a voucher must actually exist on the task) — see SupplierPayableRule::isCommitted().
    */
    'supplier_payable' => [

        // The per-supplier default applied when `suppliers.payable_trigger` is null/unrecognised
        // (a row written before the migration, or a hand-edited value). Matches the migration's
        // own column default and the legacy ledger's measured behaviour — CT-A1 §1.7's only
        // supplier-payable writer, TaskController.php:2315, fired at `issued`.
        'default_trigger' => 'on_issue',

        'triggers' => [
            'on_supplier_confirm' => ['confirmed', 'issued', 'reissued', 'ticketed', 'emd'],
            'on_issue' => ['issued', 'reissued', 'ticketed', 'emd'],
            'on_voucher' => ['issued', 'reissued', 'ticketed', 'emd'],
            'manual' => [],
        ],

        // `tasks.voucher_status` values that mean "no voucher was actually raised". Compared
        // case-insensitively after trimming; an empty/null column is always treated as no voucher.
        // Only consulted by the 'on_voucher' trigger.
        'voucher_negative_statuses' => ['cancelled', 'canceled', 'void', 'voided', 'failed', 'none', 'pending'],

        // Task statuses that REVERSE an already-posted accrual (the mirror of the trigger lists
        // above). A task that was committed and later lands on one of these has its issuance
        // document reversed by PostingService::reverse() -- never deleted, never UPDATEd.
        'reversing_statuses' => ['void', 'cancelled', 'refund', 'refunded', 'expired'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipts (CT-A3 wave 2, W2-2) - OWNER RULING R-CT3 applied to money IN
    |--------------------------------------------------------------------------
    |
    | Wave 1 set the pattern on the supplier payable: the TRIGGER and the ACCOUNT come from
    | configured master-data status, never from a code constant. These two blocks are the same
    | thing for receipts, read by App\Services\Accounting\ReceiptPostingRule and by nothing
    | else.
    |
    | The defaults reproduce the behaviour that was hard-coded in ReceiptVoucherController before
    | wave 2, so turning this on changes no existing number - EXCEPT for `bounced`, which was
    | previously in no list at all: bounce() reversed the cheque-CLEARANCE journal but left the
    | receipt document itself (Dr cheque-in-hand / Cr AR) standing and the invoice marked paid, so
    | a bounced cheque quietly collected a receivable that was never collected. Listing it here as
    | a reversing status is the fix.
    */
    'receipt' => [

        // Statuses at which a receipt document MUST be on the ledger.
        'posting_statuses' => ['approved'],

        // Statuses that take an already-posted receipt back OFF the ledger, through
        // PostingService::reverse() - a dated REV document, never an UPDATE or a delete.
        //   bounced  - the cheque did not clear; the money never arrived.
        //   reversed - an operator reversed the voucher (destroy()).
        //   rejected - the voucher was refused at approval.
        'reversing_statuses' => ['bounced', 'reversed', 'rejected'],

        // Statuses that carry no ledger footprint at all: drafted, not yet actioned.
        'draft_statuses' => ['pending'],

        'instrument' => [
            // The purpose code used ONLY when a receipt names no settlement channel and carries no
            // explicit bank account - a genuine over-the-counter cash receipt. Every other receipt
            // resolves its account from the payment method's own configured `charges.acc_bank_id`.
            // Each use is logged as accounting.receipt.instrument.fallback_used so the payment
            // methods still missing an account are findable rather than invisible.
            'fallback_purpose' => 'CASH_IN_HAND',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier refunds (CT-A3 wave 2, W2-3) - R-CT3, the recovery direction
    |--------------------------------------------------------------------------
    |
    | Wave 1's `supplier_payable` block answers "is this cost a guaranteed liability yet?".
    | This one answers the mirror question: "is the supplier actually giving the money back?".
    | Read by App\Services\Accounting\SupplierRefundRule and by nothing else.
    |
    | CT-A1 CT-F11 is the finding these defaults exist to close: 319 legacy refund lines
    | (KWD 57,891.068) credited a COGS leaf while the cost sat in asset 1430, and 367 refunded
    | tasks never had their revenue reversed at all. The engine's own RefundPostingService
    | carried the same assumption from the other side - it credited the FULL original cost back
    | on every refund, so "nobody recorded what the supplier did" read as "the supplier refunded
    | in full", erasing a cost the agency had genuinely borne.
    |
    | Note these defaults deliberately do NOT reproduce the legacy behaviour, unlike wave 1's
    | `on_issue`. CT-F11 says the legacy behaviour was wrong; reproducing it would preserve the
    | defect.
    */
    'supplier_refund' => [

        // Applied when `suppliers.refund_trigger` is null or unrecognised. The conservative
        // choice on purpose: a cost leaves the books only once the supplier has actually
        // confirmed, or an operator has explicitly typed the amount.
        'default_trigger' => 'on_supplier_refund_confirmed',

        // Each trigger -> the `tasks.status` values at which this supplier's money counts as
        // recoverable. `tasks.status` is already the normalised output of `supplier_status_maps`
        // (W6.S), so a supplier's own raw spelling never reaches here.
        //   refunded - the supplier has confirmed and the money is coming back.
        //   refund   - we have ASKED; only a supplier with a standing agreement counts that.
        'triggers' => [
            'on_supplier_refund_confirmed' => ['refunded'],
            'on_refund_request' => ['refund', 'refunded'],
            // Recovery only ever from an explicitly typed refund_details.supplier_refund_amount.
            'manual' => [],
            // This supplier does not refund. The cost stays with us, always.
            'never' => [],
        ],
    ],

];
