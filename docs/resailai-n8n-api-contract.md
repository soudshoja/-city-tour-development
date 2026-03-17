# ResailAI n8n API Contract

Developer reference for the ResailAI PDF processing pipeline integration between Laravel and n8n.

---

## 1. Overview

When a PDF document is uploaded through the application, Laravel dispatches a background job (`ProcessDocumentJob`) that sends the document context to an n8n webhook for AI-powered extraction. After n8n processes the document, it calls back to Laravel with the extracted task data. Laravel then normalizes the data and creates one or more `Task` records.

**Pipeline:**

```
File uploaded → ProcessDocumentJob queued
    → POST n8n webhook (document context + file path)
        → n8n fetches PDF, runs extraction
            → POST /api/modules/resailai/callback (extracted task data)
                → TaskWebhookBridge normalizes fields
                    → TaskWebhook creates Task record(s)
```

**Prerequisites:** PDF processing must be enabled for the supplier/company combination via the `auto_process_pdf` flag on the `supplier_companies` table. If not enabled, the job exits silently and the callback returns a 200 with `"PDF processing not enabled"`.

---

## 2. Outbound: Laravel to n8n Webhook

Laravel sends a POST request to the URL configured in `config('resailai.n8n_webhook_url')` with a Bearer token from `config('resailai.api_token')`.

### 2.1 Request Headers

```
Authorization: Bearer <RESAILAI_API_TOKEN>
Content-Type: application/json
```

### 2.2 Payload Fields

| Field | Type | Description |
|---|---|---|
| `document_id` | string (UUID) | FileUpload record ID. Must be echoed back in the callback. |
| `company_id` | integer | Company that owns this document. |
| `supplier_id` | integer | Supplier the document belongs to. |
| `agent_id` | integer | Agent who uploaded the document (may be null). |
| `branch_id` | integer | Branch context (may be null). |
| `file_path` | string | Relative storage path to the PDF (e.g., `company_name/supplier_name/files_unprocessed/invoice.pdf`). |
| `callback_url` | string | Full URL for n8n to POST results back to. Always `{APP_URL}/api/modules/resailai/callback`. |
| `created_at` | string (ISO 8601) | Timestamp when the job was dispatched. |

### 2.3 Example Payload

```json
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "company_id": 1,
  "supplier_id": 7,
  "agent_id": 42,
  "branch_id": 3,
  "file_path": "city_travelers/amadeus/files_unprocessed/KU123456.pdf",
  "callback_url": "https://development.citycommerce.group/api/modules/resailai/callback",
  "created_at": "2026-03-17T09:30:00+00:00"
}
```

---

## 3. Inbound: n8n to Laravel Callback

### 3.1 Callback Endpoint

```
POST /api/modules/resailai/callback
Authorization: Bearer <API_TOKEN>
Content-Type: application/json
```

**Authentication:** The token is looked up against the `resailai_credentials` table. Tokens are stored encrypted; the middleware decrypts each active credential and compares to the provided token. An inactive or unrecognized token returns HTTP 401.

**Always returns HTTP 200** for all recognized outcomes (success, error, pending, processing disabled). HTTP 422 is returned for validation failures. HTTP 500 is returned for unhandled exceptions.

### 3.2 Top-Level Envelope (Required for All Callbacks)

These fields are required regardless of format:

| Field | Type | Required | Description |
|---|---|---|---|
| `document_id` | string (UUID) | Yes | Must match the `document_id` sent in the outbound payload. |
| `status` | string | Yes | Processing outcome: `success`, `error`, or `pending`. |
| `supplier_id` | integer | Recommended | Echo back from original payload. Used to check feature flag. |
| `company_id` | integer | Recommended | Echo back from original payload. Used to check feature flag. |
| `agent_id` | integer | Optional | Echo back from original payload. |
| `branch_id` | integer | Optional | Echo back from original payload. |
| `file_url` | string (URL) | Optional | URL to the processed document, if applicable. |

**For `status: "error"`**, the `error` object is required:

| Field | Type | Required | Description |
|---|---|---|---|
| `error.code` | string | Yes (when status=error) | Machine-readable error code (e.g., `EXTRACTION_FAILED`, `PDF_UNREADABLE`). |
| `error.message` | string | Yes (when status=error) | Human-readable error description. |

