<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use App\Models\Account;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\Role;
use App\Models\PasswordUpdateToken;
use App\Mail\PasswordUpdateCode;
use App\Models\JournalEntry;

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
        // if (!$company) {
        //     return redirect()->route('home')->withErrors(['message' => 'No company associated with this user.']);
        // }
        $phone = null;
        $email = $user->email;
        $commissions = collect();
        $totalCommission = 0;

        // Check if user has a role and role has an id
        if ($user->role && $user->role->id) {
            switch ($user->role->id) {
                case Role::COMPANY:
                    $profile = Company::where('user_id', $user->id)->first();
                    $phone = $profile?->phone;
                    break;

                case Role::BRANCH:
                    $profile = Branch::where('user_id', $user->id)->first();
                    $phone = $profile?->phone;
                    break;

                case Role::AGENT:
                    $profile = Agent::where('user_id', $user->id)->first();
                    $phone = $profile?->phone_number; // different column name
                    if ($profile) {
                        $data = $this->getAgentCommissions($profile->id);
                        $commissions = $data['commissions'];
                        $totalCommission = $data['totalCommission'];
                    }
                    break;

                default:
                    break;
            }
        }

        return view('profile.edit', [
            'user' => $user,
            'userPhone' => $phone,
            'userEmail' => $email,
            'commissions' => $commissions,
            'totalCommission' => $totalCommission,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {   
        Log::info('Updated bank ID:', ['acc_bank_id' => $request->input('acc_bank_id')]);

        $user = $request->user();
        $user->fill($request->validated());
        
        $accBankId = (int) $request->input('acc_bank_id');

        $accountExists = Account::where('id', $accBankId)
            ->where('company_id', $user->company_id)  // Make sure the bank account is related to the same company
            ->exists();

        // If the account does not exist, return an error message
        // if (!$accountExists) {
        //     return redirect()->route('profile.edit')
        //         ->withErrors(['acc_bank_id' => 'The selected bank account is invalid or does not belong to your company.']);
        // }

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

    public function requestPasswordUpdate(Request $request)
    {
        if ($request->input('current_password') !== '__retry__') {
            $request->validate([
                'current_password' => ['required', 'current_password'],
            ]);
        }


        $user = $request->user();

        // Generate a 6-digit random verification code
        $code = random_int(100000, 999999);

        // Create or update the verification code and expiry
        PasswordUpdateToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code' => $code,
                'expires_at' => now()->addMinutes(10),
            ]
        );

        // Send the verification code to the user's email
        Mail::to($user->email)->send(new PasswordUpdateCode($code));

        return redirect()
            ->route('profile.password.confirm-code')
            ->with('status', 'code-sent');
    }

    /**
     * Verify the submitted code.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        // Check for valid, unexpired token matching the code
        $token = PasswordUpdateToken::where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('expires_at', '>=', now())
            ->first();

        if (! $token) {
            return back()->withErrors(['code' => 'Invalid or expired code.']);
        }

        // Mark session as verified to allow password update
        session(['password_update_verified' => true]);

        // Redirect to profile edit page with success status
        return redirect()->route('profile.edit', ['tab' => 'Security'])
            ->with('status', 'code-verified');
    }

    /**
     * Show the form to confirm the verification code.
     */
    public function showConfirmCodeForm()
    {
        // Ensure a valid code request exists, otherwise redirect back with error
        $tokenExists = PasswordUpdateToken::where('user_id', auth()->id())
            ->where('expires_at', '>=', now())
            ->exists();

        if (! $tokenExists) {
            return redirect()->route('profile.edit', ['tab' => 'Security'])
                ->withErrors(['code' => 'Please request a verification code first.']);
        }

        return view('profile.password.confirm-password-code');
    }

    /**
     * Show the form to update the password.
     */
    public function showPasswordForm()
    {
        if (! session('password_update_verified')) {
            return redirect()->route('profile.password.confirm-code');
        }

        return view('profile.password.update-password-form');
    }

    /**
     * Update the password in the database.
     */
    public function updatePassword(Request $request)
    {
        if (! session('password_update_verified')) {
            return redirect()->route('profile.password.confirm-code');
        }

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        Log::info("Updating password for user ID: {$user->id}");

        $user->password = Hash::make($request->password);
        $user->save();

        Log::info("Password updated for user ID: {$user->id}");

        // Clean up: remove token and clear session flag
        PasswordUpdateToken::where('user_id', $user->id)->delete();
        session()->forget('password_update_verified');

        return redirect()->route('profile.edit', ['tab' => 'Security'])
            ->with('status', 'password-updated');
    }

    /**
     * Get agent commission
     */
    private function getAgentCommissions($agentId)
    {
        $query = JournalEntry::with('invoice')
            ->leftJoin('invoice_details', function ($join) {
                $join->on('journal_entries.invoice_id', '=', 'invoice_details.invoice_id')
                     ->whereRaw('invoice_details.id = (
                        SELECT MIN(id) FROM invoice_details 
                        WHERE invoice_details.invoice_id = journal_entries.invoice_id
                     )');
            })
            ->join('invoices', 'journal_entries.invoice_id', '=', 'invoices.id')
            ->where('journal_entries.account_id', 43)
            ->where('invoices.agent_id', $agentId)
            ->select('journal_entries.*')
            ->orderBy('journal_entries.created_at', 'desc');
    
        $paginated = $query->paginate(5, ['*'], 'commission');
    
        $mapped = $paginated->getCollection()->map(function ($entry) {
            return [
                'credit' => $entry->credit,
                'entry_id' => $entry->id,
            ];
        });
    
        $paginated->setCollection($mapped);
    
        $totalCommission = JournalEntry::join('invoices', 'journal_entries.invoice_id', '=', 'invoices.id')
            ->where('journal_entries.account_id', 43)
            ->where('invoices.agent_id', $agentId)
            ->sum('journal_entries.credit');
    
        return [
            'commissions' => $paginated,
            'totalCommission' => $totalCommission,
        ];
    }
}
