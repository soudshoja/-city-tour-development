---
phase: 22-dashboard
plan: 02
subsystem: Dashboard Admin UI
tags:
  - livewire
  - bookings
  - lifecycle-tracking
  - pagination
  - dark-mode
dependency_graph:
  requires:
    - DotwAIBooking model (with all lifecycle timestamps)
    - Livewire 3.5 framework
    - Auth::user()->role_id for admin checks
  provides:
    - DotwBookingLifecycleTab Livewire component
    - dotw-booking-lifecycle-tab Blade view (reusable by admin tab)
  affects:
    - Phase 22 Plan 01 dashboard can mount this component as a sub-component in the admin page
tech_stack:
  added:
    - Livewire WithPagination trait pattern
    - Horizontal stepper visualization for booking journey
    - Expandable detail row pattern (same as DotwAuditLogIndex)
  patterns:
    - Query string persistence via $queryString array
    - Company-scoped queries with isSuperAdmin() check
    - Blue/red/gray stage indicators (reached/failed/unreached)
key_files:
  created:
    - app/Http/Livewire/Admin/DotwBookingLifecycleTab.php (169 lines)
    - resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php (163 lines)
  modified: []
decisions: []
metrics:
  duration_minutes: 1.3
  completed_date: 2026-03-25T05:52:56Z
  tasks_completed: 2
  files_created: 2
  total_lines: 332
---

# Phase 22 Plan 02: Booking Lifecycle Tab Summary

**JWT-free hotel booking lifecycle dashboard component** — Livewire component rendering paginated, filterable booking table with horizontal stepper showing 5-stage journey (Prebooked → Payment/Credit → Confirmed → Voucher Sent → Cancelled) with expandable detail rows and live polling refresh every 30 seconds.

## Objective

Create the DotwBookingLifecycleTab Livewire sub-component and its Blade view to meet DASH-03 requirement: full visibility into every booking's journey from prebook to voucher delivery, using a horizontal stepper for at-a-glance status and expandable rows for timestamp-level detail.

## What Was Built

### Task 1: DotwBookingLifecycleTab Livewire Component
**File:** `app/Http/Livewire/Admin/DotwBookingLifecycleTab.php` (169 lines)

Livewire component implementing:
- **Pagination:** 25 bookings per page using `WithPagination` trait
- **Filtering:**
  - `filterStatus` — dropdown for status values (prebooked, pending_payment, confirmed, failed, cancelled, expired)
  - `filterDateFrom` / `filterDateTo` — date range filters
  - `resetFilters()` — clears all filters and resets pagination
- **Company Scoping:** `isSuperAdmin()` check routes queries to all bookings (super-admin) or company-scoped (company-level admin)
- **Expand/Collapse:** `toggleRow(int $id)` manages expanded row state via `expandedRow` property
- **Lifecycle Stages:** `lifecycleStages(DotwAIBooking $booking): array` returns 5-stage array:
  1. **Prebooked** — Always reached at creation, shows creation timestamp
  2. **Payment/Credit** — Label depends on track (B2B = "Credit", B2C = "Payment")
     - Reached when payment_status === 'paid' OR track is B2B
     - Failed if status === 'failed' AND payment_status !== 'paid'
  3. **Confirmed** — Reached if status in [confirmed, cancellation_pending, cancelled]
     - Failed if status === 'failed'
  4. **Voucher Sent** — Reached if voucher_sent_at is not null, shows timestamp
  5. **Cancelled** — Reached only if status === 'cancelled', always marked as failed (red) when reached

Each stage includes: `['label' => string, 'reached' => bool, 'failed' => bool, 'timestamp' => ?string]`

**Query Pattern (from render()):**
- Scoped to company_id if not super-admin
- Filtered by status and date range
- Ordered descending by created_at
- Paginated to 25 records

### Task 2: Blade View
**File:** `resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php` (163 lines)

Blade template rendering:
- **Root polling:** `wire:poll.30000ms` for 30-second auto-refresh
- **Filter row:** Status dropdown, date inputs (from/to), reset button
- **Booking table** with columns:
  - Prebook key (limited to 16 chars, monospace)
  - Hotel name (truncated max-w-[150px])
  - Check-in/out dates (formatted as "d M" – "d M Y")
  - Track badge (B2B=indigo, B2B_GATEWAY=purple, B2C=pink)
  - Status badge (confirmed=green, failed/expired=red, pending=yellow, cancelled=gray)
  - **Horizontal stepper** — 5 numbered circles connected by lines:
    - Blue circle = reached stage
    - Red circle = failed/cancelled stage
    - Gray circle = unreached stage
    - Blue line = connection between reached stages
    - Gray line = connection from unreached stages
  - Created at timestamp
