<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Account;
use App\Models\CoaCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

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
            return redirect()->route('some.route')->with('error', 'Company not found.');
        }

        // Fetch all agents related to the company
        $agents = Agent::where('company_id', $company->id)->get();
        $agentIds = $agents->pluck('id')->toArray();

        // Fetch invoices and clients related to the agents
        $invoices = Invoice::with('agent.company', 'client')
            ->whereIn('agent_id', $agentIds)
            ->get();

        $clients = Client::whereIn('agent_id', $agentIds)
            ->with('agent.company')
            ->get();

        // Fetch all suppliers
        $suppliers = Supplier::all();

        // Get  data from the privates function
        $assets = $this->getAssets();
        $liabilities = $this->getLiabilities();
        $incomes = $this->getIncome();
        $expenses = $this->getExpenses();

        return view('coa.index', compact('assets',  'liabilities', 'incomes' , 'expenses','invoices', 'clients', 'suppliers'));
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

            foreach ($assets as $asset) {
                $asset->level3assets = Account::where('parent_id', $asset->id)->get();

                foreach ($asset->level3assets as $level3asset) {
                    // Fetch level 4 for each level 3
                    $level3asset->level4assets = Account::where('parent_id', $level3asset->id)->get();

                             foreach ($level3asset->level4assets as $level4asset) {
                            // Fetch actual_balance and budget_balance attributes
                            $actualBalanceAssets = $level4asset->actual_balance ?? 0; // Default to 0 if not set
                            $budgetBalanceAssets = $level4asset->budget_balance ?? 0; // Default to 0 if not set

                            // Optional: Store the values in an array for later use if needed
                            $balancesAssets[] = [
                                'actual_balance' => $actualBalanceAssets,
                                'budget_balance' => $budgetBalanceAssets,
                            ];
                          
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


}