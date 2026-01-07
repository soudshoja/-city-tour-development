<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LinkIssuedToConfirmedTasks extends Command
{
    protected $signature = 'tasks:link-issued-to-confirmed 
                            {--company= : Filter by company ID}
                            {--dry-run : Run without making changes}
                            {--limit= : Limit the number of tasks to process}';

    protected $description = 'Link issued tasks to their corresponding confirmed tasks based on matching reference and company';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $companyId = $this->option('company');
        $limit = $this->option('limit');

        $this->info('Starting to link issued tasks to confirmed tasks...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $issuedTasksQuery = Task::where('status', 'issued')
            ->whereNull('original_task_id');

        if ($companyId) {
            $issuedTasksQuery->where('company_id', $companyId);
            $this->info("Filtering by company ID: {$companyId}");
        }

        if ($limit) {
            $issuedTasksQuery->limit((int) $limit);
            $this->info("Limiting to {$limit} tasks");
        }

        $issuedTasks = $issuedTasksQuery->get();
        $totalTasks = $issuedTasks->count();

        $this->info("Found {$totalTasks} issued tasks without original_task_id");

        if ($totalTasks === 0) {
            $this->info('No tasks to process.');
            return Command::SUCCESS;
        }

        $linked = 0;
        $skipped = 0;
        $errors = 0;
        $linkedTasks = [];

        $progressBar = $this->output->createProgressBar($totalTasks);
        $progressBar->start();

        foreach ($issuedTasks as $issuedTask) {
            try {
                $confirmedTask = Task::where('reference', $issuedTask->reference)
                    ->where('company_id', $issuedTask->company_id)
                    ->where('status', 'confirmed')
                    ->where('passenger_name', $issuedTask->passenger_name)
                    ->where('id', '!=', $issuedTask->id)
                    ->first();

                if ($confirmedTask) {
                    if (!$dryRun) {
                        $issuedTask->update(['original_task_id' => $confirmedTask->id]);
                    }

                    $linked++;
                    
                    $linkedTasks[] = [
                        'issued_id' => $issuedTask->id,
                        'issued_ref' => $issuedTask->reference,
                        'issued_passenger' => $issuedTask->passenger_name,
                        'confirmed_id' => $confirmedTask->id,
                        'confirmed_ref' => $confirmedTask->reference,
                        'confirmed_passenger' => $confirmedTask->passenger_name,
                    ];

                    Log::info('[TASK:LINK] Linked issued task to confirmed task', [
                        'issued_task_id' => $issuedTask->id,
                        'confirmed_task_id' => $confirmedTask->id,
                        'reference' => $issuedTask->reference,
                        'passenger_name' => $issuedTask->passenger_name,
                        'company_id' => $issuedTask->company_id,
                        'dry_run' => $dryRun,
                    ]);
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('[TASK:LINK] Error linking task', [
                    'issued_task_id' => $issuedTask->id,
                    'reference' => $issuedTask->reference,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if (!empty($linkedTasks)) {
            $this->info('=== Linked Tasks ===');
            $this->table(
                ['Issued ID', 'Issued Reference', 'Issued Passenger', 'Confirmed ID', 'Confirmed Reference', 'Confirmed Passenger'],
                $linkedTasks
            );
            $this->newLine();
        }

        $this->info('=== Summary ===');
        $this->info("Total processed: {$totalTasks}");
        $this->info("Linked: {$linked}");
        $this->info("Skipped (no matching confirmed task): {$skipped}");
        
        if ($errors > 0) {
            $this->error("Errors: {$errors}");
        }

        if ($dryRun && $linked > 0) {
            $this->newLine();
            $this->warn("This was a dry run. Run without --dry-run to apply changes.");
        }

        return Command::SUCCESS;
    }
}
