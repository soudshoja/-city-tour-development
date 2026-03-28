# DOTW Certification Evidence Guide

**Platform:** City Commerce Group — Travel Agency Management Platform
**Date:** 2026-03-28
**Prepared for:** Olga Chicu — DOTW Integration Consultant
**Subject:** How to Verify Our Implementation — Evidence Options

---

## 1. Purpose

This document outlines the two ways Olga / the DOTW certification team can verify our implementation:

- **Option A:** Olga tests directly via WhatsApp as a test user.
- **Option B:** We provide a package of WhatsApp screenshots + DOTW XML logs.

Both options cover all 9 issues raised in Olga's March 27 feedback. We will provide whichever format Olga prefers, or both if needed.

---

## 2. Option A: Direct WhatsApp Testing

Olga can be set up as a test agent on the platform and run the full booking flow herself via WhatsApp.

### Setup Steps

1. Create a test company with DOTW sandbox credentials.
2. Register Olga's WhatsApp number as an agent under that company.
3. Provide Olga with the test hotel IDs and dates to use.
4. Olga messages the WhatsApp number and runs through the booking flow.

### What Olga Can Test Directly

| Test | Flow |
|------|------|
| Hotel search | "Find hotels in Dubai, 2 adults, 15-18 March" |
| Room details with mandatory features | Select a hotel from results |
| Pre-booking with rate lock | Confirm room selection |
| B2B booking (credit) | Confirm booking using test credit balance |
| B2C booking (payment) | Receive payment link, test checkout |
| Cancellation step 1 | Request cancellation — see penalty |
| Cancellation step 2 | Confirm cancellation |
| 2-room booking | "Book 2 rooms at [hotel], 2 adults each" |
| 2-room cancellation | Cancel the 2-room booking |
| Special requests | Select non-smoking room / baby cot etc. |
| Salutation codes | Booking with Mr/Mrs/Miss/Dr title |
| Nationality selection | Provide non-Kuwait nationality |

### Contact to Arrange

To set up Olga's test access, contact the development team at https://development.citycommerce.group.

---

## 3. Option B: Screenshot + XML Log Evidence Package

For each certification test, we will provide:

1. **WhatsApp conversation screenshot** — showing the user interaction and the system's response
2. **DOTW request XML (RQ)** — from `storage/logs/dotw-certification/`
3. **DOTW response XML (RS)** — from `storage/logs/dotw-certification/`

### WhatsApp Screenshots to Capture

Each screenshot must show the mandatory features as they appear to the end user.

| # | Screenshot | What to Show |
|---|-----------|-------------|
| 1 | Search results | Numbered hotel list with rates, star rating, meal plan |
| 2 | Room details | Cancellation policy rules with dates and penalty amounts |
| 3 | Room details | Tariff notes text from getRooms response |
| 4 | Room details | Minimum stay requirement (if applicable) |
| 5 | Room details | Minimum Selling Price (MSP) value |
| 6 | Room details | Special promotions (if any active) |
| 7 | Room details | Property fees (payable at property vs. included) |
| 8 | Prebook confirmation | All mandatory fields confirmed before booking |
| 9 | B2C payment link | Payment link sent after prebook |
| 10 | Booking confirmation | Confirmation number, hotel name, dates, passenger names |
| 11 | Voucher message | Voucher with `paymentGuaranteedBy` field |
| 12 | Cancellation step 1 | "Cancellation penalty: X KWD. Confirm?" |
| 13 | Cancellation step 2 | Cancellation confirmed message |
| 14 | 2-room booking | Confirmation showing 2 rooms |
| 15 | 2-room cancellation | Both rooms cancelled, productsLeftOnItinerary=0 in XML |
| 16 | Special requests | Booking XML showing valid special request codes |

---

## 4. Evidence Checklist — All 9 CERT Issues

The following checklist tracks evidence for each issue Olga raised on March 27.

### CERT-01: Salutation ID Mapping

