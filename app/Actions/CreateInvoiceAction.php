<?php
namespace App\Actions;

use App\Models\Account;
use App\Models\Agent;
use App\Models\Client;
use App\Models\JournalEntry;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreateInvoiceAction
{
    public function handle(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.description' => 'required|string',
            'tasks.*.remark' => 'nullable|string',
            'tasks.*.invprice' => 'required|numeric',
            'tasks.*.supplier_id' => 'required|integer',
            'tasks.*.client_id' => 'required|integer',
            'tasks.*.agent_id' => 'required|integer',
            'invdate' => 'required|date',
            'duedate' => 'required|date',
            'subTotal' => 'required|numeric',
            'clientId' => 'required|integer',
            'agentId' => 'required|integer',
            'invoiceNumber' => 'required|string',
            'currency' => 'required|string',
        ]);

        $tasks = $request->input('tasks');
        $duedate = $request->input('duedate');
        $invdate = $request->input('invdate');
        $amount = $request->input('subTotal');
        $clientId = $request->input('clientId');
        $agentId = $request->input('agentId');
        $invoiceNumber = $request->input('invoiceNumber');
        $currency = $request->input('currency');

        $agent = Agent::findOrFail($agentId);
        $companyId = $agent->branch->company->id ?? null;
        $branchId = $agent->branch_id;

        Log::info('Company ID:', ['companyId' => $companyId]);

        $receivableAccount = Account::where('name', 'like', '%Receivable%')
            ->where('company_id', $companyId)
            ->first();

        $payableAccount = Account::where('name', 'like', '%Payable%')
            ->where('company_id', $companyId)
            ->first();

        $incomeAccount = Account::where('name', 'like', '%Income On Sales%')
            ->where('company_id', $companyId)
            ->first();

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'agent_id' => $agentId,
            'client_id' => $clientId,
            'sub_amount' => $amount,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'unpaid',
            'invoice_date' => $invdate,
            'due_date' => $duedate,
            'payment_type' => 'full',
        ]);

        foreach ($tasks as $task) {
            $this->createInvoiceDetails($task, $invoice, $companyId, $branchId, $invoiceNumber, $payableAccount, $receivableAccount, $incomeAccount);
        }
    }

    private function createInvoiceDetails($task, $invoice, $companyId, $branchId, $invoiceNumber, $payableAccount, $receivableAccount, $incomeAccount)
    {
        try {
            $selectedTask = Task::findOrFail($task['id']);
            $supplier = Supplier::findOrFail($task['supplier_id']);
            $client = Client::findOrFail($task['client_id']);
            $agent = Agent::findOrFail($task['agent_id']);

            $invoiceDetail = InvoiceDetail::create([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'task_id' => $task['id'],
                'task_description' => $task['description'],
                'task_remark' => $task['remark'],
                'client_notes' => $task['note'],
                'task_price' => $task['invprice'],
                'supplier_price' => $selectedTask->total,
                'markup_price' => $task['invprice'] - $selectedTask->total,
                'paid' => false,
            ]);

            $transaction = Transaction::create([
                'entity_id' => $companyId,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' => $task['invprice'],
                'date' => Carbon::now(),
                'description' => 'Invoice: ' . $invoiceNumber . ' Generated',
                'invoice_id' => $invoice->id,
                'reference_type' => 'Invoice',
            ]);

            $this->createJournalEntrys($transaction, $companyId, $branchId, $payableAccount, $receivableAccount, $incomeAccount, $selectedTask, $task, $invoice, $invoiceDetail, $supplier, $client, $agent);

            $selectedTask->status = 'Assigned';
            $selectedTask->save();
        } catch (Exception $e) {
            Log::error('Failed to create InvoiceDetails for task: ' . $e->getMessage());
            throw new Exception('Failed to create InvoiceDetails for task: ' . $task['description']);
        }
    }

    private function createJournalEntrys($transaction, $companyId, $branchId, $payableAccount, $receivableAccount, $incomeAccount, $selectedTask, $task, $invoice, $invoiceDetail, $supplier, $client, $agent)
    {
        // Logic for creating general ledger entries (Payable, Receivable, Income)
        // Add the code for JournalEntry creation as in your original function
    }
}
