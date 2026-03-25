# Phase 20: Cancellation + Accounting - Research

**Researched:** 2026-03-24
**Domain:** Laravel cancellation flow, double-entry accounting, JournalEntry/Invoice creation, WhatsApp messaging, company statement generation
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Cancellation Flow (2-step via DOTW API)
- Step 1: `cancel_booking` with `confirm=no` calls `DotwService::cancelBooking(['confirm' => 'no', 'bookingCode' => X])` — returns penalty amount (charge field) without executing cancellation
- Step 2: User confirms — same endpoint called with `confirm=yes` and `penaltyApplied` amount — executes the cancellation
- Both steps return `bookingCode`, `refund`, `charge`, `status` from `parseCancellation()`
- DotwAIBooking status transitions: `confirmed` → `cancellation_pending` (after step 1 shown) → `cancelled` (after step 2 confirmed)
- WhatsApp message after step 1: show penalty amount, ask for explicit confirmation
- WhatsApp message after step 2: confirmation with warning that DOTW cancellation may take time to reflect on their portal

#### Cancellation Accounting (hybrid approach — locked from PROJECT.md)
- Penalty > 0 (charged cancellation): create Invoice + JournalEntry for the penalty amount — money moved
- Penalty = 0 (free cancellation): update DotwAIBooking status to `cancelled`, update CRM record — NO journal entry, no invoice
- B2B with credit: refund the original booking amount minus penalty back to credit line via `CreditService::refundCredit()`
- B2B without credit / B2C: penalty was already paid, refund difference via payment gateway (or note for manual processing)
- APR bookings: already invoiced at confirmation (Phase 19 LIFE-04 deferred to Phase 21 — but cancellation still needs to handle the case where APR booking was auto-invoiced)

#### Journal Entry Creation
- Uses existing JournalEntry model with debit/credit pattern
- CRITICAL (ACCT-04): Must use explicit `company_id` field — NOT rely on Auth global scope — since cancellations may be triggered from queue/scheduler context
- JournalEntry links: `invoice_id`, `task_id`, `company_id`, `branch_id`, `account_id`
- Account mapping: use existing Chart of Accounts (Account model) — cancellation penalty debits customer receivable, credits revenue
- Currency from DotwAIBooking record (stored at prebook time)

#### Invoice Creation for Penalties
- Use existing Invoice model: `client_id`, `agent_id`, `currency`, `amount`, `status`
- Invoice status: `paid` (if penalty deducted from credit) or `pending` (if penalty to be collected)
- Link InvoiceDetail with penalty line item description
- No invoice for free cancellations (CANC-04)

#### Company Statement (ACCT-02)
- New endpoint: GET /api/dotwai/statement — accepts phone, date_from, date_to
- Returns: list of bookings, cancellations, credits, debits for the company
- Matches with DOTW portal statement for reconciliation
- WhatsApp-formatted summary with totals

#### Credit Limit Management (ACCT-05)
- CreditService already has `getBalance()` from Phase 19
- Add: ability to view credit transactions history
- Company credit limit stored on CompanyDotwCredential (or Company model)
- Admin-only adjustment — not exposed via WhatsApp API

### Claude's Discretion
- CancellationService class structure (standalone or extend BookingService)
- AccountingService class structure (standalone service or split per concern)
- Statement format details (exact fields, grouping by date/type)
- How to handle edge case: DOTW cancellation succeeds but our accounting entry fails (retry? flag for admin?)
- Whether to create a separate AccountingService or add methods to BookingService
- Test strategy for accounting entries (mock vs real DB)

