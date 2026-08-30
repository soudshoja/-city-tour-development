-- ============================================================================
-- DATA INTEGRITY AUDIT PACK — citytour accounting tables
-- ============================================================================
-- Purpose : Find "books are wrong" conditions in LIVE data. Every statement
--           below is a plain SELECT — nothing here writes, and nothing here
--           should ever be changed to write. Run it, read the row counts,
--           investigate any non-zero result before touching the app.
--
-- Scope   : journal_entries, invoices, invoice_details, invoice_partials,
--           payments, payment_applications, credits, refunds, transactions,
--           accounts, clients (joined only to resolve company ownership).
--
-- Portability: No database name is hardcoded anywhere in this file. Run it
--           with the target database already selected (`USE <db>;` or
--           `mysql <db> < this_file.sql`, or pass -D<db> on the CLI).
--
-- Provenance: Column names were taken from `SHOW COLUMNS` against the local
--           dev database `laravel_testing` on 2026-07-07 (Laravel 11 app at
--           soud-laravel, branch enhance/upstream-sync-2026-07), cross-checked
--           against app/Models/*.php and app/Services/PaymentApplicationService.php.
--           Do not assume these queries are correct against a schema that has
--           since migrated — re-run `SHOW COLUMNS` before trusting old output.
--
-- Companion: See `Accounting Gap/02-posting-engine.md` in this repo for the
--           qualitative audit that motivated checks 1 and 6 (no Sum(debit) =
--           Sum(credit) enforcement anywhere in the posting code; is_group is
--           never checked before posting).
-- ============================================================================


-- ============================================================================
-- 1. UNBALANCED POSTING GROUPS
-- ============================================================================
-- A "posting group" is the set of journal_entries rows that belong to one
-- logical accounting document and must net to zero (Sum(debit) = Sum(credit)).
-- The codebase groups these primarily by `transaction_id` (journal_entries.
-- transaction_id -> transactions.id): every JournalEntry::create() call site
-- that posts a debit+credit pair passes the same $transaction->id to both legs
-- (see app/Services/PaymentApplicationService.php createCreditPaymentCOA()).
-- BUT transaction_id is NULLABLE, so some legacy/edge paths only carry
-- invoice_id, or only type + type_reference_id. We check all three grouping
-- keys, falling back only for rows that lack the higher-priority key, so no
-- row is silently skipped and none is double-counted.
--
-- Non-zero result means: a document was posted (or edited/soft-deleted) in a
-- way that leaves debits and credits out of balance for that group — the
-- single most direct "the books are wrong" signal in the whole pack.
-- ============================================================================

-- 1.1 Unbalanced posting groups by transaction_id (primary grouping key)
SELECT je.transaction_id AS group_key,
       COUNT(*)          AS leg_count,
       SUM(je.debit)     AS total_debit,
       SUM(je.credit)    AS total_credit,
       SUM(je.debit) - SUM(je.credit) AS diff
FROM journal_entries je
WHERE je.transaction_id IS NOT NULL
GROUP BY je.transaction_id
HAVING ABS(SUM(je.debit) - SUM(je.credit)) > 0.01;

-- 1.2 Unbalanced posting groups by invoice_id (only for rows with no transaction_id)
SELECT je.invoice_id AS group_key,
       COUNT(*)       AS leg_count,
       SUM(je.debit)  AS total_debit,
       SUM(je.credit) AS total_credit,
       SUM(je.debit) - SUM(je.credit) AS diff
FROM journal_entries je
WHERE je.transaction_id IS NULL
  AND je.invoice_id IS NOT NULL
GROUP BY je.invoice_id
HAVING ABS(SUM(je.debit) - SUM(je.credit)) > 0.01;

-- 1.3 Unbalanced posting groups by type + type_reference_id (final fallback:
--     rows with neither transaction_id nor invoice_id)
SELECT je.type          AS grp_type,
       je.type_reference_id AS grp_ref,
       COUNT(*)         AS leg_count,
       SUM(je.debit)    AS total_debit,
       SUM(je.credit)   AS total_credit,
       SUM(je.debit) - SUM(je.credit) AS diff
FROM journal_entries je
WHERE je.transaction_id IS NULL
  AND je.invoice_id IS NULL
  AND je.type_reference_id IS NOT NULL
GROUP BY je.type, je.type_reference_id
HAVING ABS(SUM(je.debit) - SUM(je.credit)) > 0.01;


-- ============================================================================
-- 2. INVOICES WITH NO JOURNAL ENTRIES
-- ============================================================================
-- Every invoice is expected to have at least one journal_entries row (the
-- "Invoice Generation COA" transaction — see PaymentApplicationService::
-- applyPaymentsToInvoice() STEP 1A, which explicitly checks
-- Transaction::where('invoice_id', ...)->where('reference_type','Invoice')
-- ->exists() and creates it if missing). Historically ConfirmBookingAfter
-- PaymentJob (pre-Phase-30) created invoices without ever calling that path.
--
-- Non-zero result means: an invoice exists in the books with zero accounting
-- trail — it will never show up on a trial balance, AR aging, or P&L.
-- ============================================================================

-- 2.1 Invoices with ZERO journal_entries rows at all (including soft-deleted JEs)
SELECT i.id, i.invoice_number, i.amount, i.status, i.created_at
FROM invoices i
LEFT JOIN journal_entries je ON je.invoice_id = i.id
WHERE i.deleted_at IS NULL
GROUP BY i.id, i.invoice_number, i.amount, i.status, i.created_at
HAVING COUNT(je.id) = 0;

-- 2.2 Invoices that DO have journal_entries rows, but every single one of them
--     is soft-deleted (i.e. zero currently-active postings) — a narrower,
--     "posting was created then lost" variant of the same defect.
SELECT i.id, i.invoice_number, i.amount, i.status,
       COUNT(je.id)                                       AS total_je_rows,
       SUM(CASE WHEN je.deleted_at IS NULL THEN 1 ELSE 0 END) AS active_je_rows
FROM invoices i
JOIN journal_entries je ON je.invoice_id = i.id
WHERE i.deleted_at IS NULL
GROUP BY i.id, i.invoice_number, i.amount, i.status
HAVING COUNT(je.id) > 0
   AND SUM(CASE WHEN je.deleted_at IS NULL THEN 1 ELSE 0 END) = 0;


-- ============================================================================
-- 3. INVOICE PAID-STATUS VS APPLICATIONS DRIFT
-- ============================================================================
-- CAVEAT (read before treating 3.1 as a bug list): invoices can be settled
-- through TWO parallel mechanisms in this codebase —
--   (a) payment_applications (used by the credit/split/partial flow in
--       PaymentApplicationService and InvoiceController), and
--   (b) a direct payments.invoice_id link with payments.status IN
--       ('success','completed','paid') OR payments.completed = 1 (used by the
--       plain gateway-checkout flow in PaymentController).
-- A gateway-paid invoice legitimately has ZERO payment_applications rows.
-- Query 3.1 therefore reports BOTH signals side by side so you can tell a
-- real gap (both signals low) from a false positive (direct payment covers
-- it, payment_applications just wasn't used for that invoice).
--
-- Non-zero result means: the invoice's `status` column disagrees with the
-- money actually recorded against it — status is either stale or the
-- payment/application step failed partway through.
-- ============================================================================

-- 3.1 Invoices marked 'paid' but underpaid per payment_applications AND
--     underpaid per direct payments (check both columns before flagging)
SELECT * FROM (
    SELECT i.id, i.invoice_number, i.status, i.amount,
           (SELECT COALESCE(SUM(pa.amount), 0) FROM payment_applications pa
              WHERE pa.invoice_id = i.id AND pa.deleted_at IS NULL) AS applied_via_pa,
           (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
              WHERE p.invoice_id = i.id AND p.deleted_at IS NULL
                AND (p.status IN ('success', 'completed', 'paid') OR p.completed = 1)) AS applied_via_direct_payment
    FROM invoices i
    WHERE i.deleted_at IS NULL AND i.status = 'paid'
) t
WHERE (t.applied_via_pa + t.applied_via_direct_payment) < (t.amount - 0.01);

-- 3.2 Invoices marked 'unpaid' but fully covered per payment_applications
--     (application step ran but status update never fired)
SELECT * FROM (
    SELECT i.id, i.invoice_number, i.status, i.amount,
           (SELECT COALESCE(SUM(pa.amount), 0) FROM payment_applications pa
              WHERE pa.invoice_id = i.id AND pa.deleted_at IS NULL) AS applied_via_pa
    FROM invoices i
    WHERE i.deleted_at IS NULL AND i.status = 'unpaid'
) t
WHERE t.applied_via_pa > 0 AND t.applied_via_pa >= (t.amount - 0.01);

-- 3.3 Invoices marked 'partial' whose applied amount doesn't actually look
--     partial (<=0 -> should be unpaid; >= total -> should be paid)
SELECT * FROM (
    SELECT i.id, i.invoice_number, i.status, i.amount,
           (SELECT COALESCE(SUM(pa.amount), 0) FROM payment_applications pa
              WHERE pa.invoice_id = i.id AND pa.deleted_at IS NULL) AS applied_via_pa
    FROM invoices i
    WHERE i.deleted_at IS NULL AND i.status = 'partial'
) t
WHERE t.applied_via_pa <= 0 OR t.applied_via_pa >= (t.amount - 0.01);


-- ============================================================================
-- 4. CREDIT BALANCE DRIFT
-- ============================================================================
-- NOTE ON SCHEMA: `credits` has no `remaining_amount` / `used_amount` column.
-- The app computes "available balance" live, at read time, as
-- SUM(credits.amount) grouped by the ORIGINAL source (Credit::
-- getAvailableBalanceByPayment() / getAvailableBalanceByRefund() in
-- app/Models/Credit.php) — every time credit is spent, a NEW credits row is
-- inserted with a NEGATIVE amount and the SAME payment_id/refund_id as the
-- source row, rather than decrementing a balance column. Separately,
-- payment_applications.credit_id points at the ORIGINAL source credit row
-- (not the negative consumption row) as an audit trail of who-applied-what.
-- These are two independently-maintained ledgers for the same fact; check 4.1
-- verifies they agree.
--
-- Non-zero result on 4.1 means: the running credit ledger (self-consistency
-- inside `credits`) and the formal payment_applications audit trail disagree
-- — either a consumption row was written without its audit-trail sibling, or
-- vice versa. Non-zero on 4.2/4.3 means an individual credits row has the
-- wrong sign for its type, which will silently corrupt every balance
-- calculation built on top of it.
-- ============================================================================

-- 4.1 Credit ledger balance vs payment_applications audit-trail drift, for
--     every TOPUP/REFUND source credit
SELECT * FROM (
    SELECT c.id AS credit_id, c.company_id, c.client_id, c.type, c.amount AS source_amount,
           c.payment_id, c.refund_id,
           (SELECT COALESCE(SUM(c2.amount), 0) FROM credits c2
              WHERE c2.deleted_at IS NULL
                AND ((c.type = 'Topup'  AND c2.payment_id = c.payment_id)
                  OR (c.type = 'Refund' AND c2.refund_id  = c.refund_id))
           ) AS ledger_group_balance,
           (SELECT COALESCE(SUM(pa.amount), 0) FROM payment_applications pa
              WHERE pa.credit_id = c.id AND pa.deleted_at IS NULL) AS applied_via_pa
    FROM credits c
    WHERE c.deleted_at IS NULL AND c.type IN ('Topup', 'Refund')
) t
WHERE ABS(t.ledger_group_balance - (t.source_amount - t.applied_via_pa)) > 0.01;

-- 4.2 Credit CONSUMPTION rows (type='Invoice') with a non-negative amount
--     (every consumption row should be a negative deduction)
SELECT id, client_id, invoice_id, invoice_partial_id, amount, description, created_at
FROM credits
WHERE deleted_at IS NULL AND type = 'Invoice' AND amount >= 0;

-- 4.3 Credit SOURCE rows (type IN ('Topup','Refund')) with a non-positive
--     amount (every source row should be positive)
SELECT id, client_id, type, payment_id, refund_id, amount, description, created_at
FROM credits
WHERE deleted_at IS NULL AND type IN ('Topup', 'Refund') AND amount <= 0;


-- ============================================================================
-- 5. ORPHANS (foreign keys pointing at rows that no longer exist)
-- ============================================================================
-- These are plain LEFT JOIN ... IS NULL checks. Because raw SQL ignores
-- Eloquent's soft-delete global scope, a row that is merely soft-deleted
-- still satisfies the join (it "exists" for this purpose) — these checks
-- only fire for rows that are HARD missing (never existed, or the parent
-- row/table was physically deleted, e.g. a bad manual DELETE or a truncated
-- import). Soft-deleted-but-still-referenced is a DIFFERENT problem, covered
-- in section 9.
--
-- Non-zero result means: a foreign key is dangling. Depending on which side
-- is missing, this ranges from "cosmetic" (an audit note with the wrong
-- description) to "invisible money" (a journal entry with no invoice to
-- explain it, or a payment_applications row nobody can trace back to a
-- payment).
-- ============================================================================

-- 5.1 journal_entries.invoice_id -> missing invoice
SELECT je.id AS je_id, je.invoice_id, je.debit, je.credit, je.company_id
FROM journal_entries je
LEFT JOIN invoices i ON i.id = je.invoice_id
WHERE je.invoice_id IS NOT NULL AND i.id IS NULL;

-- 5.2 journal_entries.account_id -> missing account
SELECT je.id AS je_id, je.account_id, je.debit, je.credit, je.company_id
FROM journal_entries je
LEFT JOIN accounts a ON a.id = je.account_id
WHERE je.account_id IS NOT NULL AND a.id IS NULL;

-- 5.3 payment_applications.payment_id -> missing payment
SELECT pa.id AS pa_id, pa.payment_id, pa.invoice_id, pa.amount
FROM payment_applications pa
LEFT JOIN payments p ON p.id = pa.payment_id
WHERE pa.payment_id IS NOT NULL AND p.id IS NULL;

-- 5.4 payment_applications.invoice_id -> missing invoice
SELECT pa.id AS pa_id, pa.invoice_id, pa.payment_id, pa.amount
FROM payment_applications pa
LEFT JOIN invoices i ON i.id = pa.invoice_id
WHERE pa.invoice_id IS NOT NULL AND i.id IS NULL;

-- 5.5 invoice_details without a parent invoice
SELECT idt.id AS invoice_detail_id, idt.invoice_id, idt.task_id, idt.task_price
FROM invoice_details idt
LEFT JOIN invoices i ON i.id = idt.invoice_id
WHERE i.id IS NULL;

-- 5.6 journal_entries.transaction_id -> missing transaction
SELECT je.id AS je_id, je.transaction_id, je.debit, je.credit
FROM journal_entries je
LEFT JOIN transactions t ON t.id = je.transaction_id
WHERE je.transaction_id IS NOT NULL AND t.id IS NULL;

-- 5.7 payment_applications.credit_id -> missing credit
SELECT pa.id AS pa_id, pa.credit_id, pa.invoice_id, pa.amount
FROM payment_applications pa
LEFT JOIN credits c ON c.id = pa.credit_id
WHERE pa.credit_id IS NOT NULL AND c.id IS NULL;

-- 5.8 invoice_partials.invoice_id -> missing invoice
SELECT ip.id AS invoice_partial_id, ip.invoice_id, ip.amount, ip.status
FROM invoice_partials ip
LEFT JOIN invoices i ON i.id = ip.invoice_id
WHERE i.id IS NULL;

-- 5.9 credits.invoice_id -> missing invoice
SELECT c.id AS credit_id, c.invoice_id, c.type, c.amount
FROM credits c
LEFT JOIN invoices i ON i.id = c.invoice_id
WHERE c.invoice_id IS NOT NULL AND i.id IS NULL;

-- 5.10 credits.refund_id -> missing refund
SELECT c.id AS credit_id, c.refund_id, c.type, c.amount
FROM credits c
LEFT JOIN refunds r ON r.id = c.refund_id
WHERE c.refund_id IS NOT NULL AND r.id IS NULL;

-- 5.11 credits.payment_id (varchar column!) -> missing payment, numeric rows only
SELECT c.id AS credit_id, c.payment_id, c.type, c.amount
FROM credits c
WHERE c.payment_id IS NOT NULL AND c.payment_id != ''
  AND c.payment_id REGEXP '^[0-9]+$'
  AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.id = CAST(c.payment_id AS UNSIGNED));

-- 5.12 refunds.invoice_id -> missing invoice
SELECT r.id AS refund_id, r.refund_number, r.invoice_id, r.total_refund_amount
FROM refunds r
LEFT JOIN invoices i ON i.id = r.invoice_id
WHERE i.id IS NULL;

-- 5.13 transactions.invoice_id -> missing invoice
SELECT t.id AS transaction_id, t.invoice_id, t.amount, t.reference_type
FROM transactions t
LEFT JOIN invoices i ON i.id = t.invoice_id
WHERE t.invoice_id IS NOT NULL AND i.id IS NULL;

-- 5.14 transactions.payment_id -> missing payment
SELECT t.id AS transaction_id, t.payment_id, t.amount, t.reference_type
FROM transactions t
LEFT JOIN payments p ON p.id = t.payment_id
WHERE t.payment_id IS NOT NULL AND p.id IS NULL;

-- 5.15 [data hygiene, not strictly an orphan] credits.payment_id holding a
--      non-numeric value — this silently breaks any join/cast to payments.id
--      (including query 5.11 above, which deliberately skips these rows)
SELECT id AS credit_id, payment_id, type, amount
FROM credits
WHERE payment_id IS NOT NULL AND payment_id != '' AND payment_id NOT REGEXP '^[0-9]+$';


-- ============================================================================
-- 6. POSTINGS AGAINST PARENT (NON-LEAF) ACCOUNTS
-- ============================================================================
-- Only leaf accounts should ever receive a posting; parent/group accounts
-- exist purely to roll up their children on reports. Per Accounting Gap/
-- 02-posting-engine.md Finding (accounts.is_group is never checked before
-- any of the 21 posting call sites), this is a KNOWN, confirmed gap in the
-- application code — these queries measure how much damage it has actually
-- done to the data.
--
-- Non-zero result means: a balance now sits on a rollup account instead of
-- (or in addition to) the leaf accounts underneath it — trial balance and
-- any report that sums by leaf will double-count or misclassify money.
-- ============================================================================

-- 6.1 Postings against an account that structurally HAS CHILDREN (self-join
--     on accounts.parent_id — authoritative, ignores the is_group flag)
SELECT je.id AS je_id, je.account_id, a.name AS account_name, a.is_group, je.debit, je.credit, je.company_id
FROM journal_entries je
JOIN accounts a ON a.id = je.account_id
WHERE EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id);

-- 6.2 Postings against an account explicitly flagged is_group = 1 (the
--     business-logic flag the app itself defines but never enforces)
SELECT je.id AS je_id, je.account_id, a.name AS account_name, a.is_group, je.debit, je.credit, je.company_id
FROM journal_entries je
JOIN accounts a ON a.id = je.account_id
WHERE a.is_group = 1;


-- ============================================================================
-- 7. CROSS-COMPANY CONTAMINATION
-- ============================================================================
-- This is a multi-tenant system (Company -> Branch -> Agent -> Client).
-- `invoices` and `agents` do NOT carry their own company_id column directly;
-- company ownership for an invoice is derived via invoices.client_id ->
-- clients.company_id. `journal_entries`, `accounts`, `payments`, `credits`,
-- `refunds`, and `transactions` DO carry company_id directly.
--
-- Non-zero result means: a posting, application, or reference crosses a
-- tenant boundary — company A's ledger now contains an entry that actually
-- belongs to company B (or vice versa). This is a severe finding: besides
-- being wrong bookkeeping, it can leak one tenant's financial data into
-- another tenant's reports.
-- ============================================================================

-- 7.1 Journal entry's own company_id disagrees with its account's company_id
SELECT je.id AS je_id, je.company_id AS je_company_id, je.account_id, a.company_id AS account_company_id
FROM journal_entries je
JOIN accounts a ON a.id = je.account_id
WHERE je.company_id != a.company_id;

-- 7.2 Journal entry's company_id disagrees with its invoice's owning company
--     (resolved via invoice -> client -> company)
SELECT je.id AS je_id, je.company_id AS je_company_id, je.invoice_id, cl.company_id AS client_company_id
FROM journal_entries je
JOIN invoices i ON i.id = je.invoice_id
JOIN clients cl ON cl.id = i.client_id
WHERE je.invoice_id IS NOT NULL AND je.company_id != cl.company_id;

-- 7.3 Journal entry's company_id disagrees with its linked transaction's company_id
SELECT je.id AS je_id, je.company_id AS je_company_id, je.transaction_id, t.company_id AS transaction_company_id
FROM journal_entries je
JOIN transactions t ON t.id = je.transaction_id
WHERE je.transaction_id IS NOT NULL AND je.company_id != t.company_id;

-- 7.4 payment_applications linking a payment and an invoice that belong to
--     two DIFFERENT companies (payment company vs invoice's client company)
SELECT pa.id AS pa_id, pa.payment_id, pa.invoice_id, p.company_id AS payment_company_id, cl.company_id AS invoice_client_company_id
FROM payment_applications pa
JOIN payments p ON p.id = pa.payment_id
JOIN invoices i ON i.id = pa.invoice_id
JOIN clients cl ON cl.id = i.client_id
WHERE pa.payment_id IS NOT NULL AND p.company_id != cl.company_id;


-- ============================================================================
-- 8. NEGATIVE IMPOSSIBLE VALUES
-- ============================================================================
-- Certain columns should never be negative given the domain rules of this
-- system. Note: `credits.amount` is DELIBERATELY negative for consumption
-- rows (type='Invoice') — see section 4 — so it is NOT included here; its
-- sign is validated by checks 4.2/4.3 instead, which know the correct sign
-- per row type.
--
-- Non-zero result means: a value that can never legitimately be negative
-- has ended up negative — almost always a sign error in the code path that
-- wrote it, and it will flip the direction of every downstream sum.
-- ============================================================================

-- 8.1 journal_entries with a negative debit or credit column
SELECT id, account_id, debit, credit, company_id, invoice_id
FROM journal_entries
WHERE debit < 0 OR credit < 0;

-- 8.2 invoices with a negative amount / sub_amount / invoice_charge
SELECT id, invoice_number, amount, sub_amount, invoice_charge, status
FROM invoices
WHERE amount < 0 OR sub_amount < 0 OR invoice_charge < 0;

-- 8.3 payments with a negative amount
SELECT id, invoice_id, amount, status, payment_gateway
FROM payments
WHERE amount < 0;

-- 8.4 payment_applications with a negative amount
SELECT id, payment_id, invoice_id, credit_id, amount
FROM payment_applications
WHERE amount < 0;

-- 8.5 refunds with negative refund amount fields
SELECT id, refund_number, invoice_id, total_refund_amount, total_refund_charge, total_nett_refund
FROM refunds
WHERE total_refund_amount < 0 OR total_refund_charge < 0 OR total_nett_refund < 0;


-- ============================================================================
-- 9. SOFT-DELETE INCONSISTENCIES
-- ============================================================================
-- All ledger-adjacent tables in this schema use soft deletes (deleted_at).
-- An "active" (non-soft-deleted) child row should never point at a parent
-- row that has itself been soft-deleted — that parent is supposed to be
-- gone from every report, but the child keeps it alive by reference.
-- There is already a maintenance command for part of this
-- (`php artisan credits:soft-delete-orphaned`, app/Console/Commands/
-- SoftDeleteOrphanedCreditsAndPaymentApplications.php) which cleans up
-- payment_applications/credits left behind by soft-deleted invoices — a
-- non-zero count on 9.3/9.6 below means that command needs to be (re-)run;
-- it currently only handles invoices, not payments/transactions/credits as
-- the parent, which is why checks 9.2/9.4/9.5 exist as their own queries.
--
-- Non-zero result means: a soft-deleted record is still being counted as
-- live through an active child row — reports that respect soft-deletes on
-- the parent but join through the child will silently resurrect deleted
-- financial data.
-- ============================================================================

-- 9.1 Active journal_entries referencing a soft-deleted invoice
SELECT je.id AS je_id, je.invoice_id, i.deleted_at AS invoice_deleted_at
FROM journal_entries je
JOIN invoices i ON i.id = je.invoice_id
WHERE je.deleted_at IS NULL AND i.deleted_at IS NOT NULL;

-- 9.2 Active journal_entries referencing a soft-deleted transaction
SELECT je.id AS je_id, je.transaction_id, t.deleted_at AS transaction_deleted_at
FROM journal_entries je
JOIN transactions t ON t.id = je.transaction_id
WHERE je.deleted_at IS NULL AND t.deleted_at IS NOT NULL;

-- 9.3 Active payment_applications referencing a soft-deleted invoice
--     (this is exactly what `credits:soft-delete-orphaned` is meant to clean up)
SELECT pa.id AS pa_id, pa.invoice_id, i.deleted_at AS invoice_deleted_at
FROM payment_applications pa
JOIN invoices i ON i.id = pa.invoice_id
WHERE pa.deleted_at IS NULL AND i.deleted_at IS NOT NULL;

-- 9.4 Active payment_applications referencing a soft-deleted payment
SELECT pa.id AS pa_id, pa.payment_id, p.deleted_at AS payment_deleted_at
FROM payment_applications pa
JOIN payments p ON p.id = pa.payment_id
WHERE pa.deleted_at IS NULL AND p.deleted_at IS NOT NULL;

-- 9.5 Active payment_applications referencing a soft-deleted credit
SELECT pa.id AS pa_id, pa.credit_id, c.deleted_at AS credit_deleted_at
FROM payment_applications pa
JOIN credits c ON c.id = pa.credit_id
WHERE pa.deleted_at IS NULL AND c.deleted_at IS NOT NULL;

-- 9.6 Active credits referencing a soft-deleted invoice
--     (also within scope of `credits:soft-delete-orphaned`)
SELECT c.id AS credit_id, c.invoice_id, i.deleted_at AS invoice_deleted_at
FROM credits c
JOIN invoices i ON i.id = c.invoice_id
WHERE c.deleted_at IS NULL AND i.deleted_at IS NOT NULL;

-- 9.7 Active invoice_details referencing a soft-deleted invoice
SELECT idt.id AS invoice_detail_id, idt.invoice_id, i.deleted_at AS invoice_deleted_at
FROM invoice_details idt
JOIN invoices i ON i.id = idt.invoice_id
WHERE idt.deleted_at IS NULL AND i.deleted_at IS NOT NULL;

-- 9.8 Active invoice_partials referencing a soft-deleted invoice
SELECT ip.id AS invoice_partial_id, ip.invoice_id, i.deleted_at AS invoice_deleted_at
FROM invoice_partials ip
JOIN invoices i ON i.id = ip.invoice_id
WHERE ip.deleted_at IS NULL AND i.deleted_at IS NOT NULL;


-- ============================================================================
-- BONUS — beyond the required minimum, cheap to run, found naturally while
-- inspecting the schema. Not required by the audit spec; keep or drop freely.
-- ============================================================================

-- B.1 Journal entries posted to an account flagged disabled = 1
SELECT je.id AS je_id, je.account_id, a.name AS account_name, a.disabled, je.debit, je.credit
FROM journal_entries je
JOIN accounts a ON a.id = je.account_id
WHERE a.disabled = 1;

-- ============================================================================
-- END OF PACK
-- ============================================================================
