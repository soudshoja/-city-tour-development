<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\CreditApplicationTotalMismatchException;
use App\Exceptions\Accounting\UnresolvedBranchException;
use App\Models\Account;
use App\Models\CurrencyExchange;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Accounting\CreditApplicationDraftBuilder;
use App\Services\Accounting\CreditApplicationInput;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * W2b build (KEY: draft-builder — design call E1), fixed W2c (orchestrator rulings B-2/B-3/R-a/
 * R-g). Unit tests for the shared credit-apply engine draft builder. This class only builds a
 * {@see \App\Services\Accounting\DocumentDraft}; it never touches
 * InvoiceController/PaymentApplicationService directly.
 *
 * ON-path tests post the built draft through the real {@see PostingService} directly (never
 * {@see \App\Services\Accounting\PostingSeam}, since this class has no seam concerns of its own)
 * against a REAL {@see CoaSeeder}::run() + {@see SystemAccountsSeeder}::run() chart, per this
 * build's own safety rule.
 */
class CreditApplicationDraftBuilderTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function makeInvoice(array $tenant, float $amount = 100.0, ?string $currency = null): Invoice
    {
        return Invoice::factory()->create(array_filter([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => $amount,
            'sub_amount' => $amount,
            'currency' => $currency,
        ], fn ($v) => $v !== null));
    }

    private function pa(int $id, float $amount, ?string $voucherLabel = null): CreditApplicationInput
    {
        return new CreditApplicationInput(
            idSource: CreditApplicationInput::SOURCE_PAYMENT_APPLICATION,
            id: $id,
            amountApplied: $amount,
            voucherLabel: $voucherLabel,
        );
    }

    /**
     * Design call E1 acceptance shape: 2 applications -> 3 balanced lines, debits on the
     * CLIENT_ADVANCE leaf (CoaSeeder code 2632, root Liabilities), credit on the
     * RECEIVABLE_CONTROL leaf (CoaSeeder code 1351).
     */
    public function test_two_applications_post_three_balanced_lines_on_the_correct_leaves(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        $invoice = $this->makeInvoice($tenant, 130.0, 'KWD');

        $applications = [
            $this->pa(101, 80.0, 'TOPUP-1'),
            $this->pa(102, 50.0, 'TOPUP-2'),
        ];

        $draft = (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: $applications,
            callerTotalAmount: 130.0,
            companyId: $company->id,
        );

        $posted = app(PostingService::class)->post($draft);

        $entries = JournalEntry::where('transaction_id', $posted->transaction->id)->get();
        $this->assertCount(3, $entries);

        $advanceLeaf = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '2632')
            ->first();
        $receivableLeaf = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '1351')
            ->first();
        $this->assertNotNull($advanceLeaf, 'CoaSeeder must seed code 2632 for this test to be meaningful');
        $this->assertNotNull($receivableLeaf, 'CoaSeeder must seed code 1351 for this test to be meaningful');

        $debitEntries = $entries->where('debit', '>', 0)->values();
        $creditEntries = $entries->where('credit', '>', 0)->values();
        $this->assertCount(2, $debitEntries);
        $this->assertCount(1, $creditEntries);

        foreach ($debitEntries as $entry) {
            $this->assertSame($advanceLeaf->id, $entry->account_id);
            $this->assertSame('payable', $entry->type);
        }

        $this->assertSame($receivableLeaf->id, $creditEntries->first()->account_id);
        $this->assertSame('receivable', $creditEntries->first()->type);

        $this->assertEqualsWithDelta(130.0, (float) $debitEntries->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(130.0, (float) $creditEntries->sum('credit'), 0.001);
        $this->assertEqualsWithDelta(
            (float) $entries->sum('debit'),
            (float) $entries->sum('credit'),
            0.0005
        );

        $expectedKey = PaymentIdempotencyKey::forCreditApplication($invoice->id, $applications);
        $this->assertSame($expectedKey, $posted->transaction->idempotency_key);
        $this->assertNull($posted->transaction->payment_id, 'trap 1 — header payment_id must stay NULL');
        $this->assertSame('Payment', $posted->transaction->reference_type, 'sourceType must be pinned, not fall back off docType');
    }

    /**
     * Both legacy copies: `if ($amountApplied <= 0) continue;`. A zero and a negative
     * application are both skipped; the credit leg equals the sum of the ones that survive, not
     * the count or a caller-supplied figure that ignores the skip.
     */
    public function test_zero_or_negative_application_is_skipped_and_credit_equals_posted_sum(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 80.0, 'KWD');

        $applications = [
            $this->pa(1, 50.0),
            $this->pa(2, 0.0),
            $this->pa(3, -10.0),
            $this->pa(4, 30.0),
        ];

        $draft = (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: $applications,
            callerTotalAmount: 80.0,
            companyId: $tenant['company']->id,
        );

        $debitLines = array_values(array_filter($draft->lines, fn ($line) => $line->side === 'debit'));
        $creditLines = array_values(array_filter($draft->lines, fn ($line) => $line->side === 'credit'));

        $this->assertCount(2, $debitLines, 'only ids 1 and 4 should survive the amountApplied <= 0 skip');
        $this->assertCount(1, $creditLines);
        $this->assertEqualsWithDelta(
            80.0,
            array_sum(array_map(fn ($line) => $line->amount, $debitLines)),
            0.001
        );
        $this->assertEqualsWithDelta(80.0, $creditLines[0]->amount, 0.001);

        // The idempotency key still folds in the SKIPPED ids (2 and 3), not just the posted ones
        // — see PaymentIdempotencyKey::forCreditApplication()'s own docblock.
        $this->assertSame(
            PaymentIdempotencyKey::forCreditApplication($invoice->id, $applications),
            $draft->idempotencyKey
        );
    }

    /**
     * Design call E1: "If the caller's $totalAmount != that sum -> throw a typed PostingException
     * subclass (loud data error) — never post an unbalanced or silently-adjusted document."
     */
    public function test_caller_total_mismatch_throws_typed_exception(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 100.0, 'KWD');

        $applications = [$this->pa(1, 50.0)];

        try {
            (new CreditApplicationDraftBuilder())->build(
                invoice: $invoice,
                applications: $applications,
                callerTotalAmount: 999.0,
                companyId: $tenant['company']->id,
            );
            $this->fail('Expected CreditApplicationTotalMismatchException was not thrown.');
        } catch (CreditApplicationTotalMismatchException $e) {
            $this->assertSame($invoice->id, $e->invoiceId);
            $this->assertEqualsWithDelta(999.0, $e->callerTotalAmount, 0.001);
            $this->assertEqualsWithDelta(50.0, $e->postedDebitTotal, 0.001);
        }
    }

    /**
     * Design call E2: the idempotency key is derived from the SET of application ids, so
     * submitting the identical set in a different order must produce the identical key.
     */
    public function test_idempotency_key_is_stable_under_application_order(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 80.0, 'KWD');
        $builder = new CreditApplicationDraftBuilder();

        $forward = [$this->pa(5, 30.0), $this->pa(7, 50.0)];
        $reversed = [$this->pa(7, 50.0), $this->pa(5, 30.0)];

        $draftForward = $builder->build($invoice, $forward, 80.0, $tenant['company']->id);
        $draftReversed = $builder->build($invoice, $reversed, 80.0, $tenant['company']->id);

        $this->assertSame($draftForward->idempotencyKey, $draftReversed->idempotencyKey);
        $this->assertSame(
            PaymentIdempotencyKey::forCreditApplication($invoice->id, $forward),
            $draftForward->idempotencyKey
        );
    }

    public function test_requires_at_least_one_application(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 100.0, 'KWD');

        $this->expectException(\InvalidArgumentException::class);

        (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: [],
            callerTotalAmount: 0.0,
            companyId: $tenant['company']->id,
        );
    }

    /**
     * W2c fix (B-2): a 'pa' source and a 'partial' source with the SAME numeric id must never
     * collapse onto the same idempotency key — this is exactly the collision that silently
     * dropped a real second credit-application event in W2b (lead report §5, B-2).
     */
    public function test_key_differs_by_source_for_equal_numeric_ids(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 50.0, 'KWD');
        $builder = new CreditApplicationDraftBuilder();

        $paApplications = [$this->pa(5, 50.0)];
        $partialApplications = [new CreditApplicationInput(
            idSource: CreditApplicationInput::SOURCE_PARTIAL,
            id: 5,
            amountApplied: 50.0,
        )];

        $draftPa = $builder->build($invoice, $paApplications, 50.0, $tenant['company']->id);
        $draftPartial = $builder->build($invoice, $partialApplications, 50.0, $tenant['company']->id);

        $this->assertNotSame(
            $draftPa->idempotencyKey,
            $draftPartial->idempotencyKey,
            'a pa-sourced id=5 and a partial-sourced id=5 must key into DIFFERENT namespaces'
        );
        $this->assertStringContainsString(':pa:', $draftPa->idempotencyKey);
        $this->assertStringContainsString(':partial:', $draftPartial->idempotencyKey);
    }

    /**
     * W2c fix (B-3): PostingService rounds EACH line independently before checking the header
     * balance. Two applications of 1.2345 round to 1.235 each (Σ = 2.470); the OLD builder built
     * the credit leg from round(Σ unrounded) = round(2.469, 3) = 2.469, a 0.001 gap against the
     * engine's 0.0005 tolerance — UnbalancedDocumentException on data the builder itself had
     * just certified as balanced. Fixed: round each debit BEFORE building its LineDraft, and
     * build the credit leg from the SUM of those already-rounded values.
     */
    public function test_rounding_beyond_base_decimals_posts_balanced_through_the_real_engine(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        $invoice = $this->makeInvoice($tenant, 2.47, 'KWD');

        $applications = [
            $this->pa(1, 1.2345),
            $this->pa(2, 1.2345),
        ];

        // The caller total a correctly-rounding caller would compute — round(1.2345,3) twice,
        // i.e. 2.470, NOT the naive unrounded array_sum() of 2.469. This is what a caller must
        // pass once its own total-computation is aligned with the engine's per-line rounding;
        // the mismatch check compares against the SAME rounded-per-line sum this builder now
        // produces (see class docblock, B-3).
        $callerTotal = round(1.2345, 3) + round(1.2345, 3);
        $this->assertEqualsWithDelta(2.470, $callerTotal, 0.0001);

        $draft = (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: $applications,
            callerTotalAmount: $callerTotal,
            companyId: $company->id,
        );

        // Posting through the REAL PostingService must not throw UnbalancedDocumentException.
        $posted = app(PostingService::class)->post($draft);

        $entries = JournalEntry::where('transaction_id', $posted->transaction->id)->get();
        $debitTotal = (float) $entries->where('debit', '>', 0)->sum('debit');
        $creditTotal = (float) $entries->where('credit', '>', 0)->sum('credit');

        $this->assertEqualsWithDelta(2.470, $debitTotal, 0.0005);
        $this->assertEqualsWithDelta(2.470, $creditTotal, 0.0005);
        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.0005);
    }

    /**
     * W2c fix (R-a): a non-base-currency invoice must write its OWN currency/original amount/
     * exchange rate onto every line — never relabel a foreign magnitude as base currency at rate
     * 1.0. Base amount = originalAmount × exchangeRate, rounded to base decimals.
     */
    public function test_non_base_currency_invoice_writes_original_currency_and_exchange_rate(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        CurrencyExchange::create([
            'company_id' => $company->id,
            'base_currency' => 'USD',
            'exchange_currency' => 'KWD',
            'exchange_rate' => 0.3,
            'is_manual' => true,
        ]);

        $invoice = $this->makeInvoice($tenant, 100.0, 'USD');

        $applications = [$this->pa(1, 100.0)];

        $draft = (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: $applications,
            callerTotalAmount: 100.0,
            companyId: $company->id,
        );

        $debitLine = $draft->lines[0];
        $this->assertSame('USD', $debitLine->currency);
        $this->assertEqualsWithDelta(100.0, $debitLine->originalAmount, 0.001);
        $this->assertEqualsWithDelta(0.3, $debitLine->exchangeRate, 0.000001);
        $this->assertEqualsWithDelta(30.0, $debitLine->amount, 0.001);

        $creditLine = $draft->lines[1];
        $this->assertSame('USD', $creditLine->currency);
        $this->assertEqualsWithDelta(100.0, $creditLine->originalAmount, 0.001);
        $this->assertEqualsWithDelta(30.0, $creditLine->amount, 0.001);

        $posted = app(PostingService::class)->post($draft);
        $entries = JournalEntry::where('transaction_id', $posted->transaction->id)->get();

        foreach ($entries as $entry) {
            $this->assertSame('USD', $entry->currency);
        }
        $this->assertEqualsWithDelta(30.0, (float) $entries->where('debit', '>', 0)->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $entries->where('credit', '>', 0)->sum('credit'), 0.001);
    }

    /**
     * W2c fix (R-a): when no CurrencyExchange row exists for the invoice's currency pair, the
     * builder must not throw or silently mislabel — it falls back to exchangeRate = 1.0 and logs
     * a loud, observable warning instead.
     *
     * accounting-builds T1 (F1/Q2 fix): the builder now tries the invoice's own POSTED INV-rate
     * FIRST (see resolvePostedInvoiceRate()'s own docblock) — no INV document is posted in this
     * test's fixture, so that lookup finds nothing and logs
     * 'accounting.credit_apply_rate_fallback_live_lookup' (info) before falling through to the
     * SAME live-lookup path this test already exercised, which then finds no CurrencyExchange row
     * either and hits the ORIGINAL 'accounting.fx_rate_missing' warning unchanged.
     */
    public function test_missing_exchange_rate_falls_back_to_one_and_logs_warning(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 50.0, 'GBP');

        Log::shouldReceive('info')
            ->once()
            ->with('accounting.credit_apply_rate_fallback_live_lookup', \Mockery::on(function (array $context) use ($invoice) {
                return $context['invoice_id'] === $invoice->id
                    && $context['currency'] === 'GBP';
            }));

        Log::shouldReceive('warning')
            ->once()
            ->with('accounting.fx_rate_missing', \Mockery::on(function (array $context) use ($invoice) {
                return $context['invoice_id'] === $invoice->id
                    && $context['currency'] === 'GBP';
            }));

        $applications = [$this->pa(1, 50.0)];

        $draft = (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: $applications,
            callerTotalAmount: 50.0,
            companyId: $tenant['company']->id,
        );

        $this->assertEqualsWithDelta(1.0, $draft->lines[0]->exchangeRate, 0.000001);
        $this->assertEqualsWithDelta(50.0, $draft->lines[0]->amount, 0.001);
    }

    /**
     * W2c fix (R-g): a null branch chain must refuse loudly with a typed exception — never cast
     * to the 0 sentinel and post under a phantom "branch 0" document sequence.
     */
    public function test_refuses_null_branch_with_typed_exception(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 50.0, 'KWD');
        // Simulate an unresolved branch chain on the already-loaded relation, without touching
        // schema (agents.branch_id is NOT NULL at the database level — this mirrors what a null
        // relation chain would produce for $invoice->agent?->branch_id).
        $invoice->agent->branch_id = null;

        $applications = [$this->pa(1, 50.0)];

        $this->expectException(UnresolvedBranchException::class);

        (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: $applications,
            callerTotalAmount: 50.0,
            companyId: $tenant['company']->id,
        );
    }

    public function test_refuses_zero_branch_with_typed_exception(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant, 50.0, 'KWD');
        $invoice->agent->branch_id = 0;

        $applications = [$this->pa(1, 50.0)];

        $this->expectException(UnresolvedBranchException::class);

        (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: $applications,
            callerTotalAmount: 50.0,
            companyId: $tenant['company']->id,
        );
    }
}
