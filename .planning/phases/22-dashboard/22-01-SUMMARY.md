---
phase: 22-dashboard
plan: 01
completed_date: "2026-03-25T05:55:09Z"
duration_seconds: 348
tasks_completed: 2
files_created: 2
files_modified: 0
commit: "6cc72dd2"
subsystem: DOTW Dashboard
tags:
  - livewire
  - apexcharts
  - monitoring
  - rest-api-support
dependency_graph:
  provides:
    - DotwDashboardTab Livewire component (admin oversight of DOTW operations)
    - dotw-dashboard-tab.blade.php (dashboard UI with charts and table)
  requires:
    - DotwAIBooking model (dotwai_bookings table)
    - DotwAuditLog model (dotw_audit_logs table)
    - Livewire 3.5, ApexCharts
    - Auth & Role system (isSuperAdmin pattern)
  affects:
    - DASH-01 (incoming API call logs)
    - DASH-02 (outgoing DOTW call monitoring with empty response flagging)
tech_stack:
  added:
    - ApexCharts (line chart for booking trend, bar chart for operations)
    - Livewire 3 @script block for scoped JS
  patterns:
    - Company-scoped queries (isSuperAdmin() check before filtering)
    - wire:poll for auto-refresh without full page reload
    - Loading state management with wire:loading directives
key_files:
  created:
    - app/Http/Livewire/Admin/DotwDashboardTab.php (178 lines)
    - resources/views/livewire/admin/dotw-dashboard-tab.blade.php (258 lines)
decisions:
  - Dashboard refreshes every 30 seconds (wire:poll.30000ms) to keep metrics current without overwhelming DB
  - Chart initialization destroys and re-creates on every poll to handle dark mode changes and prevent duplicate DOM elements
  - Empty response detection uses empty($log->response_payload) to flag DOTW calls that returned null/empty JSON
  - Operation labels (search/rates/block/book) hardcoded; no enum dependency on DOTW module
---

# Phase 22 Plan 01: DOTW Dashboard Summary

## One-liner
Livewire component and Blade view for DOTW module health monitoring with 4 stats cards, 2 ApexCharts, and live API call log table.

## Objective
Create the DotwDashboardTab Livewire sub-component and its Blade view. Provides administrators with a live overview of DOTW AI module health: four key metric cards, two trend charts (bookings over 14 days, API operation breakdown), an incoming API call log, and flagging of outgoing DOTW calls that returned empty/null responses.

## What Was Built

### Task 1: DotwDashboardTab Livewire Component
**File:** `app/Http/Livewire/Admin/DotwDashboardTab.php` (178 lines)

**Components:**
- Public properties for 4 stat cards: `$totalBookings`, `$bookingsToday`, `$errorsToday`, `$activePrebooks`
- Public properties for chart data: `$bookingTrendDates`, `$bookingTrendCounts`, `$operationLabels`, `$operationCounts`
- Public property for recent logs: `$recentLogs` (plain array, no pagination — fixed 25 rows)

**Methods:**
- `isSuperAdmin(): bool` — Returns true if current user role_id === Role::ADMIN (constant 1)
- `mount(): void` — Calls refreshMetrics() on component initialization
- `refreshMetrics(): void` — Executes 8 queries:
  1. Total bookings (all time)
  2. Bookings today
  3. Errors today (failed + expired status only)
  4. Active prebooks (prebooked + pending_payment)
  5. Booking trend (14-day date range with counts)
  6. Operation breakdown (7-day window, grouping by operation_type)
  7. Recent API log (last 25 dotw_audit_logs rows, ordered desc by created_at)
  8. Dispatch 'dashboardMetricsUpdated' event for chart re-init
- `render(): View` — Returns `view('livewire.admin.dotw-dashboard-tab')`

**Company Scoping:**
- Every query uses `.when(!$this->isSuperAdmin(), fn($q) => $q->where('company_id', Auth::user()->company_id))`
- Super-admins (role_id=1) see all companies' data
- Company-level users see only their own company's bookings and API calls

**Empty Response Detection:**
- Maps DotwAuditLog rows to plain arrays with `has_empty_response = empty($log->response_payload)`
- response_payload is cast to array; empty means null or empty array

### Task 2: dotw-dashboard-tab.blade.php
**File:** `resources/views/livewire/admin/dotw-dashboard-tab.blade.php` (258 lines)

**UI Sections:**
1. **Header** — "DOTW Module Overview" with "Refreshes every 30 seconds" note
2. **4 Stats Cards** (grid-cols-2 on mobile, grid-cols-4 on lg):
   - Total Bookings (blue)
   - Bookings Today (green)
   - Errors Today (red)
   - Active Prebooks (yellow)
   - Each card has wire:loading.remove + wire:loading skeletons for smooth polling
