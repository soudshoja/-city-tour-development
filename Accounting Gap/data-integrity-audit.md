# Data Integrity Audit — citytour Accounting Tables

**Pack file:** `Accounting Gap/data-integrity-queries.sql` (46 read-only SELECT statements, 9 required categories + 1 bonus)
**Dry-run environment:** local dev DB `laravel_testing`, connection `mysql`, host `127.0.0.1:3306` (from repo `.env`)
**Dry-run date:** 2026-07-07
**Access mode:** READ-ONLY throughout — every statement executed was a `SELECT` (or `SHOW COLUMNS`/`SHOW TABLES` for schema discovery). No `INSERT`/`UPDATE`/`DELETE`/DDL was run at any point.

---

## 0. Headline finding: the local dev DB has no data to audit

Before trusting the "0 hits" table below, read this section.

`SHOW COLUMNS` and row-count checks were run against every candidate local database:

| Database | journal_entries | invoices | payments | credits | accounts | users |
|---|---|---|---|---|---|---|
| `laravel_testing` (the one named in `.env`) | 0 | 0 | 0 | 0 | 0 | 0 |
| `city_tour_test` (also present on this MySQL instance) | 0 | 0 | 0 | 0 | 0 | 0 |

**All accounting tables in every local database on this machine are empty.** This is a freshly-migrated schema with zero seeded/transactional data — not a snapshot of real bookings. Every query in the pack ran cleanly (correct table/column names, no SQL errors, no timeouts), which does validate that **the pack itself is syntactically correct against the real schema** — but a "0" in the count column below means *"no rows existed to test,"* not *"the books are clean."* Do not report these zeros to anyone as evidence of data integrity.

The actual data this audit needs to run against lives on the **dev app server** `development.citycommerce.group` (DB `citycomm_city-tour-test`, per prior session memory) or, for a real production signal, on `tour.citycommerce.group` (DB `citycomm_city-tour`). Section 3 below gives the exact commands to run this pack there.

Schema notes picked up along the way (useful if you re-run schema discovery later):
- The table the task asked about as `invoice_receipt` does not exist; the real table is **`invoice_receipts`** (plural) — columns: `id, type(enum: invoice/credit/account/import), invoice_id, invoice_partial_id, account_id, credit_id, transaction_id, amount, status(enum: pending/approved/rejected), is_used, created_at, updated_at`. It has no `deleted_at` (no soft delete) and wasn't required by any of the 9 checks, so it isn't used in the pack, but flag this naming mismatch if it's referenced elsewhere.
- `credits` has no `remaining_amount`/`used_amount` column — "remaining balance" is computed live by summing `credits.amount` grouped by the original `payment_id`/`refund_id` (see `app/Models/Credit.php::getAvailableBalanceByPayment/ByRefund`). Check 4.1 is built around this real mechanism, not a guessed column.
- `invoices` and `agents` do **not** carry `company_id` directly. Company ownership for an invoice is resolved via `invoices.client_id -> clients.company_id`. All cross-company checks in section 7 join through `clients` for this reason.
- `accounts` has no `deleted_at` (accounts are never soft-deleted); it does have a `disabled` flag and an `is_group` flag, neither of which is enforced by any posting code path — confirmed independently in `Accounting Gap/02-posting-engine.md` (already in this repo), which found `is_group` is "never checked before any of the 21 posting call sites." Checks 6.1/6.2/B.1 in this pack measure the actual damage from that known gap.

---

## 1. Results table (dev DB `laravel_testing`)

