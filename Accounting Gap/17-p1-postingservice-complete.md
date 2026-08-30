# 17 — P1 PostingService: COMPLETE (verdict SHIP) + P2 cutover gates

**Date:** 2026-08-24
**Verdict:** **SHIP** — after 4 build/fix/verify rounds (opus adversarial verification each round, xhigh effort).
**Test status:** accounting suite GREEN — **36 tests, 350 assertions, 0 failures, 1 intentional skip** (`ArchitectureTest::no_journal_entry_writes_outside_engine`, the documented P2 phase-exit gate).
**Safety:** engine is **flag-OFF and wired to nothing** — proven by execution, not by reading. Nothing in production or dev is affected.

---

## 1. What was built (P1 deliverables, per file 11 §P1)

| Area | Delivered |
|---|---|
| Registry | `system_accounts` (purpose-code → leaf, replaces ~237 name-string lookups), `SystemAccount` model, `SystemAccountsSeeder` (idempotent, skips+reports on ambiguity) |
| Numbering | `serial_schemas` + `SerialSchema` + `SequenceService` (atomic, `SELECT … FOR UPDATE`, branch-scoped) |
| Engine | `PostingService` (`post`/`reverse`/`repost`), `DocumentDraft`/`LineDraft`/`PostedDocument`, `AccountResolver`, `AccountService`, `AccountCodeGenerator`, `PeriodGuard`, `Money` |
| Document header | `transactions` gains doc_type/sub_type/doc_year/posting_status/total_debit/total_credit/reversal_of_transaction_id/idempotency_key + `unique(company_id, idempotency_key)` |
| Guards | `config/accounting.php` — `engine.enabled` (default false) **and an independent** `account_observer.enabled` (default false) |
| Admin visibility | `idempotency_key_rejections` table + `IdempotencyKeyRejection` model |
| Tests | Unit + Feature acceptance suite, C1 invariant helper (now capable of failing — proven) |

## 2. Round-by-round (why 4 rounds)

| Round | Outcome |
|---|---|
| 1 build | FIX-FIRST — 5 blockers + 22 findings. Worst: **AccountResolver never asserted tenant ownership** on the purpose-code path — a mis-seeded registry row would write cross-tenant journal lines, the exact failure the engine exists to prevent. |
| 2 fix | 3 blockers fixed, 2 partial, **3 NEW regressions introduced by the fixes** — incl. parallel fixers contradicting each other on the leaf rule (PostingService stopped trusting `is_group`, AccountResolver still checked it → the whole purpose-code path dead on real data). |
| 3 fix | All 5 blockers + all 3 regressions closed. **Tests made safe and actually RUN for the first time** (26 passed). Verifier proved 3 prior "fixed" claims false by reading code — everything before this was unexecuted assertion. |
| 4 fix | 3 final blocking items closed with owner decisions (below). **SHIP.** |

**Owner decisions taken in round 4:**
- **Document numbering: branch-scoped, branch visible in the number.** `DEFAULT_MASK = {TYPE}{BRANCH:4}-{YYYY}-{SEQ:5}`. Branchless renders `INV-2026-00005` (byte-identical to legacy shape); branch 7 renders `INV-0007-2026-00005`. `branches` has no code column → zero-padded id used.
- **Reused idempotency key on a soft-deleted transaction: THROW + admin visibility.** `SupersededIdempotencyKeyException` (carries key, dead transaction id, deletion timestamp, attempted amount) + a persisted, queryable `idempotency_key_rejections` row that survives the transaction rollback.

## 3. ⚠ P2 CUTOVER GATES — must be true before ANY company goes live on the engine

W1 build work may start now. **Switching a company to live must not happen until these are done, in order:**

