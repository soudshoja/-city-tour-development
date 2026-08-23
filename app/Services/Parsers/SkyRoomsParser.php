<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Sky Rooms hotel invoices.
 *
 * Format markers:
 *  - "The sky rooms" branding line at the top
 *  - "Invoice #" + "Booking ID" + "Voucher ID"
 *  - Hotel name on a line of its own followed by address
 *  - "Check-in date / Check-out date / Total Nights / No. of Rooms"
 *  - "Travellers Details" lists pax (multi-line possible)
 *  - "Room Rate KWD <amount>"
 *  - "Gross Total KWD <amount>"
 */
class SkyRoomsParser
{
    public const SUPPLIER_NAME    = 'Sky Rooms';
    public const SUPPLIER_COUNTRY = 'Saudi Arabia';
    public const SIGNATURE_A      = 'The sky rooms';
    public const SIGNATURE_B      = 'theskyrooms.com';

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

    /**
     * Build from a body-only HTML email (Sky Rooms sends the voucher in the
     * email body, no PDF attachment — e.g. "Hotel Confirmation Number Addition"
     * mails like SRH49892). The HTML layout uses different labels than the PDF
     * invoice, so the extractors below handle BOTH.
     */
    public static function fromHtml(string $html): self
    {
        $instance = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->filePath = '';
        $instance->text  = self::htmlToText($html);
        $instance->lines = explode("\n", $instance->text);
        return $instance;
    }

    private static function htmlToText(string $html): string
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

    public static function matches(string $rawText): bool
    {
        return stripos($rawText, self::SIGNATURE_A) !== false
            || stripos($rawText, self::SIGNATURE_B) !== false;
    }

    public function parseTaskSchema(): array
    {
        $bookingId = $this->extractBookingId();
        if (!$bookingId) {
            throw new \Exception("Could not locate Sky Rooms booking id in PDF");
        }
        $holder    = $this->extractHolder();
        $hotel     = $this->extractHotel();
        $dates     = $this->extractDates();
        $total     = $this->extractTotal();
        $issued    = $this->extractIssueDate();
        $pax       = $this->extractPaxCount();
        $roomType  = $this->extractRoomType();
        $mealType  = $this->extractMealType();
        $city      = $this->extractCity();

        $task = [
            'additional_info'      => $this->buildAdditionalInfo($hotel, $dates, $pax, $total),
            'ticket_number'        => null,
            'gds_reference'        => $bookingId,
            'airline_reference'    => null,
            'status'               => 'issued',
            'supplier_status'      => 'issued',
            'refund_date'          => null,
            'void_date'            => null,
            'price'                => $total['total'],
            'currency'             => $total['currency'] ?? 'KWD',
            'exchange_currency'    => $total['currency'] ?? 'KWD',
            'original_price'       => ($total['currency'] ?? 'KWD') === 'KWD' ? null : $total['total'],
            'original_currency'    => ($total['currency'] ?? 'KWD') === 'KWD' ? null : $total['currency'],
            'total'                => $total['total'],
            'surcharge'            => 0,
            'penalty_fee'          => null,
            'tax'                  => 0,
            'taxes_record'         => null,
            'refund_charge'        => null,
            'reference'            => $bookingId,
            'original_ticket_number' => null,
            'original_reference'   => null,
            'created_by'           => null,
            'issued_by'            => null,
            'iata_number'          => null,
            'type'                 => 'hotel',
            'agent_name'           => null,
            'agent_email'          => null,
            'agent_amadeus_id'     => null,
            'client_name'          => $holder ?: 'UNKNOWN GUEST',
            'supplier_name'        => self::SUPPLIER_NAME,
            'supplier_country'     => self::SUPPLIER_COUNTRY,
            'cancellation_policy'  => 'Per Sky Rooms invoice terms.',
            'venue'                => $hotel,
            'issued_date'          => $issued ?: ($dates['check_in'] ?? null),
            'is_exchanged'         => false,
            'task_flight_details'  => [],
            // Hotel detail row consumed by TaskController::store() ->
            // saveHotelDetails(). Without it the detail page shows N/A for every
            // hotel field. Mirrors SmileHolidaysParser.
            'task_hotel_details'   => [
                'hotel_name'  => $hotel ?: ($city ?: 'UNKNOWN HOTEL'),
                'check_in'    => $dates['check_in'],
                'check_out'   => $dates['check_out'],
                'room_type'   => $roomType,
                'room_number' => null,
                'meal_type'   => $mealType,
                'city'        => $city,
                'adults'      => $pax,
            ],
        ];

        return [$task];
    }

