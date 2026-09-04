<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for HEYSEM TOURISM & TRAVEL (supplier "Heysam Group",
 * Turkey) hotel bookings, posted in the "Heysem / City Travelers KW" WhatsApp
 * group as TWO PDFs that together describe one booking:
 *
 *   - VOUCHER  ("CTK-<no> VCH.pdf": "VOUCHER FORM" / "HEYSEM TOURISM & TRAVEL")
 *     — one page per room/hotel segment: hotels, guests, check-in/out. NO price.
 *   - INVOICE  ("CTK-<no> INV.pdf": "TOUR OPERATOR SALE INVOICE")
 *     — per-segment prices + "VOUCHER INVOICE TOTALS" grand totals, possibly in
 *     SEVERAL currencies at once (e.g. USD 640,00 + EUR 1.182,00, European
 *     number format). Converted to KWD here (currency_exchanges rates, static
 *     fallback) because the task money model holds a single currency.
 *
 * Both emit reference = "CTK-<voucher no>" so the store folds the pair into one
 * hotel task (same auto-merge as TboHotelParser / RateHawkHotelParser).
 */
class HeysamParser
{
    public const SUPPLIER_NAME    = 'Heysam Group';
    public const SUPPLIER_COUNTRY = 'Turkey';

    private const FALLBACK_KWD_RATES = ['USD' => 0.310, 'EUR' => 0.365, 'TRY' => 0.010, 'GBP' => 0.420];

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

    public static function matches(string $rawText): bool
    {
        $isVoucher = stripos($rawText, 'VOUCHER FORM') !== false
            && stripos($rawText, 'HEYSEM TOURISM') !== false;
        $isInvoice = stripos($rawText, 'TOUR OPERATOR SALE INVOICE') !== false
            && preg_match('/CITY\s+TRAVELS\s*\/\s*KUWAIT/i', $rawText);
        return $isVoucher || $isInvoice;
    }

    /**
     * One task PER ROOM SEGMENT (sub-voucher): the voucher has one page block per
     * segment and the invoice one priced block per segment, printed in the SAME
     * order — so segment i of each file pairs up as reference CTK-<no>-<i> and
     * the generic hotel auto-merge folds each pair into one task.
     */
    public function parseTaskSchema(): array
    {
        $isInvoice = stripos($this->text, 'TOUR OPERATOR SALE INVOICE') !== false;
        return $isInvoice ? $this->parseInvoice() : $this->parseVoucher();
    }

    /** "CITY TRAVELS / KUWAIT0143" (voucher) or "... KUWAIT\n0143" (invoice) -> CTK-0143 */
    private function reference(): ?string
    {
        if (preg_match('/CITY\s+TRAVELS\s*\/\s*KUWAIT\s*(\d{2,6})/i', $this->text, $m)) {
            return 'CTK-' . $m[1];
        }
        if (preg_match('/Voucher\s*:\s*(\d{2,6})/i', $this->text, $m)) {
            return 'CTK-' . $m[1];
        }
        return null;
    }

    // ──────────────────────────── VOUCHER ────────────────────────────