### Deferred Ideas (OUT OF SCOPE)
- Auto-reminders before cancellation deadline (Phase 21 — LIFE-01, LIFE-02)
- Auto-invoicing after deadline passes (Phase 21 — LIFE-03)
- APR auto-invoice on confirmation (Phase 21 — LIFE-04)
- Booking history endpoint (Phase 21 — HIST-01, HIST-02)
- Resend voucher (Phase 21 — HIST-03)
- Event webhooks for automation (Phase 21 — EVNT-01)
- Dashboard monitoring (Phase 22)
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CANC-01 | `cancel_booking` shows penalty amount before confirming (2-step) | DotwService::cancelBooking() already built with confirm=no/yes pattern; maps directly to 2-step flow |
| CANC-02 | Cancellation confirmation sent via WhatsApp with warning about DOTW confirmation delay | MessageBuilderService static pattern established; add formatCancellationPending() and formatCancellationConfirmed() |
| CANC-03 | Cancellation with penalty > 0 creates journal entry + invoice | JournalEntry::create() + Invoice::create() with explicit company_id; InvoiceStatus::UNPAID for pending, PAID for credit-deducted |
| CANC-04 | Free cancellation (penalty = 0) updates CRM/booking status only, no journal entry | Branch on charge value from parseCancellation(); status transition to `cancelled` only |
| ACCT-01 | Hybrid approach — all cancellations tracked in CRM, journal entries only for money movement | Implemented via CANC-03/CANC-04 branching logic in CancellationService |
| ACCT-02 | Company statement generation to match with DOTW portal | New StatementController + StatementService; queries DotwAIBooking + JournalEntry + Credit tables |
| ACCT-03 | No journal entry created until money moves or liability is confirmed | Enforced by only creating JournalEntry inside the confirm=yes path, after DOTW confirms |
| ACCT-04 | JournalEntry creation from queue/scheduler uses explicit company_id (not auth global scope) | Use `JournalEntry::withoutGlobalScopes()` when reading; always pass company_id explicitly when creating — established pattern from PaymentBridgeService |
| ACCT-05 | Company credit limit management for B2B agents | CreditService::getBalance() already built; add getCreditHistory() for transaction listing |
</phase_requirements>

---

## Summary

Phase 20 builds two tightly related capabilities: the 2-step cancellation flow and the hybrid accounting integration. The cancellation flow reuses the already-built `DotwService::cancelBooking()` which natively supports `confirm=no` (penalty preview) and `confirm=yes` (execute). The `parseCancellation()` method returns `{bookingCode, refund, charge, status}` — the `charge` field is the penalty to use for branching.

The accounting side requires careful attention to Laravel's Auth global scopes. Both `JournalEntry` and `Account` models apply a company scope tied to `auth()->user()->company`. Since cancellations may be triggered from API context (no authenticated user), all accounting writes must use explicit `company_id` and account lookups must use `withoutGlobalScopes()`. This pattern is already established in Phase 19's `PaymentBridgeService`.

The recommended architecture uses a standalone `CancellationService` (not extending `BookingService`) and a standalone `AccountingService`. This keeps concerns separated and matches the module's established pattern of single-purpose services. The Statement feature deserves its own `StatementService` + `StatementController` since it is a read-only reporting concern orthogonal to cancellation writes.

**Primary recommendation:** Create CancellationService + AccountingService as separate files; add cancel_booking route to existing BookingController; add statement route to a new StatementController.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| App\Models\JournalEntry | Existing | Double-entry journal entry creation | Already used project-wide; has all required fields |
| App\Models\Invoice | Existing | Invoice creation for penalty charges | Already used; auto-generates invoice_number via saving hook |
| App\Models\InvoiceDetail | Existing | Line item for penalty description | Paired with Invoice; required for statement reconciliation |
| App\Models\Credit | Existing | Credit refund on B2B cancellation | CreditService::refundCredit() already built in Phase 19 |
| App\Models\Account | Existing | Chart of Accounts lookup | Required for JournalEntry account_id resolution |
| App\Enums\InvoiceStatus | Existing | Invoice status constants | UNPAID/PAID/REFUNDED — validated on Invoice::saving() |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Illuminate\Support\Facades\DB | Laravel 11 | Transaction wrapping | Wrap cancel + accounting writes atomically |
| Illuminate\Support\Facades\Log | Laravel 11 | Channel-specific logging | Use Log::channel('dotw') for all cancellation logs |
| App\Modules\DotwAI\Services\DotwAIResponse | Existing | Response envelope | Wrap all controller responses |
| App\Modules\DotwAI\Services\MessageBuilderService | Existing | WhatsApp message formatting | Extend with cancellation message formatters |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Standalone CancellationService | Extending BookingService | Extension creates a god class; standalone keeps test surface small |
| Standalone AccountingService | Methods inside CancellationService | Accounting logic is reusable across phases 20+21; separate class is correct |
| Direct JournalEntry::create() | Custom accounting facade | JournalEntry::create() is the established codebase pattern — don't abstract further |

