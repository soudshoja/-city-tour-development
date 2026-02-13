# Invoice Payment Processing Research - Complete Documentation

## Overview

This research project documents the complete invoice payment workflow in the Soud Laravel travel agency management system. The system supports multiple payment methods including client credits, external payment gateways (MyFatoorah, Tap, Hesabe, uPayment, Knet), and manual cash entries.

## Research Scope

**What's Documented:**
- How invoices are paid (4 different payment paths)
- Invoice status transitions and lifecycle
- Core models and relationships
- Payment application service (main logic)
- Accounting integration (COA entries)
- Credit balance system
- Split payment handling
- Integration points for bulk payment processing

**What's NOT Fully Documented:**
- Gateway webhook handler implementations
- Payment link generation triggers
- Payment notification email system
- Receipt generation integration
- Charge model's role in detail

## Documentation Files (77 KB Total)

### 1. [PAYMENT_RESEARCH_INDEX.md](PAYMENT_RESEARCH_INDEX.md) - **START HERE**
**Master navigation guide (9.6 KB)**

Quick links organized by:
- Task (what do you want to do?)
- Model (which model do you need?)
- File (which code file?)

Use this as your entry point to find the right section in other documents.

### 2. [PAYMENT_WORKFLOW_RESEARCH.md](PAYMENT_WORKFLOW_RESEARCH.md) - **Architecture**
**Comprehensive technical reference (23 KB, 719 lines)**

Complete system documentation:
- Executive summary
- Invoice status enum and lifecycle
- 5 key models with relationships and methods
- 4 payment processing paths explained
- PaymentApplicationService detailed breakdown
- Database table structures
- Supported payment methods
- Accounting integration
- Integration points for bulk upload
- Code references and file locations

Read this for deep understanding of how the system works.

### 3. [PAYMENT_WORKFLOW_CODE_EXAMPLES.md](PAYMENT_WORKFLOW_CODE_EXAMPLES.md) - **Implementation**
**Practical code examples (16 KB, 626 lines)**

10 ready-to-use code examples:
1. Apply credit payment (full/partial/split modes)
2. Validate payment selection before applying
3. Get payment history for invoice
4. Get available credits for client
5. Check invoice payment status
6. Get payment details
7. Create payment link via gateway
8. Mark invoice as paid (cash)
9. Change payment type (after paid)
10. Get invoice partials (payment splits)

Plus:
- Direct SQL queries
- Flow diagrams
- Route examples with request/response
- Unit test examples

Use this when implementing payment features.

### 4. [PAYMENT_GATEWAY_RESEARCH.md](PAYMENT_GATEWAY_RESEARCH.md) - **Gateways**
**External gateway integrations (29 KB, 823 lines)**

Detailed information on 5 supported payment gateways:
- **MyFatoorah** - Cards, bank transfer, Apple Pay
- **Tap** - Cards, wallet
- **Hesabe** - Mobile payment platform
- **uPayment** - Digital payment
- **Knet** - Payment gateway

Per gateway:
- Integration method
- Models and relationships
- Webhook handling
- Configuration

Use this when working with external payment gateways.

## Quick Start by Use Case

### I'm implementing invoice payment for bulk upload

1. Read: [PAYMENT_RESEARCH_INDEX.md](PAYMENT_RESEARCH_INDEX.md) → "Integration for Bulk Upload"
2. Copy code from: [PAYMENT_WORKFLOW_CODE_EXAMPLES.md](PAYMENT_WORKFLOW_CODE_EXAMPLES.md) → "1. Apply Credit Payment"
3. Reference: [PAYMENT_WORKFLOW_RESEARCH.md](PAYMENT_WORKFLOW_RESEARCH.md) → "Integration Points for Bulk Upload"

### I need to understand invoice payment status

1. Read: [PAYMENT_WORKFLOW_RESEARCH.md](PAYMENT_WORKFLOW_RESEARCH.md) → "Invoice Status Flow"
2. Check: [PAYMENT_WORKFLOW_CODE_EXAMPLES.md](PAYMENT_WORKFLOW_CODE_EXAMPLES.md) → "5. Check Invoice Payment Status"

### I'm debugging a payment issue

1. Check: [PAYMENT_WORKFLOW_RESEARCH.md](PAYMENT_WORKFLOW_RESEARCH.md) → "Key Models & Relationships"
2. Use queries from: [PAYMENT_WORKFLOW_CODE_EXAMPLES.md](PAYMENT_WORKFLOW_CODE_EXAMPLES.md) → "Database Queries"
3. Review: [PAYMENT_RESEARCH_INDEX.md](PAYMENT_RESEARCH_INDEX.md) → "Questions Not Fully Answered"

