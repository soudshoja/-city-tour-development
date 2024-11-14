<?php

namespace App\Http\Controllers;

use App\Services\TextFileProcessor;

class FileController extends Controller
{
    protected $fileProcessor;

    public function __construct(TextFileProcessor $fileProcessor)
    {
        $this->fileProcessor = $fileProcessor;
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

    public function processFile($filePath)
    {
        try {

            // Call the service to process the file
            $data = $this->fileProcessor->readAndExtractData($filePath);

            // Return the processed data as JSON or handle it further as needed
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
