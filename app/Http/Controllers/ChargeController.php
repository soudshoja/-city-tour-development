<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function index()
    {
        $charges = Charge::factory()->count(10)->create();
        return view('charges.index', compact('charges'));
    }

    public function create()
    {
        return view('charges.create');
    }

    public function edit()
    {
        return true;
    }

    public function destroy()
    {
        return true;
    }
}
