# Test 7: Cancel booking — check productsLeftOnItinerary in response

**Result:** ✔ PASS

## Request/Response Files

- `7a-search_RQ.xml` / `7a-search_RS.xml`
- `7b-browse-hhotel_RQ.xml` / `7b-browse-hhotel_RS.xml`
- `7c-block-hhotel_RQ.xml` / `7c-block-hhotel_RS.xml`
- `7d-confirm-hhotel_RQ.xml` / `7d-confirm-hhotel_RS.xml`
- `7e-cancel-check_RQ.xml` / `7e-cancel-check_RS.xml`
- `7f-cancel-confirm_RQ.xml` / `7f-cancel-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — far-future date (outside cancel deadline)
- [7a] Found 168 hotels — looking for cancellable rates
- [7b] Browse OK — refundable rates selected
- [7c] Blocked OK — all rooms status: checked
- [7d] Booking confirmed — bookingCode: 938745393 | ref: HTL-WBD-938745393
- [STEP] cancelBooking (confirm=no) — check charge
- [7e] Charge reported: 13.2818
- [STEP] cancelBooking (confirm=yes) — check productsLeftOnItinerary
- [7f] Cancellation confirmed — bookingCode: 938745393
- [7g] productsLeftOnItinerary=0 — all services cancelled (single-product itinerary)

