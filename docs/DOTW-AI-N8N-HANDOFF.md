# DOTW AI + n8n Integration Handoff Document

**Purpose**: Comprehensive guide for troubleshooting the n8n + Laravel integration for DOTW hotel booking AI agent.

**Scope**: Complete pipeline from WhatsApp (Resayil) → n8n AI Agent → Laravel API → DOTW XML API

**Version**: 1.0
**Date**: 2026-03-28
**Framework**: Laravel 11, n8n Workflow, Resayil WhatsApp
**Server**: development.citycommerce.group
**Database**: `citycomm_city-tour-test`

---

## 1. Architecture Overview

### High-Level Pipeline

```
WhatsApp User (Resayil)
         ↓
  Resayil Trigger (n8n)
         ↓
   Extract: fromNumber, body
         ↓
DOTW AI Agent (n8n LangChain)
  - System message: bilingual (Arabic/English)
  - Maintains conversation history (WindowBufferMemory)
  - Single tool: dotwai_agent
         ↓
       POST /api/dotwai/agent-b2c
  (Laravel HTTP Request Tool)
         ↓
      DotwAIContext Middleware
  (Resolve phone → company → credentials)
         ↓
    AgentController (Router)
  - 8 actions: search, details, book, pay, cancel, status, history, voucher
         ↓
   Appropriate Service Layer
  (HotelSearchService, BookingService, etc.)
         ↓
       DotwService
  (DOTW XML API client)
         ↓
     DOTW XML Response
         ↓
  Laravel → DotwAIResponse
  (JSON with whatsappMessage + sessionContext)
         ↓
    n8n Processes Response
  (Extracts whatsappMessage)
         ↓
  Resayil Send Node
  (WhatsApp message back to user)
         ↓
   WhatsApp User Receives Reply
```

### Key Design Decisions

1. **Single Tool Pattern**: n8n AI agent has one tool (`dotwai_agent`). All actions routed via `action` parameter.
2. **Session State Management**: Per-phone session cached for 60 minutes (rolling TTL). Tracks stage, search results, prebook key, expiry timestamps.
3. **Bilingual Responses**: All WhatsApp messages include Arabic + English. System message instructs AI to respond in user's language.
4. **No Tool Registration in n8n UI**: Tool is defined inline in the HTTP Request node. Tool name must be `dotwai_agent` (alphanumeric + underscore only).
5. **DOTW Time Constraints Enforced in Laravel**: Search TTL (10 min), prebook expiry (30 min), and rate blocking windows validated per-call.

---

## 2. n8n Workflow Structure

### Node List and Flow

| Node Name | Type | Purpose | Outgoing |
|-----------|------|---------|----------|
| **Resayil Trigger** | CUSTOM.resayilTrigger | Listen for WhatsApp messages | → DOTW Agent |
| **DOTW Agent** | @n8n/n8n-nodes-langchain.agent | AI orchestration + tool calling | → Send message |
| **OpenAI Chat Model** | @n8n/n8n-nodes-langchain.lmChatOpenAi | LLM (qwen3.5, T=0.3) | → Agent (ai_languageModel) |
| **Window Buffer Memory** | @n8n/n8n-nodes-langchain.memoryBufferWindow | Conversation context (20-msg window) | → Agent (ai_memory) |
| **dotwai_agent** | @n8n/n8n-nodes-langchain.toolHttpRequest | HTTP POST to `/api/dotwai/agent-b2c` | → Agent (ai_tool) |
| **Send a text message** | CUSTOM.resayil | Send WhatsApp reply | (terminal) |

### Workflow JSON Reference

Location: `/c/Users/User/OneDrive - City Travelers/soud-laravel/public/downloads/api-templates/dotw-b2c-ai-agent-workflow.json`

Import this JSON directly into n8n to get the full working workflow.

### Critical n8n Configuration

#### 1. Resayil Trigger Node

```json
{
  "device": "68ac2c4c80090e92ccbf6d74",  // Device ID from Resayil
  "events": ["message:in:new"],
  "sampleEvent": "inbound-text"
}
```

**Output** (available to downstream nodes):
```json
{
  "data": {
    "fromNumber": "+965XXXXXXXX",  // Customer's WhatsApp number
    "body": "search for hotels in Dubai"  // Customer's message text
  }
}
```

#### 2. DOTW Hotel Booking Agent (LangChain Agent Node)

```json
{
  "promptType": "define",
  "text": "={{ $json.data.body }}",  // Customer message
  "options": {
    "systemMessage": "[full bilingual system message from dotwai-system-message.md]"
  }
}
```

**Input**: Customer message
**Process**:
- Loads system message (bilingual, with tool definition)
- Maintains conversation history (20-message window)
- Decides when to call the `dotwai_agent` tool
- Formats output as natural language response

**Output**:
```json
{
  "message": "[AI's natural response]"  // Only the AI text, no tool output yet
}
```

**Critical Note**: This node does NOT send tool output to WhatsApp. It waits for the tool to return, then reformats the response. See section below.

#### 3. Window Buffer Memory Node

```json
{
  "sessionIdType": "customKey",
  "sessionKey": "={{ $('Resayil Trigger').item.json.data.fromNumber }}",
  "contextWindowLength": 20
}
```

**Purpose**: Store last 20 messages per phone number to maintain conversation context.

**Key Expression**: `$('Resayil Trigger').item.json.data.fromNumber`
- References the Resayil Trigger node's data
- Uses the customer's phone as the session key
- Critical: This must match exactly. If phone is not extracted correctly, conversation history is lost.

#### 4. dotwai_agent Tool (HTTP Request Node)

```json
{
  "method": "POST",
  "url": "https://development.citycommerce.group/api/dotwai/agent-b2c",
  "authentication": "genericCredentialType",
  "genericAuthType": "httpHeaderAuth",
  "sendBody": true,
  "specifyBody": "json",
  "jsonBody": "={\n  \"telephone\": \"{{ $('Resayil Trigger').item.json.data.fromNumber }}\",\n  \"action\": \"{action}\",\n  \"params\": {params}\n}",
  "placeholderDefinitions": {
    "values": [
      {
        "name": "action",
        "description": "Operation type: search, details, book, pay, cancel, status, history, voucher",
        "type": "string"
      },
      {
        "name": "params",
        "description": "JSON object with action-specific parameters",
        "type": "json"
      }
    ]
  }
}
```

**Placeholders** (extracted from n8n expressions by the agent):
- `{action}` — dynamically set by LLM
- `{params}` — dynamically set by LLM

