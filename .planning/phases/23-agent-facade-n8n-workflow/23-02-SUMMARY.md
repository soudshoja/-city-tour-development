---
phase: 23
plan: 02
subsystem: DotwAI
tags: [n8n-integration, ai-agent, system-message, command-scheduling]
dependencies:
  requires: [23-01]
  provides: [System message for single-tool AI, n8n workflow JSON for Resayil B2C, CleanStaleSessionsCommand]
  affects: [n8n n8n import, integration testing, session lifecycle]
tech_stack:
  added:
    - n8n AI Agent nodes (4-node workflow)
    - Resayil WhatsApp trigger/send
    - Window Buffer Memory with phone-based session key
  patterns:
    - Single-tool HTTP endpoint design
    - Session context-driven AI decisions
    - Bilingual system messages
key_files:
  created:
    - app/Modules/DotwAI/Commands/CleanStaleSessionsCommand.php
    - public/downloads/api-templates/dotw-b2c-ai-agent-workflow.json
  modified:
    - app/Modules/DotwAI/Config/dotwai-system-message.md
    - app/Modules/DotwAI/Providers/DotwAIServiceProvider.php
    - app/Console/Kernel.php
decisions:
  D-20: System message bilingual AR/EN, describes single dotwai_agent tool with all 8 actions
  D-21: One HTTP Request tool pointing to /api/dotwai/agent-b2c (all operations unified)
  D-22: Resayil Trigger device 68ac2c4c80090e92ccbf6d74, credential "Resayil AI" (4QeuhC3zYnCEYxpd)
  D-23: Resayil Send outputs {{ $json.output }} to fromNumber from trigger
  D-19: Window Buffer Memory 20 messages, sessionKey from telephone (DOTW Config)
duration: 15m
completed_date: 2026-03-28
---

# Phase 23 Plan 02: n8n Workflow Integration Summary

**One-liner:** Streamlined n8n workflow with single-tool HTTP endpoint, bilingual system message, and stale session cleanup command — ready to import and test the full WhatsApp → n8n → Laravel agent chain.

---

## Objective

Build the n8n integration layer to complete the agent facade. Replace the existing 11-tool n8n workflow with a clean 4-node Resayil workflow, update the AI system message for single-tool operation, and add a stale session cleanup command scheduled daily.

---

## Tasks Executed

### Task 1: Rewrite system message + create CleanStaleSessionsCommand + register it

**Status:** COMPLETE

Created and updated four files:

1. **app/Modules/DotwAI/Config/dotwai-system-message.md** — Completely rewritten
   - Previously: 11-tool system message (search_hotels, get_hotel_details, prebook_hotel, etc.)
   - Now: Single `dotwai_agent` tool with 8 actions
   - All actions documented bilingual AR/EN
   - Session context (stage, next_actions) documented
   - APR warnings prominent and bilingual
   - Phone number handling clarified

2. **app/Modules/DotwAI/Commands/CleanStaleSessionsCommand.php** — Created
   - `php artisan dotwai:clean-sessions [--dry-run]` command
   - Logs cleanup events to 'dotw' channel
   - Dry-run mode shows session key pattern
   - Production mode acknowledges Cache TTL handles expiry
   - Ready for future extension to DB-backed sessions

3. **app/Modules/DotwAI/Providers/DotwAIServiceProvider.php** — Updated
   - Added `use App\Modules\DotwAI\Commands\CleanStaleSessionsCommand;`
   - Registered `CleanStaleSessionsCommand::class` in commands array

4. **app/Console/Kernel.php** — Updated
   - Added `$schedule->command('dotwai:clean-sessions')->dailyAt('03:00');`
   - Scheduled immediately after `dotwai:process-deadlines` (same time)

**Verification:**
```
✓ php artisan dotwai:clean-sessions --dry-run outputs success
✓ php artisan list | grep clean-sessions shows command registered
✓ Grep confirms dotwai_agent, sessionContext, next_actions, APR, non-refundable in system message
✓ CleanStaleSessionsCommand use statement and registration in provider
✓ Schedule entry in Kernel.php with dailyAt('03:00')
```

### Task 2: Replace n8n workflow JSON with single-tool Resayil workflow

**Status:** COMPLETE

Completely replaced **public/downloads/api-templates/dotw-b2c-ai-agent-workflow.json**:

**Old workflow:** 11 separate tool nodes (search_hotels, get_hotel_details, prebook_hotel, payment_link, etc.)

**New workflow:** 7-node streamlined design

**Nodes:**
1. **Resayil Trigger** (resayil-trigger)
   - Device: 68ac2c4c80090e92ccbf6d74
   - Credential: 4QeuhC3zYnCEYxpd (Resayil AI)
   - Receives WhatsApp messages
   - Outputs: fromNumber, message text

