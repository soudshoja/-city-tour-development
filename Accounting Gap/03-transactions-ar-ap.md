# Audit — Dimension 03: Transactions & AR/AP

**Codebase:** `citytourv2` (main branch checkout at `scratchpad/citytourv2-main-checkout`, HEAD `431f97e68`)
**Blueprint:** `travel-accounting-system` skill, `references/03-transactions-and-ar-ap.md`
**Date:** 2026-07-07
**Overall completeness for this dimension: ~45%**

All file paths below are relative to the checkout root:
`C:\Users\User\AppData\Local\Temp\claude\C--Users-User-OneDrive---City-Travelers-soud-laravel\001d2669-f340-4d6d-9568-2dee0f332d34\scratchpad\citytourv2-main-checkout\`

Unmerged-branch cross-references (per task brief; code NOT analyzed, names only):
- `fix/payment-voucher` — attempted PaymentVoucher + PaymentReconciliation models (open-item/reconciliation rework). Stale, 161 commits behind main.
- `fix/rv` — attempted renaming `invoice_receipt` → `receipt_vouchers`. Abandoned upstream.
- `agent-settlement` — AgentSettlement/Detail/Payment models for agent loss recovery. Unmerged.

---

## Finding 1 — Two-layer model (operational vs. ledger): PARTIAL

**Blueprint (§1):** Keep two parallel layers — operational (`tblTrMaster`/`tblTrDetail`/`tblTrPayment`/`tblTrPaymentAllocation`) and ledger (`tblAccHeader`/`tblAccDetail`) — linked by `RefNo`/`RefCode`/`RefType` on the ledger header. `TrType` 1=sale, 2=refund; `Locked`/`Posted` = draft vs. committed.

**What exists:**
- Operational layer: `app/Models/Task.php` (the service sold), `Invoice.php` + `InvoiceDetail.php` (one row per task/service — analog of `tblTrDetail`), `InvoicePartial.php` (how the customer pays — analog of `tblTrPayment`), `Payment.php`/`PaymentItem.php`, `PaymentApplication.php` (analog of `tblTrPaymentAllocation`).
- Ledger layer: `Transaction.php` (header) + `JournalEntry.php` (lines, with `debit`/`credit`/`account_id`).
- Back-links: `JournalEntry` carries `invoice_id`, `invoice_detail_id`, `task_id`, `transaction_id`; `Transaction` carries `invoice_id`, `payment_id`, `reference_type`, `reference_number` (`app/Models/Transaction.php:15-35`, `app/Models/JournalEntry.php:16-48`). Traceability from GL line back to the sale is genuinely present.

**Gaps:**
1. **No draft/committed state on ledger documents.** There is no `Posted` flag; every posting is immediately "live". The `Lockable` trait (`is_locked`/`locked_by`/`locked_at`, cascade via `Invoice::getLockCascadeMap()` at `app/Models/Invoice.php:74-80`) is a *manual* lock, not a posting state.
2. **`Transaction.transaction_type` is semantically incoherent** — values observed: `'credit'`, `'debit'`, `'cash'`, `'payment'`, `'refund'` (e.g. `ReceiptVoucherController.php:256,345,399,463`; `TaskController.php:2037`). Sometimes it means direction, sometimes payment kind. There is no equivalent of `TrType` (sale vs refund) at the header level; you cannot filter "all sales documents" reliably.
3. **Posted ledger documents are mutable/deletable** — see Finding 12.

**Recommendation:** Add a `doc_status` enum (`draft|posted|void`) to `transactions`; freeze posted rows via model guard; replace the mixed `transaction_type` semantics with a proper `doc_type` (Finding 2) plus a strict Dr/Cr on lines only.

---

## Finding 2 — Document type taxonomy & per-type number series: PARTIAL

**Blueprint (§2):** A `DocType` (+`SubType`) on each ledger header — `INV`, `RV`, `PV`, `JV`, `CTR`, `CRN`, `DBN`, `BDS` — each with its own number series.

**What exists:**
- `Transaction.reference_type` free-text strings: `'Invoice'`, `'Payment'`, `'Refund'`, `'Account'`, `'Import'` — a loose 5-value analog with no enum/validation.
- Number series (per company):
  - Invoices: `InvoiceSequence` (`app/Models/InvoiceSequence.php`; used at `InvoiceController.php:1361-1365` **without locking** — race condition; the bulk job version `app/Jobs/CreateBulkInvoicesJob.php` (~line 260) does use `lockForUpdate()`, so the fix pattern already exists in-repo).
  - Refunds: `RefundSequence` (`RefundController.php:120` → `RF-…`).
  - Payment vouchers: `Sequence` (`PaymentController.php:258-262` → `VOU-YYYY-#####`). Note `Sequence.sequence_for` column exists but every call site is `Sequence::firstOrCreate(['company_id' => …])` — the per-type discriminator is never used.
  - Manual payable/expense entries: ad-hoc `PY-`/`EX-` series computed by string-parsing the last reference (`AccountingController.php:576-587`) — race-prone and collision-prone.
  - Manual topups: `'MTU-' . now()->timestamp` (`CreditController.php:208`) — not a series at all.
