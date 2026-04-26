# dotw-xml-cli

Low-level DOTW XML pass-through CLI. One command per DOTW API method. Fully stateless.

**vs dotw-cli:** Use this for raw API access, debugging, or single DOTW calls. Use `dotw-cli` for full booking lifecycle flows.

---

## Install

```bash
cd packages/dotw-xml-cli && composer install && chmod +x bin/dotw-xml
```

## Configure

First run writes `~/.dotw-xml-cli/config.yaml`. Edit before use:

```yaml
default_profile: default
profiles:
  default:
    username: "YOUR_DOTW_USERNAME"
    password: "YOUR_DOTW_PASSWORD"
    company_code: "YOUR_COMPANY_CODE"
    source: 1
    product: hotel
    endpoint: "https://xml.dotwconnect.com/2018-09-01/Dotw.asmx"
    timeout: 25
    currency: 769
    nationality: 66
    residence: 66
```

Use `--profile=NAME` to switch profiles at runtime.

## Output Modes

| Flag | Output |
|------|--------|
| (default) | Pretty-printed XML |
| `--raw` | Unformatted XML exactly as received |
| `--json` | XML converted to JSON (best for AI agents) |

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success: DOTW returned TRUE |
| 1 | DOTW API error: successful=FALSE |
| 2 | HTTP or network error |
| 3 | Input validation error (missing option, invalid JSON) |
| 4 | Internal unexpected error |

---

## Command Reference

### `xml:get-all-countries`

Download all DOTW country reference data. Use to look up country codes for `--nationality` and `--residence` fields.

```bash
bin/dotw-xml xml:get-all-countries --json
```

### `xml:get-all-cities`

Download all DOTW city reference data. Use to find city codes for `xml:search-hotels`.

```bash
bin/dotw-xml xml:get-all-cities --json
```

### `xml:get-all-hotels`

Download hotel static data (name, address, star rating). Filter with `--city=CODE` or `--country=CODE`.

```bash
bin/dotw-xml xml:get-all-hotels --city=364 --json
```

### `xml:get-salutations-ids`

Download salutation codes (147=Mr, 148=Mrs, 149=Ms, 150=Miss).

```bash
bin/dotw-xml xml:get-salutations-ids --json
```

### `xml:search-hotels`

Search available hotels by city and dates.

| Option | Required | Description |
|--------|----------|-------------|
| `--city` | one of | DOTW city code |
| `--hotel` | one of | DOTW hotel ID |
| `--from` | yes | Check-in YYYY-MM-DD |
| `--to` | yes | Check-out YYYY-MM-DD |
| `--adults` | no | Adults count (default: 2) |
| `--children` | no | Children count (default: 0) |
| `--currency` | no | DOTW currency code (769=KWD) |
| `--nationality` | no | Nationality code |
| `--residence` | no | Residence code |
| `--rate-basis` | no | Rate basis, -1=all (default: -1) |

```bash
bin/dotw-xml xml:search-hotels --city=364 --from=2026-09-01 --to=2026-09-03 --adults=2 --json
```

### `xml:get-rooms`

Get room details. Two modes: browse (default) and blocking (`--block`).

| Option | Required | Description |
|--------|----------|-------------|
| `--hotel` | yes | DOTW hotel ID |
| `--from` | yes | Check-in YYYY-MM-DD |
| `--to` | yes | Check-out YYYY-MM-DD |
| `--adults` | no | Adults count (default: 2) |
| `--block` | no | Enable blocking mode |
| `--room-type` | with `--block` | roomTypeCode from browse |
| `--rate` | with `--block` | selectedRateBasis from browse |
| `--allocation` | with `--block` | allocationDetails from browse |

```bash
# Browse mode:
bin/dotw-xml xml:get-rooms --hotel=1013275 --from=2026-09-01 --to=2026-09-03

# Blocking mode (before confirm-booking):
bin/dotw-xml xml:get-rooms --hotel=1013275 --from=2026-09-01 --to=2026-09-03 \
  --block --room-type=DELUXE --rate=0 --allocation='ALLOC_STRING'
```

After blocking: verify `<status>checked</status>` before xml:confirm-booking.

### `xml:confirm-booking`

Confirm a hotel booking. Requires `allocationDetails` from a successful blocking xml:get-rooms (status=checked).

| Option | Required | Description |
|--------|----------|-------------|
| `--hotel` | yes | DOTW hotel ID |
| `--from` | yes | Check-in YYYY-MM-DD |
| `--to` | yes | Check-out YYYY-MM-DD |
| `--rooms-json` | yes | JSON array of room objects |
| `--currency` | no | DOTW currency code |
| `--email` | no | Customer email |
| `--reference` | no | Internal booking reference |

