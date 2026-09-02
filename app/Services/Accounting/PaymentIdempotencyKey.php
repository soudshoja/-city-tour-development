<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * PROPOSED NAME (W2 build, Task B / D2). Shared idempotency-key derivation for gateway-payment
 * feeders, so a webhook handler and a status-check cron both compute the SAME key for the SAME
 * real-world business event — a gateway payment, optionally scoped to a specific SET of
 * InvoicePartial rows it is settling.
 *
 * WHY THIS EXISTS: keying on the payment alone (e.g. the retired `myfatoorah:payment:{id}`
 * shape) collapses every call for that payment into one key. `PaymentController::
 * createInvoicePaymentCOA()` accepts an optional `?array $partialIds`, and one payment can post
 * COA entries for more than one distinct SET of partials over its lifetime — a payment-scoped
 * key would make PostingSeam's S1 "already posted this key" guard silently skip every partial
 * set after the first, exactly the mistake already made and fixed for the salary feeder's
 * month-scoped key (see AgentControllerSalaryPostingTest / W1 lead report). Keying on the
 * business event — gateway + payment + the exact set of partials — instead of the payment alone
 * closes that class of bug for every caller of this helper, not just the one that already
 * learned the lesson.
 *
 * KEY SHAPE: `gateway:{gateway}:payment:{id}:partials:{sorted,comma ids | none}`.
 *   - `$gateway` is normalised lower-case — the key must never split one real event into two
 *     because two call sites capitalised the gateway name differently.
 *   - `$partialIds`, when non-null and non-empty, is DEDUPLICATED then sorted ascending and
 *     comma-joined, so `[3, 1, 2]` and `[2, 3, 1]` produce the IDENTICAL key (the business event
 *     is "this payment settled this SET of partials", not "in this order"), AND `[1, 1, 2]` and
 *     `[1, 2]` also produce the IDENTICAL key — residual 16 fix: a set has no notion of a
 *     repeated member, and the docblock's own "SET of partials" language already promised this;
 *     the array_unique() call below is what actually makes it true. Two calls with genuinely
 *     different partial SETS (as opposed to different multiplicities of the same set) always
 *     still produce different keys.
 *   - `null` or an empty array both mean "no partials" -> the literal segment `none` (a cron
 *     posting a plain advance, e.g. CheckMyFatoorahPayments, never has partials at all).
 *   - NEVER a timestamp — the whole point of an idempotency key is that retrying the SAME event
 *     later reproduces the SAME key, so PostingService's own idempotency lookup returns the
 *     existing document instead of colliding or double-posting.
 */
final class PaymentIdempotencyKey
{
    /**
     * @param  int[]|null  $partialIds
     */
    public static function forGatewayPayment(string $gateway, int $paymentId, ?array $partialIds = null): string
    {
        $normalisedGateway = strtolower(trim($gateway));

        $partialsSegment = 'none';
        if ($partialIds !== null && $partialIds !== []) {
            // residual 16 fix: array_unique() BEFORE sort() — without it, [1, 1, 2] and [1, 2]
            // produced different key strings ("1,1,2" vs "1,2") despite representing the SAME
            // set of partials, contradicting this class's own "SET semantics" docblock promise.
            $sortedPartialIds = array_values(array_unique(array_map('intval', $partialIds), SORT_NUMERIC));
            sort($sortedPartialIds, SORT_NUMERIC);
            $partialsSegment = implode(',', $sortedPartialIds);
        }

        return sprintf(
            'gateway:%s:payment:%d:partials:%s',
            $normalisedGateway,
            $paymentId,
            $partialsSegment
        );
    }

