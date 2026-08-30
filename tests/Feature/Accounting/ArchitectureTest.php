<?php

namespace Tests\Feature\Accounting;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * CI architecture ratchet SKELETON (Accounting Gap/11-technical-implementation-plan.md
 * §C2): "A CI architecture test (tests/Architecture/PostingWritesTest.php)
 * that greps the app tree and fails the build if JournalEntry::create,
 * new JournalEntry, JournalEntry::insert, or
 * JournalEntry::...->update(['debit'|'credit'…]) appears outside
 * App\Services\Accounting\."
 *
 * This is EXPLICITLY a P2 deliverable / P2 phase-exit gate (file 11 L369,
 * under "Acceptance tests (P2, per wave)"), NOT a P1 acceptance test — the
 * build contract's own invariants list says so verbatim: "DO NOT add this
 * test in this build; the 131 legacy call sites still write journal_entries
 * directly and the test would fail CI immediately."
 *
 * The scan itself is implemented below so the gate is ready to flip on the
 * moment P2's strangler cutover finishes migrating every feeder in file 11's
 * wave table (W1..W6) onto PostingService — but the test method skips
 * itself before asserting, so it documents the gate without failing the
 * suite today. To activate: delete the markTestSkipped() call.
 */
class ArchitectureTest extends TestCase
{
    /**
     * Pins the P2 phase-exit gate described above. Currently a documented
     * no-op (skipped) by design — see class docblock.
     */
    public function test_no_journal_entry_writes_outside_engine(): void
    {
        $this->markTestSkipped(
            'P2 gate — enable when cutover begins. Accounting Gap/11-technical-implementation-plan.md '
                .'§C2 specifies this architecture test as a P2 deliverable, and file 11 L369 lists it under '
                .'"Acceptance tests (P2, per wave)", not P1. The 131 legacy call sites (21 files, census in '
                .'golden-rules-integration.md §7.1) still write journal_entries directly today; enabling this '
                .'assertion now would fail CI immediately for work outside this build\'s scope. Remove this '
                .'markTestSkipped() call only once P2\'s strangler cutover (waves W1..W6) has migrated every '
                .'listed feeder onto App\\Services\\Accounting\\PostingService.'
        );

        $violations = $this->findJournalEntryWritesOutsideEngine();

        $this->assertEmpty(
            $violations,
            "JournalEntry write(s) found outside App\\Services\\Accounting\\:\n".implode("\n", $violations)
        );
    }

