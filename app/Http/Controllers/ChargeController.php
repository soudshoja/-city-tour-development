<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Company;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Exception;

class ChargeController extends Controller
{
    public function index()
    {
        $charges = Charge::all();

        if (Auth::user()->role == 'company') {
            $totalCharges = Charge::where('company_id', Auth::user()->company->id)->sum('amount');
        } elseif (Auth::user()->role == 'branch') {
            $totalCharges = Charge::where('branch_id', Auth::user()->branch->id)->sum('amount');
        } else {
            $totalCharges = 0;
        }

        return view('charges.index', compact('charges', 'totalCharges'));
    }

    public function show($id)
    {
        $charge = Charge::find($id);

        if (!$charge) {
            return response()->json(['error' => 'Charge not found'], 404);
        }

        return response()->json([
            'id' => $charge->id,
            'name' => $charge->name,
            'type' => $charge->type,
            'description' => $charge->description,
            'amount' => $charge->amount,
        ]);
    }

    public function create()
    {
        // Fetch COA for Payment Gateway Fee (Expenses)   
        $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges') // or bank account
        ->where('company_id', Auth::user()->company->id)
        ->first();

        // Fetch COA for Payment Gateway (Assets)
        $coaPaymentGatewayBankAcc = Account::whereHas('parent', function ($query) {
            $query->where('name', 'Payment Gateway');
        })
        ->where('company_id', Auth::user()->company->id)
        ->whereDoesntHave('children')
        ->get();  // get() will return a collection
    
        // Fetch COA for Bank Account (Assets)
        $coaBankAccount = Account::whereHas('parent', function ($query) {
            $query->where('name', 'Bank Accounts');
        })
        ->where('company_id', Auth::user()->company->id)
        ->whereDoesntHave('children')
        ->get();  // get() will return a collection
    
        // Ensure that the COA collections are not empty
        // if ($coaPaymentGateway->isEmpty() || $coaPaymentGatewayBankAcc->isEmpty() || $coaBankAccount->isEmpty()) {
        //     return redirect()->route('charges.index')->with('error', 'Some required COA records are missing.');
        // }
        //dd($coaPaymentGateway,$coaPaymentGatewayBankAcc,$coaBankAccount);
        return view('charges.create', compact('coaPaymentGateway', 'coaPaymentGatewayBankAcc', 'coaBankAccount'));
    }
    

    // Store new charge
    public function store(Request $request)
    {   
        // Fetch COA for Payment Gateway Fee (Expenses)   
        $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges') // or bank account
        ->where('company_id', Auth::user()->company->id)
        ->first();

        // Fetch COA for Payment Gateway (Assets)
        $coaPaymentGatewayBankAcc = Account::where('name', 'Payment Gateway')
        ->where('company_id', Auth::user()->company->id)
        ->first();  // Use first() to get a single model, not a collection

        // Fetch COA for Bank Account
        $coaBankAccount = Account::whereHas('parent', function ($query) {
            $query->where('name', 'Bank Accounts');
        })
        ->where('company_id', Auth::user()->company->id)
        ->first();  // Use first() to get a single model, not a collection

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);
    
