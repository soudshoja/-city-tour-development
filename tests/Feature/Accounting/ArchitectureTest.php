<?php

namespace Tests\Feature\Accounting;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * CI architecture ratchet (Accounting Gap/11-technical-implementation-plan.md §C2): "A CI
 * architecture test ... that greps the app tree and fails the build if JournalEntry::create,
 * new JournalEntry, JournalEntry::insert, JournalEntry::...->update(['debit'|'credit'…]), or
 * Transaction::create appears outside App\Services\Accounting\."
 *
 * P2 EXIT: this is now a LIVE allow-list ratchet, not a skip. A whole-file text-presence regex
 * (see class docblock precedent set by the other two rules in this file) cannot tell a raw write
 * gated behind a `$legacy` closure / `isEnabledFor()` refusal from a genuinely-unguarded one —
 * the W7 final gate (`.planning/accounting-waves/w7/w7-final-gate.md` §2) proved exactly that: a
 * naive un-skip flags every gated file alongside the two real, unguarded blockers it found
 * (`ClientController::addCredit()`, `PaymentReleaseToCompanyBankAccProcess.php`), with no way to
 * tell them apart from the regex hit alone. Those two blockers were subsequently cut over
 * (18d2a3db "W7.X+Y — final raw writers seam-gated ..."). Rather than attempt full reachability
 * analysis (AST-scoping to code never reachable from a call passed to `PostingSeam::post()`),
 * this ratchet uses the simpler, equally-enforceable ALLOW-LIST shape: every file the scan hits
 * must appear in {@see self::ALLOW_LISTED_RAW_WRITER_FILES}, built from the actual current hits,
 * each individually re-verified (this build) to have its raw write(s) confined inside either a
 * `$legacy` closure passed to `PostingSeam::post()`/`$this->seam->post()` (OFF-path byte parity)
 * or an `isEnabledFor()`/engine-refusal branch that skips the raw write once a company is cut
 * over — see {@see self::ALLOW_LISTED_RAW_WRITER_FILES}'s own per-entry notes.
 *
 * The assertion below is two-sided:
 *   - a hit in a file NOT on the allow-list fails the build (a new raw writer landed outside the
 *     engine, or an existing one lost its gate) -- this is what makes the ratchet enforceable,
 *     not just documentary;
 *   - an allow-listed file that no longer produces a hit ALSO fails the build (a stale entry) --
 *     this forces the list to shrink as `$legacy` closures are retired, rather than silently
 *     accumulating dead allowances forever.
 */
class ArchitectureTest extends TestCase
{
    /**
     * Every file (relative to `app/`, forward slashes) the raw-writer scan is currently expected
     * to hit, individually re-verified as gated rather than assumed from a prior census. Grouped
     * by why each is allowed; nothing here is "trust the old report" -- every line was re-read at
     * P2 exit.
     *
     * KEEP THIS LIST MINIMAL. Adding a file here is loosening the ratchet -- do it only when the
     * new/changed raw write is genuinely confined behind `PostingSeam`/`isEnabledFor()`, not to
     * silence a real regression.
     */
    private const ALLOW_LISTED_RAW_WRITER_FILES = [
        // One-off data-repair / migration console commands. Confirmed NOT registered in
        // app/Console/Kernel.php's schedule or routes/console.php -- manual/operator-run only,
        // per the W7 final-gate census §1c (re-confirmed at P2 exit: still absent from both
        // schedule lists).
        'Console/Commands/CreateClientCredit.php',
        'Console/Commands/FixCreditInvoiceCOA.php',
        'Console/Commands/FixInvoiceCoa.php',
        'Console/Commands/FixOldProfit.php',
        'Console/Commands/FixPaymentLinkCOA.php',
        'Console/Commands/FixProfitAndCommission.php',
        'Console/Commands/UpdateOldTaskToTransaction.php',

        // Dev prod-drift maintenance command; legacy raw writes; refuses when engine ON (guarded
        // via RefusesWhenPostingEngineEnabled -- see accounting:repair's per-transaction company
        // check); route through PostingSeam in a later wave. Predates this rebuild's base
        // (9af11f181, prod-drift sync 254bb45a8), so it was never in the ratchet's original
        // allow-list. Confirmed absent from routes/console.php and Kernel.php's schedule.
        'Console/Commands/AccountingRepair.php',
        // Dev prod-drift maintenance command; legacy raw writes; refuses when engine ON (guarded
        // via RefusesWhenPostingEngineEnabled -- see the per-task company check in the main
        // foreach loop); route through PostingSeam in a later wave. Same prod-drift provenance
        // as AccountingRepair above.
        'Console/Commands/AssignTaskPaymentMethod.php',
        // Dev prod-drift maintenance command; legacy raw writes; refuses when engine ON (guarded
        // via RefusesWhenPostingEngineEnabled -- see processInvoice()'s per-invoice company
        // check); route through PostingSeam in a later wave. Same prod-drift provenance as
        // AccountingRepair above.
        'Console/Commands/FixProfitLossEntries.php',

        // Live/scheduled commands whose raw writes are gated.
        // CheckMyFatoorahPayments: its own topup branch now delegates to
        // ClientController::addCredit() (which is itself $legacy-gated, see below); its
        // invoice-payment posting was already PostingSeam-routed pre-W7.X.
        'Console/Commands/CheckMyFatoorahPayments.php',
        // Scheduled daily (Kernel.php); raw writes moved inside a $legacy closure passed to
        // $this->seam->post(...) in W7.X (18d2a3db) -- confirmed present at P2 exit.
        'Console/Commands/PaymentReleaseToCompanyBankAccProcess.php',
        // Guarded by the RefusesWhenPostingEngineEnabled trait: isPostingEngineEnabledForCompany()
        // refuses the whole rule (no raw write executes) once a company is cut over.
        'Console/Commands/RunAutoBilling.php',

        // The scan's regex matches whole-file TEXT, including comments/docblocks. This file's
        // only hit is a `JournalEntry::create([...])` MENTION inside a docblock describing
        // PostingService::post()'s own gate -- there is no executable JournalEntry/Transaction
        // write anywhere in this file (it declares one exception class).
        'Exceptions/Accounting/PostingEngineDisabledException.php',

        // Controllers/services whose raw ledger writes are confined inside a `$legacy` closure
        // passed to `PostingSeam::post()`/`$this->seam->post()` -- the OFF-path byte-parity
        // pattern this whole cutover is built on. Each was independently re-verified (grep for
        // $legacy/isEnabledFor/seam->post in the file, then read the enclosing method) at P2
        // exit, not carried over from an older census untouched.
        'Http/Controllers/AccountingController.php',
        'Http/Controllers/AgentController.php',
        'Http/Controllers/BankPaymentController.php',
        'Http/Controllers/ChatController.php',
        // addCredit() -- the W7-final-gate blocker -- cut over onto $seam->post() in W7.X
        // (18d2a3db); re-verified gated at P2 exit.
        'Http/Controllers/ClientController.php',
        'Http/Controllers/CreditController.php',
        // Plus two documented, permanently-inert edge branches: createSupplierLossEntries() and
        // createFeeLossEntries() each have an `else` arm (no matching loss-recovery Account
        // found) that is unreachable in practice once accounts are seeded, and is itself still
        // $legacy-gated like the rest of the method -- not a separate unguarded writer.
        'Http/Controllers/InvoiceController.php',
        'Http/Controllers/MobileController.php',
        'Http/Controllers/PaymentController.php',
        'Http/Controllers/ReceiptVoucherController.php',
        'Http/Controllers/RefundController.php',
        // Plus the dormant processRefundTask() leg: reachable only when a Task lands at literal
        // status='refund', which today only Magic Holiday's (inactive per this project's own
        // code/CLAUDE.md) reservation-import heuristic ever assigns -- a real but currently-inert
        // gap tracked as a backlog item (see P2-EXIT-REPORT.md), not fixed by this ratchet.
        'Http/Controllers/TaskController.php',
        'Modules/DotwAI/Services/AccountingService.php',
        'Services/AgentSettlementService.php',
        'Services/PaymentApplicationService.php',
    ];