Salutation codes: 147=Mr, 148=Mrs, 149=Ms, 150=Miss.

```bash
bin/dotw-xml xml:confirm-booking \
  --hotel=1013275 --from=2026-09-01 --to=2026-09-03 \
  --rooms-json='[{"roomTypeCode":"DELUXE","selectedRateBasis":0,"allocationDetails":"ALLOC","adults":2,"nationality":66,"residence":66,"passengers":[{"salutation":147,"firstName":"John","lastName":"Doe","leading":true}]}]'
```

### `xml:cancel-booking`

Two-step cancellation. Always run `--confirm=no` first.

| Option | Required | Description |
|--------|----------|-------------|
| `--booking` | yes | DOTW booking reference |
| `--confirm` | yes | `no`=preview charges; `yes`=execute |
| `--penalty` | with confirm=yes | Charge from step 1 (use `<charge>` not `<formatted>`) |

```bash
# Step 1 - check charges (safe):
bin/dotw-xml xml:cancel-booking --booking=REF123 --confirm=no

# Step 2 - execute (irreversible):
bin/dotw-xml xml:cancel-booking --booking=REF123 --confirm=yes --penalty=0
```

### `xml:get-booking-details`

Retrieve full booking details: hotel, dates, passengers, status, `paymentGuaranteedBy`.

```bash
bin/dotw-xml xml:get-booking-details --booking=REF123 --json
```

### `xml:search-bookings`

Search booking history by date range or reference.

```bash
bin/dotw-xml xml:search-bookings --from=2026-01-01 --to=2026-12-31 --json
bin/dotw-xml xml:search-bookings --booking=REF123
```

### `xml:delete-itinerary`

Delete a DOTW itinerary. Check `<productsLeftOnItinerary>` in response.

```bash
bin/dotw-xml xml:delete-itinerary --itinerary=ITIN123
```

### `xml:book-itinerary`

Confirm all items in a CC-flow itinerary.

```bash
bin/dotw-xml xml:book-itinerary --itinerary=ITIN123
```

### `xml:raw`

Send a raw XML body to any DOTW method. Escape hatch for debugging.

```bash
bin/dotw-xml xml:raw --method=getallcountries --body='<return></return>' --json
bin/dotw-xml xml:raw --method=getrooms --body='<bookingDetails>...</bookingDetails>' --raw
```

---

## PHAR Build

```bash
# humbug/box is in require-dev:
composer install

# Build PHAR:
php -d phar.readonly=0 vendor/bin/box compile

# Result: build/dotw-xml.phar  (< 5MB)
```

---

## Running Tests

```bash
# Unit tests (no network):
vendor/bin/phpunit --testsuite=Unit

# Integration tests (requires DOTW_USERNAME):
DOTW_USERNAME=x DOTW_PASSWORD=y DOTW_COMPANY_CODE=z vendor/bin/phpunit --testsuite=Integration
```

Integration tests auto-skip when `DOTW_USERNAME` is not set.

---

## AI Tool Spec

OpenAI function-calling style definitions for LLM tool-use prompts.

### `xml_get_all_countries`

```json
{
  "name": "xml_get_all_countries",
  "description": "Download all DOTW country reference data. Use to look up country codes for nationality and residence fields.",
  "parameters": {
    "type": "object",
    "properties": {
      "json": {"type": "boolean", "description": "Return as JSON (recommended)"}
    },
    "required": []
  },
  "returns": "List of country objects with name and DOTW code",
  "when_to_use": "When you need to find a DOTW country code by name (e.g. Kuwait = 66)"
}
```

### `xml_get_all_cities`

```json
{
  "name": "xml_get_all_cities",
  "description": "Download all DOTW city reference data. Use to find city codes for hotel search.",
  "parameters": {
    "type": "object",
    "properties": {
      "json": {"type": "boolean"}
    },
    "required": []
  },
  "returns": "List of city objects with name, DOTW code, and country code",
  "when_to_use": "When you need a DOTW city code by name (e.g. Dubai = 364)"
}
```

### `xml_get_all_hotels`

```json
{
  "name": "xml_get_all_hotels",
  "description": "Download hotel static data: names, addresses, star ratings, DOTW hotel IDs.",
  "parameters": {
    "type": "object",
    "properties": {
      "city": {"type": "integer", "description": "Filter by DOTW city code"},
      "country": {"type": "integer", "description": "Filter by DOTW country code"},
      "json": {"type": "boolean"}
    },
    "required": []
  },
  "returns": "Hotel list with id, name, stars, address",
  "when_to_use": "When mapping hotel names to DOTW hotel IDs"
}
```

