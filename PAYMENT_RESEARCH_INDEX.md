# Invoice Payment Processing - Research Index

This research documents the complete invoice payment workflow in the Soud Laravel system.

## Documents Created

### 1. PAYMENT_WORKFLOW_RESEARCH.md (719 lines, 23 KB)
**Comprehensive technical documentation of how invoices are paid**

Contents:
- Executive summary of payment workflow
- Invoice status enum and lifecycle
- Detailed model relationships (Invoice, Payment, PaymentApplication, InvoicePartial, Credit)
- Payment processing workflow with flow diagrams
- PaymentApplicationService core logic explanation
- Database table structures
- Payment methods supported
- Integration points for bulk upload
- Accounting system integration
- Validation rules
- Code references and file locations
- Open questions and gaps

**Use this when**: You need to understand the complete payment system architecture, status flows, or how different models relate.

---

### 2. PAYMENT_WORKFLOW_CODE_EXAMPLES.md (626 lines, 16 KB)
**Practical code examples and usage patterns**

Contents:
- 10 practical code examples:
  1. Apply credit payment (full/partial/split)
  2. Validate payment selection
  3. Get payment history
  4. Get available payments for client
  5. Check invoice payment status
  6. Get payment details
  7. Create payment link via gateway
  8. Mark invoice as cash paid
  9. Change payment type
  10. Get invoice partials
- Direct SQL queries
- Flow diagrams in text format
- Route examples with request/response
- Unit test examples
- Key points to remember

**Use this when**: You need code snippets to implement payment features or understand how to call the API.

---

### 3. PAYMENT_GATEWAY_RESEARCH.md (823 lines, 29 KB)
**Research on external payment gateways**

Contents:
- Supported payment gateways:
  - MyFatoorah
  - Tap
  - Hesabe
  - uPayment
  - Knet
- Gateway-specific models and integrations
- Webhook handling patterns
- Payment method configurations
- Gateway-specific features
- Integration notes

**Use this when**: You're working with external payment gateways or need to debug gateway integrations.

---

## Quick Navigation

### By Task

**I want to...**

| Task | Document | Section |
|------|----------|---------|
| Understand invoice payment status | RESEARCH | "Invoice Status Flow" |
| Apply credit to invoice | CODE_EXAMPLES | "1. Apply Credit Payment" |
| Check if invoice is paid | CODE_EXAMPLES | "5. Check Invoice Payment Status" |
| Implement payment in bulk upload | RESEARCH | "Integration Points for Bulk Upload" |
| Debug payment issue | RESEARCH | "Key Models & Relationships" |
| Write unit test for payment | CODE_EXAMPLES | "Testing" |
| Query payment history | CODE_EXAMPLES | "Database Queries" |
| Implement gateway payment | GATEWAY_RESEARCH | "Supported Gateways" |
| Understand accounting entries | RESEARCH | "Accounting Integration" |
| Get available credits for client | CODE_EXAMPLES | "4. Get Available Payments" |

### By Model

| Model | Document | Section |
|-------|----------|---------|
| Invoice | RESEARCH | "1. Invoice Model" |
| Payment | RESEARCH | "2. Payment Model" |
| PaymentApplication | RESEARCH | "3. PaymentApplication Model" |
| InvoicePartial | RESEARCH | "4. InvoicePartial Model" |
| Credit | RESEARCH | "5. Credit Model" |
| PaymentMethod | GATEWAY_RESEARCH | "Payment Method Configuration" |

### By File

| PHP File | Document | Section |
|----------|----------|---------|
| PaymentApplicationService | RESEARCH | "Payment Application Service" & CODE_EXAMPLES | "Examples" |
| InvoiceController | RESEARCH | "Invoice Controller Payment Methods" |
| Invoice Model | RESEARCH | "1. Invoice Model" |
| Payment Model | RESEARCH | "2. Payment Model" |
| PaymentApplication Model | RESEARCH | "3. PaymentApplication Model" |
| Credit Model | RESEARCH | "5. Credit Model" |

---

## Key Findings Summary

### Payment Flow

**4 Main Payment Paths:**

1. **Client Credit** (UNPAID → PAID)
   - Client applies available credit balance
   - Immediate payment via `applyPaymentsToInvoice()`
   - Creates InvoicePartial, Credit, PaymentApplication, and COA entries

2. **External Gateway** (UNPAID → PENDING → PAID)
   - Redirect to gateway (MyFatoorah, Tap, Hesabe, etc.)
   - Webhook callback confirms payment
   - Automatic status update to PAID

3. **Cash** (UNPAID → PAID)
   - Manual entry in system
   - No external gateway interaction
   - Creates records for accounting

4. **Split** (UNPAID → PARTIAL → PAID)
   - Credit covers portion
   - Second gateway covers remainder
   - Invoice stays PARTIAL until both paid

### Core Service

**`PaymentApplicationService::applyPaymentsToInvoice()`**
- Main method for credit-based payments
- Supports 3 modes: full, partial, split
- Automatically creates accounting entries
- Transactional with rollback on error
- Comprehensive logging

### Models & Relationships

