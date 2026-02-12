# Soud Laravel - Travel Agency Management Platform

## What This Is

A multi-tenant Laravel 11 platform for travel agencies to manage bookings, invoices, payments, and accounting. Features AI-powered document processing for tickets/hotels/visas, payment gateway integration (MyFatoorah, Knet, Tap, Hesabe, uPayment), and double-entry bookkeeping with chart of accounts.

## Core Value

**Agents can invoice clients accurately from any source (AIR files, PDFs, Excel uploads) with automated payment tracking and accounting integration.**

## Requirements

### Validated

<!-- Shipped and confirmed valuable from existing system -->

- ✓ Multi-tenant architecture (company → branch → agent hierarchy) — existing
- ✓ Task management for 12 service types (flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry) — existing
- ✓ Client, agent, supplier management with relationships — existing
- ✓ Invoice creation from tasks (manual selection via UI) — existing
- ✓ Invoice number auto-generation (INV-YYYY-XXXXX format, per-company sequence) — existing
- ✓ Payment gateway integration (MyFatoorah, Tap, Hesabe, uPayment, Knet) — existing
- ✓ Partial payment tracking (invoice_partials, payment_applications, credits) — existing
- ✓ Double-entry bookkeeping (accounts, journal entries, general ledger) — existing
- ✓ AI document processing (AIR files, PDFs, passport images via OpenAI/OpenWebUI) — existing
- ✓ Email attachment processing and task creation — existing
- ✓ Excel import for clients, agents, companies, tasks (Maatwebsite/Laravel-Excel) — existing
- ✓ WhatsApp Business API integration for client communication — existing
- ✓ Travel API integration (TBO Holidays, Magic Holiday) — existing
- ✓ GraphQL API via Lighthouse — existing

### Active

<!-- Current scope. Building toward these. -->

- [ ] **Bulk invoice upload from Excel** — Agents upload Excel file with tasks, system creates invoices with validation
- [ ] **Excel row validation** — Check required fields, validate suppliers exist, validate enum values (task type, status)
- [ ] **Client matching by mobile** — Find client by `(company_id, phone)`, flag unknown clients for manual review
- [ ] **Group tasks into invoices by client** — All tasks for same client mobile → one invoice per client
- [ ] **Preview before commit** — Show summary of invoices to be created, allow agent to approve/reject
- [ ] **Invoice PDF generation** — Auto-generate printable PDF invoices after upload
- [ ] **Email invoice to accountant + agent** — Send each created invoice to company accountant and the agent who uploaded
- [ ] **Upload history tracking** — Track which Excel file created which invoices, audit trail
- [ ] **Error reporting** — Show clear error messages for validation failures (missing supplier, invalid enum, empty fields)
- [ ] **Manual review queue** — Flagged rows (unknown clients) go to review queue for agent to fix

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Auto-create clients from Excel — Requires manual review to ensure data quality (mobile + name not enough)
- Modify existing invoices via Excel — Too risky, only new invoice creation supported
- Multi-currency in Excel upload — Use company default currency, manual adjustment if needed
- Real-time Excel editing in browser — Upload file, preview, approve/reject (no spreadsheet UI)
- WhatsApp notifications on upload — Manual follow-up preferred, avoid spam to clients

## Context

**Discovery Findings:**
- Database has 45+ tables with comprehensive invoice/task/payment tracking
- Existing Excel import pattern (TasksImport, ClientsImport) using Maatwebsite/Laravel-Excel
- Invoice creation logic in `InvoiceController::store()` groups tasks, creates invoice_details, generates invoice_number
- Client phone field is NOT unique alone (only `company_id + civil_no` unique constraint)
- Invoices CAN contain tasks from multiple suppliers and mixed task types
- Payment gateway stored in `invoice_partials.payment_gateway` enum
- Double-entry accounting auto-creates journal entries on invoice creation

**Codebase Architecture:**
- Multi-tenant with company_id isolation throughout
- Service layer for complex operations (AirFileParser, PaymentApplicationService)
- AI integration layer (OpenAI, OpenWebUI) for document extraction
- 115 Eloquent models with relationships
- GraphQL API via Lighthouse
- Livewire 3.5 + Alpine.js frontend

## Constraints

- **Tech Stack**: Laravel 11, PHP 8.2+, MySQL — Existing platform, must integrate seamlessly
- **Excel Format**: Must use Maatwebsite/Laravel-Excel library — Existing pattern in codebase
- **Client Matching**: Cannot auto-create clients — Business rule to maintain data quality
- **Multi-Tenant**: company_id must isolate data — Security requirement for multi-company platform
- **Invoice Numbering**: Must use existing invoice_sequence table — Prevents duplicate invoice numbers
- **Accounting**: Must create journal entries on invoice creation — Existing accounting system requirement
- **Email**: Use existing email service (Resend, AWS SES, Postmark) — Already configured

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| One invoice per client (not per row) | Matches existing manual invoice creation pattern, reduces invoice clutter | — Pending |
| Flag unknown clients instead of auto-create | Prevents duplicate/incorrect client creation, matches existing validation approach | — Pending |
| Full validation before preview | Fail fast with clear errors, better UX than partial imports | — Pending |
| Email to accountant + agent (not WhatsApp) | Professional invoice delivery, avoid client notification spam until invoice is reviewed | — Pending |
| Leverage existing InvoiceController logic | Reuse proven invoice creation, maintain consistency with manual invoices | — Pending |

---
*Last updated: 2026-02-12 after initialization*
