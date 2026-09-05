<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\CrossCurrencyApplyException;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\ApplyFxInput;
use App\Services\Accounting\CreditApplicationInput;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RealisedFxService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T1 — ADVERSARIAL VERIFIER's own tests (not the builder's). These exist to
 * probe cases the builder's own census does not discriminate: the real intra-event rounding
 * policy, the sub-fils boundary, partial applies, exact reversal restoration, re-apply after
 * reversal, legacy-shaped rows, cross-tenant scoping, and the repost/`payment_id` linkage.
 */
class RealisedFxAdversarialTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    private array $tenant;
    private int $companyId;
    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);
        $this->tenant = $this->createTenant();
        $this->companyId = $this->tenant['company']->id;
        $this->clientId = $this->tenant['client']->id;

        CoaSeeder::run($this->companyId);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        $this->trackCompanyForInvariants($this->companyId);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────────────────────────────

    private function postSourceLine(
        string $side,
        float $originalAmount,
        string $currency,
        float $rate,
        string $key,
        ?int $companyId = null,
        ?int $paymentId = null,
        ?float $forceBaseAmount = null,
    ): int {
        $companyId ??= $this->companyId;
        $base = (string) config('accounting.engine.base_currency');
        $baseAmount = $forceBaseAmount ?? round($originalAmount * $rate, 3);
        $other = $side === 'debit' ? 'credit' : 'debit';

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: null,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Adversarial source fixture',
            lines: [
                new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE',
                    accountId: null,
                    side: $side,
                    amount: $baseAmount,
                    currency: $currency,
                    originalAmount: $originalAmount,
                    exchangeRate: $rate,
                    transactionType: 'FIXTURE',
                    partyAccountRef: $this->clientId,
                ),
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND',
                    accountId: null,
                    side: $other,
                    amount: $baseAmount,
                    currency: $base,
                    originalAmount: $baseAmount,
                    exchangeRate: 1.0,
                    transactionType: 'FIXTURE',
                ),
            ],
            idempotencyKey: 'adv-source:'.$key,
            paymentId: $paymentId,
        );

        $posted = app(PostingService::class)->post($draft);

        return (int) JournalEntry::where('transaction_id', $posted->transaction->id)
            ->where('account_id', app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $companyId)->id)
            ->value('id');
    }

    private function postAppliedLine(
        float $originalAmount,
        string $currency,
        float $rate,
        string $key,
        ?int $companyId = null,
        ?int $invoiceId = null,
    ): int {
        $companyId ??= $this->companyId;
        $base = (string) config('accounting.engine.base_currency');
        $baseAmount = round($originalAmount * $rate, 3);

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: null,
            docType: 'INV',
            subType: null,
            docDate: now(),
            narration: 'Adversarial applied fixture',
            lines: [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL',
                    accountId: null,
                    side: 'debit',
                    amount: $baseAmount,
                    currency: $currency,
                    originalAmount: $originalAmount,
                    exchangeRate: $rate,
                    transactionType: 'FIXTURE',
                    partyAccountRef: $this->clientId,
                    invoiceId: $invoiceId,
                ),
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND',
                    accountId: null,
                    side: 'credit',
                    amount: $baseAmount,
                    currency: $base,
                    originalAmount: $baseAmount,
                    exchangeRate: 1.0,
                    transactionType: 'FIXTURE',
                    invoiceId: $invoiceId,
                ),
            ],
            idempotencyKey: 'adv-applied:'.$key,
            invoiceId: $invoiceId,
        );

        $posted = app(PostingService::class)->post($draft);

        return (int) JournalEntry::where('transaction_id', $posted->transaction->id)
            ->where('account_id', app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $companyId)->id)
            ->value('id');
    }

    private function input(int $sourceLineId, int $appliedLineId, float $amount, int $id, ?int $companyId = null): ApplyFxInput
    {
        return new ApplyFxInput(
            companyId: $companyId ?? $this->companyId,
            branchId: null,
            sourceLineId: $sourceLineId,
            appliedLineId: $appliedLineId,
            appliedFcAmount: $amount,
            idSource: 'pa',
            id: $id,
            docDate: now(),
        );
    }

    private function partyAccountId(): int
    {
        return app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $this->companyId)->id;
    }

    /** Net (debit - credit) movement on an account across every live posted line. */
    private function netMovement(int $accountId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()
            ->whereNull('journal_entries.deleted_at')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->where('transactions.company_id', $this->companyId)
            ->whereNull('transactions.deleted_at')
            ->where('journal_entries.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(journal_entries.debit),0) - COALESCE(SUM(journal_entries.credit),0) AS net')
            ->value('net');

        return round((float) $rows, 3);
    }

    // ── 1. Rounding policy (discriminating oracle) ──────────────────────────────────────────

    /**
     * The builder's own `test_two_applications_round_independently_before_summing` is a
     * tautology (it asserts A+B == A+B after already asserting each half) AND its expected value
     * uses `round(a*rs,3) - round(a*rt,3)` — a DIFFERENT formula from the implementation's
     * `round(a*rs - a*rt, 3)`, which happens to agree at the values chosen. This test picks
     * values where the two formulas DISAGREE, pinning the policy the code actually implements:
     * subtract the two EXACT products, round ONCE (the plan's "Σ before round", at line level).
     *
     * a=100, r_s=0.100004, r_t=0.050006:
     *   exact:        100*0.100004 - 100*0.050006 = 10.0004 - 5.0006 = 4.9998 -> round -> 5.000
     *   round-first:  round(10.0004,3) - round(5.0006,3) = 10.000 - 5.001   = 4.999
     */
    public function test_difference_is_rounded_once_after_subtraction_not_per_product(): void
    {
        $source = $this->postSourceLine('debit', 100.0, 'USD', 0.100004, 'round-policy');
        $applied = $this->postAppliedLine(100.0, 'USD', 0.050006, 'round-policy');

        $draft = app(RealisedFxService::class)->compute($this->input($source, $applied, 100.0, 90001));

        $this->assertNotNull($draft);
        $this->assertSame(
            5.000,
            round($draft->amount, 3),
            'policy must be: subtract exact products, round once (5.000) — not round each product first (4.999)'
        );
    }

    // ── 2. Sub-fils boundary ────────────────────────────────────────────────────────────────

    public function test_difference_below_half_a_fils_posts_nothing_at_all(): void
    {
        // a=1, r_s-r_t = 0.0001 -> D = 0.0001 -> round(.,3) = 0.000 -> below epsilon.
        $source = $this->postSourceLine('credit', 1.0, 'USD', 0.300100, 'tiny');
        $applied = $this->postAppliedLine(1.0, 'USD', 0.300000, 'tiny');

        $service = app(RealisedFxService::class);
        $input = $this->input($source, $applied, 1.0, 90002);

        $this->assertNull($service->compute($input), 'a sub-half-fils difference must produce no draft');
        $this->assertNull($service->postForApply($input), 'and no document');
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $this->companyId)->where('doc_type', 'FXR')->count(),
            'no FXR document, and therefore no reserved document number, may exist'
        );
    }

    /**
     * The smallest REPRESENTABLE difference (one fils) must never be swallowed by the epsilon.
     * NOTE (documented, deliberately not asserted): a difference of EXACTLY half a fils (0.0005)
     * is indeterminate — `1.5005 - 1.5` evaluates to 0.00049999999999989 in IEEE-754, so the
     * epsilon cut fires and nothing posts. That is harmless (half a fils is not representable at
     * 3dp either way) but means the docblock's "half the smallest representable unit" boundary is
     * not exact. Recorded as a cosmetic finding, not a defect.
     */
    public function test_a_one_fils_difference_is_never_swallowed_by_the_epsilon(): void
    {
        // a=10, rate delta 0.0001 -> D = 0.001 exactly.
        $source = $this->postSourceLine('credit', 10.0, 'USD', 0.300100, 'boundary');
        $applied = $this->postAppliedLine(10.0, 'USD', 0.300000, 'boundary');

        $draft = app(RealisedFxService::class)->compute($this->input($source, $applied, 10.0, 90003));

        $this->assertNotNull($draft, 'a genuine one-fils difference must post, not vanish');
        $this->assertSame(0.001, round($draft->amount, 3));
    }

    // ── 3. Partial applies ──────────────────────────────────────────────────────────────────

    public function test_partial_applies_post_two_independent_documents_with_no_double_count(): void
    {
        // One USD 100 advance @0.310 drawn against a USD 100 invoice @0.300, 40% now, 60% later.
        $source = $this->postSourceLine('credit', 100.0, 'USD', 0.310, 'partial');
        $applied = $this->postAppliedLine(100.0, 'USD', 0.300, 'partial');

        $service = app(RealisedFxService::class);
        $first = $service->postForApply($this->input($source, $applied, 40.0, 90004));
        $second = $service->postForApply($this->input($source, $applied, 60.0, 90005));

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->transaction->id, $second->transaction->id, 'two apply events -> two documents');

        $docs = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('doc_type', 'FXR')->get();
        $this->assertCount(2, $docs, 'exactly two FXR documents, never a third from double counting');

        // 40*(0.310-0.300)=0.400 ; 60*(0.310-0.300)=0.600 ; total 1.000 == the whole-apply figure.
        $party = $this->partyAccountId();
        $fxOnParty = JournalEntry::withoutGlobalScopes()
            ->whereIn('transaction_id', $docs->pluck('id'))
            ->where('account_id', $party)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS net')
            ->value('net');

        $this->assertSame(
            1.000,
            round((float) $fxOnParty, 3),
            'the two partial FX documents must sum to exactly the single-apply figure (credit-sourced gain -> party Dr)'
        );
    }

    // ── 4. Reversal restores the party balance exactly ──────────────────────────────────────

    public function test_reversal_restores_the_party_balance_exactly(): void
    {
        $source = $this->postSourceLine('credit', 100.0, 'USD', 0.310, 'reversal');
        $applied = $this->postAppliedLine(100.0, 'USD', 0.300, 'reversal');

        $party = $this->partyAccountId();
        $before = $this->netMovement($party);

        $service = app(RealisedFxService::class);
        $posted = $service->postForApply($this->input($source, $applied, 100.0, 90006));
        $this->assertNotNull($posted);
        $this->assertSame(1.000, round($this->netMovement($party) - $before, 3), 'FX doc moves the party by exactly D');

        $reversal = $service->reverseForApply($this->companyId, 'pa', 90006, now(), null);
        $this->assertNotNull($reversal, 'un-apply must reverse the FX document');
        $this->assertSame(
            $posted->transaction->id,
            (int) $reversal->transaction->reversal_of_transaction_id,
            'the reversal must point back at the original FXR document'
        );
        $this->assertSame(
            $before,
            $this->netMovement($party),
            'after reversal the party balance must be restored EXACTLY, to the fils'
        );

        // Idempotent: nothing live left to reverse.
        $this->assertNull($service->reverseForApply($this->companyId, 'pa', 90006, now(), null));
    }

    // ── 5. Re-apply after reversal — design intent probe ────────────────────────────────────

    public function test_reapply_under_the_same_apply_id_after_reversal_does_not_post_again(): void
    {
        $source = $this->postSourceLine('credit', 100.0, 'USD', 0.310, 'reapply');
        $applied = $this->postAppliedLine(100.0, 'USD', 0.300, 'reapply');

        $service = app(RealisedFxService::class);
        $first = $service->postForApply($this->input($source, $applied, 100.0, 90007));
        $service->reverseForApply($this->companyId, 'pa', 90007, now(), null);

        $again = $service->postForApply($this->input($source, $applied, 100.0, 90007));

        // Design intent: the idempotency key survives reversal (PostingService::findByIdempotencyKey
        // deliberately does NOT filter on posting_status), so re-posting under the SAME apply id is
        // a no-op returning the (now reversed) original. A genuine re-apply always carries a NEW
        // payment_applications row id, hence a new key. Pinned here so a future change that makes
        // re-apply silently double-post is caught.
        $this->assertSame(
            $first->transaction->id,
            $again->transaction->id,
            're-posting the same apply id must return the existing document, never a second one'
        );
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()
                ->where('company_id', $this->companyId)
                ->where('idempotency_key', RealisedFxService::idempotencyKeyFor('pa', 90007))
                ->count()
        );
    }

    // ── 6. Legacy-shaped rows ───────────────────────────────────────────────────────────────

    public function test_line_with_null_original_amount_is_handled_without_error(): void
    {
        $source = $this->postSourceLine('credit', 100.0, 'USD', 0.310, 'nullorig');
        $applied = $this->postAppliedLine(100.0, 'USD', 0.300, 'nullorig');

        // Legacy shape: original_amount NULL / 0 on both lines while exchange_rate survives — the
        // exact row shape PostingService's own docblock (~L1601) says legacy writers produce.
        JournalEntry::withoutGlobalScopes()->whereIn('id', [$source, $applied])
            ->update(['original_amount' => null]);
        JournalEntry::withoutGlobalScopes()->where('id', $applied)->update(['original_amount' => 0]);

        $draft = app(RealisedFxService::class)->compute($this->input($source, $applied, 100.0, 90008));

        // No division by original_amount anywhere -> no DivisionByZeroError; the rate column alone
        // drives the computation.
        $this->assertNotNull($draft);
        $this->assertSame(1.000, round($draft->amount, 3));
    }

    // ── 7. Base-vs-foreign apply (documents the current REJECT behaviour) ───────────────────

    public function test_base_currency_source_against_a_foreign_invoice_is_rejected(): void
    {
        $base = (string) config('accounting.engine.base_currency');
        $source = $this->postSourceLine('credit', 30.0, $base, 1.0, 'basevsfc');
        $applied = $this->postAppliedLine(100.0, 'USD', 0.300, 'basevsfc');

        $this->expectException(CrossCurrencyApplyException::class);
        app(RealisedFxService::class)->compute($this->input($source, $applied, 100.0, 90009));
    }

    // ── 8. Multi-company scoping of the low-level entry point ───────────────────────────────

    public function test_compute_does_not_scope_the_named_lines_to_the_input_company(): void
    {
        $other = $this->createTenant();
        $otherCompanyId = $other['company']->id;
        CoaSeeder::run($otherCompanyId);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $otherCompanyId, '--enable' => true]);
        $this->trackCompanyForInvariants($otherCompanyId);

        // Lines belong to company A; the input claims company B.
        $source = $this->postSourceLine('credit', 100.0, 'USD', 0.310, 'xco');
        $applied = $this->postAppliedLine(100.0, 'USD', 0.300, 'xco');

        $draft = app(RealisedFxService::class)->compute(
            $this->input($source, $applied, 100.0, 90010, $otherCompanyId)
        );

        // Documents the CURRENT behaviour: compute() reads the two journal_entries rows with
        // withoutGlobalScopes() and NO company filter, so it happily builds a draft whose party
        // accountId belongs to a DIFFERENT company. The production wiring
        // (postForApplication()) does scope both lookups, so this is defence-in-depth only —
        // but any future caller of compute()/postForApply() inherits the hole.
        $this->assertNotNull($draft, 'compute() currently accepts cross-company line ids (unscoped)');

        // Posting it must still be refused by the engine's own tenant consistency guard.
        $threw = false;
        try {
            app(RealisedFxService::class)->postForApply(
                $this->input($source, $applied, 100.0, 90010, $otherCompanyId)
            );
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertTrue(
            $threw || Transaction::withoutGlobalScopes()
                ->where('company_id', $otherCompanyId)->where('doc_type', 'FXR')->count() === 0,
            'a cross-company FX document must never be persisted'
        );
    }

    // ── 9. The repost / payment_id linkage ──────────────────────────────────────────────────

    /**
     * PostingService's own docblock states it outright: after a repost, "a query such as
     * `WHERE payment_id = P AND posting_status = 'posted'` finds NEITHER the reversal nor the
     * replacement". RealisedFxService::resolveSourceLineId() is exactly that query. This test
     * proves the FX document is silently lost for any payment whose receipt document was ever
     * reposted (a normal operation — e.g. the W5.R cheque-clearance flow).
     */
    public function test_source_line_survives_a_repost_of_the_receipt_document(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->clientId,
            'agent_id' => $this->tenant['agent']->id,
            'amount' => 100.0,
            'sub_amount' => 100.0,
            'currency' => 'USD',
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $this->tenant['agent']->id,
            'client_id' => $this->clientId,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $this->tenant['user']->id,
            'status' => 'completed',
        ]);

        $this->postSourceLine('credit', 100.0, 'USD', 0.310, 'repost', paymentId: $payment->id);
        $this->postAppliedLine(100.0, 'USD', 0.300, 'repost', invoiceId: $invoice->id);

        $application = new CreditApplicationInput(
            idSource: 'pa',
            id: 90011,
            amountApplied: 100.0,
            sourceType: 'payment',
            sourceId: $payment->id,
        );

        $service = app(RealisedFxService::class);

        // Control: before any repost, the FX document posts.
        $posted = $service->postForApplication($application, $invoice, $this->companyId, null, now(), null);
        $this->assertNotNull($posted, 'control: FX must post while payment_id is still on a posted document');

        // Now repost the receipt document (a normal operation) and retry a NEW apply event.
        $receipt = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('payment_id', $payment->id)
            ->where('posting_status', 'posted')
            ->firstOrFail();

        $replacement = new DocumentDraft(
            companyId: $this->companyId,
            branchId: null,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Reposted receipt',
            lines: [
                new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE', accountId: null, side: 'credit', amount: 31.0,
                    currency: 'USD', originalAmount: 100.0, exchangeRate: 0.310,
                    transactionType: 'FIXTURE', partyAccountRef: $this->clientId,
                ),
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND', accountId: null, side: 'debit', amount: 31.0,
                    currency: (string) config('accounting.engine.base_currency'),
                    originalAmount: 31.0, exchangeRate: 1.0, transactionType: 'FIXTURE',
                ),
            ],
            // Same key as the original — exactly what ReceiptVoucherController::update() does;
            // repost() then suffixes it with ':repost:{old->id}'.
            idempotencyKey: 'adv-source:repost',
            paymentId: $payment->id,
        );

        app(PostingService::class)->repost($receipt, $replacement, now(), null);

        $secondApplication = new CreditApplicationInput(
            idSource: 'pa',
            id: 90012,
            amountApplied: 100.0,
            sourceType: 'payment',
            sourceId: $payment->id,
        );

        $afterRepost = $service->postForApplication($secondApplication, $invoice, $this->companyId, null, now(), null);

        $this->assertNotNull(
            $afterRepost,
            'DEFECT PIN: after a repost of the receipt document the source line must still be '
            .'resolvable — resolving by `payment_id AND posting_status=posted` finds nothing, so '
            .'the realised-FX document is silently skipped.'
        );

        // ...and it must use the REPLACEMENT's own rate (0.310 vs the invoice's 0.300 on USD 100).
        $this->assertSame(
            1.000,
            round((float) JournalEntry::withoutGlobalScopes()
                ->where('transaction_id', $afterRepost->transaction->id)
                ->where('account_id', $this->partyAccountId())
                ->value('debit'), 3),
            'the FX amount after a repost must come from the live replacement line'
        );
    }
}
