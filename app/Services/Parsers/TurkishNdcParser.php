<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Turkish Airlines NDC order documents
 * ("Order ID XXXXXX" e-ticket / itinerary PDFs issued through the THY NDC
 * portal, Issuing Office 158761106 - CITY TRAVELERS).
 *
 * This is NOT the Accelya NDC family (no "Itinerary for Record Locator") — it's
 * Turkish's own order layout. Two physical layouts are seen in production:
 *   A) Glued single-line  — most e-tickets; labels run together
 *                           ("Order ID T8T6NXCreate Date 11 Feb, 2026...").
 *   B) Newline layout      — unticketed quotes/itineraries (one field per line).
 * Both are handled by tolerating optional whitespace/newlines between tokens.
 *
 * Ticketing state:
 *   has "Ticket Date" OR a 13-digit ticket number  → status = 'issued'
 *   otherwise (quote / confirmed order)             → status = 'confirmed'
 *
 * Returns the same shape as AirFileParser::parseTaskSchema() — one task array
 * per passenger.
 */
class TurkishNdcParser
{
    public const SUPPLIER_NAME    = 'Turkish Airline NDC';
    public const SUPPLIER_COUNTRY = 'Turkey';
    public const IATA_CODE        = 'TK';

    private ?string $filePath = null;
    private string $text = '';
    /** @var string[] */
    private array $lines = [];
    /** True when built from an HTML email body (Turkish e-ticket mail), not a PDF. */
    private bool $isEmail = false;

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            $this->filePath = $filePath;
            $parser = new PdfParser();
            $this->setText($parser->parseFile($filePath)->getText());
        }
    }

    /**
     * Build from a raw HTML email body (Turkish Airlines "E-Ticket - <PNR>" mail).
     * This is a DIFFERENT layout from the NDC "Order ID" PDF: it uses
     * "Reservation Code", a "Passenger Name / Ticket Number" block, bare airport
     * codes and carries no fare. Body-only ingestion (SupplierPdfDetector::detectHtml
     * + ProcessAirFiles HTML dispatch) calls this factory.
     */
    public static function fromHtml(string $html): self
    {
        $p = new self();
        $p->isEmail = true;
        $p->setText($p->htmlToText($html));
        return $p;
    }

    /** Flatten an HTML email body to plain text — kept in sync with SupplierPdfDetector::flattenHtml. */
    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/\s*(p|div|tr|li|h[1-6]|td|th)\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*(p|div|tr|li|h[1-6])\b[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\s*(td|th)\b[^>]*>/i', "\t", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function setText(string $text): void
    {
        $this->text = self::normalizeText($text);
        $this->lines = explode("\n", $this->text);
    }

    /**
     * Fold NBSP / other Unicode spaces to plain spaces so the `\s`/`[ \t]`
     * classes match. The U+FFFD replacement glyph that Turkish uses as the
     * city/time separator ("Kuwait (KWI) <FFFD> 06:25") is matched in the
     * extractors with a tolerant `\S?` rather than stripped here.
     */
    private static function normalizeText(string $t): string
    {
        // Fold NBSP and the assorted Unicode spaces Turkish/airline docs sprinkle
        // in — including U+200A HAIR SPACE, which the e-ticket email uses between
        // the passenger name and "Date of Birth" (PCRE \s without /u won't match
        // it). The U+FFFD glyph used as a city/time separator is intentionally NOT
        // folded — the extractors skip it tolerantly.
        return preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $t);
    }

    public static function matches(string $rawText): bool
    {
        $isTurkish = stripos($rawText, 'Turkish Airlines') !== false
            || preg_match('/\bTK\d{2,4}\b/', $rawText) === 1;
        if (!$isTurkish) {
            return false;
        }
        // PDF NDC order layout: distinctive "Order ID". Accelya NDC uses
        // "Itinerary for Record Locator" (no "Order ID").
        if (stripos($rawText, 'Order ID') !== false) {
            return true;
        }
        // Email body layout (Turkish "E-Ticket - <PNR>" mail): ISSUED-ONLY — the
        // issued e-ticket mail says "Your e-ticket has been issued"; the
        // reservation/hold and shared-content mails for the same PNR do not, so
        // they are intentionally NOT matched (no task from unticketed mails).
        return stripos($rawText, 'e-ticket has been issued') !== false
            && stripos($rawText, 'Reservation Code') !== false;
    }

    /**
     * Is this the Turkish "Shared Content Information" email — the ONLY Turkish
     * mail carrying the fare (Payment Details / Total … KWD)? It is NOT a booking
     * (no task is created from it); TurkishFareResolver uses it to price the
     * tasks the E-Ticket mail already loaded at 0.
     */
    public static function isSharedContent(string $rawText): bool
    {
        $t = self::sharedContentText($rawText);
        $isTurkish = stripos($t, 'Turkish Airlines') !== false
            || stripos($t, 'turkishairlines') !== false
            || preg_match('/\bTK\d{2,4}\b/', $t) === 1;
        if (!$isTurkish) {
            return false;
        }
        if (stripos($t, 'Shared Content') !== false) {
            return true;
        }
        return stripos($t, 'Reservation Code') !== false
            && stripos($t, 'Payment Details') !== false
            && preg_match('/Total(?:\s+Fare)?\s+[\d.,]+\s*(?:KWD|USD|EUR|TRY)/i', $t) === 1;
    }

    /**
     * Pull the fare out of a Shared Content email.
     * @return array{pnr:string, total:float, pax:int, per_pax:float, currency:string, tickets:array<int,string>}|null
     */
    public static function extractSharedContentFare(string $html): ?array
    {
        $t = self::sharedContentText($html);

        if (!preg_match('/Reservation Code\s+([A-Z0-9]{5,7})/', $t, $pm)) {
            return null;
        }
        $pnr = strtoupper($pm[1]);

        // Prefer the final "Total … KWD" in Payment Details; fall back to
        // "Total Fare …" then the header "Payment … KWD".
        $total = null;
        $ccy   = 'KWD';
        if (preg_match_all('/Total(?:\s+Fare)?\s+([\d.,]+)\s*(KWD|USD|EUR|TRY)/i', $t, $tm, PREG_SET_ORDER)) {
            $last  = end($tm);
            $total = (float) str_replace(',', '', $last[1]);
            $ccy   = strtoupper($last[2]);
        } elseif (preg_match('/Payment\s+([\d.,]+)\s*(KWD|USD|EUR|TRY)/i', $t, $pm2)) {
            $total = (float) str_replace(',', '', $pm2[1]);
            $ccy   = strtoupper($pm2[2]);
        }
        if ($total === null || $total <= 0) {
            return null;
        }

        preg_match_all('/Ticket Number\s+(\d{13})/i', $t, $tk);
        $tickets = array_values(array_unique($tk[1] ?? []));

        $pax = 0;
        if (preg_match('/Flight Fare\s+(\d+)\s+Adult/i', $t, $ax)) {
            $pax = (int) $ax[1];
        }
        if ($pax < 1) {
            $pax = count($tickets);
        }
        if ($pax < 1) {
            $pax = 1;
        }

        return [
            'pnr'      => $pnr,
            'total'    => round($total, 3),
            'pax'      => $pax,
            'per_pax'  => round($total / $pax, 3),
            'currency' => $ccy,
            'tickets'  => $tickets,
        ];
    }

    /** Flatten HTML (or pass plain text through) for shared-content matching. */
    private static function sharedContentText(string $raw): string
    {
        if (stripos($raw, '<') !== false && stripos($raw, '>') !== false) {
            $raw = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $raw);
            $raw = preg_replace('/<[^>]+>/', ' ', $raw);
            $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return preg_replace('/\s+/', ' ', (string) $raw);
    }

    public function parseTaskSchema(): array
    {
        if ($this->isEmail) {
            // Email "E-Ticket" layout — different sources than the PDF NDC order.
            $orderId = $this->extractEmailPnr();
            if (!$orderId) {
                throw new \Exception("Could not locate Turkish e-ticket Reservation Code in email body");
            }
            $passengers = $this->extractEmailPassengers();  // name+ticket pairs; price null (fare not in this mail)
            $flights    = $this->extractEmailFlights();
            $total      = ['total' => null, 'ccy' => 'KWD']; // fare is in the separate "Shared Content" mail
            $issueDate  = date('Y-m-d 00:00:00');            // mail = "your e-ticket has been issued" (now)
        } else {
            $orderId = $this->extractOrderId();
            if (!$orderId) {
                throw new \Exception("Could not locate Turkish NDC Order ID in PDF");
            }
            $passengers = $this->extractPassengers();   // [['name','type','ticket','price'], ...]
            $flights    = $this->extractFlights();
            $total      = $this->extractTotal();        // ['total'=>?, 'ccy'=>...]
            $issueDate  = $this->extractIssueDate();
        }

        $isTicketed = (stripos($this->text, 'Ticket Date') !== false)
            || array_filter($passengers, fn($p) => !empty($p['ticket']));
        $taskStatus = $isTicketed ? 'issued' : 'confirmed';

        if (empty($passengers)) {
            $passengers = [['name' => 'UNKNOWN PASSENGER', 'type' => 'ADT', 'ticket' => null, 'price' => null]];
        }

        // When per-pax prices are absent but a grand total exists, split equally.
        $paxWithPrice = array_filter($passengers, fn($p) => $p['price'] !== null);
        $splitPrice = (empty($paxWithPrice) && $total['total'] !== null && count($passengers) > 0)
            ? round($total['total'] / count($passengers), 3)
            : null;

        $ccy = $total['ccy'] ?? 'KWD';

        $tasks = [];
        foreach ($passengers as $pax) {
            // Never emit a null price: transactions.amount is NOT NULL, so a null
            // would silently dump the file to files_error (the FlyDubai trap).
            $price = $pax['price'] ?? $splitPrice ?? 0;

            $tasks[] = [
                'additional_info'      => $this->buildAdditionalInfo($issueDate, $passengers, $total),
                'ticket_number'        => $this->ndcTicketSerial($pax['ticket']),
                'gds_reference'        => $orderId,
                'airline_reference'    => $orderId,
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
                'tax'                  => 0,
                'taxes_record'         => null,
                'refund_charge'        => null,
                'reference'            => $this->ndcTicketSerial($pax['ticket']) ?: $orderId,
                'original_ticket_number' => null,
                'original_reference'   => null,
                'created_by'           => null,
                'issued_by'            => null,
                'iata_number'          => null,
                'type'                 => 'flight',
                'agent_name'           => null,
                'agent_email'          => null,
                'agent_amadeus_id'     => null,
                'client_name'          => $pax['name'],
                'passenger_name'       => $pax['name'],
                'supplier_name'        => self::SUPPLIER_NAME,
                'supplier_country'     => self::SUPPLIER_COUNTRY,
                'cancellation_policy'  => 'Per Turkish Airlines fare rules.',
                'venue'                => $this->extractVenue($flights),
                'issued_date'          => $issueDate,
                'supplier_pay_date'    => $issueDate,
                'is_exchanged'         => false,
                'task_flight_details'  => array_map(function ($f) use ($pax) {
                    $f['airline_id']    = 'Turkish Airlines';
                    $f['ticket_number'] = $this->ndcTicketSerial($pax['ticket']);
                    return $f;
                }, $flights),
            ];
        }

        return $tasks;
    }

    // ──────────────────────────── extractors ────────────────────────────

    private function extractOrderId(): ?string
    {
        // Order ID is usually glued to the next label ("...T8T6NXCreate Date").
        // Bound it with a lookahead at the known following labels.
        if (preg_match('/Order ID\s*([A-Z0-9]{5,8}?)\s*(?=Create Date|Status|Ticket Date|Purge Date)/', $this->text, $m)) {
            return $m[1];
        }
        // Newline layout fallback: "Order ID\n \nVMU4YN\n".
        if (preg_match('/Order ID\s*\n\s*([A-Z0-9]{5,8})\b/', $this->text, $m)) {
            return $m[1];
        }
        if (preg_match('/Order ID\s*([A-Z0-9]{6})\b/', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @return array<int, array{name:string, type:string, ticket:?string, price:?float}>
     */
    private function extractPassengers(): array
    {
        // Scope to the "Passengers Details" … "Baggage" block so flight numbers,
        // totals and baggage weights can't leak into per-pax matching.
        $start = stripos($this->text, 'Passengers Details');
        if ($start === false) {
            return [];
        }
        $end = stripos($this->text, 'Baggage', $start);
        $region = substr($this->text, $start, ($end !== false ? $end - $start : null));

        // Split into one slice per passenger, anchored on the salutation.
        if (!preg_match_all('/(?:Mr|Mrs|Ms|Miss|Mstr|Dr)\.?\s+[A-Za-z]/', $region, $tm, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $offsets = array_map(fn($x) => $x[1], $tm[0]);

        $passengers = [];
        $seen = [];
        $n = count($offsets);
        for ($i = 0; $i < $n; $i++) {
            $from = $offsets[$i];
            $to   = ($i + 1 < $n) ? $offsets[$i + 1] : strlen($region);
            $slice = substr($region, $from, $to - $from);

            // No \b after the type: on glued layouts the DOB abuts it ("Adult22
            // Mar"), and digit-after-letter is not a word boundary.
            if (!preg_match('/^(Mr|Mrs|Ms|Miss|Mstr|Dr)\.?\s*(.+?)\s*(?:IT\s+)?(Adult|Child|Infant|CHD|INF)/s', $slice, $m)) {
                continue;
            }
            $name = $this->cleanName($m[1] . ' ' . $m[2]);
            $type = $this->mapPaxType($m[3]);

            // The per-pax price directly abuts the 13-digit ticket on clean rows
            // ("2352269897329338.15 KWD"). Only trust it when it abuts — some PDFs
            // print a stray separator/digit in the Price column
            // ("2352269897326-7456.1") that is NOT the real fare; those fall back
            // to splitting the authoritative "Total Fare" across passengers.
            // Two clean layouts, both anchored on the 13-digit ticket:
            //   amount-after  : "…897329338.15 KWD"
            //   currency-mid  : "…897348KWD 389.950"  (NBSP→space)
            // The optional currency between ticket and amount covers both. A
            // stray non-digit immediately after the ticket ("…326-7456.1") makes
            // this fail → ticket-only, price left for the Total-Fare split.
            $ticket = null;
            $price  = null;
            if (preg_match('/(\d{13})\s*(?:[A-Z]{3}\s*)?([\d,]+\.\d{1,3})/', $slice, $pm)) {
                $ticket = $pm[1];
                $price  = (float) str_replace(',', '', $pm[2]);
            } elseif (preg_match('/(\d{13})/', $slice, $pm)) {
                $ticket = $pm[1];   // ticketed, but price column unreliable → leave null
            }

            $key = strtoupper($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $passengers[] = ['name' => $name, 'type' => $type, 'ticket' => $ticket, 'price' => $price];
        }
        return $passengers;
    }

    /**
     * Parse flight segments. Each begins with a TK flight number and carries two
     * "(CODE) <sep> HH:MM" + "DD Mon, DOW" airport blocks. Best-effort: returns
     * [] rather than throwing so the task still loads if the layout shifts.
     * @return array<int, array<string, mixed>>
     */
    private function extractFlights(): array
    {
        $fStart = stripos($this->text, 'Flight Details');
        $pStart = stripos($this->text, 'Passengers Details');
        if ($fStart === false) {
            return [];
        }
        $region = substr($this->text, $fStart, ($pStart !== false ? $pStart - $fStart : null));

        if (!preg_match_all('/TK\d{2,4}/', $region, $tm, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $offsets = array_map(fn($x) => $x[1], $tm[0]);

        $issueYear = (int) (substr((string) $this->extractIssueDate(), 0, 4) ?: date('Y'));
        $prevMonth = null;
        $year = $issueYear;

        $flights = [];
        $n = count($offsets);
        for ($i = 0; $i < $n; $i++) {
            $from  = $offsets[$i];
            $to    = ($i + 1 < $n) ? $offsets[$i + 1] : strlen($region);
            $chunk = substr($region, $from, $to - $from);

            if (!preg_match('/TK(\d{2,4})/', $chunk, $fm)) {
                continue;
            }
            $flightNo = 'TK ' . $fm[1];

            // Two airport blocks: "(KWI) <sep> 06:25 12 Feb". The <sep> is the
            // multibyte U+FFFD glyph, so skip up to a few non-digit bytes rather
            // than a single \S. The date may abut the time ("06:2512 Feb").
            if (!preg_match_all(
                '/\(([A-Z]{3})\)[^\d]{0,6}(\d{1,2}:\d{2})\s*(\d{1,2})\s+([A-Za-z]{3,9})/',
                $chunk,
                $am,
                PREG_SET_ORDER
            ) || count($am) < 2) {
                $flights[] = $this->emptyFlight($flightNo);
                continue;
            }
            [$depCode, $depTime, $depDay, $depMon] = [$am[0][1], $am[0][2], (int) $am[0][3], $am[0][4]];
            [$arrCode, $arrTime, $arrDay, $arrMon] = [$am[1][1], $am[1][2], (int) $am[1][3], $am[1][4]];

            $depMm = $this->monthNum($depMon);
            // Roll the year forward when the itinerary crosses Dec→Jan.
            if ($prevMonth !== null && $depMm !== null && $depMm < $prevMonth) {
                $year++;
            }
            if ($depMm !== null) {
                $prevMonth = $depMm;
            }
            $arrYear = $year;
            $arrMm = $this->monthNum($arrMon);
            if ($arrMm !== null && $depMm !== null && $arrMm < $depMm) {
                $arrYear = $year + 1;
            }

            $cabin = 'economy';
            if (preg_match('/\b(ECONOMY|BUSINESS|FIRST)\b/i', $chunk, $cm)) {
                $cabin = strtolower($cm[1]);
            }

            $flights[] = [
                'flight_number'  => $flightNo,
                'airport_from'   => $depCode,
                'airport_to'     => $arrCode,
                'terminal_from'  => null,
                'terminal_to'    => null,
                'departure_time' => $this->isoDateTime($year, $depMm, $depDay, $depTime),
                'arrival_time'   => $this->isoDateTime($arrYear, $arrMm, $arrDay, $arrTime),
                'duration_time'  => null,
                'airline_name'   => 'Turkish Airlines',
                'class_type'     => $cabin,
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
            'airline_name'   => 'Turkish Airlines',
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
     * @return array{total: ?float, ccy: string}
     */
    private function extractTotal(): array
    {
        // Currency-first: "Total Fare: KWD 779.900" (the NBSP between ccy and
        // amount is folded to a space by normalizeText — match a real space,
        // NOT a wildcard, or we'd eat the amount's leading digit).
        if (preg_match('/Total Fare:\s*([A-Z]{3})\s+([\d,]+\.\d{1,3})/', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'ccy' => strtoupper($m[1])];
        }
        // Amount-first: "Total Fare: 676.30 KWD"
        if (preg_match('/Total Fare:\s*([\d,]+\.\d{1,3})\s*([A-Z]{3})/', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[1]), 'ccy' => strtoupper($m[2])];
        }
        return ['total' => null, 'ccy' => 'KWD'];
    }

    private function extractIssueDate(): ?string
    {
        // Prefer the ticketing date; fall back to the order creation date.
        if (preg_match('/Ticket Date\s*:?\s*(\d{1,2})\s+([A-Za-z]{3,9}),?\s*(\d{4})/', $this->text, $m)) {
            return $this->isoDate((int) $m[1], $m[2], (int) $m[3]);
        }
        if (preg_match('/Create Date\s*:?\s*(\d{1,2})\s+([A-Za-z]{3,9}),?\s*(\d{4})/', $this->text, $m)) {
            return $this->isoDate((int) $m[1], $m[2], (int) $m[3]);
        }
        return null;
    }

    private function extractVenue(array $flights): ?string
    {
        if (empty($flights)) {
            return null;
        }
        $last = end($flights);
        return $last['airport_to'] ?? null;
    }

    // ─────────────────────── email-body extractors ───────────────────────

    private function extractEmailPnr(): ?string
    {
        if (preg_match('/Reservation Code\s+([A-Z0-9]{5,7})\b/', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Pair each passenger with the ticket number that follows it. Names are the
     * uppercase line directly before "Date of Birth" in the passenger-detail
     * block; the ticket is the next 13-digit "Ticket Number" after that name.
     * @return array<int, array{name:string, type:string, ticket:?string, price:?float}>
     */
    private function extractEmailPassengers(): array
    {
        // Name sits on its OWN line directly before the "Date of Birth" block.
        // Confine the name to a single line ([A-Z ...], no newlines) — a pattern
        // that lets \s span newlines backtracks catastrophically over the ~60KB
        // body and trips PCRE's backtrack limit (→ zero matches).
        if (!preg_match_all('/\n([A-Z][A-Z .\'\-]{1,40}?)\n\s*Date of Birth/', $this->text, $nm, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $passengers = [];
        $seen = [];
        foreach ($nm[1] as $idx => $nameCap) {
            $name = $this->cleanName($nameCap[0]);
            $key  = strtoupper($name);
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $after  = substr($this->text, $nm[0][$idx][1]);
            $ticket = null;
            if (preg_match('/Ticket Number\s*\n?\s*(\d{13})/', $after, $tm)) {
                $ticket = $tm[1];
            }
            $seen[$key] = true;
            $passengers[] = ['name' => $name, 'type' => 'ADT', 'ticket' => $ticket, 'price' => null];
        }
        return $passengers;
    }

    /**
     * Best-effort flight segments from the email body: TK flight numbers paired
     * with the bare 3-letter airport-code sequence in the Flight Details block.
     * Returns [] if it can't pair confidently (task still loads, venue null).
     * @return array<int, array<string, mixed>>
     */
    private function extractEmailFlights(): array
    {
        $fStart = stripos($this->text, 'Flight Details');
        if ($fStart === false) {
            return [];
        }
        $pStart = stripos($this->text, 'Passengers Details');
        $region = substr($this->text, $fStart, ($pStart !== false ? $pStart - $fStart : null));

        preg_match_all('/\bTK(\d{2,4})\b/', $region, $fm);
        preg_match_all('/\b([A-Z]{3})\b/', $region, $cm);
        $flightNos = $fm[1] ?? [];

        // De-dupe consecutive airport codes, preserving order (GRU, IST, KWI).
        $codes = [];
        foreach ($cm[1] as $c) {
            if (empty($codes) || end($codes) !== $c) {
                $codes[] = $c;
            }
        }
        if (count($flightNos) < 1 || count($codes) < 2) {
            return [];
        }

        $flights = [];
        foreach ($flightNos as $i => $no) {
            $from = $codes[$i] ?? null;
            $to   = $codes[$i + 1] ?? null;
            if ($from === null || $to === null) {
                break;
            }
            $flights[] = [
                'flight_number'   => 'TK ' . $no,
                'airport_from'    => $from,
                'airport_to'      => $to,
                'terminal_from'   => null,
                'terminal_to'     => null,
                'departure_time'  => null,
                'arrival_time'    => null,
                'duration_time'   => null,
                'airline_name'    => 'Turkish Airlines',
                'class_type'      => 'economy',
                'baggage_allowed' => null,
                'equipment'       => null,
                'flight_meal'     => null,
                'seat_no'         => null,
                'farebase'        => null,
                'ticket_number'   => null,
            ];
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
     * Turkish prints passenger names hyphenated at PDF line-wrap boundaries
     * ("ALNOWAISH-ERI", "AL-SWAILEH"); the booking's own baggage section renders
     * them merged ("Alnowaisheri", "Alswaileh"). Strip a hyphen sitting directly
     * between two letters to recover the real surname.
     */
    private function cleanName(string $raw): string
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        $raw = preg_replace('/([A-Za-z])-([A-Za-z])/', '$1$2', $raw);
        return $raw;
    }

    /**
     * IATA e-ticket / Document Numbers are 13 digits = 3-digit airline code
     * (235 = Turkish) + 10-digit serial. CityTour stores the 10-digit serial.
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

    private function isoDateTime(int $year, ?int $month, int $day, string $time): ?string
    {
        if (!$month) {
            return null;
        }
        return sprintf('%04d-%02d-%02d %s:00', $year, $month, $day, $time);
    }

    private function buildAdditionalInfo(?string $issueDate, array $passengers, array $total): string
    {
        $bits = [];
        $bits[] = "Carrier: Turkish Airlines";
        if ($issueDate) $bits[] = "Issue date: " . substr($issueDate, 0, 10);
        if (count($passengers) > 1) $bits[] = "Passengers in booking: " . count($passengers);
        if ($total['total'] !== null) {
            $label = ($total['ccy'] ?? 'KWD') !== 'KWD' ? 'Original' : 'Total fare';
            $bits[] = "$label: " . $total['total'] . ' ' . $total['ccy'];
        }
        return implode(' | ', $bits);
    }
}
