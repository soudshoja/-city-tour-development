# Phase 02: UI & Preview Workflow - Research

**Researched:** 2026-02-13
**Domain:** Laravel 11 UI/UX, Livewire 3.5, Blade templating, session-based preview workflows
**Confidence:** MEDIUM-HIGH

## Summary

Phase 2 implements a preview-and-approval workflow for bulk invoice creation. The agent uploads an Excel file (Phase 1), the system validates it and stores it in session/database, then shows a preview page grouping tasks by client and invoice date. The agent can approve (triggering invoice creation in Phase 3) or reject (discarding the upload). This phase focuses on UI presentation, session state management, and user approval actions without actually creating invoices.

The research covers three technical domains: (1) **Laravel collection grouping** for multi-level aggregation by client and date, (2) **Preview workflow patterns** using session flash data and database state, and (3) **UI components** leveraging Blade templates with Alpine.js and Tailwind CSS for interactive approval/rejection actions.

**Primary recommendation:** Use the existing `BulkUpload` database record as the source of truth for preview state (status='validated'), display preview using Blade template with Laravel collection groupBy for multi-level aggregation, and use Alpine.js for approve/reject confirmation modals with standard Laravel form POST to controller actions that update BulkUpload status and redirect with flash messages.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 11.x | Backend routing, validation, session, database | Existing project stack, mature MVC framework |
| Blade Templates | Built-in | Server-side templating for HTML views | Laravel's default templating engine, compiled and cached |
| Livewire | 3.5 | Full-stack reactive UI components | Already installed (`livewire/livewire: ^3.5`), reduces JavaScript boilerplate |
| Alpine.js | 3.x | Lightweight reactive JavaScript | Installed with Livewire, perfect for modals and simple interactions |
| Tailwind CSS | 3.x | Utility-first CSS framework | Existing project stack based on invoice templates |
| Laravel Collections | Built-in | Data manipulation and grouping | Native PHP collections, optimized for multi-level aggregation |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Barryvdh/Laravel-DomPDF | ^3.1 | PDF generation | Already installed for invoice PDFs (Phase 4 will use) |
| Maatwebsite/Laravel-Excel | ^3.1 | Excel import/export | Already installed and used in Phase 1 for template download |
| Laravel Session | Built-in | Flash messages and temporary data | For success/error notifications after approve/reject |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Database state (BulkUpload) | Pure session storage | Session expires/lost on logout, database provides audit trail and persistence |
| Blade + Alpine.js | Full Livewire components | Livewire adds overhead for simple preview page, Blade sufficient for read-only display |
| Route model binding | Manual ID lookup | Route model binding cleaner but requires Route::bind() setup, manual lookup fine for this use case |

**Installation:**
All dependencies already installed in project (`composer.json` verified). No additional packages needed.

## Architecture Patterns

### Recommended Project Structure
```
app/Http/Controllers/
├── BulkInvoiceController.php    # Existing: upload(), add preview(), approve(), reject()

resources/views/
├── bulk-invoice/
│   ├── preview.blade.php         # NEW: Preview page with grouped invoice display
│   └── success.blade.php         # NEW: Success page after approval

routes/
└── web.php                       # Add preview, approve, reject routes
```

### Pattern 1: Database-Backed Preview State
**What:** Use `BulkUpload` record with status='validated' as preview state, load eager-loaded relationships for display
**When to use:** When preview data must persist across page refreshes, browser closes, or user navigation
**Example:**
```php
// Controller: Load preview data
public function preview(int $uploadId): View
{
    $user = Auth::user();
    $companyId = getCompanyId($user);

    $bulkUpload = BulkUpload::where('id', $uploadId)
        ->where('company_id', $companyId)
        ->where('status', 'validated')
        ->with(['rows.client', 'rows.task', 'rows.supplier'])
        ->firstOrFail();

    // Group valid rows by (client_id, invoice_date)
    $invoiceGroups = $bulkUpload->rows()
        ->where('status', 'valid')
        ->get()
        ->groupBy(function($row) {
            $clientId = $row->client_id;
            $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');
            return "{$clientId}_{$invoiceDate}";
        });

    return view('bulk-invoice.preview', compact('bulkUpload', 'invoiceGroups'));
}
```

**Rationale:** Database provides audit trail, survives session expiry, enables multi-tenant isolation via company_id scoping.

