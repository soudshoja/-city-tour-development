# Evidence: Test 02 -- Book 2A+1C

Mandatory display features found in this test API responses.

## `cancellationRules` (6 occurrence(s))

**Found in step `2b-browse`:**
```xml
<cancellationRules count="2">
                <rule runno="0">
                  <fromDate>2026-03-24 10:16:22</fromDate>
                  <fromDateDetails>Tue, 24 Mar 2026 10:16:22</fromDateDetails>
                  <amendRestricted>true</amendRestricted>
                  <cancelCharge>10.4328<formatted>10.43</formatted></cancelCharge>
                  <charge>10.4328<formatted>10.43</formatted></charge>
                </rule>
                <rule runno="1">
                  <fromDate>2026-04-28 23:00:00</fromDate>
                  <fromDateDetails>Tue, 28 Apr 2026 23:00:00</fromDateDetails>
                  <noShowPolicy>true</noShowPolicy>
                  <charge>10.4328<formatted>10.43</formatted></charge>
                </rule>
              </cancellationRules>
```

**Found in step `2b-browse`:**
```xml
<cancellationRules count="3">
                <rule runno="0">
                  <toDate>2026-04-23 12:59:59</toDate>
                  <toDateDetails>Thu, 23 Apr 2026 12:59:59</toDateDetails>
                  <amendCharge>0<formatted>0.00</formatted></amendCharge>
                  <cancelCharge>0<formatted>0.00</formatted></cancelCharge>
                  <charge>0<formatted>0.00</formatted></charge>
                </rule>
                <rule runno="1">
                  <fromDate>2026-04-23 13:00:00</fromDate>
                  <fromDateDetails>Thu, 23 Apr 2026 13:00:00</fromDateDetails>
                  <amendCharge>12.2472<formatted>12.25</formatted></amendCharge>
                  <cancelCharge>12.2472<formatted>12.25</formatted></cancelCharge>
                  <charge>12.2472<formatted>12.25</formatted></charge>
                </rule>
                <rule runno="2">
                  <fromDate>2026-04-28 23:00:00</fromDate>
                  <fromDateDetails>Tue, 28 Apr 2026 23:00:00</fromDateDetails>
                  <noShowPolicy>true</noShowPolicy>
                  <charge>12.2472<formatted>12.25</formatted></charge>
                </rule>
              </cancellationRules>
```

**Found in step `2b-browse`:**
```xml
<cancellationRules count="2">
                <rule runno="0">
                  <fromDate>2026-03-24 10:16:22</fromDate>
                  <fromDateDetails>Tue, 24 Mar 2026 10:16:22</fromDateDetails>
                  <amendRestricted>true</amendRestricted>
                  <cancelCharge>16.3296<formatted>16.33</formatted></cancelCharge>
                  <charge>16.3296<formatted>16.33</formatted></charge>
                </rule>
                <rule runno="1">
                  <fromDate>2026-04-28 23:00:00</fromDate>
                  <fromDateDetails>Tue, 28 Apr 2026 23:00:00</fromDateDetails>
                  <noShowPolicy>true</noShowPolicy>
                  <charge>16.3296<formatted>16.33</formatted></charge>
                </rule>
              </cancellationRules>
```

**Found in step `2c-block`:**
```xml
<cancellationRules count="2">
                <rule runno="0">
                  <fromDate>2026-03-24 10:16:22</fromDate>
                  <fromDateDetails>Tue, 24 Mar 2026 10:16:22</fromDateDetails>
                  <amendRestricted>true</amendRestricted>
                  <cancelCharge>10.4328<formatted>10.43</formatted></cancelCharge>
                  <charge>10.4328<formatted>10.43</formatted></charge>
                </rule>
                <rule runno="1">
                  <fromDate>2026-04-28 23:00:00</fromDate>
                  <fromDateDetails>Tue, 28 Apr 2026 23:00:00</fromDateDetails>
                  <noShowPolicy>true</noShowPolicy>
                  <charge>10.4328<formatted>10.43</formatted></charge>
                </rule>
              </cancellationRules>
```

**Found in step `2c-block`:**
```xml
<cancellationRules count="3">
                <rule runno="0">
                  <toDate>2026-04-23 12:59:59</toDate>
                  <toDateDetails>Thu, 23 Apr 2026 12:59:59</toDateDetails>
                  <amendCharge>0<formatted>0.00</formatted></amendCharge>
                  <cancelCharge>0<formatted>0.00</formatted></cancelCharge>
                  <charge>0<formatted>0.00</formatted></charge>
                </rule>
                <rule runno="1">
                  <fromDate>2026-04-23 13:00:00</fromDate>
                  <fromDateDetails>Thu, 23 Apr 2026 13:00:00</fromDateDetails>
                  <amendCharge>12.2472<formatted>12.25</formatted></amendCharge>
                  <cancelCharge>12.2472<formatted>12.25</formatted></cancelCharge>
                  <charge>12.2472<formatted>12.25</formatted></charge>
                </rule>
                <rule runno="2">
                  <fromDate>2026-04-28 23:00:00</fromDate>
                  <fromDateDetails>Tue, 28 Apr 2026 23:00:00</fromDateDetails>
                  <noShowPolicy>true</noShowPolicy>
                  <charge>12.2472<formatted>12.25</formatted></charge>
                </rule>
              </cancellationRules>
```

