<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for GTS Beds (GTS - Kuwait Branch, gtsbeds.com) HOTEL
 * bookings, emailed from no-reply@gtsbeds.com as separate PDFs per booking:
 *
 *   - VOUCHER  ("Hotel_Voucher_-_GTS-<ref>.pdf": "Hotel Voucher" header,
 *     "Ref No: GTS-XXXXXXX") — hotel, guests, check-in/out, room, board. NO price.
 *   - INVOICE  ("HotelInvoice_GTS-<ref>.pdf": "Booking ID: GTS-XXXXXXX",
 *     "Total Amount: KWD <n>") — the PRICE (KWD), invoice no + dates.
 *   - ON-HOLD  ("GTSBeds_Booking_Confirmation__On_Hold__...") — same layout as the
 *     voucher plus a "Payment Due Date" line. NOT a confirmed stay yet: skipped
 *     (returns no tasks) — the final voucher arrives after payment and creates
 *     the task then.
 *
 * Both halves emit reference = the GTS-XXXXXXX Booking ID, so
 * TaskController::store folds the pair into one task (generic hotel
 * invoice/voucher auto-merge), mirroring TboHotelParser / HeysamParser.
 * GTS bills in KWD, so the invoice total goes straight into price/total.
 */
class GtsBedsParser
{
    public const SUPPLIER_NAME    = 'GTS Beds';
    public const SUPPLIER_COUNTRY = 'Kuwait';

    private string $text;

    public function __construct(string $filePath = '')
    {
        if ($filePath !== '') {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            $this->text = (new PdfParser())->parseFile($filePath)->getText();
        } else {
            $this->text = '';
        }
    }

    public static function fromText(string $text): self
    {
        $p = new self();
        $p->text = $text;
        return $p;
    }

    /**
     * GTS Beds fingerprint: a GTS-XXXXXXX reference anchored to its own label
     * ("Ref No:" on vouchers / "Booking ID:" on invoices), or the gtsbeds.com
     * domain in the invoice letterhead. The label anchor keeps random "GTS"
     * mentions in other suppliers' documents from matching.
     */
    public static function matches(string $rawText): bool
    {
        if (preg_match('/\b(?:Ref\s*No|Booking\s*ID)\s*:?\s*GTS-[A-Z0-9]{5,10}\b/i', $rawText)) {
            return true;
        }
        return stripos($rawText, 'gtsbeds.com') !== false
            && (bool) preg_match('/\bGTS-[A-Z0-9]{5,10}\b/i', $rawText);
    }

    public function parseTaskSchema(): array
    {
        // On-hold confirmation = voucher layout + "Payment Due Date". Booking is
        // not paid/final yet — skip (file lands in files_processed with 0 tasks).
        $isVoucherLayout = stripos($this->text, 'Hotel Voucher') !== false;
        if ($isVoucherLayout && stripos($this->text, 'Payment Due Date') !== false) {
            return [];
        }

        // Payment receipt ("HotelInvoiceReceipt_*.pdf": "payment request has been
        // made by Travel Agent" + Paid Amount) — a wallet-payment notification,
        // not a booking document. The invoice already carries the price; parsing
        // this would emit an UNKNOWN GUEST task with no data.
        if (stripos($this->text, 'payment request has been made') !== false) {
            return [];
        }

        $isInvoice = stripos($this->text, 'Booking ID') !== false
            || stripos($this->text, 'Invoice Number') !== false;

        $data = $isInvoice ? $this->parseInvoice() : $this->parseVoucher();
        return $data === null ? [] : [$data];
    }

    // ──────────────────────────── INVOICE ────────────────────────────