| # | Check | Category | Row count | Real drift found? |
|---|---|---|---|---|
| 1.1 | Unbalanced posting groups by `transaction_id` | Unbalanced postings | 0 | No data to test |
| 1.2 | Unbalanced posting groups by `invoice_id` (no transaction_id) | Unbalanced postings | 0 | No data to test |
| 1.3 | Unbalanced posting groups by `type`+`type_reference_id` | Unbalanced postings | 0 | No data to test |
| 2.1 | Invoices with zero journal_entries rows | No JEs | 0 | No data to test |
| 2.2 | Invoices whose JEs are all soft-deleted | No JEs | 0 | No data to test |
| 3.1 | Invoices `paid` but underpaid (PA + direct payments) | Paid-status drift | 0 | No data to test |
| 3.2 | Invoices `unpaid` but fully applied | Paid-status drift | 0 | No data to test |
| 3.3 | Invoices `partial` with non-partial applied amount | Paid-status drift | 0 | No data to test |
| 4.1 | Credit ledger vs payment_applications drift | Credit drift | 0 | No data to test |
| 4.2 | Credit consumption rows with wrong sign (>=0) | Credit drift | 0 | No data to test |
| 4.3 | Credit source rows with wrong sign (<=0) | Credit drift | 0 | No data to test |
| 5.1 | JE.invoice_id orphan | Orphans | 0 | No data to test |
| 5.2 | JE.account_id orphan | Orphans | 0 | No data to test |
| 5.3 | payment_applications.payment_id orphan | Orphans | 0 | No data to test |
| 5.4 | payment_applications.invoice_id orphan | Orphans | 0 | No data to test |
| 5.5 | invoice_details orphan (no parent invoice) | Orphans | 0 | No data to test |
| 5.6 | JE.transaction_id orphan | Orphans | 0 | No data to test |
| 5.7 | payment_applications.credit_id orphan | Orphans | 0 | No data to test |
| 5.8 | invoice_partials.invoice_id orphan | Orphans | 0 | No data to test |
| 5.9 | credits.invoice_id orphan | Orphans | 0 | No data to test |
| 5.10 | credits.refund_id orphan | Orphans | 0 | No data to test |
| 5.11 | credits.payment_id (numeric) orphan | Orphans | 0 | No data to test |
| 5.12 | refunds.invoice_id orphan | Orphans | 0 | No data to test |
| 5.13 | transactions.invoice_id orphan | Orphans | 0 | No data to test |
| 5.14 | transactions.payment_id orphan | Orphans | 0 | No data to test |
| 5.15 | credits.payment_id non-numeric (hygiene) | Orphans | 0 | No data to test |
| 6.1 | Postings against account with children | Parent-account postings | 0 | No data to test |
| 6.2 | Postings against `is_group=1` account | Parent-account postings | 0 | No data to test |
| 7.1 | JE.company_id vs account.company_id mismatch | Cross-company | 0 | No data to test |
| 7.2 | JE.company_id vs invoice/client company mismatch | Cross-company | 0 | No data to test |
| 7.3 | JE.company_id vs transaction.company_id mismatch | Cross-company | 0 | No data to test |
| 7.4 | payment_applications payment/invoice company mismatch | Cross-company | 0 | No data to test |
| 8.1 | Negative debit/credit on journal_entries | Negative values | 0 | No data to test |
| 8.2 | Negative invoice amount/sub_amount/charge | Negative values | 0 | No data to test |
| 8.3 | Negative payment amount | Negative values | 0 | No data to test |
| 8.4 | Negative payment_applications amount | Negative values | 0 | No data to test |
| 8.5 | Negative refund amount fields | Negative values | 0 | No data to test |
| 9.1 | Active JE -> soft-deleted invoice | Soft-delete inconsistency | 0 | No data to test |
| 9.2 | Active JE -> soft-deleted transaction | Soft-delete inconsistency | 0 | No data to test |
| 9.3 | Active payment_applications -> soft-deleted invoice | Soft-delete inconsistency | 0 | No data to test |
| 9.4 | Active payment_applications -> soft-deleted payment | Soft-delete inconsistency | 0 | No data to test |
| 9.5 | Active payment_applications -> soft-deleted credit | Soft-delete inconsistency | 0 | No data to test |
| 9.6 | Active credits -> soft-deleted invoice | Soft-delete inconsistency | 0 | No data to test |
| 9.7 | Active invoice_details -> soft-deleted invoice | Soft-delete inconsistency | 0 | No data to test |
| 9.8 | Active invoice_partials -> soft-deleted invoice | Soft-delete inconsistency | 0 | No data to test |
| B.1 | Postings against a disabled account | Bonus | 0 | No data to test |

**Sample offending rows:** none for any check — every query returned an empty result set because every source table is empty (see Section 0). There is nothing to sample.