### Pattern 2: Multi-Level Collection GroupBy
**What:** Group collection by multiple fields sequentially to create nested structure (client → date → tasks)
**When to use:** When displaying hierarchical data like "invoices grouped by client and date"
**Example:**
```php
// Source: https://christoph-rumpel.com/2018/1/groupby-multiple-levels-in-laravel
$grouped = $collection->groupBy([
    function($row) { return $row->client_id; },
    function($row) { return $row->raw_data['invoice_date']; }
]);

// Result structure:
// [
//   client_id_1 => [
//     '2026-02-10' => Collection([row1, row2]),
//     '2026-02-11' => Collection([row3])
//   ],
//   client_id_2 => [...]
// ]
```

**Alternative (simpler for this use case):** Single-level grouping with composite key as shown in Pattern 1, easier to iterate in Blade.

### Pattern 3: Alpine.js Confirmation Modal
**What:** Use Alpine.js `x-data`, `x-show`, and `x-on:click` to show confirmation modal before form submission
**When to use:** When user action requires confirmation (approve all invoices, reject upload)
**Example:**
```html
<!-- Source: https://codecourse.com/articles/form-submit-confirmation-with-alpinejs -->
<div x-data="{ showModal: false }">
    <button @click="showModal = true" class="btn-success">
        Approve All Invoices
    </button>

    <!-- Modal -->
    <div x-show="showModal"
         @keydown.escape="showModal = false"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-xl">
            <h3 class="text-lg font-bold mb-4">Confirm Approval</h3>
            <p>Create 5 invoices for 3 clients?</p>
            <div class="mt-6 flex gap-4">
                <form method="POST" action="{{ route('bulk-invoices.approve', $bulkUpload->id) }}">
                    @csrf
                    <button type="submit" class="btn-success">Confirm</button>
                </form>
                <button @click="showModal = false" class="btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>
```

**Why Alpine.js over Livewire:** Simpler for modal show/hide logic, no server roundtrip needed for UI state.

### Pattern 4: RESTful Approve/Reject Actions
**What:** POST routes for approve and reject actions that update BulkUpload status and redirect with flash messages
**When to use:** Standard for state-changing actions (approve → processing, reject → failed)
**Example:**
```php
// routes/web.php
Route::post('/bulk-invoices/{id}/approve', [BulkInvoiceController::class, 'approve'])
    ->name('bulk-invoices.approve');
Route::post('/bulk-invoices/{id}/reject', [BulkInvoiceController::class, 'reject'])
    ->name('bulk-invoices.reject');

// BulkInvoiceController
public function approve(int $id): RedirectResponse
{
    $user = Auth::user();
    $companyId = getCompanyId($user);

    $bulkUpload = BulkUpload::where('id', $id)
        ->where('company_id', $companyId)
        ->where('status', 'validated')
        ->firstOrFail();

    // Update status to trigger Phase 3 queue job (future implementation)
    $bulkUpload->update(['status' => 'processing']);

    // Source: https://laravel.com/docs/11.x/redirects
    return redirect()
        ->route('bulk-invoices.success', $id)
        ->with('message', 'Invoices are being created. You will be notified when complete.');
}

public function reject(int $id): RedirectResponse
{
    $bulkUpload = BulkUpload::where('id', $id)
        ->where('company_id', $companyId)
        ->where('status', 'validated')
        ->firstOrFail();

    $bulkUpload->update(['status' => 'rejected']);

    return redirect()
        ->route('bulk-invoices.index')
        ->with('message', 'Upload rejected and discarded.');
}
```

**Rationale:** RESTful pattern matches existing Laravel controller conventions, POST for state changes, redirect with flash for UX feedback.

