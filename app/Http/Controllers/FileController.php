<?php

namespace App\Http\Controllers;

use App\Services\TextFileProcessor;
use Exception;
use Spatie\PdfToImage\Pdf;

class FileController extends Controller
{
    protected $fileProcessor;

    public function __construct()
    {
    }

    public function saveFile($file)
    {
        try {
            // Save the file to the storage disk
            $filePath = $file->store('files');

            return $filePath;
            // Process the file
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function convertPdfToImage($file) 
    {
        try {
            // Convert the PDF file to an image
            $image = new Pdf($file->getRealPath());

            $image->saveImage('app/public/images');

            // dd($image);


            return $image;
        } catch(Exception $e)
        {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