### 3.3 Two Accepted Payload Formats

Laravel accepts two formats for the extraction data within the same callback envelope.

#### Format A: Nested (Recommended for multi-task documents)

Task data lives inside `extraction_result.tasks[]`. Use this when one PDF produces multiple tasks (e.g., a multi-passenger flight booking).

```json
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "success",
  "company_id": 1,
  "supplier_id": 7,
  "agent_id": 42,
  "branch_id": 3,
  "extraction_result": {
    "tasks": [
      {
        "reference": "KU123456",
        "type": "flight",
        "status": "issued",
        "client_name": "AHMED AL RASHIDI MR",
        ...
      },
      {
        "reference": "KU123457",
        "type": "flight",
        "status": "issued",
        "client_name": "FATIMA AL RASHIDI MRS",
        ...
      }
    ],
    "metadata": {
      "processor": "tika",
      "confidence": 0.95
    }
  }
}
```

The `metadata` key inside `extraction_result` is stored for logging and ignored during task creation.

#### Format B: Flat (Suitable for single-task documents)

Task data lives directly inside `extraction_result` (no `tasks` array), or at the top level of the callback if `extraction_result` is omitted entirely.

```json
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "success",
  "company_id": 1,
  "supplier_id": 7,
  "agent_id": 42,
  "extraction_result": {
    "reference": "KU123456",
    "type": "flight",
    "status": "issued",
    "client_name": "AHMED AL RASHIDI MR",
    ...
  }
}
```

In both formats, `document_id`, `company_id`, `supplier_id`, `agent_id`, and `branch_id` from the envelope are automatically merged into each task payload, so you do not need to repeat them inside each task object (though doing so is harmless and the task-level values will take precedence if provided).

### 3.4 Critical Task Fields (Required — Callback Rejected Without These)

These four fields are validated before any normalization. If any is missing or invalid, the task is not created and the callback returns `success: false` with an error message.

| Field | Type | Valid Values | Notes |
|---|---|---|---|
| `reference` | string | Any non-empty string | The booking/ticket reference number. |
| `type` | string | `flight`, `hotel`, `visa`, `insurance` | Case-insensitive; normalized to lowercase. |
| `company_id` | integer | Positive integer, must exist in `companies` table | |
| `status` | string | See Section 3.5 for valid values | Case-insensitive; normalized to lowercase. |

### 3.5 Task Status Values

| Value | Meaning |
|---|---|
| `issued` | Confirmed and ticketed |
| `reissued` | Re-issued ticket (links to original via `original_reference`) |
| `void` | Voided ticket |
| `refund` | Refunded ticket |
| `emd` | Electronic Miscellaneous Document — normalized to `issued` by Laravel |
| `on hold` | Tentative booking |
| `confirmed` | Confirmed but not yet ticketed (48-hour expiry auto-set if no `expiry_date`) |

**Supplier-specific status overrides (applied automatically by Laravel):**

| Supplier | `confirmed` becomes | `on hold` becomes |
|---|---|---|
| Jazeera Airways | `issued` | `confirmed` |
| Fly Dubai | `issued` | `confirmed` |
| VFS | `issued` | `confirmed` |

### 3.6 Common Task Fields (Optional but Strongly Recommended)

These fields are passed through `normalizeCommonFields()` in `TaskWebhookBridge`. Non-critical normalization failures produce a warning in the `DocumentError` table but do not block task creation — the task is created with `is_complete: false` instead.

