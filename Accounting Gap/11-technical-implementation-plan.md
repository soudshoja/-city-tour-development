# 11 — Master Technical Implementation Plan

> **Amendments applied from doc 22 rev 3 (2026-08-27).** E1 (Phase map: rows added for P5.12/P5.13/P5.16/P5.17/P7.5), E2 (P5.3: branch GL pointers, `SystemCurrency`, FIFO auto-allocation, config-key read-once rule, P5.3.A COA additions), E3 (P5.2: serial schemas forward at year-end, live P&L injection into the TB, `close-year` refuses while `1952` is non-zero), E4 (P5.4: Day Book, comparative-period column, Balance-Sheet group summary + comparatives, FC ledger, multi-line invoice consolidation), E5 (P5.8 retitled; BSP fragment removed, ships in P7.5), E6 (P5.9/P5.11 MF-citation corrected to MF-48), E7 (P7: eight items folded in from 22 §3), E8 (§C3 defer list: fifteen items recorded explicitly, decision-by-silence closed), E9 (Appendix B: table moves + additions), E10 (Appendix C note 2: `actual_balance` DROP decision + corrected 137-reference/31-file count), E11 (P2 wave table: 22 §2 fold-in pointers on W3–W6, incl. W3.A2/W3.F/W3.G, W4.C/W4.D, and the W6 void wave), E18 (P5.7: column-creation struck — `cost_centers` master + columns now created in W3), E20 (P5.4: `posting_status` filter invariant on every `LedgerReportQuery` query). Source text: [22 — Plan Amendments](22-plan-amendments.md) §5.

**Turning citytourv2 into a sellable, production-grade travel-agency accounting product.**

Audience: a developer or agent fleet that will build directly from this document. It is written so you do **not** need to re-derive the audit. Every phase cites the audit finding IDs it closes (bugs `BUG-*` from [08](08-prioritized-bug-list.md), features `MF-*` from [09](09-prioritized-missing-features.md), golden-rule violations `R1..R8` from [golden-rules-integration.md](golden-rules-integration.md), tenant findings `T1..T13` from [tenant-isolation-audit.md](tenant-isolation-audit.md), concurrency findings `F1..F14` from [concurrency-idempotency-audit.md](concurrency-idempotency-audit.md), and prod-data checks `1.1..9.8` from [data-integrity-results-prod-copy.md](data-integrity-results-prod-copy.md)).

All paths are relative to the repo root (`app/…`, `database/…`). Code skeletons show **contracts** (signatures + invariants as comments), not finished implementations. Migration snippets use real, existing column names and the codebase's conventions (Laravel 11, `Schema::create`/`Schema::table`, 3-decimal money).

Complexity key: **S** ≤ 2 dev-days · **M** ~1 week · **L** ~2–4 weeks · **XL** > 1 month.

---

## 0. The one decision that orders everything

**Build the posting engine (P1) and cut the 131 write-sites over to it (P2) BEFORE repairing production data (P3).**

The prod copy shows 2,273 unbalanced posting groups (~11% of all transactions, check 1.1), 7 invoices with no journal entries (2.1), 2 cross-tenant journal rows (7.1), 46 sign-error lines (8.1), 51 negative money fields (8.1+8.5). Those numbers are not history — they are **the current output** of the live write paths. If you rebalance the ledger while `PaymentApplicationService`, `ChatController`, `CheckMyFatoorahPayments`, and the invoice edit paths still hand-roll one-sided documents, the corruption simply re-accrues. Repair is only durable once the engine is the **sole writer** and the old paths are deleted. Hence the strict order: **stop the bleeding (P0) → build the engine (P1) → make it the only writer (P2) → then and only then repair the wound (P3)**.

The second-order decision: P0 (the prod hotfix list, [file 12](12-prod-hotfix-list.md)) ships **immediately and independently** of everything else, because it closes a live cross-tenant funds-transfer primitive and an internet-facing invoice/PII enumeration hole that are being exploited-capable *today*. P0 does not touch architecture; P1–P9 do.

---

## Phase map

| Phase | Theme | Complexity | Depends on | Sole new capability |
|------|-------|:---------:|-----------|---------------------|
| **P0** | Emergency prod hotfixes | S–M | — | Stops active harm; see **[file 12](12-prod-hotfix-list.md)** |
| **P1** | The PostingService (the heart) | XL | P0 | One balanced, leaf-only, tenant-safe, idempotent writer |
| **P2** | Migrate the 131 call sites (strangler) | XL | P1 | Engine becomes the *only* writer of `journal_entries` |
| **P3** | Data repair on staging → prod | L | P2 | Corrupt ledger rebalanced, corruption stopped at source |
| **P4** | Tenant hardening (structural) | L | P0, P2 | `company_id` on invoices + model scoping + record-ownership policies |
| **P5** | Blueprint accounting gaps | XL | P3, P4 | Periods, opening journal, year-end close, AR/AP open-item, reporting rebuild, fixed assets, FX reval, cost centre, travel controls |
| **P6** | Shareholder equity module | L | P5.2 | Per-shareholder capital, dividends, profit distribution, SOCE |
| **P7** | Product layer | L–XL | P5.4 | COA templates on signup, auditor exports, period-lock UI, opening-balance import, external-auditor role, Arabic/RTL |
| **P8** | XBRL / iXBRL compliance | L | P5.4, P7 | IFRS taxonomy mapping, iXBRL export, Arelle validation sidecar |
| **P9** | GCC VAT + ZATCA e-invoicing | XL | P1, P7 | VAT engine, tax codes, KSA Fatoora phase-2 e-invoicing |
| **P5.12** *(22 §3)* | Credit control | — (22 §3) | P5.3, P5.3.F | Block/warn a credit sale to an over-limit or blacklisted party |
| **P5.13** *(22 §3)* | Agent sub-ledger → doc 20 | — (22 §3) | W3, P5.3.A–D, P5.4 | `agent_charge_policies`, charge/settlement documents, agent statement |
| **P5.16** *(22 §3)* | Balance strategy | — (22 §3) | P5.4 | Measured ceiling for ledger-derived reporting vs `account_monthly_balances` |
| **P5.17** *(22 §3)* | Audit trail, integrity checks, `Fix*` retirement | — (22 §3) | P3 | `*_log` history, drift checker, retirement of the nine `Fix*` commands |
| **P7.5** *(22 §3)* | BSP reconciliation + ADM/ACM | — (22 §3) | P5.3.D, 15 Stage C, MF-27, P5.3.A, P5.3.E *(five prerequisites — 22 §6.1)* | Ticket-by-ticket BSP variance; ADM/ACM memo posting |

**Critical path:** P0 → P1 → P2 → P3 → P4 → P5 → {P6, P7} → P8 → P9. P6 and P7 fan out after P5; P8 needs P7's statement layer; P9 is largely independent of P6–P8 and can be pulled forward for the KSA market once P1 (tax-aware lines) and P7 (e-invoice document model) exist.

**Deviation from the [10-implementation-roadmap.md](10-implementation-roadmap.md) sketch:** that sketch put COA hardening in its own Phase 1 before the PostingService. This plan folds the *minimum* COA prerequisites the engine cannot run without — the system-account/purpose-code registry, `is_group`/leaf truth, the account-code generator, and derived `account_type` — into **P1 as workstream P1.0**, and defers the rest of COA hardening (party-master FKs for de-pooling AR/AP) into P5.3 where it is actually consumed. This keeps a clean 10-phase (P0–P9) product spine while honouring the true dependency that the engine needs a registry to resolve accounts by role instead of by name string (the root cause of BUG-H6 / R7 / T-Finding-1).

---

## Cross-cutting foundations (apply to every phase)

### C1 — The invariant test suite (build this first, in P1, before any posting code)
A trait `Tests\Concerns\AssertsLedgerBalanced` with the assertions every phase re-uses:
- `assertTransactionBalanced(int $transactionId)` — `abs(Σdebit − Σcredit) < 0.0005` for the group.
- `assertCompanyLedgerBalanced(int $companyId, ?period)` — the trial balance for the company/period has `is_balanced === true` (reuse `TrialBalanceService::generate(...)['totals']['is_balanced']`).
- `assertNoOrphanLines(int $companyId)` — no `journal_entries` with `transaction_id = NULL`.
- `assertNoLeafViolations(int $companyId)` — no active line whose account has children (check 6.1 shape).
- `assertNoCrossTenantLines(int $companyId)` — every line's `account.company_id === je.company_id` (check 7.1 shape).

**Global invariant:** *after any operation exercised by any test in the accounting suite, the acting company's trial balance still balances.* Wire this as a Pest `afterEach`/PHPUnit `tearDown` hook in a base `AccountingTestCase` so every posting-touching test enforces it whether or not the author remembered to. This is the single mechanism that keeps P1–P9 from regressing the ledger.

