# Technology Stack

**Project:** Soud Laravel - Bulk Invoice Upload Feature
**Researched:** 2026-02-12
**Confidence:** HIGH

## Executive Summary

This stack leverages existing Laravel 11 infrastructure with proven libraries already in the codebase. No major new dependencies required - only version confirmations and minor additions for validation enhancement. The existing `maatwebsite/excel`, `barryvdh/laravel-dompdf`, and Laravel queue system provide complete functionality for bulk Excel upload, validation, PDF generation, and email delivery.

**Key Finding:** All required libraries are already installed and compatible with Laravel 11. Focus is on patterns and integration rather than new dependencies.

---

## Core Dependencies (Already Installed)

### Excel Processing
| Technology | Current Version | Latest Compatible | Purpose | Why |
|------------|----------------|-------------------|---------|-----|
| **maatwebsite/excel** | 3.1.x | 3.1.x (verified 2026-02-10) | Excel import/export with validation | Industry standard for Laravel Excel operations. Built on PhpSpreadsheet. Provides `WithValidation`, `SkipsOnFailure`, `SkipsEmptyRows` concerns essential for bulk import validation. Already used in codebase for `TasksImport`, `ClientsImport`. **Laravel 11 compatible** (illuminate/support ^11.0). |
| **phpoffice/phpspreadsheet** | 1.30.x | 5.4.0 (PHP 8.1+) | Underlying Excel engine | Powers maatwebsite/excel. Framework-agnostic. **Current version 1.30 sufficient** for needs - upgrade to 5.x optional but not required. No breaking changes needed. |

**Integration Pattern:**
```php
// Existing pattern in codebase
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
```

