<?php

namespace App\Http\Controllers;

use App\AI\AIManager;
use App\Http\Traits\Converter;
use App\Http\Traits\NotificationTrait;
use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Agent;
use App\Models\TaskFlightDetail;
use App\Models\Airline;
use App\Models\Client;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Branch;
use App\Models\Room;
use App\Models\TaskHotelDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\SupplierCompany;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use Illuminate\Support\Facades\Date;

// use Carbon\Carbon;

class TaskController extends Controller
{
    use NotificationTrait, Converter;

    public function index()
    {
        $user = Auth::user();
        $agent = null;
        $taskCount = 0;
        $clients = collect();
        $agents = collect();
        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice', 'refundDetail', 'originalTask', 'linkedTask')->orderBy('id', 'desc');
        $countries = Country::all();

        $queueTasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')
            ->withoutGlobalScope('enabled')
            ->where('enabled', false)
            ->orderBy('id', 'desc');

        if ($user->role_id == Role::ADMIN) {
            $tasks = $tasks->orderBy('created_at', 'desc')->get();
            $clients = Client::all();
            $agents = Agent::all();
            $queueTasks = $queueTasks->get();
            $suppliers = Supplier::all();
        } elseif ($user->role_id == Role::COMPANY) {

            $branches = Branch::where('company_id', $user->company->id)->get();
            $agents = Agent::with('branch')->whereIn('branch_id', $branches->pluck('id'))->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $tasks = $tasks->where('company_id', $user->company->id)->get();
            $queueTasks = $queueTasks->where('company_id', $user->company->id)->get();
            $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->company->id)->where('is_active', true);
            })->get();

            // Add is_active property for each supplier based on pivot 'active' field
            $suppliers->transform(function ($supplier) use ($user) {
                $company = $supplier->companies()->where('company_id', $user->company->id)->first();
                $supplier->is_active = $company && isset($company->pivot->is_active) ? (bool)$company->pivot->is_active : false;
                return $supplier;
            });
        } elseif ($user->role_id == Role::BRANCH) {
            $agents = Agent::with('branch')->where('branch_id', $user->branch_id)->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $tasks = $tasks->whereIn('agent_id', $agentsId)->where('company_id', $user->company_id)->get();
            $queueTasks = $queueTasks->where('company_id', $user->company_id)->get();
            $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->branch->company_id);
            })->get();
        } elseif ($user->role_id == Role::AGENT) {

            $clients = Client::where('agent_id', $user->agent->id)->get();
            $tasks = $tasks->where('agent_id', $user->agent->id)->get();
            $queueTasks = $queueTasks->where('agent_id', $user->agent->id)->get();
            $companyId = $user->agent->branch->company_id;
            $suppliers = Supplier::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->get();
        } else {
            return redirect()->back()->with('error', 'User not authorized to view tasks.');
        }
        $processTask = $tasks->toArray();
        $processTask = array_map(function ($row) {

            $row = (array) $row;
            $hasNull = false;

            foreach ($row as $key => $value) {
                if ($value === null) {
                    $hasNull = true;
                    break;
                }
            }

            if ($hasNull) {
                $row['is_complete'] = false;
            } else {
                $row['is_complete'] = true;
            }

            return $row;
        }, $processTask);

        $taskCount = $tasks->count();
        $types = Task::distinct()->pluck('type');

        $importedTask = Cache::get('imported_task');
        if ($user->hasAnyRole('admin', 'company')) {

            $branches = $user->role_id == Role::ADMIN ? Branch::all() : Branch::where('company_id', $user->company_id)->get();
            $companyId = $user->role_id == Role::ADMIN ? null : $user->company->id;

            // dd($agents);
            return view('tasks.index', compact(
                'tasks',
                'agent',
                'taskCount',
                'agents',
                'clients',
                'suppliers',
                'branches',
                'types',
                'queueTasks',
                'processTask',
                'companyId',
                'countries'
            ));
        }

        return view('tasks.index', compact(
            'tasks',
            'agent',
            'taskCount',
            'agents',
            'clients',
            'suppliers',
            'types',
            'queueTasks',
            'processTask',
            'companyId',
            'countries'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
            'status' => 'required',
            'company_id' => 'required|exists:companies,id',
        ]);

        // Basic validation - most fields are now nullable except company_id
        $request->validate([
            'type' => 'nullable|string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'reference' => 'required|string',
            'gds_reference' => 'nullable|string',
            'airline_reference' => 'nullable|string',
            'created_by' => 'nullable|string',
            'issued_by' => 'nullable|string',
            'status' => 'required|string',
            'supplier_status' => 'nullable|string',
            'price' => 'nullable|numeric',
            'exchange_currency' => 'nullable|string',
            'original_price' => 'nullable|numeric',
            'original_currency' => 'nullable|string',
            'total' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'penalty_fee' => 'nullable|numeric',
            'client_name' => 'nullable|string',
            'agent_id' => 'nullable',
            'client_id' => 'nullable|exists:clients,id',
            'additional_info' => 'nullable|string',
            'taxes_record' => 'nullable|string',
            'enabled' => 'nullable|boolean',
            'refund_date' => 'nullable|date',
            'ticket_number' => 'nullable|string',
            'refund_charge' => 'nullable|numeric',
            'task_hotel_details' => 'nullable|array',
            'task_flight_details' => 'nullable|array',
            'file_name' => 'nullable|string',
        ]);

        $queryChkExistTask = Task::query();
        $queryChkExistTask->where('reference', $request->reference)
            ->where('company_id', $request->company_id)
            ->where('status', $request->status);

        if ($request->supplier_id) {
            $queryChkExistTask->where('supplier_id', $request->supplier_id);
        }

        $existingTask = $queryChkExistTask->first();

        if ($existingTask) {
            if ($existingTask->gds_reference == null || $existingTask->airline_reference == null) {
                $existingTask->gds_reference = $request->gds_reference;
                $existingTask->airline_reference = $request->airline_reference;
                $existingTask->created_at = $request->created_at;
                $existingTask->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Task updated with GDS and Airline reference.',
                    'data' => $existingTask,
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Task with this reference already exists.',
            ], 422);
        }

        // Set default values for nullable fields
        $request->penalty_fee = $request->penalty_fee ?? 0;
        $request->passenger_name = $request->client_name ?? null;
        $request->tax = $request->tax ?? 0;
        $request->enabled = $request->enabled ?? false;

        // Handle original task for non-issued statuses
        if (in_array($request->status, ['reissued', 'refund', 'void', 'emd'])) {
            $originalTask = Task::where('reference', $request->reference)
                ->where('company_id', $request->company_id)
                ->where('status', 'issued')
                ->first();

            if ($originalTask) {
                $request->merge(['original_task_id' => $originalTask->id]);
            }
        }

        DB::beginTransaction();

        try {
            Log::debug('Task Data:', $request->all());

            $task = Task::create($request->all());

            // Save flight/hotel details if provided (regardless of enabled status)
            if ($task->type === 'hotel' && $request->has('task_hotel_details') && !empty($request->task_hotel_details)) {
                $this->saveHotelDetails($request->task_hotel_details, $task->id);
            } elseif ($task->type === 'flight' && $request->has('task_flight_details') && !empty($request->task_flight_details)) {
                $this->saveFlightDetails($request->task_flight_details, $task->id);
            }

            // Only process financial transactions if task is enabled and complete
            if ($task->enabled && $task->is_complete) {
                $this->processTaskFinancial($task);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task created successfully.',
                'data' => $task,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Task creation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Task creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process all financial transactions for a task
     */
    private function processTaskFinancial(Task $task)
    {
        Log::info('Processing financial for task: ' . $task->reference);
        // Use the Task model's is_complete attribute to check completeness
        if (!$task->is_complete) {
            throw new Exception('Task is not complete. Missing required fields: ' . $this->getMissingFields($task));
        }

        if (!$task->agent) {
            throw new Exception('Agent not found for the task.');
        }

        $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
            ->where('company_id', $task->company_id)
            ->first();

        if (!$supplierCompany) {
            throw new Exception('Supplier company not activated or not found.');
        }

        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $task->company_id)
            ->first();

        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $task->company_id)
            ->first();

        if (!$liabilities || !$expenses) {
            throw new Exception('Liabilities or Expenses account not found.');
        }

        $receivableAccount = Account::where('name', 'like', '%Receivable%')
            ->where('company_id', $task->company_id)
            ->first();

        Log::info('Receivable Account: ', ['account' => $receivableAccount]);

        if (!$receivableAccount) {
            throw new Exception('Receivable account not found.');
        }

        $supplier = Supplier::find($task->supplier_id);

        $supplierPayable = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        $supplierCost = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $expenses->id)
            ->first();

        $issuedByAccount = null;

        Log::info('Supplier Payable Account: ', ['account' => $supplierPayable]);

        if ($task->type == 'flight') {
            Log::info('Processing flight task financial for: ' . $task->reference);
            $companyIssuedBy = $task->issued_by ?? 'Not Issued';

            $issuedByAccount = Account::where('name', $companyIssuedBy)
                ->where('company_id', $task->company_id)
                ->where('root_id', $liabilities->id)
                ->where('parent_id', $supplierPayable->id)
                ->first();

            Log::info('Issued By Account: ', ['account' => $issuedByAccount]);

            if (!$issuedByAccount) {
                Log::info('Creating new issued by account for: ' . $companyIssuedBy);
                $code = 2151;
                $lastIssuedByAccount = Account::where('company_id', $task->company_id)
                    ->where('root_id', $liabilities->id)
                    ->where('parent_id', $supplierPayable->id)
                    ->orderBy('code', 'desc')
                    ->first();

                if ($lastIssuedByAccount) {
                    $code = $lastIssuedByAccount->code + 1;
                }

                try {
                    $issuedByAccount = Account::create([
                        'name' => $companyIssuedBy,
                        'parent_id' => $supplierPayable->id,
                        'company_id' => $task->company_id,
                        'branch_id' => $task->agent->branch_id,
                        'root_id' => $liabilities->id,
                        'code' => $code,
                        'account_type' => 'liability',
                        'report_type' => 'balance sheet',
                        'level' => $supplierPayable->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create issued by account: ' . $e->getMessage());
                    throw new Exception('Failed to create issued by account.');
                }
            }
        }

        if (!$supplierCost || !$supplierPayable) {
            Log::error('Supplier cost or payable account not found for task: ' . $task->reference);
            throw new Exception('Supplier account not found.');
        }

        Log::info('Processing task financials for: ' . $task->reference, [
            'supplierCost' => $supplierCost,
            'supplierPayable' => $supplierPayable,
            'issuedByAccount' => $issuedByAccount,
            'supplierCompany' => $supplierCompany,
        ]);

        // Process based on status
        switch (strtolower($task->status)) {
            case 'issued':
                Log::info('Processing issued task financial for: ' . $task->reference);
                $this->processIssuedTask($task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany);
                break;
            case 'void':
                Log::info('Processing void task financial for: ' . $task->reference);
                $this->processVoidTask($task);
                break;
            case 'refund':
                Log::info('Processing refund task financial for: ' . $task->reference);
                $this->processRefundTask($task);
                break;
            default:
                Log::error('Task status not recognized for financial processing: ' . $task->status);
                throw new Exception('Task status not recognized for financial processing: ' . $task->status);
        }
    }

    /**
     * Get missing required fields with custom error messages
     */
    private function getMissingFields(Task $task): string
    {
        $missingFields = [];

        // Define custom messages for each required field
        $fieldMessages = [
            'client_id' => 'Please update the client',
            'agent_id' => 'Agent is required to enable this task',
            'company_id' => 'Company information is missing',
            'supplier_id' => 'Supplier must be assigned to this task',
            'type' => 'Task type (flight/hotel) must be specified',
            'status' => 'Task status is required',
            'client_name' => 'Client name is required',
            'reference' => 'Reference number is mandatory',
            'total' => 'Total amount must be specified',
        ];

        foreach ($task->getRequiredColumns() as $column) {
            if (empty($task->$column)) {
                // Use custom message if available, otherwise use default format
                $message = $fieldMessages[$column] ?? ucfirst(str_replace('_', ' ', $column)) . ' is required';
                $missingFields[] = $message;
            }
        }

        return implode(', ', $missingFields);
    }

    /**
     * Process issued task financials
     */
    private function processIssuedTask(Task $task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany)
    {
        $transaction = Transaction::create([
            'branch_id' => $task->agent->branch_id,
            'company_id' => $task->company_id,
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $task->total,
            'description' => 'Task created: ' . $task->reference,
            'reference_type' => 'Payment',
        ]);

        if (!$transaction) {
            throw new Exception('Transaction creation failed.');
        }

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'account_id' => $supplierCost->id,
            'task_id' => $task->id,
            'transaction_date' => Carbon::now(),
            'description' => 'Task from supplier (Expenses): ' . $supplierCompany->supplier->name,
            'name' => $supplierCompany->supplier->name,
            'debit' => $task->total,
            'credit' => 0,
            'balance' => $task->total,
            'type' => 'payable',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'account_id' => $issuedByAccount ? $issuedByAccount->id : $supplierPayable->id,
            'task_id' => $task->id,
            'transaction_date' => Carbon::now(),
            'description' => 'Records Payable to (Liabilities): ' . $supplierCompany->supplier->name,
            'name' => $supplierCompany->supplier->name,
            'debit' => 0,
            'credit' => $task->total,
            'balance' => $task->total,
            'type' => 'payable',
        ]);
    }

    /**
     * Process void task financials
     */
    private function processVoidTask(Task $task)
    {
        Log::info('Check for invoice created for this task.');

        $originalTask = Task::find($task->original_task_id);
        if (!$originalTask) {
            throw new Exception('Original task not found for void processing.');
        }

        $payment = Payment::whereHas('partials.invoice.invoiceDetails', function ($query) use ($originalTask) {
            $query->where('task_id', $originalTask->id);
        })
            ->whereHas('partials', function ($query) {
                $query->where('status', 'paid');
            })
            ->first();

        if ($payment && $payment->client_id) {
            Log::info('Invoice is already paid. Processing paid void reversal.');
            $this->voidTask($task, $originalTask, $payment);
        } else {
            Log::info('Invoice for the void task is not paid nor found. Processing unpaid void reversal.');
            $this->ReverseUnpaidVoidedTask($originalTask);
        }
    }

    /**
     * Process refund task financials
     */
    private function processRefundTask(Task $task)
    {
        $accountSupplierName = $task->supplier ? $task->supplier->name : 'Unknown Supplier';

        // Get or create Supplier Refund Account
        $assetsPayableAccount = Account::where('name', 'Accounts Payable')
            ->where('company_id', $task->company_id)
            ->where('root_id', 2)
            ->first();

        $supplierRefundAccount = Account::where('name', 'LIKE', '%' . $accountSupplierName . '%')
            ->where('company_id', $task->company_id)
            ->where('root_id', $assetsPayableAccount->root_id)
            ->first();

        if (!$supplierRefundAccount) {
            $supplierRefundAccount = Account::create([
                'name' => $accountSupplierName,
                'parent_id' => $assetsPayableAccount->id,
                'company_id' => $task->company_id,
                'branch_id' => $task->agent->branch_id,
                'root_id' => $assetsPayableAccount->root_id,
                'code' => $assetsPayableAccount->code + 1,
                'account_type' => 'asset',
                'report_type' => 'balance sheet',
                'level' => $assetsPayableAccount->level + 1,
                'is_group' => 0,
                'disabled' => 0,
                'actual_balance' => 0.00,
                'budget_balance' => 0.00,
                'variance' => 0.00,
                'currency' => 'KWD',
            ]);
        }

        // Get Expense Account
        $expensesDirectExpenses = Account::where('name', 'LIKE', '%Direct Expenses%')
            ->where('company_id', $task->company_id)
            ->where('root_id', 5)
            ->first();

        $accountSupplierRefundExpenses = ucfirst($task->type) . 's Cost';

        $supplierRefundExpenses = Account::where('name', $accountSupplierRefundExpenses)
            ->where('company_id', $task->company_id)
            ->where('root_id', $expensesDirectExpenses->root_id)
            ->first();

        if (!$supplierRefundExpenses) {
            $supplierRefundExpenses = Account::create([
                'name' => $accountSupplierRefundExpenses,
                'parent_id' => $expensesDirectExpenses->id,
                'company_id' => $task->company_id,
                'branch_id' => $task->agent->branch_id,
                'root_id' => $expensesDirectExpenses->root_id,
                'code' => $expensesDirectExpenses->code + 1,
                'account_type' => 'asset',
                'report_type' => 'balance sheet',
                'level' => $expensesDirectExpenses->level + 1,
                'is_group' => 0,
                'disabled' => 0,
                'actual_balance' => 0.00,
                'budget_balance' => 0.00,
                'variance' => 0.00,
                'currency' => 'KWD',
            ]);
        }

        // Create Transaction Record
        $transaction = Transaction::create([
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'transaction_type' => 'debit',
            'amount' => $task->total,
            'description' => 'Refund Task: ' . $task->reference,
            'reference_type' => 'Refund',
            'name' => $task->client_name,
        ]);

        if (!$transaction) {
            throw new Exception('Refund Transaction creation failed.');
        }

        JournalEntry::create([
            'transaction_date' => Carbon::now(),
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'account_id' => $supplierRefundAccount->id,
            'description' => 'Refund Task - Supplier refunds us (Liabilities): ' . $supplierRefundAccount->name,
            'debit' => $task->total,
            'credit' => 0,
            'name' => $supplierRefundAccount->name,
            'type' => 'refund',
        ]);

        JournalEntry::create([
            'transaction_date' => Carbon::now(),
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'account_id' => $supplierRefundExpenses->id,
            'description' => 'Refund Task - Flight cost return (Expenses): ' . $supplierRefundExpenses->name,
            'debit' => 0,
            'credit' => $task->total,
            'name' => $supplierRefundExpenses->name,
            'type' => 'refund',
        ]);
    }

    /**
     * Enable task and process financials when task is complete
     */
    public function enableTask(Task $task)
    {
        if (!$task->is_complete) {
            throw new Exception('Task is not complete. Missing required fields: ' . $this->getMissingFields($task));
        }

        DB::beginTransaction();

        try {
            $task->enabled = true;
            $task->save();

            $this->processTaskFinancial($task);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Task enabled and processed successfully.',
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function toggleStatus(Request $request, Task $task)
    {
        $task->enabled = $request->is_enabled;

        if ($task->enabled) {
            if (!$task->is_complete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task is not complete. Missing required fields: ' . $this->getMissingFields($task)
                ], 400);
            }

            $journalEntries = JournalEntry::where('task_id', $task->id)->exists();

            if (!$journalEntries) {
                try {
                    $this->processTaskFinancial($task);
                } catch (Exception $e) {
                    Log::error('Failed to process task financial: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to enable task: ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        $task->save();

        return response()->json(['success' => true]);
    }

    public function voidTask(Task $task, Task $issuedTask, Payment $payment)
    {
        $client = Client::find($payment->client_id);

        if (!$client) {
            throw new \Exception("Client not found for payment ID: {$payment->id}");
            Log::warning("Client not found for payment [{$payment->id}] during void refund.");
        }

        $oldCredit = $client->credit;

        $client->credit += $payment->amount;
        $client->save();

        Log::info("Void for task [{$task->reference}]: Client credit before = {$oldCredit}, after = {$client->credit}");

        $voidTransaction = Transaction::create([
            'branch_id'        => $client->agent->branch_id,
            'company_id'       => $client->agent->branch->company_id,
            'entity_id'        => $client->id,
            'entity_type'      => 'client',
            'transaction_type' => 'debit',
            'amount'           => $payment->amount,
            'description'      => 'Void task: ' . $task->reference,
            'reference_type'   => 'Refund',
            'reference_number' => $payment->voucher_number,
        ]);

        if (!$voidTransaction) {
            throw new \Exception("Failed to create refund transaction.");
        }

        $entries = JournalEntry::whereHas('invoiceDetail', function ($query) use ($task) {
            $query->where('task_description', $task->reference);
        })->get();

        foreach ($entries as $entry) {
            JournalEntry::create([
                'transaction_id'   => $voidTransaction->id,
                'company_id'       => $entry->company_id,
                'branch_id'        => $entry->branch_id,
                'account_id'       => $entry->account_id,
                'task_id'          => $issuedTask->id,
                'transaction_date' => now(),
                'description'      => 'Void: ' . $entry->description,
                'debit'            => $entry->credit,
                'credit'           => $entry->debit,
                'balance'          => ($entry->balance ?? 0) * -1,
                'type'             => $entry->type,
                'name'             => $entry->name,
                'voucher_number'   => $entry->voucher_number,
            ]);
        }

        Log::info('Voided task refunded and reversed: ' . $task->reference);

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Paid void task reversal journal completed.',
            'data' => $issuedTask,
        ], 201);
    }

    public function show($id)
    {
        $task = Task::with(['agent.branch', 'client', 'flightDetails.countryFrom',  'flightDetails.countryTo', 'hotelDetails.hotel', 'supplier'])->withoutGlobalScope('enabled')->findOrFail($id);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        if ($task->flightDetails) {
            $task['country_from'] = $task->flightDetails->countryFrom->name;
            $task['country_to'] = $task->flightDetails->countryTo->name;
            $task['description'] = $task['country_from'] . ' ---> ' . $task['country_to'];
        } elseif ($task->hotelDetails) {
            $task['hotel_name'] = $task->hotelDetails->hotel->name;
            $task['hotel_country'] = $task->hotelDetails->hotel->country;
            $task['description'] = $task['hotel_name'] . '/' . $task['hotel_country'];
        } else {
            $task['description'] = 'No description';
        }


        // Return the task data as JSON for the modal to load dynamically
        return response()->json($task, 200);
    }

    public function edit($id)
    {
        // Include both 'agent' and 'client' in the query
        $task = Task::with(['agent', 'client'])->findOrFail($id);
        return view('tasks.update', compact('task'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required',
            'agent_id' => 'required',
            'supplier_id' => 'required',
            'status' => 'required',
            'total' => 'required',
        ], [
            'client_id.required' => 'Please select a client',
            'agent_id.required' => 'Please select an agent',
            'supplier_id.required' => 'Please select a supplier',
            'status.required' => 'Please select a status',
            'total.required' => 'Please enter the total amount',
        ]);

        if (strtolower($request->status) !== 'issued') {
            $request->validate([
                'original_task_id' => 'required|exists:tasks,id',
            ], [
                'original_task_id.required' => 'Task must be linked to an original task',
            ]);
        }

        // Find the task
        $task = Task::findOrFail($id);
        $prevClientName = $task->client_name;
        $wasEnabled = false;

        $hasJournalEntries = JournalEntry::where('task_id', $task->id)->exists();

        if ($hasJournalEntries) {
            $wasEnabled = true;
        }

        $client = Client::findOrFail($request->client_id);

        // If the request is an AJAX request, handle inline editing
        if ($request->ajax()) {
            try {
                $field = key($request->all()); // Get the field being updated
                $value = $request->input($field);

                // Update the specific field
                $task->update([$field => $value]);

                // Check if task should be enabled/disabled after the update
                if ($task->is_complete && !$task->enabled) {
                    $task->enabled = true;
                    $task->save();

                    // Process financial transactions for newly enabled task
                    try {
                        $this->processTaskFinancial($task);
                    } catch (Exception $e) {
                        Log::error('Failed to process task financial after inline update: ' . $e->getMessage());
                        return response()->json([
                            'success' => false,
                            'message' => 'Task updated but failed to process financials: ' . $e->getMessage()
                        ], 500);
                    }
                } elseif (!$task->is_complete && $task->enabled) {
                    $task->enabled = false;
                    $task->save();
                }

                return response()->json(['success' => true], 200);
            } catch (Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        } else {
            DB::beginTransaction();

            try {
                $task->update($request->only(['client_id', 'agent_id', 'supplier_id', 'total', 'status']));
                $task->client_name = $client->name;

                // Determine if task should be enabled
                $shouldBeEnabled = $task->is_complete;

                if ($shouldBeEnabled && !$wasEnabled) {
                    // Task is now complete and wasn't enabled before - enable and process financials
                    $task->enabled = true;
                    $task->save();

                    $this->processTaskFinancial($task);
                } elseif (!$shouldBeEnabled && $wasEnabled) {
                    // Task is no longer complete but was enabled - disable it
                    $task->enabled = false;
                    $task->save();
                } else {
                    // Just save the enabled status
                    $task->enabled = $shouldBeEnabled;
                    $task->save();
                }

                // Update journal entries if client name changed
                $transaction = Transaction::with('journalEntries')->where('description', 'like', '%' . $task->reference . '%')->first();

                if ($transaction) {
                    $transaction->journalEntries->each(function ($journalEntry) use ($client, $prevClientName) {
                        if ($journalEntry->name == $prevClientName) {
                            $journalEntry->name = $client->name;
                            $journalEntry->save();
                        }
                    });
                }

                DB::commit();
                return redirect()->back()->with('success', 'Task updated successfully.');
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Task update failed: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Task update failed: ' . $e->getMessage());
            }
        }
    }

    public function upload(Request $request)
    {
        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company_id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company_id;
        } else {
            return redirect()->back()->with('error', 'User not authorized to upload tasks.');
        }

        $request->validate([
            'task_file' => 'required|mimes:pdf,txt',
            'agent_id' => 'required|exists:agents,id',
        ]);

        $file = $request->file('task_file')->store('public/tasks');
        if (!$file) {
            return [
                'status' => 'error',
                'message' => 'File upload failed.'
            ];
        }

        $aiManager = new AIManager();
        $filePath = storage_path('app/' . $file);
        $fileName = $request->file('task_file')->getClientOriginalName();

        $extractedData = $aiManager->processWithAiTool($filePath, $fileName);

        if ($extractedData['status'] === 'error') {
            Log::error("AI tool processing error for {$fileName}: " . $extractedData['message']);
            return [
                'status' => 'error',
                'message' => 'Something went wrong, please contact support.',
            ];
        }

        $extractedData = is_array($extractedData) ? $extractedData : json_decode($extractedData, true);

        $responses = [];
        $createdTasks = [];
        $loop = 0;
        foreach ($extractedData['data'] as $taskData) {

            $newRequest = new Request($taskData);

            $supplier = Supplier::where('name', 'like', $taskData['supplier_name'])->first();

            $newRequest->merge([
                'enabled' => false,
                'agent_id' => (int)$request->agent_id,
                'supplier_id' => $supplier->id,
                'company_id' => $companyId,
                'refund_date' => $taskData['refund_date'] ?? null,
            ]);

            $responseWithoutJson = $this->store($newRequest);
            $response = json_decode($responseWithoutJson->getContent(), true);

            if ($response['status'] == 'error') {
                // Rollback all previously created tasks and related data
                foreach ($createdTasks as $createdTask) {
                    // Delete related flight/hotel details
                    if ($createdTask->type === 'flight') {
                        TaskFlightDetail::where('task_id', $createdTask->id)->delete();
                    } elseif ($createdTask->type === 'hotel') {
                        TaskHotelDetail::where('task_id', $createdTask->id)->delete();
                    }
                    // Delete related journal entries and transactions by finding journal entries via task_id
                    $journalEntries = JournalEntry::where('task_id', $createdTask->id)->get();
                    $transactionIds = $journalEntries->pluck('transaction_id')->unique();

                    // Delete journal entries
                    JournalEntry::where('task_id', $createdTask->id)->delete();

                    // Delete related transactions
                    Transaction::whereIn('id', $transactionIds)->delete();

                    // Delete the task itself
                    $createdTask->delete();
                }

                $responses = [
                    'status' => 'error',
                    'message' => 'Error occurred while saving task: ' . ($taskData['reference'] ?? '[unknown reference]') . ': ' . $response['message'] ?? 'Unknown error',
                    'error_detail' => $response['message'] ?? 'Unknown error',
                    'success_tasks' => array_map(function ($t) {
                        return $t->reference;
                    }, $createdTasks),
                    'failed_task' => $taskData['reference'] ?? '[unknown reference]',
                ];
                break;
            } else {
                // Save the created task for possible rollback
                $createdTask = Task::find($response['data']['id']);
                if ($createdTask) {
                    $createdTasks[] = $createdTask;
                }
            }
            $responses = [
                'status' => 'success',
                'message' => 'Task saved for: ' . implode(', ', array_map(function ($t) {
                    return $t->reference;
                }, $createdTasks)),
                'created_tasks' => $createdTasks,
            ];
        }

        return $responses;
    }

    public function exportCsv()
    {

        // Fetch all agents data
        $tasks = Task::with('agent')->get();

        // Create a CSV file in memory
        $csvFileName = 'tasks.csv';
        $handle = fopen('php://output', 'w');

        // Set headers for the response
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        // Add CSV header
        fputcsv($handle, ['Agent Name', 'Agent Email', 'Task', 'Type', 'Status']);

        // Add company data to CSV
        foreach ($tasks as $task) {
            fputcsv($handle, [
                $task->agent->name,
                $task->agent->email,
                $task->description,
                $task->task_type,
                $task->status
            ]);
        }

        fclose($handle);
        exit();
    }

    public function fileToTask() {}
    /**
     * Get all tasks for a specific agent
     * @param $agentId
     * @return array
     */
    public function getTasks($agentId)
    {
        // get tasks that doesnt have invoice only
        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->whereDoesntHave('invoiceDetail')->where('agent_id', $agentId)->get();

        return response()->json($tasks);
    }

    /**
     * Save flight details to the database
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void 
     *
     */
    public function saveFlightDetails(array $data, int $taskId)
    {

        try {

            $airline = isset($data['airline_name']) ? Airline::where('name', 'like', '%' . $data['airline_name'] . '%')->first() : null;
            $countryFrom = isset($data['departure_from']) ? Country::where('name', 'like', '%' . $data['departure_from'] . '%')->first() : null;
            $countryTo = isset($data['departure_from']) ? Country::where('name', 'like', '%' . $data['arrive_to'] . '%')->first() : null;


            $flightDetails = [
                'farebase' => isset($data['farebase']) ? (float) $data['farebase'] : null,
                'departure_time' => $data['departure_time'] ?? null,
                'country_id_from' => $countryFrom->id ?? null,
                'airport_from' => $data['airport_from'] ?? null,
                'terminal_from' => $data['terminal_from'] ?? null,
                'arrival_time' => $data['arrival_time'] ?? null,
                'duration_time' => $data['duration_time'] ?? null,
                'country_id_to' => $countryTo->id ?? null,
                'airport_to' => $data['airport_to'] ?? null,
                'terminal_to' => $data['terminal_to'] ?? null,
                'airline_id' => $airline->id ?? null,
                'flight_number' => $data['flight_number'] ?? null,
                'ticket_number' => $data['ticket_number'] ?? null,
                'class_type' => $data['class_type'] ?? null,
                'baggage_allowed' => $data['baggage_allowed'] ?? null,
                'equipment' => $data['equipment'] ?? null,
                'flight_meal' => $data['flight_meal'] ?? null,
                'seat_no' => $data['seat_no'] ?? null,
                'task_id' => $taskId
            ];

            TaskFlightDetail::create($flightDetails);
        } catch (Exception $e) {

            throw $e;
        }
    }

    /**
     * Save hotel details to the database
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void
     */
    public function saveHotelDetails(array $data, int $taskId)
    {
        try {
            $hotel = Hotel::where('name', 'like', '%' . $data['hotel_name'] . '%')->first();

            // $hotelCountry = Country::where('name', 'like', '%' . $data['hotel_country'] . '%')->first();

            if (!$hotel) {
                $hotel = Hotel::create([
                    'name' => $data['hotel_name'],
                    'address' => $data['hotel_address'] ?? null,
                    'city' => $data['hotel_city'] ?? null,
                    'state' => $data['hotel_state'] ?? null,
                    'country' => $data['hotel_country'] ?? null,
                    'zip' => $data['hotel_zip'] ?? null,
                ]);
            }

            $hotelDetails = [
                'hotel_id' => $hotel->id,
                'booking_time' => $data['booking_time'] ?? null,
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'room_amount' => $data['room_amount'] ?? null,
                'room_details' => $data['room_details'] ?? null,
                'rate' => $data['rate'] ?? null,
                'task_id' => $taskId
            ];

            TaskHotelDetail::create($hotelDetails);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function queue()
    {

        $queueTasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')
            ->withoutGlobalScope('enabled')
            ->where('enabled', false)
            ->orderBy('id', 'desc');

        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $queueTasks = $queueTasks->get();
        } else if ($user->role_id == Role::BRANCH) {
            $queueTasks = $queueTasks->where('agent_id', $user->branch->agents->pluck('id'))->get();
        } else if ($user->role_id == Role::AGENT) {
            $queueTasks = $queueTasks->where('agent_id', $user->agent->id)->get();
        } else {
            return redirect()->back()->with('error', 'User not authorized to view tasks.');
        }

        return view('tasks.queue', compact('queueTasks'));
    }

    public function supplierTask($id)
    {
        $user = Auth::user();

        if (!$user->role_id == Role::COMPANY) {
            return redirect()->back()->with('error', 'User is not a company');
        }

        $supplier = Supplier::findOrFail($id);
        $supplierController = new SupplierController();
        $companyId = $user->company->id;


        if (!$supplier) {
            return redirect()->back()->with('error', 'Does not have task from supplier');
        }

        if ($supplier->name == 'Magic Holiday') {

            $response = $supplierController->getMagicHoliday();

            $data = json_decode($response->getContent(), true);

            Log::channel('magic_holidays')->info('Magic Holiday response: ', $data);

            if (isset($data['error'])) {
                Log::channel('magic_holidays')->error('Error getting task from supplier: ' . $data['error']);
                return redirect()->back()->with('error', 'Something went wrong');
            }

            if (isset($data['status']) && $data['status'] == 'error') {
                Log::channel('magic_holidays')->error('Error getting task from supplier: ' . $data['detail']);
                return redirect()->back()->with('error', 'Something went wrong');
            }

            $data = $data['data'];

            if (isset($data['_embedded'])) { // Check if it's a list
                foreach ($data['_embedded']['reservation'] as $reservation) {
                    $response = $this->processSingleReservation($reservation, null, $companyId);

                    if ($response['status'] == 'error') {
                        return redirect()->back()->with('error', $response['message']);
                    }
                }
            } else {
                $response = $this->processSingleReservation($data, null, $companyId);

                if ($response['status'] == 'error') {
                    return redirect()->back()->with('error', $response['message']);
                }
            }

            return redirect()->back()->with('success', 'Magic Holiday task received successfully');
        }

        return redirect()->back()->with('error', 'Does not have task from supplier');
    }

    public function processSingleReservation($reservation, $agentId = null, $companyId)
    {
        $clientName = $reservation['service']['passengers'][0]['firstName'] ? $reservation['service']['passengers'][0]['firstName'] . ' ' . $reservation['service']['passengers'][0]['lastName'] : null;
        $hotel = $reservation['service']['hotel'] ?? null;
        $serviceDates = $reservation['service']['serviceDates'] ?? null;
        $prices = $reservation['service']['prices'] ?? null;
        $status = 'issued'; // Default status

        $cancellationPolicy = [];

        if ($agentId === null) {
            $agent = $reservation['agent'];

            if (!$agent) {
                throw new Exception('Agent not found in reservation data');
            }

            $agentInDB = Agent::where('name', $agent['name'])
                ->orWhere('email', 'like', $agent['email'])
                ->orWhere('phone_number', 'like', $agent['telephone'])
                ->first();

            if ($agentInDB) {
                $agentId = $agentInDB->id;
            } else {
                Log::channel('magic_holidays')->error('Agent ' . $agent['name'] . ' not found in database');
                throw new Exception('Agent ' . $agent['name'] . ' not found in database');
            }
        }

        if (isset($reservation['service']['cancellationPolicy'])) {
            //logger('Cancellation Policy: ', $reservation['service']['cancellationPolicy']);

            foreach ($reservation['service']['cancellationPolicy']['policies'] as $policy) {
                $cancellationPolicy[] = [
                    'type' => $policy['type'],
                    'charge' => $policy['charge'] !== null ? $policy['charge']['value'] : null,
                ];
            }

            $cancellationDate = $reservation['service']['cancellationPolicy']['date'];

            if ($cancellationDate) {
                $cancellationDate = Carbon::parse($cancellationDate)->toDateTimeString();

                if (Date::now()->greaterThanOrEqualTo($cancellationDate)) {
                    $status = 'issued';
                } else {
                    $status = 'confirmed';
                }
            } else {
                throw new Exception('Cancellation date not found in reservation data');
            }
        } else {
            throw new Exception('Cancellation policy not found in reservation data');
        }

        $cancellationPolicy = json_encode($cancellationPolicy);
        $supplier = Supplier::where('name', 'Magic Holiday')->first();

        if (!$supplier) {
            Log::channel('magic_holidays')->error('Supplier not found: Magic Holiday');
            return [
                'status' => 'error',
                'message' => 'Something Went Wrong',
            ];
        }

        $supplierId = $supplier->id;

        if (!$reservation['service']['rooms']) {
            Log::channel('magic_holidays')->warning('No rooms data found for reservation: ' . ($reservation['id'] ?? 'Unknown'));
            return; // Skip this reservation if no rooms are found
        }

        $processResult = [];
        $processResult['success'] = [];
        $processResult['failed'] = [];

        foreach ($reservation['service']['rooms'] as $room) {
            $enabled = true; // Assume enabled by default

            if ($reservation['service']['status'] ?? null) {
                $statusMagicTask = $reservation['service']['status'] == 'OK' ? 'issued' : 'confirmed'; //not used for now

            } else { // but we still throw an exception if status is not found
                throw new Exception('Status not found');
            }

            $taskData = [
                'client_id' => null,
                'agent_id' => $agentId,
                'company_id' => $companyId,
                'type' => 'hotel',
                'status' => $status,
                'supplier_status' => $reservation['service']['status'],
                'client_name' => $clientName,
                'reference' => (string)$reservation['id'] ?? null,
                'duration' => $serviceDates['duration'] ?? null,
                'payment_type' => $reservation['service']['payment']['type'] ?? null,
                'price' => $prices['issue']['selling']['value'] ?? null,
                'tax' => 0.00,
                'surcharge' => 0.00,
                'total' => $prices['total']['selling']['value'] ?? null,
                'cancellation_policy' => json_encode($cancellationPolicy) ?? null,
                'additional_info' => $reservation['service']['hotel']['name'] . ' - ' . $clientName,
                'supplier_id' => $supplierId,
                'venue' => $hotel['name'] ?? null,
                'invoice_price' => null,
                'voucher_status' => null,
                'refund_date' => null,
                'task_hotel_details' => [
                    'hotel_name' => $hotel['name'],
                    'hotel_country' => $hotel['countryId'],
                    'room_reference' => $room['id'] ?? null,
                    'booking_time' =>  Carbon::parse($reservation['added']['time'])->toDateTimeString() ?? null,
                    'check_in' => Carbon::parse($serviceDates['startDate'])->toDateTimeString() ?? null,
                    'check_out' => Carbon::parse($serviceDates['endDate'])->toDateTimeString() ?? null,
                    'room_reference' => (string) $room['id'] ?? null,
                    'room_number' => $room['number'] ?? null,
                    'room_type' => $room['type'] ?? null,
                    'room_amount' => count($room['passengers'] ?? []),
                    'room_details' => json_encode($room) ?? null,
                    'rate' => $price['issue']['selling']['value'] ?? null,
                    'meal_type' => $room['board'] ?? null,
                    'is_refundable' => strpos(strtolower($room['info'] ?? ''), 'non-refundable') === false,
                ],
            ];

            foreach ($taskData as $key => $value) {
                if ($value === null) {
                    $enabled = false;
                    Log::channel('magic_holidays')->warning("Missing required field: $key for reservation: " . ($reservation['id'] ?? 'Unknown'));
                    break;
                }
            }
            $taskData['enabled'] = $enabled;
            Log::channel('magic_holidays')->info('Creating Task Initiate');

            $request = new Request($taskData);
            $request->merge([
                'company_id' => $companyId,
            ]);

            $existingTask = Task::where('reference', $taskData['reference'])
                ->where('agent_id', $taskData['agent_id'])
                ->where('supplier_id', $taskData['supplier_id'])
                ->first();

            if ($existingTask) {
                if ($existingTask->supplier_status !== $taskData['supplier_status']) {
                    $existingTask->supplier_status = $taskData['supplier_status'];
                    $existingTask->status = $taskData['status'];
                    $existingTask->save();
                    Log::channel('magic_holidays')->info('Updated existing task: ' . $existingTask->reference);

                    $processResult['success'][] = [
                        'reference' => $existingTask->reference,
                        'message' => 'Task already exists, updated status',
                    ];

                    continue; // Skip creating a new task if it already exists but update the status
                } else {
                    Log::channel('magic_holidays')->info('Existing task already exists: ' . $existingTask->reference);

                    $processResult['success'][] = [
                        'reference' => $existingTask->reference,
                        'message' => 'Task already exists, no changes made',
                    ];

                    continue; // Skip creating a new task if it already exists
                }
            } else {
                $response = $this->store($request);
            }

            $response = json_decode($response->getContent(), true);
            logger('Task created: ', $response);

            if ($response['status'] == 'error') {
                Log::channel('magic_holidays')->error('Error creating task: ' . $response['message']);

                $processResult['failed'][] = [
                    'reference' => $taskData['reference'],
                    'message' => 'Error creating task: ' . $response['message'],
                ];
            }

            $task = Task::with('hotelDetails')->find($response['data']['id']);

            if (!$task) {
                Log::channel('magic_holidays')->error('Task not found after creation: ' . $response['data']['id']);
                $processResult['failed'][] = [
                    'reference' => $taskData['reference'],
                    'message' => 'Task not found after creation',
                ];
                continue; // Skip to the next room if task creation failed
            }

            $passengers = $reservation['service']['passengers'] ?? null;

            $adultCount = 0;
            $childCount = 0;

            foreach ($room['passengers'] as $passengerId) {
                $passenger = collect($passengers)->where('paxId', $passengerId)->first();

                if (!$passenger) {
                    continue;
                }

                if ($passenger['type'] == 'adult') {
                    $adultCount++;
                } elseif ($passenger['type'] == 'child') {
                    $childCount++;
                } else {
                    logger('Unknown passenger type: ' . $passenger['type']);
                    continue;
                }
            }

            try {
                $room = Room::create([
                    'task_hotel_details_id' => $task->hotelDetails->id,
                    'name' => $room['name'] ?? null,
                    'reference' => (string)$room['id'] ?? null,
                    'adult_count' => $adultCount,
                    'child_count' => $childCount,
                ]);
            } catch (Exception $e) {
                $task->delete();

                Log::channel('magic_holidays')->error('Error creating room: ' . $e->getMessage(), [
                    'reservation' => $reservation,
                    'room' => $room,
                ]);

                $processResult['failed'][] = [
                    'reference' => $taskData['reference'],
                    'message' => 'Error creating room: ' . $e->getMessage(),
                ];
                continue; // Skip to the next room if room creation failed
            }


            Log::channel('magic_holidays')->info('Task created for reservation: ' . ($reservation['id'] ?? 'Unknown') . ', Room: ' . ($room['id'] ?? 'Unknown'));

            $processResult['success'][] = [
                'reference' => $taskData['reference'],
                'message' => 'Task created successfully',
                'room_id' => $room->id,
            ];
        }


        if (count($processResult['success']) > 0) {
            Log::channel('magic_holidays')->info('Successfully processed reservation: ' . ($reservation['id'] ?? 'Unknown'));
        }

        if (count($processResult['failed']) > 0) {
            Log::channel('magic_holidays')->error('Failed to process reservation: ' . ($reservation['id'] ?? 'Unknown'));
        }

        return [
            'status' => count($processResult['failed']) > 0 ? 'error' : 'success',
            'message' => count($processResult['failed']) > 0 ? 'Some tasks failed to process' : 'All tasks processed successfully',
            'data' => $processResult,
        ];
    }

    public function supplierTaskForAgent(Request $request)
    {
        $request->validate([
            'agent_id' => 'required',
            'supplier_ref' => 'nullable',
            'task_file' => 'nullable|mimes:pdf,txt',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $user = Auth::user();
        $agent = Agent::findOrFail($request->agent_id);

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company->id;
        } else {
            return redirect()->back()->with('error', 'User not authorized to create task');
        }

        if (!$agent) {
            return redirect()->back()->with('error', 'Agent not found');
        }

        $supplier = Supplier::findOrFail($request->supplier_id);
        $supplierController = new SupplierController();

        switch ($supplier->name) {
            case 'Magic Holiday':

                if (!$request->supplier_ref) {
                    return redirect()->back()->with('error', 'Supplier reference is required for Magic Holiday');
                }

                $response = $supplierController->getMagicHoliday($request->supplier_ref);
                $response = json_decode($response->getContent(), true);

                Log::channel('magic_holidays')->info('Magic Holiday response: ', $response);

                if (isset($response['status']) && $response['status'] == 'error') {
                    return redirect()->back()->with('error', $response['message']);
                }
                $data = $response['data'];

                if (isset($data['_embedded'])) { // Check if it's a list
                    foreach ($data['_embedded']['reservation'] as $reservation) {
                        $response = $this->processSingleReservation($reservation, $agent->id, $companyId);

                        if ($response['status'] == 'error') {
                            return redirect()->back()->with('error', $response['message']);
                        }

                        $supplierController->magicReserveWebhook($reservation['id']);
                    }
                } else {

                    $response = $this->processSingleReservation($data, $agent->id, $companyId);

                    if ($response['status'] == 'error') {
                        return redirect()->back()->with('error', $response['message']);
                    }

                    $supplierController->magicReserveWebhook($data['id']);
                }

                return redirect()->back()->with('success', 'Magic Holiday task received successfully');
            case 'Amadeus':
                $response = $this->upload($request);

                return redirect()->back()->with($response['status'], $response['message']);
            default:
                return redirect()->back()->with('error', 'This supplier will be available soon');
        }
    }

    public function getTboTask($companyId)
    {
        logger('TBO task is running');
        $tboController = new TBOController();

        $bookingDetailsToday = $tboController->bookingDetailByDate(
            new Request([
                'startDate' => date('Y-m-d', strtotime('-60 days')),
                'endDate' => date('Y-m-d')
            ])
        );

        if (isset($bookingDetailsToday['error'])) {
            logger('TBO Task Error: ' . $bookingDetailsToday['error']);
            return;
        }


        logger('TBO Task: ', $bookingDetailsToday);

        foreach ($bookingDetailsToday as $booking) {
            // $agent = Agent::where('tbo_reference', $booking['ClientReferenceNumber'])->first();

            // if (!$agent) {
            //     logger('TBO Task Error: Client Reference Number does not register with any agent. Client Reference Number: ' . $booking['ClientReferenceNumber']);
            //     return;
            // }

            $supplier = Supplier::where('name', 'TBO Holiday')->first();

            $existingTask = Task::where(['reference' => $booking['ConfirmationNo'], 'supplier_id' => $supplier->id])
                ->withoutGlobalScope('enabled')->first();

            if ($existingTask) {
                logger('TBO Task Error: Task already exists');
                return redirect()->back()->with('error', 'Task ' . $existingTask->reference . ' already exists');
            }

            $checkInDate = new \DateTime($booking['CheckInDate']);
            $checkOutDate = new \DateTime($booking['CheckOutDate']);
            $interval = $checkInDate->diff($checkOutDate);
            $hours = $interval->days * 24 + $interval->h;

            $details = $tboController->bookingDetail(
                new Request([
                    'confirmationNumber' => $booking['ConfirmationNo']
                ])
            );

            logger('TBO Task Details: ', $details);

            if (!isset($details['Rooms'])) {
                logger('TBO Task Error: No rooms found');
                return;
            }

            if (count($details['Rooms']) < 1) {
                logger('TBO Task Error: No rooms found');
                return;
            }

            foreach ($details['Rooms'] as $room) {

                if (!isset($room['CustomerDetails'])) {
                    logger('TBO Task Error: No customer details found');
                    return;
                }

                if (count($room['CustomerDetails']) < 1) {
                    logger('TBO Task Error: No customer details found');
                    return;
                }

                foreach ($room['CustomerDetails'][0]['CustomerNames'] as $key => $customer) {
                    $client = Client::updateOrCreate([
                        'name' => $customer['FirstName'] . ' ' . $customer['LastName'],
                    ]);

                    if (!$client) {
                        logger('TBO Task Error: Client failed to create');
                        return;
                    }

                    logger('TBO Task Client: ' . $client->name . ' created');

                    if ($key == 0) {
                        $leaderCustomer = $client;

                        logger('TBO Task : Leader Customer: ' . $leaderCustomer->name);
                    }
                }
                try {
                    $task = Task::create([
                        'client_id' => $client->id,
                        'agent_id' => null,
                        'company_id' => $companyId,
                        'type' => 'hotel',
                        'status' => strtolower($booking['BookingStatus']),
                        'client_name' => $leaderCustomer->name,
                        'reference' => $booking['ConfirmationNo'],
                        'duration' => $hours,
                        'payment_type ' => null,
                        'price' => $room['TotalFare'],
                        'tax' => $room['TotalTax'],
                        'surcharge' => null,
                        'total' => $room['TotalFare'],
                        'cancellation_policy' => json_encode($room['CancelPolicies']),
                        'additional_info' => null,
                        'supplier_id' => $supplier->id,
                        'venue' =>  $details['HotelDetails']['City'],
                        'invoice_price' => null,
                        'voucher_status' => (string)$details['VoucherStatus'],
                        'refund_date' => null,

                    ]);
                } catch (Exception $e) {
                    logger('TBO Task Error: ' . $e->getMessage());
                    return redirect()->back()->with('error', 'Task failed to create');
                }

                try {
                    $hotelRating = 0.0;

                    switch ($details['HotelDetails']['Rating']) {
                        case 'OneStar':
                            $hotelRating = 1.0;
                            break;
                        case 'TwoStar':
                            $hotelRating = 2.0;
                            break;
                        case 'ThreeStar':
                            $hotelRating = 3.0;
                            break;
                        case 'FourStar':
                            $hotelRating = 4.0;
                            break;
                        case 'All':
                            $hotelRating = 5.0;
                            break;
                        default:
                            $hotelRating = 0.0;
                            break;
                    }

                    $taskHotelDetails = TaskHotelDetail::create([
                        'task_id' => $task->id,
                        'hotel_id' => 1,
                        'booking_time' => Date('Y-m-d H:i:s', strtotime($booking['BookingDate'])),
                        'check_In' => Date('Y-m-d H:i:s', strtotime($booking['CheckInDate'])),
                        'check_out' => Date('Y-m-d H:i:s', strtotime($booking['CheckOutDate'])),
                        'room_amount' => 1,
                        'room_type' => json_encode($room['Name']),
                        'room_details' => $room['Inclusion'],
                        'room_promotion' => $room['RoomPromotion'] ?? null,
                        'rate' => $hotelRating,
                        'meal_type' => $room['MealType'],
                        'is_refundable' => $room['IsRefundable'],
                        'supplements' => isset($room['Supplements']) ? json_encode($room['Supplements']) : null,
                    ]);

                    logger('task with id: ' . $task->id . ' and task hotel details with id: ' . $taskHotelDetails->id . ' has been created');
                } catch (Exception $e) {
                    logger('TBO Task Error: ' . $e->getMessage());
                    $task->delete();
                    return redirect()->back()->with('error', 'Task Details failed to create');
                }
            }
        }

        logger('TBO task is done');

        return redirect()->back()->with('success', 'TBO task received successfully');
    }

    public function flightPdf($taskId)
    {
        $task = Task::with('flightDetails', 'flightDetails.countryFrom', 'flightDetails.countryTo')->findOrFail($taskId);
        $flight = $task->flightDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        return view('tasks.pdfView.flight-view', compact('task', 'flight', 'companyLogoSrc'));
    }

    public function flightPdfDownload($taskId)
    {
        $task = Task::with('flightDetails', 'flightDetails.countryFrom', 'flightDetails.countryTo')->findOrFail($taskId);
        $flight = $task->flightDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        $pdf = Pdf::loadView('tasks.pdf.flight', compact('task', 'flight', 'companyLogoSrc'));

        return $pdf->download('flight.pdf');
    }

    public function hotelPdf($taskId)
    {
        $task = Task::with('hotelDetails', 'hotelDetails.hotel', 'hotelDetails.room', 'hotelDetails.hotel.country')->findOrFail($taskId);
        $hotelDetails = $task->hotelDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        return view('tasks.pdfView.hotel-view', compact('task', 'hotelDetails', 'companyLogoSrc'));
    }


    public function hotelPdfDownload($taskId)
    {
        $task = Task::with('hotelDetails', 'hotelDetails.hotel', 'hotelDetails.room', 'hotelDetails.hotel.country')->findOrFail($taskId);
        $hotelDetails = $task->hotelDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        $pdf = Pdf::loadView('tasks.pdf.hotel', compact('task', 'hotelDetails', 'companyLogoSrc'));

        return $pdf->download('hotel.pdf');
    }

    public function receiptPdf($taskId)
    {
        $task = Task::with('invoiceDetail', 'invoiceDetail.task', 'invoiceDetail.invoice', 'invoiceDetail.invoice.payment')->findOrFail($taskId);
        $invoiceDetail = $task->invoiceDetail;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        return view('tasks.pdfView.receipt-view', compact('task', 'invoiceDetail', 'companyLogoSrc'));
    }

    public function receiptPdfDownload($taskId)
    {
        $task = Task::with('invoiceDetail', 'invoiceDetail.task', 'invoiceDetail.invoice', 'invoiceDetail.invoice.payment')->findOrFail($taskId);
        $invoiceDetail = $task->invoiceDetail;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        $pdf = Pdf::loadView('tasks.pdf.receipt', compact('task', 'invoiceDetail', 'companyLogoSrc'));

        return $pdf->download('receipt.pdf');
    }

    public function ReverseUnpaidVoidedTask(Task $originalTask)
    {

        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $originalTask->company_id)
            ->first();

        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $originalTask->company_id)
            ->first();

        $supplier = Supplier::find($originalTask->supplier_id);
        $supplierCompany = SupplierCompany::where('supplier_id', $originalTask->supplier_id)
            ->where('company_id', $originalTask->company_id)
            ->first();

        $supplierPayable = Account::where('name', $supplier->name)
            ->where('company_id', $originalTask->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        $companyIssuedBy = $originalTask->issued_by;

        if (!$companyIssuedBy) {
            Log::error('Company issued by not found for task ID: ' . $originalTask->id);
            throw new Exception('Company issued by not found.');
        }

        $issuedByAccount = Account::where('name', $companyIssuedBy)
            ->where('company_id', $originalTask->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        if (!$issuedByAccount) {
            Log::error('Issued by account not found for task ID: ' . $originalTask->id);
            throw new Exception('Issued by account not found.');
        }
        $supplierCost = Account::where('name', $supplier->name)
            ->where('company_id', $originalTask->company_id)
            ->where('root_id', $expenses->id)
            ->first();

        if (!$supplierPayable || !$supplierCost) {
            Log::error('Missing required accounts for reversal.', [
                'payable' => $supplierPayable,
                'cost' => $supplierCost
            ]);
            throw new Exception('Missing required accounts for reversal.');
        }

        Log::info('Recording reversal journal & transaction for task ID: ' . $originalTask->id);

        $transaction = Transaction::create([
            'branch_id' => $originalTask->agent->branch_id,
            'company_id' => $originalTask->company_id,
            'entity_id' => $originalTask->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $originalTask->total,
            'task_id' => $originalTask->id,
            'description' => 'Void reversal for: ' . $originalTask->reference,
            'reference_type' => 'Payment',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $originalTask->company_id,
            'branch_id' => $originalTask->agent->branch_id,
            'account_id' => $supplierCost->id,
            'task_id' => $originalTask->id,
            'transaction_date' => now(),
            'description' => 'Reversal: Cancelled Cost from ' . $supplierCompany->supplier->name,
            'name' => $supplierCompany->supplier->name,
            'debit' => 0,
            'credit' => $originalTask->total,
            'balance' => $originalTask->total,
            'type' => 'payable',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $originalTask->company_id,
            'branch_id' => $originalTask->agent->branch_id,
            'account_id' => $issuedByAccount->id,
            'task_id' => $originalTask->id,
            'transaction_date' => now(),
            'description' => 'Reversal: Cancelled Payable to ' . $supplierCompany->supplier->name,
            'name' => $supplierCompany->supplier->name,
            'debit' => $originalTask->total,
            'credit' => 0,
            'balance' => $originalTask->total,
            'type' => 'payable',
        ]);

        Log::info('Void reversal journal completed for task: ' . $originalTask->reference);
        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Unpaid void task reversal journal completed.',
            'data' => $originalTask,
        ], 201);
    }

    public function clientPassport(Request $request)
    {
        if ($request->hasFile('file')) {
            try {
                $file = $request->file('file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads', $fileName, 'public');

                $fullFilePath = storage_path('app/public/' . $filePath);

                Log::info('Processing passport file with AI:', [
                    'fileName' => $fileName,
                    'filePath' => $fullFilePath
                ]);

                $aiManager = new AIManager();
                $response = $aiManager->extractPassportData($fullFilePath, $fileName);

                Log::info('AI passport extraction response:', ['response' => $response]);

                if ($response['status'] === 'success') {
                    $passportData = $response['data'];

                    return response()->json([
                        'success' => true,
                        'message' => 'Passport data extracted successfully using AI!',
                        'data' => $passportData,
                    ], 200);
                } else {
                    Log::error('AI passport extraction failed: ' . $response['message']);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to extract passport data using AI: ' . $response['message'],
                        'errors' => $response['message'],
                    ], 400);
                }
            } catch (Exception $e) {
                Log::error('Failed to process passport with AI: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Error processing passport with AI',
                    'errors' => $e->getMessage(),
                ], 400);
            }
        } else {
            Log::error('No file uploaded for passport processing');
            return response()->json([
                'success' => false,
                'message' => 'Error processing passport',
                'errors' => 'No file uploaded.',
            ], 400);
        }
    }
}