### C2 — Feature-flag / cutover approach (strangler)
- A per-company boolean `companies.posting_engine_enabled` (migration in P1) plus a global kill-switch `config('accounting.engine.enabled')` (env `ACCOUNTING_ENGINE_ENABLED`).
- Every migrated call site in P2 calls the engine through one seam. During cutover the seam supports three modes per company: `legacy` (old code only), `shadow` (old code writes; engine **dual-writes to a scratch schema and diffs** — logs mismatches, does not affect books), `live` (engine only; legacy code deleted or short-circuited). Move companies `legacy → shadow → live` one at a time; the 3-company prod tenant set makes this tractable.
- A CI architecture test (`tests/Architecture/PostingWritesTest.php`) that greps the app tree and **fails the build if `JournalEntry::create`, `new JournalEntry`, `JournalEntry::insert`, or `JournalEntry::...->update(['debit'|'credit'…])` appears outside `App\Services\Accounting\`.** This is the ratchet that stops the 131-site problem from ever regrowing (R7 recommendation 4).

### C3 — What we explicitly defer (out of scope for v1 sellable product)
Inter-company posting (single legal entity per tenant today — MF-50); air-cargo/GSA module (MF-31); GDS segment-target/incentive-accrual analytics beyond the cheap wins (MF-29 phase-2, MF-36 phase-2); ticket mod-7 check-digit validation (MF-31); the generic `tblMaster` polymorphic-lookup consolidation (existing per-table lookups are an acceptable alternative — MF-50); staff-commission bracket tables (MF-50); OFX bank-statement parsing (CSV only in v1 — MF-45); worldwide multi-jurisdiction tax beyond Kuwait(no-VAT)/GCC(VAT)/KSA(ZATCA) — other jurisdictions are a P9-follow-on, not v1.

**Explicitly deferred with the decision recorded, rather than deferred by silence (22 §5.2 E8):** CTR accrual-timing postings (03-4 / N11 — deferral sanctioned by the blueprint itself); BDS bulk daily sheet (03-5); XO exchange-order document (03-31); front-office counter receipt (03-14); running-balance history from the account log (02-25); the denormalised `DC` column (02-5); the serial-number shelf (06-5 / N28 — we use row locks; the consequence is that a race loser burns a number, producing gaps, never duplicates — documented here for auditors); protected users (07-26); staff sales targets (07-30); per-account posting-date windows (01-13); the per-account multi-currency gate (01-10); `DiscountAcc` (06-23); `tblTRVControls` (06-10); monthly serial reset modes (06-6); ageing by cost centre (05-25). **Bulk void (04-30) is NOT on this list** — it is built in **W6** as a loop-in-one-transaction (22 §2.4, orchestrator ruling O6), not deferred.

---

## P0 — Emergency prod hotfixes (S–M)

**Goal:** eliminate the actively-exploitable and actively-corrupting defects on the *current* `tour.citycommerce.group` production with surgical diffs and no architecture change.

**This phase is specified in full in [12-prod-hotfix-list.md](12-prod-hotfix-list.md)** — HF-1..HF-11, ordered by exploitability × impact, each with exact file/method, minimal diff, test, deploy risk, effort. Ship it first and independently.

**Closes:** T-Finding-1..6 (cross-tenant), F1–F6 (gateway double-post), BUG-C3 SQL-injection portion, BUG-C5 (report auth), BUG-H6 (:6143 hotfix), BUG-H8 (`filterLedgers` crash), BUG-H9 (soft-delete raw totals), BUG-H3 (delete authz + cascade), BUG-M4 (dead routes). One-time baseline: run `data-integrity-queries.sql` and record counts (this is P3's "before").

---

## P1 — The PostingService (XL) · the keystone

**Goal:** exactly one code path may write `journal_entries`, and it is impossible for it to write an unbalanced, non-leaf, negative, cross-tenant, or duplicate document. Every invariant from golden rules 1–3, 7, 8 and the six `spAccDetailInsertUpdateSingleItem` validations (blueprint 02 §3) is enforced here atomically or nowhere.

**Depends on:** P0.

### P1.0 — COA prerequisites the engine cannot run without

Deliverables (migrations + services), in this order:

**Migration `create_system_accounts_table`** — the purpose-code registry that replaces ~237 name-string lookups. This is the fix vehicle for R7.3 / BUG-H6 / T-Finding-1's account half.

```php
Schema::create('system_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
    $table->string('purpose_code', 64);           // e.g. RECEIVABLE_CONTROL, GATEWAY_CLEARING, RETAINED_EARNINGS, FX_GAIN_LOSS, VAT_OUTPUT, SUSPENSE
    $table->string('service_type', 32)->nullable(); // task type for per-service revenue/payable/cost (flight, hotel, visa…); NULL for global controls
    $table->foreignId('account_id')->constrained('accounts');  // must be a leaf, same company
    $table->timestamps();
    $table->unique(['company_id', 'purpose_code', 'service_type']);
    $table->index(['company_id', 'purpose_code']);
});
```

**Migration `add_engine_columns_to_accounts_table`** — make the tree machine-trustworthy:
```php
Schema::table('accounts', function (Blueprint $table) {
    // is_group already exists but is unreliable (check 6.2: 42,401 vs 6.1: 25) — recompute in backfill below.
    $table->softDeletes();                                   // BUG-H3: stop hard-delete of ledger history
    $table->unsignedBigInteger('created_by')->nullable();    // MF-13 / R8 audit attribution
    $table->unsignedBigInteger('updated_by')->nullable();
    // account_type stays but becomes DERIVED + non-fillable (see AccountService); no schema change needed.
});
```
Backfill job (data migration, idempotent): `UPDATE accounts SET is_group = EXISTS(child)` for every account (fixes the over-broad flag behind check 6.2); then verify check-6.1 count is the only leaf-violation source.

**Migration `widen_money_columns`** (BUG-M5) — one wave, do it now while volumes are low and before repair:
```php
// journal_entries: debit/credit/balance/amount  -> decimal(18,3); exchange_rate -> decimal(18,6)
// accounts: actual_balance/opening_balance/budget_balance/variance -> decimal(18,3)
// transactions: amount -> decimal(18,3)
// All via ->change(); requires doctrine/dbal. KWD = 3 fils decimals, so 3dp is the money standard; 6dp for rates.
```

**Migration `create_serial_schemas_table`** + **`SequenceService`** (BUG-H10, F6/F10/F13) — atomic, per-tenant numbering the engine uses for voucher/doc numbers:
```php
Schema::create('serial_schemas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');
    $table->foreignId('branch_id')->nullable();
    $table->string('doc_type', 8);          // INV, RV, PV, JV, CRN, DBN, OJV, REV…
    $table->unsignedSmallInteger('doc_year');
    $table->string('mask')->default('{TYPE}-{YYYY}-{SEQ:5}');
    $table->unsignedBigInteger('last_serial')->default(0);
    $table->unsignedInteger('increment')->default(1);
    $table->timestamps();
    $table->unique(['company_id', 'branch_id', 'doc_type', 'doc_year']);
});
```
```php
namespace App\Services\Accounting;

final class SequenceService
{
    /**
     * Atomically reserve the next document number for a company/branch/type/year.
     * INVARIANT: MUST be called inside the caller's DB::transaction; uses SELECT … FOR UPDATE.
     * Returns [formattedNumber, numericValue]. Never returns a value already returned.
     * Replaces every ad-hoc `Sequence::firstOrCreate → read → increment` (F6/F10/F13).
     */
    public function next(string $docType, int $companyId, ?int $branchId, \DateTimeInterface $date): array;
}
```

**`AccountService`** (MF-1) + **`AccountCodeGenerator`** (BUG-H1) + derived `account_type` (BUG-H2):
```php
namespace App\Services\Accounting;

final class AccountService
{
    /**
     * The ONLY way to create an account. Enforces the blueprint's nine rules:
     *   parent mandatory (except the ≤6 fixed roots), depth ≤ 6, level = parent.level + 1,
     *   no mixed parents, root_id + account_type DERIVED from the root (never from request),
     *   code from AccountCodeGenerator, is_group = false at birth, company scope.
     * A `creating` model observer backstops this so imports/tinker cannot bypass it.
     */
    public function create(array $attrs): Account;

    /** Auto-create the per-party leaf under the right group (reuse the working AgentController tree pattern). */
    public function ensurePartyLeaf(string $purposeCode, Model $party, int $companyId): Account;
}
```
Refactor the 10+ creation sites (`AgentController:451`, `BranchController:139`, `ChargeController:232/253`, `SupplierCompanyController:154`, `TaskController:1682`, `InvoiceController:1536`, `ChatController:1140`, `ImportChartOfAccounts`, `CoaController::addCategory/updateCode`) onto `AccountService`. Clean the `CoaSeeder` duplicate codes (`2130`, `4130`) and names (`Clients`, `Payment Gateway`), then:
```php
Schema::table('accounts', function (Blueprint $table) {
    $table->unique(['company_id', 'code']);              // after de-dup
    $table->unique(['company_id', 'parent_id', 'name']); // after de-dup
});
```

**Seeder `SystemAccountsSeeder`** — for each existing company, map every `purpose_code` to its leaf (RECEIVABLE_CONTROL, PAYABLE_CONTROL, GATEWAY_CLEARING per gateway, RETAINED_EARNINGS→`3400`, FX_GAIN_LOSS→`5219`, SUSPENSE, per-`service_type` revenue/payable/cost). This is what lets P2 resolve accounts by role.

### P1.1 — The posting engine proper

**Migration `add_document_columns_to_transactions_table`** — turn the quasi-header into a real one (MF-41):
```php
Schema::table('transactions', function (Blueprint $table) {
    $table->string('doc_type', 8)->nullable()->after('reference_type');       // INV/RV/PV/JV/CRN/DBN/OJV/REV
    $table->string('sub_type', 16)->nullable()->after('doc_type');
    $table->unsignedSmallInteger('doc_year')->nullable()->after('sub_type');
    $table->enum('posting_status', ['draft','posted','reversed','void'])->default('posted')->after('doc_year'); // existing rows = posted
    $table->decimal('total_debit', 18, 3)->default(0)->after('amount');
    $table->decimal('total_credit', 18, 3)->default(0)->after('total_debit');
    $table->foreignId('reversal_of_transaction_id')->nullable()->after('total_credit'); // idempotency + reverse-then-apply (R4, BUG-H7)
    $table->string('idempotency_key')->nullable()->after('reversal_of_transaction_id');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('posted_by')->nullable();
    $table->timestamp('posted_at')->nullable();
    $table->unique(['company_id', 'idempotency_key']);   // DB-level idempotency backstop (F2/F8/F9)
    $table->index(['company_id', 'doc_type', 'doc_year']);
    $table->index('reversal_of_transaction_id');
});
```
`journal_entries.transaction_id` stays nullable in P1 (orphan lines exist in prod, check would fail) — it is made **NOT NULL in P3** after the backfill. The engine always sets it.

**The value objects** (`app/Services/Accounting/`):
```php
/** Immutable description of one balanced document to post. Built by feeders; validated by PostingService. */
final class DocumentDraft
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $branchId,
        public readonly string $docType,          // INV/RV/PV/JV/CRN/DBN/OJV/REV
        public readonly ?string $subType,
        public readonly \DateTimeInterface $docDate,   // this IS transaction_date — the ONE period column (BUG-C4)
        public readonly string $narration,
        /** @var LineDraft[] */ public readonly array $lines,
        public readonly ?string $idempotencyKey = null, // gateway txn id, payment id, task id… (F2)
        public readonly ?int $sourceType = null,        // reference_type
        public readonly mixed $sourceId = null,         // invoice_id / payment_id / task_id
        public readonly ?int $userId = null,
        public readonly ?int $costCenterId = null,
    ) {}
}

/** One one-sided line. Feeders name accounts by PURPOSE, never by string. */
final class LineDraft
{
    public function __construct(
        public readonly string $purposeCode,      // resolved via AccountResolver; OR explicit accountId for user-picked lines
        public readonly ?int $accountId,          // set when the user picked a specific leaf (manual JV)
        public readonly string $side,             // 'debit' | 'credit' — exactly one side, non-negative amount
        public readonly float $amount,            // base-currency amount
        public readonly string $currency,         // original currency
        public readonly float $originalAmount,    // FC amount; == amount when currency == base
        public readonly float $exchangeRate,      // > 0; 1.0 for base
        public readonly ?string $transactionType, // audit label: CUSTOMERDEBITED, SUPPLIERCREDITED, INCOME, CCCHARGES…
        public readonly ?int $partyAccountRef = null,
        public readonly ?string $description = null,
    ) {}
}

/** Result of a successful post. */
final class PostedDocument
{
    public readonly Transaction $transaction;
    /** @var JournalEntry[] */ public readonly array $lines;
}
```

**The service** — the whole contract:
```php
namespace App\Services\Accounting;

final class PostingService
{
    public function __construct(
        private AccountResolver $accounts,
        private SequenceService $sequences,
        private PeriodGuard $periods,       // P5.1 no-op stub in P1: always allows
        private Money $money,
    ) {}

