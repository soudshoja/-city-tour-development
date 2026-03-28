# DOTW B2B/B2C Connection Guide

**Platform:** City Commerce Group — Travel Agency Management Platform
**Date:** 2026-03-28
**Prepared for:** Olga Chicu — DOTW Integration Consultant
**Subject:** How Other Agencies Connect Through Our Development

---

## 1. System Overview

City Commerce Group operates a multi-tenant travel agency management platform that integrates DOTW hotel inventory as a core booking module. The platform enables travel agencies and their customers to search, prebook, confirm, and cancel DOTW hotel reservations entirely through WhatsApp — powered by an AI-driven conversation flow.

**Key architecture points:**

- All DOTW API calls originate from the platform's backend (Laravel 11). There is no direct client-to-DOTW communication.
- Each agency (company) stores its own DOTW credentials, isolated from other agencies.
- The platform supports both B2B (agency agent) and B2C (end customer) booking tracks.
- The WhatsApp interface is the primary user-facing channel — no custom web portal is required for agents to make bookings.

**Live URL:** https://development.citycommerce.group

---

## 2. Multi-Tenant Architecture

The platform implements a three-level company hierarchy that ensures complete data isolation between agencies:

```
City Commerce Group Platform (Admin)
        │
        ├── Company (Travel Agency A)
        │   ├── DOTW Credentials (username, password, company ID)
        │   ├── Branch (Dubai Office)
        │   │   ├── Agent (user 1)
        │   │   └── Agent (user 2)
        │   └── Branch (Abu Dhabi Office)
        │       └── Agent (user 3)
        │
        └── Company (Travel Agency B)
            ├── DOTW Credentials (username, password, company ID)  ← separate set
            └── Branch (Head Office)
                └── Agent (user 4)
```

**Isolation guarantees:**

| Scope | Isolation |
|-------|-----------|
| DOTW credentials | Each company has its own username/password/company ID stored encrypted in the database |
| Bookings | Agents only see bookings created under their company |
| Transactions | Accounting ledger is fully separated per company |
| WhatsApp sessions | Each agent's session is scoped to their company's DOTW credentials |
| Pricing/markup | Custom markup percentages and payment gateway settings per company |

**Onboarding process:**

1. Agency contacts City Commerce Group for onboarding.
2. Platform admin creates a Company record for the agency.
3. Admin enters the agency's DOTW credentials (username, password, company ID) in the company settings.
4. Admin links the agency's WhatsApp number(s) to the company profile.
5. Agents and customers from that agency gain immediate access to hotel booking via WhatsApp.
6. White-label responses: message branding can be configured per company.

---

## 3. Client Interface: WhatsApp

The primary user interface is WhatsApp. Agents and customers do not need to log into any web portal to make hotel bookings.

**Technology stack:**

```
WhatsApp (Resayil) → n8n AI Agent → REST API → Laravel Backend → DOTW XML API
```

| Component | Role |
|-----------|------|
| **WhatsApp / Resayil** | Message transport — receives user messages, delivers system responses |
| **n8n AI Agent** | Conversation logic — understands natural language, maintains session state, calls REST endpoints |
| **REST API** (`/api/dotwai/`) | Laravel endpoints that execute hotel search, prebook, confirm, and cancel operations |
| **Laravel Backend** | Business logic, DOTW API calls, accounting, payment processing |
| **DOTW XML API** | Hotel inventory, rates, and booking confirmation |

**Session management:**

- Each WhatsApp phone number has an isolated booking session.
- Session state (search results, selected hotel, prebook data) cached for 60 minutes.
- Sessions are scoped per company — same number cannot accidentally book under a different agency.

---

## 4. B2B Flow (Agency Agents)

**Who:** Authenticated travel agency agents using WhatsApp.

**Flow:**