    private function parseVoucher(): array
    {
        $ref = $this->reference();
        if (!$ref) {
            return [];
        }

        // one "VOUCHER FORM" page block per room segment
        $blocks = array_values(array_filter(array_map('trim',
            preg_split('/VOUCHER\s+FORM/i', $this->text) ?: [])));
        $tasks = [];
        $i = 0;
        foreach ($blocks as $block) {
            if (stripos($block, 'HEYSEM TOURISM') === false) {
                continue;
            }
            $i++;

            $guest = null;
            if (preg_match('/((?:Mr|Mrs|Ms|Chd)\.?\s*[A-Z][A-Z() \d]+?)\s*(?:\(View attached|\-|\nConf)/i', $block, $m)) {
                $guest = $this->cleanName($m[1]);
            } elseif (preg_match('/Customer Name List\s*\n\-+\s*\n\s*((?:Mr|Mrs|Ms|Chd)\.?\s*[A-Z][A-Z ]+)/i', $block, $m)) {
                $guest = $this->cleanName($m[1]);
            }

            $rooms = 1;
            $checkIn = $checkOut = null;
            if (preg_match('/(\d)(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})/', $block, $dm)) {
                $rooms    = max(1, (int) $dm[1]);
                $checkIn  = $this->dmyDate($dm[2]);
                $checkOut = $this->dmyDate($dm[3]);
            }

            $hotel = null;
            if (preg_match('/Flight Details\s*:\s*\n(?:\s*[\d ]{6,20}\n)?\s*([A-Z][A-Z0-9 .&\'\-]{3,60})\s*(?:\n|$)/', $block, $hm)) {
                $hotel = trim($hm[1]);
            }

            $roomDesc = null;
            if (preg_match('/\n([^\n]{4,80}?)\s+BED AND BREAKFAST|\n([^\n]{4,80}?(?:ROOM|BUNGALOV|SUITE|VIEW)[^\n]{0,40})\n/i', $block, $rm)) {
                $roomDesc = trim($rm[1] ?: ($rm[2] ?? ''));
            }

            $segRef = "{$ref}-{$i}";
            $hotelDetail = $hotel ? array_filter([
                'hotel_name' => $hotel,
                'check_in'   => $checkIn ? substr($checkIn, 0, 10) : null,
                'check_out'  => $checkOut ? substr($checkOut, 0, 10) : null,
            ], fn($v) => $v !== null) : null;

            $info = $this->joinInfo([
                "Heysem booking {$ref} segment {$i} (voucher)",
                $hotel ? "Hotel: {$hotel}" : null,
                $roomDesc ?: null,
                $rooms > 1 ? "{$rooms} rooms" : null,
            ]);

            $tasks[] = $this->taskShape([
                'reference'          => $segRef,
                'gds_reference'      => $ref,
                'client_name'        => $guest ?: 'UNKNOWN GUEST',
                'issued_date'        => null,
                'venue'              => $hotel,
                'additional_info'    => $info,
                'currency'           => 'KWD',
                'price'              => null,
                'total'              => null,
                'task_hotel_details' => $hotelDetail ? [$hotelDetail] : [],
            ]);
        }

        return $tasks;
    }

    // ──────────────────────────── INVOICE ────────────────────────────

