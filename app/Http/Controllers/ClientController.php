<?php

namespace App\Http\Controllers;

use App\Http\Traits\Converter;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Invoice;
use App\Models\Agent;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ClientsImport;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\RefundClient;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\Credit;
use App\Enums\ChargeType;
use App\Models\InvoicePartial;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ClientController extends Controller
{
    use Converter;

    public function index(Request $request)
    {
        $user = Auth::user();

        $clients = Client::with('agent.branch');
        $fullClients = clone $clients;

        if ($user->role_id == Role::ADMIN) {
            $agentIds = Agent::all()->pluck('id')->toArray();
            $branch = Branch::pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();

            $clients = $clients->whereIn('agent_id', $agentIds);
            $fullClients = $fullClients->whereIn('agent_id', $agentIds);

        } elseif ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();

            $clients = $clients->whereIn('agent_id', $agentIds);
            $fullClients = $fullClients->whereIn('agent_id', $agentIds);

        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();

            $clients = $clients->where('agent_id', $agent->id);
            $fullClients = $fullClients->where('agent_id', $agent->id);
        
        } 

        if($request->has('search') && $request->search != '') {
            $search = $request->search;
            $clients = $clients->where(function($query) use ($search) {
                $searchTerm = '%' . strtolower($search) . '%';
                $query->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('middle_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhere('email', 'LIKE', $searchTerm)
                    ->orWhere('phone', 'LIKE', $searchTerm)
                    ->orWhereHas('agent', function ($q) use ($searchTerm) {
                        $q->where('name', 'LIKE', $searchTerm);
                    });
                
                // Handle multi-word search for name combinations
                if (str_word_count($search) > 1) {
                    $searchWords = explode(' ', trim($search));
                    $query->orWhere(function($nameQuery) use ($searchWords) {
                        $firstWord = $searchWords[0];
                        $lastWord = end($searchWords);
                        $middleWords = array_slice($searchWords, 1, -1);
                        
                        // For 2 words: first_name + last_name
                        if (count($searchWords) == 2) {
                            $nameQuery->where(function($q) use ($firstWord, $lastWord) {
                                $q->where('first_name', 'LIKE', '%' . $firstWord . '%')
                                  ->where('last_name', 'LIKE', '%' . $lastWord . '%');
                            });
                        }
                        // For 3+ words: first_name + middle_name(s) + last_name
                        else if (count($searchWords) >= 3) {
                            $nameQuery->where(function($q) use ($firstWord, $middleWords, $lastWord) {
                                $q->where('first_name', 'LIKE', '%' . $firstWord . '%')
                                  ->where('last_name', 'LIKE', '%' . $lastWord . '%');
                                
                                // Add middle name conditions
                                foreach ($middleWords as $middleWord) {
                                    $q->where('middle_name', 'LIKE', '%' . $middleWord . '%');
                                }
                            });
                        }
                    });
                }
            });

        }


        $clientsCount = $clients->count();

        $clients = $clients->orderByDesc('created_at')->paginate(20);
        $fullClients = $fullClients->orderByDesc('created_at')->get();

        return view('clients.index', compact(
            'agent',
            'fullClients',
            'clients',
            'clientsCount'
        ));
    }

    public function storeProcess(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255', 
            'dial_code' => 'required|string|max:30',
            'phone' => 'required|string|max:15',
            'agent_id' => 'required|exists:agents,id',
            'passport_no' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();
            $message = 'Client created successfully.';

            $client = Client::create([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'status' => 'active',
                'phone' => preg_replace('/\s+/', '', $request->phone),
                'country_code' => $request->dial_code,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
                'civil_no' => $request->civil_no,
                'passport_no' => $request->passport_no,
                'old_passport_no' => $request->passport_no,
                'agent_id' => $request->agent_id,
            ]);

            if ($request->filled('task_id')) {
                $task = Task::findOrFail($request->task_id);

                // Link client
                $task->client_id = $client->id;
                $task->client_name = $client->first_name;

                // Attempt to auto-enable
                if (!$task->enabled && $task->is_complete) {
                    $task->enabled = true;
                }

                $task->save();
                $message = 'Client created and task updated successfully.';
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => $message,
                'data' => $client,
                'task_id' => $request->task_id,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            logger('Error in storeProcess(): ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Failed to create client',
            ];
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:clients,email',
            'dial_code' => 'required|string|max:30',
            'phone' => 'required|string|max:15',
            'agent_id' => 'required|exists:agents,id',
            'company_id' => 'nullable|exists:companies,id', //this will be compulsory later
            'civil_no' => 'required|unique:clients,civil_no',
            'passport_no' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
        ]);

        if(!$request->company_id){ //this fallback is temporary until company_id is added in the form
            $companyId = Agent::find($request->agent_id)->branch->company_id;

            $request->merge(['company_id' => $companyId]);
        }

        $response = $this->storeProcess($request);

        if ($response['status'] === 'error') {
            return redirect()->back()->withInput()->with('error', $response['message']);
        }

        return redirect()->back()->with('success', $response['message']);
    }

    /**
     * Check if a string is valid JSON.
     *
     * @param string $string
     * @return bool
     */
    private function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    public function show($id)
    {
        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();
            $agentIds = [$agent->id];
        } else {
            $agentIds = [];
        }

        $client = Client::findOrFail($id);
        $agents = Agent::with('branch')->get();

        $invoices = Invoice::with('invoiceDetails', 'agent')->where('client_id', $id)->get();
        $tasks = Task::where('client_id', $id)->get();

        foreach ($tasks as $task) {
            if (is_string($task->cancellation_policy) && $this->isValidJson($task->cancellation_policy)) {
                $stringCancelPolicy = json_decode($task->cancellation_policy, true);
                $arrayCancelPolicy = json_decode($stringCancelPolicy, true);

                if (is_array($arrayCancelPolicy)) {
                    $task->cancellation_policy = $arrayCancelPolicy;
                } else {
                    $task->cancellation_policy = [];
                }
            }
        }

        $paid   = 0.0;
        $unpaid = 0.0;

        $invoicesPart = Invoice::where('client_id', $id)->get(['id','amount','status']);

        foreach ($invoicesPart as $invoice) {
            $total = $invoice->amount;
            $paidPartials = $invoice->invoicePartials->where('status', 'paid')->sum('amount');

            if ($invoice->status === 'paid') {
                $paid   += $total;
                continue;
            }

            $paidOnInvoice   = min($paidPartials, $total);
            $outstanding     = max(0.0, $total - $paidOnInvoice);

            $paid   += $paidOnInvoice;
            $unpaid += $outstanding;
        }

        $clients = Client::with('agent.branch')->get();
        $balanceCredit = Credit::getTotalCreditsByClient($id);

        $countries = Country::all(); // Fetch all countries for the view

        $selectedDialingCode = $countries->where('dialing_code', $client->country_code)->pluck('id')->first();

        $payments = Payment::where('client_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $paymentGateways = Charge::where('type', ChargeType::PAYMENT_GATEWAY)
            ->where('is_active', true)
            ->get();
        $paymentMethods  = PaymentMethod::where('is_active', true)->get();

        // Fetch the client groups where this client is the parent (i.e., group of sub-clients)
        // $childClients = ClientGroup::where('parent_client_id', $id)
        //     ->with('childClient') // Load related child clients
        //     ->get()
        //     ->map(function ($group) {
        //         return [
        //             'client' => $group->childClient, // Extract child client details
        //             'relation' => $group->relation, // Include relation column
        //         ];
        //     });

        // $parentClients = ClientGroup::where('child_client_id', $id)
        //     ->with('parentClient') // Load related child clients
        //     ->get()
        //     ->map(function ($group) {
        //         return [
        //             'client' => $group->parentClient, // Extract child client details
        //             'relation' => $group->relation, // Include relation column
        //         ];
        //     });

        return view('clients.new-profile', compact(
            'client',
            'agents',
            'invoices',
            'payments',
            'paymentGateways',
            'paymentMethods',
            'tasks',
            'paid',
            'unpaid',
            'clients',
            'balanceCredit',
            'countries',
            'selectedDialingCode',
        )); // Ensure the view exists
    }

    // Show the form for editing a client
    public function edit($id)
    {
        Gate::authorize('edit', [Client::class, Client::findOrFail($id)]);

        $agents = [];
        if (Gate::allows('clientAgent', Client::class)) {
            $agents = Agent::with('branch')->get();
        }

        $client = Client::findOrFail($id);
        return view('clients.edit', compact('client', 'agents')); // Ensure the view exists
    }

    // Update the client in the database
    public function update(Request $request, $id)
    {
        Gate::authorize('update', [Client::class, $client = Client::findOrFail($id)]);

        // Validate the incoming request data
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'email',
            'status' => 'nullable',
            'phone' => 'string|max:15',
            'country_code' => 'string|max:30',
            'file' => 'nullable|mimes:jpeg,jpg,png', // Optional passport file field
            'agent_ids' => 'nullable|array|exists:agents,id',
        ]);

        try {
            // Update the client data
            $client->update($request->only([
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'status',
                'country_code',
                'phone',
                'address',
            ]));

            if($request->has('agent_ids')) {
                $response = Gate::inspect('assignAgents', Client::class);

                if ($response->denied()) {
                    return redirect()->back()->withInput()->with('error', $response->message() ?: 'You do not have permission to assign agents.');
                }

                $client->agents()->sync($request->agent_ids);
            }

            // If a file (image) is uploaded, process it
            if ($request->hasFile('file')) {
                try {
                    $imagePath = $request->file('file')->getRealPath();
                    // Process the image using OCR
                    $ocrResponse = $this->processImage($imagePath);  // Get the response from processImage

                    // Now $ocrResponse is already an array, so no need to decode it
                    if (isset($ocrResponse['ParsedResults'][0]['ParsedText'])) {
                        $parsedText = $ocrResponse['ParsedResults'][0]['ParsedText'];

                        // You can now use the parsed text (e.g., for passport extraction)
                        $openai = new OpenAiController();
                        $response = $openai->extractPassport($parsedText); // Pass the parsed text to OpenAI

                        // Since extractPassport already returns the parsed data (not a JSON string), 
                        // we can use it directly as an array
                        if (isset($response['data'])) {
                            $this->updateClientPassport($client, $response['data']);
                        } else {
                            // Handle case where 'data' is not available
                            return redirect()->back()->withInput()->with('error', 'OCR processing failed or no data returned.');
                        }
                    } else {
                        return redirect()->back()->withInput()->with('error', 'No text found in OCR response.');
                    }
                } catch (Exception $e) {
                    return redirect()->back()->withInput()->with('error', $e->getMessage());
                }
            }

            // Redirect back with success message
            return Redirect::back()->with('success', 'Client updated successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function updateClientPassport($client, $data)
    {
        $client->passport_no = $data['passport_no'];
        $client->civil_no = $data['civil_no'];
        // $client->passport_expiry = $data['passport_expiry'];
        // $client->passport_country = $data['passport_country'];
        $client->save();
    }

    public function upload()
    {
        $clients = Client::with('agent')->get();

        return view('clients.clientsUpload', compact('clients'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new ClientsImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Clients imported successfully.');
    }


    public function changeAgent(Request $request, $id)
    {
        // Validate the new agent ID
        $validatedData = $request->validate([
            'agent_id' => 'required|exists:agents,id',
        ]);

        // Update the client's agent
        $client = Client::findOrFail($id);
        $client->agent_id = $request->agent_id;
        $client->save();

        return redirect()->back()->with('success', 'Agent updated successfully for pending tasks.');
    }

    public function exportCsv()
    {
        // Fetch all agents data
        $clients = Client::with('agent')->get();

        // Create a CSV file in memory
        $csvFileName = 'clients.csv';
        $handle = fopen('php://output', 'w');

        // Set headers for the response
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        // Add CSV header
        fputcsv($handle, ['Client Name', 'Client Email', 'Phone', 'Agent']);

        // Add company data to CSV
        foreach ($clients as $client) {
            fputcsv($handle, [
                $client->first_name,
                $client->email,
                $client->phone,
                $client->agent->name,
            ]);
        }

        fclose($handle);
        exit();
    }


    public function addToGroup(Request $request)
    {
        $request->validate([
            'parent_client_id' => 'required|exists:clients,id',
            'child_client_id' => 'required|exists:clients,id|different:parent_client_id',
        ]);

        // Check if relationship already exists
        $exists = ClientGroup::where('parent_client_id', $request->parent_client_id)
            ->where('child_client_id', $request->child_client_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Client is already in this group'], 409);
        }

        // Create the client group relationship
        ClientGroup::create([
            'parent_client_id' => $request->parent_client_id,
            'child_client_id' => $request->child_client_id,
        ]);

        return response()->json(['message' => 'Client added to the group successfully'], 201);
    }

    /**
     * Remove a client from a group.
     */
    public function removeFromGroup(Request $request)
    {
        $request->validate([
            'parent_client_id' => 'required|exists:clients,id',
            'child_client_id' => 'required|exists:clients,id',
        ]);

        $deleted = ClientGroup::where('parent_client_id', $request->parent_client_id)
            ->where('child_client_id', $request->child_client_id)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Client removed from the group'], 200);
        }

        return response()->json(['message' => 'Client not found in this group'], 404);
    }

    public function getSubClients(int $parentClientId)
    {

        $childClients = ClientGroup::where('parent_client_id', $parentClientId)
            ->with('childClient') // Load related child clients
            ->get()
            ->map(function ($group) {
                return [
                    'client' => $group->childClient, // Extract child client details
                    'relation' => $group->relation, // Include relation column
                ];
            });
        return response()->json($childClients);
    }


    public function getParClients(int $childClientId)
    {
        $parentClients = ClientGroup::where('child_client_id', $childClientId) // Fetch parents using child ID
            ->with('parentClient') // Load related parent clients
            ->get()
            ->map(function ($group) {
                return [
                    'client' => $group->parentClient, // Extract parent client details
                    'relation' => $group->relation, // Include relation column
                ];
            });

        return response()->json($parentClients);
    }

    public function getDetails($id)
    {
        // Retrieve client details along with related client groups
        $client = Client::find($id);

        // Check if client exists
        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        return response()->json($client);
    }


    public function updateGroup(Request $request, int $id)
    {
        // Validate request
        $request->validate([
            'relation' => 'required|string|max:255',
            'selectedId' => 'required|exists:clients,id',
        ]);

        // Ensure that relation is a valid string
        $relation = (string)$request->relation;

        // Log query parameters for debugging
        Log::info('Query parameters:', [
            'parent_client_id' => $id,
            'child_client_id' => $request->selectedId,
        ]);

        // Find the client group based on parent_client_id and child_client_id
        $clientGroup = ClientGroup::where('parent_client_id', $id)
            ->where('child_client_id', $request->selectedId)
            ->first();

        // Log if the client group is found or not
        Log::info('clientGroup found:', ['clientGroup' => $clientGroup]);

        // If no client group is found, return an error response
        if (!$clientGroup) {
            return response()->json([
                'success' => false,
                'message' => 'Client relationship not found!',
            ], 404);
        }

        // Check current value of relation before updating
        Log::info('Current relation value:', ['relation' => $clientGroup->relation]);

        // If relation is null, set a default value
        if ($clientGroup->relation === null) {
            Log::warning('Relation is null, setting a default value.');
            $clientGroup->relation = 'parents'; // Or whatever default you prefer
        }

        // Update the specific client group's relation field
        $clientGroup->relation = $relation;

        // Log the updated relation value
        Log::info('Updated relation value:', ['relation' => $clientGroup->relation]);

        // Save the updated record explicitly
        try {
            $clientGroup->save();
        } catch (\Exception $e) {
            Log::error('Error saving client group:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client relationship!',
            ], 500);
        }

        // Return response with updated client group data
        return response()->json([
            'success' => true,
            'message' => 'Client relationship updated successfully!',
            'clientGroup' => $clientGroup,
        ]);
    }

    public function addCredit(Payment $payment)
    {

        $client = Client::findOrFail($payment->client_id);
        $agent = Agent::find($payment->agent_id);

        if (!$client) {
            return [
                'status' => 'error',
                'message' => 'Client not found',
            ];
        }

        DB::beginTransaction();
        try {
            // Insert credit table
            $topupCreditClientData = [
                'company_id'  => $agent->branch->company->id,
                'client_id'   => $client->id,
                'type'        => 'Topup',
                'description' => 'Topup Credit via ' . $payment->voucher_number,
                'amount'      => $payment->amount,
            ];

            Log::info('Creating Credit record:', $topupCreditClientData);

            Credit::create($topupCreditClientData);

            Log::info('Credit record created successfully for client ID: ' . $client->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Credit record', [
                'data'  => $topupCreditClientData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        DB::commit();

        $paymentMethod = $payment->paymentMethod; // Eloquent auto-loads relation
        $paidBy = $paymentMethod->paid_by ?? null;

        DB::beginTransaction();
        try {

            $chargeRecord = Charge::where('name', 'LIKE', '%' . $payment->payment_gateway . '%')
                ->where('company_id', $agent->branch->company->id)
                ->select('amount', 'acc_bank_id', 'acc_fee_bank_id', 'acc_fee_id')
                ->first();

            if ($chargeRecord) {
                $defaultPaymentGatewayFee = $chargeRecord->amount;
                $coaBankIdRec = $chargeRecord->acc_bank_id; //COA (Assets) for Debited Bank Account
                $coaFeeIdRec = $chargeRecord->acc_fee_id; //COA (Expenses) for Payment Gateway Fee
                $coaBankFeeIdRec = $chargeRecord->acc_fee_bank_id; //COA (Assets) for Bank Account for the selected Payment Gateway

                $bankCOAFee = Account::where('id', $coaFeeIdRec)
                    ->where('company_id', $agent->branch->company->id)
                    ->first();

                $bankPaymentFee = Account::where('id', $coaBankFeeIdRec)
                    ->where('company_id', $agent->branch->company->id)
                    ->first();
            }

            $transaction = Transaction::create([
                'branch_id' =>  $agent->branch->id,
                'company_id' =>  $agent->branch->company->id,
                'entity_id' =>  $agent->branch->company->id,
                'entity_type' => 'client',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'description' => 'Client Credit of ' . $client->first_name,
                'invoice_id' => null,
                'reference_type' => 'Payment',
                'reference_number' => $payment->voucher_number,
            ]);

            $receivableAccount = Account::where('name', 'Clients')->first();
            $receivableAccountId = $receivableAccount->id;

            if ($bankPaymentFee) {
                // JournalEntry::create([
                //     'transaction_id' => $transaction->id,
                //     'branch_id' => $client->agent->branch->id,
                //     'company_id' => $client->agent->branch->company->id,
                //     'account_id' =>  $receivableAccountId,
                //     'transaction_date' => Carbon::now(),
                //     'description' => 'Client Pays via ' . $bankPaymentFee->name . ' by (Assets): ' . $client->first_name,
                //     'debit' => 0,
                //     'credit' => $payment->amount,
                //     'balance' => null,
                //     'name' =>  $client->first_name,
                //     'type' => 'receivable',
                //     'voucher_number' => $payment->voucher_number,
                //     'type_reference_id' => $receivableAccountId
                // ]);


                // Create record to payment_gateway assets coa account (OK)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $agent->branch->company->id,
                    'branch_id' => $agent->branch->id,
                    'account_id' =>  $bankPaymentFee->id,
                    'transaction_date' => Carbon::now(),
                    'description' => 'Client Pays by ' . $client->first_name . ' via (Assets): ' . $bankPaymentFee->name,
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'name' =>  $bankPaymentFee->name,
                    'type' => 'bank',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $bankPaymentFee->id
                ]);

                $bankPaymentFee->actual_balance += ($payment->amount - $defaultPaymentGatewayFee);
                $bankPaymentFee->save();
            }

            $bankCOAFee->actual_balance += $defaultPaymentGatewayFee;

            if ($bankCOAFee) {
                JournalEntry::create([
                    'transaction_id'    => $transaction->id,
                    'company_id'        => $agent->branch->company->id,
                    'branch_id'         => $agent->branch->id,
                    'account_id'        => $bankCOAFee->id,
                    'voucher_number'    => $payment->voucher_number,
                    'transaction_date'  => Carbon::now(),
                    'description'       => ($paidBy === 'Company'
                        ? 'Company Pays Gateway Fee: '
                        : 'Client Pays Gateway Fee: ') . $bankCOAFee->name,
                    'debit'             => $defaultPaymentGatewayFee,
                    'credit'            => 0,
                    'balance'           => $bankCOAFee->actual_balance + $defaultPaymentGatewayFee,
                    'name'              => $bankCOAFee->name,
                    'type'              => 'charges',
                    'type_reference_id' => $bankCOAFee->id
                ]);

                $bankCOAFee->actual_balance += $defaultPaymentGatewayFee;
                $bankCOAFee->save();
            }


            // $client->credit += $payment->amount;
            // $client->save();

            // Insert credit table
            // $topupCreditClientData = [
            //     'company_id'  => $client->agent->branch->company->id,
            //     'client_id'   => $client->id,
            //     'type'        => 'Topup',
            //     'description' => 'Topup Credit via ' . $payment->voucher_number,
            //     'amount'      => $payment->amount,
            // ];

            // Log::info('Creating Credit record:', $topupCreditClientData);

            // Credit::create($topupCreditClientData);
        } catch (Exception $e) {
            DB::rollBack();
            logger('Error adding JournalEntry: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to add JournalEntry',
            ];
        }

        DB::commit();
        return [
            'status' => 'success',
            'message' => 'Credit added successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $payment->amount,
            ],
        ];
    }

    public function updateCredit($id, $amount)
    {
        try {
            $client = Client::findOrFail($id);
            // $client->credit = $amount;
            // $client->save();

            $balanceCredit = Credit::getTotalCreditsByClient($client->id);
            $difference = $amount - $balanceCredit;

            Log::info('Credit Update', [
                'client_id'       => $client->id,
                'client_name'     => $client->first_name,
                'current_balance' => $balanceCredit,
                'new_amount'      => $amount,
                'difference'      => $difference,
                'change_type'     => $difference > 0 ? 'Increase' : ($difference < 0 ? 'Decrease' : 'No change'),
            ]);

            if ($difference != 0) {
                Credit::create([
                    'company_id'  => $client->agent->branch->company->id,
                    'client_id'   => $client->id,
                    'type'        => 'Update Credit',
                    'description' => 'Update Credit for ' . $client->first_name,
                    'amount'      => $difference,
                ]);
            }
        } catch (Exception $e) {
            logger('Error updating credit: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to update credit',
            ];
        }

        logger('Credit updated successfully for client ID: ' . $id);
        return [
            'status' => 'success',
            'message' => 'Credit updated successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $difference,
            ],
        ];
    }

    public function refund($id, Request $request)
    {
        $response = $this->refundProcess($id, $request);

        return redirect()->back()->with($response['status'], $response['message']);
    }

    public function refundProcess($id, Request $request)
    {
        //dd($id, $request->all());
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'agent_id' => 'required|exists:agents,id',
        ]);

        if ($request->amount <= 0) {
            return [
                'status' => 'error',
                'message' => 'Refund amount must be greater than zero',
            ];
        }

        $client = Client::findOrFail($id);
        $balanceCredit = Credit::getTotalCreditsByClient($client->id);
        if ($balanceCredit < $request->amount) {
            return [
                'status' => 'error',
                'message' => 'Insufficient credit',
            ];
        }

        $agent = Agent::find($request->agent_id);

        //dd($balanceCredit);

        DB::beginTransaction();

        try {

            $liabilities = Account::where('name', 'Liabilities')
                ->where('company_id', $agent->branch->company->id)
                ->first();

            if (!$liabilities) {
                throw new Exception('Liabilities account not found');
            }

            $advances = Account::where('name', 'Advances')
                ->where('company_id', $agent->branch->company->id)
                ->where('parent_id', $liabilities->id)
                ->first();

            if (!$advances) {
                throw new Exception('Advances account not found');
            }

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $agent->branch->company->id)
                ->where('parent_id', $advances->id)
                ->where('root_id', $liabilities->id)
                ->first();

            if (!$clientAdvance) {
                throw new Exception('Client Advance account not found');
            }

            $refundPayable = Account::where('name', 'Refund Payable')
                ->where('company_id', $agent->branch->company->id)
                ->where('root_id', $liabilities->id)
                ->first();

            if (!$refundPayable) {
                throw new Exception('Refund Payable account not found');
            }

            $clientRefund = Account::where('name', 'Clients')
                ->where('company_id', $agent->branch->company->id)
                ->where('parent_id', $refundPayable->id)
                ->where('root_id', $liabilities->id)
                ->first();

            if (!$clientRefund) {
                throw new Exception('Client Refund account not found');
            }

            $transaction = Transaction::create([
                'branch_id' =>  $agent->branch->id,
                'company_id' =>  $agent->branch->company->id,
                'entity_id' =>  $agent->branch->company->id,
                'entity_type' => 'client',
                'transaction_type' => 'credit',
                'amount' => $request->amount,
                'description' => 'Client Refund of ' . $client->first_name . ' of ' . $request->amount,
                'invoice_id' => null,
                'reference_type' => 'Refund',
                'reference_number' => null,
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $agent->branch->id,
                'company_id' => $agent->branch->company->id,
                'account_id' =>  $clientAdvance->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Deduct Client Advance: ' . $client->first_name . ' of ' . $request->amount,
                'debit' => $request->amount,
                'credit' => 0,
                'balance' => null,
                'name' =>  $client->first_name,
                'type' => 'receivable',
                'voucher_number' => null,
                'type_reference_id' => $advances->id
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $agent->branch->id,
                'company_id' => $agent->branch->company->id,
                'account_id' =>  $clientRefund->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Debit Client Refund Payable: ' . $client->first_name . ' of ' . $request->amount,
                'debit' => 0,
                'credit' => $request->amount,
                'balance' => null,
                'name' =>  $client->first_name,
                'type' => 'payable',
                'voucher_number' => null,
                'type_reference_id' => $refundPayable->id
            ]);

            // $client->credit -= $request->amount;
            // $client->save();



            RefundClient::create([
                'client_id' => $client->id,
                'agent_id' => $request->agent_id,
                'status' => 'pending',
                'amount' => $request->amount,
                'currency' => 'KWD',
                'remark' => $request->remark,
            ]);

            try {
                Credit::create([
                    'company_id'  => $agent->branch->company->id,
                    'client_id'   => $client->id,
                    'type'        => 'Refund Credit',
                    'description' => 'Refund Credit for ' . $client->first_name,
                    'amount'      => - ($request->amount),
                ]);
            } catch (Exception $e) {
                Log::error('Failed to create Credit: ' . $e->getMessage());
                return response()->json('Something Went Wrong', 500);
            }
        } catch (Exception $e) {
            DB::rollBack();
            logger('Error processing refund: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to process refund',
            ];
        }

        DB::commit();

        return [
            'status' => 'success',
            'message' => 'Credit refunded successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $request->amount,
            ],
        ];
    }

    public function getAgent($id)
    {
        $client = Client::with('agent')->find($id);

        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        if (!$client->agent) {
            return response()->json(['error' => 'No agent assigned to this client'], 404);
        }

        return response()->json([
            'agent' => [
                'id' => $client->agent->id,
                'name' => $client->agent->name,
            ],
        ]);
    }

    // get Credit balance of a client
    public function getCreditBalance($id)
    {
        $credit = Credit::getTotalCreditsByClient($id);
        return response()->json(['credit' => $credit]);
    }

    public function showCredit($id)
    {
        $client = Client::with('agent')->findOrFail($id);
        $base = Credit::with(['client','invoice'])->where('client_id', $client->id);

        $totals = (clone $base)
            ->selectRaw('SUM(GREATEST(amount,0)) total_in, SUM(LEAST(amount,0)) total_out')
            ->first();

        $totalIn  = $totals->total_in  ?? 0;
        $totalOut = $totals->total_out ?? 0;
        $netBalance = $totalIn + $totalOut;

        $credits = (clone $base)
            ->orderBy('id','asc')
            ->paginate(25)
            ->withQueryString();
        
        return view('clients.credit', compact('client','credits','totalIn','totalOut','netBalance'));
    }
}
