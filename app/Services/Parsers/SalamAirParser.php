<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for SalamAir (OV) "Your Booking Receipt" PDFs, e.g.
 * BookingReceipt_ZF8NW7.pdf mailed from no-reply@salamair.com.
 *
 * smalot renders the receipt vertically; the reliable anchors are:
 *   "BOOKING REFERENCE" followed by the 6-char PNR
 *   "TOTAL AMOUNT PAID 95.56 KWD"
 *   "Booked on 19 Jul , 2026 - SalamAir"
 *   per-segment summary "ZF8NW7  KWI TO MCT  MON JULY 20 2026, depart 5: 00pm, arrive 8: 00pm"
 *   per-segment flight line "OV228 | 0 stop | 2 hrs 0 mins"
 *   fare-breakup passenger rows "Mr Nawwaf Alhajeri" followed by "Adult"/"Child"/"Infant"
 *
 * Returns the same shape as AirFileParser::parseTaskSchema() — array of task
 * arrays, one per passenger (total split per pax, Jazeera convention).
 */
class SalamAirParser
{
    // Must equal the existing suppliers.name on prod (id 59, "Salam Air") —
    // the storage folder slug salam_air derives from it.
    public const SUPPLIER_NAME    = 'Salam Air';
    public const SUPPLIER_COUNTRY = 'Oman';
    public const AIRLINE_CODE     = 'OV';