**Installation:** No new packages required. All dependencies are existing models and services.

---

## Architecture Patterns

### Recommended Project Structure
```
app/Modules/DotwAI/
├── Http/
│   ├── Controllers/
│   │   ├── BookingController.php        # Add cancelBooking() method here
│   │   └── StatementController.php      # New — getStatement() endpoint
│   └── Requests/
│       ├── CancelBookingRequest.php     # New — prebook_key + confirm + penalty_amount
│       └── StatementRequest.php         # New — date_from, date_to
├── Services/
│   ├── CancellationService.php          # New — cancel flow orchestration
│   ├── AccountingService.php            # New — JournalEntry + Invoice creation
│   └── StatementService.php             # New — statement query + formatting
└── Routes/api.php                        # Add cancel_booking + statement routes
```

### Pattern 1: 2-Step Cancellation via cancel_booking endpoint

**What:** Single endpoint handles both steps. Step determination is via `confirm` parameter (`no` = preview, `yes` = execute).
**When to use:** Every cancellation request goes through this endpoint.

```php
// CancelBookingRequest validation
public function rules(): array
{
    return [
        'phone'          => ['required', 'string'],
        'prebook_key'    => ['required', 'string'],
        'confirm'        => ['required', 'in:no,yes'],
        'penalty_amount' => ['required_if:confirm,yes', 'numeric', 'min:0'],
    ];
}
```

```php
// CancellationService::cancel() — the core orchestration method
public function cancel(DotwAIContext $context, array $input): array
{
    $booking = DotwAIBooking::where('prebook_key', $input['prebook_key'])
        ->where('company_id', $context->companyId)
        ->first();

    if ($booking === null) {
        return ['error' => true, 'code' => DotwAIResponse::PREBOOK_NOT_FOUND];
    }

    if ($booking->status !== DotwAIBooking::STATUS_CONFIRMED
        && $booking->status !== DotwAIBooking::STATUS_CANCELLATION_PENDING) {
        return ['error' => true, 'code' => DotwAIResponse::CANCELLATION_NOT_ALLOWED];
    }

    $dotwService = new DotwService($context->companyId);

    if ($input['confirm'] === 'no') {
        // Step 1: preview penalty
        $result = $dotwService->cancelBooking([
            'confirm'     => 'no',
            'bookingCode' => $booking->booking_ref,
        ]);
        $booking->update(['status' => DotwAIBooking::STATUS_CANCELLATION_PENDING]);
        return ['step' => 'preview', 'charge' => $result['charge'], 'refund' => $result['refund']];
    }

    // Step 2: execute cancellation
    $result = $dotwService->cancelBooking([
        'confirm'        => 'yes',
        'bookingCode'    => $booking->booking_ref,
        'penaltyApplied' => $input['penalty_amount'],
    ]);

    DB::transaction(function () use ($booking, $result, $context, $input) {
        $booking->update(['status' => DotwAIBooking::STATUS_CANCELLED]);

        if ((float) $result['charge'] > 0) {
            // Charged cancellation — create accounting entries
            $this->accountingService->createCancellationEntries($booking, $result['charge'], $context);
        }
        // Free cancellation: no accounting entries (CANC-04 / ACCT-01)
    });

    return ['step' => 'confirmed', 'charge' => $result['charge'], 'refund' => $result['refund']];
}
```

### Pattern 2: JournalEntry Creation with Explicit company_id (ACCT-04)

**What:** Always pass `company_id` explicitly. Never rely on `auth()->user()` in service/job context.
**When to use:** Every JournalEntry::create() call in this module.

