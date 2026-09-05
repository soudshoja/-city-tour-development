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
 * Once a settlement POSTS for (company, gateway), the still-`completed=0` `Payment` rows for that
 * exact gateway dated on/before the payout date that this payout's own `gross` actually COVERS
 * (an exact, boundary-aligned subset — see {@see self::assertSettleable()} Guard B, which refuses
 * anything else outright) are marked `completed=1` — the SAME flag
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

        // Post-sign-off fix (T7 review packet §12 finding 3): refuse pre-flight, before anything
        // is persisted, when the settlement's currency is not the company's base currency. Every
        // line post() builds uses exchangeRate 1.0 with no rate input anywhere on the HTTP/CLI/CSV
        // surfaces that reach here — a non-base currency would silently book a foreign-currency
        // figure into the ledger at parity. See GatewaySettlementCurrencyMismatchException.
        $baseCurrency = strtoupper(trim((string) config('accounting.engine.base_currency')));
        $requestedCurrency = $currency !== null && trim($currency) !== '' ? strtoupper(trim($currency)) : $baseCurrency;
        if ($requestedCurrency !== $baseCurrency) {
            throw new \App\Exceptions\Accounting\GatewaySettlementCurrencyMismatchException(
                $gatewayKey,
                $payoutReference,
                $requestedCurrency,
                $baseCurrency,
            );
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
        // refuse an invalid bank account). Wave 3 lane I item A1: pass $requestedCurrency
        // explicitly (already asserted == $baseCurrency above) so a non-base-currency LEAF is
        // refused here with its own BankLeafCurrencyMismatchException, distinct from — and never
        // reached in the same call as — the non-base-currency SETTLEMENT refusal above.
        $this->accountResolver->assertUnderBankGroup($bankAccountId, $companyId, $requestedCurrency);

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

        // Post-fix re-verification (T7, second adversarial pass): run the money guards BEFORE the
        // row is persisted, not only inside post(). Guarding after create() left a refused payout
        // as a permanent `recorded` row occupying its own (company, gateway, payout_reference)
        // key — so the very correction the exception message asks the operator to make ("correct
        // the payout figures") would then collide with the SAME-key-different-figures conflict
        // check above and be refused a second time, with no way through under that reference.
        // Pre-flight means a refused payout leaves no trace and its reference stays re-usable.
        // post() still re-runs the identical guards, so a directly-posted row (engine turned on
        // later, CSV replay) is never let through unchecked.
        $this->assertSettleable(
            companyId: $companyId,
            gateway: $gatewayKey,
            payoutReference: $payoutReference,
            payoutDate: Carbon::parse($payoutDate)->toDateString(),
            gross: $gross,
            recognisedFee: $recognisedFee,
        );

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

        // Loop-3 re-verification: keep the coverage the guard actually VALIDATED and sweep exactly
        // that set. Re-querying the pool after the document posts made the validated set and the
        // swept set two different reads: a receipt landing in between (a gateway webhook capturing
        // a payment while an operator records a payout) shifts the greedy walk onto a different
        // subset, so the document drains `gross` while marking a set that no longer sums to it —
        // the same strand/double-move corruption Guard B exists to refuse, reintroduced through a
        // TOCTOU window rather than through a bad payout.
        $validatedCoverage = $this->assertSettleable(
            companyId: $companyId,
            gateway: $gatewayKey,
            payoutReference: (string) $settlement->payout_reference,
            payoutDate: (string) $settlement->payout_date,
            gross: (float) $settlement->gross,
            recognisedFee: (float) $settlement->recognised_fee,
        );

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

        $this->skipDailyReleaseForSettledPayments($validatedCoverage['ids']);

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
     * The two money guards, run pre-flight in {@see self::record()} and again in
     * {@see self::post()}. No-ops entirely when the engine is OFF for the company: nothing posts
     * and no Payment is swept on that path, so there is no money to protect and an OFF-path
     * recording must still persist ("engine OFF -> recording persists the record but posts
     * nothing", L2).
     *
     * -- Guard A: over-settlement, measured against the DERIVED CLEARING BALANCE ----------------
     * Post-fix re-verification (T7, second adversarial pass) -- supersedes the first pass's
     * pending-`Payment`-pool comparison, which skipped itself whenever that pool was EMPTY. That
     * escape hatch was the whole hole: a payout recorded for a gateway with no unswept payments
     * (every one of them already released by the daily job, or a manual/CSV entry with no local
     * linkage) sailed through unconditionally and drained clearing money that was not there --
     * a negative clearing account, the exact outcome the guard exists to prevent, and the exact
     * scenario the first pass's own `..._skipped_when_the_pending_pool_is_genuinely_empty` test
     * pinned as CORRECT.
     *
     * The `Payment` pool was only ever a proxy. The money itself is the balance of
     * `GATEWAY_CLEARING_{gw}`, derived from `journal_entries` (SUM(debit) - SUM(credit)) and never
     * read from `accounts.actual_balance` -- the eager column is a cache this engine deliberately
     * never trusts for a decision. Comparing the amount this document will actually take OUT of
     * clearing (`gross - recognisedFee`, not raw `gross` -- receipts booked their fee at receipt
     * time, so clearing only ever held the net) against that balance makes the invariant
     * inductive: every settlement drains no more than is there, so clearing can never be driven
     * negative by any sequence of settlements. No date window is applied -- the guard is about
     * whether the money EXISTS, and restricting it to lines dated on/before the payout would
     * falsely refuse a legitimately late-booked receipt without buying any extra safety.
     *
     * Skipped only when the company has no mapped clearing leaf for this gateway at all, in which
     * case {@see PostingService} refuses the document downstream on the unmapped purpose code
     * anyway.
     *
     * -- Guard B: payment-boundary alignment ---------------------------------------------------
     * See {@see \App\Exceptions\Accounting\GatewaySettlementUnmatchedException} for the full
     * derivation: `Payment.completed` is a boolean, so a payout whose `gross` does not land on a
     * whole-payment boundary of the pending trail cannot be recorded without either stranding or
     * double-moving the difference. Refused as the spec's "unmatched case", never absorbed.
     * Only applies when a pending pool exists -- with no local linkage there is nothing to align
     * against and Guard A is the protection.
     *
     * Returns the coverage it validated (Guard B's own read of the pending pool) so that
     * {@see self::post()} sweeps EXACTLY the payments the guard passed, instead of re-deriving
     * them from a second, later read of a pool that may have moved underneath it.
     *
     * @return array{ids: list<int>, covered: float, pending: float}
     */
    private function assertSettleable(
        int $companyId,
        string $gateway,
        string $payoutReference,
        string $payoutDate,
        float $gross,
        float $recognisedFee,
    ): array {
        if (! $this->seam->isEnabledFor($companyId)) {
            return ['ids' => [], 'covered' => 0.0, 'pending' => 0.0];
        }

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.001);

        // Guard A -- the money.
        $clearingBalance = $this->derivedClearingBalance($companyId, $gateway);
        $clearingDrain = round($gross - $recognisedFee, 3);

        if ($clearingBalance !== null && $clearingDrain > $clearingBalance + $tolerance) {
            throw new \App\Exceptions\Accounting\GatewayOverSettledException(
                $gateway,
                $payoutReference,
                $clearingDrain,
                $clearingBalance,
            );
        }

        // Guard B -- the payment trail.
        $coverage = $this->resolvePaymentCoverage($companyId, $gateway, $payoutDate, $gross);

        if ($coverage['pending'] > 0.0005 && abs($coverage['covered'] - $gross) > $tolerance) {
            throw new \App\Exceptions\Accounting\GatewaySettlementUnmatchedException(
                $gateway,
                $payoutReference,
                $gross,
                $coverage['covered'],
                $coverage['pending'],
            );
        }

        return $coverage;
    }

    /**
     * SUM(debit) - SUM(credit) over every live `journal_entries` row on this company's
     * `GATEWAY_CLEARING_{gw}` leaf -- the clearing account is debit-normal (receipts debit it,
     * settlements and the daily release credit it), so this is the money genuinely still sitting
     * in it. Never `accounts.actual_balance`. Returns null when the company has no mapped
     * clearing leaf for the gateway (the posting pipeline refuses the document downstream).
     */
    private function derivedClearingBalance(int $companyId, string $gateway): ?float
    {
        try {
            $clearingAccountId = $this->accountResolver->resolve("GATEWAY_CLEARING_{$gateway}", $companyId)->id;
        } catch (\App\Exceptions\Accounting\UnmappedPurposeException) {
            return null;
        }

        $row = \App\Models\JournalEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('account_id', $clearingAccountId)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        return round(((float) $row->d) - ((float) $row->c), 3);
    }

    /**
     * The greedy oldest-first (`payment_date`, then `id`) subset of the still-pending
     * (`completed = 0`) `Payment` pool for this gateway dated on/before the payout date, consumed
     * up to `gross` (a payment that would overshoot is skipped, never truncated). Returns the
     * covered ids, the covered total, and the whole pool's total -- Guard B refuses the settlement
     * unless `covered` lands exactly on `gross`, so by the time these ids are actually marked the
     * coverage is known to be exact.
     *
     * @return array{ids: list<int>, covered: float, pending: float}
     */
    private function resolvePaymentCoverage(int $companyId, string $gateway, string $payoutDate, float $gross): array
    {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.001);

        $eligible = Payment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('completed', 0)
            ->whereRaw('UPPER(payment_gateway) = ?', [$gateway])
            ->whereDate('payment_date', '<=', $payoutDate)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'amount']);

        $ids = [];
        $covered = 0.0;
        $pending = 0.0;

        foreach ($eligible as $payment) {
            $amount = (float) $payment->amount;
            $pending = round($pending + $amount, 3);

            $projected = round($covered + $amount, 3);
            if ($projected > $gross + $tolerance) {
                continue;
            }

            $ids[] = (int) $payment->id;
            $covered = $projected;
        }

        return ['ids' => $ids, 'covered' => $covered, 'pending' => $pending];
    }

    /**
     * MP-7-3-adjacent non-double-move guard: marks the still-unswept `Payment` rows this
     * payout-driven settlement actually COVERS as `completed = 1`, the SAME flag
     * `PaymentReleaseToCompanyBankAccProcess`'s own daily grouping query filters on. See this
     * class's own docblock ("Daily clearing->bank JV non-double-move") for the full reasoning —
     * this is a Payment-status write, never a `journal_entries`/`transactions` write, so it does
     * not go through PostingSeam and is not in ArchitectureTest's raw-writer scope.
     *
     * First adversarial pass (defect #1) replaced the original blind "every completed=0 payment on
     * or before the payout date" sweep with a greedy oldest-first consumption up to `gross`, so a
     * genuinely PARTIAL payout stops leaving the residual marked-but-unmoved. This pass keeps that
     * greedy walk but no longer lets it run when it does not land exactly on `gross`: Guard B in
     * {@see self::assertSettleable()} has already refused any non-aligned payout before a document
     * was ever drafted, precisely because a boolean `completed` flag cannot express "half of this
     * payment was released" and either rounding of it corrupts clearing (stranded money one way,
     * double-moved money the other). So by the time this runs, `covered` is known to equal `gross`
     * to the fils, and marking those ids is an exact statement of what the GWS document moved.
     *
     * Loop-3 re-verification: the ids are the ones Guard B VALIDATED, handed down from
     * {@see self::assertSettleable()}, not a fresh query run after the document posted. The old
     * re-query reopened the very hole Guard B closes whenever a receipt for this gateway landed
     * between the two reads (a webhook capture during an operator's payout entry): the greedy walk
     * would then settle on a different subset, and the document would drain `gross` while marking
     * a set that no longer sums to it.
     *
     * @param  list<int>  $validatedIds
     */
    private function skipDailyReleaseForSettledPayments(array $validatedIds): void
    {
        if ($validatedIds !== []) {
            Payment::withoutGlobalScopes()->whereIn('id', $validatedIds)->update(['completed' => 1]);
        }
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
     *
     * The rail itself is normalised through {@see self::normaliseRailCode()} first — see that
     * method's docblock (post-sign-off fix, T7 review packet §12 finding 4).
     */
    public static function channelFor(string $gatewayKey, ?string $railCode): string
    {
        $gateway = strtolower(trim($gatewayKey));
        $rail = self::normaliseRailCode($railCode);

        if ($rail === null) {
            return $gateway;
        }

        return $gateway.':'.$rail;
    }

    /**
     * Post-sign-off fix (T7 review packet §12 finding 4 — Fable orchestrator sign-off,
     * 2026-09-02): production Tap `PaymentMethod` codes (seeded by `TapPaymentMethodSeeder`) are
     * `src_kw.knet`, `src_card`, `src_deema`, `src_sa.mada`, `src_bh.benefit`, `src_qa.qpay` — Tap
     * source ids, not L12's plain vocabulary. A live receipt therefore stamped `tap:src_kw.knet`
     * while the plan's own vocabulary and the T7 test fixtures use `tap:knet` (and the fixture
     * seeder used the bare code `knet`). `PostingService` truncates `settlement_channel` to 24
     * chars silently, so the raw code could also collide/clip further down the line.
     *
     * This is the single place every settlement-channel writer funnels through
     * ({@see \App\Http\Controllers\PaymentController}, {@see \App\Http\Controllers\ClientController},
     * {@see \App\Console\Commands\CheckMyFatoorahPayments},
     * {@see \App\Console\Commands\PaymentReleaseToCompanyBankAccProcess}, and this service's own
     * `record()` default) — so the rail is canonicalised once, here, rather than scattered across
     * each call site.
     *
     * Rule: a Tap `src_...` source id strips its `src_` prefix, then — for a compound id like
     * `kw.knet` or `sa.mada` — keeps only the segment after the last `.` (the country/rail prefix
     * carries no information L12's vocabulary distinguishes on). This maps BOTH the production
     * shape and the plain rail name onto the same canonical token:
     *
     *   'src_kw.knet' -> 'knet'     'knet'  -> 'knet'   (already canonical — passthrough)
     *   'src_card'    -> 'card'     'card'  -> 'card'   (already canonical — passthrough)
     *   'src_deema'   -> 'deema'    'deema' -> 'deema'  (already canonical — passthrough)
     *   'src_sa.mada' -> 'mada'     'src_bh.benefit' -> 'benefit'   'src_qa.qpay' -> 'qpay'
     *
     * A code that is already bare (no `src_` prefix) is returned lower-cased and trimmed only —
     * never touched further — so every pre-existing test fixture that seeds a bare rail (e.g.
     * `knet`) keeps producing the exact same canonical token it always did.
     */
    private static function normaliseRailCode(?string $railCode): ?string
    {
        if ($railCode === null || trim($railCode) === '') {
            return null;
        }

        $rail = strtolower(trim($railCode));

        if (str_starts_with($rail, 'src_')) {
            $rail = substr($rail, 4);

            $lastDot = strrpos($rail, '.');
            if ($lastDot !== false) {
                $rail = substr($rail, $lastDot + 1);
            }
        }

        return $rail;
    }
}