    /**
     * PHASE GATE (accounting-builds) T12 ratchet extension — GATE-REPORT §3's first recorded
     * pre-existing gap: "the scan has no coverage at all for raw `accounts`-table writes ... both
     * predate the phase." Deliberately scoped to the UPDATE path only, not accounts CREATE:
     * account creation already has its own dedicated (if currently flag-disabled) guard --
     * {@see \App\Observers\AccountObserver}'s `creating` backstop, which throws when
     * config('accounting.account_observer.enabled') is on and the write did not go through
     * {@see \App\Services\Accounting\AccountService::create()}. There is no equivalent mechanism
     * anywhere for a raw UPDATE of an existing account's identity fields (code, name, ...) -- a
     * query-builder bulk update (`DB::table('accounts')->update(...)` or
     * `Account::where(...)->update(...)`) never fires Eloquent model events, so the observer above
     * cannot see it even if it were enabled. That is the actual gap: zero coverage of any kind for
     * this write shape, at any time.
     *
     * The census below is the complete list at P2/gate time -- confirmed by a whole-repo scan
     * (`grep -rn "DB::table('accounts')"` plus a multi-line query-builder `->update(` scan) that
     * every raw `DB::table('accounts')` call site other than the two allow-listed below is a READ
     * (`->first()`, `->get()`, `->pluck()`, `->max()`, `->find()`) or an INSERT
     * (`->insertGetId()`, e.g. AgentController's auto-numbered agent-profit/AR leaves -- a
     * long-standing, separately-documented legacy pattern per AccountObserver's own docblock,
     * "~10 still-unrefactored legacy Account::create() call sites ... keeps working exactly as
     * before" -- not an update, and not this rule's concern).
     */
    private const ALLOW_LISTED_RAW_ACCOUNTS_WRITER_FILES = [
        // EnsureSystemLeaves::renumberGatewayFeeRecoveryLeaf() (private helper, ~line 1256):
        // `DB::table('accounts')->where('id', $child->id)->update(['code' => '4131']);`.
        // In-file comment immediately above the call: "Plain UPDATE, not
        // AccountService::create()/createSystemLeaf() -- this is a renumber of an EXISTING leaf,
        // not the creation of a new one; AccountService's contract has no 'renumber' operation."
        // Guarded by its own collision check (refuses if code 4131 already used by another
        // account for the company) immediately before the raw update.
        'Console/Commands/EnsureSystemLeaves.php',
        // SupplierController::updateSupplier() (~line 394):
        // `Account::where('name', 'LIKE', "%{$oldName}%")->update(['name' => $newName]);` --
        // bulk-renames every Account whose name contains the old supplier name when a supplier is
        // renamed. Legacy denormalisation-sync pre-dating AccountService; GATE-REPORT §3 named
        // this as a pre-existing gap, not a defect this phase fixes.
        'Http/Controllers/SupplierController.php',
    ];

    /**
     * Two-sided, same shape as {@see self::test_no_journal_entry_writes_outside_engine()}: a hit
     * in a file not on the allow-list fails the build (new/regressed raw accounts write), and an
     * allow-listed file with no hit also fails (stale entry).
     */
    public function test_no_raw_accounts_table_updates_outside_engine(): void
    {
        $result = $this->scanForRawAccountsWriters();

        $message = '';

        if (! empty($result['unlisted'])) {
            $message .= 'Raw accounts-table update(s) found outside App\\Services\\Accounting\\ in '
                .'file(s) NOT on the allow-list (new/regressed raw accounts writer -- route this '
                .'write through AccountService, or if genuinely gated/pre-existing, add the file to '
                ."ArchitectureTest::ALLOW_LISTED_RAW_ACCOUNTS_WRITER_FILES with a note explaining "
                ."why):\n".implode("\n", $result['unlisted'])."\n";
        }

        if (! empty($result['stale'])) {
            $message .= 'Allow-listed accounts-writer file(s) with NO raw-update hit found (stale '
                .'allow-list entry -- remove from '
                ."ArchitectureTest::ALLOW_LISTED_RAW_ACCOUNTS_WRITER_FILES):\n"
                .implode("\n", $result['stale']);
        }

        $this->assertTrue(empty($result['unlisted']) && empty($result['stale']), $message);
    }