### I'm integrating a new payment gateway

1. Read: [PAYMENT_GATEWAY_RESEARCH.md](PAYMENT_GATEWAY_RESEARCH.md) → "Supported Gateways"
2. Study existing: [PAYMENT_GATEWAY_RESEARCH.md](PAYMENT_GATEWAY_RESEARCH.md) → "MyFatoorah Integration"
3. Reference: [PAYMENT_WORKFLOW_RESEARCH.md](PAYMENT_WORKFLOW_RESEARCH.md) → "Database Tables Structure"

### I need to write a test

1. Copy template from: [PAYMENT_WORKFLOW_CODE_EXAMPLES.md](PAYMENT_WORKFLOW_CODE_EXAMPLES.md) → "Testing"
2. Understand flow from: [PAYMENT_WORKFLOW_RESEARCH.md](PAYMENT_WORKFLOW_RESEARCH.md) → "Payment Processing Workflow"

## Key Concepts

### Invoice Status Flow
```
UNPAID
  ├─ → PAID (full payment)
  ├─ → PARTIAL (partial payment)
  ├─ → PAID_BY_REFUND (refund credit)
  ├─ → REFUNDED (full refund)
  └─ → PARTIAL_REFUND (partial refund)
```

### 4 Payment Paths
1. **Client Credit** → Immediate via `applyPaymentsToInvoice()`
2. **External Gateway** → Redirect to gateway + webhook callback
3. **Cash** → Manual entry
4. **Split** → Credit + another gateway

### Core Service
**`PaymentApplicationService::applyPaymentsToInvoice()`**
- Single method for all credit-based payments
- 3 modes: full, partial, split
- Automatic accounting entries
- Transactional with rollback

### Core Models
1. **Invoice** - Main record with status and payment tracking
2. **Payment** - Gateway payment records
3. **PaymentApplication** - Audit trail (new model)
4. **InvoicePartial** - Split payment records
5. **Credit** - Balance tracking

## File Locations in Codebase

### Main Service
```
/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php (786 lines)
```

### Models
```
/home/soudshoja/soud-laravel/app/Models/
├─ Invoice.php
├─ Payment.php
├─ PaymentApplication.php
├─ InvoicePartial.php
└─ Credit.php
```

### Controller
```
/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php (lines 5204-5249)
```

### Route
```
POST /apply-payments → InvoiceController::applyPaymentsToInvoice()
```

### Enums
```
/home/soudshoja/soud-laravel/app/Enums/InvoiceStatus.php
```

## Key Methods Reference

### PaymentApplicationService
- `applyPaymentsToInvoice()` - Main payment method
- `validatePaymentSelection()` - Validate before applying
- `getAvailablePaymentsForClient()` - Get credit balances
- `getPaymentHistoryForInvoice()` - Payment history
- `linkPaymentsToInvoicePartial()` - Link existing partial

### Invoice Model
- `paymentApplications()` - Get related payments
- `getTotalPaidViaApplicationsAttribute()` - Total paid amount
- `getRemainingBalanceAttribute()` - Remaining amount
- `isFullyPaidViaApplications()` - Check if paid

## Data Flow Diagram

```
Invoice Created (UNPAID)
           ↓
    [Choose Payment Method]
           ↓
    ┌──────┼──────┬─────────┐
    ↓      ↓      ↓         ↓
  Credit Gateway Cash     Split
    ↓      ↓      ↓         ↓
  Apply Redirect Manual  Part1+Part2
    ↓      ↓      ↓         ↓
  PAID  [Webhook] PAID    PARTIAL
           ↓                ↓
         PAID            [Wait...]
                            ↓
                          PAID
           ↓
    Create Records:
    ├─ InvoicePartial
    ├─ Credit
    ├─ PaymentApplication
    └─ JournalEntry (COA)
           ↓
    Update Invoice Status
```

## Validation Rules

When applying payments:
- Amount ≤ available balance ✓
- Mode 'full': amount ≥ invoice amount
- Mode 'partial': amount < invoice amount
- Mode 'split': amount < invoice amount + other_gateway specified

## Accounting Integration

Every payment automatically creates:
- **DEBIT**: Liabilities → Advances → Client → Payment Gateway
- **CREDIT**: Accounts Receivable → Clients

