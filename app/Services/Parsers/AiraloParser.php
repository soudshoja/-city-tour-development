<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Airalo (AirGSM Pte. Ltd.) per-order eSIM invoices.
 *
 * Format markers:
 *  - "AirGSM Pte" provider block
 *  - "Invoice number: INVP-YYYY-NNNNNN"
 *  - one "[eSIM] <package name> - <n> days\t<qty> $<unit> USD $<total> USD" line
 *
 * One invoice = one eSIM package = one task (type esim). Amounts are USD (Airalo
 * bills in USD) — captured in the currency/original_currency fields as USD.
 *
 * The monthly "Invoice For <month>" payment-reminder emails have NO "[eSIM]" line
 * and are deliberately NOT matched (they aggregate already-invoiced orders — would
 * double-count).
 */
class AiraloParser
{
    public const SUPPLIER_NAME    = 'Airalo';
    public const SUPPLIER_COUNTRY = 'Singapore';

    private string $text;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        $parser = new PdfParser();
        $this->text = $parser->parseFile($filePath)->getText();
    }

    public static function matches(string $rawText): bool
    {
        // AirGSM (Airalo's legal entity) + an eSIM line = a per-order invoice.
        return stripos($rawText, 'AirGSM') !== false
            && stripos($rawText, '[eSIM]') !== false;
    }

    public function parseTaskSchema(): array
    {
        $invoice = $this->extractInvoiceNumber();
        if (!$invoice) {
            throw new \Exception('Could not locate Airalo invoice number');
        }

        $line = $this->extractEsimLine();
        if (!$line) {
            throw new \Exception('Could not locate Airalo [eSIM] line item');
        }

        $issued = $this->extractInvoiceDate();

        // Attribution by the invoice's "Billed to" party (user rule 2026-07-15):
        //   "City Travelers API" -> assign to Saeid Shoja
        //   "City Travelers"     -> leave UNASSIGNED (no agent)
        // suppress_ingest_agent tells ProcessAirFiles NOT to fall back to the
        // mailbox-owner agent (Airalo always lands in Saeid's ingest mailbox, which
        // would otherwise force Saeid onto every invoice).
        $billedTo = $this->extractBilledTo();
        $isApi    = $billedTo !== null && stripos($billedTo, 'API') !== false;

        // Airalo bills in USD; the app posts price in the company base (KWD). Convert
        // with the stored USD->KWD rate (original USD preserved in original_price).
        $usd  = $line['total'];
        $rate = $this->usdToKwdRate();
        $kwd  = round($usd * $rate, 3);

        // Keep the "[eSIM]" prefix on the product label (as it appears on the invoice).
        $label = '[eSIM] ' . $line['package'];

        return [[
            'additional_info'      => "{$label} | Qty {$line['qty']} | Invoice {$invoice} | USD {$usd} @ {$rate} = KWD {$kwd}",
            'ticket_number'        => null,
            'gds_reference'        => $invoice,
            'airline_reference'    => null,
            'status'               => 'issued',
            'supplier_status'      => 'issued',
            'refund_date'          => null,
            'void_date'            => null,
            'price'                => $kwd,
            'currency'             => 'KWD',
            'exchange_currency'    => 'KWD',
            'original_price'       => $usd,
            'original_currency'    => 'USD',
            'total'                => $kwd,
            'surcharge'            => 0,
            'penalty_fee'          => null,
            'tax'                  => 0,
            'taxes_record'         => null,
            'refund_charge'        => null,
            'reference'            => $invoice,
            'original_ticket_number' => null,
            'original_reference'   => null,
            'created_by'           => null,
            'issued_by'            => null,
            'iata_number'          => null,
            'type'                 => 'esim',
            'agent_name'           => $isApi ? 'Saeid Shoja' : null,
            'agent_email'          => $isApi ? 'saeid@citytravelers.co' : null,
            'agent_amadeus_id'     => null,
            'suppress_ingest_agent' => true,
            // Client = the invoice's billed-to party (Airalo bills the agency, not an
            // end customer). The eSIM package is the product -> venue + additional_info.
            'client_name'          => $billedTo ?: 'City Travelers',
            'supplier_name'        => self::SUPPLIER_NAME,
            'supplier_country'     => self::SUPPLIER_COUNTRY,
            'cancellation_policy'  => 'Per Airalo terms.',
            'venue'                => $label,
            'issued_date'          => $issued,
            'is_exchanged'         => false,
            'task_flight_details'  => [],
        ]];
    }

    /**
     * Company USD->KWD rate from currency_exchanges (falls back to 0.34 if the DB
     * isn't reachable, e.g. in the isolated parser-matrix test).
     */
    private function usdToKwdRate(): float
    {
        try {
            $r = \Illuminate\Support\Facades\DB::table('currency_exchanges')
                ->where('base_currency', 'USD')->where('exchange_currency', 'KWD')
                ->orderByDesc('is_manual')->value('exchange_rate');
            return $r ? (float) $r : 0.34;
        } catch (\Throwable $e) {
            return 0.34;
        }
    }

    /**
     * The "Billed to" party — "City Travelers" or "City Travelers API".
     * (Per-order invoices bill "City Travelers"; the API/monthly ones "City
     * Travelers API".)
     */
    private function extractBilledTo(): ?string
    {
        if (preg_match('/Billed to\s+(City Travelers(?:\s+API)?)/i', $this->text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        return null;
    }

    private function extractInvoiceNumber(): ?string
    {
        if (preg_match('/Invoice number:\s*(INVP-\d{4}-\d{4,8})/i', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractInvoiceDate(): ?string
    {
        // "Invoice date: 09 July 2026"
        if (preg_match('/Invoice date:\s*(\d{1,2}\s+[A-Za-z]+\s+\d{4})/i', $this->text, $m)) {
            $dt = \DateTime::createFromFormat('d F Y', trim($m[1]))
               ?: \DateTime::createFromFormat('j F Y', trim($m[1]));
            if ($dt) {
                return $dt->format('Y-m-d') . ' 00:00:00';
            }
        }
        return null;
    }

    /**
     * @return array{package: string, qty: int, unit: float, total: float}|null
     */
    private function extractEsimLine(): ?array
    {
        // "[eSIM] Mamma Mia Unlimited - 15 days\t1 $12.00 USD $12.00 USD"
        if (preg_match('/\[eSIM\]\s*(.+?)\s+(\d+)\s+\$([\d.,]+)\s*USD\s+\$([\d.,]+)\s*USD/is', $this->text, $m)) {
            return [
                'package' => trim(preg_replace('/\s+/', ' ', $m[1])),
                'qty'     => (int) $m[2],
                'unit'    => (float) str_replace(',', '', $m[3]),
                'total'   => (float) str_replace(',', '', $m[4]),
            ];
        }
        return null;
    }
}
