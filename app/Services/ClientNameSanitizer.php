<?php

namespace App\Services;

/**
 * Defense-in-depth cleaner for client name fields coming out of AI passport
 * extraction. Vision models are instructed to transcribe the MRZ "exactly,
 * including every '<'" AND to split the name on '<<' themselves — and they
 * sometimes leak the raw MRZ filler/separator '<' into first_name/middle_name/
 * last_name (e.g. first_name "DOAA<<ABDELMONEM"). The controller persists those
 * fields verbatim, so this strips the MRZ artefacts at the storage boundary
 * regardless of which model produced them.
 *
 * Rule: '<' (MRZ filler) and abbreviation dots (e.g. "RAED F. H." on the
 * passport's printed name line) are never wanted in a stored name field. Strip
 * every character that isn't a letter, space, hyphen or apostrophe — hyphens and
 * apostrophes are kept because they occur in real names (AL-SAYED, O'BRIEN) —
 * then collapse runs of whitespace and trim. Returns null for null/blank input
 * so nullable columns stay null rather than becoming an empty string.
 */
class ClientNameSanitizer
{
    public static function clean(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        // Anything that isn't a (Unicode) letter, space, hyphen or apostrophe
        // becomes a space — this drops '<' filler, abbreviation dots and digits.
        $clean = preg_replace('/[^\p{L}\s\'-]+/u', ' ', $name);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));
        return $clean === '' ? null : $clean;
    }
}
