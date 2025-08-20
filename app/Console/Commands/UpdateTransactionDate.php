<?php
 
namespace App\Console\Commands;
 
use App\Models\JournalEntry;
use App\Models\Supplier;
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
    protected $signature = 'app:update-transaction-date
                    { --supplierId= : The ID of the supplier to filter transactions.}
                    { --reference= : The reference to filter transactions within the supplier.}
    ';
   
 
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command updates the transaction date based on the task issued date or cancellation deadline for transactions linked to a specific supplier.';
 
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Validation: if reference is provided, supplierId must also be provided
        if ($this->option('reference') && !$this->option('supplierId')) {
            $this->error('Supplier ID is required when using the reference option.');
            return;
        }
 
        if (!$this->option('supplierId')) {
            $answer = $this->ask('You did not specify a supplier ID. Do you want to proceed with all suppliers? (yes/no)');
            if (strtolower($answer) !== 'yes') {
                $this->info('Operation cancelled.');
                return;
            }
        }
 
        $transactionQuery = Transaction::with('journalEntries')
            ->whereHas('journalEntries', function ($query) {
                $query->where('task_id', '!=', null);
            });
 
        if ($this->option('supplierId')) {
 
            $supplier = Supplier::find($this->option('supplierId'));
 
            if (!$supplier) {
                $this->error("Supplier with ID {$this->option('supplierId')} not found.");
                return;
            }
 
            $confirmationMessage = 'You are about to update transactions for supplier: ' . $supplier->name;
            if ($this->option('reference')) {
                $confirmationMessage .= ' with reference: ' . $this->option('reference');
            }
            $confirmationMessage .= '. Are you sure? (yes/no)';
 
            $answer = $this->ask($confirmationMessage);
            if (strtolower($answer) !== 'yes') {
                $this->info('Operation cancelled.');
                return;
            }
 
            $transactionQuery->whereHas('journalEntries.task', function ($taskQuery) {
                $taskQuery->where('supplier_id', $this->option('supplierId'));
            });
 
            // Add reference filtering if provided
            if ($this->option('reference')) {
                $transactionQuery->whereHas('journalEntries.task', function ($taskQuery) {
                    $taskQuery->where('reference', $this->option('reference'));
                });
            }
        }
 
        $transactions = $transactionQuery->get();
 
        foreach ($transactions as $transaction) {
             
            $task = $transaction->journalEntries->first()->task;
           
            if (!$task) {
                $this->error("Task not found for transaction ID {$transaction->id}");
                continue;
            }
           
            $issuedDate = $task->issued_date ?? null;
            $cancellationDeadline = $task->cancellation_deadline ?? null;
            $task_type = $task->type;
 
            if(!$task_type) {
                $this->error("Task type is not set for transaction ID {$transaction->id}");
                continue;
            }
 
            $supplier_pay_date = $issuedDate;
 
            if ($task_type === 'hotel' && $cancellationDeadline) {
                if ($cancellationDeadline <= $issuedDate) {
                    $supplier_pay_date = $issuedDate;
                } elseif ($cancellationDeadline > $issuedDate) {
                    $supplier_pay_date = $cancellationDeadline;
                }
 
            }
 
            $task->update(['supplier_pay_date' => $supplier_pay_date]);
 
            if ($supplier_pay_date) {
                try{
                    $transaction->update(['transaction_date' => $supplier_pay_date]);
                    $transaction->journalEntries->each(function ($entry) use ($supplier_pay_date) {
                        $entry->update(['transaction_date' => $supplier_pay_date]);
                        $this->info("Journal Entry ID {$entry->id} updated with date: {$supplier_pay_date}");  
                    });
                    $this->info("Transaction ID {$transaction->id} with task reference {$task->reference} updated with date: {$supplier_pay_date}");
                } catch (Exception $e) {
                   
                    $this->error("Failed to update transaction ID {$transaction->id}: " . $e->getMessage());
                    continue;
                }
               
            }
        }
 
        // $journalEntries = JournalEntry::with('task')->whereNotNull('task_id')->get();
 
        // foreach ($journalEntries as $entry) {
        //     // Update the transaction_date in transactions based on the task's issued_date
        //     if ($entry->task && $entry->task->issued_date) {
        //         $entry->transaction->update(['transaction_date' => $entry->task->issued_date]);
        //     }
        // }
    }
}
 