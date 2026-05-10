# Test 10: Passenger Name Restrictions — sanitization + min 2 / max 25 chars, no dupes

**Result:** ✔ PASS

## Request/Response Files


## Evidence

- 'John' → 'John' (4 chars) — VALID — Standard name — no change
- 'J' → 'J' (1 chars) — INVALID — Too short (1 char) — INVALID
- 'JohnAlexanderMaximilian123' → 'JohnAlexanderMaximilian' (23 chars) — VALID — Digits stripped; 23 chars → VALID (≤25)
- 'James Lee' → 'JamesLee' (8 chars) — VALID — Space stripped → "JamesLee" (8 chars) — VALID
- 'O'Brien' → 'OBrien' (6 chars) — VALID — Apostrophe stripped → "OBrien" (6 chars) — VALID
- '123' → '' (0 chars) — INVALID — All digits → empty after sanitize — INVALID
- All sanitization cases match expected output

