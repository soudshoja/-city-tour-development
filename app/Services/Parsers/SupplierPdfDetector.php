<?php

namespace App\Services\Parsers;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Sniffs a PDF's text header to identify which supplier-specific parser
 * (if any) can handle it. Returns null if no parser knows the format —
 * caller should then fall back to AI.
 *
 * Add a new supplier by:
 *   1. Implementing App\Services\Parsers\<Supplier>Parser with:
 *        - public const SIGNATURE = 'distinct text from page 1'
 *        - public static function matches(string $text): bool
 *        - public function parseTaskSchema(): array
 *   2. Adding the class to $registered below.
 */
class SupplierPdfDetector
{
    /** @var array<int, class-string> */
    private static array $registered = [
        \App\Services\Parsers\GtsBedsParser::class,   // GTS Beds (gtsbeds.com) hotel voucher + invoice (auto-merged)
        \App\Services\Parsers\CebuPacificParser::class,   // Cebu Pacific (5J) Itinerary Receipt (PDF + HTML body)
        \App\Services\Parsers\TboHotelParser::class,   // TBO Holiday hotel invoice + voucher (auto-merged)
        \App\Services\Parsers\HeysamParser::class,   // Heysem Tourism hotel voucher + invoice (auto-merged)
        \App\Services\Parsers\VietnamAirlinesParser::class,   // Vietnam Airlines e-ticket receipt (custom-font cipher)
        \App\Services\Parsers\SalamAirParser::class,   // SalamAir (OV) "Your Booking Receipt"
        \App\Services\Parsers\AjetParser::class,   // AJet (VF) body-only "Ticket information" email
        \App\Services\Parsers\JazeeraAirwaysParser::class,
        \App\Services\Parsers\SmileHolidaysParser::class,
        \App\Services\Parsers\AccelyaNdcParser::class,   // Emirates NDC + Oman Airways NDC
        \App\Services\Parsers\TurkishNdcParser::class,   // Turkish Airlines NDC ("Order ID …")
        \App\Services\Parsers\MagicHolidaysParser::class,
        \App\Services\Parsers\SkyRoomsParser::class,
        \App\Services\Parsers\ChamWingsParser::class,
        \App\Services\Parsers\IndigoParser::class,
        \App\Services\Parsers\VfsGlobalParser::class,
        \App\Services\Parsers\AlmavivaItalyVisaParser::class,   // Almaviva Italian visa (Consortium) - filed under VFS Global
        \App\Services\Parsers\AiraloParser::class,   // Airalo (AirGSM) per-order eSIM invoice
        \App\Services\Parsers\LondonVisaParser::class,
        \App\Services\Parsers\FlyDubaiParser::class,
        \App\Services\Parsers\AirArabiaParser::class,
        \App\Services\Parsers\RateHawkHotelParser::class,   // RateHawk HOTEL invoice + voucher (auto-merged)
        \App\Services\Parsers\RateHawkParser::class,     // RateHawk air-travel booking confirmations (PDF + HTML body)
    ];

    /**
     * Try each registered parser. Return the class string of whichever matches,
     * or null if none recognised this PDF.
     */
    public static function detect(string $filePath): ?string
    {
        try {
            $parser = new PdfParser();
            // Only need page-1 text for fingerprinting — read whole doc anyway since
            // smalot's API doesn't expose a "first N pages" shortcut
            $text = $parser->parseFile($filePath)->getText();
        } catch (\Throwable $e) {
            return null;
        }

        foreach (self::$registered as $cls) {
            if (method_exists($cls, 'matches') && $cls::matches($text)) {
                return $cls;
            }
        }
        return null;
    }

    /**
     * Body-only counterpart to detect(): identify which parser can handle a raw
     * HTML email body (no PDF attachment). Only parsers that expose a
     * fromHtml() factory are eligible — matching runs on the flattened text so
     * the same matches() fingerprints used for PDFs apply unchanged.
     *
     * Returns the class string of the first matching HTML-capable parser, or
     * null if none recognise this body.
     */
    public static function detectHtml(string $html): ?string
    {
        $text = self::flattenHtml($html);

        foreach (self::$registered as $cls) {
            if (method_exists($cls, 'fromHtml')
                && method_exists($cls, 'matches')
                && $cls::matches($text)) {
                return $cls;
            }
        }
        return null;
    }

    /**
     * Flatten an HTML email body to the same plain-text shape the per-supplier
     * parsers' private htmlToText() produces, so detection fingerprints line up
     * with what the parser will see. Kept in sync with those parsers.
     */
    private static function flattenHtml(string $html): string
    {
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
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
