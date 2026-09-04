<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for VFS Global visa appointment confirmations.
 *
 * Format markers:
 *  - "Appointment Confirmation" header
 *  - "Group URN - <REFERENCE>" — group reference like KUW71861659214
 *  - "Visa Application Center" + center name (e.g. "Germany Visa Application
 *    Center- Kuwait")
 *  - "Appointment Date / Appointment Time" rows
 *  - Per-applicant rows: "<NAME> <PASSPORT> <TIME> <CATEGORY> <REF/N>"
 *
 * The PDF's "Payment Invoice" page DOES carry the VFS service fee — a per-applicant
 * "VFS Service Charge <fee>" plus a "Grand Total : KWD <n>". We surface the
 * per-applicant fee as the task price (each applicant is one task), because
 * TaskController::store() writes a transactions row whose `amount` column is
 * NOT NULL — a null price throws "Column 'amount' cannot be null" and the file
 * is dumped to files_error (root cause of VFS tasks silently going missing).
 */
class VfsGlobalParser
{
    public const SUPPLIER_NAME    = 'VFS Global';
    public const SUPPLIER_COUNTRY = 'India';
    public const SIGNATURE_A      = 'Appointment Confirmation';
    public const SIGNATURE_B      = 'Visa Application Center';
    public const SIGNATURE_C      = 'vfshelpline.com';

    private string $filePath;
    private string $text;
    /** @var string[] */
    private array $lines;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        $this->filePath = $filePath;
        $parser  = new PdfParser();
        $this->text = $parser->parseFile($filePath)->getText();
        $this->lines = explode("\n", $this->text);
    }

    public static function matches(string $rawText): bool
    {
        return (stripos($rawText, self::SIGNATURE_A) !== false
                && stripos($rawText, self::SIGNATURE_B) !== false)
            || stripos($rawText, self::SIGNATURE_C) !== false
            || stripos($rawText, 'vfsglobal.com') !== false;
    }

    public function parseTaskSchema(): array
    {
        $urn = $this->extractUrn();
        if (!$urn) {
            throw new \Exception("Could not locate VFS Group URN in PDF");
        }
        $apptDate    = $this->extractAppointmentDate();
        $apptTime    = $this->extractAppointmentTime();
        $issuedDate  = $this->extractIssuedDate();
        $center      = $this->extractCenter();
        $applicants  = $this->extractApplicants();
        if (empty($applicants)) $applicants = [['name' => 'UNKNOWN APPLICANT', 'urn' => $urn]];

        // Price = per-applicant VFS service fee = Grand Total / applicant count.
        // VFS charges a flat fee per applicant, so this reproduces the historical
        // per-task price exactly (74.04/6 = 12.34, 11.50/1 = 11.50). Default to 0
        // when the PDF has no pricing page so store() never hits the NOT NULL
        // `amount` wall (same "emit a numeric price" guard as FlyDubai / Vietnam).
        $grandTotal   = $this->extractGrandTotal();
        $perApplicant = ($grandTotal !== null && count($applicants) > 0)
            ? round($grandTotal / count($applicants), 3)
            : ($grandTotal ?? 0.0);

        $country  = $this->extractIssuingCountry();
        $category = $this->extractCategory();

        $tasks = [];
        foreach ($applicants as $a) {
            $tasks[] = [
                'additional_info'      => $this->buildAdditionalInfo($apptDate, $apptTime, $center, $a),
                'ticket_number'        => null,
                'gds_reference'        => $a['urn'] ?? $urn,
                'airline_reference'    => null,
                'status'               => 'issued',
                'supplier_status'      => 'issued',
                'refund_date'          => null,
                'void_date'            => null,
                'price'                => $perApplicant,
                'currency'             => 'KWD',
                'exchange_currency'    => 'KWD',
                'original_price'       => null,
                'original_currency'    => null,
                'total'                => $perApplicant,
                'surcharge'            => 0,
                'penalty_fee'          => null,
                'tax'                  => 0,
                'taxes_record'         => null,
                'refund_charge'        => null,
                'reference'            => $a['urn'] ?? $urn,
                'original_ticket_number' => null,
                'original_reference'   => null,
                'created_by'           => null,
                'issued_by'            => null,
                'iata_number'          => null,
                'type'                 => 'visa',
                'agent_name'           => null,
                'agent_email'          => null,
                'agent_amadeus_id'     => null,
                'client_name'          => $a['name'],
                'supplier_name'        => self::SUPPLIER_NAME,
                'supplier_country'     => self::SUPPLIER_COUNTRY,
                'cancellation_policy'  => 'Per VFS Global terms.',
                'venue'                => $center,
                // Issued date = when the appointment/payment was GENERATED (the
                // Payment-Invoice Transaction Date), NOT the appointment date. The
                // appointment date is kept in additional_info. Falls back to the
                // appointment date only if no transaction date is present.
                'issued_date'          => $issuedDate ?? $apptDate,
                'is_exchanged'         => false,
                'task_flight_details'  => [],
                // Populate the Visa Details panel. Only URN + issuing country +
                // category come from an appointment letter; expiry / entries /
                // stay aren't in it. number_of_entries is an ENUM and expiry/stay
                // are date/int — pass explicit null (never ''), else the insert
                // hits "1265 Data truncated" and the task falls back to files_error.
                'task_visa_details'    => [
                    'visa_type'          => $category,
                    'application_number' => $a['urn'] ?? $urn,
                    'expiry_date'        => null,
                    'number_of_entries'  => null,
                    'stay_duration'      => null,
                    'issuing_country'    => $country,
                    'appointment_date'   => $apptDate ? substr($apptDate, 0, 10) : null,
                ],
            ];
        }
        return $tasks;
    }

    private function extractUrn(): ?string
    {
        // Country prefix is 3 letters (KUW, ITA, NLD) OR 4 (SWKW = Switzerland-
        // Kuwait) — {3} alone threw "Could not locate VFS Group URN" on Swiss
        // appointments and dumped them to files_error.
        if (preg_match('/Group URN\s*-?\s*([A-Z]{3,4}\d{8,15})/i', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractAppointmentDate(): ?string
    {
        // Appointment date is DD-MM-YYYY (hyphens); transaction dates use slashes
        // (MM/DD/YYYY) so the hyphen pattern never grabs those. The value usually
        // sits on its own line under the "Appointment Date" label (the old regex
        // wrongly required the time on the SAME line, so multi-line layouts like
        // Italy's yielded null issued_date). Anchor on the label, else fall back
        // to the first hyphen-date in the document.
        if (preg_match('/Appointment Date[\s\S]{0,60}?(\d{2})-(\d{2})-(\d{4})/i', $this->text, $m)
            || preg_match('/(\d{2})-(\d{2})-(\d{4})/', $this->text, $m)) {
            return sprintf('%04d-%02d-%02d 00:00:00', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        return null;
    }

    /**
     * Issue date = when the appointment/payment was generated = the Payment-Invoice
     * "Transaction Date" (MM/DD/YYYY, slash-separated, optionally with a time). The
     * appointment date uses hyphens (DD-MM-YYYY) so the two never collide. Returns
     * "Y-m-d H:i:s" (time defaults to midnight when the PDF splits it off elsewhere).
     */
    private function extractIssuedDate(): ?string
    {
        if (preg_match('#(\d{2})/(\d{2})/(\d{4})(?:\s+(\d{1,2}:\d{2}:\d{2}))?#', $this->text, $m)) {
            $time = $m[4] ?? '00:00:00';
            return sprintf('%04d-%02d-%02d %s', (int)$m[3], (int)$m[1], (int)$m[2], $time);
        }
        return null;
    }

    /**
     * Destination country = the leading name in the center title
     * ("Italy Visa Application Center -Kuwait City" -> Italy; "Germany Visa
     * Application Center- Kuwait" -> Germany). Handles two-word countries
     * (Czech Republic) via the optional second word.
     */
    private function extractIssuingCountry(): ?string
    {
        // Country name (capitalised, 3+ letters, optional 2nd word) immediately
        // before "Visa Application Center". NO /i and {2,} so the "am" of
        // "10:00 am" can't match; the negative lookaheads exclude the words of a
        // preceding bare "…Visa Application Center" header so
        // "Center\nGermany Visa Application Center" resolves to "Germany" (not
        // "Center Germany"), while real two-word countries (Czech Republic) survive.
        if (preg_match('/\b(?!Cent(?:er|re)\b)(?!Visa\b)(?!Application\b)([A-Z][a-z]{2,}(?:\s+(?!Visa\b)[A-Z][a-z]+)?)\s+Visa Application Cent(?:er|re)/', $this->text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        return null;
    }

    /**
     * Best-effort visa category from the per-applicant row
     * ("<time> am/pm <CATEGORY> <URN>/<n>"). Returns null when the layout
     * doesn't line up — the visa-details row is still created from the URN +
     * issuing country, so a null category is harmless.
     */
    private function extractCategory(): ?string
    {
        // VFS categories are split across lines in the PDF, so match the known
        // phrasings loosely then collapse whitespace + drop the stray applicant-
        // count digit that bleeds in ("General (Business, Visit,\n6 Family Visit)").
        if (preg_match('/KUWAITI\s+NATIONAL(?:\s+ONLY)?/i', $this->text, $m)) {
            return preg_replace('/\s+/', ' ', trim($m[0]));
        }
        if (preg_match('/General\s*\(\s*Business[\s\S]{0,60}?Visit\s*\)/i', $this->text, $m)) {
            $c = preg_replace('/\s+/', ' ', trim($m[0]));
            return trim(preg_replace('/\s*\d+\s*/', ' ', $c));
        }
        return null;
    }

    private function extractAppointmentTime(): ?string
    {
        if (preg_match('/(\d{1,2}:\d{2})\s*([ap]m)/i', $this->text, $m)) {
            return $m[1] . ' ' . strtolower($m[2]);
        }
        return null;
    }

    /**
     * Total VFS service fee for the whole group, from the Payment Invoice page.
     * "Grand Total : KWD 74.04" is authoritative; "Total Amount : KWD 74.04" is
     * the same figure and used as a fallback. Returns null when the PDF has no
     * pricing page (caller then defaults the price to 0). smalot may drop the
     * space ("KWD74.04") so KWD is followed by \s* not \s+.
     */
    private function extractGrandTotal(): ?float
    {
        if (preg_match('/Grand\s*Total\s*:?\s*KWD\s*([\d,]+\.\d{1,3})/i', $this->text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        if (preg_match('/Total\s*Amount\s*:?\s*KWD\s*([\d,]+\.\d{1,3})/i', $this->text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        return null;
    }

    private function extractCenter(): ?string
    {
        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\s+Visa Application Cent(?:er|re)[^\n]*)/i', $this->text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * @return array<int, array{name: string, urn: string}>
     */
    private function extractApplicants(): array
    {
        $applicants = [];
        $seen = [];
        // Per-applicant: "<NAME ALL-CAPS> <PASSPORT_REDACTED> <TIME> ... <URN>/<N>"
        // Some lines split name+passport from urn. Use a 2-step scan.
        $urns = [];
        if (preg_match_all('/([A-Z]{3,4}\d{8,15})\/(\d+)/', $this->text, $um, PREG_SET_ORDER)) {
            foreach ($um as $m) {
                $urns[] = $m[1] . '/' . $m[2];
            }
        }
        // Names: ALL-CAPS phrase before passport pattern (P0xxxxxxNN)
        if (preg_match_all('/([A-Z][A-Z\s\']{4,40}?)\s+(P0[Xx0-9]{4,8}\d{2,4})/', $this->text, $matches, PREG_SET_ORDER)) {
            $i = 0;
            foreach ($matches as $m) {
                $name = trim(preg_replace('/\s+/', ' ', $m[1]));
                if (isset($seen[$name])) continue;
                $seen[$name] = true;
                $applicants[] = [
                    'name' => $name,
                    'urn'  => $urns[$i] ?? '',
                ];
                $i++;
            }
        }
        return $applicants;
    }

    private function buildAdditionalInfo(?string $apptDate, ?string $apptTime, ?string $center, array $applicant): string
    {
        $bits = [];
        if ($center) $bits[] = "Center: $center";
        if ($apptDate) $bits[] = "Appointment: " . substr($apptDate, 0, 10) . ($apptTime ? " $apptTime" : '');
        if (!empty($applicant['urn'])) $bits[] = "Applicant URN: " . $applicant['urn'];
        return implode(' | ', $bits);
    }
}
