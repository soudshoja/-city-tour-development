# Test 13: Blocking Step Validation — abort if status != "checked"

**Result:** ✔ PASS

## Request/Response Files

- `13a-search_RQ.xml` / `13a-search_RS.xml`
- `13b-browse_RQ.xml` / `13b-browse_RS.xml`
- `13c-block_RQ.xml` / `13c-block_RS.xml`

## Evidence

- [STEP] searchhotels
- Blocking getRooms returned status: [checked]
- [13c] Status is 'checked' — proceed to confirmbooking

