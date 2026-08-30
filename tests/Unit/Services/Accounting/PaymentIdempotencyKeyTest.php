<?php

namespace Tests\Unit\Services\Accounting;

use App\Services\Accounting\CreditApplicationInput;
use App\Services\Accounting\PaymentIdempotencyKey;
use Tests\Support\AccountingTestCase;

/**
 * W2 build (Task B / D2 — shared idempotency-key derivation for gateway-payment feeders, so a
 * webhook handler and a status-check cron compute the SAME key for the SAME business event).
 * Pure static-method pins; no DocumentDraft/PostingService involvement.
 */
class PaymentIdempotencyKeyTest extends AccountingTestCase
{
    public function test_same_inputs_produce_the_same_key(): void
    {
        $first = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [3, 1, 2]);
        $second = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [3, 1, 2]);

        $this->assertSame($first, $second);
    }

    public function test_partial_id_order_is_irrelevant(): void
    {
        $ascending = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [1, 2, 3]);
        $descending = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [3, 2, 1]);
        $shuffled = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [2, 3, 1]);

        $this->assertSame($ascending, $descending);
        $this->assertSame($ascending, $shuffled);
    }

    public function test_different_partial_sets_produce_different_keys(): void
    {
        $twoPartials = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [1, 2]);
        $threePartials = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [1, 2, 3]);
        $otherPartials = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [4, 5]);

        $this->assertNotSame($twoPartials, $threePartials);
        $this->assertNotSame($twoPartials, $otherPartials);
        $this->assertNotSame($threePartials, $otherPartials);
    }

    public function test_null_and_empty_partial_sets_both_mean_no_partials(): void
    {
        $null = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, null);
        $empty = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, []);

        $this->assertSame($null, $empty);
        $this->assertStringEndsWith(':partials:none', $null);
    }

    public function test_different_payments_produce_different_keys(): void
    {
        $paymentOne = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 1, null);
        $paymentTwo = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 2, null);

        $this->assertNotSame($paymentOne, $paymentTwo);
    }

    public function test_gateway_name_is_normalised_case_insensitively(): void
    {
        $lower = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, null);
        $upper = PaymentIdempotencyKey::forGatewayPayment('MyFatoorah', 42, null);
        $shouting = PaymentIdempotencyKey::forGatewayPayment('MYFATOORAH', 42, null);

        $this->assertSame($lower, $upper);
        $this->assertSame($lower, $shouting);
    }

    public function test_different_gateways_produce_different_keys_for_the_same_payment_id(): void
    {
        $myfatoorah = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, null);
        $tap = PaymentIdempotencyKey::forGatewayPayment('tap', 42, null);

        $this->assertNotSame($myfatoorah, $tap);
    }

    public function test_matches_the_documented_key_shape(): void
    {
        $this->assertSame(
            'gateway:myfatoorah:payment:42:partials:1,2,3',
            PaymentIdempotencyKey::forGatewayPayment('MyFatoorah', 42, [3, 1, 2])
        );
    }

    /**
     * Residual 16 fix: before array_unique() was added, [1, 1, 2] and [1, 2] produced DIFFERENT
     * key strings ("1,1,2" vs "1,2") despite representing the identical SET of partials — a
     * direct contradiction of this class's own docblock ("the business event is 'this payment
     * settled this SET of partials'"). Unreachable via any of today's nine call sites (none pass
     * duplicate ids), but a genuine SET-semantics violation the class's own contract promises
     * never happens. Revert the array_unique() call to see this go red.
     */
    public function test_duplicate_partial_ids_are_deduplicated_before_the_key_is_built(): void
    {
        $withDuplicate = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [1, 1, 2]);
        $withoutDuplicate = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', 42, [1, 2]);

        $this->assertSame($withoutDuplicate, $withDuplicate);
        $this->assertSame('gateway:myfatoorah:payment:42:partials:1,2', $withDuplicate);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // forCreditApplication() — W2b build (KEY: draft-builder, design call E2), source-namespaced
    // W2c (orchestrator ruling B-2). $applications elements here are plain [source, id] pairs —
    // the method's own documented alternative to a full CreditApplicationInput — to keep these
    // pins independent of that class's own constructor validation.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function pa(int $id): array
    {
        return [CreditApplicationInput::SOURCE_PAYMENT_APPLICATION, $id];
    }

    private function partial(int $id): array
    {
        return [CreditApplicationInput::SOURCE_PARTIAL, $id];
    }

    public function test_credit_application_same_inputs_produce_the_same_key(): void
    {
        $first = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(3), $this->pa(1), $this->pa(2)]);
        $second = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(3), $this->pa(1), $this->pa(2)]);

        $this->assertSame($first, $second);
    }

    public function test_credit_application_id_order_is_irrelevant(): void
    {
        $ascending = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(1), $this->pa(2), $this->pa(3)]);
        $descending = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(3), $this->pa(2), $this->pa(1)]);
        $shuffled = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(2), $this->pa(3), $this->pa(1)]);

        $this->assertSame($ascending, $descending);
        $this->assertSame($ascending, $shuffled);
    }

    public function test_credit_application_different_id_sets_produce_different_keys(): void
    {
        $twoIds = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(1), $this->pa(2)]);
        $threeIds = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(1), $this->pa(2), $this->pa(3)]);
        $otherIds = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(4), $this->pa(5)]);

        $this->assertNotSame($twoIds, $threeIds);
        $this->assertNotSame($twoIds, $otherIds);
        $this->assertNotSame($threeIds, $otherIds);
    }

    public function test_credit_application_different_invoices_produce_different_keys_for_the_same_id_set(): void
    {
        $invoiceOne = PaymentIdempotencyKey::forCreditApplication(1, [$this->pa(1), $this->pa(2)]);
        $invoiceTwo = PaymentIdempotencyKey::forCreditApplication(2, [$this->pa(1), $this->pa(2)]);

        $this->assertNotSame($invoiceOne, $invoiceTwo);
    }

    public function test_credit_application_matches_the_documented_key_shape(): void
    {
        $this->assertSame(
            'credit-apply:invoice:9:pa:1,2,3',
            PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(3), $this->pa(1), $this->pa(2)])
        );
    }

    public function test_credit_application_partial_source_matches_the_documented_key_shape(): void
    {
        $this->assertSame(
            'credit-apply:invoice:9:partial:1,2,3',
            PaymentIdempotencyKey::forCreditApplication(9, [$this->partial(3), $this->partial(1), $this->partial(2)])
        );
    }

    /**
     * Mirrors forGatewayPayment()'s own residual-16 pin: a set has no notion of a repeated
     * member, so [1, 1, 2] and [1, 2] must produce the identical key.
     */
    public function test_credit_application_duplicate_ids_are_deduplicated_before_the_key_is_built(): void
    {
        $withDuplicate = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(1), $this->pa(1), $this->pa(2)]);
        $withoutDuplicate = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(1), $this->pa(2)]);

        $this->assertSame($withoutDuplicate, $withDuplicate);
        $this->assertSame('credit-apply:invoice:9:pa:1,2', $withDuplicate);
    }

    /**
     * W2c fix (B-2, the actual regression): a 'pa'-sourced id and a 'partial'-sourced id that are
     * numerically EQUAL must key into completely different namespaces — this is the exact
     * collision that silently dropped a real second credit-application event in W2b
     * (`InvoiceController` supplied `invoice_partials.id`, `PaymentApplicationService` supplied
     * `payment_applications.id`, and small ids from the two independent AUTO_INCREMENT sequences
     * coincided routinely). Named to mirror the orchestrator brief's own phrasing: "IC-partial
     * key != PAS-pa key for equal numeric ids".
     */
    public function test_ic_partial_key_differs_from_pas_pa_key_for_equal_numeric_ids(): void
    {
        $icPartialKey = PaymentIdempotencyKey::forCreditApplication(2, [$this->partial(5)]);
        $pasPaKey = PaymentIdempotencyKey::forCreditApplication(2, [$this->pa(5)]);

        $this->assertNotSame($icPartialKey, $pasPaKey);
        $this->assertSame('credit-apply:invoice:2:partial:5', $icPartialKey);
        $this->assertSame('credit-apply:invoice:2:pa:5', $pasPaKey);
    }

    public function test_credit_application_mixed_source_set_throws_a_typed_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(1), $this->partial(2)]);
    }

    public function test_credit_application_requires_at_least_one_application(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentIdempotencyKey::forCreditApplication(9, []);
    }

    public function test_credit_application_accepts_real_credit_application_input_instances(): void
    {
        $viaInput = PaymentIdempotencyKey::forCreditApplication(9, [
            new CreditApplicationInput(idSource: CreditApplicationInput::SOURCE_PAYMENT_APPLICATION, id: 3, amountApplied: 10.0),
            new CreditApplicationInput(idSource: CreditApplicationInput::SOURCE_PAYMENT_APPLICATION, id: 1, amountApplied: 10.0),
        ]);
        $viaPair = PaymentIdempotencyKey::forCreditApplication(9, [$this->pa(3), $this->pa(1)]);

        $this->assertSame($viaPair, $viaInput);
    }
}
