<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Task;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;

class RefundController extends Controller
{

    public function index()
    {
        if (Auth::user()->role->name == 'company') {
            $refunds = Refund::with('invoice.client')
                ->where('company_id', Auth::user()->company->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->name == 'branch') {
            $refunds = Refund::with('invoice.client')
                ->where('branch_id', Auth::user()->branch->id)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $refunds = collect();
        }
    
        $totalRefunds = $refunds->count();
        return view('refunds.index', compact('refunds', 'totalRefunds'));
    }
    
    public function create(Invoice $invoice)
    {
        if ($invoice->status !== 'paid') {
            abort(403, 'Refunds are only allowed for paid invoices.');
        }
    
        $assetsRootIdAssets = Account::where('name', 'Assets')->value('id');
        $liabilitiesRootIdLiabilities = Account::where('name', 'Liabilities')->value('id');
        
        $coaAccounts = Account::doesntHave('children')
            ->whereHas('parent', function ($query) use ($assetsRootIdAssets, $liabilitiesRootIdLiabilities) {
                $query->whereIn('root_id', [$assetsRootIdAssets, $liabilitiesRootIdLiabilities]);
            })
            ->get();


        // Get task IDs from invoice details
        $taskIds = $invoice->invoiceDetails()->pluck('task_id')->filter()->unique();
    
        // Fetch tasks using those IDs
        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')
            ->whereIn('id', $taskIds)
            ->orderBy('id', 'desc')
            ->get();

        $totals = $invoice->invoiceDetails()
            ->whereIn('task_id', $taskIds)
            ->selectRaw('SUM(task_price) as total_task_price, SUM(supplier_price) as total_supplier_price, SUM(markup_price) as total_markup_price')
            ->first();

       // dd($tasks);
    
        return view('refunds.create', compact('invoice', 'coaAccounts', 'tasks', 'totals'));
    }

    public function store(Request $request, Invoice $invoice)
    {   
        $request->validate([
            'total_nett_refund' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string'],
            'method' => ['required', 'in:Bank,Cash,Online'],
            'account_id' => ['required', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
        ]);

        $refund = Refund::create([
            'refund_number' => 'RF-' . now()->timestamp, 
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->agent->branch->company_id,
            'branch_id' => $invoice->agent->branch_id,
            'agent_id' => $invoice->agent_id,
            'remarks' => $request->input('remarks'),
            'remarks_internal' => $request->input('remarks_internal'),
            'airline_nett_fare' => $request->input('airline_nett_fare'),
            'tax_refund' => $request->input('tax_refund'),
            'refund_airline_charge' => $request->input('refund_airline_charge'),
            'original_task_profit' => $request->input('original_task_profit'),
            'new_task_profit' => $request->input('new_task_profit'),
            'total_nett_refund' => $request->input('total_nett_refund'),
            'service_charge' => $request->input('service_charge'),
            'reason' => $request->reason,
            'method' => $request->method,
            'account_id' => $request->account_id,
            'date' => $request->date,
            'reference' => $request->reference,
            'status' => 'processed',
            'created_by' => auth()->user()->id,
        ]);

        // Create Transaction Record
        $transaction = Transaction::create([
            'entity_id' => $invoice->agent->branch->company_id,
            'entity_type' => 'company',
            'company_id' => $invoice->agent->branch->company_id,
            'branch_id' => $invoice->agent->branch_id,
            'transaction_type' => 'debit',
            'amount' => $request->input('total_nett_refund'),
            'date' => $request->date,
            'description' => 'Refund: '.$invoice->invoice_number.' ('.$refund->refund_number.') - '.$request->input('remarks'),
            'reference_type' => 'Refund',
            'invoice_id' => $invoice->id,
            'reference_number' => $request->bankpaymentref,
            'name' => $invoice->client->name,
            'remarks_internal' => $request->input('remarks_internal'),
            
        ]);

       
        try {
            $updateInvoiceRec = Invoice::where('id', $invoice->id)->update([
                'status_next' => 'refund',
                'status_next_date' => Carbon::now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update invoice status', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        $assetsReceivableAccount = Account::where('name', 'Accounts Receivable')->first(); 
        $invoiceDetails = InvoiceDetail::where('invoice_id', $invoice->id)->get();
        
        foreach ($invoiceDetails as $invdetail) {
            if ($invdetail->task_id) {
                $supplierName = $invdetail->task->supplier->name;
                $accountSupplierName = 'Supplier Refunds – ' . $supplierName;
        
                // Get or create Supplier Refund Account
                $supplierRefundAccount = Account::where('name', 'LIKE', $accountSupplierName)
                    ->where('company_id', $invoice->agent->branch->company->id)
                    ->first();

                
                if (!$supplierRefundAccount) {
                    $supplierRefundAccountId = Account::create([
                        'name' => $accountSupplierName,
                        'parent_id' => $assetsReceivableAccount->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $assetsReceivableAccount->root_id,
                        'code' => $assetsReceivableAccount->code + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $assetsReceivableAccount->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $supplierRefundAccountEntry =  $supplierRefundAccountId;
                } else {
                    $supplierRefundAccountEntry =  $supplierRefundAccount;
                }

                //dd($supplierRefundAccountEntry);
        
                // Get or create Supplier Refund Income Account
                $incomeIndirectIncome = Account::where('name', 'LIKE', '%Indirect Income%')->first();
                $accountSupplierRefundIncome = 'Supplier Refund Income (Refund Adjustment)';
        
                $supplierRefundIncome = Account::where('name', 'LIKE', $accountSupplierRefundIncome)
                    ->where('company_id', $invoice->agent->branch->company->id)
                    ->first();
        
                if (!$supplierRefundIncome) {
                    $supplierRefundIncomeId = Account::create([
                        'name' => $accountSupplierRefundIncome,
                        'parent_id' => $incomeIndirectIncome->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $incomeIndirectIncome->root_id,
                        'code' => $incomeIndirectIncome->code + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $incomeIndirectIncome->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $supplierRefundIncomeEntry =  $supplierRefundIncomeId;
                } else {
                    $supplierRefundIncomeEntry =  $supplierRefundIncome;
                }
        
                // Step 1: Debit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $invoice->agent->branch->company_id,
                    'branch_id' => $invoice->agent->branch_id,
                    'account_id' => $supplierRefundAccountEntry->id,
                    'description' => $refund->refund_number . ' - Record Refund Due From Supplier (Assets) ('.$supplierRefundAccountEntry->name.')',
                    'debit' => $request->input('airline_nett_fare'),
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundAccountEntry->name,
                    'type' => 'refund',
                    'invoice_id' => $invoice->id,
                ]);
        
                // Step 2: Credit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $invoice->agent->branch->company_id,
                    'branch_id' => $invoice->agent->branch_id,
                    'account_id' => $supplierRefundIncomeEntry->id,
                    'description' => $refund->refund_number . ' - Record Refund Due From Supplier (Income) ('.$supplierRefundIncomeEntry->name.')',
                    'debit' => 0,
                    'credit' => $request->input('airline_nett_fare'),
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundIncomeEntry->name,
                    'type' => 'refund',
                    'invoice_id' => $invoice->id,
                ]);


                $assetsDirectIncome = Account::where('name', 'Direct Income')->first(); 
                $accountincomeName = 'Flight Booking Revenue';
        
                // Get or create Supplier Refund Account
                $incomeRefundAccount = Account::where('name', 'LIKE', $accountincomeName)
                    ->where('company_id', $invoice->agent->branch->company->id)
                    ->first();
        
                if (!$incomeRefundAccount) {
                    $incomeRefundAccountId = Account::create([
                        'name' => $accountincomeName,
                        'parent_id' => $assetsDirectIncome->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $assetsDirectIncome->root_id,
                        'code' => $assetsDirectIncome->code + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $assetsDirectIncome->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $incomeRefundAccountEntry =  $incomeRefundAccountId;
                } else {
                    $incomeRefundAccountEntry =  $incomeRefundAccount;
                }
        
                // Get or create Refund Adjustment Account
                $incomeIndirectIncomeRec = Account::where('name', 'LIKE', '%Indirect Income%')->first();
                $accountincomeRefundIncomeRec = 'Refund Adjustment (Revenue Reversal)';
        
                $incomeRefundIncomeRec = Account::where('name', 'LIKE', $accountincomeRefundIncomeRec)
                    ->where('company_id', $invoice->agent->branch->company->id)
                    ->first();
        
                if (!$incomeRefundIncomeRec) {
                    $incomeRefundIncomeRecId = Account::create([
                        'name' => $accountincomeRefundIncomeRec,
                        'parent_id' => $incomeIndirectIncomeRec->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $incomeIndirectIncomeRec->root_id,
                        'code' => $incomeIndirectIncomeRec->code + 1 + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $incomeIndirectIncomeRec->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $incomeRefundIncomeAccEntry =  $incomeRefundIncomeRecId;
                } else {
                    $incomeRefundIncomeAccEntry =  $incomeRefundIncomeRec;
                }
        
                // Step 3: Debit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $invoice->agent->branch->company_id,
                    'branch_id' => $invoice->agent->branch_id,
                    'account_id' => $incomeRefundAccountEntry->id,
                    'description' => $refund->refund_number . ' - Reverse Original Profit (Income) ('.$incomeRefundAccountEntry->name.')',
                    'debit' => $request->input('original_task_profit'),
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $incomeRefundAccountEntry->name,
                    'type' => 'refund',
                    'invoice_id' => $invoice->id,
                ]);
        
                // Step 4: Credit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $invoice->agent->branch->company_id,
                    'branch_id' => $invoice->agent->branch_id,
                    'account_id' => $incomeRefundIncomeAccEntry->id,
                    'description' => $refund->refund_number . ' - Reverse Original Profit (Income) ('.$incomeRefundIncomeAccEntry->name.')',
                    'debit' => 0,
                    'credit' => $request->input('original_task_profit'),
                    'voucher_number' => $refund->id,
                    'name' => $incomeRefundIncomeAccEntry->name,
                    'type' => 'refund',
                    'invoice_id' => $invoice->id,
                ]);



                // Get or create Refund Adjustment Account
                $incomeIndirectRefundCharges = Account::where('name', 'LIKE', '%Indirect Income%')->first();
                $incomeIndirectRefundChargesRec = 'Refund Charges';
        
                $incomeIndirectRefundChargesRecQuery = Account::where('name', 'LIKE', $incomeIndirectRefundChargesRec)
                    ->where('company_id', $invoice->agent->branch->company->id)
                    ->first();
        
                if (!$incomeIndirectRefundChargesRecQuery) {
                    $incomeIndirectRefundChargesRecQueryId = Account::create([
                        'name' => $incomeIndirectRefundChargesRec,
                        'parent_id' => $incomeIndirectRefundCharges->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $incomeIndirectRefundCharges->root_id,
                        'code' => $incomeIndirectRefundCharges->code + 1 + 1 + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $incomeIndirectRefundCharges->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $incomeIndirectRefundChargesRecEntry =  $incomeIndirectRefundChargesRecQueryId;
                } else {
                    $incomeIndirectRefundChargesRecEntry =  $incomeIndirectRefundChargesRecQuery;
                }
        
                // Step 5: Debit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $invoice->agent->branch->company_id,
                    'branch_id' => $invoice->agent->branch_id,
                    'account_id' => $incomeIndirectRefundChargesRecEntry->id,
                    'description' => $refund->refund_number . ' - Refund Service Charges to Client ('.$invoice->client->name.')',
                    'debit' => $request->input('total_nett_refund'),
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $incomeIndirectRefundChargesRecEntry->name,
                    'type' => 'refund',
                    'invoice_id' => $invoice->id,
                ]);


            }
        }
        

        return redirect()->route('invoices.refunds.list')->with('success', 'Refund processed successfully.');

        
    }

    public function edit($invoiceId, $refundId)
    {
        $invoice = Invoice::with(['client', 'agent'])->findOrFail($invoiceId);
        $refund = Refund::findOrFail($refundId);
        $coaAccounts = Account::where('report_type', 'Assets')->get(); // or however you filter them

        // Get task IDs from invoice details
        $taskIds = $invoice->invoiceDetails()->pluck('task_id')->filter()->unique();

        // Fetch tasks using those IDs
        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')
            ->whereIn('id', $taskIds)
            ->orderBy('id', 'desc')
            ->get();

        return view('refunds.edit', compact('invoice', 'refund', 'coaAccounts', 'tasks'));
    }

    public function update(Request $request, $invoiceId, $refundId)
    {
        $refund = Refund::findOrFail($refundId);

        $request->validate([
            'date' => 'required|date',
            'method' => 'required|string',
            'account_id' => 'required|exists:accounts,id',
            'total_nett_refund' => 'required|numeric|min:0',
            'refund_airline_charge' => 'nullable|numeric|min:0',
            'original_task_profit' => 'nullable|numeric|min:0',
            'new_task_profit' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
        ]);

        $refund->update($request->all());

        return redirect()->route('invoices.refunds.edit', [$invoiceId, $refundId])
            ->with('success', 'Refund updated successfully.');
    }


    public function complete_process(Refund $refund)
    {
        try {
            \Log::info("Starting refund process for ID: {$refund->id}");
    
            $updateStatus = Refund::where('id', $refund->id)->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
    
            \Log::info("Refund status update: " . json_encode($updateStatus));
        } catch (\Exception $e) {
            \Log::error('Refund processing failed', [
                'refund_id' => $refund->id ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    
        return response()->json(['message' => 'Refund processed successfully.']);
    }
    
}
