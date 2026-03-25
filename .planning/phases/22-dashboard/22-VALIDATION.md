---
phase: 22
slug: dashboard
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-25
---

# Phase 22 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.x (via `php artisan test`) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter DotwAI` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter DotwAI`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 22-01-01 | 01 | 1 | DASH-01, DASH-02 | feature | `php artisan test --filter DotwDashboardTab` | ❌ W0 | ⬜ pending |
| 22-01-02 | 01 | 1 | DASH-01, DASH-02 | feature | `php artisan test --filter DotwDashboardTab` | ❌ W0 | ⬜ pending |
| 22-02-01 | 02 | 1 | DASH-03 | feature | `php artisan test --filter DotwBookingLifecycle` | ❌ W0 | ⬜ pending |
| 22-02-02 | 02 | 1 | DASH-03 | feature | `php artisan test --filter DotwBookingLifecycle` | ❌ W0 | ⬜ pending |
| 22-03-01 | 03 | 2 | DASH-04, DASH-05 | feature | `php artisan test --filter DotwErrorTracker` | ❌ W0 | ⬜ pending |
| 22-03-02 | 03 | 2 | DASH-01 | integration | `php artisan test --filter DotwAdminIndex` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Modules/DotwAI/DotwDashboardTabTest.php` — stubs for DASH-01, DASH-02
- [ ] `tests/Feature/Modules/DotwAI/DotwBookingLifecycleTabTest.php` — stubs for DASH-03
- [ ] `tests/Feature/Modules/DotwAI/DotwErrorTrackerTabTest.php` — stubs for DASH-04, DASH-05

*Existing PHPUnit + Livewire testing infrastructure covers all framework requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| ApexCharts render correctly | DASH-01 | JS chart rendering not testable in PHPUnit | Load /admin/dotw, switch to Dashboard tab, verify charts render with data |
| Horizontal stepper visual layout | DASH-03 | CSS/visual layout not testable in PHPUnit | Load Bookings tab, click a booking, verify stepper shows blue/red dots |
| 30s polling refresh | DASH-01 | Real-time polling requires browser | Wait 30s on dashboard, verify metrics update without manual refresh |
| Dark mode styling | All | Visual CSS not testable in PHPUnit | Toggle dark mode, verify all tabs render correctly |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
