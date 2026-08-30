<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * W5.P (w5-brief.md §W5.P: "Bank balance pre-check -> TrialBalanceService read INSIDE the posting
 * transaction, refusing when pv_allow_overdraft=false and balance would go negative"). Thrown by
 * BankPaymentController::assertOverdraftAllowed() — replaces HEAD's raw `SUM(debit)-SUM(credit)`
 * TOCTOU pre-check (w5-state.md §1 "PV document" row) with a locked, in-transaction read via
 * {@see \App\Services\TrialBalanceService::getCurrentAccountBalance()}.
 *
 * Deliberately a {@see PostingException} subtype even though it is thrown BEFORE
 * {@see \App\Services\Accounting\PostingSeam::post()} is ever called (this is a plain business-rule
 * refusal the controller enforces identically on the OFF and ON path, not an engine-contract
 * failure PostingService itself would detect) — it shares the same "balanced or rejected, never
 * silently continue" family every other posting refusal in this codebase belongs to, and
 * BankPaymentController's own `catch (PostingException $e)` block already handles every member of
 * that family uniformly.
 */
final class InsufficientBankBalanceException extends PostingException
{
    public function __construct(
        public readonly int $accountId,
        public readonly float $currentBalance,
        public readonly float $requiredOutflow,
        public readonly float $projectedBalance,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Insufficient bank balance on account #%d: current KWD %.3f, required KWD %.3f, '
            .'would leave KWD %.3f (overdraft not allowed for this company).',
            $this->accountId,
            $this->currentBalance,
            $this->requiredOutflow,
            $this->projectedBalance
        ));
    }
}
