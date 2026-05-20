# Test 15: Special Promotions — detect specials and specialsApplied on rateBasis

**Result:** ✔ PASS

## Request/Response Files

- `15a-search_RQ.xml` / `15a-search_RS.xml`
- `15b-browse_RQ.xml` / `15b-browse_RS.xml`

## Evidence

- [STEP] searchhotels — Hotel 2344175 (The S Hotel Al Barsha, Dubai), 2A+2C ages 8,12, 14-15 May 2026
- [15a] Hotel: 2344175 (The S Hotel Al Barsha)
- [STEP] getRooms (browse) — request specials roomField, inspect specialsApplied
- [15b] 1 special(s) found on roomType
- [15b] specialsApplied found on rateBasis
- VERIFICATION: When specials present, display to customer before booking