    /**
     * PROPOSED NAME extension (W2b build, KEY: draft-builder — design call E2; source-namespaced
     * W2c, orchestrator ruling B-2). Shared idempotency-key derivation for
     * {@see CreditApplicationDraftBuilder}: the business event is "this invoice had this SET of
     * applications applied", not the invoice alone — an invoice can go through more than one
     * distinct credit-application event over its lifetime (a partial applied today, another
     * applied next week), and keying on the invoice alone would collapse them onto one key,
     * making PostingSeam's "already posted this key" guard silently skip every application after
     * the first. This is the identical class of mistake {@see forGatewayPayment()}'s own
     * docblock documents for the gateway-payment case, fixed here the same way: key on the SET,
     * never on the parent record alone.
     *
     * ── W2c fix (B-2): the key must be namespaced by SOURCE TABLE, not just id ────────────────
     * W2b's first cut keyed on bare ints from TWO different tables' independent AUTO_INCREMENT
     * sequences (`payment_applications.id` in one feeder, `invoice_partials.id` in the other) —
     * small ids from two unrelated sequences collide routinely, so
     * `credit-apply:invoice:2:applications:5` meant two DIFFERENT real events depending on which
     * feeder posted it, and the second one to arrive silently posted nothing (W2b lead report §5,
     * B-2). Every element handed to this method now carries an explicit
     * {@see CreditApplicationInput::$idSource} (or an equivalent `[source, id]` pair — see below),
     * and the key embeds that source as its own segment, so the two tables can never share a
     * namespace even if their ids happen to coincide numerically.
     *
     * KEY SHAPE:
     *   - `credit-apply:invoice:{invoice_id}:pa:{sorted,comma ids}` when every application's
     *     source is {@see CreditApplicationInput::SOURCE_PAYMENT_APPLICATION}.
     *   - `credit-apply:invoice:{invoice_id}:partial:{sorted,comma ids}` when every application's
     *     source is {@see CreditApplicationInput::SOURCE_PARTIAL}.
     *   - A MIXED set (some 'pa', some 'partial') in the SAME call is refused with
     *     `\InvalidArgumentException` — a single credit-application EVENT comes from exactly one
     *     producer today, and silently picking one source to key on would reintroduce exactly the
     *     class of collision this fix exists to close.
     *
     * `$applications` accepts, per element, either a {@see CreditApplicationInput} instance or a
     * plain `[source, id]` pair (a 2-element array/list) — the latter exists so a caller (or a
     * test) that already has the source/id and does not want to construct a full
     * {@see CreditApplicationInput} can still derive the identical key.
     *
     * Every id — INCLUDING any {@see CreditApplicationDraftBuilder::build()} later skips for
     * being zero/negative (see {@see CreditApplicationInput::$amountApplied}) — must still be
     * represented: the key represents "this batch of applications was submitted", not "...the
     * ones that survived filtering", so a caller that resubmits the identical batch (skips and
     * all) must still land on the identical key. DEDUPLICATED then sorted ascending
     * (array_unique() before sort(), mirroring residual 16's fix above) within the one source —
     * the SAME set of application ids always produces the SAME key regardless of
     * collection/submission order, exactly like {@see forGatewayPayment()}'s own partial-id SET
     * semantics.
     *
     * @param  array<CreditApplicationInput|array{0: string, 1: int}>  $applications
     *
     * @throws \InvalidArgumentException When `$applications` is empty, contains an element this
     *                                   method cannot interpret, or mixes more than one
     *                                   `$idSource` in a single call.
     */
    public static function forCreditApplication(int $invoiceId, array $applications): string
    {
        if ($applications === []) {
            throw new \InvalidArgumentException(
                'PaymentIdempotencyKey::forCreditApplication() requires at least one application.'
            );
        }

        $sourcesSeen = [];
        $ids = [];

        foreach ($applications as $application) {
            [$source, $id] = self::extractSourceAndId($application);
            $sourcesSeen[$source] = true;
            $ids[] = $id;
        }

        if (count($sourcesSeen) > 1) {
            throw new \InvalidArgumentException(sprintf(
                'PaymentIdempotencyKey::forCreditApplication() received a MIXED source set for '
                .'invoice %d: %s. A single credit-application event must key on exactly one '
                .'source table (CreditApplicationInput::SOURCE_PAYMENT_APPLICATION or '
                .'::SOURCE_PARTIAL), never both at once — see class docblock, W2c fix (B-2).',
                $invoiceId,
                implode(', ', array_keys($sourcesSeen))
            ));
        }

        $source = array_key_first($sourcesSeen);

        $sortedIds = array_values(array_unique(array_map('intval', $ids), SORT_NUMERIC));
        sort($sortedIds, SORT_NUMERIC);

        return sprintf(
            'credit-apply:invoice:%d:%s:%s',
            $invoiceId,
            $source,
            implode(',', $sortedIds)
        );
    }