- **Invoice**: Main record, has status, payment_type, is_client_credit flags
- **Payment**: Records of received payments from gateways
- **PaymentApplication**: Audit trail linking payments to invoices
- **InvoicePartial**: For split payments (invoice paid via multiple methods)
- **Credit**: Balance tracking (positive=available, negative=used)

### Accounting Integration

- Double-entry bookkeeping automatic
- DEBIT: Liabilities → Advances → Client → Payment Gateway
- CREDIT: Accounts Receivable → Clients
- COA created at invoice generation and payment application

### Integration for Bulk Upload

After creating invoices in bulk:

**Option A: Apply credit immediately**
```php
$service->applyPaymentsToInvoice($invoiceId, $paymentAllocations, 'full');
```

**Option B: Create gateway payment link**
```php
$payment = Payment::create([...]);  // link generated via API
```

**Option C: Mark as cash paid**
```php
$invoice->update(['status' => 'paid', 'payment_type' => 'cash']);
```

---

## File Locations

### Main Service
- `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` (786 lines)

### Models
- `/home/soudshoja/soud-laravel/app/Models/Invoice.php`
- `/home/soudshoja/soud-laravel/app/Models/Payment.php`
- `/home/soudshoja/soud-laravel/app/Models/PaymentApplication.php`
- `/home/soudshoja/soud-laravel/app/Models/Credit.php`
- `/home/soudshoja/soud-laravel/app/Models/InvoicePartial.php`

### Controllers
- `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` (lines 5204-5249)

### Enums
- `/home/soudshoja/soud-laravel/app/Enums/InvoiceStatus.php`

### Gateway Models
- `/home/soudshoja/soud-laravel/app/Models/MyFatoorahPayment.php`
- `/home/soudshoja/soud-laravel/app/Models/TapPayment.php`
- `/home/soudshoja/soud-laravel/app/Models/HesabePayment.php`
- `/home/soudshoja/soud-laravel/app/Models/UpaymentPayment.php`

### Routes
- `routes/web.php`: `POST /apply-payments`

### Migrations (Key)
- `2024_10_29_063642_create_invoices_table.php`
- `2025_03_17_111603_create_invoice_partials_table.php`
- `2025_03_17_122129_create_payments_table.php`
- `2026_01_12_154855_create_payment_applications_table.php`
- `2025_09_06_102445_add_enum_value_for_status_in_invoices_table.php`

---

## Invoice Status Values

**6 Possible Statuses:**
- `paid` - Fully paid
- `unpaid` - Not paid
- `partial` - Partially paid
- `paid by refund` - Paid via refund credit
- `refunded` - Fully refunded
- `partial refund` - Partially refunded

---

## Payment Methods Supported

**External Gateways:**
- MyFatoorah
- Tap
- Hesabe
- uPayment
- Knet

**Non-Gateway Methods:**
- Client Credit (from topup or refund)
- Cash (manual)
- Bank Transfer

---

## Validation Rules

When applying payments:
- Payment amount ≤ available balance
- For 'full' mode: amount ≥ invoice amount
- For 'partial' mode: amount < invoice amount
- For 'split' mode: amount < invoice amount AND other_gateway specified

---

## Next Steps

### To Implement Payment Processing:
1. Use `PaymentApplicationService::applyPaymentsToInvoice()` for credit payments
2. Create `Payment` record for external gateway payments
3. Set up webhook handlers for gateway callbacks
4. Ensure accounting entries are created (automatic via service)

### To Debug Payment Issues:
1. Check invoice status: `$invoice->status`
2. Check payment applications: `$invoice->paymentApplications`
3. Check available credits: `$service->getAvailablePaymentsForClient($clientId)`
4. Review logs in storage/logs/ with `[PAYMENT APPLICATION]` prefix

### For Bulk Upload Integration:
1. After creating invoices, determine payment method
2. If credit: call `applyPaymentsToInvoice()`
3. If gateway: create `Payment` record with link
4. If cash: update invoice status directly
5. All paths automatically create accounting entries

---

## Questions Answered

✅ How are invoices paid?
✅ What statuses can invoices have?
✅ How do credit payments work?
✅ How are external gateway payments handled?
✅ How is accounting integrated?
✅ What's the audit trail mechanism?
✅ How to apply payments in bulk?
✅ What are the validation rules?
✅ How to check payment history?

## Questions Not Fully Answered

❓ Where are gateway webhook handlers located?
❓ How is payment link generation triggered?
❓ When are payment notification emails sent?
❓ How is receipt generation integrated?
❓ What is the Charge model's role in payments?

---

## Related Documentation

- **PROJECT_OVERVIEW.md** - System architecture
- **DOCUMENT_PROCESSING_STRUCTURE.md** - Invoice generation via document processing
- **REPOSITORY_SETUP_COMPLETE.md** - Project setup

---

## Document Metadata

- **Research Date**: February 12, 2026
- **Research Focus**: Invoice payment processing workflow
- **Coverage**: Models, Services, Controllers, Routes, Database, Gateways
- **Total Documents**: 3
- **Total Lines**: 2,168
- **Total Size**: 68 KB
