<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Exception;

class BackupDataOnly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup-data-only'; // Unique command name

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup only database data (no structure) and email it.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting database data-only backup...');

        try {
            // Get database credentials from Laravel's config
            $databaseConfig = config('database.connections.' . config('database.default'));
            $dbHost = $databaseConfig['host'];
            $dbPort = $databaseConfig['port'] ?? 3306;
            $dbUser = $databaseConfig['username'];
            $dbPass = $databaseConfig['password'];
            $dbName = $databaseConfig['database'];

            // Determine mysqldump path (adjust if different on your cPanel server)
            // It's good practice to set this in your .env
            $mysqldumpPath = env('MYSQLDUMP_PATH', '/usr/bin/mysqldump');

            if (!file_exists($mysqldumpPath)) {
                throw new Exception("mysqldump binary not found at '{$mysqldumpPath}'. Please ensure it's installed and the path in .env is correct.");
            }

            // Define backup filename and path
            $backupDir = 'data-only-backups'; // A dedicated directory for these backups
            $filename = 'data_only_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filePath = storage_path('app/' . $backupDir . '/' . $filename);

            // Ensure the backup directory exists
            Storage::disk('local')->makeDirectory($backupDir); // Uses default local disk

            // Build the mysqldump command
            // --no-create-info (-t): Do not write CREATE TABLE statements.
            // --compact: Produce less verbose output (optional, but good for logs).
            // --skip-triggers: Do not dump triggers (optional, but common for data-only).
            $command = sprintf(
                '%s --host=%s --port=%s --user=%s --password=%s --no-create-info --compact --skip-triggers %s > %s',
                escapeshellarg($mysqldumpPath),
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($filePath)
            );

            // Execute the command
            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $this->info("Data-only backup successful: {$filename}");
                
                // Compress the backup file
                $compressedFilePath = $this->compressBackupFile($filePath, $filename);
                
                // Email the compressed backup file
                $this->sendBackupEmail($compressedFilePath, basename($compressedFilePath));

            } else {
                $errorMessage = implode("\n", $output);
                throw new Exception("mysqldump failed with exit code {$returnVar}: {$errorMessage}");
            }

        } catch (Exception $e) {
            $this->error('Data-only backup failed: ' . $e->getMessage());
            // Send a failure notification email
            Mail::raw('Data-only database backup failed for ' . config('app.name') . ': ' . $e->getMessage(), function ($message) {
                $message->to(env('MAIL_TO_DATA_ONLY_BACKUP', 'your-alert-email@example.com'))
                        ->subject('Laravel Data-Only Backup Failed');
            });
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Compress the backup file using gzip
     *
     * @param string $filePath The original backup file path
     * @param string $filename The original filename
     * @return string The compressed file path
     */
    protected function compressBackupFile($filePath, $filename)
    {
        $compressedFilePath = $filePath . '.gz';
        
        // Read the original file and compress it
        $originalContent = file_get_contents($filePath);
        $compressedContent = gzencode($originalContent, 9); // Maximum compression level
        
        file_put_contents($compressedFilePath, $compressedContent);
        
        // Remove the original uncompressed file to save space
        unlink($filePath);
        
        $originalSize = strlen($originalContent);
        $compressedSize = filesize($compressedFilePath);
        $compressionRatio = round((1 - ($compressedSize / $originalSize)) * 100, 2);
        
        $this->info("File compressed: {$this->formatBytes($originalSize)} → {$this->formatBytes($compressedSize)} ({$compressionRatio}% reduction)");
        
        return $compressedFilePath;
    }

    /**
     * Format bytes into human readable format
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Sends the backup SQL file via email.
     *
     * @param string $filePath The absolute path to the backup file.
     * @param string $filename The original filename of the backup.
     * @return void
     */
    protected function sendBackupEmail($filePath, $filename)
    {
        $recipient = env('MAIL_TO_DATA_ONLY_BACKUP', 'your-email@example.com');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->warn("Invalid email recipient for data-only backup: '{$recipient}'. Skipping email.");
            return;
        }

        // Check if file exists and is readable before attaching
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("Data-only backup file not found or not readable at '{$filePath}'. Cannot attach to email.");
            Mail::raw("Data-only backup successful but file '{$filename}' was not found or readable for email attachment.", function ($message) use ($recipient) {
                $message->to($recipient)
                        ->subject('Laravel Data-Only Backup: File Missing');
            });
            return;
        }

        // Check file size (most email providers have 25MB limit)
        $fileSize = filesize($filePath);
        $maxEmailSize = 20 * 1024 * 1024; // 20MB to be safe
        
        if ($fileSize > $maxEmailSize) {
            $this->warn("Backup file is too large ({$this->formatBytes($fileSize)}) to send via email. Sending notification only.");
            Mail::raw(
                "Data-only backup was successful, but the file ({$this->formatBytes($fileSize)}) is too large to email.\n\n" .
                "The backup file '{$filename}' is stored on the server at: {$filePath}\n\n" .
                "Please download it manually or consider using a file sharing service.",
                function ($message) use ($recipient) {
                    $message->to($recipient)
                            ->subject('Laravel Data-Only Backup: File Too Large - ' . date('Y-m-d'));
                }
            );
            $this->info("Large file notification sent to {$recipient}.");
            return;
        }

        Mail::raw(
            "Please find attached your compressed data-only database backup for " . config('app.name') . ".\n\n" .
            "File size: {$this->formatBytes($fileSize)}\n" .
            "To restore, decompress the .gz file first, then import the SQL file.",
            function ($message) use ($filePath, $filename, $recipient) {
                $message->to($recipient)
                        ->subject('Laravel Data-Only Database Backup: ' . date('Y-m-d'))
                        ->attach($filePath, [
                            'as' => $filename,
                            'mime' => 'application/gzip',
                        ]);
            }
        );

        $this->info("Data-only backup email sent to {$recipient}.");
    }
}