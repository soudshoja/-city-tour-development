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
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\RefundClient;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\Credit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ClientController extends Controller
{
    use Converter;

    public function index()
    {
        $user = Auth::user();
        if ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();
            $clientsCount = Client::whereIn('agent_id', $agentIds)->count();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();
            $clientsCount = Client::where('agent_id', $agent->id)->count();
        } else {
            $clientsCount = Client::count();
        }

        if ($user->role_id == Role::ADMIN) {
            $agentIds = Agent::all()->pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
            // retrieve client that has the latest task
            $clients = Client::with('agent.branch')->whereIn('agent_id', $agentIds)->orderByDesc(
                Task::select('client_id')->whereColumn('client_id', 'clients.id')->limit(1)
            )->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();

            // retrieve client that has the latest task
            $clients = Client::with('agent.branch')->whereIn('agent_id', $agentIds)->orderByDesc(
                Task::select('client_id')->whereColumn('client_id', 'clients.id')->limit(1)
            )->get();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();

            // retrieve client that has the latest task
            $clients = Client::with('agent.branch')->where('agent_id', $agent->id)->orderByDesc(
                Task::select('client_id')->whereColumn('client_id', 'clients.id')->limit(1)
            )->get();
        }
        //dd($agent->name);
        return view('clients.index', compact('agent', 'clients', 'clientsCount'));
    }

    public function list() {}

    public function create()
    {
        return view('clients.create');
    }

    public function storeProcess(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:clients,email',
            'phone' => 'nullable|string|max:15',
            'agent_id' => 'nullable|exists:agents,id',
        ]);

        try {
            $client = Client::create([
                'name' => $request->name,
                'email' => $request->email,
                'status' => $request->status,
                'phone' => $request->dial_code . $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
                'civil_no' => $request->civil_no,
                'status' => 'active',
                'passport_no' => $request->passport_no,
                'agent_id' => $request->agent_id,
            ]);
        } catch (Exception $e) {
            logger('Error creating client: ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Failed to create client',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Client created successfully',
            'data' => $client,
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'dial_code' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:15',
            'agent_id' => 'required|exists:agents,id',
        ]);

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

        if($user->role_id == Role::COMPANY) {
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
                $task->cancellation_policy = json_decode($task->cancellation_policy)->policies;
            }
        }

        $invoicesPart = Invoice::with('invoicePartials', 'agent')->where('client_id', $id)->get();
        $paid = $invoicesPart->flatMap->invoicePartials->where('status', 'paid')->sum('amount');
        $unpaid = $invoicesPart->flatMap->invoicePartials->where('status', '<>', 'paid')->sum('amount');

        $clients = Client::with('agent.branch')->get();
        $balanceCredit = Credit::getTotalCreditsByClient($id);

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

        return view('clients.new-profile', compact('client', 'agents', 'invoices', 'tasks', 'paid', 'unpaid', 'clients', 'balanceCredit')); // Ensure the view exists
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
        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'email|unique:clients,email,' . $id,
            'status' => 'nullable',
            'phone' => 'string|max:15',
            'file' => 'nullable|mimes:jpeg,jpg,png', // Optional passport file field
        ]);

        try {
            // Update the client data
            $client->update($request->only(['name', 'email', 'status', 'phone', 'address']));

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

        // Get the new agent details
        $newAgent = $client->agent;

        // Update only pending tasks related to this client, changing the agent's email and id
        Task::where('client_id', $client->id)
            ->where('status', 'pending')
            ->update([
                'agent_id' => $newAgent->id,
                'agent_email' => $newAgent->email,
            ]);

        // Redirect back with a success message
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
                $client->name,
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

        if (!$client) {
            return [
                'status' => 'error',
                'message' => 'Client not found',
            ];
        }

        DB::beginTransaction();

        try {

            $chargeRecord = Charge::where('name', 'LIKE', '%Tap%')
                ->where('company_id', $client->agent->branch->company->id)
                ->select('amount', 'acc_bank_id', 'acc_fee_bank_id', 'acc_fee_id')
                ->first();

            if ($chargeRecord) {
                $defaultPaymentGatewayFee = $chargeRecord->amount;
                $coaBankIdRec = $chargeRecord->acc_bank_id; //COA (Assets) for Debited Bank Account
                $coaFeeIdRec = $chargeRecord->acc_fee_id; //COA (Expenses) for Payment Gateway Fee
                $coaBankFeeIdRec = $chargeRecord->acc_fee_bank_id; //COA (Assets) for Bank Account for the selected Payment Gateway

                $tapAccount = Account::where('id', $coaFeeIdRec)
                    ->where('company_id', $client->agent->branch->company->id)
                    ->first();

                $bankPaymentFee = Account::where('id', $coaBankFeeIdRec)
                    ->where('company_id', $client->agent->branch->company->id)
                    ->first();
            }

            $transaction = Transaction::create([
                'branch_id' =>  $client->agent->branch->id,
                'company_id' =>  $client->agent->branch->company->id,
                'entity_id' =>  $client->agent->branch->company->id,
                'entity_type' => 'client',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'date' => Carbon::now(),
                'description' => 'Client Credit of ' . $client->name,
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
                //     'description' => 'Client Pays via ' . $bankPaymentFee->name . ' by (Assets): ' . $client->name,
                //     'debit' => 0,
                //     'credit' => $payment->amount,
                //     'balance' => null,
                //     'name' =>  $client->name,
                //     'type' => 'receivable',
                //     'voucher_number' => $payment->voucher_number,
                //     'type_reference_id' => $receivableAccountId
                // ]);


                // Create record to payment_gateway assets coa account (OK)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $client->agent->branch->company->id,
                    'branch_id' => $client->agent->branch->id,
                    'account_id' =>  $bankPaymentFee->id,
                    'transaction_date' => Carbon::now(),
                    'description' => 'Client Pays by ' . $client->name . ' via (Assets): ' . $bankPaymentFee->name,
                    'debit' => $payment->amount, //'debit' => $payment->amount - $defaultPaymentGatewayFee,
                    'credit' => 0,
                    'name' =>  $bankPaymentFee->name,
                    'type' => 'bank',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $bankPaymentFee->id
                ]);

                $bankPaymentFee->actual_balance += $payment->amount - $defaultPaymentGatewayFee;
                $bankPaymentFee->save();
            }

            $tapAccount->actual_balance += $defaultPaymentGatewayFee;

            if ($tapAccount) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $client->agent->branch->company->id,
                    'branch_id' => $client->agent->branch->id,
                    'account_id' =>  $tapAccount->id,
                    'voucher_number' => $payment->voucher_number,
                    'transaction_date' => Carbon::now(),
                    'description' => 'Record Payment Gateway Charge (Expenses): ' . $tapAccount->name,
                    'debit' => $defaultPaymentGatewayFee,
                    'credit' => 0,
                    'balance' => $tapAccount->actual_balance,
                    'name' =>  $tapAccount->name,
                    'type' => 'charges',
                    'type_reference_id' => $tapAccount->id
                ]);

                $tapAccount->actual_balance += $defaultPaymentGatewayFee; // Add to expenses account
                $tapAccount->save();
            }


            // $client->credit += $payment->amount;
            // $client->save();

            // Insert credit table
            $topupCreditClientData = [
                'company_id'  => $client->agent->branch->company->id,
                'client_id'   => $client->id,
                'type'        => 'Topup',
                'description' => 'Topup Credit via ' . $payment->voucher_number,
                'amount'      => $payment->amount,
            ];

            Log::info('Creating Credit record:', $topupCreditClientData);

            Credit::create($topupCreditClientData);


        } catch (Exception $e) {
            DB::rollBack();
            logger('Error adding credit: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to add credit',
            ];
        }

        DB::commit();
        return [
            'status' => 'success',
            'message' => 'Credit added successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $client->credit,
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
                'client_name'     => $client->name,
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
                    'description' => 'Update Credit for ' . $client->name,
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

        if($request->amount <= 0) {
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

        //dd($balanceCredit);

        DB::beginTransaction();

        try {
    
            $liabilities = Account::where('name', 'Liabilities')
                ->where('company_id', $client->agent->branch->company->id)
                ->first();

            if (!$liabilities) {
                throw new Exception('Liabilities account not found');
            }

            $advances = Account::where('name', 'Advances')
                ->where('company_id', $client->agent->branch->company->id)
                ->where('parent_id', $liabilities->id)
                ->first();
            
            if (!$advances) {
                throw new Exception('Advances account not found');
            }

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $client->agent->branch->company->id)
                ->where('parent_id', $advances->id)
                ->where('root_id', $liabilities->id)
                ->first();
            
            if (!$clientAdvance) {
                throw new Exception('Client Advance account not found');
            }

            $refundPayable = Account::where('name', 'Refund Payable')
                ->where('company_id', $client->agent->branch->company->id)
                ->where('root_id', $liabilities->id)
                ->first();

            if (!$refundPayable) {
                throw new Exception('Refund Payable account not found');
            }

            $clientRefund = Account::where('name', 'Clients')
                ->where('company_id', $client->agent->branch->company->id)
                ->where('parent_id', $refundPayable->id)
                ->where('root_id', $liabilities->id)
                ->first();
            
            if (!$clientRefund) {
                throw new Exception('Client Refund account not found');
            }

            $transaction = Transaction::create([
                'branch_id' =>  $client->agent->branch->id,
                'company_id' =>  $client->agent->branch->company->id,
                'entity_id' =>  $client->agent->branch->company->id,
                'entity_type' => 'client',
                'transaction_type' => 'credit',
                'amount' => $request->amount,
                'date' => Carbon::now(),
                'description' => 'Client Refund of ' . $client->name . ' of ' . $request->amount,
                'invoice_id' => null,
                'reference_type' => 'Refund',
                'reference_number' => null,
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $client->agent->branch->id,
                'company_id' => $client->agent->branch->company->id,
                'account_id' =>  $clientAdvance->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Deduct Client Advance: ' . $client->name . ' of ' . $request->amount,
                'debit' => $request->amount,
                'credit' => 0,
                'balance' => null,
                'name' =>  $client->name,
                'type' => 'receivable',
                'voucher_number' => null,
                'type_reference_id' => $advances->id
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $client->agent->branch->id,
                'company_id' => $client->agent->branch->company->id,
                'account_id' =>  $clientRefund->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Debit Client Refund Payable: ' . $client->name . ' of ' . $request->amount,
                'debit' => 0,
                'credit' => $request->amount,
                'balance' => null,
                'name' =>  $client->name,
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

            try{
                Credit::create([
                    'company_id'  => $client->agent->branch->company->id,
                    'client_id'   => $client->id,
                    'type'        => 'Refund Credit',
                    'description' => 'Refund Credit for ' . $client->name,
                    'amount'      => -($request->amount),
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

}
