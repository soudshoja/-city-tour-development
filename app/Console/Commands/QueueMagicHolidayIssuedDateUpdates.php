<?php

namespace App\Console\Commands;

use App\Jobs\UpdateMagicHolidayIssuedDateJob;
use App\Models\Supplier;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueMagicHolidayIssuedDateUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'magic-holiday:queue-issued-date-updates 
                            {--batch-size=100 : Number of jobs to queue at once}
                            {--delay=0 : Delay in seconds between each job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue jobs to update issued_date for Magic Holiday hotel tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Queuing Magic Holiday issued dates update jobs...');

        // Get Magic Holiday supplier
        $supplier = Supplier::where('name', 'Magic Holiday')->first();
        if (!$supplier) {
            $this->error('Magic Holiday supplier not found');
            return 1;
        }

        // Get hotel tasks with null or potentially wrong issued_date
        $tasksQuery = Task::where('supplier_id', $supplier->id)
            ->where('type', 'hotel')
            ->whereNotNull('reference');

        $totalTasks = $tasksQuery->count();

        if ($totalTasks === 0) {
            $this->info('No tasks found that need issued_date updates');
            return 0;
        }

        $this->info("Found {$totalTasks} tasks to process");

        $batchSize = $this->option('batch-size');
        $delay = $this->option('delay');
        $queuedJobs = 0;

        // Process tasks in batches
        $tasksQuery->chunk($batchSize, function ($tasks) use (&$queuedJobs, $delay) {
            foreach ($tasks as $task) {
                // Dispatch job with delay to avoid overwhelming the API
                UpdateMagicHolidayIssuedDateJob::dispatch($task)
                    ->delay(now()->addSeconds($queuedJobs * $delay));
                
                $queuedJobs++;
                
                if ($queuedJobs % 50 === 0) {
                    $this->info("Queued {$queuedJobs} jobs so far...");
                }
            }
        });

        $this->info("Successfully queued {$queuedJobs} jobs for processing");
        $this->info("Jobs will be processed by the queue worker with {$delay} second intervals");
        
        Log::channel('magic_holidays')->info('Queued Magic Holiday issued date update jobs', [
            'total_jobs' => $queuedJobs,
            'batch_size' => $batchSize,
            'delay' => $delay
        ]);

        return 0;
    }
}
