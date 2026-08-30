<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by AccountResolver::assertUnderBankGroup() (W5.L, w5-brief.md §W5.L item 4: "bank leaf on
 * a voucher is passed by account id and validated to sit under the BANK group") when the account id
 * a caller (a future RV/PV feeder, W5.R/W5.P) names as "the bank leaf this voucher pays into/out of"
 * exists, belongs to the right company, and is a real leaf, but does NOT have the expected group
 * account (`config('accounting.engine.bank_group_name')`, 'Bank Accounts' in the seed COA) anywhere
 * in its ancestor chain — e.g. a caller accidentally passing a Cash In Hand leaf, a Clients
 * receivable leaf, or any other unrelated account id where a bank leaf was required.
 *
 * Generic by design (not "NotUnderBankGroupException"): the same structural check — "this leaf's
 * ancestor chain must include a specific named group somewhere above it" — has no reason to be
 * bank-specific if a later wave needs the identical shape for a different group; the group name
 * involved is always carried on the exception itself so a bank-specific catcher can still assert it.
 */
final class AccountNotUnderGroupException extends PostingException
{
    public function __construct(
        public readonly int $accountId,
        public readonly ?string $accountName,
        public readonly string $expectedGroupName,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "Account #%d (%s) is not under the '%s' group.",
            $this->accountId,
            $this->accountName ?? 'unknown',
            $this->expectedGroupName
        ));
    }
}
