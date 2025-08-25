<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Company;
use App\Models\Account;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\Log;

class ChargeController extends Controller
{
    public function index()
    {
        if (Auth::user()->role->id == Role::COMPANY) {
            $totalCharges = Charge::where('company_id', Auth::user()->company->id)->count();
            $charges = Charge::where('company_id', Auth::user()->company->id)->get();
        } elseif (Auth::user()->role->id == Role::BRANCH) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view charges.');
            $totalCharges = Charge::where('branch_id', Auth::user()->branch->id)->count();
            $charges = Charge::where('branch_id', Auth::user()->branch->id)->get();
        } else {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view charges.');
            // $totalCharges = 0;
            // $charges = collect();
        }

        $inactiveCharges = [];

        foreach($charges as $charge){
            if($charge->api_key == null){
                $charge->is_active = false;
                $charge->save();
                $inactiveCharges[] = $charge->name;
            }
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
            'charge_type' => $charge->charge_type,
            'paid_by' => $charge->paid_by,
            'description' => $charge->description,
            'amount' => $charge->amount,
            'is_auto_paid' => $charge->is_auto_paid,
            'has_url' => $charge->has_url,
            // 'auth_type' => $charge->auth_type,
            // 'base_url'    => $charge->base_url,
            'api_key'    => $charge->api_key,
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
        $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges')->first();

        $childCoaPaymentGateway = Account::where('parent_id', $coaPaymentGateway->id)
            ->where('name',  $request->name)
            ->first();

        if ($childCoaPaymentGateway) {
            return redirect()->route('charges.index')->withErrors(['name' => 'Please change the charges name.']);
        }

        // Fetch COA for Payment Gateway (Assets)
        $coaPaymentGatewayBankAcc = Account::where('name', 'Payment Gateway')->first();

        //dd($coaPaymentGateway,$childCoaPaymentGateway,$coaPaymentGatewayBankAcc);

        // Fetch COA for Bank Account
        $coaBankAccount = Account::whereHas('parent', function ($query) {
            $query->where('name', 'Bank Accounts');
        })
            ->first();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'charge_type' => 'required',
            'paid_by' => 'required',
            'amount' => 'required|numeric',
            'is_auto_paid' => 'nullable|boolean',
            'has_url' => 'nullable|boolean',
            // 'auth_type' => 'required|in:basic,oauth',
            // 'base_url' => 'nullable|url',
            'api_key' => 'required|string',
        ]);

        // Fetch COA for Payment Gateway
        $coaRootIdAssets = Account::where('name', 'Assets')->first();
        $coaRootIdExpenses = Account::where('name', 'Expenses')->first();


        try {
            DB::beginTransaction();

            //dd($coaPaymentGatewayBankAcc->id);
            // Create Account for Payment Gateway Bank Fee (if it doesn't exist)
            $newAccountBankFee = Account::create([
                'name' => $request->name,
                'parent_id' => $coaPaymentGatewayBankAcc->id,
                'company_id' => Auth::user()->company->id,
                'branch_id' => Auth::user()->branch_id,
                'root_id' => $coaRootIdAssets->id,
                'code' => '1213',
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

            if (is_null($childCoaPaymentGateway)) {
                $newPaymentGatewayExpenses = Account::create([
                    'name' => $request->name,
                    'parent_id' => $coaPaymentGateway->id,
                    'company_id' => Auth::user()->company->id,
                    'branch_id' => Auth::user()->branch_id,
                    'root_id' => $coaRootIdExpenses->id,
                    'code' => '5111',
                    'account_type' => 'expense',
                    'report_type' => 'profit loss',
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
                $PaymentGatewayExpenses = $newPaymentGatewayExpenses->id;
            } else {
                $PaymentGatewayExpenses = $childCoaPaymentGateway->id;
            }


            $charge = Charge::create([
                'name' => $request->get('name'),
                'description' => $request->get('description'),
                'type' => $request->get('type'),
                'amount' => $request->get('amount'),
                'acc_fee_id' => $PaymentGatewayExpenses,
                'acc_bank_id' => $request->get('acc_bank_id'),
                'acc_fee_bank_id' => $newAccountBankFee->id,
                'company_id' => Auth::user()->company->id,
                'branch_id' => Auth::user()->branch->id,
                'charge_type' => $request->get('charge_type'),
                'paid_by' => $request->get('paid_by'),
                'is_auto_paid' => $request->has('is_auto_paid') ? 1 : 0,
                'has_url' => $request->has('has_url') ? 1 : 0,
                // 'auth_type' => $request->get('auth_type'),
                // 'base_url' => $request->get('base_url'),
                'api_key' => $request->get('api_key'),
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

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'paid_by' => 'required',
            'charge_type' => 'required',
            'amount' => 'required|numeric',
            'is_auto_paid' => 'nullable|boolean',
            'has_url' => 'nullable|boolean',
            // 'auth_type' => 'required|in:basic,oauth',
            // 'base_url'    => 'nullable|url',
            'api_key'     => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Fetch the charge to update
            $charge = Charge::findOrFail($id);

            // Update charge fields
            $charge->update([
                'name' => $request->get('name'),
                'amount' => $request->get('amount'),
                'paid_by' => $request->get('paid_by'),
                'description' => $request->get('description'),
                'charge_type' => $request->get('charge_type'),
                'is_auto_paid' => $request->has('is_auto_paid') ? 1 : 0,
                'has_url' => $request->has('has_url') ? 1 : 0,
                // 'auth_type' => $request->get('auth_type'),
                // 'base_url'    => $request->get('base_url'),
                // 'api_key'    => $request->get('api_key'),

                //'acc_bank_id' => $request->get('acc_bank_id'),
                // 'acc_fee_id' => $request->get('acc_fee_id'),
                // 'acc_fee_bank_id' => $request->get('acc_fee_bank_id'),
            ]);


            // // Fetch COA for Payment Gateway Fee (Expenses)  
            // $coaPaymentGateway = Account::where('name', 'Payment Gateway Charges')->first(); // Query if the account exists

            // // Check if child account exists for Payment Gateway Charges
            // $childCoaPaymentGateway = Account::where('parent_id', $coaPaymentGateway ? $coaPaymentGateway->id : null)
            //     ->where('name', 'like', '%' . $request->get('name') . '%')
            //     ->first(); 

            // // Create new account for Payment Gateway Fee if not found
            // if (!$childCoaPaymentGateway) {

            //     // Create new Payment Gateway Fee account
            //     $coaPaymentGateway = Account::create([
            //         'name' => $request->name,
            //         'parent_id' => $coaPaymentGateway->id,
            //         'company_id' => Auth::user()->company->id,
            //         'branch_id' => Auth::user()->branch_id,
            //         'account_type' => 'expense',
            //         'report_type' => 'profit loss',
            //         'level' => 4,
            //         'is_group' => 0,
            //         'disabled' => 0,
            //         'actual_balance' => 0.00,
            //         'budget_balance' => 0.00,
            //         'variance' => 0.00,
            //         'currency' => 'KWD',
            //     ]);
            // }

            // // Fetch COA for Payment Gateway (Assets)  
            // $coaPaymentGatewayBankAcc = Account::where('name', 'Payment Gateway')->first(); // Query if the account exists

            // // Check if child account exists for Payment Gateway Bank Account
            // $childCoaPaymentGatewayBankAcc = Account::where('parent_id', $coaPaymentGatewayBankAcc ? $coaPaymentGatewayBankAcc->id : null)
            //     ->where('name', 'like', '%' . $request->get('name') . '%')
            //     ->first(); 

            // // Create new account for Payment Gateway Bank if not found
            // if (!$childCoaPaymentGatewayBankAcc) {

            //     // Create new Payment Gateway Bank account
            //     $coaPaymentGatewayBankAcc = Account::create([
            //         'name' => $request->name,
            //         'parent_id' => $coaPaymentGatewayBankAcc,
            //         'company_id' => Auth::user()->company->id,
            //         'branch_id' => Auth::user()->branch_id,
            //         'account_type' => 'asset',
            //         'report_type' => 'balance sheet',
            //         'level' => 4,
            //         'is_group' => 0,
            //         'disabled' => 0,
            //         'actual_balance' => 0.00,
            //         'budget_balance' => 0.00,
            //         'variance' => 0.00,
            //         'currency' => 'KWD',
            //     ]);
            // }

            // Commit the transaction
            DB::commit();

            return redirect()->route('charges.index')->with('success', 'Charges updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }


    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $charges = Charge::findOrFail($id);
            $charges->delete();

            DB::commit();

            return redirect()->route('charges.index')->with('success', 'Gateway charge successfully deleted!');
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function editMethodForm($id)
    {
        $method = PaymentMethod::findOrFail($id);

        $paidByChild = $this->getEnumValues('payment_methods', 'paid_by');
        $chargeTypeChild = $this->getEnumValues('payment_methods', 'charge_type');

        return view('edit_method', compact('method', 'paidByChild', 'chargeTypeChild'));
    }

    public function getEnumValues($table, $column)
    {
        $type = DB::select(DB::raw("SHOW COLUMNS FROM {$table} WHERE Field = '{$column}'"))[0]->Type;

        preg_match('/enum\((.*)\)$/', $type, $matches);
        $enum = [];

        foreach (explode(',', $matches[1]) as $value) {
            $enum[] = trim($value, "'");
        }

        return $enum;
    }

    public function updateCredentials(Request $request, $id)
    {
        $request->validate([
            'api_key'     => 'required|string',
        ]);

        $charge = Charge::findOrFail($id);

        if (!$charge) {
            return redirect()->route('charges.index')->with('error', 'Charge not found.');
        }

        try{
            $charge->update([
                'api_key' => $request->get('api_key'),
            ]);
        } catch (Exception $e) {

            Log::error('Failed to update charge credentials', ['error' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'Something went wrong while updating credentials.');
        }

        if($charge->api_key != null && $charge->is_active == false){
            $charge->is_active = true;
            $charge->save();

            return redirect()->route('charges.index')->with('success', 'Gateway is now active and credentials updated.');
        }

        return redirect()->route('charges.index')->with('success', 'Gateway credentials updated.');
    }
}