| Field | Type | Description | When to Include |
|---|---|---|---|
| `supplier_id` | integer | Supplier database ID | Always; required for supplier-specific logic |
| `agent_id` | integer | Agent database ID | When known; used for auto-billing matching |
| `branch_id` | integer | Branch database ID | When known |
| `client_name` | string | Passenger/client full name | Always; also populates `passenger_name` |
| `client_id` | integer | Client database ID | When client is known in system |
| `issued_by` | string | GDS office code that issued the ticket (e.g., `KWIKT211N`) | Amadeus only |
| `original_reference` | string | Reference of the original ticket being reissued/refunded | When `status` is `reissued`, `refund`, or `void` |
| `gds_reference` | string | GDS-level booking reference | Amadeus only |
| `airline_reference` | string | Airline-level booking reference | Amadeus only |
| `created_by` | string | GDS agent who created the booking | Amadeus only |
| `issued_date` | string (date) | Date the ticket was issued | Always |
| `expiry_date` | string (date) | Ticket/booking expiry date | For `on hold` and `confirmed` bookings |
| `ticket_number` | string | Primary ticket number | Flight tasks |
| `original_ticket_number` | string | Ticket number being replaced | When `status` is `reissued` |
| `booking_reference` | string | API booking reference (TBO, etc.) | TBO Holiday tasks |
| `client_ref` | string | Client-side booking reference | Magic Holiday tasks |
| `cancellation_deadline` | string (date) | Last date to cancel without penalty | Hotel tasks |
| `iata_number` | string | IATA wallet number for billing | When IATA wallet processing is needed |
| `supplier_status` | string | Raw status string from supplier system | When supplier uses non-standard status terminology |
| `supplier_pay_date` | string (date) | Date payment is due to supplier | Optional; auto-derived from `issued_date` for most types |
| `refund_date` | string (date) | Date refund was processed | When `status` is `refund` |
| `refund_charge` | numeric | Penalty amount charged on refund | When `status` is `refund`; defaults to 0 |
| `additional_info` | string | Free-text notes | Optional |
| `enabled` | boolean | Whether task should be visible/active | Defaults to false; auto-set by system rules |
| `file_name` | string | Original PDF filename | Recommended for traceability |
| `passenger_name` | string | Explicit passenger name override | If omitted, copied from `client_name` |

**Date format support:** All date fields accept `DD/MM/YYYY`, `DD-Mon-YYYY` (e.g., `15-Mar-2026`), `YYYYMMDD`, `YYYY-MM-DD`, `YYYY-MM-DD HH:MM:SS`, and ISO 8601. All dates are normalized to `YYYY-MM-DD HH:MM:SS` internally.

**Boolean field support:** `enabled` accepts `true`, `false`, `1`, `0`, `"yes"`, `"true"`.

### 3.7 Financial Fields

All financial amounts are assumed to be in KWD unless `exchange_currency` specifies otherwise. See currency swap behavior below.

| Field | Type | Description |
|---|---|---|
| `price` | numeric | Net price (before tax). Defaults to 0. |
| `total` | numeric | Total amount charged to client. Defaults to 0. |
| `tax` | numeric | Tax component. Defaults to 0. |
| `surcharge` | numeric | Surcharge component. Defaults to 0. |
| `penalty_fee` | numeric | Penalty fee (e.g., for reissue/refund). Defaults to 0. |
| `exchange_currency` | string | Currency of the amounts above (ISO 4217, e.g., `USD`, `AED`). Defaults to `KWD`. |
| `exchange_rate` | numeric | Rate to convert `exchange_currency` to KWD. Required when `exchange_currency != KWD`. |
| `original_price` | numeric | Original amount in foreign currency (if you have pre-calculated KWD amounts). |
| `original_total` | numeric | Original total in foreign currency. |
| `original_tax` | numeric | Original tax in foreign currency. |
| `original_surcharge` | numeric | Original surcharge in foreign currency. |
| `original_currency` | string | Foreign currency code (set automatically from `exchange_currency` during swap). |
| `taxes_record` | string | Serialized tax breakdown string (passed through as-is). |

**Currency swap behavior:**

When `exchange_currency` is not `KWD` and `original_*` fields are NOT provided, Laravel automatically:
1. Moves `price`, `total`, `tax`, `surcharge` to `original_price`, `original_total`, `original_tax`, `original_surcharge`.
2. Sets `original_currency` to the provided `exchange_currency`.
3. Sets `exchange_currency` to `KWD`.
4. Multiplies all amounts by `exchange_rate` to produce the KWD values.
5. If `exchange_rate` is missing or zero, amounts are zeroed and a normalization error is logged.

When `exchange_currency` is not `KWD` and `original_*` fields ARE provided, Laravel assumes you have pre-calculated both sets and keeps them as-is, only normalizing `exchange_currency` to `KWD`.

**Numeric string support:** Commas are stripped before parsing, so `"1,234.56"` is accepted.

### 3.8 Flight Detail Fields (`task_flight_details`)

