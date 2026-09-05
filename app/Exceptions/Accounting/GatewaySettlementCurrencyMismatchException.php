<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Post-sign-off fix (T7, review packet §12 finding 3 — Fable orchestrator sign-off, 2026-09-02):
 * a gateway settlement whose `currency` is not the company's base currency
 * (`config('accounting.engine.base_currency')`, KWD) must be refused before anything is saved.
 *
 * {@see \App\Services\Accounting\GatewaySettlementService::post()} builds every line with
 * `exchangeRate: 1.0` and `originalAmount` equal to the settlement's own base-currency figure —
 * there is no rate input anywhere on the HTTP/CLI/CSV surfaces that record a settlement, so a
 * non-base currency would silently book a foreign-currency figure into the base ledger at parity
 * (e.g. a USD 900 payout posted as if it were KWD 900). `PostingService` step 3f accepts that
 * shape as a perfectly consistent single-currency line — it has no way to know the exchange rate
 * is fabricated. Refusing pre-flight, before the `gateway_settlements` row is even created, keeps
 * this in the same family as {@see GatewayOverSettledException} /
 * {@see GatewaySettlementUnmatchedException}: nothing saved, nothing posted, the payout reference
 * stays free for a corrected re-record once a real rate input exists.
 */
final class GatewaySettlementCurrencyMismatchException extends PostingException
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $payoutReference,
        public readonly string $requestedCurrency,
        public readonly string $baseCurrency,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "Settlement for gateway '%s' payout reference '%s' reports currency '%s', but the "
            ."company's base currency is '%s' and no exchange-rate input exists for gateway "
            .'settlements — posting it would book the %s figure into the ledger as %s at a '
            .'fabricated 1.0 rate. Record the settlement in %s, or convert it to %s before '
            .'submitting.',
            $this->gateway,
            $this->payoutReference,
            $this->requestedCurrency,
            $this->baseCurrency,
            $this->requestedCurrency,
            $this->baseCurrency,
            $this->baseCurrency,
            $this->baseCurrency,
        ));
    }
}