3. **2 ApexCharts** (grid-cols-1 on mobile, grid-cols-2 on lg):
   - Booking trend (line chart, 14 dates on x-axis, counts on y-axis)
   - API operations (bar chart, [search/rates/block/book] on x-axis, call counts on y-axis)
4. **Recent API Calls Table** (last 25 entries, no pagination):
   - Columns: ID, Company, Message ID, Operation, Response, Time
   - Operation badges: green for 'book', blue for 'search', gray for 'rates'/'block'
   - Response column: "OK" in green OR red "Empty" badge with exclamation icon (for empty_payload)
5. **Charts JavaScript** (@script block, Livewire 3 scoped):
   - Dark mode detection (document.documentElement.classList.contains('dark'))
   - Chart initialization with text color + grid color per theme
   - Destroy existing charts before re-init (prevents duplication on wire:poll)
   - Listen to 'dashboardMetricsUpdated' event for re-init after every poll

**Key Styling:**
- Dark mode support throughout (dark: prefixes)
- Tailwind utilities: text-xs/sm, grid, gap, border, rounded
- No inline styles
- Heroicons SVG for empty response warning icon

**Data Labels:**
- No "Resayil" or "n8n" in any visible label (satisfies DASH-01 no-branding requirement)
- Operation type enum values shown as-is (search, rates, block, book)
- Message ID trimmed to 20 chars with Str::limit()

## Acceptance Criteria Met

- ✅ File `app/Http/Livewire/Admin/DotwDashboardTab.php` exists with `class DotwDashboardTab extends Component`
- ✅ Contains `public function refreshMetrics(): void`
- ✅ Contains `public array $recentLogs = [];`
- ✅ Company scoping present on all queries (3+ occurrences of isSuperAdmin check)
- ✅ Event dispatch: `$this->dispatch('dashboardMetricsUpdated');`
- ✅ File `resources/views/livewire/admin/dotw-dashboard-tab.blade.php` contains `wire:poll.30000ms="refreshMetrics"`
- ✅ Loading skeletons: `wire:loading.remove` and `wire:loading` directives (4 cards × 2)
- ✅ ApexCharts containers: `id="dotw-bookings-trend-chart"` and `id="dotw-operations-chart"`
- ✅ Chart re-init: `$wire.on('dashboardMetricsUpdated', () => { initCharts(); })`
- ✅ Empty response badge: Red badge with "Empty" label + exclamation icon
- ✅ No "Resayil" or "n8n" in user-facing labels
- ✅ Livewire 3 @script block for scoped JS

## Verification Results

**Code Quality:**
- PHP syntax check: PASSED (No syntax errors)
- Blade syntax check: PASSED (No syntax errors)

**Component Verification:**
- Class declaration: FOUND ✓
- refreshMetrics() method: FOUND ✓
- recentLogs property: FOUND ✓
- Company scoping (isSuperAdmin): FOUND ✓
- Event dispatch: FOUND ✓
- Wire polling directive: FOUND ✓
- Loading skeletons: FOUND ✓
- ApexCharts containers: FOUND ✓
- Chart re-init logic: FOUND ✓
- Empty response flagging: FOUND ✓
- No forbidden branding: VERIFIED ✓

## Deviations from Plan

None — plan executed exactly as written.

## Data Flow

1. **Component Mount** → refreshMetrics() called
2. **refreshMetrics()** →
   - Queries DotwAIBooking for 4 stats (all time, today, errors, active)
   - Queries DotwAIBooking with DATE grouping for 14-day trend
   - Queries DotwAuditLog with GROUP BY operation_type for 7-day operations
   - Queries DotwAuditLog latest 25 rows and maps to plain array
   - Dispatches 'dashboardMetricsUpdated' event
3. **Blade Renders** →
   - 4 stat cards with Livewire properties
   - ApexCharts containers ready for JS init
   - Table from $recentLogs array
4. **@script Block Runs** →
   - initCharts() called immediately
   - Listens to 'dashboardMetricsUpdated' event for re-init
5. **Every 30 Seconds** →
   - wire:poll triggers refreshMetrics() again
   - New data loaded into public properties
   - Event dispatched → charts destroyed and re-initialized
   - Blade updates only changed values, no full page reload

## Known Stubs

None.

## Self-Check: PASSED

- ✅ File created: `app/Http/Livewire/Admin/DotwDashboardTab.php`
- ✅ File created: `resources/views/livewire/admin/dotw-dashboard-tab.blade.php`
- ✅ Commit exists: `6cc72dd2`
- ✅ All acceptance criteria verified
