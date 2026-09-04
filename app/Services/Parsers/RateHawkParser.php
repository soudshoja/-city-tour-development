<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for RateHawk (Emerging Travel Group) air-travel booking
 * confirmations. Two shapes, both handled:
 *   - PDF "info-bill" attachment  (booking_information_<order>_en.pdf) — PRIMARY,
 *     carries every flight segment with airports/times/fare codes.
 *   - HTML email body             (the "[Air travel] Booking confirmation" email)
 *     — FALLBACK, only the route summary (one leg) + headline price.
 *
 * Sender: b2b@news.ratehawk.com / b2b@email.ratehawk.com.
 *
 * Returns the same shape as AirFileParser::parseTaskSchema() — an array of task
 * arrays, one per passenger. Foreign-currency totals (RateHawk bills in USD/EUR
 * etc.) are left in original_* so TaskController::store does the KWD conversion.
 */
class RateHawkParser
{
    public const SUPPLIER_NAME    = 'Rate Hawk';
    public const SUPPLIER_COUNTRY = 'Cyprus';

    private string $text;

    public function __construct(string $filePath = '')
    {
        if ($filePath !== '') {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            $parser = new PdfParser();
            $this->text = $parser->parseFile($filePath)->getText();
        } else {
            $this->text = '';
        }
    }

    /** Build from raw PDF/extracted text (used for tests + the file pipeline). */
    public static function fromText(string $text): self
    {
        $p = new self();
        $p->text = $text;
        return $p;
    }

    /** Build from a raw HTML email body (body-only ingestion path). */
    public static function fromHtml(string $html): self
    {
        $p = new self();
        $p->text = self::flattenHtml($html);
        return $p;
    }

    /**
     * Content fingerprint. Works for both the PDF text and the flattened HTML.
     * Requires the RateHawk brand AND a booking marker so generic marketing
     * mail never matches.
     */
    public static function matches(string $rawText): bool
    {
        $hasBrand = stripos($rawText, 'ratehawk') !== false;
        if (!$hasBrand) {
            return false;
        }
        return stripos($rawText, 'BOOKING NUMBER (PNR)') !== false    // PDF
            || stripos($rawText, "VENDOR'S LOCATOR") !== false        // PDF
            || stripos($rawText, 'Booking confirmation') !== false    // HTML
            || stripos($rawText, 'Booking code') !== false;           // HTML
    }

