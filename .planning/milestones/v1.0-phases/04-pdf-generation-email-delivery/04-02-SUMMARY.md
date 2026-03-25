---
phase: 04-pdf-generation-email-delivery
plan: 02
subsystem: email-integration
tags:
  - email
  - pdf-generation
  - bulk-invoices
  - queue-jobs
  - ui-enhancement
dependency-graph:
  requires:
    - "04-01: Email infrastructure (BulkInvoicesMail, SendInvoiceEmailsJob)"
    - "03-02: CreateBulkInvoicesJob for invoice creation"
    - "02-02: Success page UI"
  provides:
    - "Automatic email dispatch after invoice creation"
    - "PDF download links on success page"
    - "Complete invoice delivery pipeline"
  affects:
    - "Future: Email monitoring/logging"
tech-stack:
  added:
    - "Queue chaining with afterCommit()"
    - "Separate queue for emails ('emails' vs 'invoices')"
  patterns:
    - "Dispatch outside transaction to prevent DB lock hold during PDF generation"
    - "onQueue('emails') for independent email worker scaling"
    - "afterCommit() to ensure status committed before email job starts"
key-files:
  created: []
  modified:
    - path: "app/Jobs/CreateBulkInvoicesJob.php"
      lines: 191
      purpose: "Dispatches SendInvoiceEmailsJob after transaction commits"
      changes: "Added email job dispatch (lines 180-188)"
    - path: "resources/views/bulk-invoice/success.blade.php"
      lines: 109
      purpose: "Enhanced success page with PDF download links"
      changes: "Added 'Download PDF' link and email notification message"
decisions:
  - choice: "Dispatch email job AFTER transaction (not inside)"
    reasoning: "Research Pitfall 1: PDF generation is CPU-intensive and must not hold DB locks"
    impact: "Transaction completes faster, prevents deadlocks, allows independent email failure"
  - choice: "Use separate 'emails' queue"
    reasoning: "Allows independent scaling of email workers vs invoice workers"
    impact: "Email failures don't block invoice creation queue"
  - choice: "Use afterCommit() on email dispatch"
    reasoning: "Ensures 'completed' status is committed before email job starts"
    impact: "Email job sees correct BulkUpload status, matches BulkInvoiceController pattern"
  - choice: "Use route('invoice.pdf') for download links"
    reasoning: "Existing route already tested and working from Phase 3 research"
    impact: "No new code needed, consistent with existing invoice PDF generation"
metrics:
  duration: 2
  completed: 2026-02-13
  tasks: 2
  files: 2
  commits: 2
---

# Phase 04 Plan 02: Integration with CreateBulkInvoicesJob Summary

**One-liner:** Integrated SendInvoiceEmailsJob dispatch into CreateBulkInvoicesJob (after transaction) and added PDF download links to success page, completing the full invoice pipeline: upload → validate → preview → approve → create → email.

## What Was Built

### Integration Points

1. **CreateBulkInvoicesJob.php** - Modified to:
   - Dispatch `SendInvoiceEmailsJob` AFTER `DB::transaction` completes (line 182)
   - Use `->onQueue('emails')` to put email jobs on separate queue from invoice creation
   - Use `->afterCommit()` to ensure email job only starts after 'completed' status committed
   - Add logging for email job dispatch
   - **Critical:** Dispatch is at line 182, AFTER transaction closure at line 178

2. **success.blade.php** - Enhanced to:
   - Add "Download PDF" link for each created invoice (alongside existing "View" link)
   - Add email notification message: "Invoice PDFs are being emailed to the company accountant and uploading agent"
   - Use existing `route('invoice.pdf', [$companyId, $invoiceNumber])` route
   - Preserve all existing UI elements (summary card, status indicators, navigation)

## How It Works

```php
// CreateBulkInvoicesJob::handle() flow:
DB::transaction(function () {
    // Create all invoices
    // Update status to 'completed'
}); // Transaction ends here

// Email dispatch happens AFTER transaction commits
SendInvoiceEmailsJob::dispatch($bulkUploadId)
    ->onQueue('emails')
    ->afterCommit();
```

**Complete Pipeline:**
1. User uploads Excel file → `BulkInvoiceController::upload`
2. System validates → `BulkInvoiceController::preview`
3. User approves → `BulkInvoiceController::approve`
4. CreateBulkInvoicesJob creates all invoices in transaction
5. **[NEW]** SendInvoiceEmailsJob dispatches after transaction
6. **[NEW]** Success page shows PDF download links
7. SendInvoiceEmailsJob sends emails with PDF attachments to accountant and agent

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification steps passed:

✓ `php -l app/Jobs/CreateBulkInvoicesJob.php` - No syntax errors
✓ `./vendor/bin/pint app/Jobs/CreateBulkInvoicesJob.php` - Passes formatting
✓ `SendInvoiceEmailsJob::dispatch` exists in file (line 182)
✓ `->onQueue('emails')` exists (line 183)
✓ `->afterCommit()` exists (line 184)
✓ No import needed (same namespace App\Jobs) - Pint confirmed this
✓ Dispatch is AFTER transaction (line 182 > line 178 where transaction closes)
✓ `php artisan view:cache` - Blade templates compile successfully
✓ "Download PDF" text exists in success.blade.php
✓ `route('invoice.pdf')` exists in invoice list section
✓ Email notification message exists in completed state section
✓ "View" link still exists (not removed)

## Task Breakdown

| Task | Description                                      | Files                                        | Commit   |
| ---- | ------------------------------------------------ | -------------------------------------------- | -------- |
| 1    | Dispatch SendInvoiceEmailsJob from CreateBulkInvoicesJob | app/Jobs/CreateBulkInvoicesJob.php           | 1d7c5217 |
| 2    | Add PDF Download Links to Success Page          | resources/views/bulk-invoice/success.blade.php | c7de8f3e |

## Success Criteria Met

✓ SendInvoiceEmailsJob dispatches from CreateBulkInvoicesJob after transaction, not inside it
✓ Email job uses 'emails' queue (separate from 'invoices' queue)
✓ afterCommit() prevents email job from starting before 'completed' status is committed
✓ Each invoice on success page has a working PDF download link
✓ Completed state message mentions email delivery status
✓ All existing success page elements preserved (summary card, status indicators, navigation)

## Integration Points

**Email Flow:**
- CreateBulkInvoicesJob completes → dispatches SendInvoiceEmailsJob
- SendInvoiceEmailsJob loads invoices → generates BulkInvoicesMail
- BulkInvoicesMail generates PDF attachments → sends to accountant and agent
- PDFs use existing `invoice.pdf.invoice` template with `isPdf => true`

**UI Flow:**
- Success page loads with `$invoices` collection (when status='completed')
- Each invoice shows "View" link (existing) and "Download PDF" link (new)
- PDF download uses existing `InvoiceController::generatePdf` route
- Email notification message visible in completed state

**Queue Architecture:**
- `invoices` queue: CreateBulkInvoicesJob (handles invoice creation)
- `emails` queue: SendInvoiceEmailsJob (handles PDF email delivery)
- Workers can scale independently based on load

## Self-Check: PASSED

**Modified files verified:**
```
FOUND: app/Jobs/CreateBulkInvoicesJob.php
FOUND: resources/views/bulk-invoice/success.blade.php
```

**Commits verified:**
```
FOUND: 1d7c5217
FOUND: c7de8f3e
```

**Dispatch location verified:**
- Transaction starts: line 80
- Transaction ends: line 178
- Email dispatch: line 182 (AFTER transaction ✓)

All claimed files exist. All commit hashes exist in git history. Dispatch is correctly placed outside transaction. Summary is accurate.
