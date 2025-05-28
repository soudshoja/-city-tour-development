<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Credit;
use App\Models\InvoicePartial;
use App\Models\Transaction;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreditController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id == Role::ADMIN) {
            $allCreditRecords = Credit::with('client')
                ->orderBy('id', 'desc')
                ->get();
            $totalCredits = Credit::count();
            $totalCreditsAmount = Credit::sum('amount');

        } elseif ($user->role_id == Role::COMPANY) {
            $allCreditRecords = Credit::with('client')
                ->where('company_id', $user->company->id)
                ->orderBy('id', 'desc')
                ->get();
            $totalCredits = Credit::where('company_id', $user->company->id)->count();
            $totalCreditsAmount = Credit::where('company_id', $user->company->id)->sum('amount');

        } elseif ($user->role_id == Role::AGENT) {
            $allCreditRecords = Credit::with('client')
                ->where('client_id', $user->client->id)
                ->where('company_id', $user->company->id)
                ->orderBy('id', 'desc')
                ->get();
            $totalCredits = Credit::where('client_id', $user->client->id)
                ->where('company_id', $user->company->id)
                ->count();
            $totalCreditsAmount = Credit::where('client_id', $user->client->id)
                ->where('company_id', $user->company->id)
                ->sum('amount');
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('credits.index', compact('allCreditRecords','totalCredits','totalCreditsAmount'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'client_id' => 'required|exists:clients,id',
            'task_id' => 'nullable|exists:tasks,id',
            'type' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
        ]);

        Credit::create($request->all());

        return redirect()->route('credits.index')->with('success', 'Credit created successfully.');
    }

    public function filter(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $credits = Credit::where('client_id', $request->client_id)
            ->whereDate('created_at', '>=', $request->from)
            ->whereDate('created_at', '<=', $request->to)
            ->orderBy('id', 'desc')
            ->get(['created_at', 'type', 'description', 'amount']);

        return response()->json($credits->map(function ($credit) {
            return [
                'date' => $credit->created_at->format('Y-m-d'),
                'type' => $credit->type,
                'description' => $credit->description,
                'amount' => $credit->amount,
            ];
        }));
    }

    public function useCreditNow(Invoice $invoice, InvoicePartial $invoicePartial, $balanceCredit)
    {
        //dd($invoice, $invoicePartial, $balanceCredit);
        $client = $invoice->client;
        $currentClientCredit = Credit::getTotalCreditsByClient($client->id);

        if ($currentClientCredit <= 0) {
            return redirect()->back()->with('error', 'No client\'s credit available.');
        }

        $creditSubmit = Credit::create([
            'company_id'         => $client->agent->branch->company_id,
            'client_id'          => $client->id,
            'invoice_id'         => $invoice->id,
            'invoice_partial_id' => $invoicePartial->id,
            'type'               => 'Invoice',
            'description'        => 'Payment for ' . $invoice->invoice_number,
            'amount'             => -($balanceCredit),
        ]);

        if ($invoicePartial->amount <= $balanceCredit) {
            $invoicePartial->status = 'paid';
            $invoicePartial->save();   
        }

        // Check if all partials are marked as 'paid'
        $allPaid = InvoicePartial::where('invoice_id', $invoice->id)
            ->where('status', '!=', 'paid')
            ->doesntExist();

        // If all are paid, update the invoice status
        if ($allPaid) {
            $invoice->status = 'paid';
            $invoice->paid_date = Carbon::now();
            $invoice->save();
        }

        return redirect()->back()->with('success', 'Credit used for splitted invoice.');
    }



}
