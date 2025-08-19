<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PaymentMethodController extends Controller
{
    public function index()
    {
        // Optional: can be used to list methods
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
}
