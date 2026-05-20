# Test 19: Special Requests — confirmbooking with specialRequests count=1 (no smoking)

**Result:** ✔ PASS

## Request/Response Files

- `19a-search_RQ.xml` / `19a-search_RS.xml`
- `19b-browse_RQ.xml` / `19b-browse_RS.xml`
- `19c-block_RQ.xml` / `19c-block_RS.xml`
- `19d-confirm_RQ.xml` / `19d-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — Dubai, 2 adults, 1 night
- [19a] Hotel: 1013275
- [STEP] getRooms (browse)
- [19b] Browse OK
- [STEP] getRooms (blocking)
- [19c] Blocked OK — status: checked
- [STEP] confirmbooking — specialRequests count=1, code=1 (no smoking)
- [19d] Booking with special request code=1 confirmed: 938746703
- VERIFICATION: Special request code 1 (no smoking) sent in XML

