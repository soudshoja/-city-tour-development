<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Company;
use Illuminate\Http\Request;
use Exception;

class ChargeController extends Controller
{
    public function index()
    {
        $charges = Charge::all();
        return view('charges.index', compact('charges'));
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
