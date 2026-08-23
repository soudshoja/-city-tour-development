<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Magic Holidays hotel vouchers (TCPDF-generated).
 *
 * Format markers:
 *  - "HOTEL VOUCHER" header
 *  - "Traveler(s):" line listing pax (may include multiple)
 *  - "Booking ID: <id>" + "Booking date: DD-MM-YYYY"
 *  - "Leading passeger: <name>" on page 3 (note the typo — they really
 *    write "passeger")
 *  - "Selling price: KWD<amount>" on page 3
 *
 * Returns one task per booking (the leading passenger is the named client).
 */
class MagicHolidaysParser
{
    public const SUPPLIER_NAME    = 'Magic Holidays';
    public const SUPPLIER_COUNTRY = 'Kuwait';
    public const SIGNATURE_A      = 'HOTEL VOUCHER';
    public const SIGNATURE_B      = 'Leading passeger';

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

    public static function matches(string $rawText): bool
    {
        // Never claim another supplier's hotel document — TBO/RateHawk/Heysam
        // have their own parsers (some checked AFTER this one in the detector).
        if (stripos($rawText, 'ratehawk') !== false
            || stripos($rawText, 'TBOH') !== false
            || stripos($rawText, 'Heysam') !== false) {
            return false;
        }

        // Summary variant (also covers the old combined voucher+summary PDF):
        // the "Leading passeger" typo is Magic's fingerprint; newer standalone
        // summaries say "Reservation summary" + "Internal no.:".
        if (stripos($rawText, self::SIGNATURE_B) !== false
            || (stripos($rawText, 'Reservation summary') !== false
                && stripos($rawText, 'Internal no.:') !== false)) {
            return true;
        }

        // Voucher-only variant — since ~Aug 2026 Magic posts the voucher and the
        // reservation summary as SEPARATE files; the voucher alone carries none
        // of the summary markers the old signature required.
        return stripos($rawText, self::SIGNATURE_A) !== false
            && stripos($rawText, 'Booking ID:') !== false
            && stripos($rawText, 'Booked and Paid by') !== false;
    }

    public function parseTaskSchema(): array
    {
        $bookingId  = $this->extractBookingId();
        if (!$bookingId) {
            throw new \Exception("Could not locate Magic Holidays booking id in PDF");
        }
        $holder      = $this->extractHolder();
        $hotel       = $this->extractHotel();
        $dates       = $this->extractDates();
        $total       = $this->extractTotal();
        $issued      = $this->extractIssueDate();
        $pax         = $this->extractPaxCount();

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
            'cancellation_policy'  => 'Per Magic Holidays voucher terms.',
            'venue'                => $hotel,
            'issued_date'          => $issued ?: ($dates['check_in'] ?? null),
            'is_exchanged'         => false,
            'task_flight_details'  => [],
            'task_hotel_details'   => $this->buildHotelDetail($hotel, $dates, $pax),
        ];