- [ ] Booking XML shows correct `value` codes: Mr=147, Mrs=149, Miss=15134, Ms=148, Dr=558
- [ ] NOT the old wrong codes: Mr=1, Mrs=2, Miss=3
- [ ] WhatsApp screenshot shows salutation displayed in booking confirmation
- [ ] XML log: `<salutation>147</salutation>` for Mr (or equivalent real code)

### CERT-02: Special Request Codes

- [ ] Booking XML shows valid special request codes from DOTW `getspeicalrequests` API
- [ ] Non-smoking room = code 1711 (NOT code 1)
- [ ] Baby cot = code 1719
- [ ] At least one booking XML log showing `<req runno="...">1711</req>` or similar
- [ ] WhatsApp screenshot showing special request selection menu with options

### CERT-03: rateBasis Fix

- [ ] No `rateBasis=0` in any search or booking request XML
- [ ] Multi-room search: both rooms show consistent `rateBasis` (either -1 for all rates, or a specific valid ID)
- [ ] XML logs from 2-room search showing correct rateBasis values

### CERT-04: Nationality / Country of Residence

- [ ] Booking XML shows nationality collected from user (not hardcoded Kuwait=66 for all bookings)
- [ ] Booking XML shows countryOfResidence field populated
- [ ] WhatsApp screenshot: system asks "What is your nationality?" during booking flow
- [ ] At least one booking with non-Kuwait nationality in XML logs

### CERT-05: APR Flow Removed

- [ ] No `savebooking` calls in any certification test XML logs
- [ ] No `bookitinerary` calls in any certification test XML logs
- [ ] All bookings use `confirmBooking` only
- [ ] Test 16 (APR) removed or marked N/A in certification runner

### CERT-06: 2-Room Cancellation

- [ ] XML log: 2-room booking confirmed with 2 products
- [ ] XML log: cancellation check step — penalty shown for both rooms
- [ ] XML log: cancellation confirmed — `productsLeftOnItinerary=0`
- [ ] WhatsApp screenshot: "Your 2-room booking for [hotel] has been cancelled"

### CERT-07: Mandatory Display Features in WhatsApp Messages

All 8 mandatory features must be visible in WhatsApp before booking AND in confirmation/voucher:

- [ ] **Cancellation Policy** — rules with dates and penalty amounts shown in room selection
- [ ] **Tariff Notes** — full text displayed at checkout step and in confirmation
- [ ] **Minimum Stay** — shown if `minStay > 1` before booking
- [ ] **Minimum Selling Price (MSP)** — visible in room pricing display
- [ ] **Special Promotions** — shown when active specials apply
- [ ] **Special Requests** — selection offered, chosen request shown in confirmation
- [ ] **Restricted Cancellation Rules** — warning shown if `cancelRestricted=true`
- [ ] **Taxes & Property Fees** — shown with "payable at property" or "included" label

### CERT-08: B2B/B2C Connection Document

- [ ] Document sent to Olga: `docs/DOTW-B2B-B2C-Connection-Guide.md`
- [ ] Document explains multi-tenant architecture (Company -> Branch -> Agent)
- [ ] Document explains WhatsApp as the booking interface
- [ ] Document explains how new agencies onboard
- [ ] Document explains B2B credit line and payment gateway modes
- [ ] Document explains B2C markup pricing and upfront payment

### CERT-09: Test Access

- [ ] Option A: Olga's WhatsApp number set up as test agent (if preferred)
- [ ] Option B: Full screenshot + XML log package delivered (20 scenarios)
- [ ] All DOTW request/response XML logs available in `storage/logs/dotw-certification/`

---

## 5. How to Generate XML Evidence

Run the certification test suite to produce all request/response XML logs:

```bash
# Run all certification tests (uses DOTW sandbox API)
php artisan dotw:certify --test=1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,17,18,19,20

# Logs are saved to:
storage/logs/dotw-certification/

# Each test produces files named:
# - {test-number}-{step}-RQ.xml   (request sent to DOTW)
# - {test-number}-{step}-RS.xml   (response received from DOTW)
# - test-summary.txt              (PASS/FAIL summary for all tests)
```

