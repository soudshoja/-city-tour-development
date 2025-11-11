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
use App\Jobs\SyncGatewayMethods;
use Illuminate\Support\Facades\Gate;

class ChargeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Charge::class);

        $companyId = null;

        if(Auth::user()->role->id == Role::ADMIN){
            $companyId = $request->company_id ?? 1;
            $totalCharges = Charge::where('company_id', $companyId)->count();
            $charges = Charge::where('company_id', $companyId)->get();
        } else if (Auth::user()->role->id == Role::COMPANY) {
            $companyId = Auth::user()->company->id;
            $totalCharges = Charge::where('company_id', $companyId)->count();
            $charges = Charge::where('company_id', $companyId)->get();
        } elseif (Auth::user()->role->id == Role::BRANCH) {
            $companyId = Auth::user()->branch->company_id;
            $totalCharges = Charge::where('company_id', $companyId)->count();
            $charges = Charge::where('company_id', $companyId)->get();
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $companyId = Auth::user()->accountant->branch->company_id;
            $totalCharges = Charge::where('company_id', $companyId)->count();
            $charges = Charge::where('company_id', $companyId)->get();
        } else {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view charges.');
            // $totalCharges = 0;
            // $charges = collect();
        }

        if($companyId === null){
            Log::warning('Company ID is null for user trying to access charges', [
                'user_id' => Auth::user()->id,
                'role_id' => Auth::user()->role->id,
            ]);

            return redirect()->route('dashboard')->with('error', 'You do not have permission to view charges.');
        }

        // $inactiveCharges = [];

        // foreach($charges as $charge){
        //     if($charge->api_key == null){
        //         $charge->is_active = false;
        //         $charge->save();
        //         $inactiveCharges[] = $charge->name;
        //     }
        // }

        return view('charges.index', compact(
            'charges',
            'totalCharges',
            'companyId'
        ));
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
        
        return view('charges.create', compact('coaPaymentGateway', 'coaPaymentGatewayBankAcc', 'coaBankAccount'));
    }


    // Store new charge
    public function store(Request $request)
    {
        Gate::authorize('create', Charge::class);

        $systemGateways = ['Tap', 'MyFatoorah', 'UPayment', 'Hesabe'];
        if (in_array($request->name, $systemGateways)) {
            Gate::authorize('createSystemGateway', Charge::class);
        }

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
            'self_charge' => 'nullable|numeric',
            'is_auto_paid' => 'nullable|boolean',
            'has_url' => 'nullable|boolean',
            'can_charge_invoice' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'can_generate_link' => 'nullable|boolean',
            // 'auth_type' => 'required|in:basic,oauth',
            // 'base_url' => 'nullable|url',
            'api_key' => 'required|string',
        ]);

        // Fetch COA for Payment Gateway
        $coaRootIdAssets = Account::where('name', 'Assets')->first();
        $coaRootIdExpenses = Account::where('name', 'Expenses')->first();


        try {
            DB::beginTransaction();

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
                'self_charge' => $request->get('self_charge'),
                'acc_fee_id' => $PaymentGatewayExpenses,
                'acc_bank_id' => $request->get('acc_bank_id'),
                'acc_fee_bank_id' => $newAccountBankFee->id,
                'company_id' => Auth::user()->company->id,
                'branch_id' => Auth::user()->branch->id,
                'charge_type' => $request->get('charge_type'),
                'paid_by' => $request->get('paid_by'),
                'is_auto_paid' => $request->has('is_auto_paid') ? 1 : 0,
                'has_url' => $request->has('has_url') ? 1 : 0,
                'can_charge_invoice' => $request->has('can_charge_invoice') ? 1 : 0,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                'can_generate_link' => $request->has('can_generate_link') ? $request->boolean('can_generate_link') : true,
                'api_key' => $request->get('api_key'),
                'is_system_default' => false,
                'can_be_deleted' => true,
                'enabled_by' => Auth::user()->role->id == Role::ADMIN ? 'admin' : 'company',
            ]);

            // Commit the transaction
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        try {
            SyncGatewayMethods::dispatchSync(
                companyId: Auth::user()->company->id,
                gatewayName: $request->get('name')
            );
        } catch (\Throwable $e) {
            Log::warning('Gateway sync failed (non-blocking)', [
                'company_id' => Auth::user()->company->id,
                'gateway' => $request->get('name'),
                'error' => $e->getMessage(),
            ]);
        }        

        return redirect()->route('charges.index')->with('success', 'Charge created successfully!');
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
        $charge = Charge::findOrFail($id);
        
        Gate::authorize('update', $charge);
        
        if (Gate::allows('updateAll', $charge)) {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
                'paid_by' => 'required',
                'charge_type' => 'required',
                'amount' => 'required|numeric',
                'extra_charge' => 'nullable|numeric',
                'self_charge' => 'nullable|numeric',
                'api_key'     => 'nullable|string',
            ]);

            try {
                DB::beginTransaction();

                $charge->update([
                    'name' => $request->get('name'),
                    'amount' => $request->get('amount'),
                    'extra_charge' => $request->get('extra_charge') ?? 0,
                    'self_charge' => $request->get('self_charge'),
                    'paid_by' => $request->get('paid_by'),
                    'description' => $request->get('description'),
                    'charge_type' => $request->get('charge_type'),
                ]);

                DB::commit();

                return redirect()->route('charges.index')->with('success', 'Charges updated successfully!');
            } catch (Exception $e) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        } elseif (Gate::allows('updateLimited', $charge)) {
            $request->validate([
                'self_charge' => 'nullable|numeric',
                'extra_charge' => 'nullable|numeric',
                'description' => 'nullable|string|max:255',
            ]);

            try {
                DB::beginTransaction();

                $charge->update([
                    'self_charge' => $request->get('self_charge'),
                    'extra_charge' => $request->get('extra_charge') ?? 0,
                    'description' => $request->get('description'),
                ]);

                DB::commit();

                return redirect()->route('charges.index')->with('success', 'Gateway charges updated successfully!');
            } catch (Exception $e) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return redirect()->route('charges.index')->with('error', 'You do not have permission to update this gateway.');
    }

    public function destroy($id)
    {
        try {
            $charge = Charge::findOrFail($id);

            Gate::authorize('delete', $charge);

            DB::beginTransaction();

            $charge->delete();

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
        $charge = Charge::findOrFail($id);

        if (!$charge) {
            return redirect()->route('charges.index')->with('error', 'Charge not found.');
        }

        if (Gate::allows('updateCredentials', $charge)) {
            $request->validate([
                'api_key'     => 'required|string',
                'is_auto_paid' => 'nullable|boolean',
                'has_url' => 'nullable|boolean',
                'can_charge_invoice' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
                'can_generate_link' => 'nullable|boolean',
            ]);

            try{
                $charge->update([
                    'api_key' => $request->get('api_key'),
                    'is_auto_paid' => $request->has('is_auto_paid') ? 1 : 0,
                    'has_url' => $request->has('has_url') ? 1 : 0,
                    'can_charge_invoice' => $request->has('can_charge_invoice') ? 1 : 0,
                    'is_active' => $request->has('is_active') ? 1 : 0,
                    'can_generate_link' => $request->has('can_generate_link') ? 1 : 0,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to update charge credentials', ['error' => $e->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'Something went wrong while updating credentials.');
            }

            return redirect()->route('charges.index')->with('success', 'Gateway credentials updated.');
        } elseif (Gate::allows('toggleActive', $charge)) {
            $request->validate([
                'is_active' => 'nullable|boolean',
            ]);

            try{
                $charge->update([
                    'is_active' => $request->has('is_active') ? 1 : 0,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to update charge status', ['error' => $e->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'Something went wrong while updating gateway status.');
            }

            return redirect()->route('charges.index')->with('success', 'Gateway status updated.');
        }

        return redirect()->route('charges.index')->with('error', 'You do not have permission to update this gateway.');
    }
}
