<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Credit;
use App\Models\Transaction;
use App\Models\Role;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Currency;
use App\Models\Branch;
use App\Http\Controllers\JournalEntryController;
use App\Models\JournalEntry;
use App\Http\Controllers\Controller;
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
            return abort(403, 'Unauthorized action.');
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        $agents = Agent::all();
        $agentId = $agents->pluck('id')->toArray();
        $clients = Client::whereIn('agent_id', $agentId)->get();
        $invoices = Invoice::all();
        $currencies = Currency::all();

        return view('credits.index', compact('allCreditRecords', 'totalCredits', 'totalCreditsAmount', 'agents', 'clients', 'invoices', 'currencies'));
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

    public function creditTopup(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|exists:clients,id',
            'agent_id'     => 'required|exists:agents,id',
            'amount'        => 'required|numeric|min:0',
            'description'   => 'nullable|string|max:255',
            'invoice_id'    => 'nullable|exists:invoices,id',
            'account_id'    => 'nullable|exists:accounts,id',
        ]);

        $client = Client::with('agent')->findOrFail($request->client_id);
        $agent = Agent::with('branch.company')->findOrFail($request->agent_id);
        $topupBy = auth()->user()->getRoleNames()->first();

        DB::beginTransaction();

        try {
            Credit::create([
                'company_id'        => $agent->branch->company->id,
                'client_id'         => $client->id,
                'branch_id'         => $agent->branch->id,
                'type'              => 'Topup',
                'description'       => 'Manual Topup for ' . $client->name,
                'amount'            => $request->amount,
                'topup_by'          => ucfirst($topupBy),
            ]);

            Transaction::create([
                'branch_id'         => $agent->branch->id,
                'company_id'        => $agent->branch->company->id,
                'entity_id'         => $agent->branch->company->id,
                'entity_type'       => 'Company',
                'transaction_type'  => 'credit',
                'amount'            => $request->amount,
                'description'       => 'Company Advance to Client: ' . $client->name,
                'reference_type'    => 'Payment',
            ]);

            $transaction = Transaction::create([
                'branch_id'         => $agent->branch->id,
                'company_id'        => $agent->branch->company->id,
                'entity_id'         => $client->id,
                'entity_type'       => 'Client',
                'transaction_type'  => 'debit',
                'amount'            => $request->amount,
                'description'       => 'Client Credit of ' . $client->name,
                'reference_type'    => 'Payment',
            ]);

            $liabilitiesAccount = Account::where('name', 'Liabilities')
                ->where('company_id', $client->agent->branch->company->id)
                ->first();

            $clientAdvance = Account::where('name', 'Client')
                ->where('root_id', $liabilitiesAccount->id ?? null)
                ->where('company_id', $client->agent->branch->company->id)
                ->first();

            if ($clientAdvance) {
                JournalEntry::create([
                    'transaction_id'      => $transaction->id,
                    'branch_id'           => $agent->branch->id,
                    'company_id'          => $agent->branch->company->id,
                    'account_id'          => $clientAdvance->id,
                    'transaction_date'    => now(),
                    'description'         => 'Advance Payment for: ' . $client->name,
                    'debit'               => 0,
                    'credit'              => $request->amount,
                    'balance'             => $clientAdvance->actual_balance - $request->amount,
                    'name'                => $client->name,
                    'type'                => 'receivable',
                    'voucher_number'      => 'MTU-' . now()->timestamp,
                    'type_reference_id'   => $clientAdvance->id,
                ]);

                $clientAdvance->actual_balance -= $request->amount;
                $clientAdvance->save();
            }

            $receivableRoot = Account::where('name', 'Assets')
                ->where('company_id', $client->agent->branch->company->id)
                ->first();

            $clientReceivable = Account::where('name', 'Clients')
                ->where('root_id', $receivableRoot->id ?? null)
                ->where('company_id', $client->agent->branch->company->id)
                ->first();

            if ($clientReceivable) {
                JournalEntry::create([
                    'transaction_id'      => $transaction->id,
                    'branch_id'           => $agent->branch->id,
                    'company_id'          => $agent->branch->company->id,
                    'account_id'          => $clientReceivable->id,
                    'transaction_date'    => now(),
                    'description'         => 'Manual Topup Receivable: ' . $client->name,
                    'debit'               => $request->amount,
                    'credit'              => 0,
                    'balance'             => $clientReceivable->actual_balance + $request->amount,
                    'name'                => $client->name,
                    'type'                => 'receivable',
                    'voucher_number'      => 'MTU-' . now()->timestamp,
                    'type_reference_id'   => $clientReceivable->id,
                ]);

                $clientReceivable->actual_balance += $request->amount;
                $clientReceivable->save();
            }

            DB::commit();

            return redirect()->back()->with('success', 'Client credit successfully topped up.');
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error('Topup failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Topup failed. Please try again.');
        }
    }
}
