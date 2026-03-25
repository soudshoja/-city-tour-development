---
phase: 21
slug: lifecycle-history
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-25
---

# Phase 21 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (Laravel 11 built-in) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter "LifecycleServiceTest\|BookingHistoryTest"` |
| **Full suite command** | `php artisan test --filter DotwAI` |
| **Estimated runtime** | ~20 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick command
- **After every plan wave:** Run full suite
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 20 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 21-01-01 | 01 | 1 | LIFE-01 | unit | `php artisan test --filter LifecycleServiceTest::test_deadline_stored` | ❌ W0 | ⬜ pending |
| 21-01-01 | 01 | 1 | LIFE-02 | unit | `php artisan test --filter LifecycleServiceTest::test_reminders_dispatched` | ❌ W0 | ⬜ pending |
| 21-01-01 | 01 | 1 | LIFE-03 | unit | `php artisan test --filter LifecycleServiceTest::test_auto_invoice_after_deadline` | ❌ W0 | ⬜ pending |
| 21-01-01 | 01 | 1 | LIFE-04 | unit | `php artisan test --filter LifecycleServiceTest::test_apr_immediate_invoice` | ❌ W0 | ⬜ pending |
| 21-01-01 | 01 | 1 | LIFE-05 | unit | `php artisan test --filter LifecycleServiceTest::test_scheduler_command` | ❌ W0 | ⬜ pending |
| 21-02-01 | 02 | 2 | HIST-01 | unit | `php artisan test --filter BookingHistoryTest::test_booking_status` | ❌ W0 | ⬜ pending |
| 21-02-01 | 02 | 2 | HIST-02 | unit | `php artisan test --filter BookingHistoryTest::test_booking_history` | ❌ W0 | ⬜ pending |
| 21-02-01 | 02 | 2 | HIST-03 | unit | `php artisan test --filter BookingHistoryTest::test_resend_voucher` | ❌ W0 | ⬜ pending |
| 21-02-01 | 02 | 2 | HIST-04 | unit | `php artisan test --filter BookingHistoryTest::test_dotw_voucher_retrieval` | ❌ W0 | ⬜ pending |
| 21-02-01 | 02 | 2 | EVNT-01 | unit | `php artisan test --filter WebhookEventTest::test_event_dispatched` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/DotwAI/LifecycleServiceTest.php` — stubs for LIFE-01..05
- [ ] `tests/Unit/DotwAI/BookingHistoryTest.php` — stubs for HIST-01..04
- [ ] `tests/Unit/DotwAI/WebhookEventTest.php` — stubs for EVNT-01

*Existing infrastructure covers test framework — no install needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| WhatsApp reminder delivery | LIFE-02 | Requires Resayil API | Trigger scheduler, verify WhatsApp received |
| DOTW voucher PDF retrieval | HIST-04 | Requires live DOTW API | Call booking_status with real booking code |
| n8n webhook consumption | EVNT-01 | Requires n8n workflow | Verify n8n receives and processes event |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 20s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
