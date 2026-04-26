# dotw-cli

Standalone DOTW hotel booking CLI for the Akeed AI agent. Exposes the full DOTW V4 booking
lifecycle as shell commands with `--json` output and structured exit codes.

**Not a Laravel package.** Zero framework coupling — runs on any PHP 8.2+ system.

---

## Installation

```bash
cd packages/dotw-cli
composer install
chmod +x bin/dotw
```

### PHAR (portable single-file)

```bash
# Install box globally
composer global require box-project/box

# Build the PHAR (run from packages/dotw-cli/)
php -d phar.readonly=0 $(composer global config home)/vendor/bin/box compile

# Verify size < 8MB
ls -lh dotw-cli.phar

# Test portability
php dotw-cli.phar list
```

---

## Configuration

On first run, `~/.dotw-cli/config.yaml` is written with a starter template. Edit it:

```yaml
default_profile: akeed

profiles:
  akeed:
    username: "YOUR_DOTW_USERNAME"
    password: "YOUR_DOTW_PASSWORD_PLAINTEXT"   # CLI MD5-hashes it on every request
    company_code: "YOUR_COMPANY_CODE"
    endpoint: "https://xml.dotwconnect.com/2018-09-01/Dotw.asmx"
    timeout: 25
    currency: 769           # KWD (Kuwait Dinar)
    nationality: 66         # Kuwait
    residence: 66
    myfatoorah_api_key: ""
    myfatoorah_base_url: "https://api.myfatoorah.com/v2"
    myfatoorah_payment_method_id: 2
    b2b_credit_balance: 5000.0
    markup_percent: 20.0

state_db: "~/.dotw-cli/state.db"
```

Secure the file:
```bash
chmod 600 ~/.dotw-cli/config.yaml
```

Multiple company profiles: add additional entries under `profiles:` and switch with `--profile=<name>`.

---

## Commands

All commands accept:
- `--json` — emit machine-readable JSON to stdout (errors also go to stderr)
- `--profile=<name>` — select a config profile (default: value of `default_profile`)

---

### dotw:search

Search hotels by city and dates.

```bash
bin/dotw dotw:search --city=364 --from=2026-05-01 --to=2026-05-02 [--adults=2] [--children=0] [--json]
```

**Arguments / options:**

| Flag | Required | Description |
|------|----------|-------------|
| `--city` | yes | DOTW city code (e.g. 364 = Kuwait City) |
| `--from` | yes | Check-in date (YYYY-MM-DD) |
| `--to` | yes | Check-out date (YYYY-MM-DD) |
| `--adults` | no | Number of adults (default: 2) |
| `--children` | no | Number of children (default: 0) |

**JSON output schema:** `docs/json-schema/search.json`

---

### dotw:hotels:show

Show hotel details and all available room rates for given dates.

```bash
bin/dotw dotw:hotels:show <hotel_id> --from=2026-05-01 --to=2026-05-02 [--adults=2] [--json]
```

**JSON output schema:** `docs/json-schema/hotels-show.json`

---

### dotw:rooms:browse

Browse available room rates for a hotel without locking inventory. Returns a numbered list
with an `index` field — pass index to `dotw:prebook`.

```bash
bin/dotw dotw:rooms:browse <hotel_id> --from=2026-05-01 --to=2026-05-02 [--adults=2] [--json]
```

**JSON output schema:** `docs/json-schema/rooms-browse.json`

---

### dotw:prebook

Lock a rate for 3 minutes (DOTW dual-getRooms pattern: browse then block). Returns a
`prebook_key` that must be passed to `dotw:confirm` before `expires_at`.

```bash
bin/dotw dotw:prebook <hotel_id> <option_index> --from=2026-05-01 --to=2026-05-02 [--adults=2] [--json]
```

**Arguments:**

| Argument | Description |
|----------|-------------|
| `hotel_id` | DOTW hotel ID (from dotw:search output) |
| `option_index` | Zero-based index from dotw:rooms:browse output |

**JSON output schema:** `docs/json-schema/prebook.json`

Returns `prebook_key` — pass to `dotw:confirm` **within 3 minutes** (see `expires_at`).

---

### dotw:confirm

Confirm a prebooked rate. Two payment tracks:

**B2B** (direct credit-line debit, confirms immediately):
```bash
bin/dotw dotw:confirm <prebook_key> --track=b2b [--guest-name="Soud Shoja"] [--guest-email=guest@example.com] [--json]
```

**B2C** (MyFatoorah payment link, polls every 5s for up to 5 minutes):
```bash
bin/dotw dotw:confirm <prebook_key> --track=b2c [--json]
```

