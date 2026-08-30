<?php

declare(strict_types=1);

namespace App\Services\Accounting\Statements;

use App\Models\JournalEntry;
use App\Services\Accounting\AccountResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P2.5.H — supplier statement source. Unlike the client/agent sides, this codebase has no
 * document-level "supplier bill" model yet (verified: no SupplierInvoice/equivalent, and
 * `BankPayment` -- the PV document, W5.P -- targets an account, never a specific payable
 * document). Doc 11 §P5.3's party-master FKs and real open-item apply engine (the mechanism the
 * brief's own wording "computed from ledger open items via the apply engine (P5.3
 * settled_amount)" describes) have not shipped either -- see config('accounting.statements')'s
 * own docblock for the verified facts.
 *
 * This class derives open items directly from posted `journal_entries` on the PAYABLE_CONTROL
 * leaf (resolved via {@see AccountResolver}, never by name) filtered by `type_reference_id` =
 * supplier id -- the same (account_id, type_reference_id) pair
 * `AccountingController::filterLedgers()` already uses to scope one party's ledger -- FIFO-
 * matching settlement lines against charge lines at READ time. This is a read-only projection: it
 * never writes `journal_entries.settled_amount`, never touches `accounts.actual_balance`, and
 * never posts anything. Once P5.3's real apply engine ships, only this one class's internals need
 * to change -- {@see PartyStatementSourceInterface}'s contract does not.
 */
final class SupplierLedgerStatementSource implements PartyStatementSourceInterface
{
    public function __construct(private readonly AccountResolver $accountResolver) {}

    public function documents(int $companyId, int $partyId, Carbon $asOf): Collection
    {
        return $this->build($companyId, $partyId, $asOf)['documents'];
    }

    public function unapplied(int $companyId, int $partyId, Carbon $asOf): Collection
    {
        return $this->build($companyId, $partyId, $asOf)['unapplied'];
    }

    /**
     * @return array{documents: Collection<int, StatementItem>, unapplied: Collection<int, StatementItem>}
     */
    private function build(int $companyId, int $partyId, Carbon $asOf): array
    {
        $account = $this->accountResolver->resolve('PAYABLE_CONTROL', $companyId);

        $lines = JournalEntry::query()
            ->where('account_id', $account->id)
            ->where('type_reference_id', $partyId)
            ->where('posting_date', '<=', $asOf->copy()->endOfDay())
            ->whereHas('transaction', fn ($q) => $q->where('posting_status', 'posted'))
            ->orderBy('posting_date')
            ->orderBy('id')
            ->get();

        // FIFO netting: a "charge" line (credit > debit on this liability leaf) increases what is
        // owed to the supplier; a "settlement" line (debit > credit) reduces it. A settlement
        // consumes the OLDEST open charge(s) first. A settlement with nothing open left to consume
        // becomes a prepayment pool that the NEXT charge to arrive consumes from first -- so a
        // supplier paid in advance of a bill correctly shows that bill as (partly) pre-settled,
        // rather than the payment showing as a stray, permanently-unmatched credit.
        $openCharges = []; // ['id' => line id, 'date' => Carbon, 'number' => string, 'amount' => float, 'remaining' => float, 'settled' => float]
        $prepaymentPool = 0.0;
        $lastSettlementDate = null;

        foreach ($lines as $line) {
            $chargeAmount = round((float) $line->credit - (float) $line->debit, 3);
            $settleAmount = round((float) $line->debit - (float) $line->credit, 3);

            if ($chargeAmount > 0.001) {
                $remaining = $chargeAmount;
                $settledNow = 0.0;

                if ($prepaymentPool > 0.001) {
                    $consume = min($prepaymentPool, $remaining);
                    $prepaymentPool = round($prepaymentPool - $consume, 3);
                    $remaining = round($remaining - $consume, 3);
                    $settledNow = $consume;
                }

                $openCharges[] = [
                    'id' => $line->id,
                    'date' => Carbon::parse($line->posting_date ?? $line->transaction_date),
                    'number' => (string) ($line->voucher_number ?: ('JE-'.$line->id)),
                    'amount' => $chargeAmount,
                    'remaining' => $remaining,
                    'settled' => $settledNow,
                ];
            } elseif ($settleAmount > 0.001) {
                $toConsume = $settleAmount;
                $lastSettlementDate = Carbon::parse($line->posting_date ?? $line->transaction_date);

                foreach ($openCharges as &$charge) {
                    if ($toConsume <= 0.001) {
                        break;
                    }
                    if ($charge['remaining'] <= 0.001) {
                        continue;
                    }
                    $consume = min($charge['remaining'], $toConsume);
                    $charge['remaining'] = round($charge['remaining'] - $consume, 3);
                    $charge['settled'] = round($charge['settled'] + $consume, 3);
                    $toConsume = round($toConsume - $consume, 3);
                }
                unset($charge);

                if ($toConsume > 0.001) {
                    $prepaymentPool = round($prepaymentPool + $toConsume, 3);
                }
            }
            // A zero-amount line (chargeAmount and settleAmount both ~0) contributes nothing to
            // either side -- skipped by construction (neither branch's threshold is met).
        }

        $documents = collect($openCharges)->map(fn (array $c) => new StatementItem(
            kind: 'document',
            documentType: 'supplier_charge',
            documentId: $c['id'],
            documentNumber: $c['number'],
            documentDate: $c['date'],
            dueDate: null,
            amount: $c['amount'],
            settledAmount: $c['settled'],
            description: 'Supplier charge '.$c['number'],
        ))->values();

        $unapplied = collect();
        if ($prepaymentPool > 0.001) {
            $unapplied->push(new StatementItem(
                kind: 'unapplied',
                documentType: 'supplier_prepayment',
                documentId: null,
                documentNumber: 'PREPAY',
                documentDate: $lastSettlementDate ?? $asOf,
                dueDate: null,
                amount: $prepaymentPool,
                settledAmount: 0.0,
                description: 'Unapplied supplier payment (paid ahead of a charge)',
            ));
        }

        return ['documents' => $documents, 'unapplied' => $unapplied];
    }
}
