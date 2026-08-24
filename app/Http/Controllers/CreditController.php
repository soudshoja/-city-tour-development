<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
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
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $allCreditRecords = Credit::with('client');

        if ($user->role_id == Role::ADMIN) {

            $allCreditRecords = $allCreditRecords; // this may seem redundant, but it allows for future modifications if needed

        } elseif ($user->role_id == Role::COMPANY) {

            $allCreditRecords = $allCreditRecords->where('company_id', $user->company->id);

        } elseif ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        if($request->has('search')) {
            $search = $request->input('search');
            $allCreditRecords = $allCreditRecords->where(function ($query) use ($search) {

                $searchTerm = '%' . strtolower($search) . '%';

                $query->whereHas('client', function ($q) use ($searchTerm) {
                    $q->where('first_name', 'like', $searchTerm)
                        ->orWhere('middle_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('phone', 'like', $searchTerm)
                        ->orWhereHas('agent', function ($q) use ($searchTerm) {
                            $q->where('name', 'like', $searchTerm)
                                ->orWhere('amadeus_id', 'like', $searchTerm)
                                ->orWhere('email', 'like', $searchTerm)
                                ->orWhere('phone_number', 'like', $searchTerm);
                        });
                    
                })->orWhere('description', 'like', $searchTerm);
            });
        }

        $totalCredits = $allCreditRecords->count();
        $totalCreditsAmount = $allCreditRecords->sum('amount');

        $allCreditRecords = $allCreditRecords
            ->orderBy('created_at', 'desc')
            ->paginate(20);

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
        // This endpoint is only ever called via fetch() from clients/index.blade.php
        // (a plain fetch() sends neither X-Requested-With nor an Accept: */json
        // header, so Illuminate\Http\Request::expectsJson() is false here).
        // $request->validate() would therefore treat a validation failure as a
        // normal browser request and 302-redirect back() instead of returning
        // JSON — and since this route is gated module:crm while credits.index
        // (a common "back" target) is gated module:accounting, that redirect
        // could 404 for a package client with CRM but not accounting (R1/CRM-3).
        // Build the validator manually so this endpoint ALWAYS answers in JSON,
        // success or failure, and never redirects anywhere.
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid filter parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

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

    /**
     * Apply the client's available credit balance against one existing,
     * still-unpaid invoice split ("Pay Now with Credit" on invoice
     * show/split views).
     *
     * Route: POST credits/use-credit-now/{invoice}/{invoicePartial}/{balanceCredit}
     * (module:payment_gateway — see routes/web.php CREDITS group / R1).
     *
     * This is deliberately a different operation from
     * InvoiceController::createInvoiceLinkWithClientCredit (route
     * invoice.client-credit): that one decides full-vs-split for a fresh
     * invoice and creates brand-new InvoicePartial rows from scratch.
     * This one pays down ONE already-existing split by its own ID, so it
     * cannot double-create partials on an invoice that already has some.
     *
     * $balanceCredit from the URL is never trusted for money math — it is
     * only what the confirmation modal *showed* the user; the amount
     * actually applied is recomputed here from the live credit balance and
     * the split's live remaining amount.
     */
    public function useCreditNow(Request $request, $invoice, $invoicePartial, $balanceCredit)
    {
        $partial = InvoicePartial::with('invoice.agent.branch.company', 'client')->find($invoicePartial);

        if (!$partial || !$partial->invoice || (int) $partial->invoice_id !== (int) $invoice) {
            return redirect()->back()->with('error', 'Invoice split not found.');
        }

        // Tenant isolation: only the acting user's own company may draw
        // down credit against one of its own invoice splits.
        $companyId = getCompanyId(Auth::user());
        abort_unless(
            $companyId && $partial->invoice->agent?->branch?->company_id === $companyId,
            403,
            'Unauthorized: this invoice split does not belong to your company.'
        );

        if ($partial->status !== 'unpaid') {
            return redirect()->back()->with('error', 'This invoice split is not open for payment.');
        }

        $client = $partial->client;
        $creditBalance = $client ? Credit::getTotalCreditsByClient($client->id) : 0;

        if ($creditBalance <= 0) {
            return redirect()->back()->with('error', 'Client has no available credit balance.');
        }

        $applyAmount = min((float) $partial->amount, (float) $creditBalance);

        if ($applyAmount <= 0) {
            return redirect()->back()->with('error', 'No credit is available to apply.');
        }

        $invoiceModel = $partial->invoice;

        DB::beginTransaction();
        try {
            Credit::create([
                'company_id'         => $companyId,
                'branch_id'          => $invoiceModel->agent?->branch_id,
                'client_id'          => $client->id,
                'invoice_id'         => $partial->invoice_id,
                'invoice_partial_id' => $partial->id,
                'type'               => Credit::INVOICE,
                'amount'             => -$applyAmount,
                'description'        => "Client credit applied to split of invoice {$partial->invoice_number}",
            ]);

            $remaining = round($partial->amount - $applyAmount, 2);

            if ($remaining <= 0) {
                $partial->status = 'paid';
                $partial->payment_gateway = 'Credit';
            } else {
                // Partial credit only: shrink the outstanding balance on
                // this split and leave it open for the rest.
                $partial->amount = $remaining;
            }
            $partial->save();

            // Keep the parent invoice's own status in sync with its splits,
            // the same rule InvoiceController::savePartial() uses.
            $hasUnpaid = $invoiceModel->invoicePartials()->where('status', 'unpaid')->exists();
            $hasPaid = $invoiceModel->invoicePartials()->where('status', 'paid')->exists();

            if ($hasPaid && $hasUnpaid) {
                $invoiceModel->status = 'partial';
            } elseif ($hasPaid && !$hasUnpaid) {
                $invoiceModel->status = 'paid';
                $invoiceModel->paid_date = $invoiceModel->paid_date ?? now();
            }
            $invoiceModel->save();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('useCreditNow failed: ' . $e->getMessage(), [
                'invoice_partial_id' => $invoicePartial,
                'requested_balance_credit' => $balanceCredit,
            ]);

            return redirect()->back()->with('error', 'Failed to apply credit. Please try again.');
        }

        return redirect()->back()->with('success', 'Client credit applied to this invoice split.');
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
                'description'       => 'Manual Topup for ' . $client->full_name,
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
                'description'       => 'Company Advance to Client: ' . $client->full_name,
                'reference_type'    => 'Payment',
                'transaction_date' => now(),
            ]);

            $transaction = Transaction::create([
                'branch_id'         => $agent->branch->id,
                'company_id'        => $agent->branch->company->id,
                'entity_id'         => $client->id,
                'entity_type'       => 'Client',
                'transaction_type'  => 'debit',
                'amount'            => $request->amount,
                'description'       => 'Client Credit of ' . $client->full_name,
                'reference_type'    => 'Payment',
                'transaction_date' => now(),
            ]);

            $liabilitiesAccount = Account::where('name', 'Liabilities')
                ->where('company_id', $agent->branch->company->id)
                ->first();

            $clientAdvance = Account::where('name', 'Client')
                ->where('root_id', $liabilitiesAccount->id ?? null)
                ->where('company_id', $agent->branch->company->id)
                ->first();

            $paymentGateway = Account::where('name', 'Payment Gateway')
                    ->where('company_id', $agent->branch->company_id)
                    ->where('parent_id', $clientAdvance->id)
                    ->first();
            if (!$paymentGateway) {
                throw new Exception('Payment Gateway account not found');
            }

            if ($paymentGateway) {
                JournalEntry::create([
                    'transaction_id'      => $transaction->id,
                    'branch_id'           => $agent->branch->id,
                    'company_id'          => $agent->branch->company->id,
                    'account_id'          => $paymentGateway->id,
                    'transaction_date'    => now(),
                    'description'         => 'Advance Payment for: ' . $client->full_name,
                    'debit'               => 0,
                    'credit'              => $request->amount,
                    'balance'             => $paymentGateway->actual_balance - $request->amount,
                    'name'                => $client->full_name,
                    'type'                => 'receivable',
                    'voucher_number'      => 'MTU-' . now()->timestamp,
                    'type_reference_id'   => $paymentGateway->id,
                ]);

                $paymentGateway->actual_balance -= $request->amount;
                $paymentGateway->save();
            }

            $receivableRoot = Account::where('name', 'Assets')
                ->where('company_id', $agent->branch->company->id)
                ->first();

            $clientReceivable = Account::where('name', 'Clients')
                ->where('root_id', $receivableRoot->id ?? null)
                ->where('company_id', $agent->branch->company->id)
                ->first();

            if ($clientReceivable) {
                JournalEntry::create([
                    'transaction_id'      => $transaction->id,
                    'branch_id'           => $agent->branch->id,
                    'company_id'          => $agent->branch->company->id,
                    'account_id'          => $clientReceivable->id,
                    'transaction_date'    => now(),
                    'description'         => 'Manual Topup Receivable: ' . $client->full_name,
                    'debit'               => $request->amount,
                    'credit'              => 0,
                    'balance'             => $clientReceivable->actual_balance + $request->amount,
                    'name'                => $client->full_name,
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
