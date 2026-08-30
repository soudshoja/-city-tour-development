# 13 — Pooled→Per-Party Ledger Reattribution (PostingService · Per-Party AR/AP · Historical Data Migration)

**Target:** soud-laravel / citytourv2 lineage (`tour.citycommerce.group`, DB `citycomm_city-tour`; staging clone `citycomm_citytourv2` on `ct-server`).
**Date:** 2026-08-22.
**Position in the plan set:** execution plan for three already-decided items — (1) the single shared `PostingService`, (2) per-party AR/AP sub-ledger accounts mirroring the Agent pattern, (3) **the historical data migration that reattributes existing pooled-account journal entries to the new per-party accounts with dual-record traceability**. Items (1) and (2) are already phased in [10-implementation-roadmap.md](10-implementation-roadmap.md) (Phases 1–2, 5) and specified in [11-technical-implementation-plan.md](11-technical-implementation-plan.md) (P1, P2, P5.3); this file recaps them only as gates and adds their execution deltas. Item (3) — the reattribution migration — **is planned nowhere else** and is specified in full here (Stages C–F).
**Complexity key** (matches file 10/11): **S** ≤ 2 dev-days · **M** ~1 week · **L** ~2–4 weeks · **XL** > 1 month.

> **⚠ AMENDED 2026-08-24 — read [14-prod-drift-findings-2026-08.md](14-prod-drift-findings-2026-08.md) alongside this file.** Seven corrections from the July–August prod drift investigation are stitched in below as **[AMENDMENT n]** blocks. The most material: (1) the Stage A census is **27 unique files across prod+local**, not 24, and each entry needs a `prod-live`/`local-ahead`/`both` deployment tag; (2) Gate A.2(2) is invalidated as written — prod's unbalanced count collapsed 2,273→56 via an undocumented `AccountingRepair` suspense-parking run (Jul 7–13), not genuine repair; (3) the Stage B.2 supplier-creation citation moved to `SupplierActivationService::activate()`; (4) Stage E must whitelist the new pooled asset account "Unbilled Supplier Cost" (code 1430); (5) self-service company provisioning (Aug 20–21) must not outrun `system_accounts` seeding; (6) all Stage C counts must be re-derived at execution time (ledger grew 52,670 → 72,901+ entries); (7) new package-client companies with no pooled history skip Stages C–E entirely — their `module.accounting` activation needs only P1+P2+Stage B live.

> **Decision status:** PostingService and per-party AR/AP are decided — this document does not re-litigate them. The open decisions this document surfaces are marked **[USER-DECIDE]** (mostly naming and one scope question), per the standing rule to never invent account/module names.

---

## 0. What this document adds — and what it deliberately does not re-plan

| Piece | Already planned in | What this file adds |
|---|---|---|
| `PostingService` (one writer, `Σdebit = Σcredit` at save, FK/purpose-code account resolution) | [11 §P1–P2](11-technical-implementation-plan.md) — full contract (`DocumentDraft`/`LineDraft`/`post()`/`reverse()`), wave-by-wave cutover of all hand-rolled `JournalEntry::create()` sites | Stage A: call-site census refresh (24 files today, up from the audited 21) + gate criteria the migration depends on |
| Per-party AR/AP accounts | [10 Phase 1 item 5 / Phase 5](10-implementation-roadmap.md), [11 §P5.3](11-technical-implementation-plan.md) ("party de-pooling"), MF-2 in [09](09-prioritized-missing-features.md) | Stage B: concrete schema, tree placement that avoids the mixed-parent trap, creation-trigger recommendation, pointer-FK columns |
| **Historical reattribution of pooled entries** | **Nowhere** — 11 §P3 repairs *corruption* (unbalanced docs, sign errors) but never moves clean historical entries off the pooled `Clients`/supplier accounts; P5.3 de-pools **go-forward only** | Stages C–F: pre-migration audit, the reattribution mechanism with dual-record traceability, verification, rollback |

Why the migration matters: the prod copy holds **52,670 journal entries across 20,723 transactions, 1,754 invoices, 1,743 clients** ([data-integrity-results-prod-copy.md](data-integrity-results-prod-copy.md) §Clone verification). Every historical customer receivable line sits on one shared `Accounts Receivable → Clients` leaf (`01-chart-of-accounts.md` Finding 11; per-client accounts were removed by migration `2025_03_28_105231_remove_account_id_from_clients_table.php`). If P5.3 only changes new postings, per-party ageing and statements (MF-34, MF-40) start from a blank history — a client's statement would show nothing before cutover day. Reattribution makes the per-party sub-ledger true from day one.

**The one structural fact that makes this a migration, not a rebuild:** every ledger line already carries its source-document keys — `journal_entries.invoice_id`, `invoice_detail_id`, `task_id`, `transaction_id`, `type`/`type_reference_id` are all fillable columns (`app/Models/JournalEntry.php:16-48`), and the source documents carry the party: `invoices.client_id` (`app/Models/Invoice.php:19,84`), `tasks.client_id` + `tasks.supplier_id` (`app/Models/Task.php:18,21,214-226`). The party was never lost — it just never reached the account dimension. We re-derive it and post a correction; we never guess.

---

