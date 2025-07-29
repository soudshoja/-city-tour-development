<?php

use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            $table->date('transaction_date')->nullable()->before('created_at')->comment("Date of the transaction follow task's issued date");

        });

        // Update existing records with the date from issued_date field in tasks table
        $transactions = Transaction::with('journalEntries')
            ->whereHas('journalEntries', function ($query) {
                $query->where('task_id', '!=', null);
            })
            ->get();

        foreach ($transactions as $transaction) {
            // get issued_date from task in first journal entry
            $issuedDate = $transaction->journalEntries->first()->task->issued_date ?? null;

            if ($issuedDate) {
                // Update the transaction_date with issued_date
                $transaction->update(['transaction_date' => $issuedDate]);
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

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_date');
        });
    }
};
