<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Models\Transaction;

/**
 * Shared helpers every source needs and none should re-implement: "is this document already on the
 * ledger" and the two "has anything been posted for this key" lookups. Kept as a trait rather than
 * a base class so a source is free to extend a framework class if it ever needs to.
 *
 * CT-A3 wave 2 (W2-1).
 */
trait ChecksExistingDocument
{
    protected function existingDocument(int $companyId, string $idempotencyKey): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    protected function alreadyPosted(int $companyId, string $idempotencyKey): bool
    {
        return $this->existingDocument($companyId, $idempotencyKey) !== null;
    }
}
