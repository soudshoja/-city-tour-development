<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for Almaviva "Consortium for Italian Visa Services"
 * (Italian visa appointments, Saudi/Dammam centers; sender sa.info@almaviva-visa.it).
 *
 * Each booking arrives as TWO PDFs sharing a Folder Number (e.g. 26DAM0004153):
 *   - SUMMARY_RESERVATION_*.pdf  — applicant, folder, appointment date/time, center.
 *   - PAYMENT_RECEIPT_*.pdf      — "Simplified Tax Invoice", the paid AVS booking fee
 *                                  (e.g. SAR 52.09 incl. 15% VAT).
 *
 * The SUMMARY is the primary (one visa task per applicant); it merges the sibling
 * PAYMENT_RECEIPT (same folder) for the price. A PAYMENT_RECEIPT processed on its own
 * yields NO task (merged into the summary). Amounts are SAR -> converted to KWD.
 *
 * Filed under the existing "VFS Global" supplier (Almaviva runs on the VFS platform)
 * per user directive 2026-07-15 — avoids creating a new supplier + COA accounts.
 */
class AlmavivaItalyVisaParser
{
    public const SUPPLIER_NAME    = 'VFS Global';
    public const SUPPLIER_COUNTRY = 'Italy';
    public const SIGNATURE        = 'Consortium for Italian Visa Services';

