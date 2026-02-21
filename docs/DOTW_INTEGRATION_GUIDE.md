# DOTW Hotel Booking Integration Guide

**Version:** 1.0
**Last Updated:** 2026-02-21
**Status:** Production Ready

This guide covers the DOTW (DOTWconnect) hotel booking module, including:
- Admin UI setup and credential management
- API token generation for n8n automation
- GraphQL integration for n8n workflows
- Error handling and troubleshooting

---

## Table of Contents

1. [Admin UI Guide](#admin-ui-guide)
2. [Credential Management](#credential-management)
3. [API Token Generation for n8n](#api-token-generation-for-n8n)
4. [Audit Logs](#audit-logs)
5. [n8n Integration](#n8n-integration)
6. [GraphQL Operations](#graphql-operations)
7. [Error Handling](#error-handling)
8. [Deployment & Environment](#deployment--environment)
9. [Troubleshooting](#troubleshooting)

---

## Admin UI Guide

### Accessing DOTW Admin

The DOTW admin interface is accessible via **two paths**:

1. **Standalone Page**: `/admin/dotw`
   - Full-screen DOTW management interface
   - Direct navigation from sidebar (Webbeds logo icon)
   - Best for dedicated configuration work

2. **Settings Tab**: `/settings` → **DOTW / Hotel API** tab
   - Embedded within Project Settings
   - Quick access for configuration tweaks
   - Credentials + Audit Logs + API Tokens (for Super Admin)

### Prerequisites

- **User Role**: `ADMIN` (super admin) OR `COMPANY` (company admin)
- **Authentication**: Must be logged in
- **Middleware**: `auth` + `dotw_audit_access` required

### Role-Based Access Control

| Role | Credentials | Audit Logs | API Tokens |
|------|-------------|-----------|-----------|
| Super Admin (ADMIN) | Via API only | All companies | Full access |
| Company Admin (COMPANY) | Per-company form | Own company only | Not accessible |
| Branch / Agent | None | None | None |

---

## Credential Management

### Credentials Tab

The **Credentials** tab allows you to configure DOTW API access for your company.

#### For Company Admins

1. **Navigate** to `/settings` → **DOTW / Hotel API** → **Credentials** tab

2. **Fill in the form** with your DOTW credentials:

   | Field | Type | Required | Notes |
   |-------|------|----------|-------|
   | **DOTW Username** | Text | Yes | Your DOTW V4 API username (max 100 chars) |
   | **DOTW Password** | Password | Yes | Your DOTW V4 API password (max 200 chars) |
   | **DOTW Company Code** | Text | Yes | Your company code assigned by DOTW (max 50 chars) |
   | **Markup %** | Number | No | Percentage markup on hotel rates (0-100, default 20%) |

3. **Security Notes**:
   - Password field is **never pre-filled** after save — you must enter it each time you update
   - Passwords are **encrypted at rest** using Laravel's `Crypt` helper
   - Never shared in API responses or logs
   - Stored encrypted in `company_dotw_credentials` table

4. **Save** by clicking the "Save Credentials" button

5. **Confirmation**: A green success message appears: "DOTW credentials saved successfully."

#### For Super Admins

Super Admins cannot save credentials from the `/settings` page. Instead, use the **REST API endpoint**:

```bash
POST /api/admin/companies/{companyId}/dotw-credentials
```

**Request Body** (JSON):

```json
{
  "dotw_username": "your_username",
  "dotw_password": "your_password",
  "dotw_company_code": "YOUR_CODE",
  "markup_percent": 20
}
```

**cURL Example**:

```bash
curl -X POST https://development.citycommerce.group/api/admin/companies/5/dotw-credentials \
  -H "Content-Type: application/json" \
  -d '{
    "dotw_username": "demo_user",
    "dotw_password": "secure_pass_123",
    "dotw_company_code": "COMP001",
    "markup_percent": 20
  }'
```

**Response** (200 OK):

```json
{
  "success": true,
  "message": "DOTW credentials saved successfully",
  "company_id": 5,
  "markup_percent": 20
}
```

#### Validation Rules

All fields are validated on save:

- `dotw_username`: Required, max 100 characters
- `dotw_password`: Required, max 200 characters
- `dotw_company_code`: Required, max 50 characters
- `markup_percent`: Optional, 0-100 decimal range

If validation fails, the form displays specific error messages for each field.

#### Credential Encryption

Credentials are encrypted using Laravel's `Crypt::encrypt()` at the model level via `Illuminate\Database\Eloquent\Casts\Attribute`:

- **Automatic on save**: Credentials are encrypted before storage
- **Automatic on retrieval**: Credentials are decrypted when accessed via model accessors
- **Key**: Uses `APP_KEY` from `.env`

**Database Storage**:

```sql
Table: company_dotw_credentials
Columns:
  - id (int)
  - company_id (int, unique)
  - dotw_username (text, encrypted)
  - dotw_password (text, encrypted)
  - dotw_company_code (text, plaintext)
  - markup_percent (decimal)
  - is_active (bool)
  - created_at (timestamp)
  - updated_at (timestamp)
```

---

## API Token Generation for n8n

### API Tokens Tab (Super Admin Only)

The **API Tokens** tab allows Super Admins to generate and revoke Sanctum authentication tokens for n8n workflow integration.

#### Who Can Access?

- **Super Admin (ADMIN)** only
- Navigate to `/admin/dotw` → **API Tokens** tab
- Or `/settings` → **DOTW / Hotel API** → **API Tokens** tab

#### Token Management Table

The page displays a table of all companies with active DOTW credentials:

| Column | Description |
|--------|-------------|
| **Company** | Company name and ID |
| **Status** | DOTW credential status (Active/Inactive) |
| **Primary User** | Email of the company's primary user (who owns the token) |
| **Token (masked)** | First 4 and last 4 characters of the token (security masked) |
| **Actions** | Generate / Regenerate / Revoke buttons |

#### Generating a Token

**Step 1**: Click **"Generate"** (or **"Regenerate"** if token exists)

**Step 2**: A modal appears showing the **plaintext token**:

```
Token Generated — Company #5

Copy this token now. It will not be shown again after you close this dialog.

eyd...7a9 [Copy Button]

[Close Button]
```

**Step 3**: Click **"Copy"** to copy the token to clipboard

**Step 4**: Click **"I've copied it — Close"** to dismiss the modal

**Important**: The plaintext token is shown **only once**. After dismissal, the token cannot be viewed again (only hashed version is stored).

#### Token Storage

Tokens are stored in the `personal_access_tokens` table:

```sql
Table: personal_access_tokens
Columns:
  - id (int)
  - tokenable_id (int) — User ID
  - tokenable_type (string) — "App\\Models\\User"
  - name (string) — "dotw-n8n" (always this value)
  - token (text) — Hashed token
  - abilities (text) — JSON array (empty = all abilities)
  - created_at (timestamp)
  - updated_at (timestamp)
```

#### Token Format

Generated tokens follow Laravel Sanctum format:

```
{UUID}|{HASH}
```

Example: `eyde8a67-35b7-4abd-a7e9-f8c3a1b2c3d4|6de17de8d...` (65 chars total)

#### Revoking a Token

**Step 1**: Click **"Revoke"** button for the company

**Step 2**: Confirm the warning dialog:

```
Revoke dotw-n8n token for {Company Name}?
n8n workflows using this token will stop working immediately.
```

**Step 3**: Click **"OK"** to revoke

**Result**: Token is deleted from `personal_access_tokens` table and all n8n requests using this token will receive `401 Unauthorized`.

#### Token Lifecycle

1. **Generated**: Token issued and plaintext shown once
2. **Stored**: Hashed token saved in `personal_access_tokens`
3. **Valid**: Can authenticate requests for ~1 year (depends on Sanctum config)
4. **Revoked**: Token deleted, all requests fail with 401

---

## Audit Logs

### Audit Logs Tab

The **Audit Logs** tab provides a live record of all DOTW GraphQL operations executed via n8n.

#### Accessing Audit Logs

- Navigate to `/admin/dotw` → **Audit Logs** tab
- Or `/settings` → **DOTW / Hotel API** → **Audit Logs** tab

#### Log Filtering

The page includes a filter bar to search and filter logs:

| Filter | Type | Description |
|--------|------|-------------|
| **Operation** | Dropdown | Filter by operation type: search, rates, block, book |
| **Message ID** | Text input | Filter by WhatsApp message ID (supports partial match) |
| **From** | Date picker | Filter logs on or after this date |
| **To** | Date picker | Filter logs on or before this date |
| **Company ID** | Text input | Filter by company ID (Super Admin only) |

#### Log Table Columns

| Column | Description |
|--------|-------------|
| **ID** | Unique log ID (Super Admin only) |
| **Company** | Company ID that executed the operation (Super Admin only) |
| **Message ID** | WhatsApp conversation message ID (shortened, full ID in tooltip) |
| **Quote ID** | WhatsApp booking quote reference ID |
| **Operation** | Operation type: **search** (blue), **rates** (yellow), **block** (orange), **book** (green) |
| **Payloads** | "View" button to expand request/response JSON |
| **Created** | Timestamp in `YYYY-MM-DD HH:mm:ss` format |

#### Viewing Payloads

1. Click the **"View"** button in the Payloads column
2. A row expands showing two code blocks side-by-side:
   - **Request Payload**: The GraphQL query/mutation sent to DOTW
   - **Response Payload**: The raw response from DOTW API

Both are displayed as formatted JSON with a dark background for readability.

#### Log Data Structure

```sql
Table: dotw_audit_logs
Columns:
  - id (int)
  - company_id (int)
  - resayil_message_id (string) — WhatsApp message ID
  - resayil_quote_id (string) — Booking quote reference
  - operation_type (enum) — search, rates, block, book
  - request_payload (json) — GraphQL input
  - response_payload (json) — DOTW response
  - created_at (timestamp)
  - updated_at (timestamp)
```

#### Role-Based Visibility

- **Super Admin**: Sees all companies' logs
- **Company Admin**: Sees only their own company's logs
- **Non-Admin**: Cannot access audit logs

#### Pagination

Logs are paginated 25 per page. Use the pagination links at the bottom to navigate.

#### Filtering Examples

**Example 1: All failed bookings today**
- Operation: `book`
- From: Today's date
- To: Today's date

**Example 2: All rate checks for Company #7**
- Company ID: `7`
- Operation: `rates`

**Example 3: Logs for a specific WhatsApp conversation**
- Message ID: `wamid.xxx` (partial match)

---

## n8n Integration

### Overview

n8n workflows integrate with DOTW via:
1. **Sanctum Bearer Token** for authentication
2. **GraphQL endpoint** at `/graphql`
3. **HTTP Node** with Bearer token header

### Step-by-Step Setup

#### Step 1: Generate API Token

1. Login to platform with **Super Admin** role
2. Navigate to `/admin/dotw` → **API Tokens**
3. Click **"Generate"** for your company
4. Copy the plaintext token (shown once)
5. Store securely (e.g., n8n environment variable)

#### Step 2: Create n8n HTTP Node

In your n8n workflow, add an **HTTP Request** node:

| Setting | Value |
|---------|-------|
| **Method** | POST |
| **URL** | `https://development.citycommerce.group/graphql` |
| **Authentication** | None (use headers below) |
| **Body** | GraphQL query/mutation (JSON) |

#### Step 3: Configure Authorization Header

In the **Headers** section of the HTTP node, add:

```
Authorization: Bearer <YOUR_TOKEN_HERE>
```

**Example** (n8n node configuration):

```json
{
  "headers": {
    "Authorization": "Bearer eyde8a67-35b7-4abd-a7e9-f8c3a1b2c3d4|6de17de8d...",
    "Content-Type": "application/json"
  }
}
```

#### Step 4: Send GraphQL Query

Set the **Body** to your GraphQL query. Examples below in [GraphQL Operations](#graphql-operations).

### Authentication Flow

```
n8n HTTP Node
  ↓ (POST /graphql with Authorization header)
Sanctum Middleware
  ↓ (Verifies Bearer token from personal_access_tokens)
GraphQL Resolver (e.g., DotwSearchHotels)
  ↓ (Extracts company_id from authenticated user)
DotwService (initialized with company_id)
  ↓ (Loads credentials from company_dotw_credentials)
DOTW V4 API
  ↓ (Returns hotel results)
DotwAuditService (logs request/response)
  ↓ (Stores in dotw_audit_logs)
Response sent back to n8n
```

### Error Handling in n8n

All DOTW responses follow the same **DotwResponseEnvelope** structure:

```json
{
  "success": true/false,
  "error": {
    "error_code": "CIRCUIT_BREAKER_OPEN",
    "error_message": "Too many recent DOTW failures",
    "error_details": "5 failures in 60 seconds",
    "action": "RETRY_IN_30_SECONDS"
  },
  "meta": {
    "trace_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "timestamp": "2026-02-21T14:30:00Z",
    "company_id": 5,
    "request_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  },
  "cached": false,
  "data": { ... }
}
```

#### Workflow Best Practices

1. **Always check `success` field**:
   ```javascript
   if (response.success === true) {
     // Process data
   } else {
     // Handle error based on response.error.action
   }
   ```

2. **Handle specific error actions**:
   ```javascript
   switch(response.error.action) {
     case "RETRY":
       // Immediate retry
     case "RETRY_IN_30_SECONDS":
       // Wait 30s and retry
     case "RECONFIGURE_CREDENTIALS":
       // Alert admin
     case "RESEARCH":
       // Run new searchHotels
     case "CANCEL":
       // Abort workflow
   }
   ```

3. **Log trace_id for debugging**:
   ```javascript
   // Include meta.trace_id in error logs for support tickets
   console.log(`Failed: ${response.error.error_message} (Trace: ${response.meta.trace_id})`);
   ```

---

## GraphQL Operations

### Endpoint

```
POST /graphql
```

**Authentication**: `Authorization: Bearer <TOKEN>`

**Content-Type**: `application/json`

### Operation 1: getCities

**Purpose**: List all cities served by DOTW for a given country.

**Frequency**: Use once per country lookup, cache results locally.

**GraphQL Query**:

```graphql
query GetCities($countryCode: String!) {
  getCities(country_code: $countryCode) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    data {
      cities {
        code
        name
      }
      total_count
    }
  }
}
```

**Variables**:

```json
{
  "countryCode": "AE"
}
```

**cURL Example**:

```bash
curl -X POST https://development.citycommerce.group/graphql \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetCities($countryCode: String!) { getCities(country_code: $countryCode) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { cities { code name } total_count } } }",
    "variables": {
      "countryCode": "AE"
    }
  }'
```

**Response** (200 OK):

```json
{
  "data": {
    "getCities": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "timestamp": "2026-02-21T14:30:00Z",
        "company_id": 5
      },
      "data": {
        "cities": [
          {
            "code": "DXB",
            "name": "Dubai"
          },
          {
            "code": "AUH",
            "name": "Abu Dhabi"
          }
        ],
        "total_count": 2
      }
    }
  }
}
```

#### n8n Workflow Template: getCities

```json
{
  "name": "Get DOTW Cities",
  "nodes": [
    {
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300],
      "typeVersion": 4.1,
      "parameters": {
        "method": "POST",
        "url": "https://development.citycommerce.group/graphql",
        "headers": {
          "Authorization": "Bearer {{ $env.DOTW_TOKEN }}",
          "Content-Type": "application/json"
        },
        "body": "{\n  \"query\": \"query GetCities($countryCode: String!) { getCities(country_code: $countryCode) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { cities { code name } total_count } } }\",\n  \"variables\": {\n    \"countryCode\": \"{{ $node.\\\"Get Country Code\\\".json.country_code }}\"\n  }\n}"
      }
    }
  ]
}
```

### Operation 2: searchHotels

**Purpose**: Search hotels by destination, check-in/check-out dates, and room configuration.

**Frequency**: Use to find available hotels. Results cached for 2.5 minutes per company.

**GraphQL Query**:

```graphql
query SearchHotels($input: SearchHotelsInput!) {
  searchHotels(input: $input) {
    success
    error {
      error_code
      error_message
      error_details
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    cached
    data {
      search_id
      destination_code
      checkin
      checkout
      total_hotels
      hotels {
        hotel_id
        hotel_name
        city_code
        star_rating
        image_url
        description
      }
    }
  }
}
```

**Variables**:

```json
{
  "input": {
    "destination": "DXB",
    "checkin": "2026-03-01",
    "checkout": "2026-03-05",
    "rooms": [
      {
        "adults": 2,
        "children": 0,
        "child_ages": []
      }
    ],
    "guest_nationality": "KW"
  }
}
```

**cURL Example**:

```bash
curl -X POST https://development.citycommerce.group/graphql \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query SearchHotels($input: SearchHotelsInput!) { searchHotels(input: $input) { success error { error_code error_message error_details action } meta { trace_id timestamp company_id } cached data { search_id destination_code checkin checkout total_hotels hotels { hotel_id hotel_name city_code star_rating image_url description } } } }",
    "variables": {
      "input": {
        "destination": "DXB",
        "checkin": "2026-03-01",
        "checkout": "2026-03-05",
        "rooms": [
          {
            "adults": 2,
            "children": 0,
            "child_ages": []
          }
        ],
        "guest_nationality": "KW"
      }
    }
  }'
```

**Response** (200 OK, from cache):

```json
{
  "data": {
    "searchHotels": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "b2c3d4e5-f6g7-8901-bcde-fg2345678901",
        "timestamp": "2026-02-21T14:31:00Z",
        "company_id": 5
      },
      "cached": true,
      "data": {
        "search_id": "SEARCH_2026_02_21_14_30_000",
        "destination_code": "DXB",
        "checkin": "2026-03-01",
        "checkout": "2026-03-05",
        "total_hotels": 45,
        "hotels": [
          {
            "hotel_id": "10001",
            "hotel_name": "Burj Al Arab",
            "city_code": "DXB",
            "star_rating": 5,
            "image_url": "https://...",
            "description": "Iconic sail-shaped luxury hotel on Palm Jumeirah"
          },
          {
            "hotel_id": "10002",
            "hotel_name": "Emirates Palace",
            "city_code": "AUH",
            "star_rating": 5,
            "image_url": "https://...",
            "description": "Opulent beachfront palace-style resort"
          }
        ]
      }
    }
  }
}
```

#### n8n Workflow Template: searchHotels

```json
{
  "name": "Search DOTW Hotels",
  "nodes": [
    {
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300],
      "parameters": {
        "method": "POST",
        "url": "https://development.citycommerce.group/graphql",
        "headers": {
          "Authorization": "Bearer {{ $env.DOTW_TOKEN }}",
          "Content-Type": "application/json"
        },
        "body": "{\n  \"query\": \"query SearchHotels($input: SearchHotelsInput!) { searchHotels(input: $input) { success error { error_code error_message error_details action } meta { trace_id timestamp company_id } cached data { search_id destination_code checkin checkout total_hotels hotels { hotel_id hotel_name city_code star_rating image_url description } } } }\",\n  \"variables\": {\n    \"input\": {\n      \"destination\": \"{{ $node.\\\"Get Destination\\\".json.city_code }}\",\n      \"checkin\": \"{{ $node.\\\"Get Dates\\\".json.checkin }}\",\n      \"checkout\": \"{{ $node.\\\"Get Dates\\\".json.checkout }}\",\n      \"rooms\": {{ JSON.stringify($node.\\\"Build Rooms\\\".json.rooms) }},\n      \"guest_nationality\": \"{{ $node.\\\"Get Guest Info\\\".json.nationality }}\"\n    }\n  }\n}"
      }
    },
    {
      "type": "n8n-nodes-base.if",
      "position": [450, 300],
      "parameters": {
        "conditions": {
          "boolean": [
            {
              "value1": "{{ $node.\\\"Search Hotels\\\".json.data.searchHotels.success }}",
              "operation": "equals",
              "value2": true
            }
          ]
        }
      },
      "onError": "continueErrorOutput"
    }
  ]
}
```

### Operation 3: getRoomRates

**Purpose**: Get available room rates for a selected hotel.

**Frequency**: Use after selecting a hotel from searchHotels results.

**GraphQL Query**:

```graphql
query GetRoomRates($searchId: String!, $hotelId: String!) {
  getRoomRates(search_id: $searchId, hotel_id: $hotelId) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    data {
      rooms {
        room_id
        room_name
        room_type
        occupancy
        rates {
          rate_id
          currency
          net_price
          gross_price
          markup_price
          rate_basis
          available
        }
      }
    }
  }
}
```

**Variables**:

```json
{
  "searchId": "SEARCH_2026_02_21_14_30_000",
  "hotelId": "10001"
}
```

**cURL Example**:

```bash
curl -X POST https://development.citycommerce.group/graphql \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetRoomRates($searchId: String!, $hotelId: String!) { getRoomRates(search_id: $searchId, hotel_id: $hotelId) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { rooms { room_id room_name room_type occupancy rates { rate_id currency net_price gross_price markup_price rate_basis available } } } } }",
    "variables": {
      "searchId": "SEARCH_2026_02_21_14_30_000",
      "hotelId": "10001"
    }
  }'
```

**Response** (200 OK):

```json
{
  "data": {
    "getRoomRates": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "c3d4e5f6-g7h8-9012-cdef-gh3456789012",
        "timestamp": "2026-02-21T14:32:00Z",
        "company_id": 5
      },
      "data": {
        "rooms": [
          {
            "room_id": "R001",
            "room_name": "Deluxe Suite",
            "room_type": "SUITE",
            "occupancy": 2,
            "rates": [
              {
                "rate_id": "RATE_R001_001",
                "currency": "AED",
                "net_price": 1500.00,
                "gross_price": 1650.00,
                "markup_price": 1980.00,
                "rate_basis": "BB",
                "available": true
              }
            ]
          }
        ]
      }
    }
  }
}
```

### Operation 4: blockRates

**Purpose**: Allocate a rate for a specific time window (3-minute expiry) before creating a booking.

**Frequency**: Use after selecting a room rate from getRoomRates.

**Important**: Must call before createPreBooking. Allocation expires after 3 minutes.

**GraphQL Mutation**:

```graphql
mutation BlockRates($input: BlockRatesInput!) {
  blockRates(input: $input) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    data {
      block_id
      expires_at
      hotel_id
      room_id
      rate_id
      currency
      price
    }
  }
}
```

**Variables**:

```json
{
  "input": {
    "search_id": "SEARCH_2026_02_21_14_30_000",
    "hotel_id": "10001",
    "room_id": "R001",
    "rate_id": "RATE_R001_001"
  }
}
```

**cURL Example**:

```bash
curl -X POST https://development.citycommerce.group/graphql \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "mutation BlockRates($input: BlockRatesInput!) { blockRates(input: $input) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { block_id expires_at hotel_id room_id rate_id currency price } } }",
    "variables": {
      "input": {
        "search_id": "SEARCH_2026_02_21_14_30_000",
        "hotel_id": "10001",
        "room_id": "R001",
        "rate_id": "RATE_R001_001"
      }
    }
  }'
```

**Response** (200 OK):

```json
{
  "data": {
    "blockRates": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "d4e5f6g7-h8i9-0123-defg-hi4567890123",
        "timestamp": "2026-02-21T14:33:00Z",
        "company_id": 5
      },
      "data": {
        "block_id": "BLOCK_2026_02_21_14_33_001",
        "expires_at": "2026-02-21T14:36:00Z",
        "hotel_id": "10001",
        "room_id": "R001",
        "rate_id": "RATE_R001_001",
        "currency": "AED",
        "price": 1980.00
      }
    }
  }
}
```

#### n8n Workflow Template: blockRates

```json
{
  "name": "Block Hotel Rate",
  "nodes": [
    {
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300],
      "parameters": {
        "method": "POST",
        "url": "https://development.citycommerce.group/graphql",
        "headers": {
          "Authorization": "Bearer {{ $env.DOTW_TOKEN }}",
          "Content-Type": "application/json"
        },
        "body": "{\n  \"query\": \"mutation BlockRates($input: BlockRatesInput!) { blockRates(input: $input) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { block_id expires_at hotel_id room_id rate_id currency price } } }\",\n  \"variables\": {\n    \"input\": {\n      \"search_id\": \"{{ $node.\\\"Previous Search\\\".json.data.searchHotels.data.search_id }}\",\n      \"hotel_id\": \"{{ $node.\\\"Select Hotel\\\".json.hotel_id }}\",\n      \"room_id\": \"{{ $node.\\\"Select Room\\\".json.room_id }}\",\n      \"rate_id\": \"{{ $node.\\\"Select Rate\\\".json.rate_id }}\"\n    }\n  }\n}"
      }
    }
  ]
}
```

### Operation 5: createPreBooking

**Purpose**: Create a pre-booking with passenger details (final step before actual booking).

**Frequency**: Use after blockRates succeeds to confirm the booking.

**Important**: Must include all required passenger fields or validation will fail.

**GraphQL Mutation**:

```graphql
mutation CreatePreBooking($input: CreatePreBookingInput!) {
  createPreBooking(input: $input) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    data {
      booking_id
      status
      confirmation_number
      hotel_id
      checkin
      checkout
      guests {
        full_name
        email
        phone
      }
    }
  }
}
```

**Variables**:

```json
{
  "input": {
    "block_id": "BLOCK_2026_02_21_14_33_001",
    "guest_salutation": "Mr",
    "guest_first_name": "Ahmed",
    "guest_last_name": "Al-Khalifa",
    "guest_email": "ahmed@example.com",
    "guest_phone": "+965501234567",
    "guests": [
      {
        "salutation": "Mr",
        "first_name": "Ahmed",
        "last_name": "Al-Khalifa",
        "nationality": "KW",
        "dob": "1990-01-15"
      }
    ]
  }
}
```

**cURL Example**:

```bash
curl -X POST https://development.citycommerce.group/graphql \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "mutation CreatePreBooking($input: CreatePreBookingInput!) { createPreBooking(input: $input) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { booking_id status confirmation_number hotel_id checkin checkout guests { full_name email phone } } } }",
    "variables": {
      "input": {
        "block_id": "BLOCK_2026_02_21_14_33_001",
        "guest_salutation": "Mr",
        "guest_first_name": "Ahmed",
        "guest_last_name": "Al-Khalifa",
        "guest_email": "ahmed@example.com",
        "guest_phone": "+965501234567",
        "guests": [
          {
            "salutation": "Mr",
            "first_name": "Ahmed",
            "last_name": "Al-Khalifa",
            "nationality": "KW",
            "dob": "1990-01-15"
          }
        ]
      }
    }
  }'
```

**Response** (200 OK):

```json
{
  "data": {
    "createPreBooking": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "e5f6g7h8-i9j0-1234-efgh-ij5678901234",
        "timestamp": "2026-02-21T14:34:00Z",
        "company_id": 5
      },
      "data": {
        "booking_id": "BOOKING_2026_02_21_14_34_001",
        "status": "PRE_BOOKED",
        "confirmation_number": "DXB-BURJ-00001",
        "hotel_id": "10001",
        "checkin": "2026-03-01",
        "checkout": "2026-03-05",
        "guests": [
          {
            "full_name": "Ahmed Al-Khalifa",
            "email": "ahmed@example.com",
            "phone": "+965501234567"
          }
        ]
      }
    }
  }
}
```

#### n8n Workflow Template: createPreBooking

```json
{
  "name": "Create Hotel Pre-Booking",
  "nodes": [
    {
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300],
      "parameters": {
        "method": "POST",
        "url": "https://development.citycommerce.group/graphql",
        "headers": {
          "Authorization": "Bearer {{ $env.DOTW_TOKEN }}",
          "Content-Type": "application/json"
        },
        "body": "{\n  \"query\": \"mutation CreatePreBooking($input: CreatePreBookingInput!) { createPreBooking(input: $input) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { booking_id status confirmation_number hotel_id checkin checkout guests { full_name email phone } } } }\",\n  \"variables\": {\n    \"input\": {\n      \"block_id\": \"{{ $node.\\\"Block Rate\\\".json.data.blockRates.data.block_id }}\",\n      \"guest_salutation\": \"{{ $node.\\\"Get Guest Info\\\".json.salutation }}\",\n      \"guest_first_name\": \"{{ $node.\\\"Get Guest Info\\\".json.first_name }}\",\n      \"guest_last_name\": \"{{ $node.\\\"Get Guest Info\\\".json.last_name }}\",\n      \"guest_email\": \"{{ $node.\\\"Get Guest Info\\\".json.email }}\",\n      \"guest_phone\": \"{{ $node.\\\"Get Guest Info\\\".json.phone }}\",\n      \"guests\": {{ JSON.stringify($node.\\\"Build Guests Array\\\".json.guests) }}\n    }\n  }\n}"
      }
    }
  ]
}
```

---

## Error Handling

### Error Response Structure

All DOTW GraphQL responses include an `error` object when `success: false`:

```json
{
  "success": false,
  "error": {
    "error_code": "API_TIMEOUT",
    "error_message": "The DOTW API did not respond within the 25-second timeout.",
    "error_details": "Connection timeout after 25s to api.dotwconnect.com",
    "action": "RETRY"
  },
  "meta": {
    "trace_id": "...",
    "timestamp": "...",
    "company_id": 5
  }
}
```

### Error Codes & Actions

| Error Code | Message | Cause | Action |
|-----------|---------|-------|--------|
| **CREDENTIALS_NOT_CONFIGURED** | DOTW credentials are not configured for this company. | Credentials missing from database | `RECONFIGURE_CREDENTIALS` |
| **CREDENTIALS_INVALID** | The provided DOTW credentials are invalid. | Wrong username, password, or company code | `RECONFIGURE_CREDENTIALS` |
| **ALLOCATION_EXPIRED** | The rate allocation has expired. | 3-minute window closed before createPreBooking | `RESEARCH` |
| **RATE_UNAVAILABLE** | The selected rate is no longer available from the supplier. | Rate sold out or changed | `RESEARCH` |
| **HOTEL_SOLD_OUT** | The hotel is fully booked for the requested dates. | No rooms left | `RESEARCH` |
| **PASSENGER_VALIDATION_FAILED** | A required passenger field is missing or invalid. | Missing guest_first_name, invalid dob, etc. | `CANCEL` |
| **API_TIMEOUT** | The DOTW API did not respond within 25 seconds. | Network timeout to DOTW servers | `RETRY` |
| **API_ERROR** | The DOTW API returned an unexpected error. | DOTW server error (5xx) | `RETRY` |
| **CIRCUIT_BREAKER_OPEN** | Too many recent DOTW failures (circuit breaker active). | 5+ failures in 60 seconds | `RETRY_IN_30_SECONDS` |
| **VALIDATION_ERROR** | A validation error on the GraphQL input arguments. | Missing required variable, wrong type | `CANCEL` |
| **INTERNAL_ERROR** | An unexpected internal server error occurred. | Bug in platform code | `RETRY` |

### Circuit Breaker

The `DotwCircuitBreakerService` monitors `searchHotels` failures:

- **Threshold**: 5 failures in 60 seconds
- **State**: Open (rejects requests for 30 seconds)
- **Action**: Return `CIRCUIT_BREAKER_OPEN` error

**n8n Handling**:

```javascript
if (response.error.action === "RETRY_IN_30_SECONDS") {
  // Wait 30 seconds
  const waitMs = 30000;
  // Then retry the same operation
}
```

### API Timeout

- **Timeout Duration**: 25 seconds
- **Applies to**: All DOTW API operations
- **Response**: `API_TIMEOUT` error

**n8n Handling**:

```javascript
if (response.error.error_code === "API_TIMEOUT") {
  // Retry immediately (transient error)
  // Max 3 retries recommended
}
```

### Debugging with trace_id

Every response includes a `trace_id` for log correlation:

```javascript
console.log(`Operation failed. Trace ID: ${response.meta.trace_id}`);
// Support team can search logs: grep "trace_id" storage/logs/dotw.log
```

**Check server logs**:

```bash
# SSH to server
ssh citycomm

# View DOTW logs (last 50 lines)
tail -50 /home/citycomm/development.citycommerce.group/storage/logs/dotw.log

# Search for trace_id
grep "a1b2c3d4-e5f6-7890-abcd-ef1234567890" /home/citycomm/development.citycommerce.group/storage/logs/dotw.log
```

---

## Deployment & Environment

### Environment Variables

Required for DOTW module:

```bash
# .env

# Application
APP_KEY=base64:...
APP_ENV=production

# DOTW (Fallback — credentials now loaded per-company from DB)
DOTW_BASE_URL=https://api.dotwconnect.com/xml
DOTW_USERNAME=fallback_username
DOTW_PASSWORD=fallback_password
DOTW_COMPANY_CODE=fallback_code
DOTW_TIMEOUT=25

# Database (primary)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=laravel_testing
DB_USERNAME=laravel_user
DB_PASSWORD=db_password

# Sanctum (for API tokens)
SANCTUM_STATEFUL_DOMAINS=development.citycommerce.group
SANCTUM_GUARD=web
```

### Database Migrations

Ensure all DOTW-related tables are migrated:

```bash
php artisan migrate --force
```

**Tables created**:

- `company_dotw_credentials` — Per-company DOTW API credentials (encrypted)
- `dotw_audit_logs` — Audit trail of all DOTW operations
- `personal_access_tokens` — Sanctum tokens (for n8n auth)

### Encryption Key

Credentials are encrypted using `APP_KEY`. **Important**:

- If you rotate `APP_KEY`, existing credentials become unreadable
- Always backup `APP_KEY` before rotation
- Test in staging environment first

**To update credentials after key rotation**:

```php
// tinker
$credential = CompanyDotwCredential::find(1);
$credential->update([
    'dotw_username' => 'new_username',
    'dotw_password' => 'new_password',
]);
```

### Caching

SearchHotels results are cached per company:

- **Cache Key**: `dotw_search_{company_id}_{destination}_{checkin}_{checkout}_{md5_rooms_hash}`
- **TTL**: 2.5 minutes
- **Cache Driver**: Configured in `config/cache.php` (default: file)

**To clear cache**:

```bash
php artisan cache:clear

# Or specific DOTW cache:
php artisan tinker
>>> Cache::forget('dotw_search_5_DXB_2026-03-01_2026-03-05_...');
```

### Logging

DOTW module logs to dedicated channel:

- **Log File**: `storage/logs/dotw.log`
- **Channel**: `dotw` (in `config/logging.php`)
- **Level**: INFO and above

**View logs**:

```bash
tail -f storage/logs/dotw.log

# Or via server
ssh citycomm
tail -f /home/citycomm/development.citycommerce.group/storage/logs/dotw.log
```

---

## Troubleshooting

### Issue: "DOTW credentials are not configured"

**Symptom**: GraphQL queries return `CREDENTIALS_NOT_CONFIGURED` error.

**Solution**:
1. Verify company has credentials saved in database:
   ```php
   php artisan tinker
   >>> CompanyDotwCredential::where('company_id', 5)->first();
   ```

2. If missing, configure via `/settings` → DOTW tab or API endpoint

3. Verify credentials are valid with DOTW support

### Issue: Circuit Breaker Open

**Symptom**: `searchHotels` returns `CIRCUIT_BREAKER_OPEN` error.

**Solution**:
1. Wait 30 seconds before retrying
2. Check server logs for root cause:
   ```bash
   grep "Circuit breaker" storage/logs/dotw.log
   ```

3. If persistent, contact DOTW support about API stability

### Issue: API Timeout (25 seconds)

**Symptom**: `getRoomRates` or `blockRates` timeout repeatedly.

**Solution**:
1. Check network connectivity to `api.dotwconnect.com`:
   ```bash
   curl -I https://api.dotwconnect.com/xml
   ```

2. Try operation again (transient network error)

3. If persistent, DOTW API may be down — check their status page

### Issue: Passenger Validation Failed

**Symptom**: `createPreBooking` returns `PASSENGER_VALIDATION_FAILED`.

**Solution**:
1. Verify all required fields are provided:
   - `guest_salutation` (Mr, Ms, Dr, etc.)
   - `guest_first_name` (non-empty)
   - `guest_last_name` (non-empty)
   - `guest_email` (valid email format)
   - `guest_phone` (valid phone format)
   - `guests[].dob` (YYYY-MM-DD format, past date)

2. Check error response for specific missing field:
   ```json
   "error_details": "Missing required field: guest_first_name"
   ```

### Issue: Token Revoked / 401 Unauthorized

**Symptom**: n8n requests fail with 401 Unauthorized.

**Solution**:
1. Check if token was revoked:
   ```php
   php artisan tinker
   >>> User::find(5)->tokens;
   ```

2. If token missing, regenerate new one via `/admin/dotw` → API Tokens

3. Update n8n Bearer token to new value

4. Test connection again

### Issue: Rate Expired (3-minute window)

**Symptom**: `createPreBooking` returns `ALLOCATION_EXPIRED`.

**Solution**:
1. Verify `blockRates` was called less than 3 minutes ago
2. If too much time passed, call `searchHotels` again to get fresh rates
3. Implement timeout logic in n8n:
   ```javascript
   const blockTime = new Date(blockRatesResponse.data.blockRates.data.expires_at);
   if (new Date() > blockTime) {
     // Re-search and re-block
   }
   ```

### Issue: Markup Percent Not Applied

**Symptom**: Room prices don't include configured markup percentage.

**Solution**:
1. Verify markup_percent saved in credentials:
   ```php
   php artisan tinker
   >>> CompanyDotwCredential::where('company_id', 5)->first()->markup_percent;
   ```

2. Default is 20% if not set
3. Markup is applied by `DotwService` in `getRoomRates` and `createPreBooking`
4. Formula: `gross_price * (1 + markup_percent / 100)`

### Issue: Audit Logs Not Appearing

**Symptom**: No entries in `/admin/dotw` → Audit Logs after operations.

**Solution**:
1. Verify operations completed successfully (check `success: true`)
2. Check database for audit entries:
   ```php
   php artisan tinker
   >>> DotwAuditLog::latest()->first();
   ```

3. If no entries, verify `DotwAuditService` is being called in GraphQL resolvers
4. Check `dotw` log channel for errors

### Getting Help

Include the following information in support tickets:

1. **trace_id** from error response
2. **Operation type** (getCities, searchHotels, etc.)
3. **Company ID**
4. **Timestamp** of the failure
5. **Server logs** (grep by trace_id):
   ```bash
   grep "TRACE_ID_HERE" storage/logs/dotw.log
   ```

---

## API Reference Summary

### REST Endpoints

```
POST   /api/admin/companies/{companyId}/dotw-credentials       Store credentials
GET    /api/admin/companies/{companyId}/dotw-credentials       Get credential status
```

### GraphQL Endpoint

```
POST   /graphql
       Authorization: Bearer <SANCTUM_TOKEN>
       Content-Type: application/json
```

### GraphQL Queries

```
query getCities(countryCode: String!)
query searchHotels(input: SearchHotelsInput!)
query getRoomRates(searchId: String!, hotelId: String!)
```

### GraphQL Mutations

```
mutation blockRates(input: BlockRatesInput!)
mutation createPreBooking(input: CreatePreBookingInput!)
```

---

## Quick Reference

### Common Workflow Sequence

1. **getCities** → Get city codes for a country
2. **searchHotels** → Find hotels for destination/dates
3. **getRoomRates** → Get room options and prices
4. **blockRates** → Reserve a rate (3-min expiry)
5. **createPreBooking** → Finalize booking with guest details

### cURL Template

```bash
DOTW_TOKEN="your_sanctum_token"
GRAPHQL_URL="https://development.citycommerce.group/graphql"

curl -X POST "$GRAPHQL_URL" \
  -H "Authorization: Bearer $DOTW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "YOUR_GRAPHQL_QUERY",
    "variables": { "YOUR": "VARIABLES" }
  }'
```

### n8n Template

Store your token as an environment variable in n8n:
- Variable name: `DOTW_TOKEN`
- Value: Your Sanctum token from `/admin/dotw` API Tokens tab

All HTTP nodes can then use:
```
Authorization: Bearer {{ $env.DOTW_TOKEN }}
```

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-02-21 | Initial publication. Covers credential management, token generation, audit logs, GraphQL operations, and n8n integration. |

---

## Support

For issues or questions:
1. Check [Troubleshooting](#troubleshooting) section
2. Review server logs with trace_id
3. Contact development team with trace_id and operation details