### `xml_get_salutations_ids`

```json
{
  "name": "xml_get_salutations_ids",
  "description": "Download salutation codes. Stable values: 147=Mr, 148=Mrs, 149=Ms, 150=Miss.",
  "parameters": {
    "type": "object",
    "properties": {
      "json": {"type": "boolean"}
    },
    "required": []
  },
  "returns": "Salutation code list",
  "when_to_use": "Rarely needed - salutation codes are stable. Use 147=Mr, 148=Mrs, 149=Ms, 150=Miss unless you need to verify."
}
```

### `xml_search_hotels`

```json
{
  "name": "xml_search_hotels",
  "description": "Search available hotels in a DOTW city for given dates and occupancy.",
  "parameters": {
    "type": "object",
    "properties": {
      "city": {"type": "integer", "description": "DOTW city code. Required if hotel not provided."},
      "hotel": {"type": "integer", "description": "DOTW hotel ID for single-property search"},
      "from": {"type": "string", "format": "date", "description": "Check-in YYYY-MM-DD"},
      "to": {"type": "string", "format": "date", "description": "Check-out YYYY-MM-DD"},
      "adults": {"type": "integer", "default": 2},
      "children": {"type": "integer", "default": 0},
      "currency": {"type": "integer", "description": "769=KWD"},
      "nationality": {"type": "integer"},
      "residence": {"type": "integer"},
      "rate_basis": {"type": "integer", "default": -1, "description": "-1=all"},
      "json": {"type": "boolean"}
    },
    "required": ["from", "to"]
  },
  "returns": "Hotel availability list with room types, rates, and prices",
  "when_to_use": "When searching for available hotels. Always before xml_get_rooms."
}
```

### `xml_get_rooms`

```json
{
  "name": "xml_get_rooms",
  "description": "Get detailed room/rate info for a hotel. Browse first, then block chosen rate before confirm_booking.",
  "parameters": {
    "type": "object",
    "properties": {
      "hotel": {"type": "string"},
      "from": {"type": "string", "format": "date"},
      "to": {"type": "string", "format": "date"},
      "adults": {"type": "integer", "default": 2},
      "currency": {"type": "integer"},
      "nationality": {"type": "integer"},
      "residence": {"type": "integer"},
      "block": {"type": "boolean", "description": "true=blocking mode, required before confirm_booking"},
      "room_type": {"type": "string", "description": "[blocking] roomTypeCode from browse"},
      "rate": {"type": "integer", "description": "[blocking] selectedRateBasis from browse"},
      "allocation": {"type": "string", "description": "[blocking] allocationDetails from browse"},
      "json": {"type": "boolean"}
    },
    "required": ["hotel", "from", "to"]
  },
  "returns": "Browse: room types with rates and cancellation policy. Blocking: status (checked/unchecked).",
  "when_to_use": "After xml_search_hotels. Workflow: browse -> block -> verify status=checked -> confirm_booking."
}
```

### `xml_confirm_booking`

```json
{
  "name": "xml_confirm_booking",
  "description": "Confirm a hotel booking. Requires allocationDetails from blocking xml_get_rooms (status=checked).",
  "parameters": {
    "type": "object",
    "properties": {
      "hotel": {"type": "string"},
      "from": {"type": "string", "format": "date"},
      "to": {"type": "string", "format": "date"},
      "currency": {"type": "integer"},
      "email": {"type": "string"},
      "reference": {"type": "string"},
      "rooms_json": {"type": "string", "description": "JSON array: [{roomTypeCode,selectedRateBasis,allocationDetails,adults,nationality,residence,passengers:[{salutation,firstName,lastName,leading}]}]"},
      "json": {"type": "boolean"}
    },
    "required": ["hotel", "from", "to", "rooms_json"]
  },
  "returns": "Booking confirmation with DOTW bookingCode and paymentGuaranteedBy",
  "when_to_use": "After successful blocking xml_get_rooms (status=checked). Salutations: 147=Mr, 148=Mrs, 149=Ms, 150=Miss."
}
```

### `xml_cancel_booking`

```json
{
  "name": "xml_cancel_booking",
  "description": "Cancel a DOTW booking. Always run confirm=no first (preview), then confirm=yes to execute.",
  "parameters": {
    "type": "object",
    "properties": {
      "booking": {"type": "string"},
      "confirm": {"type": "string", "enum": ["no", "yes"], "description": "no=preview charges; yes=execute"},
      "penalty": {"type": "string", "description": "Charge from confirm=no. Required for confirm=yes. Use charge not formatted."},
      "json": {"type": "boolean"}
    },
    "required": ["booking", "confirm"]
  },
  "returns": "Cancellation status and charge amount",
  "when_to_use": "Always run confirm=no first. Never skip charge preview."
}
```

