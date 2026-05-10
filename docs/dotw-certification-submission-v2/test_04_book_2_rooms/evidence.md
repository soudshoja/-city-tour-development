# Test 4: Book 2 rooms (1 single + 1 double) — multi-room booking flow

**Result:** ✔ PASS

## Request/Response Files

- `4a-search_RQ.xml` / `4a-search_RS.xml`
- `4b-browse_RQ.xml` / `4b-browse_RS.xml`
- `4c-block_RQ.xml` / `4c-block_RS.xml`
- `4d-confirm_RQ.xml` / `4d-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — Dubai, 2 rooms (1 single + 1 double)
- [4a] Hotel: 1013275 | Room0: 483146225 rb:0 | Room1: 483146225 rb:0
- [STEP] getRooms (browse) — both rooms
- [4b] allocationDetails obtained for both rooms
- [STEP] getRooms (blocking) — both rooms with roomTypeSelected
- [4c] Both rooms blocked — status0: checked | status1: checked
- [STEP] confirmbooking — 2 rooms, room0: 1 adult, room1: 2 adults
- [4d] Booking confirmed — Code: 938745173