- `BulkInvoice`/`BulkInvoiceRow` + `BulkInvoiceController.php` is a reasonable `BDS` (bulk daily sheet) analog on the operational side.

**Gaps:** No `JV` (no manual multi-line journal entry screen — `JournalEntryController.php` is read-only ledger views; the closest is the fixed 2-line `storePayableDetail`/`storeReceivableDetail` in `AccountingController.php:543+`), no `CRN`/`DBN` doc types (Finding 8), no `CTR` (Finding 5), no `SubType`, no unified per-DocType series table.

**Recommendation:** Introduce a `doc_type` column on `transactions` with an enum and one `document_sequences (company_id, doc_type, current)` table used with `lockForUpdate()` everywhere (pattern already proven in `CreateBulkInvoicesJob`). Note: the unmerged branch `fix/payment-voucher` attempted a PaymentVoucher model rework — not production-ready, but indicates upstream awareness.

---

## Finding 3 — Sales invoice posting pattern: PARTIAL (gross-revenue variant, split across two events)

**Blueprint (§3):** One balanced INV document: `Dr customer receivable = SellValue; Cr supplier payable = cost; Cr income = SellValue − Payable`.

**What exists (two half-documents at two times):**
1. **At task issue** (`TaskController.php:2027-2147`, `processIssuedTask`): `Dr supplier cost (expense account named after supplier) = task->total` (line 2049) / `Cr supplier payable (liability named after supplier, or issued-by / currency-specific sub-account) = task->total` (line 2130). Dated `supplier_pay_date`.
2. **At invoice generation/first payment** (`InvoiceController.php:1374-1675`, `addJournalEntry`): `Dr "Clients" (pooled AR) = selling` (ENTRY 1, lines 1483-1522) / `Cr "{Type} Booking Revenue" = selling` (**full sell value, not margin** — ENTRY 2, line 1575). Then profit distribution entries: `Dr Agent Salaries expense / Cr Agent Profit Payable = profit` (`createProfitEntries`, lines 2155-2290), commission entries `Dr Commissions Expense / Cr Commissions (Agents)` (2222-2278), gateway-profit entries (1686-1780), and supplier-loss / fee-loss entries (1847-2150) when margin/profit is negative.

**Assessment:** The *net* P&L effect equals the blueprint's margin (revenue gross + supplier cost as expense), which is a legitimate gross-presentation alternative. Each Dr/Cr pair is arithmetically balanced. However:
- Income recognition = full sell value on the invoice; nothing nets it to commission income, so "income account = the salesperson's margin" from the blueprint does not exist; margin is only derivable via `invoice_details.profit` (operational field, `InvoiceController.php:1476-1481`).
- The supplier-payable leg and the customer leg live in **two different ledger documents with different dates and no shared header**, so a single INV document can never be inspected/reversed as one unit.
- Ledger posting is **lazy** — journal entries for an invoice are only created at payment/apply time if missing (`PaymentApplicationService.php:117-163`, `InvoiceController.php:1114-1160`, `ReceiptVoucherController.php:337-373` all contain "create Invoice COA if it doesn't exist" fallbacks). An issued-but-unpaid invoice may have **no receivable in the GL at all**, which understates AR between invoicing and payment.

