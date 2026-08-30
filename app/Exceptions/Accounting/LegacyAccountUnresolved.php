<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * W3e (item 4, w3e-brief.md): thrown by InvoiceController::postSaleJournalEntries()'s LEGACY
 * closure when the "Direct Income" parent account it needs (to auto-create a missing
 * "{Type} Booking Revenue" leaf under) does not exist for this company.
 *
 * Before this exception existed, a missing "Direct Income" account degraded SILENTLY rather than
 * failing loudly: `$directIncomeParent` (an Account::first() result) was dereferenced unguarded
 * a few lines below (`$directIncomeParent->id`, `->root_id`) — PHP 8's read of a property on
 * `null` is an E_WARNING, not a thrown error, and evaluates to `null`, so execution continued
 * past it. Because `accounts.parent_id`/`accounts.root_id` are both nullable at the schema level,
 * the resulting `Account::create([...'parent_id' => null, 'root_id' => null...])` did not even
 * fail at the database — it silently inserted a genuinely ORPHANED chart-of-accounts leaf (no
 * parent, no root) that no COA tree view or report would ever place correctly. This exception
 * makes that failure loud instead: a company whose chart of accounts is missing its "Direct
 * Income" parent is a data-setup problem to fix (CoaSeeder has not run for this company, or ran
 * against different account names), not something to silently paper over with an orphan leaf.
 *
 * Deliberately NOT a {@see PostingException} subclass: this fires only inside the LEGACY closure
 * (the OFF path, or the ON path's own legacy-race fallback — see {@see PostingSeam}'s class
 * docblock for that race), never inside the engine pipeline itself, so it does not belong to that
 * sealed family.
 */
final class LegacyAccountUnresolved extends \Exception
{
    public function __construct(string $accountName, int $companyId)
    {
        parent::__construct(sprintf(
            "Legacy posting could not resolve required account '%s' for company #%d -- the chart of accounts is missing this control account (CoaSeeder may not have run for this company).",
            $accountName,
            $companyId
        ));
    }
}