    /**
     * W4.D fix round 2 (w4-brief.md item 3 / Accounting Gap/22-plan-amendments.md rev 3 §4.1
     * gateway_fee row, ruling B10). Shared idempotency-key derivation for
     * {@see \App\Http\Controllers\InvoiceController::createGatewayFeeRecoveryEntries()}: the
     * business event is "this gateway payment recovered its client-borne fee", the SAME real-world
     * event {@see forGatewayPayment()} already keys the RECEIPT document by — deliberately namespaced
     * as a SEPARATE key (a trailing `:fee-recovery` segment) rather than reused bare, because the two
     * calls post two DIFFERENT documents (an RV and a DBN) for the same payment; sharing one key
     * would make PostingService's "already posted this key" guard resolve the DBN's own idempotency
     * lookup to the RV's transaction instead, or vice-versa.
     *
     * KEY SHAPE: `gateway:{gateway}:payment:{id}:partials:{sorted,comma ids | none}:fee-recovery` —
     * same gateway/payment/partials segments as {@see forGatewayPayment()} (SET semantics identical,
     * same dedup-then-sort treatment), so the two keys for one real payment event sort and grep
     * together, differing only in the trailing segment.
     *
     * @param  int[]|null  $partialIds
     */
    public static function forGatewayFeeRecovery(string $gateway, int $paymentId, ?array $partialIds = null): string
    {
        return self::forGatewayPayment($gateway, $paymentId, $partialIds).':fee-recovery';
    }

    /**
     * W4.C (w4-brief.md — "supplier cost posts in the sale's own period"). Shared idempotency-key
     * derivation for {@see SupplierCostCorrectionDraftBuilder}: the business event is "this
     * InvoiceDetail's supplier cost was corrected TO this specific amount", not "this InvoiceDetail
     * had ITS COST corrected at some point" — an InvoiceDetail can legitimately go through more
     * than one correction over its lifetime (an estimate corrected once, then corrected again when
     * the final supplier invoice lands), and keying on the InvoiceDetail alone would collapse every
     * correction after the first onto one key, making PostingSeam's "already posted this key" guard
     * silently skip every correction after the first — the identical class of mistake
     * {@see forGatewayPayment()}'s own docblock documents, fixed the same way: key on the EVENT
     * (which specific corrected amount), never on the parent record alone.
     *
     * Retrying the SAME correction (the same InvoiceDetail corrected to the same amount) MUST
     * reproduce the SAME key, so PostingService's own idempotency lookup returns the existing
     * document instead of double-posting — `$correctedCostAmount` is therefore formatted to a FIXED
     * number of decimal places (`config('accounting.engine.base_decimals')`, KWD 3dp), never a raw
     * float whose string representation can vary run to run (e.g. `120.0` vs `120.00`) for the
     * numerically identical amount.
     *
     * KEY SHAPE: `invoice-detail:{invoiceDetailId}:supplier-cost-correction:{correctedCostAmount}`
     * — namespaced under the same `invoice-detail:{id}:…` prefix
     * {@see \App\Http\Controllers\InvoiceController}'s own derived-document keys already use (e.g.
     * `invoice-detail:{id}:supplier-loss:agent`), so every idempotency key touching one
     * InvoiceDetail sorts and greps together.
     */
    public static function forSupplierCostCorrection(int $invoiceDetailId, float $correctedCostAmount): string
    {
        $decimals = (int) config('accounting.engine.base_decimals', 3);
        $normalisedAmount = number_format(round($correctedCostAmount, $decimals), $decimals, '.', '');

        return sprintf(
            'invoice-detail:%d:supplier-cost-correction:%s',
            $invoiceDetailId,
            $normalisedAmount
        );
    }

