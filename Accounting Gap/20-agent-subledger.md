# 20 — Agent sub-ledger: agents as an accounting party

**Status:** implementable spec, **revision 3 — orchestrator rulings applied 2026-08-27**. **Owner decisions D1–D9 are binding inputs, restated here as rules with the accounting reason each.**
**Scope:** the agent (consultant / salesperson) as a party in the ledger — what they earn, what they owe, who bears which charge, how balances are settled, and what the statement reports.
**Out of scope (referenced, not re-planned):** the posting engine itself (11 §P1–P2, 17), client/supplier de-pooling (13 Stages B–F), the reporting rebuild (11 §P5.4), BSP statement reconciliation mechanics (P7.5).

Conventions used throughout:
- Every fact about this codebase is cited **by symbol and file**, never by line number.
- `[inference]` marks anything derived rather than read. `[unverified]` marks something this document asserts but did not read.
- Blueprint material is **quoted**, with its reference and section.
- Every posting pattern below is balanced and carries `doc_type` / `sub_type` / `cost_center_id` / `reason_tag` / attribution.
- Amounts are KWD at 3 decimals (`config('accounting.engine.base_currency')` = `'KWD'`, `base_decimals` = 3).
- **Revision 2 changed the numbers and the schema in several places.** Where a value differs from revision 1 it is flagged `[R2]` with the reason. §11 (Audit disposition) is the change log for that pass.
- **Revision 3 applies the orchestrator's binding rulings on top of revision 2.** Where a value changes again it is flagged `[R3]` with the reason. §12 (Revision 3 change log) is the change log for this pass.

---

## 1. Scope & principles

### 1.1 The one sentence

> An agent is a **party**, exactly like a client or a supplier: they have a balance we owe them, a balance they owe us, open items that age, documents that move those balances, and a statement. They are **not** a slice of the company's profit.

Everything below follows from that.

### 1.2 The rules (D1–D8 restated, each with its accounting reason)

---

**RULE 1 (D1) — The agent's liability is their *contractual earnings*, not the case profit.**

The per-agent liability leaf carries **Agent Commission Payable (X)** = what the agent has contractually earned under their agent type:

| `agent_type.name` | `agents.type_id` | Earnings formula |
|---|---|---|
| Salary | 1 | none (salary only, accrued separately — Rule 1c) |
| Commission | 2 | `Σ over tasks of round(rate × max(taskCommissionBase, 0), 3)` — **clamped per task, floored at zero** (Rule 1e) |
| Both-A | 3 | same Σ as type 2 **+ salary** — salary accrued separately, so the *commission* leg is the Σ only |
| Both-B | 4 | target-gated pool: **0** if `Σ base ≤ target`, else `max(Σ base − salary, 0) × rate` for the commission leg (+ salary accrued separately) |

*Accounting reason.* A liability is an amount **owed to a specific person under an obligation**. The company does not owe the agent the case profit; it owes the agent the commission the contract promises. Crediting the whole profit to the agent's leaf (today's behaviour) states a liability that will never be paid, and simultaneously wipes the company's own margin out of the P&L, because the offsetting debit is an expense (`Agent Salaries`, 5160). Both sides of the income statement are wrong and the balance sheet overstates liabilities.

*Where today's code does this.* `InvoiceController::createProfitEntries()` posts, per invoice detail:

```
Dr  'Agent Salaries' (5160)                    $profit
Cr  agents.profit_account_id  (2230 subtree)   $profit
```

…and then, **in the same method and in addition**, posts a *second* pair for `$commission`:

```
Dr  'Commissions Expense (Agents)' (5130)      $commission
Cr  'Commissions (Agents)' (2210, POOLED)      $commission
```

So an agent on a 10.000 KWD case at 20% is credited 10.000 to their own leaf **and** 2.000 to the pooled commission leaf — 12.000 of liability for a 2.000 obligation, and 12.000 of expense against 10.000 of gross margin. This is a **legacy error corrected in W3**, not a design to preserve. (§8 covers the historical repair; §10.1 the numbers.)

**RULE 1b — Per-case profit is a REPORTING dimension, not a liability.**
The number the agent watches ("what did I make on this file") is carried as `journal_entries.cost_center_id = CC(A)` `[R3 — notation: CC(A) denotes agent A's row in the new cost_centers master (§3.2, §9 W3.F), never agents.id itself — O9]` on **every line of every document the agent originated** — the sale, the cost, the fee, the commission. The agent's profit report is then a P&L filtered to one cost centre, which is what a cost centre is for. Nothing about it belongs on the balance sheet.

**RULE 1c — Salary stays pooled (D2).**
Salary is accrued to `Salaries & Wages Payable` (**2201**, `CoaSeeder`) via the existing `SALARY_PAYABLE` purpose code, on a document whose `doc_type` is **`HRJV`** and which is gated by a payroll permission. It is *never* mixed into the commission leaf. See §5.1 for why `HRJV` is a `doc_type` and not a `sub_type`, and §5.12 for the rule that **no `HRJV` document may ever touch an agent's own two leaves**.

**RULE 1d — Type-4 is computed in ONE service.**
`App\Services\Accounting\AgentEarningsService::earningsFor(Agent $agent, CarbonPeriod $period): AgentEarnings` is the sole implementation of the four formulas, and **both** the posting feeder and the agent-profit report call it. Today the formula exists twice — `AgentController::…` (`switch ($agent->type_id)`, cases 1–4) and `ReportController::…` (`switch ((int) $agent->type_id)`, cases 1–4) — and the two already disagree on the type-4 base (`max($profitTotal - $salary, 0.0)` in the report vs. an unclamped `$totalProfit - $agent->salary` in the controller). Two formulas means the ledger and the report can never be reconciled.

**RULE 1e `[R2 — new, and the most consequential change in this revision]` — The commission base is GROSS MARGIN, defined once, and the clamp is per task.**

Revision 1 left this ambiguous (§7.3 called `invoice_details.profit` *"GROSS case profit — `markup_price` less agent-borne deductions"*, which is self-contradictory and factually wrong). It is now fixed by fiat:

> **`taskCommissionBase` = gross margin = `sell price − supplier cost`, before ANY agent-borne charge.**
> Every agent-borne charge (gateway-fee share, negative margin, refund clawback, ADM share) is a **separate D3-routed debit document**, never a reduction of the commission base.

*Why by fiat.* The alternative — a base net of agent charges — is circular: the charge reduces the base, which reduces the commission, which reduces the payable that the charge is then debited against. There is no fixed point worth defending, and two feeders will pick different orders. One definition, stated once, is the only workable answer.

*Why gross.* A commission is compensation for producing margin. Cost-sharing arrangements are a separate contractual matter with their own bearer policy (Rule 4) and their own document the agent can be shown (Rule 3b). Folding them into the base makes the agent's earned commission depend on which payment method the client happened to choose — unexplainable on a statement.

*The clamp is per task, floored at zero.* Today `InvoiceController::addJournalEntry()` reads `if (in_array($agent->type_id,[2,3,4]) && $profit > 0) { $commission = round($profit * $rate, 3); }` — a **per-task** test. Rule 1's Σ preserves that: each task contributes `round(rate × max(base, 0), 3)`, so a loss-making task contributes zero and **never reduces another task's commission**. A period-level Σ over signed margins would produce a different — sometimes negative — number, and a negative commission is a debit to a payable with no obligation behind it. Type 4's pool is the one place a period-level Σ is correct, because its formula is contractually a pool.
Test: `AgentEarningsTest::a_loss_making_task_never_reduces_another_task_s_commission`.

> **`invoice_details.profit` does NOT hold this base `[R2]`.** `InvoiceController::addJournalEntry()` computes
> `$profit = $clientPaid ? round(($margin + $accountingFeePerTask) - $agentFeeDeduction, 3) : round($margin - $agentFeeDeduction, 3)`
> — i.e. **net of the agent's gateway-fee share** (the migration comment says `profit = markup_price - agent_charge_deduction`; 19 §3(a) says *"sell price minus supplier cost, net of the agent's share of the gateway fee"*). §7.3 item 1 redefines the mirror accordingly.

> **This is an economic change and the owner must sign it off.** On §5.2/§5.3's worked example (gross margin 10.000, real agent fee share 1.500, rate 20%) today's code pays the agent `20% × 8.500 = 1.700` and raises no charge. Under Rule 1e the agent earns `20% × 10.000 = 2.000` and is separately charged 1.500, netting **0.500** — a **−1.200 swing per case**. The mechanics are identical whichever way the owner decides; only the base moves. If the owner prefers today's economics, the correct expression of that is `gateway_fee.bearer = company` (no charge at all; commission on gross margin = 2.000) or a lower commission rate — **not** a net base. Recorded as owner question **Q-20.1** (§10.19).

---

**RULE 2 (D2) — Two balance-sheet leaves per agent, same depth, never netted in the ledger.**

```
Liabilities (2000)
└── Accrued Expenses (2200)
    └── Agent Commission Payable (2230)          ← group (renamed from "Agent Profit Payable")
        └── {Agent}                              ← leaf, level 4   → agents.commission_account_id

Assets (1000)
└── Accounts Receivable (1350)
    └── Agent Receivables (135900)               ← NEW group, level 3   [R2: code, see §2.3]
        └── {Agent}                              ← leaf, level 4   → agents.receivable_account_id
```

*Accounting reason.* Offsetting an asset against a liability is prohibited unless a legal right of set-off exists and settlement is intended net. Two separate obligations — "we owe the agent commission" and "the agent owes us for a loan" — are two separate balances until a settlement document actually sets them off. Netting them in the ledger destroys both the true liability and the true asset, and makes the receivable **un-ageable**: you cannot age a number that a commission credit has already silently reduced.

**Netting happens on the statement footer only** (§7), never in an account.

*Same depth matters.* Today the two leaves live at different depths — payable at level 4 (`2230 › {Agent}`), receivable at level **5** (`Accounts Receivable › {Company Name} › {Agent} › Agent Loss Receivable`, built by `AgentController` and by migration `2026_02_11_162453_add_profit_loss_accounts_in_agents_table`). A side-by-side statement, a rollup, and a balance-sheet line all have to special-case one side. Levels are also capped: blueprint 01 §6 rule 2 — *"**Max depth = 6 levels** (`AccLevel > 6` → reject)"* — and the level-5 placement burns two of them on a `{Company Name}` node that is pure redundancy inside a `company_id`-scoped COA.

---

**RULE 3 (D3) — Charge nature decides which leaf a charge lands on.**

| Charge arises from… | Lands on | Overflow | `reason_tag` `[R2]` |
|---|---|---|---|
| **earnings** — gateway-fee share, negative margin, refund clawback, ADM pass-through | **Agent Commission Payable (X)** first (debit it down) | → **Agent Receivable (X)** | `fee` / `loss` / `loss` / `adm` |
| **not earnings** — loan, services bought on credit, personal advance | **Agent Receivable (X)** directly | n/a | `loan` / `service` |

*Accounting reason.* A charge that arises from the agent's own earnings is a **reduction of the obligation**, not a new asset — the blueprint pattern is to debit the salesperson's earnings account (07 §11: each salesperson's balance *"**is** their earned commission"*, so a clawback reduces it). Booking it as a receivable instead would recognise an asset the company will never separately collect *and* leave the liability overstated by the same amount. Conversely a loan is a genuine new asset with its own collection story and its own ageing clock; forcing it through the commission payable would make the agent's earned commission disappear into a loan repayment nobody agreed to.

**Overflow.** When the earnings-derived charge exceeds the current payable balance, the excess becomes a receivable — because at that point it *is* an amount the agent owes with no earnings to absorb it. Guard: **the payable leaf must never be debited below zero** by a charge document. The split is computed at posting time, both legs in the same document, **under the lock and the exact balance predicate specified in §6.5 `[R2]`** — without which two concurrent charges each read the same balance and both consume it, and the guard silently fails.

**RULE 3b — Collection is never automatic.**
"Deduct from commission / deduct from salary / cash / payment link / wallet" is a **decision taken per settlement**, recorded as its own document (§5.11–§5.13). No feeder may deduct a balance as a side effect of anything else. The statement always shows the balance **and its ageing**, and the settlement UI proposes but never applies.

*Accounting reason.* An automatic deduction is an unrecorded set-off: it changes two parties' balances with no document, no date, no approver, and no reversibility. It also crosses into payroll (deducting from salary), where an undocumented deduction is a legal exposure, not just a bookkeeping one.

---

**RULE 4 (D4) — ONE bearer policy model, four charge kinds, and the fee expense is always booked gross.**

Per **company**, per **charge kind** ∈ `{gateway_fee, negative_margin, refund_clawback, adm}`:
`bearer` ∈ `{client | company | agent | split}` + `agent_percentage` (default 50), with the **per-gateway / per-payment-method override for `gateway_fee` expressed by extending `charges.paid_by` / `payment_methods.paid_by`, not by a second override column** `[R2]` — per D4's own wording (*"payment_methods/charges `paid_by` today = client|company; **add the internal split**"*) and 22 §2.1c (*"extend the vocabulary, do not add a second column"*).

- `client` is valid **only** for `gateway_fee`, and it is a **pricing option (gross-up)**, not a posting variant. The client is charged more; the fee expense is booked identically.
- The gateway-fee **expense is ALWAYS booked in full** to the gateway charges leaf (`5141` TAP / `5142` MyFatoorah / `5143` Hesabe / `5144` KNET / `5145` uPayment, under `Payment Gateway Charges` 5140), resolved by `GATEWAY_FEE_EXPENSE_{GATEWAY}`.
- Recovery **from the client** → `4131 Gateway Fee Recovery`.
- Recovery **from the agent** → **`5146 Gateway Fee Recovery (Agents)`**, a **contra-expense** leaf under `5140` `[R2 — was 4133 under Direct Income; see §2.2]`.
- The agent's share is computed on the **real** fee (`ChargeService::calculate()`'s `accountingFee`, documented in that class as *"For COA/profit: exact service charge"*), **never** the client-facing figure (`gatewayFee` / `finalAmount`), which `ChargeService::calculateChargeForPayment()` produces by `ceil()`-ing the percentage charge.
- **The client-facing fee has THREE components, not two `[R2]`:**
  `clientFee = accountingFee + markup_profit + rounding_profit`
  where `markup_profit = (self_charge − service_charge)` applied to the base (`ChargeService::calculateMarkupProfit()`, returned separately by `calculate()`) and `rounding_profit` is the `ceil()` uplift (*"Always company profit"*). Revision 1's two-component identity (`real fee + rounding uplift`) is wrong for any gateway where `self_charge ≠ service_charge`, and invariant A8 as written would not have caught it.
- **The markup and the rounding uplift are income**, credited to `4131`, and are never left sitting inside the client receivable.

*Accounting reason.* Gross reporting: the cost of accepting card payments is a real operating cost and must be visible in full in the P&L. Netting a recovery straight against the expense line makes the true cost of the gateway unknowable and breaks the blueprint's own reconciliation design — 03 §9: *"The fee on a separate line keeps processing cost visible and lets the bank line reconcile to the *net* actually deposited."* Separating client recovery (4131) from agent recovery (5146) matters because they are economically different: one is revenue from a customer, the other is a cost-sharing arrangement with staff — and **only the first is revenue** (§2.2's classification note).

*Why the real fee, not the ceil.* The ceil uplift is the company's pricing margin. Charging the agent 2.000 when the bank took 1.500 bills the agent for the company's own markup.

---

**RULE 5 (D5) — ADM/ACM are DOCUMENTS, and the agent leg is a second document.**

A memo module (blueprint 07 §5: *"`tblMemoHeader` (`MemoType` `D`/`C`) + `tblMemoDetail` is the entry front for credit notes (`CRN`), debit notes (`DBN`), BSP ADM/ACM memos, and commission adjustments"*) carries: header + lines, its own `SequenceService` series, party, `ticket_number`, `pnr`, `airline_id`, `BSPTYPE` ∈ **`ET|VOID|REFUND|ADM|ACM|EMD`** `[R3 — `VOID` restored to the vocabulary per the orchestrator's ruling]`, and a reason. The `bsptype` column is added **nullable** in **W3** (§9) and stamped on every airline document from **W3/W4** onward; historical rows are backfilled later, out of scope here.

**Two-step, always:**
1. **Airline memo.** ADM: `Dr Airline Memo Control / Cr Airline Payable`. ACM: the inverse.
2. **Bearer leg,** *if* `bearer ∈ {agent, split}`: an **agent DBN** — `Dr agent (per Rule 3) / Cr Airline Memo Control`.

*Accounting reason.* The airline's claim on us and our claim on the agent are **two separate obligations against two separate parties**, arising at different moments and settled by different means. Collapsing them into one entry (`Dr agent / Cr airline`) records a liability to the airline as if the agent had already assumed it, so the airline payable is right by accident and the agent's balance moves before anyone decided it should. The `Airline Memo Control` clearing account exists so that step 2 can be *late, partial, or never* without corrupting step 1 — and its **balance must be zero once every memo is dispositioned**, which is a testable invariant.

BSP reconciliation (P7.5) consumes the memos **per ticket**; incentive accruals clear against the ACM (blueprint 04 §6: *"many agencies accrue it and clear it against the BSP ACM"*).

---

**RULE 6 (D6) — An agent may be a customer.**

`agents.client_id` (new, nullable) links an agent to a client record. When a sale's client **is** that linked client, the **receivable leg routes to `Agent Receivable (X)`** instead of the pooled `Clients` leaf. Everything else about the sale is a normal sale: revenue, cost, supplier payable, tax — unchanged.

*Accounting reason.* A debt is classified by **who owes it**, not by what was sold. If the agent's personal ticket sits in the client pool, the agent's statement is incomplete, the balance cannot be set off against their commission, and the client ageing report contains a debtor who is actually staff. One party = one receivable balance.

Note: there is no `agents.client_id` today. `Agent::clients()` is a `belongsToMany` over the `client_agents` pivot (clients *served by* the agent) and `Agent::clientQuery()` unions that with `clients.agent_id` — both are "the agent's book of business", the opposite relationship. The new column is a distinct, single, nullable pointer and must not reuse either.

---

**RULE 7 (D7) — Credit control per party, mode is a company option.**

Per party (**client and agent**): `credit_limit`, `credit_from_date`, **`is_blacklisted`** `[R2 — the column name 22 §3 P5.12 specifies, on both `clients` and `agents`]`. Enforcement mode is a company option `{warn | block}`, **default `block`** for sales on credit. The current balance is **recomputed from open items** (§6), not from a cached column.

Blueprint 03 §8: *"A nightly/triggered routine recomputes `CurrentBalance = Σ(Dr − Cr)` on the customer's account (excluding opening journals) and `AvailableCredit = CreditLimit − CurrentBalance`. A `IsBacklisted` flag is a hard stop — no new sales. Enforce the limit at invoice-save time: block (or warn) when the new invoice would exceed available credit."*

Both qualifiers in that quote are load-bearing and are carried into O9 `[R2]`: opening journals (`OJV` `Balance B/F` lines) are **excluded** from `CurrentBalance`, and no credit sale is permitted before `credit_from_date`.

*Accounting reason.* Not a bookkeeping rule — an exposure control. It belongs here because the agent sub-ledger is the first place an agent balance becomes measurable, and because Rule 6 makes an agent a debtor like any other.

---

**RULE 8 (D8) — Reporting spine, one direction only.**

```
ledger integrity (W3 / W4 / P3)
   └─► canonical LedgerReportQuery (11 §P5.4)
          └─► statements (agent statement, ageing, agent P&L)
```

The agent statement is **two ledgers side by side** — payable and receivable — each with a brought-forward row and ageing, **netted in the footer only**. It reads **the ledger**, keyed by `invoice_id` / document, as the single source of truth. `invoice_details.profit` and `invoice_details.commission` become **stored mirrors** — display and recompute aids, never the source of a reported balance.

*Accounting reason.* A statement that a party can act on must be reproducible from the ledger, line for line, or it is not a statement — it is an opinion. Today `ProfileController`'s agent statement is a hybrid: profit and commission come from `invoice_details` (`$invoice->invoiceDetails->sum('profit')`, `->sum('commission')`) while losses come from the ledger (`JournalEntry::where('account_id', $agent->loss_account_id)`). The two halves cannot be tied to each other, and any recompute of the mirrors silently rewrites reported history.

---

### 1.3 One deliberate deviation from the blueprint, stated up front

Blueprint 07 §11 models staff commission as **per-service *income* accounts on the staff record** (`TKTIncomeAccID_FK`, `TRVIncomeAccID_FK`, `XOIncomeAccID_FK`), flagged `AccTransType = 8` (*"commission control"*), *"named like `PETER WASELY-TICKET COMM`"*, so that *"each person's GL balance **is** their earned commission per service type"*.

**We put earned commission on the balance sheet as a payable instead**, and carry the per-service breakdown as **dimensions** (`cost_center_id → CC(agent)` `[R3 — the value is the agent's cost-centre row id, never the agent id itself — O9]`, `serviceType` on the line) rather than as separate accounts.

*Why.* An amount the company has earned but not yet paid to a person is, by definition, a **liability** — not income of the company. The blueprint's placement is a reporting device (it makes "commission by consultant by service" a one-account query in a system without dimensions). Our engine already carries dimensions per line: `LineDraft::$serviceType` exists today (*"task type dimension for per-service purpose codes (flight/hotel/visa/…)"*), and `cost_center_id` is scheduled onto `journal_entries` in W3 (21 §5a; 22 §2.1a row 06-12/05-6, which requires it on **both** `journal_entries` and `transactions`). So we get the blueprint's report without misclassifying a liability, and without one account per agent per service type (12 service types × N agents).

**What we keep from 07 §11:** the pointer-on-the-party-record pattern (§3), the per-service reporting requirement (§7), the rate-table and target layers, and the `AccTransType`-style behaviour marker (§2.5).

**Required amendments to the plan of record `[R2]`.** This deviation is not free: two agreed artefacts assume the blueprint's per-service *accounts*, and unless they are amended, two coverage rows stay PARTIAL forever and **P5.13's exit gate cannot be met**.

| Artefact | Today | Amend to |
|---|---|---|
| 22 §3 P5.13 acceptance list | `PerServiceCommissionTest::commission_lands_on_the_service_specific_leaf_of_the_person` | `PerServiceCommissionTest::commission_is_attributable_per_service_line_for_the_person` `[R3, O16 — aligned to 22's own test name; AM-20.1 is ACCEPTED]` |
| 22 §3 P5.13 deliverable *"Per-service commission accounts (03-30 / 07-28)"* | per-person, per-service GL leaves | per-person, per-service **reporting dimension** (`cost_center_id` + `serviceType`), deviation recorded in doc 20 §1.3 |
| 21 row **03-30** (*PARTIAL — "SCHEDULE (P5.13, §5)"*) | PARTIAL | **DONE (by dimension)** on P5.13 delivery, deviation recorded in doc 20 §1.3 |
| 21 row **07-28** (*PARTIAL — "see 03-30"*) | PARTIAL | same |

These are amendment *instructions*, not unilateral edits: docs 21 and 22 are outside this document's edit scope. Tracked as **AM-20.1**.

---

## 2. Chart changes

### 2.1 What exists today (verified)

From `CoaSeeder::run()`:

| Code | Name | Level | Parent |
|---|---|---|---|
| `1350` | Accounts Receivable | 2 | Assets |
| `1351` | Clients | 3 | Accounts Receivable |
| `1950` | Temporary Accounts | 2 | Assets |
| `1951` | Temporary Opening | 3 | Temporary Accounts |
| `2200` | Accrued Expenses | 2 | Liabilities |
| `2201` | Salaries & Wages Payable | 3 | Accrued Expenses |
| `2210` | Commissions (Agents) | 3 | Accrued Expenses |
| `2220` | Expenses (General) | 3 | Accrued Expenses |
| `2230` | **Agent Profit Payable** | 3 | Accrued Expenses |
| `4130` | Commission & Service Fee Income | 3 | Direct Income |
| `4131` | Gateway Fee Recovery | 4 | Commission & Service Fee Income |
| `4132` | Markup Income | 4 | Commission & Service Fee Income |
| `4170` | Loss Recovery Income | 3 | Direct Income |
| `4200` | Indirect Income | 2 | Income (group; **no seeded children**) |
| `5122` | Agent Bonus | 3 | Direct Expenses (Cost of Sales) |
| `5123` | Fee Loss Provision | 3 | Direct Expenses (Cost of Sales) |
| `5130` | Commissions Expense (Agents) | 3 | Direct Expenses (Cost of Sales) |
| `5140` | Payment Gateway Charges | 3 | Direct Expenses (Cost of Sales) |
| `5141`–`5145` | TAP / MyFatoorah / Hesabe / KNET / uPayment Charges | 4 | Payment Gateway Charges |
| `5160` | Agent Salaries | 3 | Direct Expenses (Cost of Sales) |
| `5218` | **Write Off** | 3 | Indirect Expenses (Operating Expenses) |
| `5221` | Company Loss on Sales | 3 | Indirect Expenses (Operating Expenses) |

Plus, created at runtime and **not** in `CoaSeeder`:
- per-agent leaves under `2230`, created **both** by `AgentController` and by migration `2026_02_11_162453` (`createProfitAccount()` does `getOrCreateAccount($accruedExpenses, 'Agent Profit Payable', '2230', …)` then `getOrCreateAccountId($profitGroup, $agent->name, $this->getNextCode($profitGroup), …)`), with `code = max(sibling code) + 1` starting at `2231` `[R2 — revision 1 credited that migration only for the loss tree]`;
- the level-5 loss tree `Assets › Accounts Receivable (1350) › {Company Name} › {Agent} › Agent Loss Receivable`, created by `AgentController` and by the same migration (`createLossAccount()`), each level using the same `max(sibling)+1` generator;
- **a fourth, undocumented per-agent leaf** `[R2]`: `AgentController::store()` also runs `Account::create([… 'code' => 'AGT-' . rand(1000000, 9999999), 'parent_id' => $branch->account->id, 'root_id' => $assetsAccount->id, 'agent_id' => $agent->id])` — a per-agent **asset** leaf under the *branch* tree with a **non-numeric random code**. §2.6 deals with it.

### 2.2 Target tree `[R2 — classification and codes changed]`

