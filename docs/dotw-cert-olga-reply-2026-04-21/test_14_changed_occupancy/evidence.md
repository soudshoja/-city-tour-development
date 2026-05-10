# Test 14: Changed Occupancy — validForOccupancy overrides search adultsCode/children/extraBed

**Result:** ✔ PASS

## Request/Response Files

- `14a-search_RQ.xml` / `14a-search_RS.xml`
- `14b-browse_RQ.xml` / `14b-browse_RS.xml`
- `14c-block_RQ.xml` / `14c-block_RS.xml`
- `14d-confirm_RQ.xml` / `14d-confirm_RS.xml`

## Evidence

- [STEP] searchhotels — Dubai, 3 adults + 1 child (age 12), 1 night
- [14a] Hotel: 1015245 | Rate: 0
- [STEP] getRooms (browse) — detect changedOccupancy and validForOccupancy
- [14b] changedOccupancy detected: 4,0,,0
- [VERIFY] Using validForOccupancy: adults=4, extraBed=0
- [STEP] getRooms (blocking)
- [14c] Blocked OK — status: checked
- [STEP] confirmbooking — validForOccupancy adultsCode/extraBed, original actualAdults/actualChildren
- [VERIFY] XML: <adultsCode>4</adultsCode> (from validForOccupancy)
- [VERIFY] XML: <actualAdults>3</actualAdults> (from original search)
- [VERIFY] XML: <children no="1"><child runno="0">12</child></children> (from validForOccupancy)
- [VERIFY] XML: <actualChildren no="1"><actualChild runno="0">12</actualChild></actualChildren> (from original search)
- [14d] Booking confirmed — Code: 938745603
- VERIFICATION: validForOccupancy values used for adultsCode/children/extraBed, original for actualAdults/actualChildren

