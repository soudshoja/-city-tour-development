# Multi-Tenant Isolation Audit — Financial Data (citytourv2)

Scope: read-only checkout of `citytourv2-main` at
`C:/Users/User/AppData/Local/Temp/claude/.../scratchpad/citytourv2-main-checkout`.
Focus: can Company A ever read or write Company B's financial data (accounts, journal
entries, invoices, payments, credits, refunds, transactions)?

**Bottom line: yes, in multiple confirmed ways, including at least one unauthenticated
path and one write-path that lets any logged-in user drain another company's client
credit balance.**

## Summary table

| # | Severity | Finding | Location |
|---|----------|---------|----------|
| 1 | CRITICAL | Cross-tenant **credit theft**: `applyPaymentsToInvoice` / `getAvailablePayments` / `getInvoicePaymentHistory` never verify the `client_id` / `credit_id` / `invoice_id` supplied by the client belongs to the caller's company | `app/Services/PaymentApplicationService.php` (whole file), `app/Http/Controllers/InvoiceController.php:6284-6433` |
| 2 | CRITICAL | 8 authenticated AJAX endpoints trust client-supplied `company_id` with zero ownership check — dumps another company's chart of accounts, branches, agents, suppliers, **bank account details**, invoices | `app/Http/Controllers/AccountingController.php:286-448` |
| 3 | CRITICAL | `generatePdf()` builds an invoice PDF from `invoice_number` alone — ignores the `$companyId` route param entirely, no auth | `app/Http/Controllers/InvoiceController.php:3270-3283` |
| 4 | CRITICAL | Public (`withoutMiddleware(['auth'])`) invoice view/PDF/proforma routes keyed by **sequential, guessable** `{companyId}/{invoiceNumber}` — full internet-facing enumeration of every company's invoices, bank details, client PII | `routes/web.php:460,473-474,498-505`; number format in `InvoiceController.php:2886-2889`, `:371,1361` |
| 5 | CRITICAL | `lockInvoice`/`unlockInvoice`/`getLossBearer`/`updateLossBearer` use Laravel route-model binding on `Invoice` by raw ID with only a *role* check, no company-match check — any Company/Accountant/Admin-role user can lock or rewrite the loss-bearer split of another company's invoice | `app/Http/Controllers/InvoiceController.php:6456-6560` |
| 6 | CRITICAL | Systemic: every Policy that gates financial models (`InvoicePolicy`, `AccountPolicy`, `PaymentPolicy`, `CreditPolicy`, `RefundPolicy`) checks only a Spatie permission string, never the record's `company_id` vs. the user's company | `app/Policies/InvoicePolicy.php`, `AccountPolicy.php`, `PaymentPolicy.php`, `CreditPolicy.php`, `RefundPolicy.php` |
| 7 | CRITICAL (architectural) | The entire invoice/payment-application pipeline — `Invoice`, `InvoiceDetail`, `InvoicePartial`, `PaymentApplication`, `Credit`, `Refund`, `InvoiceReceipt`, `Client` — has **no tenant scope at all** (no trait, no global scope; `invoices` table doesn't even have a `company_id` column). Isolation depends entirely on every one of dozens of hand-written queries in a 7,700-line controller remembering to add `whereHas('agent.branch.company', …)` | `app/Models/Invoice.php`, `InvoiceDetail.php`, `InvoicePartial.php`, `PaymentApplication.php`, `Credit.php`, `Refund.php`, `InvoiceReceipt.php`, `Client.php`; `database/migrations/2024_10_29_063642_create_invoices_table.php` |
| 8 | HIGH | `multiPaymentLinkInitiate` bypasses the company scope on `PaymentMethod` and loads it by raw client-supplied ID with no ownership check | `app/Http/Controllers/PaymentController.php:6784` (also `:3229`) |
| 9 | HIGH | `Transaction` model reimplements tenant scoping ad hoc instead of using `BelongsToCompany`; ADMIN role is scoped to "all companies" here vs. "one company via session" everywhere else; BRANCH role is scoped by `branch_id` only, never validated against `company_id` | `app/Models/Transaction.php:43-64` |
| 10 | MEDIUM | Fail-open scoping: `if ($companyId) { ...->where('company_id', $companyId) }` around raw `DB::table('transactions')`/`journal_entries` joins — if `getCompanyId()` resolves to `null` (broken/orphaned role relation), the filter is silently skipped and the query returns **all companies'** rows | `app/Http/Controllers/ReportController.php:37-65` |
| 11 | MEDIUM | `CreditController::index()` gives ADMIN role an entirely unfiltered, all-companies credit list (no `company_id` clause at all in that branch) | `app/Http/Controllers/CreditController.php:27-45` |
| 12 | LOW | `AccountingController::exportExcel` builds the ledger Excel purely from client-POSTed JSON (`$request->input('ledgers')`) with no server-side re-query — an attacker can inject arbitrary fabricated rows into an official-looking export | `app/Http/Controllers/AccountingController.php:210-219` |
| 13 | LOW (latent) | `AccountTransaction` model has a `company_id` column but no scope/trait at all; currently unreferenced by any controller (dead code today, live risk the day it's wired up) | `app/Models/AccountTransaction.php` |

**Counts: 7 Critical, 2 High, 2 Medium, 2 Low.**

**Worst finding:** any authenticated user of *any* company can call the invoice
payment-application endpoints with another company's `client_id`/`credit_id`, see that
company's client's stored credit/refund balances, and then actually **apply** that
balance to pay off an unrelated invoice — a genuine cross-tenant funds-transfer/fraud
primitive, not just a read leak (`PaymentApplicationService::applyPaymentsToInvoice`,
`InvoiceController::getAvailablePayments`/`applyPaymentsToInvoice`).

---

## 1. How tenancy is *supposed* to work

### 1.1 `BelongsToCompany` trait

`app/Traits/BelongsToCompany.php`:

```php
protected static function bootBelongsToCompany(): void
{
    static::addGlobalScope('company', function (Builder $q) {
        if (Auth::check()) {
            $id = static::resolveCompanyId();
            if ($id !== null) {
                $q->where($q->qualifyColumn('company_id'), $id);
            }
        }
    });

    static::creating(function (Model $model) {
        if ($model->company_id === null && Auth::check()) {
            $id = static::resolveCompanyId();
            if ($id !== null) {
                $model->company_id = $id;
            }
        }
    });
}
```

`resolveCompanyId()` calls the global helper `getCompanyId($user)`
(`app/Helper/helper.php:6-23`):

```php
function getCompanyId($user): ?int
{
    switch ($user->role_id) {
        case Role::ADMIN:      return (int) session('company_id', 1);
        case Role::COMPANY:    return $user->company?->id;
        case Role::BRANCH:     return $user->branch?->company?->id;
        case Role::AGENT:      return $user->agent?->branch?->company?->id;
        case Role::ACCOUNTANT: return $user->accountant?->branch?->company?->id;
        default:               return null;
    }
}
```

When a model uses `BelongsToCompany`, **every** Eloquent query (`::find()`, `::all()`,
`::where()`, relationship loads, etc.) is automatically constrained to
`company_id = <the logged-in user's resolved company>`, and new rows auto-fill
`company_id` on create. This is a solid, correct mechanism — *when applied*.

Its known weak points:
- It does nothing for raw `DB::table()` queries, `withoutGlobalScope()`/`withoutGlobalScopes()` calls, or unauthenticated requests (`Auth::check()` false → scope is a no-op).
- ADMIN is scoped to **one** company at a time via `session('company_id', 1)`, not "all companies" — this differs from how some controllers (e.g. `CreditController`) treat ADMIN, see Finding 11.

### 1.2 Coverage — which financial models actually use it

| Model | Uses `BelongsToCompany` (or equivalent scope) | `company_id` column exists |
|---|---|---|
| `Account` | Yes | Yes |
| `JournalEntry` | Yes | Yes |
| `Payment` | Yes | Yes |
| `PaymentMethod` | Yes | Yes |
| `Charge` | Yes | Yes |
| `Transaction` | **Custom, non-equivalent** scope in `booted()` (see Finding 9) | Yes |
| `Invoice` | **No** | **No column at all** |
| `InvoiceDetail` | **No** | No |
| `InvoicePartial` | **No** | No |
| `PaymentApplication` | **No** | No |
| `Credit` | **No** | Yes (column present, unenforced) |
| `Refund` | **No** | Yes (column present, unenforced) |
| `InvoiceReceipt` | **No** | No |
| `AccountTransaction` | **No** | Yes (column present, unenforced) |
| `Client` | **No** | Yes (column present, unenforced) |

Only 5 of the ~15 financial models in scope are protected by an automatic tenant
scope. Everything downstream of `Invoice` (the central financial document) has zero
model-layer protection — including the entire payments/credits/refunds pipeline built
on it — and `invoices` doesn't even have a `company_id` column to filter on; scoping
must go through `invoice->agent->branch->company_id` by hand, every time.

---

## 2. Critical findings

### Finding 1 — Cross-tenant credit application (the worst one)

`app/Services/PaymentApplicationService.php`:

```php
public function applyPaymentsToInvoice(int $invoiceId, array $paymentAllocations, ...): array {
    ...
    $invoice = Invoice::findOrFail($invoiceId);            // no company check
    ...
    foreach ($paymentAllocations as $allocation) {
        ...
        $sourceCredit = Credit::findOrFail($creditId);      // no company check
        ...
        // deducts $sourceCredit's balance and marks $invoice as paid
    }
}
```

and the controller layer that feeds it, `app/Http/Controllers/InvoiceController.php`:

```php
6284: public function getAvailablePayments(Request $request): JsonResponse
...
6295:   $clientId = $request->input('client_id');
6296:   $availablePayments = Credit::getAvailablePaymentsForClient($clientId);   // no company check
...
6407: public function getInvoicePaymentHistory(int $invoiceId): JsonResponse
6409:   $invoice = Invoice::findOrFail($invoiceId);                              // no company check
```

Routes (`routes/web.php:479-482`) sit only under the top-level `auth` middleware group
— no role or company middleware:

```php
Route::post('/available-payments', [InvoiceController::class, 'getAvailablePayments'])->name('available-payments');
Route::post('/apply-payments', [InvoiceController::class, 'applyPaymentsToInvoice'])->name('apply-payments');
Route::get('/payment-history/{invoiceId}', [InvoiceController::class, 'getInvoicePaymentHistory'])->name('payment-history');
```

Laravel's `exists:clients,id` / `exists:credits,id` / `exists:invoices,id` validation
rules (used in these controller methods) only check the row exists *somewhere in the
database* — they do not scope by company.

**How the leak works:** Any authenticated user (any role, any company) can:
1. POST `client_id` = a client belonging to a *different* company to `available-payments` and get back that client's topup/refund credit balances, voucher numbers, refund numbers and dates.
2. POST that `credit_id` plus their own (or any) `invoice_id` to `apply-payments`. `PaymentApplicationService` debits the foreign company's `Credit` balance, creates a `PaymentApplication`/`InvoicePartial` row, and marks the target invoice "paid" — corrupting both companies' books and effectively stealing the other company's client credit.
3. GET `payment-history/{invoiceId}` for any invoice ID and read who paid it, voucher numbers, and amounts.

**Fix:** In `PaymentApplicationService::applyPaymentsToInvoice()` and
`InvoiceController::getAvailablePayments/getInvoicePaymentHistory`, resolve the
caller's `company_id` via `getCompanyId(Auth::user())` and require
`$invoice->agent->branch->company_id === $companyId` and
`$sourceCredit->company_id === $companyId` (and `$client->company_id === $companyId`)
before doing anything — throw/403 otherwise. Add the same check to
`linkPaymentsToInvoicePartial`.

### Finding 2 — `AccountingController` trusts `$request->company_id`

`app/Http/Controllers/AccountingController.php`, all under the plain `auth` group
(`routes/web.php:319-326`), e.g.:

```php
288: public function getAccountsByCompanyReceivable(Request $request)
     { $accounts = Account::where('company_id', $request->company_id)-> ... }

342: public function getBranchByCompany(Request $request)
     { $branches = Branch::where('company_id', $request->company_id)->get(); }

400: public function getBankAccountByCompany(Request $request)
402:     $companyId = $request->company_id;
408:     $parentIds = Account::where('name', 'LIKE', '%Bank Accounts%')->where('company_id', $companyId)->pluck('id');
415:     $bankaccounts = Account::whereIn('parent_id', $parentIds)->where('company_id', $companyId)->get();

433: public function getInvoicesByJournalEntry(Request $request)
     { $ledgerEntries = JournalEntry::where('company_id', $request->company_id)->pluck('invoice_id'); ... }
```

`Account`/`JournalEntry` have the `BelongsToCompany` global scope, but these methods
explicitly re-filter with `$request->company_id` — for `Account`/`JournalEntry` the
extra `where()` is *additive* to the auto-scope only if the two values match; when
they differ, the explicit clause combined with the model's own scope actually means
the query becomes `WHERE company_id = <user's company> AND company_id = <attacker's
requested company>` which returns nothing for the auto-scoped models. **But**
`getBranchByCompany` (`Branch` has no `BelongsToCompany` at all) and
`getInvoicesByJournalEntry`'s subsequent `Invoice::whereIn('id', ...)` (unscoped) leak
in full, and any endpoint here reachable by a role for which `getCompanyId()` returns
`null` bypasses the auto-scope entirely, leaving `$request->company_id` as the only
filter. Regardless of the exact per-model interaction, this is broken-by-design:
**no code path here ever checks that `$request->company_id` equals the caller's own
company**, so `Branch`, chart-of-accounts labels, agent lists, supplier lists and
(most sensitively) **bank account records** for an arbitrary company are directly
reachable by any logged-in user, including low-privilege Agent/Branch accounts.

**Fix:** Ignore `$request->company_id` for non-admin roles; derive `$companyId =
getCompanyId(Auth::user())` server-side as `InvoiceController::edit()` correctly does
(`InvoiceController.php:404`), and only let ADMIN pass an explicit company id (still
via a validated, permission-gated parameter).

### Finding 3 — `generatePdf()` ignores company scoping and auth entirely

```php
3270: public function generatePdf(int $companyId, string $invoiceNumber)
3271: {
3273:     $invoice = Invoice::where('invoice_number', $invoiceNumber)->with(...)->first();
          // $companyId parameter is never used to filter anything
3280:     $pdf = Pdf::loadView('invoice.pdf', compact('invoice', 'invoiceDetails', 'invoicePartials', 'paymentGateway'));
3282:     return $pdf->download("Invoice_{$invoiceNumber}.pdf");
3283: }
```

Route: `Route::get('/{companyId}/{invoiceNumber}/pdf', ...)->withoutMiddleware(['auth'])`
(`routes/web.php:499`). Compare with `showDetails()`/`show()` in the same file, which
*do* filter via `whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId))`
— `generatePdf` simply forgot it. Any `invoiceNumber` (see Finding 4 for how guessable
that is) returns a full PDF (client name, amounts, IBAN/SWIFT/bank fields) with zero
company check and zero authentication.

**Fix:** Add the same `whereHas('agent.branch.company', …)` filter used in `show()`,
and treat the missing check as a P0 bug independent of the auth question below.

### Finding 4 — Public invoice links are sequential and enumerable

Routes are intentionally public (client-facing invoice/payment links), which is a
legitimate pattern *if* the URL is unguessable:

```php
460: Route::get('/{companyId}/{invoiceNumber}/arabic', ...)->withoutMiddleware(['auth']);
498: Route::get('/{companyId}/{invoiceNumber}', [InvoiceController::class, 'show'])->withoutMiddleware(['auth']);
499: Route::get('/{companyId}/{invoiceNumber}/pdf', ...)->withoutMiddleware(['auth']);
500: Route::get('/{companyId}/{invoiceNumber}/proforma', ...)->withoutMiddleware(['auth']);
```

But `companyId` is the plain auto-increment `companies.id`, and `invoiceNumber` is
generated as:

```php
2886: public function generateInvoiceNumber($sequence)
2887: {
2888:     $year = now()->year;
2889:     return sprintf('INV-%s-%05d', $year, $sequence);
2890: }
```

with `$sequence` coming from a **per-company** counter (`InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1])`, line 1361). So every
company's invoices are `INV-2026-00001`, `INV-2026-00002`, … and `companyId` is `1, 2,
3, …`. An unauthenticated party can trivially enumerate `/invoice/1/INV-2026-00001`
through `/invoice/N/INV-2026-99999` and pull every company's invoices, PDFs, and
proforma documents — this is worse than a cross-tenant bug between logged-in users,
it's full public exposure.

**Fix:** Replace the two-part `{companyId}/{invoiceNumber}` key with a high-entropy
opaque token (UUID or signed URL, e.g. Laravel's `URL::temporarySignedRoute`) for any
route that must stay unauthenticated. Combine with Finding 3's fix as defense in depth.

### Finding 5 — Invoice lock / loss-bearer endpoints bound by raw ID only

```php
6456: public function lockInvoice(Invoice $invoice)
6459:     Gate::authorize('manageLocks', User::class);   // role/permission check only
...
6518: public function updateLossBearer(Request $request, Invoice $invoice): JsonResponse
6522:     if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) { ... 403 ... }
          // still no check that $invoice belongs to this user's company
```

Routes (`routes/web.php:506-509`) use implicit route-model binding on the numeric
`{invoice}` segment, with no scoping middleware. A Company-role user at Company A who
has `manageLocks` permission (or simply is COMPANY/ACCOUNTANT/ADMIN role) can lock,
unlock, or rewrite the agent/company loss-split percentages of **any invoice ID**,
including ones belonging to a different company.

**Fix:** Add an explicit ownership check
(`abort_unless($invoice->agent->branch->company_id === getCompanyId(Auth::user()), 403)`)
at the top of each of these four methods, or add a route-level scoped binding /
middleware that does it once.

### Finding 6 — Policies never check tenant ownership (systemic)

```php
// InvoicePolicy.php
public function view(User $user, Invoice $invoice): bool {
    return $user->can('view invoice') || $user->id == $invoice->user_id;  // Invoice has no user_id column
}

// AccountPolicy.php
public function view(User $user, Account $account) : bool {
    return $user->can('view account') || $user->id == $account->user_id; // Account has no user_id column either
}

// PaymentPolicy.php
public function view(User $user, Payment $payment) : bool {
    if ($user->can('view payment')) return true;               // permission alone is enough
    if ($user->agent !== null && $payment->agent_id === $user->agent->id) return true;
    return false;
}

// CreditPolicy.php / RefundPolicy.php
public function viewAny(User $user) : bool { return $user->can('view credit'/'view refund'); }
// no view($user, $model) method exists at all — no per-record check is even possible
```

`$invoice->user_id` and `$account->user_id` don't exist as columns on those models (see
their `$fillable` lists), so those secondary clauses are always false — meaning
**every one of these policies collapses to a bare permission-string check**, with no
comparison of the record's owning company to the acting user's company at all. Any
role that has been granted `view invoice` / `view account` / `view payment` (a normal
grant for Company/Branch/Accountant roles) is authorized by the Policy layer to view
*any* invoice/account/payment in the system, from any company, wherever
`Gate::authorize('view', $model)` is actually invoked. In practice several controllers
avoid this hole by hand-rolling their own `whereHas('agent.branch.company', …)` filter
before the record is even loaded (e.g. `InvoiceController::show/edit`), which is why
this isn't universally exploitable — but it means the *authorization layer itself*
provides no tenant guarantee, and every new/edited endpoint that leans on
`Gate::authorize('view', ...)` alone (rather than re-deriving its own scoped query) is
one refactor away from a leak.

**Fix:** Every `view`/`update`/`delete` policy method for a financial model must
compare the model's resolved `company_id` against `getCompanyId($user)` (or the
model's already-scoped relation), in addition to the permission check — not instead of
a scoped query, but as a second independent gate.

### Finding 7 — No tenant scope anywhere in the invoice/payment pipeline (architectural)

`Invoice`, `InvoiceDetail`, `InvoicePartial`, `PaymentApplication`, `Credit`, `Refund`,
`InvoiceReceipt`, and `Client` (the model `Invoice` chains through to reach a company)
have no `BelongsToCompany` trait, no global scope, and in the case of `invoices`,
`invoice_details`, `invoice_partials`, `payment_applications`, `invoice_receipt` — **no
`company_id` column at all** (confirmed against their `create_*` migrations). Tenant
boundaries for this entire subsystem exist only as a convention: "remember to add
`whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId))`" — repeated,
by hand, dozens of times across `InvoiceController.php` (7,700+ lines). Findings 1, 3,
4 and 5 above are exactly the places where that convention was forgotten; there is no
structural reason to believe those are the only ones, and every future change to this
file inherits the same risk with no automated backstop (no test, no scope, no policy
catches a missed filter).

**Fix (structural, beyond the point fixes above):** Either (a) add a `company_id`
column + `BelongsToCompany` to `Invoice` directly (denormalized, refreshed on
agent/client change) and propagate it to `InvoiceDetail`/`InvoicePartial`/`Credit`/
`Refund`/`PaymentApplication`/`InvoiceReceipt` so the existing global-scope mechanism
protects them automatically, or (b) introduce a dedicated `Invoice::scopeForCompany()`
/ policy-backed route-model-binding resolver and mandate its use via a lint rule /
code-review checklist, since the trait approach isn't available without the column.
Option (a) is strongly preferred — it's the same mechanism already proven for
`Account`/`JournalEntry`/`Payment`.

---

## 3. High-severity findings

### Finding 8 — `PaymentMethod::withoutGlobalScope('company')->find(...)` on user input

```php
// PaymentController.php:6784
$paymentMethod = PaymentMethod::withoutGlobalScope('company')
    ->with(['charge', 'paymentMethodGroup'])
    ->find($request->payment_method_id);   // no company ownership check after bypassing the scope