**SQL correctness confirmation:** all 46 statements executed without a single SQL error against the live schema (verified by running the whole pack through `php artisan tinker` against `laravel_testing`, since no `mysql` CLI binary is installed on this machine — see Section 3 for why that doesn't block production use).

---

## 2. Interpretation guide (what a non-zero result means, per category)

1. **Unbalanced posting groups (1.1–1.3).** The strongest possible "books are wrong" signal — a document's debit legs and credit legs don't net to zero. Confirmed by the existing qualitative audit in `Accounting Gap/02-posting-engine.md`: there is **no Sum(debit)=Sum(credit) assertion anywhere in the posting code** (Finding 2), and several code paths can knowingly post one-sided documents when an account lookup fails. Expect this check to find real rows once run against populated data.
2. **Invoices with no JEs (2.1–2.2).** Confirms/quantifies the known historical bug referenced in the task brief (pre-Phase-30 `ConfirmBookingAfterPaymentJob`). 2.1 is invoices that never got a posting; 2.2 is the rarer case where a posting existed and was later soft-deleted, leaving none active.
3. **Paid-status vs applications drift (3.1–3.3).** Read the CAVEAT in the `.sql` file before acting on 3.1: this codebase settles invoices through two independent mechanisms (`payment_applications` for the credit/split flow, and a direct `payments.invoice_id` link with `status IN ('success','completed','paid')` for the plain gateway-checkout flow). 3.1 reports both signals side-by-side specifically so a real gap can be told apart from an invoice that was simply paid the "direct" way.
4. **Credit balance drift (4.1–4.3).** `credits` has no stored balance column; balance is computed live by summing rows sharing a `payment_id`/`refund_id`. 4.1 cross-checks that live computation against the formal `payment_applications` audit trail (`credit_id`) — drift here means the two records of "how much of this credit has been used" disagree. 4.2/4.3 catch a much simpler failure mode: a row with the wrong sign for its type, which will silently invert every balance built on it.
5. **Orphans (5.1–5.15).** A dangling foreign key. Severity ranges from cosmetic (an audit `notes` field referencing a gone record) to serious (a journal entry with no invoice to explain what it was for). 5.11/5.15 exist because `credits.payment_id` is stored as a `varchar`, not the expected numeric FK type — 5.15 flags rows where that string isn't even a valid integer, which is a data-hygiene problem in its own right (it silently breaks any code, including 5.11, that tries to join/cast it to `payments.id`).
6. **Parent-account postings (6.1–6.2).** Confirmed pre-existing gap: `Accounting Gap/02-posting-engine.md` found `accounts.is_group` is defined but never checked before posting. 6.1 is the structural check (does the account actually have children, via `parent_id`); 6.2 is the business-flag check (`is_group=1`). Any hit corrupts leaf-level report totals (double-counting or misclassification).
7. **Cross-company contamination (7.1–7.4).** In a multi-tenant system, this is a severe class of bug — one tenant's ledger contains a fact that belongs to a different tenant, which is both wrong bookkeeping and a potential data-leak. `invoices`/`agents` don't carry `company_id` directly, so 7.2/7.4 resolve company via `client_id -> clients.company_id`.
8. **Negative impossible values (8.1–8.5).** Straightforward sign-error detector. Note `credits.amount` is intentionally negative for consumption rows and is deliberately excluded here — its sign correctness is validated by 4.2/4.3 instead, which know which sign is correct per row type.
9. **Soft-delete inconsistencies (9.1–9.8).** A live maintenance command already exists for part of this scope (`php artisan credits:soft-delete-orphaned`, `app/Console/Commands/SoftDeleteOrphanedCreditsAndPaymentApplications.php`), but it only handles `payment_applications`/`credits` whose **invoice** was soft-deleted (checks 9.3/9.6). Checks 9.1, 9.2, 9.4, 9.5, 9.7, 9.8 cover the same failure mode for the other parent tables (transactions, payments, credits-as-parent, invoice_details, invoice_partials) that the existing command does not touch. A non-zero 9.3/9.6 means: re-run that command (with `--dry-run` first).

---

## 3. Running this pack against production

**This section is instructions only. Do not run anything in this section without explicit user approval — production credentials and a production run were not authorized as part of this task.**

### 3.0 Before you run anything against production

- Use a **read-only** MySQL account if one exists (a dedicated reporting user, not the app's write-capable Laravel user). If no read-only user exists, get one created first — do not run an ad-hoc audit pack under credentials that can also `DELETE`.
- Never put the production password inline on the command line (it lands in shell history and `ps`). Use `-p` with no value (interactive prompt) or a `--defaults-extra-file=~/.my.cnf.readonly` (chmod 600) instead.
- Confirm which environment you're pointing at before running: per project memory, `development.citycommerce.group` (DB `citycomm_city-tour-test`) is dev, `tour.citycommerce.group` (DB `citycomm_city-tour`) is the real production database — do not conflate the two.
- This pack has **no hardcoded database name** anywhere — the database is selected purely by which `-D<dbname>` / `USE` you point it at, so the exact same `.sql` file is correct for dev, staging, or production.

### 3.1 Run the whole pack in one shot (recommended)

```bash
# From a machine with SSH/mysql-client access to the target DB server:
mysql -h <PROD_DB_HOST> -P 3306 -u <READONLY_USER> -p <PROD_DB_NAME> \
      --table --verbose \
      < "Accounting Gap/data-integrity-queries.sql" \
      > "integrity_audit_$(date +%Y%m%d_%H%M).txt" 2>&1
```

This preserves the `-- N.N` comments as section markers in a plain-text run log, and `--table` formats each result set as an ASCII table so row counts are easy to eyeball. Every statement is a `SELECT`; nothing in the file can mutate data even if run by a writable user, but use the read-only user anyway as a defense-in-depth habit.

### 3.2 Run one specific numbered check only

Extract the statement between its own `-- N.N ...` comment line and the next blank line/semicolon, then pipe just that block to `mysql`. Example for check 5.1 (JE.invoice_id orphan):

```bash
awk '/^-- 5\.1 /,/;$/' "Accounting Gap/data-integrity-queries.sql" \
  | mysql -h <PROD_DB_HOST> -P 3306 -u <READONLY_USER> -p <PROD_DB_NAME> --table
```

Swap `5\.1` for any other check ID (escape the dot: `1\.1`, `7\.4`, `B\.1`, etc.) to run just that one query. This is the fastest way to re-check a single category after a suspected fix, without re-running all 46.

### 3.3 If you only have `php artisan tinker` access (no `mysql` CLI, as on this machine)

This machine has no `mysql.exe` installed, which is why the dev dry-run above was executed through Laravel instead. The same approach works against any environment reachable by `php artisan` with the right `.env`:

```bash
php artisan tinker --execute="
  \$rows = DB::select(file_get_contents('Accounting Gap/data-integrity-queries.sql'));
  // NOTE: DB::select() only runs a single statement — for the whole file, split on
  // semicolons yourself, or run one query at a time as shown below, e.g.:
  \$rows = DB::select(\"SELECT je.id AS je_id, je.invoice_id, je.debit, je.credit, je.company_id FROM journal_entries je LEFT JOIN invoices i ON i.id = je.invoice_id WHERE je.invoice_id IS NOT NULL AND i.id IS NULL\");
  echo count(\$rows) . \" rows\n\";
  foreach (array_slice(\$rows, 0, 5) as \$r) { echo json_encode(\$r) . \"\n\"; }
"
```

Point this at production only by pointing `php artisan` itself at a production `.env` (or `--env=production` with the right config) — never by hand-editing DB credentials into a shared `.env` on a dev machine.

### 3.4 What to do with a non-zero count on production

1. Do **not** attempt an automated fix from this pack — it is a detector, not a repair tool.
2. Re-run the specific check (3.2) with an added `ORDER BY <id> DESC LIMIT 20` to see the full recent set, not just the first 5.
3. Cross-reference the affected `invoice_id`/`company_id`/`credit_id` against the app's own logs (`storage/logs/laravel.log`, and specifically `[PAYMENT APPLICATION]` / `[CREDIT PAYMENT COA]` log lines from `PaymentApplicationService`) to find the originating request.
4. Bring the finding to the user before writing any repair script — a repair is a write operation and needs its own explicit sign-off, separate from this read-only audit.