    /**
     * Grep-style static scan over app/ for the four write shapes file 11's
     * C2 names, excluding the engine's own directory
     * (app/Services/Accounting), which is the one place allowed to write
     * journal_entries. This is deliberately a lightweight regex scan (matching
     * the "greps the app tree" phrasing in the source spec), not a full AST
     * analysis — good enough to gate CI, not intended as a general-purpose
     * static analyzer.
     *
     * @return string[] absolute file paths containing at least one violation
     */
    private function findJournalEntryWritesOutsideEngine(): array
    {
        $appDir = base_path('app');
        $allowedDir = str_replace('\\', '/', base_path('app/Services/Accounting'));

        $patterns = [
            // JournalEntry::create(...)
            '/JournalEntry::create\s*\(/',
            // new JournalEntry(...)
            '/new\s+JournalEntry\s*\(/',
            // JournalEntry::insert(...)
            '/JournalEntry::insert\s*\(/',
            // JournalEntry::<anything>(...)->update([...'debit'... or ...'credit'...])
            '/JournalEntry::\w+\([^;]*?\)\s*->\s*update\s*\(\s*\[[^\]]*([\'"]debit[\'"]|[\'"]credit[\'"])/s',
        ];

        $violations = [];

        if (! is_dir($appDir)) {
            return $violations;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            $normalizedPath = str_replace('\\', '/', $realPath);

            // The engine itself is the sole allowed writer.
            if (str_starts_with($normalizedPath, $allowedDir)) {
                continue;
            }

            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = $realPath;
                    break;
                }
            }
        }

        return $violations;
    }

    /**
     * W6.I "Importer contract" item 4 (w6-brief.md; Accounting Gap/22-plan-amendments.md §16.1
     * "Dead enums" row). `ticketed`/`refunded` get ZERO new writers anywhere touched by this
     * sub-wave -- importer-status-contract.md's own Table 1 already confirms "Nobody" writes
     * either value to `tasks.status` today (a distinct fact from `invoices.status`, which DOES use
     * `refunded` legitimately -- see this test's own exclusion below). This is a permanent CI
     * ratchet, NOT skipped like the JournalEntry gate above: unlike that P2 phase-exit gate (which
     * knows about 131 pre-existing legacy call sites it cannot yet fix), this assertion protects a
     * status quo that is already true everywhere today, so it can and should fail CI the moment a
     * future change reintroduces either write.
     *
     * The enum values themselves are NOT removed from the `tasks.status` column (additive-only
     * convention, same as every other wave) -- this test only forbids NEW code from ever writing
     * them, per the brief's own "retire from active use ... do not silently carry a dead enum
     * forward" wording.
     */
    public function test_no_new_writes_of_ticketed_or_refunded_task_status(): void
    {
        $violations = $this->findTaskStatusWritesToRetiredValues();

        $this->assertEmpty(
            $violations,
            "tasks.status write(s) to the retired 'ticketed'/'refunded' values found:\n".implode("\n", $violations)
        );
    }

    /**
     * Scans app/ for the shapes that actually WRITE `tasks.status` to `'ticketed'` or
     * `'refunded'` -- an array-literal assignment (`'status' => 'ticketed'`) or a property
     * assignment (`->status = 'ticketed'`/`$task->status = 'refunded'`). Deliberately does NOT
     * match a bare read (`where('status', 'ticketed')`, `in_array($status, ['ticketed', ...])`,
     * an enum-literal list in a comment) -- those are legitimate legacy-compat reads/back-compat
     * enum declarations, not new writers, and importer-status-contract.md's own finding is
     * specifically about writers ("Nobody ... zero logic reads OR writes it" for `ticketed`;
     * "zero WRITES of 'refunded' to tasks.status anywhere in TaskController" for `refunded` --
     * this test pins exactly that second, narrower claim, permanently).
     *
     * `database/migrations/` is out of scope by construction (this method only scans `app/`): the
     * enum's own column definition legitimately lists both values forever (additive-only
     * convention) without ever "writing" a task row.
     *
     * FIX ROUND (W6.I re-verify): the previous cut's two patterns scanned every PHP file
     * unconditionally, with nothing tying either shape to the `tasks` TABLE specifically --
     * `->status = 'refunded'` on ANY object (e.g. `$invoice->status = 'refunded'`, a real,
     * legitimate value on `invoices.status` per importer-status-contract.md's own Table 1) or
     * `'status' => 'refunded'` inside an unrelated `Invoice::create([...])`/array literal would
     * have matched too -- a latent CI-fragility false-positive risk the previous build report
     * itself flagged (zero actual false positives found by grep at the time, but nothing in the
     * pattern PREVENTED one). Narrowed here, still as a plain text/regex scan (no AST), to two
     * Task-specific shapes:
     *   - property assignment: the variable/property chain itself must look like a Task (matches
     *     `$task->status = ...`, `$originalTask->status = ...`, `$parentTask->status = ...` --
     *     the actual shapes this codebase uses -- never a bare `$anything->status`).
     *   - array-literal `'status' => 'ticketed'|'refunded'`: has no variable name of its own to
     *     anchor on, so a match is only counted when the PRECEDING 20 lines of the SAME file also
     *     reference `Task::` or a `$...task...`-named variable -- the actual multi-line shape
     *     every real call site in this codebase uses (`Task::create([...'status' => ...])`,
     *     `$task->update([...'status' => ...])`). A file/region that never mentions Task in that
     *     window cannot plausibly be writing `tasks.status`.
     *
     * @return string[] "path:line" for every violation found.
     */
    private function findTaskStatusWritesToRetiredValues(): array
    {
        $appDir = base_path('app');

        $violations = [];

        if (! is_dir($appDir)) {
            return $violations;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            $lines = file($realPath);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $lineContent) {
                $windowStart = max(0, $lineNumber - self::TASK_STATUS_CONTEXT_WINDOW_LINES);
                $window = implode('', array_slice($lines, $windowStart, $lineNumber - $windowStart + 1));

                if (self::isTaskStatusRetiredValueWrite($lineContent, $window)) {
                    $violations[] = $realPath.':'.($lineNumber + 1).': '.trim($lineContent);
                }
            }
        }

        return $violations;
    }

    /**
     * How many lines of file that PRECEDE an array-literal `'status' => 'ticketed'|'refunded'`
     * match are searched for a `Task::`/`$...task...` context reference (see
     * {@see self::isTaskStatusRetiredValueWrite()}'s own docblock). Sized to comfortably cover
     * this codebase's own multi-line `Task::create([` / `$task->update([` array-literal shapes
     * without also reaching back far enough to pick up an unrelated PRECEDING statement's own
     * Task reference in a long method.
     */
    private const TASK_STATUS_CONTEXT_WINDOW_LINES = 20;

    /**
     * Pure decision function -- exposed as `public static` (same convention as
     * {@see \Tests\Concerns\GuardsTestDatabaseIsolation::evaluateDatabaseIsolation()}) specifically
     * so a dedicated unit test can exercise the exact regex/context-window logic against synthetic
     * strings, without touching the real `app/` tree -- see
     * {@see \Tests\Unit\Support\TaskStatusRetiredValueWriteDetectionTest} for the false-positive
     * regression this pins (an `->status = 'refunded'` or `'status' => 'refunded'` write on a
     * DIFFERENT table, e.g. `invoices.status`, must never match).
     *
     * Two shapes count as a write of `tasks.status` to `'ticketed'`/`'refunded'`:
     *   - property assignment (`$lineContent` alone decides it): the receiver's own
     *     variable/property name must itself look like a Task (`$task->status = ...`,
     *     `$originalTask->status = ...`, `$parentTask->status = ...` -- the actual shapes this
     *     codebase uses) -- never a bare `$anything->status`, which would also match e.g.
     *     `$invoice->status = 'refunded'` (a real, legitimate value on `invoices.status` per
     *     importer-status-contract.md's own Table 1).
     *   - array-literal `'status' => 'ticketed'|'refunded'`: has no receiver of its own to anchor
     *     on, so `$precedingWindow` (the current line plus the preceding
     *     {@see self::TASK_STATUS_CONTEXT_WINDOW_LINES} lines of the SAME file) must ALSO
     *     reference `Task::` or a `$...task...`-named variable -- the actual multi-line shape
     *     every real call site in this codebase uses (`Task::create([...'status' => ...])`,
     *     `$task->update([...'status' => ...])`). A region that never mentions Task in that
     *     window cannot plausibly be writing `tasks.status`.
     */
    public static function isTaskStatusRetiredValueWrite(string $lineContent, string $precedingWindow): bool
    {
        // ->status = 'ticketed'/"refunded" -- ONLY when the receiver's own variable/property name
        // looks like a Task ($task, $originalTask, $parentTask, ...->task->status, etc.).
        $propertyPattern = '/\$[A-Za-z_]*[Tt]ask[A-Za-z0-9_]*\s*->\s*status\s*=(?!=)\s*[\'"](ticketed|refunded)[\'"]/';

        if (preg_match($propertyPattern, $lineContent) === 1) {
            return true;
        }

        // 'status' => 'ticketed'/"refunded" (array literal) -- has no receiver of its own; scoped
        // via the surrounding-lines Task-context check below instead.
        $arrayPattern = '/[\'"]status[\'"]\s*=>\s*[\'"](ticketed|refunded)[\'"]/';
        $taskContextPattern = '/\bTask::|\$[A-Za-z_]*[Tt]ask[A-Za-z0-9_]*\b/';

        if (preg_match($arrayPattern, $lineContent) === 1 && preg_match($taskContextPattern, $precedingWindow) === 1) {
            return true;
        }

        return false;
    }

    /**
     * P2.5.B (p2_5-brief.md §P2.5.B; BUG-C4, doc 08): "Add ArchitectureTest rule: no report query
     * references journal_entries.created_at for period filtering." A permanent CI ratchet, not
     * skipped -- ReportController::profitLoss() (the one confirmed BUG-C4 offender) and every
     * TrialBalanceService query were fixed to filter/group by posting_date as part of this same
     * wave (see those files' own P2.5.B notes), so this assertion protects a status quo that is
     * already true, exactly like {@see self::test_no_new_writes_of_ticketed_or_refunded_task_status()}
     * above.
     *
     * Deliberately narrow, matching this class's own established "lightweight regex, not a general
     * static analyzer" convention -- and specifically reusing the SAME windowed-context shape
     * {@see self::findTaskStatusWritesToRetiredValues()} above already established for a bare,
     * receiver-less array/string match, rather than the whole-file pairing this rule's first draft
     * used. Whole-file pairing was tried and rejected during this wave's own build: grepping this
     * app tree for the filter shape alone turned up 11 files with an unrelated
     * `where('created_at', ...)` on some OTHER model (Task, Payment, Credit, DotwAIBooking, ...)
     * that also happened to mention `JournalEntry`/`journal_entries` SOMEWHERE ELSE, hundreds of
     * lines away in the same file (e.g. `RunAutoBilling.php`'s `Task::query()->whereBetween(
     * 'created_at', ...)` sits nowhere near that file's own unrelated `JournalEntry::where(...)`
     * calls) -- false positives a permanent CI ratchet cannot carry. The windowed check below
     * requires the JournalEntry/journal_entries reference to appear within
     * {@see self::JOURNAL_ENTRY_CREATED_AT_CONTEXT_WINDOW_LINES} lines BEFORE the filter itself,
     * which is exactly the shape a real offending query chain has (e.g.
     * `JournalEntry::whereIn(...)->where('created_at', '<=', $endDate)`, the one genuine violation
     * this check found in `SupplierController.php` prior to this wave's own fix of it) and which
     * none of the 11 false positives satisfy.
     *
     * Flags a `where`/`whereBetween` call whose date argument is a bare `'created_at'` or an
     * explicitly `journal_entries`/`je`-qualified `'created_at'` -- `'transactions.created_at'`/
     * `'t.created_at'` and any OTHER table's created_at are untouched, since BUG-C4 is specifically
     * about journal_entries being bucketed into the wrong accounting period (e.g.
     * ReportController::settlementsReport()'s gateway-settlement listing filters
     * `transactions.created_at` and is deliberately NOT this rule's concern -- see that method's
     * own P2.5.B note). A bare `orderBy('created_at')` is likewise never flagged -- display/sort
     * order does not miscount an entry into the wrong period the way a WHERE-style filter does;
     * only `where`/`whereBetween` are matched.
     */
    public function test_no_report_query_periodizes_journal_entries_on_created_at(): void
    {
        $violations = $this->findJournalEntryCreatedAtPeriodFilters();

        $this->assertEmpty(
            $violations,
            "journal_entries query filtering by created_at (must use posting_date -- BUG-C4) found:\n"
                .implode("\n", $violations)
        );
    }

    /**
     * How many lines PRECEDING a `where(Between)?('created_at', ...)` match are searched for a
     * `JournalEntry`/`journal_entries` context reference (see this rule's own docblock above for
     * why this is windowed rather than whole-file). Sized to comfortably cover this codebase's own
     * real offending shape (`JournalEntry::whereIn(...)->where('created_at', ...)`, one line apart)
     * plus a few lines of intermediate `->with()`/`->whereIn()` calls, without reaching far enough
     * to pick up an unrelated JournalEntry reference elsewhere in a long method.
     */
    private const JOURNAL_ENTRY_CREATED_AT_CONTEXT_WINDOW_LINES = 8;

    /**
     * @return string[] "path:line" for every violation found.
     */
    private function findJournalEntryCreatedAtPeriodFilters(): array
    {
        $appDir = base_path('app');
        $violations = [];

        if (! is_dir($appDir)) {
            return $violations;
        }

        // Requires the literal quote to be followed IMMEDIATELY by either nothing or
        // 'journal_entries.'/'je.', then 'created_at' -- so 'transactions.created_at' (a
        // different table entirely) can never match: the text between the quote and
        // 'created_at' would be 'transactions.', which satisfies neither branch.
        $filterPattern = '/->\s*where(?:Between)?\s*\(\s*[\'"](?:journal_entries\.|je\.)?created_at[\'"]/';
        $journalEntryContextPattern = '/\bJournalEntry::|use\s+App\\\\Models\\\\JournalEntry\b|\bjournal_entries\b/';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            $lines = file($realPath);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $lineContent) {
                if (preg_match($filterPattern, $lineContent) !== 1) {
                    continue;
                }

                $windowStart = max(0, $lineNumber - self::JOURNAL_ENTRY_CREATED_AT_CONTEXT_WINDOW_LINES);
                $window = implode('', array_slice($lines, $windowStart, $lineNumber - $windowStart + 1));

                if (preg_match($journalEntryContextPattern, $window) === 1) {
                    $violations[] = $realPath.':'.($lineNumber + 1).': '.trim($lineContent);
                }
            }
        }

        return $violations;
    }
}
