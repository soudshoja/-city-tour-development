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
}
