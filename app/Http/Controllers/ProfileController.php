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
use App\Models\BonusAgent;
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
        if ($request->input('filter_month') && $request->input('filter_year')) {
            $month = Carbon::createFromDate(
                $request->input('filter_year'),
                $request->input('filter_month'),
                1
            )->startOfMonth();
        } elseif ($request->input('month')) {
            $month = Carbon::parse($request->input('month'))->startOfMonth();
        } else {
            $month = now()->startOfMonth();
        }        
        $viewType = $request->input('view_type', 'invoice');

        $phone = null;
        $email = $user->email;
        $commissionData['commissions'] = collect();
        $totalCommission = 0;
        $totalProfit = 0;
        $totalLoss = 0;

        $filterBonus = now()->startOfMonth();
        $bonuses = collect();

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
                $phone = $profile?->phone_number;
                $typeId = $profile?->type_id;
                $company = $user->agent->branch->company;

                $stored = AgentMonthlyCommissions::where('agent_id', $profile->id)
                    ->where('month', $month->month)
                    ->where('year', $month->year)
                    ->first();

                if ($stored) {
                    if (in_array($typeId, [2, 3, 4])) {
                        $totalCommission = number_format($stored->total_commission, 3);
                    }
                    $totalProfit = number_format($stored->total_profit, 3);
                } else {
                    $summary = app(AgentController::class)->calculateMonthlySummary($profile, $month);
                    if (in_array($typeId, [2, 3, 4])) {
                        $totalCommission = number_format($summary['commission'], 3);
                    }
                    $totalProfit = number_format($summary['profit'], 3);
                }

                $commissionData = $this->getAgentCommissions($profile->id, $month, $viewType);

                $totalProfit = number_format($commissionData['totalProfit'], 3);
                if (in_array($typeId, [2, 3, 4])) {
                    $totalCommission = number_format($commissionData['totalCommission'], 3);
                }
                
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfmonth();

                $totalLoss = number_format(
                    JournalEntry::where('account_id', $profile->loss_account_id)
                        ->whereBetween('transaction_date', [$start, $end])
                        ->sum('debit'),
                    3
                );

                $filterBonusMonth = (int) request('filter_month', now()->month);
                $filterBonusYear  = (int) request('filter_year', now()->year);
                $filterBonus = Carbon::createFromDate($filterBonusYear, $filterBonusMonth, 1)->startOfMonth();

                $bonuses = BonusAgent::where('agent_id', $profile->id)
                ->with('transaction')
                ->orderByDesc('created_at')
                ->get();
                break;

            case Role::ADMIN:
                $companyId = getCompanyId($user);
                $company = $companyId ? Company::find($companyId) : null;
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
            'company' => $company,
            'viewType' => $viewType,
            'filteredBonuses' => $bonuses ?? collect(),
            'filterBonus' => $filterBonus,
            'totalLoss' => $totalLoss,
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
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'snapchat' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'url', 'max:255'],
            'whatsapp' => ['nullable', 'url', 'max:255'],
        ]);

        $companyid = getCompanyId($user);

        $company = Company::find($companyid);

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

            // Save social media links
            if ($request->filled('facebook')) $company->facebook = $request->input('facebook');
            if ($request->filled('instagram')) $company->instagram = $request->input('instagram');
            if ($request->filled('snapchat')) $company->snapchat = $request->input('snapchat');
            if ($request->filled('tiktok')) $company->tiktok = $request->input('tiktok');
            if ($request->filled('whatsapp')) $company->whatsapp = $request->input('whatsapp');

            $company->save();
        }

        // Update related profile information based on user role
        if ($user->role_id) {
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

    public function updateIataSettings(Request $request)
    {
        $request->validate([
            'iata_code' => ['nullable', 'digits:8'],
            'iata_client_id' => ['nullable', 'string'],
            'iata_client_secret' => ['nullable', 'string'],
        ]);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return back()->with('error', 'No company selected. Please select a company first.');
        }

        $company = Company::find($companyId);

        $company->update([
            'iata_code' => $request->iata_code,
            'iata_client_id' => $request->iata_client_id,
            'iata_client_secret' => $request->iata_client_secret,
        ]);

        return back()->with('success', 'IATA EasyPay settings updated successfully.');
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
        $query = Invoice::with(['invoiceDetails.task'])
            ->where('agent_id', $agent->id)
            ->whereBetween('invoice_date', [$start, $end])
            ->orderBy('invoice_date', 'asc');

        // Calculate totals from ALL invoices BEFORE pagination
        $allInvoices = $query->get();

        $totalProfit = $allInvoices->sum(fn($inv) => $inv->invoiceDetails->sum('profit'));
        $totalCommission = 0;
        if (in_array($agent->type_id, [2, 3, 4])) {
            $totalCommission = $allInvoices->sum(fn($inv) => $inv->invoiceDetails->sum('commission'));
        }

        $paginated = $query->paginate(5, ['*'], 'commission');

        // Get per-invoice loss from journal entries
        $invoiceLosses = JournalEntry::where('account_id', $agent->loss_account_id)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereNotNull('invoice_id')
            ->selectRaw('invoice_id, SUM(debit) as total_loss')
            ->groupBy('invoice_id')
            ->pluck('total_loss', 'invoice_id');

        $mapped = $paginated->getCollection()->map(function ($invoice) use ($agent, $invoiceLosses) {
            $invoiceProfit = $invoice->invoiceDetails->sum('profit');
            $invoiceCommission = in_array($agent->type_id, [2, 3, 4])
                ? $invoice->invoiceDetails->sum('commission')
                : 0;

            return [
                'type' => 'invoice',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'company_id' => $invoice->agent->branch->company_id,
                'task_count' => $invoice->invoiceDetails->count(),
                'total_profit' => $invoiceProfit,
                'total_loss' => $invoiceLosses[$invoice->id] ?? 0,
                'total_commission' => $invoiceCommission,
                'tasks' => $invoice->invoiceDetails->map(fn($detail) => [
                    'task_reference' => $detail->task->reference ?? 'N/A',
                    'passenger_name' => $detail->task->passenger_name ?? 'N/A',
                    'task_price' => $detail->task_price,
                    'markup_price' => $detail->markup_price,
                ]),
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
        $query = InvoiceDetail::with(['task', 'invoice.agent.branch'])
            ->whereHas('invoice', fn($q) => $q->where('agent_id', $agent->id)
                ->whereBetween('invoice_date', [$start, $end]))
            ->orderBy('id', 'asc');

        // Calculate totals from ALL tasks in the month BEFORE pagination
        $allDetails = $query->get();
        $totalProfit = $allDetails->sum('profit');
        $totalCommission = in_array($agent->type_id, [2, 3, 4]) ? $allDetails->sum('commission') : 0;

        // Get per-task loss from journal entries
        $taskLosses = JournalEntry::where('account_id', $agent->loss_account_id)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereNotNull('invoice_detail_id')
            ->pluck('debit', 'invoice_detail_id');

        $paginated = $query->paginate(5, ['*'], 'commission');

        $mapped = $paginated->getCollection()->map(function ($detail) use ($agent, $taskLosses) {
            $task = $detail->task;
            $invoice = $detail->invoice;

            return [
                'type' => 'task',
                'task_reference' => $task?->reference ?? 'N/A',
                'passenger_name' => $task?->passenger_name ?? 'N/A',
                'transaction_date' => $invoice->invoice_date,
                'task_profit' => $detail->profit ?? 0,
                'task_loss' => $taskLosses[$detail->id] ?? 0,
                'net_commission' => in_array($agent->type_id, [2, 3, 4]) ? ($detail->commission ?? 0) : 0,
                'invoice' => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'date' => $invoice->invoice_date,
                    'company_id' => $invoice->agent->branch->company_id,
                    'total_profit' => $invoice->invoiceDetails->sum('profit'),
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

        return match ($invoice->payment_type) {
            'full' => 'Full Payment',
            'partial' => 'Partial Payment',
            'split' => 'Split Payment',
            default => 'Unknown'
        };
    }
}
