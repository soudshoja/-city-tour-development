# Trial Balance Report — Business Guide

## What Is a Trial Balance?

A Trial Balance is a foundational accounting report that lists all general ledger accounts with their **debit** and **credit** balances at a specific point in time. The fundamental rule of double-entry bookkeeping:

$$\sum \text{Debits} = \sum \text{Credits}$$

If they don't match, there's an error in the bookkeeping entries.

### Why It Matters

| Purpose | Description |
|---------|-------------|
| Error Detection | Catches one-sided entries, posting errors, and mathematical mistakes |
| Period-End Preparation | Starting point for Profit & Loss and Balance Sheet |
| Audit Readiness | First document auditors request — proves the books are in balance |
| Reconciliation | Validates all transactions have been properly recorded in double-entry |

### In a Travel Agency

Every financial event creates balanced journal entries:

| Event | Debit | Credit |
|-------|-------|--------|
| Invoice created | Accounts Receivable (Client) | Revenue (Sales) |
| Payment received | Bank / Payment Gateway | Accounts Receivable (Client) |
| Supplier payment | Accounts Payable (Supplier) | Bank Account |
| Refund issued | Refund Expense | Bank / Client Credit |
| Agent commission | Commission Expense | Accounts Payable (Agent) |

The trial balance aggregates all these entries per account and verifies the books balance.

---

## Account Balance Rules

| Account Type | Normal Balance | Balance Calculation |
|-------------|---------------|---------------------|
| **Assets** | Debit | Debit − Credit |
| **Expenses** | Debit | Debit − Credit |
| **Liabilities** | Credit | Credit − Debit |
| **Income** | Credit | Credit − Debit |
| **Equity** | Credit | Credit − Debit |

This feeds directly into the Balance Sheet equation:

$$\text{Assets} = \text{Liabilities} + \text{Equity} + (\text{Income} - \text{Expenses})$$

---

## Features Delivered

### Report Capabilities
- **Leaf-level accounts** grouped by root category (Assets, Liabilities, Equity, Income, Expenses)
- **7-column layout**: Code, Account Name, Opening Balance, Debit, Credit, Closing Balance, Action
- **Opening Balance**: net balance of all entries before the report start date, shown as a single value with Dr/Cr indicator
- **Closing Balance**: opening + period movement, shown with Dr/Cr indicator
- **Balance status**: green badge when balanced, red when out of balance with the difference amount
- **Unbalanced transactions**: lists any transactions where debit ≠ credit, with directional Dr/Cr indicators and footer totals (Total Excess Debit, Total Excess Credit, Net Imbalance)

### Filtering
- **Date range**: from/to dates, defaults to current month
- **Branch**: optional filter for multi-branch companies
- **Show zero balances**: toggle to include accounts with no activity in the period

### Export & Output
- **PDF**: professional A4 landscape format with company header, grouped accounts, and generation timestamp
- **CSV**: Excel-compatible with Code, Account Name, Root Type, Debit, Credit columns
- **Print**: browser print dialog with print-friendly CSS
- **Drill-down**: click any account name to view its journal entry ledger

### Access Control
- Allowed: ADMIN, COMPANY, ACCOUNTANT roles
- Blocked: AGENT role (403 Forbidden)
- Company-scoped: users see only their own company's data

---

## How to Use

1. **Navigate** to Reports → Trial Balance (or `/reports/trial-balance`)
2. **Set dates** — defaults to 1st of current month through today
3. **Generate** — report loads with balance status indicator
4. **Review** — check if balanced; if not, examine the Unbalanced Transactions section
5. **Export** — PDF for auditors, CSV for Excel analysis, Print for hard copies

### Reading the Report

- **Opening Balance**: what the account balance was before the report period started
- **Debit / Credit**: activity during the selected period
- **Closing Balance**: the account's balance at the end of the period
- **Dr/Cr indicator**: shows whether the net balance is a debit or credit based on the account's normal balance direction

### When "Out of Balance"

1. Check the Unbalanced Transactions section at the bottom
2. Each row shows a transaction where debit ≠ credit, with the direction (Dr = excess debit, Cr = excess credit)
3. Click the transaction to investigate and correct

### Terminology Note

The **trial balance difference** (total debits minus total credits across all accounts) and the **net imbalance** from the unbalanced transactions list measure different things:
- Trial balance difference includes all entries, even those without a `transaction_id`
- Net imbalance only counts entries grouped by transaction where debit ≠ credit
- They are not expected to match

---

## Report Types

This implementation is an **Unadjusted Trial Balance** — raw balances from all journal entries for the period. Future enhancements could include:
- Adjusted Trial Balance (after accruals, deferrals, depreciation)
- Post-Closing Trial Balance (only permanent accounts after closing temporaries)
- Comparative Trial Balance (period vs period)