**Example Call** (generated by AI agent):
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "search",
  "params": {
    "city": "Dubai",
    "check_in": "2026-06-01",
    "check_out": "2026-06-05",
    "occupancy": [{"adults": 2, "children_ages": []}],
    "hotel": null,
    "star_rating": null,
    "meal_type": null,
    "refundable": null,
    "price_min": null,
    "price_max": null,
    "nationality": null
  }
}
```

#### 5. Send a text message (Resayil Output Node)

```json
{
  "phone": "={{ $('Resayil Trigger').item.json.data.fromNumber }}",
  "options": {}
}
```

**Input**: The `whatsappMessage` field from the agent response.

**Critical Note**: The node must receive the `whatsappMessage` from the agent's final output, not the raw tool response.

### How the Agent Uses the Tool

The AI agent (LangChain) is instructed by the system message to:
1. Parse the customer's natural language request
2. Determine the appropriate action (`search`, `details`, `book`, etc.)
3. Extract or infer the required parameters
4. Call the `dotwai_agent` tool with `action` and `params`
5. Receive the Laravel response (JSON with `whatsappMessage`)
6. Format a natural language reply combining the tool output and conversational context

**Flow Example**:

Customer: "I want to book a hotel in Dubai for June 1-5, 2 adults"

→ Agent parses → calls tool with action=`search`, params={city, check_in, check_out, occupancy}

→ Laravel returns: `{success: true, data: {hotels: [...]}, whatsappMessage: "Found 8 hotels...", sessionContext: {...}}`

→ Agent reads `whatsappMessage`, adds personal touch if needed, sends back to user

### Known n8n Gotchas

#### 1. Tool Name Must Be Alphanumeric + Underscore

**WRONG**: `dotwai-agent`, `dotwai:agent`, `dotw ai agent`
**CORRECT**: `dotwai_agent`

n8n's LangChain integration uses the tool name as a parameter. Hyphens, colons, and spaces break the tool invocation.

#### 2. Session Key Expression Must Reference Exact Node Path

**WRONG**:
```
$json.data.fromNumber
Resayil Trigger.item.json.data.fromNumber
```

**CORRECT**:
```
$('Resayil Trigger').item.json.data.fromNumber
```

The exact syntax `$('NodeName')` is required for cross-node references in n8n.

#### 3. HTTP Tool Body Expressions

The `jsonBody` field uses `{{ }}` for n8n expressions. Placeholders `{action}` and `{params}` are literal strings replaced by the LangChain agent when calling the tool.

**NOT an n8n expression**:
```json
"jsonBody": "={\n  \"action\": \"{action}\",\n  \"params\": {params}\n}"
```

The `=` prefix means "evaluate as expression", but `{action}` and `{params}` are placeholders defined in the tool definition.

#### 4. Credentials Must Be Configured

- **Resayil API Key**: Device authentication for WhatsApp messages
- **LLM Credentials**: OpenAI API key (or equivalent) for the qwen3.5 model
- **HTTP Header Auth**: Any required headers for the Laravel endpoint (usually empty in development)

If any credential is missing, the workflow stalls silently with "invalid credentials" errors in the n8n logs.

#### 5. Tool Output Goes Back to Agent, Not to Send Node

The `Send a text message` node must receive input from the **Agent node's output**, not the tool node directly.

**Connection**: Agent → Send message (not Tool → Send message)

This ensures the AI has a chance to format the response naturally.

---

## 3. Laravel API — POST /api/dotwai/agent-b2c

### Endpoint Specification

**URL**: `POST https://development.citycommerce.group/api/dotwai/agent-b2c`

**Middleware Stack**:
1. `dotwai.resolve` — ResolveDotwAIContext middleware
2. Form request validation — AgentRequest

### Request Format

```json
{
  "telephone": "+965XXXXXXXX",
  "action": "search|details|book|pay|cancel|status|history|voucher",
  "params": {
    // action-specific parameters (see below)
  }
}
```

### All 8 Actions

#### Action: search

**Purpose**: Search for hotels by city, date range, occupancy, and optional filters.

**Required Params**:
- `city`: string — city name (e.g., "Dubai", "Doha")
- `check_in`: string (YYYY-MM-DD) — check-in date
- `check_out`: string (YYYY-MM-DD) — check-out date
- `occupancy`: array of objects — room occupancy breakdown

**Optional Params**:
- `hotel`: string — hotel name (for filtering)
- `star_rating`: int (1-5) — filter by star rating
- `meal_type`: string — "breakfast", "half_board", "full_board", "all_inclusive"
- `refundable`: bool — true = refundable only, false = all, null = all
- `price_min`: float — minimum price in KWD
- `price_max`: float — maximum price in KWD
- `nationality`: string — guest nationality code

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "search",
  "params": {
    "city": "Dubai",
    "check_in": "2026-06-01",
    "check_out": "2026-06-05",
    "occupancy": [
      {"adults": 2, "children_ages": []}
    ],
    "hotel": null,
    "star_rating": null,
    "meal_type": null,
    "refundable": null,
    "price_min": null,
    "price_max": null,
    "nationality": null
  }
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "hotels": [
      {
        "option_number": 1,
        "id": "23456",
        "name": "Hilton Dubai Creek",
        "city": "Dubai",
        "star_rating": 5,
        "cheapest_price": 45.000,
        "currency": "KWD",
        "meal_type": "Breakfast",
        "is_refundable": true
      },
      {
        "option_number": 2,
        "id": "34567",
        "name": "The Ritz-Carlton Dubai",
        "city": "Dubai",
        "star_rating": 5,
        "cheapest_price": 65.000,
        "currency": "KWD",
        "meal_type": "Breakfast",
        "is_refundable": false
      }
    ],
    "total_found": 12,
    "showing": 10,
    "city_name": "Dubai",
    "check_in": "2026-06-01",
    "check_out": "2026-06-05",
    "sessionContext": {
      "stage": "searching",
      "summary": "Search results available: 10 hotels found for Dubai.",
      "next_actions": [
        "select a hotel from results",
        "search again with different filters"
      ]
    }
  },
  "whatsappMessage": "نتائج البحث | Search Results\n──────────────────────────────\n\n1. Hilton Dubai Creek\n   ★★★★★ | Dubai\n   KWD 45.000 - Breakfast\n   قابل للاسترداد | Refundable\n\n2. The Ritz-Carlton Dubai\n   ★★★★★ | Dubai\n   KWD 65.000 - Breakfast\n   غير قابل للاسترداد | Non-Refundable",
  "whatsappOptions": []
}
```

**Error Response** (HTTP 422):
```json
{
  "success": false,
  "error": {
    "code": "CITY_NOT_FOUND",
    "message": "Could not resolve city: Xyz",
    "suggestedAction": "Ask the user to provide a different city name or check spelling."
  },
  "whatsappMessage": "عذرا، لم نتمكن من العثور على المدينة المطلوبة. حاول كتابة اسم المدينة بشكل مختلف.\nSorry, we could not find the requested city. Try a different spelling.",
  "whatsappOptions": []
}
```

---

#### Action: details

**Purpose**: Get room details, rates, and cancellation policies for a specific hotel from search results.

**Required Params**:
- `option`: int — hotel number from search results (1-based indexing)

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "details",
  "params": {
    "option": 2
  }
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "hotel": {
      "id": "34567",
      "name": "The Ritz-Carlton Dubai",
      "city": "Dubai",
      "star_rating": 5,
      "description": "Ultra-luxury beachfront property..."
    },
    "rooms": [
      {
        "room_type": "Superior Room",
        "occupancy_max": 2,
        "price_per_night": 65.000,
        "total_price": 260.000,
        "currency": "KWD",
        "meal_type": "Breakfast",
        "is_refundable": false,
        "cancellation_deadline": "2026-05-20T23:59:59Z",
        "penalty_percent": 100
      },
      {
        "room_type": "Deluxe Room",
        "occupancy_max": 2,
        "price_per_night": 85.000,
        "total_price": 340.000,
        "currency": "KWD",
        "meal_type": "Breakfast",
        "is_refundable": true,
        "cancellation_deadline": "2026-06-01T23:59:59Z",
        "penalty_percent": 50
      }
    ],
    "sessionContext": {
      "stage": "viewing_details",
      "summary": "Viewing room details for The Ritz-Carlton Dubai.",
      "next_actions": [
        "select a room to book",
        "search for a different hotel"
      ]
    }
  },
  "whatsappMessage": "تفاصيل الفندق | Hotel Details\n...",
  "whatsappOptions": []
}
```

---

#### Action: book

**Purpose**: Lock a hotel rate for 30 minutes (prebook). Rate is held by DOTW, customer must pay or confirm within 30 min.