        return [$task];
    }

    private function extractBookingId(): ?string
    {
        if (preg_match('/Booking ID:\s*(\d{4,15})/i', $this->text, $m)) {
            return $m[1];
        }
        if (preg_match('/Internal no\.?:\s*(\d{4,15})/i', $this->text, $m)) {
            return $m[1];
        }
        // Filename pattern fallback: MagicHoliday-NNNNNNNNNN
        if (preg_match('/MagicHoliday-(\d{10,15})/i', $this->filePath, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractHolder(): ?string
    {
        if (preg_match('/Leading passeger:\s*(.+?)(?:\n|$)/i', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        if (preg_match('/Leading passenger:\s*(.+?)(?:\n|$)/i', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        if (preg_match('/Traveler\(s\):\s*(.+?)(?:,|\n|$)/i', $this->text, $m)) {
            return $this->cleanName($m[1]);
        }
        return null;
    }

    private function extractHotel(): ?string
    {
        // 1) "Hotel: <name>"  (page 3 summary)
        if (preg_match('/Hotel:\s*(.+?)(?:\n|$)/i', $this->text, $m)) {
            return trim($m[1]);
        }
        // 2) Capitalized hotel line right after the address block
        //    Find the first line after "HOTEL VOUCHER" that looks like a hotel name.
        $foundVoucher = false;
        foreach ($this->lines as $ln) {
            if (!$foundVoucher) {
                if (stripos($ln, 'HOTEL VOUCHER') !== false) $foundVoucher = true;
                continue;
            }
            $t = trim($ln);
            // Skip the standard label rows
            if ($t === '' || stripos($t, 'Traveler') !== false || stripos($t, 'Booking') !== false ||
                stripos($t, 'Issue') !== false || stripos($t, 'Confirmation') !== false) continue;
            // The first all-CAPS line that starts with a hotel keyword
            if (preg_match('/^[A-Z][A-Z0-9 &,\-\.\']{6,}$/', $t)) {
                return $t;
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

        if (preg_match('/CHECK-?IN:?\s*(\d{4}-\d{2}-\d{2})/i', $this->text, $m)) {
            $checkIn = $m[1] . ' 00:00:00';
        }
        if (preg_match('/CHECK-?OUT:?\s*(\d{4}-\d{2}-\d{2})/i', $this->text, $m)) {
            $checkOut = $m[1] . ' 00:00:00';
        }
        // Voucher variant: the dates are a table row "<in>\t<out>\tID: <booking>"
        // (smalot renders the values before the CHECK-IN/CHECK-OUT header row).
        if (!$checkIn && preg_match('/^(\d{4}-\d{2}-\d{2})[\t ]+(\d{4}-\d{2}-\d{2})[\t ]+ID:/m', $this->text, $m)) {
            $checkIn  = $m[1] . ' 00:00:00';
            $checkOut = $m[2] . ' 00:00:00';
        }
        if (preg_match('/(\d+)\s+\d+\s+Adults:/i', $this->text, $m)) {
            $nights = (int)$m[1];
        }
        return ['check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => $nights];
    }

    /**
     * @return array{total: ?float, currency: string}
     */
    private function extractTotal(): array
    {
        // "Selling price: KWD178.18" (reservation summary / old page 3)
        if (preg_match('/Selling price:\s*([A-Z]{3})\s*([\d,.]+)/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[2]),
                'currency' => strtoupper($m[1]),
            ];
        }
        // Voucher-only documents carry no price. Emit 0 (not null): task creation
        // posts a financial transaction whose amount column is NOT NULL, and the
        // auto-merge treats 0 as empty so the summary's real price fills it later.
        return ['total' => 0.0, 'currency' => 'KWD'];
    }

    private function extractIssueDate(): ?string
    {
        if (preg_match('/Issue date:\s*(\d{4}-\d{2}-\d{2})/i', $this->text, $m)) {
            return $m[1] . ' 00:00:00';
        }
        if (preg_match('/Booking date:\s*(\d{2})-(\d{2})-(\d{4})/i', $this->text, $m)) {
            return sprintf('%s-%s-%s 00:00:00', $m[3], $m[2], $m[1]);
        }
        return null;
    }

    private function extractPaxCount(): ?int
    {
        if (preg_match('/Adults:\s*(\d+)/i', $this->text, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/Pax:\s*Adults\s*x\s*(\d+)/i', $this->text, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Room + board. Summary: "Rooms: 1 X double cave suite (...)". Voucher: the
     * lines between "Children: N (...)" and the "NIGHTS" column header hold the
     * wrapped room type plus the board code (BB/HB/Room Only/...).
     *
     * @return array{room_type: ?string, meal_type: ?string}
     */
    private function extractRoom(): array
    {
        $room = null;
        $meal = null;

        if (preg_match('/Rooms:\s*\d+\s*X\s*(.+?)(?:\n|$)/i', $this->text, $m)) {
            $room = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        $collecting = false;
        $parts = [];
        foreach ($this->lines as $ln) {
            $t = trim($ln);
            if (preg_match('/^Children:\s*\d+/i', $t)) {
                $collecting = true;
                continue;
            }
            if ($collecting) {
                if ($t === '' ) {
                    continue;
                }
                if (stripos($t, 'NIGHTS') !== false || preg_match('/^\d{4}-\d{2}-\d{2}/', $t)) {
                    break;
                }
                if (preg_match('/^(BB|HB|FB|AI|UAI|RO|Room Only|Bed \& Breakfast|Half Board|Full Board|All Inclusive)$/i', $t)) {
                    $meal = $t;
                } elseif (stripos($t, 'refundable') === false) {
                    $parts[] = $t;
                }
            }
        }
        if ($room === null && $parts) {
            $room = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
        }

        return ['room_type' => $room, 'meal_type' => $meal];
    }

    /** One hotel-detail row when a hotel name was found; store() creates/merges it. */
    private function buildHotelDetail(?string $hotel, array $dates, ?int $pax): array
    {
        if (!$hotel) {
            return [];
        }
        $roomInfo = $this->extractRoom();

        return [[
            'hotel_name' => $hotel,
            'room_type'  => $roomInfo['room_type'],
            'meal_type'  => $roomInfo['meal_type'],
            'check_in'   => $dates['check_in'] ? substr($dates['check_in'], 0, 10) : null,
            'check_out'  => $dates['check_out'] ? substr($dates['check_out'], 0, 10) : null,
            'adults'     => $pax,
        ]];
    }

    private function cleanName(string $raw): string
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        // Strip trailing punctuation from name
        $raw = preg_replace('/[\.,;:\s]+$/', '', $raw);
        return $raw;
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
