<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierCredentialRequest;
use App\Models\SupplierCredential;
use Illuminate\Http\Request;

class SupplierCredentialController extends Controller
{
    public function store(SupplierCredentialRequest $request){

        $request->validated();

        SupplierCredential::create($request->all());

        return redirect()->route('suppliers.index');
    }
}
