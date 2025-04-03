<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Role;
use App\Models\User;
use Exception;
use Carbon\Carbon;
use Database\Seeders\CoaSeeder;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index()
    {
        $usersCount = User::all()->count();
        $users = User::with('roles')->get();
        
        return view('users.index', compact('users', 'usersCount'));
    }

    public function editRole($roleId)
    {
        $user = User::find($roleId);
        $roles = Role::all();

        return view('users.edit', compact('user', 'roles'));
    }

    public function storeRole(Request $request)
    {
        $validatedData = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::find($validatedData['user_id']);
        $role = Role::find($validatedData['role_id']);
        try {
            $user->syncRoles($role);
        } catch (Exception $e) {
            logger($e->getMessage());
            return redirect()->route('users.index')->with('error', 'Role assignment failed.');
        }

        return redirect()->route('users.index')->with('success', 'Role assigned successfully.');
    }

    public function newCompany()
    {
        $countries = Country::all(); // Fetch all countries from the `countries` table
        return view('admin.addnewCompany', compact('countries'));
    }

    public function create()
    {
        $user = auth()->user();
        if ($user->role_id == Role::ADMIN) {
            $branches = Branch::all();
        } elseif ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', auth()->user()->company->id)->get();
        } else {
            return redirect()->route('home')->with('error', 'You are not authorized to access this page.');
        }

        $branches_id = $branches->pluck('id');

        $agents = Agent::whereIn('branch_id', $branches_id)->get();
        

        $agentTypes = AgentType::all(); 
        $countries = Country::all(); 

        return view('users.create', compact('agents', 'branches', 'agentTypes', 'countries'));
    }

    public function store(Request $request)
    {
        Log::info('Store function called with request data:', $request->all());

        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:15',
            'code' => 'required|string|max:100|unique:companies,code',
            'country_id' => 'required|integer|exists:countries,id',
            'address' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        Log::info('Validation passed:', $validatedData);

        // Create the user (Company Owner)
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role_id' => Role::COMPANY,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        Log::info('Company owner user created:', ['user_id' => $user->id]);

        // Create the company
        $company = Company::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'code' => $validatedData['code'],
            'country_id' => $validatedData['country_id'],
            'address' => $validatedData['address'],
            'phone' => $validatedData['phone'] ?? null,
            'user_id' => $user->id,
            'status' => $validatedData['status'],
        ]);

        Log::info('Company created:', ['company_id' => $company->id]);


         // Insert to accounts for coa
         $accounts = [
            // Top-Level (Level 1)
            ['code' => '1000', 'name' => 'Assets',     'level' => 1, 'parent' => null],
            ['code' => '2000', 'name' => 'Liabilities','level' => 1, 'parent' => null],
            ['code' => '3000', 'name' => 'Equity',     'level' => 1, 'parent' => null],
            ['code' => '4000', 'name' => 'Income',     'level' => 1, 'parent' => null],
            ['code' => '5000', 'name' => 'Expenses',   'level' => 1, 'parent' => null],
        
            // Assets (Level 2 and deeper)
            ['code' => '1100', 'name' => 'Cash In Hand',                 'level' => 2, 'parent' => 'Assets'],
            ['code' => '1110', 'name' => 'Petty Cash',                   'level' => 3, 'parent' => 'Cash In Hand'],
            
            ['code' => '1200', 'name' => 'Bank Accounts',                'level' => 2, 'parent' => 'Assets'],
            ['code' => '1201', 'name' => 'Maybank',                      'level' => 3, 'parent' => 'Bank Accounts'],
            ['code' => '1203', 'name' => 'Banktest',                     'level' => 3, 'parent' => 'Bank Accounts'],
            ['code' => '1204', 'name' => 'RHB',                          'level' => 3, 'parent' => 'Bank Accounts'],
        
            ['code' => '1300', 'name' => 'Accounts Receivable',          'level' => 2, 'parent' => 'Assets'],
            ['code' => '1310', 'name' => 'Accounts Receivable – Clients','level' => 3, 'parent' => 'Accounts Receivable'],
            ['code' => '1320', 'name' => 'Accounts Receivable – Agents/Branches','level' => 3, 'parent' => 'Accounts Receivable'],
        
            ['code' => '1400', 'name' => 'Supplier Advances/Prepayments','level' => 2, 'parent' => 'Assets'],
            ['code' => '1410', 'name' => 'Prepaid Flights',             'level' => 3, 'parent' => 'Supplier Advances/Prepayments'],
            ['code' => '1420', 'name' => 'Prepaid Hotels',              'level' => 3, 'parent' => 'Supplier Advances/Prepayments'],
        
            ['code' => '1500', 'name' => 'Stock Assets',                'level' => 2, 'parent' => 'Assets'],
            ['code' => '1510', 'name' => 'Stock In Hand',               'level' => 3, 'parent' => 'Stock Assets'],
        
            ['code' => '1600', 'name' => 'Tax Assets',                  'level' => 2, 'parent' => 'Assets'],
        
            ['code' => '1700', 'name' => 'Loans and Advances (Assets)', 'level' => 2, 'parent' => 'Assets'],
            ['code' => '1710', 'name' => 'Employee Advances',           'level' => 3, 'parent' => 'Loans and Advances (Assets)'],
            ['code' => '1720', 'name' => 'Securities and Deposits',     'level' => 3, 'parent' => 'Loans and Advances (Assets)'],
            ['code' => '1721', 'name' => 'Earnest Money',               'level' => 4, 'parent' => 'Securities and Deposits'],
        
            ['code' => '1800', 'name' => 'Fixed Assets',                'level' => 2, 'parent' => 'Assets'],
            ['code' => '1810', 'name' => 'Capital Equipments',          'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1820', 'name' => 'Electronic Equipments',       'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1830', 'name' => 'Furniture and Fixtures',      'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1840', 'name' => 'Office Equipments',           'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1850', 'name' => 'Plants and Machineries',      'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1860', 'name' => 'Buildings',                   'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1870', 'name' => 'Softwares',                   'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1880', 'name' => 'Accumulated Depreciation',    'level' => 3, 'parent' => 'Fixed Assets'],
            ['code' => '1890', 'name' => 'CWIP Account (Construction Work in Progress)', 'level' => 3, 'parent' => 'Fixed Assets'],
        
            ['code' => '1900', 'name' => 'Investments',                 'level' => 2, 'parent' => 'Assets'],
        
            ['code' => '1950', 'name' => 'Temporary Accounts',          'level' => 2, 'parent' => 'Assets'],
            ['code' => '1951', 'name' => 'Temporary Opening',           'level' => 3, 'parent' => 'Temporary Accounts'],
        
            // Liabilities (Level 2 and deeper)
            ['code' => '2100', 'name' => 'Accounts Payable',            'level' => 2, 'parent' => 'Liabilities'],
            ['code' => '2110', 'name' => 'Creditors',                   'level' => 3, 'parent' => 'Accounts Payable'],
            ['code' => '2120', 'name' => 'Accounts Payable – Suppliers (Flights)', 'level' => 3, 'parent' => 'Accounts Payable'],
            ['code' => '2130', 'name' => 'Accounts Payable – Suppliers (Hotels)',  'level' => 3, 'parent' => 'Accounts Payable'],
            ['code' => '2150', 'name' => 'Magic Holiday',               'level' => 3, 'parent' => 'Accounts Payable'],
        
            ['code' => '2200', 'name' => 'Accrued Expenses',            'level' => 2, 'parent' => 'Liabilities'],
            ['code' => '2210', 'name' => 'Accrued Commissions (Agents)','level' => 3, 'parent' => 'Accrued Expenses'],
            ['code' => '2220', 'name' => 'Accrued Expenses (General)',  'level' => 3, 'parent' => 'Accrued Expenses'],
        
            ['code' => '2300', 'name' => 'Stock Liabilities',           'level' => 2, 'parent' => 'Liabilities'],
            ['code' => '2310', 'name' => 'Stock Received But Not Billed','level' => 3, 'parent' => 'Stock Liabilities'],
            ['code' => '2320', 'name' => 'Asset Received But Not Billed','level' => 3, 'parent' => 'Stock Liabilities'],
        
            ['code' => '2400', 'name' => 'Duties and Taxes',            'level' => 2, 'parent' => 'Liabilities'],
            ['code' => '2410', 'name' => 'TDS Payable',                 'level' => 3, 'parent' => 'Duties and Taxes'],
            ['code' => '2420', 'name' => 'GST Payable',                 'level' => 3, 'parent' => 'Duties and Taxes'],
        
            ['code' => '2500', 'name' => 'Loans (Liabilities)',         'level' => 2, 'parent' => 'Liabilities'],
            ['code' => '2510', 'name' => 'Secured Loans',               'level' => 3, 'parent' => 'Loans (Liabilities)'],
            ['code' => '2520', 'name' => 'Unsecured Loans',             'level' => 3, 'parent' => 'Loans (Liabilities)'],
            ['code' => '2530', 'name' => 'Bank Overdraft Account',      'level' => 3, 'parent' => 'Loans (Liabilities)'],
        
            // Equity (Level 2)
            ['code' => '3100', 'name' => 'Capital Stock',               'level' => 2, 'parent' => 'Equity'],
            ['code' => '3200', 'name' => 'Dividends Paid',              'level' => 2, 'parent' => 'Equity'],
            ['code' => '3300', 'name' => 'Opening Balance Equity',      'level' => 2, 'parent' => 'Equity'],
            ['code' => '3400', 'name' => 'Retained Earnings',           'level' => 2, 'parent' => 'Equity'],
        
            // Income (Level 2 and deeper)
            ['code' => '4100', 'name' => 'Direct Income (Revenue)',     'level' => 2, 'parent' => 'Income'],
            ['code' => '4110', 'name' => 'Flight Booking Revenue',      'level' => 3, 'parent' => 'Direct Income (Revenue)'],
            ['code' => '4120', 'name' => 'Hotel Booking Revenue',       'level' => 3, 'parent' => 'Direct Income (Revenue)'],
            ['code' => '4130', 'name' => 'Commission & Service Fee Income', 'level' => 3, 'parent' => 'Direct Income (Revenue)'],
            ['code' => '4140', 'name' => 'Sales',                       'level' => 3, 'parent' => 'Direct Income (Revenue)'],
            ['code' => '4150', 'name' => 'Services (other)',            'level' => 3, 'parent' => 'Direct Income (Revenue)'],
        
            ['code' => '4200', 'name' => 'Indirect Income',             'level' => 2, 'parent' => 'Income'],
        
            // Expenses (Level 2 and deeper)
            ['code' => '5100', 'name' => 'Direct Expenses (Cost of Sales)', 'level' => 2, 'parent' => 'Expenses'],
            ['code' => '5110', 'name' => 'Flights Cost',                    'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)'],
            ['code' => '5120', 'name' => 'Hotels Cost',                     'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)'],
            ['code' => '5130', 'name' => 'Commissions Expense (Agents)',    'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)'],
            ['code' => '5140', 'name' => 'Payment Gateway Charges',         'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)'],
            ['code' => '5150', 'name' => 'Stock Expenses',                  'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)'],
            ['code' => '5151', 'name' => 'Cost of Goods Sold',              'level' => 4, 'parent' => 'Stock Expenses'],
            ['code' => '5152', 'name' => 'Expenses Included in Asset Valuation', 'level' => 4, 'parent' => 'Stock Expenses'],
            ['code' => '5159', 'name' => 'Stock Adjustment',                'level' => 4, 'parent' => 'Stock Expenses'],
        
            ['code' => '5200', 'name' => 'Indirect Expenses (Operating Expenses)', 'level' => 2, 'parent' => 'Expenses'],
            ['code' => '5201', 'name' => 'Administrative Expenses',              'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5202', 'name' => 'Commission on Sales',                  'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5203', 'name' => 'Depreciation',                         'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5204', 'name' => 'Entertainment Expenses',               'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5205', 'name' => 'Freight and Forwarding Charges',       'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5206', 'name' => 'Legal Expenses',                       'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5207', 'name' => 'Marketing Expenses',                   'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5208', 'name' => 'Office Maintenance Expenses',          'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5209', 'name' => 'Office Rent',                          'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5210', 'name' => 'Postal Expenses',                      'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5211', 'name' => 'Print and Stationery',                 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5212', 'name' => 'Round Off',                            'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5213', 'name' => 'Salary',                               'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5214', 'name' => 'Sales Expenses',                       'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5215', 'name' => 'Telephone Expenses',                   'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5216', 'name' => 'Travel Expenses',                      'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5217', 'name' => 'Utility Expenses',                     'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5218', 'name' => 'Write Off',                            'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5219', 'name' => 'Exchange Gain/Loss',                   'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
            ['code' => '5220', 'name' => 'Gain/Loss on Asset Disposal',          'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)'],
        ];
        

        // Store newly inserted IDs
        // $idMapping = [];

        // // Insert accounts dynamically
        // foreach ($accounts as $account) {
        //     // Determine the parent_id dynamically
        //     $parentId = isset($account['parent']) && isset($idMapping[$account['parent']]) 
        //     ? $idMapping[$account['parent']]->id 
        //     : null;

        //     // Insert account
        //     $newId = Account::create([
        //         'name' => $account['name'],
        //         'level' => $account['level'],
        //         'actual_balance' => 0,
        //         'budget_balance' => 0,
        //         'variance' => 0,
        //         'created_at' => Carbon::now(),
        //         'updated_at' => Carbon::now(),
        //         'company_id' => $company->id,
        //         'parent_id' => $parentId,
        //     ]);

        //     // Store new ID for future parent_id references
        //     $idMapping[$account['name']] = $newId;
        // }

        try {
            CoaSeeder::run($company->id);
        } catch (Exception $e) {
            Log::error('Error seeding COA:', ['error' => $e->getMessage()]);
            return redirect()->route('companies.index')->with('error', 'Error creating COA accounts.');
        }


        // Create a default branch for the company
        $defaultBranch = Branch::create([
            'name' => $company->name . ' - Main Branch',
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        Log::info('Default branch created:', ['branch_id' => $defaultBranch->id]);

        // Create a default agent for the company
        // $defaultAgentUser = User::create([
        //     'name' => 'Default Agent',
        //     'email' => 'agent_' . $company->code . '@example.com',
        //     'password' => Hash::make('password123'),
        //     'role_id' => 3,
        //     'remember_token' => Str::random(10),
        //     'first_login' => 1,
        // ]);

        // Log::info('Default agent user created:', ['user_id' => $defaultAgentUser->id]);

        // Agent::create([
        //     'name' => 'Default Agent',
        //     'email' => $defaultAgentUser->email,
        //     'phone_number' => null,
        //     'type_id' => 1,
        //     'branch_id' => $defaultBranch->id,
        //     'user_id' => $defaultAgentUser->id,
        // ]);

        // Log::info('Default agent created.');

        return redirect()->route('companies.index')->with('success', 'Company registered successfully.');
    }

    public function ShowCompanies(Request $request)
    {
        // Retrieve all companies with their related nationality
        $companies = Company::with('nationality')->get(); // Eager load the nationality relationship

        // Retrieve all companies and their count
        $companiesCount = Company::count();

        // Return view with the companies data
        return view('admin.companiesList', compact('companies', 'companiesCount'));
    }
}
