<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * accounting-builds Wave 3 lane I, item A1 (T10 §12 / Lane B sign-off finding): thrown by
 * {@see \App\Services\Accounting\AccountResolver::assertUnderBankGroup()} when a bank leaf that
 * otherwise passes every structural check (exists, same tenant, not disabled, a genuine leaf,
 * under the Bank Accounts group) carries an `accounts.currency` that does not match the
 * currency of the document being posted.
 *
 * Every caller of assertUnderBankGroup() today (FixedAssetService::capitalise()/dispose(),
 * GatewaySettlementService::record(), the BankPaymentController/ReceiptVoucherController/
 * CreditController voucher feeders) builds every line with a hardcoded `exchangeRate: 1.0` and
 * no rate input anywhere on its HTTP/CLI surface — exactly the same fabricated-parity risk
 * {@see GatewaySettlementCurrencyMismatchException} already closes off for a settlement's own
 * `currency` field. Without this check, a USD-denominated bank leaf accepted for a KWD document
 * (or vice versa) would silently book the document's base-currency figure into a foreign-currency
 * leaf at parity — nothing upstream re-derives or checks a rate for this path. Refusing here,
 * at the single shared resolution point, closes the gap for every caller at once rather than
 * requiring each feeder to re-implement the same comparison.
 */
final class BankLeafCurrencyMismatchException extends PostingException
{
    public function __construct(
        public readonly int $accountId,
        public readonly ?string $accountName,
        public readonly string $accountCurrency,
        public readonly string $documentCurrency,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "Account #%d (%s) is denominated in '%s', but the document being posted is in '%s' — "
            .'no exchange-rate input exists on this posting path, so booking it here would record '
            .'the %s figure into a %s leaf at a fabricated 1.0 rate. Choose a bank leaf denominated '
            .'in %s, or convert the document to %s before submitting.',
            $this->accountId,
            $this->accountName ?? 'unknown',
            $this->accountCurrency,
            $this->documentCurrency,
            $this->documentCurrency,
            $this->accountCurrency,
            $this->documentCurrency,
            $this->accountCurrency,
        ));
    }
}