**Confidence:** HIGH - Package verified in composer.json, Laravel 11 support confirmed via [official Packagist](https://packagist.org/packages/maatwebsite/excel) updated 2026-02-10.

---

### PDF Generation
| Technology | Current Version | Latest Compatible | Purpose | Why |
|------------|----------------|-------------------|---------|-----|
| **barryvdh/laravel-dompdf** | 3.1.x | 3.1.0 (2025-01-21) | Generate invoice PDFs | Already integrated in `InvoiceController` (line 41: `use Barryvdh\DomPDF\Facade\Pdf;`). **Laravel 11 compatible** (illuminate/support ^11). Mature, reliable, handles complex layouts. Zero setup needed - already working. |
| **dompdf/dompdf** | 3.x | 3.0+ | PDF rendering engine | Underlying engine for barryvdh/laravel-dompdf. Handles HTML-to-PDF conversion. Current version supports modern CSS. |

**Integration Pattern:**
```php
// Already used in InvoiceController
use Barryvdh\DomPDF\Facade\Pdf;

$pdf = Pdf::loadView('invoices.pdf', $data);
$pdf->save(storage_path('app/invoices/INV-123.pdf'));
```

**Confidence:** HIGH - Verified in use via controller imports, Laravel 11 support confirmed via [GitHub releases](https://github.com/barryvdh/laravel-dompdf/releases) (v3.1.0 supports illuminate/support ^11).

---

### Queue System
| Technology | Current Version | Configuration | Purpose | Why |
|------------|----------------|---------------|---------|-----|
| **Laravel Queue** | 11.x (built-in) | Database driver (configured) | Background job processing for bulk operations | Native Laravel feature. `config/queue.php` shows database driver configured with 'database' and 'api_sync' connections. Job batching available via `Bus::batch()` for tracking bulk invoice creation progress. |
| **Job Batches** | 11.x (built-in) | `job_batches` table required | Track bulk operation progress, handle failures gracefully | Laravel 11 provides `Bus::batch()` with `then()`, `catch()`, `finally()` callbacks. Essential for reporting "25 of 30 invoices created" progress to agents. |

**Integration Pattern:**
```php
// Laravel 11 job batching
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new CreateInvoiceJob($data1),
    new CreateInvoiceJob($data2),
])->then(function (Batch $batch) {
    // All invoices created successfully
})->catch(function (Batch $batch, Throwable $e) {
    // First invoice creation failure
})->finally(function (Batch $batch) {
    // Send email report
})->dispatch();
```

**Confidence:** HIGH - Queue configuration verified in `config/queue.php`, job batching is core Laravel 11 feature documented in [official Laravel 11 docs](https://laravel.com/docs/11.x/queues).

---

### Email System
| Technology | Current Version | Configuration | Purpose | Why |
|------------|----------------|---------------|---------|-----|
| **Laravel Mail** | 11.x (built-in) | SMTP/SES/Postmark/Resend configured | Send invoice PDFs to accountant + agent | Native Laravel feature. `config/mail.php` shows support for SMTP, AWS SES, Postmark, Resend. Project context confirms email service configured. Mailables support PDF attachments via `attachData()` or `attachFromStorage()`. |

**Integration Pattern:**
```php
// Laravel 11 Mailable with PDF attachment
use Illuminate\Mail\Mailable;

class InvoiceCreatedMail extends Mailable
{
    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path('app/invoices/INV-123.pdf'))
                ->as('Invoice-INV-123.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

// Queue the mailable
Mail::to($accountant)->queue(new InvoiceCreatedMail($invoice));
```

**Confidence:** HIGH - Mail configuration verified in `config/mail.php`, Laravel 11 Mailable system documented in [official Laravel 11 docs](https://laravel.com/docs/11.x/mail).

---

## Validation Stack (Leverage Existing Patterns)

### Row-Level Validation
| Concern | Purpose | When to Use | Integration |
|---------|---------|-------------|-------------|
| **WithValidation** | Define Laravel validation rules per row | Always - validate required fields, enums, formats | Return `rules()` method with array of validation rules. Use `prepareForValidation()` to normalize data before validation. |
| **SkipsOnFailure** | Handle failed rows gracefully without transaction rollback | Always - collect ALL validation errors for user review | Implement `onFailure(Failure ...$failures)` to store failed rows. Return failures to user with clear error messages. |
| **SkipsEmptyRows** | Ignore blank rows in Excel | Always - agents may leave trailing blank rows | Auto-skips rows where all cells are empty. Can customize via `isEmptyWhen()`. |
| **SkipsOnError** | Handle database exceptions (duplicate keys, foreign key violations) | When database constraints might fail | Implement `onError(Throwable $e)` to catch DB exceptions without stopping import. |

**Pattern for Bulk Invoice Import:**
```php
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Validators\Failure;

class InvoicesImport implements WithValidation, SkipsOnFailure, SkipsEmptyRows
{
    public function rules(): array
    {
        return [
            '*.client_mobile' => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
            '*.task_type' => ['required', Rule::in(TaskType::values())],
            '*.supplier_name' => ['required', 'exists:suppliers,name'],
            '*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function prepareForValidation($data, $index)
    {
        // Normalize phone: remove spaces, add country code
        $data['client_mobile'] = normalizePhone($data['client_mobile']);
        return $data;
    }

    public function onFailure(Failure ...$failures)
    {
        // Store failures for display
        foreach ($failures as $failure) {
            $this->errors[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
            ];
        }
    }
}
```

**Confidence:** HIGH - All concerns documented in [Laravel Excel validation docs](https://docs.laravel-excel.com/3.1/imports/validation.html). Pattern matches existing `TasksImport` and `ClientsImport` in codebase.

---

### File Upload Validation
| Validation Rule | Purpose | Configuration |
|----------------|---------|---------------|
| **File size limit** | Prevent memory exhaustion | `max:10240` (10MB) in Laravel validator. Set `upload_max_filesize=10M` and `post_max_size=12M` in php.ini. For larger files (50MB+), implement chunked upload. |
| **File type validation** | Security - only allow Excel files | `mimes:xlsx,xls,csv` or `File::types(['xlsx', 'xls'])` in Laravel 11. |
| **Malware scanning** | Optional - scan uploaded files | Not required for MVP. If needed later, use ClamAV integration via `sunspikes/clamav-validator` package. |

**Pattern:**
```php
// FormRequest validation
public function rules(): array
{
    return [
        'invoice_file' => [
            'required',
            'file',
            'mimes:xlsx,xls,csv',
            'max:10240', // 10MB
        ],
    ];
}
```

**Confidence:** HIGH - Standard Laravel 11 file validation rules documented in [Laravel 11 validation docs](https://laravel.com/docs/11.x/validation#rule-file).

---

## Preview Before Commit Pattern

### Implementation Strategy
| Step | Technique | Library/Method |
|------|-----------|----------------|
| **1. Upload & Validate File** | Store temporarily, validate file type/size | `$request->file('invoice_file')->store('temp')` |
| **2. Parse Without Saving** | Read Excel into collection without DB writes | `Excel::toCollection(new InvoicesImport, $file)` returns collection for preview |
| **3. Validate Rows** | Run validation rules, collect errors | Use `WithValidation` + `SkipsOnFailure` to gather all errors |
| **4. Display Preview** | Show summary: X invoices, Y clients, Z errors | Livewire component or Blade view with grouped preview |
| **5. User Approval** | Agent clicks "Approve" or "Reject" | Store validated data in session/cache keyed by upload ID |
| **6. Process on Approval** | Dispatch batch jobs to create invoices | `Bus::batch([...jobs])` to process approved upload |

**Pattern:**
```php
// Controller: Preview
public function preview(Request $request)
{
    $file = $request->file('invoice_file');
    $uploadId = Str::uuid();

    // Parse Excel into collection (no DB writes)
    $rows = Excel::toCollection(new InvoicesImport, $file)->first();

    // Validate and group by client
    $import = new InvoicesImport();
    $import->validate($rows);

    $preview = [
        'upload_id' => $uploadId,
        'total_rows' => $rows->count(),
        'invoices_to_create' => $import->groupedByClient()->count(),
        'errors' => $import->getErrors(),
    ];

    // Store for approval
    Cache::put("upload:$uploadId", $rows, now()->addHour());

    return view('invoices.bulk-preview', $preview);
}

// Controller: Process
public function process(Request $request)
{
    $uploadId = $request->input('upload_id');
    $rows = Cache::get("upload:$uploadId");

    // Dispatch batch
    $batch = Bus::batch(
        collect($rows)->map(fn($group) => new CreateInvoiceJob($group))
    )->dispatch();

    return redirect()->route('invoices.batch.status', $batch->id);
}
```

**Confidence:** MEDIUM - Pattern assembled from Laravel Excel docs and Laravel queue docs. Requires custom implementation but uses only documented features.

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| **Excel Library** | maatwebsite/excel 3.1.x | Direct PhpSpreadsheet integration | Maatwebsite provides Laravel-specific concerns (WithValidation, ToModel) that reduce boilerplate. Already in codebase with proven patterns. |
| **PDF Library** | barryvdh/laravel-dompdf 3.1.0 | Snappy (wkhtmltopdf), mPDF, TCPDF | DomPDF already working in codebase. TCPDF also installed (line 33 in composer.json) but DomPDF simpler API. Snappy requires external binary. mPDF adds dependency. |
| **Queue Driver** | Database (current config) | Redis, Amazon SQS, Beanstalkd | Database driver sufficient for MVP - no additional infrastructure. Upgrade to Redis if >1000 invoices/hour. SQS for AWS-hosted projects. |
| **File Upload** | Standard Laravel upload | Laravel Vapor, Livewire file upload, chunked upload | Standard upload works for <10MB files. Livewire if UI requires real-time progress. Chunked upload if >50MB files common (not expected for invoice Excel). |
| **Email Queue** | Laravel Mailable + Queue | Third-party (SendGrid, Mailgun API) | Native Laravel mail works with existing email service. No need for third-party API when SMTP/SES/Postmark configured. |
| **Validation** | Laravel built-in + Excel concerns | Custom validation service layer | Laravel validation rules + Excel concerns provide all needed features. Custom layer adds complexity without benefit. |

---

## Installation (Nothing New Required)

### Verify Existing Setup
```bash
# All dependencies already installed in composer.json
# Verify versions:
composer show maatwebsite/excel        # Should be 3.1.x
composer show barryvdh/laravel-dompdf  # Should be 3.1.x
composer show phpoffice/phpspreadsheet # Should be 1.30+

# Check queue configuration
php artisan queue:table           # Create jobs table if missing
php artisan queue:batches-table   # Create job_batches table if missing
php artisan migrate

# Test queue worker
php artisan queue:work --queue=default --tries=3
```

### Optional: Upgrade PhpSpreadsheet (Not Required)
```bash
# ONLY if advanced Excel features needed (charts, macros, etc.)
composer require phpoffice/phpspreadsheet:^5.4

# Note: maatwebsite/excel 3.1.x supports PhpSpreadsheet 1.x and 2.x
# Upgrading to 5.x requires testing compatibility
```

### Configuration Changes Required
```bash
# None - all packages pre-configured
# Only need to:
# 1. Ensure MAIL_* variables set in .env
# 2. Ensure QUEUE_CONNECTION=database in .env
# 3. Run migrations for jobs/job_batches tables
```

---

## Performance Considerations

| Scenario | Recommendation | Rationale |
|----------|---------------|-----------|
| **<100 invoices per upload** | Synchronous processing acceptable | Fast enough (<10 seconds), simpler error handling |
| **100-1000 invoices per upload** | Queue with batching (current approach) | Background processing prevents timeout, batch tracking provides progress updates |
| **>1000 invoices per upload** | Chunked queue processing + Redis driver | Split into 100-invoice batches, use Redis for faster queue. Add Laravel Horizon for monitoring. |
| **File size >10MB** | Implement chunked upload | Split client-side into 5-10MB chunks, assemble server-side. Prevents php.ini upload limits. |
| **Multiple concurrent uploads** | Rate limiting + queue priorities | Use `RateLimited` middleware on upload endpoint. Assign queue priorities: `high` for accountants, `default` for agents. |

---

## Security Considerations

| Concern | Mitigation | Implementation |
|---------|-----------|----------------|
| **Malicious Excel files** | File type validation, size limits | `mimes:xlsx,xls,csv` + `max:10240` in FormRequest |
| **CSV injection** | Prefix cells starting with `=`, `+`, `@`, `-` with single quote | Implement in `prepareForValidation()` method |
| **Multi-tenant data leakage** | Filter by company_id in all queries | Use existing multi-tenant pattern: `where('company_id', auth()->user()->company_id)` |
| **Unauthorized invoice creation** | Gate authorization on upload | `Gate::authorize('create', Invoice::class)` in controller |
| **Sensitive data in temp files** | Auto-delete after processing | `Cache::forget("upload:$uploadId")` after batch dispatch. Use Laravel scheduler to clean old temp files: `Storage::disk('temp')->delete(...)` |

---

## Integration Points with Existing Codebase

### Reuse Existing Services
| Service | Location | How to Use |
|---------|----------|-----------|
| **InvoiceController::store()** | `app/Http/Controllers/InvoiceController.php` | Extract invoice creation logic into `InvoiceService::createFromTasks($tasks, $clientId)`. Call from both manual UI and bulk import job. |
| **InvoiceSequence** | `app/Models/InvoiceSequence.php` | Already handles company-specific invoice numbering. No changes needed. |
| **JournalEntry creation** | Triggered by Invoice model events | Existing accounting integration works automatically when invoice created. No changes needed. |
| **Multi-tenant filtering** | Global scope or manual `where('company_id', ...)` | Use `getCompanyId($user)` helper (line 58 in InvoiceController) consistently. |
| **Email service** | Laravel Mailable + configured SMTP/SES | Create `InvoiceCreatedMail` mailable, queue via `Mail::to(...)->queue(new InvoiceCreatedMail())`. |

### Models Touched
| Model | Purpose | Changes Needed |
|-------|---------|----------------|
| **Invoice** | Store created invoices | None - existing model sufficient |
| **InvoiceDetail** | Store line items (tasks) | None - existing model sufficient |
| **Task** | Source data for invoice line items | Add `bulk_upload_id` nullable field to track which upload created each task (optional, for audit trail) |
| **Client** | Link invoices to clients | Ensure `findByPhone($companyId, $phone)` method exists or add scope |
| **FileUpload** | Track uploaded Excel files | Add `type='bulk_invoice'` enum value if FileUpload model tracks different upload types |

---

## Testing Stack

| Tool | Purpose | Already Available? |
|------|---------|-------------------|
| **PHPUnit** | Unit tests for validation, import classes | Yes - `phpunit/phpunit ^11.0.1` in composer.json |
| **Laravel Dusk** | Browser tests for upload UI (optional) | No - add if E2E tests needed: `composer require --dev laravel/dusk` |
| **Faker** | Generate test Excel files with realistic data | Yes - `fakerphp/faker ^1.23` in composer.json |

**Test Pattern:**
```php
// Feature test for bulk upload
public function test_bulk_upload_creates_invoices()
{
    // Generate test Excel file
    $file = $this->createTestExcelFile([
        ['client_mobile' => '+96512345678', 'task_type' => 'flight', ...],
        ['client_mobile' => '+96512345678', 'task_type' => 'hotel', ...],
    ]);

    // Upload
    $response = $this->actingAs($agent)
        ->post(route('invoices.bulk.preview'), ['invoice_file' => $file]);

    // Assert preview shown
    $response->assertSee('2 rows');
    $response->assertSee('1 invoice'); // Grouped by client

    // Approve
    $uploadId = $response->json('upload_id');
    $this->post(route('invoices.bulk.process'), ['upload_id' => $uploadId]);

    // Assert invoice created
    $this->assertDatabaseHas('invoices', ['client_id' => ...]);
}
```

---

## Monitoring & Observability

| Concern | Solution | Implementation |
|---------|----------|----------------|
| **Queue job failures** | Failed jobs table + notifications | Laravel stores in `failed_jobs` table. Add daily check: `Schedule::command('queue:prune-failed')->daily()` |
| **Batch progress tracking** | Job batches table | Query `job_batches` table: `$batch->progress()`, `$batch->failedJobs` |
| **Email delivery failures** | Log channel + exception handling | Catch `Swift_TransportException` in Mailable, log to `storage/logs/email.log` |
| **Validation error trends** | Store validation failures | Create `bulk_upload_errors` table to track common validation errors over time |

**Optional: Laravel Horizon** (if queue load increases)
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```
Horizon provides real-time dashboard for queue monitoring, job throughput, failed jobs. Not needed for MVP but valuable if processing >100 uploads/day.

---

## Configuration Checklist

### Required .env Variables
```env
# Queue (already configured)
QUEUE_CONNECTION=database

# Email (verify set)
MAIL_MAILER=smtp          # or ses, postmark, resend
MAIL_FROM_ADDRESS=invoices@example.com
MAIL_FROM_NAME="Invoice System"

# Optional: Queue-specific
QUEUE_FAILED_DRIVER=database-uuids
```

### Required Migrations
```bash
# Create if missing
php artisan queue:table
php artisan queue:batches-table
php artisan queue:failed-table

# Run migrations
php artisan migrate
```

### Optional: Increase PHP Limits
```ini
# php.ini (if Excel files >2MB common)
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
max_execution_time = 300  # 5 minutes for bulk processing
```

---

## Sources

### Official Documentation (HIGH Confidence)
- [Laravel Excel - Row Validation](https://docs.laravel-excel.com/3.1/imports/validation.html) - WithValidation, SkipsOnFailure concerns
- [Laravel 11 Queues](https://laravel.com/docs/11.x/queues) - Job batching, queue drivers
- [Laravel 11 Mail](https://laravel.com/docs/11.x/mail) - Mailables, attachments, queueing
- [Laravel 11 Validation](https://laravel.com/docs/11.x/validation) - File validation rules

### Package Repositories (HIGH Confidence)
- [maatwebsite/excel on Packagist](https://packagist.org/packages/maatwebsite/excel) - Version 3.1.x, Laravel 11 support verified 2026-02-10
- [barryvdh/laravel-dompdf on GitHub](https://github.com/barryvdh/laravel-dompdf) - Version 3.1.0, Laravel 11 support (illuminate/support ^11)
- [phpoffice/phpspreadsheet on Packagist](https://packagist.org/packages/phpoffice/phpspreadsheet) - Version 5.4.0, PHP 8.1+

### Best Practices & Guides (MEDIUM Confidence)
- [Laravel Excel Best Practices 2026](https://medium.com/@mahmoudhadry/10-essential-laravel-excel-file-handling-strategies-every-developer-should-master-80ef79775634)
- [Laravel Queue Design Guide (Feb 2026)](https://blog.greeden.me/en/2026/02/11/field-proven-complete-guide-laravel-queue-design-and-async-processing-jobs-queues-horizon-retries-idempotency-delays-priorities-failure-isolation-external-api-integrations/)
- [Laravel Chunked Upload Guide](https://webdock.io/en/docs/how-guides/laravel-guides/laravel-chunked-upload-uploading-huge-files)
- [Laravel Notifications Best Practices](https://laravel.com/docs/11.x/notifications)

### Community Resources (MEDIUM Confidence)
- [Dynamic Excel Import with Preview in Laravel](https://www.mindforge.digital/articles/dynamic-excel-import-with-preview-laravel)
- [Laravel Mail Queue Tutorial - Mailtrap](https://mailtrap.io/blog/laravel-mail-queue/)
- [Advanced Laravel Queue Management 2025](https://nihardaily.com/47-advanced-laravel-queue-management-complete-guide-to-background-jobs-batching-performance-optimization)

---

## Summary

**All required technology already in codebase.** This is a feature addition using existing Laravel 11 patterns, not a greenfield stack decision.

**Zero new dependencies needed for MVP.** Focus implementation on:
1. Excel import class with validation concerns
2. Preview controller logic using `toCollection()`
3. Batch job for invoice creation
4. Mailable for email delivery

**Upgrade path clear:** If scaling needed, add Redis queue driver and Laravel Horizon. If files >50MB, implement chunked upload. Current stack handles 100-1000 invoices per upload comfortably.

**Integration risk: LOW.** Reuses proven patterns from existing `TasksImport`, `ClientsImport`, and `InvoiceController::store()`.
