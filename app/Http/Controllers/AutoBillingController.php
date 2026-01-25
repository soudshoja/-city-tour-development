<?php

namespace App\Http\Controllers;

use App\Models\{AutoBilling, Company, Client, Agent, Role, Charge, PaymentMethod};
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AutoBillingController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!in_array($user->role_id, [Role::COMPANY, Role::ADMIN])) {
            return redirect()->back()->with('error', 'Not Authorized');
        }

        if ($user->role_id == Role::ADMIN && !$companyId) {
            return view('auto-billing.index', [
                'company' => null,
                'rules' => collect(),
                'clients' => collect(),
                'agents' => collect(),
                'companyTimezone' => 'Asia/Kuala_Lumpur',
                'paymentGateways' => collect(),
                'paymentMethods' => collect(),
                'noCompanySelected' => true,
            ]);
        }

        $company = Company::with('nationality', 'branches')->find($companyId);
        if (!$company) {
            return abort(404, 'Company not found.');
        }

        $countryName = strtolower($company->nationality->name ?? '');
        $companyTimezone = match (true) {
            str_contains($countryName, 'malaysia') => 'Asia/Kuala_Lumpur',
            str_contains($countryName, 'kuwait') => 'Asia/Kuwait',
            default => 'Asia/Kuala_Lumpur',
        };

        $branchIds = $company->branches->pluck('id')->toArray();
        $agents = Agent::whereIn('branch_id', $branchIds)->get();

        $usedClientIds = AutoBilling::where('company_id', $company->id)
            ->pluck('client_id')
            ->toArray();

        $clients = Client::where(function ($q) use ($company) {
            $q->where('company_id', $company->id)
                ->orWhereHas('agent.branch', function ($q2) use ($company) {
                    $q2->where('company_id', $company->id);
                });
        })
            ->whereNotIn('id', $usedClientIds)
            ->get();

        $paymentGateways = Charge::where('is_active', true)
            ->where('can_generate_link', true)
            ->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        $rules = AutoBilling::where('company_id', $company->id)->get();

        return view('auto-billing.index', compact(
            'company',
            'rules',
            'clients',
            'agents',
            'companyTimezone',
            'paymentGateways',
            'paymentMethods'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return back()->withErrors(['company' => 'No company selected.']);
        }

        $company = Company::with('nationality')->find($companyId);

        if (!$company) {
            return abort(404, 'Company not found.');
        }

        $countryName = strtolower($company->nationality->name ?? '');
        $timezone = match (true) {
            str_contains($countryName, 'malaysia') => 'Asia/Kuala_Lumpur',
            str_contains($countryName, 'kuwait') => 'Asia/Kuwait',
            default => 'Asia/Kuala_Lumpur',
        };

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'add_amount' => 'numeric|min:0',
            'gateway_id' => 'nullable|exists:charges,id',
            'method_id' => 'nullable|exists:payment_methods,id',
            'invoice_time_company' => 'required',
            'auto_send_whatsapp' => 'boolean',
        ]);

        if (empty($request->created_by) && empty($request->issued_by) && empty($request->agent_id)) {
            return back()->withErrors([
                'created_by' => 'At least one of Created By, Issued By, or Agent is required.',
            ])->withInput();
        }

        $companyTime = Carbon::createFromFormat('H:i', $request->invoice_time_company, $timezone);
        $systemTime = $companyTime->copy()->setTimezone('Asia/Kuala_Lumpur');

        AutoBilling::create([
            'company_id' => $companyId,
            'created_by' => $request->input('created_by'),
            'agent_id' => $request->input('agent_id'),
            'issued_by' => $request->input('issued_by'),
            'client_id' => $request->client_id,
            'add_amount' => $request->add_amount ?? 1,
            'gateway_id' => $request->gateway_id,
            'method_id' => $request->method_id,
            'invoice_time_company' => $companyTime->format('H:i:s'),
            'invoice_time_system' => $systemTime->format('H:i:s'),
            'timezone' => $timezone,
            'auto_send_whatsapp' => $request->boolean('auto_send_whatsapp', false),
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Auto Billing Rule created successfully.');
    }

    public function update(Request $request, $id)
    {
        $rule = AutoBilling::findOrFail($id);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($rule->company_id != $companyId) {
            return abort(403, 'Unauthorized action.');
        }

        $company = Company::with('nationality')->find($companyId);

        if (!$company) {
            return abort(404, 'Company not found.');
        }

        $countryName = strtolower($company->nationality->name ?? '');
        $timezone = match (true) {
            str_contains($countryName, 'malaysia') => 'Asia/Kuala_Lumpur',
            str_contains($countryName, 'kuwait') => 'Asia/Kuwait',
            default => 'Asia/Kuala_Lumpur',
        };

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'add_amount' => 'numeric|min:0',
            'gateway_id' => 'nullable|exists:charges,id',
            'method_id' => 'nullable|exists:payment_methods,id',
            'invoice_time_company' => 'required',
            'auto_send_whatsapp' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $companyTime = Carbon::parse($request->invoice_time_company, $timezone);
        $systemTime = $companyTime->copy()->setTimezone('Asia/Kuala_Lumpur');

        $rule->update([
            'created_by' => $request->input('created_by'),
            'agent_id' => $request->input('agent_id'),
            'issued_by' => $request->input('issued_by'),
            'client_id' => $request->client_id,
            'add_amount' => $request->add_amount ?? 1,
            'gateway_id' => $request->gateway_id,
            'method_id' => $request->method_id,
            'invoice_time_company' => $companyTime->format('H:i:s'),
            'invoice_time_system' => $systemTime->format('H:i:s'),
            'timezone' => $timezone,
            'auto_send_whatsapp' => $request->boolean('auto_send_whatsapp', false),
            'is_active' => $request->has('is_active'),
        ]);

        return back()->with('success', 'Auto Billing Rule updated successfully.');
    }

    public function destroy(AutoBilling $rule)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($rule->company_id != $companyId) {
            return abort(403, 'Unauthorized action.');
        }

        $rule->delete();
        return back()->with('success', 'Rule deleted.');
    }
}