```
Assets (1000)
└── Accounts Receivable (1350)
    ├── Clients (1351)                                  [pooled — being drained by 13 Stage D]
    ├── Trade Receivables — Clients  (per 13 §B.3, name USER-DECIDE)
    ├── Agent Receivables (135900)        ← NEW group, level 3, six digits (§2.3)
    │   ├── {Agent A} (135901)            ← leaf, level 4 → agents.receivable_account_id
    │   └── {Agent B} (135902)
    └── Airline Incentive Receivable (135800)  ← NEW leaf, level 3 (Rule 5 / §5.14)
└── Temporary Accounts (1950)
    ├── Temporary Opening (1951)
    └── Airline Memo Control (1952)       ← NEW leaf, level 3, clearing; must net to zero

Liabilities (2000)
└── Accrued Expenses (2200)
    ├── Salaries & Wages Payable (2201)   [existing — pooled salary, HRJV only]
    ├── Payroll Deduction Clearing (2202) ← NEW leaf, level 3 (§5.12)
    ├── Commissions (Agents) (2210)       [existing pooled leaf — FROZEN after P3, §8]
    └── Agent Commission Payable (2230)   ← RENAMED from "Agent Profit Payable"
        ├── {Agent A} (223001)            ← leaf, level 4 → agents.commission_account_id
        └── {Agent B} (223002)

Income (4000)
└── Direct Income (4100)
    ├── Commission & Service Fee Income (4130)
    │   ├── Gateway Fee Recovery (4131)   [CLIENT recovery + markup + rounding uplift]
    │   ├── Markup Income (4132)
    │   ├── Cancellation Fee Income (4134)  ← NEW leaf, level 4 [R3, S6 — fee-invoice income; 4133 stays permanently retired, never reused, to avoid confusion with AM-20.3's migration]
    │   └── Change Fee Income (4135)        ← NEW leaf, level 4 [R3, S6]
    ├── Airline Memos & Incentives (ACM) (4160)  ← NEW leaf, level 3  [genuine third-party income]
    └── Loss Recovery Income (4170)       [existing — FROZEN after P3; see the note below]
└── Indirect Income (4200)
    └── Unclaimed Balances Written Back (4210)   ← NEW leaf, level 3 (§5.17)

Expenses (5000)
└── Direct Expenses (Cost of Sales) (5100)
    ├── Commissions Expense (Agents) (5130)      [the ONLY debit leg for earned commission]
    ├── Airline Debit Memos (ADM) (5124)         ← NEW leaf, level 3
    ├── Airline Refund Clawback (5125)           ← NEW leaf, level 3  [R3 — new expense leaf only, no reclassification; see §5.5(a), O4]
    ├── Loss Recovery (Agents) (5126)            ← NEW leaf, level 3  CONTRA-EXPENSE (credit balance)
    ├── Payment Gateway Charges (5140)
    │   ├── TAP/MyFatoorah/Hesabe/KNET/uPayment (5141–5145)
    │   ├── Gateway Fee Recovery (Agents) (5146) ← NEW leaf, level 4  CONTRA-EXPENSE (credit balance)
    │   └── Gateway Reconciliation Difference (5147) ← NEW leaf, level 4 (§5.3(a))
    └── Agent Salaries (5160)                    [salary expense; NOT profit-share]
└── Indirect Expenses (Operating Expenses) (5200)
    ├── Sales Incentive Expense (5211)           ← NEW leaf, level 3 [R3, S8 — spot commission, incentive mode, §5.19]
    ├── Write Off (5218)                         [EXISTING — the BAD_DEBT_EXPENSE target, §5.17]
    └── Company Loss on Sales (5221)             [FROZEN after W4 — §10.5]
```

**Classification note — why agent recoveries are NOT income `[R2]`.**
Revision 1 sited `Gateway Fee Recovery (Agents)` at `4133` under `Commission & Service Fee Income (4130) › Direct Income`, and credited `Loss Recovery Income (4170)`, also Direct Income, for negative-margin and clawback recoveries.

Recovering a processing cost or a trading loss **from a member of staff is not revenue**. There is no contract with a customer (IFRS 15 has no performance obligation here) and the counterparty is an employee. Putting either under **Direct Income** inflates the revenue line and the **gross-margin subtotal** that every management report, benchmark and covenant reads — precisely the class of error §10.5 and §10.6 condemn elsewhere in this document.

D4 mandates the *account* (a separate leaf for agent recovery, distinct from client recovery). It does not mandate its **placement**. So:

| Recovery | Revision 1 | Revision 2 | Presentation consequence |
|---|---|---|---|
| Gateway fee, from **client** | `4131`, Direct Income | **unchanged** — `4131`, Direct Income | Correct: a fee charged to a customer |
| Gateway fee, from **agent** | `4133`, Direct Income | **`5146`, contra-expense under `5140`** | Revenue unaffected; `5140`'s net balance answers *"what did card acceptance actually cost us after staff contributions"*, while `5141–5145` still show the gross cost |
| Negative margin, from **agent** | `4170`, Direct Income | **`5126`, contra-expense under `5100`** | Gross margin no longer inflated by staff cost-sharing |
| Refund clawback, from **agent** | `4170`, Direct Income | **credited back to `5125`** (§5.5) | The clawback expense line reports net of recovery, with the gross visible in the document trail |

`4170 Loss Recovery Income` is **frozen** after P3 (`disabled = 1`, ` (CLOSED)` appended) exactly as `2210` is: its historical balance stays attached to it, and no new line posts there. §10.14's argument for separating client from agent recovery is unaffected — it is *where* the agent leaf sits that changed, not *that* it exists.

*Naming.* The leaf keeps the name D4 gives it, **`Gateway Fee Recovery (Agents)`**; only its code and parent moved. Any reader looking for "4133" in D4 or in 22 §4.1 should read **5146**. Tracked as amendment **AM-20.3**.

### 2.3 Code choice, and why these numbers `[R2 — materially revised; revision 1's `1360` is withdrawn]`

The constraint is not aesthetic. Generators that can mint codes:

1. **`AgentController` mints FOUR codes**, not three, from `max(sibling code)+1` with **no collision check at all** — `$lastProfitCode` (children of the `2230` profit group), `$lastArCode` (children of `1350`), `$lastCompanyCode` (children of the `{Company}` group), `$lastAgentCode` (children of the `{Agent}` group). Migration `2026_02_11_162453` runs the same pattern via `getNextCode()`.
2. **A fifth generator** lives in `InvoiceController::addJournalEntry()`'s revenue-account creation: `orderByDesc('code')` + 5 under `Direct Income` — **lexicographic on a varchar**, so any non-numeric sibling code (see §2.6's `AGT-…` leaves) can poison it.
3. `AccountCodeGenerator::generate()` — numeric max among siblings, **padded to sibling width**, with a company-scoped `codeExists()` retry loop. Safe by construction.
4. `CoaController` manual creation — guarded by `Account::where('code', $request->code)->first()`, **not company-scoped** (verified: the check in `CoaController::store()` carries no `company_id` clause, while a *different* check in the same file — the code-edit path — **is** company-scoped; two different rules in one controller, and `Account` carries no global scope that would supply one), so a code used by *any* company blocks manual creation everywhere.

**Trace generator (1) under `1350`:**

| Step | Source | Value |
|---|---|---|
| `{Company}` group | `max(children of 1350)+1`; seeded child is `1351 Clients` | `1352` |
| Agent 1 `{Agent}` group | `max(children of {Company})+1`; none yet → `(int){Company}.code + 1` | `1353` |
| Agent 1 loss leaf | `max(children of {Agent})+1`; none yet → `(int){Agent}.code + 1` | `1354` |
| Agent 2 `{Agent}` group | `max(children of {Company})+1` = `max(1353)+1` | **`1354` — collides with agent 1's leaf** |
| Agent 2 loss leaf | `(int)1354 + 1` | `1355` |
| … | … | … |
| Agent 7 loss leaf | | **`1360`** |

