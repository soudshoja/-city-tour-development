<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deterministic post-extraction corrections for AI-read passport data, applied
 * before the data is persisted as a client or turned into an SSR DOCS line.
 * Vision models scramble structured fields in ways the document itself can
 * disprove: splitting given names into last_name (first "THAMER MOHAMMED" /
 * last "HEND AL AJMI" for passenger ALAJMI<<HEND<THAMER<MOHAMMED), or putting
 * the Kuwaiti 12-digit civil number in passport_no and the passport number in
 * civil_no. The MRZ line-1 structure and the civil-number date encoding are
 * both verifiable, so corrections happen only when the evidence proves the AI
 * fields wrong — never on heuristics alone.
 */
class PassportDataNormalizer
{
    public static function normalize(array $data): array
    {
        $mrz = MrzParser::parseTd3($data['mrz_line1'] ?? null, $data['mrz_line2'] ?? null);
        $data = self::fixNameSplit($data, $mrz);
        $data = self::fixCivilPassportSwap($data, $mrz);

        return $data;
    }

    /**
     * Adopt the MRZ surname/given-names split when the MRZ names are provably
     * the SAME name the AI read from the printed zone (identical letter
     * multiset), just split/ordered differently. Identical letters means the
     * MRZ transcription is clean — a corrupt one (place-of-birth words, digits,
     * truncations) can never pass, which is what keeps this safe.
     */
    private static function fixNameSplit(array $data, ?array $mrz): array
    {
        if (!$mrz || $mrz['surname'] === '' || $mrz['given_names'] === '') {
            return $data;
        }

        $mrzBag = self::letterBag($mrz['surname'] . $mrz['given_names']);
        if ($mrzBag === '') {
            return $data;
        }

        $candidates = [
            $data['name'] ?? null,
            trim(implode(' ', array_filter([
                $data['first_name'] ?? null,
                $data['middle_name'] ?? null,
                $data['last_name'] ?? null,
            ]))),
        ];
        $proven = false;
        foreach ($candidates as $candidate) {
            if ($candidate && self::letterBag($candidate) === $mrzBag) {
                $proven = true;
                break;
            }
        }
        if (!$proven) {
            return $data;
        }

        $givenWords = preg_split('/\s+/', trim($mrz['given_names']));
        $first = array_shift($givenWords);
        $middle = $givenWords ? implode(' ', $givenWords) : null;

        if (($data['first_name'] ?? null) !== $first
            || ($data['middle_name'] ?? null) !== $middle
            || ($data['last_name'] ?? null) !== $mrz['surname']) {
            Log::info('[PassportNormalizer] name split corrected from MRZ', [
                'ai' => [
                    'first' => $data['first_name'] ?? null,
                    'middle' => $data['middle_name'] ?? null,
                    'last' => $data['last_name'] ?? null,
                ],
                'mrz' => ['surname' => $mrz['surname'], 'given' => $mrz['given_names']],
            ]);
        }

        $data['first_name'] = $first;
        $data['middle_name'] = $middle;
        $data['last_name'] = $mrz['surname'];
        $data['name'] = trim($mrz['given_names'] . ' ' . $mrz['surname']);

        return $data;
    }

    /**
     * Swap civil_no/passport_no when the passport field provably holds a civil
     * number (12 digits, [123] century digit, embedded yymmdd that matches the
     * extracted date of birth when one is available) and the civil field does
     * not. Also backfills an empty civil_no from the MRZ personal number when
     * that value passes the same civil-number validation.
     */
    private static function fixCivilPassportSwap(array $data, ?array $mrz): array
    {
        $dob = $data['date_of_birth'] ?? null;
        $civil = trim((string) ($data['civil_no'] ?? ''));
        $passport = trim((string) ($data['passport_no'] ?? ''));

        if (self::looksLikeCivilNo($passport, $dob) && !self::looksLikeCivilNo($civil, $dob)) {
            Log::info('[PassportNormalizer] civil/passport swap corrected', [
                'ai_passport_no' => $passport,
                'ai_civil_no' => $civil,
            ]);
            [$civil, $passport] = [$passport, $civil];
        }

        if ($civil === '' && $mrz && self::looksLikeCivilNo($mrz['personal_number'], $dob)) {
            $civil = $mrz['personal_number'];
            Log::info('[PassportNormalizer] civil_no backfilled from MRZ personal number');
        }

        $data['civil_no'] = $civil !== '' ? $civil : null;
        $data['passport_no'] = $passport !== '' ? $passport : null;

        return $data;
    }

    /** Kuwaiti civil number: 12 digits, century digit 1-3, embedded yymmdd DOB. */
    public static function looksLikeCivilNo(?string $s, ?string $dob = null): bool
    {
        $s = trim((string) $s);
        if (!preg_match('/^[123]\d{11}$/', $s)) {
            return false;
        }
        $mm = (int) substr($s, 3, 2);
        $dd = (int) substr($s, 5, 2);
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return false;
        }
        if ($dob) {
            try {
                $d = Carbon::parse($dob);
                if ($d->format('ymd') !== substr($s, 1, 6)) {
                    return false;
                }
            } catch (Throwable $e) {
                // unparseable DOB: fall through to the format-only verdict
            }
        }

        return true;
    }

    /** Uppercase A-Z only, characters sorted — an order-insensitive letter multiset. */
    private static function letterBag(?string $s): string
    {
        $letters = preg_replace('/[^A-Z]+/', '', strtoupper((string) $s));
        $chars = str_split($letters);
        sort($chars);

        return implode('', $chars);
    }
}