**Required Params**:
- `option`: int — hotel number from search results

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "book",
  "params": {
    "option": 2
  }
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
    "hotel_name": "The Ritz-Carlton Dubai",
    "hotel_id": "34567",
    "check_in": "2026-06-01",
    "check_out": "2026-06-05",
    "total_amount": 260.000,
    "currency": "KWD",
    "room_type": "Superior Room",
    "meal_type": "Breakfast",
    "is_refundable": false,
    "needs_payment": true,
    "cancellation_deadline": "2026-05-20T23:59:59Z",
    "sessionContext": {
      "stage": "prebooked",
      "summary": "Rate locked for The Ritz-Carlton Dubai. Prebook key: DOTWAI-550E8400-E29B-41D4-A716-446655440000.",
      "next_actions": [
        "get payment link",
        "cancel booking"
      ]
    }
  },
  "whatsappMessage": "تم تأمين السعر | Rate Locked\n\nالفندق | Hotel: The Ritz-Carlton Dubai\nتاريخ الدخول | Check-in: 2026-06-01\nتاريخ المغادرة | Check-out: 2026-06-05\nالسعر الإجمالي | Total: KWD 260.000\nصلاحية السعر | Rate valid for: 30 minutes",
  "whatsappOptions": [
    "Get payment link",
    "Cancel booking"
  ]
}
```

**Error Response** (search or prebook expired):
```json
{
  "success": false,
  "error": {
    "code": "SEARCH_EXPIRED",
    "message": "Search results expired (10 min limit)",
    "suggestedAction": "Ask the user to initiate a new hotel search."
  },
  "whatsappMessage": "انتهت صلاحية نتائج البحث. يرجى البحث مجدداً.\nSearch results have expired (10 min limit). Please search again.",
  "whatsappOptions": []
}
```

---

#### Action: pay

**Purpose**: Generate a payment link for a prebooked booking (B2C flow). Booking auto-confirms after payment.

**Required Params**: (empty)

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "pay",
  "params": {}
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "payment_link": "https://myfatoorah.checkout.com/...",
    "payment_id": "PY-1234567890",
    "amount": 260.000,
    "currency": "KWD",
    "expires_at": "2026-03-30T12:00:00Z",
    "hotel_name": "The Ritz-Carlton Dubai",
    "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
    "sessionContext": {
      "stage": "awaiting_payment",
      "summary": "Payment link sent for The Ritz-Carlton Dubai. Awaiting payment confirmation.",
      "next_actions": [
        "check if payment is complete",
        "cancel booking"
      ]
    }
  },
  "whatsappMessage": "رابط الدفع | Payment Link\n\nاضغط على الرابط أدناه لإتمام الدفع:\nhttps://myfatoorah.checkout.com/...\n\nالمبلغ | Amount: KWD 260.000\nصلاحية الرابط | Link valid until: 2026-03-30 12:00 GMT+3",
  "whatsappOptions": []
}
```

**Error Response** (no active prebook):
```json
{
  "success": false,
  "error": {
    "code": "SESSION_EMPTY",
    "message": "No active prebook in session",
    "suggestedAction": "Ask the customer to search for a hotel and complete a booking first."
  },
  "whatsappMessage": "لا توجد جلسة نشطة. يرجى البحث عن فندق أولاً.\nNo active session found. Please start by searching for a hotel.",
  "whatsappOptions": []
}
```

---

#### Action: cancel

**Purpose**: Two-step cancellation (preview penalty, then confirm).

**Step 1 — Preview Penalty**:

**Required Params**:
- `confirm`: "no" — preview mode

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "cancel",
  "params": {
    "confirm": "no"
  }
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
    "hotel_name": "The Ritz-Carlton Dubai",
    "penalty_amount": 50.000,
    "penalty_percent": 50,
    "refund_amount": 210.000,
    "currency": "KWD",
    "can_cancel": true,
    "reason": "Free cancellation if cancelled before 2026-05-20"
  },
  "whatsappMessage": "معاينة الإلغاء | Cancellation Preview\n\nالفندق | Hotel: The Ritz-Carlton Dubai\nالمبلغ المدفوع | Paid Amount: KWD 260.000\nالغرامة | Penalty: KWD 50.000 (50%)\nالاسترداد | Refund: KWD 210.000\n\nهل تريد المتابعة؟ | Do you want to proceed?",
  "whatsappOptions": [
    "Confirm cancellation",
    "Keep booking"
  ]
}
```

**Step 2 — Confirm Cancellation**:

**Required Params**:
- `confirm`: "yes"
- `penalty_amount`: float — penalty amount from preview

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "cancel",
  "params": {
    "confirm": "yes",
    "penalty_amount": 50.000
  }
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
    "status": "cancelled",
    "refund_amount": 210.000,
    "currency": "KWD"
  },
  "whatsappMessage": "تم إلغاء الحجز | Booking Cancelled\n\nشكراً لاستخدام خدمتنا.\nYour booking has been cancelled.\nRefund: KWD 210.000",
  "whatsappOptions": []
}
```

---

#### Action: status

**Purpose**: Check the current status of a booking.

**Optional Params**:
- `prebook_key`: string — if not in session, use this key

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "status",
  "params": {}
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
    "status": "confirmed",
    "hotel_name": "The Ritz-Carlton Dubai",
    "check_in": "2026-06-01",
    "check_out": "2026-06-05",
    "total_amount": 260.000,
    "currency": "KWD",
    "confirmation_no": "TRC-20260601-12345",
    "cancellation_deadline": "2026-05-20T23:59:59Z"
  },
  "whatsappMessage": "حالة الحجز | Booking Status\n\nحالة | Status: Confirmed\nرقم التأكيد | Confirmation: TRC-20260601-12345\nالفندق | Hotel: The Ritz-Carlton Dubai\nآخر موعد للإلغاء | Cancel before: 2026-05-20",
  "whatsappOptions": []
}
```

---

#### Action: history

**Purpose**: Get booking history for the phone number.

**Optional Params**:
- `status`: string — filter by status (confirmed, cancelled, expired, etc.)
- `page`: int — pagination (default 1)
- `per_page`: int — results per page (default 10)

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "history",
  "params": {
    "status": null,
    "page": 1,
    "per_page": 10
  }
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "bookings": [
      {
        "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
        "hotel_name": "The Ritz-Carlton Dubai",
        "status": "confirmed",
        "check_in": "2026-06-01",
        "check_out": "2026-06-05",
        "created_at": "2026-03-28T10:30:00Z"
      }
    ],
    "total": 1,
    "page": 1,
    "per_page": 10
  },
  "whatsappMessage": "سجل الحجوزات | Booking History\n\nعدد الحجوزات | Total Bookings: 1\n\n1. The Ritz-Carlton Dubai\n   Status: Confirmed\n   2026-06-01 to 2026-06-05",
  "whatsappOptions": []
}
```

---

#### Action: voucher

**Purpose**: Resend the booking confirmation voucher via WhatsApp.

**Required Params**: (empty)

**Example Request**:
```json
{
  "telephone": "+965XXXXXXXX",
  "action": "voucher",
  "params": {}
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "data": {
    "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
    "success": true,
    "voucher_url": "https://development.citycommerce.group/api/dotwai/download_voucher?prebook_key=..."
  },
  "whatsappMessage": "تم إرسال القسيمة | Voucher Resent\n\nشكراً لاستخدام خدمتنا.\nYour booking voucher has been resent.",
  "whatsappOptions": []
}
```

---

### Session State Management

#### Cache Structure

**Cache Key**: `dotwai_session_{phone}`
**TTL**: 60 minutes (rolling — refreshed on each request)
**Store**: Redis (or file cache in development)

#### Session Data Structure