    /**
     * PHASE GATE (accounting-builds) T12 — mutation proof for the accounts-writer ratchet above,
     * same construction as {@see self::test_the_raw_writer_ratchet_actually_bites_a_synthetic_violation()}:
     * builds a throwaway tree carrying both raw-write shapes (DB::table('accounts')->update(...)
     * and Account::where(...)->update(...)) plus the sole-writer exemption and a clean file, and
     * asserts the scanner reports exactly what it should.
     */
    public function test_the_accounts_writer_ratchet_actually_bites_a_synthetic_violation(): void
    {
        $root = sys_get_temp_dir().'/arch-accounts-ratchet-mutation-'.uniqid();

        $violations = [
            'Console/Commands/ProbeDbTableAccountsUpdate.php' => "DB::table('accounts')->where('id', 1)->update(['code' => '9999']);",
            'Http/Controllers/ProbeAccountWhereUpdate.php' => "Account::where('name', 'LIKE', '%x%')->update(['name' => 'y']);",
        ];

        try {
            foreach ($violations as $relative => $body) {
                $path = $root.'/'.$relative;
                @mkdir(dirname($path), 0777, true);
                file_put_contents($path, "<?php\n\n".$body."\n");
            }

            // The engine's own directory is the sole allowed writer: an identical violation here
            // must NOT be reported.
            $enginePath = $root.'/Services/Accounting/ProbeEngineAccountsWriter.php';
            @mkdir(dirname($enginePath), 0777, true);
            file_put_contents($enginePath, "<?php\n\nDB::table('accounts')->where('id', 1)->update(['code' => '9999']);\n");

            // A file with no raw write at all must not be reported either.
            $cleanPath = $root.'/Http/Controllers/ProbeAccountsClean.php';
            file_put_contents($cleanPath, "<?php\n\n\$x = 1;\n");

            $result = $this->scanForRawAccountsWriters($root);

            $reported = array_map(
                fn (string $p): string => ltrim(str_replace('\\', '/', substr(str_replace('\\', '/', $p), strlen(str_replace('\\', '/', $root)))), '/'),
                $result['unlisted']
            );
            sort($reported);

            foreach (array_keys($violations) as $relative) {
                $this->assertContains(
                    $relative,
                    $reported,
                    "The accounts-writer ratchet did NOT flag {$relative}. The regex for this write "
                    .'shape has stopped matching, so this class of unguarded accounts write would '
                    .'now reach production with a green CI. Fix the pattern in scanForRawAccountsWriters().'
                );
            }

            $this->assertNotContains(
                'Services/Accounting/ProbeEngineAccountsWriter.php',
                $reported,
                'The accounts-writer ratchet flagged a write inside the engine\'s own directory, '
                .'which is the sole allowed writer.'
            );

            $this->assertNotContains(
                'Http/Controllers/ProbeAccountsClean.php',
                $reported,
                'The accounts-writer ratchet flagged a file containing no raw write at all.'
            );
        } finally {
            $this->removeDirectory($root);
        }
    }

