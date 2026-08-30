# Data Integrity Audit — Production Copy Results

**Data source:** `citycomm_citytourv2` on `ct-server`, cloned **2026-07-07** from live production `citycomm_city-tour` via `mysqldump --single-transaction --quick --no-tablespaces --routines --triggers` (no locks, no writes against prod). This is a read-only snapshot; all 46 checks below were run against the copy, not against production directly.

Query pack: `Accounting Gap/data-integrity-queries.sql` (46 numbered checks, provenance dated 2026-07-07 against `laravel_testing`, cross-checked here against the actual production schema/data).

## Clone verification

Prod DB size: **424 MB** (well under the 5000 MB stop threshold). Table count matches exactly: **124 tables** in both source and target.

Row counts, source (`citycomm_city-tour`) vs staging (`citycomm_citytourv2`), captured back-to-back immediately after import:

| Table | Prod | Staging | Match |
|---|---:|---:|---|
| invoices | 1,754 | 1,754 | yes |
| journal_entries | 52,670 | 52,670 | yes |
| payments | 3,840 | 3,840 | yes |
| credits | 4,813 | 4,813 | yes |
| refunds | 31 | 31 | yes |
| payment_applications | 1,485 | 1,485 | yes |
| transactions | 20,723 | 20,723 | yes |
| accounts | 1,478 | 1,478 | yes |
| clients | 1,743 | 1,743 | yes |
| companies | 3 | 3 | yes |
| tasks | 10,249 | 10,249 | yes |

Clone is exact — no drift on any key table (dump/import completed in well under a minute, so no live writes landed in the gap).

## Summary — all 46 checks

| ID | What it detects | Hits |
|---|---|---:|
| 1.1 | Unbalanced posting groups by `transaction_id` | **2,273** |
| 1.2 | Unbalanced posting groups by `invoice_id` (fallback, no transaction_id) | 0 |
| 1.3 | Unbalanced posting groups by type+type_reference_id (final fallback) | 0 |
| 2.1 | Invoices with zero `journal_entries` rows | **7** |
| 2.2 | Invoices whose JE rows are all soft-deleted | 0 |
| 3.1 | Invoices marked 'paid' but underpaid on both signals | **12** |
| 3.2 | Invoices marked 'unpaid' but fully covered per `payment_applications` | **18** |
| 3.3 | Invoices marked 'partial' whose applied amount isn't actually partial | **1** |
| 4.1 | Credit ledger balance vs `payment_applications` audit-trail drift | **27** |
| 4.2 | Credit consumption rows (`type='Invoice'`) with non-negative amount | 0 |
| 4.3 | Credit source rows (`Topup`/`Refund`) with non-positive amount | **3** |
| 5.1 | `journal_entries.invoice_id` → missing invoice | 0 |
| 5.2 | `journal_entries.account_id` → missing account | 0 |
| 5.3 | `payment_applications.payment_id` → missing payment | 0 |
| 5.4 | `payment_applications.invoice_id` → missing invoice | 0 |
| 5.5 | `invoice_details` without parent invoice | 0 |
| 5.6 | `journal_entries.transaction_id` → missing transaction | 0 |
| 5.7 | `payment_applications.credit_id` → missing credit | 0 |
| 5.8 | `invoice_partials.invoice_id` → missing invoice | 0 |
| 5.9 | `credits.invoice_id` → missing invoice | 0 |
| 5.10 | `credits.refund_id` → missing refund | 0 |
| 5.11 | `credits.payment_id` (numeric) → missing payment | 0 |
| 5.12 | `refunds.invoice_id` → missing invoice | 0 |
| 5.13 | `transactions.invoice_id` → missing invoice | 0 |
| 5.14 | `transactions.payment_id` → missing payment | 0 |
| 5.15 | `credits.payment_id` holding a non-numeric value (hygiene) | 0 |
| 6.1 | Postings against accounts that structurally have children | **25** |
| 6.2 | Postings against accounts flagged `is_group=1` | **42,401** |
| 7.1 | JE `company_id` vs its account's `company_id` mismatch | **2** |
| 7.2 | JE `company_id` vs its invoice's owning company mismatch | 0 |
| 7.3 | JE `company_id` vs its transaction's `company_id` mismatch | 0 |
| 7.4 | payment_applications linking payment/invoice across companies | 0 |
| 8.1 | `journal_entries` with negative debit or credit | **46** |
| 8.2 | Invoices with negative amount/sub_amount/invoice_charge | 0 |
| 8.3 | Payments with negative amount | 0 |
| 8.4 | payment_applications with negative amount | 0 |
| 8.5 | Refunds with negative refund-amount fields | **5** |
| 9.1 | Active JE referencing a soft-deleted invoice | **2** |
| 9.2 | Active JE referencing a soft-deleted transaction | **5** |
| 9.3 | Active payment_applications referencing a soft-deleted invoice | **2** |
| 9.4 | Active payment_applications referencing a soft-deleted payment | 0 |
| 9.5 | Active payment_applications referencing a soft-deleted credit | 0 |
| 9.6 | Active credits referencing a soft-deleted invoice | **3** |
| 9.7 | Active invoice_details referencing a soft-deleted invoice | 0 |
| 9.8 | Active invoice_partials referencing a soft-deleted invoice | **1** |
| B.1 | JE posted to a `disabled=1` account | 0 |