```json
{
  "stage": "prebooked",
  "search_cached_at": "2026-03-28T10:30:00+00:00",
  "search_city": "Dubai",
  "search_hotel_count": 10,
  "last_search_params": {
    "city": "Dubai",
    "check_in": "2026-06-01",
    "check_out": "2026-06-05",
    "occupancy": [{"adults": 2, "children_ages": []}]
  },
  "selected_hotel_id": "34567",
  "selected_hotel_name": "The Ritz-Carlton Dubai",
  "prebook_key": "DOTWAI-550E8400-E29B-41D4-A716-446655440000",
  "prebook_expires_at": "2026-03-28T11:00:00+00:00",
  "last_option": 2
}
```

#### Session Lifecycle

| Stage | When | Data | TTL Check |
|-------|------|------|-----------|
| `idle` | Session empty | `{}` | N/A |
| `searching` | After search action | search_cached_at, hotels count | Check every call (10 min) |
| `viewing_details` | After details action | selected_hotel_id, selected_hotel_name | Implicit via search_cached_at |
| `prebooked` | After book action | prebook_key, prebook_expires_at | Check every call (30 min) |
| `awaiting_payment` | After pay action | prebook_key, prebook_expires_at | Check at pay/confirm |
| `confirmed` | After payment success | booking_ref, selected_hotel_name | No expiry (booking complete) |
| `cancelling` | After cancel (confirm=no) | prebook_key | Session resets after confirm=yes |

#### TTL and Expiry Rules

**Search Expiry** (10 minutes):
```php
// In AgentSessionService::isSearchExpired()
return $cachedAt->diffInSeconds(now()) > 600;
```

**Prebook Expiry** (30 minutes):
```php
// In AgentSessionService::isPrebookExpired()
return now()->isAfter(Carbon::parse($session['prebook_expires_at']));
```

Both are validated **per action call**. If expired, the action returns error code `SEARCH_EXPIRED` or `PREBOOK_EXPIRED`.

---

### Error Response Format

All errors return HTTP 422 with standardized structure:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE_CONSTANT",
    "message": "Technical error message for logging",
    "suggestedAction": "What the AI agent should do next"
  },
  "whatsappMessage": "Arabic/English message for the customer",
  "whatsappOptions": []
}
```

**Error codes** (from DotwAIResponse class):

| Code | HTTP | Meaning | Example |
|------|------|---------|---------|
| `PHONE_NOT_FOUND` | 422 | Phone not registered | Agent phone not in system |
| `COMPANY_NOT_FOUND` | 422 | Company not found | Agent assigned to invalid branch |
| `CREDENTIALS_NOT_FOUND` | 422 | DOTW not configured | Admin hasn't set up DOTW credentials |
| `TRACK_DISABLED` | 422 | B2B/B2C not enabled | B2C is turned off for this company |
| `CITY_NOT_FOUND` | 422 | City not recognized | "Xyz" doesn't match any DOTW city |
| `HOTEL_NOT_FOUND` | 422 | Hotel option invalid | Option 99 selected but only 10 results |
| `NO_RESULTS` | 422 | Search returned nothing | No hotels match the criteria |
| `DOTW_API_ERROR` | 422 | DOTW API failed | DOTW XML service timeout/error |
| `VALIDATION_ERROR` | 422 | Missing required params | city or check_in missing |
| `SEARCH_EXPIRED` | 422 | Search > 10 min old | Search cache TTL exceeded |
| `SESSION_EMPTY` | 422 | No session for phone | Details/book called before search |
| `PREBOOK_NOT_FOUND` | 422 | Prebook key invalid | Booking not found in database |
| `PREBOOK_EXPIRED` | 422 | Prebook > 30 min old | Rate lock released by DOTW |
| `INSUFFICIENT_CREDIT` | 422 | B2B agent low credit | Agent can't afford booking |
| `PAYMENT_REQUIRED` | 422 | Payment needed | B2C track requires payment before confirm |
| `PAYMENT_FAILED` | 422 | Payment gateway error | MyFatoorah/Knet rejected charge |
| `BOOKING_FAILED` | 422 | DOTW confirm failed | DOTW XML error during confirm |
| `RATE_UNAVAILABLE` | 422 | Rate no longer available | Re-blocking failed (sold out) |
| `ALREADY_CONFIRMED` | 422 | Booking already confirmed | Can't re-confirm a confirmed booking |
| `CANCELLATION_NOT_ALLOWED` | 422 | Can't cancel this status | Only confirmed/pending can cancel |
| `CANCELLATION_FAILED` | 422 | DOTW cancellation error | DOTW XML error during cancellation |

---

## 4. Response Format

### Success Response Structure

```json
{
  "success": true,
  "data": {
    // Action-specific data (hotels, prebook_key, status, etc.)
    "sessionContext": {
      "stage": "searching|prebooked|confirmed|etc",
      "summary": "Human-readable stage summary",
      "next_actions": ["action 1", "action 2"]
    }
  },
  "whatsappMessage": "Bilingual Arabic/English message for customer",
  "whatsappOptions": ["Option 1", "Option 2"]
}
```

### Error Response Structure

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Technical error (for logging)",
    "suggestedAction": "What AI should do next"
  },
  "whatsappMessage": "Bilingual message for customer",
  "whatsappOptions": []
}
```

### Session Context

Every successful response includes a `sessionContext` that tells the AI where the customer is in the booking journey:

```json
{
  "sessionContext": {
    "stage": "searching",
    "summary": "Search results available: 10 hotels found for Dubai.",
    "next_actions": [
      "select a hotel from results",
      "search again with different filters"
    ]
  }
}
```

**Stages and Next Actions**:

| Stage | Summary | Next Actions |
|-------|---------|--------------|
| `idle` | No active session. Customer can start a new search. | `["search for a hotel"]` |
| `searching` | Search results available: N hotels found. | `["select a hotel from results", "search again with different filters"]` |
| `viewing_details` | Viewing room details for HOTEL_NAME. | `["select a room to book", "search for a different hotel"]` |
| `prebooked` | Rate locked for HOTEL_NAME. | `["get payment link", "cancel booking"]` |
| `awaiting_payment` | Payment link sent. Awaiting completion. | `["check if payment is complete", "cancel booking"]` |
| `confirmed` | Booking confirmed. | `["view booking status", "resend voucher", "cancel booking"]` |
| `cancelling` | Cancellation initiated. Awaiting confirmation. | `["confirm cancellation", "keep booking"]` |

---

## 5. B2C Booking Flow (Complete Step-by-Step)

### Full Journey From Search to Voucher Delivery

