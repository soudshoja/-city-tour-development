<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * CT-A3 E2 (CT-F35 — `.planning/phases/citytravelers-accounting-audit/CT-A2-ENGINE-REPLAY-
 * 2026-09-08.md` §3.2, CT-A1 §2.1): `invoice_receipts.company_id` is NULL on every one of the 109
 * legacy-imported receipt voucher rows. {@see \App\Http\Controllers\ReceiptVoucherController::
 * buildVoucherDraft()} used to do `$companyId = (int) $r->company_id;`, casting that NULL to the
 * sentinel `0` and sending every such row into `AccountResolver::resolve('CASH_IN_HAND', 0)`,
 * which throws `UnmappedPurposeException` (no `system_accounts` mapping is ever seeded for
 * company 0) — 109 of 109 refused to post, and the legacy (pre-engine) path silently never posted
 * 104 of them either (CT-A1 CT-F12).
 *
 * Thrown by {@see \App\Http\Controllers\ReceiptVoucherController::resolveReceiptCompanyId()} when
 * a row's own `company_id` is null/non-positive AND none of the fallback chain (invoice ->
 * agent -> branch; client -> company_id or client -> agent -> branch; task; account/bank_account;
 * branch) resolves to a positive company id either. Deliberately fatal, mirroring
 * {@see UnresolvedBranchException}'s own "never post the 0 sentinel" rule: a receipt voucher this
 * codebase cannot attribute to a real company must never post — silently posting it under company
 * 0 (or any guessed company) would misstate that company's books.
 */
final class UnresolvedReceiptCompanyException extends PostingException
{
    public function __construct(
        public readonly int $receiptId,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Cannot resolve a company for receipt voucher (invoice_receipts.id=%d): company_id is '
            .'null/non-positive and no link in the invoice/client/task/account/branch chain '
            .'resolves to a positive company id. Refusing to post under the 0 sentinel — run '
            .'`accounting:repair-receipt-company --apply` after fixing the underlying data link, '
            .'or attribute this receipt to a company manually.',
            $this->receiptId
        ));
    }
}
