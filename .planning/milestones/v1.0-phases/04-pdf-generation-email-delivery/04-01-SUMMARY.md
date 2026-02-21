---
phase: 04-pdf-generation-email-delivery
plan: 01
subsystem: email-infrastructure
tags:
  - email
  - pdf-generation
  - bulk-invoices
  - queue-jobs
  - mailable
dependency-graph:
  requires:
    - "03-01: Invoice model and relationships"
    - "03-02: CreateBulkInvoicesJob for invoice creation"
  provides:
    - "BulkInvoicesMail: Generates PDF attachments for bulk invoices"
    - "SendInvoiceEmailsJob: Queue job for email delivery"
    - "Email template: Professional invoice summary with table"
  affects:
    - "04-02: Integration with CreateBulkInvoicesJob"
tech-stack:
  added:
    - "Laravel 11 Mail::to()->queue() pattern"
    - "Laravel 11 Attachment::fromData() for PDF generation"
    - "Barryvdh\DomPDF for in-memory PDF rendering"
  patterns:
    - "Mailable without ShouldQueue (job handles queueing)"
    - "Eager loading relationships for PDF generation"
    - "Null-safe email checks with logging"
    - "Guard clauses for status validation"
key-files:
  created:
    - path: "app/Mail/BulkInvoicesMail.php"
      lines: 130
      purpose: "Mailable class generating one PDF per invoice using existing invoice.pdf.invoice template"
    - path: "resources/views/email/bulk-invoices.blade.php"
      lines: 109
      purpose: "Email body template showing professional invoice summary table"
    - path: "app/Jobs/SendInvoiceEmailsJob.php"
      lines: 145
      purpose: "Queue job sending emails to company accountant and agent"
  modified: []
decisions:
  - choice: "Serialize only bulkUploadId (not Eloquent model) in job constructor"
    reasoning: "Anti-pattern from research - prevents serialization issues in queue"
    impact: "More reliable queue processing, follows best practices"
  - choice: "No ShouldQueue on Mailable class"
    reasoning: "SendInvoiceEmailsJob handles queueing"
    impact: "Clear separation of concerns, explicit queue control"
  - choice: "Email failure does not update BulkUpload status"
    reasoning: "Invoices already created successfully, email is just notification"
    impact: "Non-critical failure handling, invoices remain valid"
  - choice: "Generate PDFs in attachments() method (not build())"
    reasoning: "Laravel 11 pattern for dynamic attachments"
    impact: "PDFs generated on-demand when email queued"
metrics:
  duration: 1
  completed: 2026-02-13
  tasks: 2
  files: 3
  commits: 2
---

# Phase 04 Plan 01: Email Infrastructure for Bulk Invoices Summary

**One-liner:** Created BulkInvoicesMail mailable with PDF generation using Laravel 11 Attachment::fromData pattern, SendInvoiceEmailsJob for delivery to company accountant and agent, and professional email template with invoice summary table.

## What Was Built

### Core Components

1. **BulkInvoicesMail.php** - Mailable class that:
   - Accepts `int $bulkUploadId` (not Eloquent model - serialization anti-pattern)
   - Loads BulkUpload with `agent.branch.company` eager loading
   - Loads invoices with all necessary relationships (client, agent, invoiceDetails, task details)
   - Returns email view with invoice summary
   - Generates one PDF attachment per invoice in `attachments()` method
   - Uses `Pdf::loadView('invoice.pdf.invoice')` with `isPdf => true` flag
   - Uses Laravel 11 `Attachment::fromData()` pattern for in-memory PDF generation
   - Does NOT implement ShouldQueue (job handles queueing)

2. **bulk-invoices.blade.php** - Email template that:
   - Shows professional header with company branding
   - Displays bulk upload filename
   - Shows invoice summary table: Invoice Number | Client | Date | Amount
   - Displays total amount summary
   - Includes "PDFs attached" notice
   - Footer with automated message disclaimer
   - Inline CSS (no external resources - DomPDF constraint)