```
1. SEARCH
   Customer: "Hotels in Dubai"
   ↓
   n8n: Calls action=search with city, dates, occupancy
   ↓
   Laravel: HotelSearchService::searchHotels()
      - Resolves city code via FuzzyMatcherService
      - Calls DotwService::searchHotels() (no blocking)
      - Returns 10 hotels numbered 1-10
      - Caches in: dotwai_search_{phone}
      - Session: stage=searching, search_cached_at=NOW
   ↓
   Response: {hotels: [...], whatsappMessage: "Found 10 hotels", sessionContext}
   ↓
   n8n: Sends WhatsApp message with hotel list

2. VIEW DETAILS
   Customer: "Tell me more about option 2"
   ↓
   n8n: Calls action=details with option=2
   ↓
   Laravel: AgentController::handleDetails()
      - Validates session exists (if not → SESSION_EMPTY error)
      - Validates search not expired (10 min check)
      - Gets hotel #2 from dotwai_search_{phone}
      - Calls HotelSearchService::getHotelDetails()
      - Returns room types, rates, cancellation policies
      - Session: stage=viewing_details, selected_hotel_id=X
   ↓
   Response: {hotel: {...}, rooms: [...], whatsappMessage, sessionContext}
   ↓
   n8n: Sends WhatsApp message with room details

3. PREBOOK (Lock Rate)
   Customer: "Book room option 2"
   ↓
   n8n: Calls action=book with option=2
   ↓
   Laravel: AgentController::handleBook()
      - Validates session exists
      - Validates search not expired (10 min check)
      - Calls BookingService::prebook()
         * Gets hotel from cache using option number
         * Calls DotwService::getRooms(blocking=true)
            → DOTW holds rate for 30 minutes
         * Creates DotwAIBooking record
            - status=prebooked
            - prebook_key=DOTWAI-{UUID}
            - original_total_fare (from DOTW)
            - display_total_fare (with B2C markup applied)
            - is_refundable flag
         * Caches nothing (rates locked in DOTW, not in Laravel)
      - Session: stage=prebooked, prebook_key=X, prebook_expires_at=NOW+30min
   ↓
   Response: {prebook_key, hotel_name, total_amount, sessionContext}
   ↓
   n8n: Sends "Rate locked for 30 min. Continue to payment?"

4. PAYMENT LINK
   Customer: "Generate payment link"
   ↓
   n8n: Calls action=pay with empty params
   ↓
   Laravel: AgentController::handlePay()
      - Validates prebook_key in session
      - Validates prebook not expired (30 min check)
      - Finds DotwAIBooking by prebook_key
      - Calls PaymentBridgeService::createPaymentLink()
         * Resolves MyFatoorah gateway credentials
         * Calls MyFatoorah ExecutePayment API directly
         * Creates Payment record (accounting audit trail)
         * Returns payment_url + expiry
      - Updates booking: status=pending_payment, payment_link=URL
      - Session: stage=awaiting_payment
   ↓
   Response: {payment_link, expires_at, whatsappMessage}
   ↓
   n8n: Sends WhatsApp with payment link

5. PAYMENT CALLBACK (Automatic)
   MyFatoorah: POST /api/dotwai/payment_callback
   ↓
   Laravel: PaymentCallbackController::handleCallback()
      - Verifies MyFatoorah signature
      - Finds DotwAIBooking by payment_id
      - Updates Payment record: status=paid
      - Calls BookingService::confirmAfterPayment()
         * Re-blocks rate via DotwService::getRooms(blocking=true)
         * Calls DotwService::confirmBooking() with DOTW XML
         * On success: status=confirmed, confirmation_no=X
         * Creates Invoice record (accounting)
         * Sends voucher PDF via Resayil WhatsApp
   ↓
   Database: DotwAIBooking.status = confirmed

6. BOOKING CONFIRMED
   n8n does NOT know yet (callback is async)
   ↓
   Customer: "What's my booking status?"
   ↓
   n8n: Calls action=status with empty params
   ↓
   Laravel: AgentController::handleStatus()
      - Finds DotwAIBooking by prebook_key from session
      - Returns current status, confirmation number, cancellation deadline
   ↓
   Response: {status: confirmed, confirmation_no: TRC-..., sessionContext}
   ↓
   n8n: Sends "Booking confirmed! Confirmation: TRC-..."

7. VOUCHER RESEND
   Customer: "Send me the voucher again"
   ↓
   n8n: Calls action=voucher with empty params
   ↓
   Laravel: AgentController::handleVoucher()
      - Validates booking is confirmed (status=confirmed only)
      - Calls VoucherService::resendVoucher()
         * Regenerates/finds PDF
         * Sends via Resayil WhatsApp
      - Updates booking: voucher_sent_at=NOW
   ↓
   Response: {success: true, sessionContext}
   ↓
   n8n: Sends confirmation message

8. CANCELLATION (Optional)
   Customer: "I want to cancel"
   ↓
   n8n: Calls action=cancel with confirm=no
   ↓
   Laravel: CancellationService::cancel(confirm=no)
      - Finds DotwAIBooking
      - Calls DotwService::checkCancellation()
      - Returns penalty_amount, refund_amount
      - Status: still confirmed (no change yet)
   ↓
   Response: {penalty_amount: X, refund_amount: Y, whatsappMessage}
   ↓
   n8n: Sends "Cancel? You'll pay KWD X and get KWD Y back"

   Customer: "Yes, cancel"
   ↓
   n8n: Calls action=cancel with confirm=yes, penalty_amount=X
   ↓
   Laravel: CancellationService::cancel(confirm=yes)
      - Calls DotwService::cancelBooking() (DOTW XML)
      - On success:
         * Updates DotwAIBooking: status=cancelled
         * Creates Invoice + JournalEntry for penalty (if > 0)
         * Creates credit refund for B2B agent (if applicable)
      - Session: stage=idle
   ↓
   Response: {status: cancelled, refund_amount, whatsappMessage}
   ↓
   n8n: Sends "Booking cancelled. Refund processing..."

===================================================
```

### Status Transitions on DotwAIBooking Model

```
prebooked
    ↓
    ├→ pending_payment (if payment required)
    │   ↓
    │   └→ confirming → confirmed (after payment + DOTW confirm)
    │
    └→ confirmed (B2B credit flow, no payment needed)

From ANY status:
    ├→ cancelled (if cancellation allowed)
    ├→ failed (if DOTW confirm/cancel fails)
    └→ expired (if prebook not confirmed within 30 min)
```

### Payment Callback to Auto-Confirm

**Flow**:
1. Customer completes MyFatoorah payment
2. MyFatoorah sends POST to `/api/dotwai/payment_callback`
3. PaymentCallbackController verifies signature
4. Calls `BookingService::confirmAfterPayment()`
5. Re-blocks rate via `getRooms(blocking=true)`
6. Confirms via `confirmBooking()` XML
7. Creates Invoice, sends voucher PDF
8. DotwAIBooking.status = confirmed

**No n8n involvement** — all automatic via webhook callback.

---

## 6. Session State — Deep Dive

### Cache Key Structure

```
dotwai_session_{phone}
```

Example: `dotwai_session_+965XXXXXXXX`

### What's Stored

```php
[
    // Current stage in the journey
    'stage' => 'idle|searching|viewing_details|prebooked|awaiting_payment|confirmed|cancelling',

    // Search metadata (for validating 10-min TTL)
    'search_cached_at' => '2026-03-28T10:30:00+00:00',  // ISO8601 timestamp
    'search_city' => 'Dubai',
    'search_hotel_count' => 10,
    'last_search_params' => [
        'city' => 'Dubai',
        'check_in' => '2026-06-01',
        'check_out' => '2026-06-05',
        'occupancy' => [{'adults' => 2, 'children_ages' => []}],
        // ... other search filters
    ],

    // Selected hotel during details/book
    'selected_hotel_id' => '34567',
    'selected_hotel_name' => 'The Ritz-Carlton Dubai',
    'last_option' => 2,  // Option number from search

    // Prebook metadata (for validating 30-min TTL)
    'prebook_key' => 'DOTWAI-550E8400-E29B-41D4-A716-446655440000',
    'prebook_expires_at' => '2026-03-28T11:00:00+00:00',  // NOW + 30 min at booking time

    // Booking reference (after confirmation)
    'booking_ref' => 'TRC-20260601-12345',
]
```

### TTL: 60 Minutes, Rolling

**Cache.put() with TTL**:
```php
Cache::put(
    'dotwai_session_' . $phone,
    $data,
    now()->addMinutes(60)  // Expires at CURRENT_TIME + 60 min
);
```

**Result**: Session survives as long as the customer is active (refreshed on every action). If the customer goes silent for 60 minutes, session is purged automatically by Laravel's cache driver.

### How Expiry Checks Work

**Search Expiry Check** (in every details/book/pay call):