    /**
     * Post one balanced document atomically.
     *
     * PIPELINE (all inside ONE DB::transaction):
     *   1. Idempotency: if idempotencyKey set AND a posted transaction already exists for
     *      (companyId, idempotencyKey) -> return the existing PostedDocument, do NOTHING. (F2/F8/F9)
     *   2. Resolve every line's account:
     *        - explicit accountId -> load with lockForUpdate, assert company_id === draft.companyId (T7.1)
     *        - purposeCode        -> AccountResolver::resolve(purpose, companyId, serviceType) — THROWS if unmapped
     *      Never Account::where('name', …). Never auth-scoped lookup (safe in webhook/queue). (R7.3, BUG-H6)
     *   3. Per-line iron rules (blueprint 02 §3, MF-11) — THROW, never Log::warning-and-continue:
     *        a. round debit/credit to base decimals; originalAmount to FC decimals
     *        b. amount >= 0 (NonNegativeAmountException)
     *        c. side is debit XOR credit (OneSidedLineException)
     *        d. account is a leaf: children()->doesntExist() AND is_group === false (NonLeafAccountException) (R1, BUG-C2)
     *        e. account not disabled (FrozenAccountException)
     *        f. if currency === base: originalAmount === amount AND rate === 1 (FcConsistencyException) (R3)
     *   4. Header rule: abs(Σdebit − Σcredit) < 0.0005 (UnbalancedDocumentException) (R2, BUG-C1, check 1.1)
     *   5. Period guard: PeriodGuard::assertOpen(companyId, docDate) (P5.1) (R5, MF-12)
     *   6. Number: SequenceService::next(docType, companyId, branchId, docDate) inside this txn
     *   7. Write header (transactions: posting_status='posted', total_debit/credit, doc_type/year,
     *      idempotency_key, reversal_of_transaction_id=null, created_by/posted_by/posted_at)
     *   8. Write each JournalEntry with transaction_id set; transaction_date = docDate (BUG-C4);
     *      currency/exchange_rate/original_currency/original_amount populated on EVERY line (R3);
     *      branch_id, cost_center_id (P5.7) stamped.
     *   9. Balance side-effect (choose ONE, atomic): Account::whereKey($id)->update([
     *          'actual_balance' => DB::raw('actual_balance + (debit - credit)')  // via increment/decrement
     *      ]) — NEVER read-modify-write (F5/F11); OR treat actual_balance as derived-only and drop it (P3 decides).
     *  10. Return PostedDocument.
     *
     * THROWS PostingException (typed subclasses) on any violation -> the transaction rolls back whole.
     * NEVER returns a JSON error, NEVER partially commits (kills R2.2c, BUG-C1's one-sided branches).
     */
    public function post(DocumentDraft $draft): PostedDocument;

    /**
     * Reverse a posted document as a NEW dated document (never mutate/delete posted lines) (R4, BUG-H4).
     *   - refuse if transaction already reversal_of (idempotent): return existing reversal.
     *   - refuse if any line is reconciled != 0 or settled (protected) unless $force by supervisor. (blueprint 02 §5)
     *   - build a DocumentDraft with debit<->credit swapped, docType 'REV', reversal_of_transaction_id set,
     *     dated $reversalDate (defaults to today; caller may back-date within an open period).
     */
    public function reverse(Transaction $posted, \DateTimeInterface $reversalDate, ?int $userId, bool $force = false): PostedDocument;

