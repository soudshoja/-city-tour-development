<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Transaction;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if($user->role_id == Role::ADMIN){

            $request->validate([
                'company_id' => 'required|default:1|exists:companies,id',
            ]);

            $company = Company::findOrFail($request->company_id);

            $branchesId = $company->branches->pluck('id')->toArray();
            $transactions = Transaction::whereIn('branch_id', $branchesId)->orderBy('created_at', 'desc')->get();
            $totalRecords = $transactions->count();

        } if ($user->role->id == Role::COMPANY) {
            
            $branchesId = $user->company->branches->pluck('id')->toArray();

            $transactions = Transaction::whereIn('branch_id', $branchesId)->orderBy('created_at', 'desc')->get();
            $totalRecords = $transactions->count();

        } elseif ($user->role->id == Role::BRANCH) {

            $transactions = Transaction::where('branch_id', $user->branch->id)->get();
            $totalRecords = $transactions->count();

        } else {
            $transactions = collect(); 
            $totalRecords = 0; // No transactions for other roles
        }
        return view('transactions.index', compact('transactions','totalRecords'));
    }
}
