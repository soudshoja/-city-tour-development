# Audit 01 — Chart of Accounts

**Dimension:** Chart of Accounts (COA)
**Blueprint:** `.claude/skills/travel-accounting-system/references/01-chart-of-accounts.md`
**Codebase audited:** `C:\Users\User\city-tours-main` (citytourv2 mirror, `main` branch only)
**Date:** 2026-07-07
**Completeness estimate:** ~38%

## Executive summary

citytourv2 has a real, working, per-company hierarchical COA: an `accounts` table with `parent_id`/`root_id`/`level`, a per-company seeder that runs on company onboarding, a tree-rendering controller with rollup totals, opening balances, and Excel import/export. That is the good news, and it covers the *shape* of the blueprint.

What is almost entirely absent is the blueprint's **rule layer**. There is no central account-creation service, so every one of the ~12 places that create accounts invents its own (frequently wrong) code, level, and type. None of the invariants that keep the tree valid — leaf-only posting, no mixed parents, derived account type, max depth, unique name/code, freeze-blocks-posting — are enforced anywhere. The one guard that was written (a `JournalEntry::creating` hook rejecting postings to accounts with children) is **commented out**. The live running balance column (`actual_balance`) is maintained in only a handful of flows (one of which updates the wrong account and contains a SQL-injection vector) and is stale everywhere else; the UI silently recomputes balances from journal entries instead. Account resolution for posting is done by ~60 magic-name string lookups (`Account::where('name', 'Clients')`, `LIKE '%Receivable%'`) rather than the blueprint's party-master pointer FKs, and the seeded COA itself contains duplicate names that make several of those lookups ambiguous.

Per-party leaf accounts exist for **suppliers** (payable + cost accounts auto-created on activation) and **agents** (receivable + profit + loss accounts with real pointer columns `profit_account_id`/`loss_account_id`), but **clients post to a single shared "Clients" account** (per-client accounts were explicitly removed in migration `2025_03_28_105231_remove_account_id_from_clients_table.php`), and **airlines have no COA presence at all** beyond a retroactive "delegate Amadeus" split tool.

---

## Finding 1 — Hierarchical self-referencing tree: PRESENT_OK

**Blueprint (§1):** "A self-referencing hierarchical tree of accounts, up to 6 levels deep," group/header nodes vs transactional leaves.

**Found:**
- `database/migrations/2025_03_17_091543_create_accounts_table.php` — `accounts` table with `parent_id` (self-FK, `constrained('accounts')`), `level`, `company_id`.
- `app/Models/Account.php:52-90` — `parent()`, `children()`, `root()` relations.
- `app/Http/Controllers/CoaController.php:363-472` (`buildAccountTree`) — recursive tree build with parent rollups computed from children (single-query optimized version) and leaf balances computed from `journal_entries` aggregates plus `opening_balance`.
- Multi-tenancy: `app/Traits/BelongsToCompany.php:34-53` adds a global `company_id` scope on `Account` and auto-fills `company_id` on create — each company gets its own tree.

**Assessment:** The structural core is genuinely implemented and the tree-rendering path is even performance-tuned. Depth is unbounded (see Finding 8) and grouping is display-time-derived rather than flag-driven (see Finding 2), but the tree itself is fine.

**Recommendation:** None structurally. Add a composite index on `(company_id, parent_id)` if not present in production.

---

## Finding 2 — Leaf-only posting invariant (`HasSubAcc` discipline): BUGGY (critical)

**Blueprint (§1, §6.3):** "A parent is *either* a header *or* a leaf, never both — this guarantees postings only ever land on leaves, which keeps rollups clean." Rule 3: "No mixed parents."

**Found:**
- `accounts.is_group` exists (`2025_04_03_112301_add_new_columns_in_accounts_table.php`, default `true`) but is **never read by any posting or creation code**. Grep across `app/` shows it is only *written* at creation time and round-tripped in import/export (`app/Http/Controllers/CoaController.php:818,922`, `app/Imports/AccountsImport.php:33`).
- The one enforcement mechanism ever written is **commented out**: `app/Models/JournalEntry.php:57-78`:

```php
// public static function boot()
// {
//     static::creating(function ($journalEntry) {
//         $account = Account::find($journalEntry->account_id);
//         if ($account && $account->children()->exists()) {
//             ...
//             throw new \Exception('Cannot create journal entry for an account that has child accounts.');
//         }
//     });
// }
```

- Group-ness in the UI is inferred at render time from `children()->exists()` (`CoaController::buildAccountTree`), not from `is_group`, so the flag and reality can diverge freely.
- Mixed parents happen in practice: `CoaController::delegatePriceAmadeus` (`CoaController.php:944-1143`) exists precisely to *retroactively* split journal entries that were posted directly onto the "Amadeus" account and then re-home them onto newly created child accounts — i.e., the system routinely posts to nodes that later become parents. Similarly, gateway child accounts are created under "Payment Gateway" (`ChargeController.php:226-244`) while other flows post journal entries directly to a "Payment Gateway" account resolved by name (`CreditController.php:187`, `PaymentApplicationService.php:697`).

