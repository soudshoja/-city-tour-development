# Bulk Invoice Upload — Developer Guide

**Feature:** Bulk Invoice Upload v2
**Status:** In Progress (Job execution fixes ongoing)
**Last Updated:** 2026-02-16

---

## Overview

The Bulk Invoice Upload feature allows agents and company/branch staff to upload an Excel file to **batch-create invoices** from existing tasks and payments. It does NOT create new tasks — it links existing tasks to clients, sets their selling price, and creates invoices with payment applications.

---

## User Flow

```
1. Download Excel Template
2. Fill in task/client/payment references
3. Upload Excel File
4. System validates each row (finds tasks, clients, payments in DB)
5. Preview page shows grouped invoices to be created
6. User approves (or rejects) the upload
7. Background job creates invoices and applies payments
8. Success page auto-refreshes until complete
9. Email sent to company accountant + uploading agent
```

---

## Excel Template

**Download:** `GET /bulk-invoices/template`

### Columns (in order):

| Column | Required | Description |
|--------|----------|-------------|
| `invoice_date` | ✅ | Date of invoice (any valid date format) |
| `client_mobile` | ✅ | Client phone number — must match `clients.phone` in the company |
| `task_reference` | ✅ | Task ID (numeric) or task `reference` string (e.g. `HYS3XQ`) |
| `task_status` | ✅ | Must be one of: `pending`, `issued`, `confirmed`, `reissued`, `refund`, `void`, `emd` |
| `selling_price` | ✅ | Numeric >= 0. Sets `tasks.selling_amount` |
| `payment_reference` | ✅ | Payment voucher number or numeric payment ID |
| `notes` | ❌ | Optional notes for the invoice detail |

### Grouping Logic

Rows with the **same `client_mobile` + `invoice_date`** are grouped into **one invoice** with multiple invoice details (one per task).

---

## Routes

```php
// All routes under prefix: /bulk-invoices, name prefix: bulk-invoices.
Route::get('/',                'index')           // bulk-invoices.index
Route::post('/upload',         'upload')          // bulk-invoices.upload
Route::get('/template',        'downloadTemplate') // bulk-invoices.template
Route::get('/{id}/preview',    'preview')         // bulk-invoices.preview
Route::post('/{id}/approve',   'approve')         // bulk-invoices.approve
Route::post('/{id}/reject',    'reject')          // bulk-invoices.reject
Route::get('/{id}/success',    'success')         // bulk-invoices.success
Route::get('/{id}/error-report','downloadErrorReport') // bulk-invoices.error-report
```

---

## Key Files

### Controllers & Services
| File | Purpose |
|------|---------|
| `app/Http/Controllers/BulkInvoiceController.php` | Main controller — upload, validate, preview, approve, reject, success |
| `app/Services/BulkUploadValidationService.php` | Row-by-row validation, DB lookups for task/client/payment |
| `app/Http/Requests/BulkInvoiceUploadRequest.php` | Form request validation (file type, size) |

### Jobs
| File | Queue | Purpose |
|------|-------|---------|
| `app/Jobs/CreateBulkInvoicesJob.php` | `invoices` | Creates invoices, applies payments (runs after approve) |
| `app/Jobs/SendInvoiceEmailsJob.php` | `emails` | Sends email notification to accountant + agent |

### Models
| File | Table | Purpose |
|------|-------|---------|
| `app/Models/BulkUpload.php` | `bulk_uploads` | Parent record per upload session |
| `app/Models/BulkUploadRow.php` | `bulk_upload_rows` | One row per Excel row with validation result |

### Exports & Imports
| File | Purpose |
|------|---------|
| `app/Exports/BulkInvoiceTemplateExport.php` | Multi-sheet Excel template export |
| `app/Exports/BulkInvoiceTemplateSheet.php` | Template sheet with styled headers |
| `app/Exports/BulkUploadErrorReportExport.php` | Error report Excel (red/yellow coded rows) |
| `app/Imports/BulkInvoiceImport.php` | Parses uploaded Excel into array |

### Views
| File | Purpose |
|------|---------|
| `resources/views/bulk-invoice/upload.blade.php` | Upload form with agent selector |
| `resources/views/bulk-invoice/preview.blade.php` | Preview of grouped invoices before approval |
| `resources/views/bulk-invoice/success.blade.php` | Success page with auto-refresh (5s) while processing |

---

## Database Schema

### `bulk_uploads`
```sql
id, company_id, agent_id, user_id,
original_filename, stored_path,
status ENUM('pending','validating','validated','processing','completed','failed','rejected'),
total_rows, valid_rows, error_rows, flagged_rows,
error_summary JSON,
invoice_ids JSON,       -- populated after job completes
timestamps, soft_deletes
```

### `bulk_upload_rows`
```sql
id, bulk_upload_id,
row_number,
status ENUM('valid','error','flagged'),
task_id,       -- matched task (nullable)
client_id,     -- matched client (nullable)
supplier_id,   -- matched supplier (nullable)
payment_id,    -- matched payment (nullable) ← added 2026-02-16
raw_data JSON, -- original Excel row data
errors JSON,   -- validation error messages
flag_reason,
timestamps
```

