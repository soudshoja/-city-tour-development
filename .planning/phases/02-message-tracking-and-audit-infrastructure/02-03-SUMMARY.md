---
phase: 02-message-tracking-and-audit-infrastructure
plan: "03"
subsystem: ui
tags: [livewire, blade, tailwind, middleware, sidebar, audit-log, dotw, rbac]

dependency_graph:
  requires:
    - phase: "02-01"
      provides: "DotwAuditLog Eloquent model and dotw_audit_logs table"
  provides:
    - DotwAuditAccess middleware (Role::ADMIN and Role::COMPANY gating, registered as 'dotw_audit_access')
    - Route GET /admin/dotw/audit-logs -> admin.dotw.audit-logs
    - DotwAuditLogIndex Livewire component (role-scoped queries, filters, pagination)
    - dotw-audit-log-index.blade.php (role-aware columns, collapsible JSON payloads, operation badges)
    - WhatsApp AI sidebar button (visible to Role::ADMIN and Role::COMPANY)
  affects:
    - Phase 4 (Hotel Search GraphQL — admins will use this page to inspect search logs)
    - Phase 5 (Rate Browsing & Rate Blocking — rates/block operations appear here)
    - Phase 6 (Pre-Booking & Confirmation — book operations appear here)

tech-stack:
  added: []
  patterns:
    - Role-gating via custom middleware alias (dotw_audit_access) in bootstrap/app.php
    - Livewire WithPagination + queryString for shareable filtered URLs
    - Role-scoped Eloquent queries in Livewire render() — Company Admin auto-scoped to own company_id
    - @if($isSuperAdmin) blade directive for conditional column/filter visibility
    - Collapsible row pattern using $expandedRow state with toggleRow() Livewire method

key-files:
  created:
    - app/Http/Middleware/DotwAuditAccess.php
    - app/Http/Livewire/Admin/DotwAuditLogIndex.php
    - resources/views/livewire/admin/dotw-audit-log-index.blade.php
  modified:
    - bootstrap/app.php
    - routes/web.php
    - resources/views/layouts/sidebar.blade.php

key-decisions:
  - "Livewire component placed under App\Http\Livewire\Admin namespace (separate from existing App\Livewire) to group DOTW admin views"
  - "x-app-layout used for full-page rendering — consistent with existing admin pages (bulk-invoice views)"
  - "isSuperAdmin() calls Auth::user()->role_id === Role::ADMIN — pure PHP, no policy overhead for a simple role check"
  - "Sidebar button uses @if(in_array(...)) rather than @can for consistency with plan spec and existing sidebar ADMIN check pattern"

requirements-completed: [MSG-01, MSG-03]

duration: 10min
completed: 2026-02-21
---

# Phase 2 Plan 3: WhatsApp AI Button + DOTW Audit Log Viewer (Role-Based) Summary

**Role-gated DOTW audit log viewer (Livewire + Blade) with WhatsApp AI sidebar button — Super Admin sees all companies and ID columns, Company Admin sees own logs only.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-02-21T00:00:00Z
- **Completed:** 2026-02-21
- **Tasks:** 5
- **Files modified:** 6

## Accomplishments

- DotwAuditAccess middleware registered as `dotw_audit_access` alias — 403 for any role below Company Admin
- Full-page Livewire audit log viewer with role-based column visibility (id and company_id hidden from Company Admin)
- WhatsApp AI sidebar button visible to Super Admin and Company Admin, links to `/admin/dotw/audit-logs`
- Filterable by operation type, message ID, date range, and company ID (super admin only); URL-querystring-synced
- Collapsible JSON payload viewer with terminal-style green-on-dark display; operation type colored badges

## Task Commits

Each task was committed atomically:

1. **Task 1: DotwAuditAccess Middleware** - `39ce7bd9` (feat)
2. **Task 2: Route** - `404d8bee` (feat)
3. **Task 3: Sidebar Button** - `8c6d6074` (feat)
4. **Task 4: Livewire Component** - `02e1040d` (feat)
5. **Task 5: Blade View** - `b6ef8e9d` (feat)

## Files Created/Modified

- `app/Http/Middleware/DotwAuditAccess.php` - Middleware: allows Role::ADMIN and Role::COMPANY only, 403 for all others
- `bootstrap/app.php` - Registered `dotw_audit_access` middleware alias in withMiddleware()
- `routes/web.php` - Route GET /admin/dotw/audit-logs with auth + dotw_audit_access middleware
- `resources/views/layouts/sidebar.blade.php` - WhatsApp AI sidebar button added after Bulk Invoice Upload block
- `app/Http/Livewire/Admin/DotwAuditLogIndex.php` - Livewire component: role-scoped query, 5 filters, toggleRow, paginate(25)
- `resources/views/livewire/admin/dotw-audit-log-index.blade.php` - Full-page view: filter bar, role-aware table, collapsible payloads, pagination

## Decisions Made

- **`App\Http\Livewire\Admin` namespace** — Grouped separately from existing `App\Livewire` to keep DOTW admin components distinct and future-proof for Phase 4-6 admin views
- **`x-app-layout` for full-page** — Consistent with the `bulk-invoice` admin views which also use `x-app-layout`
- **`isSuperAdmin()` as a method** — Keeps the role check DRY; called in both `render()` (for query scoping) and passed to view (for blade conditionals)
- **Sidebar uses `@if(in_array(...))` not `@can`** — The plan specified this pattern and it matches the `Role::ADMIN` check already used in the sidebar for the company switcher component

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

Phase 2 is now fully complete (all 3 plans done). The audit log viewer is live and will populate automatically as DOTW GraphQL operations execute in Phases 4-6. The middleware and route are production-ready.

---
*Phase: 02-message-tracking-and-audit-infrastructure*
*Completed: 2026-02-21*
