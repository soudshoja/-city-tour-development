# Phase 11: DOTW V4 Real Certification — Research

**Researched:** 2026-02-26
**Domain:** PHP Artisan Console Command surgery — DotwCertify WARN-pattern fix, SKIP state, confirmbooking XML correctness, sandbox vs live environment
**Confidence:** HIGH (all findings from direct source code inspection and MEMORY.md project history)

---

## Summary

Phase 11 exists because Phase 10 was marked complete under false pretenses. The `DotwCertify` command has a WARN-pattern fallback in every test (1–20): when `count($hotels) === 0` it logs a note and calls `endTest(N, true)` — recording PASS. Since the xmldev.dotwconnect.com sandbox returned no hotels for tests 2–20, all 19 of those tests auto-passed without executing a single booking API call. Only Test 1 ever ran a real booking flow (sandbox happened to return hotels that run).

Beyond the WARN→SKIP fix, the real-run path also has correctness bugs: Tests 2–20 confirmbooking XML is missing mandatory fields (`extraBed`, `passengerNationality`, `passengerCountryOfResidence`) and has `beddingPreference` in the wrong position (before `passengersDetails` instead of after). Test 1 was fixed by commit `befd9995` and serves as the correct reference template. These XML bugs must be fixed before any test can produce a real PASS.

Phase 11 has two clear work streams: (1) surgery on DotwCertify to replace WARN→auto-PASS with WARN→SKIP state and fix the confirmbooking XML in tests 2–20, and (2) running the corrected tests against an environment that actually has hotel inventory — either finding a different sandbox city/date combination, or switching to production credentials.

**Primary recommendation:** Fix the WARN-pattern (SKIP not PASS), fix confirmbooking XML for tests 2–20 using Test 1 as template, then attempt a real run against sandbox with multiple cities/dates to find inventory. If sandbox remains empty, run against production with a real hotel.

---

## Standard Stack

### Core (already in place — no new installs needed)

| Component | Version | Purpose | Notes |
|-----------|---------|---------|-------|
| `DotwCertify.php` | current | Artisan command running all 20 tests | Lives at `app/Console/Commands/DotwCertify.php` |
| `DotwService.php` | current | Core DOTW XML API service (~1,544 lines) | All 23 DOTW commands implemented |
| `config/dotw.php` | current | DOTW credentials + endpoint config | `dev_mode=true` → xmldev, `dev_mode=false` → production |
| SKILL.md | rewritten | DOTW V4 API reference (being rewritten by user) | `/home/soudshoja/.claude/skills/DOTWV4/SKILL.md` |

**No new packages required.** This is entirely a code-surgery and test-execution phase.

### DOTW Internal Codes (confirmed from real Test 1 execution)

| Code | Meaning | Used In |
|------|---------|---------|
| `769` | KWD (Kuwaiti Dinar) | currency field in all search/booking requests |
| `364` | Dubai, UAE | city filter in searchhotels |
| `6` | UAE | country code |
| `66` | Kuwait | passengerNationality + passengerCountryOfResidence |
| `-1` | Any rate basis | rateBasis in searchhotels + getrooms (confirmed working) |

---

## Architecture Patterns

### How DotwCertify Is Structured

```
DotwCertify::handle()
├── option('--currencies') → getAvailableCurrencies() → exit
├── option('--countries')  → getServingCountries() → exit
├── option('--cities=X')   → getServingCitiesForCountry() → exit
└── foreach testsToRun → runTestN()
    └── printSummary()
```

Each `runTestN()` method:
1. `startTest(N, title)` — logs header
2. Executes API steps with `$this->post($xml, label)` calls
3. On WARN path: `$this->warn(msg)` + `$this->log(...)` + `$this->endTest(N, true)` — THIS IS THE BUG
4. On real path: multiple steps → `endTest(N, true/false)` based on actual result
5. `endTest(N, bool)` → stores in `$this->results[$num]`

### The WARN Pattern (current — wrong)

```php
// PATTERN THAT MUST CHANGE (appears ~35 times across tests 1-20):
$hotels = $response->hotels->hotel ?? null;
if (! $hotels || count($hotels) === 0) {
    $this->warn('Xa: No hotels in dev environment — documenting X logic');
    $this->log('  VERIFICATION: ...');
    $this->endTest(X, true);   // <-- BUG: records PASS
    return;
}
```

### The SKIP State (to be added)

`endTest()` currently only accepts `bool $passed`. A third state `null` or a new `skipTest()` method is needed:

