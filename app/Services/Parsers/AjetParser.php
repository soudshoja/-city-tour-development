<?php

namespace App\Services\Parsers;

/**
 * Deterministic parser for AJet (VF, Turkish Airlines' low-cost brand)
 * BODY-ONLY "Ticket information" emails from onlineticket@mail.ajet.com.
 * No PDF attachment — the HTML body carries everything:
 *   "Reservation Code" → PNR, "Dear" → passenger, "Total Fare" → "63.76 KWD",
 *   "Ticket No" → 13-digit e-ticket, "Transaction Date" → dd.mm.yyyy issue date,
 *   per-segment "Ticket Information" blocks: date, City/IATA/HH:MM pairs, VF####.
 * One passenger per email. Same task shape as the other airline parsers.
 */
class AjetParser
{
    public const SUPPLIER_NAME    = 'AJET Airline';
    public const SUPPLIER_COUNTRY = 'Turkey';

    private string $text;
    /** @var string[] */
    private array $lines;

    public static function fromHtml(string $html): self
    {
        $self = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $self->text  = self::htmlToText($html);
        $self->lines = explode("\n", $self->text);

        return $self;
    }

    public static function matches(string $rawText): bool
    {
        if (stripos($rawText, 'AJet') === false
            || stripos($rawText, 'Reservation Code') === false
            || stripos($rawText, 'Ticket No') === false) {
            return false;
        }
        // Check-in confirmations / reminders reuse the same layout and carry a
        // Reservation Code and Ticket No, but they are NOT a purchase — loading
        // them created phantom 0-price tasks for an already-ticketed booking.
        // NB: AJet writes "Check-in" with a non-ASCII hyphen (U+2013).
        $normalised = str_replace(["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}"], '-', $rawText);
        foreach (['Check-in Information', 'Check-in Reminder', 'check-in has been completed',
                  'opened for check-in'] as $needle) {
            if (stripos($normalised, $needle) !== false) {
                return false;
            }
        }

        return stripos($rawText, 'Total Fare') !== false;
    }

    /**
     * AJet prints fares in the Turkish locale ("4.552,84 TRY" = 4552.84) but
     * KWD fares come through plain ("63.76 KWD"). Whichever separator comes
     * last is the decimal one; anything before it is a thousands separator.
     */
    private static function parseAmount(string $raw): float
    {
        $raw = trim($raw);
        $lastDot   = strrpos($raw, '.');
        $lastComma = strrpos($raw, ',');
        if ($lastDot !== false && $lastComma !== false) {
            $decimalAt = max($lastDot, $lastComma);
        } elseif ($lastComma !== false) {
            // Lone comma: decimal only when it looks like one ("31,50"), else
            // a thousands separator ("4,552").
            $decimalAt = preg_match('/,\d{1,2}$/', $raw) ? $lastComma : false;
        } else {
            $decimalAt = preg_match('/\.\d{1,2}$/', $raw) || substr_count($raw, '.') === 1 ? $lastDot : false;
        }
        if ($decimalAt === false) {
            return (float) preg_replace('/[^\d]/', '', $raw);
        }
        $int  = preg_replace('/[^\d]/', '', substr($raw, 0, $decimalAt));
        $frac = preg_replace('/[^\d]/', '', substr($raw, $decimalAt + 1));

        return (float) ($int . '.' . $frac);
    }

    /** Same normalisation stack as AirArabiaParser (CRLF, NBSP, hard collapse). */
    private static function htmlToText(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/\s*(p|div|tr|li|h[1-6]|td|th)\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*(p|div|tr|li|h[1-6])\b[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\s*(td|th)\b[^>]*>/i', "\t", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00A0}\x{202F}\x{2007}]/u', ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);

        return trim($text);
    }

