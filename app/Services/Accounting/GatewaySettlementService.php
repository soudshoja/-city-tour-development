<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\GatewaySettlement;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * accounting-builds T7 (Lane D — PLAN.md §5, L11/L12): records a real gateway PAYOUT (gross
 * collected / fee actually charged / net paid into the bank, keyed by the gateway's own payout
 * reference) and, when the engine is ON for the company, posts the ledger movement it implies —
 * clearing draining to the bank, plus a FEE TRUE-UP against whatever was already recognised as
 * fee expense at RECEIPT time (L11: fee stays recognised at receipt; this is the ONLY place a
 * settlement-time adjustment happens).
 *
 * ── Two-step, draft-then-post shape (same convention as ReconciliationFixDraftService) ─────────
 * {@see self::record()} is the ONLY writer of `gateway_settlements` — it always persists a row
 * (status starts 'recorded'), then immediately attempts to post it through {@see PostingSeam}.
 * When the engine is OFF for this company, the seam's own `$legacy` closure here does nothing but
 * log `accounting.feature_skipped_engine_off` and return null (L2) — the row stays
 * status='recorded', exactly the "recording persists the record but posts nothing" contract. A
 * 'recorded' row that was never posted (engine was off, or a prior post() attempt threw) can be
 * re-posted later via {@see self::post()} once the engine is on.
 *
 * ── Idempotency (L: "idempotent on (gateway, payout reference)") ────────────────────────────────
 * `record()` is idempotent per (company_id, gateway, payout_reference) — a second call with the
 * IDENTICAL figures returns the existing row untouched (no duplicate, no re-post); a second call
 * for the SAME key with DIFFERENT figures is a genuine data conflict and throws
 * (`\RuntimeException`) rather than silently overwriting — "unmatched/over-settled cases produce
 * exceptions, never silent absorption". The ON-path ledger idempotency key derived from the SAME
 * tuple is `gw-settle:{gateway}:{payout_reference}` (MP-7-3: keying on (gateway, date) alone would
 * collapse two same-day payouts onto one document — this key never does, because two distinct
 * payout references always produce two distinct keys even when their payout_date is identical).
 *
 * ── The posting shape, and why the clearing leg is `gross - recognisedFee`, not raw `gross` ────
 * Every receipt feeder (`PaymentController`, `ClientController::addCredit()`,
 * `CheckMyFatoorahPayments`) debits GATEWAY_CLEARING_{gw} for the amount NET of the fee it
 * estimated at receipt time (`netAmount = amount - accountingFee`) — the fee itself already went
 * to GATEWAY_FEE_EXPENSE_{gw} as a THIRD leg of that same balanced document. So the clearing
 * account's accumulated balance for the batch of receipts behind one payout is
 * `gross - recognisedFee` (gross = the payout's own reported gross, i.e. Σ of the underlying
 * charge amounts; recognisedFee = Σ of what was already estimated/recognised as fee for those
 * same receipts), NOT `gross` on its own. Draining exactly that figure to zero (rather than the
 * raw gross) is what keeps this document self-balancing for ANY value of `recognisedFee`,
 * including the documented default of 0 (L11/Q1 fallback: "0 when unknown, then the full fee is
 * Dr") — proof:
 *
 *   Dr bank                      = net
 *   Cr GATEWAY_CLEARING_{gw}     = gross - recognisedFee
 *   true-up = fee - recognisedFee:
 *     true-up > 0 -> Dr GATEWAY_FEE_EXPENSE_{gw}  true-up
 *     true-up < 0 -> Cr GATEWAY_FEE_EXPENSE_{gw}  |true-up|
 *     true-up = 0 -> line omitted (PostingService rejects a zero-amount line, same convention
 *                    every other conditional-third-line feeder in this codebase already follows)
 *
 *   Since gross = net + fee (validated at record() — see below), for true-up >= 0:
 *     total Dr = net + (fee - recognisedFee) = (net + fee) - recognisedFee = gross - recognisedFee
 *              = total Cr.                                                                  QED
 *   Symmetric proof for true-up < 0 (Cr fee_expense |true-up| instead): total Cr becomes
 *     (gross - recognisedFee) + (recognisedFee - fee) = gross - fee = net = total Dr.       QED
 *
 * When `recognisedFee` is 0 (the default — L11/Q1's own fallback), the clearing leg reduces to
 * plain `gross`, matching the plan's literal shorthand for the common case exactly; the
 * `- recognisedFee` term only activates when a caller supplies a real figure (e.g. once a future
 * task links specific receipt fee lines to a payout — out of this task's scope, see the review
 * packet's Deviations section).
 *
 * ── Validation (L: "never silent absorption") ───────────────────────────────────────────────────
 * `record()` refuses (does not persist) when `gross != net + fee` (tolerance
 * `config('accounting.engine.balance_tolerance')`) — a malformed payout report is a data problem
 * to surface immediately, never a silently-absorbed rounding difference.
 *
 * ── Daily clearing->bank JV non-double-move (see class docblock on
 *    {@see \App\Console\Commands\PaymentReleaseToCompanyBankAccProcess}) ────────────────────────
 * Once a settlement POSTS for (company, gateway), every still-`completed=0` `Payment` row for
 * that exact gateway dated on/before the payout date is marked `completed=1` — the SAME flag
 * `PaymentReleaseToCompanyBankAccProcess`'s own `Payment::where('completed', 0)` query already
 * uses to decide what it still needs to sweep. This is not a ledger write (Payment.completed is
 * not `journal_entries`/`transactions`, so ArchitectureTest's raw-writer scan is not implicated),
 * and it is forward-looking only: it stops the DAILY job from ever picking up these payments on
 * its NEXT run (this settlement has already moved that money, via a real payout-driven `GWS`
 * document instead of the daily job's own grouped `JV`) — it does not retroactively undo a daily
 * JV that already ran before this settlement existed. See
 * {@see \Tests\Unit\Services\Accounting\GatewaySettlementServiceTest} for the proof.
 */
final class GatewaySettlementService
{
    public function __construct(
        private readonly AccountResolver $accountResolver,
        private readonly PostingSeam $seam,
    ) {}

    /**
     * @param  array{payout_items?: array}|null  $raw
     */
    public function record(
        int $companyId,
        string $gateway,
        string $payoutReference,
        \DateTimeInterface $payoutDate,
        float $gross,
        float $fee,
        float $net,
        int $bankAccountId,
        ?string $currency = null,
        ?string $settlementChannel = null,
        float $recognisedFee = 0.0,
        string $source = GatewaySettlement::SOURCE_MANUAL,
        ?array $raw = null,
        ?User $actor = null,
    ): GatewaySettlement {
        $gatewayKey = strtoupper(trim($gateway));

        $knownGateways = array_keys((array) config('accounting.purpose_codes.gateways', []));
        if (! in_array($gatewayKey, $knownGateways, true)) {
            throw new \InvalidArgumentException("Unknown gateway '{$gatewayKey}' — must be one of: ".implode(', ', $knownGateways));
        }

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.001);
        if (abs($gross - ($net + $fee)) > $tolerance) {
            throw new \InvalidArgumentException(
                sprintf('Settlement does not balance: gross (%.3f) must equal net (%.3f) + fee (%.3f).', $gross, $net, $fee)
            );
        }

        // Structural validation ALWAYS runs, regardless of engine state — there is no legacy
        // codepath for this brand-new feature to preserve, so a bad bank leaf is a data problem
        // to refuse immediately (MP-7-2: this is the check that must exist for post() to ever
        // refuse an invalid bank account).
        $this->accountResolver->assertUnderBankGroup($bankAccountId, $companyId);

        $channel = $settlementChannel ?? self::channelFor($gatewayKey, null);

        $existing = GatewaySettlement::forCompany($companyId)
            ->where('gateway', $gatewayKey)
            ->where('payout_reference', $payoutReference)
            ->first();

        if ($existing !== null) {
            $sameFigures = abs((float) $existing->gross - $gross) <= $tolerance
                && abs((float) $existing->fee - $fee) <= $tolerance
                && abs((float) $existing->net - $net) <= $tolerance;

            if (! $sameFigures) {
                throw new \RuntimeException(
                    "A settlement already exists for gateway '{$gatewayKey}' payout reference "
                    ."'{$payoutReference}' with different figures (gross {$existing->gross} / fee "
                    ."{$existing->fee} / net {$existing->net}) — this is a genuine conflict, not a "
                    .'replay. Correct the payout reference or investigate the discrepancy.'
                );
            }

            // Idempotent replay of the identical event — return the existing row untouched
            // (never re-create, never re-post a second time from here; a caller that wants to
            // retry posting an unposted row calls post() directly).
            return $existing;
        }

        $settlement = GatewaySettlement::create([
            'company_id' => $companyId,
            'gateway' => $gatewayKey,
            'settlement_channel' => $channel,
            'payout_reference' => $payoutReference,
            'payout_date' => Carbon::instance(Carbon::parse($payoutDate))->toDateString(),
            'gross' => round($gross, 3),
            'fee' => round($fee, 3),
            'net' => round($net, 3),
            'recognised_fee' => round($recognisedFee, 3),
            'currency' => $currency ?? (string) config('accounting.engine.base_currency'),
            'bank_account_id' => $bankAccountId,
            'status' => GatewaySettlement::STATUS_RECORDED,
            'imported_by' => $actor?->id,
            'source' => $source,
            'raw' => $raw,
        ]);

        AccountingLog::write(
            action: 'gateway_settlement_recorded',
            companyId: $companyId,
            subjectType: 'gateway_settlement',
            subjectId: (int) $settlement->id,
            after: ['gateway' => $gatewayKey, 'payout_reference' => $payoutReference, 'gross' => $gross, 'fee' => $fee, 'net' => $net],
            actorId: $actor?->id,
        );

        return $this->post($settlement, $actor);
    }

    /**
     * Attempts to post an already-recorded settlement through the seam. A no-op (returns the
     * settlement unchanged) when it is already `posted`; refuses to re-attempt a `failed` row
     * (the failure_reason is a permanent record, not something a bare re-call should paper over —
     * an operator corrects the underlying data and records a fresh settlement, or a future task
     * adds an explicit retry action).
     */
    public function post(GatewaySettlement $settlement, ?User $actor = null): GatewaySettlement
    {
        if ($settlement->status === GatewaySettlement::STATUS_POSTED) {
            return $settlement;
        }

        if ($settlement->status === GatewaySettlement::STATUS_FAILED) {
            throw new \RuntimeException("Settlement #{$settlement->id} previously failed ({$settlement->failure_reason}) — correct the data and record a fresh settlement.");
        }

        $gatewayKey = $settlement->gateway;
        $companyId = (int) $settlement->company_id;
        $idempotencyKey = self::idempotencyKeyFor($gatewayKey, $settlement->payout_reference);

        $clearingAmount = round((float) $settlement->gross - (float) $settlement->recognised_fee, 3);
        $trueUp = round((float) $settlement->fee - (float) $settlement->recognised_fee, 3);

        $lines = [
            new LineDraft(
                purposeCode: '',
                accountId: (int) $settlement->bank_account_id,
                side: 'debit',
                amount: (float) $settlement->net,
                currency: (string) $settlement->currency,
                originalAmount: (float) $settlement->net,
                exchangeRate: 1.0,
                transactionType: 'GATEWAYSETTLED',
                description: "Gateway settlement {$gatewayKey} payout {$settlement->payout_reference}",
                ledgerType: 'bank',
                settlementChannel: $settlement->settlement_channel,
            ),
            new LineDraft(
                purposeCode: "GATEWAY_CLEARING_{$gatewayKey}",
                accountId: null,
                side: 'credit',
                amount: $clearingAmount,
                currency: (string) $settlement->currency,
                originalAmount: $clearingAmount,
                exchangeRate: 1.0,
                transactionType: 'GATEWAYCLEARED',
                description: "Gateway settlement {$gatewayKey} payout {$settlement->payout_reference}",
                ledgerType: 'bank',
                settlementChannel: $settlement->settlement_channel,
            ),
        ];

        if (abs($trueUp) > 0.0005) {
            $lines[] = new LineDraft(
                purposeCode: "GATEWAY_FEE_EXPENSE_{$gatewayKey}",
                accountId: null,
                side: $trueUp > 0 ? 'debit' : 'credit',
                amount: abs($trueUp),
                currency: (string) $settlement->currency,
                originalAmount: abs($trueUp),
                exchangeRate: 1.0,
                transactionType: 'GATEWAYFEETRUEUP',
                description: "Gateway settlement {$gatewayKey} fee true-up (actual {$settlement->fee} vs recognised {$settlement->recognised_fee})",
                ledgerType: 'charges',
                settlementChannel: $settlement->settlement_channel,
            );
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: null,
            docType: 'GWS',
            subType: null,
            docDate: $settlement->payout_date,
            narration: "Gateway settlement — {$gatewayKey} payout {$settlement->payout_reference}",
            lines: $lines,
            idempotencyKey: $idempotencyKey,
            sourceType: 'GatewaySettlement',
            sourceId: $settlement->id,
            userId: $actor?->id,
        );

        $legacy = function () use ($gatewayKey, $settlement) {
            Log::info('accounting.feature_skipped_engine_off', [
                'feature' => 'gateway_settlement',
                'gateway' => $gatewayKey,
                'payout_reference' => $settlement->payout_reference,
                'company_id' => $settlement->company_id,
            ]);

            return null;
        };

        $posted = $this->seam->post($draft, $legacy, 'gateway-settlement.post');

        if ($posted === null) {
            // Either the engine is OFF for this company (record stays 'recorded' — the documented
            // "posts nothing" contract), or the seam's own S1 short-circuit found this exact key
            // already posted under a prior call. Distinguish by checking whether the engine is
            // live for this company right now: if it is, the document genuinely exists already
            // and this row should reflect 'posted', not linger as 'recorded' forever.
            if ($this->seam->isEnabledFor($companyId)) {
                $settlement->status = GatewaySettlement::STATUS_POSTED;
                $settlement->save();
            }

            return $settlement->refresh();
        }

        $settlement->status = GatewaySettlement::STATUS_POSTED;
        $settlement->transaction_id = $posted->transaction->id;
        $settlement->save();

        $this->skipDailyReleaseForSettledPayments($settlement);

        AccountingLog::write(
            action: 'gateway_settlement_posted',
            companyId: $companyId,
            subjectType: 'gateway_settlement',
            subjectId: (int) $settlement->id,
            transactionId: (int) $posted->transaction->id,
            after: ['clearing_amount' => $clearingAmount, 'true_up' => $trueUp],
            actorId: $actor?->id,
        );

        return $settlement->refresh();
    }

    /**
     * MP-7-3-adjacent non-double-move guard: marks every still-unswept `Payment` row this
     * payout-driven settlement now covers as `completed = 1`, the SAME flag
     * `PaymentReleaseToCompanyBankAccProcess`'s own daily grouping query filters on. See this
     * class's own docblock ("Daily clearing->bank JV non-double-move") for the full reasoning —
     * this is a Payment-status write, never a `journal_entries`/`transactions` write, so it does
     * not go through PostingSeam and is not in ArchitectureTest's raw-writer scope.
     */
    private function skipDailyReleaseForSettledPayments(GatewaySettlement $settlement): void
    {
        Payment::withoutGlobalScopes()
            ->where('company_id', $settlement->company_id)
            ->where('completed', 0)
            ->whereRaw('UPPER(payment_gateway) = ?', [$settlement->gateway])
            ->whereDate('payment_date', '<=', $settlement->payout_date)
            ->update(['completed' => 1]);
    }

    public static function idempotencyKeyFor(string $gateway, string $payoutReference): string
    {
        return sprintf('gw-settle:%s:%s', strtolower(trim($gateway)), $payoutReference);
    }

    /**
     * `{gateway}:{rail}` when a rail is known (L12 shape, e.g. `tap:knet`), or the bare
     * lower-cased gateway key alone when no rail is available (a settlement or a MyFatoorah
     * advance carries no per-instrument breakdown today — see the review packet's Deviations
     * section for why this degrades gracefully rather than fabricating an `unknown` segment).
     */
    public static function channelFor(string $gatewayKey, ?string $railCode): string
    {
        $gateway = strtolower(trim($gatewayKey));

        if ($railCode === null || trim($railCode) === '') {
            return $gateway;
        }

        return $gateway.':'.strtolower(trim($railCode));
    }
}
