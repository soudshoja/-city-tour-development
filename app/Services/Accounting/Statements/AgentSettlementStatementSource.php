<?php

declare(strict_types=1);

namespace App\Services\Accounting\Statements;

use App\Models\AgentSettlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P2.5.H — agent statement source. `AgentSettlement` (`total_amount`/`paid_amount`/
 * `remaining_amount`, `settlement_date`) is already a document-level open-item model, exactly
 * analogous to Invoice+PaymentApplication one wave over -- real, populated, tested via
 * {@see \App\Services\AgentSettlementService} elsewhere in this codebase.
 */
final class AgentSettlementStatementSource implements PartyStatementSourceInterface
{
    public function documents(int $companyId, int $partyId, Carbon $asOf): Collection
    {
        return AgentSettlement::query()
            ->where('agent_id', $partyId)
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->where('settlement_date', '<=', $asOf->copy()->endOfDay())
            ->orderBy('settlement_date')
            ->orderBy('id')
            ->get()
            ->map(function (AgentSettlement $settlement) {
                $amount = (float) $settlement->total_amount;
                $settled = (float) $settlement->paid_amount;

                return new StatementItem(
                    kind: 'document',
                    documentType: 'agent_settlement',
                    documentId: $settlement->id,
                    documentNumber: (string) ($settlement->settlement_number ?? ('AS-'.$settlement->id)),
                    documentDate: Carbon::parse($settlement->settlement_date),
                    dueDate: null,
                    amount: $amount,
                    settledAmount: min($settled, $amount),
                    description: $settlement->notes ?: 'Agent settlement '.($settlement->settlement_number ?? $settlement->id),
                );
            })
            ->values();
    }

    /**
     * No analogous "unapplied receipt" pool exists for agents today -- `AgentSettlement.
     * paid_amount` already nets every {@see \App\Models\AgentSettlementPayment} applied against
     * it at the header level (unlike the client side, where a Payment/Credit can sit entirely
     * unapplied against no invoice at all). Documented design decision, not an omission.
     */
    public function unapplied(int $companyId, int $partyId, Carbon $asOf): Collection
    {
        return collect();
    }
}
