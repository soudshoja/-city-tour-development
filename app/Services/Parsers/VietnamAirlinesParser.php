<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Vietnam Airlines "Electronic Ticket Receipt" PDFs
 * (sender no-reply@service.vietnamairlines.com), as forwarded to the per-agent
 * cPanel ingest mailboxes (e.g. saeid@...). Issued e-tickets only.
 *
 * ── The cipher ──────────────────────────────────────────────────────────────
 * Vietnam Airlines renders the receipt with a custom subset font whose glyph
 * codes are a UNIFORM Caesar shift of real ASCII (observed offset +29), and
 * Smalot emits one NUL (\0) between every glyph with NO spaces at word
 * boundaries. So `Smalot::getText()` yields e.g.
 *     "9\0,\0(\07\01\0$\00\0$\0,\05\0/\0,\01\0(\06"   ->  "VIETNAMAIRLINES"
 * and digits arrive as control bytes 19..28 ('0'..'9' minus 29). decodeCipher()
 * strips the NULs and un-shifts, self-calibrating the offset from the text so a
 * different (but still uniform) font subset can't silently break extraction —
 * if it can't recover the brand markers it returns the raw text and matches()
 * simply declines (fail closed → file stays unrouted, never a garbage task).
 *
 * ── Currency ────────────────────────────────────────────────────────────────
 * The receipt totals in USD ("Total amount: USD 203.50"). We mirror
 * RateHawkParser's foreign-currency contract exactly (price/total left null,
 * original_price/original_currency carry the USD figure) so the same downstream
 * USD→KWD conversion that already works for RateHawk applies unchanged.
 *
 * Returns the same task shape as AirFileParser::parseTaskSchema() — one array
 * per passenger.
 */
class VietnamAirlinesParser
{
    public const SUPPLIER_NAME    = 'Vietnam Airlines';
    public const SUPPLIER_COUNTRY = 'Vietnam';
    public const IATA_CODE        = 'VN';

    /** Observed font offset; tried first before self-calibration. */
    private const DEFAULT_OFFSET = 29;

