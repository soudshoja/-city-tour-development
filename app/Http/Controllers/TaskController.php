<?php

namespace App\Http\Controllers;
// testing
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
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use Illuminate\Support\Facades\Date;
use App\Models\FileUpload;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

// use Carbon\Carbon;

class TaskController extends Controller
{
    use NotificationTrait, Converter;

    public function index(Request $request)
    {
        $user = Auth::user();
        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice', 'refundDetail', 'originalTask', 'linkedTask');
        $paymentMethod = Account::where('parent_id', 39)->get();

        if ($search = $request->query('q')) {

            $tasks = $tasks->where(function ($query) use ($search) {
                $query->where('reference', 'like', '%' . $search . '%')
                    ->orWhere('client_name', 'like', '%' . $search . '%')
                    ->orWhere('ticket_number', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                    });
            });
        }
        $countries = Country::all();

        if ($user->role_id == Role::ADMIN) {
            $tasks = $tasks;
            $clients = Client::all();
            $agents = Agent::all();
            $suppliers = Supplier::all();
        } elseif ($user->role_id == Role::COMPANY) {

            $branches = Branch::where('company_id', $user->company->id)->get();
            $agents = Agent::with('branch')->whereIn('branch_id', $branches->pluck('id'))->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $tasks = $tasks->where('company_id', $user->company->id);

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
            $tasks = $tasks->whereIn('agent_id', $agentsId)->where('company_id', $user->company_id);

            $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->branch->company_id);
            })->get();
        } elseif ($user->role_id == Role::AGENT) {

            $agents = Agent::with('branch')->where('id', $user->agent->id)->get();
            $clients = Client::where('agent_id', $user->agent->id)->get();

            // Get tasks assigned to this agent OR unassigned tasks in the same company
            $tasks = $tasks->where(function ($query) use ($user) {
                $query->where('agent_id', $user->agent->id)
                    ->orWhere(function ($subQuery) use ($user) {
                        $subQuery->whereNull('agent_id')
                            ->where('company_id', $user->agent->branch->company_id);
                    });
            })->where('company_id', $user->agent->branch->company_id);

            $companyId = $user->agent->branch->company_id;
            $suppliers = Supplier::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->get();
        } else {
            return redirect()->back()->with('error', 'User not authorized to view tasks.');
        }
  
        $taskCount = $tasks->count();
        $tasks = $tasks->orderBy('issued_date', 'desc')->orderBy('id', 'desc')->paginate(50);
        $types = Task::distinct()->pluck('type');

        $importedTask = Cache::get('imported_task');


        return view('tasks.index', compact(
            'tasks',
            'taskCount',
            'agents',
            'clients',
            'suppliers',
            'types',
            'countries',
            'paymentMethod'
            // 'searchTask'
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
            'issued_date' => 'nullable|date',
        ]);

        $queryChkExistTask = Task::query();
        $queryChkExistTask->where('reference', $request->reference)
            ->where('company_id', $request->company_id)
            ->where('client_id', $request->client_id)
            ->where('client_name', $request->client_name) // same reference name but different client name considered as different task
            ->where('status', $request->status);

        if ($request->supplier_id) {
            $queryChkExistTask->where('supplier_id', $request->supplier_id);
        }

        $existingTask = $queryChkExistTask->first();

        if ($existingTask) {

            if ($existingTask->gds_reference == null || $existingTask->airline_reference == null) {
                $existingTask->gds_reference = $request->gds_reference;
                $existingTask->airline_reference = $request->airline_reference;
                $existingTask->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Task updated with GDS and Airline reference.',
                    'data' => $existingTask,
                ], 200);
            } else {

                $existingTask->issued_date = $request->issued_date;
                $existingTask->save();
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Task with this reference already exists.',
            ], 422);
        }
        $amadeusId = Supplier::where('name', 'Amadeus')->value('id');

        if ($request->supplier_id !== $amadeusId) {

            Log::info("remove GDS and Airline references for non-Amadeus tasks", [
                'supplier_id' => $request->supplier_id,
                'gds_reference' => $request->gds_reference,
                'airline_reference' => $request->airline_reference
            ]);

            // Use merge() to update the input array so $request->all() reflects changes
            $request->merge([
                'gds_reference' => null,
                'airline_reference' => null
            ]);

            Log::info("GDS and Airline references removed for non-Amadeus supplier", [
                'supplier_id' => $request->supplier_id,
                'updated_gds_reference' => $request->gds_reference,
                'updated_airline_reference' => $request->airline_reference
            ]);
        }

        $supplierName = Supplier::where('id', $request->supplier_id)->value('name');

        if(strtolower($supplierName) == 'jazeera airways' || strtolower($supplierName) == 'fly dubai' || strtolower($supplierName) == 'vfs') {
            if ($request->status == 'confirmed') {
                $status = 'issued';
            } elseif ($request->status == 'on hold') {
                $status = 'confirmed';
            } else {
                $status = $request->status;
            }
            $request->merge(['status' => $status]);
        }

        // Set default values for nullable fields using merge()
        $request->merge([
            'penalty_fee' => $request->penalty_fee ?? 0,
            'passenger_name' => $request->client_name ?? null,
            'tax' => $request->tax ?? 0,
            'enabled' => $request->enabled ?? false
        ]);

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

        if ($request->file_name) {
            $fileUpload = FileUpload::where([
                'file_name' => $request->file_name,
                'company_id' => $request->company_id,
            ])->first();

            if ($fileUpload && $fileUpload->user_id) {
                Log::info("FileUpload found for {$request->file_name}", [
                    'file_upload_id' => $fileUpload->id,
                    'user_id' => $fileUpload->user_id,
                    'supplier_id' => $fileUpload->supplier_id
                ]);

                $agent = Agent::where('user_id', $fileUpload->user_id)->first();

                if ($agent) {
                    $request->merge(['agent_id' => $agent->id]);
                    Log::info("Assigned agent_id from file uploader", [
                        'file_name' => $request->file_name,
                        'user_id' => $fileUpload->user_id,
                        'agent_id' => $agent->id,
                        'reason' => 'File uploader is an agent'
                    ]);
                } else {
                    Log::info("File uploader is not an agent", [
                        'file_name' => $request->file_name,
                        'user_id' => $fileUpload->user_id,
                        'user_type' => 'admin_or_company'
                    ]);
                }
            } else {
                Log::warning("FileUpload not found or no user_id for file: {$request->file_name}");
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
           
            // Set enabled status: task must be complete AND have an agent assigned
            if($task->is_complete && $task->agent_id && $task->client !== null) {
                $task->enabled = true;
                $task->save(); 
                Log::info('Task enabled for complete task with agent: ' . $task->reference);
            } else {
                $task->enabled = false;
                $task->save();
                Log::info('Task disabled - reason: ' . (!$task->is_complete ? 'incomplete' : 'no agent assigned') . ' - task: ' . $task->reference);
            }

            // Process financial transactions immediately if task is complete (regardless of agent assignment)
            // This ensures company liability to supplier is tracked immediately
            // Special case: Void tasks should ALWAYS process financials if they have an original_task_id
            $shouldProcessFinancials = $task->is_complete || 
                                     ($task->status === 'void' && $task->original_task_id);
            
            if ($shouldProcessFinancials) {
                $reason = $task->is_complete ? 'complete task' : 'void task with original_task_id';
                Log::info("Processing financial transactions for {$reason}: " . $task->reference . ' (agent_id: ' . ($task->agent_id ?? 'none') . ')');
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
     * Get branch ID for task financial processing
     * Returns agent's branch_id if agent exists, otherwise returns company's main branch_id
     */
    private function getTaskBranchId(Task $task)
    {
        if ($task->agent && $task->agent->branch_id) {
            return $task->agent->branch_id;
        }
        
        // Get company's main branch if no agent
        $company = \App\Models\Company::find($task->company_id);
        if (!$company) {
            throw new Exception('Company not found for task: ' . $task->reference);
        }
        
        $mainBranch = $company->getMainBranch();
        return $mainBranch->id;
    }

    /**
     * Process all financial transactions for a task
     */
    private function processTaskFinancial(Task $task)
    {
        if(!($task->status == 'issued' || $task->status == 'reissued' || $task->status == 'void' || $task->status == 'refund' || $task->status == 'emd')) {
            Log::info('Skipping financial processing for task: ' . $task->reference . ' - status: ' . $task->status);
            return;
        }
        Log::info('Processing financial for task: ' . $task->reference);
        
        // Special handling for void tasks: they should process even if incomplete
        // as long as they have an original_task_id to reference
        if (!$task->is_complete) {
            if ($task->status === 'void' && $task->original_task_id) {
                Log::info('Allowing incomplete void task to process financials: ' . $task->reference . ' (has original_task_id: ' . $task->original_task_id . ')');
            } else {
                throw new Exception('Task is not complete. Missing required fields: ' . $this->getMissingFields($task));
            }
        }

        // Get branch_id - from agent if exists, otherwise from company's main branch
        $branchId = $this->getTaskBranchId($task);

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

        if (in_array($task->type, ['flight', 'visa'])) {
            Log::info('Processing flight task financial for: ' . $task->reference);
            $companyIssuedBy = $task->issued_by ?? 'Not Issued';
            
            Log::info('Issued by value determination', [
                'original_issued_by' => $task->issued_by,
                'final_company_issued_by' => $companyIssuedBy,
                'is_null_issued_by' => is_null($task->issued_by)
            ]);

            $issuedByAccount = Account::where('name', $companyIssuedBy)
                ->where('company_id', $task->company_id)
                ->where('root_id', $liabilities->id)
                ->where('parent_id', $supplierPayable->id)
                ->first();

            Log::info('Issued By Account: ', ['account' => $issuedByAccount]);

            if (!$issuedByAccount) {
                Log::info('Creating new issued by account for: ' . $companyIssuedBy . ' (was null: ' . (is_null($task->issued_by) ? 'yes' : 'no') . ')');
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
                        'branch_id' => $branchId,
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

                    Log::info('New issued by account created for task: ' . $task->reference, [
                        'issuedByAccount' => $issuedByAccount,
                        'account_id' => $issuedByAccount ? $issuedByAccount->id : 'null',
                        'account_name' => $issuedByAccount ? $issuedByAccount->name : 'null'
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create issued by account: ' . $e->getMessage(), [
                        'task_reference' => $task->reference,
                        'company_issued_by' => $companyIssuedBy,
                        'supplier_payable_id' => $supplierPayable->id,
                        'exception' => $e->getMessage()
                    ]);
                    throw new Exception('Failed to create issued by account: ' . $e->getMessage());
                }
            }

            // Final validation that we have a valid issued by account for flight tasks
            if (!$issuedByAccount) {
                Log::error('Flight task still does not have issued by account after creation attempt', [
                    'task_reference' => $task->reference,
                    'issued_by' => $task->issued_by,
                    'company_issued_by' => $companyIssuedBy ?? 'undefined'
                ]);
                throw new Exception('Failed to create or find issued by account for flight task.');
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

        // Additional validation: For flight tasks, we must have an issuedByAccount to avoid using parent account
        if ($task->type == 'flight' && !$issuedByAccount) {
            Log::error('Flight task missing issued by account - this should not happen!', [
                'task_reference' => $task->reference,
                'issued_by' => $task->issued_by,
                'supplier_payable_has_children' => $supplierPayable->children()->exists()
            ]);
            throw new Exception('Flight task must have a valid issued by account to avoid using parent account.');
        }

        // Process based on status
        switch (strtolower($task->status)) {
            case 'issued':
                Log::info('Processing issued task financial for: ' . $task->reference);
                $this->processIssuedTask($task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId);
                break;
            case 'reissued':
                Log::info('Processing reissued task financial for: ' . $task->reference);
                $this->processIssuedTask($task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId);
                break;
            case 'emd':
                Log::info('Processing EMD task financial for: ' . $task->reference);
                $this->processIssuedTask($task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId);
                break;
            case 'void':
                Log::info('Processing void task financial for: ' . $task->reference);
                $this->processVoidTask($task, $branchId);
                break;
            case 'refund':
                Log::info('Processing refund task financial for: ' . $task->reference);
                $this->processRefundTask($task, $branchId);
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
    private function processIssuedTask(Task $task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId)
    {
        // Use task's issued_date as transaction_date
        $transactionDate = $task->issued_date ? Carbon::parse($task->issued_date) : Carbon::now();
        
        $transaction = Transaction::create([
            'branch_id' => $branchId,
            'company_id' => $task->company_id,
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $task->total,
            'description' => 'Task created: ' . $task->reference,
            'reference_type' => 'Payment',
            'transaction_date' => $transactionDate,
        ]);

        if (!$transaction) {
            throw new Exception('Transaction creation failed.');
        }

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $supplierCost->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
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
            'branch_id' => $branchId,
            'account_id' => $issuedByAccount ? $issuedByAccount->id : $supplierPayable->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
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
    private function processVoidTask(Task $task, $branchId)
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
    private function processRefundTask(Task $task, $branchId)
    {
        // Get the same accounts as in processTaskFinancial for consistency
        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $task->company_id)
            ->first();

        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $task->company_id)
            ->first();

        if (!$liabilities || !$expenses) {
            throw new Exception('Liabilities or Expenses account not found.');
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

        if (!$supplierCost || !$supplierPayable) {
            throw new Exception('Supplier accounts not found for refund processing.');
        }

        // For flight tasks, use the same issued_by logic as in processTaskFinancial
        $issuedByAccount = null;
        $payableAccountToUse = $supplierPayable;

        if ($task->type == 'flight' ) {
            Log::info('Processing flight refund task financial for: ' . $task->reference);
            $companyIssuedBy = $task->issued_by ?? 'Not Issued';
            
            Log::info('Refund - Issued by value determination', [
                'original_issued_by' => $task->issued_by,
                'final_company_issued_by' => $companyIssuedBy,
                'is_null_issued_by' => is_null($task->issued_by)
            ]);

            $issuedByAccount = Account::where('name', $companyIssuedBy)
                ->where('company_id', $task->company_id)
                ->where('root_id', $liabilities->id)
                ->where('parent_id', $supplierPayable->id)
                ->first();

            Log::info('Refund - Issued By Account lookup: ', ['account' => $issuedByAccount]);

            if (!$issuedByAccount) {
                Log::info('Refund - Creating new issued by account for: ' . $companyIssuedBy . ' (was null: ' . (is_null($task->issued_by) ? 'yes' : 'no') . ')');
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
                        'branch_id' => $branchId,
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

                    Log::info('Refund - New issued by account created for task: ' . $task->reference, [
                        'issuedByAccount' => $issuedByAccount,
                        'account_id' => $issuedByAccount ? $issuedByAccount->id : 'null',
                        'account_name' => $issuedByAccount ? $issuedByAccount->name : 'null'
                    ]);
                } catch (Exception $e) {
                    Log::error('Refund - Failed to create issued by account: ' . $e->getMessage(), [
                        'task_reference' => $task->reference,
                        'company_issued_by' => $companyIssuedBy,
                        'supplier_payable_id' => $supplierPayable->id,
                        'exception' => $e->getMessage()
                    ]);
                    throw new Exception('Failed to create issued by account for refund: ' . $e->getMessage());
                }
            }

            // Use the issued by account for flight tasks
            if ($issuedByAccount) {
                $payableAccountToUse = $issuedByAccount;
            } else {
                Log::error('Refund - Flight task missing issued by account', [
                    'task_reference' => $task->reference,
                    'issued_by' => $task->issued_by,
                    'company_issued_by' => $companyIssuedBy
                ]);
                throw new Exception('Failed to create or find issued by account for flight refund task.');
            }
        }

        Log::info('Processing refund task with correct accounts', [
            'task_reference' => $task->reference,
            'supplier_name' => $supplier->name,
            'payable_account' => $payableAccountToUse->name,
            'payable_account_id' => $payableAccountToUse->id,
            'expense_account' => $supplierCost->name,
            'expense_account_id' => $supplierCost->id,
            'is_flight_task' => $task->type == 'flight',
            'issued_by' => $task->issued_by
        ]);

        // Use task's issued_date as transaction_date
        $transactionDate = $task->issued_date ? Carbon::parse($task->issued_date) : Carbon::now();

        // Create Transaction Record
        $transaction = Transaction::create([
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'transaction_type' => 'debit',
            'amount' => $task->total,
            'description' => 'Refund Task: ' . $task->reference,
            'reference_type' => 'Refund',
            'name' => $task->client_name,
            'transaction_date' => $transactionDate,
        ]);

        if (!$transaction) {
            throw new Exception('Refund Transaction creation failed.');
        }

        // Create journal entries using the correct accounts
        JournalEntry::create([
            'transaction_date' => $transactionDate,
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $payableAccountToUse->id,
            'task_id' => $task->id,
            'description' => 'Refund Task - Supplier refunds us (Liabilities): ' . $payableAccountToUse->name,
            'debit' => $task->total,
            'credit' => 0,
            'name' => $supplier->name,
            'type' => 'refund',
        ]);

        JournalEntry::create([
            'transaction_date' => $transactionDate,
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $supplierCost->id,
            'task_id' => $task->id,
            'description' => 'Refund Task - Supplier cost return (Expenses): ' . $supplierCost->name,
            'debit' => 0,
            'credit' => $task->total,
            'name' => $supplier->name,
            'type' => 'refund',
        ]);
    }

    /**
     * Enable task and process financials when task is complete
     */
    // public function enableTask(Task $task)
    // {
    //     if (!$task->is_complete) {
    //         throw new Exception('Task is not complete. Missing required fields: ' . $this->getMissingFields($task));
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $task->enabled = true;
    //         $task->save();

    //         $this->processTaskFinancial($task);

    //         DB::commit();

    //         return [
    //             'status' => 'success',
    //             'message' => 'Task enabled and processed successfully.',
    //         ];
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

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

            if(!$task->agent_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must have an agent assigned to be enabled.'
                ], 400);
            }

            if($task->client == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must have a client assigned to be enabled.'
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

        // Use task's issued_date as transaction_date
        $transactionDate = $task->issued_date ? Carbon::parse($task->issued_date) : Carbon::now();

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
            'transaction_date' => $transactionDate,
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
                'transaction_date' => $transactionDate,
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
            'reference' => 'nullable|string',
            'status' => 'required',
            'price' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'surcharge' => 'nullable|numeric',
            'total' => 'required|numeric',
            'payment_method_account_id' => 'nullable|string',
            'agent_id' => 'nullable',
            'client_id' => 'nullable|exists:clients,id',
            'supplier_id' => 'required',
        ], [
            'supplier_id.required' => 'Please select a supplier',
            'status.required' => 'Please select a status',
            'total.required' => 'Please enter the total amount',
        ]);

        if (strtolower($request->status) !== 'issued' && strtolower($request->status) !== 'confirmed') {
            $request->validate([
                'original_task_id' => 'required|exists:tasks,id',
            ], [
                'original_task_id.required' => 'Task must be linked to an original task',
            ]);
        }

        DB::beginTransaction();

        try {
            $task = Task::findOrFail($id);
            $oldPaymentMethod = $task->payment_method_account_id;

            Log::info('Before task detail update: agent_id: ' . $task->agent_id . ', client_id: ' . $task->client_id);
            Log::info('Incoming Request: agent_id: ' . $request->agent_id . ', client_id: ' . $request->client_id);

            $prevClientName = $task->client_name;
            $prevAgentId = $task->agent_id;
            $wasEnabled = JournalEntry::where('task_id', $task->id)->exists();

            $data = $request->only([
                'reference',
                'status',
                'price',
                'tax',
                'surcharge',
                'total',
                'agent_id',
                'supplier_id',
                'original_task_id',
                'payment_method_account_id',
            ]);

            if ($request->filled('client_id')) {
                $client = Client::findOrFail($request->client_id);
                $data['client_id'] = $client->id;
                $data['client_name'] = $client->name;
            }

            if ($request->filled('agent_id')) {
                $agent = Agent::findOrFail($request->agent_id);
                $data['agent_id'] = $agent->id;
                $data['agent_name'] = $agent->name;
            }

            $task->update($data);
            Log::info('After task detail update: agent_id: ' . $task->agent_id . ', client_id: ' . $task->client_id);

            if ($request->filled('payment_method_account_id') && $request->payment_method_account_id != $oldPaymentMethod) {
                $this->reverseJournalforChangedPaymentMethod($task);
            }

            // Check if agent was just assigned or changed
            $agentWasAssigned = !$prevAgentId && $task->agent_id;
            $agentWasChanged = $prevAgentId && $task->agent_id && $prevAgentId != $task->agent_id;

            // Update enabled status: task must be complete AND have an agent assigned
            $shouldBeEnabled = $task->is_complete && $task->agent_id;

            if ($shouldBeEnabled && !$wasEnabled) {
                $task->enabled = true;
                $task->save();
                // Process financials if not already processed
                if (!JournalEntry::where('task_id', $task->id)->exists()) {
                    Log::info('Processing financial transactions for newly enabled task: ' . $task->reference);
                    $this->processTaskFinancial($task);
                }
            } elseif (!$shouldBeEnabled && $wasEnabled) {
                $task->enabled = false;
                $task->save();
            } else {
                $task->enabled = $shouldBeEnabled;
                $task->save();
            }

            // If agent was assigned or changed, update branch_id in existing journal entries
            if (($agentWasAssigned || $agentWasChanged) && $task->agent_id) {
                Log::info('Agent assignment/change detected for task: ' . $task->reference . 
                         ' (prev: ' . ($prevAgentId ?? 'none') . ', new: ' . $task->agent_id . ')');
                $this->updateJournalEntriesBranch($task);
            }

            $transaction = Transaction::with('journalEntries')
                ->where('description', 'like', '%' . $task->reference . '%')
                ->first();

            if (isset($client) && $transaction) {
                $transaction->journalEntries->each(function ($journalEntry) use ($client, $prevClientName) {
                    if ($journalEntry->name === $prevClientName) {
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


    public function upload(Request $request)
    {
        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $company = $user->company;
        } elseif ($user->role_id == Role::BRANCH) {
            $company = $user->branch->company;
        } elseif ($user->role_id == Role::AGENT) {
            $company = $user->agent->branch->company;
        } else {
            return redirect()->back()->with('error', 'User not authorized to upload tasks.');
        }

        if(!$company) {
            Log::error("Company not found for user ID: {$user->id}");
            return redirect()->back()->with('error', 'Something went wrong.');
        }

        $request->validate([
            'task_file' => 'required|array',
            'task_file.*' => 'mimes:pdf,txt',
            'agent_id' => 'nullable|exists:agents,id',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $files = $request->file('task_file');
        $supplier = Supplier::find($request->supplier_id);

        $companyName = strtolower(preg_replace('/\s+/', '_', $company->name));
        $supplierName = strtolower(preg_replace('/\s+/', '_', $supplier->name));

        $filePath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");

        if (!File::isDirectory($filePath)) {
            Log::error("Source directory {$filePath} not found.");
            File::makeDirectory($filePath, 0755, true, true);
            Log::info("Created source directory: {$filePath}, please ensure files are pushed here.");
        }

        $error = false;
        $errorFilesWithMessage = [];

        $success = false;
        $successFiles = [];

        foreach ($files as $file) {
            $errorFile = [];
            $fileName = $file->getClientOriginalName();

            $existingFileUpload = FileUpload::where([
                'file_name' => $fileName,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
            ]);

            if($existingFileUpload->exists()) {
                Log::info("File {$fileName} already exists for supplier {$supplier->name}, in company {$company->name}. Skipping upload.");

                $userUpload = $existingFileUpload->first()->user;

                if ($userUpload->id !== $user->id) {
                
                    if($userUpload->company !== null){
                        $message = "File has been uploaded by your admin. Please contact them to resolve this issue.";
                    } else {
                        $message = "File has been uploaded by another user : {$userUpload->name}. Please contact them to resolve this issue.";
                    }

                    Log::info("File {$fileName} already uploaded by another user: {$userUpload->name}.");

                    $errorFile['file_name'] = $fileName;
                    $errorFile['message'] = $message;

                    $errorFilesWithMessage[] = $errorFile;

                } else {
                    Log::info("File {$fileName} already uploaded by the same user: {$user->name}.");

                    $errorFile['file_name'] = $fileName;
                    $errorFile['message'] = "File has already been uploaded by you";

                    $errorFilesWithMessage[] = $errorFile;
                }
                $error = true;
                continue;
            }

            $file->move($filePath, $fileName);

            Log::info("Uploading file: " . $file->getClientOriginalName() . " to: " . $filePath);

            try{
                FileUpload::create([
                    'file_name' => $file->getClientOriginalName(),
                    'destination_path' => $filePath . '/' . $file->getClientOriginalName(),
                    'user_id' => $user->id,
                    'supplier_id' => $supplier->id,
                    'company_id' => $company->id,
                    'status' => 'pending',
                ]);
            } catch (Exception $e) {
                Log::error("Failed to create file upload record for {$fileName}: " . $e->getMessage());
                $errorFilesWithMessage['file_name'] = $fileName;
                $errorFilesWithMessage['message'] = "Something went wrong"; 
                $error = true;
                continue;
            }

            $successFiles[] = $file->getClientOriginalName();
            $success = true;
        }

        $response = [];
        if ($error) {
            Log::error("Some files failed to upload: ");

            $data = [];
            
            foreach ($errorFilesWithMessage as $fileError) {
                $data[] = [
                    'file_name' => $fileError['file_name'],
                    'message' => $fileError['message'],
                ];
            }

            $response[] = [
                'status' => 'error',
                'message' => 'Some files failed to upload.',
                'data' => $data,
            ];
        }

        if($success){
            Log::info("Files uploaded successfully: " . implode(', ', $successFiles));

            $response[] = [
                'status' => 'success',
                'message' => 'Files uploaded successfully: ' . implode(', ', $successFiles),
                'data' => $successFiles,
            ];
        }

        return $response;
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
    public function saveFlightDetails($data, int $taskId)
    {
        try {
            // Handle both single flight detail object and array of flight details
            if (isset($data[0]) && is_array($data[0])) {
                // Multiple flight segments - array of flight detail objects
                foreach ($data as $flightData) {
                    $this->createSingleFlightDetail($flightData, $taskId);
                }
            } else {
                // Single flight detail object
                $this->createSingleFlightDetail($data, $taskId);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Create a single flight detail record
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void
     */
    private function createSingleFlightDetail(array $data, int $taskId)
    {
        try {
            $airline = isset($data['airline_name']) ? Airline::where('name', 'like', '%' . $data['airline_name'] . '%')->first() : null;

            // Handle both 'departure_from'/'arrive_to' and 'country_id_from'/'country_id_to' fields
            $countryFrom = null;
            $countryTo = null;

            if (isset($data['departure_from'])) {
                $countryFrom = Country::where('name', 'like', '%' . $data['departure_from'] . '%')->first();
            } elseif (isset($data['country_id_from'])) {
                $countryFrom = is_numeric($data['country_id_from'])
                    ? Country::find($data['country_id_from'])
                    : Country::where('name', 'like', '%' . $data['country_id_from'] . '%')->first();
            }

            if (isset($data['arrive_to'])) {
                $countryTo = Country::where('name', 'like', '%' . $data['arrive_to'] . '%')->first();
            } elseif (isset($data['country_id_to'])) {
                $countryTo = is_numeric($data['country_id_to'])
                    ? Country::find($data['country_id_to'])
                    : Country::where('name', 'like', '%' . $data['country_id_to'] . '%')->first();
            }

            // Handle airline_id field - could be airline name or ID
            $airlineId = null;
            if (isset($data['airline_id'])) {
                if (is_numeric($data['airline_id'])) {
                    $airlineId = $data['airline_id'];
                } else {
                    $airlineFromId = Airline::where('name', 'like', '%' . $data['airline_id'] . '%')->first();
                    $airlineId = $airlineFromId ? $airlineFromId->id : null;
                }
            } elseif ($airline) {
                $airlineId = $airline->id;
            }

            $flightDetails = [
                'farebase' => isset($data['farebase']) ? (float) $data['farebase'] : null,
                'departure_time' => $data['departure_time'] ?? null,
                'country_id_from' => $countryFrom ? $countryFrom->id : null,
                'airport_from' => $data['airport_from'] ?? null,
                'terminal_from' => $data['terminal_from'] ?? null,
                'arrival_time' => $data['arrival_time'] ?? null,
                'duration_time' => $data['duration_time'] ?? null,
                'country_id_to' => $countryTo ? $countryTo->id : null,
                'airport_to' => $data['airport_to'] ?? null,
                'terminal_to' => $data['terminal_to'] ?? null,
                'airline_id' => $airlineId,
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
                Log::channel('magic_holidays')->error('Agent not found in reservation data for reservation ID: ' . ($reservation['id'] ?? 'Unknown'));
                return [
                    'status' => 'error',
                    'message' => 'Something Went Wrong',
                ];
            }

            $agentInDB = Agent::where('name', $agent['name'])
                ->orWhere('email', 'like', $agent['email'])
                ->orWhere('phone_number', 'like', $agent['telephone'])
                ->first();

            if ($agentInDB) {
                $agentId = $agentInDB->id;
            } else {
                Log::channel('magic_holidays')->error('Agent ' . $agent['name'] . ' not found in database');

                return [
                    'status' => 'error',
                    'message' => 'Agent ' . $agent['name'] . ' not found in database. Please create the agent first.',
                ];
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
                'cancellation_deadline' => $cancellationDate ?? null,
                'additional_info' => $reservation['service']['hotel']['name'] . ' - ' . $clientName,
                'supplier_id' => $supplierId,
                'venue' => $hotel['name'] ?? null,
                'invoice_price' => null,
                'voucher_status' => null,
                'refund_date' => null,
                'issued_date' => Carbon::parse($reservation['added']['time'])->toDateTimeString() ?? null,
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
            'supplier_ref' => 'nullable',
            'task_file' => 'nullable|array',
            'task_file.*' => 'mimes:pdf,txt',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $supplier = Supplier::findOrFail($request->supplier_id);
        $supplierController = new SupplierController();

        // if($supplier->name !== 'Magic Holiday'){
        //     $request->validate([
        //         'agent_id' => 'required|exists:agents,id',
        //     ], [
        //         'agent_id.required' => 'Please select an agent',
        //     ]);
        // }

        $user = Auth::user();
        $agentId = null;
        if ($request->agent_id) {
            $agent = Agent::findOrFail($request->agent_id);

            if (!$agent) {
                return redirect()->back()->with('error', 'Agent not found');
            }

            $agentId = $agent->id;
        }

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company->id;
        } else {
            return redirect()->back()->with('error', 'User not authorized to create task');
        }

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
                        $response = $this->processSingleReservation($reservation, $agentId, $companyId);

                        if ($response['status'] == 'error') {
                            return redirect()->back()->with('error', $response['message']);
                        }

                        $supplierController->magicReserveWebhook($reservation['id']);
                    }
                } else {

                    $response = $this->processSingleReservation($data, $agentId, $companyId);

                    if ($response['status'] == 'error') {
                        return redirect()->back()->with('error', $response['message']);
                    }

                    $supplierController->magicReserveWebhook($data['id']);
                }

                return redirect()->back()->with('success', 'Magic Holiday task received successfully');

            default:
                $responses = $this->upload($request);
                // Artisan::call('app:process-files', [], null, true);
                $redirectResponse = redirect()->back();

                foreach($responses as $response) {
                    if ($response['status'] == 'success') {
                        $redirectResponse = $redirectResponse->with('success', $response['message']); 
                    }

                    if($response['status'] == 'error') {
                        $redirectResponse = $redirectResponse->with('error', $response['message'])->with('data', $response['data']);
                    }
                }

                return $redirectResponse;
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

        // Use task's issued_date as transaction_date
        $transactionDate = $originalTask->issued_date ? Carbon::parse($originalTask->issued_date) : Carbon::now();

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
            'transaction_date' => $transactionDate,
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $originalTask->company_id,
            'branch_id' => $originalTask->agent->branch_id,
            'account_id' => $supplierCost->id,
            'task_id' => $originalTask->id,
            'transaction_date' => $transactionDate,
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
            'transaction_date' => $transactionDate,
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

    /**
     * Update branch_id in all journal entries for a task when agent is assigned/changed
     */
    private function updateJournalEntriesBranch(Task $task)
    {
        if (!$task->agent_id) {
            Log::warning('Cannot update journal entries branch - no agent assigned to task: ' . $task->reference);
            return;
        }

        $agent = Agent::find($task->agent_id);
        if (!$agent || !$agent->branch_id) {
            Log::warning('Cannot update journal entries branch - agent has no branch: ' . $task->reference);
            return;
        }

        $newBranchId = $agent->branch_id;
        
        // Find all transactions related to this task
        $transactions = Transaction::where('description', 'like', '%' . $task->reference . '%')->get();
        
        foreach ($transactions as $transaction) {
            $oldBranchId = $transaction->branch_id;
            
            // Update transaction branch_id
            $transaction->update(['branch_id' => $newBranchId]);
            
            // Update all journal entries for this transaction
            $updatedEntries = JournalEntry::where('transaction_id', $transaction->id)
                ->update(['branch_id' => $newBranchId]);
            
            Log::info('Updated journal entries for task agent assignment', [
                'task_reference' => $task->reference,
                'transaction_id' => $transaction->id,
                'old_branch_id' => $oldBranchId,
                'new_branch_id' => $newBranchId,
                'updated_entries_count' => $updatedEntries
            ]);
        }
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

    public function destroy($id)
    {
        $response = $this->destroyProcess($id);

        $response = json_decode($response->getContent(), true);

        if ($response['status'] === 'success') {
            return redirect()->back()->with('success', $response['message']);
        } else {
            return redirect()->back()->with('error', $response['message'])->with('data', $response['data'] ?? null);
        }
    }

    public function destroyProcess($id)
    {
        Gate::authorize('destroy', Task::class);

        // Check if user is super admin (admin role)
        $user = Auth::user();

        if ($user->role_id != Role::ADMIN) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only super admin can delete tasks.',
            ], 403);
        }

        $task = Task::findOrFail($id);

        DB::beginTransaction();

        try {
            Log::info("Starting soft delete process for task: {$task->reference} (ID: {$id})");

            // 1. Soft delete journal entries related to the task
            $journalEntries = JournalEntry::where('task_id', $id)->get();
            if ($journalEntries->isNotEmpty()) {
                foreach ($journalEntries as $journalEntry) {
                    // Get transaction ID before soft deleting journal entry
                    $transactionId = $journalEntry->transaction_id;
                    
                    // Soft delete journal entry
                    $journalEntry->delete();
                    
                    // Soft delete associated transaction if it exists
                    if ($transactionId) {
                        $transaction = Transaction::find($transactionId);
                        if ($transaction) {
                            $transaction->delete();
                        }
                    }
                }
                Log::info("Soft deleted " . $journalEntries->count() . " journal entries and their transactions for task: {$task->reference}");
            }

            // 2. Soft delete invoice details related to the task
            $invoiceDetails = InvoiceDetail::where('task_id', $id)->get();
            $invoiceIds = [];
            
            if ($invoiceDetails->isNotEmpty()) {
                $invoiceIds = $invoiceDetails->pluck('invoice_id')->unique()->toArray();
                
                foreach ($invoiceDetails as $invoiceDetail) {
                    $invoiceDetail->delete();
                }
                Log::info("Soft deleted " . $invoiceDetails->count() . " invoice details for task: {$task->reference}");
            }

            // 3. Soft delete payments related to task invoices
            if (!empty($invoiceIds)) {
                $payments = Payment::whereIn('invoice_id', $invoiceIds)->get();
                if ($payments->isNotEmpty()) {
                    foreach ($payments as $payment) {
                        $payment->delete();
                    }
                    Log::info("Soft deleted " . $payments->count() . " payments for task: {$task->reference}");
                }

                // 4. Soft delete transactions related to task invoices
                $invoiceTransactions = Transaction::whereIn('invoice_id', $invoiceIds)->get();
                if ($invoiceTransactions->isNotEmpty()) {
                    foreach ($invoiceTransactions as $transaction) {
                        $transaction->delete();
                    }
                    Log::info("Soft deleted " . $invoiceTransactions->count() . " invoice transactions for task: {$task->reference}");
                }

                // 5. Soft delete invoices themselves
                $invoices = Invoice::whereIn('id', $invoiceIds)->get();
                if ($invoices->isNotEmpty()) {
                    foreach ($invoices as $invoice) {
                        $invoice->delete();
                    }
                    Log::info("Soft deleted " . $invoices->count() . " invoices for task: {$task->reference}");
                }
            }

            // 6. Soft delete task flight details
            $flightDetails = TaskFlightDetail::where('task_id', $id)->get();
            if ($flightDetails->isNotEmpty()) {
                foreach ($flightDetails as $flightDetail) {
                    $flightDetail->delete();
                }
                Log::info("Soft deleted " . $flightDetails->count() . " flight details for task: {$task->reference}");
            }

            // 7. Soft delete task hotel details
            $hotelDetails = TaskHotelDetail::where('task_id', $id)->get();
            if ($hotelDetails->isNotEmpty()) {
                foreach ($hotelDetails as $hotelDetail) {
                    $hotelDetail->delete();
                }
                Log::info("Soft deleted " . $hotelDetails->count() . " hotel details for task: {$task->reference}");
            }

            // 8. Finally, soft delete the task itself
            $task->delete();
            Log::info("Soft deleted task: {$task->reference} (ID: {$id})");

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Task '{$task->reference}' and all related data have been soft deleted successfully.",
                'data' => [
                    'task_id' => $id,
                    'task_reference' => $task->reference,
                    'deleted_at' => now()->toISOString()
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollback();
            Log::error("Error during task soft delete: " . $e->getMessage(), [
                'task_id' => $id,
                'task_reference' => $task->reference ?? 'Unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete task: ' . $e->getMessage(),
            ], 500);
        }
    }
    
   public function reverseJournalforChangedPaymentMethod(Task $task)
{
    $task = Task::findOrFail($task->id);
    Log::info('Task ID: ' . $task->id . '. For Journal Reversal of Changed Payment Method');

    $supplier = Supplier::find($task->supplier_id);
    $branchId = $this->getTaskBranchId($task);

    $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
        ->where('company_id', $task->company_id)
        ->first();

    if (!$supplierCompany) {
        Log::error('Supplier company not activated or not found.');
        return;
    }

    $liabilities = Account::where('name', 'like', '%Liabilities%')
        ->where('company_id', $task->company_id)
        ->first();

    $expenses = Account::where('name', 'like', '%Expenses%')
        ->where('company_id', $task->company_id)
        ->first();

    if (!$liabilities || !$expenses) {
        Log::error('Liabilities or Expenses account not found.');
        return;
    }

    $supplierPayable = Account::where('name', $supplier->name)
        ->where('company_id', $task->company_id)
        ->whereIn('root_id', function ($query) use ($task) {
            $query->select('id')
                ->from('accounts')
                ->where('company_id', $task->company_id)
                ->where('name', 'like', '%Liabilities%');
        })
        ->first();

    $supplierCost = Account::where('name', $supplier->name)
        ->where('company_id', $task->company_id)
        ->whereIn('root_id', function ($query) use ($task) {
            $query->select('id')
                ->from('accounts')
                ->where('company_id', $task->company_id)
                ->where('name', 'like', '%Expenses%');
        })
        ->first();

    // ✅ New logic: get issuedByAccount under supplierPayable
    $companyIssuedBy = $task->issued_by ?? 'Not Issued';

    $issuedByAccount = Account::where('name', $companyIssuedBy)
        ->where('company_id', $task->company_id)
        ->where('root_id', $liabilities->id)
        ->where('parent_id', $supplierPayable->id ?? 0)
        ->first();

    $creditorAccount = Account::find($task->payment_method_account_id);
    Log::info('Creditor Account Details', [
        'id' => $creditorAccount->id,
        'name' => $creditorAccount->name,
        'root_id' => $creditorAccount->root_id,
        'is_group' => $creditorAccount->is_group,
        'disabled' => $creditorAccount->disabled,
    ]);

    if ((!$issuedByAccount && !$supplierPayable) || !$supplierCost || !$creditorAccount) {
        Log::error('Required accounts not found for reversal or new payment method.');
        return;
    }

    Log::info('Data of Reversal Journal for Changed Payment Method', [
        'task_id' => $task->id,
        'branch_id' => $branchId,
        'supplier_company' => $supplierCompany,
        'liabilities' => $liabilities,
        'expenses' => $expenses,
        'supplier_payable' => $supplierPayable?->name,
        'supplier_cost' => $supplierCost->name,
        'creditor_account' => $creditorAccount->name,
        'total_amount' => $task->total
    ]);
    Log::info('Starting Journal Reversal for Changed Payment Method');

    // Use task's issued_date as transaction_date
    $transactionDate = $task->issued_date ? Carbon::parse($task->issued_date) : Carbon::now();

    try {
        $transaction1 = Transaction::create([
            'branch_id' => $branchId,
            'company_id' => $task->company_id,
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $task->total,
            'description' => 'Reversed journal of supplier payment',
            'reference_type' => 'Payment',
            'transaction_date' => $transactionDate,
        ]);

        Log::info('Reversed Journal Entries for Task ID: ' . $task->id . ' with Transaction: ' . $transaction1);

        // ✅ Debit to correct payable account
        JournalEntry::create([
            'transaction_id' => $transaction1->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $issuedByAccount ? $issuedByAccount->id : $supplierPayable->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Reversal of supplier payable: ' . $supplier->name,
            'name' => $supplier->name,
            'debit' => $task->total,
            'credit' => 0,
            'balance' => $task->total,
            'type' => 'payable',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction1->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $supplierCost->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Reversal of supplier cost',
            'name' => $supplier->name,
            'debit' => 0,
            'credit' => $task->total,
            'balance' => 0,
            'type' => 'payable',
        ]);

        $transaction2 = Transaction::create([
            'branch_id' => $branchId,
            'company_id' => $task->company_id,
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $task->total,
            'description' => 'Task paid via creditor: ' . $creditorAccount->name,
            'reference_type' => 'Payment',
            'transaction_date' => $transactionDate,
        ]);

        Log::info('New Journal Entries for Task ID: ' . $task->id . ' with Transaction: ' . $transaction2);

        JournalEntry::create([
            'transaction_id' => $transaction2->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $supplierCost->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Expense paid via creditor: ' . $creditorAccount->name,
            'name' => $supplier->name,
            'debit' => $task->total,
            'credit' => 0,
            'balance' => $task->total,
            'type' => 'expense',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction2->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $creditorAccount->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Creditor payable: ' . $creditorAccount->name,
            'name' => $creditorAccount->name,
            'debit' => 0,
            'credit' => $task->total,
            'balance' => $task->total,
            'type' => 'payable',
        ]);

        Log::info('Kenapa dia tanak masuk creditors gaes: ' .
            'Transaction: ' . $transaction2->id . ' Company ID: ' . $task->company_id . ' Branch ID: ' . $branchId . ' Account ID: ' . $creditorAccount->id . ' Account Name: ' . $creditorAccount->name . ' Amount: ' . $task->total);
    } catch (\Exception $e) {
        Log::error('Failed to create journal entry: ' . $e->getMessage());
    }
}


}
