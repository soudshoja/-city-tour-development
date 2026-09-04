<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for IndiGo (6E) Indian airline itineraries.
 *
 * Hybrid: accepts PDF attachment (preferred), HTML email body, or both.
 *   - fromFile($path)       → PDF only (legacy path, smalot/pdfparser)
 *   - fromHtml($html)       → HTML body only
 *   - fromHybrid($pdf,$html) → PDF primary + HTML fallback per field
 *
 * Format markers:
 *  - "IndiGo" header, "goindigo.in" anywhere
 *  - "PNR/Booking Ref.: <CODE>"
 *  - "CONFIRMED 29 Apr 26 18:16:31 (UTC)"
 *  - Pax: "Mr. <Name>"
 *  - Flight: "6E<num>" + date/airport/times
 *  - "Total Fare KWD <amount>"
 */
class IndigoParser
{
    public const SUPPLIER_NAME    = 'IndiGo';
    public const SUPPLIER_COUNTRY = 'India';
    public const AIRLINE_CODE     = '6E';
    public const SIGNATURE        = 'IndiGo Flight(s)';

    private ?string $filePath = null;
    /** Combined text used by extractors: PDF text + "\n\n" + HTML text (when both). */
    private string $text = '';
    /** PDF-derived text alone (preferred source when both available). */
    private string $primaryText = '';
    /** HTML-derived text (fallback). */
    private string $secondaryText = '';
    /** @var string[] */
    private array $lines = [];

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            $this->filePath = $filePath;
            $parser  = new PdfParser();
            $this->primaryText = $parser->parseFile($filePath)->getText();
            $this->rebuildText();
        }
    }

    /** Build from HTML body only (no PDF). */
    public static function fromHtml(string $html): self
    {
        $instance = new self();
        $instance->secondaryText = self::htmlToText($html);
        $instance->rebuildText();
        return $instance;
    }

    /** Hybrid: PDF as primary source, HTML as fallback for fields PDF doesn't yield. */
    public static function fromHybrid(?string $pdfPath, ?string $html): self
    {
        $instance = new self();
        if ($pdfPath !== null && file_exists($pdfPath)) {
            try {
                $parser = new PdfParser();
                $instance->primaryText = $parser->parseFile($pdfPath)->getText();
                $instance->filePath    = $pdfPath;
            } catch (\Throwable $e) {
                // Bad PDF — fall back to HTML alone
                $instance->primaryText = '';
            }
        }
        if ($html !== null && $html !== '') {
            $instance->secondaryText = self::htmlToText($html);
        }
        $instance->rebuildText();
        return $instance;
    }

    private function rebuildText(): void
    {
        $this->text = trim($this->primaryText . "\n\n" . $this->secondaryText);
        $this->lines = explode("\n", $this->text);
    }

    /** Flatten HTML to plain text the same way the other Option-A parsers do. */
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
            || stripos($rawText, 'goindigo.in') !== false;
    }

    public function parseTaskSchema(): array
    {
        $pnr = $this->extractPnr();
        if (!$pnr) {
            throw new \Exception("Could not locate IndiGo PNR in input (PDF and/or HTML)");
        }

        // Only ISSUED/ticketed IndiGo itineraries become tasks. A HOLD reservation
        // is unpaid and not ticketed — it prints "Payment Status: Needs Payment" and
        // "This reservation will remain on hold until …". Loading it would create a
        // phantom issued task + supplier payable for a booking that may never be paid
        // (see SD32ND, 2026-06-16). Skip it; the real issued itinerary (no HOLD
        // markers) loads normally when/if it arrives.
        if (preg_match('/Needs\s+Payment/i', $this->text)
            || preg_match('/remain\s+on\s+hold\s+until/i', $this->text)) {
            throw new NotIssuedException(
                "IndiGo reservation {$pnr} is on HOLD / unpaid (not ticketed) — skipped to avoid a phantom issued task."
            );
        }

        $issueDate  = $this->extractIssueDate();
        $passengers = $this->extractPassengers();
        $total      = $this->extractTotal();
        $flights    = $this->extractFlights();
        if (empty($passengers)) $passengers = ['UNKNOWN PASSENGER'];

        // IndiGo itinerary emails routinely omit the fare entirely (no "Total Fare"
        // line). When no total is found, extractTotal() returns currency KWD with a
        // null total, so default perPax to 0.0 (NOT null): a null price/total makes
        // TaskController::store choke on the NOT-NULL transactions.amount and dump
        // the file to files_error (see U35WYE, 2026-06-21 — ~138 IndiGo body emails
        // silently stuck this way). The task loads at 0 and the agent prices it after.
        $perPax = ($total['total'] !== null && count($passengers) > 0)
            ? round($total['total'] / count($passengers), 3) : 0.0;
        $isKwd = ($total['currency'] ?? 'KWD') === 'KWD';

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
                // For foreign-currency leave price/total null so the controller's
                // conversion path runs (matches the Jazeera / Accelya pattern).
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
                'cancellation_policy'  => 'Per IndiGo fare rules.',
                'venue'                => $flights[0]['airport_to'] ?? null,
                'issued_date'          => $issueDate,
                'supplier_pay_date'    => $issueDate,
                'is_exchanged'         => false,
                // Stamp airline name on each flight row (task_flight_details.airline_id
                // is a varchar holding the airline NAME per dev1 convention).
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
        if (preg_match('/PNR\/Booking Ref\.?:\s*([A-Z0-9]{5,8})/i', $this->text, $m)) return $m[1];
        // Fallback patterns seen in HTML emails:
        if (preg_match('/Booking Reference[:\s]+([A-Z0-9]{5,8})/i', $this->text, $m)) return $m[1];
        if (preg_match('/PNR[:\s]+([A-Z0-9]{5,8})/i', $this->text, $m)) return $m[1];
        return null;
    }

    private function extractIssueDate(): ?string
    {
        // Real IndiGo bodies write the booking date compactly ("01Jun26") and
        // on the line *after* CONFIRMED, so allow optional whitespace between
        // day/month/year (covers both "01Jun26" and "01 Jun 26").
        if (preg_match('/CONFIRMED\s+(\d{1,2})\s*([A-Za-z]{3})\s*(\d{2,4})/i', $this->text, $m)) {
            $y = (int)$m[3]; if ($y < 100) $y += 2000;
            return $this->toIsoDate((int)$m[1], $m[2], $y);
        }
        // Look for "Date of Booking" near a date
        if (preg_match('/Date of Booking[^\d]*(\d{1,2})\s*([A-Za-z]{3})\s*(\d{2,4})/i', $this->text, $m)) {
            $y = (int)$m[3]; if ($y < 100) $y += 2000;
            return $this->toIsoDate((int)$m[1], $m[2], $y);
        }
        return null;
    }

    private function extractPassengers(): array
    {
        $passengers = [];
        $seen = [];
        // Keep the name on a single line: use [ \t] (not \s) so the match stops
        // at the newline and does NOT swallow the table headers ("Date",
        // "From (Terminal)" …) that follow the passenger name in the body.
        // Continuation words allow a lowercase initial: IndiGo prints names exactly
        // as keyed, e.g. "Mr. Kader maideen Saja mohamed" — requiring every word to
        // start uppercase truncated that to "Mr. Kader". Cap the run (the [ \t]
        // line-stop is the real guard) so we never bleed into following text.
        if (preg_match_all('/(?:Mr|Mrs|Ms|Miss|Dr|Mstr)\.[ \t]+[A-Za-z]+(?:[ \t]+[A-Za-z]+){0,5}/u', $this->text, $matches)) {
            foreach ($matches[0] as $full) {
                $key = trim(preg_replace('/[ \t]+/', ' ', $full));
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $passengers[] = $key;
            }
        }
        return $passengers;
    }

    /** @return array{total: ?float, currency: string} */
    private function extractTotal(): array
    {
        // "Total Fare" is newline-split in the real body ("Total\nFare\nKWD\n178.90"),
        // so allow whitespace (incl. newlines) between every token.
        if (preg_match('/Total\s+Fare\s+([A-Z]{3})\s+([\d,]+\.\d{1,3})/i', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        // HTML often: "Total Amount: <symbol>123.45" — try a looser pattern
        if (preg_match('/Total[^A-Z\d]{0,20}([A-Z]{3})\s*([\d,]+\.\d{1,3})/i', $this->text, $m)) {
            return ['total' => (float) str_replace(',', '', $m[2]), 'currency' => strtoupper($m[1])];
        }
        return ['total' => null, 'currency' => 'KWD'];
    }

    /**
     * Line-based parse of the IndiGo itinerary table. Each segment's *value*
     * block always lays out in this fixed order right after the header row:
     *
     *   03 Jun 26        <- date            (flight-number line minus 3)
     *   Kuwait (T1)      <- from (terminal) (minus 2)
     *   20:25            <- departs         (minus 1)
     *   6E 1236          <- flight number   (anchor)
     *   (A320)           <- aircraft        (plus 1)
     *   19:10            <- check-in closes (plus 2, skipped)
     *   Mumbai (T2)      <- to (terminal)   (plus 3)
     *   04:00+1          <- arrives (+N day)(plus 4)
     *
     * Anchors on the value-block flight number ("6E 1236"); the header label
     * "Flight Number" never matches /^6E\s*\d/, so it is naturally skipped.
     * Falls back to flight-numbers-only if the positional parse finds nothing.
     */
    private function extractFlights(): array
    {
        // IndiGo cells end in a UTF-8 non-breaking space (0xC2A0) that PHP's
        // trim() does NOT strip — normalise it to a real space first, else the
        // anchored line patterns below never match ("6E 1236\xC2\xA0").
        $lines = preg_split('/\R+/', $this->text);
        $lines = array_map(function ($l) {
            return trim(str_replace("\xc2\xa0", ' ', $l));
        }, $lines);
        $lines = array_values(array_filter($lines, fn ($l) => $l !== ''));

        $flights = [];
        $seen = [];
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            if (!preg_match('/^6E\s*(\d{3,4})$/', $lines[$i], $fm)) {
                continue;
            }
            $flightNo = '6E ' . $fm[1];

            $date    = $this->parseDayMonYear($lines[$i - 3] ?? '');
            [$fromAirport, $fromTerminal] = $this->splitCityTerminal($lines[$i - 2] ?? '');
            [$toAirport, $toTerminal]     = $this->splitCityTerminal($lines[$i + 3] ?? '');
            $depTime = preg_match('/^(\d{1,2}:\d{2})/', $lines[$i - 1] ?? '', $dm) ? $dm[1] : null;
            $arrTime = null; $arrOffset = 0;
            if (preg_match('/^(\d{1,2}:\d{2})(?:\+(\d))?/', $lines[$i + 4] ?? '', $am)) {
                $arrTime = $am[1];
                $arrOffset = isset($am[2]) ? (int) $am[2] : 0;
            }
            $equipment = preg_match('/^\(([A-Z0-9]+)\)$/', $lines[$i + 1] ?? '', $em) ? $em[1] : null;

            $depDateTime = ($date && $depTime)
                ? sprintf('%04d-%02d-%02d %s:00', $date[0], $date[1], $date[2], $depTime)
                : null;
            $arrDateTime = null;
            if ($date && $arrTime) {
                $ts = mktime(0, 0, 0, $date[1], $date[2] + $arrOffset, $date[0]);
                $arrDateTime = date('Y-m-d', $ts) . ' ' . $arrTime . ':00';
            }

            $key = $flightNo . '|' . ($fromAirport ?? '') . '|' . ($toAirport ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $flights[] = [
                'flight_number'  => $flightNo,
                'airport_from'   => $fromAirport,
                'airport_to'     => $toAirport,
                'terminal_from'  => $fromTerminal,
                'terminal_to'    => $toTerminal,
                'departure_time' => $depDateTime,
                'arrival_time'   => $arrDateTime,
                'duration_time'  => null,
                'class_type'     => 'economy',
                'baggage_allowed'=> null,
                'equipment'      => $equipment,
                'flight_meal'    => null,
                'seat_no'        => null,
                'farebase'       => null,
                'ticket_number'  => null,
            ];
        }

        // Fallback: positional parse found nothing (layout variant) — at least
        // surface the flight numbers so the task still carries the segments.
        if (empty($flights) && preg_match_all('/(6E\s*\d{3,4})\b/', $this->text, $matches)) {
            foreach ($matches[1] as $fn) {
                $clean = preg_replace('/\s+/', ' ', trim($fn));
                if (isset($seen[$clean])) continue;
                $seen[$clean] = true;
                $flights[] = [
                    'flight_number'  => $clean,
                    'airport_from'   => null,
                    'airport_to'     => null,
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => null,
                    'arrival_time'   => null,
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
        return $flights;
    }

    /** "03 Jun 26" or "03Jun26" -> [year, month, day], or null. */
    private function parseDayMonYear(string $s): ?array
    {
        if (!preg_match('/^(\d{1,2})\s*([A-Za-z]{3})\s*(\d{2,4})$/', trim($s), $m)) {
            return null;
        }
        $months = ['Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,
                   'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12];
        $mm = $months[ucfirst(strtolower($m[2]))] ?? null;
        if (!$mm) return null;
        $y = (int) $m[3]; if ($y < 100) $y += 2000;
        return [$y, $mm, (int) $m[1]];
    }

    /** "Kuwait (T1)" -> ["Kuwait","T1"]; "Prayagraj" -> ["Prayagraj", null]. */
    private function splitCityTerminal(string $s): array
    {
        $s = trim($s);
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $s, $m)) {
            return [trim($m[1]) ?: null, trim($m[2]) ?: null];
        }
        return [$s !== '' ? $s : null, null];
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
        if (!empty($total['currency']) && ($total['currency'] !== 'KWD') && $total['total'] !== null) {
            $bits[] = "Original: " . $total['total'] . ' ' . $total['currency'];
        }
        return implode(' | ', $bits);
    }
}
