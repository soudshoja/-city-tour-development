<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodGroup;
use App\Models\PaymentMethodChose;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\DraftEmail;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        $user = Auth::user();

        if($user->role_id == Role::ADMIN){
            $companyId = $request->company_id ?? 1;
        } else if ($user->role_id == Role::COMPANY){
            $companyId = $user->company->id;

        } else if ($user->role_id == Role::BRANCH){
            $companyId = $user->branch->company_id;
        } else if ($user->role_id == Role::AGENT){
            $companyId = $user->agent->branch->company_id;
        } else if ($user->role_id == Role::ACCOUNTANT){
            $companyId = $user->accountant->branch->company_id;
        } else {
            abort(403, 'Unauthorized action.');
        }

        // Get all payment method groups with their payment methods
        $paymentMethodGroups = PaymentMethodGroup::with(['paymentMethods.company', 'paymentMethods.charge'])
            ->whereHas('paymentMethods')
            ->get();

        // Get existing choices for this company
        $choices = PaymentMethodChose::where('company_id', $companyId)->get();
        $selectedMethods = $choices->pluck('payment_method_id', 'payment_method_group_id')->toArray();
        $enabledGroups = $choices->pluck('is_enabled', 'payment_method_group_id')->toArray();
        $choiceIds = $choices->pluck('id', 'payment_method_group_id')->toArray();

        return view('charges.partial.choose_payment_method', compact(
            'paymentMethodGroups',
            'selectedMethods',
            'enabledGroups',
            'choiceIds',
            'companyId'
        ));
    }

    public function show($id)
    {
        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            return response()->json(['error' => 'Payment Method not found'], 404);
        }

        return response()->json([
            'id' => $paymentMethod->myfatoorah_id,
            'gateway' => $paymentMethod->type,
            'arabic_name' => $paymentMethod->arabic_name,
            'english_name' => $paymentMethod->english_name,
            'type' => $paymentMethod->type,
            'service_charge' => $paymentMethod->service_charge,
            'currency' => $paymentMethod->currency,
            'self_charge' => $paymentMethod->self_charge,
            'charge_type' => $paymentMethod->charge_type,
            'paid_by' => $paymentMethod->paid_by,
            'description' => $paymentMethod->description,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'service_charge' => 'required',
            'self_charge' => 'required',
            'charge_type' => 'required',
            'paid_by' => 'required',
            'description' => 'nullable',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $paymentMethod = PaymentMethod::findOrFail($id);

            $paymentMethod->update([
                'service_charge' => $request->get('service_charge'),
                'self_charge' => $request->get('self_charge'),
                'charge_type' => $request->get('charge_type'),
                'paid_by' => $request->get('paid_by'),
                'description' => $request->get('description'),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            DB::commit();

            return redirect()->route('charges.index')->with('success', 'Child Method charge successfully updated!');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $paymentMethod = PaymentMethod::findOrFail($id);
            $paymentMethod->delete();

            DB::commit();

            return redirect()->route('charges.index')->with('success', 'Child Method charge successfully deleted!');
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setGroup(Request $request)
    {
        Gate::authorize('managePaymentMethodGroup', PaymentMethod::class);

        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $companyId = $request->company_id;
        $saved = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->all() as $key => $value) {
                // Extract group ID from field name like "payment_method_group_1"
                if (preg_match('/^payment_method_group_(\d+)$/', $key, $matches)) {
                    $groupId = $matches[1];
                    $paymentMethodId = $value;

                    PaymentMethodChose::updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'payment_method_group_id' => $groupId,
                        ],
                        [
                            'payment_method_id' => $paymentMethodId,
                            'is_enabled' => true,
                        ]
                    );
                    $saved++;
                }
            }

            DB::commit();

            Log::info('Payment method groups set successfully', [
                'company_id' => $companyId,
                'groups_saved' => $saved,
            ]);

            return redirect()->back()->with('success', "Successfully saved $saved payment method(s)");
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error setting payment method groups', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to save payment methods: ' . $e->getMessage());
        }
    }

    public function toggleEnable(int $id)
    {
        Gate::authorize('managePaymentMethodGroup', PaymentMethod::class);

        $paymentMethodChose = PaymentMethodChose::findOrFail($id);
        try {
            $paymentMethodChose->is_enabled = !$paymentMethodChose->is_enabled;
            $paymentMethodChose->save();
        } catch (Exception $e) {
            Log::error('Error toggling payment method choice', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to toggle payment method choice'], 500);
        }

        Log::info('Payment method choice toggled successfully', [
            'payment_method_chose_id' => $paymentMethodChose->id,
            'is_enabled' => $paymentMethodChose->is_enabled,
        ]);

        return response()->json(['success' => 'Payment method choice updated successfully', 'is_enabled' => $paymentMethodChose->is_enabled]);
    }
}