    private function parseInvoice(): array
    {
        $ref = $this->reference();
        if (!$ref) {
            return [];
        }

        $issued = null;
        if (preg_match('/Print Date\s*:?\s*(\d{2}\/\d{2}\/\d{4})/', $this->text, $m)) {
            $issued = $this->dmyDate($m[1]);
        }

        // one priced block per segment, printed in the same order as the voucher
        // pages. "Grand Total : 320,00 USD" per block (European number format).
        $blocks = preg_split('/Tour Operator\s*:/i', $this->text) ?: [];
        array_shift($blocks); // page header before the first segment
        $tasks = [];
        $i = 0;
        foreach ($blocks as $block) {
            if (stripos($block, 'KUWAIT') === false) {
                continue;
            }
            $i++;

            $kwd = 0.0;
            $costNote = null;
            if (preg_match('/Grand Total\s*:\s*([\d.,]+)\s*(USD|EUR|TRY|GBP|KWD)/i', $block, $gm)) {
                $amount = $this->euroNumber($gm[1]);
                $cur = strtoupper($gm[2]);
                $rate = $cur === 'KWD' ? 1.0 : $this->kwdRate($cur);
                $kwd = round($amount * $rate, 3);
                $costNote = number_format($amount, 2) . " {$cur}"
                    . ($cur === 'KWD' ? '' : " @ {$rate}") . " = {$kwd} KWD";
            }

            $invNo = preg_match('/Inv\.?\s*Nr\s*:?\s*(\d{4,10})/i', $block, $im) ? $im[1] : null;
            $hotel = preg_match('/Hotel\s*:\s*([A-Z][A-Z0-9 .&\'\-]{3,60})\s*(?:\n|$)/', $block, $hm) ? trim($hm[1]) : null;
            $guest = preg_match('/1\-\s*(?:Mr|Mrs|Ms|Chd)\.?\s*([A-Z][A-Z ]+)/', $block, $gm2) ? $this->cleanName($gm2[1]) : null;
            $rooms = preg_match('/(\d+)\s*Room\b/i', $block, $rm) ? max(1, (int) $rm[1]) : 1;

            $info = $this->joinInfo([
                "Heysem booking {$ref} segment {$i} (invoice)",
                $invNo ? "Supplier invoice: {$invNo}" : null,
                $hotel ? "Hotel: {$hotel}" : null,
                $rooms > 1 ? "{$rooms} rooms" : null,
                $costNote ? "Cost: {$costNote}" : null,
            ]);

            $tasks[] = $this->taskShape([
                'reference'          => "{$ref}-{$i}",
                'gds_reference'      => $ref,
                'ticket_number'      => $invNo ?: "{$ref}-{$i}",
                'client_name'        => $guest ?: 'UNKNOWN GUEST',
                'issued_date'        => $issued,
                'venue'              => $hotel,
                'additional_info'    => $info,
                'currency'           => 'KWD',
                'price'              => $kwd > 0 ? $kwd : null,
                'total'              => $kwd > 0 ? $kwd : null,
                'task_hotel_details' => [],
            ]);
        }

        return $tasks;
    }

    // ──────────────────────────── shared shape ────────────────────────────

    private function taskShape(array $o): array
    {
        return [
            'additional_info'        => $o['additional_info'],
            'ticket_number'          => $o['ticket_number'] ?? $o['reference'],
            'gds_reference'          => $o['gds_reference'] ?? $o['reference'],
            'airline_reference'      => null,
            'status'                 => 'confirmed',
            'supplier_status'        => 'confirmed',
            'refund_date'            => null,
            'void_date'              => null,
            'price'                  => $o['price'],
            'currency'               => $o['currency'],
            'exchange_currency'      => $o['currency'],
            'original_price'         => $o['price'],
            'original_currency'      => $o['currency'],
            'total'                  => $o['total'],
            'original_total'         => $o['total'],
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

    /** "1.182,00" -> 1182.00 (European format; also tolerates "640,00"). */
    private function euroNumber(string $raw): float
    {
        $raw = trim($raw, " \t.,");
        return (float) str_replace(',', '.', str_replace('.', '', $raw));
    }

    private function kwdRate(string $currency): float
    {
        try {
            $r = \Illuminate\Support\Facades\DB::table('currency_exchanges')
                ->where('base_currency', $currency)->where('exchange_currency', 'KWD')
                ->orderByDesc('is_manual')->value('exchange_rate');
            if ($r) {
                return (float) $r;
            }
        } catch (\Throwable $e) {
            // fall through to static rates
        }
        return self::FALLBACK_KWD_RATES[$currency] ?? 1.0;
    }

    /** "28/07/2026" or "28/07/26" -> "2026-07-28 00:00:00". */
    private function dmyDate(?string $d): ?string
    {
        if ($d && preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/', $d, $m)) {
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }
            return sprintf('%04d-%02d-%02d 00:00:00', $y, (int) $m[2], (int) $m[1]);
        }
        return null;
    }

    private function cleanName(string $raw): string
    {
        $raw = preg_replace('/^(Mrs|Mr|Ms|Chd)\.?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*\(\d+\)\s*/', ' ', $raw); // child age "(11)"
        return trim(preg_replace('/\s+/', ' ', $raw));
    }

    private function joinInfo(array $parts): string
    {
        return implode(' | ', array_values(array_filter($parts, fn($p) => $p !== null && $p !== '')));
    }
}
