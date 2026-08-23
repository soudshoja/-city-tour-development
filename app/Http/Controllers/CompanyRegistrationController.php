<?php

namespace App\Http\Controllers;

use App\Exports\AgentsTemplateExport;
use App\Http\Requests\CompanyRegistrationRequest;
use App\Mail\CompanyRegisteredAdminMail;
use App\Mail\CompanyWelcomeMail;
use App\Models\Company;
use App\Models\CompanyInvite;
use App\Models\Country;
use App\Models\Supplier;
use App\Services\CompanyProvisioner;
use App\Support\CompanyRegistrationData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class CompanyRegistrationController extends Controller
{
    public function show(string $token)
    {
        if (request()->boolean('done')) {
            return response()->view('register.company-success');
        }

        $invite = CompanyInvite::where('token', $token)->first();

        if (!$invite || !$invite->isUsable()) {
            return response()->view('register.company-invalid');
        }

        return view('register.company', [
            'invite' => $invite,
            'countries' => Country::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Bulk-agents Excel template download for wizard step 4. Gated behind the
     * same usable-invite check as show()/store() — an expired/used/unknown
     * token must not leak the template.
     */
    public function agentsTemplate(string $token)
    {
        $invite = CompanyInvite::where('token', $token)->first();

        if (!$invite || !$invite->isUsable()) {
            abort(404);
        }

        return Excel::download(new AgentsTemplateExport(), 'agents-template.xlsx');
    }

    public function store(CompanyRegistrationRequest $request, string $token)
    {
        $payload = $request->validated();

        // Drop agent rows with no email (empty template rows from the wizard)
        $payload['agents'] = array_values(array_filter($payload['agents'] ?? [], fn ($a) => !empty($a['email'])));
        $payload['gateways'] = array_values(array_filter($payload['gateways'] ?? [], fn ($g) => !empty($g['api_key'])));
        // Drop GDS/PCC rows with no pcc (empty template rows from the wizard)
        $payload['gds_pccs'] = array_values(array_filter($payload['gds_pccs'] ?? [], fn ($g) => !empty($g['pcc'])));

        // Captured by reference from inside the transaction closure so both the
        // success path and the failure catch can reach the invite (and its
        // creator) for the admin notification below.
        $invite = null;

        try {
            $company = DB::transaction(function () use ($payload, $token, &$invite) {
                $invite = CompanyInvite::where('token', $token)->lockForUpdate()->first();

                if (!$invite || !$invite->isUsable()) {
                    throw ValidationException::withMessages([
                        'token' => 'This registration link is invalid or has already been used.',
                    ]);
                }

                $data = CompanyRegistrationData::fromArray($payload);

                return app(CompanyProvisioner::class)->provision($data, $invite);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Company registration failed', [
                'token' => substr($token, 0, 8) . '…', 'error' => $e->getMessage(),
            ]);

            $this->notifyAdminOfFailure($invite, $token, $payload['company_name'] ?? '(unknown)', $e);

            // Secrets never round-trip back into the form/session — they don't
            // survive a failed submission and must not be re-echoed.
            return back()->withInput($request->except([
                'owner_password', 'owner_password_confirmation', 'iata_client_secret', 'gateways',
            ]))->withErrors([
                'provisioning' => 'Registration failed — nothing was created. Please try again or contact City Travelers.',
            ]);
        }

        // Store logo only after the transaction committed
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $company->update(['logo' => $path]);
        }

        // Registration already committed at this point — a mail failure below
        // must never turn this into a 500 or make the customer think signup
        // failed, so each send is independently guarded.
        try {
            Mail::to($payload['owner_email'])->send(
                new CompanyWelcomeMail($company->name, $payload['owner_email'], route('login'))
            );
        } catch (\Throwable $e) {
            Log::warning('Company welcome mail failed', ['company_id' => $company->id, 'error' => $e->getMessage()]);
        }

        $this->notifyAdminOfSuccess($invite, $company);

        return redirect(route('company-register.show', $token) . '?done=1');
    }

    private function notifyAdminOfSuccess(?CompanyInvite $invite, Company $company): void
    {
        if (!$invite || !$invite->creator || !$invite->creator->email) {
            return;
        }

        try {
            Mail::to($invite->creator->email)->send(
                new CompanyRegisteredAdminMail($company->name, $invite->email, true)
            );
        } catch (\Throwable $e) {
            Log::warning('Company registration admin notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyAdminOfFailure(?CompanyInvite $invite, string $token, string $attemptedCompanyName, \Throwable $original): void
    {
        if (!$invite) {
            $invite = CompanyInvite::where('token', $token)->first();
        }
        if (!$invite || !$invite->creator || !$invite->creator->email) {
            return;
        }

        try {
            Mail::to($invite->creator->email)->send(
                new CompanyRegisteredAdminMail($attemptedCompanyName, $invite->email, false, $original->getMessage())
            );
        } catch (\Throwable $e) {
            Log::warning('Company registration failure notification failed', ['error' => $e->getMessage()]);
        }
    }
}
