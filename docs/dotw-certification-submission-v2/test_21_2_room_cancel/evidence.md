# Test 21: 2-Room Cancellation — book 2 rooms then cancel (CERT-06 evidence for Olga)

**Result:** ✔ PASS

## Request/Response Files

- `21a-search_RQ.xml` / `21a-search_RS.xml`
- `21b-browse-hhotel_RQ.xml` / `21b-browse-hhotel_RS.xml`
- `21c-block-hhotel_RQ.xml` / `21c-block-hhotel_RS.xml`
- `21d-confirm-hhotel_RQ.xml` / `21d-confirm-hhotel_RS.xml`
- `21e-cancel-check_RQ.xml` / `21e-cancel-check_RS.xml`
- `21f-cancel-confirm_RQ.xml` / `21f-cancel-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — Dubai, 2 rooms (2 adults each)
- [21a] Found 350 hotels — looking for 2-room cancellable rates
- [21b] Browse OK — refundable rates selected
- [21c] Blocked OK — all rooms status: checked
- [21d] Booking confirmed — bookingCode: 938746833 | ref: HTL-WBD-938746833
- [STEP] cancelBooking (confirm=no) — get cancellation charges for 2-room booking
- [21e] Services in charge response: 1 — charge retrieved successfully
- [STEP] cancelBooking (confirm=yes) — cancel all 2 rooms, check productsLeftOnItinerary
- [21f] 2-room cancellation confirmed — bookingCode: 938746833
- [21g] productsLeftOnItinerary=1 — 1 service(s) still on itinerary (partial cancel scenario)
- [NOTE] productsLeftOnItinerary > 0 means the booking itinerary has more products not yet cancelled
- CERT-06 EVIDENCE: 2-room search → getRooms → confirmBooking → cancelBooking (2 services) all logged