```php
public function isSearchExpired(string $phone): bool
{
    $session = $this->getSession($phone);
    if (empty($session['search_cached_at'])) {
        return true;  // No search = treat as expired
    }
    $cachedAt = Carbon::parse($session['search_cached_at']);
    return $cachedAt->diffInSeconds(now()) > 600;  // 10 minutes = 600 seconds
}
```

**Prebook Expiry Check** (in every pay/cancel/status call):

```php
public function isPrebookExpired(string $phone): bool
{
    $session = $this->getSession($phone);
    if (empty($session['prebook_expires_at'])) {
        return true;  // No prebook = treat as expired
    }
    return now()->isAfter(Carbon::parse($session['prebook_expires_at']));
}
```

### Stage Context for AI Navigation

The `getStageContext()` method returns guidance for the AI:

```php
public function getStageContext(array $session): array
{
    $stage = $session['stage'] ?? 'idle';

    return [
        'stage' => $stage,
        'summary' => "Human-readable description of current state",
        'next_actions' => ["List of allowed next steps"]
    ];
}
```

**The AI uses this to decide what to suggest next**:
- If stage = "idle", suggest: "Search for a hotel"
- If stage = "searching", suggest: "Select a hotel from results"
- If stage = "prebooked", suggest: "Get payment link or cancel booking"

---

## 7. DOTW Time Constraints

### Three Independent Time Windows

#### 1. Search Cache: 10 Minutes

**What**: Search results are cached per phone for 10 minutes.

**Where**: Cache key `dotwai_search_{phone}` (separate from session)

**How It's Validated**:
```php
// In AgentSessionService::isSearchExpired()
$cachedAt = Carbon::parse($session['search_cached_at']);
return $cachedAt->diffInSeconds(now()) > 600;
```

**What Happens When It Expires**:
- Any `details`, `book`, or `pay` action that relies on cached search will return:
  ```json
  {
    "error": {
      "code": "SEARCH_EXPIRED",
      "message": "Search results expired",
      "suggestedAction": "Ask the user to initiate a new hotel search."
    }
  }
  ```

**Why 10 Minutes**: DOTW requires a fresh search call every 10 minutes. Cached results older than that may have rate changes or availability updates.

#### 2. Prebook Allocation: 30 Minutes

**What**: DOTW holds a rate lock for 30 minutes after a successful `prebook` call.

**Where**: Tracked in DotwAIBooking.prebook_expires_at and session.prebook_expires_at

**How It's Validated**:
```php
// In AgentSessionService::isPrebookExpired()
return now()->isAfter(Carbon::parse($session['prebook_expires_at']));
```

**What Happens When It Expires**:
- Any `pay`, `cancel`, or `status` action will return:
  ```json
  {
    "error": {
      "code": "PREBOOK_EXPIRED",
      "message": "Prebook allocation expired (30 min limit)",
      "suggestedAction": "Ask the user to search again — the rate lock has expired after 30 minutes."
    }
  }
  ```

**Why 30 Minutes**: This is DOTW's standard allocation period. After 30 min, they release the rate block and it may sell to another customer.

#### 3. Rate Blocking Window: 3 Minutes (Internal)

**What**: During the `book` and `confirmAfterPayment` calls, DOTW internally requires rate re-blocking within 3 minutes.

**Where**: Inside DOTW's `getRooms(blocking=true)` and `confirmBooking()` calls

**How It Works**:
1. `book` action calls `getRooms(blocking=true)` → DOTW locks rate for 3 min internally
2. Payment happens (customer may take minutes to complete)
3. `confirmAfterPayment` callback calls `getRooms(blocking=true)` again → re-block the rate
4. Then `confirmBooking()` confirms within the 3-min window

**What Happens If Missed**: If more than 3 min passes between the initial block and the re-block before confirm, DOTW may return `RATE_UNAVAILABLE` error (rate sold out).

**Mitigation**: The payment link has 48-hour expiry, but DOTW rate lock doesn't. This is a known constraint:
- If payment gateway takes too long, the 3-min re-block window is missed
- The system returns `RATE_UNAVAILABLE` error and customer must search again

---

### How Laravel Validates and Returns Expiry Errors

**Every action that needs a search checks**:
```php
if ($this->sessionService->isSearchExpired($phone)) {
    // Return SEARCH_EXPIRED error
}
```

**Every action that needs a prebook checks**:
```php
if ($this->sessionService->isPrebookExpired($phone)) {
    // Return PREBOOK_EXPIRED error
}
```

**These checks happen FIRST**, before any DOTW API calls, to avoid wasting API quota on expired sessions.

---

## 8. System Message (Full Text)

Location: `/c/Users/User/OneDrive - City Travelers/soud-laravel/app/Modules/DotwAI/Config/dotwai-system-message.md`

The system message is loaded by n8n and embedded in the LangChain agent. It instructs the AI on:
1. Available tool (dotwai_agent)
2. All 8 actions and their parameters
3. Session context interpretation
4. Conversation style (bilingual, natural, polite)
5. When to use each action

**Key sections**:

- **Role**: Bilingual Arabic/English, customer-facing hotel booking assistant
- **Available Tool**: Single tool `dotwai_agent` with `action` and `params`
- **Actions**: Complete specs for all 8 actions with JSON examples
- **Session Context**: How to interpret stage, summary, next_actions
- **Core Rule**: Always use `next_actions` from sessionContext; never invent steps
- **Conversation Style**: Be natural, avoid rigid menus, ask for confirmation before locking rates

**The AI reads this and learns**:
- "When user wants to book, I should call search → details → book → pay"
- "After prebook, the sessionContext tells me to offer 'Get payment link' or 'Cancel booking'"
- "If session expired, apologize and ask them to start a new search"
- "Always respond in their language (Arabic or English)"

---

## 9. Monitoring & Debugging

### Dashboard at /admin/dotw

**Dashboard URL**: `https://development.citycommerce.group/admin/dotw` (requires authentication)

**What Each Tab Shows**:

1. **Bookings Tab**: List of all DotwAIBooking records
   - Filters by status, company, date range
   - Shows prebook_key, hotel_name, total_amount, payment_status
   - Click to view full booking details

2. **Sessions Tab**: Active per-phone sessions in cache
   - Shows phone, stage, last_action, created_at, expires_at
   - Useful for debugging session state issues

3. **Audit Logs Tab**: All API requests and responses (if implemented)
   - Logged to dotw_audit_logs table
   - Shows phone, action, request params, response code, created_at
   - Useful for tracing a customer's journey

4. **Error Logs Tab**: Failed actions and their error codes
   - Groups by error code (SEARCH_EXPIRED, PREBOOK_NOT_FOUND, etc.)
   - Shows count of occurrences
   - Useful for identifying patterns

### Audit Logs Table

**Table**: `dotw_audit_logs`

**Schema** (approximate):
```sql
CREATE TABLE dotw_audit_logs (
    id BIGINT PRIMARY KEY,
    company_id INT,
    phone VARCHAR(20),
    action VARCHAR(50),  -- search, details, book, pay, cancel, status, history, voucher
    request_data JSON,
    response_code INT,
    response_data JSON,
    error_code VARCHAR(50),
    error_message TEXT,
    created_at TIMESTAMP
);
```

### How to Trace a Request

**Goal**: Follow a customer's interaction from WhatsApp to DOTW and back.

**Steps**:

1. **Get the phone number** — ask customer or extract from WhatsApp chat

2. **Check session in cache**:
   ```bash
   php artisan tinker
   >>> Cache::get('dotwai_session_+965XXXXXXXX')
   ```
   Returns current stage, search params, prebook key, etc.

3. **Query audit logs** (if enabled):
   ```bash
   >>> DB::table('dotw_audit_logs')
   >>>   ->where('phone', '+965XXXXXXXX')
   >>>   ->orderBy('created_at', 'desc')
   >>>   ->limit(10)
   >>>   ->get();
   ```
   Shows last 10 API calls for this phone.

