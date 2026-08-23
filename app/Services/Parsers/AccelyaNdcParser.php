<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for NDC airline itineraries delivered through Accelya
 * (no-reply@accelya.com). Supports any airline distributing through this
 * platform — currently observed: Emirates, Oman Air. Easy to extend.
 *
 * Detection: header "Itinerary for Record Locator XXXXXX" + Accelya sender or
 * "<Airline> Record Locator" line.
 *
 * The parser dispatches `supplier_name` based on the carrier mentioned in the
 * body — so one parser fills two different supplier rows in the tasks table.
 *
 * Returns the same shape as AirFileParser::parseTaskSchema() — array of task
 * arrays, one per passenger.
 */
class AccelyaNdcParser
{
    public const SIGNATURE        = 'Itinerary for Record Locator';
    public const SUPPLIER_COUNTRY = 'Kuwait';

    /**
     * Carrier mapping: airline name in PDF body -> supplier metadata.
     * Add new NDC carriers here as they're observed.
     */
    private const CARRIER_MAP = [
        'Emirates' => ['supplier' => 'Emirates NDC',      'iata' => 'EK', 'country' => 'United Arab Emirates'],
        'Oman Air' => ['supplier' => 'Oman Airways NDC',  'iata' => 'WY', 'country' => 'Oman'],
    ];

