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

            $totalRecords = Transaction::whereIn('branch_id', $branchesId)->count();
            $transactions = Transaction::whereIn('branch_id', $branchesId)->get();
        } elseif (Auth::user()->role->id == Role::BRANCH) {
            $totalRecords = Transaction::where('branch_id', Auth::user()->branch->id)->count();
            $transactions = Transaction::where('branch_id', Auth::user()->branch->id)->get();
        } else {
            $totalRecords = 0;
            $transactions = collect(); 
        }
        return view('transactions.index', compact('transactions','totalRecords'));
    }
}
