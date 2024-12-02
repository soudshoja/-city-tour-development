<?php

namespace App\Http\Traits;

use \Smalot\PdfParser\Parser;

trait Converter
{
    /**
     * Convert PDF to text
     *
     * @param string $filePath
     * @return string text content
     */
    private function pdfToText($filePath)
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }
}