**JSON output schema:** `docs/json-schema/confirm.json`

Returns `booking_ref` — use with `dotw:voucher` and `dotw:cancel:*`.

---

### dotw:voucher

Generate a bilingual Arabic/English hotel voucher.

```bash
bin/dotw dotw:voucher <booking_ref> [--pdf] [--json]
```

Text printed to stdout by default. `--pdf` writes to `~/.dotw-cli/vouchers/{booking_ref}.pdf`.

**JSON output schema:** `docs/json-schema/voucher.json`

---

### dotw:cancel:preview

Preview cancellation penalty without executing the cancellation. Safe to call multiple times.

```bash
bin/dotw dotw:cancel:preview <booking_ref> [--json]
```

Multi-room bookings: fires one DOTW API call per booking code (CERT-13 pattern).
Pass `data.total_charge` as `--penalty` to `dotw:cancel:execute`.

**JSON output schema:** `docs/json-schema/cancel-preview.json`

---

### dotw:cancel:execute

Execute cancellation after reviewing the penalty.

```bash
bin/dotw dotw:cancel:execute <booking_ref> --penalty=<amount_from_preview> [--json]
```

**JSON output schema:** `docs/json-schema/cancel-execute.json`

---

### dotw:accounting:show

Show the chart-of-accounts ledger with debit/credit/balance per account.

```bash
bin/dotw dotw:accounting:show [--booking=<ref>] [--from=2026-01-01] [--to=2026-12-31] [--json]
```

**Options:**

| Flag | Description |
|------|-------------|
| `--booking` | Filter to a single booking reference |
| `--from` | Start date filter (YYYY-MM-DD) |
| `--to` | End date filter (YYYY-MM-DD) |

**JSON output schema:** `docs/json-schema/accounting-show.json`

---

## Exit Codes

| Code | Meaning | AI Retry Strategy |
|------|---------|-------------------|
| 0 | Success | N/A |
| 1 | Input / validation error | Fix inputs and retry immediately |
| 2 | DOTW API error | Retry after 10s delay (max 3 attempts) |
| 3 | Payment / financial error | Requires human intervention |
| 4 | Internal / unexpected error | Check stderr output |

---

## Error Codes

All errors emit `[DOTW_E_CODE] message` to stderr. In `--json` mode the full error object is
also written to stdout as:

```json
{
  "status": "error",
  "error_code": "DOTW_E_PREBOOK_EXPIRED",
  "message": "The rate lock has expired. Please run dotw:prebook again.",
  "context": { "prebook_key": "abc123", "expired_at": "2026-05-01T12:03:00Z" }
}
```

**Canonical error schema:** `docs/json-schema/error.json`

| Error Code | Exit | Meaning | Action |
|------------|------|---------|--------|
| `DOTW_E_INPUT` | 1 | Missing or invalid argument | Fix argument and retry |
| `DOTW_E_API` | 2 | DOTW XML API returned error response | Retry after 10s (max 3x) |
| `DOTW_E_NO_INVENTORY` | 2 | No rooms available for hotel/dates | Search other hotels or dates |
| `DOTW_E_BLOCK_FAILED` | 2 | Rate block returned no allocationDetails | Retry prebook |
| `DOTW_E_PREBOOK_EXPIRED` | 1 | 3-minute rate lock has expired | Re-run dotw:prebook |
| `DOTW_E_PREBOOK_NOT_FOUND` | 1 | prebook_key not found in local state | Re-run dotw:prebook |
| `DOTW_E_BOOKING_NOT_FOUND` | 1 | booking_ref not found in local state | Verify booking_ref |
| `DOTW_E_ALREADY_CANCELLED` | 1 | Booking is already cancelled | No action needed |
| `DOTW_E_PAYMENT` | 3 | MyFatoorah payment error or 5-min polling timeout | Human intervention |
| `DOTW_E_PDF` | 4 | mpdf PDF generation failed | Check disk space and permissions |
| `DOTW_E_INTERNAL` | 4 | Unexpected internal error | Check stderr; file a bug |

---

## AI Tool Spec

This section defines the JSON contracts for every command, enabling AI agents to call the CLI
as a deterministic tool. JSON schemas live in `docs/json-schema/`. The canonical error
envelope is in `docs/json-schema/error.json`.

### Booking workflow for AI agents