**Why it matters:** Once an account holds both direct postings and children, every rollup (COA screen, trial balance, P&L) double-counts or under-counts depending on the traversal. The commented-out guard shows the team hit this and disabled the fix instead of fixing the offending flows.

**Recommendation:** Re-enable the `JournalEntry::creating` guard (as a DB-level check too, if possible), backfill `is_group` from `children()->exists()`, and enforce Rule 3 at account creation: reject a child insert when the parent has journal entries, and reject a posting when the account has children. Provide a one-time "re-home postings to a new leaf" migration tool for the existing mixed nodes (the delegate-Amadeus code is a prototype of exactly this).

---

## Finding 3 — Fixed roots: PARTIAL (five roots, not six; matched by name)

**Blueprint (§3):** Seed exactly six roots — ASSETS, LIABILITIES, INCOMES, EXPENSES, APPROPRIATIONS, EQUITY — the spine everything hangs from.

**Found:**
- `database/seeders/CoaSeeder.php:18-22` seeds **five** roots: Assets (1000), Liabilities (2000), Equity (3000), Income (4000), Expenses (5000). No Appropriations root exists anywhere (`grep -i appropriation` over the whole repo: zero hits).
- Seeding *is* wired to company onboarding: `app/Http/Controllers/AdminUsersController.php:228` (`CoaSeeder::run($company->id)`) and `database/seeders/EntitySeeder.php:80`, plus a manual `company-coa:seed` command (`app/Console/Commands/SeedCompanyCoaCommand.php`).
- Roots are identified *by display name* everywhere: `CoaController.php:111-135` (`$rootConfig = ['Assets' => 'normal', ...]`, `firstWhere('name', $rootName)`), `AgentController.php:398`, `ChargeController.php:203-206`, etc. There is no stable machine key (enum, constant code) for a root; renaming "Income" to "Incomes" in the DB would silently drop the whole Income subtree from the COA page (the `match` at `CoaController.php:128-134` would leave `$incomes = null`).

**Assessment:** For a Kuwaiti travel agency, missing Appropriations is a defensible simplification (tax provisions/reserves could live under Equity). The fragile name-matching is the real defect.

**Recommendation:** Add a `root_type` enum column (or reuse the existing but abandoned `root_type` column added in `2025_03_26_084232` and then converted by `2025_03_27_043843_change_root_type_to_root_id_in_accounts.php`) with values `asset|liability|income|expense|equity(|appropriation)`, set it in the seeder, and key all root lookups off it instead of `name`.

---

## Finding 4 — Type-band internal ID / root banding: PARTIAL

**Blueprint (§3, §4):** `Acc_ID` first digit encodes the root band (1=Asset … 6=Equity); new IDs are `MAX(Acc_ID)+1` within the band.

**Found:** `accounts.id` is a plain auto-increment with no banding. The functional substitute is `root_id` (FK to the root account), which most creation paths populate by copying the parent's `root_id`. However:
- `app/Console/Commands/ImportChartOfAccounts.php:117` hardcodes `'root_id' => null` for **every imported account**, so imported subtrees have no root linkage — `CoaController::openingBalances` (`CoaController.php:1197-1199`) groups them under `'Other'`, and any report keying off `root_id` misses them.
- `CoaSeeder.php:185` derives `root_id` from the in-memory parent map, which is correct for the seeded set, but `CoaController::addCategory` (`CoaController.php:190`) takes `root_id` **directly from the request** with only an `integer` validation — the client can attach an Expense leaf to the Assets root.

**Recommendation:** Make `root_id` derived, never user-supplied: on create, walk to the parent and copy `parent.root_id ?? parent.id`. Backfill imported rows (`UPDATE` walking `parent_id` up to the null-parent ancestor).

---

## Finding 5 — AccCode auto-generation: BUGGY (high)

**Blueprint (§4, §6.6):** The visible number is auto-generated as the next free number under the parent, left-padded to sibling width, unique, with the internal ID as a fallback.

**Found:** There is no central code generator; every creation site improvises:

| Site | Code strategy | Defect |
|---|---|---|
| `AgentController.php:459` | `'AGT-' . rand(1000000, 9999999)` | random, collision-possible, no relation to parent |
| `BranchController.php:139` | `'BRN-' . rand(1000000, 9999999)` | same |
| `ChatController.php:1147` | `'AGT-' . rand(...)` | same |
| `ChargeController.php:232` | hardcoded `'1213'` for **every** gateway bank-fee account | guaranteed duplicates across gateways/companies |
| `ChargeController.php:253` | hardcoded `'5111'` for every gateway expense account | duplicates; `5111` already belongs to "Visa Cost" in the seeder (`CoaSeeder.php:130`) |
| `SupplierCompanyController.php:154-160` | `(int)$parent->code + 1` | *parent's* code + 1, not max-sibling + 1 → every supplier under "Suppliers (Hotels)" gets the same code |
| `SupplierCompanyController.php:382` | `'SUP' . parentId . padded(children+1)` | different scheme again; race-prone `count()+1` |
| `InvoiceController.php:1536-1542` | `orderByDesc('code')` then `+5` | lexicographic `max` on a varchar column; arbitrary +5 step |
| `TaskController.php:1681-1689` | `orderBy('code','desc')` then `+1` | lexicographic ordering: `'999' > '1000'` |
| `AgentController.php:525-526, 605-606, 641-642` | `max('code') + 1` | `MAX()` on varchar is lexicographic (`'9' > '10'`); seeds from parent's own code when no sibling |

