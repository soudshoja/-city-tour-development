# Feature Landscape: DOTW AI Module

**Domain:** WhatsApp-driven hotel booking with B2B/B2C tracks via n8n AI agents
**Researched:** 2026-03-24
**Overall Confidence:** HIGH

---

## Table Stakes

Features that the system must have for the module to function. Missing any of these means the booking flow breaks.

| Feature | Why Expected | Complexity | Notes |
|---------|-------------|------------|-------|
| Unified hotel search (city + dates + occupancy) | Core function -- n8n needs one query to search | High | Involves DOTW search -> browse -> block 3-step flow. Already have DotwService methods. |
| City name fuzzy resolution | WhatsApp users type "Dubaii" or "kualalumpur" -- must resolve to DOTW code | Medium | Levenshtein distance on dotw_cities table. Existing DotwGetCities query returns raw codes. |
| Country/nationality resolution | DOTW requires numeric codes, users type country names | Low | Same fuzzy pattern as cities, applied to dotw_countries |
| Multi-hotel disambiguation | "Marriott Dubai" may match 5 hotels -- must ask user to pick | Medium | Return `hotelOptions` list, n8n asks user, re-search with exact hotel ID |
| Rate blocking (3-min allocation) | DOTW requires blocking step before booking to lock price | Medium | Already have DotwBlockRates mutation. Module wraps this in the search flow. |
| B2B info-only response | Agents get pricing, book manually outside system | Low | Return search results + prebookKey, stop there. No DOTW confirmBooking. |
| B2C payment link generation | Customers must pay before DOTW booking is placed | Medium | Reuse existing `route('payment.link.show')` pattern from WhatsAppHotelController |
| B2C DOTW confirmation after payment | Payment webhook triggers actual DOTW booking | High | Must handle: rate expired, DOTW rejection, double webhook, partial failures |
| 2-step cancellation | DOTW requires check-charge then confirm-cancel | Medium | DotwService already has both methods. Module adds charge display + user confirmation flow. |
| APR booking block | Non-refundable (APR) bookings cannot be cancelled | Low | Check `is_apr` flag, return error if cancellation attempted |
| Cancellation deadline tracking | Store deadline dates from DOTW cancellation policies | Low | Parse cancellation_rules JSON, store earliest free-cancellation cutoff |
| Prebook expiry cleanup | Expired allocations must not block new searches | Low | Scheduled command, same pattern as DeleteExpiredHotelOffers |
| n8n REST endpoints (confirm/cancel/status) | n8n calls REST, not GraphQL, for booking actions | Low | 3 REST routes in module's api.php |

## Differentiators

Features that set this module apart from basic hotel search. Not strictly required for first release but deliver significant value.

| Feature | Value Proposition | Complexity | Notes |
|---------|------------------|------------|-------|
| Automated cancellation reminders (3/2/1 day) | Prevents agents/customers from accidentally incurring charges by missing free cancellation deadline | Medium | Creates Reminder records for scheduled WhatsApp delivery. Uses existing Reminder model + SendReminders command. |
| Auto-invoice after deadline passes | Revenue protection -- non-cancelled refundable bookings become billable automatically | Medium | Scheduled job checks bookings past deadline, creates Invoice + JournalEntry |
| WhatsApp voucher delivery | After DOTW confirms, send booking voucher via WhatsApp (hotel name, dates, confirmation number, paymentGuaranteedBy) | Low | Message builder formats text, ResayilController sends |
| Credit line booking (B2B) | Agents with credit can book without upfront payment | Medium | Check company Credit model, deduct from available credit, create invoice for reconciliation |
| Currency conversion + markup transparency | Display KWD prices with breakdown (original fare, markup amount, final fare) | Low | Already implemented in RateMarkup GraphQL type in dotw.graphql |
| Tariff notes display | DOTW-mandated notes shown to customer before booking (certification requirement) | Low | Stored in prebook, passed to n8n, AI agent shows to user |
| Special promotions display | Highlight "Early Bird", "Free Night", "Honeymoon" offers from DOTW | Low | Mapped from DOTW specials XML, returned in GraphQL response |
| Changed occupancy handling | When DOTW adjusts room occupancy (e.g., adds extra bed), track both original and actual | Medium | Dual-source mapping in prebook, used in confirmBooking passenger/room details |
| Booking status endpoint | n8n can check "what happened to this booking?" anytime | Low | Simple GET returning current prebook + booking state |

## Anti-Features

Features to explicitly NOT build in this milestone.

| Anti-Feature | Why Avoid | What to Do Instead |
|-------------|-----------|-------------------|
| Livewire/web UI for hotel booking | B2B is WhatsApp-only per design. Web UI adds surface area without value for this track. | B2B uses WhatsApp exclusively. B2C gets a payment link page (already exists). |
| Multi-supplier aggregation | Combining DOTW + TBO + Magic Holiday results adds immense complexity (different schemas, pricing models, cancellation policies) | DOTW-only for this milestone. Supplier abstraction layer is a future milestone. |
| Real-time rate updates via WebSocket | DOTW allocations are point-in-time (3-min window). Push updates don't match the API model. | n8n re-searches when user asks again. Fresh results each time. |
| WhatsApp media messages (images, PDFs) | Resayil API currently supports text messages. Sending hotel images or PDF vouchers requires media upload support. | Text-only vouchers and confirmations. Media support is a future enhancement. |
| Rate comparison across meal plans | Showing "Breakfast is KWD 5 more than Room Only" requires complex display logic in WhatsApp text. | Show all available rates. Let user/AI agent compare. |
| Automated re-booking on failure | If DOTW rejects after payment, auto-searching for alternatives adds unpredictable behavior. | Notify agent/customer of failure. Manual re-search. Refund the payment. |
| Guest name collection via WhatsApp | Asking for each guest's first name, last name, salutation via multi-turn WhatsApp conversation is error-prone. | Use booker's name for all adults, "Child1/Child2" for children (matching existing WhatsAppHotelController pattern). |

## Feature Dependencies

```
Static Data Sync --> City/Country Resolution --> Hotel Search
                                                      |
                                                      v
                                              Rate Blocking --> Prebook Creation
                                                                      |
                                              +----------------------+----------------------+
                                              |                                             |
                                         B2B: Info Only                                B2C: Payment Link
                                              |                                             |
                                              v                                             v
                                         (End for B2B)                              Payment Webhook
                                                                                           |
                                                                                           v
                                                                                    DOTW confirmBooking
                                                                                           |
                                                                                           v
                                                                              Task + Invoice Creation
                                                                                           |
                                                                              Cancellation Deadline Tracking
                                                                                           |
                                                                              +------------+------------+
                                                                              |                         |
                                                                     Reminder Scheduling        Auto-Invoice
                                                                              |                         |
                                                                              v                         v
                                                                     WhatsApp Delivery         Journal Entry
```

## MVP Recommendation

Prioritize for first working end-to-end:

1. **Static data sync** (table stakes) -- without city codes, nothing works
2. **Hotel search query** (table stakes) -- n8n's primary tool
3. **B2B info-only response** (table stakes) -- simplest booking track, proves the pipeline
4. **B2C payment + DOTW confirm** (table stakes) -- the revenue-generating path
5. **Cancellation reminders** (differentiator) -- highest value add for agent experience

Defer to post-MVP:
- **Auto-invoice after deadline:** Important but not blocking initial launch. Can be run manually first.
- **Credit line B2B booking:** Requires careful accounting integration. Start with "all B2B is info-only" and add credit booking later.
- **Changed occupancy handling:** Edge case that affects <5% of bookings. Handle it, but don't let it block launch.
