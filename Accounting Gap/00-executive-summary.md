# 00 — Executive Summary

**Audit:** citytourv2 (travel-agency Laravel 11 app) accounting module vs the `travel-accounting-system` skill blueprint (a mature production IATA/BSP agency system, ~260 tables / ~860 stored procedures).
**Codebase audited:** `main` branch checkout only (55-branch mirror; unmerged branches cross-referenced by name where relevant).
**Date:** 2026-07-07
**Overall completeness:** **~34%** (dimension-weighted).

---

## One-paragraph maturity assessment

citytourv2 has faithfully built the **shape** of the blueprint — a per-company hierarchical chart of accounts, a `journal_entries` ledger with soft-deletes and 3-decimal money, a `TrialBalanceService` that correctly recomputes balances from raw lines, an `is_locked` lock framework, a working payment-application registry, and a genuinely strong GDS auto-import pipeline that funnels all 12 task types through one feeder. What it has **not** built is the blueprint's **invariant, posting, and close layers**. There is no single posting service: ~21 controllers/commands each hand-roll `JournalEntry::create()`, so nothing asserts `sum(debit) = sum(credit)` at save time (five distinct paths post provably one-sided documents), nothing enforces leaf-only posting (the one guard ever written is commented out), and accounts are resolved by ~237 magic-name string lookups — several of which run **unscoped in unauthenticated gateway callbacks**, creating a cross-tenant posting risk. Posted ledger rows are freely mutated and hard-deleted (an account delete cascades away its journal history), the live `actual_balance` column is maintained non-atomically in a minority of flows (one path even interpolates an unvalidated request amount into raw SQL), opening balances have three contradictory definitions, and there is no financial-year / period-close concept at all. The reporting layer compounds this: P&L filters by `created_at` while the Trial Balance uses `transaction_date` (so the two can never tie out), report authorization is enforced **only in the navigation menu** (any agent can fetch company P&L or any account ledger by URL), and the Balance Sheet, AR/AP ageing, Cash Flow, and every travel-industry analytic (BSP reconciliation, segment counts, airline incentives) are simply absent. In short: a solid operational skeleton with a largely-missing accounting-integrity spine. The good news is that the missing pieces are well-scoped and dependency-ordered — a single `PostingService` plus a hardened `AccountService` unlock most of the remaining work.

---

## Completeness by dimension

| # | Dimension | % complete | Most critical gap |
|---|-----------|:---------:|-------------------|
| 01 | Chart of Accounts | **38%** | Leaf-only posting invariant is commented out; accounts resolved by ~237 magic-name lookups instead of party-master FK pointers; clients pooled into one shared `Clients` account, airlines have no COA presence. |
| 02 | Double-Entry Posting Engine | **22%** | No save-time `debit = credit` assertion anywhere — multiple production paths post one-sided documents by design; no single posting service. |
| 03 | Transactions & AR/AP | **45%** | Balanced-document invariant unenforced (5 proven unbalanced write paths); customer AR pooled into one account so per-party ageing/statements are structurally impossible. |
| 04 | Travel Industry (IATA/BSP) | **35%** | No BSP statement reconciliation at all; void flow matches reversal entries by description string and silently misses auto-invoiced tickets (client credited, AR/revenue never reversed). |
| 05 | Reporting Layer | **32%** | P&L (`created_at`) and Trial Balance (`transaction_date`) cannot tie out; report authorization enforced only in menus (URL bypass); no Balance Sheet, no ageing, no cash flow. |
| 06 | Data Model, Numbering & Config | **40%** | Account balances maintained by scattered non-atomic read-modify-write (plus a raw-SQL amount-interpolation vector); number-series engine race-prone with latent cross-tenant `invoice_number` collision; no financial-year/period-close entity. |
| 07 | Modules & Config (sub-ledgers, year-end, cross-cutting) | **30%** | No year-end close (P&L never swept to equity); no FX revaluation despite multi-currency bookkeeping; no credit/debit-note memo module; credit-apply GL posting silently swallows exceptions. |

---

## What is already solid (preserve, don't rebuild)

