# Phase 4: PDF Generation & Email Delivery - Research

**Researched:** 2026-02-13
**Domain:** Laravel 11 PDF generation (DomPDF), Laravel Mail system, Queue jobs
**Confidence:** HIGH

## Summary

Phase 4 extends the bulk invoice creation system (Phase 3) to automatically generate PDF invoices and email them to stakeholders. The codebase already has mature PDF generation infrastructure via barryvdh/laravel-dompdf v3.1+ and a well-designed invoice email template at `resources/views/invoice/pdf/invoice.blade.php`. The existing `InvoiceMail` mailable class demonstrates the correct pattern but currently has PDF attachment code commented out (lines 49-60).

The primary technical challenge is creating a queued job that generates PDFs for all invoices created by `CreateBulkInvoicesJob` and sends emails without blocking the invoice creation transaction. Laravel's queue system with the database driver is already configured, and the existing job pattern in `CreateBulkInvoicesJob` provides a proven template.

**Primary recommendation:** Create a separate `SendInvoiceEmailsJob` that receives invoice IDs, generates PDFs in memory using DomPDF, and queues emails using Laravel's `Attachment::fromData()` method. Dispatch this job after the bulk invoice transaction commits successfully.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| barryvdh/laravel-dompdf | ^3.1 | PDF generation from Blade views | Industry standard for Laravel PDFs (133,000+ projects), proven in codebase |
| Laravel Mail | 11.x | Email sending with attachments | Native Laravel mail system, supports multiple providers |
| Laravel Queue | 11.x | Background job processing | Native database-backed queue system already configured |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Resend, AWS SES, Postmark | Latest | Email delivery providers | Configured in .env, selected via MAIL_MAILER |
| iio/libmergepdf | ^4.0 | PDF merging | Already installed, not needed for this phase |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| DomPDF | Snappy/wkhtmltopdf | Better rendering but requires binary installation, not already installed |
| Attachment::fromData() | Attachment::fromPath() | Requires temp file storage/cleanup, slower |
| Database queue | Redis/SQS | Faster but requires additional infrastructure setup |

**Installation:**
All required packages already installed. No additional dependencies needed.

## Architecture Patterns

### Recommended Job Structure
```
app/Jobs/
├── CreateBulkInvoicesJob.php      # Existing - creates invoices
└── SendInvoiceEmailsJob.php       # New - generates PDFs and emails
```

### Pattern 1: Chained Queue Jobs
**What:** Dispatch email job after invoice creation job completes
**When to use:** When second job depends on first job's database commits
**Example:**
```php
// In CreateBulkInvoicesJob::handle() - AFTER transaction commits
DB::transaction(function () use ($bulkUpload) {
    // ... create invoices ...

    $bulkUpload->update([
        'status' => 'completed',
        'invoice_ids' => $invoiceIds,
    ]);
});

// CRITICAL: Dispatch AFTER transaction, not inside
SendInvoiceEmailsJob::dispatch($this->bulkUploadId)
    ->onQueue('emails')
    ->afterCommit();
```

