<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Transaction;
use Exception;
use Illuminate\Console\Command;

class UpdateTransactionDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-transaction-date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $transactions = Transaction::with('journalEntries')
            ->whereHas('journalEntries', function ($query) {
                $query->where('task_id', '!=', null);
            })
            ->get();
       
        foreach ($transactions as $transaction) {
             
            $issuedDate = $transaction->journalEntries->first()->task->issued_date ?? null;

            if ($issuedDate) {
                try{
                    $transaction->update(['transaction_date' => $issuedDate]);
                    $this->info("Transaction ID {$transaction->id} updated with date: {$issuedDate}");
                } catch (Exception $e) {
                    
                    $this->error("Failed to update transaction ID {$transaction->id}: " . $e->getMessage());
                    continue;
                }
                
            }
        }

        $journalEntries = JournalEntry::with('task')->whereNotNull('task_id')->get();

        foreach ($journalEntries as $entry) {
            // Update the transaction_date in transactions based on the task's issued_date
            if ($entry->task && $entry->task->issued_date) {
                $entry->transaction->update(['transaction_date' => $entry->task->issued_date]);
            }
        }
    }
}