    /**
     * Same shape as {@see self::scanForRawLedgerWriters()}, scoped to the two raw-update
     * shapes named in this rule's own docblock above: a whole-table `DB::table('accounts')`
     * query-builder update, or an `Account::` query-builder (where/whereIn/whereNotIn/orWhere)
     * bulk update. Both bypass Eloquent model events, so {@see \App\Observers\AccountObserver}
     * (which only fires on `creating`) cannot see either shape regardless of its own flag.
     *
     * @return array{unlisted: string[], stale: string[]}
     */
    private function scanForRawAccountsWriters(?string $rootDir = null): array
    {
        $appDir = $rootDir ?? base_path('app');
        $allowedDir = rtrim(str_replace('\\', '/', $appDir), '/').'/Services/Accounting';
        $appDirNormalized = rtrim(str_replace('\\', '/', $appDir), '/');

        $patterns = [
            // DB::table('accounts')->...->update(...) -- whole-table query-builder update.
            '/DB::table\(\s*[\'"]accounts[\'"]\s*\)[^;]*?->\s*update\s*\(/s',
            // Account::where(...)/whereIn(...)/whereNotIn(...)/orWhere(...)->update(...) --
            // Eloquent query-builder bulk update (skips per-model events, unlike ->save()).
            '/Account::(?:where|whereIn|whereNotIn|orWhere)\([^;]*?\)\s*->\s*update\s*\(/s',
        ];

        $unlisted = [];
        $hitAllowListed = [];

        if (! is_dir($appDir)) {
            return ['unlisted' => $unlisted, 'stale' => self::ALLOW_LISTED_RAW_ACCOUNTS_WRITER_FILES];
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

            if (str_starts_with($normalizedPath, $allowedDir)) {
                continue;
            }

            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }

            $hit = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $hit = true;
                    break;
                }
            }

            if (! $hit) {
                continue;
            }

            $relativePath = ltrim(substr($normalizedPath, strlen($appDirNormalized)), '/');

            if (in_array($relativePath, self::ALLOW_LISTED_RAW_ACCOUNTS_WRITER_FILES, true)) {
                $hitAllowListed[$relativePath] = true;
            } else {
                $unlisted[] = $realPath;
            }
        }

        $stale = array_values(array_diff(self::ALLOW_LISTED_RAW_ACCOUNTS_WRITER_FILES, array_keys($hitAllowListed)));

        return ['unlisted' => $unlisted, 'stale' => $stale];
    }

    /**
     * The live P2-exit ratchet. Fails when a raw-writer hit appears in a file not on
     * {@see self::ALLOW_LISTED_RAW_WRITER_FILES} (new/regressed unguarded writer), and also fails
     * when an allow-listed file no longer produces a hit (stale entry -- the list must shrink as
     * legacy code is retired, never accumulate silently).
     */
    public function test_no_journal_entry_writes_outside_engine(): void
    {
        $result = $this->scanForRawLedgerWriters();

        $message = '';

        if (! empty($result['unlisted'])) {
            $message .= "Raw ledger write(s) found outside App\\Services\\Accounting\\ in file(s) "
                ."NOT on the ArchitectureTest allow-list (new/regressed raw writer -- either route "
                ."this write through PostingSeam::post(), or if it is genuinely gated, add the file "
                ."to ArchitectureTest::ALLOW_LISTED_RAW_WRITER_FILES with a note explaining the "
                ."gate):\n".implode("\n", $result['unlisted'])."\n";
        }

        if (! empty($result['stale'])) {
            $message .= "Allow-listed file(s) with NO raw-writer hit found (stale allow-list "
                ."entry -- remove from ArchitectureTest::ALLOW_LISTED_RAW_WRITER_FILES; the legacy "
                ."write this entry was covering appears to be gone):\n".implode("\n", $result['stale']);
        }

        $this->assertTrue(empty($result['unlisted']) && empty($result['stale']), $message);
    }

    /**
     * PHASE GATE (accounting-builds) — the mutation proof PLAN.md's T12 named as MP-12-1 ("insert
     * a JournalEntry::create into FixedAssetController -> ArchitectureTest fails; proves the
     * ratchet still bites for new files") and which was never built, because T12 was never
     * started.
     *
     * Why it matters: the app tree currently has ZERO real violations of this rule, so
     * test_no_journal_entry_writes_outside_engine() above passes whether the scanner works or
     * not. A regex that silently stopped matching — an escaping slip, a refactor that renamed
     * JournalEntry, a `continue` in the wrong branch — would look exactly like a clean codebase.
     * Nothing in CI could tell the difference. This test is what tells the difference: it builds a
     * throwaway tree containing the violation MP-12-1 describes and asserts the scanner reports
     * it, so a broken guard fails here loudly instead of passing quietly everywhere.
     *
     * All five raw-writer shapes are probed, plus both sides of the decision the scanner makes
     * about a hit: inside app/Services/Accounting it is the sole allowed writer and must be
     * ignored; outside it and off the allow-list it must be reported.
     */
    public function test_the_raw_writer_ratchet_actually_bites_a_synthetic_violation(): void
    {
        $root = sys_get_temp_dir().'/arch-ratchet-mutation-'.uniqid();

        // Every shape the live scanner claims to catch, one file each. If any regex has rotted,
        // its file goes missing from the reported set and this test names exactly which one.
        $violations = [
            'Http/Controllers/Accounting/FixedAssetController.php' => 'JournalEntry::create([1]);',
            'Http/Controllers/ProbeNewJournalEntry.php' => '$e = new JournalEntry([1]);',
            'Http/Controllers/ProbeInsert.php' => 'JournalEntry::insert([1]);',
            'Http/Controllers/ProbeDebitUpdate.php' => "JournalEntry::where('id', 1)->update(['debit' => 1]);",
            'Console/Commands/ProbeTransactionCreate.php' => 'Transaction::create([1]);',
        ];

        try {
            foreach ($violations as $relative => $body) {
                $path = $root.'/'.$relative;
                @mkdir(dirname($path), 0777, true);
                file_put_contents($path, "<?php\n\n".$body."\n");
            }

            // The engine's own directory is the sole allowed writer: an identical violation here
            // must NOT be reported. Without this leg, a scanner that flagged everything
            // unconditionally would still satisfy the assertions above.
            $enginePath = $root.'/Services/Accounting/ProbeEngineWriter.php';
            @mkdir(dirname($enginePath), 0777, true);
            file_put_contents($enginePath, "<?php\n\nJournalEntry::create([1]);\n");

            // A file with no raw write at all must not be reported either.
            $cleanPath = $root.'/Http/Controllers/ProbeClean.php';
            file_put_contents($cleanPath, "<?php\n\n\$x = 1;\n");

            $result = $this->scanForRawLedgerWriters($root);

            $reported = array_map(
                fn (string $p): string => ltrim(str_replace('\\', '/', substr(str_replace('\\', '/', $p), strlen(str_replace('\\', '/', $root)))), '/'),
                $result['unlisted']
            );
            sort($reported);

            foreach (array_keys($violations) as $relative) {
                $this->assertContains(
                    $relative,
                    $reported,
                    "The raw-writer ratchet did NOT flag {$relative}. The regex for this write shape "
                    .'has stopped matching, so this class of unguarded ledger write would now reach '
                    .'production with a green CI. Fix the pattern in scanForRawLedgerWriters().'
                );
            }

            $this->assertNotContains(
                'Services/Accounting/ProbeEngineWriter.php',
                $reported,
                'The ratchet flagged a write inside the engine\'s own directory, which is the sole '
                .'allowed writer — the exemption branch is broken and the live test would now fail '
                .'against legitimate engine code.'
            );

            $this->assertNotContains(
                'Http/Controllers/ProbeClean.php',
                $reported,
                'The ratchet flagged a file containing no raw ledger write at all.'
            );
        } finally {
            // Always clean up: a leaked probe file under a real path would poison every later run.
            $this->removeDirectory($root);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
        }

        @rmdir($dir);
    }

    /**
     * Grep-style static scan over app/ for the raw ledger-write shapes file 11's C2 names,
     * excluding the engine's own directory (app/Services/Accounting), which is the one place
     * allowed to write journal_entries/transactions directly. Deliberately a lightweight regex
     * scan (matching the "greps the app tree" phrasing in the source spec), not a full AST
     * analysis -- good enough to gate CI, not intended as a general-purpose static analyzer.
     *
     * @return array{unlisted: string[], stale: string[]} 'unlisted' = absolute paths with a hit
     *   not on the allow-list; 'stale' = allow-list entries (relative paths) with no hit.
     */
    private function scanForRawLedgerWriters(?string $rootDir = null): array
    {
        // PHASE GATE (accounting-builds T12/MP-12-1): $rootDir exists ONLY so the mutation proof
        // below can point this same scanner at a synthetic tree and prove it actually bites. It
        // defaults to base_path('app'), so the live ratchet above is byte-identical in behaviour
        // to what it was before this parameter existed. Both the "sole writer" exemption and the
        // relative-path the allow-list is matched on are derived FROM the root, so a synthetic
        // tree reproduces the real decision logic rather than a lookalike of it.
        $appDir = $rootDir ?? base_path('app');
        $allowedDir = rtrim(str_replace('\\', '/', $appDir), '/').'/Services/Accounting';
        $appDirNormalized = rtrim(str_replace('\\', '/', $appDir), '/');

        $patterns = [
            // JournalEntry::create(...)
            '/JournalEntry::create\s*\(/',
            // new JournalEntry(...)
            '/new\s+JournalEntry\s*\(/',
            // JournalEntry::insert(...)
            '/JournalEntry::insert\s*\(/',
            // JournalEntry::<anything>(...)->update([...'debit'... or ...'credit'...])
            '/JournalEntry::\w+\([^;]*?\)\s*->\s*update\s*\(\s*\[[^\]]*([\'"]debit[\'"]|[\'"]credit[\'"])/s',
            // Transaction::create(...) -- the ledger header row (app/Models/Transaction.php),
            // not a payment-gateway record; every current use of this call is a ledger document.
            '/Transaction::create\s*\(/',
        ];

        $unlisted = [];
        $hitAllowListed = [];

        if (! is_dir($appDir)) {
            return ['unlisted' => $unlisted, 'stale' => self::ALLOW_LISTED_RAW_WRITER_FILES];
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

            $hit = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $hit = true;
                    break;
                }
            }

            if (! $hit) {
                continue;
            }

            $relativePath = ltrim(substr($normalizedPath, strlen($appDirNormalized)), '/');

            if (in_array($relativePath, self::ALLOW_LISTED_RAW_WRITER_FILES, true)) {
                $hitAllowListed[$relativePath] = true;
            } else {
                $unlisted[] = $realPath;
            }
        }

        $stale = array_values(array_diff(self::ALLOW_LISTED_RAW_WRITER_FILES, array_keys($hitAllowListed)));

        return ['unlisted' => $unlisted, 'stale' => $stale];
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
     * accounting-builds T0b (M1, L12, MP-0b-2): "written ONLY by PostingService, never a post-hoc
     * `->update(['settlement_channel' => ...])`" — the `TaskStatusService` `reason_tag` post-hoc
     * pattern (two existing sites, see §10 of the accounting-builds phase plan) is EXPLICITLY not
     * to be copied for this new column. A permanent CI ratchet, not skipped — this build introduces
     * ZERO post-hoc writers of this column anywhere (the only write site is PostingService::post()'s
     * own step-8 INSERT), so this assertion protects a status quo that is already true everywhere
     * today, same convention as {@see self::test_no_new_writes_of_ticketed_or_refunded_task_status()}
     * and {@see self::test_no_report_query_periodizes_journal_entries_on_created_at()} above.
     *
     * Deliberately excludes app/Services/Accounting/ (the engine's own directory, same convention
     * as the raw-writer scan above) — PostingService::post()'s own INSERT (not an ->update() call
     * at all) never matches this pattern regardless.
     */
    public function test_no_post_hoc_settlement_channel_updates(): void
    {
        $violations = $this->findPostHocSettlementChannelUpdates();

        $this->assertEmpty(
            $violations,
            "Post-hoc JournalEntry::...->update(['settlement_channel' => ...]) found outside "
                ."App\\Services\\Accounting\\ (L12: written ONLY by PostingService's own INSERT, "
                ."never a post-hoc update — do not copy the TaskStatusService reason_tag "
                ."anti-pattern):\n".implode("\n", $violations)
        );
    }

    /**
     * PHASE GATE (accounting-builds) T12 — mutation proof for the settlement_channel post-hoc
     * rule above, closing GATE-REPORT §3's second recorded gap ("the settlement_channel and
     * reconciled post-hoc-update rules in ArchitectureTest still have no mutation proof ... same
     * silent-rot exposure" as the raw-writer rule GATE-3 already fixed). Same synthetic-tree
     * construction as {@see self::test_the_raw_writer_ratchet_actually_bites_a_synthetic_violation()}:
     * plants the exact forbidden shape, an engine-directory write (which this rule does NOT
     * exempt — settlement_channel has exactly one legitimate writer, PostingService's own INSERT,
     * which this ->update() pattern never matches regardless of directory), and a clean file.
     */
    public function test_the_settlement_channel_ratchet_actually_bites_a_synthetic_violation(): void
    {
        $root = sys_get_temp_dir().'/arch-settlement-channel-ratchet-mutation-'.uniqid();

        $violationPath = $root.'/Http/Controllers/ProbeSettlementChannelUpdate.php';

        try {
            @mkdir(dirname($violationPath), 0777, true);
            file_put_contents(
                $violationPath,
                "<?php\n\n\$je->where('id', 1)->update(['settlement_channel' => 'wire']);\n"
            );

            $cleanPath = $root.'/Http/Controllers/ProbeSettlementChannelClean.php';
            file_put_contents($cleanPath, "<?php\n\n\$x = 1;\n");

            // Normalise both sides to forward slashes before comparing -- getRealPath() returns
            // OS-native separators (backslash on Windows), while $violationPath/$cleanPath above
            // are built with a literal forward slash, same normalisation convention as
            // scanForRawLedgerWriters()'s own mutation proof above.
            $violations = array_map(
                fn (string $p): string => str_replace('\\', '/', $p),
                $this->findPostHocSettlementChannelUpdates($root)
            );

            $this->assertNotEmpty(
                $violations,
                'The settlement_channel post-hoc-update ratchet did NOT flag a synthetic '
                ."->update(['settlement_channel' => ...]) write. The regex has stopped matching, "
                .'so this class of write would now reach production with a green CI. Fix the '
                .'pattern in findPostHocSettlementChannelUpdates().'
            );

            $this->assertContains(str_replace('\\', '/', $violationPath), $violations);
            $this->assertNotContains(
                str_replace('\\', '/', $cleanPath),
                $violations,
                'The settlement_channel ratchet flagged a file containing no post-hoc update at all.'
            );
        } finally {
            $this->removeDirectory($root);
        }
    }

    /**
     * @return string[] absolute paths with a hit.
     */
    private function findPostHocSettlementChannelUpdates(?string $rootDir = null): array
    {
        // PHASE GATE (accounting-builds T12): $rootDir exists ONLY so the mutation proof above
        // can point this scanner at a synthetic tree, same convention as
        // {@see self::scanForRawLedgerWriters()}'s own $rootDir parameter. Defaults to
        // base_path('app'), so the live rule is byte-identical to before this parameter existed.
        $appDir = $rootDir ?? base_path('app');
        $allowedDir = rtrim(str_replace('\\', '/', $appDir), '/').'/Services/Accounting';

        $violations = [];

        if (! is_dir($appDir)) {
            return $violations;
        }

        // Same shape as the raw-writer scan's debit/credit ->update() pattern above, scoped to
        // 'settlement_channel' instead — any receiver (JournalEntry::where(...), a resolved
        // model variable, etc.) chaining ->update([...'settlement_channel'...]).
        $pattern = '/->\s*update\s*\(\s*\[[^\]]*[\'"]settlement_channel[\'"]/s';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            $normalizedPath = str_replace('\\', '/', $realPath);

            if (str_starts_with($normalizedPath, $allowedDir)) {
                continue;
            }

            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }

            if (preg_match($pattern, $contents) === 1) {
                $violations[] = $realPath;
            }
        }

        return $violations;
    }

    /**
     * accounting-builds T8 (Lane E, MP-8-3): "matcher writes reconciled=1 directly ->
     * ArchitectureTest post-hoc rule (from T0b, generalised to reconciled) fails." Generalises
     * {@see self::test_no_post_hoc_settlement_channel_updates()}'s shape to the `reconciled`
     * column, with one necessary difference: that rule excludes the WHOLE
     * app/Services/Accounting/ directory because settlement_channel has exactly one legitimate
     * writer (PostingService's own INSERT, which the ->update() pattern never matches anyway).
     * `reconciled` is different — {@see \App\Services\Accounting\ReconciliationService} and
     * {@see \App\Services\Accounting\ReconciliationProposalService} are TWO pre-existing,
     * legitimate writers already living in that same directory (property-assignment + save(),
     * e.g. `$book->reconciled = 1; $book->save();`, not just ->update()), so a directory-level
     * exclusion would blind this rule to exactly the file it exists to police —
     * SupplierStatementMatcher.php, which lives in the SAME app/Services/Accounting/ tree. This
     * rule therefore allow-lists the two known-legitimate FILES individually (same shape as
     * ArchitectureTest::ALLOW_LISTED_RAW_WRITER_FILES above), not a directory, and scans every
     * other file under app/ (this directory included) for either write style: `->update([...
     * 'reconciled' ...])` or a direct `->reconciled = ` property assignment (excluding `==`/`=>`
     * via a negative lookahead so a mere comparison/array-literal-value read is not flagged).
     *
     * A matcher/importer in this task must only ever create/update SupplierStatementImportLine
     * rows and ReconciliationProposal rows (state) — flipping journal_entries.reconciled itself
     * happens exclusively through ReconciliationProposalService::approve()'s existing,
     * owner-gated flow (spec: "reconciliation is read + state only").
     */
    private const ALLOW_LISTED_RECONCILED_WRITER_FILES = [
        'app/Services/Accounting/ReconciliationService.php',
        'app/Services/Accounting/ReconciliationProposalService.php',
    ];

    public function test_no_post_hoc_reconciled_updates(): void
    {
        $violations = $this->findPostHocReconciledUpdates();

        $this->assertEmpty(
            $violations,
            'Post-hoc journal_entries.reconciled write found outside '
                .implode(', ', self::ALLOW_LISTED_RECONCILED_WRITER_FILES)
                ." (MP-8-3: reconciliation is read + state only — flipping 'reconciled' happens "
                .'ONLY through ReconciliationProposalService::approve()):'."\n".implode("\n", $violations)
        );
    }

    /**
     * PHASE GATE (accounting-builds) T12 — mutation proof for the `reconciled` post-hoc rule
     * above, GATE-REPORT §3's second recorded gap (see the settlement_channel MP's docblock
     * immediately above {@see self::findPostHocSettlementChannelUpdates()} for the full context).
     * Probes BOTH write styles the live rule matches (`->update([...'reconciled'...])` and the
     * direct `->reconciled = ` property assignment), plus the two allow-listed engine files
     * (which must NOT be reported — they are the two known-legitimate writers) and a clean file.
     */
    public function test_the_reconciled_ratchet_actually_bites_a_synthetic_violation(): void
    {
        $root = sys_get_temp_dir().'/arch-reconciled-ratchet-mutation-'.uniqid();

        $violations = [
            'Http/Controllers/ProbeReconciledUpdate.php' => "\$book->where('id', 1)->update(['reconciled' => 1]);",
            'Http/Controllers/ProbeReconciledPropertyAssign.php' => '$book->reconciled = 1;',
        ];

        try {
            foreach ($violations as $relative => $body) {
                $path = $root.'/'.$relative;
                @mkdir(dirname($path), 0777, true);
                file_put_contents($path, "<?php\n\n".$body."\n");
            }

            // The two allow-listed engine files are the sole legitimate writers -- an identical
            // write inside either must NOT be reported.
            foreach (self::ALLOW_LISTED_RECONCILED_WRITER_FILES as $allowed) {
                $allowedPath = $root.'/'.$allowed;
                @mkdir(dirname($allowedPath), 0777, true);
                file_put_contents($allowedPath, "<?php\n\n\$book->reconciled = 1;\n");
            }

            // A false-positive guard: a mere comparison/array-literal-value read must not match.
            $cleanPath = $root.'/Http/Controllers/ProbeReconciledClean.php';
            file_put_contents($cleanPath, "<?php\n\nif (\$book->reconciled == 1) { \$x = ['reconciled' => \$book->reconciled]; }\n");

            // Normalise both sides to forward slashes -- getRealPath() returns OS-native
            // separators (backslash on Windows), same convention as the settlement_channel MP
            // test and scanForRawLedgerWriters()'s own mutation proof above.
            $found = array_map(
                fn (string $p): string => str_replace('\\', '/', $p),
                $this->findPostHocReconciledUpdates($root)
            );

            foreach (array_keys($violations) as $relative) {
                $expected = str_replace('\\', '/', $root.'/'.$relative);
                $this->assertContains(
                    $expected,
                    $found,
                    "The reconciled post-hoc-update ratchet did NOT flag {$relative}. The regex for "
                    .'this write shape has stopped matching, so this class of write would now reach '
                    .'production with a green CI. Fix the pattern in findPostHocReconciledUpdates().'
                );
            }

            foreach (self::ALLOW_LISTED_RECONCILED_WRITER_FILES as $allowed) {
                $this->assertNotContains(
                    str_replace('\\', '/', $root.'/'.$allowed),
                    $found,
                    "The reconciled ratchet flagged {$allowed}, one of its own two allow-listed "
                    .'legitimate writers.'
                );
            }

            $this->assertNotContains(
                str_replace('\\', '/', $cleanPath),
                $found,
                'The reconciled ratchet flagged a bare comparison / array-literal read as a write.'
            );
        } finally {
            $this->removeDirectory($root);
        }
    }

    /**
     * @return string[] absolute paths with a hit.
     */
    private function findPostHocReconciledUpdates(?string $rootDir = null): array
    {
        // PHASE GATE (accounting-builds T12): $rootDir exists ONLY so the mutation proof above
        // can point this scanner at a synthetic tree, same convention as
        // {@see self::scanForRawLedgerWriters()}'s own $rootDir parameter. Defaults to
        // base_path('app'), so the live rule is byte-identical to before this parameter existed.
        $appDir = $rootDir ?? base_path('app');

        $violations = [];

        if (! is_dir($appDir)) {
            return $violations;
        }

        // Either write style: ->update([...'reconciled'...]) or a direct property assignment
        // (->reconciled = ...), excluding == / => so a comparison or an unrelated array literal
        // key=>value read is never flagged.
        $pattern = '/->\s*update\s*\(\s*\[[^\]]*[\'"]reconciled[\'"]|->reconciled\s*=(?!=)/s';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            $normalizedPath = str_replace('\\', '/', $realPath);
            $relativePath = null;
            foreach (self::ALLOW_LISTED_RECONCILED_WRITER_FILES as $allowed) {
                if (str_ends_with($normalizedPath, $allowed)) {
                    $relativePath = $allowed;
                    break;
                }
            }

            if ($relativePath !== null) {
                continue;
            }

            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }

            if (preg_match($pattern, $contents) === 1) {
                $violations[] = $realPath;
            }
        }

        return $violations;
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

    /**
     * Timezone-safety fix (accounting-builds, 2026-09-02 read-only audit): prod `APP_TIMEZONE` is
     * `Asia/Kuwait` but MySQL/MariaDB's own clock (`NOW()`/`CURRENT_TIMESTAMP`) is UTC — a column
     * that defaults to the DB's own clock silently drifts 3 hours from every other date/time this
     * app writes (all go through PHP's tz-aware `now()`). `journal_entries.cheque_date` was exactly
     * this bug (`timestamp('cheque_date')->nullable()->useCurrent()`,
     * `2025_03_25_085713_add_columns_to_general_ledgers_table.php:20`) — fixed by migration
     * `2026_09_02_000009_drop_db_clock_defaults_in_accounting_tables.php`. This is the CI ratchet
     * that keeps it fixed: no accounting-table migration may EVER introduce `useCurrent(`,
     * `useCurrentOnUpdate(`, or a raw `CURRENT_TIMESTAMP` default again.
     *
     * Same allow-list shape as {@see self::ALLOW_LISTED_RAW_WRITER_FILES} above: a hit in a
     * migration NOT on {@see self::ALLOW_LISTED_DB_CLOCK_DEFAULT_MIGRATIONS} fails the build (new
     * regression); an allow-listed file with no hit also fails (stale entry — remove it once the
     * migration is deleted or truly no longer matches). Deliberately does NOT flag the two
     * historical migrations that introduced (2025_03_25) then removed (2026_09_02, this same wave)
     * the `cheque_date` default — both are named on the allow-list with an explanation, since a
     * migration file is an immutable historical record and rewriting it would break `php artisan
     * migrate:fresh` on any environment that already ran it.
     */
    private const ALLOW_LISTED_DB_CLOCK_DEFAULT_MIGRATIONS = [
        // Introduced the original `journal_entries.cheque_date` useCurrent() default (table was
        // still named `general_ledgers` at the time). This is the historical bug the 2026-09-02
        // migration below fixes — kept as an immutable past migration, not rewritten.
        '2025_03_25_085713_add_columns_to_general_ledgers_table.php',
        // The fix itself: this migration's down() legitimately RE-ADDS useCurrent() (to make
        // rollback byte-identical to the pre-fix schema) — that re-add is inside a rollback path,
        // not a live schema shape, but the whole-file text scan below cannot tell down() apart from
        // up(), so this file is allow-listed rather than the scanner made AST-aware for one line.
        '2026_09_02_000009_drop_db_clock_defaults_in_accounting_tables.php',
        // The scan's regex matches whole-file TEXT, including comments/docblocks (same caveat the
        // raw-writer scan documents for PostingEngineDisabledException.php above). This migration's
        // only hit is a docblock MENTION of `useCurrent()` — describing why `cheque_clearance_date`
        // deliberately does NOT use that shape ("Deliberately a plain `date`, not the `timestamp
        // ... useCurrent()` shape `cheque_date` uses next to it") — its actual column definition is
        // a plain nullable `date` with no default at all.
        '2026_08_29_090000_add_cheque_clearance_date_to_journal_entries_table.php',
        // PRE-EXISTING GAPS, out of scope for this 2026-09-02 timezone-safety fix (which targeted
        // specifically `journal_entries.cheque_date` and `transactions.transaction_date` — see this
        // rule's own class-level docblock). This rule's table-scoped scan surfaced two more real,
        // live DB-clock defaults on the policed accounting-table list that predate this fix and
        // were not part of its scope. Allow-listed (not silently fixed) so the ratchet still catches
        // any NEW db-clock default from here on, while flagging these two as a known, tracked gap
        // for a future timezone-safety wave rather than expanding this "small, surgical" fix's
        // blast radius.
        //   - exchange_rate_histories.changed_at: `$table->timestamp('changed_at')->useCurrent();`
        '2025_07_30_110917_create_exchange_rate_histories_table.php',
        //   - payment_applications.applied_at: `$table->timestamp('applied_at')->useCurrent();`
        '2026_01_12_154855_create_payment_applications_table.php',
    ];

    /**
     * Every accounting table this rule polices (P3.j timezone-safety fix scope, matching the phase
     * brief's own table list). A migration is only flagged when it references one of THESE table
     * names — a `useCurrent()` on some unrelated table (e.g. a `password_reset_tokens.created_at`)
     * is a normal Laravel convention this rule has no opinion about.
     *
     * `general_ledgers` is included alongside `journal_entries`: it is that same table's ORIGINAL
     * name before `2025_03_28_145526_rename_table_general_ledgers_to_journal_entries.php` — the
     * allow-listed 2025_03_25 migration that introduced the historical `cheque_date` default still
     * refers to the table by its pre-rename name, so this alias is required for that file to
     * actually produce a hit (and therefore not be flagged as a stale allow-list entry).
     */
    private const DB_CLOCK_DEFAULT_POLICED_TABLES = [
        'transactions', 'journal_entries', 'general_ledgers', 'accounting_periods',
        'accounting_audit_log', 'reconciliation_', 'gateway_settlements', 'fixed_asset',
        'supplier_statement_', 'bank_statement_', 'supplier_bank_details', 'serial_schemas',
        'system_accounts', 'payment_applications', 'exchange_rate_histories',
    ];

    public function test_no_db_clock_defaults_in_accounting_migrations(): void
    {
        $result = $this->scanForDbClockDefaultsInMigrations();

        $message = '';

        if (! empty($result['unlisted'])) {
            $message .= 'Accounting-table migration(s) found introducing a DB-clock default '
                .'(useCurrent()/useCurrentOnUpdate()/CURRENT_TIMESTAMP) NOT on the allow-list -- '
                ."MySQL/MariaDB's own clock is UTC while APP_TIMEZONE is Asia/Kuwait, so a DB-clock "
                .'default silently drifts from every app-written date/time. Use an explicit '
                ."PHP now() at the write site instead (see PostingService::post() step 8's cheque_date "
                .'fallback for the pattern), or if this is a deliberately-reviewed exception, add the '
                .'file to ArchitectureTest::ALLOW_LISTED_DB_CLOCK_DEFAULT_MIGRATIONS with a note:'
                ."\n".implode("\n", $result['unlisted'])."\n";
        }

        if (! empty($result['stale'])) {
            $message .= 'Allow-listed DB-clock-default migration(s) with NO hit found (stale '
                .'allow-list entry -- remove from '
                ."ArchitectureTest::ALLOW_LISTED_DB_CLOCK_DEFAULT_MIGRATIONS):\n"
                .implode("\n", $result['stale']);
        }

        $this->assertTrue(empty($result['unlisted']) && empty($result['stale']), $message);
    }

    /**
     * Mutation proof (same construction as
     * {@see self::test_the_raw_writer_ratchet_actually_bites_a_synthetic_violation()}): plants a
     * synthetic migration carrying a `useCurrent()` default on one of the policed tables, plus one
     * on an UNPOLICED table (must not be flagged) and a clean migration (must not be flagged), and
     * asserts the scanner reports exactly the policed one.
     */
    public function test_the_db_clock_default_ratchet_actually_bites_a_synthetic_violation(): void
    {
        $root = sys_get_temp_dir().'/arch-db-clock-default-ratchet-mutation-'.uniqid();

        try {
            $violationPath = $root.'/2099_01_01_000000_probe_add_db_clock_default_to_journal_entries.php';
            @mkdir(dirname($violationPath), 0777, true);
            file_put_contents(
                $violationPath,
                "<?php\n\nSchema::table('journal_entries', function (\$table) {\n"
                    ."    \$table->timestamp('probe_col')->nullable()->useCurrent();\n});\n"
            );

            // Unrelated table -- a useCurrent() here is a normal Laravel convention this rule has
            // no opinion about, and must NOT be reported.
            $unpolicedPath = $root.'/2099_01_01_000001_probe_add_db_clock_default_to_password_resets.php';
            file_put_contents(
                $unpolicedPath,
                "<?php\n\nSchema::table('password_reset_tokens', function (\$table) {\n"
                    ."    \$table->timestamp('created_at')->nullable()->useCurrent();\n});\n"
            );

            $cleanPath = $root.'/2099_01_01_000002_probe_clean_migration.php';
            file_put_contents(
                $cleanPath,
                "<?php\n\nSchema::table('journal_entries', function (\$table) {\n"
                    ."    \$table->string('probe_col')->nullable();\n});\n"
            );

            $result = $this->scanForDbClockDefaultsInMigrations($root);

            // Normalise both sides to forward slashes -- getRealPath() returns OS-native separators
            // (backslash on Windows), same convention as the settlement_channel/reconciled mutation
            // proofs above.
            $found = array_map(fn (string $p): string => str_replace('\\', '/', $p), $result['unlisted']);

            $this->assertContains(
                str_replace('\\', '/', $violationPath),
                $found,
                'The DB-clock-default ratchet did NOT flag a synthetic useCurrent() on a policed '
                .'accounting table. The scanner has stopped matching, so this class of timezone '
                .'regression would now reach production with a green CI. Fix '
                .'scanForDbClockDefaultsInMigrations().'
            );

            $this->assertNotContains(
                str_replace('\\', '/', $unpolicedPath),
                $found,
                'The DB-clock-default ratchet flagged a useCurrent() on an unpoliced table -- this '
                .'rule must only police the accounting table list.'
            );

            $this->assertNotContains(
                str_replace('\\', '/', $cleanPath),
                $found,
                'The DB-clock-default ratchet flagged a migration with no DB-clock default at all.'
            );
        } finally {
            $this->removeDirectory($root);
        }
    }

    /**
     * @return array{unlisted: string[], stale: string[]}
     */
    private function scanForDbClockDefaultsInMigrations(?string $rootDir = null): array
    {
        $migrationsDir = $rootDir ?? database_path('migrations');

        $defaultPattern = '/useCurrent\s*\(|useCurrentOnUpdate\s*\(|CURRENT_TIMESTAMP/i';

        $unlisted = [];
        $hitAllowListed = [];

        if (! is_dir($migrationsDir)) {
            return ['unlisted' => $unlisted, 'stale' => self::ALLOW_LISTED_DB_CLOCK_DEFAULT_MIGRATIONS];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($migrationsDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            $filename = $file->getFilename();

            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }

            if (preg_match($defaultPattern, $contents) !== 1) {
                continue;
            }

            $referencesPolicedTable = false;
            foreach (self::DB_CLOCK_DEFAULT_POLICED_TABLES as $table) {
                if (str_contains($contents, "'{$table}") || str_contains($contents, "\"{$table}")) {
                    $referencesPolicedTable = true;
                    break;
                }
            }

            if (! $referencesPolicedTable) {
                continue;
            }

            if (in_array($filename, self::ALLOW_LISTED_DB_CLOCK_DEFAULT_MIGRATIONS, true)) {
                $hitAllowListed[$filename] = true;
            } else {
                $unlisted[] = $realPath;
            }
        }

        $stale = array_values(array_diff(self::ALLOW_LISTED_DB_CLOCK_DEFAULT_MIGRATIONS, array_keys($hitAllowListed)));

        return ['unlisted' => $unlisted, 'stale' => $stale];
    }
}
