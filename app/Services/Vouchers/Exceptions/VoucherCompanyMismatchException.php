<?php

namespace App\Services\Vouchers\Exceptions;

use RuntimeException;

/**
 * Thrown whenever a record handed to VoucherDataRepository does not belong
 * to the company_id the caller claims to be operating as (plan §2.4/§11.2:
 * "every issue/send re-derives company_id from the subject row and refuses
 * mismatches between template-company, subject-company and client-company").
 *
 * This is a defensive guard, not the primary isolation mechanism — every
 * query in the repository already carries an explicit company_id. Seeing
 * this thrown means a caller upstream resolved the wrong task/package for
 * the company_id it passed in, which is exactly the class of bug this
 * exception exists to make loud instead of silently leaking data.
 */
class VoucherCompanyMismatchException extends RuntimeException
{
    public static function forSubject(string $subjectType, int $subjectId, ?int $subjectCompanyId, int $expectedCompanyId): self
    {
        return new self(sprintf(
            'Voucher data requested for %s #%d (company_id=%s) does not match the expected company_id=%d.',
            $subjectType,
            $subjectId,
            $subjectCompanyId === null ? 'NULL' : (string) $subjectCompanyId,
            $expectedCompanyId
        ));
    }
}