    public function parseTaskSchema(): array
    {
        // ISSUED-ONLY: RateHawk sends a "Booking confirmation" (unticketed, STATUS
        // "Booked", "TICKET № Not paid") and later a "Tickets have been issued"
        // receipt (STATUS "Issued", a real "TICKET № 217-…"). We only load the
        // ISSUED ticket — an unticketed booking is not a real cost yet. Returning
        // [] makes ProcessAirFiles archive the file with no task (no error).
        $ticketNumber = $this->extractTicketNumber();
        if (!$this->isIssued($ticketNumber)) {
            return [];
        }

        $order      = $this->extractOrder();
        $pnr        = $this->extractPnr();
        $passengers = $this->extractPassengers();
        $totalCcy   = $this->extractTotalAndCurrency();
        $status     = 'issued';
        $bookingDt  = $this->extractBookingDate();
        $airline    = $this->extractAirline();
        $flights    = $this->extractFlightDetails();

        if (empty($passengers)) {
            $passengers = ['UNKNOWN PASSENGER'];
        }
        $reference = $order ?: $pnr;
        if (!$reference) {
            throw new \Exception('Could not locate RateHawk order/PNR');
        }

        // RateHawk shows a single order total → split evenly per passenger.
        $perPax = ($totalCcy['total'] !== null && count($passengers) > 0)
            ? round($totalCcy['total'] / count($passengers), 3)
            : null;
        $isKwd = ($totalCcy['currency'] === 'KWD');

        $tasks = [];
        foreach ($passengers as $clientName) {
            $tasks[] = [
                'additional_info'        => $this->buildAdditionalInfo($order, $pnr, $airline, $bookingDt, $passengers, $totalCcy),
                'ticket_number'          => $ticketNumber,
                'gds_reference'          => $pnr,
                'airline_reference'      => $pnr,
                'status'                 => $status,
                'supplier_status'        => $this->rawStatus() ?: $status,
                'refund_date'            => null,
                'void_date'              => null,
                'price'                  => $isKwd ? $perPax : null,
                'currency'               => $totalCcy['currency'] ?? 'KWD',
                'exchange_currency'      => $totalCcy['currency'] ?? 'KWD',
                'original_price'         => $isKwd ? null : $perPax,
                'original_currency'      => $isKwd ? null : $totalCcy['currency'],
                'total'                  => $isKwd ? $perPax : null,
                'original_total'         => $isKwd ? null : $perPax,
                'surcharge'              => 0,
                'penalty_fee'            => null,
                'tax'                    => 0,
                'taxes_record'           => null,
                'refund_charge'          => null,
                'reference'              => $reference,
                'original_ticket_number' => null,
                'original_reference'     => null,
                'created_by'             => null,
                'issued_by'              => null,
                'iata_number'            => null,
                'type'                   => 'flight',
                'agent_name'             => null,
                'agent_email'            => null,
                'agent_amadeus_id'       => null,
                'client_name'            => $clientName,
                'supplier_name'          => self::SUPPLIER_NAME,
                'supplier_country'       => self::SUPPLIER_COUNTRY,
                'cancellation_policy'    => 'Per airline fare rules (RateHawk). Issue before the stated deadline or the booking auto-cancels.',
                'venue'                  => (!empty($flights) ? end($flights)['arrive_to'] : null),
                'issued_date'            => $bookingDt,
                'is_exchanged'           => false,
                'task_flight_details'    => $flights,
            ];
        }

        return $tasks;
    }

    // ──────────────────────────── extractors ────────────────────────────

    private function extractOrder(): ?string
    {
        if (preg_match('/ORDER\s*№?\s*(\d{5,})/iu', $this->text, $m)) return $m[1];
        if (preg_match('/Order\s*No\.?\s*(\d{5,})/i', $this->text, $m)) return $m[1];
        return null;
    }

    private function extractPnr(): ?string
    {
        // PDF: "BOOKING NUMBER (PNR)  X3CN9Q"  /  "VENDOR'S LOCATOR  X3CN9Q"
        if (preg_match('/BOOKING NUMBER \(PNR\)\s*([A-Z0-9]{5,7})/i', $this->text, $m)) return strtoupper($m[1]);
        if (preg_match("/VENDOR'S LOCATOR\s*([A-Z0-9]{5,7})/i", $this->text, $m)) return strtoupper($m[1]);
        // HTML: "Booking code  X3CN9Q" / "Airline booking code  X3CN9Q"
        if (preg_match('/(?:Airline )?Booking code\s*([A-Z0-9]{5,7})/i', $this->text, $m)) return strtoupper($m[1]);
        return null;
    }

    /**
     * PDF: "PASSENGER  EBRAHIM AHRARI  STATUS". Multi-pax repeat the block; we
     * also sweep "<NAME> (adt|chd|inf)" tokens as a fallback.
     */
    private function extractPassengers(): array
    {
        $out = [];
        if (preg_match('/PASSENGER\s+([A-Z][A-Z \'\-]+?)\s+STATUS/i', $this->text, $m)) {
            $out[] = $this->cleanName($m[1]);
        }
        // HTML: "Passenger  EBRAHIM AHRARI (adt)"
        if (preg_match_all('/Passenger\s+([A-Z][A-Z \'\-]+?)\s*\((?:adt|chd|inf)\)/i', $this->text, $mm)) {
            foreach ($mm[1] as $n) {
                $n = $this->cleanName($n);
                if ($n !== '' && !in_array($n, $out, true)) $out[] = $n;
            }
        }
        // Generic sweep: "EBRAHIM AHRARI (adt)" anywhere
        if (empty($out) && preg_match_all('/\b([A-Z][A-Z\'\-]+(?:\s+[A-Z\'\-]+){0,3})\s*\((?:adt|chd|inf)\)/', $this->text, $mm)) {
            foreach ($mm[1] as $n) {
                $n = $this->cleanName($n);
                if ($n !== '' && !in_array($n, $out, true)) $out[] = $n;
            }
        }
        return array_values(array_filter($out, fn($n) => $n !== '' && strtoupper($n) !== 'STATUS'));
    }

