<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic parser for UK Visas and Immigration ETA emails (saved as PDF).
 *
 * Format markers:
 *  - "uk.visas.and.immigration.home.office@notifications.service.gov.uk" sender
 *  - "We are processing your ETA application" body
 *  - "Dear <NAME>"
 *  - "We have received your payment of <amount> Great British pounds"
 *  - "Your ETA reference number is <YYYY-MMDD-NNNN-XXXX>"
 *  - "WorldPay" + transaction "ETAWEB<digits>"
 */
class LondonVisaParser
{
    public const SUPPLIER_NAME    = 'London Visa';
    public const SUPPLIER_COUNTRY = 'United Kingdom';
    public const SIGNATURE_A      = 'ETA application';
    public const SIGNATURE_B      = 'uk.visas.and.immigration';

    private ?string $filePath = null;
    private string $text = '';
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
            $this->setText($parser->parseFile($filePath)->getText());
        }
    }

    /**
     * Build a parser instance directly from the raw HTML email body — same
     * Option A pattern as AccelyaNdcParser. Skips the Gotenberg PDF
     * round-trip so we don't lose text in the conversion.
     */
    public static function fromHtml(string $html): self
    {
        $instance = new self();
        $instance->setText(self::htmlToText($html));
        return $instance;
    }

    private function setText(string $text): void
    {
        $this->text = $text;
        $this->lines = explode("\n", $text);
    }

    /** Flatten the HTML email body to the same plain-text shape pdftotext yields. */
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
        if (stripos($rawText, self::SIGNATURE_A) === false) {
            return false;
        }
        if (stripos($rawText, self::SIGNATURE_B) !== false) {
            return true;
        }
        // Body-only ETA emails never contain SIGNATURE_B — that's the sender
        // address, which only survives in PDF printouts that include the mail
        // headers. Fingerprint the bare body by the ETA reference line plus the
        // GOV.UK footer. "Your ETA security code" OTP mails carry neither
        // SIGNATURE_A nor a reference line, so they stay excluded.
        return stripos($rawText, 'ETA reference number') !== false
            && stripos($rawText, 'GOV.UK') !== false;
    }

    public function parseTaskSchema(): array
    {
        $ref = $this->extractRef();
        if (!$ref) {
            throw new \Exception("Could not locate UK ETA reference in PDF");
        }
        $name      = $this->extractName();
        $amount    = $this->extractAmount();
        $issueDate = $this->extractIssueDate();
        $worldpay  = $this->extractWorldPay();
        $expiry    = $this->extractExpiryDate();

        $task = [
            'additional_info'      => $this->buildAdditionalInfo($worldpay, $amount),
            'ticket_number'        => null,
            'gds_reference'        => $ref,
            'airline_reference'    => null,
            'status'               => 'issued',
            'supplier_status'      => 'pending',
            'refund_date'          => null,
            'void_date'            => null,
            // ETA payment currency varies (USD/GBP/EUR/AED depending on card
            // BIN). For foreign currency leave price/total null so the
            // controller's conversion path runs and stamps the KWD value.
            // The "approved" email states no fee at all — emit numeric 0
            // (store() requires a numeric price) and flag it in
            // additional_info; the "processing" email for the same reference
            // carries the real amount and creates the priced task when it
            // processes first.
            'price'                => $amount['amount'] === null ? 0
                                        : ($amount['ccy'] === 'KWD' ? $amount['amount'] : null),
            'currency'             => $amount['ccy'] ?? 'KWD',
            'exchange_currency'    => $amount['ccy'] ?? 'KWD',
            'original_price'       => $amount['ccy'] === 'KWD' ? null : $amount['amount'],
            'original_currency'    => $amount['ccy'] === 'KWD' ? null : $amount['ccy'],
            'total'                => $amount['amount'] === null ? 0
                                        : ($amount['ccy'] === 'KWD' ? $amount['amount'] : null),
            'original_total'       => $amount['ccy'] === 'KWD' ? null : $amount['amount'],
            'surcharge'            => 0,
            'penalty_fee'          => null,
            'tax'                  => 0,
            'taxes_record'         => null,
            'refund_charge'        => null,
            'reference'            => $ref,
            'original_ticket_number' => null,
            'original_reference'   => $worldpay,
            'created_by'           => null,
            'issued_by'            => null,
            'iata_number'          => null,
            'type'                 => 'visa',
            'agent_name'           => null,
            'agent_email'          => null,
            'agent_amadeus_id'     => null,
            'client_name'          => $name ?: 'UNKNOWN APPLICANT',
            'passenger_name'       => $name ?: 'UNKNOWN APPLICANT',
            'supplier_name'        => self::SUPPLIER_NAME,
            'supplier_country'     => self::SUPPLIER_COUNTRY,
            'cancellation_policy'  => 'ETA non-refundable per UK Home Office rules.',
            'venue'                => 'United Kingdom',
            'issued_date'          => $issueDate,
            // task list "Issued Date" column reads supplier_pay_date; mirror it.
            'supplier_pay_date'    => $issueDate,
            'is_exchanged'         => false,
            'task_flight_details'  => [],
            // UK Home Office ETA — standard fixed terms apply to every issued
            // ETA: multiple-entry, 180-day stays per visit. visa_type comes
            // from the email header. expiry_date isn't in the "processing"
            // email; user fills it in or the later "granted" email updates it.
            'task_visa_details'    => [[
                'visa_type'         => 'ETA',
                'application_number'=> $ref,
                'issuing_country'   => self::SUPPLIER_COUNTRY,
                'number_of_entries' => 'multiple',
                'stay_duration'     => 180,
                'expiry_date'       => $expiry,
            ]],
        ];
        return [$task];
    }

    private function extractRef(): ?string
    {
        // Three known patterns across the 3 email variants:
        //   "Your ETA reference number is 2021-2605-1540-2898."  (processing)
        //   "ETA reference number: 2021-2605-2615-6333"          (approved header)
        //   "ETA reference 2021-..."                              (some payment)
        if (preg_match('/ETA reference number\s*(?:is|:)?\s+(\d{4}-\d{4}-\d{4}-\d{4})/i', $this->text, $m)) {
            return $m[1];
        }
        if (preg_match('/ETA reference\s*:?\s+(\d{4}-\d{4}-\d{4}-\d{4})/i', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractName(): ?string
    {
        if (preg_match('/Dear\s+([A-Z][A-Za-z\.\s\-\']+?),/', $this->text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Extract payment amount + currency. The ETA system charges in whatever
     * currency the cardholder's bank uses, so the body says things like:
     *   "payment of 28.07 United States dollars"
     *   "payment of 16.00 Great British pounds"
     *   "You have been charged USD 28.07"
     *   "You have been charged GBP 16.00"
     * @return array{amount: ?float, ccy: ?string}
     */
    private function extractAmount(): array
    {
        // Long-form first ("payment of 28.07 United States dollars")
        $longForm = [
            'GBP' => 'Great British pounds?',
            'USD' => 'United States dollars?',
            'EUR' => 'Euros?',
            'AED' => 'UAE dirhams?',
            'SAR' => 'Saudi riyals?',
            'KWD' => 'Kuwaiti dinars?',
        ];
        foreach ($longForm as $ccy => $pattern) {
            if (preg_match("/payment of\\s+(\\d{1,5}(?:\\.\\d{1,2})?)\\s+{$pattern}/i", $this->text, $m)) {
                return ['amount' => (float)$m[1], 'ccy' => $ccy];
            }
        }
        // Short-form ("charged USD 28.07" or "USD 28.07")
        if (preg_match('/(?:charged\s+)?(USD|GBP|EUR|AED|SAR|KWD)\s+(\d{1,5}(?:\.\d{1,2})?)/i', $this->text, $m)) {
            return ['amount' => (float)$m[2], 'ccy' => strtoupper($m[1])];
        }
        return ['amount' => null, 'ccy' => null];
    }

    /**
     * Extract the "Your ETA is valid until: 26 MAY 2028" date from the
     * approval email. Returns Y-m-d or null.
     */
    private function extractExpiryDate(): ?string
    {
        if (preg_match('/valid until:?\s+(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})/i', $this->text, $m)) {
            $months = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06',
                       'JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
            $mon = strtoupper(substr($m[2], 0, 3));
            if (isset($months[$mon])) {
                return sprintf('%04d-%s-%02d', (int)$m[3], $months[$mon], (int)$m[1]);
            }
        }
        return null;
    }

    /**
     * Detect which of the three ETA email variants this body is, so the
     * controller can apply merge semantics rather than naive dedup.
     */
    public function detectVariant(): string
    {
        if (stripos($this->text, 'has been approved') !== false) return 'approved';
        if (stripos($this->text, 'submitted your ETA application') !== false) return 'processing';
        if (stripos($this->text, 'payment for your electronic travel') !== false) return 'payment';
        return 'unknown';
    }

    private function extractIssueDate(): ?string
    {
        // From email metadata "Date 2026-05-15T13:04:14Z"
        if (preg_match('/Date\s+(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/', $this->text, $m)) {
            return sprintf('%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
        }
        return null;
    }

    private function extractWorldPay(): ?string
    {
        if (preg_match('/(ETAWEB\d{8,12})/', $this->text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function buildAdditionalInfo(?string $worldpay, array $amount): string
    {
        $bits = [];
        if (!empty($amount['amount']) && !empty($amount['ccy']) && $amount['ccy'] !== 'KWD') {
            $bits[] = "Original: " . $amount['amount'] . ' ' . $amount['ccy'];
        }
        if ($amount['amount'] === null) {
            $bits[] = 'ETA fee not stated in this email (approved notice) — verify cost';
        }
        if ($worldpay) $bits[] = "WorldPay txn: $worldpay";
        return implode(' | ', $bits);
    }
}
