# DOTW AI Module - Hotel Booking Platform

## What This Is

A self-contained Laravel module (app/Modules/DotwAI/) that powers hotel bookings through WhatsApp via n8n AI agents. Connects travel agencies and their customers to DOTW's 25,000+ hotel inventory with independent B2B and B2C tracks, full payment collection, accounting integration, and automated booking lifecycle management.

## Core Value

Enable travel agents to search, book, cancel, and manage DOTW hotel reservations entirely through WhatsApp — with automated accounting, payment collection, cancellation reminders, and voucher delivery.

## Current Milestone: v2.0 DOTW AI Module

**Goal:** Build the production application layer that n8n AI agents call to handle all hotel booking scenarios via WhatsApp with independent B2B and B2C tracks.

**Target features:**
- B2B track (WhatsApp-only, credit line or gateway payment, 0% markup)
- B2C track (payment upfront via MyFatoorah/KNET, configurable markup)
- Automated booking lifecycle (reminders, auto-invoice, voucher delivery)
- Hotel static data cache (DOTW → MapHotel sync)
- Smart search with diverse question handling
- Full accounting integration (hybrid: CRM for all, journal for money movement)

## Requirements

### Validated (from previous milestones)
- DOTW V4 XML API fully integrated (DotwService.php — all methods)
- DOTW certification: 17/19 PASS (awaiting hotel IDs for tests 16+17)
- 5 payment gateways operational (MyFatoorah, KNET, Hesabe, uPayment, Tap)
- Double-entry bookkeeping system built (JournalEntry, Account, Invoice, Refund)
- Company credit tracking (Credit model with types)
- MapHotel database (map_data_citytour) with hotels, cities, countries
- Resayil WhatsApp API integrated
- Company/Branch/Agent hierarchy with isolated data

### Active
(Defined in REQUIREMENTS.md for this milestone)

### Out of Scope
- Livewire UI for hotel booking (B2B is WhatsApp-only)
- Multi-supplier aggregation (DOTW only for this milestone)
- Flight/visa/insurance booking through this module
- Mobile app

## Context

**Project:** Soud Laravel - Travel agency management platform
**Tech Stack:** Laravel 11, PHP 8.2+, Livewire 3.5, GraphQL (Lighthouse), n8n workflows
**API:** DOTW V4 XML API (xmldev.dotwconnect.com / xml.dotwconnect.com)
**Distribution:** WhatsApp (Resayil) → n8n AI Agent → GraphQL/REST → DotwAI Module → DOTW API
**Skills:** /dotwai (application architecture) + /dotw-api (XML API + certification)

## Constraints

- **Module isolation:** app/Modules/DotwAI/ — own namespace, does not modify existing code
- **n8n is the consumer:** Module exposes simple tools, n8n handles conversation logic
- **WhatsApp-first:** B2B agents never leave WhatsApp for the booking flow
- **Existing infra:** Must use existing payment gateways, accounting, company hierarchy — not rebuild
- **DOTW rate expiry:** 3-minute allocation window from blocking step

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| B2B + B2C independent tracks | Different audiences, different flows, enable/disable per company | Locked |
| B2B entirely via WhatsApp | Agents don't need web portal for booking | Locked |
| B2B credit line: book now, pay later | Standard travel agency practice | Locked |
| B2B no credit: gateway payment first | Fallback for agents without credit | Locked |
| Hybrid accounting | CRM tracks all cancellations, journal only for money movement | Locked |
| Auto-reminders 3/2/1 days before deadline | Prevent accidental charges from missed cancellations | Locked |
| Auto-invoice after deadline passes | Non-cancelled refundable bookings become billable | Locked |
| Cancellation warning about DOTW confirmation | Our cancellation ≠ guaranteed DOTW cancellation | Locked |
| Hotel static data cache | Avoid excessive API calls, search by hotelId in batches of 50 | Locked |
| Direct MyFatoorah ExecutePayment API | Full control over CallBackUrl without modifying existing code | Locked |
| Text-based WhatsApp vouchers | Maximum reliability vs PDF attachments | Locked |
| CreditService pessimistic locking | lockForUpdate prevents double-spend on concurrent bookings | Locked |
| Prebook always re-blocks | Search cache has summaries only; prebook re-calls getRooms(blocking=true) | Locked |
| Module isolation in app/Modules/DotwAI/ | Don't touch existing DOTW resolvers or services | Locked |
| Local PDF vouchers via DomPDF | DOTW API has no voucher/PDF endpoint; generate locally with B2B agent details | Locked |
| Webhook events fire-and-forget | WebhookDispatchJob with retry backoff; don't block on n8n response | Locked |
| APR auto-invoice on confirmation | No reminder cycle for non-refundable bookings | Locked |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-03-25 after Phase 21*