    /** Reverse-then-apply: reverse($old) then post($new) in one transaction (the proven updateAmount pattern). */
    public function repost(Transaction $old, DocumentDraft $new, \DateTimeInterface $date, ?int $userId): PostedDocument;
}
```

```php
final class AccountResolver
{
    /**
     * Resolve a leaf account by purpose for a company, WITHOUT relying on Auth global scope
     * (safe in unauthenticated gateway/queue context). Reads system_accounts.
     * THROWS UnmappedPurposeException if no row — the engine must never silently skip a leg.
     */
    public function resolve(string $purposeCode, int $companyId, ?string $serviceType = null): Account;
}
```

**Idempotency:** carried on the header (`transactions.idempotency_key` + unique index above) rather than a side table — simpler, and the header is already the atomic unit. Gateway callbacks pass `idempotencyKey = "{gateway}:{gatewayTxnId}"`; auto-invoice passes `"task:{taskId}:issue"`; reversals pass `"rev:{originalTransactionId}"`. The unique index is the DB backstop that makes F2/F8/F9 impossible even under the F1 race.

**Legacy coexistence during migration:** the engine writes the same `transactions` + `journal_entries` tables the legacy code writes, using the **existing `transaction_id` grouping key**. So a P2-migrated feeder and a not-yet-migrated feeder coexist row-for-row; reports (which already read these tables) see both. `posting_status` defaults `posted` so legacy rows are visible; only P5 introduces `draft`. No parallel ledger, no dual schema in `live` mode.

### Acceptance tests (P1)
- `PostingServiceBalanceTest::post_rejects_unbalanced_document` — a draft with Σd≠Σc throws `UnbalancedDocumentException`, nothing written.
- `…::post_rejects_non_leaf_account` — posting to an account with children throws (R1/BUG-C2).
- `…::post_rejects_cross_tenant_account` — explicit accountId of company B while draft.companyId = A throws (T7.1).
- `…::post_is_idempotent` — two `post()` with same `idempotencyKey` create exactly one transaction + N lines (F2).
- `…::reverse_creates_swapped_document_and_leaves_original` — original lines untouched; new REV doc balances; running balance nets to zero (R4/BUG-H4).
- `…::reverse_refuses_reconciled_lines_without_force` (blueprint 02 §5).
- `AccountResolverTest::throws_on_unmapped_purpose` and `resolves_without_auth_context` (webhook safety).
- `SequenceServiceTest::next_is_unique_under_concurrency` — parallel calls never collide (F6).
- Global invariant (C1) runs after every one of the above.

### Rollback
P1 ships **behind the flag** (`posting_engine_enabled = false` for all companies). No feeder calls the engine yet (that is P2), so P1 is pure addition: new tables, new services, backfilled `is_group`, widened columns, unique constraints. Rollback = drop the new tables and revert the column widenings; the unique constraints on `accounts` are the only forward-only step (keep them — they are correct). The `is_group` backfill and money widening are safe to keep even if P1 is paused.

**Closes:** MF-1, MF-3, MF-11, MF-41, MF-42 (registry portion), BUG-H1, BUG-H2, BUG-H10 (numbering), BUG-M5 (precision), and *builds the vehicle* that P2 uses to close BUG-C1, BUG-C2, BUG-H4, BUG-H6, R1/R2/R3/R7.

---

## P2 — Migrate the 131 call sites (XL) · strangler cutover

**Goal:** the PostingService becomes the **only** writer of `journal_entries`. Old hand-rolled paths are deleted wave-by-wave; the CI architecture test (C2) locks the door behind each wave.

**Depends on:** P1.

**Method:** 21 files, 131 sites (census in [golden-rules-integration.md](golden-rules-integration.md) §7.1). Group into waves ordered by *risk descending* — the paths that are broken/one-sided/unbalanced *today* go first, so cutover fixes them instead of preserving them. Each wave: (1) build a feeder method that emits a `DocumentDraft` and calls `PostingService::post()`; (2) switch the company set `legacy → shadow → live`; (3) in `shadow`, diff engine output vs legacy for ≥1 week of real traffic, reconcile mismatches; (4) delete the legacy posting code; (5) the architecture test now forbids its return.

| Wave | Sites (files) | Why first | Closes |
|------|--------------|-----------|--------|
| **W1 — broken/unbalanced feeders** | `ChatController` (3, unbalanced by construction R2.2a), `CheckMyFatoorahPayments` (1, one-sided R2.2b), `AgentController:332` (one-sided salary) | Producing bad documents *now* | R2, check 1.1 sources |
| **W2 — gateway webhook posting** | `PaymentController::createInvoicePaymentCOA` (3) + `createCreditPaymentCOA` | Tenant bug + F1/F2 amplifier; idempotencyKey closes the double-post class structurally | BUG-H6, F1/F2, R7.3 |
| **W3 — invoice sale + edits** | `InvoiceController` (33: `addJournalEntry`, `createCreditPaymentCOA`, `addInvoiceChargeJournalEntries`, the reversal blocks) | Highest volume; edit paths corrupt via delete-and-recreate (R4.1) | BUG-C1, BUG-H4, R4 — **fold-ins: 22 §2.1**, incl. **W3.A2** (anchor purpose codes + `AccountResolver::resolveAnchor()`), **W3.F** (`cost_centers` master + FK), **W3.G** (`transactions.bsptype`); **W3a/W3b/W3c CLOSED 2026-08-28** (gate: `Tests\Feature\Accounting\*` + `Tests\Unit\Services\Accounting\*` ~39 classes fully green, `PostingEngineGateTest`/`AccountObserverGateTest`/`AccountingRouteGateTest`/`PostingSeamTest` pass; `PostingSeam.php` md5 unchanged `e79e2aacd129471c4a75ed9aab4b2205`, `PostingService.php` changed for the new `reverse()`/`repost()` consumers; full suite 909 pass / 248 fail, the 248 being 33 pre-existing non-accounting classes + a legacy-closure null-deref in `InvoiceCreationTest` + the real-SMTP `test_send_invoice_email` — none are accounting regressions); **W3d (in flight 2026-08-28)**: sale draft shape correction to NET per doc 22 §15.1, follow-on after the W3 gate; **W3e (after W3d, before W4)**: `InvoiceController` leftovers — `update()`'s raw delete+recreate without the seam, `updateAmount()`/`updateDetailsAmount()`'s description-LIKE reversal targeting, row locks for the invoice-number sequence + dup-guard, and a guard for the legacy `%Direct Income%` null-deref — brief at `.planning/accounting-waves/w3/w3e-brief.md` |
| **W4 — refund** `[rev 4, 2026-08-27 — scope/order updated per the binding W4 refund brief; owner decisions: see doc 22 rev 4, §10]` | `RefundController` (23) | One-sided `handlePaidRefund`; misclassified clawback account | BUG-C1, MF-30, R4.2 — **fold-ins: 22 §2.2**, run in this order: **(0) W4.0 (first)** InvoiceController residual raw writers from the W3e gate — `updateTaskPrice()` in-place mutation, `updatePaymentType()`/`updatePaymentGateway()` raw RV deletes, invoice-charge raw creates, derived-doc repost mode; **(1) W4.C** supplier cost posts in the sale's own period, lands before W4.A; **(2) W4.A** delete the `Cr {supplier cost}` / `Cr 5123` offsets, company-borne negative margin posts nothing; **(3) W4.D** delete `createGatewayProfitEntries()` + all four call sites, gross-up, `5147`; **(4) W4.R** the refund document itself — header + per-task lines, engine-emitted linked docs (CRN reversal of the sale · recharge lines for penalty pass-through + fee income · supplier credit item on the airline/consolidator payable · commission un-earn JV · the three-event `5125` clawback · disposition of client net to `2632`/PV-refund-out/apply per `invoice_overpay_cancel_policy`), statuses draft→approved→posted→completed\|rejected, plus the **gateway refund completion handler** (listener on `GatewayRefundStatusChanged`, one txn via `PaymentIdempotencyKey::forGatewayRefund`), `refund_clients` folded into the refund doc, and `credits` becoming a view of `2632` under engine ON; **(5) bundled fixes** — `RefundPolicy` full abilities (viewAny/view/create/update/approve/complete/delete), `completeRefundClient` authorize, `refunds.show` → auth or signed URL (Invoice's `publicUrl()` pattern), dead `store-unpaid` route removed/implemented, `ClientController::refundProcess` Dr/Cr orientation fixed, `RefundSequence` → serial schema with collision retry, the one-sided `handlePaidRefund` JE killed, and the `InvoiceController` hard-deletes (`revertFinancialsForTask`, `deleteLossEntries`) replaced with `reverse()` (the `TaskController` hard-deletes are **W6**'s, not this wave's, per the void-wave split already stated in the W6 row below). Void, void-with-fee, reissue and bulk-void stay in **W6** (§2.4 fold-ins) — unchanged by this update, confirmed here for the avoidance of doubt. **W4.C reduced to late-arriving cost corrections** once W3d posts supplier cost inside the sale document itself (doc 22 §15.1). |
| **W5 — receipt/bank vouchers** `[rev 5, 2026-08-27 — scope widened per the binding W5 vouchers brief; owner rulings: see doc 22 §11]` | `ReceiptVoucherController` (12), `BankPaymentController` (~10), `AgentSettlementService` (legacy `settleByProfit`/`onPaymentCompleted`), `LineDraft`/`DocumentDraft`, `PostingService`, `PaymentApplicationService` (RV allocation) | Two-sided settling docs deleting one leg (BUG-C1); P2 exit requires the engine to be the **sole** ledger writer — RV/PV/legacy settlement are three more raw `JournalEntry::create()` writers §2.3's narrow column-only scope did not close | BUG-C1, BUG-C3 remainder — **fold-ins: 22 §11**, run in this order: **(1) W5.L** `LineDraft` instrument fields (`chequeNo`/`chequeDate`/`bankInfo`/`authNo`/`chequeClearanceDate`) + `cheque_clearance_date` migration, `RV`/`PV` serial schemas, `doc_type`/`sub_type` lists, anchor purpose codes incl. new leaves **`1215` Cheques in Hand**, **`2215` Cheques Issued Not Cleared**, **`CASH_OVER_SHORT`** under `5100`; **(2) W5.R** Receipt Voucher through `PostingSeam`, allocation lines + remainder → `2632` per `invoice_overpay_cancel_policy`, PDC basic posting (cheque → `1215` → bank on clearance, bounce = reverse + fee DBN), reverse+repost, reconciled/period locks, kill `actual_balance +=`, `voucher_approval_threshold`; **(3) W5.P** Payment Voucher through the seam, bank-balance pre-check via `TrialBalanceService` inside the txn (`pv_allow_overdraft` default false), `2215` cheque-issued-not-cleared, manual bank-charge line company-borne default; **(4) W5.S** legacy `AgentSettlementService` routed through the seam as a **temporary `AST/{branch}/{yy}/{seq}` serial schema** (`sub_type = LEGACY`, for P5.13 to inherit), kill `actual_balance +=`, Rule 3b (no automatic deduction); **(5) W5.X** full RV/PV policy ability sets, invariant **A21** (cash/bank/Day Book select by line movement, never `doc_type`), `ArchitectureTest` extension, OFF-path parity. New options this wave: `voucher_approval_threshold`, `pv_allow_overdraft` (default false), `cash_close_mode` (`manual_leaf`\|`daily_close`, W5 adds the option + `CASH_OVER_SHORT` leaf only — the `daily_close` automation is P5.18) |
| **W6 — tasks + services + mobile: the void wave** `[rev 6, 2026-08-27 — full kind/leg/consolidation design per the binding W6 brief; owner rulings: see doc 22 §12]` | `TaskController` (9, `processIssuedTask` parent-posting), `TaskWebhook`, `TaskPolicy`, `ProcessVoidTasksFinancials`, `ProcessExpiredConfirmedTasks`, new `TaskStatusService`; `MobileController` (5), `ClientController` (6), `CreditController` (2) | Parent-account posting (R1.2), duplicate posting logic, top-up Dr-receivable bug (BUG-M2); **this is also where BUG-H7's three void paths live, not W4 (22 §2.4, correcting 21 §4/§5a)**; two more raw writers (`TaskWebhook`'s duplicated status logic, the two void commands) that P2 exit's engine-is-sole-writer bar (§11.1's rule, restated here) has not yet closed | BUG-C2, BUG-M2, R1.2, BUG-H7 — **fold-ins: 22 §12**, run as sub-waves **W6.S → W6.V → W6.R → W6.B**: **(1) W6.S** new `TaskStatusService` consolidates status mapping/`original_task_id`/financial dispatch across `store/update/toggleStatus/updateMulti/bulkUpdate`, `TaskWebhook`, `ProcessAirFiles`, both void commands and Magic's `processSingleReservation` (`TaskWebhook`'s duplicated copy deleted), plus the bundled fixes: webhook signature auth on `/api/task/webhook` (reuse the codebase's existing HMAC middleware, do not write a third) + `payload_hash` dedupe, `TaskPolicy` gains `update`/`void`/`reissue`/`bulkVoid`/`switchInvoice` with `Gate::authorize` on every mutating action, the premature `DB::commit()`s in `voidTask()`/`ReverseUnpaidVoidedTask()` removed, `ProcessVoidTasksFinancials`'s idempotency fixed via engine key (not the broken `'Void reversal for: '` vs `'Void reversal: '` description match) with company filter in-query, `revertFinancialsForTask`/`Void`/`handleClientChange`/`handleAmountChange` delete-by-description replaced with `reverse()`, `ProcessExpiredConfirmedTasks` routed through `TaskStatusService::void()` instead of a raw save, the dead `triggerCheckTaskEvent`→`CheckConfirmedOrIssuedTask`→`ProcessTaskFinancials` listener removed, `bulkUpdate()`'s null-deref fixed; **(2) W6.V** two-leg model (`tasks.ticket_status`/`client_status`, additive, backfilled from `status`) — VOID, AUTO_VOID (status-only when never invoiced), VOID WITH FEE (`Dr AR / Cr 4134 Void Fee Income`, gated by `commissionable_fee_types`, reusing W4's `fee_table`/`refund_fee_override`); **(3) W6.R** REISSUE — reverse-and-relink original, post new lines on the same invoice, fare difference as DBN/CRN never folded into a total, fee → **4135 Reissue Fee Income**, receipt allocations re-applied automatically, `switchInvoiceTask()` becomes a thin wrapper (its logged-only profit delta now posts); **(4) W6.B** bulk void — one outer transaction, new option `bulk_void_mode` (`atomic`\|`per_task_report`, default atomic), `updateMulti()` delegates to `TaskStatusService` and rethrows instead of swallowing. Preconditions/idempotency (period-lock + reconciled guard, `void:{task_id}`/`reissue:{old}:{new}` keys) and commission un-earn (`commission_on_refunded_sale`, reused) run across all four sub-waves, per doc 22 §12.3–§12.4. ADM/ACM stay **P7.5**, unchanged by this wave. |
| **W7 — console/migration/repair** | `PaymentReleaseToCompanyBankAccProcess` (2), `UpdateOldTaskToTransaction` (4), and the six `Fix*COA` commands (24) | Lowest traffic; the `Fix*` family is *retired* once balances are engine-maintained | R7.2 (duplicate helpers) |

Delete the duplicated helper pairs as their wave lands: `createCreditPaymentCOA` (InvoiceController **and** PaymentApplicationService), `storeJournalEntryEntries` (BankPaymentController **and** ReceiptVoucherController). Kill the inverted architecture where `PaymentApplicationService` and `RunAutoBilling` resolve a **controller** out of the container to post (R7.1) — they call `PostingService` directly.

UI rule (2026-08-28): every wave ships a minimal UI sub-lane (W4.U/W5.U/W6.U) + period-close screen at P2.5 + bearer matrix per-agent tab at P5.13; polish deferred to P7; design via frontend-design-pro skill.

### Backlog from W3 gate (2026-08-28)

Recorded from the W3 lead re-gate (`.planning/accounting-waves/w3/w3-final-gate.md`) and the W3b re-verify (`w3b-verify-final.md`). Not accounting regressions; not blocking W3a/b/c close.

**W3a–W3e CLOSED 2026-08-28** — family re-gate (`.planning/accounting-waves/w3/w3e-gate.md`): accounting namespace (`tests/Feature/Accounting` + `tests/Unit/Services/Accounting`) **311 pass / 0 fail** (1 skipped); full suite **939 pass / 246 fail**, all 246 pre-existing non-accounting (net improvement vs. the pre-W3d/post-W3d baselines, not a regression); `PostingSeam.php` md5 `e79e2aacd129471c4a75ed9aab4b2205` unchanged from the pre-W3 baseline. Two new reachable-ON gaps found by this re-gate (`updateTaskPrice()` in-place mutation; `updatePaymentType()`/`updatePaymentGateway()` unconditional raw RV deletes) are carried forward as **W4.0**, first sub-wave of the W4 row above — not blocking this close.

- **Pre-existing non-accounting suite failures**, all outside `App\Services\Accounting\*` and owned by their respective areas, not this cutover: staging webhook/HMAC routing, missing `N8n webhook secret` config, DotwAI/AkeedDotwAI unit-test fixtures hitting a `BranchFactory` hardcoded `user_id => 1` FK violation, and 404s on Auth/Admin/BulkUpload staging routes.
- **Test-environment gap**: `laravel_user` lacks `CREATE DATABASE` privilege, so the W3 gate run reused the pre-existing generic `city_tour_test`/`city_tour_test_map` pair instead of a freshly provisioned database — flagged so a future gate can provision cleanly if the privilege is granted.

**New-phase pointers (22 §3) — one line each, full spec lives in doc 22:**
- **P5.12** — Credit control (D7): block/warn a credit sale over the party's limit or to a blacklisted party.
- **P5.13** — Agent sub-ledger (D1, D2, D3, D4, D6) → doc **20**: `agent_charge_policies`, the agent charge and settlement documents, the agent statement.
- **P5.16** — Balance strategy (D9): ledger-derived reporting kept, with a measured ceiling instead of `account_monthly_balances`.
- **P5.17** — Audit trail, integrity checks, and `Fix*` retirement (D9): full `*_log` history, the two missing integrity checks, the nightly drift checker, the nine `Fix*` commands retired.
- **P5.18** — Vouchers completeness (addendum S9, the RV/PV completeness item) `[rev 5, 2026-08-27 — carve-out narrowed; W5 now builds the engine cutover and basic PDC/bank-charge/cash-close options: see doc 22 §11.5]`: PDC register/alerts, realized-FX-line implementation, bank-charge recharge-to-bearer option, refund-out-vs-keep-as-advance, supplier advance PV, expense/staff PV, BSP remittance/EasyPay top-up PV, daily-cash-close automation.
- **P7.5** — BSP reconciliation + ADM/ACM (D5): promoted from deferred; memo documents, two-step posting, settlement modes, EasyPay wallet, statement ingestion, ticket-by-ticket variance, the monthly ritual.
- **W6.I** importer contract (auto-invoice at issue, per-PNR grouping, EMD, import idempotency) — doc 22 §16.

### Acceptance tests (P2, per wave)
- Per feeder: `…FeederTest::emits_balanced_draft_for_<scenario>` — the classic scenarios (gateway sale with fee, markup invoice, paid/unpaid refund, void, top-up, multi-passenger task) each produce a balanced `DocumentDraft` whose posted result passes C1.
- `ChatControllerPostingTest::whatsapp_invoice_is_balanced` — the exact R2.2a case (markup) now nets to zero (was −2×markup).
- `GatewayDoublePostTest::concurrent_callbacks_post_once` — simulate F1 interleaving; assert one transaction (idempotency).
- `ArchitectureTest::no_journal_entry_writes_outside_engine` — greps app tree; **must pass at the end of every wave** and is the phase-exit gate.
- **Pre-cutover checklist item:** posting basis per service type (net for tickets) + revenue recognition timing — doc 22 §15.
- **P2.5.F Accounting Log Center** — append-only `accounting_audit_log` + admin screen; the 15 `accounting.*` file events mirrored; see p2_5-brief.md.
- **P2.5.G Reconciliation Center v0** (book vs reconciled vs unreconciled, manual match) — v1 statement import at P5.10; see reconciliation-design.md.
- **P2.5 Reconciliation Center v0** — book/reconciled/unreconciled-balance screen (manual only, no statement import yet), feeding the month-close checklist; v1 (import + auto-match) ships at P5.10 on the same screen — see .planning/accounting-waves/reconciliation-design.md.
- **P2.5.I Reminder engine v2** — automatic generation (per-kind, ledger-driven, dedupe/quiet-hours) + sender repair (cap, `error_message`, `cancelled` status) + three prod-drift commands ported into git; moved from P5, doc 22 §16.7; see p2_5-brief.md.
- **Deploy note (P2.5.I):** once the Laravel scheduler carries `process:reminder` and `reminder:generate`, delete the direct crontab reminder lines (`process:reminder --proceed` every minute, `reminder:unassigned-tasks`, `reminder:uninvoiced-tasks`, `reminder:uninvoiced-payment-links`) — documented here only; this is a **user go** action at deploy, never executed automatically.

### Rollback
Per-company, per-wave: flip the company back to `legacy`/`shadow`. Because the engine writes the same tables, there is no data to unwind on a flip — you only change which code path runs next. Shadow-mode diffs are the safety net that catches a wave's behavioural regression *before* `live`.

**Closes:** BUG-C1, BUG-C2 (posting half), BUG-H4, BUG-H6 (full), BUG-M2, MF-13 (attribution via `created_by`), R1, R2, R3, R7, and the ongoing source of checks 1.1 / 6.1 / 8.1.

---

## P3 — Data repair on staging → prod (L)

**Goal:** rebalance the ledger and remove the historical corruption **after** P2 stopped it flowing. Repair never runs while a corrupting path is still `live`.

**Depends on:** P2 (all waves `live`).

**Environment:** the staging clone `citycomm_citytourv2` on `ct-server` (424 MB, cloned 2026-07-07, exact row-match on all key tables — see [data-integrity-results-prod-copy.md](data-integrity-results-prod-copy.md)). Every repair script is written as an idempotent Artisan command with `--dry-run` (default) and `--company=` scoping, is run on the clone first, its output diffed and signed off, then run on prod inside a maintenance window with a fresh `mysqldump` taken immediately before.

**Detection queries already exist** — `data-integrity-queries.sql` checks 1.1–9.8. Each repair command re-runs its detection check as its own pre/post assertion.

### Repair strategy per corruption class

| Class | Check | Count (prod copy) | Auto-fixable? | Strategy |
|-------|-------|:-----:|:-----:|----------|
| **Unbalanced posting groups** | 1.1 | 2,273 | **No — accountant-gated** | For each group: recompute the intended document from its source (payment/invoice/task) and post a **balancing adjustment** via `PostingService` (docType `REV`/`JV`, `idempotencyKey="repair:balance:{transactionId}"`) to a per-company **`SUSPENSE` control account**, tagged for accountant review. Do **not** silently invent the missing leg into revenue/expense — route the plug to suspense and produce a worklist. The consistent whole-unit diffs (1–4) suggest one fee/rounding path; trace `transaction_id=5442` first, and if a single systemic cause is found, a targeted rule can reclassify the plug out of suspense in bulk (accountant sign-off per rule, not per row). |
| **Invoices with zero JEs** | 2.1 | 7 | **Yes** | Backfill the "Invoice Generation COA" document via the P2 invoice feeder with `idempotencyKey="task:{…}:issue"`. Known historical gap (pre-Phase-30 `ConfirmBookingAfterPaymentJob`); dates Aug 2025–Feb 2026. |
| **Status drift (paid/unpaid/partial)** | 3.1(12), 3.2(18), 3.3(1) | 31 | **3.2/3.3 Yes; 3.1 No** | 3.2/3.3: mechanical recompute of `Invoice.status` from `payment_applications` (the P5.3 open-item engine's projection). 3.1 (paid but zero money on both signals): accountant review — may be offline/manual payments; do not fabricate a payment. |
| **Credit ledger vs applications drift** | 4.1 | 27 (some ambiguous) | **Partial** | Re-run 4.1 filtering the same-`payment_id` artifact (1681/1682 family). For genuine drift (e.g. credit 585: 40 applied unaccounted on ledger), post a suspense adjustment + worklist. Accountant-gated. |
| **Sign errors — negative debit/credit** | 8.1 | 46 | **Yes (rule-based)** | The matched pairs (15201/15202: both −101.160) are a reversal-sign bug now impossible under P2. Rewrite as a proper reversal document via `PostingService::reverse` semantics: post a correcting pair so net effect is preserved, soft-supersede the bad rows (never hard-delete). |
| **Negative refund/credit source rows** | 4.3(3), 8.5(5) | 8 | **Yes + dedup** | Fix the sign on the refund-credit source rows; **de-duplicate** RF-2026-00006 (rows 2888/2889 both −20.95). Accountant confirms the intended refund amount for the 5 `total_refund_amount` sign flips. |
| **Cross-tenant JE rows** | 7.1 | 2 | **No — manual** | JE 16953/16954 (company 1 → account of company 2). Manually determine correct account, repost under the right tenant, soft-supersede. Severe regardless of count. |
| **Soft-delete inconsistencies** | 9.1(2),9.2(5),9.3(2),9.6(3),9.8(1) | 13 | **Yes** | Extend the existing `credits:soft-delete-orphaned` command to cover transaction/JE-invoice/invoice_partial parents (9.1/9.2/9.8 have no command today). Soft-delete the orphaned children or re-parent. |
| **`is_group` over-broad flag** | 6.2 vs 6.1 | 42,401 vs 25 | **Yes** | Already recomputed in P1.0 (`is_group = EXISTS(child)`). Post-P1 this check should read ~25. Re-verify. |
| **Postings to genuine parent accounts** | 6.1 | 25 | **Yes** | Re-home onto explicit leaves (the existing `delegatePriceAmadeus` logic, now via `AccountService`), reverse-then-apply. |

### Enforce `transaction_id` NOT NULL (deferred from P1)
After the 2.1 backfill and an orphan-line sweep (any `journal_entries.transaction_id IS NULL` gets wrapped in a synthetic single-document header or reversed), run:
```php
Schema::table('journal_entries', function (Blueprint $table) {
    $table->foreignId('transaction_id')->nullable(false)->change(); // MF-41 completion
});
```
Then extend `findUnbalancedTransactions` is no longer needed as a *detector of orphans* — but keep it as the nightly drift check.

### Dry-run-on-staging protocol & sign-off gate
1. Run every repair command with `--dry-run` on `citycomm_citytourv2`; capture the proposed changes as a CSV worklist per class.
2. Diff pre/post detection counts on the clone; the accountant (Soud/finance) signs off the **suspense worklist** and the **status-recompute list** explicitly. Auto-fixable classes (2.1, 3.2, 8.1 pairs, 9.x, 6.x) need one blanket sign-off; accountant-gated classes (1.1, 3.1, 4.1, 7.1, 8.5 amounts) need per-worklist sign-off.
3. **Gate:** prod repair runs only after (a) clone dry-run counts match expectations, (b) sign-off recorded, (c) a fresh prod `mysqldump` snapshot exists for rollback, (d) the run is inside a maintenance window with the app in read-only/maintenance mode so no new writes race the repair.
4. Post-run: re-run all 46 checks on prod; 1.1 should approach 0 (residual = suspense-plugged, tracked), section 5 stays 0, 7.1 = 0.

### Rollback
Every repair is a **soft-supersede + new posting**, never a hard delete, so a bad repair is itself reversible via `PostingService::reverse`. The pre-run `mysqldump` is the nuclear rollback. Retire the six `Fix*COA` commands after this phase (they exist only because balances drifted — P1's atomic maintenance + this repair make them dead, BUG-C3 confession).

**Closes:** checks 1.1, 2.1, 3.1–3.3, 4.1, 4.3, 6.1, 6.2, 7.1, 8.1, 8.5, 9.x; BUG-C3 (balance rebuild), MF-41 (NOT NULL).

---

## P4 — Tenant hardening, structural (L)

**Goal:** cross-tenant isolation stops being a per-query convention and becomes a schema + model + policy guarantee. P0 stopped the live exploit; P4 removes the *class* of bug.

**Depends on:** P0 (hotfixes already live), P2 (engine tenant-safe).

**Deliverables:**

**Migration `add_company_id_to_invoice_pipeline`** — the architectural fix (T-Finding-7). `invoices` has no `company_id` column at all:
```php
Schema::table('invoices', function (Blueprint $table) {
    $table->foreignId('company_id')->nullable()->after('id')->constrained('companies');
    $table->index('company_id');
});
// same for invoice_details, invoice_partials, payment_applications, invoice_receipt (no company_id today)
```
Backfill migration: `UPDATE invoices i JOIN agents a … SET i.company_id = a…company_id` walking `invoice → agent → branch → company`; propagate to the children from their parent invoice. Then add `use BelongsToCompany;` to `Invoice`, `InvoiceDetail`, `InvoicePartial`, `PaymentApplication`, `Credit`, `Refund`, `InvoiceReceipt`, `Client` (T-Finding-7 option (a), the preferred one — reuses the mechanism already proven for `Account`/`JournalEntry`/`Payment`). Keep `company_id` refreshed on agent/client reassignment via an observer.

**Policy record-ownership checks** (T-Finding-6) — every `view`/`update`/`delete` on a financial model asserts `$model->company_id === getCompanyId($user)` **in addition to** the permission string, in `InvoicePolicy`, `AccountPolicy`, `PaymentPolicy`, `CreditPolicy`, `RefundPolicy`. Delete the dead `$user->id == $invoice->user_id` clauses (those columns don't exist).

**Replace `Transaction`'s bespoke scope** (T-Finding-9) with `use BelongsToCompany;` so ADMIN behaves consistently (one company via session, not "all companies"), and BRANCH is company-checked not just branch-checked.

**Fail-closed** the `if ($companyId)` raw-report guards (T-Finding-10/11): `abort_unless($companyId, 403)` before building any raw `DB::table('transactions'|'journal_entries')` query.

**Server-side re-query** the ledger Excel export (T-Finding-12) instead of trusting client-POSTed rows.

### Acceptance tests (P4)
- `TenantIsolationTest::user_of_company_a_cannot_load_company_b_invoice` — `Invoice::find($bId)` under A's auth returns null (global scope).
- `…::apply_payments_rejects_foreign_credit` — the P0 fix, now enforced structurally (T-Finding-1).
- `…::policy_denies_foreign_record_even_with_permission` — a user with `view invoice` cannot `Gate::authorize('view', $foreignInvoice)` (T-Finding-6).
- `…::raw_report_aborts_when_company_null` (T-Finding-10).
- A tenancy fuzz test: for each financial model, a cross-company `find`/`update` attempt 403s or returns empty.

### Rollback
`company_id` columns are nullable and additive; the `BelongsToCompany` traits can be removed per-model to revert to the pre-P4 (P0-patched) state. Backfill is idempotent and re-runnable.

**Closes:** T-Finding-1 (structural), 6, 7, 9, 10, 11, 12; BUG-C5 remainder (report scoping); MF-42 (finishes name→registry control-account replacement started in P1).

---

## P5 — Blueprint accounting gaps (XL) · umbrella of sub-phases

**Goal:** everything the blueprint has and citytourv2 lacks in the core ledger: periods & locking, the opening journal & year-end close, AR/AP open-item completion, the canonical reporting layer, fixed assets, FX revaluation, cost-centre, and the travel-industry controls. Each sub-phase is first-class (own goal/tests/complexity) but shares the P1 engine.

**Depends on:** P3 (clean data), P4 (tenant-safe).

### P5.1 — Accounting periods & global lock enforcement (M) — MF-12, R5.2
```php
Schema::create('accounting_periods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');
    $table->unsignedSmallInteger('fiscal_year');
    $table->date('period_start');
    $table->date('period_end');
    $table->enum('status', ['open','soft_closed','locked'])->default('open');
    $table->foreignId('closed_by')->nullable();
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
    $table->unique(['company_id','fiscal_year','period_start']);
});
```
Implement `PeriodGuard::assertOpen(companyId, date)` (the P1 stub becomes real): reject any post/reverse whose `docDate` falls in a `locked` period. This is enforced **in the engine**, so it covers all feeders at once (fixes "locked month silently writable via non-invoice documents"). Extend the existing `Lockable` framework's UI to drive `accounting_periods`, not per-row flags. **Tests:** `PeriodGuardTest::post_into_locked_period_throws`, `reverse_into_locked_period_throws`, `open_period_allows`.

### P5.2 — Opening journal + year-end close (L) — MF-17, BUG-H5, R5.3
Convert `accounts.opening_balance`/`_date` into a posted, immutable **Opening Journal Voucher** (docType `OJV`) balanced against `RETAINED_EARNINGS` (`3400`). One shared `OpeningBalanceService::openingFor(account, asOfDate)` used by `TrialBalanceService`, `JournalEntryController::show`, and `CoaController::childAccount` — killing the three-way divergence (BUG-H5). `close-year` Artisan command: validate year order (`AccountsClosingRollbackDisable` system param), post a locked OJV carrying balance-sheet leaves forward as "Balance B/F", sweep net P&L into `3400` for the year, advance the `accounting_periods` pointer, make it irreversible. **Tests:** `YearEndCloseTest::pl_accounts_reset_and_bs_carried_forward`, `retained_earnings_equals_net_profit`, `reopen_closed_year_is_blocked`, `opening_balance_is_single_source` (TB == ledger == COA).

**Fold-ins (22 §5.2 E3):**
- **Copy serial schemas forward at year-end** (06-7 / N29) — two lines inside `close-year`; without it the first document of the new year fails. `SequenceTest::first_document_of_a_new_year_mints_successfully`.
- **Live P&L injection into the trial balance** (05-15 / N21) — without it the TB visibly fails to balance for eleven months of every year (21 §3: *"the single most damaging thing an accountant can see"*).
- **`close-year` refuses while `1952 Airline Memo Control` is non-zero** (doc 20 §5.16, §10.18) — a non-zero control balance means undispositioned memos, and the undispositioned balance is the BSP worklist's standing content. This is a **P5.2** deliverable even though the memos themselves are P7.5's. **Test:** `YearEndCloseTest::close_year_refuses_while_1952_is_non_zero`.

### P5.3 — AR/AP open-item completion + party de-pooling + memos (L) — MF-2, MF-18, MF-19, R6
- **Party-master FKs (MF-2):** `clients.receivable_account_id`, `supplier_companies.payable_account_id`/`cost_account_id`, `airlines.account_id`; auto-create per-party leaves via `AccountService::ensurePartyLeaf` in party `created` observers (reuse the working agent pattern). De-pools the single shared `Clients` AR account — the precondition for per-party ageing/statements.
- **Open-item engine (MF-18):** add `journal_entries.settled_amount decimal(18,3) default 0`, maintained **only** by an apply/release service with a never-negative guard; derive `reconciled` from `settled_amount == amount`. Make `PaymentApplication` the single paid-state writer (R6 recommendation); `Invoice.status` becomes a derived projection. Add unique index `payment_applications(invoice_id, credit_id)` (F8). Guarded standalone `unapply()` that reverse-posts (never deletes GL). Extend to supplier-side application.
- **Memo module (MF-19):** `credit_notes`/`debit_notes` header+lines (type C/D, party, optional linked invoice, currency/rate), numbered via `SequenceService`, posted balanced via `PostingService`; route refund CRNs and future airline ADM/ACM through it.
- **Post-at-issue (MF-20):** post the receivable/revenue document at invoice *issue*, not at first payment (fixes understated AR).

**Fold-ins (22 §5.2 E2):**
- **Branch GL pointers** (06-13 / P12): four FKs on `branches` — `CashAccID` / `BankAccID` / `BranchAccID` / discount — routing is scattered across `users.acc_bank_id`, `charges`, and name-walking today.
- **`SystemCurrency` base-currency parameter** (06-15) — replaces `'KWD'` hardcoded at 39+ sites.
- **FIFO auto-allocation** (03-22 / N10) — roughly 20 lines on top of the apply service; *offered* alongside manual allocation, never automatic per D3.
- **Non-account config keys** (06-15, 06-17, 06-25) validated and **read once per transaction**, the way `system_accounts` already is (06-29).
- **P5.3.A — COA additions (codes locked by orchestrator ruling A4, leaves added by A1/A2/A3/A6/S6/S8):** create `135900 Agent Receivables` (group, level 3 under `1350`) with `135800 Airline Incentive Receivable` and per-agent leaves minting as `135901…`; `1952 Airline Memo Control`; `2202 Payroll Deduction Clearing` (A5); `5146 Gateway Fee Recovery (Agents)` (contra-expense under `5140`); `5126` (agent loss recovery, contra-expense under `5100`); `5125 Airline Refund Clawback` (new expense leaf, never a rename of the misclassified `5130`); `5147 Gateway Reconciliation Difference` (kept, A7); `4160`; `5124 ADM`; `4210 Unclaimed Balances Written Back` (under `4200`); `Cancellation Fee Income` / `Change Fee Income` (under `4130`, S6); `5211 Sales Incentive Expense` (S8); rename `2230` to *Agent Commission Payable*; register their purpose codes incl. the W3.A2 anchors, and run doc 20 §2.4's M1–M8 tree migration behind §8.4's gates. **Hard ordering gate (A4): this depends on W3.A (22 §2.1c, §6.1)** — the legacy generator must be gone before `135900` is minted, or it mints `135901` itself first.

**Tests:** `OpenItemTest::apply_reduces_outstanding_and_never_goes_negative`, `unapply_reverses_via_new_lines`, `AgeingSourceTest::outstanding_equals_invoice_minus_applied`, `MemoTest::credit_note_posts_balanced`.

### P5.4 — Reporting layer rebuild (L–XL) — MF-32/33/34/35/37/38/39, BUG-C4, BUG-M3
`App\Services\Accounting\LedgerReportQuery` owns period/branch/cost-centre/company filters, opening-balance computation (via P5.2), sign rules, and roll-up — built on `TrialBalanceService`'s proven conventions. Rewrite P&L, ledger drill-down, and COA-dashboard totals as thin callers (kills the four divergent calculators). Fix P&L to `transaction_date` (BUG-C4) and replace `abs($total)` with `debit − credit` (BUG-M3). Build the **Balance Sheet** (A/L/Equity as-of a date + computed period profit as a virtual equity line — needs P5.2's retained earnings), **AR/AP ageing** (30/60/90/120 buckets via `DATEDIFF` in SQL on the P5.3 open-item source), **Cash Flow**, **Apply/Unapplied payments** report. Fix TB branch filter to `je.branch_id`.

**Fold-ins (22 §5.2 E4, E20):** **Day Book** (05-30 / N20); **comparative-period column** (05-9); **Balance-Sheet group summary + comparatives** (05-17 / N26); **ledger in foreign currency** (05-21 / N22 — a display toggle, the data is already on the line); **multi-line invoice consolidation in the ledger** (05-22 / N23). **Invariant (E20):** every query in `LedgerReportQuery` joins `transactions` and filters `posting_status = 'posted'` — `posting_status` lives on `transactions` only, and the draft workflow's "invisible to reports" rule (§4.3) and doc 20 §6.2's apply guard both depend on this join existing.

**Tests:** `LedgerReportInvariantTest::pl_ties_to_trial_balance` (post a JE whose `transaction_date` ≠ `created_at` month; assert P&L income/expense == TB — the BUG-C4 regression), `BalanceSheetTest::assets_equal_liabilities_plus_equity`, `AgeingTest::buckets_sum_to_outstanding`, `ArchitectureTest::no_report_query_reads_journal_entries_without_the_posting_status_filter`.

### P5.5 — Fixed assets & depreciation (M) — MF-48
`fixed_assets` (cost, acquired_at, useful_life, method, salvage, account links) + `fixed_asset_depreciations`; a monthly `depreciate` command posting Dr depreciation-expense / Cr accumulated-depreciation via `PostingService`. **Tests:** `DepreciationTest::monthly_run_posts_balanced_and_is_idempotent_per_period`.

### P5.6 — FX revaluation (M) — MF-44, MF-14
Activate `FX_GAIN_LOSS` (`5219`). Effective-date the rate lookup (`system_exchange_rates.valid_from`). Period-end `revalue-fx` command posting `Σ(original × current rate) − Σ(booked base)` per FC account as a JV into `fx_rate_adjustments` (reversed at settlement); book realized diffs in the PV flow. **Tests:** `FxRevalTest::open_fc_balance_revalued_to_period_end_rate`, `settlement_books_realized_diff`.

### P5.7 — Cost-centre dimension (S) — MF-43, R8
**Column-creation struck (22 §5.2 E18): the `cost_centers` master and both `cost_center_id` columns are now created in W3** (22 §2.1a/§2.1c) — the engine must stamp a real cost-centre id from the first cut-over document, so P5.7 cannot wait to create the table it depends on. P5.7 keeps the **hierarchy**, the branch/task-type **default rules** the engine's stamp falls back to when no more specific dimension applies, and the cost-centre P&L, which switches on in P5.4's query with zero backfill. **Tests:** `CostCenterTest::engine_stamps_cost_center_on_every_line` (the branch/task-type default path — the agent-cost-centre path stamped in W3 has its own test, `AgentDimensionTest::the_stamped_value_resolves_to_a_cost_centers_row_not_an_agents_row`, per 22 §2.1a — one test name, one owner).

### P5.8 — Travel-industry controls (M) — MF-27, MF-28
*(Retitled and narrowed — 22 §5.1 E5: BUG-H7's void fix moves to the void wave, W6 (22 §2.4), and MF-24's duplicate-invoice guard moves to W3 (22 §2.1b) — neither belongs in this phase's title or body any more.)*
- **Airline dimension (MF-27) + fare decomposition (MF-28):** resolve `substr(ticket_number,0,3) → airline`; parse the AIR FM commission element. (Do the cheap analytics — airline-wise sales, segment count, void-% — immediately, as they need only existing data.)
- **BSP reconciliation and ADM/ACM ship in P7.5 — see 22 §3.** *(The `bsp_statement_lines` table + CSV importer joined on `tasks.ticket_number` for per-ticket variance, formerly specified here, is P7.5's — Appendix B moves it there, 22 §5.1 E9.)*

**Tests:** see **W6** (`VoidTest::…`, 22 §2.4) for the void fix, **W3** (`DuplicateInvoiceTest::…`, 22 §2.1b) for the duplicate-invoice guard, and **P7.5** (`BspReconTest::…`, 22 §3) for BSP — each fold-in carries its acceptance tests at its new home.

### P5.9 — Budgeting (M) — MF-48
*(Citation corrected — 22 §5.2 E6: file 09 §MF-48 bundles Fixed assets & depreciation, Budgeting, and Recurring journal templates into one line; `MF-49` is Security hardening (password/session policy, P7 extended), not budgeting. P5.5 Fixed assets already cites MF-48; all three sub-phases share it.)*
*(Added in fable review: locked scope — the plan widened `accounts.budget_balance`/`variance` in P1.0 but never built the module.)*
`budgets` table (`company_id`, `fiscal_year`, `status draft|approved|locked`) + `budget_lines` (`budget_id`, `account_id` leaf-only, `period_month`, `amount decimal(18,3)`); a `BudgetService::variance(companyId, period)` that compares budget lines against actuals from `LedgerReportQuery` (P5.4) — never against the denormalized `accounts.budget_balance`, which becomes a derived cache or is dropped. Budget-vs-actual report (per account/branch/cost-centre) as a P5.4 report specialization. Import via CSV alongside the P7 opening-balance importer. **Tests:** `BudgetTest::variance_equals_actual_minus_budget_per_period`, `budget_lines_reject_non_leaf_accounts`.

### P5.10 — Bank & card reconciliation (M) — MF-45
*(Added in fable review: `bank_statement_lines` was listed in Appendix B but specified nowhere.)*
`bank_statement_lines` (`company_id`, `bank_account_id`, `statement_date`, `description`, `amount`, `matched_journal_entry_id` nullable, `status unmatched|matched|excluded`) + CSV importer (OFX deferred per C3); a matching screen that pairs statement lines against ledger lines on the bank leaf (auto-suggest on amount+date proximity); marking a pair sets `journal_entries.reconciled` — which the engine's `reverse()` already refuses to touch without `$force` (P1). Closing-balance check: statement closing == ledger balance + unmatched items, surfaced as the reconciliation report (this also replaces the raw-total queries patched in HF-11a with the P5.4 query layer). **Tests:** `BankRecTest::matched_line_blocks_reversal_without_force`, `reconciliation_report_ties_statement_to_ledger`.

Generic matching engine (statement_lines/statements, all sources) — see .planning/accounting-waves/reconciliation-design.md.

Nightly `accounting:reconcile --auto` (gateway API pull + re-match + self-checks; flags only) — gateway adapter moved here from P7; see reconciliation-design.md.

### P5.11 — Recurring journals (S) — MF-48
*(Citation corrected — 22 §5.2 E6: `MF-47` is "Open-item registry AR-only", subsumed by MF-18, not recurring journals. File 09 §MF-48 bundles Recurring journal templates with Fixed assets & Budgeting.)*
*(Added in fable review: blueprint 07 §3, absent from plan and defer list.)*
`recurring_journal_templates` (`company_id`, `name`, frequency `monthly|quarterly|yearly`, `next_run_date`, `end_date` nullable, `is_active`) + `recurring_journal_template_lines` (mirror of `LineDraft` fields); a scheduled `journals:run-recurring` command that materializes due templates into `DocumentDraft`s posted via `PostingService` with `idempotencyKey="recurring:{templateId}:{period}"` (idempotent per period by construction — safe against scheduler double-fire). Depreciation (P5.5) MAY later be re-expressed as a recurring template but ships standalone first. **Tests:** `RecurringJournalTest::due_template_posts_once_per_period_even_if_run_twice`.

### Rollback (P5)
Each sub-phase is independently flagged and additive (new tables, new commands, new report routes). The engine already enforces balance, so no sub-phase can unbalance the ledger. Year-end close (P5.2) is the one irreversible operation — guard it behind an explicit confirm + the `mysqldump` snapshot.

**Closes:** MF-2, MF-12, MF-17, MF-18, MF-19, MF-20, MF-27, MF-28, MF-30, MF-32, MF-33, MF-34, MF-35, MF-37, MF-38, MF-39, MF-43, MF-44, MF-45 (CSV portion), MF-47, MF-48, BUG-C4, BUG-H5, BUG-M3, R5, R6 (MF-24 moved to W3; MF-25 moved to P7.5; BUG-H7 moved to W6; MF-49 moved to P7-extended, integrity-check half in P5.17 — 22 §5.1 E5/E6/E7/E9/E11).

---

## P6 — Shareholder equity module (L)

**Goal:** per-client-company shareholder accounting — capital contributions, dividends, profit-distribution ratios, and an auditor-ready Statement of Changes in Equity. Multi-tenant: each client company has its own shareholders.

**Depends on:** P5.2 (year-end close / retained earnings — you cannot distribute profit that hasn't been closed to equity).

**Deliverables:**
```php
Schema::create('shareholders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');
    $table->string('name');
    $table->foreignId('capital_account_id')->constrained('accounts');   // per-shareholder equity leaf
    $table->foreignId('current_account_id')->nullable()->constrained('accounts'); // dividends payable / drawings
    $table->decimal('profit_share_ratio', 9, 6)->default(0);            // Σ per company must = 1.0
    $table->timestamps();
});
Schema::create('equity_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');
    $table->foreignId('shareholder_id')->constrained('shareholders');
    $table->enum('type', ['capital_contribution','capital_withdrawal','dividend_declared','dividend_paid','profit_allocation']);
    $table->foreignId('transaction_id')->nullable()->constrained('transactions'); // the posted GL doc
    $table->decimal('amount', 18, 3);
    $table->date('movement_date');
    $table->timestamps();
});
```
```php
final class EquityService
{
    /** Post a capital contribution: Dr cash/bank, Cr shareholder capital. Via PostingService. */
    public function contribute(Shareholder $s, float $amount, int $bankAccountId, \DateTimeInterface $date): PostedDocument;