    private function parseInvoice(): ?array
    {
        $ref = $this->firstMatch('/Booking\s*ID\s*:?\s*(GTS-[A-Z0-9]{5,10})/i');
        if (!$ref) {
            return null;
        }

        $flat = preg_replace('/\s+/', ' ', $this->text);

        $invoiceNo = $this->firstMatch('/Invoice\s*Number\s*:?\s*([A-Za-z0-9\-]+)/i');
        // Issued = when the booking was made; invoice date is the fallback.
        $issued = $this->longDate($flat, '/Booking\s*Date\s*:?\s*([A-Za-z]+\s+\d{1,2},?\s*\d{4})/i')
            ?? $this->longDate($flat, '/Invoice\s*Date\s*:?\s*([A-Za-z]+\s+\d{1,2},?\s*\d{4})/i');

        // "Total Amount: KWD 324.57"
        $total = null; $currency = 'KWD';
        if (preg_match('/Total\s*Amount\s*:?\s*([A-Z]{3})?\s*([\d,]+(?:\.\d{1,3})?)/i', $flat, $m)) {
            $currency = strtoupper($m[1] ?: 'KWD');
            $total    = (float) str_replace(',', '', $m[2]);
        }

        // "Hotel Name: Malak Hotel Regency City: <address>"
        $hotelName = $this->cleanHotel(preg_match('/Hotel\s*Name\s*:?\s*(.+?)\s+City\s*:/i', $flat, $hm) ? $hm[1] : null);
        $guest     = $this->firstGuest();
        $checkIn   = $this->longDate($flat, '/Check-?\s*in\s*Date\s+([A-Za-z]+\s+\d{1,2},?\s*\d{4})/i');
        $checkOut  = $this->longDate($flat, '/Check-?\s*Out\s*Date\s+([A-Za-z]+\s+\d{1,2},?\s*\d{4})/i');

        // KWD goes straight into price/total; a foreign-currency invoice (not
        // seen yet) stays in original_* for the downstream conversion.
        $isKwd = ($currency === 'KWD');

        $hotelDetail = $hotelName ? array_filter([
            'hotel_name' => $hotelName,
            'check_in'   => $checkIn ? substr($checkIn, 0, 10) : null,
            'check_out'  => $checkOut ? substr($checkOut, 0, 10) : null,
        ], fn($v) => $v !== null) : null;

        $info = $this->joinInfo([
            "GTS Beds booking {$ref} (invoice)",
            $invoiceNo ? "Supplier invoice: {$invoiceNo}" : null,
            $hotelName ? "Hotel: {$hotelName}" : null,
            ($total !== null) ? "Total: {$total} {$currency}" : null,
        ]);

        return $this->taskShape([
            'reference'          => $ref,
            'client_name'        => $guest ?: 'UNKNOWN GUEST',
            'issued_date'        => $issued,
            'venue'              => $hotelName,
            'additional_info'    => $info,
            'currency'           => $currency,
            'price'              => $isKwd ? $total : null,
            'total'              => $isKwd ? $total : null,
            'original_price'     => $total,
            'original_total'     => $total,
            'task_hotel_details' => $hotelDetail ? [$hotelDetail] : [],
        ]);
    }

    // ──────────────────────────── VOUCHER ────────────────────────────

    private function parseVoucher(): ?array
    {
        $ref = $this->firstMatch('/Ref\s*No\s*:?\s*(GTS-[A-Z0-9]{5,10})/i');
        if (!$ref) {
            return null;
        }

        $flat = preg_replace('/\s+/', ' ', $this->text);

        // "Issue date: 22 Aug, 2026" (wraps across lines in the PDF)
        $issued = null;
        if (preg_match('/Issue\s*date\s*:?\s*(\d{1,2})\s+([A-Za-z]{3,9}),?\s*(\d{4})/i', $flat, $m)) {
            $mm = $this->monthNum($m[2]);
            if ($mm) {
                $issued = sprintf('%04d-%02d-%02d 00:00:00', (int)$m[3], $mm, (int)$m[1]);
            }
        }

        // Leader name is on its own line under the label.
        $leader = null;
        if (preg_match('/Leader\s*Name\s*:?\s*\n\s*([A-Z][A-Za-z\' ]+?)\s*\n/i', $this->text, $m)) {
            $leader = $this->cleanName($m[1]);
        }

        // Hotel name line sits above the property metadata: "MALAK HOTEL REGENCY\n
        // Rating: 5.0 Stars" or "TRIBE AMSTERDAM CITY\nCategory: Hotel\n…". The
        // char class excludes ':' so the metadata lines themselves can't match.
        $hotelName = $this->cleanHotel(
            preg_match('/\n\s*([A-Z][A-Za-z0-9&\'\. \-]{3,60}?)\s*\n\s*(?:Rating|Category|Accommodation)\s*:/', $this->text, $hm) ? $hm[1] : null
        );

        $city     = $this->firstMatch('/City\s*:\s*([A-Za-z][A-Za-z \-]{2,30}?)\s*\n/');
        $checkIn  = $this->longDate($flat, '/Check\s*in\s+([A-Za-z]+\s+\d{1,2},?\s*\d{4})/i');
        $checkOut = $this->longDate($flat, '/Check\s*Out\s+([A-Za-z]+\s+\d{1,2},?\s*\d{4})/i');
        $nights   = $this->firstMatch('/Nights?\s+(\d{1,3})\b/i');
        $rooms    = $this->firstMatch('/Rooms?\s+(\d{1,2})\b/i');
        $adults   = $this->firstMatch('/Adults\s*:?\s*(\d{1,2})/i');

        // "✓Deluxe-Bed & Breakfast-Refundable Rate Single Room"
        $roomType = null;
        if (preg_match('/[\x{2713}\x{2714}]\s*([^\n]{4,90})/u', $this->text, $rm)) {
            $roomType = trim($rm[1]);
        } elseif (preg_match('/Description\s*:\s*([^\n]{4,90})/i', $this->text, $rm)) {
            $roomType = trim($rm[1]);
        }
        // "Board Basis: BedAndBreakfast," / "Board Basis: room only,"
        $meal = $this->firstMatch('/Board\s*Basis\s*:?\s*([A-Za-z][A-Za-z ]*?)\s*[,\n]/i');

        // Guests list: "MOHAMMAD ALAJMI - Adult"
        $guests = [];
        if (preg_match_all('/([A-Z][A-Za-z\' ]+?)\s*-\s*(?:Adult|Child)\b/', $this->text, $gm)) {
            foreach ($gm[1] as $g) {
                $g = $this->cleanName($g);
                if ($g !== '' && !in_array($g, $guests, true)) {
                    $guests[] = $g;
                }
            }
        }
        $guestName = $leader ?: ($guests[0] ?? 'UNKNOWN GUEST');

        $hotelDetail = $hotelName ? array_filter([
            'hotel_name' => $hotelName,
            'city'       => $city,
            'check_in'   => $checkIn ? substr($checkIn, 0, 10) : null,
            'check_out'  => $checkOut ? substr($checkOut, 0, 10) : null,
            'room_type'  => $roomType,
            'meal_type'  => $meal,
            'adults'     => $adults !== null ? (int) $adults : (count($guests) ?: null),
        ], fn($v) => $v !== null && $v !== '') : null;

        $info = $this->joinInfo([
            "GTS Beds booking {$ref} (voucher)",
            $hotelName ? "Hotel: {$hotelName}" : null,
            $roomType ?: null,
            $meal ?: null,
            $nights ? "{$nights} nights" : null,
            ($rooms && (int)$rooms > 1) ? "{$rooms} rooms" : null,
            count($guests) > 1 ? (count($guests) . ' guests') : null,
            $city ? "City: {$city}" : null,
        ]);

        return $this->taskShape([
            'reference'          => $ref,
            'client_name'        => $guestName,
            'issued_date'        => $issued,
            'venue'              => $city ?: $hotelName,
            'additional_info'    => $info,
            'currency'           => 'KWD',
            'price'              => null,   // voucher has no price
            'total'              => null,
            'original_price'     => null,
            'original_total'     => null,
            'task_hotel_details' => $hotelDetail ? [$hotelDetail] : [],
        ]);
    }

