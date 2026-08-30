# 14 — Production Drift Findings (2026-07-07 → 2026-08-22)

**Scope:** All accounting-relevant changes made to production (`tour.citycommerce.group`, app root `/home/citycomm/tour.citycommerce.group`) since the 2026-07-07 audit baseline, and their impact verdicts against `13-party-ledger-reattribution-plan.md`.
**Method:** Read-only SSH investigation (agent run 2026-08-22, reviewed and verdicts confirmed by lead session). DB state checks were read-only SELECTs via a bootstrapped Laravel script; no writes made anywhere.
**Context:** Production has drifted from its GitHub deploy source (`ShobakMoha/city-tour`, frozen at `431f97e`, 2026-04-06) via direct SSH/FTP hotfixes. `.bak_YYYYMMDD_*` files next to live sources mark hotfix dates. The local dev checkout (`soud-laravel`) is *also* ahead of prod in places — the two have diverged in both directions.
**Prod DB snapshot at investigation time:** 3 companies (all KWD), **72,901 live journal entries** (up from the 52,670 the audit baselined — confirms plan 13 Stage C.1's "take a fresh snapshot" requirement is real, not optional).

---

## 1. Headline finding — `AccountingRepair.php` already ran (suspense parking)

| | |
|---|---|
| File | `app/Console/Commands/AccountingRepair.php` (**prod-only**, never committed to any repo) |
| Built | 2026-07-07 |
| Executed | 2026-07-07 → 2026-07-13, with `--commit` |
| What it does | For every unbalanced transaction, posts one balancing `JournalEntry` into a per-company **"Suspense / Adjustments"** equity account (append-only, dry-run by default). |
| Confirmed effect | **5,114 suspense lines across 3,679 transactions, 2 companies, ~KWD 756,966 total parked.** Unbalanced-transaction count on prod: 2,273 (audit baseline) → **56** today. |

**Why this matters:**

- Plan 13's Gate A.2(2) ("data-integrity check 1.1 unbalanced groups → ~0") now *appears* nearly satisfied on prod — but via suspense-plugging, not root-cause repair. The repaired transactions are "balanced" only because of an added, source-document-less leg.
- The pooled-account legs of those transactions are unaffected by the plug — their party resolution (Stage C/D E1/E2/E3 classes) still applies normally. Only the **gate narrative** and the treatment of the suspense legs themselves need defining.
- **⚠ Business flag, outside plan scope:** the ~KWD 757K unexplained-imbalance figure exists only in this command's own log output. It has likely never been surfaced to the accountant/owner in any report. It should get a human review regardless of this remediation plan.

**Verdict: CHANGES-DESIGN** — plan 13 Stage A.1/C.2 must add this file to the census and explicitly define how already-suspense-patched transactions are classified (not "still M3", but not "cleanly resolved" either).

---

## 2. Authoritative journal-entry write-site census (prod vs plan 13)

Grep basis: `JournalEntry::create | journalEntries()->create | addJournalEntry(` over prod `app/` (`.bak` files excluded from count).

- **Prod live total: 24 files** (25 with the broader pattern — `RunAutoBilling.php` is caught only by the broader pattern, on both prod and local; not a real discrepancy).
- Plan 13's Stage A inventory (24 files) was taken from the **local checkout**, not prod. The two lists differ in both directions:

### On prod, NOT in plan 13's list (3 — all must be ADDED to the census)

| File | Origin | Notes |
|---|---|---|
| `app/Console/Commands/AccountingRepair.php` | Prod-only hotfix, Jul 2026 | See §1. Already executed. |
| `app/Console/Commands/AssignTaskPaymentMethod.php` | Prod-only hotfix, May 2026 | Bulk-reassigns `payment_method_account_id` on tasks by GDS issued-by code and posts matching JEs ("mirrors UI updateJournalPaymentMethod"). Manual-only, not cron'd. **A manual tool that moves money between ledger accounts outside any documented posting path.** |
| `app/Console/Commands/FixProfitLossEntries.php` | Prod-only, Feb 2026 (pre-freeze) | Drift-repair sibling the local checkout never had. Joins the W7 "retire with the rest" bucket. |

### In plan 13's list, NOT live on prod (3 — census must tag deployment status)

| File | Status |
|---|---|
| `app/Modules/DotwAI/Services/AccountingService.php` | Module doesn't exist under the prod app root. DOTW AI runs only on `development.citycommerce.group` (confirmed via crontab — all `dotwai:*`/`akeed-dotwai:*` entries point at development). Local/dev-ahead. |
| `app/Services/AgentSettlementService.php` | Doesn't exist on prod; commit `7f73badab` is local-only, not deployed. Local-ahead. |
| `app/Console/Commands/CheckMyFatoorahPayments.php` | File exists on prod but the **deployed version has no `JournalEntry::create` call** — the local repo's version was later enhanced to post JEs that prod hasn't received. Local-ahead. |

### Net census correction

Only **21 of plan 13's 24 files are both deployed and JE-writing on production today**. Prod additionally carries 3 files plan 13 never inventoried. **True combined census across both environments: 27 unique files**, and each entry needs a deployment-status tag — `prod-live` / `local-ahead` / `both` — so the P2 cutover waves target the right environment and the ArchitectureTest "all files migrated or deleted" gate isn't silently short.

---

## 3. Full findings table with verdicts

| # | Finding | Date | Verdict vs plan 13 | Reasoning |
|---|---------|------|--------------------|-----------|
| 1 | `AccountingRepair.php` suspense-parking, already executed (§1) | Jul 7–13 | **CHANGES-DESIGN** | Gate A.2(2) and Stage C.3's M3 class assume genuine repair; prod shows ~0 unbalanced via a suspense plug the plan doesn't name. Census + gate wording must change. |
| 2 | `AssignTaskPaymentMethod.php` — undocumented manual JE poster | May 2026 | **ADDS-SCOPE** | New write site invisible to the census; joins the P2 console/repair wave (alongside `FixProfitAndCommission`). |
| 3 | `FixProfitLossEntries.php` — orphaned prod-only Fix* command | Feb 2026 | **ADDS-SCOPE** | Same W7 retire bucket; must be added so the migration-complete gate counts it. |
| 4 | `InvoiceController::reclassifyUnbilledCostToCogs()` — new `type='cogs_reclass'` posting: Dr per-supplier COGS / Cr **new pooled asset account "Unbilled Supplier Cost" (code 1430)** at invoicing | Jul 27 | **ADDS-SCOPE (minor)** | File already censused, so no new file — but a newly-discovered pooled, non-per-party asset account that Stage E's V1/V2 trial-balance checks must whitelist as legitimate, and that plan 13 doesn't name anywhere. |
| 5 | `InvoiceController` — new try/catch "receivable-posting fix (INV ledger gap)" that **deletes** JournalEntry/Transaction/Invoice rows non-atomically on failure | Jul 27 | **NO-IMPACT (validates design)** | Live evidence of exactly the atomicity gap PostingService's transactional `post()` fixes. No scope change. |
| 6 | `PaymentController` MyFatoorah webhook hotfix — completion derived from signed webhook body instead of re-calling rate-limited `GetPaymentStatus` (was 429-ing, stalling payer confirmation 6–13 min) | Jul 21 | **NO-IMPACT** | No new call site; live incident validates prioritizing Wave W2 (gateway webhooks) in cutover order. |
| 7 | Per-supplier payable+cost leaf creation moved from `SupplierCompanyController.php:127-167` into `SupplierActivationService::activate()` (local commit `f58ea38c1`, also on prod) | In window | **CHANGES-DESIGN (citation only)** | Plan 13 Stage B.2's cited creation-trigger location is stale. B.5's hybrid trigger should target `SupplierActivationService::activate()` — already a centralized service, which makes the hook *easier* than the plan assumed. Still name-lookup + `parentCode+1` sequential coding, so B.2/B.3 concerns stand. |
| 8 | `companies.currency` (default KWD) + full self-service registration pipeline (`CompanyRegistrationController`, `CompanyInviteController`, `ProvisionCompany`, `CompanyProvisioner` → calls `CoaSeeder::run()` per new company) | Aug 20–21 | **NO-IMPACT today, ADDS-SCOPE future** | Dormant: 0 companies provisioned this way; all 3 existing companies KWD. But it structurally opens multi-currency companies before any FX handling exists, and every provisioned company joins the P1 `system_accounts` seeding backlog. Needs a sequencing line in Stage A.2(3)/Stage G so provisioning can't outrun accounting readiness. |
| 9 | `TaskController.php` Aug 14 hotfix (`.bak_20260814_qy5pkk`) — removed Jazeera/Fly Dubai exclusion from the "0/0 task backfilled by priced sibling" rule | Aug 14 | **NO-IMPACT** | Changes which existing TaskController call sites fire for two carriers; adds/removes nothing, doesn't touch pooled-account resolution. |
| 10 | New migrations: `2026_07_14` (visa appointment_date), `2026_07_28` (suppliers.whatsapp_group), `2026_08_20` (company_invites, companies.currency), `2026_08_21` (company_gds_pccs) | Jul–Aug | **NO-IMPACT** | Verified: none touch `journal_entries`, `accounts`, `invoices`, `payments`, or `coa_*`. (`company_invites.monthly_fee` exists but nothing posts it to accounting yet.) |
| 11 | `InvoiceObserver.php`, `InvoicePartialObserver.php` modified | In window | **NO-IMPACT** | Notification-only (email/WhatsApp); full read confirmed no JournalEntry/Account code. |

---

## 4. Incidental observations (not chased, logged for awareness)

- `routes/web.php.bak_20260821_selfreg` (Aug 21) is the backup taken right before self-registration routes went live — the CompanyProvisioner pipeline is very recent and untested in production traffic.
- Several Jul/Aug `.bak` files for `AirFileParser.php`, `SupplierPdfDetector.php`, `TurkishNdcParser.php` are document-parsing hotfixes (AIR/PDF ingestion), not accounting — out of scope here.
- `system_accounts` table (plan 13's P1 registry) **does not exist on prod** — P1 genuinely hasn't started, consistent with plan 13's framing of Stage A as "gate, not yet done."

---

## 5. Required amendments to plan 13 (summary)

1. **Stage A census** → replace the 24-file list with the 27-file combined census, each entry tagged `prod-live` / `local-ahead` / `both`; add findings #1–#3 files to the appropriate P2 waves.
2. **Gate A.2(2) wording** → "unbalanced ~0" must exclude suspense-plugged transactions; define the classification of the 3,679 suspense-patched transactions and their 5,114 plug legs (plug legs are not party-resolvable by design; original pooled legs resolve normally via E1/E2/E3).
3. **Stage B.2/B.5 citation** → supplier account-creation trigger is now `SupplierActivationService::activate()`, not `SupplierCompanyController.php:127-167`.
4. **Stage E whitelist** → add "Unbilled Supplier Cost" (1430) as a legitimate pooled asset account (it is deliberately pooled and out of AR/AP party scope).
5. **Stage A.2(3)/Stage G sequencing** → self-service company provisioning must not onboard a non-KWD company (or any company at volume) before `system_accounts` seeding and the PostingService base exist.
6. **Fresh snapshot mandatory** → ledger grew 52,670 → 72,901 entries since audit; all Stage C counts must be re-derived at execution time, never reused from the audit.