```
1. dotw:search --city=<city_code> --from=<YYYY-MM-DD> --to=<YYYY-MM-DD> --adults=<n> --json
   → data.hotels[].hotel_id  (pass to step 2)
   → data.hotels[].min_fare  (show to user)

2. dotw:rooms:browse <hotel_id> --from=<date> --to=<date> --adults=<n> --json
   → data.rooms[N].index     (pass as option_index to step 3)
   → data.rooms[N].room_name, data.rooms[N].total_fare  (show to user)

3. dotw:prebook <hotel_id> <option_index> --from=<date> --to=<date> --json
   → data.prebook_key  (pass to step 4 — MUST confirm before data.expires_at)
   → data.expires_at   (ISO 8601; confirm within 3 minutes)

4. dotw:confirm <prebook_key> --track=b2b --guest-name="..." --json
   → data.booking_ref  (persist; use in steps 5-7)
   → data.status = "confirmed"

5. dotw:voucher <booking_ref> --json
   → data.voucher_text  (deliver to customer)

6. (when customer requests cancellation)
   dotw:cancel:preview <booking_ref> --json
   → data.total_charge  (show penalty to customer)
   → data.currency

7. (after customer confirms penalty)
   dotw:cancel:execute <booking_ref> --penalty=<data.total_charge> --json
   → data.status = "cancelled"
   → data.refund_due
```

---

### Tool: dotw:search

```json
{
  "name": "dotw_search",
  "description": "Search available hotels in a city for given dates. Returns list of hotels with minimum fare. Use hotel_id from results with dotw_rooms_browse.",
  "parameters": {
    "type": "object",
    "required": ["city", "from", "to"],
    "properties": {
      "city":     { "type": "integer", "description": "DOTW city code (e.g. 364 = Kuwait City, 348 = Dubai)" },
      "from":     { "type": "string",  "format": "date", "description": "Check-in date YYYY-MM-DD" },
      "to":       { "type": "string",  "format": "date", "description": "Check-out date YYYY-MM-DD" },
      "adults":   { "type": "integer", "default": 2 },
      "children": { "type": "integer", "default": 0 },
      "profile":  { "type": "string",  "description": "Config profile name (optional)" }
    }
  },
  "command_template": "bin/dotw dotw:search --city={city} --from={from} --to={to} --adults={adults} --json",
  "returns": {
    "$ref": "docs/json-schema/search.json"
  },
  "errors": [
    { "code": "DOTW_E_INPUT",        "exit": 1, "when": "Missing required flag (city/from/to)" },
    { "code": "DOTW_E_API",          "exit": 2, "when": "DOTW returned XML error" },
    { "code": "DOTW_E_NO_INVENTORY", "exit": 2, "when": "No hotels found for city/dates" }
  ]
}
```

---

### Tool: dotw:rooms:browse

```json
{
  "name": "dotw_rooms_browse",
  "description": "Browse available room types and rates for a specific hotel. Returns indexed list — use index N with dotw_prebook.",
  "parameters": {
    "type": "object",
    "required": ["hotel_id", "from", "to"],
    "properties": {
      "hotel_id": { "type": "string",  "description": "DOTW hotel ID from dotw_search output" },
      "from":     { "type": "string",  "format": "date" },
      "to":       { "type": "string",  "format": "date" },
      "adults":   { "type": "integer", "default": 2 },
      "children": { "type": "integer", "default": 0 }
    }
  },
  "command_template": "bin/dotw dotw:rooms:browse {hotel_id} --from={from} --to={to} --adults={adults} --json",
  "returns": {
    "$ref": "docs/json-schema/rooms-browse.json"
  },
  "errors": [
    { "code": "DOTW_E_NO_INVENTORY", "exit": 2, "when": "Hotel has no available rooms for dates" },
    { "code": "DOTW_E_API",          "exit": 2, "when": "DOTW API error" }
  ]
}
```

---

### Tool: dotw:prebook

```json
{
  "name": "dotw_prebook",
  "description": "Lock a room rate for 3 minutes (DOTW dual-getRooms pattern). Returns prebook_key — pass to dotw_confirm BEFORE expires_at.",
  "parameters": {
    "type": "object",
    "required": ["hotel_id", "option_index", "from", "to"],
    "properties": {
      "hotel_id":     { "type": "string",  "description": "DOTW hotel ID" },
      "option_index": { "type": "integer", "description": "Zero-based index from dotw_rooms_browse output" },
      "from":         { "type": "string",  "format": "date" },
      "to":           { "type": "string",  "format": "date" },
      "adults":       { "type": "integer", "default": 2 }
    }
  },
  "command_template": "bin/dotw dotw:prebook {hotel_id} {option_index} --from={from} --to={to} --adults={adults} --json",
  "returns": {
    "$ref": "docs/json-schema/prebook.json"
  },
  "key_fields": {
    "data.prebook_key": "Pass as first argument to dotw_confirm",
    "data.expires_at":  "ISO 8601 UTC; must call dotw_confirm before this time",
    "data.total_fare":  "Raw DOTW cost (B2B price)",
    "data.markup_fare": "B2C selling price with markup applied"
  },
  "errors": [
    { "code": "DOTW_E_NO_INVENTORY",  "exit": 2, "when": "Hotel has no available rooms" },
    { "code": "DOTW_E_BLOCK_FAILED",  "exit": 2, "when": "Rate block did not return allocationDetails — retry" },
    { "code": "DOTW_E_API",           "exit": 2, "when": "DOTW API error" }
  ]
}
```