4. **Find booking by prebook key**:
   ```bash
   >>> DB::table('dotwai_bookings')
   >>>   ->where('prebook_key', 'DOTWAI-...')
   >>>   ->first();
   ```
   Returns full booking state, status, payment_status, etc.

5. **Check payment by ID**:
   ```bash
   >>> DB::table('payments')
   >>>   ->where('id', $booking->payment_id)
   >>>   ->first();
   ```
   Shows payment status, gateway response, etc.

6. **Check Laravel logs**:
   ```bash
   tail -f storage/logs/dotw.log
   ```
   Real-time logs of all DotwAI actions and errors.

### Common Errors and Their Meaning

| Error Code | Cause | Fix |
|------------|-------|-----|
| `SESSION_EMPTY` | Customer called `details`/`book` without searching first | Ask customer to search |
| `SEARCH_EXPIRED` | Search > 10 min old; results may be stale | Trigger fresh search |
| `PREBOOK_EXPIRED` | Rate lock released; DOTW won't confirm | Ask customer to search again |
| `HOTEL_NOT_FOUND` | Option number doesn't exist (only 5 results but asked for #10) | Check search result count |
| `CITY_NOT_FOUND` | FuzzyMatcher can't find city | Check spelling; use LIKE fallback |
| `DOTW_API_ERROR` | DOTW XML service timeout or error | Check DOTW status; retry with backoff |
| `RATE_UNAVAILABLE` | Rate no longer available (sold out or timing issue) | Search again; pick different hotel |
| `INSUFFICIENT_CREDIT` | B2B agent account balance too low | Admin tops up agent credit |
| `PAYMENT_FAILED` | MyFatoorah or Knet rejected the payment | Customer retries with different card |
| `BOOKING_FAILED` | DOTW confirm failed despite successful prebook | DOTW internal issue; retry search |

---

## 10. Configuration

### Key Config Values (dotwai.php)

```php
// B2B / B2C Track Toggles
'b2b_enabled' => env('DOTWAI_B2B_ENABLED', true),
'b2c_enabled' => env('DOTWAI_B2C_ENABLED', true),

// Default B2C Markup
'default_markup_percent' => env('DOTWAI_DEFAULT_MARKUP', 20),  // 20%

// Search
'search_results_limit' => 10,  // Max 10 hotels returned
'search_cache_ttl' => 600,     // 10 minutes in seconds

// Booking
'prebook_expiry_minutes' => 30,    // Prebook holds for 30 min
'payment_link_expiry_hours' => 48, // Payment link valid for 48 hours

// Payment Gateway
'default_payment_gateway' => 'myfatoorah',

// DOTW API Defaults
'default_currency' => '520',      // KWD
'default_nationality' => '66',    // Kuwait
'default_residence' => '66',      // Kuwait
'display_currency' => 'KWD',

// System Message Path
'system_message_path' => __DIR__ . '/dotwai-system-message.md',

// Webhook (if enabled for lifecycle events)
'webhook_url' => env('DOTWAI_WEBHOOK_URL', ''),
'webhook_events' => [
    'payment_completed',
    'reminder_due',
    'deadline_passed',
    'booking_confirmed',
],
```

### Environment Variables

These should be set in `.env`:

```bash
# B2B/B2C Track Control
DOTWAI_B2B_ENABLED=true
DOTWAI_B2C_ENABLED=true

# Default Markup for B2C
DOTWAI_DEFAULT_MARKUP=20

# Search Config
DOTWAI_SEARCH_LIMIT=10
DOTWAI_SEARCH_CACHE_TTL=600

# Prebook Config
DOTWAI_PREBOOK_EXPIRY=30
DOTWAI_PAYMENT_LINK_EXPIRY=48

# DOTW API Defaults
DOTWAI_DEFAULT_CURRENCY=520
DOTWAI_DEFAULT_NATIONALITY=66
DOTWAI_DEFAULT_RESIDENCE=66
DOTWAI_DISPLAY_CURRENCY=KWD

# Payment Gateway
DOTWAI_DEFAULT_GATEWAY=myfatoorah

# Webhook (optional)
DOTWAI_WEBHOOK_URL=https://n8n.example.com/webhook/dotw-events
```

### Server & Database

**Server**: `development.citycommerce.group`

**Database**:
- **Primary**: `citycomm_city-tour-test` (main app data)
- **Map Database**: `map_data_citytour` (geographic/city data)

**Redis** (for session cache):
- Default: localhost:6379
- In production: configure via Redis provider

---

## 11. File Map

### Controllers

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Http/Controllers/AgentController.php` | Single unified endpoint for all 8 actions (search, details, book, pay, cancel, status, history, voucher) |
| `app/Modules/DotwAI/Http/Controllers/SearchController.php` | Legacy search endpoints (kept for backward compatibility) |
| `app/Modules/DotwAI/Http/Controllers/BookingController.php` | Legacy booking endpoints (prebook, confirm, payment link, status, history, voucher) |
| `app/Modules/DotwAI/Http/Controllers/PaymentCallbackController.php` | Webhook for MyFatoorah payment callbacks |
| `app/Modules/DotwAI/Http/Controllers/StatementController.php` | Company statement/balance endpoint |

### Services

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Services/AgentSessionService.php` | Per-phone session state (cache, TTL, expiry checks) |
| `app/Modules/DotwAI/Services/HotelSearchService.php` | Hotel search orchestration (city resolve, DOTW API, filtering, caching) |
| `app/Modules/DotwAI/Services/BookingService.php` | Prebook and rate blocking via DOTW |
| `app/Modules/DotwAI/Services/PaymentBridgeService.php` | MyFatoorah payment link generation |
| `app/Modules/DotwAI/Services/CancellationService.php` | Two-step cancellation with DOTW XML calls |
| `app/Modules/DotwAI/Services/MessageBuilderService.php` | WhatsApp message formatting (bilingual) |
| `app/Modules/DotwAI/Services/DotwAIResponse.php` | Standardized response envelope with error codes |
| `app/Modules/DotwAI/Services/VoucherService.php` | Voucher PDF generation and resend |
| `app/Modules/DotwAI/Services/PhoneResolverService.php` | Resolve phone → agent → company → credentials → context |
| `app/Modules/DotwAI/Services/FuzzyMatcherService.php` | Fuzzy matching for cities, hotels, countries |
| `app/Modules/DotwAI/Services/CreditService.php` | B2B agent credit balance and locking |
| `app/Modules/DotwAI/Services/AccountingService.php` | Invoice + JournalEntry creation for payments/penalties |
| `app/Modules/DotwAI/Services/WebhookEventService.php` | Dispatch lifecycle events to webhook URL |

### Models

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Models/DotwAIBooking.php` | Main booking record with lifecycle status constants |
| `app/Modules/DotwAI/Models/DotwAICity.php` | DOTW city reference data |
| `app/Modules/DotwAI/Models/DotwAIAuditLog.php` | Audit trail of API requests/responses |

### DTOs & Data Classes

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/DTOs/DotwAIContext.php` | Immutable DTO for resolved phone context (agent, company, credentials, track, markup) |

### Requests & Validation

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Http/Requests/AgentRequest.php` | Form request validation for agent endpoint (telephone, action, params) |

### Routes

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Routes/api.php` | All DotwAI API routes (search, booking, payment, cancellation, agent facade) |

### Middleware

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Http/Middleware/ResolveDotwAIContext.php` | Resolve phone to context; validate track enabled; attach to request |

### Config

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Config/dotwai.php` | Configuration for B2B/B2C, markup, search limits, TTLs, webhook URL |
| `app/Modules/DotwAI/Config/dotwai-system-message.md` | System prompt for n8n AI agent (bilingual) |

