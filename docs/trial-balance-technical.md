# Trial Balance Report — Technical Reference

## Architecture

```
Route (web.php)
  ├── GET /reports/trial-balance           → ReportController::trialBalance()        → Blade view
  ├── GET /reports/trial-balance/pdf       → ReportController::trialBalancePdf()     → PDF download
  ├── GET /reports/trial-balance/export    → ReportController::trialBalanceExport()  → CSV download
  └── GET /reports/trial-balance/validation→ ReportController::trialBalanceValidation()→ JSON
                │
                └── TrialBalanceService (business logic & DB queries)
```

## Files

| File | Purpose |
|------|---------|
| `app/Services/TrialBalanceService.php` | Core business logic: queries, calculations, grouping |
| `app/Http/Controllers/ReportController.php` | 4 methods added for trial balance endpoints |
| `resources/views/reports/trial-balance.blade.php` | Interactive HTML report (Tailwind, dark mode, `<x-app-layout>`) |
| `resources/views/reports/pdf/trial-balance.blade.php` | PDF export template (A4 landscape, inline CSS for DomPDF) |
| `routes/web.php` | 4 routes inside the `reports` middleware group |

No new database tables, migrations, or composer packages were needed.

---

## Service: `TrialBalanceService`

### `generate(int $companyId, Carbon $dateFrom, Carbon $dateTo, array $options = []): array`

Main entry point. Returns:
- `accounts` — leaf-level accounts with period debit/credit
- `grouped` — accounts grouped by root category with subtotals, sorted: Assets → Liabilities → Equity → Income → Expenses
- `totals` — grand total debit, credit, difference, `is_balanced` flag
- `opening_balances` — pre-period balances per account
- `unbalanced_transactions` — transactions where debit ≠ credit

### `getAccountBalances()`

Fetches leaf-level accounts with their debit/credit totals for the date range.

**Leaf-only filter**: `NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)` — ensures only accounts with no children are included.

**Date filter placement**: The `transaction_date BETWEEN` clause is inside the LEFT JOIN condition, not in the WHERE clause. This is critical — if the date filter were in WHERE, accounts with zero activity in the period would be excluded from results even when `show_zero` is enabled, because the LEFT JOIN would produce NULL rows that the WHERE clause would then filter out.

### `getOpeningBalances()` (public)

Sums all journal entries before `dateFrom` per leaf account. Made public so other services can reuse it (e.g., for balance sheet or other reports).

**Opening balance for display**: For debit-normal accounts (Assets, Expenses), opening = debit − credit. For credit-normal accounts (Liabilities, Income, Equity), opening = credit − debit. The view displays the absolute value with a Dr/Cr suffix.

### `findUnbalancedTransactions()`

Groups journal entries by `transaction_id` and finds transactions where `ABS(SUM(debit) - SUM(credit)) > 0.001`.

Returns both `imbalance` (absolute) and `signed_imbalance` (directional: positive = excess debit, negative = excess credit). The view shows direction with color coding (red for Dr, amber for Cr) and footer totals.

### `getNormalBalance()`

Returns `'debit'` for Assets and Expenses, `'credit'` for everything else.

### `groupByRootCategory()`

Groups accounts by their root account name. Sort order is hardcoded: `['Assets', 'Liabilities', 'Equity', 'Income', 'Expenses']`.

---

## Key SQL Queries

### Leaf Account Balances

```sql
SELECT a.id, a.code, a.name, a.root_id, root.name AS root_name,
       COALESCE(SUM(je.debit), 0) AS total_debit,
       COALESCE(SUM(je.credit), 0) AS total_credit
FROM accounts a
LEFT JOIN journal_entries je
    ON je.account_id = a.id
    AND je.deleted_at IS NULL
    AND je.transaction_date BETWEEN :date_from AND :date_to   -- inside JOIN, not WHERE
JOIN accounts root ON root.id = a.root_id
WHERE a.company_id = :company_id
  AND a.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)
GROUP BY a.id, a.code, a.name, a.root_id, root.name
```

When `show_zero = false`, a HAVING clause filters: `COALESCE(SUM(je.debit), 0) != 0 OR COALESCE(SUM(je.credit), 0) != 0`

### Opening Balances

```sql
SELECT a.id,
       COALESCE(SUM(je.debit), 0) AS opening_debit,
       COALESCE(SUM(je.credit), 0) AS opening_credit
FROM accounts a
LEFT JOIN journal_entries je
    ON je.account_id = a.id
    AND je.deleted_at IS NULL
    AND je.transaction_date < :date_from
WHERE a.company_id = :company_id
  AND NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)
GROUP BY a.id
```

### Unbalanced Transactions

