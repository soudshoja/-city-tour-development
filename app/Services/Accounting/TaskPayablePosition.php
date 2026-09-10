<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * CT-A3 R3 (owner ruling **R-CT8**) — where ONE task's supplier payable currently sits, as a value.
 *
 * Produced only by {@see TaskPayablePositionResolver}. Three facts, and nothing else:
 *
 *  - `accountId`   — the LEAF the payable is on right now. The purpose-resolved
 *                    `SERVICE_PAYABLE`/{type} control when the task was never reassigned; the
 *                    nominated payee account once the who-to-pay screen has moved it.
 *  - `partyRef` / `partyName` — the party the position is attributed to (the supplier: a
 *                    reassignment moves the LEAF, never the party — see
 *                    `TaskController::postSupplierReassignDocument()`, which passes the task's own
 *                    supplier as the destination party).
 *  - `amount`      — the net CREDIT currently standing on that leaf for this task, from posted
 *                    journal rows. Positive means "still owed". Never read from
 *                    `accounts.actual_balance` or `journal_entries.balance` (CT-A1 §4.1 measured
 *                    Σ|drift| KWD 6,277,563.301 across 200 of 207 posted accounts).
 *
 * `isNominated` records WHY the leaf is what it is, so a caller (and a log line) can distinguish
 * "the control, because nobody nominated anyone" from "the control, because the nomination happens
 * to point at it".
 */
final class TaskPayablePosition
{
    public function __construct(
        public readonly int $taskId,
        public readonly int $companyId,
        public readonly int $accountId,
        public readonly float $amount,
        public readonly bool $isNominated,
        public readonly ?int $partyRef = null,
        public readonly ?string $partyName = null,
    ) {}

    /** @return array<string, mixed> */
    public function toLogContext(): array
    {
        return [
            'task_id' => $this->taskId,
            'company_id' => $this->companyId,
            'payable_account_id' => $this->accountId,
            'payable_amount' => $this->amount,
            'payable_is_nominated' => $this->isNominated,
            'payable_party_ref' => $this->partyRef,
        ];
    }
}