### Commands

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Commands/CleanStaleSessionsCommand.php` | Daily cron job to sweep expired sessions from cache |

### Views

| File | Purpose |
|------|---------|
| `app/Modules/DotwAI/Views/voucher-pdf.blade.php` | Blade template for booking voucher PDF |

---

## 12. Known Issues & Gotchas

### 1. n8n Tool Name Must Be Alphanumeric + Underscore

**Issue**: If the tool in the HTTP Request node is named `dotwai-agent` or `dotwai:agent`, the LangChain agent can't call it.

**Root Cause**: n8n's tool invocation uses the tool name as a parameter. Hyphens and colons break the regex parsing.

**Fix**: Rename tool to `dotwai_agent` (underscore only).

### 2. Session Key Expression Typo

**Issue**: Window Buffer Memory node uses wrong expression → conversation history lost.

**Wrong**: `$json.data.fromNumber` or `Resayil Trigger.item.json.data.fromNumber`

**Correct**: `$('Resayil Trigger').item.json.data.fromNumber`

**Fix**: Double-check the exact syntax with `$('NodeName')` notation.

### 3. .env Resets on git pull

**Issue**: Server `.env` file is not tracked in Git. When you `git pull`, any local environment changes (DB credentials, API keys) are lost.

**Root Cause**: `.env` is in `.gitignore` for security.

**Mitigation**:
- Server has a separate `.env.production` or credentials stored in a secure vault
- After `git pull`, verify `.env` is still correct: `cat .env | grep DB_`
- Contact DevOps if credentials are missing

### 4. DOTW Sandbox vs Production

**Issue**: Different API endpoints for testing vs live bookings.

**Sandbox**: Testing with fake hotels, rates, payment.
**Production**: Real DOTW network, real bookings.

**How to Check**:
```php
// In DotwService or DOTW XML client
$apiUrl = config('dotw.api_url');  // Check if it's sandbox or production
```

**Risk**: If configured to production but code is buggy, real bookings fail. Always test in sandbox first.

### 5. Phone Resolution Context in n8n

**Issue**: If Resayil Trigger doesn't correctly extract `fromNumber`, the phone passed to Laravel is empty or wrong.

**How It Happens**:
- Resayil message format changes
- Trigger node configured incorrectly
- n8n expression syntax error

**Debug**:
```
In n8n: Add a Debug node after Resayil Trigger
Check the output: is $json.data.fromNumber populated?
```

### 6. Search Cache Key Isolation

**Issue**: Multiple phones' searches can interfere if cache key is not per-phone.

**Current**: `dotwai_search_{phone}` — separate cache per phone ✓

**Previous Bug**: If cache key was global `dotwai_search`, a fresh search by phone B would overwrite phone A's results.

**Status**: FIXED. Each phone has its own cache bucket.

### 7. Prebook Expiry Check Timing

**Issue**: Session says prebook expires at T+30min, but DOTW may release the rate at a different time (e.g., due to network latency).

**Mitigation**: Laravel checks `prebook_expires_at` timestamp before any operation. If now() is past that time, error out immediately without calling DOTW (saves API quota).

### 8. Rate Re-blocking During Payment

**Issue**: Between `book` (which blocks) and `confirmAfterPayment` (which re-blocks), the rate may be sold.

**DOTW Constraint**: 3-minute re-block window. If payment takes too long, re-block fails.

**Response**: `RATE_UNAVAILABLE` error. Customer must search again.

**Mitigation**: Keep payment link expiry to 48 hours (reasonable window), but educate customers that rates may change.

### 9. Cancellation Penalty Async Update

**Issue**: After calling DOTW cancellation, the penalty amount returned by DOTW may differ from preview.

**Why**: DOTW's internal rules; maybe refund policy changed between preview and confirm.

**Handling**: CancellationService captures actual penalty from DOTW response and creates accounting entries with that amount (not the preview amount).

### 10. Webhook Callback Race Condition

**Issue**: If payment callback arrives before booking record is created, payment updates fail.

**Flow**:
1. POST /api/dotwai/agent-b2c action=pay creates Payment record
2. MyFatoorah sends callback immediately
3. PaymentCallbackController queries DotwAIBooking → might not exist yet

**Mitigation**:
- Create DotwAIBooking during `book` action (status=prebooked)
- Update during `pay` action (status=pending_payment)
- Payment callback can safely find booking

**Status**: Already implemented correctly.

### 11. Session TTL Rolling vs Fixed

**Issue**: If session TTL is fixed (expires at a specific time), it resets only once. If rolling, it extends every time the customer acts.

**Current Design**: Rolling (60 minutes from last action) ✓

**Why It Matters**: If session expires at 10:00 and customer is active until 9:55, session extends to 10:55, keeping them in the flow.

### 12. Search Results Numbering

**Issue**: If search returns more hotels than `search_results_limit` (e.g., 100 hotels), only 10 are returned but numbered 1-10. If customer asks for "option 15", it fails.

**Handling**: AgentController checks if option number is within bounds. Returns `HOTEL_NOT_FOUND` error if not.

---

## Appendix: Quick Troubleshooting Flowchart

```
n8n Error or n8n → Laravel Integration Broken?
│
├─ n8n Agent won't start
│  ├─ Check OpenAI credentials (LLM node)
│  ├─ Check Resayil credentials (Trigger + Send nodes)
│  └─ Check tool name = dotwai_agent (no hyphens)
│
├─ Tool call fails in n8n logs
│  ├─ Check HTTP endpoint URL (development.citycommerce.group/api/dotwai/agent-b2c)
│  ├─ Check placeholder definitions (action, params match tool definition)
│  └─ Check HTTP header auth credentials
│
├─ Laravel returns error response
│  ├─ Check error.code (PHONE_NOT_FOUND, CITY_NOT_FOUND, etc.)
│  ├─ Read suggestedAction to fix
│  └─ Check Laravel logs: tail -f storage/logs/dotw.log
│
├─ Session expired error (SEARCH_EXPIRED or PREBOOK_EXPIRED)
│  ├─ Customer must start fresh search
│  └─ Session TTL is 10 min for search, 30 min for prebook — by design
│
├─ Payment callback not working
│  ├─ Check MyFatoorah credentials in GatewayConfigService
│  ├─ Verify callback URL registered in MyFatoorah dashboard
│  └─ Check storage/logs for callback errors
│
└─ DOTW API error (RATE_UNAVAILABLE, DOTW_API_ERROR)
   ├─ Check DOTW status page / contact Olga Chicu
   ├─ Verify company DOTW credentials are valid
   └─ Retry the action (transient failure)
```

---

## Summary

This document provides everything needed to troubleshoot n8n + Laravel integration for the DOTW hotel booking AI agent. Key takeaways:

1. **Single tool pattern**: n8n agent calls one tool (`dotwai_agent`) with 8 actions
2. **Session state**: Per-phone, 60-min TTL, tracks search (10 min) and prebook (30 min) validity
3. **Request/response format**: Standardized JSON with `whatsappMessage` and `sessionContext`
4. **Error codes**: Comprehensive list with suggested actions for AI
5. **DOTW time constraints**: Search (10 min), Prebook (30 min), Rate block (3 min internal)
6. **File structure**: Controllers, Services, Models, Config organized by concern
7. **Monitoring**: Dashboard, audit logs, and tinker-based debugging

For production issues, check:
- n8n logs (credentials, tool invocation)
- Laravel logs (business logic, DOTW API)
- Cache state (session expiry)
- Database (booking status, payment records)

Contact soudshoja for production incidents.