    public function parseTaskSchema(): array
    {
        $pnr = $this->valueAfter('Reservation Code', '/^([A-Z0-9]{6})$/');
        if (!$pnr) {
            throw new \Exception('Could not locate AJet PNR in email body');
        }
        $ticket13 = $this->valueAfter('Ticket No', '/^(\d{13})$/');
        // 10-digit serial, airline prefix stripped (Turkish-family convention)
        $ticket = $ticket13 ? substr($ticket13, 3) : null;
        $name   = $this->valueAfter('Dear', '/^([A-Z][A-Z ]{2,60})$/');
        $issued = null;
        if (preg_match('/Transaction Date\n(\d{2})\.(\d{2})\.(\d{4})/', $this->text, $m)) {
            $issued = "{$m[3]}-{$m[2]}-{$m[1]} 00:00:00";
        }
        $totalCcy = ['total' => null, 'currency' => 'KWD'];
        if (preg_match('/Total Fare\n([\d.,]+)\s*([A-Z]{3})/', $this->text, $m)) {
            $totalCcy = ['total' => self::parseAmount($m[1]), 'currency' => strtoupper($m[2])];
        }
        $isKwd = $totalCcy['currency'] === 'KWD';
        $flights = $this->extractFlights();

        $task = [
            'additional_info'      => trim(('Booking date: ' . substr((string) $issued, 0, 10))
                . ($ticket13 ? " | E-ticket: {$ticket13}" : '')),
            'ticket_number'        => $ticket,
            'gds_reference'        => $pnr,
            'airline_reference'    => $pnr,
            'status'               => 'issued',
            'supplier_status'      => 'issued',
            'refund_date'          => null,
            'void_date'            => null,
            'price'                => $isKwd ? ($totalCcy['total'] ?? 0.0) : null,
            'currency'             => $isKwd ? 'KWD' : $totalCcy['currency'],
            'exchange_currency'    => $isKwd ? 'KWD' : $totalCcy['currency'],
            'original_price'       => $isKwd ? null : $totalCcy['total'],
            'original_currency'    => $isKwd ? null : $totalCcy['currency'],
            'total'                => $isKwd ? ($totalCcy['total'] ?? 0.0) : null,
            'original_total'       => $isKwd ? null : $totalCcy['total'],
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
            'client_name'          => $name ?: 'UNKNOWN PASSENGER',
            'supplier_name'        => self::SUPPLIER_NAME,
            'supplier_country'     => self::SUPPLIER_COUNTRY,
            'cancellation_policy'  => 'Per AJet fare rules.',
            'venue'                => $flights[0]['arrive_to'] ?? null,
            'issued_date'          => $issued,
            'is_exchanged'         => false,
            'task_flight_details'  => $flights,
        ];

        return [$task];
    }

    /** First non-empty line after a label line matching $re. */
    private function valueAfter(string $label, string $re): ?string
    {
        foreach ($this->lines as $i => $l) {
            if (strcasecmp(trim($l), $label) !== 0) {
                continue;
            }
            for ($j = $i + 1; $j < min($i + 6, count($this->lines)); $j++) {
                $t = trim($this->lines[$j]);
                if ($t === '') {
                    continue;
                }
                if (preg_match($re, $t, $m)) {
                    return $m[1];
                }
                break;
            }
        }

        return null;
    }

    /**
     * Segment blocks:
     *   Ticket Information \n 06 August 2026 \n ... \n Istanbul \n SAW \n 16:45
     *   \n Izmir \n ADB \n 17:55 \n ... \n VF3068 \n PREMIUM / O Class
     */
    private function extractFlights(): array
    {
        $flights = [];
        $re = '/Ticket Information\n(\d{1,2} [A-Za-z]+ \d{4})\n'
            . '(?:[^\n]{0,40}\n){0,4}?'
            . '([A-Za-z][A-Za-z ]*)\n([A-Z]{3})\n(\d{1,2}:\d{2})\n'
            . '([A-Za-z][A-Za-z ]*)\n([A-Z]{3})\n(\d{1,2}:\d{2})'
            . '[\s\S]{0,120}?\n(VF\s?\d{3,4})\n(?:([A-Z]+) \/ ([A-Z]) Class\n)?/u';
        if (preg_match_all($re, $this->text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $date = date('Y-m-d', strtotime($m[1]));
                $flights[] = [
                    'flight_number'  => preg_replace('/^(VF)\s?/', 'VF ', $m[8]),
                    'airport_from'   => $m[3],
                    'airport_to'     => $m[6],
                    'departure_from' => trim($m[2]),
                    'arrive_to'      => trim($m[5]),
                    'terminal_from'  => null,
                    'terminal_to'    => null,
                    'departure_time' => "{$date} {$m[4]}:00",
                    'arrival_time'   => "{$date} {$m[7]}:00",
                    'duration_time'  => null,
                    'airline_name'   => 'AJet',
                    'class_type'     => isset($m[9]) ? strtolower($m[9]) : 'economy',
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
}
