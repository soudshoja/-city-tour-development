<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\PeriodLockedException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.A (p2_5-brief.md §P2.5.A): "confirm the seam legacy branch also invokes the guard (add the
 * single call if missing)" — proves PeriodGuard is actually reached on BOTH PostingSeam paths:
 *
 *   - OFF (legacy): PostingSeam::post()'s legacy branch now calls PeriodGuard::assertOpen() before
 *     invoking $legacy() — this suite's main addition, since that call did not exist before P2.5.A.
 *   - ON (engine): PostingService::post()'s own step 5 already routed through the guard before
 *     this wave; P2.5.A only made the guard's body real. Covered here too so the FULL matrix (open/
 *     soft/locked x allowLocked) is proven on both paths in one place, per the brief's own test
 *     list ("on both engine ON and OFF").
 *
 * Docs used in March 2026 throughout (a month with no accounting_periods row unless a test
 * creates one — see PeriodGuard's own "no row = open" docblock), so every fixture that does not
 * explicitly create a period row exercises the "not yet initialised" no-op case.
 */
class PostingSeamPeriodGuardTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /** @return LineDraft[] */
    private function twoLines(Account $debitAccount, Account $creditAccount, float $amount): array
    {
        return [
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
        ];
    }

    private function draft(
        Company $company,
        Branch $branch,
        Account $debitAccount,
        Account $creditAccount,
        \DateTimeInterface $docDate,
        ?bool $allowLockedPeriods = false,
        ?int $userId = null,
        ?string $overrideReason = null,
    ): DocumentDraft {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: $docDate,
            narration: 'PostingSeamPeriodGuardTest fixture',
            lines: $this->twoLines($debitAccount, $creditAccount, 25.000),
            idempotencyKey: 'test:period:'.uniqid(),
            userId: $userId,
            allowLockedPeriods: $allowLockedPeriods,
            overrideReason: $overrideReason,
        );
    }

    private function march(): Carbon
    {
        return Carbon::create(2026, 3, 15, 9, 0, 0);
    }

    // ── OFF / legacy branch ─────────────────────────────────────────────────────────────────────

    public function test_legacy_branch_refuses_to_run_legacy_closure_when_period_is_locked(): void
    {
        config(['accounting.engine.enabled' => false]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);

        $draft = $this->draft($company, $branch, $debitAccount, $creditAccount, $this->march());

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $this->expectException(PeriodLockedException::class);

        try {
            app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.period-locked-off');
        } finally {
            $this->assertFalse($legacyCalled, 'The legacy closure must never run for a locked period.');
            $this->assertSame(0, DB::table('transactions')->where('company_id', $company->id)->count());
        }
    }

    public function test_legacy_branch_refuses_when_period_is_soft_closed_without_permission_or_reason(): void
    {
        config(['accounting.engine.enabled' => false]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_SOFT_CLOSED,
        ]);

        $draft = $this->draft($company, $branch, $debitAccount, $creditAccount, $this->march());

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $this->expectException(PeriodLockedException::class);

        try {
            app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.period-soft-off');
        } finally {
            $this->assertFalse($legacyCalled);
        }
    }

    public function test_legacy_branch_runs_when_period_is_soft_closed_with_permission_and_reason(): void
    {
        config(['accounting.engine.enabled' => false]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_SOFT_CLOSED,
        ]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $draft = $this->draft(
            $company, $branch, $debitAccount, $creditAccount, $this->march(),
            userId: $admin->id, overrideReason: 'late audit adjustment',
        );

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.period-soft-override-off');

        $this->assertTrue($legacyCalled, 'A permitted soft_closed override must still run the legacy closure.');
        $this->assertSame('legacy-ran', $result);
    }

    public function test_legacy_branch_runs_normally_when_no_period_row_exists_yet(): void
    {
        config(['accounting.engine.enabled' => false]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        // No AccountingPeriod row for this company at all.
        $draft = $this->draft($company, $branch, $debitAccount, $creditAccount, $this->march());

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.period-no-row-off');

        $this->assertTrue($legacyCalled, 'A company with no accounting_periods rows yet must not be blocked.');
        $this->assertSame('legacy-ran', $result);
    }

    // ── ON / engine branch ──────────────────────────────────────────────────────────────────────

    /**
     * P2.5.B CORRECTION (p2_5-brief.md §P2.5.B; period-lock-design.md §8.1 — the three-date model,
     * which explicitly "supersedes §4's docDate framing" without rewriting it, the same append-only
     * convention doc 22 §14/§15 use). Prior to this wave, a locked period with no
     * `$allowLockedPeriods` bypass always threw `PeriodLockedException` and wrote nothing — this
     * method used to pin exactly that (assert 0 rows written, expect the throw). That is no longer
     * what a NORMAL (no explicit `$draft->postingDate`, no override) post does: `PostingService::
     * post()`'s step 5 now catches that internal throw and silently redirects the document's
     * `posting_date` to the earliest period this company has open on or after its own date —
     * `transaction_date` (the document's own date) is NEVER altered either way. The owner example
     * this exists for (period-lock-design.md §8.1): "A Feb 10 invoice entered March 5, after Feb
     * closed... posting_date becomes March... nothing reopens." Renamed and rewritten accordingly;
     * `test_engine_branch_posts_when_period_is_locked_but_allow_locked_periods_is_set` and
     * `test_engine_branch_posts_when_period_is_open` below are UNCHANGED by this wave — the shift
     * only ever fires on the "no valid override" branch that used to end in a throw.
     */
    public function test_engine_branch_shifts_posting_date_and_posts_when_period_is_locked_with_no_override(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
        // April is left with NO row -- "no row = open" (PeriodGuard's own documented convention),
        // so this is where the March-locked document's posting_date shifts to.

        $draft = $this->draft($company, $branch, $debitAccount, $creditAccount, $this->march());

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.period-locked-shift-on');

        $this->assertInstanceOf(PostedDocument::class, $result);
        $this->assertFalse($legacyCalled, 'The engine path must never fall back to legacy.');

        $transaction = DB::table('transactions')->where('company_id', $company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertSame('2026-03-15', Carbon::parse($transaction->transaction_date)->toDateString());
        $this->assertSame('2026-04-01', Carbon::parse($transaction->posting_date)->toDateString());

        $journalEntries = DB::table('journal_entries')->where('company_id', $company->id)->get();
        $this->assertCount(2, $journalEntries);
        foreach ($journalEntries as $line) {
            $this->assertSame('2026-04-01', Carbon::parse($line->posting_date)->toDateString());
        }

        Log::shouldHaveReceived('info')->once()->with(
            'accounting.posting_date_shifted',
            Mockery::on(fn (array $ctx) => $ctx['company_id'] === $company->id
                && $ctx['requested_posting_date'] === '2026-03-15'
                && $ctx['resolved_posting_date'] === '2026-04-01')
        );
    }

    /**
     * `$draft->postingDate` changes WHICH date gets resolved (in place of `$docDate`), not WHETHER
     * the open/shift/override rule applies to it -- the same rule governs an explicit date as a
     * defaulted one. An explicit postingDate landing on a locked period, with no valid override,
     * shifts exactly like a defaulted one does (the sibling test above): `$docDate` (May, itself
     * open) stays untouched and unused for bucketing; `posting_date` becomes April, not March.
     */
    public function test_engine_branch_shifts_an_explicit_posting_date_the_same_way_as_a_defaulted_one(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
        // April: no row -> open (the shift target, same convention as the sibling test above).

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: Carbon::create(2026, 5, 1, 9, 0, 0), // the document's OWN date is open (May)
            narration: 'PostingSeamPeriodGuardTest explicit-postingDate fixture',
            lines: $this->twoLines($debitAccount, $creditAccount, 10.000),
            idempotencyKey: 'test:period:'.uniqid(),
            postingDate: $this->march(), // ...but the caller explicitly asks to resolve March instead.
        );

        $result = app(PostingSeam::class)->post($draft, fn () => 'legacy-should-not-run', 'test.feeder.period-locked-explicit-on');

        $this->assertInstanceOf(PostedDocument::class, $result);

        $transaction = DB::table('transactions')->where('company_id', $company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertSame('2026-05-01', Carbon::parse($transaction->transaction_date)->toDateString());
        $this->assertSame('2026-04-01', Carbon::parse($transaction->posting_date)->toDateString());
    }

    /**
     * The DISTINGUISHING behaviour explicit `$draft->postingDate` actually exists for (see that
     * field's own P2.5.B docblock note on DocumentDraft): paired with a VALID soft_closed override
     * (permission + reason), `assertOpen()` never throws in the first place, so no shift is ever
     * attempted -- the document lands exactly on the explicit date the caller asked for, even
     * though it differs from `$docDate`. This is the "post this correction dated exactly into this
     * soft_closed period" shape a future close-screen action would use.
     */
    public function test_engine_branch_posts_on_the_explicit_posting_date_unshifted_when_a_valid_override_is_present(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_SOFT_CLOSED,
        ]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: Carbon::create(2026, 5, 1, 9, 0, 0), // May -- itself open
            narration: 'PostingSeamPeriodGuardTest explicit-postingDate-with-override fixture',
            lines: $this->twoLines($debitAccount, $creditAccount, 10.000),
            idempotencyKey: 'test:period:'.uniqid(),
            userId: $admin->id,
            postingDate: $this->march(), // explicitly targets the soft_closed month...
            overrideReason: 'late audit adjustment for the exact period it belongs to',
        );

        $result = app(PostingSeam::class)->post($draft, fn () => 'legacy-should-not-run', 'test.feeder.period-soft-explicit-on');

        $this->assertInstanceOf(PostedDocument::class, $result);

        $transaction = DB::table('transactions')->where('company_id', $company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertSame('2026-05-01', Carbon::parse($transaction->transaction_date)->toDateString());
        // ...and lands there UNSHIFTED, because the valid override meant assertOpen() never threw.
        $this->assertSame('2026-03-15', Carbon::parse($transaction->posting_date)->toDateString());
    }

    public function test_engine_branch_posts_when_period_is_locked_but_allow_locked_periods_is_set(): void
    {
        // Simulates the year-end close job's reserved bypass (traps: never exposed to a controller).
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);

        $draft = $this->draft($company, $branch, $debitAccount, $creditAccount, $this->march(), allowLockedPeriods: true);

        $result = app(PostingSeam::class)->post($draft, fn () => 'legacy-should-not-run', 'test.feeder.period-locked-allow-on');

        $this->assertInstanceOf(PostedDocument::class, $result);
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
    }

    public function test_engine_branch_posts_when_period_is_open(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        AccountingPeriod::create([
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);

        $draft = $this->draft($company, $branch, $debitAccount, $creditAccount, $this->march());

        $result = app(PostingSeam::class)->post($draft, fn () => 'legacy-should-not-run', 'test.feeder.period-open-on');

        $this->assertInstanceOf(PostedDocument::class, $result);
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
    }
}
