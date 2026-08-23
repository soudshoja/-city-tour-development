<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Air Arabia confirmation PDFs.
 *
 * Format markers:
 *  - "CONFIRMED RESERVATION" header
 *  - "Mr/Mrs <Name>" line (just below the barcode reference)
 *  - "Reservation Number" / "E-ticket number <digits>" / "<PNR>"
 *  - "Booking Date <DD MMM YYYY>"
 *  - "Travel Itinerary" then flight rows like "3L021" or "G9123"
 *  - No price field in these confirmations (extras booked separately)
 *
 * ALSO parses the BODY-ONLY "Itinerary for the Reservation <PNR>" email from
 * reservation@airarabia.com (no PDF): "Confirmed Reservation", "Reservation
 * Number" block, per-segment itinerary with terminals/times, and — unlike the
 * PDF — a "Summary of Charges" with the KWD total. Before 2026-08-03 these
 * bodies were sender-routed into the folder but no parser matched, so every
 * body-only Air Arabia booking died in files_error.
 */
class AirArabiaParser
{
    public const SUPPLIER_NAME    = 'Air Arabia';
    public const SUPPLIER_COUNTRY = 'United Arab Emirates';
    public const SIGNATURE_A      = 'CONFIRMED RESERVATION';
    public const SIGNATURE_B      = 'airarabia.com';

    private string $filePath;
    private string $text;
    /** @var string[] */
    private array $lines;
    private bool $isEmail = false;

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

    /** Build from a raw HTML email body (body-only confirmation mail). */
    public static function fromHtml(string $html): self
    {
        $self = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $self->filePath = '';
        $self->text     = self::htmlToText($html);
        $self->lines    = explode("\n", $self->text);
        $self->isEmail  = true;

        return $self;
    }

    public static function matches(string $rawText): bool
    {
        // PDF attachment fingerprint
        if (stripos($rawText, self::SIGNATURE_A) !== false
            && stripos($rawText, self::SIGNATURE_B) !== false) {
            return true;
        }

        // Body-only email fingerprint (flattened text has no airarabia.com —
        // the domain only occurs inside stripped href attributes)
        return stripos($rawText, 'Confirmed Reservation') !== false
            && stripos($rawText, 'Reservation Number') !== false
            && stripos($rawText, 'Air Arabia') !== false;
    }