    /** Declare a dividend: Dr retained earnings, Cr dividends payable (shareholder current acct). */
    public function declareDividend(int $companyId, float $total, \DateTimeInterface $date): array;

    /**
     * Allocate a closed year's net profit across shareholders by profit_share_ratio.
     * INVARIANT: Σ(ratios) must equal 1.0 (RatioSumException); allocation posts balanced
     * Dr retained-earnings / Cr each shareholder capital via PostingService. Idempotent per (company, year).
     */
    public function allocateProfit(int $companyId, int $fiscalYear): array;
}
```
Statement of Changes in Equity report built on `LedgerReportQuery` (P5.4): opening equity + contributions − withdrawals + profit − dividends = closing, per shareholder, per year.

### Acceptance tests
- `EquityTest::profit_allocation_requires_ratios_sum_to_one`.
- `…::dividend_declaration_posts_balanced`.
- `SoceTest::statement_reconciles_to_equity_accounts` — the SOCE closing equity per shareholder equals the sum of their capital + current account balances from the ledger.

### Rollback
All movements are posted GL documents reversible via `PostingService::reverse`; the tables are additive.

**Closes:** the shareholder-equity requirement (locked scope); R5.4 (equity accounting that "is not part of this system today").

---

## P7 — Product layer (L–XL)

**Goal:** the features that make it *sellable* and *onboardable*, not just correct.

**Depends on:** P5.4 (statements to export).

**Deliverables:**
- **COA templates on signup** — `coa_templates` + `coa_template_lines` (a Kuwait-services template, a generic-trading template); a signup step that clones a template into the new company's `accounts` via `AccountService`, seeds `system_accounts` purpose mappings, and creates the first `accounting_periods` row. (Replaces the fragile `CoaSeeder`.)
- **Auditor-ready statement exports** — PDF/XLSX of TB, Balance Sheet, P&L, ledger, ageing, SOCE, each with company header, period, "as-of" stamp, and a signed hash footer. Built on `LedgerReportQuery`.
- **Period-locking UI** — a screen over `accounting_periods` (open/soft-close/lock, with a warning list of un-posted drafts), replacing the per-row `Lockable` UI.
- **Opening-balance import** — CSV → a posted `OJV` via `PostingService`, validated to balance before commit (kills the mutable-column opening-balance anti-pattern for new tenants).
- **External-auditor read-only role** — a Spatie role with `view`-only permissions across all financial models, cross-company disabled, and download access to the auditor exports; enforced by P4's policies.
- **Arabic / RTL** — `name_ar` on `accounts` and document types; RTL Blade layout + Arabic number/date formatting; Kuwait-first locale (no VAT lines shown for KW tenants).

**Fold-ins (22 §5.2 E7 / §3 P7-extended):**
- **Six typed roots** including APPROPRIATIONS and EQUITY, with a `root_type` enum (01-16 / N2) — not five roots matched by English name strings. COA-template time is the last cheap moment to change the tree's shape.
- **`AccGroup` rollup key** (materialised path, +2 digits per level) + statement ordering by it (01-7 / 05-10 / N1, N25) — templates stamp it at clone time.
- **`AltAccCodeExp` / `AltAccCodeImp`** — two nullable columns holding the account's code in an external system (01-14 / N3), for auditor-package export.
- **`AccTransType` consolidation** — one `label` column replacing the three half-built mechanisms; cash/bank/gateway is inferred from parent names today (01-9 / MF-6, doc 20 §2.5).
- **Freeze cascade to children + ` (CLOSED)` rename + picker filtering** (01-11 / MF-5) — posting is already safe (`FrozenAccountException`); this is the UX half, and doc 20 §2.4's migration and §5.17's leaver flow both rely on the semantics being real.
- **Universal attachments registry** (07-31 / N38, D9) — attach by document type + number, with a size limit. Not per-module uploads.
- **Password policy** — history, complexity, renewal (07-22 / N34, D9, reinstated).
- **Session policy** — single active session per user/app, IP/device logging, inactivity timeout (07-25 / N35, D9, reinstated).

### Acceptance tests
- `SignupTemplateTest::new_company_gets_balanced_coa_and_purpose_mappings`.
- `OpeningImportTest::unbalanced_opening_csv_is_rejected`.
- `AuditorRoleTest::auditor_can_read_all_reports_but_cannot_post_or_see_other_companies`.
- `StatementExportTest::balance_sheet_pdf_matches_ledger_query`.
- `CoaTemplateTest::a_cloned_template_seeds_six_typed_roots_and_a_rollup_key_on_every_node`.
- `StatementOrderTest::statements_order_by_accgroup_not_by_name`.
- `AltCodeTest::export_uses_the_alternate_code_when_present`.
- `FreezeTest::freezing_a_group_cascades_to_children_and_renames_with_CLOSED`; `FreezeTest::freezing_a_leaf_with_a_non_zero_balance_is_refused`.
- `AttachmentTest::an_attachment_is_addressable_by_doc_type_and_number`.
- `PasswordPolicyTest::reused_password_within_history_is_rejected`.
- `SessionPolicyTest::a_second_concurrent_session_terminates_the_first`.

**Closes:** the product-layer locked scope; MF-40 (numbered statements), MF-10 (Arabic `name_ar`), MF-5 (freeze/disable in pickers).

---

## P8 — XBRL / iXBRL compliance (L)

**Goal:** IFRS-taxonomy-tagged financial statements with iXBRL export and an offline Arelle validation sidecar.

**Depends on:** P5.4 (the statement figures), P7 (the export layer).

**Deliverables:**
- `xbrl_taxonomy_mappings` — map each COA `account_type`/report line to an IFRS taxonomy concept (e.g. `ifrs-full:CashAndCashEquivalents`), per template so it clones on signup.
- `XbrlExportService` — render Balance Sheet / P&L / SOCE / Cash Flow from `LedgerReportQuery` into **iXBRL** (inline XBRL in XHTML) using the mappings; emit the instance document + the presentation context (period, entity identifier, unit=KWD).
- **Arelle validation sidecar** — a containerized Arelle service (or Artisan wrapper shelling to the Arelle CLI) that validates the generated iXBRL against the IFRS taxonomy; the export UI blocks download until validation passes and stores the validation report.

### Acceptance tests
- `XbrlMappingTest::every_reported_leaf_has_a_taxonomy_concept`.
- `XbrlExportTest::ixbrl_instance_is_wellformed_and_balances` (assets = liabilities + equity in the tagged facts).
- `ArelleValidationTest::generated_ixbrl_passes_arelle` (integration test against the sidecar, fixture company).

**Closes:** the XBRL locked-scope requirement.

---

## P9 — GCC VAT + ZATCA e-invoicing (XL)

**Goal:** VAT for GCC markets and KSA ZATCA (Fatoora) phase-2 e-invoicing. Kuwait ships with VAT *disabled* (no VAT today); the engine is VAT-aware so enabling a GCC tenant is config, not a rebuild.

**Depends on:** P1 (tax-aware lines), P7 (e-invoice document model + templates).

**Deliverables:**
- **VAT engine:** `tax_codes` (rate, type: standard/zero/exempt, jurisdiction) + `LineDraft` gains optional `taxCodeId`/`taxAmount`; `PostingService` posts the VAT leg to `VAT_OUTPUT`/`VAT_INPUT` control accounts (new purpose codes) and asserts `net + tax = gross` per taxable line. Per-company `vat_enabled` + `vat_registration_number` system params.
- **VAT return report** — output tax − input tax per period, from `LedgerReportQuery`.
- **ZATCA KSA e-invoicing (Fatoora phase 2):** generate the ZATCA-compliant UBL 2.1 XML invoice, embed the cryptographic stamp (CSID from the ZATCA onboarding API), the QR (TLV base64), the invoice hash + previous-invoice-hash chain; clear (B2B) / report (B2C) to the ZATCA API; store the cleared XML + QR on the invoice. A sandbox/onboarding flow for the CSID lifecycle.
- **Market gate:** Kuwait tenants → VAT off, no ZATCA; KSA tenants → VAT on + ZATCA clearing; other GCC → VAT on, no ZATCA.

### Acceptance tests
- `VatPostingTest::taxable_invoice_posts_net_plus_vat_equals_gross`.
- `VatReturnTest::return_equals_output_minus_input_tax_for_period`.
- `ZatcaTest::invoice_xml_validates_against_ubl_schema_and_carries_qr_and_hash_chain`.
- `ZatcaClearanceTest::b2b_invoice_is_cleared_and_stores_stamp` (sandbox integration).

### Rollback
VAT and ZATCA are per-company flags; a KW tenant is unaffected. The e-invoice XML/clearing is a post-issue side-channel — a clearing failure blocks the *e-invoice*, not the GL posting (which already committed via P1), so the books stay consistent.

**Closes:** the GCC/VAT/ZATCA locked-scope requirement; MF-6 VAT config, blueprint `VATEnable/VAT*` params.

---

## Appendix A — Traceability: every audit finding → phase

| Finding | Phase(s) |
|---------|----------|
| BUG-C1 (no debit=credit) | P1 (engine) + P2 (cutover) |
| BUG-C2 (leaf-only commented out) | P1.0 (`is_group` truth) + P1.1 (leaf guard) + P2 + P3 (6.1 re-home) |
| BUG-C3 (balance RMW + SQLi) | P0 (SQLi) + P1.1 (atomic maintenance) + P3 (rebuild) |
| BUG-C4 (P&L vs TB date) | P5.4 |
| BUG-C5 (report auth) | P0 + P4 |
| BUG-H1 (code gen) | P1.0 |
| BUG-H2 (account_type free text) | P1.0 |
| BUG-H3 (unsafe delete) | P0 (cascade→RESTRICT) + P1.0 (SoftDeletes) |
| BUG-H4 (reverse-then-apply) | P1 (reverse/repost) + P2 |
| BUG-H5 (opening balances) | P5.2 |
| BUG-H6 (name lookups unscoped) | P0 (:6143) + P1.0 (registry) + P2 (W2) |
| BUG-H7 (void flow) | P2 (W6) |
| BUG-H8 (`filterLedgers` crash) | P0 |
| BUG-H9 (soft-delete totals) | P0 |
| BUG-H10 (numbering) | P1.0 (SequenceService) |
| BUG-M1..M5 | P1.0 (M5 precision) / P5.4 (M3) / P0 (M4) / P2 (M2 top-up) / seeder (M1→P7 templates) |
| MF-1..MF-50 | as cited per phase above |
| R1..R8 | P1 + P2 (R1/R2/R3/R7), P4 (R8 tenant), P5.2 (R5), P5.3 (R6), P6 (R5.4 equity) |
| T-Finding-1..13 | P0 (live exploits) + P4 (structural) |
| F1..F14 | P0 (locks + unique indexes) + P1 (idempotency-key backstop) |
| Checks 1.1..9.8 | P3 (repair) |

## Appendix B — New tables introduced (by phase)
*(Updated — 22 §5.1 E9.)* P1: `system_accounts`, `serial_schemas`, + columns on `transactions`/`accounts`/`journal_entries`. **P2 (W3):** `cost_centers` (moved forward from P5.7 by 22 §5.2 E18 — the engine stamps it from W3's first cut-over document). P4: `company_id` across the invoice pipeline (note: the receipt table's real name is `invoice_receipts`, plural — verified against the live schema). P5: `accounting_periods`, `credit_notes`/`debit_notes`, `fixed_assets`/`fixed_asset_depreciations`, `fx_rate_adjustments`, `bank_statement_lines` (P5.10), `budgets`/`budget_lines` (P5.9), `recurring_journal_templates`/`_lines` (P5.11). **P5.12:** per-party credit columns — `credit_limit`, `credit_from_date`, `is_blacklisted` on `clients` and `agents`. **P5.13:** `agent_charge_policies` (consolidates `agent_loss` + `agent_charge`) + the agent settlement document tables (`doc_type = AST`). **P5.17:** `*_log` mirror tables on `accounts`, `transactions`, `journal_entries`. P6: `shareholders`, `equity_movements`. P7: `coa_templates`/`coa_template_lines`. **P7.5:** `bsp_statement_lines` (moved here from P5 — 22 §5.1 E9), plus `credit_notes`/`debit_notes` ADM/ACM extension columns (`ticket_number`, `pnr`, `airline_id`, `bsp_type`, `reason`). P8: `xbrl_taxonomy_mappings`. P9: `tax_codes` + VAT/ZATCA columns on `invoices`.

## Appendix C — Fable review notes (2026-07-07)
Reviewed against: sequencing correctness, locked-scope completeness, schema honesty vs the live prod clone, hotfix minimality, executor buildability. **Approved** with these additions/annotations:
1. **P5.9 Budgeting, P5.10 Bank reconciliation, P5.11 Recurring journals added** — all three were locked-scope or blueprint items missing from the original draft (budgeting columns were even being widened in P1.0 with no module to use them).
2. **Decided — DROP `actual_balance` (22 §5.2 E10, closing this note and 21 §6 Q1).** No longer an open decision: the engine's step 9 is already a no-op, `decimal(10,2)` cannot represent a 3-decimal KWD fils delta, and P5.4's `LedgerReportQuery` is the single balance source — a second, mutable, silently-truncating balance is exactly the drift P5.17's nightly checker exists to detect. **Corrected reader count: 137 references across 31 files in `app/` today** (the "115 across 20+" figure was doc 17's August measurement and is stale). Migration order: (1) inventory the 137 readers; (2) migrate each onto the ledger-derived accessor / `TrialBalanceService`, or mark it explicitly "display-only, stale-tolerant"; (3) delete the hand-increments (e.g. `AgentSettlementService::onPaymentCompleted()`'s `$gatewayAssetAccount->actual_balance += $netAmount; …->save();`); (4) drop the column in **W7**. The 41.5% dev-account disagreement figure (19 §R1) is **re-measured** before step 2 and after step 3, and both numbers recorded here.
3. **P2 shadow-mode mechanism** ("dual-writes to a scratch schema and diffs") is directionally right but underspecified — before W1 cutover, the executor must spec: same-DB prefixed tables vs separate schema, diff job cadence, and mismatch alert channel. Small design note, not a blocker.
4. Table-name nit: `invoice_receipt` → actual name `invoice_receipts` (Appendix B corrected).
