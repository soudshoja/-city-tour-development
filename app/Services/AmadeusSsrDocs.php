<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds an Amadeus SSR DOCS cryptic string from extracted passport data, for the
 * WhatsApp client-creation flow. Returns null (caller skips sending) when any
 * required field is missing or malformed — never throws, never blocks client creation.
 *
 * Format (compact, single passenger, expiry included when known):
 *   SR DOCS YY HK1-P-{coi3}-{passport}-{nat3}-{DOB DDMMMYY}-{M|F}-{EXP DDMMMYY}-{SURNAME}-{GIVEN NAMES}/P{n}
 * Example:
 *   SR DOCS YY HK1-P-KWT-P06626745-KWT-23JUN90-F-18OCT32-ALAJMI-HEND THAMER MOHAMMED/P1
 */
class AmadeusSsrDocs
{
    public static function build(array $data, int $paxNo = 1): ?string
    {
        // The MRZ is authoritative when present: it carries the FULL (often
        // multi-word) surname, issuing country, doc number, nationality, DOB and
        // sex in exactly the SSR code formats. Fall back to the human-readable
        // AI fields per-field when the MRZ is missing/unreadable.
        $mrz = MrzParser::parseTd3($data['mrz_line1'] ?? null, $data['mrz_line2'] ?? null);

        // Doc number: MRZ first — but line-2 transcriptions get shifted, and a
        // shifted read puts the (all-digit) civil/personal number where the
        // passport number belongs. When the MRZ "passport" is digits-only and
        // is a prefix of the civil or MRZ personal number, that's proof of a
        // corrupt line 2 — use the AI's printed-zone read instead.
        $mrzPassport = strtoupper(trim((string) ($mrz['passport_no'] ?? '')));
        $aiPassport  = strtoupper(trim((string) ($data['passport_no'] ?? '')));
        $passport = $mrzPassport !== '' ? $mrzPassport : $aiPassport;
        if ($mrzPassport !== '' && $aiPassport !== '' && $mrzPassport !== $aiPassport && ctype_digit($mrzPassport)) {
            $civilNo  = (string) ($data['civil_no'] ?? '');
            $personal = (string) ($mrz['personal_number'] ?? '');
            if (($civilNo !== '' && str_starts_with($civilNo, $mrzPassport))
                || ($personal !== '' && $personal !== $mrzPassport && str_starts_with($personal, $mrzPassport))) {
                $passport = $aiPassport;
            }
        }
        $nat      = strtoupper(trim((string) ($mrz['nationality'] ?? $data['nationality_code'] ?? '')));
        $gender   = strtoupper(substr(trim((string) ($mrz['gender'] ?? $data['gender'] ?? '')), 0, 1));

        // Names: the AI's printed-zone read (last_name/first_name) is the reliable
        // source. Vision models transcribe the dense MRZ unreliably — injecting
        // place-of-birth words and digits into the name zone, mis-placing the '<<'
        // separator, or expanding the 3-letter issuer to a full country name — so
        // trusting the MRZ names produced garbage like
        //   "-HAWASH RAED-F H KUWAIT 02081009671021402 02".
        // Default to the AI name and only UPGRADE to the MRZ name when the MRZ
        // value is clean (letters only) AND strictly more complete than the AI
        // name (contains every AI word plus more). That preserves a full
        // multi-word MRZ surname where the AI truncated it (e.g. AI "FARRARA" ->
        // MRZ "MOHAMED MOKHTAR FARRARA") without ever importing MRZ garbage.
        // The SSR given-name element carries ALL given names (first + middle),
        // matching how the passenger is ticketed in the PNR.
        $aiGiven = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
        ])));
        $surname = self::pickName($data['last_name'] ?? null, $mrz['surname'] ?? null);
        $first   = self::pickName($aiGiven !== '' ? $aiGiven : null, $mrz['given_names'] ?? null);

        // Country of issue: prefer the AI's explicit read (the MRZ line-1 issuer is
        // the same unreliable field); fall back to the MRZ issuer, then nationality
        // (issuing country usually equals nationality).
        $coi = strtoupper(trim((string) ($data['country_of_issue'] ?? $data['nationality_code'] ?? $mrz['country_of_issue'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $coi)) {
            $coi = $nat;
        }

        // DOB: MRZ provides DDMMMYY directly; otherwise format date_of_birth.
        if (!empty($mrz['dob_ssr'])) {
            $dob = $mrz['dob_ssr'];
        } else {
            $dobRaw = trim((string) ($data['date_of_birth'] ?? ''));
            if ($dobRaw === '') {
                return null;
            }
            try {
                $dob = strtoupper(Carbon::parse($dobRaw)->format('dMy')); // e.g. 29APR97
            } catch (Throwable $e) {
                Log::debug('[AmadeusSsrDocs] unparseable date_of_birth: ' . $dobRaw);
                return null;
            }
        }

        if ($passport === '' || $surname === '' || $first === '') {
            return null;
        }
        if (!preg_match('/^[A-Z]{3}$/', $nat) || !preg_match('/^[A-Z]{3}$/', $coi)) {
            return null;
        }
        if ($gender !== 'M' && $gender !== 'F') {
            return null;
        }

        if ($paxNo < 1) {
            $paxNo = 1;
        }

        // Expiry: the AI's dedicated date_of_expiry field is the reliable
        // source (MRZ line-2 transcriptions shift); MRZ is the fallback. When
        // neither yields a date the segment is omitted rather than sent empty.
        $expiry = '';
        $expiryRaw = trim((string) ($data['date_of_expiry'] ?? ''));
        if ($expiryRaw !== '') {
            try {
                $expiry = strtoupper(Carbon::parse($expiryRaw)->format('dMy'));
            } catch (Throwable $e) {
                Log::debug('[AmadeusSsrDocs] unparseable date_of_expiry: ' . $expiryRaw);
            }
        }
        if ($expiry === '' && !empty($mrz['expiry_ssr'])) {
            $expiry = $mrz['expiry_ssr'];
        }
        $expirySegment = $expiry !== '' ? "-{$expiry}" : '';

        return "SR DOCS YY HK1-P-{$coi}-{$passport}-{$nat}-{$dob}-{$gender}{$expirySegment}-{$surname}-{$first}/P{$paxNo}";
    }

    /**
     * Choose between the AI printed-zone name and the MRZ name. Defaults to the
     * (more reliable) AI name; upgrades to the MRZ name only when the MRZ value is
     * a clean letters-only string that contains every word of the AI name and has
     * MORE words — i.e. the MRZ surname is genuinely fuller, not garbage. Both
     * values are normalised to letters+spaces first, so digits, dots and MRZ
     * filler can never reach the SSR segment.
     */
    private static function pickName(?string $ai, ?string $mrz): string
    {
        $aiClean  = self::normalizeName($ai);
        $mrzClean = self::normalizeName($mrz);

        $chosen = $aiClean !== '' ? $aiClean : $mrzClean;

        if ($mrzClean !== '') {
            $aiWords  = $aiClean === '' ? [] : explode(' ', $aiClean);
            $mrzWords = explode(' ', $mrzClean);
            $containsAllAiWords = true;
            foreach ($aiWords as $w) {
                if (!in_array($w, $mrzWords, true)) {
                    $containsAllAiWords = false;
                    break;
                }
            }
            if ($aiClean === '' || ($containsAllAiWords && count($mrzWords) > count($aiWords))) {
                $chosen = $mrzClean;
            }
        }

        return $chosen;
    }

    /** Uppercase, replace anything but A-Z with a space, collapse whitespace. */
    private static function normalizeName(?string $s): string
    {
        $s = strtoupper((string) $s);
        $s = preg_replace('/[^A-Z]+/', ' ', $s); // drop digits, dots, '<', punctuation
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