**Recommendation:** Post the receivable/revenue document at invoice *issue* (status change to issued), not first-payment; keep the gross model if preferred but document it; link the task-cost document and invoice document via a common header or explicit cross-reference.

---

## Finding 4 — Control (CTR) postings / accrual timing: MISSING (acceptable per blueprint's "build simple first")

**Blueprint (§3):** When billing date ≠ service date, write `CTR` docs through `PayableControlAcc` / income-control accounts so sub-ledgers tie to the GL.

**What I searched:** `grep -ri "control account|CTR|PayableControl|income.control"` over `app/` — nothing. No control accounts in COA seeding, no timing-reallocation documents. The only timing nuance is that the supplier-cost document is dated `supplier_pay_date` while the invoice document is dated `invoice_date` (`TaskController.php:2030`, `InvoiceController.php:1504`).

**Assessment:** The blueprint explicitly says "Build the simple 3-line version first; add control postings when you need accrual timing and sub-ledger control accounts", so this is a *low-severity, expected* gap — but it should be on the roadmap because the current lazy posting (Finding 3) makes period-accurate accrual reporting impossible.

---

## Finding 5 — Balanced-document invariant NOT enforced; multiple provably unbalanced postings: BUGGY (critical)

**Blueprint (§3, §5, §6):** every ledger document "foots to zero"; the engine must guarantee Σdebit = Σcredit per document.

**What exists:** No validation anywhere. `JournalEntry` lines are inserted one at a time; there is no per-`transaction_id` Σdebit=Σcredit assertion before commit (grep for any such check across `app/` — none; the old boot-time guard in `JournalEntry.php:57-78` is commented out and was about leaf accounts anyway). Concrete unbalanced writes on main:

1. **RV approval, `account` type creates a single one-sided entry.** `ReceiptVoucherController.php:866-882` creates exactly ONE JournalEntry (debit OR credit on the chosen account) with **no counter-leg** — a permanently unbalanced document. Also `branchId = $transaction->company_id` at line 848 (branch set to company id — data corruption).
2. **Paid-refund posting is one-sided.** `RefundController.php:775-792` (`handlePaidRefund`) posts a single `Dr Refund Payable > Clients = refundAmount` with no Cr in the same transaction (the commission Dr/Cr pair at 719-745 balances itself, but the refund leg does not). The client `Credit` row (794-803) is operational, not ledger.
3. **Conditional legs skip silently.** The pervasive pattern `if ($account) { JournalEntry::create(...) }` means a missing/renamed account drops one leg and commits the other: e.g. `PaymentApplicationService.php:702-732` (Dr legs only `if ($liabilityAccount)`) vs `744-766` (Cr leg only `if ($receivableAccount)`); `InvoiceController.php:1494-1515` (ENTRY 1 only if `$clientAccount` found — and if it isn't, the method logs nothing and continues to ENTRY 2, posting income with no receivable); `createProfitEntries` posts the expense leg unconditionally but the liability leg only `if ($agent->profit_account_id)` (`InvoiceController.php:2176-2219`).
4. **Posting failures swallowed inside committed transactions.** `PaymentApplicationService::createCreditPaymentCOA` catches its own exceptions and returns null (`PaymentApplicationService.php:776-784`), so the outer `DB::commit()` at line 344 commits applied credits with missing/partial GL. Similarly `addJournalEntry`'s JSON error response is ignored at `PaymentApplicationService.php:149-155`.
5. **Un-reconcile deletes one leg of a two-leg document.** `ReceiptVoucherController::declineReconcile` (805-835) and the BankPayment equivalent (`BankPaymentController.php:726-765`) delete only the settling JE line (`JournalEntry::where('id', …)->delete()` at `ReceiptVoucherController.php:831`), leaving its bank-side counterpart orphaned → unbalanced document.

**Recommendation:** Create a single `PostingService::post(array $lines)` that (a) validates Σdebit=Σcredit, (b) resolves all accounts up-front and aborts if any is missing, (c) wraps in one DB transaction, (d) is the only code path allowed to insert `journal_entries`. Retrofit the five call sites above first; then sweep the remaining ~21 files that call `JournalEntry::create` directly (list obtainable via `grep -rln "JournalEntry::create" app/`).

---

## Finding 6 — Party-master account derivation: PARTIAL, with the core AR principle violated

**Blueprint (§4):** Feeders never hard-code accounts; each party row carries COA pointers (`CustAccID_FK`, `SuppAccID_FK`, `AirlineAccID_FK`); new parties auto-create a leaf account under the right group; **receivables/payables tracked at individual party accounts, not pooled** — that is what makes party-level ageing/statements possible.

**What exists:**
- **Suppliers:** onboarding auto-creates per-supplier payable and cost leaf accounts under per-service groups ("Suppliers (Visas)" / "Visa Cost" etc.) — `SupplierCompanyController.php:96-168`. Good match for the auto-create rule. BUT there is no FK pointer from supplier→account; posting resolves accounts **by name string match** (`TaskController.php` around 1900: `Account::where('name', $supplier->name)`), plus sub-accounts by `issued_by` and by currency (`TaskController.php:1692, 1863, 2275`). A supplier rename breaks posting; duplicate names collide.
- **Agents:** real FK pointers `agents.profit_account_id`, `agents.loss_account_id` (`app/Models/Agent.php:28-29, 80-85`), accounts auto-created (`AgentController.php:449`). This is the blueprint pattern done right.
- **Clients:** **NO per-client accounts at all.** `app/Models/Client.php:15-31` has no account pointer. All customer AR is pooled into one `Accounts Receivable > Clients` leaf, resolved by name at every posting site (`InvoiceController.php:1485-1492`, `ReceiptVoucherController.php:1051-1066`, `PaymentApplicationService.php:735-741`, `RefundController.php:1029-1036`). Party-level detail survives only as a free-text `name` column on the JE line. Per-client ledger statements and account-level ageing are structurally impossible.
- **Cross-tenant bug:** `PaymentController.php:6143` — `Account::where('name', 'Clients')->first()` with **no `company_id` filter** inside the gateway payment posting; in this multi-tenant schema it can post another company's receivable account. (Almost every other lookup filters by company; this one doesn't.)
- **Airlines:** no airline party accounts; flights approximate it via per-`issued_by` payable sub-accounts under the supplier (`TaskController.php:2108-2117`), which loosely parallels the BSP/branch-control idea but isn't a party master.

**Recommendation:** (1) Add `account_id` FK columns (`clients.receivable_account_id`, `suppliers.payable_account_id`, `…cost_account_id`) and backfill; stop resolving by name. (2) Auto-create a per-client leaf under `Accounts Receivable > Clients` on client creation (mirror the supplier flow). (3) Fix `PaymentController.php:6143` immediately (add `->where('company_id', $companyId)`).

---

## Finding 7 — Receipts (RV) and Payments (PV): PARTIAL

**Blueprint (§5):** RV: Dr bank/cash, Cr customer receivable, then apply; PV: Dr supplier payable, Cr bank/cash, then apply; both carry instrument detail (cheque no/date, bank, auth no); a front-office receipt is an RV raised at the counter with a dedicated counter record linked to the RV.