> ⚠️ **Migration note:** If deploying to a server where `bulk_upload_rows` already exists, run only the `payment_id` migration:
> ```bash
> php artisan migrate --path=database/migrations/2026_02_16_152412_add_payment_id_to_bulk_upload_rows_table.php
> ```

---

## Validation Logic (`BulkUploadValidationService`)

Each row is validated in order:

1. **`invoice_date`** — must be a parseable date
2. **`client_mobile`** — looked up via `clients.phone` scoped to `company_id`
3. **`task_reference`** — if numeric → searched by `tasks.id`; otherwise → searched by `tasks.reference`
   - Also filtered by `task_status` if provided
4. **`task_status`** — must be a valid enum value
5. **`selling_price`** — must be numeric >= 0
6. **`payment_reference`** — if numeric → searched by `payments.id`; otherwise → searched by `payments.voucher_number`
   - Payment must belong to the company (via `agent → branch → company_id`)

**Row status outcomes:**
- `valid` — all fields pass, matched IDs saved to `bulk_upload_rows`
- `error` — one or more fields failed validation
- `flagged` — currently unused (reserved for future soft-fail logic)

---

## Job Execution (`CreateBulkInvoicesJob`)

**Queue:** `invoices`
**Tries:** 3
**Timeout:** 300s

### Steps:
1. Load all `valid` rows from `bulk_upload_rows`
2. For each row:
   - Load `Task`, `Client`, `Payment` by their saved IDs
   - Set `task.selling_amount` from Excel `selling_price`
   - Link `task.client_id` if not already set
   - Group rows by `client_id + invoice_date`
3. For each group:
   - Generate invoice number (pessimistic lock on `invoice_sequences`)
   - Create `Invoice`
   - Create `InvoiceDetail` per task
   - Apply payment via `PaymentApplicationService` (uses topup credit)
4. Update `bulk_upload.status = 'completed'`, save `invoice_ids`
5. Dispatch `SendInvoiceEmailsJob` (on `emails` queue, after commit)

### On Failure:
- Sets `bulk_upload.status = 'failed'`
- Saves error message to `bulk_upload.error_summary['job_failure']`
- Shown on success page

---

## Running the Queue (Development)

```bash
# Process the invoices queue (one job)
php artisan queue:work database --queue=invoices --once

# Process emails queue (one job)
php artisan queue:work database --queue=emails --once

# Process both continuously
php artisan queue:work database --queue=invoices,emails
```

> ⚠️ **Important:** The job is on the `invoices` queue. Running `queue:work` without `--queue=invoices` will NOT process it (it defaults to `default` queue).

### Production — Supervisor Config
```ini
[program:laravel-invoices-worker]
command=php /path/to/artisan queue:work database --queue=invoices,emails --tries=3
autostart=true
autorestart=true
```

---

## Role-Based Access

| Role | Behaviour |
|------|-----------|
| `AGENT` | Uploads on behalf of themselves (auto-assigned `agent_id`) |
| `COMPANY` | Must select an agent from their company's branches |
| `BRANCH` | Must select an agent from their branch |
| `ACCOUNTANT` | Must select an agent from their branch |
| `ADMIN` | Must select an agent scoped to active `session('company_id')` |

---

## Known Issues & Fixes Applied

| Date | Issue | Fix |
|------|-------|-----|
| 2026-02-16 | `pnr`, `booking_reference`, `confirmation_code` columns don't exist | Changed to `reference` column only |
| 2026-02-16 | `payment_id` not saved to `bulk_upload_rows` | Added `payment_id` column + migration |
| 2026-02-16 | Job accessed `$row->matched['task_id']` (null) | Changed to `$row->task_id`, `$row->client_id`, `$row->payment_id` |
| 2026-02-16 | Queue worker processed `default` queue, job was on `invoices` | Must run `--queue=invoices` |
| 2026-02-16 | Duplicate migration files caused `table already exists` error | Removed duplicate migrations |

---

## Logs

All bulk upload activity is logged with `[BULK UPLOAD]` prefix:

```bash
# Tail bulk upload logs
grep "BULK UPLOAD" storage/logs/laravel.log | tail -50

# Watch in real-time
tail -f storage/logs/laravel.log | grep -E "BULK UPLOAD|bulk invoice|CreateBulkInvoices"
```

### Key log events:
- `[BULK UPLOAD] Upload started` — file received
- `[BULK UPLOAD] Client found / not found` — client mobile lookup result
- `[BULK UPLOAD] Task found / not found` — task reference lookup result
- `[BULK UPLOAD] Payment found / not found` — payment reference lookup result
- `[BULK UPLOAD] Row validation result` — final status per row
- `Starting bulk invoice creation` — job started
- `Created invoice` — invoice created successfully
- `Bulk invoice creation completed` — all invoices done