**Clean (0 hits): 29 of 46 checks** — notably every orphan/dangling-FK check in section 5 (15/15), all negative-value checks except 8.1/8.5 (3/5), and 3 of 4 cross-company checks. **17 checks found real hits.**

---

## 1.1 — Unbalanced posting groups by transaction_id (2,273 hits)

This is the single largest and most direct finding in the pack. **2,273 of 20,723 transactions (~11%)** have journal-entry legs whose debits and credits don't net to zero.

| group_key (transaction_id) | leg_count | total_debit | total_credit | diff |
|---:|---:|---:|---:|---:|
| 5442 | 3 | 1201.000 | 1200.000 | 1.000 |
| 5443 | 3 | 845.000 | 844.000 | 1.000 |
| 5444 | 3 | 2859.000 | 2855.000 | 4.000 |
| 5446 | 3 | 1001.000 | 1000.000 | 1.000 |
| 5451 | 3 | 1885.000 | 1883.000 | 2.000 |

**Interpretation:** the diffs are small, consistent, whole-unit amounts (1–4), not random noise — this looks systemic rather than a handful of one-off mistakes. Every sampled group has exactly 3 legs. This directly confirms the qualitative finding already on record in `Accounting Gap/02-posting-engine.md`: there is no `Sum(debit) = Sum(credit)` enforcement anywhere in the posting code. At ~11% of all transactions, this is not an edge case — it is the routine behavior of at least one common posting path (likely a fee/charge/rounding leg that isn't being captured on both sides). Recommend tracing one of these transaction_ids (e.g. 5442) through `PaymentApplicationService` / the relevant controller to find the code path that writes an unbalanced triple-leg entry.

## 2.1 — Invoices with zero journal_entries (7 hits)

| id | invoice_number | amount | status | created_at |
|---:|---|---:|---|---|
| 34 | INV-2025-00162 | 59.790 | unpaid | 2025-08-06 13:53:40 |
| 50 | INV-2025-00178 | 70.000 | unpaid | 2025-08-07 13:53:07 |
| 705 | INV-2025-00822 | 25.000 | unpaid | 2025-11-30 15:33:07 |
| 1153 | INV-2026-01266 | 261.000 | paid by refund | 2026-02-03 17:05:04 |
| 1264 | INV-2026-01376 | 693.000 | paid by refund | 2026-02-25 23:18:35 |

(2 more rows exist beyond this 5-row sample; 7 total.)

**Interpretation:** exactly the historical gap documented in the query file's provenance notes — invoices created by the pre-Phase-30 `ConfirmBookingAfterPaymentJob` path never got an "Invoice Generation COA" transaction. These invoices have zero accounting trail: they will never appear on a trial balance, AR aging, or P&L. Dates range from Aug 2025 through Feb 2026, so this is not fully closed out by the Phase 30 fix (memory notes it closed the *job* gap in commit `40791a25`, but pre-existing affected invoices were never backfilled). Recommend a one-time backfill pass for these 7 invoices.

## 3.1 — Paid invoices underpaid on both signals (12 hits)

| id | invoice_number | status | amount | applied_via_pa | applied_via_direct_payment |
|---:|---|---|---:|---:|---:|
| 98 | INV-2025-00226 | paid | 374.000 | 0.000 | 0.000 |
| 348 | INV-2025-00473 | paid | 168.000 | 0.000 | 0.000 |
| 444 | INV-2025-00564 | paid | 145.000 | 0.000 | 0.000 |
| 632 | INV-2025-00749 | paid | 117.000 | 0.000 | 0.000 |
| 670 | INV-2025-00787 | paid | 230.000 | 0.000 | 0.000 |

**Interpretation:** all 5 sampled rows show **zero** on both the payment_applications sum and the direct-payment sum — not partial underpayment, total absence of any recorded money against an invoice flagged 'paid'. This is a real gap distinct from the false-positive case the query's caveat warns about (that caveat covers *partially* covered invoices where one signal legitimately doesn't apply; here both signals are fully zero). Worth checking whether these were manually marked paid, paid through a mechanism not covered by either signal (e.g. an offline/manual note), or a symptom of the same historical gap as check 2.1.

