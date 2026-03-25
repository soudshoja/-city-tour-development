# Phase 22: Dashboard - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-25
**Phase:** 22-dashboard
**Areas discussed:** Dashboard placement, Data visualization, Lifecycle view layout, Real-time behavior

---

## Dashboard Placement

| Option | Description | Selected |
|--------|-------------|----------|
| New tabs in existing page | Add 'Dashboard', 'Bookings', 'Errors' tabs to existing /admin/dotw page alongside credentials/audit-logs/api-tokens. Single entry point, consistent with current pattern. | ✓ |
| Standalone page | New route /admin/dotw/dashboard with its own layout. Separate from settings. Sidebar gets a second DOTW link. | |
| Replace current audit tab | Upgrade the existing audit-logs tab into a full dashboard with sub-sections. Keep one page, fewer tabs. | |

**User's choice:** New tabs in existing page (Recommended)
**Notes:** Consistent with established DotwAdminIndex tab pattern.

---

## Data Visualization

| Option | Description | Selected |
|--------|-------------|----------|
| Stats cards + tables only | Key metrics as number cards. Data in filterable tables below. Simple, fast to build. | |
| Cards + trend charts | Stats cards PLUS line/bar charts for bookings over time, error rate trends, API response times. ApexCharts already available. | ✓ |
| Full analytics dashboard | Cards, charts, pie charts for track distribution, heatmaps for peak hours, comparison widgets. Most comprehensive but heavier build. | |

**User's choice:** Cards + trend charts (Recommended)
**Notes:** Leverages existing ApexCharts integration.

---

## Lifecycle View Layout

| Option | Description | Selected |
|--------|-------------|----------|
| Visual stepper/timeline | Horizontal stepper showing search → prebook → confirmed → voucher sent, with timestamps. Failed/cancelled as red branches. Click to see full journey. | ✓ |
| Status table with expandable rows | Table of bookings with status column. Click to expand and see all timestamps. Simpler, reuses existing expandable-row pattern. | |
| Kanban-style columns | Bookings grouped in columns by status. Visual grouping helps spot bottlenecks. | |

**User's choice:** Visual stepper/timeline (Recommended)
**Notes:** DotwAIBooking model has all lifecycle timestamps needed.

---

## Real-time Behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Livewire polling every 30s | Auto-refresh key metrics and tables every 30 seconds. Low server impact, keeps data fresh. | ✓ |
| Manual refresh only | Data loads on page visit. User clicks refresh button. Simplest, zero background load. | |
| Polling + toast alerts | 30s polling PLUS toast notifications for new errors or failed bookings. More proactive but adds complexity. | |

**User's choice:** Livewire polling every 30s (Recommended)
**Notes:** No toast alerts needed — polling refresh is sufficient.

---

## Claude's Discretion

- Tab ordering, chart colors, stats card layout, error detail panel, loading skeletons, pagination size

## Deferred Ideas

- DOTW Hub Documentation milestone — separate from monitoring dashboard
