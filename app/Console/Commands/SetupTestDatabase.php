<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupTestDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:setup-db {--force : Force recreation of test database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the test database for running tests safely';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testDbName = env('DB_TEST_DATABASE', 'city_tour_test');
        $mainDbName = env('DB_DATABASE', 'city_tour');
        
        // Environment safety checks
        $environment = app()->environment();
        
        if ($environment === 'production') {
            $this->error('🚫 This command is blocked in PRODUCTION environment!');
            $this->newLine();
            $this->info('For production servers:');
            $this->info('1. Create test database manually if needed');
            $this->info('2. Or better yet - don\'t run tests on production');
            $this->info('3. Use CI/CD pipeline for testing before deployment');
            return 1;
        }

        if ($environment === 'staging') {
            $this->warn('⚠️  Running on STAGING environment');
            if (!$this->confirm('Are you sure you want to set up test database on staging?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Warning about test database
        if ($testDbName === $mainDbName) {
            $this->error('Test database name cannot be the same as main database!');
            $this->error('Please set DB_TEST_DATABASE in your .env file to a different name.');
            return 1;
        }

        $this->info("Setting up test database: {$testDbName}");
        $this->info("Main database: {$mainDbName} (will NOT be affected)");
        $this->info("Environment: {$environment}");

        // Create test database if it doesn't exist
        try {
            $pdo = new \PDO(
                "mysql:host=" . env('DB_HOST', '127.0.0.1') . ";port=" . env('DB_PORT', '3306'),
                env('DB_USERNAME', 'root'),
                env('DB_PASSWORD', '')
            );
            
            if ($this->option('force')) {
                $this->warn("Force flag detected. Dropping existing test database...");
                $pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
            }
            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDbName}`");
            $this->info("✓ Test database '{$testDbName}' created/verified");
            
        } catch (\Exception $e) {
            $this->error("Failed to create test database: " . $e->getMessage());
            return 1;
        }

        // Show current configuration
        $this->newLine();
        $this->info('Current Configuration:');
        $this->table(['Setting', 'Value'], [
            ['Main Database', $mainDbName],
            ['Test Database', $testDbName],
            ['Environment', app()->environment()],
            ['Default Connection', config('database.default')],
            ['Test Connection', 'mysql_testing (used during tests)'],
        ]);

        $this->newLine();
        $this->info('✅ Test database setup complete!');
        $this->info('You can now run tests safely with: php artisan test');
        $this->info('Your main database will never be affected by tests.');

        return 0;
    }
}
