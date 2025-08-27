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
use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\AgentMonthlyCommissions;
use App\Models\Role;
use App\Models\PasswordUpdateToken;
use App\Mail\PasswordUpdateCode;
use App\Models\JournalEntry;
use App\Models\InvoiceDetail;
use Carbon\Carbon;
use App\Http\Controllers\AgentController;
use Intervention\Image\Drivers\Gd\Modifiers\DrawEllipseModifier;
use Intervention\Image\Facades\Image;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request)
    {
        $user = $request->user();
        $month = $request->input('month') ? Carbon::parse($request->input('month'))->startOfMonth() : now()->startOfMonth();

        $phone = null;
        $email = $user->email;
        $commissions = collect();
        $totalCommission = 0;
        $totalProfit = 0;


        switch ($user->role->id) {
            case Role::COMPANY:
                $profile = Company::where('user_id', $user->id)->first();
                $phone = $profile?->phone;
                $company = $user->company;
                break;

            case Role::BRANCH:
                $profile = Branch::where('user_id', $user->id)->first();
                $phone = $profile?->phone;
                $company = $user->branch->company;
                break;

            case Role::AGENT:
                $profile = Agent::where('user_id', $user->id)->first();
                $phone = $profile?->phone_number; // different column name
                $typeId = $profile?->type_id;
                $company = $user->agent->branch->company;

                $stored = AgentMonthlyCommissions::where('agent_id', $profile->id)
                    ->where('month', $month->month)
                    ->where('year', $month->year)
                    ->first();

                if ($stored) {
                    if (in_array($typeId, [2, 3, 4])) {
                        $totalCommission = number_format($stored->total_commission, 2);
                    }
                    if (in_array($typeId, [1, 3, 4])) {
                        $totalProfit = number_format($stored->total_profit, 2);
                    }
                } else {
                    $summary = app(AgentController::class)->calculateMonthlySummary($profile, $month);

                    if (in_array($typeId, [2, 3, 4])) {
                        $totalCommission = number_format($summary['commission'], 2);
                    }
                    if (in_array($typeId, [1, 3, 4])) {
                        $totalProfit = number_format($summary['profit'], 2);
                    }
                }

                $commissionData = $this->getAgentCommissions($profile->id, $month);
                $commissions = $commissionData['commissions'];
                if ($typeId == 2) {
                    $totalCommission = number_format($commissionData['totalCommission'], 2);
                }
                break;

            default:
                break;
        }

        $companyLogo = $company?->logo ? asset('storage/' . $company->logo) : asset('images/UserPic.svg');

        return view('profile.edit', [
            'user' => $user,
            'userPhone' => $phone,
            'userEmail' => $email,
            'commissions' => $commissions,
            'totalCommission' => $totalCommission,
            'totalProfit' => $totalProfit,
            'month' => $month,
            'companyLogo' => $companyLogo,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {   
        $user = $request->user();
        $originalData = $user->toArray();
        
        // Update user basic information
        $user->fill($request->validated());
    
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Save user changes first
        $user->save();
        $request->validate([
            'logo' => [
                'nullable',
                'image',
                'dimensions:max_width=700,max_height=400'
            ],
            // ... other rules ...
        ]);

        $company = $user->company; // related company

        // ✅ Prefer processed base64 logo if available
        if ($request->filled('logo_processed')) {
            $logoData = $request->input('logo_processed');

            // Strip base64 prefix
            $logoData = preg_replace('#^data:image/\w+;base64,#i', '', $logoData);
            $logoData = str_replace(' ', '+', $logoData);

            // Save into public/storage/logos/
            $fileName = 'logos/' . uniqid() . '.png';
            \Storage::disk('public_storage')->put($fileName, base64_decode($logoData));

            // Save relative path e.g. logos/filename.png
            $company->logo = $fileName;
        } elseif ($request->hasFile('logo')) {
            // Fallback: save original file if no processed image
            $path = $request->file('logo')->store('logos', 'public_storage');
            $company->logo = $path;
        }

        $company->save();

        // Update related profile information based on user role
        if ($user->role_id){
            try {
                switch ($user->role_id) {
                    case Role::COMPANY:
                        $this->updateCompanyProfile($user, $request);
                        break;

                    case Role::BRANCH:
                        $this->updateBranchProfile($user, $request);
                        break;

                    case Role::AGENT:
                        $this->updateAgentProfile($user, $request);
                        break;

                    default:
                        break;
                }
            } catch (\Exception $e) {
                Log::error('Failed to update profile for user: ' . $user->id, [
                    'error' => $e->getMessage(),
                    'role_id' => $user->role->id
                ]);
                
                return redirect()->back()->with('error', 'Failed to update profile information.');
            }
        }
    
        return redirect()->back()->with('success', 'Profile Successfully Updated.');
    }

    /**
     * Update company profile information
     */
    private function updateCompanyProfile(User $user, ProfileUpdateRequest $request): void
    {
        $company = Company::where('user_id', $user->id)->first();
        
        if ($company) {
            $updateData = [];
            
            if ($request->has('name') && $request->input('name') !== $company->name) {
                $updateData['name'] = $request->input('name');
            }
            
            if ($request->has('email') && $request->input('email') !== $company->email) {
                $updateData['email'] = $request->input('email');
            }
            
            if ($request->has('phone') && $request->input('phone') !== $company->phone) {
                $updateData['phone'] = $request->input('phone');
            }
            
            if ($request->has('address') && $request->input('address') !== $company->address) {
                $updateData['address'] = $request->input('address');
            }
            
            if (!empty($updateData)) {
                $company->update($updateData);
                Log::info('Company profile updated', ['company_id' => $company->id, 'updates' => $updateData]);
            }
        }
    }

    /**
     * Update branch profile information
     */
    private function updateBranchProfile(User $user, ProfileUpdateRequest $request): void
    {
        $branch = Branch::where('user_id', $user->id)->first();
        
        if ($branch) {
            $updateData = [];
            
            if ($request->has('name') && $request->input('name') !== $branch->name) {
                $updateData['name'] = $request->input('name');
            }
            
            if ($request->has('email') && $request->input('email') !== $branch->email) {
                $updateData['email'] = $request->input('email');
            }
            
            if ($request->has('phone') && $request->input('phone') !== $branch->phone) {
                $updateData['phone'] = $request->input('phone');
            }
            
            if ($request->has('address') && $request->input('address') !== $branch->address) {
                $updateData['address'] = $request->input('address');
            }
            
            if (!empty($updateData)) {
                $branch->update($updateData);
                Log::info('Branch profile updated', ['branch_id' => $branch->id, 'updates' => $updateData]);
            }
        }
    }

    /**
     * Update agent profile information
     */
    private function updateAgentProfile(User $user, ProfileUpdateRequest $request): void
    {
        $agent = Agent::where('user_id', $user->id)->first();
        
        if ($agent) {
            $updateData = [];
            
            if ($request->has('name') && $request->input('name') !== $agent->name) {
                $updateData['name'] = $request->input('name');
            }
            
            if ($request->has('email') && $request->input('email') !== $agent->email) {
                $updateData['email'] = $request->input('email');
            }
            
            if ($request->has('phone') && $request->input('phone') !== $agent->phone_number) {
                $updateData['phone_number'] = $request->input('phone');
            }
            
            if (!empty($updateData)) {
                $agent->update($updateData);
                Log::info('Agent profile updated', ['agent_id' => $agent->id, 'updates' => $updateData]);
            }
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
    private function getAgentCommissions($agentId, $month)
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $agent = Agent::findOrFail($agentId);
    
        $query = JournalEntry::with('invoice.invoiceDetails')
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
            ->whereBetween('journal_entries.created_at', [$start, $end])
            ->select('journal_entries.*')
            ->orderBy('journal_entries.created_at', 'desc');
    
        $paginated = $query->paginate(5, ['*'], 'commission');
    
        $mapped = $paginated->getCollection()->map(function ($entry) use ($agent) {
            $commissionValue = $entry->credit;

            if ($agent->type_id == 3) {
                $markup = 0;
                $detail = $entry->invoiceDetail;

                if ($detail) {
                    if (isset($detail->markup_price)) {
                        $markup = $detail->markup_price;
                    }
                }
                $commissionValue = $markup * ($agent->commission ?? 0.15);
            }
            return [
                'credit' => $commissionValue,
                'entry_id' => $entry->id,
            ];
        });
    
        $paginated->setCollection($mapped);
    
        $totalCommission = JournalEntry::join('invoices', 'journal_entries.invoice_id', '=', 'invoices.id')
            ->where('journal_entries.account_id', 43)
            ->where('invoices.agent_id', $agentId)
            ->whereBetween('journal_entries.created_at', [$start, $end])
            ->sum('journal_entries.credit');
    
        return [
            'commissions' => $paginated,
            'totalCommission' => $totalCommission,
        ];
    }
}