### Pattern 2: In-Memory PDF Generation
**What:** Generate PDF bytes in memory, attach directly to email without disk I/O
**When to use:** For transient PDFs sent via email (don't need persistence)
**Example:**
```php
// Source: Laravel 11.x official docs
use Illuminate\Mail\Attachment;
use Barryvdh\DomPDF\Facade\Pdf;

public function attachments(): array
{
    $invoice = Invoice::with('invoiceDetails', 'client', 'agent.branch.company')
        ->findOrFail($this->invoiceId);

    $pdf = Pdf::loadView('invoice.pdf.invoice', [
        'invoice' => $invoice,
        'company' => $invoice->agent->branch->company,
        'invoiceDetails' => $invoice->invoiceDetails,
        'isPdf' => true,
    ]);

    return [
        Attachment::fromData(fn () => $pdf->output(), "Invoice-{$invoice->invoice_number}.pdf")
            ->withMime('application/pdf'),
    ];
}
```

### Pattern 3: Multi-Recipient Email Distribution
**What:** Send same email to multiple recipients (accountant + agent)
**When to use:** Invoice distribution to stakeholders
**Example:**
```php
// Get recipients
$company = $bulkUpload->agent->branch->company;
$agent = $bulkUpload->agent;

// Send to accountant (company email)
if ($company->email) {
    Mail::to($company->email)
        ->queue(new BulkInvoicesMail($bulkUploadId));
}

// Send to uploading agent
if ($agent->email) {
    Mail::to($agent->email)
        ->queue(new BulkInvoicesMail($bulkUploadId));
}
```

### Pattern 4: Queueable Mailable
**What:** Mailable class that implements ShouldQueue for automatic background processing
**When to use:** Always for emails with attachments to prevent request timeout
**Example:**
```php
// Source: Existing codebase pattern (InvoiceMail.php)
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BulkInvoicesMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $bulkUploadId;

    public function __construct(int $bulkUploadId)
    {
        $this->bulkUploadId = $bulkUploadId;
    }

    public function build()
    {
        // Load data and generate attachments
    }
}
```

### Anti-Patterns to Avoid
- **Don't generate PDFs inside transaction:** PDF generation is slow and blocks database locks
- **Don't store PDFs to disk unnecessarily:** Use `Attachment::fromData()` for email-only PDFs
- **Don't pass Eloquent models to jobs:** Serialize only IDs (models may change before job runs)
- **Don't dispatch jobs inside transaction:** Use `afterCommit()` or dispatch after transaction completes
- **Don't call `render()` before queueing mailable:** Doubles attachments (known Laravel bug)

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF generation | Custom HTML-to-PDF converter | barryvdh/laravel-dompdf | Already installed, handles fonts/images/CSS, battle-tested |
| Email attachments | Custom MIME encoding | Laravel Attachment API | Handles encoding, MIME types, filenames correctly |
| Queue management | Custom background processing | Laravel Queue system | Database queue already configured, handles retries/failures |
| Email delivery | Direct SMTP connection | Laravel Mail + configured provider | Handles provider failover, logging, testing |

**Key insight:** Laravel's mail and queue systems handle complex edge cases like serialization of closures, database connection management in long-running jobs, and graceful failure recovery. The existing codebase patterns (CreateBulkInvoicesJob, InvoiceMail) demonstrate mature implementations to follow.

## Common Pitfalls

### Pitfall 1: PDF Generation Blocks Database Transaction
**What goes wrong:** Generating PDFs inside `DB::transaction()` holds database locks for 5-10 seconds per invoice, causing timeouts and deadlocks in multi-user scenarios.
**Why it happens:** DomPDF rendering is CPU-intensive and blocks the PHP process.
**How to avoid:** Always generate PDFs in a separate queued job dispatched AFTER transaction commits.
**Warning signs:** Queue worker logs showing "Maximum execution time exceeded" or failed_jobs table entries.

### Pitfall 2: Missing Company Email (Accountant)
**What goes wrong:** Company model has `email` field but no dedicated `accountant_email` field. Assumption that `companies.email` IS the accountant email may be wrong.
**Why it happens:** Database schema only has generic company email, not role-specific emails.
**How to avoid:**
- Verify that `companies.email` is the correct accountant contact
- Add validation to check email exists before sending
- Consider adding UI warning if company email is missing
**Warning signs:** BulkUpload completes but no accountant email sent.

### Pitfall 3: Agent Email Missing
**What goes wrong:** Not all agents have email addresses in the `agents.email` field (nullable column).
**Why it happens:** Agent creation doesn't enforce email requirement.
**How to avoid:**
- Check if agent email exists before attempting send
- Log warning when email skipped due to missing address
- Don't fail the job if agent email missing (accountant email is primary)
**Warning signs:** Job fails with "Email address cannot be null" exception.

### Pitfall 4: Serializing Eloquent Models in Queued Jobs
**What goes wrong:** Passing full Invoice model to job constructor causes stale data when job runs later (model state may change between dispatch and execution).
**Why it happens:** `SerializesModels` trait serializes model state at dispatch time, not execution time.
**How to avoid:** Always pass IDs only, reload models in job's `handle()` method.
**Warning signs:** Email shows old invoice data or relationships not loaded.

### Pitfall 5: Too Many Attachments per Email
**What goes wrong:** Bulk upload creates 50 invoices → 50 PDF attachments → email size exceeds provider limits (typically 10-25MB).
**Why it happens:** Each PDF is 100-500KB, multiplied by invoice count.
**How to avoid:**
- Send one email per invoice (not one email with all PDFs)
- OR batch invoices into multiple emails (max 10 PDFs per email)
- OR send one summary email with invoice numbers + PDF download links
**Warning signs:** Email provider rejects message with "Message too large" error.

**Recommended approach for Phase 4:** Send ONE email per recipient (accountant, agent) with ALL invoice PDFs attached, but add size check and fall back to download links if total size > 10MB.

### Pitfall 6: Queue Not Running
**What goes wrong:** Jobs dispatched but emails never send because queue worker isn't running.
**Why it happens:** `php artisan queue:work` not running in production, or crashes without restart.
**How to avoid:**
- Ensure queue worker runs via supervisor or systemd
- Add `after_commit => true` to queue config (already false in current config)
- Test with `php artisan queue:work --once` to verify job executes
**Warning signs:** `jobs` table fills up, emails never arrive.

### Pitfall 7: PDF Template Not Optimized for PDF Rendering
**What goes wrong:** `invoice.pdf.invoice` template uses web fonts or external CSS that don't load in PDF context, resulting in broken layout.
**Why it happens:** DomPDF has limited CSS support and can't load external resources unless RemoteEnabled=true.
**How to avoid:**
- Always pass `isPdf => true` to template (template already has conditional rendering)
- Use DejaVu Sans font (included in DomPDF) instead of web fonts
- Inline all CSS, no external stylesheets
- Test PDF generation in isolation before email integration
**Warning signs:** PDFs render with missing fonts, broken layout, or warnings in logs.

**Current template status:** `resources/views/invoice/pdf/invoice.blade.php` already has `$isPdf` conditional logic (line 9-19) for PDF-optimized rendering. Good foundation.

## Code Examples

Verified patterns from official sources and existing codebase:

### Invoice PDF Generation (Existing Pattern)
```php
// Source: app/Http/Controllers/InvoiceController.php:2357-2370
use Barryvdh\DomPDF\Facade\Pdf;

public function generatePdf(string $invoiceNumber)
{
    $invoice = Invoice::where('invoice_number', $invoiceNumber)
        ->with('agent.branch.company', 'client', 'invoiceDetails')
        ->first();

    $invoiceDetails = $invoice->invoiceDetails;

    $pdf = Pdf::loadView('invoice.pdf', compact('invoice', 'invoiceDetails'));

    return $pdf->download("Invoice_{$invoiceNumber}.pdf");
}
```

### Email with PDF Attachment (Commented Pattern in InvoiceMail.php)
```php
// Source: app/Mail/InvoiceMail.php:49-60 (currently commented out)
$pdfData = array_merge($viewData, ['isPdf' => true]);
$pdf = Pdf::loadView('invoice.pdf.invoice', $pdfData)
    ->setPaper('a4', 'portrait');

return $this->subject($subject)
    ->view('invoice.pdf.invoice')
    ->with($viewData)
    ->attachData(
        $pdf->output(),
        "Invoice-{$invoice->invoice_number}.pdf",
        ['mime' => 'application/pdf']
    );
```

### Modern Laravel 11 Attachment Pattern
```php
// Source: Laravel 11.x official documentation
use Illuminate\Mail\Attachment;

public function attachments(): array
{
    return [
        Attachment::fromData(fn () => $this->pdfContent, 'invoice.pdf')
            ->withMime('application/pdf'),
    ];
}
```

### Queue Job Pattern (Existing)
```php
// Source: app/Jobs/CreateBulkInvoicesJob.php:27-58
class CreateBulkInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 300;

    public function __construct(public int $bulkUploadId) {}

    public function handle(): void
    {
        $bulkUpload = BulkUpload::with('rows.client', 'rows.supplier', 'rows.task')
            ->findOrFail($this->bulkUploadId);

        DB::transaction(function () use ($bulkUpload) {
            // ... create invoices ...
        });
    }

    public function failed(Throwable $exception): void
    {
        BulkUpload::where('id', $this->bulkUploadId)->update([
            'status' => 'failed',
            'error_summary' => json_encode([
                'job_failure' => $exception->getMessage(),
            ]),
        ]);
    }
}
```

### Queueing Emails on Specific Queue
```php
// Source: Laravel 11.x official documentation
use Illuminate\Support\Facades\Mail;

// Option 1: Queue method on facade
Mail::to($recipient)
    ->queue(new InvoiceMail($invoiceId));

// Option 2: Specific queue and connection
$mailable = (new InvoiceMail($invoiceId))
    ->onQueue('emails')
    ->onConnection('database');

Mail::to($recipient)->queue($mailable);

// Option 3: Delayed delivery
Mail::to($recipient)
    ->later(now()->addMinutes(5), new InvoiceMail($invoiceId));
```

### Success Page Download Links (Enhancement for DELIVER-03)
```php
// Add to success.blade.php view
@if($invoices->isNotEmpty())
    <h3 class="font-semibold mb-3 text-lg">Created Invoices ({{ $invoices->count() }})</h3>
    <ul class="space-y-2">
        @foreach($invoices as $invoice)
            <li class="border rounded p-3 flex justify-between items-center">
                <div>
                    <p class="font-semibold">{{ $invoice->invoice_number }}</p>
                    <p class="text-sm text-gray-600">{{ $invoice->client->name ?? 'Unknown Client' }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('invoice.show', [$invoice->company_id, $invoice->invoice_number]) }}"
                       class="text-blue-600 hover:underline text-sm">View</a>
                    <a href="{{ route('invoice.pdf', $invoice->invoice_number) }}"
                       class="text-green-600 hover:underline text-sm">Download PDF</a>
                </div>
            </li>
        @endforeach
    </ul>
@endif
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| attachData() with array options | Attachment::fromData() with fluent API | Laravel 9+ (2022) | Cleaner syntax, better IDE support |
| Synchronous email sending | Queue by default with ShouldQueue | Laravel 8+ (2020) | Prevents request timeouts |
| PDF stored to temp file | In-memory PDF with fromData() | Laravel 9+ (2022) | No disk I/O, no cleanup needed |
| DomPDF RemoteEnabled=true | RemoteEnabled=false (default) | DomPDF 3.0 (Jan 2025) | Security hardening, must inline resources |

**Deprecated/outdated:**
- `$this->attach()` method in Mailable: Use `attachments()` method returning array
- Passing PDF path to attach(): Use `Attachment::fromPath()` for better control
- Global `PDF` facade: Use `Pdf` facade (lowercase 'p') from barryvdh/laravel-dompdf v3+

## Open Questions

1. **Accountant Email Identification**
   - What we know: `companies.email` field exists (nullable)
   - What's unclear: Is this field actually used for accountant email? Is it populated for all companies?
   - Recommendation: Verify with user/database that `companies.email` is correct accountant contact, add validation

2. **Email Attachment Size Limits**
   - What we know: Email providers have size limits (typically 10-25MB)
   - What's unclear: What's the realistic invoice count per bulk upload? 10? 100? 1000?
   - Recommendation: Implement size check (count invoices × estimated 200KB per PDF), fall back to download links if > 10MB

3. **Email Subject Line and Body Content**
   - What we know: Single invoice emails exist (InvoiceMail)
   - What's unclear: What should bulk invoice email say? List all invoice numbers? Just count?
   - Recommendation: Create email view showing summary (X invoices for Y clients) with attachment list

4. **PDF Template Multi-Tenant Branding**
   - What we know: Template uses `$company->logo` for branding
   - What's unclear: Are company logos always available? What if logo path is invalid?
   - Recommendation: Add fallback to company name text if logo missing/invalid

5. **Queue Worker Deployment**
   - What we know: Queue configured to use database driver
   - What's unclear: Is queue worker running in production? Supervised?
   - Recommendation: Document queue worker deployment requirement in phase verification

## Sources

### Primary (HIGH confidence)
- Existing codebase: `app/Mail/InvoiceMail.php`, `app/Jobs/CreateBulkInvoicesJob.php`, `app/Http/Controllers/InvoiceController.php`
- Existing codebase: `resources/views/invoice/pdf/invoice.blade.php` (PDF-optimized template)
- Composer packages: `barryvdh/laravel-dompdf` v3.1 installed and configured
- Queue configuration: `config/queue.php` (database driver, connections defined)
- Mail configuration: `config/mail.php` (supports smtp, ses, postmark, resend)

### Secondary (MEDIUM confidence)
- [Laravel 11.x Mail Documentation](https://laravel.com/docs/11.x/mail) - Official Laravel docs
- [Laravel 11 Generate PDF and Send Email Tutorial](https://www.itsolutionstuff.com/post/laravel-11-generate-pdf-and-send-email-tutorialexample.html)
- [barryvdh/laravel-dompdf GitHub](https://github.com/barryvdh/laravel-dompdf) - Official package repository
- [How to Configure Mail in Laravel](https://oneuptime.com/blog/post/2026-02-02-laravel-mail-configuration/view) - 2026 configuration guide
- [Laravel Send Email Tutorial with Code Snippets [2026]](https://mailtrap.io/blog/send-email-in-laravel/)

### Tertiary (LOW confidence)
- [Laracasts: How to send PDF files using QUEUES?](https://laracasts.com/discuss/channels/laravel/how-to-send-pdf-files-using-queues) - Community discussion
- [Generate PDF using job and then attach to queueable email](https://laracasts.com/discuss/channels/laravel/generate-pdf-using-job-and-then-attach-to-queueable-email) - Community patterns
- [Laravel 11 Send Email with Attachment Example](https://medium.com/@techsolutionstuff/laravel-11-send-email-with-attachment-example-8664e9ff3b01)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All packages already installed and proven in codebase
- Architecture: HIGH - Existing CreateBulkInvoicesJob provides clear pattern, Laravel 11 docs comprehensive
- Pitfalls: MEDIUM - Email size limits and queue worker deployment need runtime validation
- Code examples: HIGH - Extracted from existing working codebase and official Laravel 11 docs

**Research date:** 2026-02-13
**Valid until:** 2026-03-13 (30 days - stable technology stack)

**Critical findings:**
1. Infrastructure ready: DomPDF installed, queue configured, email providers configured
2. Template ready: `invoice.pdf.invoice` already has PDF-optimized rendering logic
3. Pattern proven: InvoiceMail shows correct approach (just needs uncommenting + refinement)
4. Main gap: No accountant email field identified - using `companies.email` is assumption
5. Success page ready: Already displays invoice list, just needs PDF download links added