## Stage A — Gate: PostingService live and sole writer  ·  Complexity: covered by 11 §P1–P2 (XL)

No new design here — [11 §P1](11-technical-implementation-plan.md) is the specification (pipeline steps 1–10: purpose-code resolution via `system_accounts`, per-line iron rules, `abs(Σdebit − Σcredit) < 0.0005` at step 4, `PeriodGuard`, atomic numbering, idempotency on `transactions.idempotency_key`). Two execution deltas found 2026-08-22, plus the gates Stage D depends on:

### A.1 Census refresh — 24 files now hand-roll `JournalEntry::create()` (audit counted 21)

> **[AMENDMENT 1] The true census is 27 unique files across prod + local** (file 14 §2). This section's 24 was measured on the local checkout only. Deltas: **add 3 prod-only files** — `AccountingRepair.php` (already executed Jul 7–13, see Gate A.2 amendment), `AssignTaskPaymentMethod.php` (manual JE poster, May 2026), `FixProfitLossEntries.php` (Feb 2026, W7 retire bucket). **Tag 3 listed files as local-ahead, not on prod**: `DotwAI/Services/AccountingService.php` (dev only), `AgentSettlementService.php` (commit `7f73badab` never deployed), `CheckMyFatoorahPayments.php` (prod's deployed copy has NO `JournalEntry::create`). Every census entry needs a `prod-live` / `local-ahead` / `both` tag so P2 waves target the right environment and the ArchitectureTest gate counts correctly per environment.

`grep -rln "JournalEntry::create" app/` on the current `soud-laravel` checkout returns **24 files**. Three are new since the 2026-07-07 audit and must be added to the P2 wave table ([11 §P2](11-technical-implementation-plan.md)):

| New call site | Suggested wave | Rationale |
|---|---|---|
| `app/Modules/DotwAI/Services/AccountingService.php` | W2 (gateway/booking webhooks) | Posts from the DOTW booking confirmation path — queue/webhook context, exactly the unauthenticated-scope hazard class of `02-posting-engine.md` Finding 14 |
| `app/Services/AgentSettlementService.php` | W6 (tasks + services) | Shipped with the agent-settlement feature (commit `7f73badab`); posts against the agent loss pointer accounts |
| `app/Console/Commands/FixProfitAndCommission.php` | W7 (console/repair — retire) | Seventh member of the `Fix*` drift-repair family; dies with the rest once the engine maintains balances |

### A.2 Gates that Stage D (migration) will assert before running

1. **`ArchitectureTest::no_journal_entry_writes_outside_engine` green** — all 24 files migrated or deleted (11 §P2 phase-exit gate). The migration must not run while any legacy path can still post to the pooled account, or reattribution becomes a treadmill (same argument as 11 §0: "repair is only durable once the engine is the sole writer").
2. **P3 data repair complete** — specifically: check 1.1 (2,273 unbalanced groups) at ~0, check 2.1 (7 invoices with zero JEs) backfilled, check 7.1 (2 cross-tenant JE rows) resolved, check 8.1 (46 negative-amount lines) corrected ([data-integrity-results-prod-copy.md](data-integrity-results-prod-copy.md)). Unbalanced or cross-tenant lines cannot be safely reattributed (Stage C classes M3/M5 quarantine any residue).

   > **[AMENDMENT 2] This gate is invalidated as written.** Prod already shows check 1.1 at ~56 (not 2,273) — but via `AccountingRepair.php`'s suspense-parking run (Jul 7–13, 2026): 5,114 plug legs across 3,679 transactions posted into per-company "Suspense / Adjustments" (code 3900) accounts. Measured live 2026-08-24: City Travelers 3,676 lines, **net KWD 22,766.017** parked (Dr 284,295.105 / Cr 261,529.088); Ojeen 3 lines, Cr 138.600. The gate must read: *check 1.1 ~0 **excluding** suspense-plugged transactions, and the suspense balance itself dispositioned by the accountant.* Classification rule: the plug legs are not party-resolvable by design (no source document — leave on suspense, they are P3's problem); the original pooled legs of plugged transactions resolve normally via E1/E2/E3. **[AMENDMENT 6]** All counts in this file (52,670 entries, 2,273 unbalanced, 1,743 clients…) are audit-era; the live ledger passed 72,901 entries in Aug 2026 — every Stage C count must be re-derived at execution time, never reused from this document.
3. **`system_accounts` registry seeded** for every company (11 §P1.0 `SystemAccountsSeeder`) — the migration resolves the pooled control accounts by purpose code (`RECEIVABLE_CONTROL_LEGACY`, see B.4), never by `Account::where('name', 'Clients')`, which is ambiguous by design (the seeder ships two accounts named "Clients": `CoaSeeder.php:35` under Accounts Receivable and `CoaSeeder.php:101` under Refund Payable — `01-chart-of-accounts.md` Finding 12).

---

## Stage B — Per-party AR/AP accounts (the Agent pattern, copied exactly)  ·  Complexity: **L** (= 11 §P5.3 first bullet, expanded)

### B.1 The working template being copied

The Agent sub-ledger is the one party pattern the codebase already does right (`00-executive-summary.md` "What is already solid"; `01-chart-of-accounts.md` Finding 11):

