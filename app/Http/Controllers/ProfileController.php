<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use App\Models\Account;
use App\Models\Company;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request)
    {
        $user = $request->user();

        // Fetch the company_id based on the authenticated user
        $company = Company::where('user_id', $user->id)->first();

        // If the user doesn't have a company, handle this scenario
        if (!$company) {
            return redirect()->route('home')->withErrors(['message' => 'No company associated with this user.']);
        }
        
        // Step 1: Find the ID(s) of the account(s) where the name is LIKE '%Bank Accounts%' based on company_id
        $parentAccounts = Account::where('company_id', $company->id)
            ->where('name', 'LIKE', '%Bank Accounts%')
            ->get(); 

        // Step 2: Use the retrieved parent IDs to get the child accounts
        $bankAccounts = Account::whereIn('parent_id', $parentAccounts->pluck('id'))
            ->where('company_id', $company->id)
            ->get(['id', 'name']); 

        //dd($bankAccounts->name);  

        // Return Blade view with the bank accounts and user data
        return view('profile.edit', [
            'user' => $user,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {   
        \Log::info('Updated bank ID:', ['acc_bank_id' => $request->input('acc_bank_id')]);

        $user = $request->user();
        $user->fill($request->validated());
        
        $accBankId = (int) $request->input('acc_bank_id');

        $accountExists = Account::where('id', $accBankId)
            ->where('company_id', $user->company_id)  // Make sure the bank account is related to the same company
            ->exists();

        // If the account does not exist, return an error message
        if (!$accountExists) {
            return redirect()->route('profile.edit')
                ->withErrors(['acc_bank_id' => 'The selected bank account is invalid or does not belong to your company.']);
        }

        if ($request->has('acc_bank_id')) {
            $user->acc_bank_id = $request->input('acc_bank_id');
        }
    
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
    
        $user->save();
    
        if ($request->user()->save()) {
            return redirect()->back()->with('success', 'Profile Successfully Updated.');
        } else {
            return redirect()->back()->with('error', 'Unable to update profile.');
        }
    }
    

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