This ensures double-entry bookkeeping for financial reporting.

## Usage Examples

### Apply Full Credit Payment
```php
$service = new PaymentApplicationService();
$result = $service->applyPaymentsToInvoice(
    $invoice->id,
    [['credit_id' => 5, 'amount' => 100]],
    'full'
);
```

### Apply Partial Credit Payment
```php
$result = $service->applyPaymentsToInvoice(
    $invoice->id,
    [['credit_id' => 5, 'amount' => 50]],  // Partial amount
    'partial'
);
```

### Get Available Credits for Client
```php
$available = $service->getAvailablePaymentsForClient($clientId);
foreach ($available as $payment) {
    echo "{$payment['voucher_number']}: {$payment['available_balance']}";
}
```

## Important Notes

1. **Credit amounts are deltas**
   - Positive = available balance
   - Negative = deducted for usage

2. **One credit can pay multiple invoices**
   - Balance tracked via SUM queries
   - Partial usage fully supported

3. **All operations are logged**
   - Prefix: `[PAYMENT APPLICATION]`
   - User ID tracked for audit trail

4. **Transactions are atomic**
   - All-or-nothing approach
   - Automatic rollback on error

5. **Accounting is automatic**
   - No manual COA entry needed
   - Created by service automatically

## Related Documentation

- [PROJECT_OVERVIEW.md](../PROJECT_OVERVIEW.md) - System architecture
- [DOCUMENT_PROCESSING_STRUCTURE.md](../DOCUMENT_PROCESSING_STRUCTURE.md) - Invoice generation
- [REPOSITORY_SETUP_COMPLETE.md](../REPOSITORY_SETUP_COMPLETE.md) - Project setup

## Questions & Answers

**Q: How do I apply a credit payment to an invoice?**
A: Use `PaymentApplicationService::applyPaymentsToInvoice()` with payment allocations.

**Q: Can I pay with multiple credits at once?**
A: Yes, pass multiple items in the `paymentAllocations` array.

**Q: What happens when a gateway payment is received?**
A: Webhook handler updates Payment status and automatically creates accounting entries.

**Q: How is the credit balance calculated?**
A: Via SUM on Credit table; positive amounts are available, negative are used.

**Q: Can I change how an invoice was paid after it's marked paid?**
A: Yes, use `handlePaymentTypeChange()` (only for paid invoices, limited gateways).

**Q: Where are accounting entries created?**
A: Automatically in `createCreditPaymentCOA()` when payment is applied.

**Q: What payment statuses exist?**
A: 6 invoice statuses: paid, unpaid, partial, paid by refund, refunded, partial refund.

## Document Statistics

| Document | Lines | Size | Coverage |
|----------|-------|------|----------|
| PAYMENT_RESEARCH_INDEX.md | 358 | 9.6 KB | Navigation & quick reference |
| PAYMENT_WORKFLOW_RESEARCH.md | 719 | 23 KB | Architecture & deep reference |
| PAYMENT_WORKFLOW_CODE_EXAMPLES.md | 626 | 16 KB | Code examples & queries |
| PAYMENT_GATEWAY_RESEARCH.md | 823 | 29 KB | Gateway integrations |
| **Total** | **2,526** | **77 KB** | Complete payment system |

## Next Steps

1. **For Implementation**: Start with [PAYMENT_WORKFLOW_CODE_EXAMPLES.md](PAYMENT_WORKFLOW_CODE_EXAMPLES.md)
2. **For Architecture**: Read [PAYMENT_WORKFLOW_RESEARCH.md](PAYMENT_WORKFLOW_RESEARCH.md)
3. **For Navigation**: Use [PAYMENT_RESEARCH_INDEX.md](PAYMENT_RESEARCH_INDEX.md)
4. **For Gateways**: Check [PAYMENT_GATEWAY_RESEARCH.md](PAYMENT_GATEWAY_RESEARCH.md)

## Support

For questions about:
- **Bulk payment processing** → See "Integration Points for Bulk Upload"
- **Credit system** → See "5. Credit Model"
- **Status transitions** → See "Invoice Status Flow"
- **Accounting** → See "Accounting Integration"
- **Code examples** → See PAYMENT_WORKFLOW_CODE_EXAMPLES.md
- **Navigation** → Start with PAYMENT_RESEARCH_INDEX.md

---

**Research Completed**: February 12, 2026
**Coverage**: Invoice Payment Processing Workflow
**Status**: Complete and Ready for Implementation