```php
// Option A: Add skipTest() method
private function skipTest(int $num, string $reason): void
{
    $this->results[$num] = null;  // null = SKIP
    $this->log("  RESULT: ⏭ SKIP — {$reason}");
    $this->warn("  ⏭ Test {$num} SKIPPED: {$reason}");
}

// Option B: Change endTest signature
private function endTest(int $num, ?bool $passed, string $reason = ''): void
```

Option A (separate method) is cleaner — avoids changing call sites for the true PASS/FAIL cases.

### printSummary() Update Required

Must handle the null/SKIP state in `$this->results`:

```php
foreach ($this->results as $num => $result) {
    if ($result === null) {
        $icon = '⏭ SKIP';
        $skipped++;
    } elseif ($result) {
        $icon = '✔ PASS';
        $passed++;
    } else {
        $icon = '✘ FAIL';
        $failed++;
    }
    $this->log("  Test {$num}: {$icon}");
}
$this->log("  Total: {$total} | Passed: {$passed} | Failed: {$failed} | Skipped: {$skipped}");
```

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| DOTW XML construction | Custom XML builder | Heredoc strings (already in DotwCertify) | Pattern already proven in Test 1 |
| HTTP posting | Custom HTTP client | `Illuminate\Support\Facades\Http` (already used) | Already wired in DotwCertify |
| XML parsing | Custom parser | `SimpleXMLElement` (already used) | Already proven throughout DotwCertify |
| Inventory discovery | New command | `--countries` + `--cities=X` flags (already built) | Use existing helpers to find live inventory |

---

## Common Pitfalls

### Pitfall 1: confirmbooking XML Field Order and Missing Fields (Tests 2–20)

**What goes wrong:** Tests 2–20 confirmbooking XML is missing `extraBed`, `passengerNationality`, `passengerCountryOfResidence` and has `beddingPreference` in the wrong position. When real hotels become available, these tests will fail with DOTW XML validation errors.

**Why it happens:** The fix in commit `befd9995` was applied to Test 1 only. Tests 2–20 were never exercised because the WARN pattern short-circuited them.

**The correct order (per official DOTW XSD, verified in Test 1 fix):**
```xml
<room runno="0">
    <roomTypeCode>...</roomTypeCode>
    <selectedRateBasis>...</selectedRateBasis>
    <allocationDetails>...</allocationDetails>
    <adultsCode>2</adultsCode>
    <actualAdults>2</actualAdults>
    <children no="0"></children>
    <actualChildren no="0"></actualChildren>
    <extraBed>0</extraBed>                           <!-- MANDATORY, must be here -->
    <passengerNationality>66</passengerNationality>   <!-- MANDATORY, must be here -->
    <passengerCountryOfResidence>66</passengerCountryOfResidence> <!-- MANDATORY -->
    <passengersDetails>
        <passenger leading="yes">...</passenger>
    </passengersDetails>
    <specialRequests count="0"></specialRequests>
    <beddingPreference>0</beddingPreference>          <!-- LAST, after passengersDetails -->
</room>
```

**Tests affected:** 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14, 15, 16, 17, 18, 19, 20
(Test 12 tests gzip headers only — no confirmbooking; Test 14 has partial fix for changedOccupancy)

**Warning signs:** DOTW returns `<successful>FALSE</successful>` with an error element when element order is wrong.

### Pitfall 2: Sandbox Has No Hotel Inventory

**What goes wrong:** `xmldev.dotwconnect.com` sandbox returns 0 hotels for Dubai (city=364) with standard dates. This is a DOTW sandbox limitation, not a bug in the code.

**Why it happens:** DOTW's development sandbox has limited/no hotel data loaded. Only when a specific city/date combination has test data does it return results.

**How to handle:**
- Try multiple cities: run `php artisan dotw:certify --countries` to see which countries have hotels, then `--cities=COUNTRY_CODE` for city codes
- Try different date ranges (further out — 60, 90, 120 days)
- If sandbox is empty across all cities, switch `config/dotw.php` `dev_mode` to `false` and use production credentials with real hotels (requires caution — real bookings cost money)
- For certification submission, DOTW themselves may provide a test city code with guaranteed inventory

**Warning signs:** All tests hitting WARN path even after the WARN→SKIP fix.

### Pitfall 3: The `results` Array Only Tracks Tests That Ran

**What goes wrong:** `printSummary()` iterates `$this->results` — tests that were skipped via `return` before `endTest()` are not in `$this->results` at all, creating a gap.

