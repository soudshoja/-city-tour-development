<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * P2.5.D (p2_5-brief.md §P2.5.D): "deferred revenue schedule report (by release month)" —
 * groups every outstanding `at_travel` deferral for a company by the month its travel/check-in
 * date falls in, for a "what will release when" schedule view.
 *
 * Thin presentation layer over {@see RevenueRecognitionService::outstandingByTask()} — no
 * separate query/derivation of its own, so the report and the job that actually releases the
 * money can never disagree about what is outstanding (the same "one derivation, many readers"
 * principle {@see \App\Services\Accounting\TrialBalanceService} already establishes for balances).
 */
final class DeferredRevenueScheduleReport
{
    public const BUCKET_DATE_PENDING = 'date_pending';

    public function __construct(private readonly RevenueRecognitionService $recognition) {}

    /**
     * @return array<string, array{
     *     release_month: string, rows: array<int, array>, revenue_total: float, cost_total: float,
     * }>
     *     Keyed by 'YYYY-MM' (release month) or {@see self::BUCKET_DATE_PENDING} for a task with
     *     no `travel_date` set yet — reported, never silently dropped (see
     *     `tasks.travel_date`'s own migration docblock). Sorted ascending by key, pending-date
     *     bucket last.
     */
    public function byReleaseMonth(int $companyId): array
    {
        $outstanding = $this->recognition->outstandingByTask($companyId);

        $buckets = [];
        foreach ($outstanding as $taskId => $row) {
            $key = $row['travel_date'] !== null
                ? $row['travel_date']->format('Y-m')
                : self::BUCKET_DATE_PENDING;

            $buckets[$key] ??= [
                'release_month' => $key,
                'rows' => [],
                'revenue_total' => 0.0,
                'cost_total' => 0.0,
            ];

            $buckets[$key]['rows'][] = [
                'task_id' => $taskId,
                'reference' => (string) ($row['task']->reference ?? "#{$taskId}"),
                'service_type' => $row['service_type'],
                'revenue_amount' => $row['revenue_amount'],
                'cost_amount' => $row['cost_amount'],
                'travel_date' => $row['travel_date']?->toDateString(),
                'invoice_id' => $row['invoice_id'],
            ];
            $buckets[$key]['revenue_total'] += $row['revenue_amount'];
            $buckets[$key]['cost_total'] += $row['cost_amount'];
        }

        // Sort ascending by month key, with the pending-date bucket (a non-date string, sorts
        // after any 'YYYY-MM' key lexicographically already — 'date_pending' > '9999-12') last —
        // asserted explicitly via ksort's own string comparison rather than relied upon silently.
        ksort($buckets);

        return $buckets;
    }
}
