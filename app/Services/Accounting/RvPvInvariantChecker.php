<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Transaction;

/**
 * W5.X (w5-brief.md §W5.X item 4): the read-only invariant logic behind `php artisan
 * accounting:verify` ({@see \App\Console\Commands\AccountingVerify}), split into its own class so
 * it is directly unit/feature-testable against its RETURN VALUE — this repo's own established
 * convention for command-backed logic (see EnsureSystemLeavesTest's own docblock: `Tests\TestCase::
 * setUp()`'s `$this->artisan('db:seed', ...)` permanently rebinds the console output mock for the
 * rest of any RefreshDatabase test, so `Artisan::output()` reads empty regardless of what a command
 * actually printed — "DB state, not console text" is the rule this class follows by being callable,
 * and assertable, without going through Artisan at all).
 *
 * Checks exactly the three invariants the brief names, nothing more:
 *   1. Every RV/PV document is balanced (Σdebit == Σcredit within tolerance).
 *   2. No RV/PV line sits on a non-cash/bank leaf without a cash/bank counter-leg somewhere on the
 *      same document ({@see AccountResolver::isCashOrBankLeaf()}).
 *   3. Every RV/PV journal_entries row carries a serial (`voucher_number`).
 */
final class RvPvInvariantChecker
{
    public function __construct(private readonly AccountResolver $accountResolver) {}

    /**
     * @return array{checked: int, violations: string[]}
     */
    public function check(?int $companyId = null): array
    {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        $query = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('doc_type', ['RV', 'PV'])
            ->with(['journalEntries' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')
                ->with(['account' => fn ($aq) => $aq->withoutGlobalScopes()])]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $violations = [];
        $checked = 0;

        $query->orderBy('id')->chunkById(200, function ($transactions) use (&$violations, &$checked, $tolerance) {
            foreach ($transactions as $transaction) {
                $checked++;
                $violations = array_merge($violations, $this->checkTransaction($transaction, $tolerance));
            }
        });

        return ['checked' => $checked, 'violations' => $violations];
    }

    /**
     * @return string[]
     */
    private function checkTransaction(Transaction $transaction, float $tolerance): array
    {
        $violations = [];
        $lines = $transaction->journalEntries;
        $label = sprintf('%s #%d (reference_number=%s)', $transaction->doc_type, $transaction->id, $transaction->reference_number ?? 'NULL');

        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) $line->debit;
            $credit += (float) $line->credit;
        }

        if (abs($debit - $credit) > $tolerance) {
            $violations[] = sprintf(
                '%s is NOT balanced: total_debit=%s, total_credit=%s (diff=%s).',
                $label,
                number_format($debit, 3),
                number_format($credit, 3),
                number_format(abs($debit - $credit), 3)
            );
        }

        $hasCashOrBankLine = false;
        $hasNonCashOrBankLine = false;

        foreach ($lines as $line) {
            if ($line->account === null) {
                continue;
            }

            if ($this->accountResolver->isCashOrBankLeaf($line->account)) {
                $hasCashOrBankLine = true;
            } else {
                $hasNonCashOrBankLine = true;
            }
        }

        if ($hasNonCashOrBankLine && ! $hasCashOrBankLine) {
            $violations[] = sprintf('%s has a line on a non-cash/bank leaf with no cash/bank counter-leg anywhere on the document.', $label);
        }

        foreach ($lines as $line) {
            if ($line->voucher_number === null || trim((string) $line->voucher_number) === '') {
                $violations[] = sprintf('%s has journal_entries.id=%d with no voucher_number (serial).', $label, $line->id);
            }
        }

        return $violations;
    }
}