**Why it happens:** WARN path calls `return` after `endTest(N, true)`. But if a test fails an early assertion before reaching the WARN check, it also returns without calling `endTest()`.

**How to avoid:** The new `skipTest()` method must be called in the WARN path so the test appears in results as SKIP, not absent. Verify that `printSummary()` loops `range(1, 20)` rather than `$this->results` keys to catch unreported tests.

### Pitfall 4: Test 2 confirmbooking beddingPreference Before passengersDetails

**What goes wrong:** In Test 2 (and Tests 3, 4, 5, 6, 7, 8, 9, etc.), `<beddingPreference>` appears immediately after `<actualChildren>` — before `<passengersDetails>`. The official XSD requires `beddingPreference` to be the LAST element inside `<room>`, after `passengersDetails` and `specialRequests`.

**Evidence:** Test 1 (fixed by `befd9995`) has correct order. Tests 2–20 have `beddingPreference` before `passengersDetails`.

### Pitfall 5: Test 4 Multi-Room Booking — Room-Level Passenger Counts

**What goes wrong:** Test 4 books 2 rooms (single + double). The WARN path is hit very early (line 1302). When real data is available, the confirmbooking XML must have two `<room>` elements each with their own passenger lists. Need to verify each room has correct passenger count for its room type (single=1 adult, double=2 adults).

**How to avoid:** Use Test 1 as template for each room, adapting adultsCode per room type.

### Pitfall 6: Cancel Tests (5, 6, 7) Depend on Prior Booking Codes

**What goes wrong:** Tests 5, 6, 7 cancel bookings made earlier in the same run. They retrieve booking codes from `$this->state['testX_booking_code']`. If tests 5/6/7 run standalone (`--test=5`) without first running the booking test, `$this->state` will be empty.

**How to avoid:** Always run the full sequence when testing cancellation flows, OR add booking code override options like `--booking-code=X`.

---

## Code Examples

### Correct confirmbooking Room XML (Test 1 — verified working)

```php
// Source: DotwCertify.php lines 327-345 (commit befd9995)
// This is the reference template for all tests
$confirmXml = $this->buildRequest('confirmbooking', '
    <bookingDetails>
        <fromDate>'.$fromDate.'</fromDate>
        <toDate>'.$toDate.'</toDate>
        <currency>769</currency>
        <productId>'.$hotelId.'</productId>
        <sendCommunicationTo>test@citycommerce.group</sendCommunicationTo>
        <customerReference>CERT-TEST-001</customerReference>
        <rooms no="1">
            <room runno="0">
                <roomTypeCode>'.$browseRoomTypeCode.'</roomTypeCode>
                <selectedRateBasis>'.$browseRateBasisId.'</selectedRateBasis>
                <allocationDetails>'.htmlspecialchars($blockAllocation).'</allocationDetails>
                <adultsCode>2</adultsCode>
                <actualAdults>2</actualAdults>
                <children no="0"></children>
                <actualChildren no="0"></actualChildren>
                <extraBed>0</extraBed>
                <passengerNationality>66</passengerNationality>
                <passengerCountryOfResidence>66</passengerCountryOfResidence>
                <passengersDetails>
                    <passenger leading="yes">
                        <salutation>1</salutation>
                        <firstName>Soud</firstName>
                        <lastName>Shoja</lastName>
                    </passenger>
                </passengersDetails>
                <specialRequests count="0"></specialRequests>
                <beddingPreference>0</beddingPreference>
            </room>
        </rooms>
    </bookingDetails>
');
```

### New skipTest() Method

```php
// Add alongside endTest() in DotwCertify.php
private function skipTest(int $num, string $reason): void
{
    $this->results[$num] = null;  // null = SKIP (distinct from true=PASS, false=FAIL)
    $this->log("  RESULT: ⏭ SKIP — {$reason}");
    $this->warn("  ⏭ Test {$num} SKIPPED: {$reason}");
}
```

### Updated WARN Pattern (how each test should change)

```php
// BEFORE (incorrect — records PASS):
if (! $hotels || count($hotels) === 0) {
    $this->warn('2a: No hotels in dev environment — documenting child booking flow logic');
    $this->log('  VERIFICATION: ...');
    $this->endTest(2, true);
    return;
}

// AFTER (correct — records SKIP):
if (! $hotels || count($hotels) === 0) {
    $this->skipTest(2, 'No hotel inventory in this environment — run against production or use --inventory-city');
    return;
}
```

