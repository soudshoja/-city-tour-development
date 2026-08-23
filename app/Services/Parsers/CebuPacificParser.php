<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Cebu Pacific (5J) Itinerary Receipt emails.
 *
 * Email markers from a real sample (JIFGUZ):
 *  - subject: "Your Itinerary Receipt for Booking No. <PNR>"
 *  - from:    "no-reply@email.mycebupacific.com"
 *  - body:    "BOOKING REFERENCE NO. <PNR>"
 *             "BOOKING DATE July 03, 2025"
 *             "<IATA>-<IATA> 5J <num> <DD MON YYYY> DEPARTURE <time>"
 *             "Status Payment Method Date Transaction ID Amount"
 *             "Approved Credit / Debit Card 03 Jul, 2025 281036408 PHP 7,273.80"
 *             Passenger: "MS|MR|MRS <NAME> Adult"
 *
 * Cebu Pacific is a low-cost carrier — booking reference IS the "ticket".
 * No separate Document Number concept like Accelya NDC.
 */
class CebuPacificParser
{
    public const SUPPLIER_NAME    = 'Cebu Pacific';
    public const SUPPLIER_COUNTRY = 'Philippines';
    public const AIRLINE_CODE     = '5J';
    public const SIGNATURE        = 'mycebupacific.com';