### Anti-Patterns to Avoid
- **Storing preview data in session only:** Session expires, no audit trail, multi-tenant isolation harder
- **Using GET for approve/reject:** CSRF vulnerability, breaks REST semantics, can be triggered by link crawlers
- **Inline JavaScript without Alpine.js:** Existing project uses Alpine.js, consistency matters
- **Client-side grouping in JavaScript:** Server-side grouping faster, less client payload, easier to test

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Multi-level data grouping | Custom nested loops | Laravel Collections `groupBy()` with array/closure | Handles edge cases (null keys, empty groups), optimized C-level implementation, chainable |
| Session flash messages | Custom session wrapper | `redirect()->with('key', 'value')` and `session('key')` | Built-in, automatic cleanup after next request, integrates with validation errors |
| Modal confirmation UI | Custom modal from scratch | Alpine.js `x-show` pattern or existing project modal component | Accessibility (ESC key, focus trap), z-index management, backdrop clicks |
| Date grouping logic | String concatenation for composite keys | Collection `groupBy(fn($row) => [$row->client_id, $row->date])` for nested | Type-safe, handles nulls, easier to refactor |
| Excel export for error report | Manual PHPSpreadsheet | Maatwebsite/Laravel-Excel (already in Phase 1) | Color coding, auto-sizing, multi-sheet support, memory-efficient streaming |

**Key insight:** Laravel's collection API is incredibly powerful for data transformation. Custom loops are almost always inferior to `groupBy()`, `map()`, `reduce()`, and `filter()` chains — they're more testable, more readable, and handle edge cases you'll forget.

## Common Pitfalls

### Pitfall 1: N+1 Query Problem on Preview Page
**What goes wrong:** Loading `$bulkUpload->rows` in loop without eager loading related clients/tasks/suppliers causes hundreds of queries
**Why it happens:** Eloquent lazy-loads relationships by default, each `$row->client->name` triggers new query
**How to avoid:** Always use `with()` to eager load relationships
**Warning signs:** Preview page slow (>1s), Laravel Debugbar shows 100+ queries

**Prevention:**
```php
// BAD: N+1 queries
$bulkUpload = BulkUpload::find($id);
foreach ($bulkUpload->rows as $row) {
    echo $row->client->name; // +1 query per row
}

// GOOD: Eager loading
$bulkUpload = BulkUpload::with(['rows.client', 'rows.task', 'rows.supplier'])->find($id);
foreach ($bulkUpload->rows as $row) {
    echo $row->client->name; // No additional queries
}
```

### Pitfall 2: Missing Multi-Tenant Isolation
**What goes wrong:** Agent from Company A can view/approve uploads from Company B by guessing upload IDs
**Why it happens:** Forgot to add `->where('company_id', $companyId)` in query
**How to avoid:** ALWAYS scope by company_id when loading BulkUpload records
**Warning signs:** Security audit flags cross-company data access

**Prevention:**
```php
// BAD: No company_id check
$bulkUpload = BulkUpload::findOrFail($id);

// GOOD: Company scoped
$bulkUpload = BulkUpload::where('id', $id)
    ->where('company_id', $companyId)
    ->firstOrFail();
```

**Better:** Use model scope from Phase 1:
```php
$bulkUpload = BulkUpload::forCompany($companyId)->findOrFail($id);
```

### Pitfall 3: Forgetting CSRF Token on Forms
**What goes wrong:** POST to approve/reject routes fails with 419 error "CSRF token mismatch"
**Why it happens:** HTML forms with POST/PUT/DELETE require `@csrf` directive in Blade
**How to avoid:** Add `@csrf` inside every `<form method="POST">` tag
**Warning signs:** Form submission fails with 419 status code

**Prevention:**
```html
<!-- BAD: Missing CSRF -->
<form method="POST" action="{{ route('bulk-invoices.approve', $id) }}">
    <button>Approve</button>
</form>

<!-- GOOD: CSRF included -->
<form method="POST" action="{{ route('bulk-invoices.approve', $id) }}">
    @csrf
    <button>Approve</button>
</form>
```

### Pitfall 4: Race Condition on Approve/Reject
**What goes wrong:** Agent clicks "Approve" twice quickly, status changes from validated → processing → processing (should be validated → processing → completed/failed only)
**Why it happens:** No optimistic locking or status validation before update
**How to avoid:** Check current status before updating, use `where('status', 'validated')->update(['status' => 'processing'])`
**Warning signs:** Duplicate invoice creation attempts, status field shows unexpected values

**Prevention:**
```php
// BAD: No status check
$bulkUpload->update(['status' => 'processing']);

// GOOD: Conditional update with status check
$updated = BulkUpload::where('id', $id)
    ->where('status', 'validated')
    ->update(['status' => 'processing']);

if ($updated === 0) {
    return redirect()->back()->withErrors(['error' => 'Upload already processed or invalid status.']);
}
```