When `type` is `flight`, include a `task_flight_details` array (also accepted as `flight_details`). Each element represents one flight segment.

| Field | Type | Description | Notes |
|---|---|---|---|
| `is_ancillary` | boolean | Whether this is an ancillary charge (e.g., extra baggage fee). | Defaults to false. |
| `farebase` | numeric | Fare basis amount. | Defaults to 0. |
| `departure_time` | string (date) | Departure date/time. | See date formats in 3.6. |
| `arrival_time` | string (date) | Arrival date/time. | See date formats in 3.6. |
| `airline_id` | string or integer | Airline name, ICAO code, or database ID. | Looked up from `airlines` table by name (LIKE) or ICAO designator. |
| `airport_from` | string | Departure airport IATA code (e.g., `KWI`). | Normalized to uppercase. |
| `terminal_from` | string | Departure terminal. | |
| `country_id_from` | string or integer | IATA code, country name, or country DB ID for origin. | Looked up from `airports` table by IATA code, then `countries` table by name. |
| `airport_to` | string | Arrival airport IATA code (e.g., `DXB`). | Normalized to uppercase. |
| `terminal_to` | string | Arrival terminal. | |
| `country_id_to` | string or integer | IATA code, country name, or country DB ID for destination. | Same lookup as `country_id_from`. |
| `flight_number` | string | Flight number (e.g., `KU104`). | |
| `ticket_number` | string | Per-segment ticket number. | |
| `class_type` | string | Cabin class (e.g., `economy`, `business`). | Normalized to lowercase. |
| `baggage_allowed` | string | Baggage allowance description (e.g., `23KG`, `2PC`). | |
| `equipment` | string | Aircraft type (e.g., `73H`, `320`). | |
| `flight_meal` | string | In-flight meal code (e.g., `VGML`). | |
| `seat_no` | string | Assigned seat number. | |
| `duration_time` | string | Flight duration (e.g., `2h 30m`). | |

**Airline lookup:** If `airline_id` is a numeric value it is used directly as the database ID. If it is a string, Laravel searches `airlines.name LIKE '%value%'` and `airlines.icao_designator = 'VALUE'`. If not found, a normalization error is logged and `airline_id` is set to 0, causing `is_complete: false`.

**Country lookup for airports:** If `country_id_from` / `country_id_to` is a 3-letter string, it is treated as an IATA airport code and looked up in the `airports` table to retrieve `country_id`. If it is a longer string, it is used as a country name lookup (LIKE). Numeric values are used directly.

**Multi-segment flights:** Include one object per segment in the `task_flight_details` array.

### 3.9 Hotel Detail Fields (`task_hotel_details`)

When `type` is `hotel`, include a `task_hotel_details` array (also accepted as `hotel_details`). Each element represents one room booking.

| Field | Type | Description | Notes |
|---|---|---|---|
| `hotel_name` | string | Full hotel name. | Used for deduplication matching. |
| `booking_time` | string (date) | When the booking was made. | Defaults to current time if not provided. |
| `check_in` | string (date) | Check-in date. | Used for deduplication matching. |
| `check_out` | string (date) | Check-out date. | Used for deduplication matching. |
| `room_reference` | string | Room or confirmation reference. | |
| `room_number` | string | Physical room number (if known). | |
| `room_type` | string | Room category (e.g., `Deluxe Double`). | Used for deduplication matching. |
| `room_amount` | numeric | Number of rooms. | Minimum enforced as 1. |
| `room_details` | string | Additional room description. | |
| `room_promotion` | string | Applied promotion code or name. | |
| `rate` | numeric | Nightly rate. | Defaults to 0. |
| `meal_type` | string | Board type code or full name. | See board code mapping below. |
| `is_refundable` | boolean | Whether the booking is refundable. | Defaults to false. |
| `supplements` | string | Additional charges or notes. | |

**Board code mapping (applied automatically):**

| Code | Normalized Value |
|---|---|
| `BB` | Bed and Breakfast |
| `HB` | Half Board |
| `FB` | Full Board |
| `AI` | All Inclusive |
| `RO` | Room Only |
| `SC` | Self Catering |

Any value not in this list is passed through unchanged.