```php
// AccountingService::createCancellationEntries()
public function createCancellationEntries(
    DotwAIBooking $booking,
    float $penaltyAmount,
    DotwAIContext $context,
): void {
    // Lookup accounts WITHOUT global scope (no auth in queue context)
    $receivableAccount = Account::withoutGlobalScopes()
        ->where('company_id', $context->companyId)
        ->where('name', 'Clients')
        ->first();

    $revenueAccount = Account::withoutGlobalScopes()
        ->where('company_id', $context->companyId)
        ->where('name', 'Revenue')
        ->first();

    // Create invoice for penalty
    $invoice = Invoice::create([
        'client_id'    => $context->clientId,
        'agent_id'     => $context->agentId,
        'currency'     => $booking->display_currency,
        'sub_amount'   => $penaltyAmount,
        'amount'       => $penaltyAmount,
        'status'       => InvoiceStatus::UNPAID->value,
        'invoice_date' => now()->toDateString(),
        'due_date'     => now()->toDateString(),
        'label'        => 'Cancellation Penalty: ' . $booking->prebook_key,
    ]);

    InvoiceDetail::create([
        'invoice_id'       => $invoice->id,
        'invoice_number'   => $invoice->invoice_number,
        'task_description' => 'Hotel cancellation penalty — ' . $booking->hotel_name,
        'task_price'       => $penaltyAmount,
        'supplier_price'   => $penaltyAmount,
    ]);

    // Debit receivable (client owes penalty)
    JournalEntry::create([
        'company_id'       => $context->companyId,
        'invoice_id'       => $invoice->id,
        'account_id'       => $receivableAccount->id,
        'transaction_date' => now(),
        'description'      => 'Cancellation penalty: ' . $booking->prebook_key,
        'debit'            => $penaltyAmount,
        'credit'           => 0,
        'currency'         => $booking->display_currency,
        'type'             => 'cancellation',
    ]);

    // Credit revenue
    JournalEntry::create([
        'company_id'       => $context->companyId,
        'invoice_id'       => $invoice->id,
        'account_id'       => $revenueAccount->id,
        'transaction_date' => now(),
        'description'      => 'Cancellation penalty revenue: ' . $booking->prebook_key,
        'debit'            => 0,
        'credit'           => $penaltyAmount,
        'currency'         => $booking->display_currency,
        'type'             => 'cancellation',
    ]);

    // B2B credit refund: refund (original - penalty) back to credit line
    if ($booking->track === DotwAIBooking::TRACK_B2B) {
        $refundAmount = (float) $booking->display_total_fare - $penaltyAmount;
        if ($refundAmount > 0) {
            $this->creditService->refundCredit(
                $context->clientId,
                $context->companyId,
                $refundAmount,
                $booking->prebook_key,
            );
        }
        $invoice->update(['status' => InvoiceStatus::PAID->value]);
    }
}
```

### Pattern 3: Account Lookup via withoutGlobalScopes()

**What:** Account and JournalEntry models have a global scope tied to `auth()->user()->company`. In API/queue context there is no authenticated user — the scope returns nothing. Always use `withoutGlobalScopes()` + explicit `company_id` filter.
**When to use:** Every Account/JournalEntry query in the DotwAI module.

```php
// WRONG — returns null in API context (no auth)
$account = Account::where('name', 'Clients')->first();

// CORRECT — always use this pattern in DotwAI services
$account = Account::withoutGlobalScopes()
    ->where('company_id', $companyId)
    ->where('name', 'Clients')
    ->first();
```

### Pattern 4: DotwAIBooking Status — New Constants Needed

Add two new status constants to `DotwAIBooking`:

```php
public const STATUS_CANCELLATION_PENDING = 'cancellation_pending'; // after step 1 shown
public const STATUS_CANCELLED            = 'cancelled';             // already defined
```

The `cancelled` constant is already defined. Only `cancellation_pending` is new.

### Pattern 5: Statement Query

**What:** Aggregate bookings + journal entries + credits for a company between two dates.
**When to use:** GET /api/dotwai/statement

