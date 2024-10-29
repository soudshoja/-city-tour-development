<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function index()
    {
        return view('charges.index');
    }

    public function create()
    {
        return view('charges.create');
    }
}