## 3.2 — Unpaid invoices fully covered by payment_applications (18 hits)

| id | invoice_number | status | amount | applied_via_pa |
|---:|---|---|---:|---:|
| 114 | INV-2025-00242 | unpaid | 145.000 | 145.000 |
| 261 | INV-2025-00389 | unpaid | 360.000 | 360.000 |
| 262 | INV-2025-00390 | unpaid | 220.000 | 220.000 |
| 263 | INV-2025-00391 | unpaid | 236.000 | 236.000 |
| 264 | INV-2025-00392 | unpaid | 115.000 | 115.000 |

**Interpretation:** clean, unambiguous status-drift — `applied_via_pa` exactly equals `amount` in every sampled row, meaning the payment-application step ran to completion but the invoice's `status` column was never flipped to 'paid'. Straightforward status-recalculation bug (or a job that failed after applying money but before saving status). Safe, mechanical fix: recompute status for these 18 invoices.

## 3.3 — Partial invoices with non-partial applied amount (1 hit)

| id | invoice_number | status | amount | applied_via_pa |
|---:|---|---|---:|---:|
| 1561 | INV-2026-01669 | partial | 585.000 | 0.000 |

**Interpretation:** single row, `applied_via_pa = 0` while status is 'partial' — should be 'unpaid'. Low-impact, one-off stale-status case, same family of bug as 3.2.

## 4.1 — Credit ledger vs payment_applications audit-trail drift (27 hits)

| credit_id | company_id | client_id | type | source_amount | payment_id | refund_id | ledger_group_balance | applied_via_pa |
|---:|---:|---:|---|---:|---|---|---:|---:|
| 431 | 1 | 124 | Refund | 202.60 | NULL | NULL | 0.00 | 0.000 |
| 585 | 1 | 434 | Topup | 860.00 | 633 | NULL | 860.00 | 40.000 |
| 1681 | 1 | 619 | Topup | 168.00 | 1454 | NULL | 336.00 | 0.000 |
| 1682 | 1 | 619 | Topup | 168.00 | 1454 | NULL | 336.00 | 0.000 |
| 1695 | 1 | 50 | Topup | 70.00 | 1357 | NULL | 140.00 | 0.000 |

**Interpretation — needs care, two different patterns mixed in here:**
- **Credit 431 (Refund):** `refund_id` is NULL, so the grouping condition (`c2.refund_id = c.refund_id`) can't match anything reliably — `ledger_group_balance` reads as 0 while `source_amount` is 202.60 with no applied_via_pa to explain the gap. Worth checking directly whether this refund credit was ever consumed and by what.
- **Credits 1681/1682 (Topup):** these are two *separate* source rows sharing the same `payment_id` (1454), each 168.00. The check's grouping key is `payment_id`, so both rows report the *combined* group total (336.00) rather than their own individual amount — this may be an artifact of two legitimate topups against one payment rather than a data bug; flagging it here as ambiguous rather than confirmed-wrong. Recommend verifying whether `payment_id=1454` is genuinely expected to fund two separate credit rows before treating this as a defect.
- **Credit 585:** less ambiguous — source 860, ledger balance still 860 (no consumption rows against payment_id 633), but `payment_applications` records 40 as applied via this credit. That 40 is unaccounted for on the ledger side — a real drift between the two independently-maintained ledgers described in the query file's design note.

Recommend re-running 4.1 with the ambiguous same-`payment_id` cases filtered out to isolate the genuine drift count before prioritizing a fix.

## 4.3 — Credit source rows with non-positive amount (3 hits)

| id | client_id | type | payment_id | refund_id | amount | description | created_at |
|---:|---:|---|---|---|---:|---|---|
| 432 | 124 | Refund | NULL | NULL | 0.00 | RF-1754471692: Refund for Clients | 2025-08-06 15:15:04 |
| 2888 | 363 | Refund | NULL | NULL | -20.95 | RF-2026-00006: Refund for Clients | 2026-01-13 22:45:31 |
| 2889 | 363 | Refund | NULL | NULL | -20.95 | RF-2026-00006: Refund for Clients | 2026-01-13 22:45:31 |

