<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for TBO Holidays (Tek Travels FZCO) HOTEL bookings, emailed
 * from noreply@tbo.com as TWO separate PDFs that together describe one stay:
 *
 *   - VOUCHER  ("HotelVoucher_<conf>.pdf": "Voucher Details", "TBOH Confirmation: <conf>")
 *     — hotel, guests, check-in/out, room type, board, cancellation policy. NO price.
 *   - INVOICE  ("HotelInvoice_<conf>.pdf": "TBOH Confirmation No: <conf>",
 *     "Net Amount Payable ($): <total>") — the PRICE (USD), invoice no + date. Minimal room info.
 *
 * Both emit reference = the TBOH Confirmation No, so TaskController::store folds the
 * pair into one task (the generic hotel invoice/voucher auto-merge). Foreign-currency
 * totals (TBO bills in USD) stay in original_* for the downstream KWD conversion,
 * mirroring RateHawkHotelParser.
 */
class TboHotelParser
{
    public const SUPPLIER_NAME    = 'TBO Holiday';
    public const SUPPLIER_COUNTRY = 'United Arab Emirates';

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
     * TBO hotel fingerprint. Both halves carry "TBOH Confirmation"; the invoice adds
     * "Net Amount Payable", the voucher adds "Voucher Details". Transfer/insurance
     * invoices (transfersops@tbo.com) lack "TBOH Confirmation" so they don't match.
     */
    public static function matches(string $rawText): bool
    {
        $hasConf = (bool) preg_match('/TBOH\s*Confirmation/i', $rawText);
        if (!$hasConf) {
            return false;
        }
        $isInvoice = stripos($rawText, 'Net Amount Payable') !== false
            || (stripos($rawText, 'Invoice No') !== false && stripos($rawText, 'TBOHolidays') !== false);
        $isVoucher = stripos($rawText, 'Voucher Details') !== false;
        return $isInvoice || $isVoucher;
    }

    public function parseTaskSchema(): array
    {
        $isInvoice = stripos($this->text, 'Net Amount Payable') !== false
            || (stripos($this->text, 'Invoice No') !== false && stripos($this->text, 'TBOHolidays') !== false);

        $data = $isInvoice ? $this->parseInvoice() : $this->parseVoucher();
        return $data === null ? [] : [$data];
    }

    // ──────────────────────────── INVOICE ────────────────────────────