---

### Tool: dotw:confirm

```json
{
  "name": "dotw_confirm",
  "description": "Confirm a prebooking. B2B: immediate credit-line debit. B2C: MyFatoorah payment link + polling. Returns booking_ref.",
  "parameters": {
    "type": "object",
    "required": ["prebook_key", "track"],
    "properties": {
      "prebook_key": { "type": "string", "description": "Value of data.prebook_key from dotw_prebook" },
      "track":       { "type": "string", "enum": ["b2b", "b2c"], "description": "b2b = credit line; b2c = MyFatoorah" },
      "guest_name":  { "type": "string", "description": "Guest full name (B2B). Format: 'Firstname Lastname'" },
      "guest_email": { "type": "string", "format": "email" }
    }
  },
  "command_template": "bin/dotw dotw:confirm {prebook_key} --track={track} --guest-name=\"{guest_name}\" --json",
  "returns": {
    "$ref": "docs/json-schema/confirm.json"
  },
  "key_fields": {
    "data.booking_ref": "Persist this — use with dotw:voucher and dotw:cancel:preview",
    "data.status":      "Always 'confirmed' on success"
  },
  "errors": [
    { "code": "DOTW_E_PREBOOK_EXPIRED",   "exit": 1, "when": "3-minute lock expired — re-run dotw_prebook" },
    { "code": "DOTW_E_PREBOOK_NOT_FOUND", "exit": 1, "when": "prebook_key not in local state" },
    { "code": "DOTW_E_PAYMENT",           "exit": 3, "when": "MyFatoorah error or 5-min polling timeout (B2C only)" },
    { "code": "DOTW_E_API",               "exit": 2, "when": "DOTW confirmbooking API error" }
  ]
}
```

---

### Tool: dotw:voucher

```json
{
  "name": "dotw_voucher",
  "description": "Generate bilingual Arabic/English hotel voucher for a confirmed booking.",
  "parameters": {
    "type": "object",
    "required": ["booking_ref"],
    "properties": {
      "booking_ref": { "type": "string", "description": "DOTW booking reference from dotw_confirm" },
      "pdf":         { "type": "boolean", "default": false, "description": "If true, also write PDF to ~/.dotw-cli/vouchers/{ref}.pdf" }
    }
  },
  "command_template": "bin/dotw dotw:voucher {booking_ref} --json",
  "returns": {
    "$ref": "docs/json-schema/voucher.json"
  },
  "key_fields": {
    "data.voucher_text": "Bilingual voucher text — send to customer via WhatsApp or email",
    "data.pdf_path":     "Absolute path to PDF (null unless --pdf flag used)"
  },
  "errors": [
    { "code": "DOTW_E_BOOKING_NOT_FOUND", "exit": 1, "when": "booking_ref not in local state" },
    { "code": "DOTW_E_PDF",              "exit": 4, "when": "mpdf PDF generation failed" }
  ]
}
```

---

### Tool: dotw:cancel:preview

```json
{
  "name": "dotw_cancel_preview",
  "description": "Preview cancellation penalty without executing. Safe to call multiple times. Returns total_charge — pass as --penalty to dotw_cancel_execute.",
  "parameters": {
    "type": "object",
    "required": ["booking_ref"],
    "properties": {
      "booking_ref": { "type": "string", "description": "DOTW booking reference" }
    }
  },
  "command_template": "bin/dotw dotw:cancel:preview {booking_ref} --json",
  "returns": {
    "$ref": "docs/json-schema/cancel-preview.json"
  },
  "key_fields": {
    "data.total_charge": "Total penalty — pass as --penalty to dotw_cancel_execute. 0 = free cancellation.",
    "data.currency":     "Currency code for the penalty amount"
  },
  "errors": [
    { "code": "DOTW_E_BOOKING_NOT_FOUND",  "exit": 1, "when": "booking_ref not in local state" },
    { "code": "DOTW_E_ALREADY_CANCELLED",  "exit": 1, "when": "Booking already cancelled" },
    { "code": "DOTW_E_API",               "exit": 2, "when": "DOTW cancelbooking API error" }
  ]
}
```