```php
// StatementService::getStatement()
public function getStatement(int $companyId, string $dateFrom, string $dateTo): array
{
    $bookings = DotwAIBooking::where('company_id', $companyId)
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->orderBy('created_at')
        ->get();

    $journalEntries = JournalEntry::withoutGlobalScopes()
        ->where('company_id', $companyId)
        ->where('type', 'cancellation')
        ->whereBetween('transaction_date', [$dateFrom, $dateTo])
        ->get();

    $credits = Credit::where('company_id', $companyId)
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->get();

    return [
        'bookings'       => $bookings,
        'journal_entries'=> $journalEntries,
        'credits'        => $credits,
        'totals'         => $this->computeTotals($bookings, $journalEntries, $credits),
    ];
}
```

### Anti-Patterns to Avoid
- **Using auth()->user()->company_id inside DotwAI services:** There is no authenticated user in API/queue context. Always use the explicit `companyId` from `DotwAIContext`.
- **Creating JournalEntry before DOTW confirms cancellation:** This violates ACCT-03. Create entries inside the DB::transaction *after* the DOTW API call succeeds.
- **Skipping the invoice for penalty cancellations:** The Invoice is what generates an invoice_number for reconciliation. Always create Invoice + InvoiceDetail before JournalEntry when penalty > 0.
- **Modifying the existing JournalEntry or Invoice models:** Phase constraint — wrap/extend only. Use `JournalEntry::create()` directly; do not add new model methods.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Invoice number generation | Custom sequence logic | `Invoice::create()` — `saving` hook auto-generates `invoice_number` | Invoice::saving() already handles InvoiceSequence; duplicating it breaks reconciliation |
| Credit balance tracking | Custom credit ledger | `Credit::create()` + `CreditService::getBalance()` | Already built in Phase 19 with pessimistic locking |
| WhatsApp message formatting | Raw string concatenation in controller | `MessageBuilderService` static methods | Established pattern — all 8 existing formatters are static; extend not bypass |
| DOTW API cancel call | Any custom HTTP implementation | `DotwService::cancelBooking()` (line 529) | Already built with full error handling and logging |
| Response envelope | Custom JSON response | `DotwAIResponse::success()` / `DotwAIResponse::error()` | All endpoints must return whatsappMessage — use the established envelope |

**Key insight:** The accounting infrastructure (JournalEntry, Invoice, InvoiceDetail, Account, Credit) is mature. The task is to call these correctly, not rebuild them. The only risk is forgetting `withoutGlobalScopes()` on reads.

---

## Common Pitfalls

### Pitfall 1: Auth Global Scope on Account and JournalEntry
**What goes wrong:** `Account::where('name', 'Clients')->first()` returns `null` in API/queue context because the global scope filters by `auth()->user()->company` and there is no authenticated user.
**Why it happens:** Both `Account` and `JournalEntry` have `addGlobalScope('company', ...)` in their `booted()` methods. These scopes silently return empty results when not authenticated.
**How to avoid:** Always use `Account::withoutGlobalScopes()->where('company_id', $companyId)`. Same for any JournalEntry reads.
**Warning signs:** `$account === null` despite the account existing in DB; empty journal entry queries that should return results.

### Pitfall 2: Creating Accounting Entries Before DOTW Confirms
**What goes wrong:** If `DotwService::cancelBooking(confirm=yes)` throws an exception after we've already created Invoice + JournalEntry, the accounting state is corrupted (entries exist but cancellation did not execute).
**Why it happens:** Accounting writes happen outside transaction scope, or the transaction wraps only the Eloquent writes but not the DOTW call.
**How to avoid:** Call DOTW API *first*, then open a `DB::transaction()` for all Eloquent writes. Never put the DOTW API call inside the transaction (external HTTP cannot be rolled back).
**Warning signs:** JournalEntry records with no corresponding DOTW cancellation status; booking stuck in `cancellation_pending`.

