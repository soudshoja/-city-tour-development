<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierCredentialRequest;
use App\Models\SupplierCredential;
use Exception;
use Illuminate\Http\Request;

class SupplierCredentialController extends Controller
{
    public function store(SupplierCredentialRequest $request){
        $request->validated();

        if(config('app.env') == 'production'){
            $environment = 'production';
        } else {
            $environment = 'sandbox';
        }

        try {
            SupplierCredential::updateOrCreate([
                'supplier_id' => $request->supplier_id,
                'company_id' => $request->company_id,
                'environment' => $environment,
            ], [
                'type' => $request->type,
                'username' => $request->username,
                'password' => $request->password,
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'access_token' => $request->access_token,
                'refresh_token' => $request->refresh_token,
                'expires_at' => $request->expires_at,
            ]);
        } catch (Exception $e) {
            return redirect()->route('suppliers.index')->with('error', 'Failed to save supplier credential');
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier credential saved successfully');
    }
}
