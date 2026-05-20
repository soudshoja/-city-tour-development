# Test 9: Cancellation Rules — sourced from getRooms (not searchhotels)

**Result:** ✔ PASS

## Request/Response Files

- `9a-search_RQ.xml` / `9a-search_RS.xml`
- `9b-rooms_RQ.xml` / `9b-rooms_RS.xml`

## Evidence

- [STEP] searchhotels
- [9a] Hotel: 1013275
- [STEP] getRooms — request cancellation field (policy source of truth)
- [9b] 2 cancellation rule(s) returned from getRooms
- VERIFICATION: Cancellation policy MUST be sourced from getRooms, not searchhotels

