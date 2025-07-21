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
                // Email the backup file
                $this->sendBackupEmail($filePath, $filename);

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

        Mail::raw('Please find attached your data-only database backup for ' . config('app.name') . '.', function ($message) use ($filePath, $filename, $recipient) {
            $message->to($recipient)
                    ->subject('Laravel Data-Only Database Backup: ' . date('Y-m-d'))
                    ->attach($filePath, [
                        'as' => $filename,
                        'mime' => 'application/sql',
                    ]);
        });

        $this->info("Data-only backup email sent to {$recipient}.");
    }
}