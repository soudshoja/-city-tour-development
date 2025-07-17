<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index($transactionId)
    {
        $journalEntries = JournalEntry::where('transaction_id', $transactionId)->get();

        if (!$journalEntries) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        $journalEntries = $this->getJournalEntries($journalEntries);

        return view('journal_entries.index', compact('journalEntries', 'transactionId'));
    }

    public function show(Request $request, $accountId)
    {
        // Get date filters from request or fallback to current month
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

        // Fetch journal entries for this account and date range
        $journalEntries = JournalEntry::with(['account', 'transaction'])
            ->where('account_id', $accountId)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->orderBy('created_at')
            ->get();

        // Optional: apply custom transformation (like calculating running balance)
        $journalEntries = $this->getJournalEntries($journalEntries);

        // Return the Blade view with all required data
        return view('journal_entries.show', compact('journalEntries', 'dateFrom', 'dateTo', 'accountId'));
    }


    public function getJournalEntries($journalEntries)
    {
        $assets = Account::where('name', 'Assets')->first();
        $liabilities = Account::where('name', 'Liabilities')->first();
        $equity = Account::where('name', 'Equity')->first();
        $income = Account::where('name', 'Income')->first();
        $expenses = Account::where('name', 'Expenses')->first();

        if (!$assets || !$liabilities || !$equity || !$income || !$expenses) {
            return redirect()->back()->with('error', 'One or more accounts not found');
        }

        $runningBalance = 0;
        foreach ($journalEntries as $journalEntry) {
            if ($journalEntry->account->root_id == $assets->id) {
                $runningBalance += $journalEntry->debit - $journalEntry->credit;
            } elseif ($journalEntry->account->root_id == $liabilities->id) {
                $runningBalance += $journalEntry->credit - $journalEntry->debit;
            } elseif ($journalEntry->account->root_id == $equity->id) {
                $runningBalance += $journalEntry->credit - $journalEntry->debit;
            } elseif ($journalEntry->account->root_id == $income->id) {
                $runningBalance += $journalEntry->credit - $journalEntry->debit;
            } elseif ($journalEntry->account->root_id == $expenses->id) {
                $runningBalance += $journalEntry->debit - $journalEntry->credit;
            } else {
                return redirect()->back()->with('error', 'Invalid account type');
            }
            $journalEntry->running_balance = $runningBalance;
        }

        return $journalEntries;
    }
}