    private ?string $filePath = null;
    private string $text = '';
    /** @var string[] */
    private array $lines = [];
    private string $carrierName = '';
    private string $supplierName = '';
    private string $iataCode = '';
    private string $supplierCountry = '';
    /** Raw HTML body when constructed via fromHtml() — enables DOM-based
     *  extraction of structured fields (flights, prices) that don't survive
     *  the lossy flattening into plain text. */
    private ?string $rawHtml = null;

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            $this->filePath = $filePath;
            $parser  = new PdfParser();
            $this->setText($parser->parseFile($filePath)->getText());
        }
    }

    /**
     * Build a parser instance directly from the raw HTML email body (Option A:
     * skip the Gotenberg PDF round-trip entirely). The HTML is flattened to
     * the same plain-text shape the PDF path produces, so every existing
     * regex extractor keeps working without modification.
     */
    public static function fromHtml(string $html): self
    {
        $instance = new self();
        $instance->rawHtml = $html;
        $instance->setText(self::htmlToText($html));
        return $instance;
    }

    private function setText(string $text): void
    {
        $this->text = $text;
        $this->lines = explode("\n", $text);
        $this->detectCarrier();
    }

    /**
     * Flatten the email HTML to the plain-text layout the regex extractors
     * expect. Strategy: drop script/style, convert block-level closes to
     * newlines, strip remaining tags, decode entities, collapse whitespace
     * but preserve line breaks. Mirrors what pdftotext -layout would yield
     * for a print-rendered version of the same email.
     */
    private static function htmlToText(string $html): string
    {
        // Kill script/style entirely (their contents must not bleed into text)
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        // Block-level transitions become newlines so labels stay on their own line
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/\s*(p|div|tr|li|h[1-6]|td|th)\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*(p|div|tr|li|h[1-6])\b[^>]*>/i', "\n", $html);
        // Tab between table cells so "Label: value" rows survive
        $html = preg_replace('/<\s*(td|th)\b[^>]*>/i', "\t", $html);
        // Strip all remaining tags
        $text = strip_tags($html);
        // Decode HTML entities (&amp; &nbsp; etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalise whitespace: collapse runs of spaces/tabs but keep newlines
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    public static function matches(string $rawText): bool
    {
        if (stripos($rawText, self::SIGNATURE) === false) return false;
        // Must mention a known carrier OR sit clearly inside an Accelya-routed flow
        if (stripos($rawText, 'accelya.com') !== false) return true;
        foreach (array_keys(self::CARRIER_MAP) as $airline) {
            if (stripos($rawText, $airline) !== false) return true;
        }
        return false;
    }

    private function detectCarrier(): void
    {
        foreach (self::CARRIER_MAP as $airline => $meta) {
            if (stripos($this->text, $airline) !== false) {
                $this->carrierName     = $airline;
                $this->supplierName    = $meta['supplier'];
                $this->iataCode        = $meta['iata'];
                $this->supplierCountry = $meta['country'];
                return;
            }
        }
        // Fallback — generic NDC
        $this->carrierName     = 'NDC';
        $this->supplierName    = 'Accelya NDC';
        $this->iataCode        = '';
        $this->supplierCountry = 'Unknown';
    }

    public function parseTaskSchema(): array
    {
        $pnr        = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception("Could not locate Accelya NDC record locator in PDF");
        }
        // Ticketing state for ALL Accelya carriers (Emirates NDC, Oman NDC, …):
        //   has "Document Number" → ticket has been issued → status = 'issued'
        //   no "Document Number"  → booking is confirmed but not yet ticketed
        //                            → status = 'confirmed' (still load the task
        //                              so the agent has visibility)
        // When the later "issued" email arrives carrying the Document Number,
        // the controller's merge logic upgrades the confirmed task in place.
        $isTicketed = stripos($this->text, 'Document Number') !== false;
        $taskStatus = $isTicketed ? 'issued' : 'confirmed';
        $altPnr     = $this->extractAltPnr();         // Emirates/Oman Record Locator
        $issueDate  = $this->extractIssueDate();
        $passengers = $this->extractPassengers();     // [['name' => ..., 'pax_type' => 'ADT'], ...]
        $flights    = $this->extractFlights();
        $priceByPax = $this->extractPriceByPax();     // ['NAME' => ['base'=>...,'taxes'=>...,'total'=>...,'ccy'=>...]]
        $grandTotal = $this->extractGrandTotal();
        $ticketNumber = $this->extractTicketNumber(); // single (fallback / PDF path)
        $ticketByPax  = $this->extractTicketByPaxFromHtml(); // per-pax Document Numbers (HTML)

        if (empty($passengers)) {
            $passengers = [['name' => 'UNKNOWN PASSENGER', 'pax_type' => 'ADT']];
        }

        $tasks = [];
        foreach ($passengers as $pax) {
            $paxKey = $pax['name'];
            // NDC issues a sequential Document Number per passenger — use the
            // per-pax e-ticket; fall back to the single one (PDF / single-pax).
            // IATA doc number = 3-digit airline code + 10-digit serial; we store
            // the 10-digit serial as the ticket/reference (citycomm convention).
            $paxTicket = $this->ndcTicketSerial($ticketByPax[$this->normName($paxKey)] ?? $ticketNumber);
            $pricing = $priceByPax[$paxKey] ?? null;
            $price = $pricing['total'] ?? ($grandTotal['total'] ?? null);
            $ccy = $pricing['ccy'] ?? ($grandTotal['ccy'] ?? 'KWD');

            // If no per-pax row but grand total exists, split equally
            if (!$pricing && $grandTotal['total'] !== null && count($passengers) > 0) {
                $price = round($grandTotal['total'] / count($passengers), 3);
            }

            // Field semantics for NDC tickets, matching Amadeus convention:
            //   reference         = e-ticket / Document Number (the actual ticket the
            //                       supplier issued — e.g. 1762950750778)
            //   gds_reference     = the Accelya/distributor PNR (e.g. OEL4AW)
            //   airline_reference = the carrier's internal record locator
            //                       (e.g. FNA2GN for Emirates, NXIORB for Oman)
            // Only airline_reference falls back to PNR if the parser couldn't
            // locate a separate airline-side locator.
            $tasks[] = [
                'additional_info'      => $this->buildAdditionalInfo($issueDate, $passengers, $grandTotal, $altPnr),
                'ticket_number'        => $paxTicket,
                'gds_reference'        => $pnr,
                'airline_reference'    => $altPnr ?: $pnr,
                'status'               => $taskStatus,
                'supplier_status'      => $taskStatus,
                'refund_date'          => null,
                'void_date'            => null,
                'price'                => $price,
                'currency'             => $ccy,
                'exchange_currency'    => $ccy,
                'original_price'       => $ccy === 'KWD' ? null : $price,
                'original_currency'    => $ccy === 'KWD' ? null : $ccy,
                'total'                => $price,
                'surcharge'            => 0,
                'penalty_fee'          => null,
                'tax'                  => $pricing['taxes'] ?? 0,
                'taxes_record'         => null,
                'refund_charge'        => null,
                'reference'            => $paxTicket ?: $pnr,
                'original_ticket_number' => null,
                'original_reference'   => null,
                'created_by'           => null,
                'issued_by'            => null,
                'iata_number'          => null,
                'type'                 => 'flight',
                'agent_name'           => null,
                'agent_email'          => null,
                'agent_amadeus_id'     => null,
                'client_name'          => $paxKey,
                'passenger_name'       => $paxKey, // task list shows passenger_name; mirror client_name
                'supplier_name'        => $this->supplierName,
                'supplier_country'     => $this->supplierCountry,
                'cancellation_policy'  => 'Per ' . $this->carrierName . ' fare rules.',
                'venue'                => $this->extractVenue($flights),
                'issued_date'          => $issueDate,
                // task list's "Issued Date" column actually reads
                // supplier_pay_date (blade tasks/index.blade.php). Mirror
                // issued_date so the UI surfaces a date instead of "-".
                'supplier_pay_date'    => $issueDate,
                'is_exchanged'         => false,
                // Per-flight enrichment: stamp the carrier name into airline_id
                // (the column stores the airline NAME as text, despite its name)
                // and the e-ticket number so each segment row carries it too.
                'task_flight_details'  => array_map(function ($f) use ($paxTicket) {
                    $f['airline_id']    = $this->carrierName;
                    $f['ticket_number'] = $f['ticket_number'] ?? $paxTicket;
                    return $f;
                }, $flights),
            ];
        }

        return $tasks;
    }

    // ──────────────────────────── extractors ────────────────────────────

    private function extractPnr(): ?string
    {
        if (preg_match('/Itinerary for Record Locator\s+([A-Z0-9]{5,8})/i', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract the airline-side record locator (the carrier's own PNR — e.g.
     * FNA2GN for Emirates, NXIORB for Oman Indirect), distinct from the
     * Accelya record locator which appears as "Itinerary for Record Locator".
     * The body always carries both, on consecutive lines:
     *   Itinerary for Record Locator OEL4AW
     *   Emirates Record Locator FNA2GN
     */
    private function extractAltPnr(): ?string
    {
        // Explicit carrier prefixes — keeps us from matching "Itinerary for
        // Record Locator <PNR>" which would otherwise capture the main PNR
        // (because lazy `[A-Z][A-Za-z\s]+?` would eat "Itinerary for").
        $carrierPrefixes = '(?:Emirates|Oman Air|Oman Indirect|Etihad|Qatar Airways|Saudia)';
        if (preg_match("/{$carrierPrefixes}\\s+Record Locator\\s+([A-Z0-9]{5,8})/", $this->text, $m)) {
            return $m[1];
        }
        // Fallback: scan for ANY "Record Locator XXX" and return the LAST one
        // — the FIRST is always the Accelya/Itinerary header, the later ones
        // are the airline-side locator(s).
        if (preg_match_all('/Record Locator\s+([A-Z0-9]{5,8})/', $this->text, $matches)) {
            $all = $matches[1];
            if (count($all) >= 2) {
                return end($all);
            }
        }
        return null;
    }

    private function extractIssueDate(): ?string
    {
        // "Date: 2026-05-09T14:54:23Z" (email header)
        if (preg_match('/Date:\s+(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z?/', $this->text, $m)) {
            return sprintf('%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
        }
        // "29JUL25" (issuance date short form)
        if (preg_match('/Electronic Ticket\s+\d+\s+(\d{1,2})([A-Z]{3})(\d{2})/', $this->text, $m)) {
            return $this->toIsoDate((int)$m[1], $m[2], 2000 + (int)$m[3]);
        }
        return null;
    }

    /**
     * @return array<int, array{name: string, pax_type: string}>
     */
    private function extractPassengers(): array
    {
        $seen = [];
        $passengers = [];
        // Pattern: "MR/MRS/MISS/CHD/INF <NAME> (ADT|CHD|INF)"
        if (preg_match_all('/((?:MRS?|MS|MISS|DR|MSTR|CHD|INF)\s+(?:[A-Z][A-Z\'\-\.]*\s+)+?)\(([A-Z]{3})\)/', $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(preg_replace('/\s+/', ' ', $m[1]));
                $key = $name;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $passengers[] = ['name' => $name, 'pax_type' => $m[2]];
            }
        }
        return $passengers;
    }

    /**
     * Parse flight segments. When the parser was constructed from HTML
     * (the new NDC pipeline path), use DOM-based extraction for the rich
     * structured fields. When the parser only has flattened text (the
     * old PDF path), fall back to the regex-only extractor.
     */
    private function extractFlights(): array
    {
        if ($this->rawHtml !== null) {
            $flights = $this->extractFlightsFromHtml();
            if (!empty($flights)) {
                return $flights;
            }
        }
        return $this->extractFlightsFromText();
    }

    /**
     * DOM-based flight extraction for HTML emails. Pulls the structured
     * FlightTable + (when present) the iflybags.com footer URL which carries
     * IATA codes and ISO datetimes for each segment as query params — a
     * much cleaner source than the printed table.
     */
    private function extractFlightsFromHtml(): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $this->rawHtml);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        // === Primary: iflybags URL ===
        // Pattern (per OEL4AW): ar1=EK&flt1=0858&dc1=KWI&dd1=2026-06-02&dt1=17:40&ac1=DXB&ad1=2026-06-02&at1=20:25&cos1=Y...
        // ar=airline code, flt=flight number, dc=depart city, dd=depart date,
        // dt=depart time, ac=arrive city, ad=arrive date, at=arrive time,
        // cos=class of service, fbc=fare basis
        $url = null;
        foreach ($xpath->query("//a[contains(@href,'iflybags.com')]/@href") as $hrefAttr) {
            $h = $hrefAttr->nodeValue;
            if (strpos($h, 'dc1=') !== false) { $url = $h; break; }
        }
        if ($url) {
            $parts = parse_url(html_entity_decode($url));
            parse_str($parts['query'] ?? '', $q);
            $n = (int)($q['fn'] ?? 0);
            $flights = [];
            for ($i = 1; $i <= $n; $i++) {
                $airline = $q["ar{$i}"] ?? $this->iataCode;
                $flt = ltrim($q["flt{$i}"] ?? '', '0');
                $from = $q["dc{$i}"] ?? null;
                $to = $q["ac{$i}"] ?? null;
                $depDate = $q["dd{$i}"] ?? null;
                $depTime = $q["dt{$i}"] ?? null;
                $arrDate = $q["ad{$i}"] ?? null;
                $arrTime = $q["at{$i}"] ?? null;
                $cos = $q["cos{$i}"] ?? null;
                $fbc = $q["fbc{$i}"] ?? null;
                if (!$flt) continue;
                $flights[] = [
                    'flight_number'  => trim("$airline $flt"),
                    'airport_from'   => $from,
                    'airport_to'     => $to,
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => $depDate && $depTime ? "$depDate $depTime:00" : null,
                    'arrival_time'   => $arrDate && $arrTime ? "$arrDate $arrTime:00" : null,
                    'duration_time'  => null,
                    'airline_name'   => $this->carrierName,
                    'class_type'     => $this->mapClassOfService($cos),
                    'baggage_allowed'=> null,
                    'equipment'      => null,
                    'flight_meal'    => null,
                    'seat_no'        => null,
                    // task_flight_details.farebase is a FLOAT amount, not the
                    // IATA fare basis code (which is e.g. "LEEOPKW1"). Keep
                    // null here; the fare basis code is preserved in the
                    // task's additional_info via buildAdditionalInfo().
                    'farebase'       => null,
                    'fare_basis_code' => $fbc, // ignored by the table insert,
                                                // available for downstream code
                    'ticket_number'  => null,
                ];
            }
            // Fill terminals from the table even when URL gave us everything else
            if (!empty($flights)) {
                $this->fillTerminalsFromTable($xpath, $flights);
                return $flights;
            }
        }

        // === Fallback: FlightTable rows ===
        // Each segment row has 9 cells: Airline / Flight# / FromAirport+Terminal
        // / FromDateTime / ToAirport+Terminal / ToDateTime / Class / Cabin / Meals
        $flights = [];
        $rows = $xpath->query("//table[@id='FlightTable']/tbody/tr|//table[@id='FlightTable']/tr");
        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells->length !== 9) continue;
            $airlineCell = trim(preg_replace('/\s+/', ' ', $cells->item(0)->textContent));
            $fltCell     = trim($cells->item(1)->textContent);
            $fromCell    = trim(preg_replace('/\s+/', ' ', $cells->item(2)->textContent));
            $fromDtCell  = trim(preg_replace('/\s+/', ' ', $cells->item(3)->textContent));
            $toCell      = trim(preg_replace('/\s+/', ' ', $cells->item(4)->textContent));
            $toDtCell    = trim(preg_replace('/\s+/', ' ', $cells->item(5)->textContent));
            $classCell   = trim($cells->item(6)->textContent);
            // Heuristic: airline cell contains a name (e.g. "Emirates"); flt cell is digits
            if (!preg_match('/^\d{2,4}$/', $fltCell)) continue;

            [$fromAirport, $fromTerminal] = $this->splitAirportTerminal($fromCell);
            [$toAirport,   $toTerminal]   = $this->splitAirportTerminal($toCell);

            $flights[] = [
                'flight_number'  => trim($this->iataCode . ' ' . $fltCell),
                'airport_from'   => $fromAirport,
                'airport_to'     => $toAirport,
                'terminal_from'  => $fromTerminal,
                'terminal_to'    => $toTerminal,
                'departure_time' => $this->parseDateTime($fromDtCell),
                'arrival_time'   => $this->parseDateTime($toDtCell),
                'duration_time'  => null,
                'airline_name'   => $this->carrierName,
                'class_type'     => $this->mapClassOfService($classCell),
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

    /** Extract city and Terminal:X from a combined cell like "Kuwait, KW\nTerminal:1" */
    private function splitAirportTerminal(string $cell): array
    {
        $terminal = null;
        if (preg_match('/Terminal:\s*(\S+)/i', $cell, $m)) {
            $terminal = $m[1];
            $cell = trim(preg_replace('/Terminal:\s*\S+/i', '', $cell));
        }
        return [trim($cell, " ,"), $terminal];
    }

    /**
     * "TUE 02JUN 17:40" (24h, Emirates) or "SAT 20JUN 08:45 PM" (12h, Oman) →
     * ISO datetime, year inferred from issued_date. Handles the AM/PM suffix —
     * Oman Air's FlightTable prints 12-hour clock, Emirates' iflybags URL is 24h.
     */
    private function parseDateTime(string $cell): ?string
    {
        if (!preg_match('/(\d{1,2})\s*([A-Z]{3})\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i', $cell, $m)) {
            return null;
        }
        $months = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06',
                   'JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
        $mm = $months[strtoupper($m[2])] ?? null;
        if (!$mm) return null;
        $year = (int)date('Y');
        $issued = $this->extractIssueDate();
        if ($issued) $year = (int)substr($issued, 0, 4);

        $hour = (int) $m[3];
        $ampm = strtoupper($m[5] ?? '');
        if ($ampm === 'PM' && $hour < 12) $hour += 12;
        if ($ampm === 'AM' && $hour === 12) $hour = 0;

        return sprintf('%04d-%s-%02d %02d:%02d:00', $year, $mm, (int)$m[1], $hour, (int)$m[4]);
    }

    /** Map IATA single-letter class to a friendly name */
    private function mapClassOfService(?string $cos): string
    {
        if (!$cos) return 'economy';
        $cos = strtoupper(trim($cos));
        if (in_array($cos, ['F','A','P'], true)) return 'first';
        if (in_array($cos, ['J','C','D','I','Z'], true)) return 'business';
        if (in_array($cos, ['W','S','E'], true)) return 'premium economy';
        return 'economy';
    }

    /** When URL gave us IATA codes but no terminals, look them up in the table */
    private function fillTerminalsFromTable(\DOMXPath $xpath, array &$flights): void
    {
        $rows = $xpath->query("//table[@id='FlightTable']/tbody/tr|//table[@id='FlightTable']/tr");
        $idx = 0;
        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells->length !== 9) continue;
            if (!isset($flights[$idx])) break;
            $fromCell = trim(preg_replace('/\s+/', ' ', $cells->item(2)->textContent));
            $toCell   = trim(preg_replace('/\s+/', ' ', $cells->item(4)->textContent));
            [, $fromTerm] = $this->splitAirportTerminal($fromCell);
            [, $toTerm]   = $this->splitAirportTerminal($toCell);
            $flights[$idx]['terminal_from'] = $fromTerm;
            $flights[$idx]['terminal_to']   = $toTerm;
            $idx++;
        }
    }

    /** Text-only fallback for the PDF path (smalot-extracted text). */
    private function extractFlightsFromText(): array
    {
        $flights = [];
        $iata = $this->iataCode;

        if (preg_match('/Price Summary \(for flights\s+([^\)]+)\)/i', $this->text, $m)) {
            if (preg_match_all('/([A-Z]{2})\s+(\d{1,4})/', $m[1], $fm, PREG_SET_ORDER)) {
                foreach ($fm as $f) {
                    $flights[] = $this->emptyFlight($f[1] . ' ' . $f[2]);
                }
            }
        }
        if (empty($flights) && $iata) {
            if (preg_match_all('/(?:^|\n)\s*(\d{2,4})\s+[A-Z][a-z]+/m', $this->text, $m)) {
                $seen = [];
                foreach ($m[1] as $fn) {
                    if (isset($seen[$fn])) continue;
                    $seen[$fn] = true;
                    $flights[] = $this->emptyFlight($iata . ' ' . $fn);
                }
            }
        }
        return $flights;
    }

    private function emptyFlight(string $flightNumber): array
    {
        return [
            'flight_number'  => $flightNumber,
            'airport_from'   => null,
            'airport_to'     => null,
            'terminal_from'  => null,
            'terminal_to'    => null,
            'departure_time' => null,
            'arrival_time'   => null,
            'duration_time'  => null,
            'airline_name'   => $this->carrierName,
            'class_type'     => 'economy',
            'baggage_allowed'=> null,
            'equipment'      => null,
            'flight_meal'    => null,
            'seat_no'        => null,
            'farebase'       => null,
            'ticket_number'  => null,
        ];
    }

    /**
     * @return array<string, array{base: ?float, taxes: ?float, total: ?float, ccy: string}>
     */
    private function extractPriceByPax(): array
    {
        $out = [];
        // Per-pax row pattern: "<NAME> base taxes total CURRENCY"
        // Names can span lines (e.g. "MRS INDRANI RAYMAN RANKOTH PEDI\nDURAYALAGE (ADT) 35.000 26.650 61.650 KWD")
        // Approach: collapse newlines around names, then regex.
        $collapsed = preg_replace('/\(ADT\)\s*\n/', '(ADT) ', $this->text);
        if (preg_match_all(
            '/((?:MRS?|MS|MISS|DR|MSTR|CHD|INF)\s+(?:[A-Z][A-Z\'\-\.]*\s+){0,8}?)\((ADT|CHD|INF)\)\s+([\d,.]+)\s+([\d,.]+)\s+([\d,.]+)\s+([A-Z]{3})/',
            $collapsed,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $name = trim(preg_replace('/\s+/', ' ', $m[1]));
                $out[$name] = [
                    'base'  => (float) str_replace(',', '', $m[3]),
                    'taxes' => (float) str_replace(',', '', $m[4]),
                    'total' => (float) str_replace(',', '', $m[5]),
                    'ccy'   => $m[6],
                ];
            }
        }
        return $out;
    }

    /**
     * @return array{total: ?float, ccy: string}
     */
    private function extractGrandTotal(): array
    {
        // HTML emails lay the totals out in a column table (label row separate
        // from value row) which the flat-text regexes below can't stitch back
        // together — read it from the DOM first when we have the raw HTML.
        if ($this->rawHtml !== null) {
            $fromDom = $this->extractGrandTotalFromHtml();
            if ($fromDom['total'] !== null) {
                return $fromDom;
            }
        }
        // "Grand Total for all travelers Amount Currency\n<n.nnn> KWD"
        if (preg_match('/Grand Total[^\n]*\n[^\n]*?([\d,.]+)\s+([A-Z]{3})/', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[1]), 'ccy' => $m[2]];
        }
        // "Total <n.nnn> KWD"
        if (preg_match('/(?<!Grand\s)(?<!Sub)Total\s+([\d,.]+)\s+([A-Z]{3})/', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[1]), 'ccy' => $m[2]];
        }
        return ['total' => null, 'ccy' => 'KWD'];
    }

    /**
     * Pull the grand total from the "Grand Total for all travelers" table in the
     * HTML body. The label cells sit in a header row; the amount + currency are
     * the first numeric/3-letter pair in the following data row.
     * @return array{total: ?float, ccy: string}
     */
    private function extractGrandTotalFromHtml(): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $this->rawHtml);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        // Anchor on the <th> carrying the phrase, then walk to its NEAREST
        // ancestor <table>. (Querying //table descendant-first would match the
        // outer wrapper table, whose descendant rows include the per-pax invoice
        // row — we'd grab the Base amount instead of the grand total.)
        foreach ($xpath->query('//th') as $th) {
            if (stripos($th->textContent, 'Grand Total for all travelers') === false) {
                continue;
            }
            $table = $th->parentNode;
            while ($table && strtolower($table->nodeName) !== 'table') {
                $table = $table->parentNode;
            }
            if (!$table) continue;

            foreach ($xpath->query('./tbody/tr|./tr', $table) as $tr) {
                $vals = [];
                foreach ($xpath->query('./td', $tr) as $c) {
                    $t = trim($c->textContent);
                    if ($t !== '') $vals[] = $t;
                }
                foreach ($vals as $i => $v) {
                    if (preg_match('/^[\d,]+\.\d{2,3}$/', $v)) {
                        $ccy = (isset($vals[$i + 1]) && preg_match('/^[A-Z]{3}$/', $vals[$i + 1]))
                            ? $vals[$i + 1] : 'KWD';
                        return ['total' => (float) str_replace(',', '', $v), 'ccy' => $ccy];
                    }
                }
            }
        }
        return ['total' => null, 'ccy' => 'KWD'];
    }

    private function extractTicketNumber(): ?string
    {
        if (preg_match('/Electronic Ticket\s+(\d{10,14})/', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Normalise a passenger name for cross-section matching (collapse ws, upper). */
    private function normName(string $n): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $n)));
    }

    /**
     * IATA e-ticket / Document Numbers are 13 digits = 3-digit airline code
     * (176 Emirates, 910 Oman Air …) + 10-digit serial. CityTour stores the
     * 10-digit serial as the ticket/reference, so drop the airline-code prefix.
     * Leaves non-13-digit values untouched (already a serial, or unknown form).
     */
    private function ndcTicketSerial(?string $doc): ?string
    {
        if ($doc === null) {
            return null;
        }
        $d = preg_replace('/\D/', '', $doc);
        return strlen($d) === 13 ? substr($d, 3) : $doc;
    }

    /**
     * Per-passenger e-ticket / Document Number from the HTML Invoice Information
     * tables. Each passenger has their OWN invoice table: a <th> header
     * "NAME (ADT)" plus an "Electronic Ticket <docNumber>" data row. NDC issues
     * a sequential Document Number per passenger, so a single shared ticket is
     * wrong — map each passenger to their own number.
     * @return array<string,string> normalisedName => documentNumber
     */
    private function extractTicketByPaxFromHtml(): array
    {
        if ($this->rawHtml === null) {
            return [];
        }
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $this->rawHtml);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        $map = [];
        foreach ($xpath->query('//table') as $table) {
            // Passenger name lives in a <th> ending in "(ADT|CHD|INF)".
            $name = null;
            foreach ($xpath->query('.//th', $table) as $th) {
                $txt = trim(preg_replace('/\s+/', ' ', $th->textContent));
                if (preg_match('/((?:MRS?|MS|MISS|DR|MSTR|CHD|INF)\s+.+?)\s*\((?:ADT|CHD|INF)\)/', $txt, $nm)) {
                    $name = trim($nm[1]);
                    break;
                }
            }
            if (!$name) {
                continue;
            }
            // Find the Electronic Ticket row within THIS table; the document
            // number is the next numeric cell after the "Electronic Ticket" cell.
            foreach ($xpath->query('.//tr', $table) as $tr) {
                $cells = [];
                foreach ($xpath->query('./td', $tr) as $c) {
                    $cells[] = trim($c->textContent);
                }
                for ($i = 0; $i < count($cells); $i++) {
                    if (stripos($cells[$i], 'Electronic Ticket') !== false) {
                        for ($j = $i + 1; $j < count($cells); $j++) {
                            if (preg_match('/^\d{10,14}$/', $cells[$j])) {
                                // First (innermost) table for a pax wins; don't
                                // overwrite with the outer wrapper's repeat.
                                $key = $this->normName($name);
                                if (!isset($map[$key])) {
                                    $map[$key] = $cells[$j];
                                }
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        return $map;
    }

    private function extractVenue(array $flights): ?string
    {
        if (empty($flights)) return null;
        $last = end($flights);
        return $last['arrive_to'] ?? null;
    }

    private function toIsoDate(int $day, string $monAbbrev, int $year): string
    {
        $months = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06',
                   'JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
        $mm = $months[strtoupper($monAbbrev)] ?? '01';
        return sprintf('%04d-%s-%02d 00:00:00', $year, $mm, $day);
    }

    private function buildAdditionalInfo(?string $issueDate, array $passengers, array $grandTotal, ?string $altPnr): string
    {
        $bits = [];
        $bits[] = "Carrier: {$this->carrierName}";
        if ($issueDate) $bits[] = "Issue date: " . substr($issueDate, 0, 10);
        if ($altPnr) $bits[] = "Airline record locator: $altPnr";
        if (count($passengers) > 1) $bits[] = "Passengers in booking: " . count($passengers);
        if ($grandTotal['total'] !== null) {
            // Mirror the cross-supplier convention used by Jazeera + ETA: when
            // the supplier charged in a foreign currency, expose it as
            // "Original: <amt> <ccy>" so the agent has the audit record even
            // after they manually edit the KWD price pre-invoice.
            if (($grandTotal['ccy'] ?? 'KWD') !== 'KWD') {
                $bits[] = "Original: " . $grandTotal['total'] . ' ' . $grandTotal['ccy'];
            } else {
                $bits[] = "Grand total: " . $grandTotal['total'] . ' ' . $grandTotal['ccy'];
            }
        }
        return implode(' | ', $bits);
    }
}
