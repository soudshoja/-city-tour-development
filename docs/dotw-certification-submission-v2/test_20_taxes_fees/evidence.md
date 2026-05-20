# Test 20: Property Taxes/Fees — detect propertyFees in searchhotels response

**Result:** ✔ PASS

## Request/Response Files

- `20a-search_RQ.xml` / `20a-search_RS.xml`

## Evidence

- [STEP] searchhotels — Dubai, 2 adults, 1 night, 20 results — inspect propertyFees
- [20a] Hotel 30914 fee: Taxes and Fees | includedinprice: No
- [VERIFY] Fee payable at property — display separately to customer
- VERIFICATION: propertyFees must be displayed to customer — paid at property, not included in DOTW rate