    /**
     * W4.R (w4-brief.md §4 "Gateway refund: listener for event GatewayRefundStatusChanged ...
     * PostingSeam Dr 2632|AR / Cr GATEWAY_CLEARING via
     * PaymentIdempotencyKey::forGatewayRefund(gateway, refundId) (add this factory if missing)").
     * The business event is "this gateway confirmed completion of THIS specific refund request" —
     * keyed on the gateway's own refund id (never the original payment id, which can carry more
     * than one refund over its lifetime — a partial refund followed by a second partial refund on
     * the same payment is two distinct completion events, not one).
     *
     * Deliberately namespaced `gateway-refund:...` — NEVER the bare `gateway:...` prefix
     * {@see forGatewayPayment()} uses for the ORIGINAL payment receipt — a refund completion and
     * the payment it refunds are different real-world events on the same payment_id and must never
     * collide in PostingService's idempotency-key lookup.
     *
     * KEY SHAPE: `gateway-refund:{gateway}:refund:{refundId}`. `$gateway` normalised the same way
     * {@see forGatewayPayment()} normalises it (lower-case, trimmed) so the two families sort and
     * grep together and can never split one real gateway into two keys over a casing difference.
     */
    public static function forGatewayRefund(string $gateway, string $refundId): string
    {
        $normalisedGateway = strtolower(trim($gateway));
        $normalisedRefundId = trim($refundId);

        return sprintf('gateway-refund:%s:refund:%s', $normalisedGateway, $normalisedRefundId);
    }

    /**
     * W4.R verify-fix round 3 (finding #2, MEDIUM): {@see \App\Http\Controllers\ClientController::
     * refundProcess()} — a standalone "pay out N of this client's pooled 2632 credit balance to
     * agent A" staff action, with no Refund/RefundClient/Credit row created yet at the point the
     * idempotency key must be computed (unlike every other feeder in this file, which keys off an
     * already-persisted document id) — previously baked `now()->format('YmdHis')` (wall-clock)
     * into the key, so a genuine retry of the identical request (double-click, network retry)
     * landed a FEW SECONDS later and got a DIFFERENT key every time, defeating
     * PostingService::post()'s own idempotency short-circuit entirely (never "the same key, so
     * return the existing document" — always "a brand new key, so post again").
     *
     * The only identity available BEFORE the write that is stable across a genuine retry, yet
     * still distinguishes one real payout request from another, is the request's own inputs: which
     * client, which agent is being paid out, and how much. Two calls with the SAME (client, agent,
     * amount) tuple are therefore treated as ONE logical operation — matching this class's own
     * established convention of keying by "the identity available at request time + a normalised
     * amount" (see {@see forSupplierCostCorrection()}'s identical shape/rationale). NEVER a
     * timestamp, matching every other factory here (see class docblock).
     *
     * KEY SHAPE: `client-credit-refund:{clientId}:agent:{agentId}:{normalisedAmount}`.
     */
    public static function forClientRefundOut(int $clientId, int $agentId, float $amount): string
    {
        $decimals = (int) config('accounting.engine.base_decimals', 3);
        $normalisedAmount = number_format(round($amount, $decimals), $decimals, '.', '');

        return sprintf('client-credit-refund:%d:agent:%d:%s', $clientId, $agentId, $normalisedAmount);
    }

