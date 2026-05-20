# Test 1: Book 2 adults — Basic full booking flow (Flow A)

**Result:** ✔ PASS

## Request/Response Files

- `1a-search_RQ.xml` / `1a-search_RS.xml`
- `1b-browse-hhotel_RQ.xml` / `1b-browse-hhotel_RS.xml`
- `1c-block-hhotel_RQ.xml` / `1c-block-hhotel_RS.xml`
- `1d-confirm-hhotel_RQ.xml` / `1d-confirm-hhotel_RS.xml`

## Evidence

- [STEP] searchhotels — Dubai, 2 adults, 1 night
- [1a] Found 332 hotels — will try each until one books successfully
- [STEP] getRooms (browse) — hotel 1013275
- [1b] allocationDetails obtained: 1775203201000001B1000B1...
- [STEP] getRooms (blocking) — hotel 1013275
- [1c] Blocked — status: checked | allocationDetails: 1775203202000001B1000B0...
- [STEP] confirmbooking — 2 adults — hotel 1013275
- [1d] Booking confirmed — bookingCode: 938745073 | returnedCode: 938745063 | ref: HTL-WBD-938745073

