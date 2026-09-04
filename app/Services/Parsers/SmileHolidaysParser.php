<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Smile Holidays hotel vouchers and invoices.
 *
 * Three formats seen in production:
 *  A) Invoice-only           — header "Smile Holidays Kuwait" + "Booking code <CODE>"
 *  B) Voucher (HOTEL COUPON) — header "Booking Ref.:" + "Voucher HOTEL COUPON"
 *  C) Legacy INVOICE         — header "A Product of Smile Holidays INVOICE"
 *
 * Many PDFs are 2-page combos (voucher page + invoice page). The parser extracts
 * the booking code, holder, hotel, dates, and totals regardless of which format
 * the PDF leads with.
 *
 * Returns the same shape as AirFileParser::parseTaskSchema() — array of task
 * arrays (always 1 task per booking; holder is the named pax).
 */
class SmileHolidaysParser
{
    public const SUPPLIER_NAME    = 'Smile Holidays';
    public const SUPPLIER_COUNTRY = 'Kuwait';
    public const SIGNATURE_A      = 'Smile Holidays';
    public const SIGNATURE_B      = 'A Product of Smile Holidays';

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
        $this->text = self::normalizeText($parser->parseFile($filePath)->getText());
        $this->lines = explode("\n", $this->text);
    }

    /**
     * Booking-request PDFs come from an HTML email and embed non-breaking and
     * other Unicode spaces (U+00A0 etc.) for column alignment. PCRE `\s` and the
     * `[ \t]` classes don't match those, which silently breaks label regexes
     * (e.g. "Departure Date\xA0:" or "Room Type:\xA0\xA0\xA0value"). Fold them to
     * plain spaces once, up front, so every extractor sees ordinary whitespace.
     */
    private static function normalizeText(string $t): string
    {
        return str_replace(
            ["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x89", "\xE2\x80\x82", "\xE2\x80\x83"],
            ' ',
            $t
        );
    }

    public static function matches(string $rawText): bool
    {
        return stripos($rawText, self::SIGNATURE_A) !== false
            || stripos($rawText, self::SIGNATURE_B) !== false
            || stripos($rawText, 'Smileholidays') !== false        // one-word variant
            || stripos($rawText, 'smileholidays.info') !== false   // booking-request email
            // Brand-less hotel coupon variant (agency-header "City Travelers"
            // vouchers like LCXTZ2 that carry no "Smile" text anywhere). "HOTEL
            // COUPON" is distinct from Magic Holidays' "HOTEL VOUCHER" and from
            // SkyRooms' brand signatures, so pairing it with "Booking Ref" is a
            // safe Smile-voucher fingerprint that won't collide with them.
            || (stripos($rawText, 'HOTEL COUPON') !== false
                && stripos($rawText, 'Booking Ref') !== false);
    }

    public function parseTaskSchema(): array
    {
        $bookingCode = $this->extractBookingCode();
        if (!$bookingCode) {
            throw new \Exception("Could not locate Smile Holidays booking code in PDF");
        }
        $holder      = $this->extractHolder();
        $hotel       = $this->extractHotel();
        $dates       = $this->extractDates();      // [check_in, check_out, nights]
        $totals      = $this->extractTotal();      // [total, currency, subtotal, taxes]
        // Voucher-only coupons (HOTEL COUPON) carry no pricing -- the amount lives
        // on the separate invoice page. NEVER emit a null price: transactions.amount
        // is NOT NULL, so a null silently sends the file to files_error. Default to 0
        // so the task loads and the cost can be filled in manually.
        if ($totals['total'] === null) {
            $totals['total'] = 0.0;
        }
        // `price` must be tax-EXCLUSIVE. Downstream (the Edit-Task form's JS and
        // ReportController's debit calc) compute total = price + tax + surcharge;
        // if price already includes the tax AND `tax` is populated, the tax is
        // double-counted (9963CR: 253.80 grand total became 311.60 on first edit).
        // When the invoice prints a "SUBTOTAL (without taxes)" line, use it as the
        // base price; otherwise the grand total is the only figure we have.
        $basePrice = ($totals['subtotal'] !== null && ($totals['taxes'] ?? null) !== null)
            ? $totals['subtotal']
            : $totals['total'];
        $bookingDate = $this->extractBookingDate();
        $pax         = $this->extractPaxCount();
        $location    = $this->extractLocation();   // city/country
        $roomType    = $this->extractRoomType();
        $mealType    = $this->extractMealType();

        // Issued = the document's own date. When absent (factura invoices carry no
        // issue date) use TODAY — files are parsed minutes after Smile sends them —
        // never the stay check-in (a future travel date is not an issue date).
        $issuedDate = $bookingDate ?: now()->format('Y-m-d 00:00:00');

        $task = [
            'additional_info'      => $this->buildAdditionalInfo($hotel, $dates, $pax, $totals, $location),
            'ticket_number'        => null,
            'gds_reference'        => $bookingCode,
            'airline_reference'    => null,
            'status'               => 'issued',
            'supplier_status'      => 'issued',
            'refund_date'          => null,
            'void_date'            => null,
            'price'                => $basePrice,
            'currency'             => $totals['currency'] ?? 'KWD',
            'exchange_currency'    => $totals['currency'] ?? 'KWD',
            'original_price'       => ($totals['currency'] ?? 'KWD') === 'KWD' ? null : $basePrice,
            'original_currency'    => ($totals['currency'] ?? 'KWD') === 'KWD' ? null : $totals['currency'],
            'total'                => $totals['total'],
            'surcharge'            => 0,
            'penalty_fee'          => null,
            'tax'                  => $totals['taxes'] ?? 0,
            'taxes_record'         => null,
            'refund_charge'        => null,
            'reference'            => $bookingCode,
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
            'cancellation_policy'  => 'Per Smile Holidays voucher terms.',
            'venue'                => $hotel ?: $location,
            'issued_date'          => $issuedDate,
            'is_exchanged'         => false,
            'task_flight_details'  => [],
            // Hotel detail row consumed by TaskController::store() -> saveHotelDetails().
            // Without this, hotel tasks have no task_hotel_details and the detail
            // page renders every hotel field as N/A (and historically crashed).
            'task_hotel_details'   => [
                'hotel_name'  => $hotel ?: ($location ?: 'UNKNOWN HOTEL'),
                'check_in'    => $dates['check_in'],
                'check_out'   => $dates['check_out'],
                'room_type'   => $roomType,
                'room_number' => null,
                'meal_type'   => $mealType,
                'city'        => $location,
                'adults'      => $pax,
            ],
        ];

        return [$task];
    }

    // ──────────────────────────── extractors ────────────────────────────

    private function extractBookingCode(): ?string
    {
        // Format D (Booking Request email): "...Ref. No. ('<CODE>')" or "Booking Reference:<CODE>"
        if (preg_match("/Ref\.?\s*No\.?\s*\(\'?([A-Z0-9]{4,20})\'?\)/i", $this->text, $m)) {
            return $m[1];
        }
        if (preg_match('/Booking Reference[ \t]*:?[ \t]*([A-Z0-9]{4,20})/i', $this->text, $m)) {
            return $m[1];
        }
        // Format A: "Booking code<CODE>" or "Booking code <CODE>" — smalot
        // often drops the space, so use \s* and exclude the column header word
        // "Description" that follows the second occurrence.
        if (preg_match_all('/Booking code\s*([A-Z0-9]{4,20})/', $this->text, $matches)) {
            foreach ($matches[1] as $cand) {
                // Skip the column-header phrase that begins "Description"
                if (strcasecmp($cand, 'Description') === 0) continue;
                return $cand;
            }
        }
        // Format C alt: "REF: <CODE>" inside the TRAVEL AGREEMENTS block —
        // matched BEFORE the "Booking Ref:" label-only fallback so we don't
        // accidentally grab "Agent" or other words that follow the label.
        if (preg_match('/REF:[ \t]+([A-Z0-9]{8,20})/', $this->text, $m)) {
            return $m[1];
        }
        // Format B: "Booking Ref.:\n<CODE>" (label-only line, value below)
        for ($i = 0; $i < count($this->lines) - 1; $i++) {
            if (preg_match('/Booking Ref\.?:?\s*$/i', trim($this->lines[$i]))) {
                // walk forward up to 10 lines looking for a pure alphanumeric token
                for ($j = $i + 1; $j < min(count($this->lines), $i + 10); $j++) {
                    $next = trim($this->lines[$j]);
                    if (preg_match('/^([A-Z0-9]{4,20})$/', $next, $mm)) {
                        return $mm[1];
                    }
                }
            }
        }
        // Format C: "Booking Ref: <CODE>" — use [ \t] not \s to prevent newline
        // crossing (otherwise we'd capture "Agent" from "Booking Ref:\nAgent")
        if (preg_match('/Booking Ref\.?:?[ \t]+([A-Z0-9]{4,20})/i', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractHolder(): ?string
    {
        // 0) Booking Request format: "Name:<First>" + "Family Name:<Last>"
        //    (negative lookbehind so the first match isn't "Family Name").
        if (preg_match('/(?<!Family )\bName[ \t]*:[ \t]*([^\n]+)/i', $this->text, $fm)) {
            $first = $this->cleanName($fm[1]);
            if ($first !== '' && stripos($first, 'family') === false) {
                if (preg_match('/Family Name[ \t]*:[ \t]*([^\n]+)/i', $this->text, $lm)) {
                    return trim($first . ' ' . $this->cleanName($lm[1]));
                }
            }
        }
        // 1) "Holder Name:    <Name>" (single line, may have tabs/spaces)
        if (preg_match('/Holder Name[ \t]*:[ \t]*([^\n]+)/i', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        // 1b) Multi-line variant: "Holder Name\n:    <Name>"
        if (preg_match('/Holder Name\s*\n[ \t]*:[ \t]*([^\n]+)/i', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        // 2a) Table row, holder starts with ALL-CAPS word ≥4 chars (Arab convention).
        //     Hotel can be anything in between; this anchors holder to the proper boundary.
        //     e.g. "B84481 Waldorf Astoria Kuwait ABDULLAH ALHAIFI 2 5/17/2026"
        if (preg_match(
            '/^[A-Z0-9]{4,20}\s+.+?\s+([A-Z]{4,}(?:\s+[A-Z][A-Z\.]{0,15})*)\s*(?:\d{1,3}\s+)?\d{1,2}\/\d{1,2}\/\d{4}/m',
            $this->text,
            $m
        )) {
            return $this->cleanName($m[1]);
        }
        // 2b) Same table row but holder is exactly 2 title-case words ("Aziz Almutairi"),
        //     name may abut pax digit with no space.
        if (preg_match(
            '/^[A-Z0-9]{4,20}\s+.+?\s+([A-Z][a-z]+\s+[A-Z][a-z]+)\s*(?:\d{1,3}\s+)?\d{1,2}\/\d{1,2}\/\d{4}/m',
            $this->text,
            $m
        )) {
            return $this->cleanName($m[1]);
        }
        // 2c) Split-table Format A variant (HIQ377108): the Booking-code column
        //     breaks onto its own line, so the description row has NO leading code:
        //     "Hotel Farah Tanger  ATHARI ALHARBAN 2 7/20/2026 - 7/22/2026  253.02..."
        //     Holder is the trailing ALL-CAPS run right before pax + date range.
        if (preg_match(
            '/^(.+?)\s+([A-Z]{4,}(?:\s+[A-Z][A-Z\.]{0,15})*)\s+\d{1,3}\s+\d{1,2}\/\d{1,2}\/\d{4}\s*-\s*\d{1,2}\/\d{1,2}\/\d{4}/m',
            $this->text,
            $m
        )) {
            return $this->cleanName($m[2]);
        }
        // 3) "CLIENT: <Name>" (legacy)
        if (preg_match('/CLIENT:\s*(.+?)(?:\n|$)/i', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        // 4) Adult line: "ADULT 1 - <Name>"
        if (preg_match('/ADULT\s+1\s+-\s+(.+?)(?:\n|$)/i', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        return null;
    }

    private function extractHotel(): ?string
    {
        // 1) "Hotel: <name>" (legacy). [ \t] not \s after the colon: in the
        //    column-flattened invoice layout "Hotel:" is a label-only line and \s*
        //    would cross the newline and capture the NEXT label ("Check In / ...").
        if (preg_match('/Hotel:[ \t]*(.+?)(?:\s+\d{1,2}-[A-Za-z]{3}-\d{4}|\n|$)/i', $this->text, $m)
            && trim($m[1]) !== '') {
            return trim($m[1]);
        }
        // 2) "HOTEL COUPON\n<Hotel Name> <stars> Stars"
        if (preg_match('/HOTEL COUPON\s*\n(.+?)(?:\s+\d\s+Stars?|\n)/i', $this->text, $m)) {
            return trim($m[1]);
        }
        // 3a) Table row, holder anchored on ALL-CAPS word ≥4 chars — hotel is the
        //     chunk before it (e.g. "Waldorf Astoria Kuwait")
        if (preg_match(
            '/^[A-Z0-9]{4,20}\s+(.+?)\s+[A-Z]{4,}(?:\s+[A-Z][A-Z\.]{0,15})*\s*(?:\d{1,3}\s+)?\d{1,2}\/\d{1,2}\/\d{4}/m',
            $this->text,
            $m
        )) {
            return trim($m[1]);
        }
        // 3b) Same with title-case 2-word holder
        if (preg_match(
            '/^[A-Z0-9]{4,20}\s+(.+?)\s+[A-Z][a-z]+\s+[A-Z][a-z]+\s*(?:\d{1,3}\s+)?\d{1,2}\/\d{1,2}\/\d{4}/m',
            $this->text,
            $m
        )) {
            return trim($m[1]);
        }
        // 3c) Split-table Format A variant (HIQ377108): row has no leading booking
        //     code — hotel is everything before the ALL-CAPS holder + pax + range.
        if (preg_match(
            '/^(.+?)\s+[A-Z]{4,}(?:\s+[A-Z][A-Z\.]{0,15})*\s+\d{1,3}\s+\d{1,2}\/\d{1,2}\/\d{4}\s*-\s*\d{1,2}\/\d{1,2}\/\d{4}/m',
            $this->text,
            $m
        )) {
            $h = trim($m[1]);
            if ($h !== '' && stripos($h, 'Description') === false) return $h;
        }
        // 4) Column-flattened invoice description block: "<Hotel Name>\n(City, Country)"
        if (preg_match('/^([^\n(][^\n]*?)\s*\n\(([^,)]+),\s*[^)]+\)/m', $this->text, $m)) {
            $h = trim($m[1]);
            if ($h !== '' && !preg_match('/^(REF|CLIENT|PERSONS)\b/i', $h)) return $h;
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

        // 1) DD/MM/YYYY format (voucher style)
        if (preg_match('/Arrival\s*Date\s*:\s*(\d{2}\/\d{2}\/\d{4})/i', $this->text, $m)) {
            $checkIn = $this->normalizeDate($m[1]);
        }
        if (preg_match('/Departure\s*Date\s*:\s*(\d{2}\/\d{2}\/\d{4})/i', $this->text, $m)) {
            $checkOut = $this->normalizeDate($m[1]);
        }

        // 2) DD-Mon-YYYY format (legacy invoice)
        if (!$checkIn && preg_match('/Check In(?:\s*\/\s*Supply Date)?:\s*(\d{1,2}-[A-Za-z]{3}-\d{4})/i', $this->text, $m)) {
            $checkIn = $this->normalizeDate($m[1]);
        }
        if (!$checkOut && preg_match('/Check Out:\s*(\d{1,2}-[A-Za-z]{3}-\d{4})/i', $this->text, $m)) {
            $checkOut = $this->normalizeDate($m[1]);
        }

        // 3) Invoice table date range "M/D/YYYY - M/D/YYYY" — the factura invoices
        //    are AMERICAN month/day (e.g. "8/11/2026 - 8/13/2026" = 11-13 Aug).
        //    Cross-check: if the MDY reading yields a reversed range, fall back to DMY.
        if (!$checkIn && preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4})/', $this->text, $m)) {
            $checkIn  = $this->normalizeDate($m[1], 'MDY');
            $checkOut = $this->normalizeDate($m[2], 'MDY');
            if ($checkIn && $checkOut && $checkOut < $checkIn) {
                $ci2 = $this->normalizeDate($m[1], 'DMY');
                $co2 = $this->normalizeDate($m[2], 'DMY');
                if ($ci2 && $co2 && $co2 >= $ci2) {
                    $checkIn  = $ci2;
                    $checkOut = $co2;
                }
            }
        }

        // 4) Column-flattened Format C: labels and values sit in separate blocks,
        //    so the check-in/check-out dates appear as two consecutive DD-Mon-YYYY
        //    value lines ("20-Jul-2026\n22-Jul-2026").
        if (!$checkIn && preg_match('/^(\d{1,2}-[A-Za-z]{3}-\d{4})\s*\n(\d{1,2}-[A-Za-z]{3}-\d{4})$/m', $this->text, $m)) {
            $checkIn  = $this->normalizeDate($m[1]);
            $checkOut = $this->normalizeDate($m[2]);
        }

        // Nights count
        if (preg_match('/Nights?:\s*(\d{1,3})/i', $this->text, $m)) {
            $nights = (int)$m[1];
        } elseif (preg_match('/No\.\s*Of\s*Night\(s\):\s*(\d{1,3})/i', $this->text, $m)) {
            $nights = (int)$m[1];
        } elseif (preg_match('/(\d{1,3})\s+Night\(s\)/i', $this->text, $m)) {
            $nights = (int)$m[1];
        }

        return ['check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => $nights];
    }

    /**
     * @return array{total: ?float, currency: string, subtotal: ?float, taxes: ?float}
     */
    private function extractTotal(): array
    {
        // 1) "Total <amount>.<dec><CURRENCY>" anchored at line start with decimal
        //    amount and word-boundary currency (prevents "Total\n8TQC1Y" matching
        //    as Total=8 currency=TQC).
        if (preg_match('/^Total[ \t]+([\d,]+\.\d{1,3})[ \t]*([A-Z]{3})\b/m', $this->text, $m)) {
            $total = (float) str_replace(',', '', $m[1]);
            $ccy = strtoupper($m[2]);
            $subtotal = null;
            $taxes = null;
            if (preg_match('/SUBTOTAL\s+\(without taxes\)[\s\t]+([\d,]+\.\d{1,3})/i', $this->text, $sm)) {
                $subtotal = (float) str_replace(',', '', $sm[1]);
            }
            if (preg_match('/^Taxes[ \t]+([\d,]+\.\d{1,3})[ \t]*[A-Z]{3}\b/m', $this->text, $tm)) {
                $taxes = (float) str_replace(',', '', $tm[1]);
            }
            return ['total' => $total, 'currency' => $ccy, 'subtotal' => $subtotal, 'taxes' => $taxes];
        }
        // 2) "Total Amount <amount>" (legacy — assumes KWD)
        if (preg_match('/Total Amount[ \t]+([\d,]+\.\d{1,3})/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[1]),
                'currency' => 'KWD',
                'subtotal' => null,
                'taxes'    => null,
            ];
        }
        // 3) Booking-request style: "Total 359.60KWD" / "Price: 359.60KWD"
        //    (currency glued to the amount, tolerant of label/colon/spacing).
        if (preg_match('/(?:Total|Price)\s*:?\s*([\d,]+\.\d{1,3})\s*([A-Z]{3})/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[1]),
                'currency' => strtoupper($m[2]),
                'subtotal' => null,
                'taxes'    => null,
            ];
        }
        return ['total' => null, 'currency' => 'KWD', 'subtotal' => null, 'taxes' => null];
    }

    private function extractBookingDate(): ?string
    {
        // 0) Booking Request: "Booking Creation Date: DD/MM/YYYY"
        if (preg_match('/Booking Creation Date[ \t]*:[ \t]*(\d{2}\/\d{2}\/\d{4})/i', $this->text, $m)) {
            return $this->normalizeDate($m[1]);
        }
        // 1) "Booking Date: DD-Mon-YYYY"
        if (preg_match('/Booking Date:\s*(\d{1,2}-[A-Za-z]{3}-\d{4})/i', $this->text, $m)) {
            return $this->normalizeDate($m[1]);
        }
        // 2) "Voucher Date: DD-Mon-YYYY"
        if (preg_match('/Voucher Date:\s*(\d{1,2}-[A-Za-z]{3}-\d{4})/i', $this->text, $m)) {
            return $this->normalizeDate($m[1]);
        }
        // 3) "Date: DD/MM/YYYY HH:MM:SS" (voucher header)
        if (preg_match('/^Date:\s*$\n(\d{2}\/\d{2}\/\d{4})/im', $this->text, $m)) {
            return $this->normalizeDate($m[1]);
        }
        // 3b) Flattened layouts detach the "Date:" label from its value — the issue
        // timestamp is the only DD/MM/YYYY HH:MM:SS datetime anywhere in the text
        // (arrival/departure dates carry no time part).
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+\d{2}:\d{2}:\d{2}/', $this->text, $m)) {
            return $this->normalizeDate($m[1]);
        }
        // 4) Two-line "Date:\n<datetime>"
        for ($i = 0; $i < count($this->lines) - 1; $i++) {
            if (preg_match('/^Date:?\s*$/i', trim($this->lines[$i]))) {
                $next = trim($this->lines[$i + 1]);
                if (preg_match('/^(\d{2}\/\d{2}\/\d{4})/', $next, $m)) {
                    return $this->normalizeDate($m[1]);
                }
            }
        }
        return null;
    }

    private function extractPaxCount(): ?int
    {
        if (preg_match('/\(\s*(\d+)\s+Adults?\s*\)/i', $this->text, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/Passengers:\s*(\d+)/i', $this->text, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/PERSONS\s*:\s*(\d+)/i', $this->text, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function extractLocation(): ?string
    {
        // "Destination: <City>". [ \t] not \s after the colon: in the flattened
        // invoice layout "Destination:" is a label-only line and \s* would cross
        // the newline and capture the first VALUE line (the hotel name).
        if (preg_match('/Destination:[ \t]*(.+?)(?:\n|$)/i', $this->text, $m)
            && trim($m[1]) !== '') {
            return trim($m[1]);
        }
        // "(City, Country)" after hotel name
        if (preg_match('/\(([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*),\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\)/', $this->text, $m)) {
            return $m[1] . ', ' . $m[2];
        }
        return null;
    }

    /**
     * Room type / room name. Handles the three hotel formats:
     *  D) Booking-request : "Room Type:   1 x DOUBLE WITH BALCONY"
     *  B) Voucher coupon  : "Rooms: 1 King Bed (ROOM ONLY) ( 2 Adults )"
     *  C) Invoice page-2  : "RoomType / Board\nDeluxe Room Bed and Breakfast ..."
     */
    private function extractRoomType(): ?string
    {
        // D) Booking-request explicit label
        if (preg_match('/Room Type[ \t]*:[ \t]*([^\n]+)/i', $this->text, $m)) {
            // drop a leading "N x " quantity prefix -> "1 x DOUBLE..." => "DOUBLE..."
            $rt = preg_replace('/^\d+\s*x\s*/i', '', $this->cleanName($m[1]));
            if ($rt !== '') return $rt;
        }
        // B) Voucher "Rooms: <room> (BOARD) ( N Adults )" — room is the chunk before "("
        if (preg_match('/Rooms?[ \t]*:[ \t]*(.+?)\s*\(/i', $this->text, $m)) {
            $rt = $this->cleanName($m[1]);
            if ($rt !== '') return $rt;
        }
        // B alt) "Room name\n<room>"
        if (preg_match('/Room name\s*\n[ \t]*([^\n]+)/i', $this->text, $m)) {
            $rt = $this->cleanName($m[1]);
            if ($rt !== '') return $rt;
        }
        // C) Invoice page-2 "RoomType / Board" block (first line below the header)
        if (preg_match('/RoomType\s*\/\s*Board\s*\n[ \t]*([^\n]+)/i', $this->text, $m)) {
            $rt = $this->cleanName($m[1]);
            if ($rt !== '') return $rt;
        }
        return null;
    }

    /**
     * Meal plan / board. Handles:
     *  D) Booking-request : "Meal Plan:   ROOM ONLY"
     *  B) Voucher coupon  : "Board: ROOM ONLY"
     */
    private function extractMealType(): ?string
    {
        // D) Booking-request explicit label
        if (preg_match('/Meal Plan[ \t]*:[ \t]*([^\n]+)/i', $this->text, $m)) {
            $mt = $this->cleanName($m[1]);
            if ($mt !== '') return $mt;
        }
        // B) Voucher "Board: ROOM ONLY" (line-anchored so we don't grab "board" in prose)
        if (preg_match('/^Board[ \t]*:[ \t]*([^\n]+)/im', $this->text, $m)) {
            $mt = $this->cleanName($m[1]);
            if ($mt !== '') return $mt;
        }
        return null;
    }

    // ──────────────────────────── helpers ────────────────────────────

    private function normalizeDate(string $raw, string $assume = 'DMY'): ?string
    {
        $raw = trim($raw);
        // X/Y/YYYY — order depends on context. Smile invoice uses M/D/YYYY ("5/17/2026"),
        // voucher uses D/M/YYYY ("07/05/2026"). Use the larger-than-12 hint to
        // disambiguate when possible, else fall back to caller-supplied $assume.
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
            $a = (int)$m[1]; $b = (int)$m[2]; $y = (int)$m[3];
            if ($a > 12)      { $day = $a; $month = $b; }   // DD/MM
            elseif ($b > 12)  { $day = $b; $month = $a; }   // MM/DD
            elseif ($assume === 'MDY') { $day = $b; $month = $a; }
            else              { $day = $a; $month = $b; }   // default DMY
            return sprintf('%04d-%02d-%02d 00:00:00', $y, $month, $day);
        }
        // DD-Mon-YYYY
        if (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/', $raw, $m)) {
            return $this->toIsoDate((int)$m[1], $m[2], (int)$m[3]);
        }
        // YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) {
            return "$raw 00:00:00";
        }
        return null;
    }

    private function toIsoDate(int $day, string $month, int $year): string
    {
        $months = ['Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
                   'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'];
        $mm = $months[ucfirst(strtolower($month))] ?? '01';
        return sprintf('%04d-%s-%02d 00:00:00', $year, $mm, $day);
    }

    private function cleanName(string $raw): string
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        // Strip trailing "(Holder)" tag
        $raw = preg_replace('/\s*\(Holder\)\s*$/i', '', $raw);
        // Strip "Mr." / "Mr " prefix duplication (we keep first encountered title)
        return $raw;
    }

    private function buildAdditionalInfo(?string $hotel, array $dates, ?int $pax, array $totals, ?string $location): string
    {
        $bits = [];
        if ($hotel) $bits[] = "Hotel: $hotel";
        if ($location) $bits[] = "Location: $location";
        if ($dates['check_in'] && $dates['check_out']) {
            $bits[] = "Stay: " . substr($dates['check_in'], 0, 10) . " -> " . substr($dates['check_out'], 0, 10);
        }
        if ($dates['nights']) $bits[] = "Nights: " . $dates['nights'];
        if ($pax) $bits[] = "Pax: $pax";
        if (($totals['currency'] ?? 'KWD') !== 'KWD' && $totals['total'] !== null) {
            $bits[] = "Original price: " . $totals['total'] . ' ' . $totals['currency'];
        }
        return implode(' | ', $bits);
    }
}
