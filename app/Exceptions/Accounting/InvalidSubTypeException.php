<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by App\Services\Accounting\VoucherSubTypeGuard::assertValid() (W5.L, w5-brief.md §W5.L
 * item 3: "doc_type RV|PV|AST accepted by the engine with sub_type lists") when a caller names a
 * `docType` this build has a REGISTERED sub_type vocabulary for (`config('accounting.sub_types')`)
 * but its `subType` is missing or not one of that vocabulary's members.
 *
 * NOT thrown by PostingService::post() itself — see VoucherSubTypeGuard's own docblock for why a
 * shared-engine-chokepoint enforcement point was tried first and proven wrong by execution
 * (docType='PV'/'RV' already carry several unrelated, already-shipped sub_type vocabularies).
 * Deliberately scoped: only doc_types present as a key in `config('accounting.sub_types')` are
 * governed at all, and only for a caller that explicitly opts in by calling the guard.
 */
final class InvalidSubTypeException extends PostingException
{
    /**
     * @param  string[]  $allowed
     */
    public function __construct(
        public readonly string $docType,
        public readonly ?string $subType,
        public readonly array $allowed,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "DocumentDraft::\$subType '%s' is not valid for docType='%s'. Allowed sub_types: [%s].",
            $this->subType ?? 'NULL',
            $this->docType,
            implode(', ', $this->allowed)
        ));
    }
}