1. **WIRE THE KILL SWITCH — genuinely blocking.** `config('accounting.engine.enabled')` and `companies.posting_engine_enabled` are read by **zero production code** today (only tests and docblocks). `config/accounting.php:11-14` admits it ("In P1 this is inert"). **There is currently no rollback lever — flipping the flag does nothing.** W1's first feeder must consult both gates before it ships.
2. **RESOLVE THE `actual_balance` GAP.** The engine deliberately maintains no legacy balance column (it is `decimal(10,2)` and truncates 3dp KWD fils deltas — see resolved-gap #10 in the class docblock, step 9 is a no-op). But W1's own feeders still read AND write it — e.g. `CheckMyFatoorahPayments.php:163` reads `$paymentGateway->actual_balance`, `:170` writes it back. Cutting that command onto PostingService silently stops the decrement while the same command keeps reading a stale value. **115 references across 20+ files** (AccountingController, CoaController, InvoiceController, PaymentController, ChatController, AccountsExport…). Either widen the column and restore maintenance, or migrate readers onto `TrialBalanceService`.
3. **RUN `accounting:seed-serial-schemas`.** Never executed. It is the only protection against engine-minted numbers colliding with legacy ones — the new `unique(company_id, doc_type, reference_number)` index cannot see legacy rows (`doc_type IS NULL`, and `doc_type` is not in `Transaction::$fillable`).

## 4. Known remaining items (non-blocking, tracked)

| Sev | Item |
|---|---|
| MEDIUM | A `serial_schemas.mask` lacking `{BRANCH}` still mints identical numbers for two branches — and the new unique index then rejects the legitimate second post with an **untyped driver exception**. `post()` has no handler for a `reference_number` unique violation at all. |
| LOW | `{BRANCH}` renders the raw **platform-wide** `branches.id`; overflows `reference_number varchar(20)` at branch id ≥ 100000 or serial ≥ 1,000,000. **[USER-DECIDE]** if that scale is plausible — widen the column ahead of time. |
| LOW | `idempotency_key_rejections` has no dedupe/rate limit; lives on a connection the test harness does not roll back. |
| LOW | The genuine concurrent-race **loser still burns a document number** (serial reserved before the header insert; the catch returns the winner and commits the increment). Produces gaps, never duplicates. Documented, unfixed. |
| LOW | `AccountObserver` hooks only `creating()`; its own message overstates the guarantee. |
| LOW | `accounting_audit` derives its DB from `DB_DATABASE` while the test connection uses `DB_TEST_DATABASE` — safe only because `phpunit.xml` forces both. |

## 5. Pre-existing RED tests found (NOT caused by this work — launch-track relevant)

- **`AccountingRouteGateTest` ×2 — `coa.index` returns 500** (`childAccounts on null`) for a company with **no root COA rows**. The P0 route-gate suite is not green. **This is a new-company path** — directly relevant to onboarding a package client (see `.planning/specs/PACKAGE-OVERVIEW.md` onboarding + file 16 blocker 1).
- `HmacMiddlewareTest` ×4 — webhook routes return 404 instead of 401, audit-log row never written. Unrelated to accounting; file dates to 2026-02-10.

## 6. Ops incident recorded

During round 2, an agent ran `php artisan migrate:fresh --env=testing`. **There is no `.env.testing` in this project**, so `--env=testing` silently fell back to `.env`, whose `DB_TEST_DATABASE=laravel_testing` — the primary LOCAL application database, which was consequently wiped (71 tables, 0 companies, 0 users; `city_tour_test` by comparison has 141 tables). **Server data was never at risk** — prod/dev were verified intact afterwards (73,222 journal entries, 11,868 tasks matching across both).

**Fixed in round 3:** `phpunit.xml` now forces `DB_TEST_DATABASE=city_tour_test` and `DB_DATABASE_MAP=city_tour_test_map` (both disposable, local, created for the purpose), proven three independent ways. **Standing rule: never pass `--env=testing` in this project, and always resolve the target database name before running migrations or tests.** `.env.example` shipped the same footgun and was corrected.

### W0 incident #2 (2026-08-26)

During the run preceding W0.1's isolation hardening, an agent ran `php artisan migrate --database=mysql_testing` directly from a bare shell, outside PHPUnit entirely. The `mysql_testing` connection resolves its database name from `env('DB_TEST_DATABASE')` (see `config/database.php`), and `.env` sets `DB_TEST_DATABASE=laravel_testing` — the primary LOCAL application database. `phpunit.xml`'s `DB_TEST_DATABASE` override only takes effect *inside* a `php artisan test` run (PHPUnit's `<php><env>` block is applied by PHPUnit itself at bootstrap); a bare `php artisan migrate` invocation never goes through PHPUnit, so that override never applied, and the command migrated straight into `laravel_testing`.

Effect: `laravel_testing` went from 71 to 74 tables (6 tables created at 13:36:44), with 7 migration rows recorded at batch 3 (migration ids 163–169), 0 rows written to any of them. Read-only PDO checks taken at the start and end of this W0.2 hardening pass both show `laravel_testing` unchanged at **74 tables, migrations table max id 169, 169 rows** — confirming no further drift occurred during this pass.

**Rule that closes this incident:** never run `php artisan migrate` locally in any form — bare, `--database=`, `--path=`, `:fresh`, `:refresh`, `:rollback` — outside of `php artisan test` managing its own `migrate:fresh` against an isolated database. The only sanctioned mechanism is: create a disposable database with `scripts/dev/create-test-db.php <city_tour_test_name>`, then export `DB_TEST_DATABASE`/`DB_DATABASE_MAP` in the shell before invoking `php artisan test`. `tests/TestCase.php` (and, since W0.2, `tests/DuskTestCase.php` via the shared `Tests\Concerns\GuardsTestDatabaseIsolation` trait) is the hard backstop that aborts any test run whose resolved database does not clearly start with `city_tour_test` — but that backstop only runs inside PHPUnit; it cannot and does not protect a bare `artisan migrate` invocation, which is exactly why that command is banned outright rather than "made safe."

**Disposable test databases present as of 2026-08-26** (safe to drop, but not dropped by this pass — leave them for whoever's workflow owns them): `city_tour_test_v_env`, `city_tour_test_v_env2`, `city_tour_test_v_env3`, `city_tour_test_v_env4`, `city_tour_test_envfix`, `city_tour_test_envfix3`, `city_tour_test_synth`, `city_tour_test_synth2`, `city_tour_test_b_ab`, `city_tour_test_b_coa`, `city_tour_test_b_ks`, `city_tour_test_v_actualba`, `city_tour_test_v_coafollo`, `city_tour_test_v_killswit` (plus each one's `_map` sibling). `city_tour_test` / `city_tour_test_map` (the phpunit.xml default) and `city_tour_test_b_env` / `city_tour_test_b_env_map` (this W0.2 pass) are still in active use.
