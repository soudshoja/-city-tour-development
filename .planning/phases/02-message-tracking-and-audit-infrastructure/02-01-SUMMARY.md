---
phase: 02-message-tracking-and-audit-infrastructure
plan: "01"
subsystem: dotw-audit
tags: [migration, eloquent-model, service, audit-logging, sanitization, dotw]
dependency_graph:
  requires: []
  provides:
    - dotw_audit_logs table (migration)
    - DotwAuditLog Eloquent model
    - DotwAuditService with sanitization
  affects:
    - Plan 02-02 (DotwService wiring — consumes DotwAuditService)
tech_stack:
  added: []
  patterns:
    - Append-only audit log (created_at only, no updated_at)
    - Recursive payload sanitization (case-insensitive key matching)
    - Fail-silent logging pattern (audit failure never breaks operation)
    - Semantic factory method (DotwAuditLog::log() over direct ::create())
key_files:
  created:
    - database/migrations/2026_02_21_100001_create_dotw_audit_logs_table.php
    - app/Models/DotwAuditLog.php
    - app/Services/DotwAuditService.php
  modified: []
decisions:
  - No FK constraint on company_id — DOTW module stays standalone per MOD-06
  - UPDATED_AT = null — audit logs are append-only, immutable after creation
  - Recursive sanitization uses closure with self-reference (not array_walk_recursive) for clarity
  - Audit failure caught but not rethrown — audit log must never break operations
  - Return unsaved DotwAuditLog on failure — callers can always type-check the return value
metrics:
  duration: "5 minutes"
  completed: "2026-02-21"
  tasks_completed: 3
  files_changed: 3
---

# Phase 2 Plan 1: DOTW Audit Infrastructure Summary

**One-liner:** Append-only dotw_audit_logs table with recursive credential-stripping DotwAuditService.

## Files Created

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_21_100001_create_dotw_audit_logs_table.php` | Creates dotw_audit_logs table with all required columns |
| `app/Models/DotwAuditLog.php` | Eloquent model for audit log rows; UPDATED_AT=null; payload columns cast to array |
| `app/Services/DotwAuditService.php` | Single write point for all DOTW audit logs; sanitizes before persisting |

## Migration Table Structure

Table name: `dotw_audit_logs`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | NOT NULL | Primary key |
| `company_id` | BIGINT UNSIGNED | NULL | No FK — module is standalone (MOD-06) |
| `resayil_message_id` | VARCHAR(255) | NULL | WhatsApp message_id from X-Resayil-Message-ID header |
| `resayil_quote_id` | VARCHAR(255) | NULL | Quoted message_id from X-Resayil-Quote-ID header |
| `operation_type` | ENUM('search','rates','block','book') | NOT NULL | Operation classification |
| `request_payload` | LONGTEXT | NULL | Sanitized request (JSON string) |
| `response_payload` | LONGTEXT | NULL | Sanitized response (JSON string) |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | NOT NULL | Append-only — no updated_at |

**Indexes:**
- `dotw_audit_logs_company_operation_idx` on `(company_id, operation_type)` — efficient query by company
- `dotw_audit_logs_message_id_idx` on `resayil_message_id` — WhatsApp conversation linking

## DotwAuditService::log() Method Signature

```php
public function log(
    string $operationType,        // One of: 'search' | 'rates' | 'block' | 'book'
    array $request,               // Raw request payload — will be sanitized
    array $response,              // Raw response payload — will be sanitized
    ?string $resayilMessageId = null,  // From X-Resayil-Message-ID header
    ?string $resayilQuoteId = null,    // From X-Resayil-Quote-ID header
    ?int $companyId = null             // Company context (nullable)
): DotwAuditLog
```

**Constants available:**
```php
DotwAuditService::OP_SEARCH = 'search'
DotwAuditService::OP_RATES  = 'rates'
DotwAuditService::OP_BLOCK  = 'block'
DotwAuditService::OP_BOOK   = 'book'
```

## Sanitization Keys List

The following keys are redacted (replaced with `'[REDACTED]'`) at any nesting depth, case-insensitively:

| Key Pattern | Reason |
|-------------|--------|
| `password` | DOTW MD5 password and any generic password field |
| `dotw_password` | DOTW-specific password key |
| `dotw_username` | DOTW-specific username key |
| `username` | Generic username |
| `md5` | MD5 hash values (common in DOTW auth) |
| `secret` | API secrets |
| `token` | Auth tokens |
| `authorization` | Authorization headers |
| `credit_card` | Payment card number |
| `card_number` | Payment card number (alternate naming) |
| `cvv` | Card verification value |
| `passport_number` | Passenger PII |

**Plan 02-02 Reference:** When wiring DotwAuditService into DotwService/middleware, use `DotwAuditService::OP_*` constants and call `$auditService->log(...)`. These key names are also the complete list — no additional keys need to be added unless the DOTW XML schema introduces new credential fields.

## Decisions Made

1. **No FK on company_id** — DOTW module is standalone per MOD-06. `company_id` is a soft reference only, allowing audit logs to survive company record changes without cascade issues.

2. **UPDATED_AT = null** — Audit logs are immutable after creation. Setting `UPDATED_AT = null` ensures Eloquent never attempts to set an `updated_at` column that doesn't exist, and makes the append-only intent explicit.

3. **Recursive sanitizer uses closure** — Instead of `array_walk_recursive` (which doesn't allow replacing scalar values with different types cleanly), the implementation uses a self-referencing closure for clarity and full control over key replacement.

4. **Fail-silent logging** — `DotwAuditService::log()` wraps `DotwAuditLog::log()` in try/catch. On failure, it writes to `Log::channel('dotw')` but never rethrows. An audit failure must never prevent a search/booking from completing.

5. **Unsaved model on failure** — When the DB write fails, the method returns `new DotwAuditLog([...])` (not null) so callers can always type-check against `DotwAuditLog` without null checks.

## DB Verification Note

The local development environment does not have MySQL server installed (only the MySQL client). The migration file is syntactically correct and structurally verified. To run the full `php artisan migrate` verification, execute on a machine with MySQL running:

```bash
php artisan migrate --pretend  # Should show: create table dotw_audit_logs
php artisan migrate            # Should complete without errors
php artisan tinker --execute="Schema::hasTable('dotw_audit_logs') ? 'EXISTS' : 'MISSING'"
```

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| Task 1 | `9682ba6e` | feat(02-01): create dotw_audit_logs migration |
| Task 2 | `2cadf62a` | feat(02-01): create DotwAuditLog Eloquent model |
| Task 3 | `395fdaf1` | feat(02-01): create DotwAuditService with sanitized audit logging |

## Deviations from Plan

None — plan executed exactly as written.

The only deviation from the verification steps is that `php artisan migrate` could not be run because MySQL server is not installed in the local development environment. The migration file is syntactically valid PHP and structurally correct per all plan specifications. All sanitization logic was verified via standalone PHP test that confirmed all 10 test cases pass.
