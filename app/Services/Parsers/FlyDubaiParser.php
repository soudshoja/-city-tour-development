<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for flydubai (FZ) Emirati airline confirmations.
 *
 * Format markers:
 *  - "flydubai" / "letstalk@flydubai.com"
 *  - "Your booking is confirmed <PNR>"
 *  - "flydubai booking reference"
 *  - "Mr./Mrs. <name>" + "Primary Adult"
 *  - "Flight FZ <number>"
 *  - "Booking total KWD <amount>"
 *  - "Booked on <date>"
 */
class FlyDubaiParser
{
    public const SUPPLIER_NAME    = 'flydubai';
    public const SUPPLIER_COUNTRY = 'United Arab Emirates';
    public const AIRLINE_CODE     = 'FZ';
    public const SIGNATURE        = 'flydubai';

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
        // Require both the brand AND a booking-confirmation signal so we don't
        // claim a marketing PDF from flydubai accidentally.
        return stripos($rawText, self::SIGNATURE) !== false
            && (stripos($rawText, 'booking is confirmed') !== false
                || stripos($rawText, 'booking reference') !== false);
    }

    public function parseTaskSchema(): array
    {
        // GDS-issued flydubai confirmations carry an "e-ticket number" line — the
        // ticket was issued through a GDS (Amadeus) and is already loaded from the
        // AIR pipeline under the GDS PNR. A flydubai-portal booking (paid on
        // flydubai.com) has a "Payment reference"/"Booking total" but no e-ticket
        // number. Skip the GDS-issued ones so we don't create a duplicate of the
        // Amadeus task (the flydubai "booking reference" is just the airline
        // record locator, distinct from the GDS PNR).
        if (preg_match('/\be-?ticket number\b/i', $this->text)) {
            throw new NotIssuedException(
                'flydubai confirmation is GDS-issued (carries an e-ticket number) — '
                . 'already loaded via the Amadeus/AIR pipeline; skipped to avoid a duplicate task.'
            );
        }
        $pnr = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception("Could not locate flydubai PNR in PDF");
        }
        $issueDate  = $this->extractBookedOn();
        $passengers = $this->extractPassengers();
        $total      = $this->extractTotal();
        $flights    = $this->extractFlights();
        if (empty($passengers)) $passengers = ['UNKNOWN PASSENGER'];

        // FINAL TOTAL is already per-passenger; a "Booking total" is the whole
        // booking and gets split across this PDF's passengers. Some flydubai
        // PDFs are e-ticket-only with no fare printed → default to 0 (the agent
        // fills it in) rather than null, which the ledger insert rejects.
        $perPax = $total['total'] === null
            ? 0.0
            : (!empty($total['per_pax'])
                ? $total['total']
                : round($total['total'] / max(1, count($passengers)), 3));

        $tasks = [];
        foreach ($passengers as $name) {
            $tasks[] = [
                'additional_info'      => $this->buildAdditionalInfo($issueDate, $passengers, $total),
                'ticket_number'        => null,
                'gds_reference'        => $pnr,
                'airline_reference'    => $pnr,
                'status'               => 'issued',
                'supplier_status'      => 'issued',
                'refund_date'          => null,
                'void_date'            => null,
                'price'                => $perPax,
                'currency'             => $total['currency'] ?? 'KWD',
                'exchange_currency'    => $total['currency'] ?? 'KWD',
                'original_price'       => ($total['currency'] ?? 'KWD') === 'KWD' ? null : $perPax,
                'original_currency'    => ($total['currency'] ?? 'KWD') === 'KWD' ? null : $total['currency'],
                'total'                => $perPax,
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
                'client_name'          => $name,
                'supplier_name'        => self::SUPPLIER_NAME,
                'supplier_country'     => self::SUPPLIER_COUNTRY,
                'cancellation_policy'  => 'Per flydubai fare rules.',
                'venue'                => $flights[0]['arrive_to'] ?? null,
                'issued_date'          => $issueDate,
                'is_exchanged'         => false,
                'task_flight_details'  => $flights,
            ];
        }
        return $tasks;
    }

    private function extractPnr(): ?string
    {
        // The 6-char record locator immediately precedes the first "flydubai"
        // brand line in BOTH layouts:
        //   "Your booking is confirmed\tSHJQ0L\nflydubai"   (older, tab)
        //   "...Passenger details\nCHQ7XV\nflydubai"         (newer)
        // No /i flag: PNRs are upper-case — this is what stops us capturing the
        // word "Thank" (from "...is confirmed\nThank you for booking with us.").
        if (preg_match('/\b([A-Z0-9]{6})\s+flydubai\b/', $this->text, $m)) {
            return $m[1];
        }
        // Structured fallbacks (still upper-case only).
        if (preg_match('/booking is confirmed[ \t]+([A-Z0-9]{6})\b/', $this->text, $m)) {
            return $m[1];
        }
        if (preg_match('/Passenger details\s+([A-Z0-9]{6})\b/', $this->text, $m)) {
            return $m[1];
        }
        // Last resort: filename "NAME-PNR.pdf" / "NAME PNR.pdf" / "PNR.pdf"
        // (separator may be space, underscore or hyphen; trailing " (1)" allowed).
        if (preg_match('/(?:^|[ _-])([A-Z0-9]{6})(?:\s*\(\d+\))*\.pdf$/i', basename($this->filePath), $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    private function extractBookedOn(): ?string
    {
        // "Booked on 09 November 2025"
        if (preg_match('/Booked on\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i', $this->text, $m)) {
            return $this->toIsoDate((int)$m[1], $m[2], (int)$m[3]);
        }
        return null;
    }

    private function extractPassengers(): array
    {
        $passengers = [];
        $seen = [];
        // [ \t] (not \s) between name words so the name stops at the line break —
        // otherwise it swallows the following "Primary Adult Scan…" lines.
        if (preg_match_all('/(Mr|Mrs|Ms|Miss|Dr|Mstr)\.[ \t]+([A-Z][a-z]+(?:[ \t]+[A-Z][a-z]+)+)/u', $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $full = $m[1] . '. ' . trim($m[2]);
                if (isset($seen[$full])) continue;
                $seen[$full] = true;
                $passengers[] = $full;
            }
        }
        return $passengers;
    }

    /** Match "<LABEL> KWD 120.850" → ['amount'=>120.85,'currency'=>'KWD']; null if absent. */
    private function matchAmount(string $re): ?array
    {
        if (preg_match($re, $this->text, $m)) {
            return ['amount' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        return null;
    }

    /**
     * @return array{total: ?float, currency: string, per_pax: bool}
     */
    private function extractTotal(): array
    {
        // 1) Newer "Financial Breakdown (KWD)" prints a PER-PASSENGER "FINAL TOTAL"
        //    (already includes the split transaction-fee share) — use directly.
        if (preg_match('/FINAL TOTAL\s+([\d,]+\.\d{1,3})/i', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[1]), 'currency' => 'KWD', 'per_pax' => true];
        }

        $passenger = $this->matchAmount('/Passenger total\s*:?\s*([A-Z]{3})\s+([\d,]+\.\d{1,3})/i');
        $booking   = $this->matchAmount('/Booking total\s*:?\s*([A-Z]{3})\s+([\d,]+\.\d{1,3})/i');
        $txnFee    = $this->matchAmount('/Transaction fee\s*:?\s*([A-Z]{3})\s+([\d,]+\.\d{1,3})/i');

        // 2) "Passenger total" is the EXPLICIT per-passenger fare. Critically,
        //    flydubai prints the whole-PNR "Booking total" even on a single-
        //    passenger PDF, so dividing "Booking total" by THIS file's passenger
        //    count overcharges a 1-pax file with the entire booking (this is the
        //    XBIFGQ bug: 255.300 landed on one ticket instead of 127.650). Use the
        //    per-pax fare and add this passenger's share of the transaction fee:
        //        per_pax = Passenger total + TransactionFee / paxInPnr
        //    where paxInPnr = round((Booking total − TransactionFee) / Passenger total).
        if ($passenger !== null) {
            $perPax = $passenger['amount'];
            if ($txnFee !== null && $booking !== null && $passenger['amount'] > 0) {
                $paxInPnr = (int) round(($booking['amount'] - $txnFee['amount']) / $passenger['amount']);
                if ($paxInPnr >= 1) {
                    $perPax = $passenger['amount'] + ($txnFee['amount'] / $paxInPnr);
                }
            }
            return ['total' => round($perPax, 3), 'currency' => $passenger['currency'], 'per_pax' => true];
        }

        // 3) Only a whole-booking "Booking total" present (older layout with no
        //    per-passenger line) — split across THIS file's passengers.
        if ($booking !== null) {
            return ['total' => $booking['amount'], 'currency' => $booking['currency'], 'per_pax' => false];
        }
        return ['total' => null, 'currency' => 'KWD', 'per_pax' => false];
    }

    private function extractFlights(): array
    {
        $flights = [];
        $seen = [];

        // Rich layout — each segment is headed by e.g.
        //   "Departure fromQaisumah(Flight )FZ 948"
        //   "Return fromDubai(Flight )FZ 947"
        // (the literal token between the origin city and the flight number is
        // "(Flight )FZ" — NOT "Flight FZ", which is why the old regex matched
        // nothing and every flydubai task loaded with no flight details).
        if (preg_match_all(
            '/(Departure|Return)\s+from(.*?)\(\s*Flight\s*\)\s*FZ\s*(\d{2,4})/i',
            $this->text, $heads, PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            $n = count($heads);
            for ($i = 0; $i < $n; $i++) {
                $fn  = 'FZ ' . $heads[$i][3][0];
                $dir = strtolower($heads[$i][1][0]);          // departure / return
                $key = $dir . '|' . $fn;
                if (isset($seen[$key])) continue;             // invoice repeats the legs per passenger
                $seen[$key] = true;

                // Text from this header up to the next header (or end of doc).
                $start = $heads[$i][0][1] + strlen($heads[$i][0][0]);
                $end   = ($i + 1 < $n) ? $heads[$i + 1][0][1] : strlen($this->text);
                $block = substr($this->text, $start, max(0, $end - $start));

                // First two HH:MM in the block → departure, arrival.
                preg_match_all('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $block, $tm);
                $depTime = $tm[0][0] ?? null;
                $arrTime = $tm[0][1] ?? null;

                // IATA codes — each printed on its own line (AQI, DXB) → from, to.
                preg_match_all('/^\s*([A-Z]{3})\s*$/m', $block, $codes);
                $fromCode = $codes[1][0] ?? null;
                $toCode   = $codes[1][1] ?? null;

                // Date "16 June 2026".
                $date = null;
                if (preg_match('/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/', $block, $dm)) {
                    $date = $this->toIsoDate((int) $dm[1], $dm[2], (int) $dm[3]);
                }

                $class    = preg_match('/\b(Business|Economy)\b/i', $block, $cm) ? strtolower($cm[1]) : 'economy';
                $terminal = preg_match('/Terminal\s+([A-Za-z0-9]+)/i', $block, $tt) ? ('Terminal ' . $tt[1]) : null;
                $baggage  = preg_match('/(\d+\s*kg checked baggage[^\n]*)/i', $block, $bm) ? trim($bm[1]) : null;

                $flights[] = [
                    'flight_number'  => $fn,
                    'airport_from'   => $fromCode,
                    'airport_to'     => $toCode,
                    'departure_from' => trim($heads[$i][2][0]) ?: null,
                    'arrive_to'      => $toCode,
                    'terminal_from'  => null,
                    'terminal_to'    => $terminal,
                    'departure_time' => $this->buildDateTime($date, $depTime),
                    'arrival_time'   => $this->buildDateTime($date, $arrTime),
                    'duration_time'  => null,
                    'airline_name'   => self::SUPPLIER_NAME,
                    'class_type'     => $class,
                    'baggage_allowed'=> $baggage,
                    'equipment'      => null,
                    'flight_meal'    => null,
                    'seat_no'        => null,
                    'farebase'       => null,
                    'ticket_number'  => null,
                ];
            }
            return $flights;
        }

        // Legacy fallback: bare "Flight FZ NNN" with no surrounding block.
        if (preg_match_all('/Flight\s+FZ\s*(\d{2,4})/i', $this->text, $matches)) {
            foreach ($matches[1] as $fn) {
                if (isset($seen[$fn])) continue;
                $seen[$fn] = true;
                $flights[] = [
                    'flight_number'  => 'FZ ' . $fn,
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

    private function buildDateTime(?string $isoDate, ?string $time): ?string
    {
        if (!$isoDate || !$time) return null;
        $day = substr($isoDate, 0, 10);                       // YYYY-MM-DD
        if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time)) return null;
        return $day . ' ' . $time . ':00';
    }

    private function toIsoDate(int $day, string $month, int $year): string
    {
        $months = ['January'=>'01','February'=>'02','March'=>'03','April'=>'04','May'=>'05','June'=>'06',
                   'July'=>'07','August'=>'08','September'=>'09','October'=>'10','November'=>'11','December'=>'12',
                   'Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','Jun'=>'06','Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'];
        $mm = $months[ucfirst(strtolower($month))] ?? '01';
        return sprintf('%04d-%s-%02d 00:00:00', $year, $mm, $day);
    }

    private function buildAdditionalInfo(?string $issueDate, array $passengers, array $total): string
    {
        $bits = [];
        if ($issueDate) $bits[] = "Booked: " . substr($issueDate, 0, 10);
        if (count($passengers) > 1) $bits[] = "Pax: " . count($passengers);
        if ($total['total'] === null) $bits[] = "Fare not printed in PDF — set to 0, please update";
        return implode(' | ', $bits);
    }
}