### Pitfall 3: Invoice requires client_id and agent_id (not nullable)
**What goes wrong:** Invoice::create() fails with a DB constraint violation because `client_id` and `agent_id` are required foreign keys.
**Why it happens:** The invoices migration (2024_10_29_063642) has `foreignId('client_id')->constrained()` and `foreignId('agent_id')->constrained()` — both NOT NULL.
**How to avoid:** The `DotwAIContext` must carry `clientId` and `agentId`. `CreditService::getClientIdForCompany()` resolves clientId; agentId comes from PhoneResolverService (already on context).
**Warning signs:** SQLSTATE[23000] integrity constraint violation on insert into `invoices`.

### Pitfall 4: InvoiceStatus enum validation on save
**What goes wrong:** Invoice::saving() throws `InvalidArgumentException` if the status value is not in the InvoiceStatus enum.
**Why it happens:** Invoice::boot() validates status on every save against `InvoiceStatus::cases()`.
**How to avoid:** Always use `InvoiceStatus::UNPAID->value` (= 'unpaid') or `InvoiceStatus::PAID->value` (= 'paid') — never raw strings.
**Warning signs:** `InvalidArgumentException: Invalid invoice status: X` during cancellation.

### Pitfall 5: DotwAIBooking missing STATUS_CANCELLATION_PENDING constant
**What goes wrong:** If you use the string `'cancellation_pending'` directly without adding the constant to DotwAIBooking, future code searching for the constant name won't find it, and static analysis will miss typos.
**Why it happens:** Only `confirmed`, `failed`, `cancelled`, `expired` were defined in Phase 19.
**How to avoid:** Add `public const STATUS_CANCELLATION_PENDING = 'cancellation_pending'` to `DotwAIBooking` in the first task of this phase. This is a model change, not a migration (status is a string column).

### Pitfall 6: Cancellation of Non-Confirmed Bookings
**What goes wrong:** Agent sends `cancel_booking` for a booking that is `prebooked` or `pending_payment` (not yet confirmed with DOTW). DOTW's `cancelBooking` will fail because there is no `bookingCode` for DOTW to cancel.
**Why it happens:** `booking_ref` is only set after DOTW confirmation. A prebooked record has no DOTW booking code.
**How to avoid:** CancellationService must validate `$booking->status === STATUS_CONFIRMED || STATUS_CANCELLATION_PENDING` before calling DotwService. Return `CANCELLATION_NOT_ALLOWED` error otherwise.
**Warning signs:** DotwService throws exception about invalid bookingCode; `booking_ref` is null on the DotwAIBooking record.

---

## Code Examples

Verified patterns from codebase inspection:

### JournalEntry::create() with explicit company_id (from CreateClientCredit.php lines 219-232)
```php
JournalEntry::create([
    'transaction_id'   => $transaction->id,
    'company_id'       => $agent->branch->company->id,   // EXPLICIT — never auth()->user()
    'branch_id'        => $agent->branch->id,
    'account_id'       => $bankPaymentFee->id,
    'transaction_date' => Carbon::now(),
    'description'      => 'Description text',
    'debit'            => $amount,
    'credit'           => 0,
    'name'             => $bankPaymentFee->name,
    'type'             => 'bank',
    'voucher_number'   => $creditPayment->voucher_number,
    'type_reference_id'=> $bankPaymentFee->id,
]);
```

### withoutGlobalScopes() pattern (from PaymentBridgeService.php lines 82-90)
```php
$paymentMethod = PaymentMethod::withoutGlobalScopes()
    ->where('company_id', $companyId)
    ->where('gateway', $gateway)
    ->first();
```

### CreditService::refundCredit() (from CreditService.php lines 83-96)
```php
public function refundCredit(
    int $clientId,
    int $companyId,
    float $amount,
    string $prebookKey,
): void {
    Credit::create([
        'company_id'  => $companyId,
        'client_id'   => $clientId,
        'type'        => Credit::REFUND,
        'amount'      => $amount,
        'description' => "DOTW Booking Refund: {$prebookKey}",
    ]);
}
```