- **Pointer FKs on the party row:** `agents.profit_account_id` / `agents.loss_account_id` — migration `2026_02_11_162453_add_profit_loss_accounts_in_agents_table.php`; fillable at `app/Models/Agent.php:28-29`; `belongsTo(Account::class, …)` relations at `app/Models/Agent.php:80-85`.
- **Auto-created account tree on party creation:** `AgentController.php:449` (`Account::create([...])` for the per-agent leaf), pointer stamped at `AgentController.php:550` (`$agent->update(['profit_account_id' => $profitAccountId])`) and `:666` (loss account), plus a per-agent receivable leaf via `accounts.agent_id` (`Agent::account()` hasOne, `app/Models/Agent.php:68-70`).
- **Consumers resolve via the FK, never by name** — which is why the agent flows are absent from the magic-name pathology list in `02-posting-engine.md` Finding 14.

### B.2 Schema — pointer FKs on the party masters

One migration (names final per 11 §P5.3, already agreed there):

```php
Schema::table('clients', function (Blueprint $table) {
    $table->foreignId('receivable_account_id')->nullable()
          ->constrained('accounts')->restrictOnDelete();   // RESTRICT, not CASCADE — 02 Finding 8
});
Schema::table('supplier_companies', function (Blueprint $table) {
    $table->foreignId('payable_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
    $table->foreignId('cost_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
});
```

Notes:
- `clients.account_id` existed and was deliberately dropped (`2025_03_28_105231`); the dead `Client::account()` relation at `app/Models/Client.php:80-82` is removed in the same PR, replaced by `receivableAccount()`.
- **[AMENDMENT 3]** The activation-time leaf creation has since moved: it now lives in `SupplierActivationService::activate()` (ported from citytourv2 commit `f58ea38c1`; on both prod and dev). Stage B.5's observer/hook targets that service, not the controller — already centralized, which makes the hook *easier* than planned. Original text (stale citation): suppliers already *have* per-supplier payable+cost leaves auto-created on activation (`SupplierCompanyController.php:127-167`) — but the pointer is ambiguous (`Supplier::payableAccount()` hasOne over `accounts.supplier_id` returns an arbitrary one of 2+ rows, `03-transactions-ar-ap.md` Finding 6), so posting falls back to name matching (`Account::where('name', $supplier->name)`, `TaskController.php` ~1900; `BankPaymentController.php:627-630` even uses `LIKE "%$supplierName%"`). For suppliers the work is therefore mostly a **pointer backfill** (walk `accounts.supplier_id` + parent group to disambiguate payable vs cost, write the two FKs), not new account creation. The AP reattribution scope (Stage C) is correspondingly small: mainly the 25 parent-account postings (check 6.1) and the `TaskController.php:2118-2128` parent-payable fallback lines.
- Airlines (`airlines.account_id`, per 11 §P5.3) are out of scope for the historical migration — no airline account has history to move (`01` Finding 11: "airlines have no COA presence at all").

### B.3 Tree placement — do NOT nest per-client leaves under the pooled leaf

The pooled `Clients` account currently holds tens of thousands of postings. Creating children under it would manufacture exactly the mixed-parent condition (`01-chart-of-accounts.md` Finding 2: a node with both direct postings and children corrupts every rollup, and `TrialBalanceService` **drops group-account postings from the TB entirely** — `02-posting-engine.md` Finding 4, `app/Services/TrialBalanceService.php:96-98`).

Therefore:

1. Create a **new group node** under `Accounts Receivable` (level 3, sibling of the pooled `Clients` leaf) to hold the per-client leaves. **[USER-DECIDE: name]** — candidate names to present, not to assume: "Trade Receivables — Clients", "Clients (Per Party)", "عملاء — تفصيلي". Same for the supplier side if any new grouping is needed (probably none — supplier leaves already sit under the per-service `Suppliers (X)` groups).
2. Per-client leaves are created via `AccountService::ensurePartyLeaf()` (11 §P1.0) with codes from `AccountCodeGenerator` — never the `'AGT-' . rand(...)` anti-pattern catalogued in `01` Finding 5.
3. The pooled `Clients` leaf **stays a leaf and stays put** during migration (it is the credit side of every reattribution document, Stage D). After verification (Stage E) drains it to ~0, set `accounts.disabled = 1` on it — enforcement of `disabled` at posting time is a P1 iron rule (pipeline step 3e), so this becomes a real freeze rather than the dead flag of `01` Finding 9. Do **not** rename it until every name-based lookup is dead (post-P2), and do not delete it ever (its historical lines remain attached to it by design — that is the dual-record guarantee).

### B.4 Registry entries

Add two purpose codes to `system_accounts` per company (seeded in the same PR):

| purpose_code | Points at | Used by |
|---|---|---|
| `RECEIVABLE_CONTROL_LEGACY` | the existing pooled `Clients` leaf (the one under Accounts Receivable — disambiguated by `parent_id`, not name) | Stage D credit leg; post-migration nothing else may resolve it |
| `RECEIVABLE_PARTY_GROUP` | the new per-client group node from B.3 | `AccountService::ensurePartyLeaf` parent anchor |

### B.5 Creation trigger — recommendation: **observer for new parties, targeted batch for existing, lazy for dormant**

Three candidate policies were considered:

