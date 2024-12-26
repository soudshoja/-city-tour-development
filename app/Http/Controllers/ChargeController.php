<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Exception;

class ChargeController extends Controller
{
    public function index()
    {
        $charges = Charge::all();

        if (Auth::user()->role == 'company') {
            $totalCharges = Charge::where('company_id', Auth::user()->company_id)->sum('amount');
        } elseif (Auth::user()->role == 'branch') {
            $totalCharges = Charge::where('branch_id', Auth::user()->branch_id)->sum('amount');
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
        return view('charges.create');
    }

    public function edit($id)
    {
        $charge = Charge::find($id);

        return view('charges.edit', compact('charge'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'string|max:255',
            'type' => 'required'
        ]);

        try {
            $charge = Charge::findOrFail($id);
            $charge->update([
                'name' => $request->get('name'),
                'amount' => $request->get('amount'),
                'type' => $request->get('type'),
                'description' => $request->get('description')
            ]);


            // Redirect to the clients list with a success message
            return redirect()->route('charges.index')->with('success', 'Charges updated successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy()
    {
        return true;
    }
}