    private string $filePath;
    private string $text;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        $this->filePath = $filePath;
        $this->text = (new PdfParser())->parseFile($filePath)->getText();
    }

    public static function matches(string $rawText): bool
    {
        return stripos($rawText, self::SIGNATURE) !== false;
    }

    public function parseTaskSchema(): array
    {
        // The PAYMENT_RECEIPT half is merged into the SUMMARY — no standalone task.
        if (stripos($this->text, 'Appointment summary') === false) {
            return [];
        }

        $folder = $this->extractFolder($this->text);
        if (!$folder) {
            throw new \Exception('Could not locate Almaviva folder number');
        }
        $customer = $this->extractCustomer($this->text);
        $passport = $this->extractPassport($this->text);
        [$apptDate, $apptTime] = $this->extractAppointment($this->text);
        $center   = $this->extractCenter($this->text);

        // "If sent to ops@ don't assign to any agent" (user rule 2026-07-15). The
        // booking's contact email is on the summary; when it's the ops mailbox the
        // task stays UNASSIGNED even if a copy was forwarded into an agent's ingest
        // box (suppress_ingest_agent bypasses the mailbox-owner fallback).
        $isOps = stripos($this->text, 'ops@citytravelers.co') !== false;

        // Merge the sibling PAYMENT_RECEIPT (same folder) for the paid fee + date.
        [$sar, $paidDate] = $this->findReceipt($folder);
        $rate  = $this->sarToKwdRate();
        $kwd   = $sar !== null ? round($sar * $rate, 3) : 0.0;
        $issued = $paidDate ?: $apptDate; // issue date = when it was paid/booked

        $info = "Italian visa appointment | Folder {$folder}"
              . ($apptDate ? " | Appt " . substr($apptDate, 0, 10) . ($apptTime ? " {$apptTime}" : '') : '')
              . ($center ? " | {$center}" : '')
              . ($sar !== null ? " | SAR {$sar} @ {$rate} = KWD {$kwd}" : '');

        return [[
            'additional_info'      => $info,
            'ticket_number'        => null,
            'gds_reference'        => $folder,
            'airline_reference'    => null,
            'status'               => 'issued',
            'supplier_status'      => 'issued',
            'refund_date'          => null,
            'void_date'            => null,
            'price'                => $kwd,
            'currency'             => 'KWD',
            'exchange_currency'    => 'KWD',
            'original_price'       => $sar,
            'original_currency'    => $sar !== null ? 'SAR' : null,
            'total'                => $kwd,
            'surcharge'            => 0,
            'penalty_fee'          => null,
            'tax'                  => 0,
            'taxes_record'         => null,
            'refund_charge'        => null,
            'reference'            => $folder,
            'original_ticket_number' => null,
            'original_reference'   => null,
            'created_by'           => null,
            'issued_by'            => null,
            'iata_number'          => null,
            'type'                 => 'visa',
            'agent_name'           => null,
            'agent_email'          => null,
            'agent_amadeus_id'     => null,
            'suppress_ingest_agent' => $isOps,
            'client_name'          => $customer ?: 'UNKNOWN APPLICANT',
            'supplier_name'        => self::SUPPLIER_NAME,
            'supplier_country'     => self::SUPPLIER_COUNTRY,
            'cancellation_policy'  => 'Per Almaviva / Italian visa terms.',
            'venue'                => $center ?: 'Italy Visa Center - Dammam',
            'issued_date'          => $issued,
            'is_exchanged'         => false,
            'task_flight_details'  => [],
            'task_visa_details'    => [
                'visa_type'          => 'Italy (Schengen)',
                'application_number' => $folder,
                'expiry_date'        => null,
                'number_of_entries'  => null,
                'stay_duration'      => null,
                'issuing_country'    => 'Italy',
                'appointment_date'   => $apptDate ? substr($apptDate, 0, 10) : null,
            ],
        ]];
    }

    private function extractFolder(string $t): ?string
    {
        if (preg_match('/(\d{2}[A-Z]{3}\d{6,9})/', $t, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractCustomer(string $t): ?string
    {
        // "Customer Name\t<arabic label>BADER ALMUTAIRI\tBADER ALMUTAIRI"
        if (preg_match('/Customer Name[^\n]*?([A-Z][A-Z\'\- ]{3,40}?)\t/u', $t, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        // fallback: ALL-CAPS name on the line following "Customer Name"
        if (preg_match('/Customer Name[\s\S]{0,40}?\b([A-Z][A-Z\']+(?:\s+[A-Z][A-Z\']+){1,3})\b/u', $t, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        return null;
    }

    private function extractPassport(string $t): ?string
    {
        if (preg_match('/\b([A-Z]{1,2}\d{5,8})\b\s*\(/', $t, $m)) {
            return $m[1];
        }
        return null;
    }

    /** @return array{0: ?string, 1: ?string} [Y-m-d H:i:s, HH:MM] */
    private function extractAppointment(string $t): array
    {
        if (preg_match('/Appointment\s+(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}:\d{2})/', $t, $m)) {
            return [sprintf('%04d-%02d-%02d 00:00:00', (int)$m[3], (int)$m[2], (int)$m[1]), $m[4]];
        }
        return [null, null];
    }

    private function extractCenter(string $t): ?string
    {
        if (preg_match('/Visa center\s*(.+?)(?:Submission|\*To be paid|\n)/u', $t, $m)) {
            $c = trim(preg_replace('/\s+/', ' ', $m[1]));
            return $c !== '' ? $c : null;
        }
        return null;
    }

    /**
     * Find the sibling PAYMENT_RECEIPT for this folder and return [SAR total, paid date].
     * Scans the file's own directory plus the processed/error siblings (the two PDFs
     * may be processed in either order). @return array{0: ?float, 1: ?string}
     */
    private function findReceipt(string $folder): array
    {
        $dir  = dirname($this->filePath);
        $dirs = [$dir, dirname($dir) . '/files_processed', dirname($dir) . '/files_error', dirname($dir) . '/files_unprocessed'];
        foreach (array_unique($dirs) as $d) {
            if (!is_dir($d)) continue;
            foreach (glob($d . '/PAYMENT_RECEIPT_*.pdf') ?: [] as $rp) {
                try {
                    $rt = (new PdfParser())->parseFile($rp)->getText();
                } catch (\Throwable $e) {
                    continue;
                }
                if (stripos($rt, $folder) === false) continue;
                $sar = null;
                // The bilingual invoice prints all labels, then all values:
                // "... SAR 45.30 [taxable]  SAR 6.79 [VAT]  SAR 52.09 [total incl VAT]".
                // The TOTAL inclusive of VAT is the last SAR figure on the invoice.
                if (preg_match_all('/SAR\s*([\d.,]+)/i', $rt, $mm) && !empty($mm[1])) {
                    $sar = (float) str_replace(',', '', end($mm[1]));
                }
                $date = null;
                if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s+\d{2}:\d{2}/', $rt, $dm)) {
                    $date = sprintf('%04d-%02d-%02d 00:00:00', (int)$dm[3], (int)$dm[2], (int)$dm[1]);
                }
                return [$sar, $date];
            }
        }
        return [null, null];
    }

    private function sarToKwdRate(): float
    {
        try {
            $r = \Illuminate\Support\Facades\DB::table('currency_exchanges')
                ->where('base_currency', 'SAR')->where('exchange_currency', 'KWD')
                ->orderByDesc('is_manual')->value('exchange_rate');
            return $r ? (float) $r : 0.0814;
        } catch (\Throwable $e) {
            return 0.0814;
        }
    }
}
