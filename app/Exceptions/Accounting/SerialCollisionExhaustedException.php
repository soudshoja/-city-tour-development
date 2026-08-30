<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * PROPOSED NAME (W3-prereq lane B build). Thrown by PostingService::post() when the header
 * INSERT collides self::SERIAL_COLLISION_MAX_ATTEMPTS times in a row on
 * `transactions_company_doctype_refnum_unique` (migration
 * 2026_08_24_120008_add_unique_reference_number_to_transactions_table.php) — i.e. every serial
 * SequenceService::next() reserved for this (company_id, branch_id, doc_type, doc_year) inside
 * this single post() call already belongs to some other row (Accounting Gap/17-p1-
 * postingservice-complete.md §3.3/§4: an engine-minted number colliding with a LEGACY
 * reference_number already sitting in `transactions` under the same tuple, most commonly because
 * `accounting:seed-serial-schemas` has not yet raised serial_schemas.last_serial above that
 * legacy ceiling for this exact company/branch/doc_type/year).
 *
 * This is NOT the idempotency-key race or the payment-reference-type collision — see
 * PostingService::isSerialCollisionViolation()'s own docblock for how the header-insert catch
 * tells the three apart. Unlike those two, a single collision here is always retried in place
 * (bounded, logged via `Log::warning('accounting.serial_collision', …)` on each attempt) — this
 * exception exists only for the case where every bounded attempt still collided, which is not a
 * transient race but a real, actionable data problem: the caller gets nothing posted (the whole
 * DB::transaction() rolls back, so no header, no lines, and none of this call's own serial
 * reservations survive either — see the header-insert catch block's own docblock for why that
 * makes retrying this exact call immediately pointless without an operator first re-running
 * `accounting:seed-serial-schemas` for this company/branch/doc_type/year, or otherwise resolving
 * the underlying legacy-numbering overlap).
 */
final class SerialCollisionExhaustedException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $branchId = null,
        public readonly ?string $docType = null,
        public readonly ?int $docYear = null,
        public readonly ?string $lastAttemptedNumber = null,
        public readonly ?int $attempts = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Exhausted %s consecutive reference-number collisions on '
                .'transactions_company_doctype_refnum_unique for company_id=%s branch_id=%s '
                .'doc_type=%s doc_year=%s (last attempted number: %s). Nothing was posted. Run '
                .'`php artisan accounting:seed-serial-schemas` for this company/branch/doc_type/'
                .'year, or otherwise resolve the legacy-numbering overlap, before retrying.',
            $this->attempts !== null ? (string) $this->attempts : 'unknown',
            $this->companyId !== null ? (string) $this->companyId : 'unknown',
            $this->branchId !== null ? (string) $this->branchId : 'NULL',
            $this->docType ?? 'unknown',
            $this->docYear !== null ? (string) $this->docYear : 'unknown',
            $this->lastAttemptedNumber ?? 'unknown'
        ));
    }
}