    /** Kept in sync with SupplierPdfDetector::flattenHtml(). */
    private static function htmlToText(string $html): string
    {
        // Raw IMAP bodies use \r\n — normalise first or the blank-line
        // collapse below never matches ("\n\r\n" is not "\n\n").
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/\s*(p|div|tr|li|h[1-6]|td|th)\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*(p|div|tr|li|h[1-6])\b[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\s*(td|th)\b[^>]*>/i', "\t", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // &nbsp; decodes to U+00A0, which ASCII \s/space matching misses
        $text = preg_replace('/[\x{00A0}\x{202F}\x{2007}]/u', ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text);
        // Air Arabia's raw template pads labels and values with DOZENS of blank
        // lines (the label sits ~7 blanks above its value) — collapse hard so
        // line-adjacency extraction works on both raw-IMAP and pre-normalised HTML.
        $text = preg_replace('/\n{2,}/', "\n", $text);

        return trim($text);
    }

    public function parseTaskSchema(): array
    {
        $pnr      = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception("Could not locate Air Arabia PNR in PDF");
        }
        $ticket   = $this->extractTicket();
        $issued   = $this->extractIssueDate();
        $flights  = $this->extractFlights();
        $totalCcy = $this->isEmail ? $this->extractTotal() : ['total' => null, 'currency' => null];
        $isKwd    = ($totalCcy['currency'] ?? 'KWD') === 'KWD';

        // Email bodies list EVERY passenger; the PDF names a single one. One
        // task per pax, booking total split evenly (Jazeera convention).
        $passengers = $this->isEmail ? $this->extractPassengers() : [];
        if (empty($passengers)) {
            $passengers = [$this->extractName() ?: 'UNKNOWN PASSENGER'];
        }
        $perPax = $totalCcy['total'] !== null
            ? round($totalCcy['total'] / count($passengers), 3)
            : null;

        $tasks = [];
        foreach ($passengers as $name) {
        $task = [
            'additional_info'      => $this->buildAdditionalInfo($issued, $ticket),
            'ticket_number'        => $ticket,
            'gds_reference'        => $pnr,
            'airline_reference'    => $pnr,
            'status'               => 'issued',
            'supplier_status'      => 'issued',
            'refund_date'          => null,
            'void_date'            => null,
            // No price on the PDF variant: emit 0, not null — task creation posts
            // a transactions row whose amount column is NOT NULL (every Air
            // Arabia attachment PDF crashed on this before 2026-08-03), and the
            // WhatsApp price-request flow asks the agent for 0-cost tickets.
            'price'                => $isKwd ? ($perPax ?? 0.0) : null,
            'currency'             => $isKwd ? 'KWD' : $totalCcy['currency'],
            'exchange_currency'    => $isKwd ? 'KWD' : $totalCcy['currency'],
            'original_price'       => $isKwd ? null : $perPax,
            'original_currency'    => $isKwd ? null : $totalCcy['currency'],
            'total'                => $isKwd ? ($perPax ?? 0.0) : null,
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
            'client_name'          => $name ?: 'UNKNOWN PASSENGER',
            'supplier_name'        => self::SUPPLIER_NAME,
            'supplier_country'     => self::SUPPLIER_COUNTRY,
            'cancellation_policy'  => 'Per Air Arabia fare rules.',
            'venue'                => $flights[0]['arrive_to'] ?? null,
            'issued_date'          => $issued,
            'is_exchanged'         => false,
            'task_flight_details'  => $flights,
        ];
        $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * Email body: titled names between the "Passengers" heading and the
     * "Contact Information" heading (the Summary of Charges repeats a
     * "Passengers" label later — only the first section counts).
     * @return string[]
     */
    private function extractPassengers(): array
    {
        $passengers = [];
        $collecting = false;
        foreach ($this->lines as $ln) {
            $t = trim($ln);
            if (!$collecting && strcasecmp($t, 'Passengers') === 0) {
                $collecting = true;
                continue;
            }
            if ($collecting) {
                if (stripos($t, 'Contact Information') !== false) {
                    break;
                }
                if (preg_match('/^(Mr|Mrs|Ms|Miss|Dr|Mstr)\.?\s+[A-Za-z].*$/', $t)
                    && !in_array($t, $passengers, true)) {
                    $passengers[] = $t;
                }
            }
        }

        return $passengers;
    }

    private function extractPnr(): ?string
    {
        // "E-ticket number 5142341520607\n1B0PFN"
        for ($i = 0; $i < count($this->lines) - 1; $i++) {
            if (preg_match('/E-ticket number\s+\d{10,15}/i', trim($this->lines[$i]))) {
                $next = trim($this->lines[$i + 1]);
                if (preg_match('/^([A-Z0-9]{5,8})$/', $next, $m)) {
                    return $m[1];
                }
            }
        }
        // Email body: "Reservation Number" block, PNR on the next non-empty line
        for ($i = 0; $i < count($this->lines) - 1; $i++) {
            if (strcasecmp(trim($this->lines[$i]), 'Reservation Number') === 0) {
                for ($j = $i + 1; $j < min($i + 4, count($this->lines)); $j++) {
                    $next = trim($this->lines[$j]);
                    if ($next === '') {
                        continue;
                    }
                    if (preg_match('/^([A-Z0-9]{5,8})$/', $next, $m)) {
                        return $m[1];
                    }
                    break;
                }
            }
        }
        // Fallback: filename pattern "..._<PNR>.pdf"
        if (preg_match('/_([A-Z0-9]{5,8})\.pdf$/i', $this->filePath, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Email-only: "Total Payment KWD 181.72" from the Summary of Charges.
     * @return array{total: ?float, currency: ?string}
     */
    private function extractTotal(): array
    {
        if (preg_match('/Total Payment\s+([A-Z]{3})\s+([\d,.]+)/i', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        return ['total' => null, 'currency' => null];
    }

    private function extractTicket(): ?string
    {
        if (preg_match('/E-ticket number\s+(\d{10,15})/i', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractIssueDate(): ?string
    {
        if (preg_match('/Booking Date\s+(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})/i', $this->text, $m)) {
            return $this->toIsoDate((int)$m[1], $m[2], (int)$m[3]);
        }
        return null;
    }

    private function extractName(): ?string
    {
        // Line right after "Scan this barcode at self check-in"
        for ($i = 0; $i < count($this->lines) - 1; $i++) {
            if (stripos($this->lines[$i], 'Scan this barcode') !== false) {
                $next = trim($this->lines[$i + 1]);
                if (preg_match('/^(Mr|Mrs|Ms|Miss|Dr|Mstr)\s+([A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+)+)$/', $next, $m)) {
                    return trim($m[1] . ' ' . $m[2]);
                }
            }
        }
        // Fallback: any "Mr/Mrs <name>" at start of line
        if (preg_match('/^(Mr|Mrs|Ms|Miss|Dr|Mstr)\s+([A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+)+)$/m', $this->text, $m)) {
            return trim($m[1] . ' ' . $m[2]);
        }
        return null;
    }

    private function extractFlights(): array
    {
        if ($this->isEmail) {
            return $this->extractFlightsFromEmail();
        }

        $flights = [];
        $seen = [];
        // Air Arabia codes: G9 (mainline), 3L (Egypt), E5 (Abu Dhabi), maybe more
        if (preg_match_all('/^(G9|3L|E5)\s*(\d{3,4})\b/m', $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $fn = $m[1] . $m[2];
                if (isset($seen[$fn])) continue;
                $seen[$fn] = true;
                $flights[] = [
                    'flight_number'  => $m[1] . ' ' . $m[2],
                    'airport_from'   => null,
                    'airport_to'     => null,
                    'departure_from' => null,
                    'arrive_to'      => null,
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => null,
                    'arrival_time'   => null,
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
        return $flights;
    }

    /**
     * Email-body segments, each shaped like:
     *   3L023 / Economy / 1h 45m / KWI / AUH /
     *   "Kuwait - Terminal 4" \n "02 Jul 2026 18:35" /
     *   "Abu Dhabi - Terminal A" \n "02 Jul 2026 21:20"
     */
    private function extractFlightsFromEmail(): array
    {
        $flights = [];
        // Two layouts: Graph-normalised ("Kuwait - Terminal 4\n02 Jul 2026 18:35")
        // and raw IMAP (city, optional "- Terminal X", date and time each on
        // their OWN line). \s+ spans the newline between date and time.
        $re = '/\b(G9|3L|E5)\s*(\d{3,4})\b(?:\n[^\n]{0,40}){0,4}?'
            . '\n([A-Z]{3})\n(?:[^\n]{0,4}\n)?([A-Z]{3})\n'
            . '([A-Za-z][A-Za-z ]*?)(?:\s*-\s*Terminal\s*(\S+))?\s*\n'
            . '(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})\s+(\d{1,2}:\d{2})\s*\n'
            . '([A-Za-z][A-Za-z ]*?)(?:\s*-\s*Terminal\s*(\S+))?\s*\n'
            . '(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})\s+(\d{1,2}:\d{2})/u';
        if (preg_match_all($re, $this->text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $flights[] = [
                    'flight_number'  => $m[1] . ' ' . $m[2],
                    'airport_from'   => $m[3],
                    'airport_to'     => $m[4],
                    'departure_from' => trim($m[5]),
                    'arrive_to'      => trim($m[9]),
                    'terminal_from'  => $m[6] !== '' ? $m[6] : null,
                    'terminal_to'    => ($m[10] ?? '') !== '' ? $m[10] : null,
                    'departure_time' => $this->emailDateTime($m[7], $m[8]),
                    'arrival_time'   => $this->emailDateTime($m[11], $m[12]),
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

        // Fallback: at least record the flight numbers
        if (empty($flights) && preg_match_all('/\b(G9|3L|E5)\s*(\d{3,4})\b/', $this->text, $mm, PREG_SET_ORDER)) {
            $seen = [];
            foreach ($mm as $m) {
                $fn = $m[1] . $m[2];
                if (isset($seen[$fn])) { continue; }
                $seen[$fn] = true;
                $flights[] = [
                    'flight_number' => $m[1] . ' ' . $m[2], 'airport_from' => null, 'airport_to' => null,
                    'departure_from' => null, 'arrive_to' => null, 'terminal_from' => null, 'terminal_to' => null,
                    'departure_time' => null, 'arrival_time' => null, 'duration_time' => null,
                    'airline_name' => self::SUPPLIER_NAME, 'class_type' => 'economy', 'baggage_allowed' => null,
                    'equipment' => null, 'flight_meal' => null, 'seat_no' => null, 'farebase' => null,
                    'ticket_number' => null,
                ];
            }
        }

        return $flights;
    }

    /** "02 Jul 2026" + "18:35" -> "2026-07-02 18:35:00" */
    private function emailDateTime(string $date, string $time): ?string
    {
        if (!preg_match('/^(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})$/', trim($date), $d)) {
            return null;
        }
        $iso = substr($this->toIsoDate((int) $d[1], $d[2], (int) $d[3]), 0, 10);

        return $iso . ' ' . sprintf('%05s', $time) . ':00';
    }

    private function toIsoDate(int $day, string $month, int $year): string
    {
        $months = ['Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
                   'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12',
                   'January'=>'01','February'=>'02','March'=>'03','April'=>'04','June'=>'06',
                   'July'=>'07','August'=>'08','September'=>'09','October'=>'10','November'=>'11','December'=>'12'];
        $mm = $months[ucfirst(strtolower($month))] ?? '01';
        return sprintf('%04d-%s-%02d 00:00:00', $year, $mm, $day);
    }

    private function buildAdditionalInfo(?string $issued, ?string $ticket): string
    {
        $bits = [];
        if ($issued) $bits[] = "Booking date: " . substr($issued, 0, 10);
        if ($ticket) $bits[] = "E-ticket: $ticket";
        return implode(' | ', $bits);
    }
}
