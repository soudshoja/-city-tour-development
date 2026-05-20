# Test 18: Minimum Stay — detect minStay and dateApplyMinStay on rateBasis

**Result:** ✔ PASS

## Request/Response Files

- `18a-search_RQ.xml` / `18a-search_RS.xml`
- `18b-rooms-h1013275_RQ.xml` / `18b-rooms-h1013275_RS.xml`
- `18b-rooms-h2290115_RQ.xml` / `18b-rooms-h2290115_RS.xml`
- `18b-rooms-h456095_RQ.xml` / `18b-rooms-h456095_RS.xml`
- `18b-rooms-h1015245_RQ.xml` / `18b-rooms-h1015245_RS.xml`
- `18b-rooms-h2291475_RQ.xml` / `18b-rooms-h2291475_RS.xml`

## Evidence

- [STEP] searchhotels — Dubai, 2 adults, 4 nights
- [18a] Found 355 hotel(s) — scanning all for minStay
- [STEP] getRooms (browse) — request minStay roomField for all hotels
- [18b] Hotel 2291475: minStay=30 nights | dateApplyMinStay=
- VERIFICATION: Block bookings where nights < minStay and arrival date matches dateApplyMinStay

