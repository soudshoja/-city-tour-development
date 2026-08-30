<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Setting;

/**
 * Resolves the two per-company voucher options config('accounting.vouchers') registers
 * (w5-brief.md §W5.L item 5) — `voucher_approval_threshold` and `pv_allow_overdraft` — via the
 * SAME `settings` table / Setting::getByKey() key-namespacing convention
 * SaleDraftBuilder::resolvePostingBasis() already established for
 * 'accounting.posting_basis.{service_type}' (see that method's own docblock): a plain per-company
 * key/value row, read with a config-level default when no row exists.
 *
 * Queue/webhook-safe by the same convention every other engine-layer class in this codebase
 * follows: $companyId is a plain argument, never resolved from Auth::user().
 *
 * Consumed by W5.R/W5.P's ReceiptVoucherController/BankPaymentController — not built in this
 * sub-wave. W5.L only ships the resolver and its config registration.
 */
final class VoucherOptions
{
    public const APPROVAL_THRESHOLD_KEY = 'accounting.voucher_approval_threshold';

    public const PV_ALLOW_OVERDRAFT_KEY = 'accounting.pv_allow_overdraft';

    /** W5.R — {@see overpayPolicy()}. */
    public const RV_OVERPAY_POLICY_KEY = 'accounting.rv_overpay_policy';

    /** W5.R — {@see receiptSendOnPayment()}. */
    public const RV_RECEIPT_SEND_ON_PAYMENT_KEY = 'accounting.rv_receipt_send_on_payment';

    /** W5.R — the only three legal values for {@see RV_OVERPAY_POLICY_KEY}. See that constant's
     * config('accounting.vouchers.rv_overpay_policy_default') docblock for what each one does. */
    public const RV_OVERPAY_POLICIES = ['credit', 'hold', 'block'];

    /**
     * Nullable amount. NULL means "always require a manual approve() step" — see
     * config('accounting.vouchers')'s own docblock. The underlying Setting row's `type` is
     * 'string' (the settings table's `type` enum has no 'float' member and 'integer' would
     * truncate a fils-level amount) — Setting::getValueAttribute() therefore hands back a numeric
     * string, cast to float here.
     */
    public static function approvalThreshold(int $companyId): ?float
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, self::APPROVAL_THRESHOLD_KEY, null) : null;

        if ($value === null) {
            $default = config('accounting.vouchers.voucher_approval_threshold_default');

            return $default === null ? null : (float) $default;
        }

        return (float) $value;
    }

    /**
     * Defaults to FALSE when unset — see config('accounting.vouchers')'s own docblock.
     */
    public static function pvAllowOverdraft(int $companyId): bool
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, self::PV_ALLOW_OVERDRAFT_KEY, null) : null;

        if ($value === null) {
            return (bool) config('accounting.vouchers.pv_allow_overdraft_default', false);
        }

        return (bool) $value;
    }

    /**
     * W5.R (w5-brief.md §W5.R). One of {@see RV_OVERPAY_POLICIES}. Falls back to the config
     * default when no Setting row exists, and to 'credit' (config's own hard default) if a stored
     * value somehow drifted outside the legal set — never lets an invalid stored string reach a
     * caller silently.
     */
    public static function overpayPolicy(int $companyId): string
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, self::RV_OVERPAY_POLICY_KEY, null) : null;

        $resolved = $value ?? config('accounting.vouchers.rv_overpay_policy_default', 'credit');

        return in_array($resolved, self::RV_OVERPAY_POLICIES, true) ? $resolved : 'credit';
    }

    /**
     * W5.R. Defaults to FALSE when unset — see config('accounting.vouchers')'s own docblock.
     */
    public static function receiptSendOnPayment(int $companyId): bool
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, self::RV_RECEIPT_SEND_ON_PAYMENT_KEY, null) : null;

        if ($value === null) {
            return (bool) config('accounting.vouchers.rv_receipt_send_on_payment_default', false);
        }

        return (bool) $value;
    }
}
