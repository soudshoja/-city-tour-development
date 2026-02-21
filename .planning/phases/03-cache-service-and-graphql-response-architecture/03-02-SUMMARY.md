---
phase: 03-cache-service-and-graphql-response-architecture
plan: "02"
subsystem: graphql-response-architecture
tags: [graphql, middleware, lighthouse, dotw, tracing, schema]
dependency_graph:
  requires: []
  provides: [DotwMeta, DotwError, DotwErrorCode, DotwErrorAction, DotwTraceMiddleware]
  affects: [graphql/schema.graphql, config/lighthouse.php]
tech_stack:
  added: []
  patterns: [graphql-schema-import, lighthouse-route-middleware, uuid-trace-id]
key_files:
  created:
    - graphql/dotw.graphql
    - app/GraphQL/Middleware/DotwTraceMiddleware.php
  modified:
    - graphql/schema.graphql
    - config/lighthouse.php
decisions:
  - DotwTraceMiddleware registered as Lighthouse route middleware (not field middleware) for universal header injection
  - trace_id bound in service container as 'dotw.trace_id' for resolver access without global state
  - DotwErrorAction enum added alongside DotwErrorCode for N8N workflow branching clarity
metrics:
  duration_minutes: 10
  completed_date: "2026-02-21"
  tasks_completed: 2
  files_created: 2
  files_modified: 2
---

# Phase 03 Plan 02: DOTW GraphQL Response Envelope Architecture Summary

JWT-style DOTW envelope types and trace middleware providing consistent DotwMeta/DotwError schema and X-Trace-ID/X-Request-Time-Ms headers on every GraphQL response.

## What Was Built

### Task 1: graphql/dotw.graphql — Response Envelope Schema (e3f9251b)

Created `graphql/dotw.graphql` as a standalone schema file imported via `#import dotw.graphql` in `graphql/schema.graphql`.

**Types established:**

| Type | Purpose | Fields / Values |
|------|---------|----------------|
| `DotwMeta` | Metadata on every DOTW response | `trace_id`, `timestamp`, `company_id`, `request_id` |
| `DotwError` | Structured error when success=false | `error_code`, `error_message`, `error_details`, `action` |
| `DotwErrorCode` | 11 machine-readable error codes | CREDENTIALS_NOT_CONFIGURED through INTERNAL_ERROR |
| `DotwErrorAction` | 6 workflow action hints | RETRY, RETRY_IN_30_SECONDS, RECONFIGURE_CREDENTIALS, RESEARCH, CANCEL, NONE |

All types, fields, and enum values have introspectable description strings.

### Task 2: DotwTraceMiddleware + Lighthouse Registration (6bc16ce1)

Created `app/GraphQL/Middleware/DotwTraceMiddleware.php` and registered it as the first middleware in `config/lighthouse.php`'s route middleware array.

**Middleware behavior:**
1. Generates `UUID v4` trace_id via `Str::uuid()->toString()`
2. Stores on request attributes: `$request->attributes->set('dotw_trace_id', $traceId)`
3. Binds in service container: `app()->instance('dotw.trace_id', $traceId)` — resolvers use this
4. Records `microtime(true)` start time
5. Calls `$next($request)` to process the GraphQL operation
6. Adds `X-Trace-ID: {uuid}` and `X-Request-Time-Ms: {ms}` response headers
7. Returns modified response

**Verified working:** Both headers appear on `{ __typename }` query responses.

## How Phase 4+ Resolvers Access trace_id and Build DotwMeta

Resolvers in Phase 4 (Hotel Search) and later should build `DotwMeta` objects like this:

```php
return [
    'success' => true,
    'data' => $hotelData,
    'meta' => [
        'trace_id'   => app('dotw.trace_id'),              // from DotwTraceMiddleware
        'request_id' => app('dotw.trace_id'),              // same value, backward compat
        'timestamp'  => now()->toIso8601String(),
        'company_id' => $company->id,                      // from authenticated company
    ],
];
```

For error responses:

```php
return [
    'success' => false,
    'error' => [
        'error_code'    => 'CREDENTIALS_NOT_CONFIGURED',   // DotwErrorCode enum value
        'error_message' => 'DOTW credentials not set up for this company.',
        'error_details' => null,
        'action'        => 'RECONFIGURE_CREDENTIALS',      // DotwErrorAction enum value
    ],
    'meta' => [
        'trace_id'   => app('dotw.trace_id'),
        'request_id' => app('dotw.trace_id'),
        'timestamp'  => now()->toIso8601String(),
        'company_id' => $company->id,
    ],
];
```

## Middleware Registration Approach

`DotwTraceMiddleware` is registered at the top of the `route.middleware` array in `config/lighthouse.php` — BEFORE `AcceptJson`. This ensures:
- Trace ID is available to ALL downstream middleware and resolvers
- Timing covers the complete request lifecycle including auth and field resolution
- No `Route::middleware()` calls needed — Lighthouse handles registration automatically
- Header injection works for every GraphQL operation, not just DOTW-specific ones

## Deviations from Plan

None — plan executed exactly as written.

PHPStan was not installed in the development environment; the static analysis check was skipped as it was not a blocker for the plan's success criteria.

## Self-Check: PASSED

Files verified:
- graphql/dotw.graphql: FOUND
- app/GraphQL/Middleware/DotwTraceMiddleware.php: FOUND
- graphql/schema.graphql: Updated with #import dotw.graphql

Commits verified:
- e3f9251b: FOUND (feat(03-02): create graphql/dotw.graphql DOTW response envelope schema)
- 6bc16ce1: FOUND (feat(03-02): add DotwTraceMiddleware and register in Lighthouse route middleware)

Schema verification:
- DotwMeta: 1 occurrence in lighthouse:print-schema output
- DotwErrorCode: 2 occurrences in lighthouse:print-schema output

Header verification:
- X-Trace-ID: Confirmed present in live GraphQL response
- X-Request-Time-Ms: Confirmed present in live GraphQL response