**Hotel deduplication:** Before creating a hotel task, Laravel checks for an existing task matching: `reference` + `company_id` + `supplier_id` + `status` + `client_name` + `hotel_name` + `room_type` + `check_in` + `check_out`. If a match is found, the existing task is updated instead of creating a new one.

### 3.10 Visa Detail Fields (`task_visa_details`)

When `type` is `visa`, include a `task_visa_details` array (also accepted as `visa_details`).

| Field | Type | Description | Notes |
|---|---|---|---|
| `visa_type` | string | Visa category (e.g., `Tourist`, `Business`, `Transit`). | |
| `application_number` | string | Visa application reference number. | |
| `expiry_date` | string (date) | Visa expiry date. | |
| `issuing_country` | string | Country name that issued the visa. | |
| `number_of_entries` | string | Entry allowance. | Must be `single`, `double`, or `multiple`. Defaults to `single` on invalid value with error logged. |
| `stay_duration` | integer or string | Maximum stay duration in days. | Extracts first integer from strings like `"30 days"`, `"Up to 30 days"`. |

### 3.11 Insurance Detail Fields (`task_insurance_details`)

When `type` is `insurance`, include a `task_insurance_details` array (also accepted as `insurance_details`).

| Field | Type | Description | Notes |
|---|---|---|---|
| `date` | string | Policy year (4-digit year string, e.g., `"2026"`). | If a full date is provided, only the year is extracted. |
| `paid_leaves` | integer | Number of paid leave days covered. | Defaults to 0. |
| `document_reference` | string | Insurance policy/document number. | |
| `insurance_type` | string | Type of insurance (e.g., `Travel`, `Medical`). | |
| `destination` | string | Travel destination covered. | |
| `plan_type` | string | Plan tier (e.g., `Basic`, `Premium`). | |
| `duration` | string | Coverage duration description (e.g., `7 days`, `Annual`). | |
| `package` | string | Package name or code. | |

---

## 4. Error Handling

### 4.1 Critical Field Validation Failure

If `reference`, `type`, `company_id`, or `status` is missing or invalid:

- Task is NOT created.
- `DocumentProcessingLog` is updated to `status: failed`.
- Response body: `{ "success": false, "error": "Missing critical field: <field>" }`

### 4.2 Non-Critical Normalization Failure

If optional fields fail normalization (unrecognized date format, unknown airline, invalid `number_of_entries`, etc.):

- Task IS created.
- Task `is_complete` is set to `false`.
- Task `enabled` is set to `false`.
- Each failing field creates a `DocumentError` record linked to the `DocumentProcessingLog`.
- `DocumentProcessingLog.needs_review` is set to `true`.
- The task will not automatically generate invoices or financials until reviewed.

### 4.3 TaskWebhook Validation Failure

If the normalized payload fails Laravel's model-level validation (e.g., `company_id` does not exist in the `companies` table):

- Task is NOT created.
- Response contains `warnings` with validation error details.
- `DocumentProcessingLog` is updated to `needs_review: true`.

### 4.4 Status "error" Callback

When n8n sends `status: "error"`:

- No task is created.
- `FileUpload.status` is updated to `error`.
- `DocumentProcessingLog` is updated to `status: failed` with `error_code` and `error_message`.
- Response: `{ "message": "Extraction failed", "document_id": "...", "error": { "code": "...", "message": "..." } }`

### 4.5 Status "pending" Callback

When n8n sends `status: "pending"`:

- No task is created.
- `FileUpload.status` is updated to `pending`.
- Response: `{ "message": "Processing pending", "document_id": "..." }`
- n8n should send a follow-up callback when processing completes.

---

## 5. Callback Response Format

Laravel always returns HTTP 200 for recognized callbacks. The response body indicates outcome.

**All tasks created successfully:**
```json
{
  "message": "All tasks created successfully",
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "tasks_processed": 2,
  "data": [
    {
      "status": "success",
      "message": "Task created successfully via webhook",
      "data": {
        "task_id": 1234,
        "reference": "KU123456",
        "type": "flight",
        "enabled": false
      }
    },
    {
      "status": "success",
      "message": "Task created successfully via webhook",
      "data": {
        "task_id": 1235,
        "reference": "KU123457",
        "type": "flight",
        "enabled": false
      }
    }
  ]
}
```

