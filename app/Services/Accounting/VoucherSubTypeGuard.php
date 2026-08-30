<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\InvalidSubTypeException;

/**
 * W5.L item 3 (w5-brief.md §W5.L: "doc_type RV|PV|AST accepted by the engine with sub_type
 * lists") — validates a docType/subType pair against config('accounting.sub_types') for the
 * NEW voucher feeders W5.R/W5.P/W5.S build, WITHOUT PostingService itself enforcing it.
 *
 * ── WHY THIS IS A CALLER-SIDE GUARD, NOT A PostingService::post() CHECK (proven by execution,
 *    not judgement) ─────────────────────────────────────────────────────────────────────────────
 * The first version of this fix put the check directly inside PostingService::post(), reasoning
 * (from w5-brief.md's own text alone) that RV/PV/AST were new enough that no feeder yet depended on
 * an undocumented sub_type shape. That reasoning was WRONG, and running the full accounting suite
 * proved it immediately: docType='PV' already has FOUR independent, already-shipped, already-
 * tested feeders with their OWN sub_type vocabulary that has nothing to do with W5's voucher taxonomy
 * — `ClientController.php` ('CLIENT_REFUND'), `HandleGatewayRefundStatusChanged.php`
 * ('REFUND_GW_PAYOUT'), and `RefundPostingService.php` alone uses six of its own ('REFUND_DISPO',
 * 'REFUND_SUPPLIER', 'AGENT_COMMISSION', 'REFUND_CLAWBACK', 'REFUND_RECHARGE',
 * 'CRN_LEGACY_SALE' — the last on docType CRN, the rest on PV). docType='RV' has two more
 * (`CheckMyFatoorahPayments.php`'s 'MYFATOORAH', `PaymentController.php`'s dynamic per-gateway
 * key). None of those nine real sub_type values is a member of config('accounting.sub_types')'s RV/
 * PV lists — a PostingService-level enforcement point rejected every one of them, breaking
 * PostingServiceBalanceTest, PostingServiceRepostPaymentIdTest, RefundPostingServiceTest,
 * RefundControllerW4RTest, RefundControllerW4UTest, SettingControllerAccountingSettingsTest, and
 * W4UReverifyRound3Test outright.
 *
 * `docType='PV'`/`'RV'` is therefore a SHARED namespace, not a namespace this wave owns
 * exclusively — file 11's own contract never scoped one docType to one feature area, and W4's
 * refund lane already legitimately uses PV for money-out events with its own vocabulary.
 * Enforcing a single whitelist at PostingService's one shared chokepoint would require that
 * whitelist to enumerate EVERY sub_type every current and future PV/RV feeder ever needs — the
 * opposite of a scoped, useful vocabulary check, and a standing invitation for the NEXT feature to
 * either break this check or bypass it by construction.
 *
 * THE FIX: this guard is a plain, stateless validator a feeder calls explicitly, at the point it
 * builds its OWN DocumentDraft, before handing it to PostingSeam::post() — the same "caller opts
 * in" shape AccountResolver::assertUnderBankGroup() already uses for a per-voucher structural
 * check. W5.R/W5.P/W5.S (not built in this sub-wave) are expected to call
 * `VoucherSubTypeGuard::assertValid($docType, $subType)` themselves; nothing changes for any
 * existing PV/RV feeder that never calls it, including every one of the nine listed above.
 * `AST` currently has ZERO existing feeders (a repo-wide grep confirms no legacy call site ever
 * constructs an AST DocumentDraft), so governing it costs nothing either way — kept on the same
 * caller-side guard as RV/PV for one consistent mechanism, not enforced differently per doc_type.
 */
final class VoucherSubTypeGuard
{
    /**
     * @throws InvalidSubTypeException when config('accounting.sub_types') has an entry for
     *                                 $docType and $subType is missing or not one of that entry's values. A $docType with no entry
     *                                 in that config is a no-op — this guard only ever governs what it explicitly registers.
     */
    public static function assertValid(string $docType, ?string $subType): void
    {
        $governedSubTypes = config('accounting.sub_types', []);

        if (! array_key_exists($docType, $governedSubTypes)) {
            return;
        }

        $allowed = $governedSubTypes[$docType];

        if ($subType === null || ! in_array($subType, $allowed, true)) {
            throw new InvalidSubTypeException($docType, $subType, $allowed);
        }
    }
}