**What exists:**
- **PV** — `BankPaymentController::store` (179-408): multi-line entry, `Dr target (supplier payable) / Cr bank` per item (311-363); instrument details captured on both legs (`cheque_no`, `cheque_date`, `bank_info`, `auth_no` — the columns exist on `journal_entries`, `app/Models/JournalEntry.php:35-38`); guards against insufficient bank balance (240-252); optional immediate "apply" by flagging selected open JE lines (381-398). Good structural match.
- **RV** — `ReceiptVoucherController::store` (165-501) with four modes (`account`, `invoice`, `credit`, `import`) creating a **pending** `InvoiceReceipt` (`app/Models/InvoiceReceipt.php`, migration `2025_09_20_060245`) that posts to the GL only on `approve()` (836-1229). The pending counter document + approval flow is a fair analog of the front-office receipt (`tblFoRctHeader`). The `invoice` mode posts the correct `Dr Receipt Voucher Cash / Cr Clients` pair (1029-1090).
- **Bugs within RV:** the `account` mode posts a single-leg entry (Finding 5.1); `branch_id` bug (848); the `import` mode credits a "Client advance Cash" liability — OK pattern; instrument detail is captured in `store` validation but **not persisted** onto the approval-time JEs for `invoice`/`account` modes (no `cheque_no`/`bank_info` in the creates at 866-882, 1029-1090).
- Cross-reference: the abandoned upstream branch `fix/rv` attempted renaming `invoice_receipt` → `receipt_vouchers`; the naming confusion (an "InvoiceReceipt" that is really an RV header) persists on main.

**Recommendation:** Fix the two RV bugs; persist instrument fields through approval; treat `InvoiceReceipt` as the RV header and give it the RV number series.

---

## Finding 8 — Credit & debit notes (CRN/DBN) and memo module: PARTIAL (CRN via refunds only), DBN/memos MISSING

**Blueprint (§6):** CRN: Dr income / Cr customer receivable. DBN: Dr receivable / Cr income. A memo module (`tblMemoHeader`/`tblMemoDetail`, MemoType D/C) as the data-entry front, also handling airline ADM/ACM and commission adjustments.

**What exists:** No credit-note or debit-note document type, no memo tables, no ADM/ACM handling anywhere (`grep -ri "credit_note|debit_note|ADM|ACM|memo"` over `app/`, `database/migrations/`, `routes/` → nothing relevant). The **Refund module** covers the credit-note *economics* for the refund case: `RefundController::handleRefundCOA` (923-1078) posts a balanced set that nets to `Dr Booking Revenue / Cr Clients receivable` for the refunded amount net of the refund charge — exactly the CRN essence — plus issues an operational client `Credit`. `RefundSequence` numbers them.

**Gaps:** (1) No generic balance adjustment: to *increase* a customer balance (DBN) or make a non-refund allowance, there is no document — users would have to abuse `storeReceivableDetail` (a 2-line bank-vs-account entry, `AccountingController.php:743+`) which forces a bank leg. (2) Commission adjustments post only inside refund/invoice flows, not as standalone memos. (3) `handlePaidRefund` is unbalanced (Finding 5.2).

**Recommendation:** Add a small memo module (header/lines, type D/C) posting `Dr/Cr receivable vs income` and reuse the refund number series or a new CRN/DBN series.

---

## Finding 9 — Open-item apply engine: PARTIAL (two half-engines, neither complete)

**Blueprint (§7):** open-item AR/AP: an apply registry (payment→invoice, amount, line IDs) + `DebitAdj`/`CreditAdj` per ledger line; outstanding = original − applied; partial settlement; release with a never-negative guard; FIFO auto-allocation.

**What exists — two disconnected mechanisms:**

**(a) Operational apply registry — the good half.** `payment_applications` table (migration `2026_01_12_154855`; model `app/Models/PaymentApplication.php`) records `payment_id`/`credit_id` → `invoice_id`/`invoice_partial_id` with `amount`, `applied_by`, `applied_at` — a faithful `tblAccIsApply` analog at the operational level. Partial settlement works: one payment across several invoices and several payments per invoice are both supported (`PaymentApplicationService::applyPaymentsToInvoice`, 37-369; `Invoice::getRemainingBalanceAttribute`, `app/Models/Invoice.php:163-166`). Source-balance guard exists (`available < requested` throws, `PaymentApplicationService.php:195-199`).