### DotwService::cancelBooking() signature (from DotwService.php line 529)
```php
public function cancelBooking(array $params): array
// $params keys: confirm ('no'|'yes'), bookingCode (string), penaltyApplied (float, only for confirm=yes)
// Returns: ['bookingCode' => string, 'refund' => float, 'charge' => float, 'status' => string]
```

### InvoiceStatus enum values (from InvoiceStatus.php)
```php
InvoiceStatus::PAID->value         // 'paid'
InvoiceStatus::UNPAID->value       // 'unpaid'
InvoiceStatus::PARTIAL->value      // 'partial'
InvoiceStatus::PAID_BY_REFUND->value  // 'paid by refund'
InvoiceStatus::REFUNDED->value     // 'refunded'
InvoiceStatus::PARTIAL_REFUND->value  // 'partial refund'
```

### MessageBuilderService static pattern (established — extend this way)
```php
public static function formatCancellationPending(array $data): string
{
    // $data keys: hotel_name, check_in, check_out, penalty_amount, currency, booking_ref
    $lines = [];
    $lines[] = "تأكيد الإلغاء | Cancellation Preview";
    $lines[] = self::SEPARATOR;
    // ... bilingual content
    return implode("\n", $lines);
}

public static function formatCancellationConfirmed(array $data): string
{
    // $data keys: hotel_name, booking_ref, penalty_amount, currency
    // Must include: DOTW portal delay warning (locked decision)
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Auth-scoped accounting queries | `withoutGlobalScopes()` + explicit company_id | Phase 19 (PaymentBridgeService) | Required for all API/queue context writes |
| Manual invoice_number assignment | Auto-generated by Invoice::saving() hook | Pre-existing | Never assign invoice_number manually |
| Direct credit balance queries | CreditService::getBalance() with pessimistic locking | Phase 19 | Always use CreditService for balance operations |

**Deprecated/outdated:**
- Raw auth()->user()->company queries in service layer: superseded by explicit companyId from DotwAIContext

---

## Open Questions

1. **Which Account names to use for cancellation penalty debit/credit**
   - What we know: Account model has `name` and `company_id` fields; examples use 'Clients' for receivable
   - What's unclear: The exact account names seeded per company for the receivable and revenue accounts used in cancellation penalty entries may differ per company setup
   - Recommendation: During Wave 0, inspect actual seeded Account names in the test DB. Use `Account::withoutGlobalScopes()->where('company_id', $companyId)->get()` in tinker to list available accounts. Fall back gracefully (log warning + skip JournalEntry) if no matching account found, rather than throwing.

2. **DotwAIContext carrying agentId for Invoice creation**
   - What we know: Invoice requires non-null `agent_id`; PhoneResolverService resolves agent from phone number
   - What's unclear: Whether `DotwAIContext` DTO currently exposes an `agentId` property or only `companyId`
   - Recommendation: Inspect DotwAIContext DTO in Wave 0; add `agentId` property if not present.

3. **Statement endpoint — pagination vs full range**
   - What we know: Statement returns bookings + credits + journal entries between date_from and date_to
   - What's unclear: Whether large companies with many bookings need pagination
   - Recommendation: For Phase 20, return full range without pagination (matches DOTW portal statement behavior). Add pagination in Phase 22 if needed.

4. **Atomicity when DOTW succeeds but accounting fails**
   - What we know: DOTW API call cannot be inside DB::transaction (external HTTP); accounting writes can be
   - What's unclear: What to do if DOTW cancel succeeds but our DB::transaction rolls back (e.g., DB error)
   - Recommendation: Log the failure with booking prebook_key + DOTW charge response; set booking status to a new `cancellation_accounting_failed` status or leave as `cancelled` with a flag. Admin handles reconciliation manually. This is the same conservative strategy used for `ConfirmBookingAfterPaymentJob::failed()`.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (Laravel 11 built-in) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter CancellationServiceTest` |