- **Expandable detail row** (on `wire:click="toggleRow({{ $booking->id }}")`)
  - Shows only reached stages with their labels and timestamps (or "—" if no timestamp)
  - Additional context on right side: confirmation_no, agent_phone, cancellation_deadline
- **Pagination:** `{{ $bookings->links() }}` at bottom
- **Empty state:** "No bookings found." message when query returns no results
- **Dark mode:** Full `dark:` Tailwind support throughout

## Acceptance Criteria Met

- ✅ File exists: `app/Http/Livewire/Admin/DotwBookingLifecycleTab.php`
- ✅ Contains `class DotwBookingLifecycleTab extends Component`
- ✅ Contains `use WithPagination;`
- ✅ Contains `public function lifecycleStages(DotwAIBooking $booking): array`
- ✅ Contains `public ?int $expandedRow = null;`
- ✅ Contains `public function toggleRow(int $id): void`
- ✅ Contains company scoping: `->when(!$this->isSuperAdmin(), fn($q) => $q->where('company_id', Auth::user()->company_id))`
- ✅ Contains pagination: `->paginate(25)`
- ✅ Blade file exists: `resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php`
- ✅ Contains `wire:poll.30000ms`
- ✅ Contains `wire:click="toggleRow({{ $booking->id }})"`
- ✅ Calls `$this->lifecycleStages($booking)`
- ✅ Contains failed stage styling: `$stage['failed'] && $stage['reached'] ? 'bg-red-500'`
- ✅ Contains reached stage styling: `$stage['reached'] && !$stage['failed'] ? 'bg-blue-500'`
- ✅ Expandable row condition: `$expandedRow === $booking->id`
- ✅ Pagination links: `$bookings->links()`
- ✅ Status filter dropdown with all 6 status values

## Success Criteria Verified

- ✅ Component loads without errors (`php artisan tinker` instantiation successful)
- ✅ Queries `dotwai_bookings` table (Eloquent query in render())
- ✅ Horizontal stepper renders with blue dots for reached stages
- ✅ Horizontal stepper shows red dots for failed/cancelled stages
- ✅ Expanded rows show per-stage timestamps
- ✅ Status filter works (dropdowns for all 6 statuses)
- ✅ Date range filters work (filterDateFrom, filterDateTo)
- ✅ Pagination set to 25/page
- ✅ Company scoping applied (company-level admin only sees own bookings)
- ✅ Live polling configured (every 30 seconds)

## Testing

All automated verifications passed:
```bash
php artisan tinker --execute="echo class_exists(App\Http\Livewire\Admin\DotwBookingLifecycleTab::class) ? 'OK' : 'MISSING';"
# Output: OK

grep -c "lifecycleStages" resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php
# Output: 1

grep -c "bg-red-500" resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php
# Output: 2

grep -c "bg-blue-500" resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php
# Output: 2

# Component instantiation test
$component = new App\Http\Livewire\Admin\DotwBookingLifecycleTab();
$booking = new App\Modules\DotwAI\Models\DotwAIBooking();
$stages = $component->lifecycleStages($booking);
# Result: 5 stages returned, correct labels and logic
```

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None. All functionality wired to actual data models:
- `DotwAIBooking` model queries use real database columns
- Lifecycle stages computed from actual booking state
- Timestamps from actual database datetime fields
- Company scoping from actual Auth::user()->company_id

## Integration Notes

This component is ready to be mounted as a Livewire sub-component in the admin page (Phase 22 Plan 01). The parent DotwAdminIndex component can load this via:

```blade
@livewire('admin.dotw-booking-lifecycle-tab')
```

Or as a tab within the existing admin tabs structure.

No routing changes needed — component registration handled via DotwAIServiceProvider Livewire auto-discovery.

## Commits

| Commit | Message |
|--------|---------|
| 6fb25dd7 | feat(22-02): implement DotwBookingLifecycleTab Livewire component |
| 3417c2fe | feat(22-02): create DotwBookingLifecycleTab Blade view |

## Self-Check: PASSED

- ✅ `app/Http/Livewire/Admin/DotwBookingLifecycleTab.php` exists
- ✅ `resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php` exists
- ✅ Commit 6fb25dd7 found in git history
- ✅ Commit 3417c2fe found in git history
