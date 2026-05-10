# Test 5: Cancel booking outside cancellation deadline — expect charge=0

**Result:** ✔ PASS

## Request/Response Files

- `5a-search_RQ.xml` / `5a-search_RS.xml`
- `5b-browse-hhotel_RQ.xml` / `5b-browse-hhotel_RS.xml`
- `5c-block-hhotel_RQ.xml` / `5c-block-hhotel_RS.xml`
- `5d-confirm-hhotel_RQ.xml` / `5d-confirm-hhotel_RS.xml`
- `5e-cancel-check_RQ.xml` / `5e-cancel-check_RS.xml`
- `5f-cancel-confirm_RQ.xml` / `5f-cancel-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — far-future date (outside cancel deadline)
- [5a] Found 299 hotels — looking for cancellable rates
- [5b] Browse OK — refundable rates selected
- [5c] Blocked OK — all rooms status: checked
- [5d] Booking confirmed — bookingCode: 938745233 | ref: HTL-WBD-938745233
- [STEP] cancelBooking (confirm=no) — check cancellation charge
- [STEP] cancelBooking (confirm=yes) — execute cancellation
- [5f] Cancellation confirmed — bookingCode: 938745233 | penaltyApplied: 13.2818