        try {
            DB::beginTransaction();
               
            //dd($coaPaymentGatewayBankAcc->id);
            // Create Account for Payment Gateway Bank Fee (if it doesn't exist)
            $newAccountBankFee = Account::create([
                'name' => $request->name,
                'parent_id' => $coaPaymentGatewayBankAcc->id,  // Use the id of the fetched model
                'company_id' => Auth::user()->company->id,
                'branch_id' => Auth::user()->branch_id, 
                'account_type' => 'asset', 
                'report_type' => 'balance sheet', 
                'level' => 4, 
                'is_group' => 0, 
                'disabled' => 0, 
                'actual_balance' => 0.00, 
                'budget_balance' => 0.00, 
                'variance' => 0.00,
                'currency' => 'KWD', // Define currency
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $charge = Charge::create([
                'name' => $request->get('name'),
                'description' => $request->get('description'),
                'type' => $request->get('type'),
                'amount' => $request->get('amount'),
                'acc_fee_id' => $coaPaymentGateway->id,
                'acc_bank_id' => $request->get('acc_bank_id'),
                'acc_fee_bank_id' => $newAccountBankFee->id, 
                'company_id' => Auth::user()->company->id, 
                'branch_id' => Auth::user()->branch->id, 
            ]);
    
            // Commit the transaction
            DB::commit();
    
            return redirect()->route('charges.index')->with('success', 'Charge created successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
    
public function edit($id)
{
    $charge = Charge::findOrFail($id);

    $accFee = Account::find($charge->acc_fee_id);
    $accBankFee = Account::find($charge->acc_fee_bank_id);
    $accBank = Account::find($charge->acc_bank_id);

    $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges')
        ->where('company_id', Auth::user()->company->id)
        ->get();

    $coaPaymentGatewayBankAcc = Account::whereHas('parent', function ($query) {
            $query->where('name', 'Payment Gateway');
        })
        ->where('company_id', Auth::user()->company->id)
        ->whereDoesntHave('children')
        ->get();

    $coaBankAccount = Account::whereHas('parent', function ($query) {
            $query->where('name', 'Bank Accounts');
        })
        ->where('company_id', Auth::user()->company->id)
        ->whereDoesntHave('children')
        ->get();

    return view('charges.edit', compact(
        'charge',
        'accFee',
        'accBankFee',
        'accBank',
        'coaPaymentGateway',
        'coaPaymentGatewayBankAcc',
        'coaBankAccount'
    ));
}
    
    // public function edit($id)
    // {   
    //     //dd($id);
    //     $charge = Charge::findOrFail($id);
    
    //     // Optional: Authorization check
    //     if (Auth::user()->role == 'company' && $charge->company_id != Auth::user()->company->id) {
    //         abort(403, 'Unauthorized');
    //     }
    
    //     if (Auth::user()->role == 'branch' && $charge->branch_id != Auth::user()->branch->id) {
    //         abort(403, 'Unauthorized');
    //     }

    //     //$accountsDebug = Account::whereDoesntHave('children')->get();
    //     //dd($accountsDebug->pluck('name', 'id'));

    //     // Fetch COA for Payment Gateway Fee (Expenses)
    //     // $coaPaymentGateway = Account::whereHas('parent', function ($query) {
    //     //     $query->where('name', 'Payment Gateway Charges');
    //     // })
    //     // ->where('company_id', Auth::user()->company->id)
    //     // ->whereDoesntHave('children')
    //     // ->get();

    //     $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges')
    //     ->where('company_id', Auth::user()->company->id)
    //     ->first();  // Use first() to get a single model, not a collection

    //     //dd($coaPaymentGateway->name);

    //     // Fetch COA for Payment Gateway (Assets)
    //     $coaPaymentGatewayBankAcc = Account::whereHas('parent', function ($query) {
    //         $query->where('name', 'Payment Gateway');
    //     })
    //     ->where('company_id', Auth::user()->company->id)
    //     ->whereDoesntHave('children')
    //     ->get();

    //     //dd($coaPaymentGatewayBankAcc->name);
                

    //     // Fetch COA for Bank Account (Assets)
    //     $coaBankAccount = Account::whereHas('parent', function ($query) {
    //         $query->where('name', 'Bank Accounts');
    //     })
    //     ->where('company_id', Auth::user()->company->id)
    //     ->whereDoesntHave('children')
    //     ->get();

    //     dd($coaPaymentGateway->name,$coaPaymentGatewayBankAcc->name,$coaBankAccount->name);
        

    //     //dd($charge->acc_fee_id,$charge->acc_bank_id,$charge->acc_fee_bank_id);
    //     $accFee = Account::find($charge->acc_fee_id);
    //     $accBank = Account::find($charge->acc_bank_id);
    //     $accBankFee = Account::find($charge->acc_fee_bank_id);
        
    //     return view('charges.edit', compact('charge','coaPaymentGateway','coaPaymentGatewayBankAcc','coaBankAccount', 'accFee', 'accBank', 'accBankFee'));
    // }
    

    // public function update(Request $request, $id)
    // {   
    //     //dd($request);
    //     // Fetch COA for Payment Gateway Fee (Expenses)
    //     // $coaPaymentGateway = Account::whereHas('parent', function ($query) {
    //     //     $query->where('name', 'Payment Gateway Charges');
    //     // })
    //     // ->where('company_id', Auth::user()->company->id)
    //     // ->whereDoesntHave('children')
    //     // ->get();

    //     $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges')
    //     ->where('company_id', Auth::user()->company->id)
    //     ->first();  // Use first() to get a single model, not a collection
        
    //     // Fetch COA for Payment Gateway (Assets)
    //     $coaPaymentGatewayBankAcc = Account::whereHas('parent', function ($query) {
    //         $query->where('name', 'Payment Gateway');
    //     })
    //     ->where('company_id', Auth::user()->company->id)
    //     ->whereDoesntHave('children')
    //     ->get();
                

    //     // Fetch COA for Bank Account (Assets)
    //     $coaBankAccount = Account::whereHas('parent', function ($query) {
    //         $query->where('name', 'Bank Accounts');
    //     })
    //     ->where('company_id', Auth::user()->company->id)
    //     ->whereDoesntHave('children')
    //     ->get();

    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'description' => 'nullable|string|max:255',
    //         'type' => 'required|string|max:255',
    //         'amount' => 'required|numeric|min:0.01',
    //     ]);

    //     try {
    //         DB::beginTransaction();
    //         $charge = Charge::findOrFail($id);
    //         $charge->update([
    //             'name' => $request->get('name'),
    //             'amount' => $request->get('amount'),
    //             'type' => $request->get('type'),
    //             'description' => $request->get('description'),
    //             'acc_bank_id' => $request->get('acc_bank_id'),
    //             'acc_fee_id' => $request->get('acc_fee_id'),
    //             'acc_fee_bank_id' => $request->get('acc_fee_bank_id'),
    //         ]);

    //         // Ensure the account exists or create a new one
    //         $newAccountFee = $coaPaymentGateway ? $coaPaymentGateway : Account::create([
    //             'name' => $request->name,
    //             'parent_id' => $coaPaymentGateway->id,  // Set parent_id to Payment Gateway Charges
    //             'company_id' => Auth::user()->company->id,
    //             'branch_id' => Auth::user()->branch_id,
    //             'account_type' => 'expense',
    //             'report_type' => 'profit loss',
    //             'level' => 4,
    //             'is_group' => 0,
    //             'disabled' => 0,
    //             'actual_balance' => 0.00,
    //             'budget_balance' => 0.00,
    //             'variance' => 0.00,
    //             'currency' => 'KWD',
    //         ]);

    //         $newAccountBankFee = $coaPaymentGatewayBankAcc ? $coaPaymentGatewayBankAcc : Account::create([
    //             'name' => $request->name,
    //             'parent_id' => $coaPaymentGatewayBankAcc->id,
    //             'company_id' => Auth::user()->company->id,
    //             'branch_id' => Auth::user()->branch_id,
    //             'account_type' => 'asset',
    //             'report_type' => 'balance sheet',
    //             'level' => 4,
    //             'is_group' => 0,
    //             'disabled' => 0,
    //             'actual_balance' => 0.00,
    //             'budget_balance' => 0.00,
    //             'variance' => 0.00,
    //             'currency' => 'KWD',
    //         ]);

    //         $newAccountBank = $coaBankAccount ? $coaBankAccount : Account::create([
    //             'name' => $request->name,
    //             'parent_id' => $coaBankAccount->id,
    //             'company_id' => Auth::user()->company->id,
    //             'branch_id' => Auth::user()->branch_id,
    //             'account_type' => 'asset',
    //             'report_type' => 'balance sheet',
    //             'level' => 4,
    //             'is_group' => 0,
    //             'disabled' => 0,
    //             'actual_balance' => 0.00,
    //             'budget_balance' => 0.00,
    //             'variance' => 0.00,
    //             'currency' => 'KWD',
    //         ]);

    //         // Commit the transaction
    //         DB::commit();


    //         // Redirect to the clients list with a success message
    //         return redirect()->route('charges.index')->with('success', 'Charges updated successfully!');
    //     } catch (Exception $e) {

    //         DB::rollBack();

    //         return redirect()->back()->withInput()->with('error', $e->getMessage());
    //     }
    // }


        public function update(Request $request, $id)
    {   
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            // Fetch the charge to update
            $charge = Charge::findOrFail($id);

            // Update charge fields
            $charge->update([
                'name' => $request->get('name'),
                'amount' => $request->get('amount'),
                'type' => $request->get('type'),
                'description' => $request->get('description'),
                'acc_bank_id' => $request->get('acc_bank_id'),
                'acc_fee_id' => $request->get('acc_fee_id'),
                'acc_fee_bank_id' => $request->get('acc_fee_bank_id'),
            ]);


            $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges')
                ->where('company_id', Auth::user()->company->id)
                ->get();  
                
            $coaPaymentGatewayBankAcc = Account::whereHas('parent', function ($query) {
                    $query->where('name', 'Payment Gateway');
                })
                ->where('company_id', Auth::user()->company->id)
                ->whereDoesntHave('children')
                ->get();  // Returns a collection
                
            $coaBankAccount = Account::whereHas('parent', function ($query) {
                    $query->where('name', 'Bank Accounts');
                })
                ->where('company_id', Auth::user()->company->id)
                ->whereDoesntHave('children')
                ->get();  // Returns a collection

            // Payment Gateway Fee Account
            if ($coaPaymentGateway->isEmpty()) {
                // Create new account if not found

                $parentAccount = $coaPaymentGateway->first();

                $coaPaymentGateway = Account::create([
                    'name' => $request->name,
                    'parent_id' => $parentAccount ? $parentAccount->id : null,
                    'company_id' => Auth::user()->company->id,
                    'branch_id' => Auth::user()->branch_id,
                    'account_type' => 'expense',
                    'report_type' => 'profit loss',
                    'level' => 4,
                    'is_group' => 0,
                    'disabled' => 0,
                    'actual_balance' => 0.00,
                    'budget_balance' => 0.00,
                    'variance' => 0.00,
                    'currency' => 'KWD',
                ]);
            }

            // Payment Gateway Bank Account
            if ($coaPaymentGatewayBankAcc->isEmpty()) {
                // Create new account if collection is empty

                $parentAccount = $coaPaymentGatewayBankAcc->first();

                $coaPaymentGatewayBankAcc = Account::create([
                    'name' => $request->name,
                    'parent_id' => $parentAccount ? $parentAccount->id : null,  // Use the first item in the collection
                    'company_id' => Auth::user()->company->id,
                    'branch_id' => Auth::user()->branch_id,
                    'account_type' => 'asset',
                    'report_type' => 'balance sheet',
                    'level' => 4,
                    'is_group' => 0,
                    'disabled' => 0,
                    'actual_balance' => 0.00,
                    'budget_balance' => 0.00,
                    'variance' => 0.00,
                    'currency' => 'KWD',
                ]);
            }

            // Bank Account
            if ($coaBankAccount->isEmpty()) {
                // Create new account if collection is empty

                $parentAccount = $coaBankAccount->first();

                $coaBankAccount = Account::create([
                    'name' => $request->name,
                    'parent_id' => $parentAccount ? $parentAccount->id : null,  // Use the first item in the collection
                    'company_id' => Auth::user()->company->id,
                    'branch_id' => Auth::user()->branch_id,
                    'account_type' => 'asset',
                    'report_type' => 'balance sheet',
                    'level' => 4,
                    'is_group' => 0,
                    'disabled' => 0,
                    'actual_balance' => 0.00,
                    'budget_balance' => 0.00,
                    'variance' => 0.00,
                    'currency' => 'KWD',
                ]);
            }

            // Commit the transaction
            DB::commit();

            return redirect()->route('charges.index')->with('success', 'Charges updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }


    public function destroy()
    {
        return true;
    }
}
