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

public function accounts(): View


{
$user = Auth::user();
$company = Company::where('user_id', $user->id)->first();
$agents = Agent::where('company_id', $company->id)->get();


$agentIds = Agent::where('company_id', $company->id)->pluck('id')->toArray();
$invoices = Invoice::with('agent.company', 'client')->whereIn('agent_id', $agentIds)->get();
$clients = Client::whereIn('agent_id', $agentIds)->with('agent.company')->get();
$accounts = Account::with( 'coacategory', 'company')->get();
$categories = CoaCategory::all();
return view('coa.accounts', compact('categories', 'clients','invoices','accounts', 'company'));
}

}