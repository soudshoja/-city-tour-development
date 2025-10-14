<?php

namespace App\Http\Controllers;

use App\Models\{AutoBilling, Company, Client, Agent, Role};
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AutoBillingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id != Role::COMPANY) {
            return abort(403, 'Unauthorized action.');
        }

        $company = $user->company;
        if (! $company) {
            return abort(403, 'No company profile linked to this account.');
        }

        $branchIds = $company->branches->pluck('id')->toArray();
        $agents = Agent::whereIn('branch_id', $branchIds)->get();

        $clients = Client::where('company_id', $company->id)
            ->orWhereHas('agent.branch', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->get();

        $rules = AutoBilling::where('company_id', $company->id)->get();

        return view('auto-billing.index', [
            'company' => $company,
            'rules' => $rules,
            'clients' => $clients,
            'agents' => $agents,
        ]);
    }

    public function store(Request $request)
    {
        $company = Company::find(Auth::user()->company_id);
        $timezone = $company->country->timezone ?? 'Asia/Kuala_Lumpur';

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'add_amount' => 'numeric|min:0',
            'gateway' => 'nullable|string',
            'method' => 'nullable|string',
            'invoice_time_company' => 'required',
            'auto_send_whatsapp' => 'boolean',
        ]);

        $companyTime = Carbon::parse($request->invoice_time_company, $timezone);
        $systemTime = $companyTime->copy()->setTimezone('Asia/Kuala_Lumpur');

        AutoBilling::create([
            'company_id' => $company->id,
            'created_by_list' => $request->input('created_by_list', []),
            'agent_ids' => $request->input('agent_ids', []),
            'issued_by_list' => $request->input('issued_by_list', []),
            'client_id' => $request->client_id,
            'add_amount' => $request->add_amount ?? 1,
            'gateway' => $request->gateway,
            'method' => $request->method,
            'invoice_time_company' => $companyTime->format('H:i:s'),
            'invoice_time_system' => $systemTime->format('H:i:s'),
            'timezone' => $timezone,
            'auto_send_whatsapp' => $request->boolean('auto_send_whatsapp', false),
        ]);

        return redirect()->back()->with('success', 'Auto Billing Rule created successfully.');
    }

    public function destroy(AutoBilling $rule)
    {
        $rule->delete();
        return back()->with('success', 'Rule deleted.');
    }
}