**(b) Ledger-line settlement flag — the crude half.** `journal_entries.reconciled` (0=open, 1=settled-by, 2=settling) + `reconciled_ref_id` (`BankPaymentController.php:185, 381-398`; unpaid AR/AP report reads `reconciled=0`, `ReportController.php:888-889`). This is **all-or-nothing per line with no amount** — no `DebitAdj`/`CreditAdj` columns exist on `journal_entries` (checked fillable + migrations), so partially-settled ledger lines are impossible, and `BankPaymentController.php:388-395` flags whatever line IDs the user selected **without validating that the payment amount covers them**.

**Missing pieces vs blueprint:**
- No unified outstanding: `remaining_balance` comes from operational tables; the GL "open items" come from the `reconciled` flag; nothing keeps them consistent.
- **Release/un-apply:** no standalone un-apply for `payment_applications` (only bulk soft-delete when the whole invoice is deleted, `InvoiceController.php:4028-4032`); the flag-based release (`declineReconcile`) exists but deletes a leg (Finding 5.5). No "adjusted balance never goes negative" guard anywhere.
- **FIFO auto-allocation:** only FIFO *ordering* of available credit sources in the picker (`Credit::getAvailablePaymentsForClient`, `app/Models/Credit.php:166-171` — sorts oldest first); allocation itself is user-selected; there is no "auto-apply to oldest open invoices" routine.
- Cross-reference: unmerged branch `fix/payment-voucher` attempted a PaymentReconciliation system — prior art exists but is stale/not production-ready.

**Recommendation:** Pick one source of truth. Cheapest path: extend `payment_applications` to also store the settled JE line IDs and add `settled_amount` to `journal_entries`, maintained only by the apply/release service, with the negative-balance guard; derive the `reconciled` flag from `settled_amount == amount` instead of setting it manually.

---

## Finding 10 — Ageing reports: MISSING

**Blueprint (§7):** ageing reads off the apply engine: outstanding per open line, bucketed 30/60/90/120 days by `DATEDIFF(DocDt, asOf)`.

**What I searched:** `grep -ri "aging|ageing|overdue|bucket|30.*60.*90"` across `app/` and `routes/` — no AR/AP ageing exists. The closest artifacts: `ReportController::unpaidaccountsPayableReceivableReport` (793-940) lists `reconciled=0` JE lines with running balances (no buckets, no per-document outstanding, and — because AR is pooled, Finding 6 — it can only filter by supplier name string, `ReportController.php:862-868`); `PaymentController::outstanding` (7245+) lists incomplete payment links/invoices (operational, no buckets); `invoices.due_date` exists but is never used for overdue logic.

**Recommendation:** Once Finding 9's unified outstanding exists, an ageing report is a single query: open items grouped by party account with `CASE WHEN DATEDIFF(...)` buckets. Blocked today by both the pooled-AR design (Finding 6) and the flag-based settlement (Finding 9).

---

## Finding 11 — Credit control: MISSING

**Blueprint (§8):** per-customer credit limit (+variance), credit-enabled date, recomputed `CurrentBalance`/`AvailableCredit`, `IsBacklisted` hard stop, enforcement at invoice save.

**What I searched:** `grep -ri "credit_limit|creditLimit|CrLimit|blacklist|black_list|is_blocked|available_credit"` across `app/` and `database/migrations/` — nothing. Facts:
- `clients.credit` (migration `2025_04_23_163107`) is a **prepaid top-up balance**, not a limit.
- `credit_facility` table (migration `2024_08_22_093937`, agent_id + balance) is **dead schema** — zero references in `app/`.
- Invoice save (`InvoiceController::store`) performs no balance/limit check of any kind; nothing blocks selling to a delinquent client. `Client.status` exists but is not consulted at invoice time.