```
1. Agent sends: "Find hotels in Dubai, 2 adults, 15-18 March"
        │
        ▼
2. AI Agent calls: POST /api/dotwai/agent-b2b/search
   → Platform calls DOTW searchhotels
   → Results returned to agent via WhatsApp (numbered list of hotels)
        │
        ▼
3. Agent replies: "Option 3" or "Book the Marriott"
   → AI Agent calls: POST /api/dotwai/agent-b2b/prebook
   → Platform calls DOTW getRooms (blocking=true) — rate locked for 3 minutes
   → Agent sees: room details, cancellation policy, tariff notes, special requests, MSP, fees
        │
        ▼
4. Agent provides passenger details via WhatsApp (names, nationality, salutation)
        │
        ▼
5. Platform completes booking:
        │
        ├── Mode A: Credit Line
        │   → Agent's company has pre-approved credit with the platform
        │   → POST /api/dotwai/agent-b2b/confirm
        │   → Platform calls DOTW confirmBooking
        │   → Booking confirmed immediately, credit balance deducted
        │   → Voucher delivered via WhatsApp
        │
        └── Mode B: Payment Gateway
            → No credit line or insufficient balance
            → Payment link sent to agent via WhatsApp
            → Agent pays online (MyFatoorah / KNET / Hesabe / uPayment / Tap)
            → After payment: platform calls DOTW confirmBooking
            → Voucher delivered via WhatsApp
```

**Displayed before booking (mandatory features):**

- Cancellation policy (rules with dates and penalty amounts)
- Tariff notes
- Minimum stay requirement
- Minimum Selling Price (MSP)
- Special promotions
- Property fees (payable at property vs. included)
- Special requests (optional selection)
- Restricted cancellation warning (if applicable)

---

## 5. B2C Flow (Direct Customers)

**Who:** End customers of a travel agency, interacting directly via WhatsApp.

**Flow:**

```
1. Customer sends: "I want to book a hotel in London for 2 nights"
        │
        ▼
2. AI Agent calls: POST /api/dotwai/agent-b2c/search
   → Same hotel search as B2B, but prices include agency markup
   → Default markup: configurable per company (e.g. 20%)
   → MSP enforcement: selling price cannot go below DOTW minimum
        │
        ▼
3. Customer selects hotel and room
   → Prebook step locks the rate
   → Customer sees: total price (with markup), cancellation terms, fees
        │
        ▼
4. Customer provides personal details (name, nationality)
        │
        ▼
5. Payment link sent via WhatsApp
   → Customer pays online
   → After payment confirmed:
        a. Platform re-blocks rate with DOTW (getRooms blocking)
        b. Platform calls DOTW confirmBooking
        c. Booking stored with markup revenue tracked
        d. Voucher delivered via WhatsApp
        e. Company receives revenue (markup applied)
```

**B2C vs B2B differences:**

| Feature | B2B | B2C |
|---------|-----|-----|
| Payment | Credit line or gateway | Payment upfront (always gateway) |
| Pricing | Net DOTW rate (0% markup) | Net rate + agency markup |
| MSP enforcement | Yes | Yes (higher of markup vs MSP) |
| Voucher delivery | WhatsApp | WhatsApp |
| User type | Agency agent | End customer |

---

## 6. How Other Agencies Connect

New agencies are onboarded through the platform admin panel — no custom development is required for each agency.

**Step-by-step onboarding:**

1. **Agreement:** Agency agrees to use City Commerce Group's platform for hotel bookings.
2. **DOTW credentials:** Agency provides their DOTW account credentials (or a sub-account is created under the City Commerce Group master account).
3. **Platform onboarding:** Platform admin creates the Company record and enters DOTW credentials.
4. **WhatsApp setup:** Agency's WhatsApp Business number is registered with Resayil and linked to the company profile.
5. **Configuration:** Admin sets agency-specific settings:
   - Markup percentage (B2C pricing)
   - Payment gateway (which gateway to use for their customers)
   - Enabled booking tracks (B2B only, B2C only, or both)
   - White-label branding (custom response messages)
6. **Agents activated:** Agency staff are added as agents under their company's branches.
7. **Ready:** The agency's agents can immediately begin booking via WhatsApp.