    private string $text;
    /** @var string[] */
    private array $lines;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        $parser = new PdfParser();
        $this->text  = self::normalizeSpaces($parser->parseFile($filePath)->getText());
        $this->lines = explode("\n", $this->text);
    }

    public static function matches(string $rawText): bool
    {
        $t = self::normalizeSpaces($rawText);

        return stripos($t, 'SalamAir') !== false
            && stripos($t, 'BOOKING REFERENCE') !== false;
    }

    /**
     * The receipt renders nearly every space as a non-breaking space (U+00A0),
     * which defeats both literal ' ' matches and ASCII \s classes — normalise
     * all exotic spaces to plain ones before any matching.
     */
    private static function normalizeSpaces(string $s): string
    {
        return preg_replace('/[\x{00A0}\x{202F}\x{2007}]/u', ' ', $s);
    }

    public function parseTaskSchema(): array
    {
        $pnr = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception('Could not locate SalamAir PNR in PDF');
        }
        $bookingDate = $this->extractBookingDate();
        $status      = $this->extractStatusInternal();
        $totalCcy    = $this->extractTotalAndCurrency();
        $passengers  = $this->extractPassengers();
        $flights     = $this->extractFlightDetails($pnr);

        if (empty($passengers)) {
            $passengers = ['UNKNOWN PASSENGER'];
        }

        // Per-pax price = grand total / N (receipt totals are lump-sum).
        $perPax = ($totalCcy['total'] !== null && count($passengers) > 0)
            ? round($totalCcy['total'] / count($passengers), 3)
            : null;

        $isKwd = ($totalCcy['currency'] ?? 'KWD') === 'KWD';

        $tasks = [];
        foreach ($passengers as $clientName) {
            $tasks[] = [
                'additional_info'      => $this->buildAdditionalInfo($bookingDate, $passengers, $totalCcy),
                'ticket_number'        => null,
                'gds_reference'        => $pnr,
                'airline_reference'    => $pnr,
                'status'               => $status,
                'supplier_status'      => $status,
                'refund_date'          => null,
                'void_date'            => null,
                // Foreign-currency totals must leave price/total null so the
                // TaskController conversion path fills them (Jazeera B8UT7D lesson).
                'price'                => $isKwd ? $perPax : null,
                'currency'             => $totalCcy['currency'] ?? 'KWD',
                'exchange_currency'    => $totalCcy['currency'] ?? 'KWD',
                'original_price'       => $isKwd ? null : $perPax,
                'original_currency'    => $isKwd ? null : $totalCcy['currency'],
                'total'                => $isKwd ? $perPax : null,
                'original_total'       => $isKwd ? null : $perPax,
                'surcharge'            => 0,
                'penalty_fee'          => null,
                'tax'                  => 0,
                'taxes_record'         => null,
                'refund_charge'        => null,
                'reference'            => $pnr,
                'original_ticket_number' => null,
                'original_reference'   => null,
                'created_by'           => null,
                'issued_by'            => null,
                'iata_number'          => null,
                'type'                 => 'flight',
                'agent_name'           => null,
                'agent_email'          => null,
                'agent_amadeus_id'     => null,
                'client_name'          => $clientName,
                'supplier_name'        => self::SUPPLIER_NAME,
                'supplier_country'     => self::SUPPLIER_COUNTRY,
                'cancellation_policy'  => 'Non-refundable per SalamAir terms.',
                'venue'                => $this->extractVenue($flights),
                'issued_date'          => $bookingDate,
                'is_exchanged'         => false,
                'task_flight_details'  => $flights,
            ];
        }

        return $tasks;
    }

    // ──────────────────────────── extractors ────────────────────────────

    private function extractPnr(): ?string
    {
        // "BOOKING REFERENCE" anchor, PNR on the next non-empty line.
        foreach ($this->lines as $i => $line) {
            if (strcasecmp(trim($line), 'BOOKING REFERENCE') === 0) {
                for ($j = $i + 1; $j < min($i + 4, count($this->lines)); $j++) {
                    $t = trim($this->lines[$j]);
                    if (preg_match('/^([A-Z0-9]{6})$/', $t, $m)) {
                        return $m[1];
                    }
                }
            }
        }
        // Fallback: the per-segment summary line "ZF8NW7  KWI TO MCT ..."
        if (preg_match('/^([A-Z0-9]{6})\s+[A-Z]{3}\s+TO\s+[A-Z]{3}\b/m', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractBookingDate(): ?string
    {
        // "Booked on 19 Jul , 2026 - SalamAir"
        if (preg_match('/Booked on\s+(\d{1,2})\s+([A-Za-z]{3,9})\s*,?\s*(\d{4})/i', $this->text, $m)) {
            return $this->toIsoDate((int) $m[1], $m[2], (int) $m[3]);
        }
        return null;
    }

    private function extractStatusInternal(): string
    {
        if (preg_match('/Booking status:\s*([A-Z]+)/i', $this->text, $m)) {
            return match (strtoupper($m[1])) {
                'CONFIRMED', 'ISSUED' => 'issued',
                'HOLD', 'PENDING'     => 'hold',
                'CANCELLED'           => 'cancelled',
                'REFUNDED'            => 'refunded',
                default               => 'issued',
            };
        }
        return 'issued';
    }

    /** @return array{total: ?float, currency: string} */
    private function extractTotalAndCurrency(): array
    {
        if (preg_match('/TOTAL AMOUNT PAID\s+([\d.,]+)\s+([A-Z]{3})/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[1]),
                'currency' => strtoupper($m[2]),
            ];
        }
        return ['total' => null, 'currency' => 'KWD'];
    }

    /**
     * Passenger rows in the fare-breakup / extras tables: a titled name line
     * ("Mr Nawwaf Alhajeri") whose NEXT non-empty line is the pax type
     * (Adult/Child/Infant). The same pax repeats in OPTIONAL EXTRAS — dedup.
     */
    private function extractPassengers(): array
    {
        $passengers = [];
        $n = count($this->lines);
        foreach ($this->lines as $i => $line) {
            $t = trim($line);
            if (!preg_match('/^(Mr|Mrs|Ms|Miss|Dr|Mstr|Chd|Inf)\.?\s+([A-Za-z][A-Za-z\'\- ]+)$/i', $t, $m)) {
                continue;
            }
            $isPax = false;
            for ($j = $i + 1; $j < min($i + 3, $n); $j++) {
                $next = trim($this->lines[$j]);
                if ($next === '') {
                    continue;
                }
                $isPax = (bool) preg_match('/^(Adult|Child|Infant)$/i', $next);
                break;
            }
            if (!$isPax) {
                continue;
            }
            $full = strtoupper($m[1]) . ' ' . strtoupper(trim($m[2]));
            if (!in_array($full, $passengers, true)) {
                $passengers[] = $full;
            }
        }

        // Fallback: untitled passenger name in the itinerary block (line before
        // the baggage line "5kg Hand baggage...").
        if (empty($passengers)) {
            foreach ($this->lines as $i => $line) {
                if (stripos($line, 'Hand baggage') !== false && $i > 0) {
                    $prev = trim($this->lines[$i - 1]);
                    if (preg_match('/^[A-Za-z][A-Za-z\'\- ]{2,}$/', $prev)) {
                        $passengers[] = strtoupper($prev);
                    }
                }
            }
        }

        return $passengers;
    }

    /**
     * One segment per summary line:
     *   "ZF8NW7  KWI TO MCT  MON JULY 20 2026, depart 5: 00pm, arrive 8: 00pm"
     * Flight numbers come from the "OV228 | 0 stop | ..." lines in document
     * order; city names from the "KUWAIT TO MUSCAT" header lines in order.
     */
    private function extractFlightDetails(string $pnr): array
    {
        $flightNos = [];
        if (preg_match_all('/\b(OV\s?\d{2,4})\s*\|/i', $this->text, $mm)) {
            foreach ($mm[1] as $fn) {
                $flightNos[] = strtoupper(preg_replace('/\s+/', '', $fn));
            }
        }

        $cityPairs = [];
        if (preg_match_all('/^\s*([A-Z][A-Z ]+?)\s+TO\s+([A-Z][A-Z ]+?)\s*$/m', $this->text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                // Skip the airport-code summary rows (3-letter codes handled below)
                if (strlen(trim($m[1])) === 3 && strlen(trim($m[2])) === 3) {
                    continue;
                }
                $cityPairs[] = [ucwords(strtolower(trim($m[1]))), ucwords(strtolower(trim($m[2])))];
            }
        }

        $segments = [];
        $re = '/' . preg_quote($pnr, '/') . '\s+([A-Z]{3})\s+TO\s+([A-Z]{3})\s+'
            . '[A-Z]{3}\s+([A-Z]+)\s+(\d{1,2})\s+(\d{4})\s*,\s*'
            . 'depart\s+([\d]{1,2}:\s?\d{2}\s*(?:am|pm))\s*,\s*'
            . 'arrive\s+([\d]{1,2}:\s?\d{2}\s*(?:am|pm))/i';
        if (preg_match_all($re, $this->text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $k => $m) {
                $dateIso = $this->toIsoDate((int) $m[4], $m[3], (int) $m[5]);
                $date    = $dateIso ? substr($dateIso, 0, 10) : null;
                $segments[] = [
                    'flight_number'  => isset($flightNos[$k])
                        ? substr($flightNos[$k], 0, 2) . ' ' . substr($flightNos[$k], 2)
                        : null,
                    'airport_from'   => strtoupper($m[1]),
                    'airport_to'     => strtoupper($m[2]),
                    'departure_from' => $cityPairs[$k][0] ?? null,
                    'arrive_to'      => $cityPairs[$k][1] ?? null,
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => $this->buildDateTime($date, $m[6]),
                    'arrival_time'   => $this->buildDateTime($date, $m[7]),
                    'duration_time'  => null,
                    'airline_name'   => self::SUPPLIER_NAME,
                    'class_type'     => 'economy',
                    'baggage_allowed'=> null,
                    'equipment'      => null,
                    'flight_meal'    => null,
                    'seat_no'        => null,
                    'farebase'       => null,
                    'ticket_number'  => null,
                ];
            }
        }

        return $segments;
    }

    /** "5: 00pm" -> "17:00" appended to the ISO date. */
    private function buildDateTime(?string $date, ?string $rawTime): ?string
    {
        if (!$date || !$rawTime) {
            return null;
        }
        $t = strtolower(preg_replace('/\s+/', '', $rawTime)); // "5:00pm"
        if (!preg_match('/^(\d{1,2}):(\d{2})(am|pm)$/', $t, $m)) {
            return null;
        }
        $h = (int) $m[1] % 12;
        if ($m[3] === 'pm') {
            $h += 12;
        }
        return sprintf('%s %02d:%s:00', $date, $h, $m[2]);
    }

    /** Month may be abbreviated ("Jul") or full uppercase ("JULY"). */
    private function toIsoDate(int $day, string $month, int $year): ?string
    {
        $months = ['jan'=>'01','feb'=>'02','mar'=>'03','apr'=>'04','may'=>'05','jun'=>'06',
                   'jul'=>'07','aug'=>'08','sep'=>'09','oct'=>'10','nov'=>'11','dec'=>'12'];
        $mm = $months[strtolower(substr($month, 0, 3))] ?? null;
        if ($mm === null) {
            return null;
        }
        return sprintf('%04d-%s-%02d 00:00:00', $year, $mm, $day);
    }

    private function extractVenue(array $flights): ?string
    {
        return $flights[0]['arrive_to'] ?? null;
    }

    private function buildAdditionalInfo(?string $bookingDate, array $passengers, array $totalCcy): string
    {
        $bits = [];
        if ($bookingDate) {
            $bits[] = 'Booking date: ' . substr($bookingDate, 0, 10);
        }
        if (count($passengers) > 1) {
            $bits[] = 'Passengers in booking: ' . count($passengers);
        }
        if (($totalCcy['currency'] ?? 'KWD') !== 'KWD' && $totalCcy['total'] !== null) {
            $bits[] = 'Original: ' . $totalCcy['total'] . ' ' . $totalCcy['currency'];
        }
        return implode(' | ', $bits);
    }
}