So `1360` is reached by the **7th agent**, not left with "a 7-code gap" as revision 1 claimed. `1370` (revision 1's `Airline Incentive Receivable`) is the 13th agent's loss leaf. The same trace also **upgrades §10.9's `[inference]` about colliding codes to a verified fact**: agent *n*'s loss leaf and agent *n+1*'s group are issued the identical code by construction, on every company, from the second agent onward.

**Decisions `[R2]`:**

- **`Agent Receivables` group = `135900`** — six digits, structurally unreachable by any `max(4-digit sibling) + 1` generator, and outside the *entire* `13xx` runway rather than a guessed distance up it. Per-agent leaves are `135901, 135902, …` (99 agents before a width change; `AccountCodeGenerator` widens on its own).
- **`Airline Incentive Receivable` = `135800`** — same exposure, same treatment.
- **Hard ordering gate.** The `135900` group must **not** be created until **W3.A has deleted generators (1) and (2)**. Once a 6-digit code is the max child of `1350`, `max(child of 1350)+1` returns `135901` and the *legacy* generator would mint our first agent leaf's code for the next `{Company}` group. Stated as a dependency in §9: P5.3.A **depends on** W3.A, it does not merely follow it.
- **Per-company census, not `CoaSeeder` alone.** The migration runs a per-company census of every existing `13xx` code before creating anything, and **refuses the whole migration** on any collision, naming the company and the code. `CoaSeeder` describes the seed, not the runtime chart — `CoaSeeder`'s own comment (*"2240 is taken on City Travelers by an auto-numbered agent-profit leaf … so any code in that increasing range can eventually collide"*) is the precedent for not trusting it.
- **`1952`, `2202`, `4160`, `4210`, `5124`–`5126`, `5146`, `5147`** — all verified absent from `CoaSeeder`, none under a parent any runtime generator writes to, all following the existing sibling pattern (`1952` extends `1951`; `5146`/`5147` extend `5141–5145`; `5124`–`5126` extend `5122`/`5123`).
- **`4134`, `4135` `[R3, S6]`** — Cancellation/Change Fee Income, extending `4130`'s existing children (`4131`, `4132`); verified absent. `4133` stays permanently retired (AM-20.3 moved it to `5146`) and must never be reused, so these skip straight to `4134`.
- **`5211` `[R3, S8]`** — Sales Incentive Expense, extending `5200`'s existing children (`5218`, `5221`); verified absent.
- **Per-agent payable leaves use `223001, 223002, …`** — six digits, `parent_code × 100 + ordinal`.

  *Why 6 digits.* Every existing code in `CoaSeeder` is 4 digits. A 6-digit band (a) can never be produced by any `max(4-digit sibling)+1` generator, (b) can never collide with a seeded code — which matters because of generator (4)'s cross-tenant uniqueness check, (c) has room for 999 agents per group with no width change, and (d) is exactly what `AccountCodeGenerator` produces once the first leaf establishes the width (*"pad to sibling width"*). Contrast the 4-digit alternative: leaves at `2231…2299` — the collision `CoaSeeder` already had to route around.

  `ensurePartyLeaf()` seeds the **first** leaf under a fresh group explicitly (`223001` / `135901`) so `AccountCodeGenerator` has a numeric sibling to derive base and width from; without one it returns `null` and `AccountService` falls back to the row id as the code (`AccountCodeGenerator::fallbackCode()`), which would put a raw `accounts.id` in the code column.

### 2.4 Migration of the existing level-5 loss tree

Non-negotiable constraints: **no journal line ever moves accounts**, and **no account is deleted**. (13 §B.3: *"Do **not** rename it until every name-based lookup is dead … and do not delete it ever (its historical lines remain attached to it by design — that is the dual-record guarantee)."*)

Sequence, one migration + one command, idempotent:

| # | Action | Notes |
|---|---|---|
| **M0** `[R2]` | **Census** every `13xx` code per company; abort the whole migration on any code in `1358xx`/`1359xx` | §2.3 |
| M1 | Create group `135900 Agent Receivables` under `1350`, `is_group = 1` | `firstOrCreate` on `(company_id, parent_id, name)`. **Requires W3.A already deployed** |
| M2 | Rename `2230` `Agent Profit Payable` → **`Agent Commission Payable`** | Group node, no postings of its own — safe. Any name-based reader must be dead first (§8.4 gate), **including migration bodies** (§2.7) |
| M3 | For each agent with a `loss_account_id`: create the new leaf `{Agent}` under `135900`, code `1359nn` | `ensurePartyLeaf`; sets `agents.receivable_account_id` |
| M4 | Move the **balance**, not the history: one `JV` per agent, `sub_type = TREE_MIGRATION`, `Dr {Agent} (1359nn) / Cr Agent Loss Receivable (old level-5 leaf)` for the closing balance | Same mechanism as 13 §D.1 ("transfer documents through the engine"). Both accounts keep their own history; the audit trail is dual-recorded |
| M5 | Old level-5 leaf → `disabled = 1`, name suffixed ` (CLOSED)` | Blueprint 01 §6 rule 9: *"**Freezing** a parent cascades to its transactional children and appends ` (CLOSED)` to the name."* `disabled` is enforced at posting time by the P1 pipeline |
| M6 | The now-empty `{Agent}` and `{Company Name}` group nodes under `1350` → `disabled = 1` | Only once every child is closed and zero |
| M7 | Renumber existing `2231…` leaves to `2230{nn}` | **Code-only.** `account_id` unchanged, pointers unchanged, journal lines untouched. Gate: §8.4's name/code-lookup census must be clean first |
| M8 | Repoint `agents.profit_account_id` → `agents.commission_account_id` (rename the column) and drop `loss_account_id` in favour of `receivable_account_id`. **Drop and re-add both FKs** (§2.7) | Keep both columns for one release with the old ones written-through, then drop |
| **M9** `[R2]` | Deal with the `AGT-…` branch leaf (§2.6) | Not optional — it poisons generator (2) |

**M4 is a balance transfer, not a reattribution.** Unlike 13 Stage D (which moves per-line attribution off a pooled leaf), the agent loss leaf is *already* per-agent — its lines are correctly attributed, they are just in the wrong place in the tree. Moving the balance with a document and freezing the old leaf preserves every historical line at its original account, which is what makes the migration reversible (13 Stage F).

### 2.5 The `AccTransType`-style behaviour marker

Blueprint 01 §5 defines a behaviour flag on the account:

| Value | Meaning |
|---|---|
| 5 | Normal posting account |
| 1 | Cash account |
| 2 | Bank / card / payment-gateway |
| **8** | **Commission control account** |
| 6 | Pure group node |

`accounts` has **no such column today** (`2026_08_24_120002_add_engine_columns_to_accounts_table` adds only `deleted_at`, `created_by`, `updated_by`).

**Decision:** add `accounts.behaviour` (`tinyint unsigned`, nullable) in the **P7** COA-template work, alongside D9's `AccTransType` consolidation (21 §5b: *"AccTransType consolidation (01-9)"*). This spec sets, at that time:

- `8` on every leaf under `2230 Agent Commission Payable`;
- `2` on the gateway clearing leaves under `1300 Payment Gateway`;
- `6` on `135900`, `2230`, and every group node.

**Interim (before P7):** the payroll gate reads `transactions.doc_type = 'HRJV'` (§5.1) and needs no new column, so nothing in §5 blocks on P7.

### 2.6 The undocumented `AGT-…` branch leaf `[R2 — new section]`

`AgentController::store()` creates a **third** account per agent that no prior revision mentioned:

```php
Account::create([
    'code'      => 'AGT-' . rand(1000000, 9999999),
    'parent_id' => $branch->account->id,
    'root_id'   => $assetsAccount->id,
    'agent_id'  => $agent->id,
]);
```

Three problems: (a) it is an **asset** leaf per agent under the *branch* tree, duplicating the receivable concept with no stated purpose; (b) its code is **non-numeric and random**, so `AccountCodeGenerator`'s "numeric max among siblings, padded to sibling width" rule and generator (2)'s lexicographic `orderByDesc('code')` both behave unpredictably for anything created under a branch account; (c) nothing was found reading it this pass `[unverified — a full consumer census is part of M9]`.

**M9 disposition:**
1. Census consumers (`account_id` FKs, name/code lookups, reports) per company.
2. If it has **no journal lines**: soft-delete it; W3.A removes the creation block.
3. If it **has** journal lines: transfer the balance to `1359nn` with a `TREE_MIGRATION` `JV` exactly as M4, then `disabled = 1` + ` (CLOSED)`. Never delete.
4. Either way, W3.A deletes the creation block, so no new `AGT-` code is minted.

### 2.7 Two schema details revision 1 glossed `[R2 — new section]`

**The FKs are being *changed*, not created.** Migration `2026_02_11_162453` created `agents.profit_account_id` and `agents.loss_account_id` with `->onDelete('set null')` (verified). §3.2 presents `restrictOnDelete()` as if the schema were new. The migration must **drop each foreign key and re-add it** with `RESTRICT`, and must state that `SET NULL` on an account with history is exactly the silent-orphan behaviour 13 §B.2 exists to stop.

**A re-run of `2026_02_11_162453` undoes M2.** `createProfitAccount()` calls `getOrCreateAccount($accruedExpenses, 'Agent Profit Payable', '2230', …)` — a **name+code** lookup. On a fresh tenant or a rebuilt environment this recreates the old-named group as a **sibling** of the renamed one and repoints `agents.profit_account_id` at it. The §8.4 gate therefore covers **migration bodies as well as application code**, and `2026_02_11_162453` is amended in the same PR as M2 to resolve the group through the `AGENT_COMMISSION_PAYABLE_GROUP` purpose code (§3.3) with a name fallback matching *both* names.

---

## 3. Purpose codes + party pointers

### 3.1 The division of labour (blueprint 01 §7) `[R2 — corrected against the shipped engine]`

> *"A party master (e.g. `tblPartner`) holds the party's details plus **pointers to its GL accounts** … the feeder modules look up these pointers to know which account to debit/credit, so the ledger itself never needs to know what a 'customer' or 'airline' is."*

So:

| Resolved by | What | How the feeder expresses it |
|---|---|---|
| **Party pointer** (per-party leaf) | Agent Commission Payable (X), Agent Receivable (X), Client receivable, Supplier payable, Airline payable | `new LineDraft(purposeCode: '', accountId: $agent->commission_account_id, transactionType: 'AGENT_COMMISSION_PAYABLE', …)` |
| **`AccountResolver::resolve()`** (registry, `system_accounts`) | every shared control/income/expense leaf | `new LineDraft(purposeCode: 'COMMISSION_EXPENSE', accountId: null, …)` |
| **`AccountResolver::resolveAnchor()`** (group node, never a posting target) | the parents `2230` and `135900`, used only by `ensurePartyLeaf()` | `resolveAnchor('AGENT_COMMISSION_PAYABLE_GROUP', $companyId)` — **new in W3.A2**, §3.5 |

**Revision 1 was wrong here and every §5 posting inherited the error.** `PostingService` **rejects outright** a line that supplies both an explicit `accountId` and a non-empty `purposeCode`. Its own docblock: *"LOW — a line that supplies both an explicit accountId and a non-empty purposeCode is now rejected outright (ambiguous input) instead of silently preferring accountId"*, implemented in `PostingService::resolveLineAccountId()`:

```php
if ($line->accountId !== null && $line->purposeCode !== '') {
    throw …('DocumentDraft::$lines[%d] supplies both accountId (#%d) and a non-empty purposeCode …');
}
```

`PostingService::reverse()` already models the correct call shape: `purposeCode: ''` with the comment *"explicit accountId path is always used for reversals"*.

**Consequences applied throughout revision 2:**
- Every per-party line in §5 passes **`purposeCode: ''`** and **`accountId:`**.
- The audit/report label moves to **`LineDraft::$transactionType`**, which exists for exactly this (*"audit label: CUSTOMERDEBITED, SUPPLIERCREDITED, INCOME, CCCHARGES…"*).
- **`AGENT_COMMISSION_PAYABLE` and `AGENT_RECEIVABLE` are `transactionType` labels** — not purpose codes, not registry entries.
- Invariant **A11 is restated** (Appendix A): *a party-pointer line carries an empty `purposeCode`*. Revision 1's assertion — *"`PostingService` should assert that an `AGENT_*` purpose label carries an `accountId`"* (§3.3 and A11) — demanded the opposite of the shipped rule and is deleted.

**Rule: a per-party leaf is NEVER resolved by name, and NEVER by `AccountResolver::resolve()`.** This is precisely why the agent flows are absent from the magic-name pathology list (13 §B.1: *"Consumers resolve via the FK, never by name — which is why the agent flows are absent from the magic-name pathology list"*). §5's patterns preserve that property; §10.11 lists the places where today's agent code broke it.

### 3.2 Party pointers — schema

```php
Schema::table('agents', function (Blueprint $table) {
    // renamed from profit_account_id; the EXISTING FK is dropped and re-added (§2.7)
    $table->foreignId('commission_account_id')->nullable()
          ->constrained('accounts')->restrictOnDelete();   // RESTRICT, per 13 §B.2
    // replaces loss_account_id; same drop-and-re-add
    $table->foreignId('receivable_account_id')->nullable()
          ->constrained('accounts')->restrictOnDelete();
    // Rule 6 — agent as customer
    $table->foreignId('client_id')->nullable()
          ->constrained('clients')->nullOnDelete();
    // Rule 7 — credit control (column names per 22 §3 P5.12)
    $table->decimal('credit_limit', 15, 3)->nullable();
    $table->date('credit_from_date')->nullable();
    $table->boolean('is_blacklisted')->default(false);
    // O8/O9 [R3] — this agent's row in the NEW cost_centers master (§9 W3.F).
    // journal_entries.cost_center_id / transactions.cost_center_id copy THIS value,
    // never agents.id — see the CC(A) notation used throughout §5.
    $table->foreignId('cost_center_id')->nullable()
          ->constrained('cost_centers')->restrictOnDelete();
});
```

`restrictOnDelete` — not cascade, and **not the `set null` that is there today** — because deleting an account that a party points at must be impossible while it has history (13 §B.2, *"RESTRICT, not CASCADE — 02 Finding 8"*).

Both pointers are populated by **`AccountService::ensurePartyLeaf()`** called from an **`Agent` `created` observer**, following 13 §B.5's hybrid verdict: observer for new parties, targeted batch for existing (§8), lazy for dormant. The hand-rolled tree-building blocks in `AgentController` are deleted, not refactored — *"just moved into an observer so imports and console paths can't bypass it."*

**`ensurePartyLeaf()` does not work today for this purpose.** §3.5 is the prerequisite.

### 3.3 Purpose codes to add

Appended to `config('accounting.purpose_codes.global')` (posting codes) and `config('accounting.purpose_codes.anchors')` (anchor codes, new — §3.5), mapped per company by `SystemAccountsSeeder::mapByChain()` (the same mechanism that maps `SALARY_EXPENSE` → 5160 and `SALARY_PAYABLE` → 2201), backfilled for existing companies by `EnsureSystemLeaves`:

| Purpose code | Kind | Resolves to | Used by |
|---|---|---|---|
| `COMMISSION_EXPENSE` | posting | `Commissions Expense (Agents)` (5130) | §5.2 — the **only** debit leg for earned commission |
| `GATEWAY_FEE_RECOVERY_CLIENT` | posting | `Gateway Fee Recovery` (4131) | §5.3(a) — client fee recovery, markup, and rounding uplift |
| `GATEWAY_FEE_RECOVERY_AGENT` | posting | `Gateway Fee Recovery (Agents)` (**5146**) | §5.3(c)(d) |
| `GATEWAY_RECON_DIFFERENCE` | posting | `Gateway Reconciliation Difference` (5147) | §5.3(a) |
| `LOSS_RECOVERY_AGENT` | posting | `Loss Recovery (Agents)` (**5126**) | §5.4 negative margin, agent share |
| `AIRLINE_CLAWBACK_EXPENSE` | posting | `Airline Refund Clawback` (5125) | §5.5 — both the airline event and the agent recovery |
| `AIRLINE_MEMO_CONTROL` | posting | `Airline Memo Control` (1952) | §5.6–§5.8 — both legs of every memo |
| `ADM_EXPENSE` | posting | `Airline Debit Memos (ADM)` (5124) | §5.6 company-borne leg |
| `ACM_INCOME` | posting | `Airline Memos & Incentives (ACM)` (4160) | §5.8, §5.14 |
| `AIRLINE_INCENTIVE_RECEIVABLE` | posting | `Airline Incentive Receivable` (135800) | §5.14 accrual |
| `COMPANY_LOSS_ON_SALES` | posting | `Company Loss on Sales` (5221) | replaces `Account::where('name', 'Company Loss on Sales')` in the code W4.A deletes; **frozen after W4** |
| `PAYROLL_DEDUCTION_CLEARING` | posting | `Payroll Deduction Clearing` (2202) | §5.12 |
| `BAD_DEBT_EXPENSE` | posting | **`Write Off` (5218)** — exists in `CoaSeeder` today and is postable now `[R2: the P7 dependency is dropped]` | §5.17 write-off |
| `UNCLAIMED_LIABILITY_INCOME` | posting | `Unclaimed Balances Written Back` (4210) | §5.17 write-back `[R2 — revision 1 credited `4200` by code with no purpose code, violating §3.1's own rule]` |
| **`CANCELLATION_FEE_INCOME`** `[R3, S6]` | posting | `Cancellation Fee Income` (4134) | §5.6-family fee invoices — cancellation |
| **`CHANGE_FEE_INCOME`** `[R3, S6]` | posting | `Change Fee Income` (4135) | §5.6-family fee invoices — change/reissue |
| **`SALES_INCENTIVE_EXPENSE`** `[R3, S8]` | posting | `Sales Incentive Expense` (5211) | §5.19 spot commission, incentive mode |
| **`AGENT_COMMISSION_PAYABLE_GROUP`** | **anchor** | group `2230` | `ensurePartyLeaf()` only — §3.5 |
| **`AGENT_RECEIVABLE_GROUP`** | **anchor** | group `135900` | `ensurePartyLeaf()` only — §3.5 |

Existing codes reused unchanged: `SALARY_EXPENSE` (5160), `SALARY_PAYABLE` (2201), `GATEWAY_FEE_EXPENSE_{GATEWAY}` (5141–5145), `GATEWAY_CLEARING_{GATEWAY}`, `RECEIVABLE_CONTROL`, `PAYABLE_CONTROL`, `SERVICE_REVENUE` / `SERVICE_COST` / `SERVICE_PAYABLE` (per-service, via `LineDraft::$serviceType`).

The two **anchor** codes follow 13 §B.5's `*_PARTY_GROUP` convention, which `AccountService`'s own docblock names as the intended pattern (*"a distinct anchor purpose code, separate from the control one … `RECEIVABLE_CONTROL_LEGACY` vs `RECEIVABLE_PARTY_GROUP`"*). If 13 lands the `_PARTY_GROUP` suffix first, adopt it — the mechanism is identical.

### 3.4 Line-level dimensions this spec requires `[R2 — materially corrected]`

**`journal_entries.agent_id` DOES NOT EXIST.** Revision 1 asserted it *"already exists and is already written"*. It does not: no migration in `database/migrations/` adds an `agent_id` column to `general_ledgers`/`journal_entries`, and `JournalEntry::$fillable` does not list it (verified — the array runs `transaction_id … locked_at` with no `agent_id`). Every `JournalEntry::create([… 'agent_id' => $agent->id …])` in `InvoiceController::createProfitEntries()`, `createSupplierLossEntries()`, `createFeeLossEntries()`, `createGatewayProfitEntries()`, `TaskController`'s supplier-cost leg, and `AgentSettlementService::settleByProfit()` / `onPaymentCompleted()` is **silently discarded by mass assignment**. 19 §3(e) already records this (*"`journal_entries` has no `agent_id` column … the attribution is silently dropped"*); revision 1 contradicted a companion doc it cited, and §8.2 P3.a's census was built on the contradiction.

**Decision `[R2]`: do not add `agent_id`. The agent dimension is `cost_center_id`.** Adding a third overlapping column (`agent_id`, `type_reference_id`, `cost_center_id`) invites three answers to one question. `cost_center_id` is what D1 names, what 22 §2.1a schedules, and what §7.3's reporting contract reads.

Columns added on `journal_entries` — all additive, all already scheduled in 21 §5a / 22 §2.1a:

| Column | Type | Purpose |
|---|---|---|
| `cost_center_id` | `unsignedBigInteger` nullable | Rule 1b — the agent dimension on **every** line of a document the agent originated. 22 §2.1a requires the twin `transactions.cost_center_id`; both are added together |
| `reason_tag` `[R3 — eight values; name and type per 22 §2.1a, not revision 1's `reason varchar(16)`]` | `varchar(16)` nullable, validated in `LineDraft` `[R3, O7 — a typed varchar column, NOT a DB `enum`; a DB `enum` would force a migration per future tag]` | Rule 3. Vocabulary (eight values, identical in 22 §2.1a row 02-7) `[R3, fix round 2]`: `loan | service | adm | fee | loss | settlement | writeoff | advance`. **Six of these are 22 §2.1a's agreed vocabulary verbatim**; `writeoff` was the first addition (**AM-20.2**, §5.17 has no other home for it); **`advance` is the second addition `[R3, S8]`** — the spot-commission advance mode's open item on `135901` (§5.19) has no other home either, and both docs carry the identical eight-value list per the orchestrator's binding ruling. 22's own acceptance test `ReasonTagTest::engine_rejects_an_unknown_value` `[R3, fix round 2 — corrected from `…_reason_tag`, the actual name 22 uses]` then still holds |
| `settled_amount` | `decimal(18,3) default 0` | §6, per 11 §P5.3 and 22 §2.1a row 02-6 |

**Revision 1's `reason varchar(16)` column is withdrawn.** It renamed 22's agreed column, changed its type, and added four values (`commission`, `clawback`, `bonus`, `writeoff`) that 22's agreed test would reject. Mapping applied throughout §5:

| Revision 1 tag | Revision 2 |
|---|---|
| `commission` | **`NULL`** — an earned-commission line is not a charge and needs no tag |
| `clawback` | **`loss`** (22 §5.3: *"`agent`/`split` add the recovery debit routed by D3, tagged `loss`"*) |
| `bonus` | **`NULL`** |
| `writeoff` | `writeoff` — **AM-20.2 is GRANTED** by ruling O7 |
| `fee`, `loss`, `adm`, `loan`, `service`, `settlement` | unchanged |

**`LineDraft` gains** the matching additive, defaulted, trailing fields `?int $costCenterId = null` and `?string $reasonTag = null`, written verbatim to those columns by `PostingService` — the same additive pattern `LineDraft` already documents for `$invoiceId` / `$ledgerType` / `$partyName`.

**`DocumentDraft::$costCenterId` already exists and is dead `[R2]`.** `PostingService`'s docblock note 9 says so outright (*"`journal_entries.cost_center_id` does not exist in P1 (P5.7, later) even though DocumentDraft carries a header-level `$costCenterId` for forward compatibility"*), and `PostingService` passes it into `PostedDocument` but never to a column. **W3.B wires the existing header field and adds the line-level one** — it does not add a parallel header field.

`type_reference_id` is unchanged and is still written from `LineDraft::$partyAccountRef` (*"always a PARTY id (client_id/supplier_id/agent_id), never an account id"*). It says **whose line this is**; `cost_center_id` says **which cost centre this belongs to**, and on a shared cost (a branch-level gateway fee split across agents) they differ.

### 3.5 Engine prerequisite: group anchors `[R2 — new section; this was a BLOCKER]`

Revision 1's §9 listed W3.A's dependency as *"P1.0 `AccountService` + `AccountCodeGenerator` (**shipped**)"*. That is wrong: **`ensurePartyLeaf()` cannot create either agent leaf today**, for two independent reasons, both verified in `app/Services/Accounting/AccountService.php` and `AccountResolver.php`.

**Reason 1 — the anchor must not be a leaf that `system_accounts` maps.**
`ensurePartyLeaf()` opens with `$anchor = $this->resolver->resolve($purposeCode, $companyId);` then calls `assertAnchorIsSafeToExpand($anchor, …)`, which throws `AccountValidationException` whenever the anchor is still a leaf that `system_accounts` maps **for any purpose code**. Its docblock states this is deliberate and names the exit: *"De-pooling a leaf into a proper per-party tree needs a distinct anchor purpose code, separate from the control one (… `RECEIVABLE_CONTROL_LEGACY` vs `RECEIVABLE_PARTY_GROUP`) plus a migration … explicitly P2 scope."*
So registering `AGENT_COMMISSION_PAYABLE_GROUP → 2230` is necessary — and, on its own, **still throws**, because the guard tests *any* `system_accounts` row pointing at the anchor, including the anchor row we just created. Revision 1's claim that *"`AGENT_COMMISSION_PAYABLE` and `AGENT_RECEIVABLE` are labels, not registry entries — they are never added to `system_accounts`"* made this worse, not better: without a registry entry there is no anchor to resolve at all.

**Reason 2 — the second agent cannot be created.**
`AccountResolver::resolve()` calls `AccountResolver::isLeaf()`, whose docblock is explicit: *"a leaf is any account with zero child rows, full stop. `accounts.is_group` is NEVER consulted."* Once agent 1's leaf exists under `2230`, `2230` has a child, so `resolve('AGENT_COMMISSION_PAYABLE_GROUP')` throws `NonLeafAccountException` for agent 2 onward.

**W3.A2 — the named prerequisite.** Two small, contained, additive engine changes:

1. **`AccountResolver::resolveAnchor(string $purposeCode, int $companyId): Account`** — resolves through `system_accounts` exactly as `resolve()` does but **omits the leaf assertion**, and **refuses** any purpose code not declared in `config('accounting.purpose_codes.anchors')`. `resolve()` itself is untouched: a posting line can still never target a group, which is the invariant that matters.
2. **`assertAnchorIsSafeToExpand()`** ignores `system_accounts` rows whose `purpose_code` is in the anchor set. Its escape hatch — which its own docblock calls *"real, just correctly unreachable under P1's current vocabulary"* — becomes reachable, exactly as designed.

`ensurePartyLeaf()` then calls `resolveAnchor()` instead of `resolve()`. No existing call site changes behaviour; the anchor set is empty until this document's two codes are registered.
Tests: `AnchorResolutionTest::an_anchor_purpose_code_resolves_to_a_group_with_children`; `…::a_posting_purpose_code_still_refuses_a_group`; `…::ensure_party_leaf_creates_the_second_and_hundredth_agent`.

**This is unbuilt work and W3.A depends on it.** §9 is corrected accordingly.

---

## 4. Company options catalogue `[R2 — table shape changed]`

Bearer policy lives in **one** table, replacing `agent_loss` and `agent_charge` (§8.3):

```php
Schema::create('agent_charge_policies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('agent_id')->nullable()->constrained()->cascadeOnDelete(); // null = company default
    $table->string('charge_kind', 24);         // gateway_fee | negative_margin | refund_clawback | adm
    $table->string('scope_key', 24)->nullable(); // `[R3, fix round 2, verifier B-scope]` real discriminator: null/'sale' = ordinary policy; 'settlement' = O13's agent-settlement-gateway-fee scope
    $table->string('bearer', 8);               // client | company | agent | split
    $table->decimal('agent_percentage', 5, 2)->default(50);
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    // NULL-safe uniqueness. MySQL treats NULLs as DISTINCT in a unique index — this codebase
    // documents that trap in 2026_08_24_120004's own comment — so a nullable agent_id, and now
    // a nullable scope_key too, can never enforce "one row per (agent, charge_kind, scope)" on
    // their own. policy_key is a STORED GENERATED column that NULL-collapses all three:
    // CONCAT(COALESCE(agent_id, 0), ':', charge_kind, ':', COALESCE(scope_key, '-')).
    $table->string('policy_key', 64)
          ->storedAs("CONCAT(COALESCE(agent_id, 0), ':', charge_kind, ':', COALESCE(scope_key, '-'))");
    $table->unique(['company_id', 'policy_key'], 'acp_policy_unique');
});
```

**Two changes from revision 1, both required:**

**(a) The per-gateway override moves out of this table and onto `paid_by` `[R2]`.** D4 says *"payment_methods/charges `paid_by` today = client|company; **add the internal split**"*, and 22 §2.1c says *"extend the vocabulary, do not add a second column"*. Revision 1 did the opposite: it added `payment_method_id` **and** `gateway` override columns here (two columns expressing one override, with no stated precedence between them) and left `paid_by` alone. Revision 2:

```php
// charges AND payment_methods
$table->enum('paid_by', ['Client', 'Company', 'Agent', 'Split'])->change();
$table->decimal('agent_percentage', 5, 2)->nullable();   // read only when paid_by = 'Split'
```

**Precedence, stated once (most specific wins) `[R3 — rows 1 and 2 swapped: the payment method wins over the charge row, per the orchestrator's binding ruling — this is also what this section's own cited evidence already implied]`:**

| # | Source | Scope |
|---|---|---|
| 1 | `payment_methods.paid_by` (+ `agent_percentage`) | the method the client chose |
| 2 | `charges.paid_by` (+ `agent_percentage`) | the specific charge row actually applied |
| 3 | `agent_charge_policies` where `agent_id = <agent>` and `charge_kind` | that agent |
| 4 | `agent_charge_policies` where `agent_id IS NULL` and `charge_kind` | company default |
| 5 | hard default | `bearer = company`, `agent_percentage = 0` |

`ChargeService::calculate()` sets `$contractCharge`/`$backOfficeCharge` from `$method` in its first branch and from `$charge` only in the else branch — so the bearer resolver must read them in that same order, or the system carries two disagreeing precedences. `[inference: read from `calculate()`'s two branches]`

**(b) `company_percentage` is not stored** — it is `100 − agent_percentage`, computed. Storing both invites the two to disagree. Note for the backfill: today `agent_loss` and `agent_charge` **both** store both, and **both default `agent_percentage` to `0`, not 50** (migrations `2026_02_11_143329` and `2026_01_29_130208`), so §8.3 must carry the stored value and never assume the new default.

**Acceptance tests this shape requires:** `BearerPolicyTest::a_second_company_default_row_for_the_same_charge_kind_is_rejected`; `BearerPolicyTest::method_wins_over_charge_wins_over_agent_policy_wins_over_company_default` `[R3, fix round 2 — this is 22 §4.1's test name; it covers the agent-policy rung this table's own rows 3/4 need, and "one test name, one owner" (22 §2.1a/§5.2 E18) means 22 owns it, not a doc-20-only rename]`.

### 4.1 The table

| # | Option | Values | Default | Stored in | Posting each value produces |
|---|---|---|---|---|---|
| **O1** | `gateway_fee.bearer` | `client` `[R3 — GUARDED, per Q-20.6: this value must NOT be enabled on ANY company until W4.D's gross-up ships. Until then `5147` exists for genuine settlement noise only, never as a substitute for the gross-up. Config validation refuses `client` while `accounting.engine.grossup_shipped` (or equivalent flag) is unset]` | **`company`** `[R2 — 22 §4.1 sets `accounting.bearer.gateway_fee` default = `company`; `payment_methods.paid_by` also defaults to `Company` (`2025_06_11_163406`) and `ChargeService` reads it first, so `company` is also the effective default today]` | `agent_charge_policies` + `charges.paid_by` / `payment_methods.paid_by` | A **`DBN / FEE_RECOVERY` dated the payment** (not the invoice — §5.3(a)) raises `Dr AR / Cr 4131` for `clientFee = accountingFee + markup + rounding`. Fee expense still booked gross in the RV: `Dr GATEWAY_CLEARING (net) + Dr GATEWAY_FEE_EXPENSE_{gw} (real) / Cr AR (gross)`. Agent charge: **none**. This **replaces `createGatewayProfitEntries()`**, which W4.D deletes |
| | | `company` | | | No fee-recovery document. RV as above. The company absorbs the real fee in 5141–5145. Agent charge: **none**. §5.3(b) |
| | | `agent` | | | RV as `company`. **Plus** a `DBN/AGENT_CHARGE`: `Dr 223001 (then 1359nn on overflow) / Cr 5146` at the **real** fee, `reason_tag = fee`. §5.3(c) |
| | | `split` (+ `agent_percentage`) | 50 | | RV as `company`. Plus `DBN/AGENT_CHARGE` for `round(realFee × pct, 3)`; the company keeps the **remainder** (`realFee − agentShare`), never a second `round()`. §5.3(d) |
| **O2** | `negative_margin.bearer` | `company` | `company` | `charge_kind = negative_margin` | **No entry** — the loss is already in the books as cost > revenue (§10.5). **Conditional on W4.C** (§5.4): the supplier-cost leg must post in the sale's own period, which it does not today |
| | | `agent` | | | `DBN/AGENT_CHARGE`: `Dr 223001 (overflow 1359nn) / Cr 5126 Loss Recovery (Agents)` at the full negative margin, `reason_tag = loss`. §5.4 |
| | | `split` | | | Same, at `round(margin × pct, 3)`; company remainder needs no entry |
| | | `client` | | | **Invalid.** Rejected at save with a typed error (22 §4.1: *"a client cannot bear our negative margin"*) |
| **O3** `[R2 — redefined; revision 1 never booked the airline's clawback at all]` `[rev 4, 2026-08-27 — owner-confirmed unchanged: commission clawback bearer defaults to company, per W4 refund brief]` | `refund_clawback.bearer` | `company` | `company` | `charge_kind = refund_clawback` | The **airline's clawback is always booked**: `Dr 5125 Airline Refund Clawback / Cr airline payable`. Under `company` that is the whole story. §5.5(a) |
| | | `agent` | | | Airline clawback as above, **plus** a recovery: `Dr 223001 (overflow 1359nn) / Cr 5125`, `reason_tag = loss` (22 §5.3 verbatim: *"The clawback is booked to the … expense account in every case. `agent`/`split` add the recovery debit routed by D3, tagged `loss`"*). §5.5(b) |
| | | `split` | | | Same at `pct` |
| **O3b** `[R2 — new; this is a THIRD, distinct event]` | `commission_on_refunded_sale` — fully qualified `accounting.agent.commission_on_refunded_sale` `[R3, fix round 2 — stated once here so this and 22's option-index cell are grep-identical]` | `un_earn` | `un_earn` | `companies` column | The agent's own commission on a sale later refunded: `Dr 223001 / Cr 5130`, subject to the **per-obligation** test in §5.5(c). Independent of O3 — one is the airline's money, the other is the agent's |
| | | `keep` | | | No entry. The agent keeps the commission on a refunded sale |
| **O4** `[rev 4, 2026-08-27 — DEFAULT CHANGED: owner ruling on the W4 refund brief flips the ADM bearer default from `company` to `agent`, each still configurable {company\|agent\|split%} per company]` | `adm.bearer` | `company` | **`agent`** `[was `company` before rev 4]` | `charge_kind = adm` | Step 2 = `Dr 5124 ADM / Cr 1952`. §5.6 |
| | | `agent` | | | Step 2 = `Dr 223001 (overflow 1359nn) / Cr 1952`, `reason_tag = adm`. §5.7 |
| | | `split` | | | Step 2 = `Dr 5124 (company share) + Dr agent (agent share) / Cr 1952`. §5.7 |
| **O5** | `gateway_fee` per-gateway / per-method override | any O1 value + `agent_percentage` | inherit | **`charges.paid_by` / `payment_methods.paid_by`** `[R2]` | Identical postings to O1; only the resolved bearer differs per gateway. Exists because a 2.5% card and a 0.250 KWD KNET flat are different economics. Precedence is §4(a)'s table |
| **O6** | `commission_recognition` | `on_invoice` | `on_invoice` (blueprint 07 §11: *"When a sale posts its income line, it credits the relevant salesperson's account"*) | `companies` column | Commission `JV/AGENT_COMMISSION` posted with the invoice, `doc_date = invoice_date`. §5.2 |
| | | `on_collection` `[R2 — fully specified]` | | | No entry at invoice. On each receipt: allocate the receipt across `invoice_details` **pro-rata to each detail's own total** — a whole-invoice ratio cannot allocate a per-task base, which Rule 1e requires. Per task: `target = round(taskCommission × taskCollected / taskTotal, 3)`; `delta = target − alreadyPosted`. **`delta > 0` → `Dr 5130 / Cr 223001`; `delta < 0` → `Dr 223001 / Cr 5130` for `abs(delta)`.** `PostingService` rejects negative amounts (blueprint 02 §3 rule 2), so the **side flips and the sign never does**. The final receipt settles the rounding remainder; refunds are the same rule with a smaller `taskCollected` |
| **O7** | ~~`type4_provisional_accrual`~~ | — | — | — | **WITHDRAWN `[R2]`.** Revision 1 proposed posting a month-end-**dated** provisional `JV` mid-month. A future-dated document is invisible on every trial balance between the posting date and month end — which defeats the option's own stated purpose ("if the owner needs the liability visible intra-month"). `PeriodGuard` is also a documented no-op stub in P1, so the behaviour would have depended on unwritten P5.1 code. **The intra-month figure is a report from `AgentEarningsService`, not a posting.** §10.4 |
| **O8** | `settlement_default_method` | `deduct_commission \| deduct_salary \| cash \| payment_link \| wallet` | `deduct_commission` | `companies` column | **No posting.** Rule 3b: this pre-selects the settlement UI only. Every settlement is still an explicit, approved document |
| **O9** | `credit_control.mode` | `block` | `block` (D7) | `companies` column | Invoice save is refused when `outstanding + newInvoice > credit_limit`, or when `is_blacklisted`, or when `doc_date < credit_from_date`. **`outstanding` excludes `OJV` opening journals** (blueprint 03 §8, verbatim) `[R2 — revision 1's formula dropped both qualifiers]`. No document is created |
| | | `warn` | | | Invoice saves; a warning is recorded on the document and the party appears on the credit-exception report. `is_blacklisted` remains a hard stop **in every mode** (22 §4.1) |
| **O10** | `agent_credit_control_applies` | `on \| off` | `on` | `companies` column | `on`: Rule 6 sales to an agent are limit-checked against the **agent's** limit. `off`: agent sales are never blocked. No posting difference |
| **O11** | `payroll_visibility` | permission `accounting.payroll.view` | granted to Company/Owner roles only | Spatie permission | Not an option that changes postings — it gates **reading** `doc_type = 'HRJV'` documents and the `2201` ledger. D9: *"payroll confidentiality gate NOW (HRJV + permission)"*. §5.12 is what makes the gate compatible with a **complete** agent statement |
| **O12** | `ticket_stock_control` | `off` | `off` (D9) | `companies` column | `off`: serial-gap detection only on issued tickets (report, no postings). `on`: allocated ranges tracked; still no postings — ticket stock is a control, not a ledger object |
| **O13** | `agent_settlement_gateway_fee.bearer` | `company \| agent \| split` | `company` | `agent_charge_policies`, `charge_kind = gateway_fee`, `agent_id` = the agent, `scope_key = 'settlement'` `[R3, fix round 2 — `scope_key` is now the real discriminator column, not a note]` | The fee on an agent's *own* settlement payment. `company`: `Dr GATEWAY_FEE_EXPENSE_{gw}` and stop. `agent`/`split`: plus a `DBN/AGENT_CHARGE` per O1(c)/(d). Today this is silently company-borne with no policy — §10.12 |
| **O14** `[R3, Q-20.5]` | `commission_on_own_purchase` | `on \| off` | **`off`** | `companies` column | Governs whether §5.2's commission postings run at all for a Rule-6 agent-as-customer sale (§5.10). `off`: no `JV/AGENT_COMMISSION` document is raised for the agent's own purchase. `on`: §5.2 runs unchanged, exactly as for a third-party sale. Decided by the orchestrator (§10.19 Q-20.5): the honest default is **no** commission on one's own purchase — commission compensates for selling to a third party |
| **O15** `[R3, S8]` | `spot_share_pct` | `0`–`100` | `0` (off) | commission plan (company → per-agent override) | `0` disables spot commission entirely — §5.2's period-end formulas are the only postings. A non-zero value is the percentage of the task's/invoice's profit (per O17) paid **same day** — §5.19 |
| **O16** `[R3, S8]` | `spot_treatment` | **`incentive`** | `incentive` | commission plan | `Dr 5211 Sales Incentive Expense (cost_center = CC(A)) / Cr Cash`, same day, P&L same day. Period commission (§5.2) still posts on the **FULL** profit on top — company cost = both. §5.19(a) |
| | | `advance` | | | `Dr 135901 Agent Receivable (A), reason_tag=advance ← OPEN ITEM / Cr Cash`. No P&L. Netted at the next settlement (§5.11–§5.13) like a loan, but tagged `advance` so the statement distinguishes it. §5.19(b) |
| **O17** `[R3, S8]` | `spot_profit_basis` | `gross` | `gross` | commission plan | The spot pct multiplies Rule 1e's gross-margin base, unadjusted |
| | | `after_gateway_fee` | | | The spot pct multiplies gross margin **less** the real gateway fee already charged to the agent under O1(c)/(d)/O5 — ties spot pay to what the agent has actually netted so far |
| **O18** `[R3, S8]` | `spot_requires_client_paid` | `off` | | commission plan | No check: the spot share may be paid the same day as the sale, before the client has paid anything |
| | | `warn` | **`warn`** | | The "Pay spot share" action shows a warning (mirrors §5.3(a)'s `$clientPaid` check) but does not block |
| | | `block` | | | The action is refused until `$invoice->invoicePartials` shows the client has paid |
| **O19** `[R3, S8]` | `spot_true_up` | `on \| off` | `on` | commission plan | `on`: when the case's profit later changes (ADM, void, refund), §5.19(c) posts the delta. `off`: no true-up — the spot payment is final regardless of later adjustments |
| **O20** `[R3, S8]` | `spot_approval` | `none \| supervisor` | `none` | commission plan | `supervisor`: the "Pay spot share" `PV/SPOT_SHARE` (or `SPOT_ADVANCE`) document requires an approval step before it posts. No posting difference once approved |
| **O21** `[R3, S8]` | `period_commission` | `%profit \| slab \| target` | `%profit` | commission plan | Selects which of Rule 1's four earnings formulas (§5.2) computes the period-end commission on the **full** profit, independent of any spot payment already made. `%profit` = type 2's flat rate; `slab`/`target` reuse type 4's target-gated pool shape (§5.2 Type 4) with plan-specific bands. No new posting shape — §5.2's documents apply unchanged; this option only selects the formula |

**Invariant across every option value:** the document balances, the gateway fee expense is gross, the payable leaf is never driven below zero, and no option can produce an entry that debits an agent without a document the agent can be shown.

---
## 5. Documents, numbering, and worked postings

### 5.1 Document types and numbering `[R2 — sub_type values shortened; AST added]`

`config('accounting.doc_types')` today: `INV, RV, PV, JV, CRN, DBN, OJV, REV`. `SequenceService::next($docType, $companyId, $branchId, $date)` mints a number per `(company_id, branch_id, doc_type, doc_year)` from `serial_schemas`, and `transactions` carries `doc_type` / `sub_type` / `doc_year` with `unique(company_id, doc_type, reference_number)`.

> **`transactions.sub_type` is `string(16)`** — verified in `2026_08_24_120004_add_document_columns_to_transactions_table` (`$table->string('sub_type', 16)`). Seven of revision 1's values exceeded it: `AGENT_POOL_ACCRUAL` (18), `INCENTIVE_ACCRUAL` (17), `AGENT_TREE_MIGRATION` (20), `AGENT_COMMISSION_PAYOUT` (23), `AGENT_SETTLEMENT_SALARY` (23), `AGENT_LIABILITY_CORRECTION` (26), `AGENT_COMMISSION_PROVISION` (26). Under non-strict MySQL these truncate **silently**, and `AGENT_COMMISSION_PAYOUT` / `AGENT_SETTLEMENT_SALARY` would both truncate into collisions with real sub-types — breaking §8.2 P3.g's batch rollback, which keys on `sub_type`. **Every value below is ≤ 16 characters.** The column is not widened: shortening the vocabulary is cheaper and safer than a schema change to a table the engine writes on every post.

| Document | `doc_type` | `sub_type` (len) | Series | Notes |
|---|---|---|---|---|
| Commission accrual | `JV` | `AGENT_COMMISSION` (16) | JV | One per invoice (O6 `on_invoice`) |
| Type-4 pool accrual | `JV` | `POOL_ACCRUAL` (12) | JV | Month-end only |
| Commission delta on collection | `JV` | `COMM_ON_COLLECT` (15) | JV | O6 `on_collection` |
| Agent charge (fee / loss / ADM share) | `DBN` | `AGENT_CHARGE` (12) | DBN | Debit note **to the agent**; an **open item** on the receivable side when it overflows |
| Client gateway-fee recovery | `DBN` | `FEE_RECOVERY` (12) | DBN | O1 `client`; §5.3(a) `[R2 — replaces `createGatewayProfitEntries()`]` |
| Agent commission un-earn / clawback | `CRN` | `AGENT_CLAWBACK` (14) | CRN | §5.5(c) |
| Airline debit memo | `DBN` | `ADM` (3) | DBN | Memo module; `BSPTYPE = ADM` |
| Airline credit memo | `CRN` | `ACM` (3) | CRN | Memo module; `BSPTYPE = ACM` |
| Memo disposition (step 2, company side) | `JV` | `ADM_DISPOSITION` (15) / `ACM_DISPOSITION` (15) | JV | §5.6, §5.8 |
| Airline refund clawback | `DBN` | `RFND_CLAWBACK` (13) | DBN | §5.5(a) — the airline's claim |
| **Agent settlement (all methods, both directions)** | **`AST`** `[R2]` | `SETTLE_OFFSET` (13) / `SETTLE_CASH` (11) / `SETTLE_GATEWAY` (14) / `SETTLE_SALARY` (13) | **AST (new series)** | 22 §3 P5.13: *"**Agent settlement document** — its own `doc_type` and `SequenceService` series."* See below |
| Commission payout | `AST` | `COMM_PAYOUT` (11) | AST | Paying the payable out is a settlement in the other direction |
| Loan to agent | `PV` | `AGENT_LOAN` (10) | PV | A disbursement, not a settlement |
| **Spot share — incentive mode** `[R3, S8]` | `PV` | `SPOT_SHARE` (10) | PV | Same-day cash incentive; `cost_center = CC(A)`; §5.19(a) |
| **Spot share — advance mode** `[R3, S8]` | `PV` | `SPOT_ADVANCE` (12) | PV | `reason_tag = advance`, open item on `135901`; §5.19(b) |
| **Spot share — true-up** `[R3, S8]` | `JV` | `SPOT_TRUEUP` (11) | JV | §5.19(c) |
| Sale to an agent-as-customer | `INV` | `AGENT_SALE` (10) | INV | Rule 6 |
| **Salary accrual / payroll half of a salary-deduction settlement** | **`HRJV`** | `AGENT_SALARY` (12) / `PAYROLL_DEDUCT` (14) | **HRJV (new series)** | See below and §5.12 |
| Tree migration balance transfer | `JV` | `TREE_MIGRATION` (14) | JV | §2.4 M4/M9 |
| Incentive accrual | `JV` | `INCENTIVE_ACCR` (14) | JV | §5.14 (P7.5) |
| Write-off / write-back | `JV` | `AGENT_WRITEOFF` (14) / `AGENT_WRITEBACK` (15) | JV | Permission-gated, §5.17 |
| P3 liability correction | `JV` | `LIAB_CORRECTION` (15) | JV | §8.2 |

**`AST` is a new `doc_type` `[R2]`.** 22 §3 P5.13 requires the agent settlement document to have *"its own `doc_type` and `SequenceService` series"*; revision 1 routed settlements onto `JV`/`RV`/`HRJV` and so did not deliver that. `AST` covers **every** agent settlement in either direction, including the ones that move cash: when a settlement is collected through a bank or a gateway, the `AST` document itself carries the cash/clearing/fee lines (blueprint 03 §9's three-line receipt shape is a *line pattern*, not a document type). `transactions.doc_type` is `string(8)`, so `AST` and `HRJV` both fit.

**`HRJV` is a `doc_type`, not a `sub_type` — deliberately.** D2 specifies `doc_type HRJV`, and the engineering reason confirms it: the permission gate must exclude payroll documents from every ledger query with **one indexed predicate**. `transactions` has `index(company_id, doc_type, doc_year)` and `unique(company_id, doc_type, reference_number)`; `sub_type` is nullable and unindexed. Gating on `doc_type = 'HRJV'` is a single indexed filter that cannot be defeated by a null. Adding `'HRJV' => 'Payroll Journal Voucher'` to `config('accounting.doc_types')` also gives payroll its own `serial_schemas` series, so payroll numbering does not interleave with operational JVs — which is what an auditor expects of a confidential series.

> **Cutover prerequisite (21 §5a, before W3):** *"Run `accounting:seed-serial-schemas`; add a typed handler for the `reference_number` unique violation."* Every new series above — `HRJV` and `AST` especially — must be seeded before the first document, or `SequenceService` mints into an auto-created row and the first collision surfaces as an untyped driver exception. 17 §3 gate 3 records that this command has not been run `[unverified against the live DB]`.

### 5.2 Commission earned — types 1 to 4

Common scenario for all worked examples: **flight task, sell 100.000, cost 90.000, GROSS case margin 10.000 (Rule 1e). Agent A, commission rate 20%. Branch 1, company 1.**

Every line below also carries: `cost_center = CC(A)` `[R3 — O9]`, `partyAccountRef = A.id` (→ `type_reference_id`), `task_id`, `invoice_id`, `invoice_detail_id`, `serviceType = 'flight'`. **Currency is not hard-coded — see §5.18.**
There is **no `agent_id` column** to carry (§3.4).

**Type 1 (Salary).** No commission document. Ever. The month's salary is:

```
HRJV / AGENT_SALARY          doc_date = month end        [permission-gated]
Dr  5160  Agent Salaries            purpose SALARY_EXPENSE     reason_tag NULL     500.000
Cr  2201  Salaries & Wages Payable  purpose SALARY_PAYABLE     reason_tag NULL     500.000
```
*(This is the existing `AgentController` engine draft, moved from `doc_type JV / sub_type AGENT_SALARY` to `HRJV`.)*
Neither line touches `223001` or `1359nn` — see the invariant in §5.12.

**Type 2 (Commission).** Commission = 20% × **10.000 gross margin** = **2.000**.

```
JV / AGENT_COMMISSION        doc_date = invoice_date
Dr  5130    Commissions Expense (Agents)   purposeCode 'COMMISSION_EXPENSE'                     2.000
Cr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id   2.000
                                           transactionType 'AGENT_COMMISSION_PAYABLE'
```
`reason_tag` is **NULL** on both lines (§3.4). The credit line uses the party pointer with an **empty** purpose code (§3.1).

**Type 3 (Both-A).** **Exactly the type-2 document, unchanged** — plus the type-1 salary `HRJV`. Two documents, never one.

> The existing formula `$monthlyCommission = $totalTaskCommission + ($agent->salary ?? 0)` (`AgentController`) and `$commissionTotal += $salary` (`ReportController`) return *total monthly earnings*. If that number were posted to the commission leaf while salary is also accrued to 2201, salary would be a liability **twice**. `AgentEarningsService` therefore returns the two components separately (`commissionComponent`, `salaryComponent`) and the feeder posts only `commissionComponent` to 2230. §10.2.

**Type 4 (Both-B).** No per-invoice document — the target gate is unknowable until the month closes. Month-end, `Σ base = 3,000.000`, `salary = 500.000`, `rate = 15%`, `target = 2,000.000`:

`Σ base (3,000.000) > target (2,000.000)` → `commissionComponent = max(3,000.000 − 500.000, 0) × 0.15 = 375.000`.
(The report's `max(…, 0.0)` clamp is the correct one of the two divergent implementations; `AgentController`'s unclamped version posts a *negative* commission whenever salary exceeds the pool base — a debit to a payable with no obligation behind it.)

```
JV / POOL_ACCRUAL            doc_date = last day of month
Dr  5130    Commissions Expense (Agents)   purposeCode 'COMMISSION_EXPENSE'                   375.000
Cr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id 375.000
```
Plus the separate salary `HRJV` for 500.000. Total earnings 875.000 = the formula's `(Σ base − salary) × rate + salary` — **arrived at by two documents to two different liabilities**, which is what makes it correct.

*Re-running the month* posts the **delta only** (`computed − alreadyPosted`), never a delete, never a re-post; a negative delta flips the side exactly as O6 specifies. Idempotency key: `agent_pool:{agent_id}:{yyyymm}`.

### 5.3 Gateway fee share, all four bearer values `[R2 — dating, components, and the double-booking all corrected]`

Scenario: the 100.000 invoice is paid by KNET. `ChargeService::calculate()` returns `accountingFee = 1.500` (the real cost, *"For COA/profit: exact service charge"*), `markup_profit = 0.200` (`self_charge − service_charge` on the base), `rounding_profit = 0.300` (the `ceil` uplift), and `gatewayFee = client_pays = 2.000`.

`clientFee = 1.500 + 0.200 + 0.300 = 2.000` ✓ — **three components** (Rule 4).

**(a) `bearer = client`** — a **pricing** decision.

> **The fee cannot be posted with the invoice `[R2]`.** `$clientPaid` is derived from `$invoice->invoicePartials`: the gateway, and therefore the fee, is unknowable until a payment partial exists. Revision 1 dated the `Cr 4131` line at `invoice_date`, which cannot be executed. It is a **`DBN / FEE_RECOVERY` dated the payment**.

```
INV                          doc_date = invoice_date
Dr  1351/client leaf  Client receivable                                        100.000
Cr  4110  Flight Booking Revenue        purpose SERVICE_REVENUE, flight        100.000

DBN / FEE_RECOVERY           doc_date = payment_date
Dr  1351/client leaf  Client receivable   purposeCode '' , accountId client leaf   2.000
Cr  4131  Gateway Fee Recovery           purpose GATEWAY_FEE_RECOVERY_CLIENT       2.000
        (= 1.500 real fee + 0.200 markup + 0.300 rounding — D4: all three are income,
           never left in AR)

RV                           doc_date = payment_date
Dr  1300x  KNET clearing      purpose GATEWAY_CLEARING_KNET                        100.500
Dr  5144   KNET Charges       purpose GATEWAY_FEE_EXPENSE_KNET  reason_tag=fee       1.500
Cr  1351/client leaf  Client receivable   purposeCode '' , accountId client leaf   102.000
```
Net P&L effect: `+2.000 income − 1.500 expense = +0.500` — markup plus rounding, as income. Agent: **no charge**.

> **This REPLACES `createGatewayProfitEntries()` `[R2]`.** That method today already posts `Dr {gateway asset} / Cr 'Gateway Fee Recovery' (4131)` for `markup + rounding` (verified — it computes `$totalGatewayProfit = $markupProfit + $roundingProfit` and creates the pair, resolving the income leaf by `Account::where('name', 'Gateway Fee Recovery')`). Revision 1 added a *second* `Cr 4131` for the same markup and rounding inside the invoice and never scheduled the old method for deletion — **markup and rounding would have been booked twice**. **W4.D deletes `createGatewayProfitEntries()` and all four of its call sites** (`InvoiceController` has four: the main `addJournalEntry` path plus three in the recalculation/credit paths).

> **Reconciliation caveat `[R2]`.** `ChargeService` computes the fee on the **base** amount (100.000), but under client-bears the gateway actually collects 102.000 and takes its percentage on **that**. Blueprint 03 §9 requires the bank line to reconcile to *"the **net** actually deposited"*. **Rule:** under `bearer = client`, the fee must be computed on the grossed-up amount — closed form `f = ceil(r × A / (1 − r)) + extra` for a percentage gateway — and `ChargeService::calculateChargeForPayment()` is amended in W4.D to do so.
>
> **`[R3]` `bearer = client` must NOT be enabled on any company until W4.D's gross-up ships (Q-20.6, DECIDED, §10.19).** `5147 Gateway Reconciliation Difference` exists **only** for genuine settlement noise on gateways that already have the gross-up — never as a way to run `client`-bears before W4.D ships; `client` is refused at config-save time until then (O1, §4.1). Once W4.D has shipped, the residual difference between the computed clearing line and the settlement actually received posts to **`5147`** (`purpose GATEWAY_RECON_DIFFERENCE`) on the gateway reconciliation document, and appears on a report. It is never absorbed into the clearing account, where it would look like an unreconciled deposit forever.

**(b) `bearer = company`** *(the default — O1)*. Invoice has no fee document (`Dr AR 100.000 / Cr 4110 100.000`). RV: `Dr clearing 98.500 + Dr 5144 1.500 / Cr AR 100.000`. The 1.500 sits in the company's P&L. Agent: no charge.

**(c) `bearer = agent`.** Postings exactly as (b), **plus**:

```
DBN / AGENT_CHARGE           doc_date = payment_date
Dr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id
                                            reason_tag=fee   cost_center=CC(A)               1.500
Cr  5146    Gateway Fee Recovery (Agents)  purpose GATEWAY_FEE_RECOVERY_AGENT                1.500
```
**1.500, not 2.000** — the real fee (D4). The credit is a **contra-expense** under 5140, not income (§2.2).

*Overflow case.* If A's payable balance is only 0.900 at that moment — read under the lock and predicate of §6.5:
```
DBN / AGENT_CHARGE
Dr  223001  Agent Commission Payable (A)   reason_tag=fee                                    0.900
Dr  135901  Agent Receivable (A)           reason_tag=fee   ← OPEN ITEM, starts ageing today  0.600
Cr  5146    Gateway Fee Recovery (Agents)                                                    1.500
                                                                        (0.900 + 0.600 = 1.500 ✓)
```

**(d) `bearer = split`, `agent_percentage = 60`.**
`agentShare = round(1.500 × 0.60, 3) = 0.900`; `companyShare = 1.500 − 0.900 = 0.600` (**remainder**, never a second `round()` — otherwise the two shares can fail to sum to the fee at 3 decimals; `AgentLoss::calculateLossDistribution()` already does this correctly, `createFeeLossEntries()` does not — §10.17).

```
DBN / AGENT_CHARGE
Dr  223001  Agent Commission Payable (A)   reason_tag=fee                                    0.900
Cr  5146    Gateway Fee Recovery (Agents)                                                    0.900
```
The company's 0.600 needs **no entry** — it is already in 5144 and simply stays there.

### 5.4 Negative margin

Scenario: sell 100.000, cost 105.000 → margin **−5.000**. `negative_margin.bearer = agent`.

The bearer decision only moves the **recovery**:

```
DBN / AGENT_CHARGE           doc_date = invoice_date
Dr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id
                                            reason_tag=loss   cost_center=CC(A)              5.000
      (overflow → Dr 135901 Agent Receivable (A), same reason_tag)
Cr  5126    Loss Recovery (Agents)          purpose LOSS_RECOVERY_AGENT                       5.000
```

**Not** the legacy pattern. `InvoiceController::createSupplierLossEntries()` currently posts, for the company's share, `Dr 'Company Loss on Sales' (5221) / Cr {supplier cost account}` — described in its own comment as *"Transfer supplier loss to loss account"*. That credit **removes real cost from COGS**, overstating gross margin by the loss and understating it below the gross-profit line. §10.5.

> **O2's "no entry at all" for company-borne loss is conditional `[R2]`.** The premise — *"the loss is already in the books"* — is true **in aggregate but not per period**. `InvoiceController::addJournalEntry()` posts only `Dr client receivable` + `Cr <type> Booking Revenue`; the `Dr <supplier cost> / Cr <supplier payable>` pair is written by **`TaskController`**, in a **separate `Transaction`** dated `$task->supplier_pay_date ?? now()`. Revenue and cost therefore routinely land in **different months**, and W4.A deletes the only entry that flagged the loss at all. Blueprint 03 §3's `CTR` control postings exist precisely to stop this.
>
> **W4.C (new, and a named dependency of O2):** the supplier-cost leg must post **in the sale's own document and period**, `doc_date = invoice_date`; `tasks.supplier_pay_date` becomes a **due date** for the payable, not the posting date. Until W4.C ships, O2 = `company` must keep a company-side accrual (`Dr 5221 Company Loss on Sales / Cr <supplier payable accrual>`) so the loss is recognised in the sale's period. §9 sequences W4.C before W4.A's deletions.

### 5.5 Refund clawback — THREE distinct events `[R2 — revision 1 collapsed them into one and lost the airline's]`

Revision 1 treated `refund_clawback` purely as un-earning the *agent's* commission (`Cr 5130`), with `bearer = company` meaning *"Nothing — the company simply keeps having paid the commission."* That is not what 22 §5.3 defines, and under it **the airline-side clawback is never booked at all**.

`[R3 — correction]` Revision 2 wrongly cited 21 row **04-31 / MF-30** ("clawback account misclassified as an asset") here. **MF-30 is a different, unrelated defect** — the **`5130` 'Commissions Expense (Agents)' asset-classification bug** — already gated in **W3** per 22 §2.0, with no bearing on this section. `5125` below is simply a **new** expense leaf; it needs no reclassification and carries no prior history.

**(a) The airline's clawback — ALWAYS booked, every bearer value.** When a ticket is refunded, the airline claws back the commission it paid us:
```
DBN / RFND_CLAWBACK          doc_date = refund_date   ticket_number=…  airline_id=XY
Dr  5125  Airline Refund Clawback   purpose AIRLINE_CLAWBACK_EXPENSE   reason_tag=loss   2.500
Cr  21xx  Airline Payable (XY)      purposeCode '' , accountId airlines.account_id       2.500
```
**`5125` is a NEW expense leaf, created by P5.3.A (§9)** `[R3 — the "W4 reclassification target for MF-30" framing and the "freeze the old asset-classified leaf" instruction are withdrawn; see the correction above]`. It carries no prior history — every airline clawback simply posts here from first use.

**(b) The agent's share of (a)** — only when `refund_clawback.bearer ∈ {agent, split}`:
```
DBN / AGENT_CHARGE           doc_date = refund_date
Dr  223001  Agent Commission Payable (A)   reason_tag=loss   (overflow → 135901)      2.500
Cr  5125    Airline Refund Clawback        purpose AIRLINE_CLAWBACK_EXPENSE           2.500
```
A **recovery against the expense**, exactly as 22 §5.3 specifies (*"the recovery debit routed by D3, tagged `loss`"*) — **not** a credit to `5130`, which would report a supplier's clawback as a reduction of our own commission expense.

**(c) Un-earning the agent's own commission — a THIRD, separate event, governed by O3b.**
Scenario: A earned 2.000 on the task; the ticket is refunded; `commission_on_refunded_sale = un_earn`.

> **The test is per-obligation, not per-balance `[R2]`.** Revision 1 branched on *"While a payable balance exists"* (the aggregate leaf balance) in §5.5 while §5.15 asserted *"the payable is zero"* after a payout. **Both can be false at once**: an agent whose task-X commission was paid out but who holds an unpaid task-Y balance would, under revision 1, have task-Y's commission silently consumed by task-X's clawback — which is exactly the unrecorded set-off **Rule 3b forbids**.
>
> **Resolution.** The commission accrual **credit line is itself an open item for matching purposes** (§6.4b): `OpenItemService` maintains `settled_amount` on it, and a payout or offset settlement writes apply rows against the specific accrual line. The clawback then asks *"has THIS commission been discharged?"*, which is `accrualLine.settled_amount > 0`, not *"is the leaf balance positive?"*.

**(c-i) The specific commission accrual is unsettled** — this is an *un-earning*, so it reverses the original document:
```
CRN / AGENT_CLAWBACK         doc_date = refund_date
Dr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id
                                            reason_tag=loss                                  2.000
Cr  5130    Commissions Expense (Agents)   purpose COMMISSION_EXPENSE                        2.000
```
The expense goes away because the commission was never truly earned. Crediting an *income* account here would report a refund as revenue. Apply row: the CRN's debit is applied against the original accrual credit, closing it.

**(c-ii) The specific commission accrual has already been settled or paid out** — it becomes a genuine debt:
```
CRN / AGENT_CLAWBACK
Dr  135901  Agent Receivable (A)           reason_tag=loss  ← open item, ages from today     2.000
Cr  5130    Commissions Expense (Agents)                                                     2.000
```
**Never** release or delete the earlier settlement's apply rows (blueprint 07 §7's release guard; 13's dual-record principle). The clawback is a new document.

Test: `ClawbackTest::a_paid_out_commission_is_clawed_back_to_the_receivable_even_when_other_commission_is_unpaid`.

### 5.6 ADM — company-borne

Scenario: airline XY issues ADM **12.000** on ticket `157-1234567890`, PNR `ABC123`. `adm.bearer = company`.

**Step 1 — airline memo (always):**
```
DBN / ADM   BSPTYPE=ADM   ticket_number=157-1234567890  pnr=ABC123  airline_id=XY
Dr  1952  Airline Memo Control   purpose AIRLINE_MEMO_CONTROL   reason_tag=adm             12.000
Cr  21xx  Airline Payable (XY)   purposeCode '' , accountId airlines.account_id            12.000
```

**Step 2 — bearer leg:**
```
JV / ADM_DISPOSITION         doc_date = same
Dr  5124  Airline Debit Memos (ADM)  purpose ADM_EXPENSE  reason_tag=adm  cost_center=CC(A)    12.000
Cr  1952  Airline Memo Control       purpose AIRLINE_MEMO_CONTROL                          12.000
```
`1952` nets to zero. ✓ (`cost_center = CC(A)` even though A bears nothing — the ADM belongs to A's file, and Rule 1b says the *report* dimension is independent of who pays.)

### 5.7 ADM — agent-borne, and split

**Agent-borne** — step 1 identical; step 2 becomes:
```
DBN / AGENT_CHARGE           doc_date = same
Dr  223001  Agent Commission Payable (A)  reason_tag=adm   (up to balance, say 4.000)       4.000
Dr  135901  Agent Receivable (A)          reason_tag=adm   (overflow)                       8.000
Cr  1952    Airline Memo Control          purpose AIRLINE_MEMO_CONTROL                     12.000
```

**Split 50/50** — step 1 identical; step 2:
```
JV / ADM_DISPOSITION
Dr  5124    Airline Debit Memos (ADM)     purpose ADM_EXPENSE   cost_center=CC(A)           6.000
Dr  223001  Agent Commission Payable (A)  reason_tag=adm  (overflow → 135901)               6.000
Cr  1952    Airline Memo Control                                                           12.000
```

**Invariant test:** `SUM(debit) − SUM(credit)` on `1952` = 0 for every fully-dispositioned memo, and the *undispositioned* balance equals the list of memos with no step 2 — which is the BSP worklist (blueprint 04 §5's *"monthly ritual"*).

**Presentation of `1952` `[R2]`.** ADM leaves the control in **debit** (an asset); ACM (§5.8) leaves it in **credit** — a negative asset if presented naively under `Assets › Temporary Accounts`. Rule, stated explicitly:

- The balance-sheet renderer presents `1952` **by sign**: a debit balance under *Other current assets*, a credit balance under *Other current liabilities*. Same account, one line, sign-driven placement — the standard treatment for a two-way clearing account.
- The intra-period balance is **always** reported alongside its ageing worklist (memos with no step 2), so a non-zero balance is never just a number.
- **Why this is not `SUSPENSE`.** `config('accounting.php')` already carries a `SUSPENSE` purpose code. Suspense holds items whose *classification is unknown*. `1952`'s items are fully classified — a known memo, from a known airline, on a known ticket; only their *disposition* is pending. Merging them into suspense would hide a specific, actionable worklist inside a generic one. `1952` gets its own purpose code and its own report.
- Year-end refuses a non-zero balance (§5.16).

### 5.8 ACM

Airline XY issues ACM **8.000**:
```
CRN / ACM   BSPTYPE=ACM   ticket_number=…  airline_id=XY
Dr  21xx  Airline Payable (XY)   purposeCode '' , accountId airlines.account_id             8.000
Cr  1952  Airline Memo Control   purpose AIRLINE_MEMO_CONTROL                               8.000

JV / ACM_DISPOSITION
Dr  1952  Airline Memo Control   purpose AIRLINE_MEMO_CONTROL                               8.000
Cr  4160  Airline Memos & Incentives (ACM)   purpose ACM_INCOME                             8.000
```
If the ACM clears a **prior accrual** (§5.14), the second document credits `135800 Airline Incentive Receivable` to the extent accrued and `4160` only for the excess.

`4160` **is** income and stays in Direct Income — the counterparty is the airline, a third party, under a real commercial arrangement. That is the distinction §2.2 draws: recoveries from staff are not income; payments from airlines are.

If a company/split policy says the agent shares in an ACM benefit, the disposition credits the agent (`Cr 223001 / Dr 1952`) — the mirror of §5.7. Blueprint 04 §5: *"**ACM** … the airline credits you (commission top-up, refund, incentive): `Dr` airline payable, `Cr` income/recovery (you owe less)."*

### 5.9 Loan to an agent

200.000 advanced from the bank. Rule 3: not earnings → straight to the receivable.
```
PV / AGENT_LOAN              doc_date = advance_date
Dr  135901  Agent Receivable (A)   purposeCode '' , accountId A.receivable_account_id
                                    reason_tag=loan  cost_center=CC(A)   ← OPEN ITEM          200.000
Cr  1200x   Bank                   purpose BANK_{…}                                       200.000
```
The commission payable is **not touched**. Repayment is a settlement (§5.11–§5.13), each instalment applying against this open item.

### 5.10 Services on credit — the agent as customer (Rule 6)

A buys a ticket for himself: sell 150.000, cost 140.000. `agents.client_id` is set.
```
INV / AGENT_SALE             doc_date = invoice_date
Dr  135901  Agent Receivable (A)      purposeCode '' , accountId A.receivable_account_id
                                       reason_tag=service   ← OPEN ITEM                   150.000
Cr  4110    Flight Booking Revenue    purpose SERVICE_REVENUE, flight                     150.000
Dr  5110    Flights Cost              purpose SERVICE_COST, flight                        140.000
Cr  2120x   Suppliers (Flights) › {supplier}   purposeCode '' , accountId supplier pointer 140.000
```
The **only** difference from a normal sale is which leaf takes the debit. Revenue, cost, supplier payable, service type, tax: identical. Whether A also earns commission on their own purchase is a policy question the owner must answer — the mechanics are §5.2 unchanged, and the honest default is **no** (§10.15).

Credit control (Rule 7) checks A's own `credit_limit`, `credit_from_date` and `is_blacklisted` when `agent_credit_control_applies = on`.

### 5.11 Settlement by commission (offset, no cash)

A owes 5.000 (receivable), is owed 12.000 (payable). The company decides to set off.
```
AST / SETTLE_OFFSET          doc_date = settlement_date
Dr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id
                                            reason_tag=settlement                           5.000
Cr  135901  Agent Receivable (A)           purposeCode '' , accountId A.receivable_account_id
                                            reason_tag=settlement                           5.000
```
Plus **apply rows** against the specific open receivable items chosen (§6). Payable after: 7.000. Receivable after: 0.

This is today's `AgentSettlementService::settleByProfit()` pattern — and its direction is right. What changes: the debit hits the *commission* leaf, not the full-profit leaf; the document is minted by `SequenceService` on the new `AST` series rather than `generateSettlementNumber()`'s private `STL-{year}-000001` counter; the balance check reads the ledger through §6.5's predicate rather than `JournalEntry::where('account_id',…)->sum('credit') - ->sum('debit')` (which is company-unscoped and ignores `posting_status`); and apply rows are written so the receivable can still be aged. `[rev 5, 2026-08-27 — the `AST/{branch}/{yy}/{seq}` schema this series needs is seeded early, at W5.S, not held for P5.13: `AgentSettlementService` is cut over to it as a temporary `sub_type = LEGACY` document during the RV/PV engine-cutover wave, so this section's build inherits an already-seeded series; see doc 22 §11.2]`

### 5.12 Settlement by salary deduction `[R2 — restructured; the revision-1 pattern broke the payroll gate]`

Revision 1 posted `Dr 2201 Salaries & Wages Payable / Cr 135901 Agent Receivable (A)` on a single **`HRJV`**, and §7.1 filtered `HRJV` off the *payable* ledger only. Both halves were wrong:

- No `HRJV` ever posts to `223001`, so the filter on the payable ledger was a **no-op**.
- The receivable query had **no** filter — so a salary deduction on an `HRJV` either (i) shows on the statement, leaking the fact and amount of a payroll deduction to anyone who can read it, or (ii) is filtered out, and then `BF + visible movement ≠ CF`, breaking §7.2's *"every row is drillable to its document"* and invariant A7.

**Resolution — two documents and a clearing account.**

```
AST / SETTLE_SALARY          doc_date = settlement_date       [visible on the agent statement]
Dr  2202    Payroll Deduction Clearing   purpose PAYROLL_DEDUCTION_CLEARING  reason_tag=settlement  5.000
Cr  135901  Agent Receivable (A)         purposeCode '' , accountId A.receivable_account_id
                                          reason_tag=settlement                                     5.000

HRJV / PAYROLL_DEDUCT        doc_date = settlement_date       [permission-gated, NOT on the statement]
Dr  2201    Salaries & Wages Payable     purpose SALARY_PAYABLE                                     5.000
Cr  2202    Payroll Deduction Clearing   purpose PAYROLL_DEDUCTION_CLEARING                         5.000
```

The agent statement is **complete** (the `AST` is a visible row with a document to drill to), the **salary amount stays hidden** (the `HRJV` never appears), and `2202` nets to zero per settlement — a testable invariant of its own.

> **INVARIANT `[R2]`: no `HRJV` document may touch `135901…` or `223001…`.**
> Test: `PayrollGateTest::no_hrjv_line_targets_an_agent_party_leaf`. This is what lets §7.1 drop the `excludeDocTypes(['HRJV'])` filter from both ledgers entirely — the gate is structural, not a query predicate that has to be remembered in every report.

> **The deduction must not exceed accrued unpaid salary `[R2]`.** `2201` is a **pooled** leaf (D2). Taking a deduction against a salary not yet accrued drives a pooled multi-employee liability into a debit balance with no per-agent detail to attribute it to, and A2's never-negative rule covers only `223001`.
>
> **Mechanism, without breaking D2's pooling:** `PayrollAccrualService::accruedUnpaidFor(Agent $a, Carbon $asOf): float` derives the figure from the `HRJV` documents themselves — Σ credits to `2201` on lines carrying `cost_center = CC(A)`, less Σ debits to `2201` on the same dimension (payouts and prior deductions). No per-agent sub-account, no second source of truth; the pooled leaf keeps its pooled balance and the per-agent figure is a dimension query, exactly as Rule 1b treats profit.
> **A2b:** a salary-deduction settlement may not exceed `accruedUnpaidFor(agent, doc_date)`. Test: `SettlementTest::salary_deduction_exceeding_accrued_unpaid_salary_is_refused`.

Requires an explicit, recorded approval (Rule 3b), because an undocumented payroll deduction is a legal exposure.

### 5.13 Settlement by cash, and by payment link

**Cash:**
```
AST / SETTLE_CASH
Dr  1100/1110  Cash In Hand / Petty Cash   purpose CASH_{…}                                  5.000
Cr  135901     Agent Receivable (A)        purposeCode '' , accountId A.receivable_account_id
                                            reason_tag=settlement                            5.000
```

**Payment link (gateway), 5.000 collected, real fee 0.125, O13 = `company`:**
```
AST / SETTLE_GATEWAY
Dr  1300x  KNET clearing        purpose GATEWAY_CLEARING_KNET                                4.875
Dr  5144   KNET Charges         purpose GATEWAY_FEE_EXPENSE_KNET   reason_tag=fee            0.125
Cr  135901 Agent Receivable (A) purposeCode '' , accountId A.receivable_account_id
                                 reason_tag=settlement                                       5.000
```
This is structurally today's `AgentSettlementService::onPaymentCompleted()` — three lines, `Cr` the agent for the **gross**, `Dr` clearing net, `Dr` fee — and it is correct. What changes: the accounts are resolved by purpose code and party pointer instead of `Charge::where('name', 'LIKE', "%{$gatewayName}%")` and `Account::find($chargeRecord->acc_fee_bank_id)`; `actual_balance` is not hand-incremented by the service; and the fee bearer is a policy (O13) instead of an unstated company subsidy.

If O13 = `agent`/`split`, add the §5.3(c)/(d) `DBN/AGENT_CHARGE` for the agent's share.

**Commission payout** is the mirror: `AST / COMM_PAYOUT`, `Dr 223001 / Cr bank`, with apply rows against the specific commission accrual lines being discharged (§6.4b — this is what makes §5.5(c)'s per-obligation test answerable).

**Overflow to receivable during settlement** cannot happen — a settlement only reduces balances. Overflow is a *charge*-time concept (§5.3, §5.7).

**`[R3 — NEW invariant]` Cash/bank reports, the Day Book, and receipts reports select by cash/bank LINE MOVEMENT, never by `doc_type`.** An `AST` settlement that moves cash or bank (§5.13's `SETTLE_CASH`/`SETTLE_GATEWAY`) — and, for the same reason, a `PV/SPOT_SHARE` or `PV/SPOT_ADVANCE` (§5.19) — must appear on those reports exactly like any other cash/bank movement: by filtering `journal_entries` on the cash/bank account, never by whitelisting `doc_type IN ('RV','PV',…)`. A report that special-cases `doc_type` silently drops every cash-moving `AST` and every spot-commission `PV` the day a new one is invented. Invariant **A21**; tests `DayBookTest`, `CashReportTest`.

### 5.14 Incentive accrual (context for ACM)

```
JV / INCENTIVE_ACCR          doc_date = period end
Dr  135800  Airline Incentive Receivable   purpose AIRLINE_INCENTIVE_RECEIVABLE              3.000
Cr  4160    Airline Memos & Incentives     purpose ACM_INCOME                                3.000
```
Cleared by §5.8's ACM disposition. Blueprint 04 §6: *"Incentive is recognized as additional income; many agencies accrue it and clear it against the BSP ACM."* Ships in **P7.5**.

### 5.15 Void after settlement

A ticket is voided after A's commission on it was already settled or paid out.

1. The **sale** is voided by the W4 void flow (21 §5a: *"Void flow fix — select by `task_id`, post via `PostingService::reverse`, block when reconciled"*).
2. The **commission** is handled by §5.5(c) under O3b.

> **The void flow must NOT select commission documents `[R2]`.** The void flow selects by `task_id`, and the `JV / AGENT_COMMISSION` document carries `task_id` on its lines (§5.2). Without an exclusion it would be **reversed by the void flow AND clawed back again by §5.5(c)** — the commission removed twice.
>
> **Rule:** the void selection excludes `doc_type = 'JV'` with `sub_type IN ('AGENT_COMMISSION','POOL_ACCRUAL','COMM_ON_COLLECT')`, and excludes every `AST` and `DBN/AGENT_CHARGE`. Agent-side effects of a void are produced **only** by the O3/O3b documents, never by the reversal sweep. Test: `VoidFlowTest::voiding_a_ticket_does_not_reverse_the_commission_accrual`.

**Hard rules:** the settlement document is never reversed, never deleted, and its apply rows are never released. Blueprint 04 §8 forbids the analogous shortcut on the sale side — *"a settled/reconciled ticket can't be silently voided"* — and the same logic applies to a settled commission: money already moved, so only a new document can move it back.

### 5.16 Year-end carry-forward

Blueprint 07 §8: *"Carry **balance-sheet** accounts forward as 'Balance B/F' lines — excluding income and …"*

- `223001` (liability) and `135901` (asset) are balance-sheet leaves → **carried forward** as `Balance B/F` lines in the locked `OJV` (11 §P5.2).
- `4131`, `4134`, `4135`, `4160`, `4210`, `5124`, `5125`, `5126`, `5130`, `5141–5147`, `5160`, `5211`, `5218`, `5221` are P&L → swept to `3400 Retained Earnings`; they start the new year at zero. `[R3, fix round 2 — added `5211 Sales Incentive Expense` (S8) and `4134`/`4135` Cancellation/Change Fee Income (S6), the three P&L leaves revision 3 created that this list had omitted]`
- `1952 Airline Memo Control` and `2202 Payroll Deduction Clearing` **must be zero at year end**. A non-zero `1952` means undispositioned memos; a non-zero `2202` means a half-posted salary deduction. The close **refuses**, or forces disposition first. Both are on the year-end pre-close checklist.
- **Open items survive the boundary.** `settled_amount` and apply rows are not reset by the close, and ageing continues to measure from the **original document date**, not from 1 January. This mirrors blueprint 03 §8's *"excluding opening journals"* for credit control, and prevents a 400-day-overdue balance from resetting to "current" every January.
- The **two-query rule** in §6.6 is what makes the previous two bullets consistent — read it before implementing either.

### 5.17 Agent leaving with a balance

**Receivable (they owe us).** It remains an asset and keeps ageing. Three dispositions, in order of preference:
1. Deduct from the final settlement — §5.11 (against remaining commission) or §5.12 (against final salary), each explicit and approved.
2. Collect — §5.13.
3. Write off, **permission-gated**, with a mandatory reason:
```
JV / AGENT_WRITEOFF
Dr  5218    Write Off              purpose BAD_DEBT_EXPENSE   reason_tag=writeoff             1.250
Cr  135901  Agent Receivable (A)   purposeCode '' , accountId A.receivable_account_id
                                    reason_tag=writeoff                                       1.250
```
`5218 Write Off` **already exists** in `CoaSeeder` under `Indirect Expenses (Operating Expenses)` and is postable today `[R2 — revision 1 invented a `5xxx Bad Debt Expense` leaf and deferred it to P7, then scheduled §5.17 inside P5.13; the dependency was circular and is dropped]`.

**Payable (we owe them).** Pay it out (`AST / COMM_PAYOUT`). If it becomes unclaimable after the statutory period, write it back — permission-gated:
```
JV / AGENT_WRITEBACK
Dr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id
                                            reason_tag=writeoff                               0.750
Cr  4210    Unclaimed Balances Written Back   purpose UNCLAIMED_LIABILITY_INCOME               0.750
```
`[R2 — revision 1 credited `4200 Indirect Income` **by code, with no purpose code**, violating §3.1's own rule that every shared leaf resolves through the registry. `4200` is also a group node in `CoaSeeder` with no seeded children, so the posting would have been refused by `AccountResolver` anyway. `4210` is a new leaf with a registered purpose code.]`

**Freezing the leaves.** Only when both balances are **zero**: `disabled = 1` and ` (CLOSED)` appended (blueprint 01 §6 rule 9). Freezing a leaf with a live balance must be refused — a frozen non-zero leaf is a balance nobody can ever clear. `disabled` is enforced at posting time (P1 pipeline step 3e), so this is a real freeze, not the dead flag noted in the COA audit.

The agent row itself is **never deleted** while a leaf points at it (`restrictOnDelete`). Note that today `AgentController` deletes the agent *and its user* in the `catch` of its account-creation block — an exception path that removes a party which may already have journal lines. §10.9.

### 5.18 Currency and FX `[R2 — new section; revision 1 had no FX treatment anywhere]`

Blueprint golden rule 3 requires **both** currencies on every line. Revision 1 hard-coded `currency = 'KWD', exchange_rate = 1.0` on commission lines derived from tasks that carry `$task->currency` / `$task->exchange_rate` (which `createProfitEntries()` currently propagates), defined `outstanding` on base `debit` only while `autoAllocate` was *"in the document's currency"*, allowed a foreign-currency sale onto `135901` (§5.10), and never revalued the open agent receivable.

**Decisions:**

| Question | Decision | Reason |
|---|---|---|
| **The commission contract's currency** | **Base currency (KWD).** `AgentEarningsService` computes on base-currency margin; the commission line is `currency = KWD, exchange_rate = 1.0, original_amount = amount` | The contract is with a Kuwaiti employee, paid in KWD. Making commission float with the ticket's currency means the agent's earnings change after the sale, with no event |
| **The task's own currency** | Preserved on **every other line** of the document (`currency`, `exchange_rate`, `original_amount` from `$task`) — sale, cost, fee, memo | Golden rule 3; it is also what `createProfitEntries()` already propagates and what the FC ledger view (05-21) reads |
| **Charge documents (§5.3–§5.7)** | The **charge** is a base-currency amount (it derives from a base-currency fee or margin), so the `DBN` is base-currency throughout | Keeps the payable leaf single-currency, which is what makes A2's never-negative guard a simple comparison |
| **`135901` Agent Receivable** | **May hold foreign-currency open items** (a Rule 6 sale in USD; a loan in USD). Each open item keeps its own `currency` / `original_amount` | A debt in USD is a USD debt |
| **Ageing currency** | Buckets are reported in **base currency**, converted at the **document's own rate** (not today's) — the same convention 11 §P5.4 uses for the AR/AP ageing report | Ageing measures exposure at recognition; revaluation is a separate, dated event |
| **Allocation** | `OpenItemService::apply()` matches **same account, same currency**. A base-currency receipt against a USD open item is a two-step: revalue, then apply | Blueprint 07 §7's *"in the document's currency"*; mixing currencies inside one apply row makes the residual unattributable |
| **Period-end revaluation** | The open agent receivable **is in scope** for blueprint 07 §4's FX revaluation, on the same run as clients and suppliers. The commission payable is **out of scope** — it is base-currency by construction | An unrevalued foreign receivable misstates assets; a base-currency payable has nothing to revalue |

Tests: `AgentFxTest::commission_is_always_base_currency`; `…::a_usd_open_item_ages_at_its_own_document_rate`; `…::period_end_revaluation_includes_the_agent_receivable_and_excludes_the_commission_payable`.

### 5.19 Spot commission `[R3 — NEW; S8, LOCKED by the owner 2026-08-27]`

Scenario (the owner's own example): agent A sells a package, sell 1,500.000, cost 1,000.000 → gross margin **500.000** (Rule 1e base). `spot_share_pct = 10`, and the company wants the spot share to **fall into company expenses on the day of the sale**, while the **period** commission still computes later on the FULL 500.000 (not 450.000) — the two are independent costs, never one netted against the other.

**(a) `spot_treatment = incentive` (DEFAULT, O16).** The spot share is company **expense**, same day:

```
PV / SPOT_SHARE               doc_date = sale date
Dr  5211  Sales Incentive Expense   purpose SALES_INCENTIVE_EXPENSE  cost_center = CC(A)    50.000
Cr  1100/1110  Cash In Hand / Petty Cash   purpose CASH_{…}                                  50.000
```

Later, at period end, the **full** commission posts through §5.2's Type 2 pattern **unchanged**, on the full 500.000 base (say a 20% contractual rate → 100.000):

```
JV / AGENT_COMMISSION
Dr  5130    Commissions Expense (Agents)   purposeCode 'COMMISSION_EXPENSE'                  100.000
Cr  223001  Agent Commission Payable (A)   purposeCode '' , accountId A.commission_account_id 100.000
```

Company cost for the case = 50.000 (spot) + 100.000 (period) = **150.000** — both real, neither a deduction of the other. This is what makes `incentive` mode not double-count: the spot payment is an **expense**, not an advance against the period commission.

**(b) `spot_treatment = advance` (O16, off by default).** No P&L at payment time — the spot share is a genuine asset until matched:

```
PV / SPOT_ADVANCE             doc_date = sale date
Dr  135901  Agent Receivable (A)   purposeCode '' , accountId A.receivable_account_id
                                    reason_tag=advance   ← OPEN ITEM                          50.000
Cr  1100/1110  Cash In Hand / Petty Cash                                                      50.000
```

The period commission still posts in full (§5.2, as in (a)). The advance is **not** netted automatically against it (Rule 3b) — it is settled like a loan (§5.11–§5.13), tagged `advance` rather than `loan` so the statement can tell the two apart, at the operator's next chosen settlement.

**(c) True-up (`spot_true_up = on`, O19).** The case is later adjusted (an ADM, a void, a partial refund) and the profit the spot share was computed on drops — say the recomputed base falls to 400.000, so the 10% spot share should have been 40.000, not 50.000. There is **no payable balance to reduce here** (unlike §5.3/§5.4's charges): the 50.000 already left as cash under `incentive` mode, so the correction debits the receivable directly, exactly as the locked design specifies:

```
JV / SPOT_TRUEUP              doc_date = adjustment date
Dr  135901  Agent Receivable (A)   purposeCode '' , accountId A.receivable_account_id
                                    reason_tag=loss   ← OPEN ITEM                             10.000
Cr  5211    Sales Incentive Expense   purpose SALES_INCENTIVE_EXPENSE                         10.000
```

`reason_tag = loss` because this is an earnings-derived clawback, the same family as §5.5(c)'s un-earning — the agent's own case profit changed, not a loan or a service. `spot_true_up = off` skips this document entirely: the spot payment stands regardless of later adjustment (O19).

**Guards.** `spot_requires_client_paid` (O18) gates the "Pay spot share" action, not the posting shape: `warn` (default) shows a warning when `$invoice->invoicePartials` shows nothing collected yet; `block` refuses the action outright; `off` never checks. `spot_approval = supervisor` (O20) requires sign-off before either `PV` posts — no posting difference once approved. `spot_profit_basis` (O17) decides whether the 10% multiplies gross margin (`gross`) or gross margin net of the agent's already-charged real gateway fee (`after_gateway_fee`); either way the **period** commission in §5.2 is unaffected, because Rule 1e's base is always gross margin regardless of the spot basis chosen.

**Never both same-day expense AND a later deduction for the same money** — that would double-count. `incentive` shows cash-out on the Day Book, not a deduction from anything; `advance` shows no P&L until settled. Test: `SpotCommissionTest::incentive_and_advance_modes_never_double_count_the_same_case`.

---

## 6. Open items & ageing on Agent Receivable

### 6.1 State

Per 11 §P5.3: *"add `journal_entries.settled_amount decimal(18,3) default 0`, maintained **only** by an apply/release service with a never-negative guard; derive `reconciled` from `settled_amount == amount`."*

An **open item** on `135901` is any `journal_entries` row where `account_id = 135901` and `debit > 0`.
`outstanding = debit − settled_amount`. Fully settled when it reaches 0.

Blueprint 03 §7: *"**Outstanding = original − applied** … When it reaches zero the item is fully settled."*

### 6.2 Apply / release

`App\Services\Accounting\OpenItemService` (P5.3) is the **only** writer of `settled_amount`:

- **`apply(sourceLine, targetLine, amount)`** — writes a `payment_applications`-style registry row (`source_journal_entry_id`, `target_journal_entry_id`, `account_id`, `amount`, `source_dc`) and increments both lines' `settled_amount`. Guards: `amount > 0`; `amount ≤ target.outstanding`; `amount ≤ source.outstanding`; same `account_id`; **same `currency`** (§5.18); same `company_id`; both lines `posting_status = 'posted'`.
  `[unverified: `payment_applications`' exact columns were not read this pass — the registry above is a shape, and P5.3 must reconcile it with the existing table (`2026_01_12_154855_create_payment_applications_table`, plus `credit_id` and soft-deletes migrations) rather than create a parallel one.]`
- **`release(applicationId)`** — decrements both, deletes the registry row, **never below zero**. Blueprint 07 §7: *"**Release** — un-apply; reverse the `*Adj` and delete the rows (guarded so balances never go negative)."*
- **`autoAllocate(sourceLine)`** — **oldest open item first (FIFO)**, in the document's currency (blueprint 07 §7). Offered, never automatic (Rule 3b): the settlement UI proposes the FIFO allocation and the operator confirms.

**Never-negative is enforced at three levels:** a service guard, a DB `CHECK (settled_amount >= 0 AND settled_amount <= debit + credit)` where the platform supports it, and an invariant test.

### 6.3 Ageing

Blueprint 03 §7: *"for each open line, `outstanding` bucketed by `DATEDIFF(day, DocDt, asOf)` into 30/60/90/120-day bands."*

Buckets: `current (0–30) | 31–60 | 61–90 | 91–120 | 120+`, measured from `journal_entries.transaction_date` — **the one period column** (`DocumentDraft::$docDate` is documented as *"this IS transaction_date — the ONE period column (BUG-C4)"*), never `created_at`.

Excluded from ageing: any line whose `outstanding` is 0, and the `OJV` `Balance B/F` line — **see §6.6, which is the rule that makes this consistent with the ledger pane.**

Grouped by `reason_tag`, so the statement can say *"of the 8.600 outstanding, 5.000 is a loan (61–90 days) and 3.600 is fee shares (current)"* — which is the difference between an actionable statement and a number.

### 6.4 The payable side

`223001` is **not** run as an ageing open-item ledger. It is a rolling accrual balance: commission credits in, charges and settlements out. There is nothing to age — the company is not overdue to itself, and payout timing is a payroll cycle, not an invoice due date. The statement shows it as a running ledger with a brought-forward row.

*(If the owner later wants "commission earned in month M must be paid by month M+1", that is a due-date report over the accrual documents, not an open-item engine. Do not build one speculatively.)*

### 6.4b Matching on the payable side `[R2 — new; §5.5(c) requires it]`

Revision 1's §6.4 (*"not open-item"*) and §5.15's *"the payable is zero"* were mutually inconsistent, and §5.5's aggregate-balance test produced the unrecorded set-off Rule 3b forbids.

**Resolution, and its limits.** `OpenItemService` maintains `settled_amount` on the **credit** lines of commission accrual documents (`JV/AGENT_COMMISSION`, `JV/POOL_ACCRUAL`, `JV/COMM_ON_COLLECT`), and every `AST/COMM_PAYOUT` and `AST/SETTLE_OFFSET` writes apply rows against the specific accrual lines it discharges. That is **matching**, not ageing:

| | Receivable `135901` | Payable `223001` |
|---|---|---|
| `settled_amount` maintained | yes | yes (accrual credit lines only) |
| Apply rows | yes | yes |
| **Ageing buckets** | **yes** | **no** — §6.4's reasoning is unchanged |
| Statement pane | ledger + ageing | ledger only |
| FIFO auto-allocate offered | yes | yes (oldest unpaid commission first) |

The only new capability is the ability to answer *"has **this** commission been discharged?"* — which §5.5(c) requires and which no aggregate balance can answer.

### 6.5 Concurrency and the never-negative guard `[R2 — new section; revision 1 specified no locking]`

A2 (*"the payable leaf is never driven below zero by a charge document"*) requires reading `223001`'s balance at posting time. §6.4 says the payable is a live aggregate, not an open-item balance — so the read is a `SUM`, and revision 1 never said how it is made consistent. Two simultaneous charges each reading a 1.500 balance both debit it: negative balance, A2 violated, no error raised. This repo's `concurrency-idempotency-audit.md` already documents the pattern.

**The balance predicate, stated exactly:**

```sql
SELECT COALESCE(SUM(je.credit) - SUM(je.debit), 0)
FROM journal_entries je
JOIN transactions t ON t.id = je.transaction_id
WHERE je.account_id  = :agent_commission_leaf_id
  AND je.company_id  = :company_id            -- the scope AgentSettlementService omits today
  AND je.deleted_at IS NULL
  AND t.posting_status = 'posted'             -- the filter AgentSettlementService omits today
  AND t.deleted_at  IS NULL
```

**Serialization.** Inside the posting transaction, and **before** the balance read:

1. `SELECT … FROM accounts WHERE id = :agent_commission_leaf_id FOR UPDATE` — `lockForUpdate()` on the **leaf row**, which is the per-agent serialization point. Two charge documents for the same agent serialize; charges for different agents do not contend.
2. Read the balance with the predicate above.
3. Compute the payable/receivable split.
4. Post through `PostingService` in the same transaction.

`PostingService` already takes `DB::transaction()` on `post()`/`reverse()`/`repost()` and already locks explicit-`accountId` lines individually in draft-line order at resolve time (its own docblock records both). **The agent leaf lock must be taken in the same order** — leaf id ascending — to avoid a deadlock against that ordering when a document touches two agents' leaves.

Tests: `AgentChargeConcurrencyTest::two_simultaneous_charges_cannot_drive_the_payable_negative`; `…::the_balance_read_ignores_draft_and_other_company_rows`.

### 6.6 The two-query rule: ledger vs ageing `[R2 — new section; revision 1 contradicted itself]`

Revision 1's §7.2 worked statement showed receivable `Brought forward 1,000`, `Carried forward 8,600`, and `Ageing 0-30 3,600 · 61-90 5,000` = **8,600** — i.e. the ageing **included** the brought-forward amount. §5.16 said *"The `Balance B/F` line is therefore **excluded from ageing**"* and A6 said buckets *"exclude `Balance B/F` lines"*. Both cannot hold.

Underneath sat a real, unstated design question: if the `OJV` posts a `Balance B/F` line to `135901` **and** the pre-year-end open items stay open (they must, or ageing restarts every January), the account carries both and the ledger double-counts — unless the period query excludes pre-`OJV` movement, in which case the open items are invisible to the ledger the statement reads.

**The rule, stated once:**

| Pane | Reads | Period filter | `OJV` `Balance B/F` |
|---|---|---|---|
| **Ledger** (both sides of §7.2) | `journal_entries` for the account | yes — `[from, to]` | **included**, as the opening row. Pre-`OJV` movement is excluded by the period filter, so nothing double-counts |
| **Ageing** (receivable only) | **open items** on the account | **none** — all time | **excluded**; a `Balance B/F` line is a summary, not an open item, and its `outstanding` is not maintained |

Consequences:
- The ageing pane ignores both the period filter and the `OJV`, so a 400-day-old loan still ages from its own `PV` date across as many year-ends as it survives.
- **A6 restated:** *buckets sum to `Σ(debit − settled_amount)` over all open items on the account, and that total equals the ledger pane's carried-forward figure.* Both halves are testable, and §7.2's example is correct exactly as printed (1,000 B/F + 7,600 movement = 8,600 CF; ageing 3,600 + 5,000 = 8,600).
- The equality in A6 holds because every debit that is not an open item (there are none on `135901` — every debit there is an open item by §6.1) and every credit is a settlement that raised `settled_amount` by the same amount.
- The **year-end close must therefore reconcile the `OJV` `Balance B/F` figure against Σ open-item outstanding** before it locks, or the two panes disagree from 1 January. Added to §5.16's checklist.

Test: `AgentStatementTest::ageing_total_equals_carried_forward_across_a_year_end`.

---

## 7. Agent statement & reports

### 7.1 Query shape

Everything reads `App\Services\Accounting\LedgerReportQuery` (11 §P5.4), which *"owns period/branch/cost-centre/company filters, opening-balance computation (via P5.2), sign rules, and roll-up."* No screen queries `journal_entries` directly, and no screen re-implements an opening balance.

```php
// ILLUSTRATIVE SHAPE, NOT A QUOTED API. 11 §P5.4 describes LedgerReportQuery's
// responsibilities, not a fluent signature; the method names below are this
// document's shorthand for those responsibilities and must be reconciled with
// P5.4's actual interface when it lands.  [R2 — revision 1 presented this as fact]
$payable = LedgerReportQuery::forAccount($agent->commission_account_id)
    ->period($from, $to)
    ->withOpeningBalance()        // P5.2 OpeningBalanceService — the single source
    ->get();                      // no HRJV filter needed: §5.12's invariant makes it structural

$receivableLedger = LedgerReportQuery::forAccount($agent->receivable_account_id)
    ->period($from, $to)
    ->withOpeningBalance()
    ->get();

// SECOND query — no period filter, no OJV (§6.6)
$receivableAgeing = LedgerReportQuery::forAccount($agent->receivable_account_id)
    ->openItems()
    ->ageing([30, 60, 90, 120], asOf: $to)
    ->get();
```

**No `excludeDocTypes(['HRJV'])` anywhere `[R2]`.** Revision 1 put that filter on the payable ledger, where it was a no-op, and omitted it from the receivable ledger, where it was needed and would have broken the statement's arithmetic. §5.12's invariant — *no `HRJV` line targets an agent party leaf* — removes the need for a filter on either side, and does so structurally rather than by a predicate every future report has to remember.

### 7.2 Layout

```
AGENT STATEMENT — {Agent A}                          1 Aug 2026 → 31 Aug 2026

 COMMISSION PAYABLE (223001)          │  AGENT RECEIVABLE (135901)
 Brought forward           4,200.000  │  Brought forward            1,000.000
 …documents, date / no / reason_tag…  │  …documents, date / no / reason_tag…
 Carried forward           7,000.000  │  Carried forward            8,600.000
                                      │  Ageing  0-30 3,600 · 31-60 0 · 61-90 5,000 · 90+ 0
                                      │         (all-time open items, §6.6 — sums to 8,600 ✓)
 ─────────────────────────────────────┴──────────────────────────────────────
 NET POSITION                                    1,600.000  DUE TO COMPANY
```

The two ledgers are **separate to the last line**. The single net figure appears in the footer, labelled with its direction, and is a **presentation** subtraction — no account holds it (Rule 2).

Every row is drillable to its document, and every document is reproducible from the ledger. The ageing block is the **second** query of §6.6 and is deliberately not tied to the period.

### 7.3 What the other terminal's agent-profit report must change

This is a contract with the report side, stated so both terminals can build against it.

1. **`invoice_details.profit` is a NET figure and its documented meaning changes `[R2]`.** It currently holds `margin − agentFeeDeduction` (plus `accountingFeePerTask` when the client paid the fee) — **net of the agent's gateway-fee share**, not gross. Revision 1 called it *"GROSS case profit — `markup_price` less agent-borne deductions"*, which is both self-contradictory and wrong.
   Under Rule 1e the **commission base is gross margin**, which `invoice_details` does not store. So:
   - `invoice_details.profit` is renamed **in schema** to **`profit_net_of_agent_charges`** `[R3 — the orchestrator overrides revision 2's "documentation-only, not in schema" call: this is a real column rename, not a naming convention]`, scheduled at **P5.13** (§9) once a full consumer census is clean — the same discipline §8.4 gate 3 applies to name-based `Account` lookups applies here to every `invoice_details.profit` read/write site. Its migration comment is corrected in the same PR. Until P5.13 ships, the column keeps its current name; revision 2's documentation-only convention (call it `profit_net_of_agent_charges` in prose, unchanged in schema) remains the interim rule.
   - A new stored mirror **`invoice_details.gross_margin`** = `sell − cost` is added in **W3.B** (unchanged — additive, no consumer migration needed). It is what `AgentEarningsService` reads, and it is a **mirror**: the ledger remains the source (Rule 8).
   - Neither column is ever a liability, is what the agent is paid, or the origin of a reported balance.
2. **Commission comes from the ledger**, not from `invoice_details.commission`. The report's per-invoice commission column reads the `JV/AGENT_COMMISSION`, `JV/POOL_ACCRUAL` and `JV/COMM_ON_COLLECT` lines on `223001` filtered by `invoice_id`. `invoice_details.commission` becomes a mirror the report may show for reconciliation but never totals.
3. **Both terminals call `AgentEarningsService`** for any *forward-looking* figure (this month's projected commission, target progress). Neither re-implements the four type formulas. The two existing copies — `AgentController`'s and `ReportController`'s — are deleted.
4. **Type-4 per-invoice commission is explicitly provisional.** `ReportController`'s proportional allocation (`$ratePool * $share`) is a fine *display*; it must be labelled provisional and must never be summed as a posted figure. Only the month-end `JV/POOL_ACCRUAL` is real. (O7's posted provisional accrual is withdrawn — §4.1.)
5. **Loss columns read the ledger by `cost_center_id` and `reason_tag`, not by `account_id`.** Today `ProfileController` sums `JournalEntry::where('account_id', $agent->loss_account_id)->…->sum('debit')` — which after this spec would miss every charge absorbed by the *payable* leaf (Rule 3), i.e. most of them.
6. **Per-service breakdown** (the blueprint 07 §11 requirement) comes from `serviceType` on the line plus `cost_center_id` — one query, not one account per agent per service. **This is the deviation §1.3 records. AM-20.1 is ACCEPTED and satisfied by 22 §3 P5.13 (O16); no outstanding gate.**
7. **The agent dimension is `cost_center_id`, and there is no `agent_id` column `[R2]`.** Any report written against `journal_entries.agent_id` — and any belief that legacy rows carry it — is wrong: the column does not exist and every legacy write of it was silently dropped by mass assignment (§3.4). For historical rows, the only surviving attribution path is `journal_entries.invoice_detail_id → invoice_details → invoices.agent_id` (`invoices.agent_id` exists and is FK-constrained, per `2024_10_29_063642_create_invoices_table`).
8. **The four management report variants D9 names ship first**: by customer, by airline, by consultant, by service. "By consultant" is this document's `cost_center_id` dimension, so it is nearly free once §3.4 lands.

---
## 8. Migration & backfill of existing data

### 8.1 The problem, sized

Every `InvoiceController::createProfitEntries()` call has written, per invoice detail, a `$profit` credit to `agents.profit_account_id` **plus** a `$commission` credit to the pooled `Commissions (Agents)` (2210) leaf. Both are wrong: the first overstates the agent's liability by `profit − commission`; the second puts a per-agent obligation in a pooled account where it cannot be attributed.

**The historical rows are not uniformly shaped. Census first, assume nothing.** Live rewriters of the same mirrors and the same entries `[R2 — revision 1 named only the first two]`:

| Rewriter | What it touches |
|---|---|
| `app/Console/Commands/FixInvoiceCoa.php` | invoice COA entries |
| `app/Console/Commands/FixOldProfit.php` | `invoice_details.profit` |
| `app/Console/Commands/FixProfitAndCommission.php` | both mirrors |
| `app/Console/Commands/FixCreditInvoiceCOA.php` | credit-invoice COA entries |
| `InvoiceController::recalculateInvoiceCOA()` | rewrites entries in place |
| `TaskController::recalculateCommissionForTask()` | recomputes commission on a supplier-amount change |
| `ApplySupplierSurcharge::calculateCommission()` | recomputes commission |

(There are also `FixGatewayCharges`, `FixPaymentGatewayCOA`, `FixPaymentLinkCOA`, `FixCreditPaymentIds` in the same directory — 22 §3 P5.17 retires the whole family in one PR alongside the drift checker. §10.13's list is corrected to match.)

**Legacy transactions may be UNBALANCED `[R2 — new; the P3 census must expect this]`.**
`createProfitEntries()` guards each leg **independently**: `if (!$agentSalariesAccount) { return; }`, then `if ($agent->profit_account_id) { … }`, then inside `if ($commission > 0)` a separate `if ($commissionExpenseAccount) { … }` and `if ($commissionLiabilityAccount) { … }`. A tenant missing any one of those leaves therefore posted **half a pair**, leaving the document unbalanced — and the whole block is wrapped in `catch (\Exception $e) { Log::error(…) }`, so the failure is logged and the request continues. `createSupplierLossEntries()` has the same shape (a missing `Loss Recovery Income` leaf → orphan debit), and `createFeeLossEntries()` compounds it by dereferencing `$feeLossProvisionAccount` inside the `if ($companyLossAccount)` branch with no null check of its own (§10.6).

Consequences for P3:
- The census must **count unbalanced transactions**, not assume balance. `findUnbalancedTransactions` already exists (22 §3 P5.17).
- An unbalanced legacy transaction cannot be corrected by a paired correction document; it needs a **balancing document to a suspense/quarantine leaf** first, reviewed by an accountant, then corrected. It goes to P3.b(iv)'s quarantine, never to P3.c.
- The size of the damage per tenant is **unknown until the census runs**: the name lookups (`Account::where('name', 'Agent Salaries')`, `'Loss Recovery Income'`, `'Fee Loss Provision'`, `'Company Loss on Sales'`) return `null` and skip silently, so whether a tenant has those leaves at all is a data question. `[unverified]`

### 8.2 P3 repair (the correction pass) `[R2 — the arithmetic was wrong and is rebuilt]`

Runs after the W3/W4 cutover and the DEV deploy, in the P3 repair slot (21 §5b).

**Why revision 1's three-stage arithmetic did not work.** Take its own numbers (profit 10.000, commission 2.000). Legacy state: `223001` Cr 10, `2210` Cr 2, `5160` Dr 10, `5130` Dr 2. Target: `223001` Cr 2, `2210` 0, `5160` 0, `5130` Dr 2.

| Stage as written | Effect |
|---|---|
| P3.c `Dr 223001 8 / Cr 5130 8` | `5130` = −6, `223001` = 2 |
| P3.d `Dr 5130 2 / Cr 5160 2` | `5130` = −4, `5160` = 8 |
| P3.e `Dr 2210 2 / Cr 223001 2` | `223001` = **4** |

Final: `223001` = 4 (want 2), `5130` = **−4, a credit balance in an expense account** (want 2), `5160` = 8 (want 0). Three of four accounts wrong — and P3.f(3)'s *"trial balance total unchanged"* check **passes anyway**, because each document is internally balanced. The check could never have caught it.

**The correction: ONE balanced document per agent per period.** `5130` is already correct and is **not touched**. P3.d and P3.e collapse into this entry.

```
JV / LIAB_CORRECTION         (open-year portion)
Dr  223001  Agent Commission Payable (A)   (profit − commission)                   8.000
Dr  2210    Commissions (Agents)           (commission, drains the pool)           2.000
Cr  5160    Agent Salaries                 (profit, reverses the wrong expense)   10.000
```

Deltas: `223001` −8 → Cr 2 ✓ · `2210` −2 → 0 ✓ · `5160` −10 → 0 ✓ · `5130` unchanged at Dr 2 ✓. Balanced: 8 + 2 = 10.

**Closed years post to Retained Earnings, not to `5160` `[R2]`.** For any portion originating in a **closed** financial year, the year-end `OJV` has already swept `5160` and `5130` into `3400 Retained Earnings` (blueprint 07 §8; §5.16). Crediting `5160` in the current period would book a prior-year error as **current-year profit** — an IAS 8 prior-period error presented as current trading, and the first finding an external auditor raises. For an agency being prepared for audit and XBRL that is not acceptable.

**`[R3]` The correction pass MUST emit a written IAS 8 restatement note**, not just post the closed-year JV silently: one line per affected financial year, naming the year and the total corrected amount for that year. This is the artefact an external auditor asks for first, generated from the same P3.a/P3.c data (never re-derived) — see the new **P3.g** stage below and invariant **A22**.

```
JV / LIAB_CORRECTION         (closed-year portion — a RESTATEMENT)
Dr  223001  Agent Commission Payable (A)   (profit − commission)                   8.000
Dr  2210    Commissions (Agents)           (commission)                            2.000
Cr  3400    Retained Earnings                                                     10.000
```

| Stage | Action |
|---|---|
| **P3.a — census** | Per agent, per period: `Σ credits on the 2230 leaf`; `Σ credits on 2210 attributable to that agent`; and the recomputed `AgentEarningsService` figure for the same period. Produce a three-column reconciliation. Read-only, on a staging clone first (same discipline as `data-integrity-audit.md` §3). **Attribution path `[R2]`:** `journal_entries.agent_id` **does not exist** (§3.4), so 2210 lines carry no agent. The only surviving link is `journal_entries.invoice_detail_id → invoice_details → invoices.agent_id` (`createProfitEntries()` does set `invoice_detail_id`, which **is** fillable). State plainly: **`invoice_details.commission` — the mirror Rule 8 distrusts — becomes the sole reconciliation anchor for the historical 2210 balance.** Any 2210 line with no `invoice_detail_id` is unattributable by construction |
| **P3.b — classify** | (i) profit-credit with a matching commission credit — the standard double-count; (ii) profit-credit with **no** commission credit; (iii) already settled/paid — the liability was *discharged*, so the correction must not create a phantom receivable; (iv) **unattributable or unbalanced** — quarantine for accountant review, never guess (§8.1) |
| **P3.b type-4 correction `[R2]`** | Revision 1 lumped **types 1 and 4** into (ii) as *"a pure overstatement"*. That is right for type 1 and **wrong for type 4**: `$commission` was 0 per invoice, but the contractual earning is the **month-end pool**, not zero. Treating it as pure overstatement erases every type-4 agent's historical commission liability. For type-4 agents, P3.c posts the **recomputed pool** (`AgentEarningsService` over each historical month) as the target `223001` balance, and the correction is `profitCredits − recomputedPool` |
| **P3.c — correct** | One `JV / LIAB_CORRECTION` per agent per period, **through `PostingService`**, never an `UPDATE`, using the open-year or closed-year shape above according to the **origin year of the corrected entry**. `DocumentDraft::$allowLockedPeriods` exists for a deliberate back-dated run (its own docblock and 13 Stage D) and is used only with `--allow-locked-periods` and explicit owner authorisation |
| **P3.d — freeze `2210`** `[R2 — no longer a separate reclassification document]` | Once every attributable balance has been drained by P3.c's `Dr 2210` lines and any quarantine is resolved: `disabled = 1` on 2210 with ` (CLOSED)` appended. Same "transfer document + freeze" mechanism as §2.4 M4 and 13 §B.3. Also freeze `4170` and `5221` (§2.2, §10.5) |
| **P3.e — verify** | (1) **`Σ credits on 2230›{agent}` == `Σ AgentEarningsService(commissionComponent)` over the same periods**, ± rounding tolerance. (2) **`closing balance on 2230›{agent}` == `Σ earnings − Σ charges − Σ settlements`**, ± tolerance. (3) `2210` balance = 0. (4) Trial balance total unchanged by every correction document. (5) No agent leaf has a negative balance. (6) **No correction document credits a P&L account with a `doc_date` in a year later than the year the corrected entry belongs to.** (7) Snapshot diff against the P3.a baseline — 13 Stage E's *"prove no money was lost, duplicated, or moved between subtrees"* |
| **P3.f — rollback** | Every correction document carries the batch id in its `idempotency_key` **and** `sub_type = LIAB_CORRECTION`, so the whole pass is reversible by `PostingService::reverse` over that batch. (`sub_type` is 15 characters — within `string(16)`, unlike revision 1's 26-character value, which would have truncated and made this rollback select the wrong rows.) |
| **P3.g — IAS 8 disclosure** `[R3 — NEW]` | Before the batch is signed off, generate the **IAS 8 restatement note** from the P3.a/P3.c data: one line per **closed** financial year touched, naming the year and the total corrected amount for that year (open-year portions are current-period corrections, not a restatement, and are excluded). The type-4 carve-out from P3.b is preserved through this step — a type-4 agent's closed-year line states the recomputed-pool correction, not a "pure overstatement" figure. Attached to the batch record alongside the P3.f rollback key |

> **On dating `[R2]`.** Revision 1 said P3.c is *"dated in the current open period, not back-dated"* and P3.d is *"one reclassification `JV` per period"* — a per-period document dated in the current period is one document, not many. Resolved: **one document per agent per origin period**, each dated in the **current open period** for open-year portions, and each **carrying its origin period in the description and in a `correction_of_period` field on the header** so the audit trail says which month it repairs. Closed-year portions use the Retained Earnings shape above and are likewise dated in the current period — a restatement is *recognised* today, it does not reopen last year.

**Non-negotiable:** no `UPDATE` and no `DELETE` on `journal_entries`. Every correction is a new, balanced, dated, numbered document. That is what makes it auditable and what makes P3.f possible.

**Invariants replacing revision 1's A3 `[R2]`.** Revision 1's A3 — *"`Σ 2230 subtree` per agent == `Σ AgentEarningsService(closed periods)` ± tolerance"* — is arithmetically false the moment Rule 3 or a settlement runs, because `223001` is debited by §5.3 fee shares, §5.4 negative margin, §5.5 clawbacks, §5.7 ADM shares and §5.11–§5.13 settlements. As stated it would fail for every active agent, and the test would be weakened or deleted rather than trusted. It becomes two invariants, both true and both testable — see P3.e(1) and P3.e(2), and Appendix A A3a/A3b.

### 8.3 `agent_loss` / `agent_charge` → `agent_charge_policies`

Two tables exist today with the **same three columns and the same three bearer constants** (`AgentLoss` and `AgentCharge`, `loss_bearer`/`charge_bearer` + `agent_percentage` + `company_percentage`, both `unique(agent_id, company_id)`, both with a `getForAgent()` returning a company-bears default when no row exists). One is read for supplier losses, the other for fee losses. Note the type mismatch (`agent_loss.loss_bearer` is an `enum('company','agent','split')`; `agent_charge.charge_bearer` is a plain `string`) and that **both default `agent_percentage` to `0`, not 50** — the backfill carries the stored value.

Backfill:

| Source | → `charge_kind` | Notes |
|---|---|---|
| `agent_loss` row | **`negative_margin` only** `[R2]` | 22 §5.3 is explicit: *"Migrate `agent_loss` rows to `charge_kind = negative_margin` and `agent_charge` rows to `charge_kind = gateway_fee`, preserving each agent's current effective behaviour exactly."* Revision 1 wrote **one source row into two kinds** (`negative_margin` **and** `refund_clawback`), which silently opts an agent who agreed to share *supplier losses* into sharing *airline refund clawbacks* — a different economic agreement, and one revision 1 flagged for owner sign-off in the ADM row but not here |
| `agent_charge` row | `gateway_fee` | `charge_bearer` → `bearer`, `agent_percentage` carried verbatim |
| *(nothing)* | **`refund_clawback`** `[R2]` | No faithful historical equivalent → seeded at the hard default `bearer = company`, `agent_percentage = 0`. **The owner must be told** (owner question Q-20.2): agents who informally absorbed refund clawbacks will show `company` until someone sets it |
| *(nothing)* | `adm` | Same: seeded at `company` / 0. **The owner must be told** (Q-20.3) |
| `invoices.agent_loss` / `invoices.company_loss` (per-invoice override percentages, migration `2026_03_01_000000_add_loss_bearer_override_to_invoices_table`) | kept as a **per-document override** | Not folded into the policy table. A per-invoice override is a legitimate exception; it just has to be recorded on the document and shown on the statement |
| `charges.paid_by` / `payment_methods.paid_by` | **extended in place** to `Client\|Company\|Agent\|Split` + `agent_percentage` (§4(a)) | D4 and 22 §2.1c. Existing values map unchanged |

`company_percentage` is dropped (computed). Old tables are kept read-only for one release, then dropped.

### 8.4 Gates before any of this runs

1. **`actual_balance` decision closed** (21 §5a: *"Named cutover gate"*) — several agent flows hand-increment `Account::$actual_balance` (`AgentSettlementService::onPaymentCompleted()` does `$gatewayAssetAccount->actual_balance += $netAmount; $gatewayAssetAccount->save();`), and the migration will move balances those increments were never told about.
2. **Serial schemas seeded** for every new series in §5.1 — `HRJV` and `AST` especially — with the typed unique-violation handler in place. 17 §3 gate 3 records that `accounting:seed-serial-schemas` has not been run `[unverified against the live DB]`.
3. **Name/code-lookup census clean, in app code AND in migration bodies `[R2]`** — before M2 (rename `2230`) and M7 (renumber `2231…`), grep must show no surviving `Account::where('name', 'Agent Profit Payable')`, no `where('code', '223…')`, no `Account::where('name', 'like', 'Commissions (Agents)%')` (live in `createProfitEntries()` today), no `Account::where('name', 'Gateway Fee Recovery')` (live in `createGatewayProfitEntries()` today), **and no `getOrCreateAccount(…, 'Agent Profit Payable', '2230', …)` in `2026_02_11_162453`** (§2.7 — a re-run recreates the old-named group as a sibling and repoints the pointer at it). 13 §B.3: *"Do not rename it until every name-based lookup is dead."*
4. **`AgentEarningsService` shipped and tested** — P3.a cannot produce its third column without it, and P3.b's type-4 branch cannot produce the recomputed pool.
5. **W3.A2 shipped** (§3.5) — `ensurePartyLeaf()` cannot create a second agent leaf without it, so M3 cannot run.
6. **W3.A shipped** — the legacy code generators must be dead before `135900` exists (§2.3's ordering gate).

---

## 9. Build order `[R2 — dependencies corrected; three new W4 items]`

Mapped onto the existing wave/phase spine (21 §5b). Nothing here creates a new phase; it fills **P5.13 (Staff/agent sub-ledger)**, which 21 §5b already scopes as *"after W3 (already the decision). Per-agent Receivable group, reason tags, settlement documents, per-service commission accounts (03-30 / 07-28)."*

| Item | Wave/phase | Depends on | Deliverable |
|---|---|---|---|
| **W3.A2** `[R2 — NEW, and W3.A depends on it]` `AccountResolver::resolveAnchor()` + anchor-aware `assertAnchorIsSafeToExpand()`; `config('accounting.purpose_codes.anchors')` | **W3** | P1.0 (shipped) | §3.5. Without this, `ensurePartyLeaf()` refuses the **first** agent and throws `NonLeafAccountException` on the **second** |
| **W3.A** Delete `AgentController`'s four hand-rolled code generators and the `AGT-…` branch-leaf block; route to `AccountService::ensurePartyLeaf()` via an `Agent` `created` observer | **W3** | **W3.A2** `[R2 — revision 1 said this dependency was "shipped"; it is not]` | One creation path; no unchecked code generator survives |
| **W3.B** Schema: `journal_entries.cost_center_id`, `.settled_amount`, `.reason_tag`; `transactions.cost_center_id`; `invoice_details.gross_margin`; **wire** the existing `DocumentDraft::$costCenterId`; add `LineDraft::$costCenterId`, `$reasonTag` | **W3** | — | 22 §2.1a schedules all but `gross_margin`. **No `agent_id` column is added** (§3.4) |
| **W3.C** Fix `createProfitEntries()`: post `AgentEarningsService`'s `commissionComponent` only, `Dr 5130 / Cr agents.commission_account_id`; delete the `$profit` pair and the 2210 pair; every party line carries `purposeCode: ''` | **W3** | W3.A, W3.B, W3.D | **The D1 correction.** Stops the bleeding before P3 cleans up |
| **W3.D** `AgentEarningsService` — one implementation of types 1–4 on the **gross-margin base** (Rule 1e), per-task clamp floored at zero, returning `commissionComponent` + `salaryComponent` separately; delete both duplicates | **W3** | W3.B (`gross_margin`) | Ledger and report can finally agree |
| **W3.E** Salary feeder moves `JV/AGENT_SALARY` → `HRJV/AGENT_SALARY`; add `HRJV` **and `AST`** to `doc_types`; seed both serial schemas | **W3** | serial-schema gate | D9 payroll gate, half of it |
| **W3.F** `[R3 — NEW, O8/O9]` `cost_centers` master table (`id`, `company_id`, `agent_id` nullable FK, `name`, timestamps) — one row per agent, created by the same `Agent` `created` observer as the party leaves (§3.2); populates `agents.cost_center_id` | **W3** | W3.A2 | `journal_entries.cost_center_id` / `transactions.cost_center_id` (W3.B) are FKs into this table's `id`, **never** the agent id directly — this is what makes the `CC(A)` notation used throughout §5 concrete, not shorthand |
| **W3.G** `[R3 — NEW, O5]` `transactions.bsptype varchar(6)` nullable, validated against `ET\|VOID\|REFUND\|ADM\|ACM\|EMD` (22 §2.1a) | **W3** | — | Stamped on every airline document from **W3/W4** onward (§1.2, Rule 5); the memo module (P5.3.D) inherits the same vocabulary on its own header once it exists — it does not itself gain the column in W3; historical backfill deferred, out of scope here |
| **W4.C** `[R2 — NEW; O2 depends on it]` Supplier-cost leg posts in the **sale's own document and period**; `tasks.supplier_pay_date` becomes a due date | **W4** | — | §5.4. Blueprint 03 §3's `CTR` control postings. Must land **before** W4.A's deletions or the loss goes unrecognised in the sale's period |
| **W4.A** Negative-margin / fee-loss feeders: delete the `Cr {supplier cost}` and `Cr 5123 Fee Loss Provision` offsets; company-borne posts nothing; agent-borne posts §5.4; freeze `5221` | **W4** | W3.C, **W4.C** | 21 §5a already puts the refund-clawback reclassification in W4 |
| **W4.D** `[R2 — NEW]` **Delete `createGatewayProfitEntries()` and all four call sites**; move client fee recovery to `DBN/FEE_RECOVERY` dated the payment (§5.3(a)); amend `ChargeService::calculateChargeForPayment()` to compute the client-bears fee on the **grossed-up** amount; add `5147` | **W4** | W3.B | Without this, markup and rounding are **booked twice** — once by the old method, once by §5.3(a) |
| ~~**W4.E**~~ | — | — | **WITHDRAWN `[R3, O4]`.** Revision 2 conflated this with **MF-30** (the unrelated `5130` asset-classification bug, already gated in **W3** per 22 §2.0) and instructed freezing an "old asset-classified leaf" that does not belong to this section. `5125` is created once, as a plain new expense leaf, by **P5.3.A** — no separate wave item, no reclassification, no freeze |
| **W6.A** `[R3, O10 — moved from W4.B]` Void flow → `PostingService::reverse`, **excluding commission and agent-charge documents from the selection** (§5.15); §5.5(c)'s clawback rule | **W6** | W4.A | 21 §5a: *"Highest-severity remaining correctness bug"*. Moved out of W4 into the **W6 void fold-in** wave per the orchestrator's binding ruling on void sequencing |
| **P5.1** Periods/locks | P5.1 | — | Needed before any back-dated correction; also the home of `PeriodGuard`'s real implementation |
| **P5.3.A** COA: **census first (M0)**, then create `135900`, `135800`, `1952`, `2202`, `4160`, `4210`, `5124`, `5125`, `5126`, `5146`, `5147`, **`4134`, `4135`, `5211`** `[R3, S6/S8]`; rename `2230`; register the new posting **and anchor** purpose codes; `EnsureSystemLeaves` backfill | **P5.3** | P5.1, **W3.A** (ordering gate, §2.3) | §2, §3.3 |
| **P5.3.B** Party pointers on `agents` (**FKs dropped and re-added**, §2.7); `client_id`; §2.4 M2–M9 tree migration; amend `2026_02_11_162453` | **P5.3** | P5.3.A, W3.A, W3.A2 | §2.4 |
| **P5.3.C** Open-item engine extended to `135901` — `settled_amount`, apply/release/FIFO, never-negative, **same-currency matching**; plus §6.4b matching on the payable accrual lines | **P5.3** | P5.3.B | §6; shares the client/supplier implementation and the existing `payment_applications` table, not a parallel one |
| **P5.3.D** Memo module (`memo_headers`/`memo_lines`) — **one** module serving CRN/DBN/ADM/ACM. Must extend P5.3's planned `credit_notes`/`debit_notes`, not sit beside it | **P5.3** | P5.3.A | §5.1, blueprint 07 §5 |
| **P5.4** `LedgerReportQuery` (and the **two-query** contract of §6.6) | P5.4 | P5.2, P5.3 | §7.1 |
| **P3** Historical repair | **P3** | W3.C, W4.A, W4.C, P5.3.B, `AgentEarningsService` | §8.2 |
| **P5.12** Credit control — limits, `credit_from_date`, `is_blacklisted`, `warn\|block`, opening journals excluded, for clients **and agents** | P5.12 | P5.3.C (needs open-item balances) | Rule 7 / D7 |
| **P5.13** **This document's remainder**: `agent_charge_policies` + resolver (+ `paid_by` vocabulary extension); agent DBN/CRN documents; `AST` settlement documents replacing `AgentSettlementService`'s five ad-hoc paths; `PayrollAccrualService`; agent statement; **`[R3]`** the SCHEMA rename `invoice_details.profit` → `profit_net_of_agent_charges` (consumer census + migration, §7.3(1), A10); spot-commission postings and UI (§5.19, S8) | **P5.13** | P5.3.A–D, P5.4 | §4, §5.2–§5.19, §7. **AM-20.1 is ACCEPTED and satisfied by 22 §3 P5.13 (O16); no outstanding gate** (§1.3) |
| **P7.5** ADM/ACM ingestion + BSP reconciliation + incentive accrual | **P7.5** | P5.3.D (memo module), 15 Stage C (ticket decomposition), MF-27 airline dimension | §5.6–§5.8, §5.14. D9 confirms this ships; **15 §F must be amended** `[unverified: not yet amended as of this revision]` |
| **P7** `accounts.behaviour` (`AccTransType`), six typed roots + AccGroup, attachments registry, password/session policy | P7 | — | §2.5, D9 |
| entitlements | entitlements track | — | O11 `accounting.payroll.view` enforcement in the route/query layer |

**The critical path is W3.A2 → W3.A → W3.D → W3.C.** Every day W3.C is not shipped, more wrong liability accrues and P3's repair grows — but it cannot ship before the three items ahead of it, and revision 1's dependency graph hid that.

---

## 10. Challenges to the owner

Each item states the scenario as it stands, what the books would say, and what we do instead.

**10.1 — "The agent monitors the profit on each case, so credit the profit to the agent's account."**
On a 10.000 KWD profit at a 20% commission, today's `createProfitEntries()` credits **10.000** to the agent's leaf *and* **2.000** to the pooled commission leaf: 12.000 of liability against a 2.000 obligation, and 12.000 of expense against 10.000 of gross margin — a company that made 10.000 reports a loss of 2.000 and owes 12.000 it will never pay. **Instead:** credit 2.000 (Rule 1); carry the per-case profit as `cost_center_id` so the agent's screen is unchanged (Rule 1b).

**10.2 — Salary is counted twice in types 3 and 4.**
`AgentController` returns `$totalTaskCommission + $agent->salary` for type 3 and `(profit − salary) × rate + salary` for type 4; `ReportController` does `$commissionTotal += $salary`. If that total is posted to the commission leaf while salary is also accrued to 2201, the salary is a liability **twice**. **Instead:** `AgentEarningsService` returns the two components separately and two documents post them to two accounts (§5.2).

**10.3 — The two type-4 formulas already disagree.**
`ReportController` clamps the base (`max($profitTotal - $salary, 0.0)`); `AgentController` does not. An agent whose salary exceeds the month's profit gets **zero** on one screen and a **negative** commission on the other — and a negative commission posted to a liability is a debit to a payable with no document behind it. **Instead:** one service, clamped, tested (Rule 1d).

**10.4 — Type-4 cannot be posted per invoice, and a future-dated provisional is not a fix.**
The target gate is a monthly test. Posting per invoice means every invoice in a month that ultimately misses target has to be un-posted. Revision 1 offered O7 — a month-end-**dated** provisional posted mid-month — which is invisible on every trial balance until its own date, defeating its stated purpose. **Instead:** one month-end accrual, delta-only on re-run (§5.2 T4), and the intra-month figure is a **report** from `AgentEarningsService`. O7 is withdrawn.

**10.5 — Company-borne losses are being double-counted, and real cost is being erased.**
`createSupplierLossEntries()` posts, for the company's share, `Dr 'Company Loss on Sales' (5221) / Cr {supplier cost account}` — *"Transfer supplier loss to loss account"*. The credit **removes cost from COGS**: gross margin is overstated by the loss, operating expenses are overstated by the same amount, and the bottom line is right by accident while both subtotals are wrong. **Instead:** a company-borne negative margin needs **no entry at all** (O2) — **conditional on W4.C**, because today revenue and supplier cost post in different documents and can land in different months (§5.4). Only the *agent's* share generates a document (§5.4).

**10.6 — `Fee Loss Provision` (5123) is being used as a contra-expense.**
`createFeeLossEntries()` posts `Dr 5221 Company Loss on Sales / Cr 5123 Fee Loss Provision` for the company's share. Both are expense accounts; the pair nets to zero in the P&L while creating two offsetting balances that mean nothing, and the *actual* gateway cost sits elsewhere entirely. Two further defects in the same block: `$feeLossProvisionAccount` is dereferenced inside the `if ($companyLossAccount)` branch with **no null check of its own**, and the whole block is wrapped in `catch (\Exception $e) { Log::error(...) }` — so a failed *half* of a balanced pair is logged and the transaction continues, leaving the ledger unbalanced (§8.1). **Instead:** gross fee expense, explicit contra-expense recovery, one document, no try/catch swallowing a half-posted pair (§5.3).

**10.7 — Netting the two agent balances into one number.**
Convenient on screen, wrong in the ledger: it destroys the liability, destroys the asset, and makes ageing impossible. It also removes the legal question of whether a right of set-off exists. **Instead:** two leaves, netted in the statement footer only (Rule 2, §7.2).

**10.8 — "Just deduct it from their commission / their salary."**
An automatic deduction is an unrecorded set-off: two balances move with no document, no date, no approver, no reversal path — and when it touches salary, it is a payroll deduction with no authorisation on file, and one that can drive a **pooled** liability into debit (§5.12's A2b). **Instead:** the charge posts (§5.3–§5.7); the *collection* is a separate, approved settlement document with a chosen method (§5.11–§5.13). The system may suggest (O8) and never act.

**10.9 — The account-creation code mints colliding codes and can delete a party.**
`AgentController` runs **four** `max(sibling code)+1` generators with no collision check (`$lastProfitCode`, `$lastArCode`, `$lastCompanyCode`, `$lastAgentCode`), plus a fifth, lexicographic one in `InvoiceController::addJournalEntry()`. `CoaSeeder` already had to route around one collision in production (*"2240 is taken on City Travelers by an auto-numbered agent-profit leaf"*). Under `1350`, **agent *n*'s loss leaf and agent *n+1*'s group are issued the identical code**, from the second agent onward — this is now a **verified trace** (§2.3), not an inference. Separately, the `catch` around that block calls `$agent->delete(); $user->delete();` — deleting a party (and a user) on an account-creation failure, after the agent row already exists. **Instead:** `ensurePartyLeaf` + `AccountCodeGenerator` (which has a `codeExists()` retry loop), 6-digit party bands and 6-digit group codes outside the runway, and `restrictOnDelete` on the pointers (§2.3, §2.6, §3.2).

**10.10 — Mixed depth and a redundant tree level.**
The receivable leaf is at level 5 (`AR › {Company} › {Agent} › Agent Loss Receivable`), the payable at level 4. The `{Company Name}` node is pure redundancy inside a `company_id`-scoped COA and consumes one of the six levels blueprint 01 §6 rule 2 allows. **Instead:** both at level 4 (§2.2), old tree migrated by balance transfer and frozen, never deleted (§2.4).

**10.11 — Name-based resolution has crept into the agent flows.**
`AgentSettlementService::onPaymentCompleted()` finds the gateway with `Charge::where('name', 'LIKE', "%{$gatewayName}%")` — a `LIKE` on a user-editable name deciding which GL accounts a payment posts to. `createProfitEntries()` finds accounts by `where('name', 'Agent Salaries')`, `where('name','like','Commissions Expense (Agents)%')`, `where('name','like','Commissions (Agents)%')`. `createGatewayProfitEntries()` uses `where('name', 'Gateway Fee Recovery')`. The agent flows were the one party pattern that resolved by FK (13 §B.1); these methods broke that. **Instead:** purpose codes for shared leaves, party pointers for per-party leaves, and nothing by name (§3.1).

**10.12 — Who pays the fee when the agent pays us?**
`onPaymentCompleted()` debits `GATEWAY_FEE_EXPENSE` and stops — the company silently absorbs the cost of collecting the agent's own debt, with no policy and no visibility. That may well be the right answer, but it should be a decision. **Instead:** O13, defaulting to `company` so today's behaviour is preserved, but now stated and reportable.

**10.13 — The statement's two halves cannot be reconciled.**
`ProfileController` reads profit and commission from `invoice_details` and losses from the ledger. The mirrors can be recomputed at any time by **seven** live rewriters — `recalculateInvoiceCOA()`, `FixOldProfit`, `FixInvoiceCoa`, `FixProfitAndCommission`, `FixCreditInvoiceCOA`, `TaskController::recalculateCommissionForTask()`, `ApplySupplierSurcharge::calculateCommission()` — silently rewriting what a past statement said `[R2 — revision 1's list named three]`. **Instead:** the ledger is the source; the mirrors are display-only (Rule 8, §7.3); the `Fix*` family is retired in one PR with the drift checker (22 §3 P5.17).

**10.14 — `Loss Recovery Income` is absorbing two different things — and neither is income.**
Today the agent's fee share and the agent's supplier-loss share both credit `Loss Recovery Income` (4170). The question *"what did card acceptance actually cost us, net of what agents contributed?"* becomes unanswerable. **And** both amounts sit in **Direct Income**, inflating revenue and gross margin with what is really staff cost-sharing. **Instead:** `5146 Gateway Fee Recovery (Agents)` (contra-expense under 5140) for fee shares, `5126 Loss Recovery (Agents)` (contra-expense under 5100) for margin recoveries, `5125` credited back for clawback recoveries; `4170` frozen (§2.2, §10.5).

**10.15 — An agent's own purchase in the client pool.**
Without `agents.client_id`, a sale to an agent lands in the pooled `Clients` receivable: the agent's statement is incomplete, the balance cannot be offset against their commission, and the **client** ageing report lists a debtor who is staff. **Instead:** Rule 6 / §5.10. Open sub-question: does an agent earn commission on their own purchase? The mechanics are identical either way; the honest default is **no**, because commission is compensation for selling to a third party.

**10.16 — We are deviating from the blueprint on where commission lives, on purpose — and the plan of record must be amended to match.**
Blueprint 07 §11 puts earned commission on per-service **income** accounts flagged `AccTransType = 8`. We put it on the balance sheet as a payable and carry "per service, per consultant" as dimensions. The blueprint's model is a reporting device for a system without line dimensions; ours has them. Classifying an amount owed to a person as company income would misstate both the P&L and the balance sheet. **The deviation was not free, but it is now settled:** 22 §3 P5.13's acceptance test is `PerServiceCommissionTest::commission_is_attributable_per_service_line_for_the_person` and 21 rows **03-30** / **07-28** flip to **DONE (by dimension)**. **AM-20.1** (§1.3) is **ACCEPTED** by ruling O16 — no outstanding gate on P5.13's sign-off.

**10.17 — Two policy tables, four bearer models, one concept.**
`agent_loss` and `agent_charge` are near-identical (one `enum`, one `string`; both defaulting `agent_percentage` to **0**, not 50), and neither covers ADM or refund clawback; `invoices.agent_loss`/`company_loss` is a third override; `charges.paid_by` / `payment_methods.paid_by` is a fourth, with only `Client|Company` and no agent at all. Note also that `AgentLoss::calculateLossDistribution()` **already uses the remainder pattern correctly** (`$companyShare = round($lossAmount - $agentShare, 3)`); it is `createFeeLossEntries()` that double-rounds both sides, and A9 targets that. **Instead:** one `agent_charge_policies` table keyed by charge kind with a documented resolution order, `paid_by` **extended** to carry the agent/split vocabulary (D4, 22 §2.1c) rather than duplicated by override columns, and the per-invoice override kept as a recorded exception (§4, §8.3).

**10.18 — The `1952 Airline Memo Control` balance is a promise.**
It is only correct if every memo gets a step 2. Add it to the year-end pre-close checklist and to the monthly BSP ritual worklist (§5.7, §5.16); a non-zero balance at close should **refuse** the close. Its intra-period presentation is sign-driven (§5.7), and it is deliberately **not** `SUSPENSE`.

**10.19 — Open owner questions this revision raises `[R2]`**

| # | Question | What it blocks |
|---|---|---|
| **Q-20.1** | **DECIDED (orchestrator, 2026-08-27) `[R3]`.** Commission base = **gross margin** (Rule 1e stands); express any different economics via `gateway_fee.bearer` or the commission rate, never a net base. The residual **fact** question — what the signed agent contracts literally state the rate applies to — is tracked as **doc 22 Q3**, not here | Closes `AgentEarningsService` (W3.D) design; P3's recomputation column proceeds on this base |
| **Q-20.2** | **OPEN (fact) — unchanged.** Refund-clawback bearer per agent. No faithful historical source exists; every agent seeds at `company` (§8.3) | P5.13 policy backfill |
| **Q-20.3** | **OPEN (fact) — unchanged.** ADM bearer per agent. Same — agents who informally absorbed ADMs will show `company` | P5.13 policy backfill |
| **Q-20.4** | **DECIDED (orchestrator, 2026-08-27) `[R3]`.** `commission_on_refunded_sale` (O3b) defaults to `un_earn`, with a per-agent override permitted | §5.5(c); O3b's table cell already carries this default |
| **Q-20.5** | **DECIDED (orchestrator, 2026-08-27) `[R3]`.** No commission on an agent's own purchase, as a real **company option** (`commission_on_own_purchase`, **O14**), default **off** — not just an unstated "honest default" | §5.10; O14 |
| **Q-20.6** | **DECIDED (orchestrator, 2026-08-27) `[R3]`.** Gross-up first: `bearer = client` (O1) must **not** be enabled on any company until W4.D's gross-up ships; `5147` is kept strictly for genuine settlement noise, never as a substitute | W4.D sequencing; O1's guard note (§4.1) |

---

## Appendix A — invariants worth a test each `[R2 — A3 split, A6 and A11 restated, five added]`

| # | Invariant | Where |
|---|---|---|
| A1 | Every document posts balanced within `config('accounting.engine.balance_tolerance')` | `PostingService` (exists) |
| A2 | `223001…` is never driven below zero by a charge document | `AgentChargeService` |
| **A2b** `[R2]` | A salary-deduction settlement never exceeds `PayrollAccrualService::accruedUnpaidFor(agent, doc_date)` | `SettlementTest` |
| **A3a** `[R2 — replaces A3]` | `Σ credits on 2230›{agent}` == `Σ AgentEarningsService(commissionComponent)` for the same periods, ± tolerance | `AgentLiabilityTiesToEarningsTest` |
| **A3b** `[R2 — replaces A3]` | `closing balance on 2230›{agent}` == `Σ earnings − Σ charges − Σ settlements`, ± tolerance | `AgentLiabilityTiesToEarningsTest` |
| A4 | `1952` nets to zero for every fully-dispositioned memo; year-end close refuses a non-zero balance (and likewise `2202`) | `AirlineMemoControlTest`, `YearEndCloseTest` |
| A5 | `settled_amount` never negative, never exceeds the line | `OpenItemTest` (P5.3) |
| **A6** `[R2 — restated]` | Ageing buckets sum to `Σ(debit − settled_amount)` over **all** open items on the account (no period filter, `Balance B/F` excluded), and that total **equals the ledger pane's carried-forward figure** | `AgeingTest`, `AgentStatementTest::ageing_total_equals_carried_forward_across_a_year_end` |
| A7 | Statement net == `payable CF − receivable CF`, and no account holds that number | `AgentStatementTest` |
| **A8** `[R2 — restated]` | Gateway fee **expense** == the real `accountingFee`, never the client-facing `gatewayFee`; **and** `Σ credits to 4131 for a payment == clientFee`; **and** `clientFee − accountingFee == markup_profit + rounding_profit` | `GatewayFeeBearerTest` (one case per O1 value, plus one gateway where `self_charge ≠ service_charge`) |
| A9 | Agent share of a split + company share == the whole charge, at 3 decimals (remainder pattern, never two `round()`s) | `SplitRemainderTest` |
| A10 | No `journal_entries` row is ever `UPDATE`d or `DELETE`d by a correction pass | `P3RepairTest` |
| **A11** `[R2 — restated; revision 1's version demanded the opposite of the shipped engine]` | A party-pointer line carries an **empty** `purposeCode` and a non-null `accountId`; a line supplying both is rejected by `PostingService` | `PostingServiceTest` |
| A12 | Every line of an agent-originated document carries `cost_center_id` (there is **no** `agent_id` column to check) | `CostCentreAttributionTest` |
| **A13** `[R2]` | **No `HRJV` document has a line on any `2230›*` or `135900›*` leaf** | `PayrollGateTest` |
| **A14** `[R2]` | Two concurrent charge documents for one agent cannot drive the payable negative; the balance read is scoped by `company_id` and `posting_status = 'posted'` | `AgentChargeConcurrencyTest` |
| **A15** `[R2]` | No correction document credits a P&L account with a `doc_date` in a year **later** than the year the corrected entry belongs to | `P3RepairTest` |
| **A16** `[R2]` | Voiding a ticket does not reverse the commission accrual, and does not double up with §5.5(c)'s clawback | `VoidFlowTest` |
| **A17** `[R2]` | Commission lines are always base-currency; a foreign-currency agent receivable ages at its own document rate and is included in period-end revaluation | `AgentFxTest` |
| **A18** `[R2; restated R3 fix round 2]` | A second company-default policy row for the same `(charge_kind, scope_key)` is rejected (`policy_key` uniqueness) | `BearerPolicyTest` |
| **A19** `[R2]` | `ensurePartyLeaf()` creates the 1st, 2nd and 100th agent leaf under both anchors without throwing | `AnchorResolutionTest` |
| **A20** `[R2]` | Every `sub_type` this document mints is ≤ 16 characters and round-trips unchanged through `transactions` | `SubTypeLengthTest` |
| **A21** `[R3]` | Cash/bank reports, the Day Book, and receipts reports select by cash/bank **line movement** on the account, never by `doc_type` — every cash-moving `AST` and every `PV/SPOT_SHARE`/`PV/SPOT_ADVANCE` appears exactly like any other cash/bank line | `DayBookTest`, `CashReportTest` |
| **A22** `[R3]` | P3 emits a written IAS 8 restatement note — one line per affected closed year, naming the year and the corrected amount for that year — before the correction batch closes | `P3RepairTest::emits_an_ias8_restatement_note_per_closed_year` |

---

## Appendix B — source index

**Codebase (read this pass):**
`app/Http/Controllers/InvoiceController.php` — `createProfitEntries()`, `createSupplierLossEntries()`, `createFeeLossEntries()`, `createGatewayProfitEntries()`, `addJournalEntry()`, `calculateTotalAccountingFee()`, `recalculateInvoiceCOA()`
`app/Http/Controllers/AgentController.php` — agent create/update, the four code generators, the `AGT-…` branch leaf, the salary `DocumentDraft`, the type-1–4 `switch`, the `catch` that deletes the agent and user
`app/Http/Controllers/ReportController.php` — the second type-1–4 `switch`
`app/Http/Controllers/ProfileController.php` — `getCommissionsByInvoice()` / `getCommissionsByTask()` (the current statement)
`app/Http/Controllers/CoaController.php` — `store()`'s non-company-scoped code check vs. the code-edit path's scoped one
`app/Http/Controllers/TaskController.php` — supplier-cost leg dated `supplier_pay_date`; `recalculateCommissionForTask()`
`app/Services/AgentSettlementService.php` — `createSettlement()`, `settleByProfit()`, `settleByPaymentLink()`, `onPaymentCompleted()`, `generateSettlementNumber()`
`app/Services/ChargeService.php` — `calculate()`, `calculateChargeForPayment()`, `calculateChargeForAccounting()`, `calculateMarkupProfit()`
`app/Models/JournalEntry.php` — `$fillable` (**no `agent_id`**)
`app/Models/AgentLoss.php` (`calculateLossDistribution()`), `app/Models/AgentCharge.php`, `app/Models/Agent.php`
`app/Services/Accounting/` — `LineDraft`, `DocumentDraft` (`$costCenterId`, dead), `PostingService` (`resolveLineAccountId()`, docblock notes 9 and the LOW finding), `AccountResolver` (`isLeaf()`, `resolve()`), `AccountService` (`ensurePartyLeaf()`, `assertAnchorIsSafeToExpand()`), `AccountCodeGenerator`, `SequenceService`, `PeriodGuard`
`database/seeders/CoaSeeder.php` (incl. `5218 Write Off`, `4200` with no seeded children), `SystemAccountsSeeder.php`, `AgentTypeSeeder.php`
`config/accounting.php` — `engine`, `doc_types`, `purpose_codes`
Migrations: `2024_08_22_093322_create_agents_table`, `2024_08_22_095323_create_agent_type_table`, `2024_10_29_063642_create_invoices_table` (`invoices.agent_id`), `2025_06_03_151208_add_charges_type_and_paid_by_into_charges_table`, `2025_06_11_163406_update_payments_and_payment_methods_table` (`payment_methods.paid_by` default `Company`), `2025_07_30_160255_add_target_to_agents_table`, `2025_07_31_110919_create_agent_monthly_commissions_table`, `2025_08_16_103316_add_self_charge_in_charges_table` (`charges.paid_by` default `Client`), `2026_01_12_154855_create_payment_applications_table`, `2026_01_29_130208_create_agent_charge_settings_table`, `2026_01_29_130316_add_profit_columns_to_invoice_details_table`, `2026_02_11_143329_add_agent_loss_table`, `2026_02_11_162453_add_profit_loss_accounts_in_agents_table` (FKs `set null`; `createProfitAccount()`), `2026_03_01_000000_add_loss_bearer_override_to_invoices_table`, `2026_03_18_031249_create_agent_settlements_table`, `2026_08_24_120001…120008` (engine series; `120004` = `sub_type string(16)` + the NULL-in-unique-index comment)

**Planning documents:** `11-technical-implementation-plan.md` §P5.2/§P5.3/§P5.4 · `13-party-ledger-reattribution-plan.md` Stages A–G (esp. §B.1–§B.5, §D.1) · `15-travel-data-capture-and-reports-plan.md` §F (to be amended) · `17-p1-postingservice-complete.md` §3.2–§4 · `19-report-data-contract.md` §3(a), §3(e) · `21-blueprint-coverage.md` §5a, §5b, §6, rows 03-30 / 04-31 / 07-28 · `22-plan-amendments.md` §2.1a, §2.1b, §2.1c, §3 (P5.12, P5.13, P5.16, P5.17, P7.5), §4.1, §5.3 · `concurrency-idempotency-audit.md` · `data-integrity-audit.md` §3

**Blueprint:** `01-chart-of-accounts.md` §5, §6, §7 · `02-posting-engine.md` §3 · `03-transactions-and-ar-ap.md` §3, §6, §7, §8, §9, §10 · `04-travel-industry.md` §5, §6, §8 · `07-modules-and-config.md` §4, §5, §7, §8, §11

**Amendments this document requests (outside its own edit scope):**

| id | Target | Change |
|---|---|---|
| **AM-20.1** | 22 §3 P5.13 acceptance + deliverable; 21 rows 03-30 and 07-28 | Per-service commission by **dimension**, not by account (§1.3, §10.16) |
| **AM-20.2** | 22 §2.1a row 02-7 | **GRANTED (O7) `[R3]`.** Add `writeoff` to the `reason_tag` vocabulary (§3.4, §5.17) — O7 fixes the eight-value `reason_tag` vocabulary identically in both documents, so the motion to add `writeoff` has already been granted |
| **AM-20.3** | D4 / 22 §4.1 | `4133 Gateway Fee Recovery (Agents)` → **`5146`**, contra-expense under `5140`; agent loss recovery → `5126`; clawback recovery → `5125` (§2.2) |
| **AM-20.4** | 15 §F | **SATISFIED BY 22 §5.1 E12 (O16) `[R3]`.** BSP staging-table rule, per D9 — one owner, marked done; no separate tracking item |

---

## 11. Audit disposition

Every finding from the audit of revision 1, with what was done. **Applied** = the document now says the corrected thing. **Applied (deviation recorded)** = corrected in a way that differs from the audit's suggested fix, with the reason. **Rejected** = not applied, with the reason.

### Blockers

| # | Finding | Disposition |
|---|---|---|
| **1** | `journal_entries.agent_id` does not exist; every legacy write is dropped by mass assignment; P3.a's census cannot be written | **Applied (deviation recorded).** §3.4 rewritten to state the column does not exist and that `JournalEntry::$fillable` omits it (verified). **Deviation:** the audit offered "add it (or rely on `type_reference_id`)"; this revision **does not add it** — a third overlapping attribution column invites three answers to one question, and D1 already names `cost_center_id`. §5.2, §7.3(7) and A12 corrected. §8.2 P3.a re-specified onto `invoice_detail_id → invoice_details → invoices.agent_id` (verified: `invoices.agent_id` exists, FK-constrained), stating explicitly that **`invoice_details.commission` becomes the sole reconciliation anchor** for the historical 2210 balance |
| **2** | `PostingService` rejects `accountId` + non-empty `purposeCode`; every §5 party line throws; A11 demands the opposite of the shipped rule | **Applied.** §3.1 rewritten with the verified code and docblock; every party line in §5.2–§5.17 now passes `purposeCode: ''` + `accountId:` + `transactionType:`; §3.3's "must also set `accountId`; `PostingService` should assert that" deleted; **A11 restated** as "a party-pointer line carries an empty `purposeCode`" |
| **3** | `ensurePartyLeaf()` cannot create the first or second agent leaf; W3.A's "shipped" dependency is false | **Applied.** New **§3.5** specifies the two anchor purpose codes (`AGENT_COMMISSION_PAYABLE_GROUP`, `AGENT_RECEIVABLE_GROUP`, per 13 §B.5's convention) **and** the engine change (`AccountResolver::resolveAnchor()` + anchor-aware `assertAnchorIsSafeToExpand()`), added to §9 as **W3.A2**, on which W3.A now explicitly depends. §3.3's "never added to `system_accounts`" claim withdrawn |
| **4** | P3.c/d/e arithmetic reaches the wrong balances on three of four accounts | **Applied.** §8.2 now shows revision 1's arithmetic failing on its own numbers, and replaces it with the audit's **single balanced document** (`Dr 223001 8 / Dr 2210 2 / Cr 5160 10`), stating `5130` is untouched. P3.d and P3.e collapse; P3.d becomes the freeze step |
| **5** | `excludeDocTypes(['HRJV'])` is a no-op on the payable and absent where needed on the receivable; §5.12 posts `HRJV` onto the receivable leaf | **Applied.** §5.12 restructured into two documents (`AST/SETTLE_SALARY` on `2202` + `HRJV/PAYROLL_DEDUCT` on `2201`↔`2202`), with the new `2202 Payroll Deduction Clearing` leaf. The invariant **"no `HRJV` may touch `135901`/`223001`"** added (A13), and §7.1 now drops the filter from **both** ledgers because the gate is structural |
| **6** | `1360` is reached by the 7th agent; the "7-code gap" reasoning is false; `1370` likewise | **Applied.** §2.3 rewritten with the full verified generator trace (including that agent *n*'s leaf and agent *n+1*'s group collide — upgrading §10.9's `[inference]` to fact). Group moved to **`135900`** (6-digit), incentive receivable to **`135800`**; a **per-company `13xx` census (M0)** added that aborts the migration on collision; and a **hard ordering gate** added (P5.3.A depends on W3.A, because once a 6-digit code is `max(child of 1350)` the legacy generator would mint `135901`) |

### High

| # | Finding | Disposition |
|---|---|---|
| **7** | `invoice_details.profit` is net, not gross; the commission base is undefined; the −1.200/case swing is unflagged | **Applied.** New **Rule 1e** fixes the base as **gross margin** by fiat, with the circularity argument; the verified `addJournalEntry()` formula quoted; the economic swing stated and raised as owner question **Q-20.1**; §7.3(1) redefines the mirror (`profit_net_of_agent_charges`) and adds `invoice_details.gross_margin` in W3.B |
| **8** | `clientFee` has three components; §5.3(a) double-books markup and rounding against the live `createGatewayProfitEntries()` | **Applied.** Rule 4 and §5.3 restated with `clientFee = accountingFee + markup_profit + rounding_profit` (verified from `ChargeService::calculate()`); §5.3(a) states it **replaces** `createGatewayProfitEntries()`; **W4.D** added to §9 to delete that method and all four call sites; **A8 extended** with both new identities |
| **9** | A3 is false the moment any charge or settlement posts | **Applied.** A3 replaced by **A3a** (`Σ credits == Σ commissionComponent`) and **A3b** (`closing == earnings − charges − settlements`); P3.e(1)/(2) updated to match |
| **10** | Closed-year corrections credited to `5160` book a prior-period error as current profit | **Applied.** §8.2 splits the correction by **origin year**: closed-year portions post `Dr 223001 / Dr 2210 / Cr 3400 Retained Earnings` as a restatement; **A15** added ("no correction credits a P&L account dated in a later year than the entry it corrects") and added to P3.e(6) |
| **11** | `4133` and `4170` under Direct Income inflate revenue and gross margin | **Applied (deviation recorded).** §2.2 gains a classification note and a table. **Deviation from D4's code, not its intent:** `4133` → **`5146`**, contra-expense under `5140`; agent loss recovery → **`5126`** under `5100`; `4170` **frozen** after P3. The audit offered "or under 4200"; contra-expense was chosen because it makes `5140`'s net balance directly answer "what did card acceptance cost us after staff contributions". Presentation consequence stated; amendment **AM-20.3** raised |
| **12** | `Dr 2201` can drive a pooled liability into debit with no guard | **Applied (deviation recorded).** §5.12 adds **A2b** and `PayrollAccrualService::accruedUnpaidFor()`. **Deviation:** the audit's options were a per-agent salary sub-account (which contradicts D2) or a payroll clearing account per agent; this revision uses **one pooled clearing account (`2202`) plus a per-agent *dimension* query** over `2201`'s `cost_center_id` — D2's pooling is preserved and the guard is still per-agent |
| **13** | `reason` column renames/retypes 22 §2.1a's agreed `reason_tag` enum and adds four rejected values | **Applied.** §3.4 adopts `reason_tag` with 22's six values verbatim; the mapping table (`clawback→loss`, `commission→NULL`, `bonus→NULL`) is applied throughout §5; `writeoff` is the single addition, raised as **AM-20.2** rather than assumed |
| **14** | Four nullable columns in a unique index — MySQL treats NULLs as distinct | **Applied (deviation recorded).** §4 replaces the index with a **stored generated `scope_key`** + `unique(company_id, scope_key)`. **Deviation:** the audit's first option was sentinel `0`; that breaks the `agent_id` FK constraint, so `scope_key` was chosen (the audit's second option). Test **A18** added. **Superseded, R3 fix round 2:** O11 needed `scope_key` to also carry a real value (`'settlement'`, O13), which a generated column can't do — §4 now makes `scope_key` a plain nullable discriminator and moves the generated NULL-safe uniqueness onto a new `policy_key` column; **A18** restated accordingly |
| **15** | Seven `sub_type` values exceed `string(16)` and truncate into collisions | **Applied.** §5.1 gains the verified column definition, the list of over-length values, and a fully shortened vocabulary (every value ≤16, lengths shown). P3's rollback key now fits (`LIAB_CORRECTION`, 15). **A20** added |
| **16** | Rule 1's Σ does not state the clamp level | **Applied.** Rule 1e states **per task, floored at zero**, quotes today's per-task test, and names the test `AgentEarningsTest::a_loss_making_task_never_reduces_another_task_s_commission` |
| **17** | No locking or read predicate for the never-negative guard | **Applied.** New **§6.5** gives the exact SQL predicate (`company_id`, `posting_status='posted'`, `deleted_at IS NULL`), `lockForUpdate()` on the leaf row as the per-agent serialization point, the lock ordering rule against `PostingService`'s own line-order locking, and **A14** |
| **18** | §5.5 and §5.15 use two different "already settled" tests; the aggregate test produces the set-off Rule 3b forbids | **Applied.** §5.5(c) is now a **per-obligation** test; **§6.4b** added, which maintains `settled_amount` and apply rows on the commission accrual credit lines **for matching only** — explicitly *not* ageing, so §6.4's reasoning stands. The contradiction with §6.4 is resolved in a table |
| **19** | `refund_clawback` is redefined; the airline's clawback is never booked; MF-30 has no home | **Applied.** §5.5 rebuilt as **three distinct events** — (a) airline clawback, always booked to `5125`; (b) agent's share, a recovery crediting `5125`; (c) un-earning the agent's own commission, governed by the new option **O3b**. **W4.E** added to §9 for the MF-30 reclassification — superseded in revision 3 (§12): W4.E is withdrawn (O4) |
| **20** | The per-service deviation is not reconciled with 22 P5.13 / 21 rows 03-30, 07-28 | **Applied.** §1.3 gains an amendment table (**AM-20.1**) naming the replacement acceptance test and the row flips; §10.16 and §7.3(6) reinforce it; §9 marks it as a P5.13 exit-gate condition |
| **21** | §7.2's ageing includes B/F while §5.16 and A6 exclude it; the underlying two-query question is never stated | **Applied.** New **§6.6** states the two-query rule in a table, explains the double-count it avoids, restates **A6**, confirms §7.2's example is correct as printed, and adds a year-end reconciliation step to §5.16 |

### Medium

| # | Finding | Disposition |
|---|---|---|
| **22** | O1 default should be `company`, not `client` | **Applied.** O1 default is `company`, citing 22 §4.1, `payment_methods.paid_by` default `Company` (verified in `2025_06_11_163406`), and `ChargeService`'s method-first read order. The parenthetical is replaced with the real reasoning |
| **23** | `transactions.cost_center_id` omitted; `DocumentDraft::$costCenterId` already exists and is dead | **Applied.** §3.4 adds the `transactions` twin per 22 §2.1a and states that W3.B **wires the existing dead header field** rather than adding a parallel one, quoting `PostingService`'s docblock note 9 |
| **24** | `paid_by` left untouched, contradicting D4 and 22 §2.1c; two override columns with no precedence | **Applied.** §4(a) drops `payment_method_id` and `gateway`, extends `charges.paid_by` / `payment_methods.paid_by` to `Client\|Company\|Agent\|Split` + `agent_percentage`, and gives a single 5-row precedence table. §8.3 and §10.17 updated |
| **25** | Settlements routed onto `JV`/`RV`/`HRJV`, contradicting 22 P5.13's "own `doc_type` and series" | **Applied.** New **`AST`** doc_type and series covers every agent settlement in both directions, including the cash and gateway variants; §5.11–§5.13 and §5.17 rewritten onto it; added to W3.E's seeding |
| **26** | The `Cr 4131` line cannot be dated at `invoice_date` — the gateway is unknown until a partial exists | **Applied.** §5.3(a) moves it to a **`DBN / FEE_RECOVERY` dated the payment**, with the reason (`$clientPaid` derives from `$invoice->invoicePartials`); O1's cell updated |
| **27** | The clearing line will not reconcile to the deposit under client-bears | **Applied.** §5.3(a) gains an explicit rule: the fee must be computed on the **grossed-up** amount (closed form given), amended in **W4.D**; until then the residual posts to the new **`5147 Gateway Reconciliation Difference`** and is reported, never absorbed into clearing. Raised as **Q-20.6** |
| **28** | O6 `on_collection` can produce a negative delta, and a whole-invoice ratio cannot allocate per-task commission | **Applied.** O6 now specifies pro-rata allocation **per `invoice_detail`**, a per-task target, and an explicit **side-flip** for a negative delta (citing the engine's non-negative rule) |
| **29** | O7 posts a future-dated document, defeating its own purpose | **Applied.** **O7 withdrawn** entirely, with the reason (invisible on every trial balance until its date; `PeriodGuard` is a P1 no-op stub). §10.4 updated |
| **30** | `BAD_DEBT_EXPENSE` deferred to P7 but used in P5.13; `5218 Write Off` already exists | **Applied.** `BAD_DEBT_EXPENSE` now maps to the existing **`5218 Write Off`** (verified in `CoaSeeder`) and the P7 dependency is dropped |
| **31** | The write-back credits `4200` by code with no purpose code | **Applied.** New leaf **`4210 Unclaimed Balances Written Back`** with purpose code `UNCLAIMED_LIABILITY_INCOME`. Also noted: `4200` is a group node with no seeded children, so the original posting would have been refused anyway |
| **32** | `1952` swings both ways; no intra-period presentation rule; `SUSPENSE` unreconciled | **Applied.** §5.7 gains a sign-based presentation rule (debit → other current assets, credit → other current liabilities), the always-with-worklist rule, and an explicit "why this is not `SUSPENSE`" paragraph |
| **33** | The "loss is already in the books" premise is false per period — cost is posted by `TaskController` on `supplier_pay_date` | **Applied.** §5.4 gains the caveat with the verified mechanism, and **W4.C** is added to §9 (supplier cost posts in the sale's document and period; `supplier_pay_date` becomes a due date), sequenced **before** W4.A. O2's cell is marked conditional on W4.C, with an interim company-side accrual |
| **34** | §8.1's census names only two rewriters | **Applied.** §8.1 now tables **seven** live rewriters (adding `FixProfitAndCommission`, `FixCreditInvoiceCOA`, `TaskController::recalculateCommissionForTask()`, `ApplySupplierSurcharge::calculateCommission()`) and notes the four other `Fix*` commands; §10.13 corrected to match |
| **35** | P3.b(ii) mis-classifies type 4, erasing its historical liability | **Applied.** §8.2 gains a dedicated **P3.b type-4** row: the recomputed month-end pool is the target balance, and the correction is `profitCredits − recomputedPool` |
| **36** | No FX treatment anywhere | **Applied.** New **§5.18** decides the commission contract currency (base), what the other lines carry, the ageing currency and rate, same-currency matching in `apply()`, and that the agent receivable **is** in scope for period-end revaluation while the commission payable is not. §5.2's hard-coded `KWD/1.0` removed; **A17** added |
| **37** | P3.c "current period" vs P3.d "per period" are inconsistent | **Applied.** §8.2's dating note resolves it: **one document per agent per origin period**, all dated in the current open period, each carrying its origin period on the header. Combined with finding 10's closed-year split |
| **38** | The void flow will select and reverse the commission document, then §5.5 claws it back again | **Applied.** §5.15 states the exclusion rule (`JV` with `AGENT_COMMISSION`/`POOL_ACCRUAL`/`COMM_ON_COLLECT`, plus every `AST` and `DBN/AGENT_CHARGE`) and adds **A16**; W4.B's row in §9 carries it — renumbered W6.A in revision 3 (O10) |
| **39** | A re-run of `2026_02_11_162453` undoes M2 | **Applied.** New **§2.7** with the verified `getOrCreateAccount($accruedExpenses, 'Agent Profit Payable', '2230', …)` call; §8.4 gate 3 extended to **migration bodies**, and the migration is amended in the same PR as M2 |
| **40** | `restrictOnDelete` silently changes existing `set null` constraints | **Applied.** §2.7 states the FKs are **dropped and re-added** (verified: `2026_02_11_162453` created both with `onDelete('set null')`); §3.2 and M8 annotated |

### Low

| # | Finding | Disposition |
|---|---|---|
| **41** | Four `max()` generators, not three; plus a fifth, lexicographic one in `InvoiceController` | **Applied.** §2.3 lists all four by variable name and the fifth separately, with the varchar-lexicographic hazard; §10.9 updated |
| **42** | A fourth per-agent account (`AGT-` random code under the branch tree) is never mentioned | **Applied.** New **§2.6** describes it, its three problems, and an M9 disposition (census → soft-delete if unused, balance-transfer + freeze if used, creation block deleted in W3.A). Added to §2.1 and §2.4 |
| **43** | The `2230` per-agent leaves are created by the migration too, not only `AgentController` | **Applied.** §2.1's runtime list corrected, quoting `createProfitAccount()` |
| **44** | `blacklisted` vs 22 P5.12's `is_blacklisted` | **Applied.** Rule 7 and §3.2 use `is_blacklisted` throughout |
| **45** | O2's "`Dr 5221 / Cr` … nothing" is a malformed instruction | **Applied.** O2's company cell now reads **"No entry"**, with the conditional on W4.C |
| **46** | `agent_loss` written into both `negative_margin` and `refund_clawback` without owner sign-off | **Applied.** §8.3 backfills `agent_loss` → **`negative_margin` only** (quoting 22 §5.3), seeds `refund_clawback` at the hard default, and raises owner question **Q-20.2** alongside ADM's **Q-20.3** |
| **47** | `AgentLoss` already uses the remainder pattern; the enum/string and default-0 differences | **Applied.** §10.17 corrected (only `createFeeLossEntries()` double-rounds, which is what A9 targets); §8.3 and §4(b) note the `enum` vs `string` mismatch and that both tables default `agent_percentage` to **0** |
| **48** | O9 drops "excluding opening journals" and the credit-enabled date | **Applied.** Rule 7 calls both qualifiers load-bearing; O9's cell now names `credit_from_date` and the `OJV` exclusion, quoting blueprint 03 §8 |
| **49** | `CoaController`'s non-company-scoped code check is now verified | **Applied.** §2.3 generator (4) states it as verified, with the contrast against the same file's scoped check, and notes `Account` carries no global scope. The `[inference]` marker is removed |
| **50** | `createProfitEntries()` guards each leg independently, so legacy transactions can be unbalanced | **Applied.** §8.1 gains a dedicated block on unbalanced legacy transactions, with the three consequences for P3 (census must count them; they go to quarantine, never to P3.c; `findUnbalancedTransactions` already exists). §10.6 keeps the `createFeeLossEntries()` half of it |

### Items from "What I could not verify"

Each is now marked in-document rather than silently assumed:

| Audit note | Where it is now marked |
|---|---|
| Whether any company has ≥7 agents | §2.3's **M0 census** makes this a migration-time check instead of a guess; the generator collision is stated as a certainty about the *generator*, not about any tenant |
| Whether `2026_02_11_162453` has run in production | §2.4 M3 is conditional on `agents.loss_account_id` being set; §2.7 covers the re-run hazard either way |
| Whether the per-company leaves (`Agent Salaries`, `Loss Recovery Income`, `Fee Loss Provision`, `Company Loss on Sales`) exist per tenant | §8.1 marks the damage size `[unverified]` and makes the census mandatory |
| Whether `accounting:seed-serial-schemas` has run | §5.1 and §8.4 gate 2 both carry `[unverified against the live DB]` |
| Whether `PeriodGuard` accepts a future-dated document | Moot — **O7 is withdrawn** (finding 29); §9 notes `PeriodGuard`'s real implementation lands in P5.1 |
| `payment_applications`' exact shape | §6.2 carries `[unverified]` and instructs P5.3 to reconcile with the existing table rather than build a parallel one |
| `LedgerReportQuery`'s API | §7.1's code block is now explicitly labelled **ILLUSTRATIVE SHAPE, NOT A QUOTED API**, to be reconciled with P5.4's real interface |
| Whether 15 §F has been amended | **AM-20.4 — SATISFIED BY 22 §5.1 E12 (O16)** `[R3]`; no separate tracking item remains |
| The current classification of the MF-30 clawback account | §5.5(a) and **W4.E** carry the reclassification; the specific account row is located during W4.E, not assumed here — **superseded in revision 3, §12: W4.E is withdrawn and MF-30 is unrelated to `5125`** |

---

## 12. Revision 3 change log

Every orchestrator ruling that touches this document, with the section(s) edited and a one-line description of the change. Rulings scoped entirely to doc 22 (O6, O11, O12, O13, O14 of the orchestrator's own numbering, the O15 doc-22-only sub-items, S1–S5, S7, S9) are **not** listed here — they left this document unchanged. `[R3, fix round 1, B17 — dropped the non-existent "O17"; O2 and O3 were never doc-22-only]` **O2** and **O3** are not doc-22-only: this document has been compliant with both **since revision 2**, not as a result of revision 3 — O2 (`accounting.bearer.gateway_fee` default = `company`) is §4.1 O1's existing default; O3 (credit-control mode `{warn|block}`, default `block`, no `off`) is Rule 7/O9's existing shape. **O16**'s AM dispositions are listed below as they do govern this document's §1.3/§7.3/§9/§10.16/Appendix B language.

| Ruling | Section(s) touched | One-line diff |
|---|---|---|
| **O1** — payment-method bearer wins over the charge row | §4(a) | Swapped precedence rows 1/2 (`payment_methods.paid_by` now beats `charges.paid_by`); renamed the acceptance test to match |
| **O4** — MF-30 is unrelated to the refund-clawback leaf | §2.2, §5.5(a), §9 | Removed the "W4 reclassification target / freeze the old asset-classified leaf" framing from `5125`; withdrew the **W4.E** build item entirely; `5125` is now documented as a plain new expense leaf created once by P5.3.A |
| **O5** — restore `VOID` to `bsptype` | §1.2 (Rule 5 / D5), §9 | `BSPTYPE` vocabulary is now `ET\|VOID\|REFUND\|ADM\|ACM\|EMD`; added build item **W3.G** (nullable column, stamped from W3/W4, backfill deferred) |
| **O7** — `reason_tag` grows an eighth value | §3.4 | `reason_tag` vocabulary (a typed `varchar(16)`, not a DB `enum`) is now `loan, service, adm, fee, loss, settlement, writeoff, advance` — `advance` added for S8's spot-commission advance mode |
| **O8** — build the `cost_centers` master | §3.2, §9 | Added `agents.cost_center_id` FK (§3.2) and build item **W3.F**, the `cost_centers` table itself |
| **O9** — `cost_center_id` holds the cost-centre row id, never the agent id | §1.2, §1.3, §5.2, §5.3, §5.6, §5.7, §5.9, §5.12 | Every worked-posting shorthand `cost_center_id = A` / `cost_center=A` rewritten to `cost_center = CC(A)`, with the notation defined once in Rule 1b |
| **O10** — void wave moves to W6 | §9 | `W4.B` renamed **`W6.A`**, moved to the W6 wave, dependency on the withdrawn `W4.E` removed |
| **O15** — close the decidable owner questions | §10.19 | Q-20.1, Q-20.4, Q-20.5, Q-20.6 marked **DECIDED** with the decision text; Q-20.2/Q-20.3 explicitly marked **OPEN (fact) — unchanged**; new option **O14** (`commission_on_own_purchase`) added to give Q-20.5 a real posting switch |
| **O16** `[R3, fix round 1, B17]` — AM-20.1/AM-20.2/AM-20.4 dispositions | §1.3, §7.3(6), §9 (P5.13), §10.16, Appendix B | **AM-20.1 ACCEPTED** and satisfied by 22 §3 P5.13; test name aligned to `PerServiceCommissionTest::commission_is_attributable_per_service_line_for_the_person`; 21 rows 03-30/07-28 flip to DONE (by dimension). **AM-20.2 GRANTED** by O7 (eight-value `reason_tag` vocabulary, identical in both documents). **AM-20.4 SATISFIED BY** 22 §5.1 E12. No outstanding gates from any of the three |
| **A7** — `5147` is for genuine settlement noise only; gross-up first | §4.1 (O1), §5.3(a) | O1's `client` value is now explicitly **guarded** (refused until W4.D ships); §5.3(a)'s reconciliation caveat states the same guard in the worked example |
| **A9** — cash/bank reports select by line movement, never `doc_type` | §5.13, Appendix A | New invariant **A21**; noted explicitly next to the `AST`/`PV` cash-moving documents in §5.13 |
| **A10** — the `invoice_details.profit` rename is a real schema change | §7.3(1), §9 (P5.13) | Revision 2's "documentation-only" call is overridden: the column is renamed in schema at **P5.13**, after a consumer census, not left as a naming convention |
| **A12** — P3 emits an IAS 8 restatement note | §8.2, Appendix A | New stage **P3.g** and invariant **A22**: one line per closed year, naming the year and the corrected amount, before the batch closes |
| **S6** — fee-invoice income leaves under 4130 | §2.2, §2.3, §3.3, §9 (P5.3.A) | New leaves `4134 Cancellation Fee Income` / `4135 Change Fee Income` (4133 stays permanently retired) with purpose codes `CANCELLATION_FEE_INCOME` / `CHANGE_FEE_INCOME` |
| **S8** — spot commission | §2.2, §2.3, §3.3, §4.1 (O15–O21), §5.1, §5.19 (new), §9 (P5.3.A, P5.13), Appendix A | New leaf `5211 Sales Incentive Expense`; seven new company options (`spot_share_pct`, `spot_treatment`, `spot_profit_basis`, `spot_requires_client_paid`, `spot_true_up`, `spot_approval`, `period_commission`); new worked-posting section §5.19 (incentive, advance, true-up); new document types `PV/SPOT_SHARE`, `PV/SPOT_ADVANCE`, `JV/SPOT_TRUEUP` |
| Header/status | Top of document | Bumped to "revision 3 — orchestrator rulings applied 2026-08-27"; added the `[R3]` convention note alongside `[R2]` |

**Not applied — outside this document's edit scope.** O10's second half ("apply doc 22 §8.3's owed corrections into doc 20") names doc 22 content this pass did not read; nothing in doc 20 depended on it, so nothing here is left inconsistent, but the merge itself is doc 22's job, not this document's. Everything else assigned to doc 20 in the ruling set is applied above.

### Fix round 1 — verifier blockers applied 2026-08-27

The cross-document verifier found internal contradictions and stale cross-references in revision 3. Every blocker below names doc 20 explicitly; blockers naming doc 22 only are out of this document's scope and are not listed.

| Blocker | Section(s) touched | What changed |
|---|---|---|
| **B1** | §3.4, §12 (O7 row), Appendix B (AM-20.2) | `reason_tag`'s type cell corrected from `enum(...)` to `varchar(16)` nullable, validated in `LineDraft` (O7's actual ruling); "22 §2.1a's agreed enum verbatim" → "agreed vocabulary verbatim"; §12's O7 row and Appendix B's AM-20.2 row no longer call it an `enum` |
| **B4** | §1.3, §7.3(6), §9 (P5.13 row), §10.16 | `PerServiceCommissionTest`'s name aligned everywhere to `commission_is_attributable_per_service_line_for_the_person` (22's ruling-compliant name); every "AM-20.1 must land or the exit gate fails" replaced with "AM-20.1 is ACCEPTED … no outstanding gate" (O16) |
| **B5** | §3.4 (mapping table), Appendix B (AM-20.2) | Dropped "subject to AM-20.2" (implied still-pending); AM-20.2 marked **GRANTED (O7)** in Appendix B |
| **B6** | Appendix B (AM-20.4), §11 ("could not verify" table) | AM-20.4 marked **SATISFIED BY 22 §5.1 E12 (O16)** in both places it appeared, replacing "still unamended `[unverified]`" |
| **B13** | §9 (W3.G row) | W3.G retargeted from the not-yet-existent memo/airline-document schema to `transactions.bsptype varchar(6)` nullable; noted that the memo module (P5.3.D) inherits the same vocabulary later, on its own header |
| **B15** | §11 (findings 19, 38) | Appended supersession notes: finding 19 → "W4.E is withdrawn (O4)"; finding 38 → "renumbered W6.A in revision 3 (O10)" |
| **B17** | §12 (intro paragraph, ruling table) | Dropped the non-existent "O17"; removed O2/O3 from the doc-22-only exclusion list and noted they've been compliant here since revision 2; added a new **O16** row documenting the AM-20.1/AM-20.2/AM-20.4 dispositions |

**Blockers considered and not applied here (named doc 22, or doc 20 was already correct):** B2, B3, B7, B8, B9 `[recommended resolution keeps doc 20's `Client|Company|Agent|Split` + `agent_percentage` unchanged; the required edit — renaming doc 22's test — lands in doc 22]`, B10, B11, B12, B14, B16.

### Fix round 2 — verifier blockers applied 2026-08-27

A second cross-document verifier pass found further contradictions. Each item below is numbered as the verifier's own report numbered it (the verifier's report numbered twelve items, 1–12, with no gaps). Only items naming doc 20 are listed; items whose required edit lands entirely in doc 22 (its own #3, #5, #9, #10, #11, #12) are not this document's to fix and are not applied here.

| Verifier # | Section(s) touched | What changed |
|---|---|---|
| **1** | §4 (schema), §4.1 (O13), Appendix A (A18), §11 (finding 14) | `agent_charge_policies.scope_key` could not be both a `CONCAT(agent_id, charge_kind)` **stored generated** uniqueness column (as originally defined) and a settable fourth dimension holding `'settlement'` (as O13 needed) at once. `scope_key` is now a real `string(24)->nullable()` discriminator (`null`/`'sale'` = ordinary, `'settlement'` = O13); NULL-safe uniqueness moves to a new stored generated `policy_key` = `CONCAT(COALESCE(agent_id,0), ':', charge_kind, ':', COALESCE(scope_key,'-'))` with `unique(company_id, policy_key)`. O13's storage cell now reads `scope_key = 'settlement'`, not "scope noted in `notes`". A18 restated over `(charge_kind, scope_key)` / `policy_key`. Finding 14 gets a supersession note |
| **2** | §3.4 (`reason_tag` row) | The eight-value list was stated only in §12's change log, never printed in §3.4 itself. Inserted verbatim: "Vocabulary (eight values, identical in 22 §2.1a row 02-7): `loan \| service \| adm \| fee \| loss \| settlement \| writeoff \| advance`" |
| **4** | §4.1 (O3b) | doc 22 names this option `accounting.agent.refund_commission_treatment`; doc 20 names it `commission_on_refunded_sale`. Per the orchestrator's required fix (which renames doc 22's identifier to match doc 20's, not the reverse), O3b's cell now states the fully-qualified form once: "`commission_on_refunded_sale` — fully qualified `accounting.agent.commission_on_refunded_sale`" — so both documents are grep-identical on doc 20's name |
| **6** | §5.16 | The year-end P&L sweep list omitted the three leaves revision 3 created (`5211`, `4134`, `4135`), which would have carried income/expense balances into the new year. Restated in full: `4131, 4134, 4135, 4160, 4210, 5124, 5125, 5126, 5130, 5141–5147, 5160, 5211, 5218, 5221` |
| **7** | §3.4 (`reason_tag` row) | This document quoted a doc-22 test name doc 22 does not use (`ReasonTagTest::engine_rejects_an_unknown_reason_tag`). Corrected to doc 22's actual name, `ReasonTagTest::engine_rejects_an_unknown_value` |
| **8** | §4 (acceptance tests) | Two differently-named `BearerPolicyTest` methods asserted the same O1 precedence rule, violating the "one test name, one owner" rule. Kept doc 22's name (`method_wins_over_charge_wins_over_agent_policy_wins_over_company_default`, which also covers the agent-policy rung) and replaced this document's own coined name with it |

**Not applicable to doc 20 (doc 22's to fix):** the verifier's own items #3 (§12 vs 22 §9.6's B15 count — doc 20's B15 row is already correct and is the reference doc 22 must match), #5 (doc 22's negative_margin/W4.A cells need doc 20's existing §4.1 O2 / §5.4 / §9 conditionality copied in — doc 20 already states it), #9 (doc 22 §9.2/§5.4's O16/AM-20.2 labels — doc 20 Appendix B already reads **GRANTED (O7)** and is the reference), #10 (doc 22 §9.1/§9.2's stale "see §9.3" pointers and the A6 "Landed in" cell — internal to doc 22's own cross-references; nothing for doc 20 to do), #11 (doc 22 §2.1c's W3.E reproduction dropped `AST` — doc 20 §9's own W3.E row already names both `HRJV` **and** `AST`), and #12 (doc 22 §4.0's option-index "—" cells for O3b/O14–O21 — doc 20 §4.1 already carries those ids).

### Fix round 3 — verifier blockers applied 2026-08-27

A third pass corrected internal contradictions found in this document's own fix-round history, plus one stale cross-reference. Items are numbered as the orchestrator's own blocker list numbered them; items whose required edit lands entirely in doc 22 (#2, #3, #4) are not this document's to fix and are not applied here.

| Item | Section(s) touched | What changed |
|---|---|---|
| **1** | §12 (Fix round 2's intro sentence and closing not-applicable list) | Fix round 2's own intro sentence claimed the second verifier's report "numbered 1–9, then 11–12; there is no item 10" — this was wrong. The verifier's report in fact numbered twelve contiguous items, 1–12, with no gaps. Corrected the intro sentence to state that, and extended the closing not-applicable list to add **#10** (doc 22 §9.1/§9.2's stale "see §9.3" pointers and the A6 "Landed in" cell — an internal doc-22 cross-reference fix; nothing here) alongside the existing #3, #5, #9, #11, #12 |
| **5** | §9 (W3.G row), §12 (O5 row) | Two stale cross-references corrected to name the heading that actually carries Rule 5/D5: §12's **O5** row's "Section(s) touched" cell, "§5 (Rule 5 intro), §9" → "§1.2 (Rule 5 / D5), §9"; §9's **W3.G** row's backfill-timing citation, "...from W3/W4 onward (§5.1's Rule 5 intro)" → "...from W3/W4 onward (§1.2, Rule 5)" |

**Not applicable to doc 20 (doc 22's to fix):** items **#2** (doc 22 §3 P5.13's settlement bullet — the `HRJV`/`AST` purpose-code vs `sub_type` labeling), **#3** (doc 22 §6.1 graph and §2.1c's P5.3.E "Scheduled here as" cell — P5.13's depends-on set; doc 20 §9's P5.13 row was already correct at P5.3.A–D and needed no change), and **#4** (doc 22 §2.1c's reconciliation table — five rows doc 22 §2.1c omitted from doc 20 §9) name doc 22 exclusively.

### Fix round 4 — verifier blockers applied 2026-08-27

A fourth pass found that Fix round 2's own intro sentence, corrected in round 3 to add #10 to its worked example, had not been carried into its parenthetical restating doc 22's not-applicable set, leaving that subsection stating two different such sets.

| Item | Section(s) touched | What changed |
|---|---|---|
| **1** | §12 (Fix round 2's intro sentence) | Fix round 2's intro sentence's parenthetical still read "(its own #3, #5, #9, #11, #12)," omitting **#10** even though the closing not-applicable list two paragraphs later (round 3's own fix) already reads #3, #5, #9, #10, #11, #12. Corrected the intro's parenthetical to **"(its own #3, #5, #9, #10, #11, #12)"** to match |

### Fix round 5 — verifier blockers applied 2026-08-27

A fifth pass found this document's own fix-round change-log entries pointing at the wrong heading for two of their own edits, plus a separate cross-document dependency-graph gap. Items are numbered as the orchestrator's own blocker list numbered them (**NEW-1**, **NEW-2**); **NEW-2**'s required edit lands entirely in doc 22 and is not this document's to fix.

| Item | Section(s) touched | What changed |
|---|---|---|
| **NEW-1** | §12 (Fix round 3's item 1 row, Fix round 4's item 1 row) | Both rows' "Section(s) touched" cells cited §9 — Build order (lines 1478–1513), which carries no fix-round text — for edits actually made in this document's own §12 change log. Fix round 3 item 1's cell corrected from "§9 (Fix round 2's intro sentence and closing not-applicable list)" to "§12 (Fix round 2's intro sentence and closing not-applicable list)"; Fix round 4 item 1's cell corrected from "§9 (Fix round 2's intro sentence)" to "§12 (Fix round 2's intro sentence)" |

**Not applicable to doc 20 (doc 22's to fix):** **NEW-2** (doc 22 §2.1c's P5.3.E row, §3 P7.5's depends-on line, §6.1's P7.5 box, and §9.3's S2 "Landed in" cell — all doc 22 content) names doc 22 exclusively.