- **`TrialBalanceService`** — recomputes opening/movement/closing from raw lines with soft-delete awareness, normal-balance handling, and an `is_balanced` tolerance flag; `findUnbalancedTransactions()` already ships on the TB screen. This is the correct seed for the future canonical report query.
- **GDS auto-import pipeline** — watched folders → staging tasks → validation → task financials → auto-invoice; multiple feeds (Amadeus AIR, TXT, PDF/passport AI, TBO/MagicHoliday APIs, n8n webhook) all write one Task shape. Faithful to the blueprint's feeder pattern.
- **One-ledger-many-feeders** — all 12 task types flow `Task → InvoiceDetail → addJournalEntry`; per-type revenue and per-supplier payable/cost accounts auto-create.
- **Agent sub-ledger** — real per-agent GL pointer FKs (`profit_account_id`/`loss_account_id`) with auto-created account trees; the working template to copy for clients/suppliers/airlines.
- **Gateway 3-line receipt**, **hotel dual-currency sub-accounts**, **payment-application registry**, **`Lockable` cascade framework**, **soft deletes + 3dp money storage** — all correct in isolation.

---

## Deliverables in this folder

### Per-dimension gap reports (full file:line citations + code excerpts)
- **[01-chart-of-accounts.md](01-chart-of-accounts.md)** — COA structure, numbering, account types, party accounts, invariants.
- **[02-posting-engine.md](02-posting-engine.md)** — double-entry balance, leaf-only rule, balances, edit/delete, audit, period locks.
- **[03-transactions-ar-ap.md](03-transactions-ar-ap.md)** — document types, sales invoice posting, RV/PV, memos, open-item apply, ageing.
- **04-travel-industry.md** — IATA/BSP specifics: void, duplicate-invoice guard, BSP recon, airline/stock/incentive. *(Produced by the travel-industry auditor; archived in the workflow scratchpad — not re-filed into this folder due to the run's path bug. Full findings are reproduced in files 08–10 and the verification log below.)*
- **[05-reporting.md](05-reporting.md)** — canonical query, P&L, Balance Sheet, ageing, ledger, bank rec, authorization.
- **06-data-model.md** — numbering engine, ledger header shape, financial year, system parameters, party master, cost center. *(Same archival note as 04.)*
- **[07-modules-config.md](07-modules-config.md)** — credit-apply, year-end close, bank/card rec, FX revaluation, memos, fixed assets, budgeting.

### Cross-cutting audits
- **[golden-rules-integration.md](golden-rules-integration.md)** — the 8 system-wide invariants (esp. rule 7: one shared posting engine vs. per-controller hand-rolling).
- **[concurrency-idempotency-audit.md](concurrency-idempotency-audit.md)** — non-atomic balance RMW, unlocked sequence increments, webhook retry double-posting.
- **[tenant-isolation-audit.md](tenant-isolation-audit.md)** — unscoped account lookups in unauthenticated contexts, cross-tenant ledger exposure.
- **[data-integrity-audit.md](data-integrity-audit.md)** + **[data-integrity-queries.sql](data-integrity-queries.sql)** — runnable queries to quantify existing drift (unbalanced docs, orphaned lines, duplicate codes).

### Synthesis (this pass)
- **[08-prioritized-bug-list.md](08-prioritized-bug-list.md)** — every confirmed `buggy` finding, ranked critical→low, with exact files and concrete fixes.
- **[09-prioritized-missing-features.md](09-prioritized-missing-features.md)** — every confirmed `missing`/`partial` finding, ranked by foundational layer + severity, with dependencies.
- **[10-implementation-roadmap.md](10-implementation-roadmap.md)** — dependency-ordered phased plan (goal / scope / findings resolved / complexity).
- **[verification-log.md](verification-log.md)** — adversarial-verification audit trail: 50 confirmed + 1 refuted high/critical finding, with reasoning and sub-claim corrections.

> **Verification caveat:** all high/critical findings in files 08–10 were independently adversarially re-verified against the codebase (see verification-log.md). Medium/low findings are reported **as-found** and were **not** adversarially re-checked — they are flagged as such wherever they appear.