**Partial failure (some tasks failed):**
```json
{
  "message": "Some tasks failed",
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "tasks_processed": 2,
  "data": [
    { "status": "success", "data": { "task_id": 1234, ... } },
    { "error": "Missing critical field: reference" }
  ]
}
```

**Note:** `tasks_processed` reflects the number of task payloads that were attempted, not the number successfully created.

---

## 6. Multi-Task Documents

One PDF can produce multiple tasks. Common cases:

- Multi-passenger flight booking: each passenger gets a separate task with the same or different reference.
- Round-trip with separate ticket numbers: still one task per passenger.
- Group hotel bookings: one task per room type/reference combination.

Use **Format A** (nested `extraction_result.tasks[]`) for multi-task documents. Each element in the `tasks` array is an independent task payload.

Laravel processes tasks sequentially within a single callback. If one task fails critical validation, it is skipped with an error in the response — other tasks in the same callback continue processing.

---

## 7. Supplier-Specific Notes

### Amadeus

- `gds_reference` and `airline_reference` are only stored for Amadeus. For all other suppliers, these fields are nulled out by Laravel even if provided.
- `created_by` and `issued_by` are only stored for Amadeus. For all other suppliers, these fields are nulled out.
- `issued_by` is used for IATA wallet routing (e.g., `KWIKT211N` routes to City Travelers EasyPay wallet).
- `iata_number` triggers automatic wallet debit journal entries for Amadeus and NDC suppliers.

### Jazeera Airways / Fly Dubai / VFS

- Status mapping is applied automatically: `confirmed` → `issued`, `on hold` → `confirmed`.
- Do not pre-map these statuses in n8n; send the raw supplier status and let Laravel apply the mapping.

### Insurance Documents

- Create **one task per policy**, not one per insured person.
- The `date` field stores only the year (4-digit string). Full dates are automatically truncated.
- Multiple insured persons on one policy are represented in `additional_info` or `passenger_name`.

### Hotel Deduplication

- Laravel checks for existing tasks before creating a new hotel task.
- Match criteria: `reference` + `hotel_name` + `room_type` + `check_in` + `check_out` + `company_id`.
- If a match exists, the existing task is updated (not duplicated).
- Ensure `hotel_name`, `room_type`, `check_in`, and `check_out` are extracted accurately.

### TBO Holiday

- Include `booking_reference` (TBO booking reference ID) to trigger automatic invoice generation.
- When a matching TBO booking with an associated payment is found, `is_n8n_booking` is set and the invoice is generated automatically.

### Magic Holiday

- Include `client_ref` (Magic Holiday client reference) to trigger automatic invoice generation.
- When a matching `HotelBooking` with an associated payment is found, `client_id`, `client_name`, and `agent_id` are pulled from the payment record automatically.

---

## 8. Example Payloads

### 8.1 Flight Task (Format A, Single Passenger)

```json
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "success",
  "company_id": 1,
  "supplier_id": 7,
  "agent_id": 42,
  "branch_id": 3,
  "extraction_result": {
    "tasks": [
      {
        "reference": "KU123456",
        "type": "flight",
        "status": "issued",
        "client_name": "AHMED AL RASHIDI MR",
        "issued_date": "17/03/2026",
        "ticket_number": "229-1234567890",
        "price": 85.000,
        "total": 95.500,
        "tax": 10.500,
        "surcharge": 0,
        "exchange_currency": "KWD",
        "file_name": "KU123456_AHMED.pdf",
        "task_flight_details": [
          {
            "is_ancillary": false,
            "farebase": 85.000,
            "departure_time": "18/03/2026 06:45",
            "arrival_time": "18/03/2026 08:15",
            "airline_id": "Kuwait Airways",
            "airport_from": "KWI",
            "country_id_from": "KWI",
            "terminal_from": "4",
            "airport_to": "DXB",
            "country_id_to": "DXB",
            "terminal_to": "1",
            "flight_number": "KU104",
            "ticket_number": "229-1234567890",
            "class_type": "economy",
            "baggage_allowed": "23KG",
            "equipment": "73H",
            "flight_meal": "",
            "seat_no": "14A",
            "duration_time": "1h 30m"
          }
        ]
      }
    ]
  }
}
```

### 8.2 Flight Task (Amadeus, Multi-Passenger, Format A)

