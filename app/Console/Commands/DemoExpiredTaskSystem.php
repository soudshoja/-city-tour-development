<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Company;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoExpiredTaskSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'demo:expired-task-system {--reset : Reset demo data}';

    /**
     * The console command description.
     */
    protected $description = 'Demonstrate the expired confirmed task processing system (converts expired confirmed tasks to void)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('reset')) {
            $this->resetDemoData();
            return 0;
        }

        $this->info('=== Expired Confirmed Task Processing System Demo ===');
        $this->line('');

        // Create demo data
        $this->createDemoData();

        // Show current state
        $this->showCurrentState();

        // Process expired tasks
        $this->line('');
        $this->info('Processing expired confirmed tasks...');
        $this->call('tasks:process-expired-confirmed');

        // Show updated state
        $this->line('');
        $this->info('Updated state after processing:');
        $this->showCurrentState();

        $this->line('');
        $this->info('Demo completed! Use --reset to clean up demo data.');

        return 0;
    }

    private function createDemoData()
    {
        $this->info('Creating demo data...');

        // Get existing company and supplier (first available)
        $company = Company::first();
        $supplier = Supplier::first();

        if (!$company || !$supplier) {
            $this->error('No company or supplier found. Please ensure you have existing data.');
            return;
        }

        $this->info("Using Company: {$company->name} (ID: {$company->id})");
        $this->info("Using Supplier: {$supplier->name} (ID: {$supplier->id})");

        // Create expired confirmed task that should become void
        Task::updateOrCreate(
            ['reference' => 'DEMO-EXPIRED-001', 'company_id' => $company->id],
            [
                'status' => 'confirmed',
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'type' => 'flight',
                'client_name' => 'John Doe',
                'total' => 100.00,
                'price' => 100.00,
                'expiry_date' => Carbon::now()->subHours(2), // Expired 2 hours ago
                'created_at' => Carbon::now()->subDays(3),
            ]
        );

        // Create another expired confirmed task that should become void
        Task::updateOrCreate(
            ['reference' => 'DEMO-EXPIRED-002', 'company_id' => $company->id],
            [
                'status' => 'confirmed',
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'type' => 'hotel',
                'client_name' => 'Jane Smith',
                'total' => 200.00,
                'price' => 200.00,
                'expiry_date' => Carbon::now()->subHour(), // Expired 1 hour ago
                'created_at' => Carbon::now()->subDays(2),
            ]
        );

        // Create non-expired confirmed task (should remain unchanged)
        Task::updateOrCreate(
            ['reference' => 'DEMO-UNCHANGED-001', 'company_id' => $company->id],
            [
                'status' => 'confirmed',
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'type' => 'flight',
                'client_name' => 'Bob Wilson',
                'total' => 150.00,
                'price' => 150.00,
                'expiry_date' => Carbon::now()->addDays(1), // Expires tomorrow
                'created_at' => Carbon::now()->subDay(),
            ]
        );

        // Create already issued task (should be ignored by the process)
        Task::updateOrCreate(
            ['reference' => 'DEMO-ALREADY-ISSUED', 'company_id' => $company->id],
            [
                'status' => 'issued',
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'type' => 'visa',
                'client_name' => 'Alice Brown',
                'total' => 75.00,
                'price' => 75.00,
                'expiry_date' => Carbon::now()->subHours(3), // Already expired but issued
                'issued_date' => Carbon::now()->subDay(),
                'created_at' => Carbon::now()->subDays(2),
            ]
        );

        $this->info('Demo data created successfully.');
    }

    private function showCurrentState()
    {
        $tasks = Task::where('reference', 'like', 'DEMO-%')
            ->orderBy('reference')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No demo tasks found.');
            return;
        }

        $this->table(
            ['Reference', 'Status', 'Type', 'Client', 'Price', 'Expiry Date', 'Expired?'],
            $tasks->map(function ($task) {
                return [
                    $task->reference,
                    $task->status,
                    $task->type,
                    $task->client_name,
                    '$' . number_format($task->total, 2),
                    $task->expiry_date ? $task->expiry_date->format('Y-m-d H:i') : 'N/A',
                    $task->expiry_date && $task->expiry_date->isPast() ? '✓' : '✗',
                ];
            })->toArray()
        );
    }

    private function resetDemoData()
    {
        $this->info('Resetting demo data...');
        
        Task::where('reference', 'like', 'DEMO-%')
            ->forceDelete();
            
        $this->info('Demo data reset successfully.');
    }
}
