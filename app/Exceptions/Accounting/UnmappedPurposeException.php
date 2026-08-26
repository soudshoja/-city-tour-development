<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by AccountResolver::resolve() when no system_accounts row maps a given
 * (companyId, purposeCode, serviceType) triple to a leaf account — the engine must never silently
 * skip a leg, so an unmapped purpose is fatal to the whole post(), not a warning.
 */
final class UnmappedPurposeException extends PostingException
{
    public function __construct(
        public readonly string $purposeCode,
        public readonly int $companyId,
        public readonly ?string $serviceType = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'No system_accounts mapping for purpose_code=%s, company_id=%d%s.',
            $this->purposeCode,
            $this->companyId,
            $this->serviceType !== null ? ", service_type={$this->serviceType}" : ''
        ));
    }
}
