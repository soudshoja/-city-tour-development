<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\CurrencyExchange;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\CreditApplicationDraftBuilder;
use App\Services\Accounting\CreditApplicationInput;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RealisedFxService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T1 — POST-FIX RE-VERIFICATION (second, independent adversarial round).
 *
 * The first verify round fixed V-1 (the source line was resolved by `payment_id AND
 * posting_status = 'posted'`, which finds nothing after a repost) by walking PostingService's
 * `':repost:{old->id}'` replacement-key convention. These tests derive the full case table that
 * fix has to satisfy — one repost, two chained reposts, a fully-voided chain, two payments whose
 * chains run side by side, a replacement that is not itself posted, and a malformed over-long
 * chain — and then probe the SAME class of blindness on the OTHER side of the apply: the
 * INVOICE's posted line, read by RealisedFxService::resolveAppliedLineId() and by
 * CreditApplicationDraftBuilder::resolvePostedInvoiceRate().
 *
 * The source-side fixtures use a DIFFERENT rate on every replacement, so "which row did the
 * resolver pick" is answerable from the posted FX amount alone — the first round's own repost
 * test gave the replacement the same rate as the original, so it could not tell them apart.
 */
class RealisedFxRepostChainTest extends AccountingTestCase
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

    private function makePayment(): Payment
    {
        return Payment::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $this->tenant['agent']->id,
            'client_id' => $this->clientId,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $this->tenant['user']->id,
            'status' => 'completed',
        ]);
    }

    private function makeInvoice(): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $this->clientId,
            'agent_id' => $this->tenant['agent']->id,
            'amount' => 100.0,
            'sub_amount' => 100.0,
            'currency' => 'USD',
        ]);
    }

    /** A receipt-shaped JV: CLIENT_ADVANCE credit (the source line) against cash. */
    private function sourceDraft(float $rate, string $key, ?int $paymentId): DocumentDraft
    {
        $base = (string) config('accounting.engine.base_currency');
        $baseAmount = round(100.0 * $rate, 3);

        return new DocumentDraft(
            companyId: $this->companyId,
            branchId: null,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'Repost-chain source fixture',
            lines: [
                new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE', accountId: null, side: 'credit', amount: $baseAmount,
                    currency: 'USD', originalAmount: 100.0, exchangeRate: $rate,
                    transactionType: 'FIXTURE', partyAccountRef: $this->clientId,
                ),
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND', accountId: null, side: 'debit', amount: $baseAmount,
                    currency: $base, originalAmount: $baseAmount, exchangeRate: 1.0,
                    transactionType: 'FIXTURE',
                ),
            ],
            idempotencyKey: $key,
            paymentId: $paymentId,
        );
    }

    private function postSource(float $rate, string $key, ?int $paymentId = null): PostedDocument
    {
        return app(PostingService::class)->post($this->sourceDraft($rate, $key, $paymentId));
    }

    /**
     * Reposts $old at $rate. $key defaults to $old's OWN key — the shape
     * InvoiceController::repostInvoiceTransactionsWithNewDate() uses (`idempotencyKey:
     * $old->idempotency_key`), which is what makes repost()'s suffix convention NEST across a
     * chain of edits.
     */
    private function repostSource(Transaction $old, float $rate, ?string $key = null): PostedDocument
    {
        return app(PostingService::class)->repost(
            $old,
            $this->sourceDraft($rate, $key ?? (string) $old->idempotency_key, null),
            now(),
            null
        );
    }

    /** An INV-shaped document: RECEIVABLE_CONTROL debit (the applied line) against cash. */
    private function invoiceDraft(float $rate, string $key, int $invoiceId): DocumentDraft
    {
        $base = (string) config('accounting.engine.base_currency');
        $baseAmount = round(100.0 * $rate, 3);

        return new DocumentDraft(
            companyId: $this->companyId,
            branchId: null,
            docType: 'INV',
            subType: null,
            docDate: now(),
            narration: 'Repost-chain invoice fixture',
            lines: [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'debit', amount: $baseAmount,
                    currency: 'USD', originalAmount: 100.0, exchangeRate: $rate,
                    transactionType: 'FIXTURE', partyAccountRef: $this->clientId, invoiceId: $invoiceId,
                ),
                new LineDraft(
                    purposeCode: 'CASH_IN_HAND', accountId: null, side: 'credit', amount: $baseAmount,
                    currency: $base, originalAmount: $baseAmount, exchangeRate: 1.0,
                    transactionType: 'FIXTURE', invoiceId: $invoiceId,
                ),
            ],
            idempotencyKey: $key,
            invoiceId: $invoiceId,
        );
    }

    private function postInvoiceDoc(float $rate, string $key, int $invoiceId): PostedDocument
    {
        return app(PostingService::class)->post($this->invoiceDraft($rate, $key, $invoiceId));
    }

    private function apply(Payment $payment, Invoice $invoice, int $applicationId): ?PostedDocument
    {
        return app(RealisedFxService::class)->postForApplication(
            new CreditApplicationInput(
                idSource: 'pa',
                id: $applicationId,
                amountApplied: 100.0,
                sourceType: 'payment',
                sourceId: $payment->id,
            ),
            $invoice,
            $this->companyId,
            null,
            now(),
            null
        );
    }

    private function partyAccountId(): int
    {
        return app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $this->companyId)->id;
    }

    /** ['debit'|'credit', amount] of the FXR document's party line. */
    private function partyLineOf(PostedDocument $doc): array
    {
        $entry = JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $doc->transaction->id)
            ->where('account_id', $this->partyAccountId())
            ->firstOrFail();

        return ((float) $entry->debit) > 0
            ? ['debit', round((float) $entry->debit, 3)]
            : ['credit', round((float) $entry->credit, 3)];
    }

    private function fxLeafCodeOf(PostedDocument $doc): string
    {
        $entry = JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $doc->transaction->id)
            ->where('account_id', '!=', $this->partyAccountId())
            ->firstOrFail();

        return (string) \App\Models\Account::withoutGlobalScopes()->findOrFail($entry->account_id)->code;
    }

    private function fxrCount(): int
    {
        return Transaction::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('doc_type', 'FXR')
            ->count();
    }

    // ── A. The repost-chain case table (source side) ────────────────────────────────────────

    /** Case 1 — posted once, never reposted: the original line is found. Control for cases 2-3. */
    public function test_case1_no_repost_uses_the_original_line(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->makeInvoice();
        $this->postSource(0.310, 'chain:c1', $payment->id);
        $this->postInvoiceDoc(0.300, 'chain-inv:c1', $invoice->id);

        $doc = $this->apply($payment, $invoice, 91001);

        $this->assertNotNull($doc);
        // credit-sourced, D = 100*(0.310-0.300) = +1.000 -> GAIN, party Dr.
        $this->assertSame(['debit', 1.000], $this->partyLineOf($doc));
    }

    /**
     * Case 2 — edited once: the REPLACEMENT is found, never the reversed original and never the
     * REV document. Discriminating by rate: original 0.310 -> 1.000 Dr; replacement 0.320 ->
     * 2.000 Dr; the REV document's own CLIENT_ADVANCE line is a DEBIT at 0.310, which would make
     * the resolver see a debit-sourced line and flip the sign (1.000 Cr).
     */
    public function test_case2_edited_once_resolves_the_replacement_not_the_reversal(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->makeInvoice();
        $original = $this->postSource(0.310, 'chain:c2', $payment->id);
        $this->postInvoiceDoc(0.300, 'chain-inv:c2', $invoice->id);

        $this->repostSource($original->transaction, 0.320);

        $doc = $this->apply($payment, $invoice, 91002);

        $this->assertNotNull($doc, 'a reposted receipt must still produce its realised FX');
        $this->assertSame(
            ['debit', 2.000],
            $this->partyLineOf($doc),
            'must use the REPLACEMENT rate 0.320 (2.000 Dr) — not the reversed original 0.310 (1.000 Dr), '
            .'and not the REV document\'s mirrored debit line (which would flip the side)'
        );
    }

    /**
     * Case 3 — edited twice: the LIVE (second) replacement wins, i.e. the walk follows the chain
     * for more than one hop. Keys nest: K -> K:repost:{O} -> K:repost:{O}:repost:{R1}.
     */
    public function test_case3_edited_twice_resolves_the_live_end_of_the_chain(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->makeInvoice();
        $original = $this->postSource(0.310, 'chain:c3', $payment->id);
        $this->postInvoiceDoc(0.300, 'chain-inv:c3', $invoice->id);

        $first = $this->repostSource($original->transaction, 0.320);
        $second = $this->repostSource($first->transaction->fresh(), 0.330);

        $this->assertSame(
            'chain:c3:repost:'.$original->transaction->id.':repost:'.$first->transaction->id,
            (string) $second->transaction->idempotency_key,
            'precondition: repost() nests its suffix when the caller passes $old\'s own key'
        );

        $doc = $this->apply($payment, $invoice, 91003);

        $this->assertNotNull($doc);
        $this->assertSame(
            ['debit', 3.000],
            $this->partyLineOf($doc),
            'two hops: must land on the LIVE replacement (0.330 -> 3.000), not the first one (2.000)'
        );
    }

    /** Case 4 — edited, then the replacement reversed outright: nothing live, so skip cleanly. */
    public function test_case4_edited_then_fully_reversed_skips_without_throwing(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->makeInvoice();
        $original = $this->postSource(0.310, 'chain:c4', $payment->id);
        $this->postInvoiceDoc(0.300, 'chain-inv:c4', $invoice->id);

        $replacement = $this->repostSource($original->transaction, 0.320);
        app(PostingService::class)->reverse($replacement->transaction->fresh(), now(), null);

        $this->assertNull($this->apply($payment, $invoice, 91004), 'a voided chain must skip, not guess');
        $this->assertSame(0, $this->fxrCount(), 'and must reserve no FXR document number');
    }

    /** Case 5 — two payments reposted side by side: neither chain leaks into the other. */
    public function test_case5_two_crossing_chains_do_not_contaminate_each_other(): void
    {
        $paymentA = $this->makePayment();
        $paymentB = $this->makePayment();
        $invoice = $this->makeInvoice();
        $this->postInvoiceDoc(0.300, 'chain-inv:c5', $invoice->id);

        $originalA = $this->postSource(0.310, 'chain:c5a', $paymentA->id);
        $originalB = $this->postSource(0.350, 'chain:c5b', $paymentB->id);

        // Interleaved reposts, so the walk cannot rely on id ordering.
        $this->repostSource($originalA->transaction, 0.320);
        $this->repostSource($originalB->transaction, 0.360);

        $docA = $this->apply($paymentA, $invoice, 91005);
        $docB = $this->apply($paymentB, $invoice, 91006);

        $this->assertNotNull($docA);
        $this->assertNotNull($docB);
        $this->assertSame(['debit', 2.000], $this->partyLineOf($docA), 'A must use A\'s own replacement (0.320)');
        $this->assertSame(['debit', 6.000], $this->partyLineOf($docB), 'B must use B\'s own replacement (0.360)');
    }

    /** Case 6 — the replacement exists but is not itself posted: skip, never post off a draft. */
    public function test_case6_a_non_posted_replacement_is_skipped(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->makeInvoice();
        $original = $this->postSource(0.310, 'chain:c6', $payment->id);
        $this->postInvoiceDoc(0.300, 'chain-inv:c6', $invoice->id);

        $replacement = $this->repostSource($original->transaction, 0.320);

        Transaction::withoutGlobalScopes()
            ->whereKey($replacement->transaction->id)
            ->update(['posting_status' => 'draft']);

        $this->assertNull($this->apply($payment, $invoice, 91007), 'a draft replacement is not a live source');
        $this->assertSame(0, $this->fxrCount());
    }

    /**
     * Case 7 — a malformed, over-long chain must TERMINATE (the walk is hop-bounded) and skip.
     * A true cycle is not constructible: every hop's key is its predecessor's key plus a
     * non-empty ':repost:{id}' suffix, so keys grow strictly and can never revisit one. The
     * bound is the defence against a chain that is merely absurd. The test completing at all is
     * the termination proof.
     */
    public function test_case7_an_overlong_malformed_chain_terminates_and_skips(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->makeInvoice();
        $this->postInvoiceDoc(0.300, 'chain-inv:c7', $invoice->id);

        $key = 'chain:c7';
        $head = $this->postSource(0.310, $key, $payment->id)->transaction;
        $ids = [$head->id];

        // 12 further links, each keyed by the convention off its predecessor — longer than the
        // resolver's 10-hop bound.
        $previousId = $head->id;
        for ($i = 0; $i < 12; $i++) {
            $key = $key.':repost:'.$previousId;
            $next = $this->postSource(0.310 + ($i / 1000), $key)->transaction;
            $ids[] = $next->id;
            $previousId = $next->id;
        }

        // Every link 'reversed' — so the walk can never short-circuit on a posted node.
        Transaction::withoutGlobalScopes()->whereIn('id', $ids)->update(['posting_status' => 'reversed']);

        $this->assertNull($this->apply($payment, $invoice, 91008), 'an unfollowable chain skips');
        $this->assertSame(0, $this->fxrCount());
    }

    // ── B. The SAME blindness on the invoice side ───────────────────────────────────────────

    /**
     * The applied (INVOICE) line is resolved by `journal_entries.invoice_id` + RECEIVABLE_CONTROL
     * + `transactions.doc_type = 'INV'`, ordered by journal-entry id, EARLIEST first. After a
     * repost of the invoice's own sale document the reversed ORIGINAL keeps its lines, keeps
     * `invoice_id`, keeps `doc_type = 'INV'` and holds the LOWEST line id — so "earliest wins"
     * silently returns the DEAD row and the whole realised-FX calculation runs against a rate
     * that is no longer the invoice's. (The reversal itself is `doc_type = 'REV'`, so it is
     * correctly excluded; the reversed original is not.)
     *
     * Discriminating oracle: source credit USD 100 @ 0.310.
     *   live INV rate 0.320 -> D = -1.000 -> credit-sourced LOSS  -> party Cr, leaf 5219
     *   dead INV rate 0.300 -> D = +1.000 -> credit-sourced GAIN  -> party Dr, leaf 4139
     * The two answers differ in side, sign AND leaf — nothing coincidental can pass this.
     */
    public function test_applied_line_uses_the_live_invoice_document_after_a_repost(): void
    {
        $payment = $this->makePayment();
        $invoice = $this->makeInvoice();
        $this->postSource(0.310, 'inv-chain:src', $payment->id);
        $invDoc = $this->postInvoiceDoc(0.300, 'inv-chain:inv', $invoice->id);

        app(PostingService::class)->repost(
            $invDoc->transaction,
            $this->invoiceDraft(0.320, (string) $invDoc->transaction->idempotency_key, $invoice->id),
            now(),
            null
        );

        $doc = $this->apply($payment, $invoice, 91009);

        $this->assertNotNull($doc);
        $this->assertSame(
            ['credit', 1.000],
            $this->partyLineOf($doc),
            'the applied rate must come from the LIVE INV document (0.320 -> a LOSS, party Cr), '
            .'never from the reversed original still sitting on the lowest journal-entry id (0.300 -> a GAIN)'
        );
        $this->assertSame('5219', $this->fxLeafCodeOf($doc), 'FX_LOSS_REALISED, not FX_GAIN_REALISED');
    }

    /**
     * The identical read in {@see CreditApplicationDraftBuilder::resolvePostedInvoiceRate()} —
     * the F1/Q2 fix that makes the apply JV clear the receivable at the invoice's POSTED rate.
     * Reading the dead original re-opens exactly the residual that fix was written to close, and
     * it does so silently (the distinct 'rate_fallback_live_lookup' log never fires, because a
     * row WAS found — just the wrong one).
     *
     * The CurrencyExchange row carries a third, unrelated rate so a pass cannot come from the
     * live table either.
     */
    public function test_credit_apply_jv_uses_the_live_invoice_rate_after_a_repost(): void
    {
        $invoice = $this->makeInvoice();
        $invDoc = $this->postInvoiceDoc(0.300, 'builder-chain:inv', $invoice->id);

        CurrencyExchange::create([
            'company_id' => $this->companyId,
            'base_currency' => 'USD',
            'exchange_currency' => (string) config('accounting.engine.base_currency'),
            'exchange_rate' => 0.500,
            'is_manual' => true,
        ]);

        app(PostingService::class)->repost(
            $invDoc->transaction,
            $this->invoiceDraft(0.320, (string) $invDoc->transaction->idempotency_key, $invoice->id),
            now(),
            null
        );

        $draft = (new CreditApplicationDraftBuilder())->build(
            invoice: $invoice,
            applications: [new CreditApplicationInput(
                idSource: CreditApplicationInput::SOURCE_PAYMENT_APPLICATION,
                id: 91010,
                amountApplied: 100.0,
            )],
            callerTotalAmount: 100.0,
            companyId: $this->companyId,
        );

        $this->assertEqualsWithDelta(
            0.320,
            $draft->lines[0]->exchangeRate,
            0.000001,
            'the apply JV must clear the receivable at the LIVE posted invoice rate (0.320) — not the '
            .'reversed original (0.300) and not the live rate table (0.500)'
        );
    }
}