```

(also at `:3229`, partially mitigated there by a subsequent `where('company_id',
$companyId)`). The `:6784` instance in `multiPaymentLinkInitiate` has no such
mitigation — a caller can supply any `payment_method_id` and the code will happily
attach a different company's gateway/fee configuration (`Charge` relation) to the
payment being processed.

**Fix:** Drop `withoutGlobalScope('company')` unless there's a proven need (e.g. an
ADMIN cross-company action), and if it is needed, immediately re-check
`$paymentMethod->company_id === $companyId`.

### Finding 9 — `Transaction`'s hand-rolled scope diverges from the trait

```php
// app/Models/Transaction.php:43-64
protected static function booted()
{
    static::addGlobalScope('company', function ($query) {
        if (!auth()->check()) return;
        $user = auth()->user();
        if ($user->role_id == Role::ADMIN) {
            $query->where('company_id', '!=', null);      // effectively: ALL companies
        } else if ($user->role_id == Role::COMPANY) {
            $query->where('company_id', $user->company->id);
        } else if ($user->role_id == Role::BRANCH) {
            $query->where('branch_id', $user->branch->id); // no company_id check at all
        } else if ($user->role_id == Role::AGENT) {
            $query->where('company_id', $user->agent->branch->company->id);
        } else if ($user->role_id == Role::ACCOUNTANT) {
            $query->where('company_id', $user->accountant->branch->company->id);
        }
    });
}
```

Two problems: (1) ADMIN gets literally unscoped access to every company's
transactions here, whereas `BelongsToCompany`'s ADMIN branch restricts to one
session-selected company — an inconsistent trust model for the same role depending on
which model you query; (2) BRANCH is scoped only by `branch_id`, never cross-checked
against `company_id` — harmless only as long as `branch_id` values are guaranteed
globally unique and a branch can never be reassigned to a different company, which is
an assumption worth stating explicitly (and testing), not left implicit.

**Fix:** Replace this bespoke scope with `use BelongsToCompany;` for consistency, or
if the ADMIN "see everything" behavior is intentional here, document why it's
different from every other model and confirm ADMIN really is platform-operator-only
(never assignable to a customer company).

---

## 4. Medium / Low findings

### Finding 10 — Fail-open `if ($companyId)` around raw report queries

```php
// ReportController.php:37-65 (index(), live code — not behind the "maintenance" early-return
// used by agentReport()/accsummary() at lines 69 and 759)
$companyId = getCompanyId($user);
$agentsQuery = DB::table('transactions')->join(...)->select(...);
if ($companyId) {                      // <-- silently skipped if getCompanyId() returns null
    $agentsQuery->where('transactions.company_id', $companyId);
}
```

`getCompanyId()` returns `null` for COMPANY/BRANCH/AGENT/ACCOUNTANT roles whenever the
underlying relation is missing (e.g. `$user->company` is null due to bad data/an
orphaned user record). In that edge case this method returns every company's
transactions/journal entries mixed together, instead of failing closed. Note:
`agentReport()` and `accsummary()` in the same file have the identical pattern but are
currently dead code (`return view('reports.maintenance');` is the first line of each
method) — not exploitable today, but the pattern will reactivate verbatim if that
placeholder is ever removed.

**Fix:** `if (!$companyId) { abort(403); }` before building the query, matching the
fail-closed pattern already used elsewhere in the same controller (e.g.
`unpaidaccountsPayableReceivableReport`, `accountsReconciliationReport`).

### Finding 11 — `CreditController::index()` unfiltered for ADMIN

```php
// CreditController.php:27-45
$allCreditRecords = Credit::with('client');
if ($user->role_id == Role::ADMIN) {
    $allCreditRecords = $allCreditRecords;   // literally no company filter
} elseif ($user->role_id == Role::COMPANY) {
    $allCreditRecords = $allCreditRecords->where('company_id', $user->company->id);
} elseif ($user->role_id == Role::AGENT) {
    return abort(403, 'Unauthorized action.');
} else {
    return redirect()->route('dashboard')->with('error', 'Page not found.');  // BRANCH/ACCOUNTANT blocked (functional bug)
}
```

If ADMIN (`role_id=1`) is guaranteed to be platform-operator-only, this is by design
and consistent with "admin sees everything." Flagging because it's inconsistent with
`BelongsToCompany`'s admin behavior (one company via session) and because BRANCH/
ACCOUNTANT are simply broken here rather than scoped — worth an explicit product
decision either way.

### Finding 12 — Ledger export trusts client-submitted JSON

```php
// AccountingController.php:210-219
public function exportExcel(Request $request)
{
    $ledgers = $request->input('ledgers');            // not re-derived from DB
    $totalDebit = $request->input('total_debit');
    $totalCredit = $request->input('total_credit');
    return Excel::download(new LedgerExport($ledgers, $totalDebit, $totalCredit), 'JournalEntryReport.xlsx');
}
```

Not a read-leak (the frontend normally populates this from the already-scoped
`filterLedgers` AJAX call), but there is no server-side re-validation, so a modified
request can produce an official-looking `.xlsx` "ledger report" containing fabricated
numbers. Low severity, but worth tightening given this is accounting output that may
be relied on externally.

### Finding 13 — `AccountTransaction` unscoped (dead code today)

`app/Models/AccountTransaction.php` has a `company_id` fillable but no
`BelongsToCompany`/scope, and — unlike every other model in scope — is not referenced
by any controller in this checkout (`grep AccountTransaction:: app` → no hits). Not
exploitable today; flagged so it isn't wired up later without first adding the trait.

---

## 5. Recommended priority order

1. Fix Finding 1 immediately (active write-path fraud vector; highest business risk).
2. Fix Findings 2–5 (all directly reachable, several unauthenticated).
3. Fix Finding 6 by adding real per-record ownership checks to every financial Policy — this is cheap and closes an entire class of "future" bugs, not just today's.
4. Plan Finding 7 as a structural project: add `company_id` to `invoices` and propagate `BelongsToCompany` down the chain, rather than continuing to patch individual controller methods.
5. Address Findings 8–13 as normal hardening work.