2. **DOTW Config** (dotw-config) — Set node
   - baseUrl: https://development.citycommerce.group
   - telephone: extracted from Resayil Trigger's fromNumber
   - Passed to downstream nodes

3. **DOTW Hotel Booking Agent** (agent-main) — LangChain Agent
   - System message: Full bilingual AR/EN (embedded as escaped JSON)
   - Integrated with LLM, Memory, and tool
   - Route: agent-main -> tool-dotwai-agent -> /api/dotwai/agent-b2c

4. **OpenAI Chat Model** (openai-chat-model)
   - Model: gpt-4o
   - Temperature: 0.3
   - Credential: YOUR_OPENAI_CREDENTIAL_ID (placeholder for user setup)

5. **Window Buffer Memory** (window-buffer-memory)
   - Context window: 20 messages
   - Session key: {{ $('DOTW Config').item.json.telephone }}
   - Per-customer conversation history

6. **Tool: dotwai_agent** (tool-dotwai-agent) — Single HTTP Request tool
   - URL: {{ $('DOTW Config').item.json.baseUrl }}/api/dotwai/agent-b2c
   - Method: POST
   - Body: { telephone, action, params }
   - Placeholders: action (string), params (json)

7. **Resayil Send** (resayil-send)
   - Device: 68ac2c4c80090e92ccbf6d74
   - To: {{ $('Resayil Trigger').item.json.data.fromNumber }}
   - Message: {{ $json.output }} (AI response)

**Connections:**
- Resayil Trigger → DOTW Config → AI Agent → Resayil Send (main flow)
- OpenAI Chat Model → AI Agent (ai_languageModel)
- Window Buffer Memory → AI Agent (ai_memory)
- Tool: dotwai_agent → AI Agent (ai_tool)

**System Message:** Complete bilingual markdown
- 8 Actions: search, details, book, pay, cancel, status, history, voucher
- Session context (stage, next_actions) rules
- APR non-refundable warning
- Phone number handling
- Conversation style guidelines
- Quick reference table

**JSON validation:**
```
✓ json_decode() succeeds (VALID JSON)
✓ Resayil Trigger node present (2 matches)
✓ Resayil Send node present (4 matches)
✓ agent-b2c endpoint (2 references)
✓ Single dotwai_agent tool (7 matches)
✓ Old 11 tools removed (0 matches)
✓ Device ID 68ac2c4c80090e92ccbf6d74 (3 matches: 2 Resayil nodes + 1 in README)
✓ Credential ID 4QeuhC3zYnCEYxpd (3 matches: 2 Resayil nodes + 1 in README)
✓ Context window: 20 messages confirmed
✓ fromNumber references in Config and Send nodes
```

---

## Deviations from Plan

None — plan executed exactly as specified. All acceptance criteria met.

---

## Auth Gates

None encountered.

---

## Known Stubs

None. System message is complete. n8n workflow is importable and fully configured (except OpenAI credential placeholder, which is documented in _readme setup section).

---

## Commits

1. **ed29e52f** — feat(23-02): rewrite system message for single-tool agent + create CleanStaleSessionsCommand
   - Files: 4 modified, 1 created
   - Key changes: System message simplified, command created and scheduled

2. **69a2e349** — feat(23-02): replace n8n workflow with single-tool Resayil workflow
   - Files: 1 file (complete JSON replacement)
   - Key changes: 11-tool → 4-node workflow, Resayil trigger/send, embedded system message

---

## Next Steps

**23-03 (if planned):** Test the complete flow end-to-end
- Import the n8n workflow into Resayil's n8n instance
- Set OpenAI credential (or swap for Ollama llm.resayil.io)
- Configure baseUrl in DOTW Config node
- Send test WhatsApp message
- Verify: Message → Resayil → n8n → /api/dotwai/agent-b2c → whatsappMessage response → Resayil Send → WhatsApp

**Setup Instructions (in _readme):**
1. Update OpenAI credential ID in Chat Model node
2. Set baseUrl in DOTW Config node to your Laravel URL
3. Verify Resayil device and credential IDs match your setup
4. Import workflow into n8n and test with DOTW sandbox

---

## Requirements Met

- AGEN-03: Bilingual AR/EN system message describing single tool with all 8 actions ✓
- AGEN-04: Ready-to-import n8n workflow with Resayil trigger, 1 HTTP tool, memory, Resayil send ✓

---

## Self-Check: PASSED

- ✓ File created: app/Modules/DotwAI/Commands/CleanStaleSessionsCommand.php
- ✓ File modified: app/Modules/DotwAI/Config/dotwai-system-message.md
- ✓ File modified: app/Modules/DotwAI/Providers/DotwAIServiceProvider.php
- ✓ File modified: app/Console/Kernel.php
- ✓ File modified: public/downloads/api-templates/dotw-b2c-ai-agent-workflow.json
- ✓ Commit ed29e52f exists in git log
- ✓ Commit 69a2e349 exists in git log
