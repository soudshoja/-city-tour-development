# Test 2: Book 2 adults + 1 child (age 11)

**Result:** ✔ PASS

## Request/Response Files

- `2a-search_RQ.xml` / `2a-search_RS.xml`
- `2b-browse_RQ.xml` / `2b-browse_RS.xml`
- `2c-block_RQ.xml` / `2c-block_RS.xml`
- `2d-confirm_RQ.xml` / `2d-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — 2 adults + 1 child age 11
- [2a] Hotel: 1013275 | Room: 483146225
- [STEP] getRooms (browse)
- [2b] allocationDetails obtained
- [STEP] getRooms (blocking)
- [2c] Blocked OK — status: checked
- [STEP] confirmbooking — 2 adults + child age 11 (runno=0)
- [2d] Confirmed — bookingCode: 938745133 | ref: HTL-WBD-938745133