    /**
     * W7.K (CreditController::creditTopup() through the seam, w7-brief.md §W7.K): the SAME
     * "no row exists yet at key-computation time" shape {@see forClientRefundOut()} solves, one
     * step earlier in the credit lifecycle — a plain client credit top-up creates its OWN new
     * `Credit` row per request (no external document, e.g. a Refund or Payment, feeds it), so
     * there is nothing with a stable, pre-existing id to key off. Deliberately NOT the literal
     * `credit:{credit_id}:create` shape a first pass at the brief suggested: `credit_id` is the
     * row THIS very request is about to create, so keying off it can never dedupe a genuine
     * retry — the second submission simply creates Credit #2 and mints key
     * "credit:2:create", distinct from the first submission's "credit:1:create", and both post —
     * reproducing, one method away, the exact wall-clock-key defect
     * {@see forClientRefundOut()}'s own docblock documents and w4-brief.md's verify-fix round 3
     * (finding #2) fixed for the payout side of this same balance. Same resolution: key off the
     * request's own stable inputs (client, agent, amount) instead, accepting the same tradeoff
     * forClientRefundOut() already accepts codebase-wide — two GENUINELY separate top-ups of the
     * identical (client, agent, amount) tuple collapse into one if submitted before the first
     * one's document exists, which CreditController::creditTopup() only invokes this pre-post
     * guard for anyway (see that method's own comment) — never a change to the OFF/legacy path,
     * which has no dedup today and keeps none.
     *
     * KEY SHAPE: `client-credit-topup:{clientId}:agent:{agentId}:payment:{paymentId}:{normalisedAmount}`.
     *
     * ── W7.Y fix (gate item 1, BLOCKER): a real `Payment` row's id is available BEFORE this key
     * is computed at every call site that uses this factory ({@see
     * \App\Http\Controllers\ClientController::addCredit()} — a gateway-driven topup, always fed
     * an existing `Payment`) — unlike {@see forManualClientCreditTopup()}'s own no-row-yet
     * problem (see that method's docblock), so there is no reason to keep this factory scoped to
     * (client, agent, amount) alone once a stable id exists to key on. Without `$paymentId`, two
     * GENUINELY different gateway payments for the SAME (client, agent, amount) tuple — the
     * ordinary case of a client topping up the identical amount on two different days — collapsed
     * onto ONE key: the second payment's own Credit row still posted (unconditional, per-payment
     * write), but its ledger document was silently swallowed by `PostingSeam`'s "already posted
     * this key" guard, understating `CLIENT_ADVANCE` by the second payment's full amount forever.
     * Embedding `$paymentId` closes this without touching the SAME-payment dedupe this key was
     * always also responsible for (a double gateway callback for the identical `Payment` row still
     * collapses to one document — the id is identical both times).
     *
     * Deliberately namespaced apart from {@see forManualClientCreditTopup()}'s own `:manual:`
     * segment — a gateway-fed topup and an admin-manual topup are different real-world business
     * events even when they happen to share (client, agent, amount), and must never resolve to the
     * same PostingService idempotency lookup.
     */
    public static function forClientCreditTopup(int $clientId, int $agentId, int $paymentId, float $amount): string
    {
        $decimals = (int) config('accounting.engine.base_decimals', 3);
        $normalisedAmount = number_format(round($amount, $decimals), $decimals, '.', '');

        return sprintf('client-credit-topup:%d:agent:%d:payment:%d:%s', $clientId, $agentId, $paymentId, $normalisedAmount);
    }

