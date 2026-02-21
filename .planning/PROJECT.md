# Soud Laravel - Travel Agency Management Platform

## What This Is

A multi-tenant Laravel 11 platform for travel agencies to manage bookings, invoices, payments, and accounting. Features AI-powered document processing for tickets/hotels/visas, payment gateway integration (MyFatoorah, Knet, Tap, Hesabe, uPayment), and double-entry bookkeeping with chart of accounts.

## Core Value

**Agents can invoice clients accurately from any source (AIR files, PDFs, Excel uploads) with automated payment tracking and accounting integration.**

## Current Milestone: DOTW v1.0 B2B

**Goal:** Enable agents to search, browse, and book hotels from multiple suppliers using real-time DOTW API rates, with per-company credential management and GraphQL API for B2B integrations.

**Target Features:**
- Hotel search by destination/dates/rooms with live DOTW rates
- Rate browsing with cancellation policies and room details
- Rate blocking with 3-minute allocation tracking
- Pre-booking creation and confirmation workflow
- Per-company DOTW credential storage and switching
- GraphQL API for search, rates, booking operations
- Integration with existing task/invoice system (future phase)

## Completed Milestones

**v1.0 Bulk Invoice Upload** — ✅ SHIPPED 2026-02-13

Delivered complete bulk invoice creation system from Excel uploads:
- Excel template download with pre-filled client list
- Row-level validation (tasks, clients, suppliers, data types)
- Preview workflow with grouped invoice cards
- Approval/rejection with Alpine.js modals
- Background invoice creation in atomic transaction
- PDF generation and email delivery to accountant + agent
- Error reporting with downloadable Excel reports
- Full upload history and audit trail

**Tech:** 10 plans executed, 4 phases, ~25 tasks. See `.planning/milestones/v1.0-ROADMAP.md` for details.

## Requirements

### Validated

<!-- Shipped and confirmed valuable -->

**Existing Platform (pre-v1.0):**
- ✓ Multi-tenant architecture (company → branch → agent hierarchy)
- ✓ Task management for 12 service types (flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry)
- ✓ Client, agent, supplier management with relationships
- ✓ Invoice creation from tasks (manual selection via UI)
- ✓ Invoice number auto-generation (INV-YYYY-XXXXX format, per-company sequence)
- ✓ Payment gateway integration (MyFatoorah, Tap, Hesabe, uPayment, Knet)
- ✓ Partial payment tracking (invoice_partials, payment_applications, credits)
- ✓ Double-entry bookkeeping (accounts, journal entries, general ledger)
- ✓ AI document processing (AIR files, PDFs, passport images via OpenAI/OpenWebUI)
- ✓ Email attachment processing and task creation
- ✓ Excel import for clients, agents, companies, tasks (Maatwebsite/Laravel-Excel)
- ✓ WhatsApp Business API integration for client communication
- ✓ Travel API integration (TBO Holidays, Magic Holiday)
- ✓ GraphQL API via Lighthouse

**v1.0 Bulk Invoice Upload (2026-02-13):**
- ✓ Bulk invoice upload from Excel — Agents upload Excel file with tasks, system creates invoices with validation
- ✓ Excel row validation — Required fields, supplier existence, enum values (task type, status)
- ✓ Client matching by mobile — Find client by `(company_id, phone)`, flag unknown clients for manual review
- ✓ Group tasks into invoices by client — All tasks for same client + date → one invoice
- ✓ Preview before commit — Summary of invoices to be created, approve/reject workflow
- ✓ Invoice PDF generation — Auto-generate printable PDF invoices after upload
- ✓ Email invoice to accountant + agent — Send created invoices to company accountant and uploading agent
- ✓ Upload history tracking — Excel file creates which invoices, full audit trail
- ✓ Error reporting — Clear error messages with row numbers and field names, downloadable Excel reports
- ✓ Manual review queue — Flagged rows (unknown clients) marked for agent review

### Active

<!-- DOTW v1.0 B2B Milestone -->

**DOTW v1.0 B2B Features (in planning):**
- Per-company DOTW credential management (username, password, company_code)
- Hotel search with live rates and DOTW filters (destination, rating, price, property type, amenities)
- Rate browsing (room types, meal plans, cancellation policies, refundability)
- Rate blocking (3-minute allocation expiry, dotw_prebooks tracking)
- Pre-booking workflow (passenger details, confirmation)
- Comprehensive GraphQL API (searchHotels, getRoomRates, blockRates, createPreBooking)
  - Full DOTW filter support for flexible B2B integrations
  - All operations require company authentication context
- B2C markup application (20% default, configurable per company)

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
| One invoice per client + date grouping | Matches existing manual invoice creation pattern, reduces invoice clutter | ✓ Good (v1.0) |
| Flag unknown clients instead of auto-create | Prevents duplicate/incorrect client creation, maintains data quality | ✓ Good (v1.0) |
| Full validation before preview | Fail fast with clear errors, better UX than partial imports | ✓ Good (v1.0) |
| Email to accountant + agent (not WhatsApp) | Professional invoice delivery, avoid client notification spam | ✓ Good (v1.0) |
| Separate queue job for email delivery | Prevents PDF generation from holding database locks during invoice creation | ✓ Good (v1.0) |
| afterCommit() on job dispatch | Ensures database status committed before background jobs start | ✓ Good (v1.0) |
| In-memory PDF generation | Uses Laravel 11 Attachment::fromData(), no temp file cleanup needed | ✓ Good (v1.0) |

---
*Last updated: 2026-02-21 after starting DOTW v1.0 B2B milestone*
