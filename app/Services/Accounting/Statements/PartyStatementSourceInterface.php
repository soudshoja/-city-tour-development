<?php

declare(strict_types=1);

namespace App\Services\Accounting\Statements;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P2.5.H (p2_5-brief.md §P2.5.H). One data source per party type (client/supplier/agent) --
 * {@see \App\Services\Accounting\StatementService} picks the right implementation and never
 * touches the underlying model shape itself.
 *
 * Every implementation returns the FULL set of documents for a party as of a date (both settled
 * and unsettled -- the caller, not the source, decides which mode's filter to apply), plus a
 * separate list of unapplied receipts/credits sitting against the same party with nothing
 * consumed yet. Never resolves an account by name; never reads `accounts.actual_balance` or
 * `journal_entries.balance`.
 */
interface PartyStatementSourceInterface
{
    /**
     * @return Collection<int, StatementItem> Every document (kind='document') dated on/before
     *                                        $asOf, oldest first, whether settled or not.
     */
    public function documents(int $companyId, int $partyId, Carbon $asOf): Collection;

    /**
     * @return Collection<int, StatementItem> Unapplied receipts/credits (kind='unapplied') sitting
     *                                        against this party as of $asOf, oldest first.
     */
    public function unapplied(int $companyId, int $partyId, Carbon $asOf): Collection;
}
