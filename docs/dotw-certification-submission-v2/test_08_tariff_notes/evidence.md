# Test 8: Tariff Notes — getRooms returns tariffNotes (mandatory display)

**Result:** ✔ PASS

## Request/Response Files

- `8a-search_RQ.xml` / `8a-search_RS.xml`
- `8b-rooms_RQ.xml` / `8b-rooms_RS.xml`

## Evidence

- [STEP] searchhotels — find a hotel
- [8a] Hotel: 1013275
- [STEP] getRooms — request tariffNotes field
- [8b] tariffNotes received (993 chars): Rate Notes:
- VERIFICATION: tariffNotes MUST be displayed in UI and on customer voucher

