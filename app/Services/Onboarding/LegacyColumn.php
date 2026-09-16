<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * Single source of truth for how a legacy CSV header becomes a stg_* SQL
 * column name. Used by BOTH LegacyCsvLoader (which creates the columns) and
 * every reader of a stg_* table (CoaImporter, MastersAuditor, ...) so the
 * two sides can never drift apart.
 */
final class LegacyColumn
{
    public static function name(string $header): string
    {
        $name = strtolower(trim($header));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);

        return substr($name, 0, 64) ?: 'col';
    }
}
