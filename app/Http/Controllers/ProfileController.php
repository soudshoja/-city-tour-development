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
use Illuminate\Support\Facades\Storage;
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
use App\Models\Invoice;
use App\Models\Task;
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
        $viewType = $request->input('view_type', 'invoice'); // Default to invoice-based view

        $phone = null;
        $email = $user->email;
        $commissionData['commissions'] = collect();
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

                    $totalProfit = number_format($stored->total_profit, 2);
                   
                } else {
                    $summary = app(AgentController::class)->calculateMonthlySummary($profile, $month);

                    if (in_array($typeId, [2, 3, 4])) {
                        $totalCommission = number_format($summary['commission'], 2);
                    }

                    $totalProfit = number_format($summary['profit'], 2);
                }

                $commissionData = $this->getAgentCommissions($profile->id, $month, $viewType);
                
                // Use totals from commission data (for current page/filtered data)
                $totalProfit = number_format($commissionData['totalProfit'], 2);
                if (in_array($typeId, [2, 3, 4])) {
                    $totalCommission = number_format($commissionData['totalCommission'], 2);
                }

                break;

            default:
                $company = null;
                break;
        }

        $companyLogo = $company?->logo ? asset('storage/' . $company->logo) : asset('images/UserPic.svg');

        return view('profile.edit', [
            'user' => $user,
            'userPhone' => $phone,
            'userEmail' => $email,
            'commissions' => $commissionData['commissions'],
            'totalCommission' => $totalCommission,
            'totalProfit' => $totalProfit,
            'month' => $month,
            'companyLogo' => $companyLogo,
            'viewType' => $viewType,
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

        // Get the correct company based on user role
        switch ($user->role_id) {
            case Role::COMPANY:
                $company = $user->company;
                break;
            case Role::BRANCH:
                $company = $user->branch->company;
                break;
            case Role::AGENT:
                $company = $user->agent->branch->company;
                break;
            default:
                $company = null;
                break;
        }

        // Handle logo upload only if company exists
        if ($company) {
            // ✅ Prefer processed base64 logo if available
            if ($request->filled('logo_processed')) {
                $logoData = $request->input('logo_processed');

                // Strip base64 prefix
                $logoData = preg_replace('#^data:image/\w+;base64,#i', '', $logoData);
                $logoData = str_replace(' ', '+', $logoData);

                // Save into public/storage/logos/
                $fileName = 'logos/' . uniqid() . '.png';
                Storage::disk('public_storage')->put($fileName, base64_decode($logoData));

                // Save relative path e.g. logos/filename.png
                $company->logo = $fileName;
            } elseif ($request->hasFile('logo')) {
                // Fallback: save original file if no processed image
                $path = $request->file('logo')->store('logos', 'public_storage');
                $company->logo = $path;
            }

            $company->save();
        }

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
     * Get the commission account ID by name 'Commission (Agents)'
     */
    private function getCommissionAccountId(Agent $agent)
    {
        static $commissionAccountId = null;
        
        $companyId = $agent->branch->company_id;
        
        if ($commissionAccountId === null) {
            $account = Account::where('name', 'Commissions (Agents)')
                ->where('company_id', $companyId)
                ->first();
            $commissionAccountId = $account ? $account->id : 43; // fallback to 43 if not found
        }
        
        return $commissionAccountId;
    }

    /**
     * Get agent commission data based on journal entries
     */
    private function getAgentCommissions($agentId, $month, $viewType = 'task')
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $agent = Agent::findOrFail($agentId);
        $commissionAccountId = $this->getCommissionAccountId($agent);

        if ($viewType === 'invoice') {
            return $this->getCommissionsByInvoice($agent, $start, $end, $commissionAccountId);
        } else {
            return $this->getCommissionsByTask($agent, $start, $end, $commissionAccountId);
        }
    }

    /**
     * Get commissions grouped by invoice
     */
    private function getCommissionsByInvoice($agent, $start, $end, $commissionAccountId)
    {
        // For salary-only agents (type 1), show all invoices in date range
        // For other agents, only show invoices with commission journal entries
        $query = Invoice::with(['invoiceDetails.task', 'invoiceDetails.JournalEntrys' => function($q) use ($commissionAccountId) {
                $q->where('account_id', $commissionAccountId);
            }])
            ->where('agent_id', $agent->id)
            ->whereBetween('invoice_date', [$start, $end]);
            
        // Only filter by commission journal entries for non-salary agents
        if ($agent->type_id != 1) {
            $query->whereHas('invoiceDetails.JournalEntrys', function($q) use ($commissionAccountId, $start, $end) {
                $q->where('account_id', $commissionAccountId)
                  ->whereBetween('transaction_date', [$start, $end]);
            });
        }
        
        $query->orderBy('invoice_date', 'asc');

        // Calculate totals from ALL invoices in the month BEFORE pagination
        $allInvoices = $query->get();
        $totalProfit = $allInvoices->sum(function($invoice) {
            return $invoice->invoiceDetails->sum('markup_price') + ($invoice->invoice_charge ?? 0);
        });
        
        // Calculate total commission from all journal entries in the month
        $totalCommission = 0;
        if (in_array($agent->type_id, [2, 3, 4])) {
            foreach ($allInvoices as $invoice) {
                foreach ($invoice->invoiceDetails as $detail) {
                    $commissionEntries = $detail->JournalEntrys()
                        ->where('account_id', $commissionAccountId)
                        ->get();
                    $totalCommission += $commissionEntries->sum('credit') - $commissionEntries->sum('debit');
                }
            }
        }

        $paginated = $query->paginate(5, ['*'], 'commission');

        $mapped = $paginated->getCollection()->map(function ($invoice) use ($agent, $commissionAccountId) {
            // Calculate total profit for this invoice: markup_price + invoice_charge
            $totalProfit = $invoice->invoiceDetails->sum('markup_price') + ($invoice->invoice_charge ?? 0);
            
            // Calculate net commission from journal entries linked to invoice details (credits - debits)
            $totalCommission = 0;
            if (in_array($agent->type_id, [2, 3, 4])) {
                foreach ($invoice->invoiceDetails as $detail) {
                    $commissionEntries = $detail->JournalEntrys()
                        ->where('account_id', $commissionAccountId)
                        ->get();
                    $totalCommission += $commissionEntries->sum('credit') - $commissionEntries->sum('debit');
                }
            }

            return [
                'type' => 'invoice',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'company_id' => $invoice->agent->branch->company_id,
                'task_count' => $invoice->invoiceDetails->count(),
                'total_profit' => $totalProfit,
                'total_commission' => $totalCommission,
                'tasks' => $invoice->invoiceDetails->map(function($detail) {
                    return [
                        'task_reference' => $detail->task->reference ?? 'N/A',
                        'passenger_name' => $detail->task->passenger_name ?? 'N/A',
                        'task_price' => $detail->task_price,
                        'markup_price' => $detail->markup_price,
                    ];
                }),
            ];
        });

        $paginated->setCollection($mapped);
        
        return [
            'commissions' => $paginated,
            'totalProfit' => $totalProfit,
            'totalCommission' => $totalCommission,
        ];
    }

    /**
     * Get commissions grouped by task
     */
    private function getCommissionsByTask($agent, $start, $end, $commissionAccountId)
    {
        // Get tasks that have commission journal entries for this agent (linked via invoice_detail_id)
        $query = JournalEntry::with(['invoice.invoiceDetails.task', 'invoiceDetail.task'])
            ->join('invoice_details', 'journal_entries.invoice_detail_id', '=', 'invoice_details.id')
            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
            ->where('journal_entries.account_id', $commissionAccountId)
            ->where('invoices.agent_id', $agent->id)
            ->whereBetween('journal_entries.transaction_date', [$start, $end])
            ->select('journal_entries.*')
            ->orderBy('journal_entries.transaction_date', 'asc');

        // Calculate totals from ALL tasks in the month BEFORE pagination
        $allEntries = $query->get();
        $totalProfit = 0;
        $totalCommission = 0;
        
        foreach ($allEntries as $entry) {
            $invoice = $entry->invoice;
            $invoiceDetail = $entry->invoiceDetail;
            
            // Calculate profit: markup + proportional invoice charge
            $markupProfit = $invoiceDetail?->markup_price ?? 0;
            
            // Add proportional invoice charge
            $totalTaskPrice = $invoice->invoiceDetails->sum('task_price');
            $taskPrice = $invoiceDetail?->task_price ?? 0;
            
            if ($totalTaskPrice > 0 && $invoice->invoice_charge > 0) {
                $proportionalCharge = ($taskPrice / $totalTaskPrice) * $invoice->invoice_charge;
                $markupProfit += $proportionalCharge;
            }
            
            $totalProfit += $markupProfit;
            
            // Calculate commission for applicable agent types
            if (in_array($agent->type_id, [2, 3, 4])) {
                $commissionEntries = $invoiceDetail?->JournalEntrys()
                    ->where('account_id', $commissionAccountId)
                    ->get();
                
                if ($commissionEntries) {
                    $netCommission = $commissionEntries->sum('credit') - $commissionEntries->sum('debit');
                    $totalCommission += $netCommission;
                }
            }
        }

        $paginated = $query->paginate(5, ['*'], 'commission');

        $mapped = $paginated->getCollection()->map(function ($entry) use ($agent, $commissionAccountId) {
            $invoice = $entry->invoice;
            $task = $entry->invoiceDetail?->task;
            
            if (!$task) {
                // Fallback: get first task from invoice
                $task = $invoice->invoiceDetails->first()?->task;
            }

            // Get all commission entries for this specific invoice detail to calculate net commission
            $commissionEntries = JournalEntry::where('invoice_detail_id', $entry->invoice_detail_id)
                ->where('account_id', $commissionAccountId)
                ->get();
            
            $netCommission = $commissionEntries->sum('credit') - $commissionEntries->sum('debit');
            
            // Get profit for this specific task: markup_price + proportional invoice_charge
            $taskProfit = $entry->invoiceDetail?->markup_price ?? 0;
            
            // Add proportional invoice charge based on task price vs total invoice amount
            $invoice = $entry->invoice;
            $totalTaskPrice = $invoice->invoiceDetails->sum('task_price');
            $taskPrice = $entry->invoiceDetail?->task_price ?? 0;
            
            if ($totalTaskPrice > 0 && $invoice->invoice_charge > 0) {
                $proportionalCharge = ($taskPrice / $totalTaskPrice) * $invoice->invoice_charge;
                $taskProfit += $proportionalCharge;
            }

            return [
                'type' => 'task',
                'task_reference' => $task?->reference ?? 'N/A',
                'passenger_name' => $task?->passenger_name ?? 'N/A',
                'transaction_date' => $entry->transaction_date,
                'task_profit' => $taskProfit,
                'net_commission' => $netCommission,
                'invoice' => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'date' => $invoice->invoice_date,
                    'company_id' => $invoice->agent->branch->company_id,
                    'total_profit' => $invoice->invoiceDetails->sum('markup_price') + ($invoice->invoice_charge ?? 0),
                    'payment_type' => $this->getPaymentTypeLabel($invoice),
                ],
                'task_details' => $task ? [
                    'client_name' => $task->client_name,
                    'supplier_pay_date' => $task->supplier_pay_date,
                    'flight_details' => $task->flightDetails,
                    'hotel_details' => $task->hotelDetails,
                ] : null,
            ];
        });

        $paginated->setCollection($mapped);
        
        return [
            'commissions' => $paginated,
            'totalProfit' => $totalProfit,
            'totalCommission' => $totalCommission,
        ];
    }

    /**
     * Get payment type label for invoice
     */
    private function getPaymentTypeLabel($invoice)
    {
        if ($invoice->is_client_credit == 1) {
            return 'Client Credit';
        }
        
        return match($invoice->payment_type) {
            'full' => 'Full Payment',
            'partial' => 'Partial Payment',
            'split' => 'Split Payment',
            default => 'Unknown'
        };
    }
}
