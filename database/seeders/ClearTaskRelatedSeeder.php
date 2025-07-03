<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ClearTaskRelatedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * @deprecated Use the command `php artisan tasks:clear-related-data` instead.
     * This seeder is kept for backward compatibility but the command provides
     * more selective deletion of only task-related records.
     */
    public function run(): void
    {
        $this->command->info('⚠️  This seeder is deprecated. Please use the command instead:');
        $this->command->info('   php artisan tasks:clear-related-data');
        $this->command->info('');
        
        if ($this->command->confirm('Do you want to run the improved command now?')) {
            $this->command->info('Running task-related data cleanup command...');
            Artisan::call('tasks:clear-related-data', ['--force' => true]);
            $this->command->info(Artisan::output());
        } else {
            $this->command->info('Operation cancelled. Please run the command manually when ready.');
        }
    }
}