| Policy | Verdict |
|---|---|
| (a) Eager for all: create a leaf for every existing client up front | Rejected — `accounts` has 1,478 rows in prod; adding 1,743 client leaves in one shot more than doubles the COA, and most of those clients have little or no ledger history. The COA tree screen (`CoaController::buildAccountTree`) and TB recompute already do full-table work per request (`02` Finding 6) — don't double their input for dead rows. |
| (b) Fully lazy: create on first posting only | Rejected as the *sole* mechanism — the historical migration itself needs accounts for every party with history, and creating them mid-migration inside the reattribution loop mixes two concerns and two failure modes. |
| **(c) Hybrid (recommended)** | **New** clients/suppliers: `created` model observer calls `AccountService::ensurePartyLeaf()` — identical trigger point to the Agent flow (`AgentController.php:449/550/666`), just moved into an observer so imports and console paths can't bypass it. **Existing** parties: Stage C produces the exact set of client/supplier ids that own ≥1 journal-bearing document; Stage D's pre-pass creates leaves for that set only (idempotent `firstOrCreate` keyed on the pointer FK, inside a lock). **Dormant** existing parties (no history) get theirs lazily on their first future posting via the same `ensurePartyLeaf`, which is idempotent by construction. |

This gives the migration everything it needs, keeps the COA proportional to real activity, and leaves exactly one code path that ever creates a party leaf.

---

## Stage C — Pre-migration data audit (quantify, classify, quarantine)  ·  Complexity: **S–M**

Run on the staging clone first, then prod, using the same read-only discipline as [data-integrity-audit.md](data-integrity-audit.md) §3. The existing pack ([data-integrity-queries.sql](data-integrity-queries.sql), checks 1.1–9.8) is the foundation; this stage **extends it with a new section 10** (append to the same file at implementation time — new numbered checks, same conventions: read-only SELECTs, no hardcoded DB name).

### C.1 Baseline snapshot (the "before" for Stage E)

Persist — not just eyeball — a per-account trial balance as of the cutoff:

- `pre_reattribution_tb_snapshots` (or a CSV under version control): `company_id`, `account_id`, `account_name`, `closing_debit`, `closing_credit`, `closing_balance`, `as_of`, `captured_at`. Populate from `TrialBalanceService::generate()` (`app/Services/TrialBalanceService.php:11`) — the audit's verified-correct recompute-from-raw-lines source (`00-executive-summary.md` "What is already solid"), **not** from `accounts.actual_balance`, which is fragmentary and drift-prone (`02` Finding 6).
- Also snapshot the operational-side truth per client: `Σ invoices.amount` and `Σ payment_applications.amount` grouped by `invoices.client_id` — used for the per-party cross-check in E.3.

### C.2 New checks 10.x — reattribution eligibility census

All scoped `WHERE je.deleted_at IS NULL` (the raw-query soft-delete trap of BUG-H9) and per company. Pooled account ids come from the `system_accounts` rows seeded in B.4 — shown as `:pooled_ar_id` below.

```sql
-- 10.1 Scope: every live line on the pooled AR account, bucketed by how it can be resolved
SELECT
  CASE
    WHEN je.invoice_id IS NOT NULL THEN 'E1_invoice'
    WHEN je.task_id    IS NOT NULL THEN 'E2_task'
    WHEN je.type = 'payment' AND je.type_reference_id IS NOT NULL THEN 'E3_payment_chain'
    ELSE 'M1_no_source_link'
  END AS bucket,
  COUNT(*) AS lines, SUM(je.debit) AS debit, SUM(je.credit) AS credit
FROM journal_entries je
WHERE je.account_id = :pooled_ar_id AND je.deleted_at IS NULL
GROUP BY 1;

-- 10.2 E1 lines whose invoice is soft-deleted or missing a client  -> M2
SELECT je.id, je.invoice_id, i.deleted_at, i.client_id
FROM journal_entries je
JOIN invoices i ON i.id = je.invoice_id
WHERE je.account_id = :pooled_ar_id AND je.deleted_at IS NULL
  AND (i.deleted_at IS NOT NULL OR i.client_id IS NULL);

-- 10.3 Conflicting resolution: invoice's client != task's client on the same line  -> M4
SELECT je.id, i.client_id AS invoice_client, t.client_id AS task_client
FROM journal_entries je
JOIN invoices i ON i.id = je.invoice_id
JOIN tasks t    ON t.id = je.task_id
WHERE je.account_id = :pooled_ar_id AND je.deleted_at IS NULL
  AND i.client_id <> t.client_id;

-- 10.4 Lines in still-unbalanced transactions (must be 0 after P3)  -> M3 quarantine
SELECT je.id, je.transaction_id
FROM journal_entries je
JOIN ( SELECT transaction_id FROM journal_entries
       WHERE deleted_at IS NULL AND transaction_id IS NOT NULL
       GROUP BY transaction_id
       HAVING ABS(SUM(debit) - SUM(credit)) > 0.001 ) ub
  ON ub.transaction_id = je.transaction_id
WHERE je.account_id = :pooled_ar_id AND je.deleted_at IS NULL;

-- 10.5 Second pooled account: lines on "Refund Payable > Clients" (CoaSeeder.php:101) — count only, scope decision below
-- 10.6 AP side: lines posted to supplier PARENT/group accounts (join check 6.1's logic restricted to the Suppliers (X) subtrees)
-- 10.7 Distinct party census: clients (via 10.1 E1/E2 joins) and suppliers owning >=1 live line — feeds B.5's batch pre-pass
```

