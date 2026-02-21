---
phase: quick-2
plan: "01"
subsystem: dotw-admin-ui
tags: [livewire, alpine, dotw, admin, credentials, audit-logs, api-tokens, tabs]
dependency_graph:
  requires: [quick-1, Phase 1 Plan 01, Phase 2 Plan 03]
  provides: [unified DOTW admin page at /admin/dotw]
  affects: [sidebar.blade.php, routes/web.php]
tech_stack:
  added: []
  patterns:
    - Alpine.js sidebar-tab pattern (x-data activeTab, x-show per panel)
    - Wrapper view + closure route (view() return, no controller class)
    - Livewire component embed inside parent component tab panel
key_files:
  created:
    - app/Http/Livewire/Admin/DotwAdminIndex.php
    - resources/views/livewire/admin/dotw-admin-index.blade.php
    - resources/views/admin/dotw/index.blade.php
  modified:
    - routes/web.php
    - resources/views/layouts/sidebar.blade.php
decisions:
  - "Route::redirect with named routes preserves existing sidebar references during transition period"
  - "Super Admin sees API info panel instead of form (credentials are per-company, no default company context)"
  - "Credential fields always empty on load — dotw_username and dotw_password never pre-filled from DB"
  - "DotwApiTokenIndex embeds via @livewire inside x-show panel — hidden from COMPANY role at PHP level"
metrics:
  duration: "~2 minutes"
  completed: "2026-02-21T23:01:59Z"
  tasks_completed: 2
  files_changed: 5
---

# Quick Task 2: Unified DOTW Admin Page — Summary

**One-liner:** Three separate DOTW admin pages consolidated into a single Alpine.js tabbed page at /admin/dotw with credentials form, audit logs, and API token management.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Create DotwAdminIndex Livewire component | cd3728e3 | DotwAdminIndex.php, dotw-admin-index.blade.php |
| 2 | Wire routes, wrapper view, and sidebar | 5368b85a | web.php, admin/dotw/index.blade.php, sidebar.blade.php |

## What Was Built

### DotwAdminIndex Livewire Component
- **File:** `app/Http/Livewire/Admin/DotwAdminIndex.php`
- Three-tab Alpine.js layout following the exact `settings/index.blade.php` sidebar-tab pattern
- `mount(string $tab = 'credentials')` — allows future deep-link to specific tab
- `saveCredentials()` — validates, calls `CompanyDotwCredential::updateOrCreate()`, clears sensitive fields after save
- `isSuperAdmin()` — DRY role check used for query scoping and blade conditionals
- `resolveCompanyId()` — returns null for ADMIN role (no default company), `Auth::user()->company?->id` for COMPANY role
- Credential fields always empty on load — `dotw_company_code` and `markup_percent` pre-filled from DB, username/password never exposed

### Livewire View
- **File:** `resources/views/livewire/admin/dotw-admin-index.blade.php`
- Three tabs: Credentials (key icon), Audit Logs (document-text icon), API Tokens (code-bracket icon)
- API Tokens sidebar button wrapped in `@if($isSuperAdmin)` — absent from DOM for COMPANY role
- Credentials tab: Super Admin sees API endpoint info panel; Company Admin sees form with validation
- Audit Logs tab: `@livewire(\App\Http\Livewire\Admin\DotwAuditLogIndex::class)` embedded
- API Tokens tab: `@livewire(\App\Http\Livewire\Admin\DotwApiTokenIndex::class)` wrapped in `@if($isSuperAdmin)`

### Wrapper View
- **File:** `resources/views/admin/dotw/index.blade.php`
- `<x-app-layout>` + `@livewire(\App\Http\Livewire\Admin\DotwAdminIndex::class)` — identical pattern to audit-logs.blade.php and api-tokens.blade.php

### Routes
- **GET /admin/dotw** → `admin.dotw.index` (new unified page, middleware: auth + dotw_audit_access)
- **ANY /admin/dotw/audit-logs** → 301 redirect to /admin/dotw (named `admin.dotw.audit-logs` preserved)
- **ANY /admin/dotw/api-tokens** → 301 redirect to /admin/dotw (named `admin.dotw.api-tokens` preserved)
- Old separate route groups (two groups with duplicate prefix) replaced by single consolidated group

### Sidebar
- **File:** `resources/views/layouts/sidebar.blade.php`
- WhatsApp AI link href changed from `route('admin.dotw.audit-logs')` to `route('admin.dotw.index')`
- No other changes — existing role guard `@if(in_array(..., [ADMIN, COMPANY]))` and WhatsApp icon preserved

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] `app/Http/Livewire/Admin/DotwAdminIndex.php` exists — FOUND
- [x] `resources/views/livewire/admin/dotw-admin-index.blade.php` exists — FOUND
- [x] `resources/views/admin/dotw/index.blade.php` exists — FOUND
- [x] `routes/web.php` has admin.dotw.index route — CONFIRMED via route:list
- [x] Sidebar points to admin.dotw.index — CONFIRMED via grep
- [x] Commits cd3728e3 and 5368b85a exist — CONFIRMED

## Self-Check: PASSED
