<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Services\Accounting\Statements\AgentSettlementStatementSource;
use App\Services\Accounting\Statements\ClientInvoiceStatementSource;
use App\Services\Accounting\Statements\PartyStatementSourceInterface;
use App\Services\Accounting\Statements\StatementItem;
use App\Services\Accounting\Statements\SupplierLedgerStatementSource;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * P2.5.H (p2_5-brief.md §P2.5.H). Builds a client/supplier/agent statement in either
 * `open_items` (default) or `full_activity` mode -- {@see StatementOptions} resolves the
 * company's mode, one {@see PartyStatementSourceInterface} implementation per party type supplies
 * the documents/unapplied rows, this class applies the mode's filter, ageing (open_items only),
 * and totals. Never reads `accounts.actual_balance` or `journal_entries.balance`; never resolves
 * an account by name.
 *
 * The Bank Reconciliation Statement (book balance -> +/- unreconciled items -> statement balance)
 * is the bank-account analogue named in the brief -- produced by P2.5.G's Reconciliation Center,
 * not this class (this class is party statements: client/supplier/agent).
 */
final class StatementService
{
    public const PARTY_CLIENT = 'client';

    public const PARTY_SUPPLIER = 'supplier';

    public const PARTY_AGENT = 'agent';

    public const PARTY_TYPES = [self::PARTY_CLIENT, self::PARTY_SUPPLIER, self::PARTY_AGENT];

    public function __construct(
        private readonly ClientInvoiceStatementSource $clientSource,
        private readonly SupplierLedgerStatementSource $supplierSource,
        private readonly AgentSettlementStatementSource $agentSource,
    ) {}

    public function sourceFor(string $partyType): PartyStatementSourceInterface
    {
        return match ($partyType) {
            self::PARTY_CLIENT => $this->clientSource,
            self::PARTY_SUPPLIER => $this->supplierSource,
            self::PARTY_AGENT => $this->agentSource,
            default => throw new InvalidArgumentException("Unknown statement party type: {$partyType}"),
        };
    }

    /**
     * @param  string|null  $modeOverride  One of {@see StatementOptions::MODES}, or null to use the
     *                                     company's own `statement_mode` option -- used by the statement screen's own
     *                                     open_items/full_activity toggle to preview the OTHER mode without changing the
     *                                     company setting.
     * @param  Carbon|null  $periodStart  Lower bound for `full_activity` mode's document list. Has
     *                                    no effect on `open_items` mode -- open items are inherently "as of $asOf", not
     *                                    period-bounded, per the brief's own wording.
     */
    public function generate(
        int $companyId,
        string $partyType,
        int $partyId,
        Carbon $asOf,
        ?string $modeOverride = null,
        ?Carbon $periodStart = null,
    ): array {
        $mode = ($modeOverride !== null && in_array($modeOverride, StatementOptions::MODES, true))
            ? $modeOverride
            : StatementOptions::mode($companyId);

        $source = $this->sourceFor($partyType);
        $documents = $source->documents($companyId, $partyId, $asOf);
        $tolerance = StatementOptions::unsettledTolerance();
        $buckets = StatementOptions::ageingBuckets();

        if ($mode === StatementOptions::MODE_FULL_ACTIVITY) {
            return $this->buildFullActivity($documents, $periodStart, $asOf, $mode);
        }

        return $this->buildOpenItems($source, $documents, $companyId, $partyId, $asOf, $tolerance, $buckets, $mode);
    }

    private function buildOpenItems(
        PartyStatementSourceInterface $source,
        \Illuminate\Support\Collection $documents,
        int $companyId,
        int $partyId,
        Carbon $asOf,
        float $tolerance,
        array $buckets,
        string $mode,
    ): array {
        $open = $documents->reject(fn (StatementItem $i) => $i->isSettled($tolerance))->values();
        $unapplied = $source->unapplied($companyId, $partyId, $asOf);

        $ageing = $this->buildAgeingSummary($open, $asOf, $buckets);

        $openTotal = round($open->sum(fn (StatementItem $i) => $i->outstanding()), 3);
        $unappliedTotal = round($unapplied->sum(fn (StatementItem $i) => $i->amount), 3);

        return [
            'mode' => $mode,
            'as_of' => $asOf->toDateString(),
            'items' => $open->map(fn (StatementItem $i) => $i->toArray($asOf, $buckets))->values()->all(),
            'unapplied' => $unapplied->map(fn (StatementItem $i) => $i->toArray($asOf, $buckets))->values()->all(),
            'ageing' => $ageing,
            'totals' => [
                'open_total' => $openTotal,
                'unapplied_total' => $unappliedTotal,
                // Net owed: outstanding documents minus whatever's sitting unapplied (a credit,
                // an unapplied receipt, a supplier prepayment) -- can go negative (the party is,
                // net, owed money / holds a credit balance), which the view surfaces as such
                // rather than clamping to zero.
                'net_outstanding' => round($openTotal - $unappliedTotal, 3),
                'item_count' => $open->count(),
                'unapplied_count' => $unapplied->count(),
            ],
            'is_empty' => $open->isEmpty() && $unapplied->isEmpty(),
        ];
    }

    private function buildFullActivity(
        \Illuminate\Support\Collection $documents,
        ?Carbon $periodStart,
        Carbon $asOf,
        string $mode,
    ): array {
        $filtered = $periodStart === null
            ? $documents
            : $documents->filter(fn (StatementItem $i) => $i->documentDate->gte($periodStart->copy()->startOfDay()))->values();

        $running = 0.0;
        $rows = [];
        foreach ($filtered as $item) {
            /** @var StatementItem $item */
            $running = round($running + $item->outstanding(), 3);
            $row = $item->toArray($asOf, []);
            $row['running_balance'] = $running;
            $rows[] = $row;
        }

        return [
            'mode' => $mode,
            'as_of' => $asOf->toDateString(),
            'period_start' => $periodStart?->toDateString(),
            'items' => $rows,
            'unapplied' => [],
            'ageing' => null,
            'totals' => [
                'closing_balance' => $running,
                'item_count' => count($rows),
            ],
            'is_empty' => empty($rows),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StatementItem>  $items
     * @param  int[]  $buckets
     */
    private function buildAgeingSummary(\Illuminate\Support\Collection $items, Carbon $asOf, array $buckets): array
    {
        $labels = $this->bucketLabels($buckets);
        $summary = [];
        foreach ($labels as $index => $label) {
            $summary[$index] = ['label' => $label, 'total' => 0.0, 'count' => 0];
        }

        foreach ($items as $item) {
            /** @var StatementItem $item */
            $index = $item->ageingBucketIndex($asOf, $buckets);
            $summary[$index]['total'] = round($summary[$index]['total'] + $item->outstanding(), 3);
            $summary[$index]['count']++;
        }

        return array_values($summary);
    }

    /** @param int[] $buckets @return string[] */
    private function bucketLabels(array $buckets): array
    {
        $labels = [];
        $prev = 0;
        foreach ($buckets as $upper) {
            $labels[] = $prev === 0 ? "0-{$upper}" : ($prev + 1)."-{$upper}";
            $prev = $upper;
        }
        $labels[] = "{$prev}+";

        return $labels;
    }
}