### Log file structure example

```
storage/logs/dotw-certification/
├── 01-searchhotels-RQ.xml
├── 01-searchhotels-RS.xml
├── 01-getRooms-browse-RQ.xml
├── 01-getRooms-browse-RS.xml
├── 01-getRooms-blocking-RQ.xml
├── 01-getRooms-blocking-RS.xml
├── 01-confirmBooking-RQ.xml
├── 01-confirmBooking-RS.xml
├── 06-cancelBooking-check-RQ.xml
├── 06-cancelBooking-check-RS.xml
├── 06-cancelBooking-confirm-RQ.xml
├── 06-cancelBooking-confirm-RS.xml
└── test-summary.txt
```

### Generate fresh logs after applying all fixes

```bash
# 1. Clear old logs
rm -f storage/logs/dotw-certification/*.xml
rm -f storage/logs/dotw-certification/test-summary.txt

# 2. Run full certification suite
php artisan dotw:certify

# 3. Review results
cat storage/logs/dotw-certification/test-summary.txt

# 4. Check specific test XML (e.g., confirm booking shows correct salutation)
cat storage/logs/dotw-certification/10-confirmBooking-RQ.xml | grep salutation
```

---

## 6. Test Case Reference

| Test # | Name | Key Evidence |
|--------|------|-------------|
| 1 | Standard search + book | Search → block → confirm XML |
| 2 | 2-room booking | confirmBooking showing 2 rooms |
| 3 | Multi-passenger | Correct names in passenger XML |
| 4 | Children | Child ages in XML |
| 5 | Cancel (free) | cancelBooking with 0 penalty |
| 6 | Cancel (penalty) | cancelBooking with penalty amount |
| 7 | Nationality | Non-Kuwait nationality in XML |
| 8 | Long stay (14 nights) | 14-night booking XML |
| 9 | Multi-city search | Two separate searches |
| 10 | Salutation | value=147 for Mr in confirmBooking XML |
| 11 | MSP enforcement | MSP visible in WhatsApp message |
| 12 | Currency | Non-KWD currency in request |
| 13 | Country of residence | countryOfResidence field in XML |
| 14 | changedOccupancy | changedOccupancy section in XML |
| 15 | Special promotions | specialsApplied in getRooms response |
| 16 | APR removed | N/A — APRs removed from DOTW API |
| 17 | Restricted cancel | cancelRestricted warning in WhatsApp |
| 18 | Minimum stay | minStay enforced in booking |
| 19 | Amendments | bookItinerary amendment XML |
| 20 | Property fees | propertyFees in WhatsApp message |

---

## 7. Summary

| CERT Issue | Type | Evidence Required |
|-----------|------|------------------|
| CERT-01: Salutation IDs | Code fix | Booking XML showing value=147 for Mr |
| CERT-02: Special request codes | Code fix | Booking XML showing code=1711 for non-smoking |
| CERT-03: rateBasis=0 | Code fix | Search XML showing rateBasis=-1 or specific ID |
| CERT-04: Nationality/residence | Code + UX | WhatsApp asks for nationality; XML shows it |
| CERT-05: APR removed | Code removal | All bookings use confirmBooking only |
| CERT-06: 2-room cancel | Test + evidence | XML logs + WhatsApp screenshots |
| CERT-07: Mandatory display | Code + UX | WhatsApp screenshots showing all 8 features |
| CERT-08: B2B/B2C document | Documentation | DOTW-B2B-B2C-Connection-Guide.md sent |
| CERT-09: Test access | Access / screenshots | Option A or Option B above |

---

*Prepared by the City Commerce Group development team*
*Date: 2026-03-28*
*For DOTW certification review — in response to Olga Chicu's March 27 feedback*