---

### Tool: dotw:cancel:execute

```json
{
  "name": "dotw_cancel_execute",
  "description": "Execute booking cancellation after customer confirms the penalty. IRREVERSIBLE.",
  "parameters": {
    "type": "object",
    "required": ["booking_ref", "penalty"],
    "properties": {
      "booking_ref": { "type": "string", "description": "DOTW booking reference" },
      "penalty":     { "type": "number", "description": "Penalty amount from data.total_charge in dotw_cancel_preview" }
    }
  },
  "command_template": "bin/dotw dotw:cancel:execute {booking_ref} --penalty={penalty} --json",
  "returns": {
    "$ref": "docs/json-schema/cancel-execute.json"
  },
  "key_fields": {
    "data.status":      "Always 'cancelled' on success",
    "data.refund_due":  "Amount to refund to customer (total_fare - penalty_applied)"
  },
  "errors": [
    { "code": "DOTW_E_BOOKING_NOT_FOUND",  "exit": 1, "when": "booking_ref not in local state" },
    { "code": "DOTW_E_ALREADY_CANCELLED",  "exit": 1, "when": "Already cancelled" },
    { "code": "DOTW_E_API",               "exit": 2, "when": "DOTW cancelbooking API error" }
  ]
}
```

---

### Tool: dotw:accounting:show

```json
{
  "name": "dotw_accounting_show",
  "description": "Show chart-of-accounts ledger. Filter by booking or date range. net_balance = agency profit.",
  "parameters": {
    "type": "object",
    "required": [],
    "properties": {
      "booking": { "type": "string", "description": "Filter to a single booking reference (optional)" },
      "from":    { "type": "string", "format": "date", "description": "Start date filter YYYY-MM-DD (optional)" },
      "to":      { "type": "string", "format": "date", "description": "End date filter YYYY-MM-DD (optional)" }
    }
  },
  "command_template": "bin/dotw dotw:accounting:show --json",
  "returns": {
    "$ref": "docs/json-schema/accounting-show.json"
  },
  "key_fields": {
    "data.net_balance": "Credits minus debits — positive value = agency profit",
    "data.ledger":      "Per-account summary (account_code, total_debit, total_credit, balance)",
    "data.entries":     "Individual journal entries (debit/credit rows)"
  },
  "errors": [
    { "code": "DOTW_E_INTERNAL", "exit": 4, "when": "SQLite error or unexpected failure" }
  ]
}
```

---

## Running Tests

```bash
# Unit tests only — no credentials, no network
./vendor/bin/phpunit --testsuite Unit

# Integration tests — requires DOTW sandbox credentials
export DOTW_CLI_USERNAME="your_username"
export DOTW_CLI_PASSWORD="your_password"
export DOTW_CLI_COMPANY_CODE="your_company_code"
./vendor/bin/phpunit --testsuite Integration

# Full end-to-end flow including cancel (destructive — creates and cancels a real sandbox booking)
export DOTW_CLI_CANCEL_AFTER_CONFIRM=1
./vendor/bin/phpunit tests/Integration/FullBookingFlowTest.php

# Syntax check all PHP files
find src/ tests/ -name "*.php" -exec php -l {} \;
```

---

## State Database

SQLite at `~/.dotw-cli/state.db`. Three tables:

| Table | Purpose |
|-------|---------|
| `prebooks` | Rate locks (3-minute expiry, prebook_key indexed) |
| `bookings` | Confirmed bookings with DOTW booking_ref, fare, status |
| `accounting_entries` | Double-entry journal (chart of accounts: 4100 Credit Line, 5100 Cost, etc.) |

---

## Extracting to a Standalone Repo

When Akeed launches as its own codebase:

```bash
# Option A: copy
cp -r packages/dotw-cli /path/to/akeed/dotw-cli

# Option B: git subtree
git subtree split --prefix=packages/dotw-cli -b dotw-cli-standalone
git push origin dotw-cli-standalone:main  # or new repo remote
```

No changes needed — zero Laravel coupling by design.

---

## Security Notes

- Config file should be `chmod 600` — contains plaintext DOTW password
- PHAR is not signed in this MVP version — verify SHA256 checksum before deploying to production
- Integration test credentials must be in env vars, not in code or config files committed to version control

---

## License

Proprietary — City Travelers / Alphia Ventures
