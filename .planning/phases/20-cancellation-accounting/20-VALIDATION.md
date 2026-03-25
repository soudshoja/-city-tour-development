---
phase: 20
slug: cancellation-accounting
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-24
---

# Phase 20 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (Laravel 11 built-in) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter "CancellationServiceTest\|AccountingServiceTest"` |
| **Full suite command** | `php artisan test --filter DotwAI` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter "CancellationServiceTest|AccountingServiceTest"`
- **After every plan wave:** Run `php artisan test --filter DotwAI`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 20-01-01 | 01 | 1 | CANC-01 | unit | `php artisan test --filter CancellationServiceTest::test_preview_returns_penalty_amount` | ❌ W0 | ⬜ pending |
| 20-01-01 | 01 | 1 | CANC-01 | unit | `php artisan test --filter CancellationServiceTest::test_confirm_cancels_booking` | ❌ W0 | ⬜ pending |
| 20-01-01 | 01 | 1 | CANC-02 | unit | `php artisan test --filter MessageBuilderServiceTest::test_cancellation_confirmed_includes_dotw_warning` | ❌ W0 | ⬜ pending |
| 20-01-02 | 01 | 1 | CANC-03 | unit | `php artisan test --filter AccountingServiceTest::test_creates_invoice_and_journal_for_penalty` | ❌ W0 | ⬜ pending |
| 20-01-02 | 01 | 1 | CANC-04 | unit | `php artisan test --filter AccountingServiceTest::test_free_cancellation_no_accounting` | ❌ W0 | ⬜ pending |
| 20-01-02 | 01 | 1 | ACCT-01 | unit | `php artisan test --filter CancellationServiceTest::test_free_cancellation_only_updates_status` | ❌ W0 | ⬜ pending |
| 20-02-01 | 02 | 2 | ACCT-02 | unit | `php artisan test --filter StatementServiceTest::test_statement_totals` | ❌ W0 | ⬜ pending |
| 20-02-01 | 02 | 2 | ACCT-03 | unit | `php artisan test --filter CancellationServiceTest::test_no_journal_entry_on_preview` | ❌ W0 | ⬜ pending |
| 20-02-01 | 02 | 2 | ACCT-04 | unit | `php artisan test --filter AccountingServiceTest::test_journal_entry_has_explicit_company_id` | ❌ W0 | ⬜ pending |
| 20-02-02 | 02 | 2 | ACCT-05 | unit | `php artisan test --filter CreditServiceTest::test_credit_history_returns_transactions` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Modules/DotwAI/CancellationServiceTest.php` — stubs for CANC-01, CANC-04, ACCT-01, ACCT-03
- [ ] `tests/Feature/Modules/DotwAI/AccountingServiceTest.php` — stubs for CANC-03, CANC-04, ACCT-01, ACCT-04
- [ ] `tests/Feature/Modules/DotwAI/StatementServiceTest.php` — stubs for ACCT-02

*Existing infrastructure covers test framework — no install needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| WhatsApp cancellation message delivery | CANC-02 | Requires Resayil API round-trip | Trigger cancellation, verify WhatsApp message received |
| Statement reconciliation with DOTW portal | ACCT-02 | Requires DOTW portal access | Compare generated statement with DOTW portal data |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
