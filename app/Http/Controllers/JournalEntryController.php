<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index($transactionId)
    {
        $journalEntries = JournalEntry::where('transaction_id', $transactionId)->get();
        return view('journal_entries.index', compact('journalEntries','transactionId'));      
    }

    public function show($accountId)
    {
        $journalEntries = JournalEntry::with('transaction')->where('account_id', $accountId)->get();

        if (!$journalEntries) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        return view('journal_entries.show', compact('journalEntries'));
    }
}