### Updated printSummary()

```php
private function printSummary(): void
{
    $this->logNewline();
    $this->log('═══════════════════════════════════════════════════════════════');
    $this->log('  CERTIFICATION TEST SUMMARY');
    $this->log('═══════════════════════════════════════════════════════════════');
    $this->info('');
    $this->info('═══════════════ SUMMARY ═══════════════');

    $passed = 0;
    $failed = 0;
    $skipped = 0;
    foreach (range(1, 20) as $num) {
        if (! isset($this->results[$num])) {
            $icon = '? NOT RUN';
        } elseif ($this->results[$num] === null) {
            $icon = '⏭ SKIP';
            $skipped++;
        } elseif ($this->results[$num]) {
            $icon = '✔ PASS';
            $passed++;
        } else {
            $icon = '✘ FAIL';
            $failed++;
        }
        $this->log("  Test {$num}: {$icon}");
        $result = $this->results[$num] ?? '?';
        $result ? $passed++ : ($result === false ? $failed++ : 0);
    }

    $total = $passed + $failed + $skipped;
    $this->log('─────────────────────────────────────────────');
    $this->log("  Total: {$total} | Passed: {$passed} | Failed: {$failed} | Skipped: {$skipped}");
    $this->log('  Log file: '.$this->logFile);
    $this->log('═══════════════════════════════════════════════════════════════');

    $this->info("Passed: {$passed}/{$total} | Skipped: {$skipped}");
    $this->info('Log saved to: '.$this->logFile);
}
```

---

## Phase Requirements (Proposed — New for Phase 11)

Phase 11 needs new CERT-REAL requirements since CERT-01..20 were marked complete (incorrectly) in Phase 10. The planner should define these as the actual acceptance criteria:

| Proposed ID | Behavior |
|-------------|----------|
| REAL-01 | `endTest(N, true)` is NEVER called when `count($hotels) === 0`; instead `skipTest(N, reason)` is called — SKIP not PASS |
| REAL-02 | `printSummary()` reports PASS / FAIL / SKIP / NOT RUN with separate counts |
| REAL-03 | Tests 2–20 confirmbooking XML has: extraBed, passengerNationality, passengerCountryOfResidence in correct XSD order; beddingPreference is last |
| REAL-04 | At least 1 test (minimum Test 1) executes a complete real booking flow (searchhotels → getrooms browse → getrooms block → confirmbooking) and records PASS |
| REAL-05 | At minimum, all WARN-pattern tests are changed to SKIP; ideally hotel inventory is found and tests 2–20 also execute real flows |

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| WARN→auto-PASS (Phase 10) | WARN→SKIP (Phase 11) | Phase 11 | Certification log is honest |
| Test 1 confirmbooking had wrong element order | Correct order per XSD | commit befd9995 | Tests 2–20 still have the old wrong order |
| Summary only shows PASS/FAIL | Summary shows PASS/FAIL/SKIP/NOT RUN | Phase 11 | Accurate count for DOTW submission |

**Deprecated/wrong:**
- `endTest(N, true)` when hotels is empty — replaced by `skipTest(N, reason)`
- `beddingPreference` before `passengersDetails` in Tests 2–20 confirmbooking XML

---

## Open Questions

1. **Does DOTW sandbox ever have hotel inventory?**
   - What we know: Test 1 did get hotels on sandbox at some point (the test actually ran). City 364 (Dubai) was used.
   - What's unclear: Whether this was a one-time occurrence or sandbox has intermittent data. Different dates may have data.
   - Recommendation: First attempt is to run with city=364 and dates further out (90-180 days). If empty, try `--countries` to find a country with data, then `--cities=COUNTRY_CODE` to find active city codes. Last resort: production credentials.

2. **Do Tests 4, 6, 7 (multi-room, cancel) work with real data?**
   - What we know: Test 4 (2 rooms) and Tests 5-7 (cancel) have full flow code written. They've never actually executed.
   - What's unclear: Whether the multi-room blocking and cancellation code is correct when real data is returned.
   - Recommendation: Run these after single-room tests pass. They may need debugging.

3. **Do Tests 14–20 (changedOccupancy, APR, restricted, minStay, specialRequests, propertyFees) have real test paths?**
   - What we know: Tests 14–20 have WARN patterns that skip when no hotels. The real code paths exist but were never executed.
   - What's unclear: These tests search for specific rate features (APR, changedOccupancy, etc.) — finding a real hotel with these specific features may be difficult.
   - Recommendation: Tests 14–20 may remain SKIP if the environment doesn't have hotels with those specific rate types. This is acceptable for Phase 11 — fix the WARN→SKIP change and fix XML correctness. Getting Tests 1–4 to PASS is the primary goal.

