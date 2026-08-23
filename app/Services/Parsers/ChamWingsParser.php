<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Cham Wings (Fly Cham) Syrian airline itineraries.
 *
 * Format markers:
 *  - "RESERVATION CONFIRMED" header
 *  - "RESERVATION NUMBER (PNR) <CODE>"
 *  - "DATE OF BOOKING" + "DATE OF ISSUE" (DD MMM YYYY)
 *  - Passenger rows: "MR/MRS <NAME>" + "Passport No. - <pp>" + amounts
 *  - "TOTAL IN <CCY> <Fare> <Charges> <Paid> <Balance>"
 *  - Optional "TOTAL IN <amt> KWD" (currency conversion)
 *  - Flight rows: "XH<flightno> OK" + airports + dates
 */
class ChamWingsParser
{
    public const SUPPLIER_NAME    = 'Cham Wings';
    public const SUPPLIER_COUNTRY = 'Syria';
    public const AIRLINE_CODE     = 'XH';
    public const SIGNATURE_A      = 'RESERVATION CONFIRMED';
    public const SIGNATURE_B      = 'flycham.com';

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
        return stripos($rawText, self::SIGNATURE_A) !== false
            && (stripos($rawText, 'chamwings') !== false
                || stripos($rawText, 'flycham') !== false
                || stripos($rawText, 'Fly Cham') !== false);
    }

    public function parseTaskSchema(): array
    {
        $pnr = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception("Could not locate Cham Wings PNR in PDF");
        }
        $issueDate  = $this->extractIssueDate();
        $passengers = $this->extractPassengers();
        $total      = $this->extractTotal();
        $flights    = $this->extractFlights();
        $ticketByName = $this->extractTicketByName();

        if (empty($passengers)) $passengers = ['UNKNOWN PASSENGER'];

        $perPax = ($total['total'] !== null && count($passengers) > 0)
            ? round($total['total'] / count($passengers), 3) : null;

        $tasks = [];
        foreach ($passengers as $name) {
            $tasks[] = [
                'additional_info'      => $this->buildAdditionalInfo($issueDate, $passengers, $total),
                'ticket_number'        => $ticketByName[$name] ?? null,
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
                'cancellation_policy'  => 'Per Cham Wings fare rules.',
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
        if (preg_match('/RESERVATION NUMBER \(PNR\)\s+([A-Z0-9]{5,8})/i', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractIssueDate(): ?string
    {
        if (preg_match('/DATE OF (?:BOOKING|ISSUE)\s+(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/', $this->text, $m)) {
            return $this->toIsoDate((int)$m[1], $m[2], (int)$m[3]);
        }
        return null;
    }

    private function extractPassengers(): array
    {
        $passengers = [];
        $seen = [];
        // Names appear as "MR FIRSTNAME LASTNAME" — there is also a "Passport No. -" line right after
        if (preg_match_all('/^(MR|MRS|MS|MISS|MSTR)\s+([A-Z][A-Z\'\-]+(?:\s+[A-Z][A-Z\'\-]+)*)$/m', $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $full = strtoupper($m[1]) . ' ' . strtoupper(trim($m[2]));
                if (isset($seen[$full])) continue;
                $seen[$full] = true;
                $passengers[] = $full;
            }
        }
        return $passengers;
    }

    /**
     * @return array{total: ?float, currency: string}
     */
    private function extractTotal(): array
    {
        // "TOTAL IN USD 83.50 110.20 193.70 0.00" — sum of (Fare + Charges) = Paid
        if (preg_match('/TOTAL IN\s+([A-Z]{3})\s+[\d.,]+\s+[\d.,]+\s+([\d.,]+)/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[2]),
                'currency' => strtoupper($m[1]),
            ];
        }
        // "TOTAL IN <amt> KWD" (conversion)
        if (preg_match('/TOTAL IN\s+([\d,]+\.\d{1,3})\s+KWD/i', $this->text, $m)) {
            return [
                'total'    => (float) str_replace(',', '', $m[1]),
                'currency' => 'KWD',
            ];
        }
        return ['total' => null, 'currency' => 'KWD'];
    }

    private function extractFlights(): array
    {
        $flights = [];
        // Flight number rows look like "XH706 OK"
        if (preg_match_all('/(XH\d{3,4})\s+OK/', $this->text, $matches)) {
            $seen = [];
            foreach ($matches[1] as $fn) {
                if (isset($seen[$fn])) continue;
                $seen[$fn] = true;
                $flights[] = [
                    'flight_number'  => $fn,
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

    private function extractTicketByName(): array
    {
        $out = [];
        // Pattern: "MR NAME (multi-word) KWI/DAM XH706 3862304495182/1"
        if (preg_match_all('/^((?:MR|MRS|MS|MISS|MSTR)\s+[A-Z][A-Z\'\-]+(?:\s+[A-Z][A-Z\'\-]+)*)\s+[A-Z]{3}\/[A-Z]{3}\s+XH\d+\s+(\d{10,15})/m', $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim($m[1]);
                $out[$name] = $m[2];
            }
        }
        return $out;
    }

    private function toIsoDate(int $day, string $month, int $year): string
    {
        $months = ['Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
                   'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'];
        $mm = $months[ucfirst(strtolower($month))] ?? '01';
        return sprintf('%04d-%s-%02d 00:00:00', $year, $mm, $day);
    }

    private function buildAdditionalInfo(?string $issueDate, array $passengers, array $total): string
    {
        $bits = [];
        if ($issueDate) $bits[] = "Issue: " . substr($issueDate, 0, 10);
        if (count($passengers) > 1) $bits[] = "Pax: " . count($passengers);
        if ($total['currency'] !== 'KWD' && $total['total'] !== null) {
            $bits[] = "Original: " . $total['total'] . ' ' . $total['currency'];
        }
        return implode(' | ', $bits);
    }
}
