<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Task;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class RefundController extends Controller
{

    public function index()
    {
        if (Auth::user()->role->name == 'company') {
            $totalRefunds = Refund::where('company_id', Auth::user()->company->id)->count();
            $refunds = Refund::where('company_id', Auth::user()->company->id)->get();
        } elseif (Auth::user()->role->name == 'branch') {
            $totalRefunds = Refund::where('branch_id', Auth::user()->branch->id)->count();
            $refunds = Refund::where('branch_id', Auth::user()->branch->id)->get();
        } else {
            $totalRefunds = 0;
            $refunds = collect(); 
        }
        return view('refunds.index', compact('refunds', 'totalRefunds'));
    }
    
    public function create(Invoice $invoice)
    {
        if ($invoice->status !== 'paid') {
            abort(403, 'Refunds are only allowed for paid invoices.');
        }
    
        $coaAccounts = Account::where('account_type', 'Asset')->get();
    
        // Get task IDs from invoice details
        $taskIds = $invoice->invoiceDetails()->pluck('task_id')->filter()->unique();
    
        // Fetch tasks using those IDs
        $tasks = Task::whereIn('id', $taskIds)->first();

        dd($tasks);
    
        return view('refunds.create', compact('invoice', 'coaAccounts', 'tasks'));
    }

    public function store(Request $request, Invoice $invoice)
    {   
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $invoice->amount],
            'reason' => ['required', 'string'],
            'method' => ['required', 'in:cash,bank,adjustment'],
            'account_id' => ['required', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
        ]);
    
        $refund = Refund::create([
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'agent_id' => $invoice->agent_id,
            'amount' => $request->amount,
            'reason' => $request->reason,
            'method' => $request->method,
            'account_id' => $request->account_id,
            'date' => $request->date,
            'reference' => $request->reference,
            'status' => 'approved', 
            'created_by' => auth()->user->id(),
        ]);
    
        // Journal entries...
        JournalEntry::create([
            'transaction_date' => $request->date,
            'account_id' => $invoice->account_id, // Receivable
            'debit' => $request->amount,
            'credit' => 0,
            'voucher_number' => $refund->id,
            'name' => 'Refund',
            'type' => 'refund',
            'invoice_id' => $invoice->id,
        ]);
    
        JournalEntry::create([
            'transaction_date' => $request->date,
            'account_id' => $request->account_id, // Cash/Bank/Other
            'debit' => 0,
            'credit' => $request->amount,
            'voucher_number' => $refund->id,
            'name' => 'Refund',
            'type' => 'refund',
            'invoice_id' => $invoice->id,
        ]);
    
        return redirect()->route('refunds.list')->with('success', 'Refund processed successfully.');

        
    }
    
}