```sql
SELECT t.id, t.name, t.reference_number, t.transaction_date,
       SUM(je.debit) AS total_debit, SUM(je.credit) AS total_credit,
       ABS(SUM(je.debit) - SUM(je.credit)) AS imbalance,
       (SUM(je.debit) - SUM(je.credit)) AS signed_imbalance
FROM transactions t
JOIN journal_entries je ON je.transaction_id = t.id AND je.deleted_at IS NULL
WHERE t.company_id = :company_id
GROUP BY t.id, t.name, t.reference_number, t.transaction_date
HAVING ABS(SUM(je.debit) - SUM(je.credit)) > 0.001
ORDER BY t.transaction_date DESC
```

---

## Design Decisions & Bug Fixes

### 1. Date Filter in LEFT JOIN (not WHERE)

**Problem**: When `show_zero` was enabled, accounts with no activity in the date range were still hidden. The `whereBetween('je.transaction_date', ...)` was in the WHERE clause, which filtered out NULL rows from the LEFT JOIN.

**Fix**: Moved the date condition into the LEFT JOIN's ON clause. Accounts with zero activity now correctly return with `COALESCE` values of 0.

### 2. Opening Balance as Single Net Value

**Decision**: Opening balance is displayed as a single net number with Dr/Cr indicator, not as separate debit/credit columns. For a debit-normal account (Assets/Expenses), the net = debit − credit. For credit-normal, net = credit − debit. Display shows the absolute value with "Dr" or "Cr" suffix.

### 3. Signed Imbalance Direction

**Problem**: The unbalanced transactions list used `ABS()` which hid whether each transaction had excess debit or excess credit. The sum of absolute imbalances didn't reconcile to anything meaningful.

**Fix**: Added `signed_imbalance` (positive = excess debit, negative = excess credit). View shows Dr/Cr per row with color coding and footer totals: Total Excess Debit, Total Excess Credit, Net Imbalance.

### 4. Public `getOpeningBalances()`

Changed from `private` to `public` to allow other services (e.g., future balance sheet) to reuse the opening balance logic without duplicating the query.

### 5. Comment Cleanup

Removed all "what the code does" comments (redundant with readable code). Kept only "why" comments explaining non-obvious decisions.

---

## Controller Methods (in ReportController)

All 4 methods follow the same pattern:
1. Authenticate user, check role (ADMIN/COMPANY/ACCOUNTANT — 403 for AGENT)
2. Get `companyId` via `getCompanyId($user)` helper
3. Parse date inputs with defaults (`startOfMonth` to `today`)
4. Call `TrialBalanceService::generate()`
5. Return view / PDF / CSV / JSON

The controller passes explicit filter defaults: `'branch_id' => $branchId, 'show_zero' => $showZero` to avoid undefined array key errors (fixed from `$request->only()` which omits absent keys).

---

## View: 7-Column Layout

| Column | Content |
|--------|---------|
| Code | Account code from COA |
| Account Name | Clickable link to journal entry ledger |
| Opening Balance | Net pre-period balance with Dr/Cr indicator |
| Debit | Sum of debits in period |
| Credit | Sum of credits in period |
| Closing Balance | Opening + period movement, with Dr/Cr indicator |
| Action | Arrow link to account ledger |

Accounts are grouped under root category headers with subtotal rows. Grand total row at the bottom with balance status (green/red badge).

Built with Tailwind CSS, dark mode support (`dark:` prefixes), and `<x-app-layout>` wrapper matching the existing application style.

---

## Security

- **Authentication**: Required (redirect if not logged in)
- **Authorization**: Role-based (ADMIN, COMPANY, ACCOUNTANT — 403 for others)
- **Data isolation**: Company-scoped via `getCompanyId()` helper and Account model's global scope
- **SQL injection**: Protected via Laravel query builder (parameterized queries)
- **XSS**: Protected via Blade `{{ }}` escaping

---

## Data Sources

| Source Controller | What It Creates | TB Impact |
|-------------------|-----------------|-----------|
| `InvoiceController::addJournalEntry()` | Revenue, A/R, Supplier Payable, Commission | Main source of income/expense entries |
| `PaymentController::createInvoicePaymentCOA()` | Bank, A/R reduction | Payment receipts |
| `BankPaymentController::store()` | A/P reduction, bank reduction | Supplier payments |
| `ReceiptVoucherController::store()` | Various receipt entries | Manual receipts |
| `CoaController::submitVoucher()` | Custom journal entries | Manual adjustments |

---

## Recommended Indexes

```sql
ALTER TABLE journal_entries ADD INDEX (account_id);
ALTER TABLE journal_entries ADD INDEX (transaction_date);
ALTER TABLE journal_entries ADD INDEX (company_id);
ALTER TABLE accounts ADD INDEX (company_id);
ALTER TABLE accounts ADD INDEX (parent_id);
ALTER TABLE accounts ADD INDEX (root_id);
```
