<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * CT-A3 R3 (owner ruling **R-CT8**) — "send any payable leg that would land on one of THESE leaves
 * to THIS leaf instead", as a value object.
 *
 * {@see PostingService::reverse()} rebuilds a reversal from the ORIGINAL document's own posted
 * lines, by explicit `account_id`. That is exactly right for every reversal except one: a supplier
 * accrual whose payable has since been moved by the who-to-pay screen. Reversing that document
 * verbatim debits the leaf the accrual credited — the purpose-resolved control — while the money
 * itself is sitting on the nominated payee, which is finding **V2** of
 * `VERIFY-CT-A3-STACK-R2-2026-09-10.md`: the AP group nets to zero (so the trial balance foots and
 * no aggregate check can see it) while each leaf is individually wrong by the full supplier cost.
 *
 * This carries the redirect WITHOUT teaching {@see PostingService} anything about who-to-pay:
 * the accounting engine is told "accounts A, B, C ⇒ account D", and
 * {@see TaskPayablePositionResolver::redirectFor()} is the only thing that decides what A, B, C
 * and D are. `$fromAccountIds` is the company's Accounts Payable subtree minus the destination, so
 * a reversal whose payable leg is ALREADY on the destination (a second void after an un-void, say)
 * is untouched by construction rather than by a special case.
 */
final class TaskPayableRedirect
{
    /** @var array<int, true> */
    private readonly array $fromIndex;

    /**
     * @param  int[]  $fromAccountIds  accounts whose lines are redirected (the destination is never one of them)
     */
    public function __construct(
        public readonly int $toAccountId,
        array $fromAccountIds,
        public readonly ?int $partyRef = null,
        public readonly ?string $partyName = null,
    ) {
        $index = [];

        foreach ($fromAccountIds as $id) {
            $id = (int) $id;

            if ($id !== $toAccountId) {
                $index[$id] = true;
            }
        }

        $this->fromIndex = $index;
    }

    public function applies(int $accountId): bool
    {
        return isset($this->fromIndex[$accountId]);
    }

    /** @return int[] */
    public function fromAccountIds(): array
    {
        return array_map('intval', array_keys($this->fromIndex));
    }
}
