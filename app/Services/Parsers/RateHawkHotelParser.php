<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for RateHawk (Emerging Travel Group) HOTEL bookings.
 *
 * RateHawk emails a hotel booking as TWO separate PDFs that together describe one
 * stay — this parser reads either one into the same task shape so the downstream
 * auto-merge (TaskController::store, keyed on the booking reference) folds them
 * into a single task:
 *
 *   - INVOICE  ("Invoice B2B #…", service line "Hotel booking ID <ref>, guests: …,
 *     <stay dates>, <hotel>,<city>, <country>") — carries the PRICE (USD), the
 *     invoice number, the invoice date, and the supplier due date. NO room/board.
 *   - VOUCHER  ("Reservation <ref> made on …", "This accommodation is booked by our
 *     partner") — carries room type, board, occupancy, check-in/out times and the
 *     cancellation policy. NO price.
 *
 * Both shapes emit reference = the RateHawk booking ID so the two halves reconcile.
 * Foreign-currency totals (RateHawk bills in USD) are left in original_* so
 * TaskController::store does the KWD conversion, mirroring RateHawkParser (air).
 *
 * HOTEL-ONLY: the invoice fingerprint requires "Hotel booking ID", so RateHawk
 * transfer / air invoices fall through to their own parsers / AI untouched.
 */
class RateHawkHotelParser
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

    /** Build from raw extracted text (tests + pipeline). */
    public static function fromText(string $text): self
    {
        $p = new self();
        $p->text = $text;
        return $p;
    }

    /**
     * Content fingerprint. Disjoint from RateHawkParser (air) — the hotel invoice
     * carries no "ratehawk" brand string (it says "Emerging Travel Inc."), and the
     * voucher is identified structurally.
     */
    public static function matches(string $rawText): bool
    {
        // Invoice — hotel service line only (transfer/air invoices won't match)
        if (stripos($rawText, 'Invoice B2B') !== false
            && stripos($rawText, 'Hotel booking ID') !== false) {
            return true;
        }
        // Voucher — "Reservation <digits> made on" + the RateHawk partner phrase
        if (preg_match('/Reservation\s+\d{6,}\s+made on/i', $rawText)
            && (stripos($rawText, 'booked by our partner') !== false
                || (stripos($rawText, 'Check-in') !== false
                    && stripos($rawText, 'Cancellation Policy') !== false))) {
            return true;
        }
        return false;
    }

    public function parseTaskSchema(): array
    {
        $isInvoice = stripos($this->text, 'Invoice B2B') !== false
            && stripos($this->text, 'Hotel booking ID') !== false;

        $data = $isInvoice ? $this->parseInvoice() : $this->parseVoucher();
        if ($data === null) {
            return [];
        }

        return [$data];
    }

    // ──────────────────────────── INVOICE ────────────────────────────

    private function parseInvoice(): ?array
    {
        $ref = $this->firstMatch('/Hotel booking ID\s+(\d{5,})/i');
        if (!$ref) {
            return null;
        }

        $invoiceNo = $this->firstMatch('/Invoice B2B\s*#\s*([\d-]+)/i');
        $issued    = $this->dotDate($this->firstMatch('/Date:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i'));
        $dueDate   = $this->dotDate($this->firstMatch('/Due date:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i'));

        // total + currency: "Total Amount Due: 825.00 USD"
        $total = null; $currency = 'USD';
        if (preg_match('/Total Amount Due:\s*([\d.,]+)\s*([A-Z]{3})/i', $this->text, $m)) {
            $total    = (float) str_replace(',', '', $m[1]);
            $currency = strtoupper($m[2]);
        }

        // service line: "Hotel booking ID <ref>, guests: <NAMES>, <ci> - <co>, <hotel>,<city>, <country>"
        $guests = [];
        $checkIn = $checkOut = $hotelName = $city = $country = null;
        if (preg_match(
            '/Hotel booking ID\s+\d{5,},\s*guests?:\s*(.+?),\s*(\d{1,2}\.\d{1,2}\.\d{4})\s*-\s*(\d{1,2}\.\d{1,2}\.\d{4}),\s*(.+?)(?:\s*-\s*-|\n\s*-|\nTotal|$)/is',
            $this->text, $m
        )) {
            $guests   = $this->splitGuests($m[1]);
            $checkIn  = $this->dotDate($m[2]);
            $checkOut = $this->dotDate($m[3]);
            [$hotelName, $city, $country] = $this->splitHotelCityCountry($m[4]);
        }

        if (empty($guests)) {
            $guests = ['UNKNOWN GUEST'];
        }

        $venue = trim(implode(', ', array_filter([$city, $country]))) ?: null;
        $hotelDetail = array_filter([
            'hotel_name' => $hotelName,
            'city'       => $city,
            'check_in'   => $checkIn,
            'check_out'  => $checkOut,
            'adults'     => count($guests) ?: null,
        ], fn($v) => $v !== null && $v !== '');

        $info = $this->joinInfo([
            "RateHawk hotel order: {$ref}",
            $invoiceNo ? "Supplier invoice: {$invoiceNo}" : null,
            $hotelName ?: null,
            $venue ? "Location: {$venue}" : null,
            $issued ? "Invoice date: " . substr($issued, 0, 10) : null,
            ($total !== null) ? "Order total: {$total} {$currency}" : null,
            count($guests) > 1 ? ("Guests: " . count($guests)) : null,
        ]);

        return $this->taskShape([
            'reference'           => $ref,
            'client_name'         => $guests[0],
            'issued_date'         => $issued,
            'supplier_pay_date'   => $dueDate,
            'cancellation_deadline' => $dueDate,
            'cancellation_policy' => null,
            'venue'               => $venue,
            'additional_info'     => $info,
            'currency'            => $currency,
            'original_total'      => $total,
            'original_price'      => $total,
            'task_hotel_details'  => $hotelDetail ? [$hotelDetail] : [],
        ]);
    }

    // ──────────────────────────── VOUCHER ────────────────────────────

    private function parseVoucher(): ?array
    {
        $ref = $this->firstMatch('/Reservation\s+(\d{5,})\s+made on/i');
        if (!$ref) {
            return null;
        }

        // "made on 10.06.26" — 2-digit year
        $issued = null;
        if (preg_match('/made on\s+(\d{1,2})\.(\d{1,2})\.(\d{2})\b/i', $this->text, $m)) {
            $issued = sprintf('20%02d-%02d-%02d 00:00:00', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        // hotel name: line right after "booked by our partner"
        $hotelName = null;
        if (preg_match('/booked by our partner\s*\n?\s*(.+?)\s*\n/i', $this->text, $m)) {
            $hotelName = trim($m[1]);
        }

        // check-in / check-out (date + time on following lines)
        $checkIn  = $this->voucherDateTime('Check-in');
        $checkOut = $this->voucherDateTime('Check-out');

        // room type + occupancy: "Classic Double room (full double bed), for 2 adults"
        $roomType = null; $adults = null;
        if (preg_match('/([^\n]*room[^\n]*?),\s*for\s*(\d+)\s*adults?/i', $this->text, $m)) {
            $roomType = trim($m[1]);
            $adults   = (int) $m[2];
        }

        // board / meal
        $meal = null;
        if (preg_match('/Meal type\s*\n?\s*(.+?)\s*\n/i', $this->text, $m)) {
            $meal = trim($m[1]);
        }

        // guests
        $guestName = null;
        if (preg_match('/Guests?:\s*(.+?)\s*\n/i', $this->text, $m)) {
            $guestName = $this->cleanName(preg_replace('/\s*\+\s*\d+\s*$/', '', $m[1]));
        }

        // city from the address line (last comma token before phone)
        $city = null;
        if ($hotelName && preg_match('/' . preg_quote($hotelName, '/') . '\s*\n\s*(.+?)\s*\n/i', $this->text, $m)) {
            $parts = array_map('trim', explode(',', $m[1]));
            $city = end($parts) ?: null;
        }

        $policy = $this->extractCancellationPolicy();

        if (!$guestName) {
            $guestName = 'UNKNOWN GUEST';
        }

        $venue = $city ?: null;
        $hotelDetail = array_filter([
            'hotel_name' => $hotelName,
            'city'       => $city,
            'check_in'   => $checkIn,
            'check_out'  => $checkOut,
            'room_type'  => $roomType,
            'meal_type'  => $meal,
            'adults'     => $adults,
        ], fn($v) => $v !== null && $v !== '');

        $info = $this->joinInfo([
            "RateHawk hotel order: {$ref}",
            $hotelName ?: null,
            $roomType ?: null,
            $meal ?: null,
            $adults ? "{$adults} adults" : null,
            $issued ? "Reservation date: " . substr($issued, 0, 10) : null,
        ]);

        return $this->taskShape([
            'reference'           => $ref,
            'client_name'         => $guestName,
            'issued_date'         => $issued,
            'supplier_pay_date'   => null,
            'cancellation_deadline' => null,
            'cancellation_policy' => $policy,
            'venue'               => $venue,
            'additional_info'     => $info,
            'currency'            => 'USD',
            'original_total'      => null,   // voucher carries no price
            'original_price'      => null,
            'task_hotel_details'  => $hotelDetail ? [$hotelDetail] : [],
        ]);
    }

    // ──────────────────────────── shared shape ────────────────────────────

    /** Build the full task array; financial fields stay USD in original_* for KWD conversion. */
    private function taskShape(array $o): array
    {
        $hasPrice = $o['original_total'] !== null;
        return [
            'additional_info'        => $o['additional_info'],
            'ticket_number'          => $o['reference'],
            'gds_reference'          => $o['reference'],
            'airline_reference'      => null,
            'status'                 => 'confirmed',
            'supplier_status'        => 'confirmed',
            'refund_date'            => null,
            'void_date'              => null,
            'price'                  => null,
            'currency'               => $o['currency'],
            'exchange_currency'      => $o['currency'],
            'original_price'         => $o['original_price'],
            'original_currency'      => $o['currency'],
            'total'                  => null,
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
            'cancellation_policy'    => $o['cancellation_policy'],
            'cancellation_deadline'  => $o['cancellation_deadline'],
            'supplier_pay_date'      => $o['supplier_pay_date'],
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

    /** "DD.MM.YYYY" -> "YYYY-MM-DD 00:00:00". */
    private function dotDate(?string $d): ?string
    {
        if ($d && preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $d, $m)) {
            return sprintf('%04d-%02d-%02d 00:00:00', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        return null;
    }

    /** Voucher "Check-in\n 01.08.2026, from\n 14:00:00" -> "2026-08-01 14:00:00". */
    private function voucherDateTime(string $label): ?string
    {
        if (preg_match('/' . preg_quote($label, '/') . ':?\s*\n?\s*(\d{1,2})\.(\d{1,2})\.(\d{4})[^\n]*\n?\s*(\d{1,2}:\d{2}(?::\d{2})?)?/i', $this->text, $m)) {
            $date = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            $time = !empty($m[4]) ? (strlen($m[4]) === 5 ? $m[4] . ':00' : $m[4]) : '00:00:00';
            return "{$date} {$time}";
        }
        return null;
    }

    /** "ABDULLAH ALHAJRI, MD DELWAR KHAN" -> ["ABDULLAH ALHAJRI","MD DELWAR KHAN"]. */
    private function splitGuests(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $g) {
            $g = $this->cleanName($g);
            if ($g !== '' && strtoupper($g) !== 'GUESTS') {
                $out[] = $g;
            }
        }
        return $out;
    }

    /** "Mercure Nice Marche aux Fleurs,Nice, France" -> [hotel, city, country]. */
    private function splitHotelCityCountry(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        $raw = preg_replace('/\s*-\s*-.*/', '', $raw); // strip trailing "- -825.00"
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($p) => $p !== ''));
        if (count($parts) >= 3) {
            $country = array_pop($parts);
            $city    = array_pop($parts);
            $hotel   = implode(', ', $parts);
            return [$hotel ?: null, $city ?: null, $country ?: null];
        }
        if (count($parts) === 2) {
            return [$parts[0] ?: null, null, $parts[1] ?: null];
        }
        return [$parts[0] ?? null, null, null];
    }

    private function extractCancellationPolicy(): ?string
    {
        if (preg_match('/Amendment & Cancellation Policy\s*(.+?)(?:Please notify in advance|Meal type|City tax|$)/is', $this->text, $m)) {
            $p = trim(preg_replace('/\s+/', ' ', $m[1]));
            return $p !== '' ? $p : null;
        }
        return null;
    }

    private function cleanName(string $raw): string
    {
        return trim(preg_replace('/\s+/', ' ', strtoupper($raw)));
    }

    private function joinInfo(array $bits): string
    {
        $bits = array_values(array_filter($bits, fn($b) => $b !== null && $b !== ''));
        return implode(' | ', $bits);
    }
}
