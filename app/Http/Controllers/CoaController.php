<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Account;
use App\Models\CoaCategory;
use App\Models\Supplier;
use App\Models\GeneralLedger;
use App\Models\Payment;
use App\Models\Sequence;
use Exception;
use Illuminate\Support\Facades\Auth;

class CoaController extends Controller
{
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists before proceeding
        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        // Fetch all agents related to the company
        $agents = Agent::where('company_id', $company->id)->get();
        $agentIds = $agents->pluck('id')->toArray();

        // Fetch invoices and clients related to the agents
        $invoices = Invoice::with('agent.branch', 'client')
            ->whereIn('agent_id', $agentIds)
            ->get();

        $clients = Client::whereIn('agent_id', $agentIds)
            ->with('agent.branch')
            ->get();

        // Fetch all suppliers
        $suppliers = Supplier::all();

        // Get  data from the privates function
        $assets = $this->getAssets();
        $liabilities = $this->getLiabilities();
        $incomes = $this->getIncome();
        $expenses = $this->getExpenses();

        return view('coa.index', compact('assets',  'liabilities', 'incomes', 'expenses', 'invoices', 'clients', 'suppliers'));
    }

    public function addCategory(Request $request)
    {
        if (auth()->user()->company == null) {
            return response()->json(['success' => false, 'message' => 'User not authorized'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'level' => 'required|integer',
            'budget_balance' => 'required|numeric',
            'actual_balance' => 'required|numeric',
            'parent_id' => 'required|integer',
            'variance' => 'required|numeric',
        ]);

        try {
            $category = new Account();
            $category->name = $request->name;
            $category->code = $request->code;
            $category->level = $request->level;
            $category->parent_id = $request->parent_id;
            $category->variance = $request->variance;
            $category->budget_balance = $request->budget_balance;
            $category->actual_balance = $request->actual_balance;
            $category->company_id = auth()->user()->company->id;
            $category->save();

            return response()->json(['success' => true, 'id' => $category->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function getAssets()
    {
        // Assets Account
        $assetsId = Account::where('name', 'Assets')->value('id');

        // Initialize assets collection
        $assets = collect();

        if ($assetsId) {
            // Top-level assets
            $assets = Account::where('parent_id', $assetsId)->get();

            foreach ($assets as $key => $asset) {
                $asset->level3assets = Account::where('parent_id', $asset->id)->get();

                foreach ($asset->level3assets as $level3asset) {
                    // Fetch level 4 for each level 3
                    $level5assetsId = [];
                    $level3asset->level4assets = Account::where('parent_id', $level3asset->id)->get();
                    foreach ($level3asset->level4assets as $key => $level4asset) {
                        if ($agent = $level4asset->agent) {

                            $invoices = Invoice::with('client')->where('agent_id', $agent->id)->get();

                            $actualBalance = 0.00;
                            $unpaidInvoices = $invoices->where('status', 'unpaid')->sum('amount');
                            $level4asset->actual_balance = $actualBalance - $unpaidInvoices;

                            $level5assets = collect();

                            // Find clients related to the invoices and make them level 5 assets
                            foreach ($invoices as $invoice) {
                                $level5Account = $invoice->client->account;
                                $actualBalance = 0.00;
                                if ($level5Account && $invoice->status == 'unpaid') {
                                    $actualBalance -= $invoice->amount;
                                }
                                $level5Account->actual_balance = $actualBalance;
                                $level5Account->save();
                                $level5assets->push($level5Account);

                                $level5assetsId[] = $level5Account->id;
                            }
                            $level4asset->level5assets = $level5assets;
                        }
                    }
                }
            }
        }
        return $assets;
    }

    private function getLiabilities()
    {
        // Liabilities Account
        $liabilitiesId = Account::where('name', 'Liabilities')->value('id');

        // Initialize liabilities collection
        $liabilities = collect();

        if ($liabilitiesId) {
            // Top-level liabilities
            $liabilities = Account::where('parent_id', $liabilitiesId)->get();

            foreach ($liabilities as $liability) {
                $liability->level3liabilities = Account::where('parent_id', $liability->id)->get();

                foreach ($liability->level3liabilities as $level3liability) {
                    // Fetch level 4 for each level 3
                    $level3liability->level4liabilities = Account::where('parent_id', $level3liability->id)->get();

                    foreach ($level3liability->level4liabilities as $level4liability) {

                        if (stripos($level3liability->name, 'payable') !== false) {

                            $suppliers = Supplier::with('tasks.invoiceDetail.invoice')->where('account_id', $level4liability->id)->get();
                            $invoiceIds = $suppliers->flatMap(function ($supplier) {
                                return $supplier->tasks->flatMap(function ($task) {
                                    return optional($task->invoiceDetail)->invoice ? [$task->invoiceDetail->invoice->id] : [];
                                });
                            })->unique();

                            $generalLedgers = GeneralLedger::whereIn('invoice_id', $invoiceIds)->where('account_id', $level3liability->id)->get();
                            $credit = 0.00;
                            $debit = 0.00;
                            $actualBalance = 0.00;
                            foreach ($generalLedgers as $generalLedger) {
                                $credit += $generalLedger->credit;
                                $debit += $generalLedger->debit;
                            }

                            $level4liability->actual_balance = $credit - $debit;
                            $level4liability->save();

                            $level4liability->credit = $credit;
                            $level4liability->debit = $debit;
                        } else {
                            // Assuming level4liability has actual_balance and budget_balance attributes
                            $actualBalanceLiabilities = $level4liability->actual_balance; // Replace with the actual field name
                            $budgetBalanceLiabilities = $level4liability->budget_balance; // Replace with the actual field name
                            // Optional: Store the values in an array for later use if needed
                            $balancesLiabilities[] = [
                                'actual_balance' => $actualBalanceLiabilities,
                                'budget_balance' => $budgetBalanceLiabilities,
                            ];
                        }
                    }
                }
            }
        }

        return $liabilities;
    }
    private function getIncome()
    {
        // Income Account
        $incomeId = Account::where('name', 'Income')->value('id');

        // Initialize income collection
        $incomes = collect();

        if ($incomeId) {
            // Top-level income
            $incomes = Account::where('parent_id', $incomeId)->get();

            foreach ($incomes as $income) {
                $income->level3income = Account::where('parent_id', $income->id)->get();

                foreach ($income->level3income as $level3income) {
                    // Fetch level 4 for each level 3
                    $level3income->level4incomes = Account::where('parent_id', $level3income->id)->get();

                    foreach ($level3income->level4incomes as $level4income) {
                        // Assuming level4income has actual_balance and budget_balance attributes
                        $actualBalanceIncome = $level4income->actual_balance; // Replace with the actual field name
                        $budgetBalanceIncome = $level4income->budget_balance; // Replace with the actual field name

                        // Optional: Store the values in an array for later use if needed
                        $balancesIncome[] = [
                            'actual_balance' => $actualBalanceIncome,
                            'budget_balance' => $budgetBalanceIncome,
                        ];
                    }
                }
            }
        }

        return $incomes;
    }

    private function getExpenses()
    {
        // Expenses Account
        $expensesId = Account::where('name', 'Expenses')->value('id');

        // Initialize expenses collection
        $expenses = collect();

        if ($expensesId) {
            // Top-level expenses
            $expenses = Account::where('parent_id', $expensesId)->get();

            foreach ($expenses as $expense) {
                $expense->level3expenses = Account::where('parent_id', $expense->id)->get();

                foreach ($expense->level3expenses as $level3expense) {
                    // Fetch level 4 for each level 3
                    $level3expense->level4expenses = Account::where('parent_id', $level3expense->id)->get();

                    foreach ($level3expense->level4expenses as $level4expense) {

                        // Assuming level4income has actual_balance and budget_balance attributes
                        $actualBalanceExpenses = $level4expense->actual_balance; // Replace with the actual field name
                        $budgetBalanceExpenses = $level4expense->budget_balance; // Replace with the actual field name

                        // Optional: Store the values in an array for later use if needed
                        $balancesExpenses[] = [
                            'actual_balance' => $actualBalanceExpenses,
                            'budget_balance' => $budgetBalanceExpenses,
                        ];
                    }
                }
            }
        }
        return $expenses;
    }



    // create accounts
    public function createAccounts(Request $request)
    {
        // Allowed account types in lowercase for validation
        $allowedTypes = ['assets', 'liabilities', 'income', 'expenses'];

        // Validate the incoming request, allowing case-insensitive matching for 'type'
        $request->validate([
            'accountName' => 'required|string|max:255',
            'type' => ['required', 'string', function ($attribute, $value, $fail) use ($allowedTypes) {
                if (!in_array(strtolower($value), $allowedTypes)) {
                    $fail('The selected type is invalid.');
                }
            }],
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        // Convert 'type' to capitalize the first letter (e.g., 'assets' -> 'Assets')
        $type = ucfirst(strtolower($request->type));

        // Find the parent account ID based on the type and company
        $parentAccount = Account::where('name', $type)
            ->where('company_id', $company->id)
            ->first();

        if (!$parentAccount) {
            return response()->json([
                'success' => false,
                'message' => "{$type} account not found for this company.",
            ], 404);
        }

        // Create the new account under the correct parent_id
        $newAccount = Account::create([
            'name' => $request->accountName,
            'parent_id' => $parentAccount->id,
            'company_id' => $company->id,
            'level' => 2,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => "New {$type} account created successfully with parent ID {$parentAccount->id}.",
            'account' => $newAccount,
        ], 201);
    }



    public function dstry($id)

    {
        $account = Account::find($id);

        if ($account) {
            $account->delete();
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }
    }

    public function updateCode(Request $request, $id)
    {
        // Validate the incoming request to ensure a code is provided
        $request->validate([
            'code' => 'required|string|max:255',
        ]);

        // Find the asset and liability by ID
        $asset = Account::find($id);
        $liability = Account::find($id); // Assuming liabilities are also stored in the Account model

        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found.',
            ], 404);
        }

        if (!$liability) {
            return response()->json([
                'success' => false,
                'message' => 'Liability not found.',
            ], 404);
        }

        // Update the asset's code
        $asset->code = $request->code;
        $asset->save();

        // Update the liability's code
        $liability->code = $request->code; // Assuming the same code update for liability
        $liability->save();

        return response()->json([
            'success' => true,
            'message' => 'Code updated successfully ',
        ]);
    }



    public function store(Request $request)
    {
        $request->validate([
            'account_name' => 'required|string|max:100',
            'account_description' => 'required|string'
        ]);

        $parent = Account::where('id', $request->parent_id)->first();

        $account = Account::create([
            'name' => $request->account_name,
            'level' => $parent->level + 1,
            'parent_id' => $request->parent_id,
            'company_id' => $parent->company_id,
            'description' => $request->account_description,
            'balance' => $request->balance,
        ]);


        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('documents', 'public'); // Store in storage/app/public/documents
                // Save the path to the database as needed
            }
        }

        // Handle the creation of the account, transaction, and document upload...

        return redirect()->back()->with('success', 'Item added successfully!');
    }

    public function payment(Request $request)
    {
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists before proceeding
        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        $voucherSequence = Sequence::where('sequence_for', 'VOUCHER')->lockForUpdate()->first();

        if (!$voucherSequence) {
            $voucherSequence = Sequence::create(['current_sequence' => 1]);
        }

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);

        $voucherSequence->current_sequence++;
        $voucherSequence->save();


        return view('coa.payment', compact('company', 'voucherNumber'));
    }


    private function generateVoucherNumber($sequence)
    {
        $year = now()->year;
        return sprintf('VOU-%s-%05d', $year, $sequence);
    }


    public function getLevel1Accounts(Request $request)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        // Fetch Level 1 accounts, assuming 'level' field defines the hierarchy level
        $accounts = Account::where('level', 1)
            ->whereNull('parent_id')
            ->where('company_id', $company->id)
            ->get(['id', 'name']); // Return only necessary fields (id, name)

        return response()->json($accounts);
    }

    // Get child accounts (Level 2) based on Level 1 selection
    public function getLevel2Accounts($level1Id)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        // Fetch child accounts for Level 1 selection
        $accounts = Account::where('parent_id', $level1Id)
            ->where('company_id', $company->id)
            ->get(['id', 'name']);

        return response()->json($accounts);
    }

    // Get child accounts (Level 3) based on Level 2 selection
    public function getLevel3Accounts($level2Id)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        // Fetch child accounts for Level 2 selection
        $accounts = Account::where('parent_id', $level2Id)
            ->where('company_id', $company->id)
            ->get(['id', 'name']);

        return response()->json($accounts);
    }

    public function getLevel4Accounts($level3Id)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        $accounts = Account::where('parent_id', $level3Id)
            ->where('company_id', $company->id)
            ->get(['id', 'name', 'actual_balance']);

        return response()->json($accounts);
    }

    public function getTransactionsByLevel4(Request $request)
    {
        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }

        $level4Id = $request->query('level4_id');

        // Fetch transactions where account_id matches the selected Level 4 ID
        $transactions = GeneralLedger::where('account_id', $level4Id)->get();

        return response()->json($transactions);
    }


    public function submitVoucher(Request $request)
    {
        Log::info('request', ['request' => $request]);
        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();
        Log::info('company', ['company' => $company]);
        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }

        $data = $request->validate([
            'voucher_no' => 'required|string|max:255',
            'voucher_date' => 'required|date',
            'payment_method' => 'string|max:255',
            'pay_to' => 'string|max:255',
            'entries' => 'required|array',
            'entries.*.account_id' => 'required|exists:accounts,id',
            'entries.*.particulars' => 'string|max:255',
            'entries.*.debit' => 'nullable|numeric',
            'entries.*.credit' => 'nullable|numeric',
        ]);

        $voucherNo = $data['voucher_no'];
        $voucherDate = $data['voucher_date'];
        $paymentMethod = $data['payment_method'];
        $payTo =  $data['pay_to'];

        Log::info('data', ['data' => $data]);
        // Create General Ledger entries
        foreach ($data['entries'] as $entry) {

            $account = Account::find($entry['account_id']);
            Log::info('account_id', ['account_id' => $entry['account_id']]);

            $amount = $entry['debit'] ?? $entry['credit'];
            $type = $entry['debit'] ? 'debit' : 'credit';

            $payment = Payment::create([
                'voucher_number' => $voucherNo,
                'from' => $company->name,
                'pay_to' => $payTo,
                'account_id' => $entry['account_id'],
                'currency' => 'KWD',
                'payment_date' => $voucherDate,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'status' => 'paid',
                'account_number' => NULL,
                'bank_name' => NULL,
                'swift_no' => NULL,
                'iban_no' => NULL,
                'country' => NULL,
                'tax' => NULL,
                'discount' => NULL,
                'shipping' => NULL,
                'payment_reference' => $voucherNo,
                'type' => $type,
            ]);

            $newBalance = $this->calculateNewBalance($account->actual_balance, $entry['debit'], $entry['credit']);
            Log::info('newBalance', ['newBalance' => $newBalance]);
            GeneralLedger::create([
                'transaction_id' => $payment->id,
                'company_id' => $company->id,
                'account_id' => $entry['account_id'],
                'transaction_date' => $data['voucher_date'],
                'voucher_number' => $voucherNo,
                'description' => $entry['particulars'],
                'debit' => $entry['debit'] ?? 0,
                'credit' => $entry['credit'] ?? 0,
                'balance' => $newBalance
            ]);

            // Update the actual_balance of the Level 4 account

            $account->actual_balance = $newBalance;
            $account->save();
        }

        return response()->json(['message' => 'Voucher submitted successfully']);
    }

    private function calculateNewBalance($currentBalance, $debit, $credit)
    {
        // Implement your logic for updating the balance
        return $currentBalance + ($debit - $credit);
    }


    public function transaction(Request $request)
    {
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists before proceeding
        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        $level3Id = $request->input('level3Id');
        $level4Id = $request->input('level4Id');

        // Retrieve all transactions ordered by date descending
        $transactions = GeneralLedger::orderBy('created_at', 'desc')->get();

        // Group transactions by date (e.g., "2025-01-11")
        $transactionsByDate = $transactions->groupBy(function ($transaction) {
            return $transaction->created_at->format('Y-m-d');
        });

        // Pass grouped transactions to the view
        return view('coa.transaction', compact('company', 'transactionsByDate', 'level4Id', 'level3Id'));
    }
}