**Summary:** Each agency has a completely isolated environment on the shared platform. They use their own DOTW credentials, see only their own bookings, and have their own configuration.

---

## 7. Technical Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    End Users (WhatsApp)                          │
│                                                                  │
│   Agent (B2B)                    Customer (B2C)                 │
│   "Find hotels Dubai..."          "Book London hotel..."        │
└──────────────────┬───────────────────────┬──────────────────────┘
                   │                       │
                   ▼                       ▼
        ┌──────────────────────────────────────────┐
        │           Resayil (WhatsApp API)          │
        │     Message routing + delivery            │
        └──────────────────────┬───────────────────┘
                               │
                               ▼
        ┌──────────────────────────────────────────┐
        │           n8n AI Agent Workflow           │
        │   Intent detection, session management,   │
        │   conversation flow, response formatting  │
        └──────────────────────┬───────────────────┘
                               │ HTTP POST
                               ▼
        ┌──────────────────────────────────────────┐
        │     City Commerce Platform REST API       │
        │                                          │
        │  POST /api/dotwai/agent-b2b/search       │
        │  POST /api/dotwai/agent-b2b/prebook      │
        │  POST /api/dotwai/agent-b2b/confirm      │
        │  POST /api/dotwai/agent-b2b/cancel       │
        │  POST /api/dotwai/agent-b2c/search       │
        │  POST /api/dotwai/agent-b2c/prebook      │
        │  POST /api/dotwai/agent-b2c/confirm      │
        │  POST /api/dotwai/agent-b2c/cancel       │
        └──────────────────────┬───────────────────┘
                               │ PHP
                               ▼
        ┌──────────────────────────────────────────┐
        │       Laravel 11 Backend (DotwAI)         │
        │                                          │
        │  - Per-company credential resolution      │
        │  - Booking lifecycle management           │
        │  - Payment processing (5 gateways)        │
        │  - Accounting (double-entry ledger)        │
        │  - Session state management               │
        └──────────────────────┬───────────────────┘
                               │ XML over HTTPS
                               ▼
        ┌──────────────────────────────────────────┐
        │           DOTW XML API V4                 │
        │   (xmldev.dotwconnect.com / sandbox)      │
        │   (xml.dotwconnect.com / production)      │
        └──────────────────────────────────────────┘
```

**API authentication:** Each REST API call is authenticated via Bearer token scoped to the agent's WhatsApp phone number and company.

---

## 8. Data Flow Summary

| Operation | Platform call | DOTW call |
|-----------|--------------|-----------|
| Search hotels | `POST /agent-b2b/search` | `searchhotels` |
| View room details | `POST /agent-b2b/prebook` (step 1) | `getRooms` (browse, blocking=false) |
| Lock rate | `POST /agent-b2b/prebook` (step 2) | `getRooms` (blocking=true, roomTypeSelected) |
| Confirm booking | `POST /agent-b2b/confirm` | `confirmBooking` |
| Cancel (check penalty) | `POST /agent-b2b/cancel` (step 1) | `cancelBooking` (confirm=no) |
| Cancel (execute) | `POST /agent-b2b/cancel` (step 2) | `cancelBooking` (confirm=yes) |

---

## 9. Security

| Concern | Implementation |
|---------|---------------|
| DOTW credential storage | AES-256 encrypted at rest in `company_settings` table |
| API authentication | Bearer token per agent/device, scoped to company |
| Data isolation | All queries filtered by `company_id` at the ORM level |
| Rate limiting | Laravel rate limiting on all `/api/dotwai/` endpoints |
| Audit trail | Every DOTW request/response logged with timestamp, agent ID, company ID |
| Payment security | PCI-compliant payment gateways; no card data stored on platform |

---

## 10. Contact for Onboarding

To onboard a new agency, contact:

**City Commerce Group Development Team**
Live platform: https://development.citycommerce.group
Email: [development team contact]

---

*Prepared by the City Commerce Group development team*
*Date: 2026-03-28*
*For DOTW certification review — in response to Olga Chicu's March 27 inquiry*
