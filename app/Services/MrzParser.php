<?php

namespace App\Services;

/**
 * Parses a TD3 passport Machine-Readable Zone (the two 44-char lines at the
 * bottom of a passport) into the fields needed for an Amadeus SSR DOCS line.
 * The MRZ is authoritative: it carries the full (multi-word) surname, given
 * names, issuing country, document number, nationality, DOB and sex — all in
 * the exact code formats SSR expects. Returns null when the input doesn't look
 * like a TD3 MRZ, so callers can fall back to the human-readable AI fields.
 *
 * TD3 layout:
 *   Line 1: P<CCC SURNAME<<GIVEN<NAMES<<...   (P, filler, 3-letter issuer, names)
 *   Line 2: DDDDDDDDD c NNN YYMMDD c S YYMMDD ...  (docno, nat, DOB, sex, expiry)
 */
class MrzParser
{
    private const MONTHS = [
        '01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APR', '05' => 'MAY', '06' => 'JUN',
        '07' => 'JUL', '08' => 'AUG', '09' => 'SEP', '10' => 'OCT', '11' => 'NOV', '12' => 'DEC',
    ];

    /**
     * @return array{country_of_issue:string,surname:string,given_names:string,passport_no:string,nationality:string,dob_ssr:string,gender:string,expiry_ssr:string,personal_number:string}|null
     */
    public static function parseTd3(?string $line1, ?string $line2): ?array
    {
        $l1 = self::normalize($line1);
        $l2 = self::normalize($line2);
        if ($l1 === '' || $l2 === '') {
            return null;
        }
        // Line 1 must start with a passport document type (P).
        if ($l1[0] !== 'P') {
            return null;
        }

        $country = substr($l1, 2, 3);
        if (!preg_match('/^[A-Z]{3}$/', $country)) {
            return null;
        }

        // Names: everything after the issuer code, split on the first run of '<<'.
        $namePart = substr($l1, 5);
        $halves = preg_split('/<<+/', $namePart, 2);
        $surname = self::cleanName($halves[0] ?? '');
        $given = self::cleanName($halves[1] ?? '');
        if ($surname === '') {
            return null;
        }

        // Line 2 fixed offsets.
        $passport = rtrim(substr($l2, 0, 9), '<');
        $nationality = substr($l2, 10, 3);
        $dobYYMMDD = substr($l2, 13, 6);
        $sex = substr($l2, 20, 1);

        if (!preg_match('/^[A-Z]{3}$/', $nationality) || !preg_match('/^\d{6}$/', $dobYYMMDD)) {
            return null;
        }
        $dobSsr = self::dobToSsr($dobYYMMDD);
        if ($dobSsr === null) {
            return null;
        }
        $gender = in_array($sex, ['M', 'F'], true) ? $sex : '';

        // Expiry (offsets 21-26) and the optional personal number (offsets
        // 28-41; on Kuwaiti passports this is the 12-digit civil number).
        // Both are best-effort: vision transcriptions of line 2 are often
        // shifted/corrupt, so callers must validate before trusting them.
        $expiryYYMMDD = substr($l2, 21, 6);
        $expirySsr = preg_match('/^\d{6}$/', (string) $expiryYYMMDD)
            ? (self::dobToSsr($expiryYYMMDD) ?? '')
            : '';
        $personal = str_replace('<', '', (string) substr($l2, 28, 14));

        return [
            'country_of_issue' => $country,
            'surname' => $surname,
            'given_names' => $given,
            'passport_no' => $passport,
            'nationality' => $nationality,
            'dob_ssr' => $dobSsr,
            'gender' => $gender,
            'expiry_ssr' => $expirySsr,
            'personal_number' => $personal,
        ];
    }

    private static function normalize(?string $line): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $line));
    }

    /** '<'-separated name components -> space-joined, e.g. MOHAMED<MOKHTAR<FARRARA -> MOHAMED MOKHTAR FARRARA */
    private static function cleanName(string $part): string
    {
        $s = str_replace('<', ' ', $part);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** YYMMDD -> DDMMMYY (SSR DOB), e.g. 990527 -> 27MAY99 */
    private static function dobToSsr(string $yymmdd): ?string
    {
        $yy = substr($yymmdd, 0, 2);
        $mm = substr($yymmdd, 2, 2);
        $dd = substr($yymmdd, 4, 2);
        if (!isset(self::MONTHS[$mm])) {
            return null;
        }
        return $dd . self::MONTHS[$mm] . $yy;
    }
}