    // ──────────────────────────── shared shape ────────────────────────────

    private function taskShape(array $o): array
    {
        return [
            'additional_info'        => $o['additional_info'],
            'ticket_number'          => $o['reference'],
            'gds_reference'          => $o['reference'],
            'airline_reference'      => null,
            'status'                 => 'confirmed',
            'supplier_status'        => 'confirmed',
            'refund_date'            => null,
            'void_date'              => null,
            'price'                  => $o['price'],
            'currency'               => $o['currency'],
            'exchange_currency'      => $o['currency'],
            'original_price'         => $o['original_price'],
            'original_currency'      => $o['currency'],
            'total'                  => $o['total'],
            'original_total'         => $o['original_total'],
            'surcharge'              => 0,
            'penalty_fee'            => null,
            'tax'                    => 0,
            'taxes_record'           => null,
            'refund_charge'          => null,
            'reference'              => $o['reference'],
            'original_ticket_number' => null,
            'original_reference'     => null,
            'created_by'             => null,
            'issued_by'              => null,
            'iata_number'            => null,
            'type'                   => 'hotel',
            'agent_name'             => null,
            'agent_email'            => null,
            'agent_amadeus_id'       => null,
            'client_name'            => $o['client_name'],
            'supplier_name'          => self::SUPPLIER_NAME,
            'supplier_country'       => self::SUPPLIER_COUNTRY,
            'cancellation_policy'    => null,
            'cancellation_deadline'  => null,
            'supplier_pay_date'      => null,
            'venue'                  => $o['venue'],
            'issued_date'            => $o['issued_date'],
            'is_exchanged'           => false,
            'task_hotel_details'     => $o['task_hotel_details'],
        ];
    }

    // ──────────────────────────── helpers ────────────────────────────

    private function firstMatch(string $re): ?string
    {
        return preg_match($re, $this->text, $m) ? trim($m[1]) : null;
    }

    /** First "<NAME> - Adult" row (room table / guests list). */
    private function firstGuest(): ?string
    {
        if (preg_match('/([A-Z][A-Za-z\' ]+?)\s*-\s*(?:Adult|Child)\b/', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        return null;
    }

    /** "August 29, 2026" (matched by $re against $flat) -> "2026-08-29 00:00:00". */
    private function longDate(string $flat, string $re): ?string
    {
        if (preg_match($re, $flat, $m) && preg_match('/([A-Za-z]{3,9})\s+(\d{1,2}),?\s*(\d{4})/', $m[1], $d)) {
            $mm = $this->monthNum($d[1]);
            if ($mm) {
                return sprintf('%04d-%02d-%02d 00:00:00', (int)$d[3], $mm, (int)$d[2]);
            }
        }
        return null;
    }

    private function monthNum(string $mon): ?int
    {
        $months = ['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,
                   'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12];
        return $months[strtolower(substr($mon, 0, 3))] ?? null;
    }

    private function cleanName(string $raw): string
    {
        return trim(preg_replace('/\s+/', ' ', strtoupper(trim($raw))));
    }

    private function cleanHotel(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        return $raw !== '' ? $raw : null;
    }

    private function joinInfo(array $bits): string
    {
        return implode(' | ', array_values(array_filter($bits, fn($b) => $b !== null && $b !== '')));
    }
}