4. **Is there a CONTEXT.md with user locked decisions for this phase?**
   - What we know: No CONTEXT.md exists for Phase 11 (confirmed by checking the phase directory).
   - What's unclear: User may have strong opinions about whether to use sandbox vs production.
   - Recommendation: Planner should make sandbox-first the default, with production as explicit opt-in requiring `dev_mode=false` in config.

---

## Implementation Map

This section maps the work to specific file locations for the planner:

### File: `app/Console/Commands/DotwCertify.php`

**Change 1 — Add `skipTest()` method** (~line 3503, alongside `endTest()`):
- New private method: `skipTest(int $num, string $reason): void`
- Sets `$this->results[$num] = null`
- Logs "⏭ SKIP" verdict

**Change 2 — Replace all WARN→PASS patterns with WARN→SKIP** (~35 occurrences):
- Lines: 207-213, 409-415, 614-619, 819-823, 921-925, 1056-1060, 1161-1165, 1301-1305, 1565-1569, 1803-1807, 2089-2093, 2326-2330, 2576-2580, 2696-2700, 2925-2929, 3047-3051, 3152-3156, 3343-3347
- Change: `$this->endTest(N, true)` → `$this->skipTest(N, 'No hotel inventory...')`

**Change 3 — Fix confirmbooking XML in Tests 2–20**:
- Affected tests: 2 (lines ~515-557), 3 (lines ~718-767), 4 (lines ~1400-1520), 5 (lines ~1600-1750), 6, 7, 8, 9, 11, 13, 14, 15, 16, 17, 18, 19, 20
- For each: add `<extraBed>0</extraBed>`, `<passengerNationality>66</passengerNationality>`, `<passengerCountryOfResidence>66</passengerCountryOfResidence>` in correct position; move `<beddingPreference>` to after `</passengersDetails>`

**Change 4 — Update `printSummary()`** (lines ~3558-3583):
- Add `$skipped` counter
- Handle `null` result as SKIP
- Report PASS/FAIL/SKIP/NOT RUN per test

### File: `config/dotw.php` (no change required for SKIP fix)

The switch from sandbox to production is controlled by `dev_mode` — no code changes needed, just a config/env change when the time comes.

---

## Sources

### Primary (HIGH confidence)
- Direct source inspection of `app/Console/Commands/DotwCertify.php` — WARN pattern at lines 207-213, 409-415, 614-619, 819-823, 921-925, 1056-1060, 1161-1165, 1301-1305, 1565-1569, 1803-1807, 2089-2093, 2326-2330, 2576-2580, 2696-2700, 2925-2929, 3047-3051, 3152-3156, 3343-3347
- Test 1 confirmbooking template (lines 317-349) — verified working, reference implementation
- Test 2 confirmbooking (lines 515-557) — confirmed missing extraBed, passengerNationality, passengerCountryOfResidence; beddingPreference in wrong position
- `endTest()` / `startTest()` / `printSummary()` implementation (lines 3488-3583)
- MEMORY.md project history — fake-pass discovery documented, known SKILL.md errors listed
- `/home/soudshoja/.claude/skills/DOTWV4/SKILL.md` — DOTW V4 API reference (rewritten)
- commit `befd9995` context — confirmed correct confirmbooking element order per official DOTW XSD

### Secondary (MEDIUM confidence)
- DOTW sandbox behavior (city 364, currency 769) — from Test 1 execution history in MEMORY.md
- DOTW internal codes (769=KWD, 364=Dubai, 66=Kuwait) — confirmed from real API calls per MEMORY.md

---

## Metadata

**Confidence breakdown:**
- DotwCertify code structure: HIGH — read directly from source
- WARN pattern locations: HIGH — grep confirmed ~35 occurrences
- confirmbooking XML bugs in tests 2–20: HIGH — verified by reading test 2 and 3 source and comparing to test 1 (fixed)
- SKIP state implementation pattern: HIGH — straightforward PHP/Laravel console pattern
- Sandbox inventory availability: LOW — unpredictable, empirical

**Research date:** 2026-02-26
**Valid until:** 2026-03-28 (code-level findings don't expire; sandbox behavior may change)