    private function parseInvoice(): ?array
    {
        $ref = $this->firstMatch('/TBOH\s*Confirmation\s*No\.?\s*:?\s*([A-Z0-9]{5,8})/i')
            ?: $this->firstMatch('/TBOH\s*Confirmation\s*:?\s*([A-Z0-9]{5,8})/i');
        if (!$ref) {
            return null;
        }

        $invoiceNo = $this->firstMatch('/Invoice No\s*:?\s*([A-Za-z0-9\-]+)/i');
        $issued    = $this->tboDate($this->firstMatch('/Invoice Date\s*:?\s*([\dA-Za-z\-\s]{9,12})/i'));

        // total + currency: "Net Amount Payable ($): 223.53"  ($ = USD)
        $total = null; $currency = 'USD';
        if (preg_match('/Net Amount Payable\s*\(?\s*(\$|USD|AED|KWD|EUR)?\s*\)?\s*:?\s*([\d.,]+)/i', $this->text, $m)) {
            $currency = $this->normCurrency($m[1] ?? '$');
            $total    = (float) str_replace(',', '', $m[2]);
        } elseif (preg_match('/Invoice Original Amount\s*=\s*([\d.,]+)/i', $this->text, $m)) {
            $total = (float) str_replace(',', '', $m[1]);
        }

        $guest    = $this->firstGuest($this->firstMatch('/Guest Name\s*:?.*?((?:Mr|Mrs|Ms|Miss|Mstr|Dr)\.?\s+[A-Z][A-Za-z\' ]+)/is'));
        // Booking agent: "Issued By: SAEID SHOJA,CITY TRAVELERS" (stop at the comma / agency)
        $agentName = $this->cleanAgent($this->firstMatch('/Issued By\s*:?\s*([A-Z][A-Za-z. ]+?)\s*(?:,|CITY TRAVELERS|$)/i'));
        // "City: Trondheim  Check In:" — anchor on the label + a following "Check"
        // so the letterhead "CITY TRAVELERS" can't leak in.
        $city     = $this->firstMatch('/City:\s*([A-Za-z][A-Za-z \-]{2,30}?)\s+Check\s*(?:In|Out)/i');
        $checkIn  = $this->tboDate($this->firstMatch('/Check In\s*:?\s*([\dA-Za-z\-\s]{9,12})/i'));
        $checkOut = $this->tboDate($this->firstMatch('/Check Out\s*:?\s*([\dA-Za-z\-\s]{9,12})/i'));

        if (!$guest) { $guest = 'UNKNOWN GUEST'; }

        // The invoice is a column table — hotel_name / room_type flatten into the
        // header labels, so we DON'T emit them here; the voucher carries clean
        // room/hotel data and the auto-merge fills them. The invoice's job is the
        // price (original_total) + the guest + the stay dates.
        // Hotel name flattens into the invoice's column header, so it's best-effort
        // here (the voucher carries the clean one). Keep every key present — the
        // merge/save code reads $incHotel['hotel_name'] directly, so a missing key
        // throws "Undefined array key".
        // The hotel name flattens into the invoice's column header and wraps across a
        // line ("Radisson Blu Royal Garden\nHotel"), so match brand-anchored on the
        // whitespace-collapsed text. Emit a hotel detail ONLY when a real name is
        // found — a null name crashes the Hotel insert, and the voucher carries the
        // clean details the merge fills in anyway.
        $hotelName = null;
        $flat = preg_replace('/\s+/', ' ', $this->text);
        if (preg_match('/\b((?:Radisson|Hilton|Marriott|Sheraton|Novotel|Mercure|Ibis|Holiday Inn|Hyatt|Ramada|Rotana|Movenpick|InterContinental|Crowne Plaza|Best Western|Four Seasons|Grand|Royal)[A-Za-z0-9 ,\'&\-]*?Hotel)\b/i', $flat, $hm)) {
            $hotelName = trim($hm[1]);
        }
        $hotelDetail = $hotelName
            ? ['hotel_name' => $hotelName, 'city' => $city, 'check_in' => $checkIn, 'check_out' => $checkOut]
            : null;

        $info = $this->joinInfo([
            "TBO hotel booking: {$ref}",
            $invoiceNo ? "Supplier invoice: {$invoiceNo}" : null,
            $city ? "Location: {$city}" : null,
            $issued ? "Invoice date: " . substr($issued, 0, 10) : null,
            ($total !== null) ? "Order total: {$total} {$currency}" : null,
        ]);

        return $this->taskShape([
            'reference'             => $ref,
            'client_name'           => $guest,
            'agent_name'            => $agentName,
            'issued_date'           => $issued,
            'supplier_pay_date'     => $issued,
            'cancellation_deadline' => null,
            'cancellation_policy'   => null,
            'venue'                 => $city,
            'additional_info'       => $info,
            'currency'              => $currency,
            'original_total'        => $total,
            'original_price'        => $total,
            'task_hotel_details'    => $hotelDetail ? [$hotelDetail] : [],
        ]);
    }

    // ──────────────────────────── VOUCHER ────────────────────────────