```json
{
  "document_id": "660e8400-e29b-41d4-a716-446655440001",
  "status": "success",
  "company_id": 1,
  "supplier_id": 2,
  "agent_id": 10,
  "branch_id": 1,
  "extraction_result": {
    "tasks": [
      {
        "reference": "DROXL0",
        "type": "flight",
        "status": "issued",
        "client_name": "SMITH JOHN MR",
        "issued_by": "KWIKT211N",
        "created_by": "KWIKT2619",
        "gds_reference": "8DROXL0101",
        "airline_reference": "KU1234",
        "iata_number": "42230215",
        "issued_date": "17/03/2026",
        "ticket_number": "229-9876543210",
        "price": 120.000,
        "total": 135.000,
        "tax": 15.000,
        "exchange_currency": "KWD",
        "task_flight_details": [
          {
            "is_ancillary": false,
            "farebase": 120.000,
            "departure_time": "20/03/2026 14:00",
            "arrival_time": "20/03/2026 17:30",
            "airline_id": "KU",
            "airport_from": "KWI",
            "country_id_from": "KWI",
            "airport_to": "LHR",
            "country_id_to": "LHR",
            "flight_number": "KU101",
            "ticket_number": "229-9876543210",
            "class_type": "business",
            "baggage_allowed": "2PC",
            "equipment": "77W",
            "duration_time": "7h 30m"
          }
        ]
      },
      {
        "reference": "DROXL0",
        "type": "flight",
        "status": "issued",
        "client_name": "SMITH JANE MRS",
        "issued_by": "KWIKT211N",
        "created_by": "KWIKT2619",
        "gds_reference": "8DROXL0101",
        "airline_reference": "KU1234",
        "iata_number": "42230215",
        "issued_date": "17/03/2026",
        "ticket_number": "229-9876543211",
        "price": 120.000,
        "total": 135.000,
        "tax": 15.000,
        "exchange_currency": "KWD",
        "task_flight_details": [
          {
            "is_ancillary": false,
            "farebase": 120.000,
            "departure_time": "20/03/2026 14:00",
            "arrival_time": "20/03/2026 17:30",
            "airline_id": "KU",
            "airport_from": "KWI",
            "country_id_from": "KWI",
            "airport_to": "LHR",
            "country_id_to": "LHR",
            "flight_number": "KU101",
            "ticket_number": "229-9876543211",
            "class_type": "business",
            "baggage_allowed": "2PC",
            "equipment": "77W",
            "duration_time": "7h 30m"
          }
        ]
      }
    ]
  }
}
```

### 8.3 Hotel Task

```json
{
  "document_id": "770e8400-e29b-41d4-a716-446655440002",
  "status": "success",
  "company_id": 1,
  "supplier_id": 15,
  "agent_id": 42,
  "extraction_result": {
    "reference": "HTL-789012",
    "type": "hotel",
    "status": "confirmed",
    "client_name": "KHALID AL MANSOURI MR",
    "issued_date": "17/03/2026",
    "cancellation_deadline": "25/03/2026",
    "price": 180.000,
    "total": 195.000,
    "tax": 15.000,
    "exchange_currency": "KWD",
    "file_name": "HTL789012.pdf",
    "task_hotel_details": [
      {
        "hotel_name": "Jumeirah Beach Hotel",
        "booking_time": "17/03/2026",
        "check_in": "28/03/2026",
        "check_out": "04/04/2026",
        "room_reference": "JBH-456789",
        "room_number": "",
        "room_type": "Deluxe Sea View",
        "room_amount": 1,
        "room_details": "King bed, sea facing, high floor",
        "room_promotion": "EARLY2026",
        "rate": 25.000,
        "meal_type": "BB",
        "is_refundable": true,
        "supplements": ""
      }
    ]
  }
}
```

### 8.4 Visa Task