### `xml_get_booking_details`

```json
{
  "name": "xml_get_booking_details",
  "description": "Retrieve full details for a booking: hotel, dates, passengers, status, paymentGuaranteedBy.",
  "parameters": {
    "type": "object",
    "properties": {
      "booking": {"type": "string"},
      "json": {"type": "boolean"}
    },
    "required": ["booking"]
  },
  "returns": "Full booking details",
  "when_to_use": "To look up voucher data, verify booking status, or retrieve passenger info."
}
```

### `xml_search_bookings`

```json
{
  "name": "xml_search_bookings",
  "description": "Search booking history by date range or booking reference.",
  "parameters": {
    "type": "object",
    "properties": {
      "from": {"type": "string", "format": "date"},
      "to": {"type": "string", "format": "date"},
      "booking": {"type": "string"},
      "status": {"type": "string"},
      "json": {"type": "boolean"}
    },
    "required": []
  },
  "returns": "List of matching bookings",
  "when_to_use": "To audit booking history or find a booking reference."
}
```

### `xml_delete_itinerary`

```json
{
  "name": "xml_delete_itinerary",
  "description": "Delete a DOTW itinerary.",
  "parameters": {
    "type": "object",
    "properties": {
      "itinerary": {"type": "string"},
      "json": {"type": "boolean"}
    },
    "required": ["itinerary"]
  },
  "returns": "Deletion status and productsLeftOnItinerary count",
  "when_to_use": "To clear a sandbox itinerary during testing or in the CC payment flow."
}
```

### `xml_book_itinerary`

```json
{
  "name": "xml_book_itinerary",
  "description": "Confirm all items in a CC-flow itinerary. Rarely needed in standard agent billing flow.",
  "parameters": {
    "type": "object",
    "properties": {
      "itinerary": {"type": "string"},
      "json": {"type": "boolean"}
    },
    "required": ["itinerary"]
  },
  "returns": "Booking confirmation for all itinerary items",
  "when_to_use": "In the CC (credit card) payment flow after savebooking."
}
```

### `xml_raw`

```json
{
  "name": "xml_raw",
  "description": "Send a raw XML body to any DOTW method. Escape hatch for unsupported methods or debugging.",
  "parameters": {
    "type": "object",
    "properties": {
      "method": {"type": "string", "description": "DOTW command name (e.g. getallcountries)"},
      "body": {"type": "string", "description": "Raw XML body inside request element"},
      "json": {"type": "boolean"}
    },
    "required": ["method", "body"]
  },
  "returns": "Raw DOTW response in chosen output format",
  "when_to_use": "When no dedicated command covers the method, or for debugging raw XML requests."
}
```

---

## dotw-cli vs dotw-xml-cli

| Scenario | Tool |
|----------|------|
| Full booking flow (search to confirm to voucher PDF) | dotw-cli |
| Cancel with accounting entry | dotw-cli |
| Debug a raw DOTW API call | dotw-xml-cli |
| Certification harness - collect API evidence | dotw-xml-cli |
| AI needs raw reference data (cities, countries) | dotw-xml-cli |
| AI needs booking history dump | dotw-xml-cli (xml:search-bookings) |
| Custom XML body not in any command | dotw-xml-cli (xml:raw) |

| Feature | dotw-cli | dotw-xml-cli |
|---------|----------|--------------|
| Purpose | High-level booking lifecycle | Low-level XML pass-through |
| State | SQLite (prebooks, bookings, accounting) | Stateless no storage |
| Business logic | Yes (rate locking, payment polling) | No (pure pass-through) |
| PDF voucher | Yes (DomPDF) | No |
| Commands | search/prebook/confirm/voucher/cancel | xml:* (13 commands) |
| When to use | End-user bookings, AI driving lifecycle | Debugging, cert evidence, raw queries |

---

## Extracting to Standalone Repo

```bash
# git subtree split:
git subtree split --prefix=packages/dotw-xml-cli -b dotw-xml-cli-standalone
git push git@github.com:your-org/dotw-xml-cli.git dotw-xml-cli-standalone:main

# Simple copy:
cp -r packages/dotw-xml-cli /path/to/new/repo
cd /path/to/new/repo
git init && git add . && git commit -m "initial: dotw-xml-cli standalone"
```

After extraction, update `composer.json` name from `dotw/xml-cli` to your target package name.
