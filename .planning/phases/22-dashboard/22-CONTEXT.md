# Phase 22: Dashboard - Context

**Gathered:** 2026-03-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Livewire admin dashboard for monitoring the DOTW AI Module. Covers incoming API call logs, outgoing DOTW API call monitoring, booking lifecycle tracking, and error investigation with filtering. No n8n branding visible.

</domain>

<decisions>
## Implementation Decisions

### Dashboard placement
- **D-01:** Add new tabs to existing `/admin/dotw` page (DotwAdminIndex component) — "Dashboard", "Bookings", "Errors" tabs alongside existing credentials/audit-logs/api-tokens tabs
- **D-02:** Single entry point at `/admin/dotw` — no standalone page or separate sidebar link needed

### Data visualization
- **D-03:** Stats cards for key metrics (total bookings, errors today, pending payments, active prebooks) PLUS trend charts using ApexCharts (already integrated)
- **D-04:** Line/bar charts for: bookings over time, error rate trends, API response times
- **D-05:** Existing ApexCharts + Chart.js libraries — no new dependencies needed

### Lifecycle view layout
- **D-06:** Visual horizontal stepper/timeline for booking journey: search → prebook → confirmed → voucher sent
- **D-07:** Failed/cancelled bookings shown as red branches in the stepper
- **D-08:** Click a booking to see its full journey with timestamps at each step
- **D-09:** DotwAIBooking model has all necessary timestamps: created_at, voucher_sent_at, reminder_sent_at, auto_invoiced_at, cancellation_deadline

### Real-time behavior
- **D-10:** Livewire polling every 30 seconds for auto-refresh of metrics and tables
- **D-11:** No toast notifications — polling refresh is sufficient for this admin tool

### Claude's Discretion
- Exact tab ordering within the admin page
- Chart color scheme (should follow existing koromiko/blue palette)
- Stats card layout and grouping
- Error detail panel design
- Loading skeleton design during polling refresh
- Pagination size for tables (existing pattern: 25 per page)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — DASH-01 through DASH-05 define the five success criteria for this phase

### Existing admin UI pattern
- `app/Http/Livewire/Admin/DotwAdminIndex.php` — Tab-based admin component pattern to extend
- `app/Http/Livewire/Admin/DotwAuditLogIndex.php` — Audit log viewer with filtering, pagination, expandable rows (reuse/extend)
- `resources/views/livewire/admin/dotw-admin-index.blade.php` — Tab UI Blade template pattern

### Data models
- `app/Modules/DotwAI/Models/DotwAIBooking.php` — Primary data model with status constants, track types, 40+ fields including all lifecycle timestamps
- `database/migrations/2026_02_21_100001_create_dotw_audit_logs_table.php` — Audit log schema with operation_type enum and composite indexes

### Module config
- `app/Modules/DotwAI/Config/dotwai.php` — Module configuration (search limits, cache TTL, webhook config)

### Styling reference
- `resources/views/dashboard.blade.php` — Main app dashboard showing ApexCharts/Chart.js usage patterns and stats card layout

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `DotwAdminIndex` Livewire component: Tab-based admin pattern with Alpine.js tab switching — extend with new tabs
- `DotwAuditLogIndex` Livewire component: Paginated table with filters (operation_type, company_id, date range), expandable rows for payload inspection
- `dotw_audit_logs` table: Already stores all DOTW API calls with request/response payloads, operation_type (search/rates/block/book), company_id scoping, and composite indexes
- ApexCharts + Chart.js: Already integrated in main dashboard — reuse for trend charts
- `DotwAIBooking` model: Status constants (STATUS_PREBOOKED, STATUS_CONFIRMED, STATUS_FAILED, STATUS_CANCELLED, etc.), track constants (TRACK_B2B, TRACK_B2B_GATEWAY, TRACK_B2C), all lifecycle timestamps

### Established Patterns
- Tab navigation: Alpine.js `x-data="{ activeTab: '' }"` with Tailwind styling
- Livewire pagination: `WithPagination` trait, 25 per page
- Role-based access: `Auth::user()->role_id === Role::ADMIN` for super-admin, company_id scoping for company-level
- Dark mode: `dark:` Tailwind prefix throughout admin views
- Expandable table rows: Click-to-expand pattern for detail inspection

### Integration Points
- Extend `DotwAdminIndex` component with new tab methods/views (or create sub-components loaded via tabs)
- Query `dotwai_bookings` table for lifecycle and stats data
- Query `dotw_audit_logs` table for API call monitoring and error tracking
- Navigation: No changes needed — `/admin/dotw` already in sidebar
- Livewire route registration: via `DotwAIServiceProvider` (existing pattern)

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches following existing admin UI patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

### Reviewed Todos (not folded)
- **DOTW Hub Documentation milestone** (area: docs) — Documentation is a separate concern, not part of the monitoring dashboard phase.

</deferred>

---

*Phase: 22-dashboard*
*Context gathered: 2026-03-25*