**Interpretation:** row 432 is a zero-amount source row (harmless but odd — a refund credit worth nothing). Rows 2888/2889 are the real issue: **two duplicate rows**, same refund number `RF-2026-00006`, same client, same negative amount (-20.95) — a source-type row that should be positive (it's meant to *create* available credit) is instead negative, and it's been inserted twice. This looks like a genuine sign-error-plus-duplication bug in whatever code path creates refund credit rows for `RF-2026-00006`. Also connects to credit_id 431/432 above (same client_id 124, same refund-credit family) — worth investigating both together.

## 6.1 / 6.2 — Postings against non-leaf accounts (25 / 42,401 hits)

6.1 sample (structural — account genuinely has children):

| je_id | account_id | account_name | is_group | debit | credit |
|---:|---:|---|---:|---:|---:|
| 13954 | 124 | Rate Hawk | 1 | 0.000 | 40.290 |
| 13956 | 124 | Rate Hawk | 1 | 0.000 | 39.370 |
| 22066 | 124 | Rate Hawk | 1 | 0.000 | 5.494 |
| 22068 | 124 | Rate Hawk | 1 | 0.000 | 5.494 |
| 31036 | 124 | Rate Hawk | 1 | 0.000 | 0.000 |

6.2 sample (flag-based — `is_group=1`):

| je_id | account_id | account_name | is_group | debit | credit |
|---:|---:|---|---:|---:|---:|
| 144 | 1351 | Payment Gateway | 1 | 0.000 | 328.000 |
| 149 | 107 | Amadeus | 1 | 227.750 | 0.000 |
| 150 | 280 | KWIKT2843 | 1 | 0.000 | 227.750 |
| 151 | 107 | Amadeus | 1 | 150.900 | 0.000 |
| 152 | 280 | KWIKT2843 | 1 | 0.000 | 150.900 |

**Interpretation:** these two counts diverge enormously — 25 (structural, authoritative) vs 42,401 (flag-based) out of 52,670 total JE rows, i.e. **over 80% of all journal entries post against an account flagged `is_group=1`**. That gap is too large to read as "the posting engine routinely posts to rollup accounts" — it strongly suggests **the `is_group` flag itself is set far too broadly across the chart of accounts**, likely including ordinary leaf/transactional accounts like `KWIKT2843` (looks like a ticket/reference-style leaf account, not a rollup) and `Amadeus`/`Payment Gateway` (also plausible leaf accounts). Recommend treating 6.1 (25 rows, structurally-confirmed parent accounts receiving postings) as the trustworthy signal for the "postings hit non-leaf accounts" defect described in `02-posting-engine.md`, and separately auditing the `accounts.is_group` column itself as a data-hygiene item — it does not appear reliable enough to use as a posting guard in its current state.

## 7.1 — JE company_id vs account company_id mismatch (2 hits)

| je_id | je_company_id | account_id | account_company_id |
|---:|---:|---:|---:|
| 16953 | 1 | 1199 | 2 |
| 16954 | 1 | 1220 | 2 |

**Interpretation:** only 2 rows, but this is the cross-tenant contamination check, flagged in the source pack as a "severe finding" regardless of volume — a journal entry tagged company_id=1 posted against an account that belongs to company_id=2. This is a genuine tenant-boundary leak (small in count, but by definition it means company 1's ledger contains a reference into company 2's chart of accounts, or vice versa). Recommend investigating these two specific JE rows manually before dismissing as low-priority just because the count is small — the other 3 cross-company checks (7.2/7.3/7.4) all came back clean, so this may be an isolated one-time data-entry mistake rather than a systemic pattern.

## 8.1 — journal_entries with negative debit or credit (46 hits)

| id | account_id | debit | credit | invoice_id |
|---:|---:|---:|---:|---|
| 5978 | 43 | 0.000 | -0.620 | 9 |
| 10553 | 73 | -0.150 | 0.000 | 24 |
| 15201 | 116 | -101.160 | 0.000 | NULL |
| 15202 | 293 | 0.000 | -101.160 | NULL |
| 22693 | 116 | -82.693 | 0.000 | NULL |

**Interpretation:** 46 out of 52,670 JE rows (~0.09%) — small but real. Rows 15201/15202 are a matched debit/credit pair (both -101.160, opposite accounts), which looks like a reversal entry written with the wrong sign convention rather than swapping which column holds the value (a proper reversal should still use positive debit/credit, just with debit and credit legs swapped between accounts). Recommend tracing the code path that creates 15201/15202-style pairs — this is a sign-convention bug in a reversal/adjustment path, not random corruption.

## 8.5 — Refunds with negative amount fields (5 hits)

| id | refund_number | invoice_id | total_refund_amount | total_refund_charge | total_nett_refund |
|---:|---|---:|---:|---:|---:|
| 22 | RF-2026-00022 | 531 | -1.780 | 0.350 | 113.000 |
| 23 | RF-2026-00023 | 530 | -1.680 | 0.350 | 113.000 |
| 27 | RF-2026-00027 | 1480 | -16.460 | 0.350 | 82.000 |
| 28 | RF-2026-00028 | 1480 | -9.750 | 0.350 | 84.000 |
| 30 | RF-2026-00030 | 1459 | -18.750 | 0.350 | 204.000 |

**Interpretation:** in every one of the 5 rows, only `total_refund_amount` is negative — `total_refund_charge` (fixed at 0.350 across all 5, suspicious in itself) and `total_nett_refund` are both positive and plausible. This looks like a specific refund-calculation code path (small negative correction/adjustment amounts, always paired with the same 0.350 charge) that stores `total_refund_amount` with the wrong sign. Worth checking whether this maps to one particular refund type or trigger in the app (e.g., a "partial charge adjustment" refund flow), since all 5 share the same charge value.

## 9.1/9.2/9.3/9.6/9.8 — Soft-delete inconsistencies (2 / 5 / 2 / 3 / 1 hits)

9.1 — active JE referencing soft-deleted invoice:

| je_id | invoice_id | invoice_deleted_at |
|---:|---:|---|
| 33092 | 435 | 2026-02-25 23:17:50 |
| 33093 | 435 | 2026-02-25 23:17:50 |

9.2 — active JE referencing soft-deleted transaction:

| je_id | transaction_id | transaction_deleted_at |
|---:|---:|---|
| 20561 | 13366 | 2025-10-05 14:27:19 |
| 20562 | 13366 | 2025-10-05 14:27:19 |
| 20727 | 13441 | 2025-10-05 14:27:19 |
| 33092 | 19103 | 2026-02-25 23:17:50 |
| 33093 | 19103 | 2026-02-25 23:17:50 |

9.3 — active payment_applications referencing soft-deleted invoice:

| pa_id | invoice_id | invoice_deleted_at |
|---:|---:|---|
| 169 | 1188 | 2026-02-11 17:02:07 |
| 539 | 331 | 2026-01-12 19:19:05 |

9.6 — active credits referencing soft-deleted invoice:

| credit_id | invoice_id | invoice_deleted_at |
|---:|---:|---|
| 1322 | 331 | 2026-01-12 19:19:05 |
| 2012 | 637 | 2025-11-08 13:54:03 |
| 2869 | 962 | 2026-01-13 02:46:38 |

9.8 — active invoice_partials referencing soft-deleted invoice:

| invoice_partial_id | invoice_id | amount | status |
|---:|---:|---:|---|
| 443 | 435 | 118.00 | paid |

**Interpretation:** all small counts, all pointing at a handful of specific soft-deleted invoices (435, 331, 637, 962) and transactions (13366, 13441, 19103) that still have active children — invoices 435 and 331 in particular are each the parent for multiple check hits (435 → JE 33092/33093 + transaction 19103 + invoice_partial 443; 331 → payment_application 539 + credit 1322). The existing `credits:soft-delete-orphaned` command (per the query file's notes) will clean up 9.3 and 9.6, but it does **not** cover 9.1, 9.2, or 9.8 — those parent types (transaction, JE-invoice link, invoice_partial) have no existing maintenance command and would need one written, or a manual one-time cleanup given the small row counts. 9.4, 9.5, 9.7 all came back clean (0 hits).

---

## Overall read

- The clone is exact and safe to use for further investigation without any risk to production.
- The most consequential finding is **1.1** (2,273 unbalanced transaction groups, ~11% of all transactions) — this is a systemic, not incidental, gap and confirms the known posting-engine weakness on record.
- **6.2** (42,401 postings against `is_group=1` accounts) is almost certainly inflated by an over-broad `is_group` flag rather than 42,401 genuine rollup-account postings — treat **6.1** (25 rows) as the trustworthy count for that specific defect, and separately flag the `is_group` column itself for a data-hygiene review.
- **7.1** (2 rows) is small in volume but high in severity — a real cross-tenant leak, worth a manual look regardless of count.
- Everything in section 5 (all 15 foreign-key/orphan checks) came back clean — no dangling references anywhere in the schema, which is a genuinely good sign for referential integrity.
- The remaining findings (2.1, 3.1, 3.2, 3.3, 4.1, 4.3, 8.1, 8.5, section 9) are smaller, mostly-explainable pockets of status drift, sign errors, and stale soft-delete references — real, worth fixing, but not systemic in scale.
