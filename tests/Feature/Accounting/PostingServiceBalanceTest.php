<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\CrossTenantAccountException;
use App\Exceptions\Accounting\InvalidCurrencyCodeException;
use App\Exceptions\Accounting\InvalidRepostSourceException;
use App\Exceptions\Accounting\NonLeafAccountException;
use App\Exceptions\Accounting\ProtectedLineException;
use App\Exceptions\Accounting\SupersededIdempotencyKeyException;
use App\Exceptions\Accounting\UnbalancedDocumentException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\IdempotencyKeyRejection;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * P1 acceptance tests for PostingService (Accounting Gap/11-technical-implementation-plan.md
 * §P1.1, "Acceptance tests (P1)"). Every test name below is pinned verbatim
 * to the build contract's acceptanceTests list.
 *
 * These tests exercise ONLY the new, flag-off App\Services\Accounting\*
 * classes directly — no existing feeder, controller, or route is touched
 * (mission scope rule 1/3). PostingService is resolved through the
 * container (app(PostingService::class)) so its AccountResolver /
 * SequenceService / PeriodGuard / Money collaborators are whatever the
 * parallel builder registers — this test suite does not construct
 * PostingService itself, keeping it decoupled from those classes' own
 * constructors.
 */
class PostingServiceBalanceTest extends AccountingTestCase
{
    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * A minimal balanced two-line draft: debit $debitAccount, credit
     * $creditAccount, for $amount, using explicit accountId lines (not
     * purposeCode) so these tests never depend on system_accounts /
     * AccountResolver being seeded.
     *
     * purposeCode is typed `string` (non-nullable) on LineDraft even though
     * these lines are user/test-picked by accountId — passing '' is the
     * contract's only way to satisfy that type when purposeCode genuinely
     * doesn't apply to a line (file 11 L219-232: "OR explicit accountId for
     * user-picked lines").
     */
    private function balancedDraft(
        Company $company,
        Branch $branch,
        Account $debitAccount,
        Account $creditAccount,
        float $amount,
        string $docType = 'JV',
        ?string $idempotencyKey = null
    ): DocumentDraft {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: $docType,
            subType: null,
            docDate: now(),
            narration: 'PostingServiceBalanceTest fixture document',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Pins: PIPELINE step 4 (header rule, R2/BUG-C1, check 1.1) —
     * abs(Σdebit − Σcredit) < 0.0005 or throw UnbalancedDocumentException.
     * Also pins the "never partially commits" invariant: a rejected draft
     * must write NOTHING (kills R2.2c / BUG-C1's one-sided branches).
     */
    public function test_post_rejects_unbalanced_document(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Unbalanced document must be rejected',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 100.000,
                    currency: 'KWD',
                    originalAmount: 100.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 50.000,
                    currency: 'KWD',
                    originalAmount: 50.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
        );

        $transactionCountBefore = DB::table('transactions')->count();
        $lineCountBefore = DB::table('journal_entries')->count();

        $this->expectException(UnbalancedDocumentException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertSame(
                $transactionCountBefore,
                DB::table('transactions')->count(),
                'A rejected unbalanced document must not write a transaction header.'
            );
            $this->assertSame(
                $lineCountBefore,
                DB::table('journal_entries')->count(),
                'A rejected unbalanced document must not write any journal_entries lines.'
            );
        }
    }

    /**
     * Pins: PIPELINE step 3d (R1/BUG-C2) — an account with children fails
     * the leaf rule (children()->doesntExist() AND is_group === false) and
     * must throw NonLeafAccountException, never post to a parent/group node.
     */
    public function test_post_rejects_non_leaf_account(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $parentAccount = Account::factory()->create(['company_id' => $company->id]);
        // Giving it a real child is what makes it non-leaf, independent of is_group.
        Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $parentAccount->id,
        ]);

        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->balancedDraft($company, $branch, $parentAccount, $creditAccount, 100.000);

        $transactionCountBefore = DB::table('transactions')->count();

