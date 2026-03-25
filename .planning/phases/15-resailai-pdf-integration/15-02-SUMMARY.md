---
phase: 15-resailai-pdf-integration
plan: 02
subsystem: api
tags: [laravel, webhook, n8n, resailai, callback, normalization, flight, hotel, visa, insurance, eloquent]

# Dependency graph
requires:
  - phase: 15-resailai-pdf-integration
    plan: 01
    provides: TaskWebhookBridge with full field normalization for all 4 task types

provides:
  - ProcessingAdapter.flattenExtractionResult() handling both nested (tasks array) and flat n8n callback formats
  - CallbackController routing multi-task callbacks through TaskWebhookBridge per task
  - DocumentProcessingLog tracking: callback_received_at, status, extraction_result, completed_at, processing_duration_ms
  - Validation rules extended to accept flat-format top-level task fields
  - Error callbacks update both FileUpload and DocumentProcessingLog

affects:
  - 15-resailai-pdf-integration

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Flatten-then-iterate: ProcessingAdapter flattens payload into task array, CallbackController iterates and routes each task individually
    - Dual format detection: isset($extraction['tasks']) check distinguishes nested from flat n8n callback format
    - Context field merging: document_id/supplier_id/company_id/agent_id/branch_id always merged into each task payload before bridge routing

key-files:
  created: []
  modified:
    - app/Modules/ResailAI/Services/ProcessingAdapter.php
    - app/Modules/ResailAI/Http/Controllers/CallbackController.php

key-decisions:
  - "flattenExtractionResult returns an array of task payloads so CallbackController can iterate — one bridge call per extracted task"
  - "processExtractionResult kept but marked @deprecated — replaced by flattenExtractionResult for proper context merging"
  - "DocumentProcessingLog status set to processing on callback receipt, then completed or failed after task creation loop"
  - "allSuccess flag tracks per-task outcomes; final FileUpload status is completed only if all tasks succeeded"
  - "Flat format detection: if extraction_result has no tasks key, the extraction data is returned as a single-element array"
  - "branch_id included in context fields even though ProcessDocumentJob sends it — preserves multi-tenant routing"

patterns-established:
  - "Flatten pattern: flattenExtractionResult(callbackPayload) returns []array, each element ready for processExtraction()"
  - "Log-and-continue: per-task bridge failures set allSuccess=false but loop continues to process remaining tasks"
  - "Dual status update: both FileUpload and DocumentProcessingLog updated to keep both models in sync"

requirements-completed: [RESAIL-13, RESAIL-14]

# Metrics
duration: 2min
completed: 2026-03-17
---

# Phase 15 Plan 02: CallbackController Callback Pipeline Summary

**CallbackController and ProcessingAdapter updated to flatten n8n callback payloads (nested tasks[] or flat format) into per-task arrays, iterate through TaskWebhookBridge per task, and track full lifecycle in DocumentProcessingLog**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-17T05:37:58Z
- **Completed:** 2026-03-17T05:40:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- ProcessingAdapter.flattenExtractionResult() handles both inbound n8n formats (nested tasks array + flat top-level)
- CallbackController iterates over each flattened task and routes through TaskWebhookBridge independently
- DocumentProcessingLog receives callback_received_at on arrival, then completed/failed status after task creation
- Validation rules extended to accept 10 flat-format task fields at top level alongside document_id
- Error callbacks update both FileUpload and DocumentProcessingLog with error_code and error_message
- All acceptance criteria checks pass (both files)

## Task Commits

Each task was committed atomically:

1. **Task 1: Update ProcessingAdapter to flatten nested extraction results** - `42d07838` (feat)
2. **Task 2: Refine CallbackController to use flattened extraction and handle multi-task callbacks** - `90884722` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `app/Modules/ResailAI/Services/ProcessingAdapter.php` - Added flattenExtractionResult() with nested/flat format detection and context field merging; getLastMetadata() for logging; processExtractionResult marked @deprecated; all existing methods preserved
- `app/Modules/ResailAI/Http/Controllers/CallbackController.php` - Replaced processExtractionResult call with flattenExtractionResult + foreach loop; added DocumentProcessingLog tracking; extended validation rules; removed ResailaiCredential import (unused)

## Decisions Made
- flattenExtractionResult returns `array[]` (array of task payloads) so the controller iterates over them — this was cleaner than returning a single merged payload and less likely to silently discard multi-task documents
- processExtractionResult was kept (marked @deprecated) rather than deleted — backward-compatible in case it's called elsewhere
- DocumentProcessingLog status is set to `processing` immediately on callback receipt and then updated to `completed` or `failed` — this avoids the log staying in stale state if the callback processing crashes
- `allSuccess` flag approach means partial success (some tasks created, some failed) returns `error` status rather than silently claiming full success

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- End-to-end flow complete: n8n POST callback -> validate -> flatten -> normalize (bridge) -> create task (TaskWebhook)
- CallbackController ready to receive real n8n extraction results
- ProcessingAdapter can be extended with additional normalization (date parsing, currency formatting) without changing the controller
- DocumentProcessingLog now tracks the full lifecycle including callback receipt timestamp and extraction result storage

---
*Phase: 15-resailai-pdf-integration*
*Completed: 2026-03-17*
