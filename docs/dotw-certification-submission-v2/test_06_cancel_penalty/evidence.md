# Test 6: Cancel 2-room booking within cancellation deadline — cancel with penalty

**Result:** ✔ PASS

## Request/Response Files

- `6a-search_RQ.xml` / `6a-search_RS.xml`
- `6b-browse_RQ.xml` / `6b-browse_RS.xml`
- `6c-block_RQ.xml` / `6c-block_RS.xml`
- `6d-confirm_RQ.xml` / `6d-confirm_RS.xml`
- `6e-cancel-check_RQ.xml` / `6e-cancel-check_RS.xml`
- `6f-cancel-confirm_RQ.xml` / `6f-cancel-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — 60-day future date (within cancel penalty window), 2 rooms
- [6a] Hotel: 1013275 (has 2 room types)
- [STEP] getRooms (browse) — both rooms
- [6b] Browse OK — allocationDetails obtained for both rooms
- [STEP] getRooms (blocking) — both rooms
- [6c] Both rooms blocked — status0: checked | status1: checked
- [STEP] confirmbooking — 2 rooms
- [6d] Booking confirmed — Code: 938745353
- [STEP] cancelBooking (confirm=no) — check cancellation charge (expect penalty)
- [6e] Cancellation charge: 11.4498 — penaltyApplied
- [STEP] cancelBooking (confirm=yes) — execute cancellation with penalty
- [6f] Cancellation confirmed — bookingCode: 938745353 | penaltyApplied: 11.4498