        $this->expectException(NonLeafAccountException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertSame(
                $transactionCountBefore,
                DB::table('transactions')->count(),
                'A rejected non-leaf posting must not write a transaction header.'
            );
        }
    }

    /**
     * Pins: PIPELINE step 2 (T7.1) — an explicit accountId is loaded and its
     * company_id asserted === draft.companyId; a cross-tenant account must
     * throw. No named exception is given for this in file 11 itself, but
     * the contract's own Open Questions section RECOMMENDs
     * CrossTenantAccountException extends PostingException specifically to
     * satisfy this acceptance test, so we pin that name here.
     */
    public function test_post_rejects_cross_tenant_account(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $branchA = $this->makeBranch($companyA);

        $debitAccountCompanyA = Account::factory()->create(['company_id' => $companyA->id]);
        $creditAccountCompanyB = Account::factory()->create(['company_id' => $companyB->id]);

        $draft = $this->balancedDraft($companyA, $branchA, $debitAccountCompanyA, $creditAccountCompanyB, 100.000);

        $transactionCountBefore = DB::table('transactions')->count();

        $this->expectException(CrossTenantAccountException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertSame(
                $transactionCountBefore,
                DB::table('transactions')->count(),
                'A rejected cross-tenant posting must not write a transaction header.'
            );
        }
    }

    /**
     * Pins: PIPELINE step 1 (F2/F8/F9) — two post() calls carrying the same
     * (companyId, idempotencyKey) must produce exactly one transaction and
     * its lines; the second call returns the SAME PostedDocument and writes
     * nothing new.
     */
    public function test_post_is_idempotent(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $idempotencyKey = 'test:idempotent-post-'.uniqid();

        $makeDraft = fn () => $this->balancedDraft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            75.000,
            'RV',
            $idempotencyKey
        );

        $service = app(PostingService::class);

        $first = $service->post($makeDraft());
        $second = $service->post($makeDraft());

        $this->assertSame(
            $first->transaction->id,
            // Cast: $second's Transaction comes back through toPostedDocument()'s re-fetch
            // (Transaction::withoutGlobalScopes()->...->first()), whose id attribute is whatever
            // type PDO_MySQL's emulated prepares hand back for a bigint column — a PHP string
            // under this connection's default options (config/database.php sets no
            // PDO::ATTR_EMULATE_PREPARES => false) — while $first->transaction->id is a genuine
            // int from Eloquent's fresh-insert path. A bare assertSame() would fail on correct
            // behaviour (.planning/P1-VERIFICATION-FINDINGS.json findings[]: "at least one
            // assertion is type-wrong").
            (int) $second->transaction->id,
            'A second post() with the same idempotencyKey must return the SAME transaction, not create a new one.'
        );

        $transactionCount = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->count();
        $this->assertSame(
            1,
            $transactionCount,
            'Exactly one transaction row may exist for a given idempotency key, no matter how many times post() runs.'
        );

        $lineCount = DB::table('journal_entries')
            ->where('transaction_id', $first->transaction->id)
            ->whereNull('deleted_at')
            ->count();
        $this->assertSame(
            2,
            $lineCount,
            'The draft\'s two lines must be written exactly once — the repeated post() must write nothing new.'
        );
    }

    /**
     * Pins: reverse() (R4/BUG-H4) — original lines are never mutated or
     * deleted; a NEW dated document is created with debit/credit swapped,
     * docType='REV', reversal_of_transaction_id set; the running balance
     * per account nets to zero across original + reversal. Also pins
     * reverse()'s own idempotency: calling it twice on the same original
     * returns the existing reversal rather than creating a duplicate.
     */
    public function test_reverse_creates_swapped_document_and_leaves_original(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->balancedDraft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            200.000,
            'INV',
            'test:reverse-original-'.uniqid()
        );

        $service = app(PostingService::class);

        $posted = $service->post($draft);
        $originalTransactionId = $posted->transaction->id;

        $originalLinesBefore = DB::table('journal_entries')
            ->where('transaction_id', $originalTransactionId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['account_id', 'debit', 'credit'])
            ->toArray();

        $reversed = $service->reverse($posted->transaction, now(), null);

        $originalLinesAfter = DB::table('journal_entries')
            ->where('transaction_id', $originalTransactionId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['account_id', 'debit', 'credit'])
            ->toArray();

        $this->assertEquals(
            $originalLinesBefore,
            $originalLinesAfter,
            'reverse() must never mutate the original posted lines.'
        );

        $this->assertNotSame($originalTransactionId, $reversed->transaction->id);
        $this->assertSame(
            'REV',
            DB::table('transactions')->where('id', $reversed->transaction->id)->value('doc_type')
        );
        $this->assertSame(
            $originalTransactionId,
            // Cast: PDO_MySQL's emulated prepares (the default here — no
            // PDO::ATTR_EMULATE_PREPARES => false is set anywhere in config/database.php) return
            // bigint columns as PHP strings, while $originalTransactionId is a genuine int from
            // Eloquent. A bare assertSame() would fail on correct behaviour
            // (.planning/P1-VERIFICATION-FINDINGS.json findings[]: "at least one assertion is
            // type-wrong").
            (int) DB::table('transactions')->where('id', $reversed->transaction->id)->value('reversal_of_transaction_id')
        );

        $netByAccount = DB::table('journal_entries')
            ->whereIn('transaction_id', [$originalTransactionId, $reversed->transaction->id])
            ->whereNull('deleted_at')
            ->selectRaw('account_id, SUM(debit) - SUM(credit) as net')
            ->groupBy('account_id')
            ->get();

        $this->assertGreaterThan(0, $netByAccount->count(), 'Expected journal_entries rows across the original + reversal to net-check.');

        foreach ($netByAccount as $row) {
            $this->assertLessThan(
                0.0005,
                abs((float) $row->net),
                "Account #{$row->account_id} does not net to zero across the original and its reversal."
            );
        }

        // reverse() idempotency: a second call on the same original must return the SAME reversal.
        $reversedAgain = $service->reverse($posted->transaction, now(), null);
        $this->assertSame(
            $reversed->transaction->id,
            // Cast: see the identical PDO-emulated-prepares note above test_post_is_idempotent's
            // equivalent assertion — $reversedAgain comes back through the same
            // toPostedDocument() re-fetch path.
            (int) $reversedAgain->transaction->id,
            'reverse() called twice on the same original must return the existing reversal, not create a second one.'
        );

        $reversalCount = DB::table('transactions')
            ->where('reversal_of_transaction_id', $originalTransactionId)
            ->count();
        $this->assertSame(1, $reversalCount, 'Only one reversal document may ever exist for a given original transaction.');
    }

    /**
     * Pins: reverse()'s reconciled-line protection (blueprint 02 §5,
     * "Protect applied/reconciled lines") — refuses when any line is
     * reconciled != 0 unless the caller passes force=true.
     *
     * File 11 itself names no exception subclass for this specific rule
     * (unlike every other pipeline rule, which gets one) — this asserts on
     * App\Exceptions\Accounting\ProtectedLineException, the concrete class
     * already present in app/Exceptions/Accounting/ProtectedLineException.php,
     * added there "following the same additive pattern file 11's Open
     * Questions section recommends for CrossTenantAccountException."
     */
    public function test_reverse_refuses_reconciled_lines_without_force(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->balancedDraft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            60.000,
            'RV',
            'test:reverse-reconciled-'.uniqid()
        );

        $service = app(PostingService::class);
        $posted = $service->post($draft);

        // Simulate a settled/reconciled line via direct data setup — P1
        // exposes no "reconcile" API of its own to produce this state.
        DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->where('account_id', $debitAccount->id)
            ->update(['reconciled' => 1]);

        $threw = false;
        try {
            $service->reverse($posted->transaction, now(), null);
        } catch (ProtectedLineException $e) {
            $threw = true;
            $this->assertSame($posted->transaction->id, $e->transactionId);
        }
        $this->assertTrue($threw, 'reverse() must refuse a document with a reconciled line unless force=true.');

        $this->assertSame(
            0,
            DB::table('transactions')->where('reversal_of_transaction_id', $posted->transaction->id)->count(),
            'A refused reverse() must not create any reversal document.'
        );

        $forced = $service->reverse($posted->transaction, now(), null, true);

        $this->assertSame(
            1,
            DB::table('transactions')->where('reversal_of_transaction_id', $posted->transaction->id)->count(),
            'reverse(..., force: true) must succeed as a supervisor override.'
        );
        $this->assertSame(
            'REV',
            DB::table('transactions')->where('id', $forced->transaction->id)->value('doc_type')
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // FIX-ROUND regression coverage (.planning/P1-VERIFICATION-FINDINGS.json) — added alongside
    // the parallel blocker fixes to PostingService/AccountResolver/SequenceService. Every test
    // below pins one blocker or finding from that verification pass by number/title in its
    // docblock.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * BLOCKER 1 (.planning/P1-VERIFICATION-FINDINGS.json blockers[0]) — end-to-end pin, the
     * single most important new test in this round: "AccountResolver never asserts the resolved
     * account belongs to the requesting company — the purposeCode path can write cross-tenant
     * journal lines." A mis-seeded system_accounts row pointing at another company's account must
     * be rejected by the FULL post() pipeline (not just by AccountResolver in isolation — see
     * AccountResolverTest for that unit-level pin). Concrete input pinned to the finding's own
     * failure scenario: INSERT INTO system_accounts (company_id=A, purpose_code=
     * 'RECEIVABLE_CONTROL', account_id=<leaf owned by company B>), then post() a document whose
     * line names that purpose code with no explicit accountId. Before the fix this committed a
     * journal_entries row with company_id=A pointing at an account belonging to company B — no
     * exception, no log.
     */
    public function test_post_rejects_cross_tenant_account_resolved_via_purpose_code(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $branchA = $this->makeBranch($companyA);

        $creditAccount = Account::factory()->create(['company_id' => $companyA->id]);
        $foreignLeaf = Account::factory()->create(['company_id' => $companyB->id]);

        // The corruption shape blocker 1 names verbatim: a system_accounts row for company A
        // that maps a purpose code to a leaf owned by company B. No API in this build produces
        // this row — inserted directly, exactly as an import/tinker-fix/mis-seeded row would.
        DB::table('system_accounts')->insert([
            'company_id' => $companyA->id,
            'purpose_code' => 'RECEIVABLE_CONTROL',
            'service_type' => null,
            'account_id' => $foreignLeaf->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $draft = new DocumentDraft(
            companyId: $companyA->id,
            branchId: $branchA->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Cross-tenant purposeCode regression pin',
            lines: [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL',
                    accountId: null,
                    side: 'debit',
                    amount: 100.000,
                    currency: 'KWD',
                    originalAmount: 100.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 100.000,
                    currency: 'KWD',
                    originalAmount: 100.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
        );

        $transactionCountBefore = DB::table('transactions')->count();
        $lineCountBefore = DB::table('journal_entries')->count();

        $this->expectException(CrossTenantAccountException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertSame(
                $transactionCountBefore,
                DB::table('transactions')->count(),
                'A rejected cross-tenant purposeCode posting must not write a transaction header.'
            );
            $this->assertSame(
                $lineCountBefore,
                DB::table('journal_entries')->count(),
                'A rejected cross-tenant purposeCode posting must not write any journal_entries lines.'
            );

            // Belt-and-braces beyond the exception itself: no journal_entries row for company A
            // may ever reference an account owned by company B — the exact invariant this
            // blocker breaks, checked directly rather than only trusting the thrown exception.
            $crossTenantRowExists = DB::table('journal_entries as je')
                ->join('accounts as a', 'a.id', '=', 'je.account_id')
                ->where('je.company_id', $companyA->id)
                ->whereColumn('je.company_id', '!=', 'a.company_id')
                ->exists();
            $this->assertFalse(
                $crossTenantRowExists,
                'No journal_entries row for company A may reference an account owned by company B.'
            );
        }
    }

    /**
     * BLOCKER 2 (.planning/P1-VERIFICATION-FINDINGS.json blockers[1]), branchless post(): a
     * company with no branch dimension (DocumentDraft::$branchId is a non-nullable int, so 0 is
     * the natural value the finding itself names) must be able to post at all.
     *
     * P1 ROUND 3 (journal-side fix, blocker 2 was still PARTIAL after round 2): the round-2 fix
     * made SequenceService/serial_schemas branchless-safe (0 sentinel, no FK on that table) but
     * left `journal_entries.branch_id` a hard NOT NULL FK to `branches` (ids start at 1) — so
     * PostingService's step-8 line write, which wrote the SAME 0 sentinel straight into that
     * column, still raised a masked MySQL 1452 on every branchless post(). Fixed two ways: (1)
     * migration 2026_08_24_120006 makes `journal_entries.branch_id` nullable (FK kept — MySQL
     * FKs never validate NULL), and (2) PostingService's step-8 JournalEntry::create() now writes
     * `$draft->branchId > 0 ? $draft->branchId : null` instead of the raw sentinel — see this
     * round's report for the exact PostingService.php edit (out of scope for this fixer to apply
     * directly). `transactions.branch_id` is deliberately UNCHANGED: it has no FK (grepped every
     * migration touching that table) and is already nullable, so it keeps storing the 0 sentinel
     * literally — asserted below exactly as before.
     */
    public function test_post_succeeds_for_branchless_document(): void
    {
        $company = $this->makeCompany();

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: 0,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Branchless post() regression pin',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 30.000,
                    currency: 'KWD',
                    originalAmount: 30.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 30.000,
                    currency: 'KWD',
                    originalAmount: 30.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            idempotencyKey: 'test:branchless-post-'.uniqid(),
        );

        $posted = app(PostingService::class)->post($draft);

        $this->assertSame(
            0,
            (int) DB::table('transactions')->where('id', $posted->transaction->id)->value('branch_id'),
            'A branchless document must persist the 0 sentinel on transactions.branch_id (no FK '
                .'there, unaffected by this round\'s journal-side fix), not fail to post at all.'
        );
        $this->assertSame(
            2,
            DB::table('journal_entries')->where('transaction_id', $posted->transaction->id)->count(),
            'Both lines must be written — a branchless post() must not silently drop lines to '
                .'dodge the FK.'
        );
        $this->assertSame(
            [null, null],
            DB::table('journal_entries')->where('transaction_id', $posted->transaction->id)->pluck('branch_id')->all(),
            'journal_entries.branch_id must be a real NULL for a branchless document — the 0 '
                .'sentinel would violate the branches FK on this column (branches.id starts at 1), '
                .'unlike transactions.branch_id above which has no FK and keeps the literal 0.'
        );
    }

    /**
     * BLOCKER 2 (.planning/P1-VERIFICATION-FINDINGS.json blockers[1]), reverse() of a legacy
     * NULL-branch transaction: the finding's failure path (b) — "PostingService::reverse()
     * explicitly builds branchId: (int) $posted->branch_id, which is 0 for the legacy NULL-branch
     * rows P3 must reverse."
     *
     * P1 ROUND 3 (journal-side fix): under round 2, `journal_entries.branch_id` was still a hard
     * NOT NULL FK, so this fixture's ORIGINAL lines had to be given a real `$branch->id` — the
     * schema gave this method no other legal value to write, even though the scenario it claims
     * to model ("a legacy NULL-branch transaction") is really about a fully branchless legacy
     * document, header AND lines alike. Migration 2026_08_24_120006 makes
     * `journal_entries.branch_id` nullable (FK kept), so the fixture now writes a real NULL on
     * both original lines too — no `Branch` fixture needed at all — and the assertions below pin
     * the actual round-3 contract: `reverse()` derives the 0 sentinel for `transactions.branch_id`
     * (that column keeps the sentinel — no FK, unaffected by this round) while the new REVERSAL's
     * `journal_entries.branch_id` comes out a real NULL (that column's FK would reject 0 outright
     * — see PostingService's step-8 write, specified in this round's report).
     */
    public function test_reverse_succeeds_for_a_legacy_null_branch_transaction(): void
    {
        $company = $this->makeCompany();

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $transactionId = DB::table('transactions')->insertGetId([
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => null, // the legacy shape blocker 2 names explicitly
            'company_id' => $company->id,
            'transaction_type' => 'JV',
            'amount' => 80.000,
            'description' => 'Legacy NULL-branch transaction fixture',
            'reference_type' => 'Invoice',
            'reference_number' => 'LEGACY-0001',
            'transaction_date' => now(),
            'doc_type' => 'JV',
            'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted',
            'total_debit' => 80.000,
            'total_credit' => 80.000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entries')->insert([
            [
                'name' => $debitAccount->name,
                'transaction_id' => $transactionId,
                'company_id' => $company->id,
                'account_id' => $debitAccount->id,
                // journal_entries.branch_id is nullable as of migration 2026_08_24_120006 (FK
                // kept) — a fully branchless legacy document can now be represented honestly,
                // header and lines alike, instead of borrowing an unrelated real branch here.
                'branch_id' => null,
                'transaction_date' => now(),
                'description' => 'Legacy debit leg',
                'debit' => 80.000,
                'credit' => 0,
                'voucher_number' => 'LEGACY-0001',
                'currency' => 'KWD',
                'exchange_rate' => 1.0,
                'amount' => 80.000,
                'reconciled' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => $creditAccount->name,
                'transaction_id' => $transactionId,
                'company_id' => $company->id,
                'account_id' => $creditAccount->id,
                'branch_id' => null,
                'transaction_date' => now(),
                'description' => 'Legacy credit leg',
                'debit' => 0,
                'credit' => 80.000,
                'voucher_number' => 'LEGACY-0001',
                'currency' => 'KWD',
                'exchange_rate' => 1.0,
                'amount' => 80.000,
                'reconciled' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $posted = Transaction::withoutGlobalScopes()->findOrFail($transactionId);
        $this->assertNull(
            $posted->branch_id,
            'Precondition: this transaction must carry the legacy NULL branch_id shape.'
        );

        $reversed = app(PostingService::class)->reverse($posted, now(), null);

        $this->assertSame(
            'REV',
            DB::table('transactions')->where('id', $reversed->transaction->id)->value('doc_type')
        );
        $this->assertSame(
            0,
            (int) DB::table('transactions')->where('id', $reversed->transaction->id)->value('branch_id'),
            'reverse() must derive the 0 branchless sentinel from a legacy NULL branch_id and '
                .'persist it on transactions.branch_id (no FK there), not fail with a serial error.'
        );
        $this->assertSame(
            2,
            DB::table('journal_entries')->where('transaction_id', $reversed->transaction->id)->count(),
            'Both reversal lines must be written despite the legacy NULL branch.'
        );
        $this->assertSame(
            [null, null],
            DB::table('journal_entries')->where('transaction_id', $reversed->transaction->id)->pluck('branch_id')->all(),
            'The REVERSAL\'s journal_entries.branch_id must be a real NULL, not the 0 sentinel — '
                .'that column keeps a real branches FK (branches.id starts at 1), which 0 would '
                .'violate; only transactions.branch_id (no FK) stores 0 literally.'
        );
    }

    /**
     * BLOCKER 3 (.planning/P1-VERIFICATION-FINDINGS.json blockers[2]) — "docType→reference_type
     * map sends JV/OJV/REV to 'Invoice', and sourceId is then written straight into
     * transactions.invoice_id." A JV/OJV/REV document must NEVER populate transactions.invoice_id
     * from sourceId, even when sourceId happens to be a plain integer (file 11 itself documents
     * sourceId as carrying "invoice_id / payment_id / task_id" — exactly file 13 Stage D's
     * reattribution shape, which posts docType='JV' carrying an old journal-entry id).
     * transactions.invoice_id carries a real FK to invoices with no tenant check anywhere, so
     * before the fix this either hard-rolls-back on a non-invoice id or silently links to
     * whatever invoice happens to own that id.
     */
    public function test_post_does_not_populate_invoice_id_for_journal_voucher_doc_types(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        foreach (['JV', 'OJV', 'REV'] as $docType) {
            $debitAccount = Account::factory()->create(['company_id' => $company->id]);
            $creditAccount = Account::factory()->create(['company_id' => $company->id]);

            $draft = new DocumentDraft(
                companyId: $company->id,
                branchId: $branch->id,
                docType: $docType,
                subType: null,
                docDate: now(),
                narration: "Blocker 3 regression pin for docType={$docType}",
                lines: [
                    new LineDraft(
                        purposeCode: '',
                        accountId: $debitAccount->id,
                        side: 'debit',
                        amount: 42.000,
                        currency: 'KWD',
                        originalAmount: 42.000,
                        exchangeRate: 1.0,
                        transactionType: 'TEST_DEBIT',
                    ),
                    new LineDraft(
                        purposeCode: '',
                        accountId: $creditAccount->id,
                        side: 'credit',
                        amount: 42.000,
                        currency: 'KWD',
                        originalAmount: 42.000,
                        exchangeRate: 1.0,
                        transactionType: 'TEST_CREDIT',
                    ),
                ],
                idempotencyKey: "test:no-invoice-link-{$docType}-".uniqid(),
                // Deliberately an int that is NOT an invoices.id in this fresh company — the
                // exact shape a JV reattribution document naturally carries (a task id or a
                // legacy journal-entry id) per the blocker's own failure scenario.
                sourceId: 999999,
            );

            $posted = app(PostingService::class)->post($draft);

            $this->assertNull(
                DB::table('transactions')->where('id', $posted->transaction->id)->value('invoice_id'),
                "docType={$docType} must never populate transactions.invoice_id from sourceId."
            );
        }
    }

    /**
     * BLOCKER 5 (.planning/P1-VERIFICATION-FINDINGS.json blockers[4]) — "Idempotency is only
     * correct when calls are serialized — a concurrent duplicate raises a raw QueryException
     * instead of returning the existing PostedDocument." The existing test_post_is_idempotent
     * only proves the DB-level unique index prevents a duplicate ROW; it never proves the
     * CONTRACT ("return the existing PostedDocument, do NOTHING") survives a race. Simulated here
     * exactly as the finding's own fix note describes the gap: a pre-existing header row with the
     * SAME (company_id, idempotency_key) but a posting_status OTHER than 'posted' — the step-1
     * SELECT filters posting_status='posted' (so it misses this row) while the unique index does
     * not (so the INSERT collides). Before the fix this raised a raw
     * Illuminate\Database\QueryException (1062) to the caller.
     */
    public function test_post_returns_existing_document_on_idempotency_key_collision(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $idempotencyKey = 'test:collision-'.uniqid();

        $existingTransactionId = DB::table('transactions')->insertGetId([
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'transaction_type' => 'JV',
            'amount' => 15.000,
            'description' => 'Simulated concurrent-race header (posting_status != posted)',
            'reference_type' => 'Invoice',
            'reference_number' => 'RACE-0001',
            'transaction_date' => now(),
            'doc_type' => 'JV',
            'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'draft',
            'total_debit' => 15.000,
            'total_credit' => 15.000,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 15.000, 'JV', $idempotencyKey);

        $result = app(PostingService::class)->post($draft);

        $this->assertSame(
            $existingTransactionId,
            // Cast: same PDO-emulated-prepares note as test_post_is_idempotent — $result's
            // Transaction, on the collision path, comes back through a re-fetch, not a fresh
            // insert.
            (int) $result->transaction->id,
            'A duplicate-key collision on the idempotency index must return the PRE-EXISTING '
                .'document, not raise a raw QueryException nor create a second row.'
        );
        $this->assertSame(
            1,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->count(),
            'Exactly one transaction row may exist for this idempotency key after the collision.'
        );
    }

    /**
     * Finding "NAN / INF slip past both the non-negative rule and the balance rule"
     * (.planning/P1-VERIFICATION-FINDINGS.json findings[]): `INF < 0` is false in PHP, so an
     * INF/INF pair could otherwise sail through step 3b (non-negative), and
     * `abs(INF - INF)` is NAN, which is never `>= tolerance` — so step 4's
     * UnbalancedDocumentException never fires either. The exact exception subclass is left
     * unpinned deliberately (the fix may reject this at the non-negative check, a new
     * is_finite() guard, or elsewhere) — what must always be true is that SOME typed
     * PostingException stops it before any write.
     */
    public function test_post_rejects_infinite_amount(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'INF amount regression pin',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: INF,
                    currency: 'KWD',
                    originalAmount: INF,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: INF,
                    currency: 'KWD',
                    originalAmount: INF,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
        );

        $transactionCountBefore = DB::table('transactions')->count();

        $this->expectException(\App\Exceptions\Accounting\PostingException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertSame(
                $transactionCountBefore,
                DB::table('transactions')->count(),
                'An INF-amount document must never write a transaction header, whatever typed '
                    .'exception rejects it.'
            );
        }
    }

    /** Same pin as above, NAN instead of INF — `NAN < 0` is false and `round(NAN, 3)` is NAN. */
    public function test_post_rejects_nan_amount(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'NAN amount regression pin',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: NAN,
                    currency: 'KWD',
                    originalAmount: NAN,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: NAN,
                    currency: 'KWD',
                    originalAmount: NAN,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
        );

        $transactionCountBefore = DB::table('transactions')->count();

        $this->expectException(\App\Exceptions\Accounting\PostingException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertSame(
                $transactionCountBefore,
                DB::table('transactions')->count(),
                'A NAN-amount document must never write a transaction header, whatever typed '
                    .'exception rejects it.'
            );
        }
    }

    /**
     * P1 FIX ROUND 4, BLOCKING #2 (.planning/P1-VERIFICATION-FINDINGS.json) — replaces the round-3
     * placeholder (test_post_does_not_crash_when_idempotency_key_collides_with_a_soft_deleted_transaction,
     * which only asserted `instanceof PostedDocument` — true even for the bug this pins). PROVEN BY
     * EXECUTION before this fix: post key K for 20.000, soft-delete that transaction, post key K
     * again for 999.000 -> the dead 20.000 document came back as a successful PostedDocument,
     * nothing was written to journal_entries, and a real document number was burned. The owner's
     * decision (see PostingService's own "P1 FIX ROUND 4" class-docblock index) was to THROW a
     * clear named exception AND make the rejection admin-visible. This test asserts all four
     * required facts: exception thrown, nothing written to the ledger, an admin record created with
     * the right fields, and the document-number serial NOT burned by the rejected attempt.
     */
    public function test_post_throws_and_records_a_rejection_when_idempotency_key_collides_with_a_soft_deleted_transaction(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $idempotencyKey = 'test:soft-deleted-collision-'.uniqid();

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $service = app(PostingService::class);
        $first = $service->post($this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 20.000, 'JV', $idempotencyKey));

        Transaction::withoutGlobalScopes()->whereKey($first->transaction->id)->delete();
        $this->assertNotNull(
            DB::table('transactions')->where('id', $first->transaction->id)->value('deleted_at'),
            'Precondition: the first transaction must actually be soft-deleted.'
        );

        $transactionCountBefore = DB::table('transactions')->count();
        $journalEntryCountBefore = DB::table('journal_entries')->count();

        // Same (company, branch, doc_type=JV, doc_year=this year) key SequenceService::next() uses
        // — captured BEFORE the rejected attempt so "not burned" is a real before/after comparison,
        // not an assumption about what the counter "should" be.
        $serialBefore = DB::table('serial_schemas')
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('doc_type', 'JV')
            ->where('doc_year', (int) now()->format('Y'))
            ->value('last_serial');

        try {
            $service->post($this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 999.000, 'JV', $idempotencyKey));
            $this->fail('Expected SupersededIdempotencyKeyException to be thrown.');
        } catch (SupersededIdempotencyKeyException $e) {
            $this->assertSame($company->id, $e->companyId);
            $this->assertSame($idempotencyKey, $e->idempotencyKey);
            $this->assertSame($first->transaction->id, $e->deadTransactionId);
            $this->assertNotNull($e->deadTransactionDeletedAt);
            $this->assertEqualsWithDelta(999.000, $e->attemptedAmount, 0.0005);
        }

        $this->assertSame(
            $transactionCountBefore,
            DB::table('transactions')->count(),
            'A rejected superseded-key attempt must never write a transaction header.'
        );
        $this->assertSame(
            $journalEntryCountBefore,
            DB::table('journal_entries')->count(),
            'A rejected superseded-key attempt must never write journal_entries lines — the dead '
                .'20.000 document must not be resurrected, and the 999.000 attempt must not post either.'
        );

        // Serial NOT burned: step 6 reserved a number for the 999.000 attempt before the collision
        // was discovered, but throwing (instead of returning) rolls the whole DB::transaction()
        // back, including that reservation.
        $serialAfter = DB::table('serial_schemas')
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('doc_type', 'JV')
            ->where('doc_year', (int) now()->format('Y'))
            ->value('last_serial');
        $this->assertSame(
            $serialBefore,
            $serialAfter,
            'A rejected superseded-key attempt must not burn a document-number serial.'
        );

        // Admin visibility: a queryable row must exist with the fields an admin needs to review
        // this rejection (company_id, key, attempted amount, the dead transaction id, timestamp,
        // resolution status) — read via the model's own 'accounting_audit' connection, proving the
        // durable-connection write actually happened (this test's own DB transaction, on the
        // DEFAULT connection, was rolled back by the exception above; if the record had been
        // written on that same connection it would already be gone by this point).
        $record = IdempotencyKeyRejection::where('idempotency_key', $idempotencyKey)->first();

        $this->assertNotNull($record, 'Expected an IdempotencyKeyRejection admin record to exist.');
        $this->assertSame($company->id, $record->company_id);
        $this->assertSame($first->transaction->id, $record->dead_transaction_id);
        $this->assertEqualsWithDelta(999.000, (float) $record->attempted_amount, 0.0005);
        $this->assertSame('open', $record->resolution_status);
        $this->assertNotNull($record->dead_transaction_deleted_at);

        // Tidy up: this table lives on a connection RefreshDatabase does not wrap/roll back (by
        // design — see IdempotencyKeyRejection's own docblock for why), so it is not auto-cleaned
        // between tests the way everything on the default connection is.
        $record->delete();
    }

    /**
     * P1 FIX ROUND 4, MEDIUM finding: a line's `currency` longer than the tightest destination
     * column (journal_entries.original_currency, varchar(3)) used to reach step 8's INSERT
     * unchecked and crash with a raw MySQL driver exception instead of a typed PostingException.
     */
    public function test_post_rejects_a_currency_code_longer_than_three_characters(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Over-long currency code regression pin',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 10.000,
                    currency: 'KUWAITI-DINAR',
                    originalAmount: 10.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 10.000,
                    currency: 'KUWAITI-DINAR',
                    originalAmount: 10.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
        );

        $transactionCountBefore = DB::table('transactions')->count();

        try {
            app(PostingService::class)->post($draft);
            $this->fail('Expected InvalidCurrencyCodeException to be thrown.');
        } catch (InvalidCurrencyCodeException $e) {
            $this->assertSame('KUWAITI-DINAR', $e->currency);
            $this->assertSame(3, $e->maxLength);
        }

        $this->assertSame(
            $transactionCountBefore,
            DB::table('transactions')->count(),
            'An over-long currency code must never write a transaction header, and must be caught '
                .'as a typed exception rather than a raw driver "Data too long" error.'
        );
    }

    /**
     * P1 FIX ROUND 4, MEDIUM finding (docblock correction, see repost()'s own "RETRY LIMITATION"
     * docblock section): pins the ACTUAL, now-documented behaviour — a retry that reloads $old
     * fresh from the database after a first successful repost() gets InvalidRepostSourceException,
     * not the first attempt's replacement document. This is a regression pin for the corrected
     * documentation, not new behaviour: if a future change makes repost() genuinely retry-safe for
     * this shape, this test's expectation should change deliberately alongside it.
     */
    public function test_repost_retry_with_a_reloaded_old_transaction_throws_instead_of_returning_the_prior_replacement(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $idempotencyKey = 'test:repost-retry-'.uniqid();
        $service = app(PostingService::class);

        $original = $service->post($this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 30.000, 'JV', $idempotencyKey));

        $replacementDraft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 45.000, 'JV', $idempotencyKey);

        // First attempt succeeds.
        $service->repost($original->transaction, $replacementDraft, now(), null);

        // Retry, the realistic shape: reload $old fresh (a new HTTP request / re-run job would do
        // exactly this, not reuse the first attempt's in-PHP-memory object).
        $reloadedOld = Transaction::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($original->transaction->id);

        $this->expectException(InvalidRepostSourceException::class);
        $service->repost($reloadedOld, $replacementDraft, now(), null);
    }

    /**
     * Finding "reverse() cannot handle the legacy corruption P3's repair plan points it at" /
     * "withoutGlobalScopes() also removes SoftDeletingScope" (.planning/P1-VERIFICATION-FINDINGS.json
     * findings[]), the reverse()-original-lines half — the more severe of the two soft-delete
     * scenarios the finding names: "a legacy document whose one leg was soft-deleted by the
     * delete-and-recreate edit path (R4.1, the exact corruption P3 must repair) gets that dead
     * leg resurrected into the reversal, over-reversing the live ledger to the fils." This test
     * deliberately does NOT use $this->makeCompany() (which auto-tracks the company for
     * AccountingTestCase's tearDown invariant check): soft-deleting one leg of a live, balanced
     * document is an intentional corruption of THAT original document for the duration of this
     * test, and would otherwise trip assertLedgerBalanced() in tearDown for the wrong reason
     * before this test's own assertions even run.
     *
     * RECONCILE-ROUND NOTE: as originally written this test asserted that reverse() would
     * succeed and produce a BALANCED 2-line reversal after dropping the soft-deleted 40 credit
     * leg from a 100-debit/60-credit/40-credit original. That is not just unasserted — it is
     * mathematically impossible: for any originally-balanced document (Σdebit == Σcredit),
     * dropping exactly one nonzero live line before swapping sides necessarily leaves the
     * remaining lines' totals apart by precisely that dropped line's amount (here, 40), on
     * either side depending on which side it came from. There is no valid PostingService
     * implementation that both (a) genuinely excludes the dead leg per this finding, and
     * (b) produces an internally-balanced reversal from the shape below — doing (b) requires
     * silently resurrecting the dead leg instead, which is precisely the bug this test exists to
     * catch. This was flagged during the fix round (PostingService fixer's own report) as a
     * likely-buggy acceptance test; reconciled here to assert the only correct, decidable
     * outcome: PostingService::post()'s own step-4 balance check throws UnbalancedDocumentException
     * on the swapped (60 debit / 100 credit) draft — proving the dead leg was truly excluded
     * (if the bug were still present, all three original lines would be read, the swap would
     * balance at 100/100, and reverse() would succeed instead of throwing here) — and that no
     * reversal document is left behind. Correcting a document in this corrupted shape is P3's
     * dedicated repair path, not plain reverse().
     */
    public function test_reverse_excludes_a_soft_deleted_original_line(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccountLive = Account::factory()->create(['company_id' => $company->id]);
        $creditAccountDeleted = Account::factory()->create(['company_id' => $company->id]);

        // A balanced three-line draft: one debit split across two credits — one of which is
        // soft-deleted below before reverse() runs, simulating the R4.1 delete-and-recreate edit
        // corruption on an already-posted document.
        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Soft-deleted-line reversal regression pin',
            lines: [
                new LineDraft(purposeCode: '', accountId: $debitAccount->id, side: 'debit', amount: 100.000, currency: 'KWD', originalAmount: 100.000, exchangeRate: 1.0, transactionType: 'TEST_DEBIT'),
                new LineDraft(purposeCode: '', accountId: $creditAccountLive->id, side: 'credit', amount: 60.000, currency: 'KWD', originalAmount: 60.000, exchangeRate: 1.0, transactionType: 'TEST_CREDIT'),
                new LineDraft(purposeCode: '', accountId: $creditAccountDeleted->id, side: 'credit', amount: 40.000, currency: 'KWD', originalAmount: 40.000, exchangeRate: 1.0, transactionType: 'TEST_CREDIT'),
            ],
            idempotencyKey: 'test:reverse-softdel-line-'.uniqid(),
        );

        $service = app(PostingService::class);
        $posted = $service->post($draft);

        // Simulate R4.1: one leg was soft-deleted by the legacy delete-and-recreate edit path.
        // This deliberately leaves the ORIGINAL transaction's live lines unbalanced (100 debit vs
        // 60 credit) — exactly the corruption shape reverse() must be robust against; it is not
        // this test's concern to keep the original balanced, only to prove the dead leg is never
        // resurrected into the reversal.
        DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->where('account_id', $creditAccountDeleted->id)
            ->update(['deleted_at' => now()]);

        $this->expectException(UnbalancedDocumentException::class);

        try {
            $service->reverse($posted->transaction, now(), null);
        } finally {
            $this->assertSame(
                0,
                DB::table('transactions')->where('reversal_of_transaction_id', $posted->transaction->id)->count(),
                'A reverse() that correctly refuses to resurrect the soft-deleted leg must not '
                    .'leave any reversal document behind.'
            );
        }
    }

    /**
     * Non-leaf rejection must not be satisfiable by relying on is_group alone. Pins PIPELINE step
     * 3d (R1/BUG-C2) explicitly against a "fix" that checks only is_group: an account with a real
     * child must be rejected EVEN WHEN its own is_group flag is false/stale — the leaf rule is
     * `children()->doesntExist() OR is_group`, an OR of two independent signals, not is_group in
     * isolation. This is the explicit, intent-documenting sibling of
     * test_post_rejects_non_leaf_account above (which already exercises this path via
     * AccountFactory's default is_group=false, but without calling it out as deliberate).
     */
    public function test_post_rejects_account_with_children_even_when_is_group_flag_is_false(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $parentAccount = Account::factory()->create([
            'company_id' => $company->id,
            'is_group' => false, // deliberately stale/wrong — see docblock
        ]);
        Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $parentAccount->id,
        ]);

        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->balancedDraft($company, $branch, $parentAccount, $creditAccount, 15.000);

        $this->expectException(NonLeafAccountException::class);

        app(PostingService::class)->post($draft);
    }
}
