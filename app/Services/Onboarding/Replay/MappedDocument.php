<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Replay;

use App\Services\Accounting\DocumentDraft;

/**
 * legacy-ledger-pilot LP3. What {@see LegacyDocumentMapper::map()} returns: the
 * draft the seam will post, plus everything MAPPING-RULES.md §8.2's
 * `map_document` / `map_document_line` rows need that the draft itself does not
 * carry (the dropped per-line dimensions, the staged decimal sums, the
 * DC-mismatch and FC-rebase counts).
 *
 * A document the mapper decides is not postable AT ALL comes back as a skip
 * (§1.6): status `skipped_no_lines` or `skipped_all_zero`, `$draft === null`.
 * That is deliberately NOT an exception -- §1.6 is explicit that these are
 * "skip, count, report", "not an error". A refusal, by contrast, throws
 * {@see LegacyDocumentRefused}.
 */
final class MappedDocument
{
    public const STATUS_MAPPABLE = 'mappable';

    public const STATUS_SKIPPED_NO_LINES = 'skipped_no_lines';

    public const STATUS_SKIPPED_ALL_ZERO = 'skipped_all_zero';

    /**
     * @param  array<int, array<string, mixed>>  $lineAudits  one per KEPT line, in draft-line order,
     *                                                        shaped for map_document_line
     */
    public function __construct(
        public readonly string $status,
        public readonly ?DocumentDraft $draft,
        public readonly array $lineAudits,
        public readonly int $stagedDebitMillis,
        public readonly int $stagedCreditMillis,
        public readonly int $stagedLineCount,
        public readonly int $droppedZeroLineCount,
        public readonly int $dcMismatchLineCount,
        public readonly int $fcRebasedLineCount,
        public readonly int $offBranchLineCount,
        /**
         * LP1e / R-currency: lines that posted in the base currency because
         * their FcCurrID_FK could not be DERIVED from line usage (the legacy
         * currency master was never exported). Not a defect and not a
         * refusal -- the FC pair is metadata, recorded per line in
         * map_document_line.metadata_currency as `legacy_curr_<fk>`.
         */
        public readonly int $currencyUnresolvedLineCount = 0,
    ) {}

    public function isPostable(): bool
    {
        return $this->status === self::STATUS_MAPPABLE && $this->draft !== null;
    }
}
