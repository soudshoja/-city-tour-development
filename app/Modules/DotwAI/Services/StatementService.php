<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Models\Credit;
use App\Models\JournalEntry;
use App\Modules\DotwAI\Models\DotwAIBooking;

/**
 * Company statement aggregation for DOTW bookings reconciliation.
 *
 * Aggregates bookings, journal entries, and credits for a date range,
 * returning a structured statement suitable for comparing against the
 * DOTW portal and for WhatsApp delivery.
 *
 * ALL JournalEntry queries MUST use withoutGlobalScopes() to bypass the
 * Auth-based company_id global scope that would fail in API/queue contexts.
 *
 * @see ACCT-02 Statement returns bookings, cancellations, credits, debits for date range
 * @see ACCT-04 All JournalEntry queries bypass global scopes
 */
class StatementService
{
    /**
     * Aggregate bookings, journal entries, and credits for a company over a date range.
     *
     * @param int    $companyId The company to query
     * @param string $dateFrom  Start date (Y-m-d)
     * @param string $dateTo    End date (Y-m-d, inclusive)
     * @return array{
     *   bookings: array,
     *   journal_entries: array,
     *   credits: array,
     *   totals: array{
     *     total_bookings: int,
     *     total_booking_amount: float,
     *     total_cancellations: int,
     *     total_penalties: float,
     *     total_credits_topup: float,
     *     total_credits_refund: float,
     *     total_credits_invoice: float,
     *     net_balance: float
     *   }
     * }
     */
    public function getStatement(int $companyId, string $dateFrom, string $dateTo): array
    {
        $dateToEnd = $dateTo . ' 23:59:59';

        // ── Query DotwAIBookings ──────────────────────────────────────────
        $bookingRecords = DotwAIBooking::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom, $dateToEnd])
            ->orderBy('created_at')
            ->get();

        $bookings = $bookingRecords->map(function (DotwAIBooking $b): array {
            return [
                'prebook_key' => $b->prebook_key,
                'hotel_name'  => $b->hotel_name,
                'check_in'    => $b->check_in?->format('Y-m-d'),
                'check_out'   => $b->check_out?->format('Y-m-d'),
                'status'      => $b->status,
                'amount'      => (float) $b->display_total_fare,
                'currency'    => $b->display_currency ?? 'KWD',
                'track'       => $b->track,
                'created_at'  => $b->created_at?->format('Y-m-d H:i:s'),
            ];
        })->values()->all();

        // ── Query JournalEntry (MUST use withoutGlobalScopes) ─────────────
        $journalRecords = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('transaction_date', [$dateFrom, $dateToEnd])
            ->orderBy('transaction_date')
            ->get();

        $journalEntries = $journalRecords->map(function (JournalEntry $j): array {
            return [
                'date'        => $j->transaction_date instanceof \Carbon\Carbon
                    ? $j->transaction_date->format('Y-m-d')
                    : (is_string($j->transaction_date)
                        ? substr($j->transaction_date, 0, 10)
                        : ''),
                'description' => $j->description,
                'debit'       => (float) $j->debit,
                'credit'      => (float) $j->credit,
                'currency'    => $j->currency ?? 'KWD',
                'type'        => $j->type,
            ];
        })->values()->all();

        // ── Query Credits ─────────────────────────────────────────────────
        $creditRecords = Credit::where('company_id', $companyId)
            ->whereBetween('created_at', [$dateFrom, $dateToEnd])
            ->orderBy('created_at')
            ->get();

        $credits = $creditRecords->map(function (Credit $c): array {
            return [
                'date'        => $c->created_at?->format('Y-m-d H:i:s'),
                'type'        => $c->type,
                'amount'      => (float) $c->amount,
                'description' => $c->description,
            ];
        })->values()->all();

        // ── Compute Totals ────────────────────────────────────────────────
        $bookableStatuses = [DotwAIBooking::STATUS_CONFIRMED, DotwAIBooking::STATUS_CANCELLED];

        $totalBookings = $bookingRecords->count();
        $totalBookingAmount = $bookingRecords
            ->whereIn('status', $bookableStatuses)
            ->sum('display_total_fare');

        $totalCancellations = $bookingRecords
            ->where('status', DotwAIBooking::STATUS_CANCELLED)
            ->count();

        // Penalties = sum of debit entries from cancellation journal entries
        $totalPenalties = $journalRecords
            ->where('type', 'cancellation')
            ->sum('debit');

        // Credit totals by type
        $totalCreditsTopup = $creditRecords
            ->where('type', Credit::TOPUP)
            ->sum('amount');
        $totalCreditsRefund = $creditRecords
            ->where('type', Credit::REFUND)
            ->sum('amount');
        $totalCreditsInvoice = $creditRecords
            ->where('type', Credit::INVOICE)
            ->sum('amount');

        // Net balance = total credit topups + refunds + invoices (INVOICE amounts are negative)
        $netBalance = (float) $totalCreditsTopup + (float) $totalCreditsRefund + (float) $totalCreditsInvoice;

        return [
            'bookings'       => $bookings,
            'journal_entries' => $journalEntries,
            'credits'        => $credits,
            'totals'         => [
                'total_bookings'         => $totalBookings,
                'total_booking_amount'   => round((float) $totalBookingAmount, 3),
                'total_cancellations'    => $totalCancellations,
                'total_penalties'        => round((float) $totalPenalties, 3),
                'total_credits_topup'    => round((float) $totalCreditsTopup, 3),
                'total_credits_refund'   => round((float) $totalCreditsRefund, 3),
                'total_credits_invoice'  => round((float) $totalCreditsInvoice, 3),
                'net_balance'            => round($netBalance, 3),
            ],
        ];
    }

    /**
     * Format a statement as a bilingual Arabic/English WhatsApp summary.
     *
     * Produces a concise reconciliation summary with date range, counts,
     * and key totals. Intended for WhatsApp delivery via n8n AI agents.
     *
     * @param array  $statementData Result from getStatement()
     * @param string $dateFrom      Start date (Y-m-d)
     * @param string $dateTo        End date (Y-m-d)
     * @return string WhatsApp-formatted message
     */
    public static function formatStatementWhatsApp(array $statementData, string $dateFrom, string $dateTo): string
    {
        $separator = "──────────────────────────────";
        $totals    = $statementData['totals'] ?? [];
        $currency  = 'KWD';

        // Pick currency from first booking if available
        if (!empty($statementData['bookings'])) {
            $currency = $statementData['bookings'][0]['currency'] ?? 'KWD';
        }

        $lines   = [];
        $lines[] = "كشف حساب | Account Statement";
        $lines[] = $separator;
        $lines[] = "Period | الفترة: {$dateFrom} → {$dateTo}";
        $lines[] = "";

        // Bookings summary
        $lines[] = "Bookings | الحجوزات: " . ($totals['total_bookings'] ?? 0);
        $lines[] = "Cancellations | الإلغاءات: " . ($totals['total_cancellations'] ?? 0);

        $bookingAmount = number_format((float) ($totals['total_booking_amount'] ?? 0), 3);
        $lines[] = "Total Booking Amount | إجمالي الحجوزات: {$currency} {$bookingAmount}";

        $penalties = number_format((float) ($totals['total_penalties'] ?? 0), 3);
        $lines[] = "Cancellation Penalties | رسوم الإلغاء: {$currency} {$penalties}";

        $lines[] = "";
        $lines[] = $separator;
        $lines[] = "Credits | الرصيد الائتماني:";

        $topup = number_format((float) ($totals['total_credits_topup'] ?? 0), 3);
        $lines[] = "  TOPUP: {$currency} {$topup}";

        $refund = number_format((float) ($totals['total_credits_refund'] ?? 0), 3);
        $lines[] = "  Refunds | استردادات: {$currency} {$refund}";

        $invoice = number_format((float) abs($totals['total_credits_invoice'] ?? 0), 3);
        $lines[] = "  Invoice Deductions | خصومات: {$currency} {$invoice}";

        $lines[] = "";

        $netBalance = number_format((float) ($totals['net_balance'] ?? 0), 3);
        $lines[] = "Net Balance | الرصيد الصافي: {$currency} {$netBalance}";

        $lines[] = $separator;

        return implode("\n", $lines);
    }
}
