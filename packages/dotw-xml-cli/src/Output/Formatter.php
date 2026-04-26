<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Output;

/**
 * XML output formatting utilities.
 * All methods are static - no state.
 */
class Formatter
{
    /**
     * Pretty-print XML string with 2-space indentation.
     * Returns the original string if DOMDocument loading fails.
     */
    public static function prettyPrint(string $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;

        if (@$dom->loadXML($xml) === false) {
            return $xml;
        }

        $result = $dom->saveXML();
        if ($result === false) {
            return $xml;
        }

        // Strip the XML declaration for cleaner terminal output
        $declarationPattern = '/^<\?xml[^?]*\?>\n?/';
        return preg_replace($declarationPattern, '', $result) ?? $result;
    }

    /**
     * Convert XML string to JSON.
     * Uses SimpleXMLElement for conversion then json_encode.
     * Returns JSON string; returns '{}' on parse failure.
     */
    public static function toJson(string $xml): string
    {
        $sxe = @simplexml_load_string($xml);
        if ($sxe === false) {
            return json_encode(['error' => 'Failed to parse XML response'], JSON_PRETTY_PRINT) ?: '{}';
        }

        $json = json_encode($sxe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{}';
        }

        $decoded = json_decode($json, true);
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
