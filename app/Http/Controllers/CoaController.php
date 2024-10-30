<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Account;
use App\Models\Supplier;
use App\Models\CoaCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class CoaController extends Controller
{


    public function index(Request $request)
    {
        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();
        
        $accounts = Account::where('company_id', $company->id)
        ->whereNull('parent_id') // Get root accounts
        ->with(relations: 'children') // Eager load children
        ->get();


        // Sample data with a nested structure
        // $accounts = [
        //     [
        //         'description' => 'Cash Flows',
        //         'code' => null,
        //         'actual' => null,
        //         'budget' => null,
        //         'variance' => null,
        //         'children' => [
        //             [
        //                 'description' => 'Adjustment for Depreciation',
        //                 'code' => '1530',
        //                 'actual' => 0,
        //                 'budget' => 0,
        //                 'variance' => 0,
        //                 'children' => [
        //                     [
        //                         'description' => 'Accumulated Depreciation, Furniture',
        //                         'code' => '1720',
        //                         'actual' => 0,
        //                         'budget' => 0,
        //                         'variance' => 0,
        //                     ],
        //                     // Add more nested accounts here
        //                 ],
        //             ],
        //             // Add more parent accounts here
        //         ],
        //     ],
        //     // Add more top-level accounts here
        // ];

        $views = ['Cash Flow - Default', 'Balance Sheet - Default'];
        $periods = ['September 2024', 'October 2024'];

        return view('coa.index', compact('accounts', 'views', 'periods'));
    }
public function accounts(): View
{
$user = Auth::user();
$company = Company::where('user_id', $user->id)->first();
$agents = Agent::where('company_id', $company->id)->get();


$agentIds = Agent::where('company_id', $company->id)->pluck('id')->toArray();
$invoices = Invoice::with('agent.company', 'client')->whereIn('agent_id', $agentIds)->get();
$clients = Client::whereIn('agent_id', $agentIds)->with('agent.company')->get();

$accounts = Account::where('company_id', $company->id)
->whereNull('parent_id') // Get root accounts
->with(relations: 'children') // Eager load children
->get();

$categories = CoaCategory::all();
$suppliers = Supplier::all();

return view('coa.accounts', compact('categories', 'clients','invoices','accounts', 'company', 'suppliers'));
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