### C.3 Eligibility classes and their disposition

| Class | Definition | Disposition |
|---|---|---|
| **E1** | Party resolvable via `je.invoice_id → invoices.client_id` | Auto-reattribute (expected large majority — `InvoiceController::addJournalEntry` stamps `invoice_id` on the pooled debit, `InvoiceController.php:1485-1514`) |
| **E2** | No invoice link; resolvable via `je.task_id → tasks.client_id` (AR) / `tasks.supplier_id` (AP) | Auto-reattribute |
| **E3** | Resolvable only via `je.type='payment'` + `type_reference_id → payments → invoice → client` | Auto-reattribute, resolution method recorded as the longer chain |
| **M1** | No source link at all (`invoice_id`, `task_id`, `type_reference_id` all NULL) — e.g. manual receipt-voucher lines where party survives only as free-text `je.name` (`03` Finding 6) | **Manual review queue.** Never auto-migrated, and **never resolved by parsing `description`/`name` strings** — description-coupled accounting logic is precisely the pathology the audit condemns (`02` Finding 1 gap 2, Finding 7). A human maps each line (or batch of matching lines) to a party or explicitly marks it "leave pooled". |
| **M2** | Source document soft-deleted or party-less (10.2) | Manual review queue |
| **M3** | Member of a still-unbalanced transaction (10.4) | Quarantine — belongs to P3's suspense/repair workflow, not this migration. Gate A.2(2) should make this set empty. |
| **M4** | Conflicting party across sources (10.3) | Manual review queue |
| **M5** | Cross-tenant rows (check 7.1 residue) | Quarantine — P3 owns these |