```json
{
  "document_id": "880e8400-e29b-41d4-a716-446655440003",
  "status": "success",
  "company_id": 1,
  "supplier_id": 20,
  "agent_id": 42,
  "extraction_result": {
    "reference": "VISA-2026-98765",
    "type": "visa",
    "status": "issued",
    "client_name": "NOUR AL SALEM MISS",
    "issued_date": "15/03/2026",
    "expiry_date": "14/09/2026",
    "price": 45.000,
    "total": 50.000,
    "tax": 5.000,
    "exchange_currency": "KWD",
    "file_name": "VISA_NOUR_DUBAI.pdf",
    "task_visa_details": [
      {
        "visa_type": "Tourist",
        "application_number": "APP-2026-112233",
        "expiry_date": "14/09/2026",
        "issuing_country": "United Arab Emirates",
        "number_of_entries": "multiple",
        "stay_duration": "90 days"
      }
    ]
  }
}
```

### 8.5 Insurance Task

```json
{
  "document_id": "990e8400-e29b-41d4-a716-446655440004",
  "status": "success",
  "company_id": 1,
  "supplier_id": 33,
  "agent_id": 42,
  "extraction_result": {
    "reference": "INS-POL-554433",
    "type": "insurance",
    "status": "issued",
    "client_name": "AL RASHIDI FAMILY",
    "issued_date": "17/03/2026",
    "expiry_date": "24/03/2026",
    "price": 18.500,
    "total": 18.500,
    "tax": 0,
    "exchange_currency": "KWD",
    "file_name": "INSURANCE_ALRASHIDI.pdf",
    "task_insurance_details": [
      {
        "date": "2026",
        "paid_leaves": 7,
        "document_reference": "POL-2026-554433",
        "insurance_type": "Travel",
        "destination": "Europe",
        "plan_type": "Premium",
        "duration": "7 days",
        "package": "EUROPE-PREMIUM-7D"
      }
    ]
  }
}
```

### 8.6 Non-KWD Currency Example (USD Flight)

```json
{
  "document_id": "aa0e8400-e29b-41d4-a716-446655440005",
  "status": "success",
  "company_id": 1,
  "supplier_id": 40,
  "agent_id": 42,
  "extraction_result": {
    "reference": "EK987654",
    "type": "flight",
    "status": "issued",
    "client_name": "SARA AL FARAJ MS",
    "issued_date": "17/03/2026",
    "price": 350.00,
    "total": 395.00,
    "tax": 45.00,
    "exchange_currency": "USD",
    "exchange_rate": 0.307,
    "task_flight_details": [
      {
        "departure_time": "22/03/2026 22:30",
        "arrival_time": "23/03/2026 02:15",
        "airline_id": "Emirates",
        "airport_from": "KWI",
        "country_id_from": "KWI",
        "airport_to": "DXB",
        "country_id_to": "DXB",
        "flight_number": "EK860",
        "class_type": "economy",
        "baggage_allowed": "30KG"
      }
    ]
  }
}
```

After processing, Laravel will store:
- `original_price: 350.00`, `original_total: 395.00`, `original_tax: 45.00`
- `original_currency: "USD"`, `exchange_currency: "KWD"`, `exchange_rate: 0.307`
- `price: 107.45`, `total: 121.265`, `tax: 13.815` (in KWD)

### 8.7 Extraction Error Callback

```json
{
  "document_id": "bb0e8400-e29b-41d4-a716-446655440006",
  "status": "error",
  "company_id": 1,
  "supplier_id": 7,
  "error": {
    "code": "PDF_UNREADABLE",
    "message": "Could not extract text from scanned PDF — OCR confidence below threshold"
  }
}
```

---

## 9. Related Files

| File | Purpose |
|---|---|
| `app/Modules/ResailAI/Jobs/ProcessDocumentJob.php` | Builds and sends the outbound payload to n8n |
| `app/Modules/ResailAI/Http/Controllers/CallbackController.php` | Receives and routes the n8n callback |
| `app/Modules/ResailAI/Services/ProcessingAdapter.php` | Flattens nested/flat formats into task arrays, checks feature flags |
| `app/Modules/ResailAI/Services/TaskWebhookBridge.php` | Normalizes all fields and calls TaskWebhook |
| `app/Http/Webhooks/TaskWebhook.php` | Creates Task records and applies supplier-specific rules |
| `app/Modules/ResailAI/Middleware/VerifyResailAIToken.php` | Bearer token authentication for the callback endpoint |
| `app/Modules/ResailAI/Routes/routes.php` | Route registration for `/api/modules/resailai/callback` |
| `config/resailai.php` | `n8n_webhook_url`, `api_token`, `timeout`, `max_retries` config |
