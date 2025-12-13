# Generate Missing Receipt Vouchers Command

## Overview
This command checks past invoices with cash payment type and generates receipt vouchers for those that don't already have them.

## Command Information
- **Command**: `app:generate-missing-receipt-vouchers`
- **Location**: `app/Console/Commands/GenerateMissingReceiptVouchers.php`

## Usage

### Basic Usage
To check and generate receipt vouchers for all cash invoices:

```bash
php artisan app:generate-missing-receipt-vouchers
```

### Generate for a Specific Invoice
To generate a receipt voucher for a specific invoice ID:

```bash
php artisan app:generate-missing-receipt-vouchers --invoice_id=123
```

## What the Command Does

1. **Finds Cash Invoices**: 
   - Searches for invoices where `payment_type = 'cash'`
   - Also checks for invoices with `InvoicePartial` records where `payment_gateway = 'Cash'`

2. **Checks Existing Receipt Vouchers**:
   - For each cash invoice found, checks if a receipt voucher already exists in the `invoice_receipts` table
   - Skips invoices that already have receipt vouchers

3. **Generates Missing Receipt Vouchers**:
   - Uses the `ReceiptVoucherController::autoGenerate()` method
   - Creates the necessary records in:
     - `invoice_receipts` table
     - `transactions` table
     - `journal_entries` table (via the journal entry process)
   - Updates `invoice_partials` if needed

4. **Provides Detailed Output**:
   - Shows progress bar during processing
   - Displays success/error messages for each invoice
   - Provides a summary at the end with counts of:
     - Total invoices checked
     - Receipt vouchers generated
     - Invoices skipped (already had vouchers)
     - Errors encountered

## Output Example

```
Starting to check and generate missing receipt vouchers for cash invoices...
Found 10 cash invoice(s) to check.
 10/10 [============================] 100%

✓ Generated receipt voucher for Invoice #INV-2024-00001 (Amount: KWD 150.00)

Invoice #INV-2024-00002 already has a receipt voucher. Skipping...

✓ Generated receipt voucher for Invoice #INV-2024-00003 (Amount: KWD 200.00)

=== Summary ===
Total cash invoices checked: 10
Receipt vouchers generated: 7
Already had receipt vouchers (skipped): 2
Errors: 1
```

## Error Handling

- All database operations are wrapped in transactions
- Errors are logged to the Laravel log file with full details
- Failed operations are rolled back automatically
- The command continues processing even if one invoice fails

## Requirements

The command requires:
- Valid cash invoices with proper relationships:
  - `client` relationship must exist
  - `agent.branch.company` relationships must exist
- The invoice must have either:
  - `payment_type = 'cash'`, OR
  - An `InvoicePartial` with `payment_gateway = 'Cash'`

## Related Models & Controllers

- **Models**: `Invoice`, `InvoicePartial`, `InvoiceReceipt`, `Transaction`, `JournalEntry`
- **Controllers**: `ReceiptVoucherController`
- **Methods**: `ReceiptVoucherController::autoGenerate()`

## Logging

All operations are logged with detailed information including:
- Invoice ID and number
- Success/failure status
- Error messages and stack traces (if any)
- Reference numbers generated

## Use Cases

1. **Data Migration**: After implementing the receipt voucher feature, backfill vouchers for historical cash invoices
2. **Error Recovery**: Regenerate receipt vouchers for invoices where the generation failed previously
3. **Audit/Compliance**: Ensure all cash invoices have proper receipt vouchers for accounting purposes
4. **Specific Invoice Fix**: Generate a receipt voucher for a specific invoice that's missing one

## Notes

- The command is idempotent - running it multiple times won't create duplicate receipt vouchers
- Only invoices without existing receipt vouchers will be processed
- The amount used for the receipt voucher is taken from the `InvoicePartial` if available, otherwise from the main `Invoice` record
- The type (full/partial) is determined from the `InvoicePartial` record
