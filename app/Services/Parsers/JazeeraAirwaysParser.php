<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Jazeera Airways itinerary PDFs.
 *
 * Two file shapes seen in production:
 *   - Customer itinerary (sometimes lacks "Total Booking Amount" line)
 *   - Travel agent invoice "_TA.pdf" suffix (has price + currency)
 *
 * smalot/pdfparser emits a vertical layout — each table cell on its own line —
 * so we drive the parse off a section-aware state machine, not horizontal regex.
 *
 * Returns the same shape as AirFileParser::parseTaskSchema() — array of task
 * arrays, one per passenger.
 */
class JazeeraAirwaysParser
{
    public const SUPPLIER_NAME    = 'Jazeera Airways';
    public const SUPPLIER_COUNTRY = 'Kuwait';
    public const AIRLINE_CODE     = 'J9';
    public const SIGNATURE        = 'Your Jazeera Airways Itinerary';

    private string $filePath;
    private string $text;
    /** @var string[] */
    private array $lines;
    /** @var array<string, ?int> */
    private array $sections;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        $this->filePath = $filePath;
        $parser  = new PdfParser();
        $this->text = $parser->parseFile($filePath)->getText();
        $this->lines = explode("\n", $this->text);
        $this->sections = $this->indexSections();
    }

    public static function matches(string $rawText): bool
    {
        return stripos($rawText, self::SIGNATURE) !== false;
    }

    public function parseTaskSchema(): array
    {
        $pnr         = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception("Could not locate Jazeera PNR in PDF");
        }
        $bookingDate = $this->extractBookingDate();
        $status      = $this->extractStatusInternal();
        $totalCcy    = $this->extractTotalAndCurrency();
        $passengers  = $this->extractPassengers();
        $flights     = $this->extractFlightDetails();

        if (empty($passengers)) {
            $passengers = ['UNKNOWN PASSENGER'];
        }

        // Per-pax price = total / N (Jazeera shows lump-sum total only)
        $perPax = ($totalCcy['total'] !== null && count($passengers) > 0)
            ? round($totalCcy['total'] / count($passengers), 3)
            : null;

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
                // For KWD bookings we have the final amount; for foreign-currency
                // bookings (EGP, USD, AED, etc.) we MUST leave `price` and `total`
                // null so TaskController::store triggers the conversion path.
                // Otherwise the controller's `$emptyOrZero($price)` guard skips
                // conversion and the raw foreign value lands in KWD columns
                // (the 17908.6 KWD bug on PNR B8UT7D was exactly this).
                'price'                => $totalCcy['currency'] === 'KWD' ? $perPax : null,
                'currency'             => $totalCcy['currency'] ?? 'KWD',
                'exchange_currency'    => $totalCcy['currency'] ?? 'KWD',
                'original_price'       => $totalCcy['currency'] === 'KWD' ? null : $perPax,
                'original_currency'    => $totalCcy['currency'] === 'KWD' ? null : $totalCcy['currency'],
                'total'                => $totalCcy['currency'] === 'KWD' ? $perPax : null,
                // original_total must mirror original_price for the foreign-currency
                // path — TaskController's needTotalConversion check only fires when
                // original_total is filled. Without this, total stays null and the
                // downstream financial transaction insert fails on amount=null.
                'original_total'       => $totalCcy['currency'] === 'KWD' ? null : $perPax,
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
                'cancellation_policy'  => 'Non-refundable per Jazeera Airways terms.',
                'venue'                => $this->extractVenue($flights),
                'issued_date'          => $bookingDate,
                'is_exchanged'         => false,
                'task_flight_details'  => $flights,
            ];
        }

        return $tasks;
    }

    // ──────────────────────────── section index ────────────────────────────

    private function indexSections(): array
    {
        $marks = [
            'header_start'   => null,  // "Booking Reference" header
            'itinerary_start'=> null,  // "Your Itinerary" line
            'ssr_start'      => null,  // "Special Service Information" line
            'baggage_start'  => null,
            'receipts_start' => null,
        ];
        foreach ($this->lines as $i => $line) {
            $t = trim($line);
            if ($marks['header_start']    === null && $t === 'Booking Reference')          $marks['header_start']    = $i;
            if ($marks['itinerary_start'] === null && $t === 'Your Itinerary')             $marks['itinerary_start'] = $i;
            if ($marks['ssr_start']       === null && $t === 'Special Service Information') $marks['ssr_start']      = $i;
            if ($marks['baggage_start']   === null && $t === 'Baggage Allowance')          $marks['baggage_start']   = $i;
            if ($marks['receipts_start']  === null && $t === 'Receipts and Payments')      $marks['receipts_start']  = $i;
        }
        return $marks;
    }

    // ──────────────────────────── extractors ────────────────────────────

    private function extractPnr(): ?string
    {
        // Look for a line that starts with 6-char alphanumeric + tab/space + day + month + year.
        // Note: case-SENSITIVE — "Number" must NOT match.
        $end = $this->sections['itinerary_start'] ?? count($this->lines);
        for ($i = 0; $i < $end; $i++) {
            $ln = $this->lines[$i];
            if (preg_match(
                '/^([A-Z0-9]{6})[\s\t]+\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}/',
                $ln,
                $m
            )) {
                return $m[1];
            }
        }
        return null;
    }

    private function extractBookingDate(): ?string
    {
        $end = $this->sections['itinerary_start'] ?? count($this->lines);
        for ($i = 0; $i < $end; $i++) {
            if (preg_match(
                '/^[A-Z0-9]{6}[\s\t]+(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4})/',
                $this->lines[$i],
                $m
            )) {
                return $this->toIsoDate((int)$m[1], $m[2], (int)$m[3]);
            }
        }
        return null;
    }

    private function extractStatusInternal(): string
    {
        // Status keyword appears in the header block (before "Your Itinerary")
        $end = $this->sections['itinerary_start'] ?? min(count($this->lines), 25);
        for ($i = 0; $i < $end; $i++) {
            if (preg_match('/\b(CONFIRMED|CANCELLED|HOLD|PENDING|REFUNDED|VOID|ISSUED)\b/', $this->lines[$i], $m)) {
                $j = strtoupper($m[1]);
                return match ($j) {
                    'CONFIRMED', 'ISSUED' => 'issued',
                    'HOLD', 'PENDING'     => 'hold',
                    'CANCELLED'           => 'cancelled',
                    'REFUNDED'            => 'refunded',
                    'VOID'                => 'void',
                    default               => 'issued',
                };
            }
        }
        return 'issued';
    }

    /**
     * @return array{total: ?float, currency: string}
     */
    private function extractTotalAndCurrency(): array
    {
        if (preg_match('/Total Booking Amount[\s\t]+([\d.,]+)\s+([A-Z]{3})/', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[1]),
                'currency' => strtoupper($m[2]),
            ];
        }
        return ['total' => null, 'currency' => 'KWD'];
    }

    /**
     * Primary source: SSR table (each pax appears with their J9 flight, full name on one line).
     * Fallback: header block lines between PNR row and "Your Itinerary".
     */
    private function extractPassengers(): array
    {
        $passengers = [];

        // Primary: SSR table
        if ($this->sections['ssr_start'] !== null) {
            $end = $this->sections['baggage_start'] ?? count($this->lines);
            for ($i = $this->sections['ssr_start']; $i < $end; $i++) {
                $ln = $this->lines[$i];
                // Name is followed by the flight (J9 <num>). smalot renders the
                // separator as a TAB for most pax but occasionally a plain SPACE
                // (seen on MSTR/child rows, e.g. PNR O3T5KI) — accept either, or
                // a passenger silently drops out of the booking.
                if (preg_match(
                    '/^(MR|MS|MRS|MISS|DR|MSTR|CHD|INF)\s+(.+?)[ \t]+J9\s*\d/i',
                    $ln,
                    $m
                )) {
                    $title = strtoupper($m[1]);
                    $name  = strtoupper(trim($m[2]));
                    $full  = "{$title} {$name}";
                    if (!in_array($full, $passengers, true)) {
                        $passengers[] = $full;
                    }
                }
            }
        }

        // Fallback: walk header block (between PNR row and "Your Itinerary")
        if (empty($passengers)) {
            $pnrIdx = null;
            $itiIdx = $this->sections['itinerary_start'] ?? count($this->lines);
            for ($i = 0; $i < $itiIdx; $i++) {
                if (preg_match('/^[A-Z0-9]{6}[\s\t]+\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/', $this->lines[$i])) {
                    $pnrIdx = $i;
                    break;
                }
            }
            if ($pnrIdx !== null) {
                // Case 1 (single-pax inline header): name is INSIDE the PNR line itself
                if (preg_match(
                    '/^[A-Z0-9]{6}[\s\t]+\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}\s+(MR|MS|MRS|MISS|DR|MSTR|CHD|INF)\s+([A-Z][A-Z\s\'\-]+?)(?:\s*\t|$)/',
                    $this->lines[$pnrIdx],
                    $m
                )) {
                    $passengers[] = strtoupper($m[1]) . ' ' . strtoupper(trim($m[2]));
                }

                // Case 2 (multi-line names): lines after PNR row up to status
                $current = null;
                for ($i = $pnrIdx + 1; $i < $itiIdx; $i++) {
                    $ln = trim($this->lines[$i]);
                    if ($ln === '' || preg_match('/^(CONFIRMED|CANCELLED|HOLD|PENDING|REFUNDED|VOID|ISSUED)$/', $ln)) {
                        if ($current !== null) {
                            if (!in_array($current, $passengers, true)) $passengers[] = $current;
                            $current = null;
                        }
                        if (preg_match('/^(CONFIRMED|CANCELLED|HOLD|PENDING|REFUNDED|VOID|ISSUED)$/', $ln)) break;
                        continue;
                    }
                    if (preg_match('/^(MR|MS|MRS|MISS|DR|MSTR|CHD|INF)\s+(.+)$/i', $ln, $m)) {
                        // Start of a new passenger
                        if ($current !== null && !in_array($current, $passengers, true)) {
                            $passengers[] = $current;
                        }
                        $current = strtoupper($m[1]) . ' ' . strtoupper(trim($m[2]));
                    } elseif ($current !== null && preg_match('/^[A-Z][A-Z\s\'\-]+$/', $ln)) {
                        // Continuation of previous name (e.g., "DURAYALAGE")
                        $current .= ' ' . strtoupper($ln);
                    }
                }
                if ($current !== null && !in_array($current, $passengers, true)) {
                    $passengers[] = $current;
                }
            }
        }

        // Final regex fallback — runs ONLY when nothing else matched, so it
        // cannot regress already-working PDFs. Scans the whole text for
        // "<TITLE> <NAME>" up to a tab / 2+ spaces / status / J9 / digit
        // boundary; handles multi-initial names (e.g. "MR NASSER J N M A
        // ALRUWAYEH") and dedupes repeats (SSR + baggage sections).
        if (empty($passengers)) {
            if (preg_match_all(
                '/\b(MR|MRS|MS|MISS|DR|MSTR|CHD|INF)\.?\s+([A-Z][A-Z\'\-]*(?:\s+[A-Z\'\-]+)*?)\s*(?=\t|\s{2,}|CONFIRMED|CANCELLED|HOLD|PENDING|REFUNDED|VOID|ISSUED|J9\b|\d|$)/m',
                $this->text,
                $mm,
                PREG_SET_ORDER
            )) {
                foreach ($mm as $m) {
                    $name = strtoupper(trim($m[2]));
                    if ($name === '') {
                        continue;
                    }
                    $full = strtoupper($m[1]) . ' ' . $name;
                    if (!in_array($full, $passengers, true)) {
                        $passengers[] = $full;
                    }
                }
            }
        }

        return $passengers;
    }

    /**
     * Extract flight segments by line-walking the Itinerary block.
     * Each segment is delimited by a `J9 ###` line.
     */
    private function extractFlightDetails(): array
    {
        if ($this->sections['itinerary_start'] === null) return [];

        // Skip the column-header line that follows "Your Itinerary"
        $start = $this->sections['itinerary_start'] + 1;
        // Block ends at the next named section
        $endCandidates = array_filter([
            $this->sections['ssr_start'],
            $this->sections['baggage_start'],
            $this->sections['receipts_start'],
        ], fn($x) => $x !== null);
        $end = $endCandidates ? min($endCandidates) : count($this->lines);

        // Skip the column-header line (e.g., "Flight From To Seat Departure Arrival Class")
        while ($start < $end && !preg_match('/^J9\s*\d/', trim($this->lines[$start]))) {
            $start++;
        }

        // Group consecutive lines into per-flight segments
        $segments = [];
        $current = null;
        for ($i = $start; $i < $end; $i++) {
            $ln = trim($this->lines[$i]);
            if (preg_match('/^J9\s*(\d{2,4})$/', $ln, $m)) {
                if ($current !== null) $segments[] = $current;
                $current = ['fn' => $m[1], 'lines' => []];
            } elseif ($current !== null) {
                if ($ln === '') continue;
                // Stop if we hit boilerplate ("For KWI..." etc.)
                if (preg_match('/^(For\s+KWI|Arrive\s+at|Note:|Important:|\*|For\s+handling)/i', $ln)) {
                    $segments[] = $current;
                    $current = null;
                    break;
                }
                $current['lines'][] = $ln;
            }
        }
        if ($current !== null) $segments[] = $current;

        // Parse each segment's accumulated lines into structured fields
        $out = [];
        foreach ($segments as $seg) {
            $out[] = $this->parseFlightSegment($seg);
        }
        return $out;
    }

    /**
     * Given a flight segment's lines, extract: airports, times, dates, seat, class.
     * Layout (12 lines max, terminal optional):
     *   from_a (short city)
     *   from_b (long city)
     *   [Terminal X]      ← optional
     *   to_a (short city)
     *   to_b (long city)
     *   [Terminal X]      ← rare
     *   seat (e.g. "13A 13B" or "16A")
     *   dep_time ("1735hr" or "19:25hr")
     *   dep_date ("14 Jun 2026")
     *   arr_time
     *   arr_date
     *   class ("Comfort"/"Business"/"Economy")
     */
    private function parseFlightSegment(array $seg): array
    {
        $lns = $seg['lines'];
        $fn  = $seg['fn'];

        // Classify each line by type so we can fill fields regardless of exact order
        $cities = [];
        $terminal = null;
        $seats = null;
        $times = [];  // ["19:25hr", "03:30hr"] in order seen
        $dates = [];
        $class = null;

        foreach ($lns as $ln) {
            if (preg_match('/^Terminal\s+(\S+)/i', $ln, $m)) {
                $terminal = $m[1];
            } elseif (preg_match('/^(\d{2}:?\d{2})hr$/i', $ln, $m)) {
                $times[] = $m[1];
            } elseif (preg_match('/^\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}$/', $ln)) {
                $dates[] = $ln;
            } elseif (preg_match('/^(Comfort|Business|Economy)$/i', $ln, $m)) {
                $class = strtolower($m[1]);
            } elseif (preg_match('/^(\d{1,3}[A-Z](?:\s+\d{1,3}[A-Z])*)$/', $ln, $m)) {
                // Seats like "22D", "13A 13B", "16A"
                $seats = $m[1];
            } elseif (preg_match('/^[A-Z][A-Za-z][A-Za-z\s\']*$/', $ln)) {
                // Plain city name (allow multi-word and concatenated like "ColomboBandaranaike")
                $cities[] = $ln;
            }
        }

        // Cities array: first two = from (short, long), next two = to (short, long).
        // If only 3 cities seen (one short repeated), still take 0=from_a, last=to_a.
        $from_a = $cities[0] ?? null;
        // Skip duplicate of from
        $to_a = null;
        foreach ($cities as $c) {
            if ($from_a !== null && strcasecmp($c, $from_a) !== 0) {
                $to_a = $c;
                break;
            }
        }

        // IATA from SSR/Baggage line: "J9 ### KWITZX"
        $iata = $this->lookupIataForFlight($fn);

        // Times+dates: assume order is [dep_time, dep_date, arr_time, arr_date]
        // but they could also be [dep_time, arr_time, dep_date, arr_date]
        $depTime = $times[0] ?? null;
        $arrTime = $times[1] ?? null;
        $depDate = $dates[0] ?? null;
        $arrDate = $dates[1] ?? null;

        return [
            'flight_number'  => 'J9 ' . $fn,
            'airport_from'   => $iata['from'],
            'airport_to'     => $iata['to'],
            'departure_from' => $from_a,
            'arrive_to'      => $to_a,
            'terminal_from'  => $terminal,
            'terminal_to'    => null,
            'departure_time' => $this->buildDateTime($depDate, $depTime),
            'arrival_time'   => $this->buildDateTime($arrDate, $arrTime),
            'duration_time'  => null,
            'airline_name'   => self::SUPPLIER_NAME,
            'class_type'     => $class ?: 'economy',
            'baggage_allowed'=> null,
            'equipment'      => null,
            'flight_meal'    => null,
            'seat_no'        => $seats,
            'farebase'       => null,
            'ticket_number'  => null,
        ];
    }

    private function lookupIataForFlight(string $flightNo): array
    {
        if (preg_match('/J9\s*' . preg_quote($flightNo, '/') . '\s+([A-Z]{3})([A-Z]{3})/', $this->text, $m)) {
            return ['from' => $m[1], 'to' => $m[2]];
        }
        return ['from' => null, 'to' => null];
    }

    private function buildDateTime(?string $dateStr, ?string $timeStr): ?string
    {
        if (!$dateStr || !$timeStr) return null;
        if (!preg_match('/^(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4})$/', $dateStr, $dm)) {
            return null;
        }
        $iso = $this->toIsoDate((int)$dm[1], $dm[2], (int)$dm[3]);
        $iso = substr($iso, 0, 10);   // YYYY-MM-DD
        $time = $this->normaliseTime($timeStr);
        return $time ? "$iso $time:00" : null;
    }

    private function normaliseTime(?string $raw): ?string
    {
        if (!$raw) return null;
        if (strpos($raw, ':') !== false) return $raw;
        if (strlen($raw) === 4 && ctype_digit($raw)) {
            return substr($raw, 0, 2) . ':' . substr($raw, 2, 2);
        }
        return $raw;
    }

    private function toIsoDate(int $day, string $month, int $year): string
    {
        $months = ['Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
                   'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'];
        $mm = $months[ucfirst(strtolower($month))] ?? '01';
        return sprintf('%04d-%s-%02d 00:00:00', $year, $mm, $day);
    }

    private function extractVenue(array $flights): ?string
    {
        return $flights[0]['arrive_to'] ?? null;
    }

    private function buildAdditionalInfo(?string $bookingDate, array $passengers, array $totalCcy): string
    {
        $bits = [];
        if ($bookingDate) $bits[] = "Booking date: " . substr($bookingDate, 0, 10);
        if (count($passengers) > 1) $bits[] = "Passengers in booking: " . count($passengers);
        if ($totalCcy['currency'] !== 'KWD' && $totalCcy['total'] !== null) {
            $bits[] = "Original: " . $totalCcy['total'] . ' ' . $totalCcy['currency'];
        }
        return implode(' | ', $bits);
    }
}
