<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index()
    {
        if (Auth::user()->role->name == 'company') {
            $totalRecords = Transaction::where('company_id', Auth::user()->company->id)->count();
            $transactions = Transaction::where('company_id', Auth::user()->company->id)->get();
        } elseif (Auth::user()->role->name == 'branch') {
            $totalRecords = Transaction::where('branch_id', Auth::user()->branch->id)->count();
            $transactions = Transaction::where('branch_id', Auth::user()->branch->id)->get();
        } else {
            $totalRecords = 0;
            $transactions = collect(); 
        }
        return view('transactions.index', compact('transactions','totalRecords'));
    }
}
