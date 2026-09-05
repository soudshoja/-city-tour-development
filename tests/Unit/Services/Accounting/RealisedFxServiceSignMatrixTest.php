<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\CrossCurrencyApplyException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\ApplyFxInput;
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
 * accounting-builds T1 (Lane A). MANDATORY test per PLAN.md §5 Lane A: a census of all four
 * (source side x rate-sign) cells {@see RealisedFxService::compute()}'s DC-aware mapping can
 * produce, each asserting the leaf ACTUALLY hit (4139 vs 5219 — posted through the real engine,
 * not merely the drafted purposeCode string), the party side, the amount to 3dp, and document
 * balance — plus the zero-diff no-op, multi-line (Σ-before-round) rounding, the same-currency
 * apply rule (coordinator steer 2026-09-02 point 2), and the base-currency-only no-op
 * (coordinator steer point 3).
 *
 * `$sourceLine`/`$appliedLine` in every case here are REAL posted `journal_entries` rows (posted
 * through {@see PostingService} directly, never a hand-built row) — L4's "posted lines only" rule
 * is exercised for real, not merely documented.
 */
class RealisedFxServiceSignMatrixTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    private int $companyId;
    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $this->companyId = $tenant['company']->id;
        $this->clientId = $tenant['client']->id;

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

    /**
     * Posts a real, balanced 2-line JV: `$side` CLIENT_ADVANCE at ($originalAmount, $currency,
     * $rate) / balancing CASH_IN_HAND leg in base currency — this is the "source" (applied-from)
     * line's own original posting. Returns the CLIENT_ADVANCE line's id.
     */
    private function postSourceLine(string $side, float $originalAmount, string $currency, float $rate, ?string $keySuffix = null): int
    {
        $base = (string) config('accounting.engine.base_currency');
        $baseAmount = round($originalAmount * $rate, 3);
        $other = $side === 'debit' ? 'credit' : 'debit';

        $draft = new DocumentDraft(
            companyId: $this->companyId,
            branchId: null,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Sign-matrix source fixture',
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
            idempotencyKey: 'sign-matrix-source:'.($keySuffix ?? uniqid('', true)),
        );

        $posted = app(PostingService::class)->post($draft);

        return JournalEntry::where('transaction_id', $posted->transaction->id)
            ->where('account_id', app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $this->companyId)->id)
            ->value('id');
    }

    /**
     * Posts a real, balanced 2-line INV document: Dr RECEIVABLE_CONTROL at ($originalAmount,
     * $currency, $rate) / Cr CASH_IN_HAND (a stand-in booking counter-leg — only the
     * RECEIVABLE_CONTROL line's rate matters to the class under test). Returns that line's id.
     */
    private function postAppliedLine(float $originalAmount, string $currency, float $rate, ?string $keySuffix = null): int
    {
        $base = (string) config('accounting.engine.base_currency');
        $baseAmount = round($originalAmount * $rate, 3);

        $draft = new DocumentDraft(
            companyId: $this->companyId,
            branchId: null,
            docType: 'INV',
            subType: null,
            docDate: now(),
            narration: 'Sign-matrix applied (invoice) fixture',
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
                    invoiceId: null,
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
                ),
            ],
            idempotencyKey: 'sign-matrix-applied:'.($keySuffix ?? uniqid('', true)),
        );

        $posted = app(PostingService::class)->post($draft);

        return JournalEntry::where('transaction_id', $posted->transaction->id)
            ->where('account_id', app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $this->companyId)->id)
            ->value('id');
    }

    private function input(int $sourceLineId, int $appliedLineId, float $amount, string $key): ApplyFxInput
    {
        return new ApplyFxInput(
            companyId: $this->companyId,
            branchId: null,
            sourceLineId: $sourceLineId,
            appliedLineId: $appliedLineId,
            appliedFcAmount: $amount,
            idSource: 'pa',
            id: crc32($key) ?: 1,
            docDate: now(),
        );
    }

    /** @return array{code: string} the resolved-account code a purposeCode maps to for this company */
    private function codeFor(string $purposeCode): string
    {
        return app(AccountResolver::class)->resolve($purposeCode, $this->companyId)->code;
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Census: all four (source side x rate-sign) cells.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_debit_sourced_positive_difference_hits_fx_loss_and_credits_party(): void
    {
        // Debit-sourced (payment->invoice), D>0 -> party Cr / FX_LOSS Dr.
        $sourceLineId = $this->postSourceLine('debit', 100.0, 'USD', 0.310, 'db-pos');
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'db-pos');

        $service = app(RealisedFxService::class);
        $draft = $service->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'db-pos'));

        $this->assertNotNull($draft);
        $this->assertFalse($draft->isGain);
        $this->assertSame('debit', $draft->sourceSide);
        $this->assertEqualsWithDelta(1.000, $draft->amount, 0.0005);
        $this->assertEqualsWithDelta(1.000, $draft->signedDifference, 0.0005);
        $this->assertSame('credit', $draft->partyLine->side, 'party must be credited on a debit-sourced loss');
        $this->assertSame('debit', $draft->fxLine->side, 'FX_LOSS must be debited');
        $this->assertSame('FX_LOSS_REALISED', $draft->fxLine->purposeCode);
        $this->assertSame('5219', $this->codeFor('FX_LOSS_REALISED'), 'leaf hit must be 5219');

        $this->assertEqualsWithDelta(0.0, $draft->partyLine->amount - $draft->fxLine->amount, 1e-9, 'document must self-balance');
    }

    public function test_debit_sourced_negative_difference_hits_fx_gain_and_debits_party(): void
    {
        // Debit-sourced (payment->invoice), D<0 -> party Dr / FX_GAIN Cr.
        $sourceLineId = $this->postSourceLine('debit', 100.0, 'USD', 0.290, 'db-neg');
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'db-neg');

        $draft = app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'db-neg'));

        $this->assertNotNull($draft);
        $this->assertTrue($draft->isGain);
        $this->assertSame('debit', $draft->sourceSide);
        $this->assertEqualsWithDelta(1.000, $draft->amount, 0.0005);
        $this->assertEqualsWithDelta(-1.000, $draft->signedDifference, 0.0005);
        $this->assertSame('debit', $draft->partyLine->side, 'party must be debited on a debit-sourced gain');
        $this->assertSame('credit', $draft->fxLine->side, 'FX_GAIN must be credited');
        $this->assertSame('FX_GAIN_REALISED', $draft->fxLine->purposeCode);
        $this->assertSame('4139', $this->codeFor('FX_GAIN_REALISED'), 'leaf hit must be 4139');
    }

    public function test_credit_sourced_positive_difference_hits_fx_gain_and_debits_party(): void
    {
        // Credit-sourced (receipt->invoice) -- mapping FLIPS. D>0 -> party Dr / FX_GAIN Cr.
        $sourceLineId = $this->postSourceLine('credit', 100.0, 'USD', 0.310, 'cr-pos');
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'cr-pos');

        $draft = app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'cr-pos'));

        $this->assertNotNull($draft);
        $this->assertTrue($draft->isGain);
        $this->assertSame('credit', $draft->sourceSide);
        $this->assertEqualsWithDelta(1.000, $draft->amount, 0.0005);
        $this->assertEqualsWithDelta(1.000, $draft->signedDifference, 0.0005);
        $this->assertSame('debit', $draft->partyLine->side, 'party must be debited on a credit-sourced gain');
        $this->assertSame('credit', $draft->fxLine->side, 'FX_GAIN must be credited');
        $this->assertSame('FX_GAIN_REALISED', $draft->fxLine->purposeCode);
        $this->assertSame('4139', $this->codeFor('FX_GAIN_REALISED'));
    }

    public function test_credit_sourced_negative_difference_hits_fx_loss_and_credits_party(): void
    {
        // Credit-sourced (receipt->invoice) -- mapping FLIPS. D<0 -> party Cr / FX_LOSS Dr.
        $sourceLineId = $this->postSourceLine('credit', 100.0, 'USD', 0.290, 'cr-neg');
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'cr-neg');

        $draft = app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'cr-neg'));

        $this->assertNotNull($draft);
        $this->assertFalse($draft->isGain);
        $this->assertSame('credit', $draft->sourceSide);
        $this->assertEqualsWithDelta(1.000, $draft->amount, 0.0005);
        $this->assertEqualsWithDelta(-1.000, $draft->signedDifference, 0.0005);
        $this->assertSame('credit', $draft->partyLine->side, 'party must be credited on a credit-sourced loss');
        $this->assertSame('debit', $draft->fxLine->side, 'FX_LOSS must be debited');
        $this->assertSame('FX_LOSS_REALISED', $draft->fxLine->purposeCode);
        $this->assertSame('5219', $this->codeFor('FX_LOSS_REALISED'));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Zero-diff, rounding, L4 skip, same-currency, base-currency-only.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_zero_difference_produces_no_document(): void
    {
        $sourceLineId = $this->postSourceLine('debit', 100.0, 'USD', 0.300, 'zero');
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'zero');

        $draft = app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'zero'));

        $this->assertNull($draft, 'identical source/applied rates must produce no FX document');
    }

    /**
     * Coordinator steer point 3 (scope fact): engine sale invoices are base-currency only in
     * practice. Both sides base (currency=KWD, rate=1.0) must be a no-op — never a phantom
     * "gain" from float noise.
     */
    public function test_both_sides_base_currency_only_is_a_no_op(): void
    {
        $base = (string) config('accounting.engine.base_currency');
        $sourceLineId = $this->postSourceLine('debit', 100.0, $base, 1.0, 'base-only');
        $appliedLineId = $this->postAppliedLine(100.0, $base, 1.0, 'base-only');

        $draft = app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'base-only'));

        $this->assertNull($draft, 'both sides base-currency must never produce a document');
    }

    /**
     * Multi-line rounding (Σ before round): two SEPARATE applications against the same invoice,
     * each with its own source rate, each rounded independently — the SUM of the two resulting
     * FX documents must equal Σ(a·r_s) − Σ(a·r_t) computed PER LINE THEN SUMMED, not the naive
     * single-blob figure (mirrors CreditApplicationDraftBuilder's own B-3 discipline).
     */
    public function test_two_applications_round_independently_before_summing(): void
    {
        $appliedLineId = $this->postAppliedLine(200.0, 'USD', 0.300, 'multi-applied');

        $sourceA = $this->postSourceLine('debit', 33.333, 'USD', 0.311, 'multi-a');
        $sourceB = $this->postSourceLine('debit', 66.667, 'USD', 0.317, 'multi-b');

        $service = app(RealisedFxService::class);
        $draftA = $service->compute($this->input($sourceA, $appliedLineId, 33.333, 'multi-a'));
        $draftB = $service->compute($this->input($sourceB, $appliedLineId, 66.667, 'multi-b'));

        $this->assertNotNull($draftA);
        $this->assertNotNull($draftB);

        $expectedA = round(round(33.333 * 0.311, 3) - round(33.333 * 0.300, 3), 3);
        $expectedB = round(round(66.667 * 0.317, 3) - round(66.667 * 0.300, 3), 3);

        $this->assertEqualsWithDelta(abs($expectedA), $draftA->amount, 0.0005);
        $this->assertEqualsWithDelta(abs($expectedB), $draftB->amount, 0.0005);
        $this->assertEqualsWithDelta(
            abs($expectedA) + abs($expectedB),
            $draftA->amount + $draftB->amount,
            0.0005,
            'each application must round independently, then sum -- never one merged figure'
        );
    }

    public function test_missing_source_line_is_skipped_not_thrown(): void
    {
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'missing-source');

        $draft = app(RealisedFxService::class)->compute(
            $this->input(999999999, $appliedLineId, 100.0, 'missing-source')
        );

        $this->assertNull($draft);
    }

    public function test_zero_exchange_rate_on_a_legacy_era_line_is_skipped(): void
    {
        // A legacy-era row: exchange_rate = 0 (L4's own named case). Given a real (if minimal)
        // Transaction header so AccountingInvariants' orphan-line check stays meaningful.
        $legacyTransaction = \App\Models\Transaction::create([
            'company_id' => $this->companyId,
            'branch_id' => null,
            'entity_id' => $this->companyId,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 100.000,
            'description' => 'Legacy-era fixture transaction (exchange_rate=0)',
            'reference_type' => 'Payment',
            'reference_number' => 'JV-LEGACY-FX-00001',
            'transaction_date' => now(),
        ]);

        $legacyLine = JournalEntry::create([
            'company_id' => $this->companyId,
            'account_id' => app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $this->companyId)->id,
            'transaction_id' => $legacyTransaction->id,
            'transaction_date' => now(),
            'debit' => 0,
            'credit' => 100.0,
            'name' => 'Legacy-era fixture line',
            'description' => 'Legacy-era fixture line',
            'type' => 'advance',
            'type_reference_id' => $this->clientId,
            'currency' => 'USD',
            'original_currency' => 'USD',
            'original_amount' => 100.0,
            'exchange_rate' => 0,
            'amount' => 0,
        ]);

        // Balancing counter-line on the SAME transaction so AccountingInvariants' per-transaction
        // debit=credit check stays green -- this fixture only needs to prove the exchange_rate=0
        // skip, not model a real legacy posting shape.
        JournalEntry::create([
            'company_id' => $this->companyId,
            'account_id' => app(AccountResolver::class)->resolve('CASH_IN_HAND', $this->companyId)->id,
            'transaction_id' => $legacyTransaction->id,
            'transaction_date' => now(),
            'debit' => 100.0,
            'credit' => 0,
            'name' => 'Legacy-era fixture counter-line',
            'description' => 'Legacy-era fixture counter-line',
            'type' => 'bank',
            'currency' => 'USD',
            'original_currency' => 'USD',
            'original_amount' => 100.0,
            'exchange_rate' => 0,
            'amount' => 0,
        ]);

        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'legacy-rate');

        $draft = app(RealisedFxService::class)->compute(
            $this->input($legacyLine->id, $appliedLineId, 100.0, 'legacy-rate')
        );

        $this->assertNull($draft, 'exchange_rate = 0 on either line must skip, per L4');
    }

    /**
     * Coordinator steer point 2 (same-currency apply rule): a payment recorded in one currency
     * applied against an invoice denominated in a DIFFERENT currency is a genuinely different,
     * disallowed scenario -- rejected loudly, never silently computed as if it were realised FX.
     */
    public function test_cross_currency_apply_is_rejected(): void
    {
        $sourceLineId = $this->postSourceLine('debit', 100.0, 'USD', 0.300, 'cross-ccy');
        $appliedLineId = $this->postAppliedLine(100.0, 'EUR', 0.330, 'cross-ccy');

        $this->expectException(CrossCurrencyApplyException::class);

        app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'cross-ccy'));
    }

    /** Same-currency, different rate -- the ordinary, ACCEPTED realised-FX case, for contrast. */
    public function test_same_currency_different_rate_is_accepted(): void
    {
        $sourceLineId = $this->postSourceLine('debit', 100.0, 'USD', 0.310, 'same-ccy');
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'same-ccy');

        $draft = app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'same-ccy'));

        $this->assertNotNull($draft);
    }

    /**
     * FX result must be computed from the POSTED lines only -- mutating the live rate table
     * between the two postings and the apply must never change the result (coordinator steer
     * point 1). RealisedFxService never calls CurrencyExchangeTrait::getExchangeRate() at all;
     * this test proves that by exercise, not by reading the source.
     */
    public function test_live_rate_table_mutation_does_not_affect_the_result(): void
    {
        $sourceLineId = $this->postSourceLine('debit', 100.0, 'USD', 0.310, 'live-mutate');
        $appliedLineId = $this->postAppliedLine(100.0, 'USD', 0.300, 'live-mutate');

        \App\Models\CurrencyExchange::create([
            'company_id' => $this->companyId,
            'base_currency' => 'USD',
            'exchange_currency' => (string) config('accounting.engine.base_currency'),
            'exchange_rate' => 0.999,
            'is_manual' => true,
        ]);

        $draft = app(RealisedFxService::class)->compute($this->input($sourceLineId, $appliedLineId, 100.0, 'live-mutate'));

        $this->assertNotNull($draft);
        $this->assertEqualsWithDelta(1.000, $draft->amount, 0.0005, 'the live rate table row must have zero effect on the posted-lines-only computation');
    }
}
