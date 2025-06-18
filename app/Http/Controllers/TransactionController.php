<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index()
    {
        if (Auth::user()->role->id == Role::COMPANY) {
            
            $branchesId = Auth::user()->company->branches->pluck('id')->toArray();

            $transactions = Transaction::whereIn('branch_id', $branchesId)->orderBy('created_at', 'desc')->get();
            $totalRecords = $transactions->count();

        } elseif (Auth::user()->role->id == Role::BRANCH) {

            $transactions = Transaction::where('branch_id', Auth::user()->branch->id)->get();
            $totalRecords = $transactions->count();

        } else {
            $transactions = collect(); 
            $totalRecords = 0; // No transactions for other roles
        }
        return view('transactions.index', compact('transactions','totalRecords'));
    }
}