Additionally `CoaController::updateCode` (`CoaController.php:554-591`) lets a user set any code with **no uniqueness check at all** (and comically loads the same row twice as `$asset` and `$liability` and saves it twice).

**Why it matters:** Codes are the human-facing account numbers on every report and export. Duplicates make exports un-reconciliable and make the "code + 1" generators produce further duplicates.

**Recommendation:** One `AccountCodeGenerator` service: given a parent, compute `MAX(CAST(code AS UNSIGNED))` among siblings inside the parent's code range, +1, left-pad to sibling width, retry-on-unique-violation with the row id as fallback (exactly the blueprint's algorithm). Migrate all ten call sites to it. Add the unique index from Finding 12 first, then clean existing duplicates.

---

## Finding 6 — AccGroup 9-digit rollup key: MISSING

**Blueprint (§4):** A 9-digit hierarchical rollup key growing 2 digits per level; groups get the next free slot, leaves inherit the parent's group; drives statement grouping; `AccType` is derived from its first digit.

**Found:** No equivalent column or concept. The only trace is `app/Console/Commands/ImportChartOfAccounts.php:69`, which **reads** `acc_group` from the legacy spreadsheet (column A — this import was clearly written against the blueprint system's export) and then never stores it. Statement grouping is done by walking `parent_id` recursively at render time (`CoaController::buildAccountTree`, `TrialBalanceService`).

**Assessment:** For tree display, parent-walking works. What is lost is (a) a stable grouping key that survives re-parenting, (b) cheap `LIKE 'prefix%'` range queries for statement sections, (c) the derivation source for account type (see Finding 7).

**Recommendation:** Either adopt a materialized-path column (modern equivalent of AccGroup: e.g. `path = parent.path + padded(position)`, maintained on create/move) or accept parent-walking but then fix type derivation another way (Finding 7). If legacy-system interop matters (the import command suggests it does), store the original `acc_group` on import into a dedicated column instead of discarding it.

---

## Finding 7 — Derived account type (`AccType`): BUGGY (high)

**Blueprint (§2, §4, §6.4):** `AccType` (A/L/I/E) is **derived, never typed by a user**, from the first digit of `AccGroup`.

**Found:** `accounts.account_type` is a free-text varchar with wildly inconsistent contents and no derivation rule:
- `CoaSeeder.php` sets `account_type => null` for **all ~110 seeded accounts** (every array row has `'account_type' => null`).
- `TaskController.php:1699` writes `'liability'`; `ChargeController.php:233,254` write `'asset'`/`'expense'`; `InvoiceController.php:1551` writes `'income'` (lowercase singular).
- `ImportChartOfAccounts.php:150-164` maps to `'Assets'/'Liabilities'/'Expenses'/'Income'` (capitalized plural) — a different vocabulary.
- `AgentController.php:451` takes `account_type` **directly from the HTTP request** (`$request->account_type`) — user-typed, exactly what the blueprint forbids.
- Nothing consumes the column for logic; debit/credit orientation is instead decided by root **name** (`CoaController.php:111-117`) and `report_type` is a separate free string ('balance sheet'/'profit loss') set per call site.

There is also a second, parallel typing mechanism — `account_type_id` → `account_types` table with 30 ERPNext-style rows (`database/seeders/AccountTypeSeeder.php`) — which **no application code ever queries** (only import/export round-trips it). And a third — the `label` column with `App\Enums\CoaLabel` (11 semantic values) — used in exactly one query (`BankPaymentController.php:131`, `LIKE '%bonus%'`).

**Why it matters:** With no reliable type on the row, every report and every posting flow must re-derive semantics from names, which is why the name-lookup pathology of Finding 11 exists.

**Recommendation:** Pick one canonical enum (`A/L/I/E/Q`), derive it on create from the root (Finding 4's fixed `root_type`), backfill from `root_id`, and drop or repurpose the other two half-mechanisms. Make it non-fillable and set it in the model's `creating` hook.

---

## Finding 8 — Account-creation rules (parent mandatory, depth ≤ 6, level = parent+1, no mixed parents): MISSING (critical)

**Blueprint (§6):** Nine enforced rules drawn from `spAccountInsertUpdateSingleItem`.

**Found:** There is no `AccountService`; creation logic is duplicated across at least ten controllers/commands (`CoaController`, `AgentController`, `BranchController`, `ChargeController`, `ChatController`, `SupplierCompanyController`, `TaskController`, `InvoiceController`, `RefundController`, `UpdateOldTaskToTransaction`, `ImportChartOfAccounts`). Rule by rule:

1. *Parent mandatory* — enforced only in `addCategory` (`required|integer`); `importAccounts` (`CoaController.php:732-733`) silently inserts rows with `parent_id = null` when the parent name doesn't resolve, creating **phantom roots**.
2. *Max depth 6* — nowhere. `grep 'level.*6'` finds nothing; `addCategory` accepts any integer level from the client.
3. *No mixed parents* — nowhere (Finding 2).
4. *Derived AccType* — violated (Finding 7).
5. *Level = parent.level + 1* — violated in multiple sites: `addCategory` takes `level` **from the request**; `ChargeController.php:235,256` hardcode `level => 4` for children of "Payment Gateway"/"Payment Gateway Charges", which are **level-2** parents (children should be 3); `ChatController.php:1140` hardcodes `level => 4` under whatever `%Receivable%` matched; `BranchController.php:130` hardcodes 3. Only `AgentController.php:453` and `TaskController.php:1701` compute `parent->level + 1`.
6. *Auto-generated code* — violated (Finding 5).
7. *Group slot / leaf inherits group* — no group concept (Finding 6).
8. *Unique name and code* — violated (Finding 12).
9. *Freeze cascade* — missing (Finding 9).

**Recommendation:** Introduce a single `AccountService::create(array $attrs): Account` implementing all nine rules (this is the highest-leverage fix in this whole dimension — it turns eight findings into one code path), and refactor call sites onto it. Enforce depth/level/parent invariants in a model `creating` hook as a backstop so imports and tinker sessions can't bypass them.

---

## Finding 9 — Freeze / close semantics: PARTIAL (column exists, zero behavior)

**Blueprint (§2, §6.9):** `IsFreeze`/`AccStatus` blocks posting; freezing a parent cascades to transactional children and appends " (CLOSED)" to the name.

**Found:** `accounts.disabled` exists (`2025_04_03_112301`, default false) and is set to `0` at ~20 creation sites and imported/exported (`CoaController.php:790-791,819`). **No code path ever checks it**: not journal-entry creation, not the posting flows in `InvoiceController`/`TaskController`/`PaymentApplicationService`, not the account pickers. (The single `where('disabled', false)` hit in `app/Services/HotelSearchService.php:778` is against a different table.) There is no cascade and no rename-on-close.

**Recommendation:** Enforce `disabled` in the same `JournalEntry::creating` guard as Finding 2 (`if ($account->disabled) throw`), filter disabled accounts out of all dropdown/picker queries, and implement cascade + " (CLOSED)" suffix in the future `AccountService::freeze()`.

---

## Finding 10 — Live running balance (`OutstandingAmt`): BUGGY (high) + SQL injection (critical, see Finding 10a)

**Blueprint (§2, §8):** `OutstandingAmt` is a live running balance (Σ Debit − Credit) maintained on **every** post.

**Found:** `accounts.actual_balance` exists (`decimal(10,2)` — note: smaller precision than `journal_entries`' `decimal(15,2)` and the newer `opening_balance` `decimal(15,3)`), but:
- The main posting flows (`InvoiceController::createJournalEntries` ~1483-1590, `TaskController::processTaskFinancial`, `PaymentApplicationService`, `RefundController`) create `JournalEntry` rows **without ever touching** `actual_balance`.
- Only scattered flows update it: `CreditController.php:212,242`, `ClientController.php:1146,1162+`, `AccountingController.php:930-948`, `CreateClientCredit.php:234,255`, `CheckMyFatoorahPayments.php:170`, and various one-off `Fix*` console commands (`FixPaymentGatewayCOA`, `FixPaymentLinkCOA` — the latter even *recomputes* `actual_balance` from entries at line 171-174, confirming it drifts).
- The UI does not trust it: `CoaController::buildAccountTree` recomputes every balance from `journal_entries` aggregates + `opening_balance` on each page load, and `JournalEntry.balance` snapshots taken at post time read `$account->balance` (a non-persisted attribute) or stale `actual_balance` (`InvoiceController.php:1508`, `PaymentApplicationService.php:722,757`) — so the per-entry "running balance" column is garbage too.
- `AccountingController::storeBankPayment` is additionally self-inconsistent: both balance updates target `$request->bank_account` — subtract at line 931-932, then **add back** at 947-948 — so the bank nets to zero and the destination account is never updated.

**Assessment vs blueprint:** either maintain the running balance atomically on every post (trigger or transactional increment) or don't have the column. The current half-state is worse than absence: several flows *display* `actual_balance`-derived numbers that no longer reflect the ledger.

**Recommendation:** Short term: stop writing `actual_balance` everywhere, treat it as derived-only, and compute balances from `journal_entries` (already the UI's behavior). Long term (blueprint-faithful): maintain it in a single posting service inside the same DB transaction as the `JournalEntry` insert, with a nightly reconciliation job comparing it to Σ(debit−credit).

---

## Finding 10a — SQL injection in balance update: BUGGY (critical)

`app/Http/Controllers/AccountingController.php:931-932,947-948`:

```php
Account::where('id', $request->bank_account)
    ->update(['actual_balance' => \DB::raw("actual_balance - {$request->amount}")]);
```

`$request->amount` is **not** in the `validate()` list at lines 882-897 (only `debit`/`credit` are validated; `amount` is used raw), and it is string-interpolated into `DB::raw`. Any authenticated company user hitting `storeBankPayment` can inject SQL. Same pattern at line 947-948.

**Recommendation:** `->update(['actual_balance' => DB::raw('actual_balance - ?')])` is not supported — use `->decrement('actual_balance', (float)$validated['amount'])` after adding `'amount' => 'required|numeric'` to validation. Audit the other `DB::raw` interpolations in fix commands.

---

## Finding 11 — Party-master → account pointers vs magic-name resolution: BUGGY (critical)

**Blueprint (§7):** Every customer, supplier and airline is a leaf account; the party master holds **pointer FKs** (`CustAccID_FK`, `SuppAccID_FK`, `AirlineAccID_FK`); feeder modules look up these pointers so "the ledger never needs to know what a customer is."

**Found — pointers (the good parts):**
- **Agents** are closest to the blueprint: `agents.profit_account_id` / `agents.loss_account_id` (migration `2026_02_11_162453`, relations at `app/Models/Agent.php:78-85`) are real pointer FKs, populated on agent creation (`AgentController.php:550,666`), plus a receivable account per agent (`accounts.agent_id`, `Agent::account()` hasOne at `Agent.php:68-70`).
- **Charges/gateways** also use pointers: `charges.acc_fee_id`, `acc_bank_id`, `acc_fee_bank_id` (`ChargeController.php:278-280`).

**Found — the gaps:**
- **Clients have no per-client accounts.** `clients.account_id` was deliberately removed (`2025_03_28_105231_remove_account_id_from_clients_table.php`); the `Client::account()` relation left at `app/Models/Client.php:80-82` is dead code pointing at a dropped column. Invoice postings debit one **shared** "Clients" account for every customer of the company (`InvoiceController.php:1485-1514`). The client sub-ledger therefore only exists implicitly via `journal_entries.invoice_id/task_id` joins — receivable-per-customer cannot be read off the COA, which is the blueprint's core reason for putting parties in the tree. (The reverse column `accounts.client_id` exists and is import-fillable, but no posting flow uses it.)
- **Suppliers get leaf accounts but ambiguous pointers.** On activation, a supplier receives a payable leaf under `Suppliers (X)` *and* a cost leaf under `X Cost` for each checked category (`SupplierCompanyController.php:127-167`), both carrying `supplier_id` — so `Supplier::payableAccount()` (`hasOne` on `accounts.supplier_id`, `app/Models/Supplier.php:46-48`) returns an arbitrary one of 2+ rows. Consequently posting code ignores the relation and resolves supplier accounts **by name**: `Account::where('name', $supplier->name)` + parent checks (`UpdateOldTaskToTransaction.php:224-232`, `FixInvoiceCoa.php:393`, `BankPaymentController.php:627-630` which even falls back to `LIKE "%$supplierName%"`).
- **Airlines have no COA accounts** — only `airlines.accounting_code` (a string, `app/Models/Airline.php:17`) and the retroactive `delegatePriceAmadeus` tool (`CoaController.php:944+`) that splits the GDS account into per-issuing-carrier children after the fact. There is no `AirlineAccID_FK` equivalent, no BSP-style separation.

**Found — name-string resolution everywhere:** `Account::where('name', ...)` appears 60+ times across posting flows (see grep in audit notes), including `LIKE` variants: `'%Receivable%'` (`BranchController.php:118`, `ChatController.php:658,1134`), `'%Liabilities%'` (`CheckMyFatoorahPayments.php:115`), `'%Income On Sales%'` (`ChatController.php:667` — an account name that the seeder never creates, so this lookup returns null). The seeder makes several of these ambiguous by design: **"Clients" exists twice** (under Accounts Receivable, `CoaSeeder.php:35`, and under Refund Payable, `CoaSeeder.php:101`) and **"Payment Gateway" exists twice** (level-2 asset `CoaSeeder.php:32` and level-4 liability leaf `CoaSeeder.php:106`) — so any unqualified `->first()` (e.g. `CreditController.php:187`, `CheckMyFatoorahPayments.php:130`) picks whichever row has the lower id. Most flows wrap these lookups in `if ($account) {...}` or try/catch that **skips the journal entry silently** on a miss (`InvoiceController.php:1494` — no client entry is written if the name lookup fails, producing an unbalanced transaction).

**Cross-reference to unmerged branches:** the `agent-settlement` branch (unmerged) builds its loss-recovery engine on top of the agent loss/profit pointer accounts that main already seeds ("Agent Loss Receivable", "Loss Recovery Income" `CoaSeeder.php:122`); the pointer pattern is thus expanding, but only for agents. Nothing in `fix/payment-voucher` or `fix/rv` addresses party-account pointers.

**Recommendation:**
1. Reintroduce `clients.account_id` (or an `account_links` table) and auto-create a per-client receivable leaf under `Accounts Receivable → {Company} → {Agent}` on client creation, exactly as agents already do — the AgentController code is a working template.
2. Split supplier pointers into `payable_account_id` and `cost_account_id` columns on `supplier_companies` (per-company, matching how the accounts are created).
3. Give airlines a payable pointer or formally document the Amadeus-delegate model.
4. Replace name-based root/system-account lookups with the `label`/`CoaLabel` mechanism that already exists (Finding 7) — one indexed `where('label', CoaLabel::RECEIVABLE)` per company, seeded deterministically.

---

## Finding 12 — Unique `AccName` / `AccCode`: MISSING (high)

**Blueprint (§6.8):** Unique account name and code across the table.

**Found:**
- **No DB unique index** on `accounts.name` or `accounts.code` in any migration (the only `unique()` in account-related migrations is on the `agents` table, `2026_02_11_162453:217`).
- App-level checks are spotty: `addCategory` checks code existence (`CoaController.php:173` — note this check is company-scoped only by accident, via the `BelongsToCompany` global scope); `updateCode` checks nothing; `delegatePriceAmadeus` checks both (`CoaController.php:1032-1038`); the ten other creation sites check neither.
- The **seeder itself violates uniqueness**: code `2130` is assigned twice (`Suppliers (Hotels)` `CoaSeeder.php:71` and `Suppliers (Ferry)` `CoaSeeder.php:81`) and code `4130` twice (`Commission & Service Fee Income` line 118 and its own child `Gateway Fee Recovery` line 119). Duplicate *names* ("Clients", "Payment Gateway", "Cash" appearing at multiple tree positions) are what break the name-resolution of Finding 11; they also corrupt the seeder's own `parentMap` (keyed by bare name, `CoaSeeder.php:180-209`), so a later row whose parent name collides re-parents under the wrong node.
- `ImportChartOfAccounts.php:100-107` "de-duplicates" by fuzzy `LIKE '%name%'` matching and then **overwrites** the matched account via `updateOrCreate` keyed on name+company — importing "Cash" would clobber whichever of the several Cash-like accounts matches first.

**Recommendation:** Decide scope (per company: `UNIQUE(company_id, code)` and `UNIQUE(company_id, parent_id, name)` is the practical choice given multi-tenancy), clean the seeder's duplicate codes and rename the duplicate-name nodes (e.g. "Refund Payable — Clients"), backfill collisions, then add the DB indexes.

---

## Finding 13 — Behaviour types (`AccTransType`: cash/bank/gateway/commission/group): PARTIAL

**Blueprint (§5):** A behaviour flag telling the rest of the system how an account behaves (normal / cash / bank-card-gateway / commission control / pure group).

**Found:** Three half-built equivalents, none load-bearing:
1. `label` + `App\Enums\CoaLabel` (`2025_10_22_124718_add_label_to_account_table.php`, `app/Enums/CoaLabel.php`) — 11 values including `cash`, `bank`, `receivable`, `payable`, `bonus`; settable in `addCategory` (`CoaController.php:159,183`); **queried exactly once** in the entire app (`BankPaymentController.php:131`, `where('label','like','%bonus%')`). Never set by the seeder or by any auto-creation path.
2. `account_type_id` → `account_types` (30 rows incl. Bank, Cash, Receivable, Payable — `AccountTypeSeeder.php`) — never queried.
3. `account_dimension` enum `service|payment|both` (`2025_08_10_151225`) — this one **is** behavioral: `payment`-dimension accounts are excluded from parent rollups to avoid double counting (`CoaController.php:221-235,384-391`). It is a bespoke invention with no blueprint analogue, and nothing in the codebase ever sets it to anything but the default (no writer found outside import).

Cash/bank/gateway behavior is instead inferred from tree position by name ("Bank Accounts" parent, "Payment Gateway" parent — `ChargeController.php:111-124,179-183`).

**Recommendation:** Consolidate on `label` (the enum already matches AccTransType's semantics), have the seeder and every auto-creation path stamp it, and migrate the cash/bank/gateway/receivable/payable name-lookups to label-lookups.

---

## Finding 14 — Foreign-language account name (`AccName_FL`): MISSING

**Blueprint (§2):** Name + foreign-language (e.g. Arabic) name.

**Found:** No `name_ar`/`AccName_FL` on `accounts` (the pattern exists elsewhere in the app: `airlines.name_ar`, `cities.name_ar`, `payment_methods.arabic_name` — so the convention is established but was not applied to the COA). For a Kuwait-market product with an Arabic locale branch (`feat/locale*` branches exist upstream), this is a visible gap in financial statements.

**Recommendation:** Add `name_ar` (nullable) to `accounts`, surface it in the COA screen and PDF exports.

---

## Finding 15 — Per-account posting date windows (`TransLockdt`/`TransOpenFromdt`/`TransOpenTodt`): MISSING

**Blueprint (§2):** Per-account date windows controlling when posting is allowed.

**Found:** Nothing at the account level; nothing at the fiscal-period level either (no `fiscal`/`period_close`/`lock_date` hits outside an unrelated hotel controller). The only locking is per-journal-entry (`journal_entries.is_locked` + `app/Http/Traits/Lockable.php`), which prevents *editing* an existing entry but cannot prevent *back-dated posting* into a closed month — `transaction_date` is caller-supplied everywhere.

**Recommendation:** Minimum viable: a `companies.books_closed_until` date checked in the central posting guard (`transaction_date <= closed_until` → reject). Full blueprint: the three per-account columns enforced in the same guard.

---

## Finding 16 — Alternate codes for external mapping (`AltAccCodeExp`/`AltAccCodeImp`): MISSING

**Found:** `accounts.serial_number` (from `2025_03_26_084232`) carries the legacy serial on import/export but nothing maps through it; `ImportChartOfAccounts` discards the legacy `acc_group`/original code linkage (Finding 6). No import/export account-mapping layer exists. Low impact unless/until two-way sync with the legacy system is needed — which the existence of `accounts:import` suggests it is.

**Recommendation:** When importing from the legacy system, persist the legacy `Acc_ID`/`AccCode` in dedicated columns so re-imports are idempotent instead of fuzzy-name-matched.

---

## Finding 17 — Audit trail (CreateID/ModID + `*Log` mirror): MISSING (medium)

**Blueprint (§2, §8):** Who/when audit columns, mirrored to an account log table by trigger.

**Found:** `accounts` has only `created_at`/`updated_at`. No `created_by`/`updated_by`, no accounts log table, no activity-log package in `composer.json` (`spatie/laravel-activitylog`, `owen-it/laravel-auditing` absent). `SystemLog` model exists but is not wired to account changes. Combined with the unguarded `updateCode`/`dstry` endpoints, COA mutations are untraceable.

**Recommendation:** Add `created_by`/`updated_by` (fill in model events) and install an activity-log package scoped to the `Account` model as the mirror-table equivalent.

---

## Finding 18 — Account deletion integrity: BUGGY (high)

**Blueprint (implied by §1/§6):** tree validity; postings land on real leaves.

**Found:** `CoaController::dstry` (`CoaController.php:536-552`, route `DELETE /coa/api/{id}`, `routes/web.php:297`):
- **No authorization check** (unlike `index`, which gates on `COAPolicy::viewAny`) — any authenticated user in the company can delete accounts.
- **No child check** — deleting a parent raises an unhandled FK `QueryException` (500) because `accounts.parent_id` is constrained…
- …but **journal entries are not FK-protected**: `journal_entries.account_id` is a bare `foreignId` with no `constrained()` (`2025_03_17_103934_create_general_ledgers_table.php:19`), so deleting a leaf that has postings silently **orphans its journal entries**. Since `accounts` has no SoftDeletes, the money disappears from every COA rollup while remaining in `journal_entries` — trial balance and COA page will disagree.
- Related schema misuse: `accounts.reference_id` is FK-constrained to `accounts` (`create_accounts_table.php:23`) yet is populated with a **user id** (`ChatController.php:1146`) and a **branch id** (`BranchController.php:138`) — semantically wrong references that only survive because low ids happen to exist in `accounts`.

**Recommendation:** Gate `dstry` behind `COAPolicy::delete`; refuse deletion when `children()->exists()` or `journalEntries()->exists()` (offer "disable" instead — Finding 9); add the missing FK on `journal_entries.account_id` (RESTRICT); repurpose or drop `reference_id`.

---

## Finding 19 — Seeder correctness: BUGGY (medium)

Beyond the duplicates covered in Finding 12, `database/seeders/CoaSeeder.php` has:
- `updateOrCreate` match-array with a duplicated `parent_id` key (`CoaSeeder.php:188-192` — harmless but sloppy) and `root_id` inside the *match* clause, so re-running the seeder after any root_id drift creates duplicate rows rather than updating.
- All seeded accounts get `account_type = null` (Finding 7) and no `label`, `is_group`, or `balance_must_be` — i.e., the seeder does not populate the very columns later code needs.
- `$parentMap` keyed by bare name (`CoaSeeder.php:209`): the second "Payment Gateway" (line 106) and second "Clients" (line 101) overwrite the first entries; any future seeder row added under those names will attach to the wrong parent silently.

**Recommendation:** Key `parentMap` by (name, parent) path; set `is_group` explicitly per row; fix the two duplicate codes; stamp `label` values (this unlocks Finding 11/13 fixes).

---

## Finding 20 — `balance_must_be` (debit/credit orientation) declared but dead: PARTIAL (low)

`accounts.balance_must_be` enum('debit','credit') exists (`2025_04_03_112301`), is imported/exported (`CoaController.php:793-794,820,924`; `AccountsImport.php:35`) — and is never read by any balance computation or validation. Orientation is instead hardcoded per root name (`CoaController.php:111-117` `normal`/`reverse`). Not a blueprint field per se (blueprint derives orientation from AccType), but it is the codebase's own attempt at the same concept, left unwired.

**Recommendation:** Either enforce it (warn when a computed leaf balance has the wrong sign — useful for detecting mispostings) or drop the column.

---

## Finding 21 — Multi-currency at account level: PARTIAL (low)

**Blueprint (§2):** `AllowMultiCurr` flag + default currency FK.

**Found:** `accounts.currency` (a string code, default handling 'KWD') acts as the default currency; journal entries carry `original_currency`/`original_amount` (`2025_08_02_170959`); the COA tree shows original-currency subtotals for non-KWD accounts (`CoaController.php:91-109,438-451`); supplier currency-specific sub-accounts are auto-created per currency (`TaskController::getOrCreateCurrencySpecificAccount`, `TaskController.php:1665-1728`) — a reasonable, arguably better-than-flag design. There is no `AllowMultiCurr` gate (any account silently accepts entries in any currency; entries whose `original_currency` differs from the account's `currency` are simply not shown in the original-currency subtotal, `CoaController.php:292-300` filters `where('original_currency', $account->currency)`), and currency is a free string with no FK to the `currencies` table.

**Recommendation:** Validate posting currency against the account's currency (or an explicit multi-currency allowance) in the central posting guard; FK the column.

---

## Summary table

| # | Capability (blueprint §) | Status | Severity |
|---|---|---|---|
| 1 | Self-referencing hierarchy, per-company (§1) | present_ok | low |
| 2 | Leaf-only posting / HasSubAcc discipline (§1,§6.3) | buggy | critical |
| 3 | Six fixed roots (§3) | partial | low |
| 4 | Root/type banding → `root_id` (§3,§4) | partial | medium |
| 5 | AccCode auto-generation (§4,§6.6) | buggy | high |
| 6 | AccGroup rollup key (§4) | missing | medium |
| 7 | Derived AccType (§2,§4,§6.4) | buggy | high |
| 8 | Creation-rule enforcement / central service (§6) | missing | critical |
| 9 | Freeze blocks posting + cascade (§2,§6.9) | partial | medium |
| 10 | Live running balance (§2,§8) | buggy | high |
| 10a | SQL injection in balance update | buggy | critical |
| 11 | Party accounts + pointer FKs (§7) | partial | critical |
| 12 | Unique name/code (§6.8) | missing | high |
| 13 | Behaviour types AccTransType (§5) | partial | medium |
| 14 | Foreign-language name (§2) | missing | low |
| 15 | Posting date windows (§2) | missing | medium |
| 16 | Alternate import/export codes (§2) | missing | low |
| 17 | Audit columns + log mirror (§2) | missing | medium |
| 18 | Deletion integrity + JE FK | buggy | high |
| 19 | Seeder correctness | buggy | medium |
| 20 | balance_must_be dead column | partial | low |
| 21 | Multi-currency flag (§2) | partial | low |

**Overall completeness: ~38%.** The skeleton (tree, seeding, display, opening balances, supplier/agent auto-accounts, multi-currency display) is real; the rule layer (validation, derivation, uniqueness, freeze, live balances, pointer-based resolution) — which the blueprint calls "get the rules exactly right, because the posting engine and every report depend on it" — is largely unimplemented or disabled.

### Highest-leverage fix order
1. **Central `AccountService`** (Findings 5, 7, 8) — one code path for create/freeze/delete implementing the blueprint's nine rules.
2. **Re-enable the leaf-only posting guard + `disabled` check** (Findings 2, 9) in `JournalEntry::creating`.
3. **Label-based system-account resolution + per-client receivable accounts** (Findings 11, 13) — kills the magic-name fragility and restores per-party sub-ledgers.
4. **Unique indexes + seeder cleanup** (Findings 12, 19).
5. **Patch the SQL injection** (Finding 10a) immediately — it is a one-line fix.

### Cross-references to unmerged branches
- `agent-settlement` (unmerged): consumes the agent profit/loss pointer accounts main already seeds; confirms the pointer-FK direction of travel but only for agents — client/airline pointers remain unaddressed anywhere.
- `fix/payment-voucher`, `fix/rv` (unmerged, stale): touch payment/receipt-voucher modeling (ref 03 territory); neither adds COA-level rules, so no COA gap in this report is "already solved" by them.
