<?php

namespace App\Http\Traits;

use \Smalot\PdfParser\Parser;
use Spatie\PdfToImage\Pdf;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use Org_Heigl\Ghostscript\Ghostscript;

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

    /**
     * Convert PDF to image (Not yet working)
     * 
     * @param string $filePath
     * 
     * @return string image path
     */
    private function pdfToImage($filePath)
    {
        $pdf = new Pdf($filePath);
        $pdf->setOutputFormat('jpg')->saveImage($filePath);

        return $filePath . '.jpg';
    }

    /**
     * Process the image using OCR.space API
     * 
     * @param string $imagePath
     * 
     * @return array response
     */
    public function processImage(string $imagePath)
    {
        $apiKey = env('OCR_SPACE_API_KEY');

        // Make sure the API key exists
        if (!$apiKey) {
            return response()->json(['error' => 'API key is missing.'], 400);
        }

        // Check if the request has a file
        if ($imagePath !== null) {

            // Use GuzzleClient instead of Client to avoid conflict
            $client = new GuzzleClient();
            $url = 'https://api.ocr.space/parse/image';

            try {

                $response = $client->post($url, [
                    'headers' => [
                        'apikey' => $apiKey,  // Use the API key from .env
                    ],
                    'multipart' => [
                        [
                            'name' => 'file',
                            'contents' => fopen($imagePath, 'r'),
                            'filename' => 'image.jpg',
                        ],
                    ]
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                // Check if OCR was successful and parsed text is available
                if (isset($result['ParsedResults'][0]['ParsedText'])) {
                    return $result; // return the entire response array to be used later
                }

                return response()->json(['error' => 'OCR processing failed.'], 500);
            } catch (\Exception $e) {
                return response()->json(['error' => 'OCR processing failed: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'No file provided.'], 400);
        }
    }

}
