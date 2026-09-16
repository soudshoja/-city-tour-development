<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Scope;

use RuntimeException;

/**
 * CD-PORT — the ONE exception class every scope refusal in this namespace throws.
 *
 * Deliberately its own class rather than a bare {@see \RuntimeException}: the ported pipeline
 * already throws RuntimeException for a dozen unrelated reasons (a missing staging table, an
 * unresolvable purpose, a malformed CSV header), so a test that asserted `expectException(
 * RuntimeException::class)` around a scope guard would pass for entirely the wrong reason — the
 * exact "oracle too wide" pitfall the verify-escalation rule names. Every mutation proof in
 * tests/Feature/Legacy/LegacyScopeGuardTest.php catches THIS class and asserts on the message,
 * so a refusal that fires for a different reason fails the test instead of satisfying it.
 */
final class LegacyScopeRefused extends RuntimeException {}
