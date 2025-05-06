<?php

namespace App\Console\Commands;

use App\AI\AIManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
// If your AI tool is a class or service, you might need to import it here
// use App\Services\YourAiProcessingService;

class ProcessAirFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'air:process-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scans the root air-files directory for new AIR files, processes them using existing logic, and moves them.';

    protected $airFilesPath;
    protected $processedFilesPath;
    // Optional: If your AI tool needs to be instantiated
    protected $aiManager;

    public function __construct(AIManager $aiManager)
    {
        parent::__construct();

        $this->airFilesPath = base_path('air-files');
        $this->processedFilesPath = storage_path('app/air_files_processed');
        $this->aiManager = $aiManager;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting AIR file processing from root/air-files directory...');
        Log::info('AIR File Processing: Service started.');

        // Ensure the source directory exists (it should, as files are pushed there)
        if (!File::isDirectory($this->airFilesPath)) {
            $this->error("Source directory not found: {$this->airFilesPath}");
            Log::error("AIR File Processing: Source directory {$this->airFilesPath} not found.");
            File::makeDirectory($this->airFilesPath, 0755, true, true); // Optionally create it
            $this->info("Created source directory: {$this->airFilesPath}, please ensure files are pushed here.");
            return 1;
        }


        if (!File::isDirectory($this->processedFilesPath)) {
            File::makeDirectory($this->processedFilesPath, 0755, true, true);
            $this->info("Created processed files directory: {$this->processedFilesPath}");
        }

        // The example filename 'AIR-BLK206;...' doesn't have a typical extension.
        // File::files() gets all files. You might want to add more specific filtering
        // if there are other types of files in this directory you want to ignore.
        $filesToProcess = File::files($this->airFilesPath);

        if (empty($filesToProcess)) {
            $this->info('No new files found in air-files directory to process.');
            Log::info('AIR File Processing: No new files found.');
            return 0; // Success
        }

        $this->info(count($filesToProcess) . ' file(s) found in air-files.');

        foreach ($filesToProcess as $file) { // $file is an SplFileInfo object
            $filePath = $file->getRealPath();
            $fileName = $file->getFilename();

            $this->info("Processing file: {$fileName}");
            Log::info("AIR File Processing: Starting file {$fileName}");

            try {
                $fileContent = File::get($filePath);

                // Call your existing AI processing logic here
                $extractedData = $this->processWithAiTool($fileContent, $fileName);

                if ($extractedData === null || (is_array($extractedData) && empty($extractedData))) {
                    Log::warning("AIR File Processing: AI tool returned no data or indicated an issue for {$fileName}. Skipping move, investigate.");
                    $this->warn("AI tool returned no data for {$fileName}. File will remain in place.");
                    // Decide if you want to move it to an error folder or leave it.
                    // For now, we'll just log and skip moving.
                    continue;
                }

                // Assuming your AI tool handles the "extraction" and what to do with the data.
                // If you need to save something specific to your Laravel app's database based on the AI's output:
                // Example:
                // YourModel::create([
                //    'filename' => $fileName,
                //    'processed_data_summary' => $extractedData['summary'] ?? 'N/A',
                //    'status' => $extractedData['status'] ?? 'processed',
                // ]);
                Log::info("AIR File Processing: File {$fileName} processed by AI tool. Output summary (if any): " . (is_array($extractedData) ? json_encode($extractedData) : $extractedData));
                $this->info("File {$fileName} processed successfully by AI tool.");

                // Move the original file to the processed directory
                $destinationPath = $this->processedFilesPath . '/' . $fileName;
                File::move($filePath, $destinationPath);

                $this->info("Successfully moved {$fileName} to {$this->processedFilesPath}.");
                Log::info("AIR File Processing: Successfully moved {$fileName} to {$destinationPath}.");

            } catch (\Exception $e) {
                $this->error("Error processing file {$fileName}: " . $e->getMessage());
                Log::error("AIR File Processing: Error processing file {$fileName}. Error: " . $e->getMessage(), [
                    'file' => $fileName,
                    'trace' => $e->getTraceAsString()
                ]);
                // Optional: Move to an error directory
                // $errorPath = storage_path('app/air_files_error');
                // if (!File::isDirectory($errorPath)) { File::makeDirectory($errorPath, 0755, true, true); }
                // File::move($filePath, $errorPath . '/' . $fileName);
            }
        }

        $this->info('AIR file processing finished.');
        Log::info('AIR File Processing: Service finished.');
        return 0; // Command success
    }

    /**
     * Placeholder method to integrate your AI processing logic.
     *
     * @param string $fileContent The content of the AIR file.
     * @param string $fileName The name of the file being processed.
     * @return mixed The result from your AI processing tool.
     * Could be an array of extracted data, a status string, etc.
     * Return null or an empty array if processing fails or yields no data.
     */
    protected function processWithAiTool(string $fileContent, string $fileName)
    {
        $this->info("Handing over content of {$fileName} to AI processing tool...");

        $response  = $this->aiManager->extractAirFiles($fileContent);

        if ($response['status'] !== 'success') {
            Log::error("AI Tool processing failed for {$fileName}: " . $response['message']);
            return null; // or handle as needed
        }

        Log::info("AI Tool processing response for {$fileName}: " . json_encode($response));

        Log::info("AI Tool processing for {$fileName} with content: " . substr($fileContent, 0, 200) . "...");

        // Simulate successful processing with some dummy data
        // Replace this with your actual AI tool call.
        // The structure of $processedData should match what your AI tool returns
        // and what your application expects.
        $processedData = [
            'status' => 'success',
            'message' => "Successfully processed {$fileName} using AI.",
            'reference_id' => uniqid(),
            'original_filename' => $fileName,
            'extracted_pnr' => 'XYZ123', // Example field
            'passenger_count' => 1       // Example field
        ];
        // Based on your example file, your AI tool likely extracts many specific fields.
        // Ensure $processedData captures that.

        // If your AI tool itself logs extensively or handles its own errors,
        // you might just return its direct output or a status indicator.
        return $processedData;
    }
}