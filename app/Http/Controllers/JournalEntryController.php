<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index($transactionId)
    {
        $journalEntries = JournalEntry::with(['agent', 'account', 'task', 'transaction'])
            ->where('transaction_id', $transactionId)
            ->paginate(15);
        if (!$journalEntries) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        $journalEntries = $this->getJournalEntries($journalEntries);

        return view('journal_entries.index', compact('journalEntries', 'transactionId'));
    }

    public function show(Request $request, $accountId)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

        $account = Account::findOrFail($accountId);
        $openingBalance = (float) ($account->opening_balance ?? 0);

        $journalEntries = JournalEntry::with(['account', 'transaction', 'task', 'task.flightDetails', 'task.hotelDetails'])
            ->where('account_id', $accountId)
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->orderBy('transaction_date', 'asc')
            ->get();

        $journalEntries = $this->getJournalEntries($journalEntries, $openingBalance);

        return view('journal_entries.show', compact('journalEntries', 'dateFrom', 'dateTo', 'accountId', 'account', 'openingBalance'));
    }


    public function getJournalEntries($journalEntries, float $startingBalance = 0)
    {
        $assets = Account::where('name', 'Assets')->first();
        $liabilities = Account::where('name', 'Liabilities')->first();
        $equity = Account::where('name', 'Equity')->first();
        $income = Account::where('name', 'Income')->first();
        $expenses = Account::where('name', 'Expenses')->first();

        if (!$assets || !$liabilities || !$equity || !$income || !$expenses) {
            return redirect()->back()->with('error', 'One or more accounts not found');
        }

        $runningBalance = $startingBalance;
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
    
    public function exportPdf(Request $request, $accountId)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        // Fetch filtered entries
        $journalEntries = JournalEntry::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('journal_entries.pdf', [
            'journalEntries' => $journalEntries,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
        return $pdf->download('journal-entries-ledger.pdf');
    }
}