3. **SendInvoiceEmailsJob.php** - Queue job that:
   - Implements ShouldQueue with tries=3, backoff=[10,30,60], timeout=300
   - Accepts `int $bulkUploadId` in constructor
   - Guard clause: checks status === 'completed' (skips if not)
   - Guard clause: checks invoice_ids not empty (skips if empty)
   - Sends BulkInvoicesMail to `$company->email` (accountant)
   - Sends BulkInvoicesMail to `$agent->email` (uploading agent)
   - Null-safe checks on both emails with appropriate logging
   - `failed()` method logs error but does NOT update BulkUpload status (non-critical failure)

## How It Works

```php
// Usage (will be integrated in 04-02):
SendInvoiceEmailsJob::dispatch($bulkUploadId);

// Flow:
1. SendInvoiceEmailsJob loads BulkUpload
2. Validates status='completed' and invoice_ids exist
3. Queues BulkInvoicesMail to company email
4. Queues BulkInvoicesMail to agent email
5. BulkInvoicesMail loads invoices and generates view
6. attachments() generates one PDF per invoice in memory
7. Each PDF uses existing invoice.pdf.invoice template with isPdf=true
8. Email delivered with all PDFs attached
```

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification steps passed:

✓ `php -l` - No syntax errors in BulkInvoicesMail.php or SendInvoiceEmailsJob.php
✓ `./vendor/bin/pint` - All files pass Laravel formatting
✓ `php artisan view:cache` - Blade templates compile successfully
✓ `Pdf::loadView('invoice.pdf.invoice')` exists in BulkInvoicesMail.php
✓ `Attachment::fromData` exists in BulkInvoicesMail.php
✓ `isPdf => true` passed to PDF view
✓ `implements ShouldQueue` exists in SendInvoiceEmailsJob
✓ `Mail::to($company->email)` exists
✓ `Mail::to($agent->email)` exists
✓ `BulkInvoicesMail` used correctly
✓ Guard clause checks `status === 'completed'`
✓ `failed()` method exists but does NOT update BulkUpload status
✓ No deprecated `$this->attach()` pattern (uses `attachments()` method)
✓ No Eloquent models in job constructor (only `int $bulkUploadId`)

## Task Breakdown

| Task | Description                                   | Files                                                                     | Commit   |
| ---- | --------------------------------------------- | ------------------------------------------------------------------------- | -------- |
| 1    | Create BulkInvoicesMail and email template    | app/Mail/BulkInvoicesMail.php, resources/views/email/bulk-invoices.blade.php | 6593f60e |
| 2    | Create SendInvoiceEmailsJob queue job         | app/Jobs/SendInvoiceEmailsJob.php                                         | 43b6a6d0 |

## Success Criteria Met

✓ BulkInvoicesMail generates one PDF attachment per invoice using existing invoice.pdf.invoice template
✓ SendInvoiceEmailsJob sends to company->email (accountant) AND agent->email with null-safe checks
✓ Email template shows professional invoice summary with invoice number, client, amount, date
✓ Both files follow existing codebase patterns (job structure from CreateBulkInvoicesJob, mailable from InvoiceMail)
✓ No ShouldQueue on Mailable class (job handles queueing)
✓ All files pass PHP lint and Pint

## Integration Points

**For Plan 04-02:**
- Add `SendInvoiceEmailsJob::dispatch($bulkUploadId)` at end of CreateBulkInvoicesJob
- Dispatch AFTER transaction commits (so invoices exist)
- Dispatch AFTER BulkUpload status updated to 'completed'

**Email Recipients:**
- Company accountant: `$bulkUpload->agent->branch->company->email`
- Uploading agent: `$bulkUpload->agent->email`

**PDF Generation:**
- Uses existing `invoice.pdf.invoice` template
- Passes `isPdf => true` for PDF-specific styling
- Eager loads all necessary relationships to prevent N+1 queries

## Self-Check: PASSED

**Created files verified:**
```
FOUND: app/Mail/BulkInvoicesMail.php
FOUND: resources/views/email/bulk-invoices.blade.php
FOUND: app/Jobs/SendInvoiceEmailsJob.php
```

**Commits verified:**
```
FOUND: 6593f60e
FOUND: 43b6a6d0
```

All claimed files exist. All commit hashes exist in git history. Summary is accurate.
