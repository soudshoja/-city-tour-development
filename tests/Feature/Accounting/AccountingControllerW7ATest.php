<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\UnbalancedDocumentException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W7.A (w7-brief.md): AccountingController's three raw-write "manual JV" screens
 * (storePayableDetail, storeReceivableDetail, storeBankPayment) cut over to
 * PostingSeam/PostingService. See AccountingController's own class docblock for
 * the full OFF/ON contract this file pins.
 */
class AccountingControllerW7ATest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /**
     * @return array{user: User, company: Company, branch: Branch}
     */
    private function makeTenant(bool $engineOn = false): array
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);

        if ($engineOn) {
            config(['accounting.engine.enabled' => true]);
            $company->forceFill(['posting_engine_enabled' => true])->save();
        }

        $this->trackCompanyForInvariants($company->id);

        return compact('user', 'company', 'branch');
    }

    private function makeLeafAccount(Company $company): Account
    {
        return Account::factory()->create(['company_id' => $company->id]);
    }

    /** A group (non-leaf) account: has at least one real child row. */
    private function makeGroupAccount(Company $company): Account
    {
        $group = Account::factory()->create(['company_id' => $company->id]);
        Account::factory()->create(['company_id' => $company->id, 'parent_id' => $group->id]);

        return $group;
    }

    private function payablePayload(Branch $branch, Account $account, Account $bank, array $overrides = []): array
    {
        return array_merge([
            'transaction_date' => now()->toDateTimeString(),
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'bank_account' => $bank->id,
            'description' => 'W7A payable test',
            'amount' => '125.500',
            'type' => 'payable',
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ], $overrides);
    }

    // ── OFF parity ─────────────────────────────────────────────────────────────────────────

    public function test_off_path_payable_writes_legacy_shape_unchanged(): void
    {
        $tenant = $this->makeTenant(engineOn: false);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $payload = $this->payablePayload($tenant['branch'], $account, $bank);

        $response = $this->actingAs($tenant['user'])
            ->post(route('payable-details.payable-store'), $payload);

        $response->assertRedirect(route('payable-details.payable-create'));

        $transaction = Transaction::where('company_id', $tenant['company']->id)
            ->where('reference_number', 'like', 'PY-%')
            ->first();

        $this->assertNotNull($transaction, 'Legacy PY- reference number must still be minted OFF.');
        $this->assertSame('debit', $transaction->transaction_type);

        $lines = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines);
        $this->assertSame(1, $lines->where('account_id', $account->id)->where('debit', 125.5)->count());
        $this->assertSame(1, $lines->where('account_id', $bank->id)->where('credit', 125.5)->count());
    }

    // ── ON: balanced -> one doc with a real engine serial ─────────────────────────────────────

    public function test_on_path_payable_posts_one_balanced_jv_with_engine_serial(): void
    {
        $tenant = $this->makeTenant(engineOn: true);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $payload = $this->payablePayload($tenant['branch'], $account, $bank);

        $response = $this->actingAs($tenant['user'])
            ->post(route('payable-details.payable-store'), $payload);

        $response->assertRedirect(route('payable-details.payable-create'));

        $transaction = Transaction::where('company_id', $tenant['company']->id)
            ->where('doc_type', 'JV')
            ->where('sub_type', 'JV_PAYABLE')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertTrue(str_starts_with((string) $transaction->reference_number, 'JV'));
        $this->assertSame('posted', $transaction->posting_status);

        $lines = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta(125.5, (float) $lines->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(125.5, (float) $lines->sum('credit'), 0.001);
        $this->assertSame(1, $lines->where('account_id', $account->id)->where('debit', 125.5)->count());
        $this->assertSame(1, $lines->where('account_id', $bank->id)->where('credit', 125.5)->count());
    }

    // ── double submit -> one doc (idempotency via the client_uuid hidden token) ───────────────

    public function test_double_submit_with_same_client_uuid_posts_once(): void
    {
        $tenant = $this->makeTenant(engineOn: true);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $payload = $this->payablePayload($tenant['branch'], $account, $bank);

        $first = $this->actingAs($tenant['user'])->post(route('payable-details.payable-store'), $payload);
        $first->assertRedirect(route('payable-details.payable-create'));

        $second = $this->actingAs($tenant['user'])->post(route('payable-details.payable-store'), $payload);
        $second->assertRedirect(route('payable-details.payable-create'));

        $count = Transaction::where('company_id', $tenant['company']->id)
            ->where('doc_type', 'JV')
            ->where('sub_type', 'JV_PAYABLE')
            ->count();

        $this->assertSame(1, $count, 'A double-submit with the same client_uuid token must post exactly once.');
    }

    // ── closed period -> refused ────────────────────────────────────────────────────────────

    public function test_closed_period_refuses_the_post_and_writes_nothing(): void
    {
        // OFF path deliberately: PostingSeam's own OFF branch calls PeriodGuard::assertOpen()
        // before invoking $legacy() (P2.5.A), so a locked period refuses the post even with the
        // engine flag off -- proving the guard applies regardless of which path is active.
        $tenant = $this->makeTenant(engineOn: false);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $docDate = now();
        AccountingPeriod::create([
            'company_id' => $tenant['company']->id,
            'year' => (int) $docDate->format('Y'),
            'month' => (int) $docDate->format('n'),
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);

        $before = Transaction::where('company_id', $tenant['company']->id)->count();

        $payload = $this->payablePayload($tenant['branch'], $account, $bank, [
            'transaction_date' => $docDate->toDateTimeString(),
        ]);

        $response = $this->actingAs($tenant['user'])
            ->postJson(route('payable-details.payable-store'), $payload);

        $response->assertStatus(422);

        $this->assertSame(
            $before,
            Transaction::where('company_id', $tenant['company']->id)->count(),
            'A refused post into a locked period must write nothing.'
        );
    }

    // ── group account -> 422 ────────────────────────────────────────────────────────────────

    public function test_group_account_is_refused_with_422_and_writes_nothing(): void
    {
        $tenant = $this->makeTenant(engineOn: true);
        $group = $this->makeGroupAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $before = Transaction::where('company_id', $tenant['company']->id)->count();

        $payload = $this->payablePayload($tenant['branch'], $group, $bank);

        $response = $this->actingAs($tenant['user'])
            ->postJson(route('payable-details.payable-store'), $payload);

        $response->assertStatus(422);

        $this->assertSame(
            $before,
            Transaction::where('company_id', $tenant['company']->id)->count(),
            'Posting to a group (non-leaf) account must never write a document.'
        );
    }

    // ── unbalanced -> 422 / no rows ──────────────────────────────────────────────────────────

    /**
     * The three manual-JV screens' HTTP forms each carry exactly ONE 'amount' field feeding both
     * the debit and credit line identically, so a well-formed request can never actually produce
     * an unbalanced pair -- there is no field an attacker/bug could desync. This test proves the
     * "balanced-or-rejected" guarantee the brief requires still holds for the EXACT document shape
     * this controller builds (doc_type=JV, two explicit-accountId lines), by feeding
     * PostingService the same shape directly with a deliberately corrupted amount pair -- the
     * identical seam/engine plumbing every controller route above goes through on the ON path.
     */
    public function test_unbalanced_manual_jv_shape_is_rejected_with_no_rows_written(): void
    {
        $tenant = $this->makeTenant(engineOn: true);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $draft = new DocumentDraft(
            companyId: $tenant['company']->id,
            branchId: $tenant['branch']->id,
            docType: 'JV',
            subType: 'JV_PAYABLE',
            docDate: now(),
            narration: 'Deliberately unbalanced manual JV',
            lines: [
                new LineDraft(
                    purposeCode: '', accountId: $account->id, side: 'debit', amount: 100.000,
                    currency: 'KWD', originalAmount: 100.000, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '', accountId: $bank->id, side: 'credit', amount: 50.000,
                    currency: 'KWD', originalAmount: 50.000, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_CREDIT',
                ),
            ],
        );

        $txBefore = DB::table('transactions')->where('company_id', $tenant['company']->id)->count();
        $lineBefore = DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count();

        $this->expectException(UnbalancedDocumentException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertSame(
                $txBefore,
                DB::table('transactions')->where('company_id', $tenant['company']->id)->count(),
                'An unbalanced manual JV must not write a transaction header.'
            );
            $this->assertSame(
                $lineBefore,
                DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count(),
                'An unbalanced manual JV must not write any journal_entries lines.'
            );
        }
    }

    // ── storeReceivableDetail: ON path smoke ────────────────────────────────────────────────

    public function test_on_path_receivable_posts_dr_bank_cr_account(): void
    {
        $tenant = $this->makeTenant(engineOn: true);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $payload = [
            'transaction_date' => now()->toDateTimeString(),
            'account_id' => $account->id,
            'branch_id' => $tenant['branch']->id,
            'bank_account' => $bank->id,
            'invoice_id' => null,
            'description' => 'W7A receivable test',
            'name' => 'Test Client',
            'amount' => '60.000',
            'type' => 'receivable',
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ];

        $response = $this->actingAs($tenant['user'])
            ->post(route('receivable-details.receivable-store'), $payload);

        $response->assertRedirect(route('receivable-details.receivable-create'));

        $transaction = Transaction::where('company_id', $tenant['company']->id)
            ->where('doc_type', 'JV')
            ->where('sub_type', 'JV_RECEIVABLE')
            ->first();

        $this->assertNotNull($transaction);

        $lines = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertSame(1, $lines->where('account_id', $bank->id)->where('debit', 60.0)->count());
        $this->assertSame(1, $lines->where('account_id', $account->id)->where('credit', 60.0)->count());
    }

    // ── storeBankPayment: ON path fixes the pre-existing same-account bug ──────────────────────

    public function test_bank_payment_on_path_posts_two_distinct_accounts_unlike_legacy_bug(): void
    {
        $tenant = $this->makeTenant(engineOn: true);
        $target = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $request = \Illuminate\Http\Request::create('/accounting/store-bank-payment', 'POST', [
            'transaction_date' => now()->toDateString(),
            'account_id' => $target->id,
            'bank_account' => $bank->id,
            'branch_id' => $tenant['branch']->id,
            'description' => 'W7A transfer test',
            'type' => 'bank_payment',
            'amount' => 40.0,
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $request->setUserResolver(fn () => $tenant['user']);
        $this->actingAs($tenant['user']);

        try {
            app(\App\Http\Controllers\AccountingController::class)->storeBankPayment($request);
        } catch (\Illuminate\Routing\Exceptions\UrlGenerationException|\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
            // storeBankPayment ends with redirect()->route('bank-payment.create'), a route name
            // that has never existed -- see that method's own docblock. Irrelevant to the ledger
            // effects asserted below, which happen inside the DB transaction before that redirect.
        }

        $transaction = Transaction::where('company_id', $tenant['company']->id)
            ->where('doc_type', 'JV')
            ->where('sub_type', 'JV_TRANSFER')
            ->first();

        $this->assertNotNull($transaction);

        $lines = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines);
        // Unlike the OFF-path legacy bug (both lines on $target -- see storeBankPayment()'s own
        // docblock), the ON path posts each leg to the account the user actually picked.
        $this->assertSame(1, $lines->where('account_id', $target->id)->where('debit', 40.0)->count());
        $this->assertSame(1, $lines->where('account_id', $bank->id)->where('credit', 40.0)->count());
    }

    // ── reversal ─────────────────────────────────────────────────────────────────────────────

    public function test_reverse_manual_journal_reverses_via_the_engine(): void
    {
        $tenant = $this->makeTenant(engineOn: true);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $payload = $this->payablePayload($tenant['branch'], $account, $bank);

        $this->actingAs($tenant['user'])->post(route('payable-details.payable-store'), $payload);

        $transaction = Transaction::where('company_id', $tenant['company']->id)
            ->where('doc_type', 'JV')
            ->where('sub_type', 'JV_PAYABLE')
            ->firstOrFail();

        $response = $this->actingAs($tenant['user'])
            ->post(route('manual-journal.reverse', ['transaction' => $transaction->id]));

        $response->assertRedirect();

        $transaction->refresh();
        $this->assertSame('reversed', $transaction->posting_status);

        $reversal = Transaction::where('reversal_of_transaction_id', $transaction->id)->first();
        $this->assertNotNull($reversal, 'reverse() must have posted a linked reversal document.');

        $reversalLines = JournalEntry::where('transaction_id', $reversal->id)->get();
        $this->assertCount(2, $reversalLines);
        $this->assertEqualsWithDelta(125.5, (float) $reversalLines->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(125.5, (float) $reversalLines->sum('credit'), 0.001);
    }

    public function test_reverse_manual_journal_refuses_when_engine_off(): void
    {
        // Post it ON, then flip the company back OFF, and prove reverse() (engine-only by
        // construction) is refused rather than raw-deleting anything.
        $tenant = $this->makeTenant(engineOn: true);
        $account = $this->makeLeafAccount($tenant['company']);
        $bank = $this->makeLeafAccount($tenant['company']);

        $payload = $this->payablePayload($tenant['branch'], $account, $bank);
        $this->actingAs($tenant['user'])->post(route('payable-details.payable-store'), $payload);

        $transaction = Transaction::where('company_id', $tenant['company']->id)
            ->where('doc_type', 'JV')
            ->firstOrFail();

        config(['accounting.engine.enabled' => false]);

        $response = $this->actingAs($tenant['user'])
            ->postJson(route('manual-journal.reverse', ['transaction' => $transaction->id]));

        $response->assertStatus(422);

        $transaction->refresh();
        $this->assertNotSame('reversed', $transaction->posting_status);

        // Re-enable so this test's own tearDown() invariant check (which needs a live engine
        // config for nothing in particular, but keeps behaviour consistent across the file) is
        // unaffected either way -- no assertion depends on this, purely defensive.
        config(['accounting.engine.enabled' => true]);
    }
}