### Pitfall 5: Displaying Raw Error JSON
**What goes wrong:** Preview page shows `{"task_id":"required","supplier_name":"invalid"}` instead of human-readable messages
**Why it happens:** `raw_data` and `errors` columns are JSON, Blade outputs them as strings
**How to avoid:** Cast to array in model (`$casts = ['errors' => 'array']`), then iterate in Blade
**Warning signs:** Preview page shows `Array` or JSON strings instead of formatted lists

**Prevention:**
```php
// In BulkUploadRow model (already done in Phase 1)
protected $casts = [
    'errors' => 'array',
    'raw_data' => 'array',
];
```

```blade
<!-- BAD: Displays raw JSON or "Array" -->
{{ $row->errors }}

<!-- GOOD: Formatted list -->
@if($row->errors)
    <ul class="text-red-600">
        @foreach($row->errors as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
```

## Code Examples

Verified patterns from official sources and existing codebase:

### Preview Page Display with Grouped Invoices
```blade
{{-- resources/views/bulk-invoice/preview.blade.php --}}
<x-app-layout>
    <div class="panel">
        <h2 class="text-2xl font-bold mb-4">Preview Bulk Upload</h2>

        <div class="bg-blue-50 border border-blue-200 p-4 rounded mb-6">
            <h3 class="font-semibold">Summary</h3>
            <p>File: {{ $bulkUpload->original_filename }}</p>
            <p>Total rows: {{ $bulkUpload->total_rows }}</p>
            <p>Valid invoices to create: {{ count($invoiceGroups) }}</p>
        </div>

        @foreach($invoiceGroups as $groupKey => $rows)
            @php
                $firstRow = $rows->first();
                $client = $firstRow->client;
                $invoiceDate = $firstRow->raw_data['invoice_date'];
                $taskCount = $rows->count();
                $total = $rows->sum(fn($r) => $r->raw_data['amount'] ?? 0);
            @endphp

            <div class="border rounded p-4 mb-4">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h4 class="font-bold text-lg">{{ $client->name }}</h4>
                        <p class="text-gray-600">{{ $client->phone }}</p>
                        <p class="text-sm text-gray-500">Invoice Date: {{ $invoiceDate }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">{{ $taskCount }} task(s)</p>
                        <p class="font-bold text-lg">{{ number_format($total, 3) }} KWD</p>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left">Task Type</th>
                            <th class="p-2 text-left">Supplier</th>
                            <th class="p-2 text-left">Status</th>
                            <th class="p-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td class="p-2">{{ $row->raw_data['task_type'] }}</td>
                                <td class="p-2">{{ $row->supplier->name ?? 'N/A' }}</td>
                                <td class="p-2">{{ $row->raw_data['task_status'] }}</td>
                                <td class="p-2 text-right">{{ $row->raw_data['amount'] ?? '0.000' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        {{-- Approve/Reject buttons with Alpine.js modal --}}
        <div x-data="{ showApproveModal: false, showRejectModal: false }" class="flex gap-4 mt-6">
            <button @click="showApproveModal = true" class="btn btn-success">
                Approve All ({{ count($invoiceGroups) }} invoices)
            </button>
            <button @click="showRejectModal = true" class="btn btn-secondary">
                Reject Upload
            </button>

            {{-- Approve Modal --}}
            <div x-show="showApproveModal"
                 @keydown.escape="showApproveModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-xl max-w-md">
                    <h3 class="text-lg font-bold mb-4">Confirm Invoice Creation</h3>
                    <p>This will create <strong>{{ count($invoiceGroups) }} invoices</strong> for <strong>{{ $bulkUpload->valid_rows }} tasks</strong>.</p>
                    <p class="text-sm text-gray-600 mt-2">This action cannot be undone.</p>
                    <div class="mt-6 flex gap-4">
                        <form method="POST" action="{{ route('bulk-invoices.approve', $bulkUpload->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-success">Confirm</button>
                        </form>
                        <button @click="showApproveModal = false" class="btn btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>

            {{-- Reject Modal --}}
            <div x-show="showRejectModal"
                 @keydown.escape="showRejectModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-xl max-w-md">
                    <h3 class="text-lg font-bold mb-4">Confirm Rejection</h3>
                    <p>This will discard the upload and no invoices will be created.</p>
                    <div class="mt-6 flex gap-4">
                        <form method="POST" action="{{ route('bulk-invoices.reject', $bulkUpload->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-danger">Reject Upload</button>
                        </form>
                        <button @click="showRejectModal = false" class="btn btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

### Collection Grouping by Composite Key
```php
// Source: Based on https://laravel.com/docs/12.x/collections#method-groupby
// BulkInvoiceController::preview()