| Full suite command | `php artisan test --filter DotwAI` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CANC-01 | confirm=no returns penalty, confirm=yes executes | unit | `php artisan test --filter CancellationServiceTest::test_preview_returns_penalty_amount` | Wave 0 |
| CANC-01 | confirm=yes transitions booking to cancelled | unit | `php artisan test --filter CancellationServiceTest::test_confirm_cancels_booking` | Wave 0 |
| CANC-02 | WhatsApp message includes DOTW delay warning | unit | `php artisan test --filter MessageBuilderServiceTest::test_cancellation_confirmed_includes_dotw_warning` | Wave 0 |
| CANC-03 | Penalty > 0 creates invoice + journal entries | unit | `php artisan test --filter AccountingServiceTest::test_creates_invoice_and_journal_for_penalty` | Wave 0 |
| CANC-04 | Penalty = 0 creates no invoice, no journal entry | unit | `php artisan test --filter AccountingServiceTest::test_free_cancellation_no_accounting` | Wave 0 |
| ACCT-01 | Free cancellation only updates booking status | unit | `php artisan test --filter CancellationServiceTest::test_free_cancellation_only_updates_status` | Wave 0 |
| ACCT-02 | Statement returns correct totals for date range | unit | `php artisan test --filter StatementServiceTest::test_statement_totals` | Wave 0 |
| ACCT-03 | Journal entry not created until DOTW confirms | unit | `php artisan test --filter CancellationServiceTest::test_no_journal_entry_on_preview` | Wave 0 |
| ACCT-04 | JournalEntry uses explicit company_id not auth scope | unit | `php artisan test --filter AccountingServiceTest::test_journal_entry_has_explicit_company_id` | Wave 0 |
| ACCT-05 | Credit history returns transactions for company | unit | `php artisan test --filter CreditServiceTest::test_credit_history_returns_transactions` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter "CancellationServiceTest|AccountingServiceTest"`
- **Per wave merge:** `php artisan test --filter DotwAI`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/DotwAI/CancellationServiceTest.php` — covers CANC-01, CANC-04, ACCT-01, ACCT-03
- [ ] `tests/Unit/DotwAI/AccountingServiceTest.php` — covers CANC-03, ACCT-04
- [ ] `tests/Unit/DotwAI/StatementServiceTest.php` — covers ACCT-02
- [ ] `tests/Unit/DotwAI/CreditServiceTest.php` — covers ACCT-05 (extend existing or create new)

---

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection: `app/Models/JournalEntry.php` — global scope behavior, fillable fields
- Direct codebase inspection: `app/Models/Invoice.php` — client_id/agent_id required, saving hook, InvoiceStatus enum
- Direct codebase inspection: `app/Models/Credit.php` — type constants, create validation
- Direct codebase inspection: `app/Modules/DotwAI/Services/CreditService.php` — refundCredit() and getBalance() signatures
- Direct codebase inspection: `app/Services/DotwService.php` lines 529-565, 1955-1963 — cancelBooking() and parseCancellation()
- Direct codebase inspection: `app/Modules/DotwAI/Models/DotwAIBooking.php` — status constants and fillable fields
- Direct codebase inspection: `app/Console/Commands/CreateClientCredit.php` lines 200-265 — real JournalEntry creation pattern with explicit company_id
- Direct codebase inspection: `app/Modules/DotwAI/Services/PaymentBridgeService.php` lines 82-90 — withoutGlobalScopes() pattern

### Secondary (MEDIUM confidence)
- Direct codebase inspection: `database/migrations/2024_10_29_063642_create_invoices_table.php` — confirmed client_id and agent_id are NOT NULL
- Direct codebase inspection: `app/Enums/InvoiceStatus.php` — confirmed all valid status values

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries exist and are used project-wide; verified by direct file inspection
- Architecture: HIGH — pattern recommendations derived from existing service patterns (BookingService, CreditService, PaymentBridgeService)
- Pitfalls: HIGH — global scope pitfall verified in JournalEntry.php and Account.php booted() methods; invoice constraint verified in migration file
- Open questions: MEDIUM — account name mapping requires runtime inspection (test DB seeding)

**Research date:** 2026-03-24
**Valid until:** 2026-04-24 (stable codebase, 30-day window)
