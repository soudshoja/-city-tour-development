# Evidence: Test 15 -- Special Promotions

Mandatory display features found in this test API responses.

## `cancellationRules` (3 occurrence(s))

**Found in step `15b-browse`:**
```xml
<cancellationRules count="3">
                <rule runno="0">
                  <toDate>2026-05-11 12:59:59</toDate>
                  <toDateDetails>Mon, 11 May 2026 12:59:59</toDateDetails>
                  <amendCharge>0<formatted>0.00</formatted></amendCharge>
                  <cancelCharge>0<formatted>0.00</formatted></cancelCharge>
                  <charge>0<formatted>0.00</formatted></charge>
                </rule>
                <rule runno="1">
                  <fromDate>2026-05-11 13:00:00</fromDate>
                  <fromDateDetails>Mon, 11 May 2026 13:00:00</fromDateDetails>
                  <amendCharge>47.8547<formatted>47.85</formatted></amendCharge>
                  <cancelCharge>47.8547<formatted>47.85</formatted></cancelCharge>
                  <charge>47.8547<formatted>47.85</formatted></charge>
                </rule>
                <rule runno="2">
                  <fromDate>2026-05-14 23:00:00</fromDate>
                  <fromDateDetails>Thu, 14 May 2026 23:00:00</fromDateDetails>
                  <noShowPolicy>true</noShowPolicy>
                  <charge>47.8547<formatted>47.85</formatted></charge>
                </rule>
              </cancellationRules>
```

**Found in step `15b-browse`:**
```xml
<cancellationRules count="1">
                <rule runno="0">
                  <fromDate>2026-03-23 03:00:01</fromDate>
                  <fromDateDetails>Mon, 23 Mar 2026 03:00:01</fromDateDetails>
                  <amendRestricted>true</amendRestricted>
                  <cancelRestricted>true</cancelRestricted>
                </rule>
              </cancellationRules>
```

**Found in step `15b-browse`:**
```xml
<cancellationRules count="1">
                <rule runno="0">
                  <fromDate>2026-03-23 03:00:01</fromDate>
                  <fromDateDetails>Mon, 23 Mar 2026 03:00:01</fromDateDetails>
                  <amendRestricted>true</amendRestricted>
                  <cancelRestricted>true</cancelRestricted>
                </rule>
              </cancellationRules>
```

## `specials` (1 occurrence(s))

**Found in step `15b-browse`:**
```xml
<specials count="1">
            <special runno="0">
              <type>stayXGetDiscountPromotion</type>
              <specialName>Promotional rate</specialName>
            </special>
          </specials>
```

## `specialsApplied` (1 occurrence(s))

**Found in step `15b-browse`:**
```xml
<specialsApplied>
                <special>0</special>
              </specialsApplied>
```

## `propertyFees` (5 occurrence(s))

**Found in step `15a-search`:**
```xml
<propertyFees count="2">
                  <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">10.141</propertyFee>
                  <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519</propertyFee>
                </propertyFees>
```

**Found in step `15a-search`:**
```xml
<propertyFees count="2">
                  <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">10.9503</propertyFee>
                  <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519</propertyFee>
                </propertyFees>
```

**Found in step `15b-browse`:**
```xml
<propertyFees count="2">
                <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">10.141<formatted>10.14</formatted></propertyFee>
                <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519<formatted>1.25</formatted></propertyFee>
              </propertyFees>
```

**Found in step `15b-browse`:**
```xml
<propertyFees count="2">
                <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">10.141<formatted>10.14</formatted></propertyFee>
                <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519<formatted>1.25</formatted></propertyFee>
              </propertyFees>
```

**Found in step `15b-browse`:**
```xml
<propertyFees count="2">
                <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">11.2661<formatted>11.27</formatted></propertyFee>
                <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519<formatted>1.25</formatted></propertyFee>
              </propertyFees>
```

## `changedOccupancy` (1 occurrence(s))

**Found in step `15b-browse`:**
```xml
<changedOccupancy>3,1,8,1</changedOccupancy>
```

## `validForOccupancy` (1 occurrence(s))

**Found in step `15b-browse`:**
```xml
<validForOccupancy>
                <adults>3</adults>
                <children>1</children>
                <childrenAges>8</childrenAges>
                <extraBed>1</extraBed>
                <extraBedOccupant>child</extraBedOccupant>
              </validForOccupancy>
```

## `cancelRestricted` (2 occurrence(s))

**Found in step `15b-browse`:**
```xml
<cancelRestricted>true</cancelRestricted>
```

**Found in step `15b-browse`:**
```xml
<cancelRestricted>true</cancelRestricted>
```

## `amendRestricted` (2 occurrence(s))

**Found in step `15b-browse`:**
```xml
<amendRestricted>true</amendRestricted>
```

**Found in step `15b-browse`:**
```xml
<amendRestricted>true</amendRestricted>
```

## `allocationDetails` (3 occurrence(s))

**Found in step `15b-browse`:**
```xml
<allocationDetails>1774336670000004B1000B0</allocationDetails>
```

**Found in step `15b-browse`:**
```xml
<allocationDetails>1774336670000005B1836B2</allocationDetails>
```

**Found in step `15b-browse`:**
```xml
<allocationDetails>1774336670000005B1836B0</allocationDetails>
```

---
*Generated by DOTW Certification Package Generator*
