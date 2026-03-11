<?php

namespace App\Http\Traits;

use \Smalot\PdfParser\Parser;
use Spatie\PdfToImage\Pdf;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Org_Heigl\Ghostscript\Ghostscript;
use Intervention\Image\Facades\Image;
use setasign\Fpdi\Tcpdf\Fpdi;


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
        $pdfPath  = storage_path('app/public/' . $filePath);

        if (!file_exists($pdfPath )) {
            \Log::info('filePath', ['filePath' => $pdfPath ]);
            return;
        }


        $pdf = new Pdf($pdfPath );
        \Log::info('pdf', ['pdf' => $pdf]);
        // Convert the entire PDF to images
        $imageContent = $pdf->setPage(1)->getImage(); 

        //  // Resolve full file path if stored in the storage folder
        //  $filePath = storage_path('app/public/' . $filePath);
        //  \Log::info('PDF Path:', ['filePath' => $filePath]);
     
        //  // Check if the file exists
        //  if (!file_exists($filePath)) {
        //      throw new \Exception("PDF file does not exist at the specified path: {$filePath}");
        //  }
     
        //  // Output directory for the images
        //  $outputDirectory = storage_path('app/public/pdf_images');
        //  if (!file_exists($outputDirectory)) {
        //      mkdir($outputDirectory, 0755, true); // Create directory if it doesn't exist
        //  }
     
        //  $outputFiles = [];
     
        //  // Initialize FPDI instance
        //  $pdf = new Fpdi();
     
        //  // Set the source file
        //  $pageCount = $pdf->setSourceFile($filePath);
     
        //  for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        //      // Import the current page
        //      $tplIdx = $pdf->importPage($pageNumber);
        //      $pdf->AddPage();
        //      $pdf->useTemplate($tplIdx);
     
        //      // Render the current page to an image
        //      $outputPath = $outputDirectory . '/page_' . $pageNumber . '.jpg';
     
        //      // Output the PDF page as an image using TCPDF's built-in image output
        //      $pdf->Output($outputPath, 'F'); // Save to file
     
        //      $outputFiles[] = $outputPath;
        //  }
     
         return $imageContent; // Return array of generated image paths
     }

    /**
     * Process the image using OCR.space API
     * 
     * @param string $imagePath
     * 
     * @return array response
     */
    public function uploadFile(Request $request)
    {
        // Validate the file upload
        $request->validate([
            'file' => 'required|file'
        ]);
    
        // Get the uploaded file
        $file = $request->file('file');
    
        // Get the temporary file path
        $imagePath = $file->getRealPath();  // This returns the file path of the temp file
    
        // Log the path for debugging
        \Log::info('Temporary file path:', ['imagePath' => $imagePath]);
    
        // Call the OCR function
        $ocrResponse = $this->processImage($imagePath);
    
        return response()->json($ocrResponse);
    }
    
    public function processImage(string $imagePath)
    {
        $apiKey = env('OCR_SPACE_API_KEY');
        $maxFileSize = 1024 * 1024; // 1 MB in bytes
    
        // Make sure the API key exists
        if (!$apiKey) {
            return response()->json(['error' => 'API key is missing.'], 400);
        }
    
        Log::info('imagePath', ['imagePath' => $imagePath]);
    
        // Check if the file exists
        if (!file_exists($imagePath)) {
            return response()->json(['error' => 'File not found or not accessible.'], 400);
        }
    
        $fileSize = filesize($imagePath);
        Log::info('fileSize', ['fileSize' => $fileSize]);
        // Default $imagePath2 to the original imagePath
        $imagePath2 = $imagePath;
    
        // Check if the file exceeds the maximum size
        if ($fileSize > $maxFileSize) {
            Log::info('maxFileSize', ['maxFileSize' => $maxFileSize]);
            $compressedImagePath = storage_path('app/public/compressed_' . basename($imagePath));
            $maxWidth = 800;
            $maxHeight = 800;
        
            $imageType = mime_content_type($imagePath);
            switch ($imageType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                default:
                    return response()->json(['error' => 'Unsupported image type.'], 400);
            }
        
            list($originalWidth, $originalHeight) = getimagesize($imagePath);
            $scale = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = $originalWidth * $scale;
            $newHeight = $originalHeight * $scale;
        
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
            if ($imageType == 'image/jpeg') {
                imagejpeg($resizedImage, $compressedImagePath, 40);
            } elseif ($imageType == 'image/png') {
                $jpegPath = storage_path('app/public/compressed_' . basename($imagePath, '.png') . '.jpg');
                imagejpeg($resizedImage, $jpegPath, 50); // Convert PNG to JPEG
                $compressedImagePath = $jpegPath;
            }
        
            Log::info('Image compressed and resized:', [
                'originalSize' => $fileSize,
                'compressedSize' => filesize($compressedImagePath),
            ]);
        
            $imagePath2 = $compressedImagePath;
        
            imagedestroy($image);
            imagedestroy($resizedImage);
        
            // Validate final file size
            $finalFileSize = filesize($compressedImagePath);
            if ($finalFileSize > $maxFileSize) {
                return response()->json([
                    'error' => 'File size exceeds 1 MB limit even after compression and resizing.'
                ], 400);
            }
        }
        
    
        // Create a copy of the temporary file with a valid extension
        $imagePathWithExtension = storage_path('app/public/temp_image.jpg');
        copy($imagePath2, $imagePathWithExtension);
    
        Log::info('imagePath', ['imagePath' => $imagePathWithExtension]);
    
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
                        'contents' => fopen($imagePathWithExtension, 'r'),
                        'filename' => basename($imagePathWithExtension),
                    ],
                ]
            ]);
    
            $result = json_decode($response->getBody()->getContents(), true);
    
            // Log the raw OCR result for debugging
            Log::info('OCR Response:', ['ocrResponse' => $result]);
    
            // Check if OCR was successful and parsed text is available
            if (isset($result['ParsedResults'][0]['ParsedText'])) {
                return $result; // return the entire response array to be used later
            }
    
            return response()->json(['error' => 'OCR processing failed.'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'OCR processing failed: ' . $e->getMessage()], 500);
        }
    }
    
    
    }