    private function cleanName(string $raw): string
    {
        return trim(preg_replace('/\s+/', ' ', strtoupper($raw)));
    }

    /** Real ticket number ("TICKET № 217-4816190497"); null on "Not paid". */
    private function extractTicketNumber(): ?string
    {
        if (preg_match('/TICKET\s*№?\s*([0-9][0-9\-]{6,})/iu', $this->text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** True only for an ISSUED ticket (has a ticket number, or STATUS "Issued"). */
    private function isIssued(?string $ticketNumber): bool
    {
        if ($ticketNumber !== null) {
            return true;
        }
        $raw = strtolower($this->rawStatus() ?? '');
        return str_contains($raw, 'issued') || str_contains($raw, 'ticketed');
    }

    /** @return array{total: ?float, currency: string} */
    private function extractTotalAndCurrency(): array
    {
        // PDF: "Total amount  645.68 $ 645.68 $"   HTML: "Payment amount  $645.68"
        if (preg_match('/(?:Total amount|Payment amount)\s*\$?\s*([\d., ]*\d)\s*(\$|€|£|USD|EUR|GBP|AED|KWD)?/i', $this->text, $m)) {
            $num = (float) str_replace([',', ' '], '', $m[1]);
            return ['total' => $num ?: null, 'currency' => $this->normaliseCurrency($m[2] ?? '$')];
        }
        if (preg_match('/(\$|€|£)\s*([\d.,]+)/', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => $this->normaliseCurrency($m[1])];
        }
        return ['total' => null, 'currency' => 'USD'];
    }

    private function normaliseCurrency(string $sym): string
    {
        $sym = trim($sym);
        return match (strtoupper($sym)) {
            '$', 'USD' => 'USD',
            '€', 'EUR' => 'EUR',
            '£', 'GBP' => 'GBP',
            'AED'      => 'AED',
            'KWD'      => 'KWD',
            default    => strtoupper($sym) ?: 'USD',
        };
    }

    private function rawStatus(): ?string
    {
        if (preg_match('/STATUS\s+([A-Za-z ]+?)\s+(?:TICKET|BOOKING|ID)\b/i', $this->text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractStatus(): string
    {
        $raw = strtolower($this->rawStatus() ?? '');
        return match (true) {
            str_contains($raw, 'cancel')           => 'cancelled',
            str_contains($raw, 'refund')           => 'refunded',
            str_contains($raw, 'void')             => 'void',
            str_contains($raw, 'ticketed'),
            str_contains($raw, 'issued')           => 'issued',
            default                                => 'issued',  // "Booked" → tracked as an issued flight task
        };
    }

    private function extractBookingDate(): ?string
    {
        // PDF: "ISSUE DATE  06.06.2026" (ticket) or "BOOKING DATE  06.06.2026"
        if (preg_match('/(?:ISSUE|BOOKING) DATE\s*(\d{1,2})\.(\d{1,2})\.(\d{4})/i', $this->text, $m)) {
            return sprintf('%04d-%02d-%02d 00:00:00', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        // Fallback: first departure date if present
        $f = $this->extractFlightDetails();
        if (!empty($f) && !empty($f[0]['departure_time'])) {
            return substr($f[0]['departure_time'], 0, 10) . ' 00:00:00';
        }
        return null;
    }

    private function extractAirline(): ?string
    {
        if (preg_match('/Operated by\s+([A-Z][A-Za-z &\-]+?)\s+(?:Airbus|Boeing|Embraer|ATR|Bombardier|Travel time)/', $this->text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/Airline\s+([A-Z][A-Za-z &\-]+?)\s+(?:booking|Route|Local)/', $this->text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Split the PDF body into per-flight segment blocks (delimited by
     * "XX-#### Operated by") and parse each. HTML body has no per-segment table,
     * so it falls through to a single route-summary leg.
     */
    private function extractFlightDetails(): array
    {
        $segments = [];

        if (preg_match_all('/\b([A-Z][A-Z0-9])\s*-\s*(\d{2,4})\s+Operated by\b/u', $this->text, $mm, PREG_OFFSET_CAPTURE)) {
            $starts = [];
            foreach ($mm[0] as $i => $hit) {
                $starts[] = ['pos' => $hit[1], 'code' => $mm[1][$i][0], 'num' => $mm[2][$i][0]];
            }
            // End the last block at "Payment information" / "Endorsements" if present
            $tail = strlen($this->text);
            foreach (['Payment information', 'Endorsements', 'Support'] as $marker) {
                $p = stripos($this->text, $marker, $starts[count($starts) - 1]['pos']);
                if ($p !== false) { $tail = min($tail, $p); }
            }
            foreach ($starts as $k => $seg) {
                $end = $starts[$k + 1]['pos'] ?? $tail;
                $block = substr($this->text, $seg['pos'], $end - $seg['pos']);
                $segments[] = $this->parseSegmentBlock($seg['code'], $seg['num'], $block);
            }
            return $segments;
        }

        // HTML route-summary fallback: "<City> <d Mon yyyy>, HH:MM ... <City> <d Mon yyyy>, HH:MM"
        if (preg_match_all('/([A-Z][a-z]+(?:\s[A-Z][a-z]+)?)\s+(\d{1,2}\s+[A-Z][a-z]+\s+\d{4}),\s*(\d{1,2}:\d{2})/', $this->text, $rm, PREG_SET_ORDER)) {
            if (count($rm) >= 2) {
                $dep = $rm[0]; $arr = $rm[count($rm) - 1];
                $segments[] = [
                    'flight_number'  => null,
                    'airport_from'   => null,
                    'airport_to'     => null,
                    'departure_from' => $dep[1],
                    'arrive_to'      => $arr[1],
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => $this->htmlDateTime($dep[2], $dep[3]),
                    'arrival_time'   => $this->htmlDateTime($arr[2], $arr[3]),
                    'duration_time'  => null,
                    'airline_name'   => $this->extractAirline() ?? self::SUPPLIER_NAME,
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

    private function parseSegmentBlock(string $code, string $num, string $block): array
    {
        // airports: first two "(XXX)"
        preg_match_all('/\(([A-Z]{3})\)/', $block, $ap);
        $from = $ap[1][0] ?? null;
        $to   = $ap[1][1] ?? null;

        // times: "15:45 15:45" doubled → collapse consecutive dupes, take [dep, arr]
        preg_match_all('/\b(\d{1,2}:\d{2})\b/', $block, $tm);
        $times = [];
        foreach ($tm[1] as $t) { if (end($times) !== $t) $times[] = $t; }
        $depTime = $times[0] ?? null;
        $arrTime = $times[1] ?? null;

        // dates: "9 Jun. 2026"
        preg_match_all('/(\d{1,2})\s+([A-Z][a-z]{2})\.?\s+(\d{4})/', $block, $dm, PREG_SET_ORDER);
        $depDate = isset($dm[0]) ? $this->monIso((int)$dm[0][1], $dm[0][2], (int)$dm[0][3]) : null;
        $arrDate = isset($dm[1]) ? $this->monIso((int)$dm[1][1], $dm[1][2], (int)$dm[1][3]) : ($depDate);

        // cities (text before "Airport (XXX)")
        preg_match_all('/([A-Z][A-Za-z]+(?:,\s*[A-Z][A-Za-z]+)?)\s+[A-Z][A-Za-z ]*Airport\s*\([A-Z]{3}\)/', $block, $cm);
        $cityFrom = $cm[1][0] ?? null;
        $cityTo   = $cm[1][1] ?? null;

        preg_match('/Terminal\s+(\S+)/i', $block, $tf);
        preg_match('/(Economy|Business|First|Premium)\s*\(([A-Z])\)/i', $block, $cl);
        preg_match('/Baggage:\s*([0-9]+\s*pcs)/i', $block, $bg);
        preg_match('/Fare code:\s*([A-Z0-9]+)/i', $block, $fb);
        preg_match('/Operated by[^\n]*?(Airbus|Boeing|Embraer|ATR|Bombardier)\s*([A-Z0-9\- ]+?)\s+Travel time/i', $block, $eq);

        return [
            'flight_number'  => "{$code}-{$num}",
            'airport_from'   => $from,
            'airport_to'     => $to,
            'departure_from' => $cityFrom,
            'arrive_to'      => $cityTo,
            'terminal_from'  => $tf[1] ?? null,
            'terminal_to'    => null,
            'departure_time' => $this->joinDt($depDate, $depTime),
            'arrival_time'   => $this->joinDt($arrDate, $arrTime),
            'duration_time'  => null,
            'airline_name'   => $this->extractAirline() ?? self::SUPPLIER_NAME,
            'class_type'     => isset($cl[1]) ? strtolower($cl[1]) : 'economy',
            'baggage_allowed'=> $bg[1] ?? null,
            'equipment'      => isset($eq[1]) ? trim($eq[1] . ' ' . ($eq[2] ?? '')) : null,
            'flight_meal'    => null,
            'seat_no'        => null,
            'farebase'       => $fb[1] ?? null,
            'ticket_number'  => null,
        ];
    }

    private function joinDt(?string $isoDate, ?string $time): ?string
    {
        if (!$isoDate) return null;
        return $time ? "{$isoDate} {$time}:00" : "{$isoDate} 00:00:00";
    }

    private function htmlDateTime(string $dateStr, string $time): ?string
    {
        if (preg_match('/(\d{1,2})\s+([A-Z][a-z]+)\s+(\d{4})/', $dateStr, $m)) {
            $iso = $this->monIso((int)$m[1], substr($m[2], 0, 3), (int)$m[3]);
            return $iso ? "{$iso} {$time}:00" : null;
        }
        return null;
    }

    private function monIso(int $day, string $mon, int $year): ?string
    {
        $map = ['Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
                'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'];
        $mm = $map[ucfirst(strtolower(substr($mon, 0, 3)))] ?? null;
        return $mm ? sprintf('%04d-%s-%02d', $year, $mm, $day) : null;
    }

    private function buildAdditionalInfo(?string $order, ?string $pnr, ?string $airline, ?string $bookingDt, array $pax, array $totalCcy): string
    {
        $bits = [];
        if ($order)   $bits[] = "RateHawk order: {$order}";
        if ($pnr)     $bits[] = "PNR: {$pnr}";
        if ($airline) $bits[] = "Airline: {$airline}";
        if ($bookingDt) $bits[] = "Booking date: " . substr($bookingDt, 0, 10);
        if (count($pax) > 1) $bits[] = "Passengers: " . count($pax);
        if ($totalCcy['currency'] !== 'KWD' && $totalCcy['total'] !== null) {
            $bits[] = "Order total: {$totalCcy['total']} {$totalCcy['currency']}";
        }
        return implode(' | ', $bits);
    }

    // ──────────────────────────── html flatten ────────────────────────────

    private static function flattenHtml(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|tr|li|h[1-6]|td|th)>#i', "\n", $html);
        $html = preg_replace('#<(p|div|tr|li|h[1-6])[^>]*>#i', "\n", $html);
        $html = preg_replace('#<(td|th)[^>]*>#i', "\t", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);
        return trim($text);
    }
}
