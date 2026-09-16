<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Replay;

/**
 * legacy-ledger-pilot LP3. A condition that stops the WHOLE run, not a type and
 * not a document.
 *
 * There are exactly four (MAPPING-RULES.md §1.8, §6, §4.2, plus the coordinator's
 * 2026-09-08 frozen-account ruling):
 *   - a posting-date SHIFT was detected (§6's silent parity killer);
 *   - a preflight assertion failed (a closed 2025 period, the engine off, a
 *     soft-deleted account behind a legacy `IsFreeze` leaf);
 *   - the seam returned something that is not a PostedDocument (§1.3);
 *   - the posted line count or sums did not equal the staged ones (§4.2, the
 *     parity crux made testable).
 *
 * Everything else is an O4 type stop or a single-document tag.
 */
final class LegacyReplayAborted extends \RuntimeException {}
