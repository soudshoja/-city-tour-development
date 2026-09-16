<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Replay;

/**
 * legacy-ledger-pilot LP3. The mapper's own refusal (MAPPING-RULES.md §0.3:
 * "the mapper throws BEFORE calling the seam, and the document lands in LP3's
 * error queue classified. Never a silent skip, never a default account, never
 * `?? 0`").
 *
 * `$failureCode` is the classification token MAPPING-RULES names -- e.g.
 * `legacy.account_unmapped`, `legacy.party_unresolved`,
 * `legacy.both_columns_nonzero`, `withheld_ojv`. Whether a given code stops a
 * whole type or tags one document is NOT decided here: it is a lookup in
 * config('legacy_pilot.replay.o4.structural_failure_codes'), so O4 stays one
 * reviewable list rather than a property scattered over throw sites.
 */
final class LegacyDocumentRefused extends \RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        public readonly ?int $legacyDocId,
        string $message,
    ) {
        parent::__construct($message);
    }
}