    private function extractBookingId(): ?string
    {
        // Most reliable: "Booking ID <ID>"
        if (preg_match('/Booking ID[\s\t]+([A-Z0-9]{4,20})/i', $this->text, $m)) {
            return $m[1];
        }
        // HTML email layout: "Your Booking Ref No. is SRH49892."
        if (preg_match('/Booking Ref(?:erence)?\.?\s*No\.?\s*(?:is)?\s*[:\.]?\s*([A-Z]{2,4}\d{3,10})/i', $this->text, $m)) {
            return strtoupper($m[1]);
        }
        // Generic Sky Rooms ref token anywhere (SRH#####)
        if (preg_match('/\b(SRH\d{4,8})\b/i', $this->text, $m)) {
            return strtoupper($m[1]);
        }
        // Filename pattern fallback
        if (preg_match('/SRH(\d{4,8})/i', $this->filePath, $m)) {
            return 'SRH' . $m[1];
        }
        if (preg_match('/SKYR-(\d{10,15})/i', $this->filePath, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractHolder(): ?string
    {
        // Anchor on the pax block header: PDF "Travellers Details" OR the HTML
        // email's "Traveller & Room Details".
        $idx = null;
        foreach ($this->lines as $i => $ln) {
            if (stripos($ln, 'Traveller') !== false && stripos($ln, 'Detail') !== false) {
                $idx = $i;
                break;
            }
        }
        if ($idx !== null) {
            for ($j = $idx + 1; $j < min(count($this->lines), $idx + 10); $j++) {
                $t = trim($this->lines[$j]);
                if (preg_match('/^(Mr|Mrs|Ms|Miss|Dr|Mstr)\.?[ \t]+([A-Z][A-Za-z\'\-]+(?:[ \t]+[A-Z][A-Za-z\'\-]+)+)/i', $t, $m)) {
                    return trim(ucfirst(strtolower($m[1])) . ' ' . $m[2]);
                }
            }
        }
        // Fallback — any "Mr/Mrs ..." token. \b avoids matching the "ms" inside
        // "rooms" (which previously produced "ms Booked By").
        if (preg_match('/\b(Mr|Mrs|Ms|Miss|Dr|Mstr)\.?[ \t]+([A-Z][A-Za-z\'\-]+(?:[ \t]+[A-Z][A-Za-z\'\-]+)+)/', $this->text, $m)) {
            return trim($m[1] . ' ' . $m[2]);
        }
        return null;
    }

    private function extractHotel(): ?string
    {
        // HTML email layout: "Your stay at <Hotel>, <City> is" (and the hotel
        // name also appears on its own line under "Booking Details").
        if (preg_match('/Your stay at\s+(.+?)\s*,\s*[A-Za-z ]+?\s+is\b/i', $this->text, $m)) {
            return trim($m[1]);
        }
        // Hotel name often appears between the "To," / agency block and "Phone No."
        $startIdx = null;
        foreach ($this->lines as $i => $ln) {
            if (stripos($ln, 'ops@citytravelers.co') !== false) { $startIdx = $i; break; }
        }
        if ($startIdx !== null) {
            for ($j = $startIdx + 1; $j < min(count($this->lines), $startIdx + 10); $j++) {
                $t = trim($this->lines[$j]);
                if ($t === '' || stripos($t, 'Phone') !== false || stripos($t, 'Tel') !== false) continue;
                if (preg_match('/^[A-Z][A-Za-z][A-Za-z0-9 ,\-\.\']+$/', $t)) {
                    return $t;
                }
            }
        }
        return null;
    }

    /**
     * @return array{check_in: ?string, check_out: ?string, nights: ?int}
     */
    private function extractDates(): array
    {
        $checkIn  = null;
        $checkOut = null;
        $nights   = null;

        // "May 02, 2026 May 03, 2026 1 Night 1 Room"
        if (preg_match('/([A-Z][a-z]{2,8}\s+\d{1,2},\s+\d{4})\s+([A-Z][a-z]{2,8}\s+\d{1,2},\s+\d{4})/', $this->text, $m)) {
            $checkIn  = $this->normalizeDate($m[1]);
            $checkOut = $this->normalizeDate($m[2]);
        }
        if (preg_match('/(\d+)\s+Night/i', $this->text, $m)) {
            $nights = (int)$m[1];
        }
        return ['check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => $nights];
    }

    /**
     * @return array{total: ?float, currency: string}
     */
    private function extractTotal(): array
    {
        // "Gross Total KWD 60.96"
        if (preg_match('/Gross Total[\s\t]+([A-Z]{3})[\s\t]*([\d,.]+)/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[2]),
                'currency' => strtoupper($m[1]),
            ];
        }
        // HTML email layout: "Total Charge\nKWD\n44.13"
        if (preg_match('/Total Charge[\s\t]+([A-Z]{3})[\s\t]*([\d,.]+)/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[2]),
                'currency' => strtoupper($m[1]),
            ];
        }
        // "Room Rate KWD 60.96" fallback
        if (preg_match('/Room Rate[\s\t]+([A-Z]{3})[\s\t]*([\d,.]+)/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[2]),
                'currency' => strtoupper($m[1]),
            ];
        }
        return ['total' => null, 'currency' => 'KWD'];
    }

    private function extractIssueDate(): ?string
    {
        // "Invoice Date Sunday, Apr 19, 2026"
        if (preg_match('/Invoice Date[^\n]*?([A-Z][a-z]+,\s*[A-Z][a-z]{2,8}\s+\d{1,2},\s+\d{4})/i', $this->text, $m)) {
            return $this->normalizeDate($m[1]);
        }
        // HTML email layout: "Booked on : 08 Jun, 2026"
        if (preg_match('/Booked on\s*:?\s*(\d{1,2}\s+[A-Z][a-z]{2,8},?\s+\d{4})/i', $this->text, $m)) {
            return $this->normalizeDate($m[1]);
        }
        return null;
    }

    private function extractPaxCount(): ?int
    {
        // Count name lines after the pax block header (PDF "Travellers Details"
        // OR HTML "Traveller & Room Details").
        $startIdx = null;
        foreach ($this->lines as $i => $ln) {
            if (stripos($ln, 'Traveller') !== false && stripos($ln, 'Detail') !== false) { $startIdx = $i; break; }
        }
        if ($startIdx === null) return null;
        $count = 0;
        // Scan to the next section (Payment/Totals), skipping the many blank
        // lines the HTML email leaves between the header and the guest names.
        for ($j = $startIdx + 1; $j < count($this->lines); $j++) {
            $t = trim($this->lines[$j]);
            if ($t === '') continue;
            if (preg_match('/^(TOTAL|Payment|Room Rate|Gross|Total Charge)/i', $t)) {
                break;
            }
            if (preg_match('/^(Mr|Mrs|Ms|Miss|Dr|Mstr)\.?[ \t]+[A-Z]/i', $t)) {
                $count++;
            }
        }
        return $count > 0 ? $count : null;
    }

    /**
     * Room type. HTML layout: a room line under "Traveller & Room Details" like
     * "Double Room King Bed(Room Only)City Tax" (board in parens, junk after).
     * PDF layout: "Room Type: <value>" or the room line near the pax block.
     */
    private function extractRoomType(): ?string
    {
        // Explicit label (PDF)
        if (preg_match('/Room Type[\s\t:]+([^\n]+)/i', $this->text, $m)) {
            $rt = $this->cleanRoom($m[1]);
            if ($rt !== '') return $rt;
        }
        // HTML room line: room words then the board, separated either by parens
        // or " - ". Two real layouts:
        //   "Double Room King Bed(Room Only)City Tax"              (parens)
        //   "Signature Suite Skyline View - Bed And Breakfast : 2 Adult..."  (dash)
        // Anchored on a room keyword + a board keyword so it can't grab prose.
        $boards = 'Room Only|Bed (?:and|&) Breakfast|Breakfast|Half Board|Full Board|All Inclusive|Self Catering';
        if (preg_match('/^(.*?(?:Room|Suite|Studio|Villa|Apartment|Bed).*?)(?:\(\s*(?:' . $boards . ')|\s+-\s+(?:' . $boards . '))/im', $this->text, $m)) {
            $rt = $this->cleanRoom($m[1]);
            if ($rt !== '' && !preg_match('/^(?:' . $boards . ')$/i', $rt)) {
                return $rt;
            }
        }
        return null;
    }

    /** Strip the "(Board)" suffix and trailing junk ("City Tax", fees) from a room line. */
    private function cleanRoom(string $raw): string
    {
        $raw = preg_replace('/\(.*$/', '', $raw);        // drop "(Room Only)City Tax..."
        $raw = preg_replace('/\s*City Tax.*$/i', '', $raw);
        return trim(preg_replace('/\s+/', ' ', $raw));
    }

    /**
     * Meal plan / board. HTML: "(Room Only)" in the room line or a standalone
     * board line. Recognises the common board names/codes.
     */
    private function extractMealType(): ?string
    {
        $boards = 'Room Only|Bed (?:and|&) Breakfast|Breakfast|Half Board|Full Board|All Inclusive|Self Catering';
        if (preg_match('/\((' . $boards . ')\)/i', $this->text, $m)) {
            return ucwords(strtolower($m[1]));
        }
        if (preg_match('/\b(' . $boards . ')\b/i', $this->text, $m)) {
            return ucwords(strtolower($m[1]));
        }
        return null;
    }

    /** City — HTML "Your stay at <Hotel>, <City> is". */
    private function extractCity(): ?string
    {
        if (preg_match('/Your stay at\s+.+?,\s*([A-Za-z][A-Za-z ]+?)\s+is\b/i', $this->text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function normalizeDate(string $raw): ?string
    {
        // "May 02, 2026" or "Sunday, Apr 19, 2026"
        $raw = trim(preg_replace('/^[A-Z][a-z]+,\s*/i', '', $raw)); // strip leading weekday
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d 00:00:00', $ts);
        }
        return null;
    }

    private function buildAdditionalInfo(?string $hotel, array $dates, ?int $pax, array $total): string
    {
        $bits = [];
        if ($hotel) $bits[] = "Hotel: $hotel";
        if ($dates['check_in'] && $dates['check_out']) {
            $bits[] = "Stay: " . substr($dates['check_in'], 0, 10) . " -> " . substr($dates['check_out'], 0, 10);
        }
        if ($dates['nights']) $bits[] = "Nights: " . $dates['nights'];
        if ($pax) $bits[] = "Pax: $pax";
        return implode(' | ', $bits);
    }
}