    private function parseVoucher(): ?array
    {
        $ref = $this->firstMatch('/TBOH\s*Confirmation\s*:?\s*([A-Z0-9]{5,8})/i');
        if (!$ref) {
            return null;
        }

        // "Trondheim (Norway)\nRadisson Blu Royal Garden Hotel\n<address>"
        $city = $country = $hotelName = null;
        if (preg_match('/([A-Z][A-Za-z \-]+?)\s*\(([A-Za-z ]+)\)\s*\n\s*(.+?)\n/', $this->text, $m)) {
            $city      = trim($m[1]);
            $country   = trim($m[2]);
            $hotelName = trim($m[3]);
        }

        $checkIn  = $this->tboDate($this->firstMatch('/Check In\s*:?\s*([\dA-Za-z\s]{9,12})/i'));
        $checkOut = $this->tboDate($this->firstMatch('/Check Out\s*:?\s*([\dA-Za-z\s]{9,12})/i'));
        $issued   = $this->tboDate($this->firstMatch('/Booked On\s*:?\s*([\dA-Za-z\s]{9,12})/i'));

        // guests: lines under "Room 1:" — "Mr RASHED ALHAJRI", "Mrs REEM ALHAJRI"
        $guests = [];
        if (preg_match_all('/(?:Mr|Mrs|Ms|Miss|Mstr|Dr)\.?\s+([A-Z][A-Z\' ]+[A-Z])/', $this->text, $gm)) {
            foreach ($gm[1] as $g) {
                $g = $this->cleanName($g);
                if ($g !== '' && !in_array($g, $guests, true)) {
                    $guests[] = $g;
                }
            }
        }

        // room type: "Room 1 Superior Room, River View,1 King Bed,NonSmoking"
        $roomType = $this->firstMatch('/Room\s*1\s+([A-Z][^\n]*?(?:Bed|Room|Suite)[^\n]*)/i');
        // board: "Incl:Full Breakfast"
        $meal = $this->firstMatch('/Incl\s*:?\s*([A-Za-z][A-Za-z ]+)/i');
        $nationality = $this->firstMatch('/Guest Nationality\s*:?\s*([A-Za-z ]+)/i');
        $policy = $this->extractCancellationPolicy();

        // Booking agent: voucher shows "Consultant\nSAEID SHOJA" (also "Booked By").
        $agentName = $this->cleanAgent($this->firstMatch('/Consultant\s*\n?\s*([A-Z][A-Za-z. ]+?)\s*\n/i'))
            ?: $this->cleanAgent($this->firstMatch('/Booked By\s*:?\s*([A-Z][A-Za-z. ]+)/i'));

        $guestName = $guests[0] ?? 'UNKNOWN GUEST';

        $hotelDetail = array_filter([
            'hotel_name' => $hotelName,
            'city'       => $city,
            'check_in'   => $checkIn,
            'check_out'  => $checkOut,
            'room_type'  => $roomType,
            'meal_type'  => $meal,
            'adults'     => count($guests) ?: null,
        ], fn($v) => $v !== null && $v !== '');

        $info = $this->joinInfo([
            "TBO hotel booking: {$ref}",
            $hotelName ?: null,
            $roomType ?: null,
            $meal ?: null,
            count($guests) > 1 ? (count($guests) . ' guests') : null,
            $nationality ? "Nationality: {$nationality}" : null,
            trim((string)($city . ($country ? ", $country" : ''))) ?: null,
        ]);

        return $this->taskShape([
            'reference'             => $ref,
            'client_name'           => $guestName,
            'issued_date'           => $issued,
            'supplier_pay_date'     => null,
            'cancellation_deadline' => null,
            'cancellation_policy'   => $policy,
            'venue'                 => trim((string)($city . ($country ? ", $country" : ''))) ?: $city,
            'agent_name'            => $agentName,
            'additional_info'       => $info,
            'currency'              => 'USD',
            'original_total'        => null,   // voucher has no price
            'original_price'        => null,
            'task_hotel_details'    => $hotelDetail ? [$hotelDetail] : [],
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
            'agent_name'             => $o['agent_name'] ?? null,
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

    /** "29-Jun-2026" or "29 Jun 2026" -> "2026-06-29 00:00:00". */
    private function tboDate(?string $d): ?string
    {
        if ($d && preg_match('/(\d{1,2})[\s\-]+([A-Za-z]{3,9})[\s\-]+(\d{4})/', $d, $m)) {
            $mm = $this->monthNum($m[2]);
            if ($mm) {
                return sprintf('%04d-%02d-%02d 00:00:00', (int)$m[3], $mm, (int)$m[1]);
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

    private function normCurrency(string $sym): string
    {
        $s = strtoupper(trim($sym));
        return match ($s) {
            '$', 'USD', '' => 'USD',
            default        => $s,
        };
    }

    private function firstGuest(?string $raw): ?string
    {
        return $raw ? $this->cleanName(preg_replace('/^(Mr|Mrs|Ms|Miss|Mstr|Dr)\.?\s+/i', '', trim($raw))) : null;
    }

    private function cleanName(string $raw): string
    {
        $raw = preg_replace('/^(Mr|Mrs|Ms|Miss|Mstr|Dr)\.?\s+/i', '', trim($raw));
        return trim(preg_replace('/\s+/', ' ', strtoupper($raw)));
    }

    /** "SAEID SHOJA,CITY TRAVELERS" / "Saeid Shoja" -> "SAEID SHOJA" (agency suffix stripped). */
    private function cleanAgent(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = preg_replace('/\s*,.*$/', '', trim($raw));
        $raw = preg_replace('/\s+/', ' ', strtoupper($raw));
        return preg_match('/[A-Z]{2,}/', $raw) ? $raw : null;
    }

    private function cleanHotel(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        return $raw !== '' ? $raw : null;
    }

    private function extractCancellationPolicy(): ?string
    {
        if (preg_match('/Cancellation and Charges\s*(.+?)(?:No Show|Hotel Map|Payment Information|$)/is', $this->text, $m)) {
            $p = trim(preg_replace('/\s+/', ' ', $m[1]));
            return $p !== '' ? $p : null;
        }
        return null;
    }

    private function joinInfo(array $bits): string
    {
        return implode(' | ', array_values(array_filter($bits, fn($b) => $b !== null && $b !== '')));
    }
}