    /**
     * W7.Y fix (gate item 1, BLOCKER): split out of the old bare {@see forClientCreditTopup()}
     * shape, which {@see \App\Http\Controllers\CreditController::creditTopup()} (W7.K) also used —
     * this is that call site's OWN factory now, keeping the ORIGINAL (client, agent, amount)-only
     * shape (see the class's own W7.K docblock section for why credit_id can never be used here:
     * the key is computed BEFORE the `Credit` row this request is about to create exists, so
     * keying on that row's id could never dedupe a genuine retry). Namespaced with a literal
     * `:manual:` segment so this admin-manual topup path can never collide with
     * {@see forClientCreditTopup()}'s own (now payment-scoped) gateway-topup keys for the same
     * (client, agent, amount) tuple — closing the cross-flow collision half of gate item 1 (the
     * same-flow, "themselves across days" half is closed by `forClientCreditTopup()`'s new
     * `$paymentId` segment above; this factory's own tradeoff — two genuinely separate manual
     * top-ups of the identical tuple collapsing into one if submitted before the first's document
     * exists — is unchanged and remains an accepted, documented tradeoff, not a new gap).
     *
     * KEY SHAPE: `client-credit-topup:manual:{clientId}:agent:{agentId}:{normalisedAmount}`.
     */
    public static function forManualClientCreditTopup(int $clientId, int $agentId, float $amount): string
    {
        $decimals = (int) config('accounting.engine.base_decimals', 3);
        $normalisedAmount = number_format(round($amount, $decimals), $decimals, '.', '');

        return sprintf('client-credit-topup:manual:%d:agent:%d:%s', $clientId, $agentId, $normalisedAmount);
    }

    /**
     * W7.X (w7-final-gate.md §1a, BLOCKER 2 — `PaymentReleaseToCompanyBankAccProcess`'s daily
     * gateway-clearing-to-bank settlement cron). The business event is "this exact SET of
     * completed-but-unreleased payments, for this company/gateway/date group, settled to the bank
     * in one run" — same "key on the SET" convention {@see forGatewayPayment()}'s own docblock
     * establishes for the identical reason: keying on (companyId, gateway, date) ALONE would
     * collapse every run's group for that gateway/date into one key forever, so a LATER, genuinely
     * separate batch of payments that lands `completed=0`/`status=completed` for the SAME
     * gateway/date (the command's own `WHERE` clause has no upper bound on when a payment can
     * reach that state) would be silently skipped by `PostingSeam`'s S1 "already posted this key"
     * guard — never posted, and never released (`$payment->completed` stays 0 forever, since the
     * command's own re-run guard is exactly that WHERE clause). Keying on the payment SET instead
     * means only a genuine re-run of the IDENTICAL batch (the real "did a kill-switch flip mid-run,
     * or did the scheduler double-fire" case this key exists to guard against) collapses to one
     * document; two distinct batches for the same gateway/date always produce distinct keys.
     *
     * KEY SHAPE: `payment-release:company:{companyId}:gateway:{gateway}:date:{date}:payments:{sorted,comma ids}`.
     * `$gateway` normalised the same way {@see forGatewayPayment()} normalises it; `$date` is taken
     * verbatim (already a `Y-m-d` string at this feeder's own call site — grouped by that exact
     * format, so it is already stable/comparable as a string, needing no further normalisation).
     *
     * @param  int[]  $paymentIds
     */
    public static function forPaymentReleaseGroup(int $companyId, string $gateway, string $date, array $paymentIds): string
    {
        $normalisedGateway = strtolower(trim($gateway));

        $sortedPaymentIds = array_values(array_unique(array_map('intval', $paymentIds), SORT_NUMERIC));
        sort($sortedPaymentIds, SORT_NUMERIC);

        return sprintf(
            'payment-release:company:%d:gateway:%s:date:%s:payments:%s',
            $companyId,
            $normalisedGateway,
            $date,
            implode(',', $sortedPaymentIds)
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function extractSourceAndId(mixed $application): array
    {
        if ($application instanceof CreditApplicationInput) {
            return [$application->idSource, $application->id];
        }

        if (is_array($application) && array_key_exists(0, $application) && array_key_exists(1, $application)) {
            return [(string) $application[0], (int) $application[1]];
        }

        throw new \InvalidArgumentException(
            'PaymentIdempotencyKey::forCreditApplication() expects each element to be a '
            .'CreditApplicationInput or a [source, id] pair.'
        );
    }
}