*... and 1 more occurrence(s)*

## `propertyFees` (7 occurrence(s))

**Found in step `2a-search`:**
```xml
<propertyFees count="2">
                  <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">2.336</propertyFee>
                  <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519</propertyFee>
                </propertyFees>
```

**Found in step `2a-search`:**
```xml
<propertyFees count="2">
                  <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">3.7063</propertyFee>
                  <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519</propertyFee>
                </propertyFees>
```

**Found in step `2a-search`:**
```xml
<propertyFees count="2">
                  <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">2.336</propertyFee>
                  <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">1.2519</propertyFee>
                </propertyFees>
```

**Found in step `2b-browse`:**
```xml
<propertyFees count="3">
                <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">7.7498<formatted>7.75</formatted></propertyFee>
                <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Occupancy Fee" description="Occupancy Fee" includedinprice="Yes">20.0306<formatted>20.03</formatted></propertyFee>
                <propertyFee runno="2" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">0.8346<formatted>0.83</formatted></propertyFee>
              </propertyFees>
```

**Found in step `2b-browse`:**
```xml
<propertyFees count="3">
                <propertyFee runno="0" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="Yes">7.7498<formatted>7.75</formatted></propertyFee>
                <propertyFee runno="1" currencyid="769" currencyshort="KWD" name="Occupancy Fee" description="Occupancy Fee" includedinprice="Yes">20.0306<formatted>20.03</formatted></propertyFee>
                <propertyFee runno="2" currencyid="769" currencyshort="KWD" name="Taxes and Fees" description="Taxes and Fees" includedinprice="No">0.8346<formatted>0.83</formatted></propertyFee>
              </propertyFees>
```

*... and 2 more occurrence(s)*

## `changedOccupancy` (4 occurrence(s))

**Found in step `2b-browse`:**
```xml
<changedOccupancy>3,0,,1</changedOccupancy>
```

**Found in step `2b-browse`:**
```xml
<changedOccupancy>3,0,,1</changedOccupancy>
```

**Found in step `2c-block`:**
```xml
<changedOccupancy>3,0,,1</changedOccupancy>
```

**Found in step `2c-block`:**
```xml
<changedOccupancy>3,0,,1</changedOccupancy>
```

## `validForOccupancy` (4 occurrence(s))

**Found in step `2b-browse`:**
```xml
<validForOccupancy>
                <adults>3</adults>
                <extraBed>1</extraBed>
                <extraBedOccupant>child</extraBedOccupant>
              </validForOccupancy>
```

**Found in step `2b-browse`:**
```xml
<validForOccupancy>
                <adults>3</adults>
                <extraBed>1</extraBed>
                <extraBedOccupant>child</extraBedOccupant>
              </validForOccupancy>
```

**Found in step `2c-block`:**
```xml
<validForOccupancy>
                <adults>3</adults>
                <extraBed>1</extraBed>
                <extraBedOccupant>child</extraBedOccupant>
              </validForOccupancy>
```

**Found in step `2c-block`:**
```xml
<validForOccupancy>
                <adults>3</adults>
                <extraBed>1</extraBed>
                <extraBedOccupant>child</extraBedOccupant>
              </validForOccupancy>
```

## `amendRestricted` (4 occurrence(s))

**Found in step `2b-browse`:**
```xml
<amendRestricted>true</amendRestricted>
```

**Found in step `2b-browse`:**
```xml
<amendRestricted>true</amendRestricted>
```

**Found in step `2c-block`:**
```xml
<amendRestricted>true</amendRestricted>
```

**Found in step `2c-block`:**
```xml
<amendRestricted>true</amendRestricted>
```

## `paymentGuaranteedBy` (1 occurrence(s))

**Found in step `2d-confirm`:**
```xml
<paymentGuaranteedBy>Payment is guaranteed by: WebBeds FZ LLC, as per final booking form reference No: HTL-WBD-932563673</paymentGuaranteedBy>
```

## `allocationDetails` (6 occurrence(s))

**Found in step `2b-browse`:**
```xml
<allocationDetails>1774336582000001B1000B2</allocationDetails>
```

**Found in step `2b-browse`:**
```xml
<allocationDetails>1774336582000001B1000B0</allocationDetails>
```

**Found in step `2b-browse`:**
```xml
<allocationDetails>1774336582000001B1000B3</allocationDetails>
```

**Found in step `2c-block`:**
```xml
<allocationDetails>1774336582000004B1000B2</allocationDetails>
```

**Found in step `2c-block`:**
```xml
<allocationDetails>1774336582000004B1000B0</allocationDetails>
```

*... and 1 more occurrence(s)*

---
*Generated by DOTW Certification Package Generator*
