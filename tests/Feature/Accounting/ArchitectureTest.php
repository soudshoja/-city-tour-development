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
}