**Recommendation:** Add `credit_limit`, `credit_limit_variance`, `credit_from_date`, `is_blacklisted` to `clients` (and optionally `agents`, superseding the dead `credit_facility` table); compute `current_balance` from the (to-be-built) per-client account or from `invoices − payment_applications`; enforce block/warn in `InvoiceController::store` and the bulk job.

---

## Finding 12 — Posted-document immutability: BUGGY

**Blueprint (§1):** `Locked`/`Posted` distinguishes drafts from committed documents; committed GL is the "single source of financial truth" (and `OJV` "never edited").

**What exists:** Deleting an invoice soft-deletes its **posted** ledger: `InvoiceController::delete` (4012-4069) does `JournalEntry::where('invoice_id',…)->delete(); Transaction::where('invoice_id',…)->delete()` (4056-4057) plus receipt-voucher transactions (4044-4051). Financial history is erased (soft deletes preserve rows in the DB but remove them from every report/trial balance) instead of reversed. `recalculateInvoiceCOA` (`InvoiceController.php:2317+`) mutates existing posted entries in place when gateway/amounts change. The `Lockable` trait is opt-in/manual. There are also period-end no-reopen guards — none found.

**Recommendation:** Once posted (Finding 1's `doc_status`), forbid update/delete of `journal_entries`/`transactions`; implement voiding as a dated reversal document. Keep `delete()` only for `draft` docs.

---

## Finding 13 — Online payment gateways (3-line RV, clearing + fee accounts): PRESENT_OK (with two defects)

**Blueprint (§9):** per-gateway clearing account + fee account in the COA; on collection auto-post `Dr clearing (net) / Dr fee / Cr customer receivable (gross)`, apply to the invoice, reconcile later by auth code.

**What exists — the strongest match in this dimension:**
- Per-gateway config: `Charge` model holds `acc_bank_id`, `acc_fee_id`, `acc_fee_bank_id` per company (`app/Models/Charge.php:31-33`) for MyFatoorah, Tap, Hesabe, uPayment, KNET (all with callback handlers in `PaymentController.php:3830-5990`).
- The exact 3-line receipt: `PaymentController::createInvoicePaymentCOA` (6074-6284) posts `Cr Clients = gross` (6188-6204), `Dr gateway clearing = net` (6206-6222), `Dr gateway fee = fee` (6229-6245), inside a `DB::transaction`, with an explicit balance log (`'balanced' => …` line 6257). Invoice/partial statuses are updated (applied) in the same flow (6091-6120).
- Auth codes captured on payments (`payments.auth_code`, `PaymentController.php:1637+`) and `auth_no` exists on JE lines; gateway statement Excel import exists (`importPaymentFile` 2100+, `importTapPaymentFile` 2301+). Full statement-to-ledger reconciliation by auth code is ref-07 territory, but the hooks are present.
- Gateway markup/rounding profit is additionally recognized (`createGatewayProfitEntries`, `InvoiceController.php:1686-1780`) — beyond blueprint.

**Defects:** (1) `PaymentController.php:6143` — unscoped `Account::where('name','Clients')->first()` (cross-tenant; see Finding 6). (2) The fee posted is **recomputed** via `ChargeService::calculate` (6149-6153) from configured rates rather than taken from the gateway's actual reported fee — rate drift silently mis-splits net vs fee, breaking future bank reconciliation of the net deposit.

**Recommendation:** Scope the account lookup; prefer the gateway-reported fee when the callback supplies it, falling back to `ChargeService`.

---

## Finding 14 — Ancillary services, one feeder pattern: PRESENT_OK

**Blueprint (§10):** every service (ticket, hotel, visa, XO, cargo) is the same feeder: capture in the detail table tagged by service type, auto-invoice into INV posting Dr customer / Cr supplier+income, with a per-type income account from the salesperson master.

**What exists:** 12 task types (flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry) all funnel through `Task` → `InvoiceDetail` → the single `addJournalEntry` engine. Per-type differentiation is automatic: income account `"{ucfirst(type)} Booking Revenue"` auto-created under Direct Income (`InvoiceController.php:1526-1561`); per-type supplier payable/cost groups ("Suppliers (Visas)"/"Visa Cost" etc., `SupplierCompanyController.php:96-110`); hotel dual-currency supplier handling via currency-specific payable sub-accounts and `original_currency`/`original_amount` on the JE (`TaskController.php:2077-2107`). The architectural win the blueprint describes — build the accounting once, add services as thin feeders — is genuinely realized.

**Deviations (minor):** income accounts hang off the *task type*, not the salesperson master (no `TKTIncomeAccID_FK`-style per-agent income accounts — instead agent profit is a separate expense/payable pair); no XO (ad-hoc charge) or cargo feeders, but those are business-scope, and `label`-based free-form invoices exist. Job-costing (sea cargo close-out JV) absent — not needed for this business.

---

## Finding 15 — Apply-engine auxiliary flows worth noting (context, mixed)

- **Client credit/top-up subsystem** (`Credit` model + `CreditController::creditTopup` 126-254): operational credit ledger with types Topup/Refund/Invoice/Invoice Refund and derived balances (`Credit::getAvailableBalanceByPayment/ByRefund`) — a workable "customer advances" sub-ledger the blueprint doesn't require but which substitutes for unapplied-receipt tracking. Defect: manual topup posts `Dr Clients receivable / Cr Client advance` (226-244) — debiting AR for money received is wrong (should debit cash/bank); and the second JE only posts `if ($clientReceivable)` (Finding 5.3 pattern).
- **Agent loss recovery**: `AgentLoss` + supplier/fee-loss postings are on main (`InvoiceController.php:1608-1658`); the fuller settlement lifecycle (AgentSettlement/Detail/Payment + service) is **attempted in unmerged branch `agent-settlement`, not production-ready** on this checkout.

---

## Summary scorecard

| Blueprint capability | Status | Severity of gap |
|---|---|---|
| §1 Two-layer model + traceability | partial | medium |
| §2 DocType/SubType + number series | partial | medium |
| §3 INV posting (balanced, Dr AR / Cr payable+income) | partial | medium |
| §3 CTR control postings | missing | low (explicitly deferrable) |
| §2/3/5/6 Balanced-document invariant | **buggy** | **critical** |
| §4 Party master account derivation, per-party AR/AP | partial/buggy | high |
| §5 RV | partial (bugs) | high |
| §5 PV | partial | medium |
| §6 CRN/DBN + memo module | partial (CRN-via-refund only) | high |
| §7 Apply registry + partial settlement | partial | high |
| §7 Release/un-apply with guard | buggy | high |
| §7 FIFO auto-allocation | partial | low |
| §7 Ageing | missing | high |
| §8 Credit control | missing | high |
| §9 Gateway 3-line RV + fee/clearing accounts | present_ok (2 defects) | medium |
| §10 Ancillary one-pattern feeders | present_ok | — |

**Overall: ~45% of the blueprint's Transactions & AR/AP capability is implemented.** The feeder architecture (§10) and gateway receipts (§9) are solid; the open-item engine is half-built in two disconnected ways; credit control and ageing are absent; and the single most important invariant — every ledger document balances — is enforced nowhere and demonstrably violated in at least five code paths.

### Priority fix order for a developer
1. Central `PostingService` with Σdebit=Σcredit + all-accounts-resolved validation (fixes Finding 5; unblocks everything).
2. Fix the five unbalanced call sites (RV account-approve, handlePaidRefund, declineReconcile leg-delete, conditional legs, swallowed COA failures) — file:line references in Finding 5.
3. `company_id` scope on `PaymentController.php:6143`.
4. Per-client receivable leaf accounts + FK pointers on parties (Finding 6).
5. Unify settlement onto `payment_applications` with `settled_amount` on JE lines + release guard (Finding 9).
6. Ageing report (Finding 10) and credit-limit enforcement (Finding 11).
7. Posted-document immutability + reversal-based void (Finding 12).
