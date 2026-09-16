<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * CT-A9 T2 — the four currency columns of a `journal_entries` row, derived from a {@see LineDraft}
 * by ONE rule, so the engine-OFF writers and the engine-ON writer cannot disagree about them.
 *
 * ── The seam, and why this class exists ────────────────────────────────────────────────────────
 * {@see PostingSeam::post()} takes a `DocumentDraft` and either posts it through
 * {@see PostingService::post()} (engine ON) or hands it to a legacy closure (engine OFF). The two
 * purpose-built OFF writers — `BankPaymentController::writeLegacyTransaction()` and
 * `ReceiptVoucherController::writeLegacyTransaction()` — build their rows from the SAME draft, so
 * "which columns does a document carry?" is supposed to be independent of a config flag.
 *
 * It was not. CT-FX-EXPOSURE-2026-09-16.md §5.4 item 2 measured the gap: the OFF writers wrote
 * `currency` and `exchange_rate` and **not** `original_currency` / `original_amount`, which
 * `PostingService::post()` step 8 has written since W1.1. Same document, two shapes, depending on a
 * flag — and the shape you got was the one that leaves a line unable to say what it is denominated
 * in, which is the exact column the report layer would need before any revaluation could ever be
 * built (`journal_entries.original_currency` is NULL on 99.88 % of the live ledger).
 *
 * ── Why a class and not four more array keys in each controller ────────────────────────────────
 * Because the rule is not "copy four fields". CT-A9 T1 (ruling R-CT13) made
 * `PostingService::post()` step 3f DERIVE the persisted rate rather than copy it: a base-currency
 * line carries `exchange_rate = 1.000000` whatever the feeder supplied, because on a base-currency
 * line the rate is 1 by definition and a supplied value is a fact about something else (the source
 * task's pricing). Copying `$line->exchangeRate` into the OFF writers would therefore have OPENED a
 * fresh OFF/ON drift on the very same seam T2 exists to close — OFF writing 0.340000 where ON
 * writes 1.000000 for one document. Two writers, one rule, one implementation of it.
 *
 * ── What it deliberately does NOT do ───────────────────────────────────────────────────────────
 * It does not REFUSE. Step 3f's refusals (an inconsistent FC triple, a foreign line with no rate,
 * a foreign line with no foreign amount) belong to the engine, which is the only path that claims
 * to validate a document. The OFF path is a legacy passthrough whose contract is "write what the
 * draft says"; making it throw would change which documents post depending on a config flag, which
 * is the opposite of parity. It normalises the one field that is definitionally fixed, and copies
 * the rest.
 */
final class LegacyLineCurrencyColumns
{
    /**
     * The four currency columns for one line, under the same rule step 3f applies.
     *
     * @return array{currency: string, exchange_rate: float, original_currency: string, original_amount: float}
     */
    public static function for(LineDraft $line, ?string $baseCurrency = null): array
    {
        $base = (string) ($baseCurrency ?? config('accounting.engine.base_currency', 'KWD'));

        // Case-normalised, exactly as step 3f compares them — a line tagged 'kwd' is a
        // base-currency line, and must not be treated as foreign by either writer.
        $isBase = strtoupper(trim($line->currency)) === strtoupper(trim($base));

        return [
            'currency' => $line->currency,
            // R-CT13: 1.000000 on a base-currency line, whatever the feeder supplied.
            'exchange_rate' => $isBase ? 1.0 : $line->exchangeRate,
            // PostingService step 8 writes `$line->currency` into BOTH `currency` and
            // `original_currency`; the OFF writers now do the same, so the pair agrees.
            'original_currency' => $line->currency,
            // On a base-currency line the foreign face value IS the base amount — step 3f asserts
            // exactly that (`originalAmount === amount`) before the engine will post one, so
            // taking the draft's own `originalAmount` here reproduces the ON path's
            // `$r['originalAmount']` for every document that would post either way.
            'original_amount' => $line->originalAmount,
        ];
    }
}
