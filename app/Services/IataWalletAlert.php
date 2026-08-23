<?php

namespace App\Services;

/**
 * Pure formatter for the IATA wallet change WhatsApp alert. Kept separate from
 * the polling command so the message shape (direction arrow, sign, 3dp amounts)
 * is unit-testable without the IATA API. Only ever called for a real change
 * (non-zero delta); the command guards delta == 0 upstream.
 *
 *   IATA Wallet ▼ DECREASE
 *   City Travelers — IATA 12345678
 *   Change: -125.500 KWD
 *   Balance: 3,200.000 → 3,074.500 KWD
 *   2026-06-14 13:05
 */
class IataWalletAlert
{
    public static function build(
        string $companyName,
        ?string $iataNumber,
        float $previous,
        float $current,
        string $currency,
        string $when
    ): string {
        $delta = $current - $previous;
        $arrow = $delta >= 0 ? '▲' : '▼';
        $word  = $delta >= 0 ? 'INCREASE' : 'DECREASE';
        $sign  = $delta >= 0 ? '+' : '-';
        $cur   = trim($currency);
        $tail  = $cur !== '' ? ' ' . $cur : '';

        $iataLine = ($iataNumber !== null && trim($iataNumber) !== '')
            ? ' — IATA ' . trim($iataNumber)
            : '';

        return "IATA Wallet {$arrow} {$word}\n"
            . "{$companyName}{$iataLine}\n"
            . "Change: {$sign}" . number_format(abs($delta), 3) . $tail . "\n"
            . "Balance: " . number_format($previous, 3) . " → " . number_format($current, 3) . $tail . "\n"
            . $when;
    }
}
