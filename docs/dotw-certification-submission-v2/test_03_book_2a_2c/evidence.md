# Test 3: Book 2 adults + 2 children (ages 8, 9) — multiple child runno

**Result:** ✔ PASS

## Request/Response Files

- `3a-search_RQ.xml` / `3a-search_RS.xml`
- `3b-browse_RQ.xml` / `3b-browse_RS.xml`
- `3c-block_RQ.xml` / `3c-block_RS.xml`
- `3d-confirm_RQ.xml` / `3d-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — 2 adults + 2 children (ages 8,9)
- [3a] Hotel: 1015245
- [STEP] getRooms (browse)
- [3b] Browse OK
- [STEP] getRooms (blocking)
- [3c] Blocked OK
- [STEP] confirmbooking — 2 adults + 2 children with runno 0,1
- [3d] Confirmed — bookingCode: 938745153 | ref: HTL-WBD-938745153