    private ?string $filePath = null;
    private string $text = '';
    private string $primaryText = '';
    private string $secondaryText = '';
    /** @var string[] */
    private array $lines = [];

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            if (!file_exists($filePath)) throw new \Exception("File not found: {$filePath}");
            $this->filePath = $filePath;
            $parser = new PdfParser();
            $this->primaryText = $parser->parseFile($filePath)->getText();
            $this->rebuildText();
        }
    }

    public static function fromHtml(string $html): self
    {
        $i = new self();
        $i->secondaryText = self::htmlToText($html);
        $i->rebuildText();
        return $i;
    }

    public static function fromHybrid(?string $pdfPath, ?string $html): self
    {
        $i = new self();
        if ($pdfPath !== null && file_exists($pdfPath)) {
            try {
                $p = new PdfParser();
                $i->primaryText = $p->parseFile($pdfPath)->getText();
                $i->filePath = $pdfPath;
            } catch (\Throwable $e) { $i->primaryText = ''; }
        }
        if ($html) $i->secondaryText = self::htmlToText($html);
        $i->rebuildText();
        return $i;
    }

    private function rebuildText(): void
    {
        $this->text = trim($this->primaryText . "\n\n" . $this->secondaryText);
        $this->lines = explode("\n", $this->text);
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
        return stripos($rawText, self::SIGNATURE) !== false
            || stripos($rawText, 'Cebu Pacific') !== false
            || stripos($rawText, 'BOOKING REFERENCE NO.') !== false;
    }

    public function parseTaskSchema(): array
    {
        $pnr = $this->extractPnr();
        if (!$pnr) throw new \Exception("Could not locate Cebu Pacific booking ref");

        $bookingDate = $this->extractBookingDate();
        $passengers  = $this->extractPassengers();
        $total       = $this->extractTotal();
        $flights     = $this->extractFlights();
        if (empty($passengers)) $passengers = ['UNKNOWN PASSENGER'];

        $perPax = ($total['total'] !== null && count($passengers) > 0)
            ? round($total['total'] / count($passengers), 3) : null;
        $isKwd = ($total['currency'] ?? 'KWD') === 'KWD';

        $tasks = [];
        foreach ($passengers as $name) {
            $tasks[] = [
                'additional_info'      => $this->buildAdditionalInfo($bookingDate, $passengers, $total),
                'ticket_number'        => null,
                'gds_reference'        => $pnr,
                'airline_reference'    => $pnr,
                'status'               => 'issued',
                'supplier_status'      => 'issued',
                'refund_date'          => null,
                'void_date'            => null,
                'price'                => $isKwd ? $perPax : null,
                'currency'             => $total['currency'] ?? 'KWD',
                'exchange_currency'    => $total['currency'] ?? 'KWD',
                'original_price'       => $isKwd ? null : $perPax,
                'original_currency'    => $isKwd ? null : $total['currency'],
                'total'                => $isKwd ? $perPax : null,
                'original_total'       => $isKwd ? null : $perPax,
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
                'passenger_name'       => $name,
                'supplier_name'        => self::SUPPLIER_NAME,
                'supplier_country'     => self::SUPPLIER_COUNTRY,
                'cancellation_policy'  => 'Per Cebu Pacific fare rules.',
                'venue'                => $flights[0]['airport_to'] ?? null,
                'issued_date'          => $bookingDate,
                'supplier_pay_date'    => $bookingDate,
                'is_exchanged'         => false,
                'task_flight_details'  => array_map(function ($f) {
                    $f['airline_id'] = self::SUPPLIER_NAME;
                    return $f;
                }, $flights),
            ];
        }
        return $tasks;
    }

    private function extractPnr(): ?string
    {
        if (preg_match('/BOOKING REFERENCE NO\.?\s+([A-Z0-9]{6})/', $this->text, $m)) return $m[1];
        if (preg_match('/Booking Reference No\.?:\s+([A-Z0-9]{6})/', $this->text, $m)) return $m[1];
        if (preg_match('/Booking No\.\s+([A-Z0-9]{6})/', $this->text, $m)) return $m[1];
        return null;
    }

    private function extractBookingDate(): ?string
    {
        // "BOOKING DATE July 03, 2025" or "Booking Date: July 03, 2025"
        if (preg_match('/BOOKING DATE[:\s]+([A-Za-z]+)\s+(\d{1,2}),\s+(\d{4})/i', $this->text, $m)) {
            $months = ['January'=>'01','February'=>'02','March'=>'03','April'=>'04','May'=>'05','June'=>'06',
                       'July'=>'07','August'=>'08','September'=>'09','October'=>'10','November'=>'11','December'=>'12'];
            $mm = $months[ucfirst(strtolower($m[1]))] ?? '01';
            return sprintf('%s-%s-%02d 00:00:00', $m[3], $mm, (int)$m[2]);
        }
        return null;
    }

    private function extractPassengers(): array
    {
        $passengers = [];
        $seen = [];
        // "MS ROSALIE TABORA Adult" / "Ms. MARIA MORENA OLGUERA Adult" / "MRS JANE SMITH Adult"
        // Title is case-insensitive with an optional period ("Ms." vs "MS"); the name
        // stays ALL-CAPS. The value can sit on its own line, so \s+ (matches newlines).
        if (preg_match_all('/(?:(?i:MS|MR|MRS|MISS|MSTR|MASTER))\.?\s+([A-Z][A-Z\s\'-]+?)\s+(?:Adult|Child|Infant)/u', $this->text, $matches)) {
            foreach ($matches[1] as $name) {
                $clean = trim(preg_replace('/\s+/', ' ', $name));
                if (isset($seen[$clean])) continue;
                $seen[$clean] = true;
                // Restore the title from the original match for the full name
                if (preg_match('/((?:(?i:MS|MR|MRS|MISS|MSTR|MASTER))\.?\s+' . preg_quote($clean) . ')/', $this->text, $fm)) {
                    $passengers[] = $fm[1];
                } else {
                    $passengers[] = $clean;
                }
            }
        }
        return $passengers;
    }

    /** @return array{total: ?float, currency: string} */
    private function extractTotal(): array
    {
        // The Approved payment row: "Approved Credit / Debit Card 03 Jul, 2025 281036408 PHP 7,273.80"
        if (preg_match('/Approved[^A-Z\d]+(?:Credit|Debit|Cash|Card|Bank)[^A-Z\d]*\d{1,2}\s+[A-Za-z]+,?\s+\d{4}\s+\d+\s+([A-Z]{3})\s+([\d,]+\.\d{2})/i', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        // Fallback: any "PHP <amount>" in fare breakdown header
        if (preg_match('/(PHP|USD|KWD|AED|SAR)\s+([\d,]+\.\d{2})/i', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        return ['total' => null, 'currency' => 'KWD'];
    }

    /**
     * Cebu segments look like:
     *   "MNL-DVO 5J 975 15 Dec 2025 DEPARTURE 9:30pm Manila - Ninoy Aquino International Airport Terminal 3 15 Dec 2025 ARRIVAL 11:30pm Davao - Francisco Bangoy International Airport"
     */
    private function extractFlights(): array
    {
        $flights = [];
        // /s flag so .*? crosses newlines in the htmlToText output
        $pattern = '/([A-Z]{3})-([A-Z]{3})\s+5J\s*(\d{1,4})\s+(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})\s+DEPARTURE\s+([\d:apm]+).*?ARRIVAL\s+([\d:apm]+)/ius';
        if (preg_match_all($pattern, $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $depDt = $this->parseDateTime($m[4], $m[5]);
                $arrDt = $this->parseDateTime($m[4], $m[6]); // best-effort: assume same day if not multi-day
                // Try to find an "<date> ARRIVAL" with a different date right after this segment
                $blockPos = strpos($this->text, $m[0]);
                if ($blockPos !== false) {
                    $after = substr($this->text, $blockPos, 400);
                    if (preg_match('/(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})\s+ARRIVAL\s+([\d:apm]+)/iu', $after, $am)) {
                        $arrDt = $this->parseDateTime($am[1], $am[2]);
                    }
                }
                $flights[] = [
                    'flight_number'  => '5J ' . $m[3],
                    'airport_from'   => $m[1],
                    'airport_to'     => $m[2],
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => $depDt,
                    'arrival_time'   => $arrDt,
                    'duration_time'  => null,
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

        // Newer layout: route as "MNL – PAG" (en-dash) up top, then per-segment
        //   "<date> <time> <dur> FLIGHT NO. 5J 771 DEPARTURE <city> ... <date> <time> ARRIVAL <city>".
        if (empty($flights)) {
            $from = $to = null;
            if (preg_match('/([A-Z]{3})\s*[–\-]\s*([A-Z]{3})/u', $this->text, $rm)) {
                [$from, $to] = [$rm[1], $rm[2]];
            }
            $pattern = '/(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})\s+(\d{1,2}:\d{2}\s*[AP]M)[\s\S]{0,60}?FLIGHT NO\.?\s+(5J\s*\d{1,4})[\s\S]*?ARRIVAL/iu';
            if (preg_match_all($pattern, $this->text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $depDt = $this->parseDateTime($m[1], $m[2]);
                    $arrDt = null;
                    // arrival date/time = the "<date> <time>" pair immediately before "ARRIVAL"
                    if (preg_match('/(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})\s+(\d{1,2}:\d{2}\s*[AP]M)\s+ARRIVAL/iu', $m[0], $am)) {
                        $arrDt = $this->parseDateTime($am[1], $am[2]);
                    }
                    $flights[] = [
                        'flight_number'  => preg_replace('/\s+/', ' ', trim($m[3])),
                        'airport_from'   => $from,
                        'airport_to'     => $to,
                        'terminal_from'  => null,
                        'terminal_to'    => null,
                        'departure_time' => $depDt,
                        'arrival_time'   => $arrDt,
                        'duration_time'  => null,
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
        }
        return $flights;
    }

    /** "15 Dec 2025" + "9:30pm" → "2025-12-15 21:30:00" */
    private function parseDateTime(string $dateStr, string $timeStr): ?string
    {
        $months = ['Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
                   'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'];
        if (!preg_match('/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/', $dateStr, $dm)) return null;
        $mm = $months[ucfirst(strtolower($dm[2]))] ?? null;
        if (!$mm) return null;
        if (!preg_match('/(\d{1,2}):(\d{2})\s*(am|pm)?/i', $timeStr, $tm)) return null;
        $h = (int)$tm[1]; $min = (int)$tm[2];
        $ap = strtolower($tm[3] ?? '');
        if ($ap === 'pm' && $h < 12) $h += 12;
        if ($ap === 'am' && $h === 12) $h = 0;
        return sprintf('%s-%s-%02d %02d:%02d:00', $dm[3], $mm, (int)$dm[1], $h, $min);
    }

    private function buildAdditionalInfo(?string $bookingDate, array $passengers, array $total): string
    {
        $bits = [];
        if ($bookingDate) $bits[] = "Booking date: " . substr($bookingDate, 0, 10);
        if (count($passengers) > 1) $bits[] = "Pax: " . count($passengers);
        if (!empty($total['currency']) && $total['currency'] !== 'KWD' && $total['total'] !== null) {
            $bits[] = "Original: " . $total['total'] . ' ' . $total['currency'];
        }
        return implode(' | ', $bits);
    }
}