$validRows = $bulkUpload->rows()->where('status', 'valid')->get();

// Group by client_id + invoice_date composite key
$invoiceGroups = $validRows->groupBy(function($row) {
    $clientId = $row->client_id;
    $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');
    return "{$clientId}_{$invoiceDate}";
});

// Count unique invoice combinations
$invoiceCount = $invoiceGroups->count();

// Alternative: Multi-level nested grouping (more complex to display)
$nestedGroups = $validRows->groupBy([
    fn($row) => $row->client_id,
    fn($row) => $row->raw_data['invoice_date'] ?? date('Y-m-d')
]);
// Result: [client_id => [date => Collection([rows])]]
```

### Success Page After Approval
```blade
{{-- resources/views/bulk-invoice/success.blade.php --}}
<x-app-layout>
    <div class="panel max-w-2xl mx-auto">
        <div class="text-center">
            <svg class="w-16 h-16 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <h2 class="text-2xl font-bold mb-2">Invoices Created Successfully</h2>
            <p class="text-gray-600 mb-6">{{ session('message') }}</p>
        </div>

        <div class="bg-gray-50 border rounded p-4 mb-6">
            <h3 class="font-semibold mb-2">Upload Summary</h3>
            <p>File: {{ $bulkUpload->original_filename }}</p>
            <p>Invoices created: {{ $invoiceCount }}</p>
            <p>Tasks processed: {{ $bulkUpload->valid_rows }}</p>
        </div>

        {{-- Invoice links (populated by Phase 3 after creation) --}}
        @if($invoices->isNotEmpty())
            <h3 class="font-semibold mb-3">Created Invoices</h3>
            <ul class="space-y-2">
                @foreach($invoices as $invoice)
                    <li class="border rounded p-3 flex justify-between items-center">
                        <div>
                            <p class="font-semibold">{{ $invoice->invoice_number }}</p>
                            <p class="text-sm text-gray-600">{{ $invoice->client->name }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-sm btn-primary">View</a>
                            <a href="{{ route('invoices.download-pdf', $invoice->id) }}" class="btn btn-sm btn-secondary">PDF</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-6 text-center">
            <a href="{{ route('bulk-invoices.index') }}" class="btn btn-primary">Upload Another File</a>
        </div>
    </div>
</x-app-layout>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Session-only preview | Database-backed state (`BulkUpload.status`) | Laravel 5.x → 8.x | Persistent, auditable, survives logout |
| Manual JavaScript modals | Alpine.js declarative UI | ~2020 Alpine.js adoption | Less code, reactive, accessible by default |
| Raw SQL grouping | Collection `groupBy()` | Laravel 5.3+ Collections API | More readable, testable, chainable |
| Blade `@foreach` loops | Collection methods (`map`, `filter`, `groupBy`) | Laravel ecosystem evolution | Functional style, fewer bugs, easier to refactor |
| Inline CSS | Tailwind utility classes | ~2019 Tailwind adoption | Faster prototyping, consistent design system |

**Deprecated/outdated:**
- **Pure session-based workflows:** Lost on logout, no audit trail, hard to debug. Use database + session flash for best of both.
- **jQuery for DOM manipulation:** Alpine.js lighter (15KB vs 90KB), integrates better with Livewire, modern reactive approach.
- **Custom modal libraries (Bootstrap modals, jQuery UI):** Alpine.js modals simpler, no jQuery dependency, better accessibility patterns.

## Open Questions

1. **Should preview page auto-refresh if validation status changes?**
   - What we know: Upload is atomic (validated or failed), no partial states
   - What's unclear: If agent navigates away and returns, should preview still show?
   - Recommendation: Allow preview only if status='validated', redirect to error page if status changed. Add status check in `preview()` method.

2. **How to handle flagged rows (unknown clients) in preview?**
   - What we know: Phase 1 flags rows with `status='flagged'`, `flag_reason='unknown_client'`
   - What's unclear: Should preview show flagged rows separately? Should approve be blocked?
   - Recommendation: Show flagged rows in separate "Pending Review" section, allow approve only for valid rows, provide link to manual client matching (Phase 3 or 4 feature).

3. **Should approve action be synchronous or queued?**
   - What we know: Phase 3 handles invoice creation, Phase 2 just updates status
   - What's unclear: Should `approve()` dispatch queue job immediately or just set status='processing' and let Phase 3 handle it?
   - Recommendation: Phase 2 sets `status='processing'`, Phase 3 implements actual queue job. Keeps phases decoupled.

4. **What happens if agent closes browser during preview?**
   - What we know: BulkUpload record persists with status='validated'
   - What's unclear: Should there be a "resume preview" flow or upload list page?
   - Recommendation: Add `GET /bulk-invoices` index page (future) showing all uploads with "View Preview" links for validated uploads. Out of scope for Phase 2, but note for roadmap.

## Sources

### Primary (HIGH confidence)
- Laravel 11 Collections Documentation - https://laravel.com/docs/12.x/collections
- Laravel 11 Routing Documentation - https://laravel.com/docs/11.x/routing
- Laravel 11 Redirects Documentation - https://laravel.com/docs/11.x/redirects
- Existing codebase:
  - `/home/soudshoja/soud-laravel/app/Http/Controllers/BulkInvoiceController.php` - Upload flow, validation, error handling patterns
  - `/home/soudshoja/soud-laravel/app/Models/BulkUpload.php` - Status enum, relationships, company scoping
  - `/home/soudshoja/soud-laravel/resources/views/invoice/create.blade.php` - Blade + Alpine.js UI patterns
  - `/home/soudshoja/soud-laravel/composer.json` - Tech stack verification (Laravel 11, Livewire 3.5, DomPDF)

### Secondary (MEDIUM confidence)
- [GroupBy Multiple Levels in Laravel](https://christoph-rumpel.com/2018/1/groupby-multiple-levels-in-laravel) - Multi-level collection grouping
- [Laravel Database Transactions Examples](https://laraveldaily.com/post/laravel-database-transactions-examples) - Transaction patterns for bulk operations
- [Understanding Laravel Transactions](https://medium.com/@erlandmuchasaj/understanding-laravel-transactions-eec68012d394) - DB::beginTransaction() best practices
- [Laravel Session Flash Messages](https://laraveldaily.com/post/laravel-redirect-to-route-with-error-messages) - Redirect with flash pattern
- [Alpine.js Form Submit Confirmation](https://codecourse.com/articles/form-submit-confirmation-with-alpinejs) - Modal confirmation pattern
- [Laravel DomPDF Invoice Generation](https://laraveldaily.com/post/laravel-dompdf-generate-simple-invoice-pdf-with-images-css) - PDF best practices for Phase 4 context

### Tertiary (LOW confidence - patterns referenced but not critical)
- [Livewire Session Properties](https://wire-elements.dev/blog/getting-started-with-livewire-session-properties) - Alternative to database-backed state (not recommended for this use case)
- [Laravel Multi-Step Forms](https://medium.com/@maitrikt1998/mastering-multi-step-forms-in-laravel-11-a-livewire-approach-8eeb2f62c9eb) - Multi-step pattern (informational only, preview is single-step)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already installed and verified in composer.json
- Architecture patterns: MEDIUM-HIGH - Blade + Alpine.js patterns extracted from existing codebase, collection groupBy verified in Laravel docs
- Pitfalls: HIGH - N+1, CSRF, multi-tenant isolation are well-documented Laravel gotchas with established solutions
- Code examples: MEDIUM - Synthesized from existing codebase patterns + official docs, not production-tested yet

**Research date:** 2026-02-13
**Valid until:** ~30 days (Laravel 11 stable, Livewire 3.5 stable, patterns unlikely to change)

**Notes:**
- No CONTEXT.md exists for this phase, so no user constraints to document
- All dependencies already installed, no new packages needed
- Phase 1 provides `BulkUpload` and `BulkUploadRow` models with proper relationships and casts
- Preview is read-only UI, actual invoice creation happens in Phase 3
- Focus on UX: clear summary, grouped display, confirmation modals, success page