**Gate to Stage D:** counts published per class per company; M3 = 0 and M5 = 0; the M1/M2/M4 worklist exported as CSV and acknowledged by the accountant (per-worklist sign-off, same protocol as 11 §P3's dry-run gate). The migration then runs on E1–E3 plus whatever M-rows the accountant has manually mapped.

### C.4 Scope decision — the second "Clients" account **[USER-DECIDE]**

`Refund Payable → Clients` (`CoaSeeder.php:101`) is a *liability* pooled by party in exactly the same way. Options: (a) de-pool it in the same run (same mechanism, `refund` resolution via `refunds.invoice_id → invoices.client_id`); (b) defer — count it in 10.5, leave pooled, revisit with the memo module (P5.3 CRN work). Recommendation: **(b) defer** unless per-party refund-liability statements are a near-term product need; it keeps this migration's blast radius to one account per side. Decision recorded before Stage D starts.

---

## Stage D — The reattribution migration  ·  Complexity: **M–L**

### D.1 Mechanism decision: transfer documents through the engine + a dedicated audit table

**Chosen:** for every reattributable pooled line, post a **balanced reattribution document** through `PostingService` — the old line is never edited, never deleted, never re-pointed — and record the old→new linkage in a purpose-built audit table.

For an AR line that originally debited the pooled account (the common case — invoice issue):

```
RAT document (docType 'JV', sub_type 'REATTR', idempotency_key 'reattr:je:{old_je_id}'):
    Dr  {client}.receivable_account_id        amount = old line's (debit - credit)
    Cr  RECEIVABLE_CONTROL_LEGACY (pooled)    same amount
```

Pooled-account *credit* lines (receipt vouchers crediting `Clients`, refund reversals) get the mirrored pair. AP-side parent-account lines get `Dr supplier-parent / Cr {supplier}.payable_account_id` mirrored appropriately. Each new line is stamped with the old line's `invoice_id`, `invoice_detail_id`, `task_id`, `currency`/`original_amount` context so per-party drill-down lands on the real source document.

Net effect: the pooled account's balance drains toward zero (residual = exactly the M-class rows still pooled); each per-party account accretes precisely its share; **every trial balance total is unchanged at every moment**, because each RAT document is internally balanced and both legs live inside the same AR (or AP) subtree.

### D.2 Why this mechanism and not the alternatives

| Alternative | Why rejected |
|---|---|
| **In-place `UPDATE journal_entries SET account_id = …`** (with or without a `migrated_from_account_id` column) | Destroys the old record — fails the dual-record requirement outright. Also violates the posted-line immutability doctrine the whole plan is built on (`02` Finding 7/8 recommendations; 11 §P1 `reverse()` contract: "never mutate/delete posted lines"), and leaves zero double-entry evidence that the move happened. |
| **Supersede-and-replace** (soft-flag old row via `superseded_by_id`, insert replacement row on the party account) | Keeps both records, but the old row must now be *excluded from every report* — and the audit already proved this codebase cannot reliably apply even the existing `deleted_at` filter to its raw queries (BUG-H9, `00-executive-summary.md`). A second exclusion flag multiplies that failure class across `TrialBalanceService`, ~30 inline `ReportController` queries, and `CoaController` rollups. Worse, the replacement rows are inserted outside a balanced document (the old document already balanced; a one-sided replacement inside it is invisible to `findUnbalancedTransactions` only if the flag filter is applied everywhere — a knife-edge). |
| **Full `reverse()` + repost of each original document** (11 §P3's repair pattern) | Correct but disproportionate: it would reverse and repost *every* leg (revenue, gateway, fee) of ~20k documents just to move one leg, tripling ledger volume and churning lines that were never wrong. Reserved for documents that are *also* broken — which P3 already handles. |
| **Column-only linkage** (`migrated_from_id` on `journal_entries`, no audit table) | The link survives but the *evidence* doesn't: no room for which source document resolved the party, which resolution method fired, who ran it, which batch, or the M-row manual reviewer's reasoning. The explicit user requirement is that a human can later trace old→new and judge whether the migration was right — that judgment needs the provenance, not just the pointer. |

The chosen design keeps every report correct **with zero query changes** (nothing is hidden; the correction is itself ordinary double-entry), satisfies dual-record traceability in both directions, and reuses the engine's own idempotency and balance guarantees instead of building parallel ones.

### D.3 The audit table — full schema (the dual-record traceability spine)

```php
Schema::create('journal_entry_reattributions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');

    // OLD record (untouched, still live on the pooled account)
    $table->foreignId('old_entry_id')->constrained('journal_entries');   // unique below: one reattribution per line, ever
    $table->foreignId('old_account_id')->constrained('accounts');        // the pooled account at migration time
    $table->decimal('old_debit', 18, 3);
    $table->decimal('old_credit', 18, 3);

    // NEW record (the party-side line of the RAT document)
    $table->foreignId('new_entry_id')->nullable()->constrained('journal_entries');  // null until posted (dry-run rows)
    $table->foreignId('new_account_id')->nullable()->constrained('accounts');       // the per-party leaf
    $table->foreignId('rat_transaction_id')->nullable()->constrained('transactions'); // the RAT document header

    // Provenance — how the party was resolved (the "reasoning" a human traces later)
    $table->string('party_type', 16);            // client | supplier
    $table->unsignedBigInteger('party_id');
    $table->string('resolution_method', 32);     // invoice_client | task_client | task_supplier | payment_chain | manual
    $table->string('source_document', 32)->nullable();   // e.g. invoice | task | payment
    $table->unsignedBigInteger('source_document_id')->nullable();
    $table->text('notes')->nullable();           // mandatory for resolution_method = manual (reviewer's reasoning)

    // Run bookkeeping
    $table->uuid('batch_id')->index();
    $table->enum('status', ['planned','posted','rolled_back'])->default('planned');
    $table->timestamp('migrated_at')->nullable();
    $table->unsignedBigInteger('migrated_by')->nullable();  // user id, or null for the console run identity
    $table->timestamp('rolled_back_at')->nullable();
    $table->timestamps();

    $table->unique('old_entry_id');              // idempotency at the audit layer
    $table->index(['company_id', 'party_type', 'party_id']);
    $table->index(['company_id', 'status']);
});
```

Trace paths this guarantees:
- **old→new:** pooled line id → `journal_entry_reattributions.old_entry_id` → `new_entry_id`/`rat_transaction_id`, plus *why* (`resolution_method`, `source_document`, `notes`).
- **new→old:** any per-party RAT line → `new_entry_id` lookup → the original pooled line, still sitting untouched on the pooled account with its original date, description, and document.
- **Nothing hidden:** both entries remain live ledger rows; an auditor can reproduce the whole migration from the ledger alone (every RAT doc is `sub_type='REATTR'`, batch-tagged in its narration) even if this table were lost.

### D.4 Resolution algorithm (per pooled line, strict order, first hit wins)

1. `je.invoice_id → invoices.client_id` (skip if invoice soft-deleted → M2) — method `invoice_client`.
2. `je.task_id → tasks.client_id` (AR) / `tasks.supplier_id` (AP) — methods `task_client`/`task_supplier`. If step 1 also resolved and disagrees → M4, stop.
3. `je.type = 'payment'` + `type_reference_id → payments.invoice_id → invoices.client_id` — method `payment_chain`.
4. A pre-approved manual mapping exists for this line (accountant resolved an M-row via the review queue) — method `manual`, `notes` required.
5. Otherwise: leave pooled, class M1, worklist.

Explicitly forbidden: resolving via `je.name`/`je.description` text, via `Account::where('name', …)`, or via any auth-scope-dependent lookup — the three resolution anti-patterns the audit catalogued (`02` Findings 1, 14; `tenant-isolation-audit.md`). Every resolved `party_id` is asserted to belong to `je.company_id` before posting (the check-7.x class must not be reintroduced by the migration itself).

### D.5 Document dating and periods

RAT documents are posted with **`transaction_date` = the old line's `transaction_date`** — otherwise per-party ageing buckets (`DATEDIFF` from doc date, MF-34) and opening balances for historical periods would be wrong, defeating the migration's purpose. Consequences, handled explicitly:

- The migration command runs with a `PeriodGuard` **migration exemption** (an explicit `--allow-locked-periods` flag whose use is logged into the batch record) — the only sanctioned back-dated writer, used once.
- **Hard sequencing rule: this migration must complete before the first P5.2 year-end close is executed for any historical year.** An OJV freezes a year's closing balances; back-dating RAT docs into a closed year would silently diverge from the frozen opening. If a close has already run (it should not have — P5.2 depends on this being done first), the fallback is one net RAT document per party per closed year dated to the year's final day, posted before re-running the close — flagged here so the sequencing never has to be invented under pressure.

### D.6 The command — idempotency and resume

`php artisan accounting:reattribute-party-ledger {--company=} {--side=ar|ap} {--dry-run : default true} {--batch-size=500} {--allow-locked-periods}`

- **Plan phase (dry-run, default):** classifies every pooled line (D.4), writes `journal_entry_reattributions` rows with `status='planned'` and no postings, emits the per-class counts and the M-worklist CSV. Re-running re-plans idempotently (planned rows for lines whose classification changed are updated; `unique(old_entry_id)` prevents duplicates).
- **Execute phase (`--dry-run=false`):** processes `planned` rows in `old_entry_id` order, chunked; each chunk in one DB transaction posts the RAT documents via `PostingService::post()` with `idempotency_key = "reattr:je:{old_entry_id}"` and flips rows to `posted`.
- **Idempotent/resumable by two independent layers:** the audit table's `unique(old_entry_id)` + `status` (a resumed run skips `posted`), and the engine's `transactions.unique(company_id, idempotency_key)` (11 §P1.1) — even a crash between posting and status-flip cannot double-post; the retry gets the existing `PostedDocument` back (P1 pipeline step 1) and completes the status flip.
- Run on the staging clone first, full Stage E verification there, accountant sign-off, then prod inside a maintenance window with a fresh `mysqldump` — the identical protocol as 11 §P3's "Dry-run-on-staging protocol & sign-off gate".

---

## Stage E — Verification (prove no money was lost, duplicated, or moved between subtrees)  ·  Complexity: **S**

All balance assertions computed via `TrialBalanceService` (`generate()` at `app/Services/TrialBalanceService.php:11`, `getOpeningBalances()` at `:131`) — the recompute-from-raw-lines source the audit verified as correct — never via `accounts.actual_balance` (`02` Finding 6) and never via the header `transactions.amount` (`02` Finding 1). Compare against the C.1 snapshot.

| # | Invariant | How |
|---|---|---|
| V1 | **Global TB unchanged:** per company, total debits, total credits, and every root subtotal (Assets/Liabilities/Income/Expenses/Equity) identical before vs after, to 0.001 | Diff `TrialBalanceService::generate()` output vs `pre_reattribution_tb_snapshots` |
| V2 | **AR subtree conserved, redistributed:** `pooled_Clients(after) + Σ per-client leaves(after) == pooled_Clients(before)`; same for the AP scope | Subtree sum over the AR group node |
| V3 | **Residual = worklist, exactly:** pooled account's remaining balance == Σ (debit−credit) of the M1/M2/M4 lines still recorded against it (reconciled line-by-line, not just in total) | Join pooled lines against `journal_entry_reattributions` (absent = must be M-class) |
| V4 | **Row conservation:** every `old_entry_id` unique; `COUNT(status='posted')` == number of RAT party-side lines; `Σ old (debit−credit)` over posted rows == `Σ` party-leg amounts == `Σ` pooled counter-leg amounts | Aggregates over `journal_entry_reattributions` + RAT lines |
| V5 | **No new imbalance:** `TrialBalanceService::findUnbalancedTransactions()` (`:191`) returns the same set as before the run (RAT docs are engine-posted, so any growth means engine breach — abort) | Before/after diff |
| V6 | **GL ties to operations per party:** for each of the top-N clients by volume (and a random sample), per-party account balance == `Σ invoices.amount − Σ payment_applications.amount − Σ direct payments` from the C.1 operational snapshot; discrepancies enumerated and explained (legit causes: M-rows still pooled, credits) | Cross-source query |
| V7 | **Full pack regression:** re-run all 46 checks in `data-integrity-queries.sql` + section 10; sections 5 (orphans), 7 (cross-company), 8 (negatives) still at their P3 values; 10.1's E-buckets now ~0 | Standard pack run |

> **[AMENDMENT 4]** Stage E's V-checks must **whitelist "Unbilled Supplier Cost" (code 1430)** — a deliberately pooled *asset* account added on prod 2026-07-27 (`InvoiceController::reclassifyUnbilledCostToCogs()`, `sub_type='cogs_reclass'`). It is legitimate, out of AR/AP party scope, and must not be flagged as an un-migrated pooled residue.

**Sign-off gate:** V1–V5 must pass exactly; V6 exceptions individually dispositioned by the accountant; only then is the batch declared final, the pooled account disabled (B.3), and the result fed to the module-activation decision (Stage G).

---

## Stage F — Rollback  ·  Complexity: **S** (designed-in, cheap by construction)

Because originals were never touched and every change is an ordinary posted document:

1. **Surgical rollback (any subset, any time before sign-off):** for each `journal_entry_reattributions` row in the target `batch_id` with `status='posted'`, call `PostingService::reverse($ratTransaction, …)` with `idempotency_key = "rev:{rat_transaction_id}"` (the engine's reversal idempotency, 11 §P1.1). The REV documents restore every balance to the pooled state to the fils; set `status='rolled_back'`, stamp `rolled_back_at`. The old entries were never modified, so there is nothing to "restore" — the ledger simply nets back. Reversal dating follows the same D.5 exemption.
2. **Partial re-run after a fix:** rolled-back rows revert to `planned` on the next plan phase and can be re-executed with a fresh batch — the engine idempotency key includes the old `je` id, and the prior RAT doc is now fully reversed, so a new RAT document is legitimate (`reattr:je:{id}` collision is avoided by suffixing the batch: `reattr:je:{id}:b{n}` from batch 2 onward; batch 1 keeps the bare key).
3. **Nuclear:** the pre-run `mysqldump` from D.6's protocol — only for a catastrophic mid-run failure that the two idempotency layers somehow did not contain (none is expected; this is the same last-resort stance as 11 §P3 Rollback).

Note the deliberate symmetry: rollback uses the identical mechanism (balanced documents through the engine) as the migration itself, so a rollback is as auditable as the migration — the trace chain becomes old line → RAT doc → REV doc, all live.

---

## Stage G — Dependencies and sequencing (the whole line, explicit)

```
P0 hotfixes (file 12, ships first)
  └─> P1 PostingService + AccountService + system_accounts + SequenceService   [11 §P1]
        └─> P2 strangler cutover — all 24 call sites (A.1 census), ArchitectureTest gate  [11 §P2]
              └─> P3 data repair: checks 1.1 / 2.1 / 7.1 / 8.1 → ~0            [11 §P3]
                    └─> Stage B  per-party accounts + pointer FKs + observers   [= 11 §P5.3 party bullet]
                          └─> Stage C  pre-migration audit, classes, worklist sign-off
                                └─> Stage D  reattribution run (staging → prod)
                                      └─> Stage E  verification V1–V7 + accountant sign-off
                                            ├─> pooled accounts disabled; ageing/statements (MF-34/40, 11 §P5.4)
                                            │    now correct over FULL history, not just post-cutover
                                            ├─> P5.2 year-end close may now run for historical years
                                            │    (MUST NOT run before Stage E — see D.5)
                                            └─> GATE for the "hidden Accounting module" activation:
                                                 per-company `module.accounting` entitlement flag
                                                 (separate initiative — flag name/config location TBD there)
                                                 flips only for companies whose Stage E sign-off is recorded
```

> **[AMENDMENT 7] Stage G's per-company gate applies only to companies with pooled HISTORY.** A new package-client company (onboarded after P1+P2+Stage B are live) has zero pooled entries — Stages C–E are vacuous for it, and its `module.accounting` flag may flip with no migration at all. The flag mechanism now exists: `module.accounting` boolean row in the company-scoped `settings` table, read via `Company::hasModule()` (built 2026-08-24, Phase 1 of the TravelERP launch — see `.planning/PLAN-TRAVELERP-LAUNCH.md`).
>
> **[AMENDMENT 5]** Self-service company registration went live on prod 2026-08-20/21 (`CompanyProvisioner` → `CoaSeeder::run()` per new company; dormant, 0 uses). Sequencing rule: **no non-KWD company, and no volume onboarding through that pipeline, before `system_accounts` seeding (P1.0) and the PostingService base exist** — otherwise every provisioned company enlarges the P1 backlog and multi-currency arrives before any FX handling (P5.6).

Hard ordering rules, restated once:

1. **PostingService before per-party accounts matter:** until P2's gate, legacy paths keep posting to the pooled account by name (`InvoiceController.php:1485-1514`, `PaymentController.php:6143`), so de-pooling would leak straight back.
2. **P3 before Stage C/D:** unbalanced (2,273) and cross-tenant (2) lines are un-reattributable by definition; the migration quarantines residue (M3/M5) but must not be P3's workaround.
3. **Stage B before Stage D:** the migration posts *to* the pointer-FK accounts; it never creates them inline except through the same `ensurePartyLeaf` pre-pass.
4. **Stage D+E before the first P5.2 year-end close** (D.5) and **before the `module.accounting` flag flips for any company** — exposing the Accounting module to clients with per-party statements that omit their own history (or still show a pooled lump) is precisely the first-impression failure the activation plan exists to avoid.

### Stage summary

| Stage | Theme | Complexity | Exit gate |
|---|---|:---:|---|
| A | PostingService live + sole writer (recap of 11 §P1–P2 + census deltas) | (XL, planned elsewhere) | ArchitectureTest green; P3 checks ~0 |
| B | Per-party AR/AP accounts + pointer FKs (Agent pattern) | L | FKs live; observers on; registry codes seeded; names confirmed **[USER-DECIDE]** |
| C | Pre-migration audit + eligibility classes + worklist | S–M | Class counts published; M3=M5=0; worklist signed off |
| D | Reattribution run (RAT docs + `journal_entry_reattributions`) | M–L | All E-class rows `posted` on staging, then prod |
| E | Verification V1–V7 against `TrialBalanceService` | S | V1–V5 exact; V6 dispositioned; accountant sign-off |
| F | Rollback capability (reverse-by-batch) | S | Tested on staging clone before any prod run |
| G | Feed into `module.accounting` activation | — | Per-company sign-off recorded |