    private ?string $filePath = null;
    private string $text = '';

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            $this->filePath = $filePath;
            $parser = new PdfParser();
            $raw = $parser->parseFile($filePath)->getText();
            $this->text = self::decodeCipher($raw);
        }
    }

    public static function matches(string $rawText): bool
    {
        $dec = self::decodeCipher($rawText);
        $flat = self::flatten($dec);
        // Vietnam Airlines AND an issued e-ticket receipt (skip marketing /
        // schedule-change notices that lack the receipt body + ticket number).
        $isVn = stripos($flat, 'VIETNAMAIRLINES') !== false;
        $isReceipt = stripos($flat, 'ELECTRONICTICKET') !== false
            || (stripos($flat, 'BOOKINGREF') !== false && preg_match('/TICKETNUMBER:?\d{13}/i', $flat) === 1);
        return $isVn && $isReceipt;
    }

    public function parseTaskSchema(): array
    {
        $pnr = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception('Could not locate Vietnam Airlines booking reference');
        }

        $passengers = $this->extractPassengers();   // [['name','type','ticket'], ...]
        if (empty($passengers)) {
            $passengers = [['name' => 'UNKNOWN PASSENGER', 'type' => 'ADT', 'ticket' => $this->extractTicket()]];
        }
        $totalCcy  = $this->extractTotal();          // ['total'=>?, 'currency'=>...]
        $flights   = $this->extractFlights();
        $issueDate = $this->extractIssueDate() ?? date('Y-m-d 00:00:00');
        $venue     = !empty($flights) ? (end($flights)['airport_to'] ?? null) : null;

        // One total on the receipt → split evenly across passengers (usually 1).
        $perPax = ($totalCcy['total'] !== null && count($passengers) > 0)
            ? round($totalCcy['total'] / count($passengers), 3)
            : null;
        $ccy   = $totalCcy['currency'] ?? 'USD';
        $isKwd = ($ccy === 'KWD');

        $tasks = [];
        foreach ($passengers as $pax) {
            $ticket = $this->ndcTicketSerial($pax['ticket']);
            $tasks[] = [
                'additional_info'        => $this->buildAdditionalInfo($pnr, $issueDate, $passengers, $totalCcy),
                'ticket_number'          => $ticket,
                'gds_reference'          => $pnr,
                'airline_reference'      => $pnr,
                'status'                 => 'issued',
                'supplier_status'        => 'issued',
                'refund_date'            => null,
                'void_date'              => null,
                // Mirror RateHawkParser: KWD goes in price, foreign currency goes
                // in original_* and is converted downstream. Never emit a null
                // price for a KWD ticket (transactions.amount is NOT NULL).
                'price'                  => $isKwd ? ($perPax ?? 0) : null,
                'currency'               => $ccy,
                'exchange_currency'      => $ccy,
                'original_price'         => $isKwd ? null : $perPax,
                'original_currency'      => $isKwd ? null : $ccy,
                'total'                  => $isKwd ? ($perPax ?? 0) : null,
                'original_total'         => $isKwd ? null : $perPax,
                'surcharge'              => 0,
                'penalty_fee'            => null,
                'tax'                    => 0,
                'taxes_record'           => null,
                'refund_charge'          => null,
                'reference'              => $ticket ?: $pnr,
                'original_ticket_number' => null,
                'original_reference'     => null,
                'created_by'             => null,
                'issued_by'              => null,
                'iata_number'            => null,
                'type'                   => 'flight',
                'agent_name'             => null,
                'agent_email'            => null,
                'agent_amadeus_id'       => null,
                'client_name'            => $pax['name'],
                'passenger_name'         => $pax['name'],
                'supplier_name'          => self::SUPPLIER_NAME,
                'supplier_country'       => self::SUPPLIER_COUNTRY,
                'cancellation_policy'    => 'Per Vietnam Airlines fare rules.',
                'venue'                  => $venue,
                'issued_date'            => $issueDate,
                'supplier_pay_date'      => $issueDate,
                'is_exchanged'           => false,
                'task_flight_details'    => array_map(function ($f) use ($ticket) {
                    $f['airline_id']    = self::SUPPLIER_NAME;
                    $f['ticket_number'] = $ticket;
                    return $f;
                }, $flights),
            ];
        }

        return $tasks;
    }

    // ──────────────────────────── decode ────────────────────────────

    /**
     * Strip the inter-glyph NULs and un-shift the uniform Caesar cipher.
     * Self-calibrates the offset: tries the known +29, then a
     * most-frequent-glyph→'e' estimate, then a bounded brute force, accepting
     * the first that recovers the "VIETNAMAIRLINES" brand. Returns the raw text
     * unchanged when nothing recovers it (non-Vietnam or unciphered PDFs).
     */
    public static function decodeCipher(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        // Unciphered already? (defensive — some forwards may rasterise to plain text.)
        if (stripos($raw, 'VIETNAM') !== false && stripos($raw, 'TICKET') !== false) {
            return $raw;
        }

        $stripped = str_replace("\0", '', $raw);

        $candidates = [self::DEFAULT_OFFSET];
        $freq = [];
        $len = strlen($stripped);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($stripped[$i]);
            if ($o >= 33 && $o <= 126) {
                $freq[$o] = ($freq[$o] ?? 0) + 1;
            }
        }
        if (!empty($freq)) {
            arsort($freq);
            $candidates[] = 101 - array_key_first($freq); // top glyph ≈ 'e'
        }

        foreach ($candidates as $k) {
            $dec = self::shift($stripped, $k);
            if (stripos($dec, 'VIETNAMAIRLINES') !== false || stripos($dec, 'VIETNAM AIRLINES') !== false) {
                return $dec;
            }
        }
        for ($k = 1; $k <= 94; $k++) {
            $dec = self::shift($stripped, $k);
            if (stripos($dec, 'VIETNAMAIRLINES') !== false) {
                return $dec;
            }
        }
        return $stripped;
    }

    /** Shift every byte by $k, leaving newlines/tabs and out-of-band bytes intact. */
    private static function shift(string $s, int $k): string
    {
        if ($k === 0) {
            return $s;
        }
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($s[$i]);
            if ($o === 9 || $o === 10 || $o === 13) {
                $out .= $s[$i];
                continue;
            }
            $n = $o + $k;
            $out .= ($n >= 32 && $n <= 126) ? chr($n) : $s[$i];
        }
        return $out;
    }

    /** Collapse all whitespace — fields print glued, so flat matching is reliable. */
    private static function flatten(string $t): string
    {
        return preg_replace('/\s+/', '', $t);
    }

    // ──────────────────────────── extractors ────────────────────────────

    private function extractPnr(): ?string
    {
        $flat = self::flatten($this->text);
        if (preg_match('/Bookingref:?([A-Z0-9]{5,7}?)(?=Ticketnumber|Electronic|$)/i', $flat, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/Bookingref:?([A-Z0-9]{6})/i', $flat, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    private function extractTicket(): ?string
    {
        $flat = self::flatten($this->text);
        if (preg_match('/Ticketnumber:?(\d{13})/i', $flat, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @return array<int, array{name:string, type:string, ticket:?string}>
     */
    private function extractPassengers(): array
    {
        $flat = self::flatten($this->text);
        $ticket = $this->extractTicket();

        $passengers = [];
        $seen = [];
        if (preg_match_all('/Passenger:?([A-Za-z][A-Za-z.\'\-]{1,60}?)\((ADT|CHD|INF|ADULT|CHILD|INFANT)\)/i', $flat, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $name = $this->cleanName($m[1]);
                $key  = strtoupper($name);
                if ($name === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $passengers[] = ['name' => $name, 'type' => $this->mapPaxType($m[2]), 'ticket' => $ticket];
            }
        }
        return $passengers;
    }

    /**
     * The receipt totals in USD. Prefer the explicit "Total amount: USD nnn.nn";
     * fall back to the "Fare equivalent" line. Returns currency=USD by default.
     * @return array{total: ?float, currency: string}
     */
    private function extractTotal(): array
    {
        $flat = self::flatten($this->text);
        if (preg_match('/Totalamount:?([A-Z]{3})([\d,]+\.\d{2})/i', $flat, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        if (preg_match('/Fareequivalent:?([A-Z]{3})([\d,]+\.\d{2})/i', $flat, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        // Amount-first defensive variant.
        if (preg_match('/Totalamount:?([\d,]+\.\d{2})([A-Z]{3})/i', $flat, $m)) {
            return ['total' => (float) str_replace(',', '', $m[1]), 'currency' => strtoupper($m[2])];
        }
        return ['total' => null, 'currency' => 'USD'];
    }

    private function extractIssueDate(): ?string
    {
        $flat = self::flatten($this->text);
        if (preg_match('/Date:?(\d{1,2})([A-Za-z]{3})(\d{4})/', $flat, $m)) {
            return $this->isoDate((int) $m[1], $m[2], (int) $m[3]);
        }
        return null;
    }

    /**
     * Best-effort segments. In the flattened receipt each segment prints as a
     * tight quartet: "VN<no><dep-HH:MM><dep-DDMonYYYY><arr-HH:MM><arr-DDMonYYYY>".
     * Anchoring on that whole quartet (rather than collecting all dates/times
     * separately) ignores the issue date and the "Duration HH:MM" noise that
     * would otherwise misalign the segments. Airport IATA codes come from the
     * Fare Calculation ("BKK VN SGN … VN BKK"): origin + each VN-prefixed code.
     * Returns [] if it can't pair confidently — the task still loads (venue null).
     * @return array<int, array<string, mixed>>
     */
    private function extractFlights(): array
    {
        $flat = self::flatten($this->text);

        // Airport sequence from the fare calc: first 3 letters = origin, then
        // every code that immediately follows the "VN" carrier marker.
        $codes = [];
        if (preg_match('/FareCalculation:?([A-Z]{3})(.*?)(?:NUC|END|ROE)/is', $flat, $fc)) {
            $codes[] = $fc[1];
            if (preg_match_all('/VN([A-Z]{3})/', $fc[2], $cm)) {
                foreach ($cm[1] as $c) {
                    $codes[] = $c;
                }
            }
        }

        // Cabin per segment ("Class: ECONOMY LITE" / "ECONOMY SUPER LITE").
        $cabins = [];
        if (preg_match_all('/Class:?(ECONOMY|BUSINESS|FIRST|PREMIUM)/i', $flat, $cab)) {
            $cabins = $cab[1];
        }

        // Greedy 3-4 digit flight no., then a colon-anchored 2-digit-hour time
        // forces the correct flight/time split on the glued "VN60011:20".
        $re = '/VN(\d{3,4})(\d{2}:\d{2})(\d{1,2})([A-Za-z]{3})(\d{4})(\d{2}:\d{2})(\d{1,2})([A-Za-z]{3})(\d{4})/';
        $flights = [];
        if (preg_match_all($re, $flat, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $i => $m) {
                $flights[] = [
                    'flight_number'  => 'VN ' . $m[1],
                    'airport_from'   => $codes[$i]     ?? null,
                    'airport_to'     => $codes[$i + 1] ?? null,
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => $this->isoDateTime((int) $m[5], $m[4], (int) $m[3], $m[2]),
                    'arrival_time'   => $this->isoDateTime((int) $m[9], $m[8], (int) $m[7], $m[6]),
                    'duration_time'  => null,
                    'airline_name'   => self::SUPPLIER_NAME,
                    'class_type'     => strtolower($cabins[$i] ?? 'economy'),
                    'baggage_allowed'=> null,
                    'equipment'      => null,
                    'flight_meal'    => null,
                    'seat_no'        => null,
                    'farebase'       => null,
                    'ticket_number'  => null,
                ];
            }
            return $flights;
        }

        // Fallback: flight numbers only (itinerary layout without the quartet).
        if (preg_match_all('/VN(\d{3,4})(\d{2}:\d{2})/', $flat, $fm, PREG_SET_ORDER)) {
            foreach ($fm as $i => $m) {
                $flights[] = [
                    'flight_number'  => 'VN ' . $m[1],
                    'airport_from'   => $codes[$i]     ?? null,
                    'airport_to'     => $codes[$i + 1] ?? null,
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => null,
                    'arrival_time'   => null,
                    'duration_time'  => null,
                    'airline_name'   => self::SUPPLIER_NAME,
                    'class_type'     => strtolower($cabins[$i] ?? 'economy'),
                    'baggage_allowed'=> null,
                    'equipment'      => null,
                    'flight_meal'    => null,
                    'seat_no'        => null,
                    'farebase'       => null,
                    'ticket_number'  => null,
                ];
            }
        }
        return $flights;
    }

    // ──────────────────────────── helpers ────────────────────────────

    private function mapPaxType(string $raw): string
    {
        $r = strtoupper($raw);
        if (str_starts_with($r, 'CHILD') || $r === 'CHD') return 'CHD';
        if (str_starts_with($r, 'INFANT') || $r === 'INF') return 'INF';
        return 'ADT';
    }

    /**
     * Names print glued and title-cased ("AlawadhiBaderMr"). Split on the
     * lower→upper camel boundary to recover words, drop the trailing salutation,
     * and return the human-readable remainder ("Alawadhi Bader").
     */
    private function cleanName(string $raw): string
    {
        $raw = preg_replace('/[^A-Za-z.\'\-]/', '', $raw);
        $spaced = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $raw);
        $spaced = trim(preg_replace('/\s+/', ' ', $spaced));
        // strip a trailing salutation token
        $spaced = preg_replace('/\s+(Mr|Mrs|Ms|Miss|Mstr|Master|Dr)\.?$/i', '', $spaced);
        return $spaced;
    }

    /**
     * IATA e-ticket numbers are 13 digits = 3-digit airline code (738 = Vietnam
     * Airlines) + 10-digit serial. CityTour stores the 10-digit serial.
     */
    private function ndcTicketSerial(?string $doc): ?string
    {
        if ($doc === null) {
            return null;
        }
        $d = preg_replace('/\D/', '', $doc);
        return strlen($d) === 13 ? substr($d, 3) : $doc;
    }

    private function monthNum(string $mon): ?int
    {
        $months = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
                   'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];
        return $months[strtoupper(substr($mon, 0, 3))] ?? null;
    }

    private function isoDate(int $day, string $mon, int $year): ?string
    {
        $mm = $this->monthNum($mon);
        if (!$mm) {
            return null;
        }
        return sprintf('%04d-%02d-%02d 00:00:00', $year, $mm, $day);
    }

    private function isoDateTime(int $year, string $mon, int $day, string $time): ?string
    {
        $mm = $this->monthNum($mon);
        if (!$mm) {
            return null;
        }
        return sprintf('%04d-%02d-%02d %s:00', $year, $mm, $day, $time);
    }

    private function buildAdditionalInfo(string $pnr, ?string $issueDate, array $passengers, array $total): string
    {
        $bits = [];
        $bits[] = 'Carrier: Vietnam Airlines';
        $bits[] = 'PNR: ' . $pnr;
        if ($issueDate) {
            $bits[] = 'Issue date: ' . substr($issueDate, 0, 10);
        }
        if (count($passengers) > 1) {
            $bits[] = 'Passengers in booking: ' . count($passengers);
        }
        if ($total['total'] !== null) {
            $bits[] = 'Total: ' . $total['total'] . ' ' . $total['currency'];
        }
        return implode(' | ', $bits);
    }
}
