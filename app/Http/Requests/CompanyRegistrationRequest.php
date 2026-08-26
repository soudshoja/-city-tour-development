<?php

namespace App\Http\Requests;

use App\Exports\AgentsTemplateExport;
use App\Imports\AgentsFileImport;
use App\Models\CompanyInvite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CompanyRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // token gate happens in the controller
    }

    /**
     * SECURITY FIX (2026-08-26 — wave-2 adversarial verification, blocker
     * 2a): `owner_email` used to be validated only as `required|email|
     * unique:users,email` — nothing tied it to the invite this
     * registration link was actually sent to. Anyone holding a valid
     * invite link could type ANY email into "Login email", including a
     * real stranger's — and that email becomes both this company's
     * TravelERP login AND the identity CompanyProvisioner's post-commit
     * job later searches for on Resayil (ResayilProvisioningService::
     * findCustomerByEmail()). Proven live: a throwaway company registered
     * with an unrelated real customer's email adopted that customer's
     * Resayil account and captured its live API key.
     *
     * The invite (`CompanyInvite.email`) is the only pre-established trust
     * anchor here — an admin created it and Mail::to()'d the token link to
     * that specific address (CompanyInviteController::store()), so only
     * whoever controls that inbox can reach this form at all. Binding
     * `owner_email` to it closes the gap without inventing a new
     * verification step. We REJECT a mismatch (a clear validation error)
     * rather than silently overwriting the submitted value with the
     * invite's: silently substituting a value the user can see they typed
     * would be confusing ("why did my login become a different email?")
     * and looks like a bug, not a security control — an explicit rejection
     * tells the registrant exactly what to fix.
     *
     * `company_email` (a separate field, prefilled from the invite but
     * intentionally left editable — it is the general company contact
     * address, not a login) is NOT constrained here: it plays no part in
     * either authentication or the Resayil lookup, so there is nothing to
     * protect by locking it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $token = $this->route('token');
            $invite = $token ? CompanyInvite::where('token', $token)->first() : null;

            if (! $invite || ! $invite->email) {
                return;   // no invite to bind to — the controller's own token check handles this
            }

            $ownerEmail = (string) $this->input('owner_email');

            if ($ownerEmail !== '' && strcasecmp($ownerEmail, $invite->email) !== 0) {
                $validator->errors()->add(
                    'owner_email',
                    'The administrator login email must match the email this invitation was sent to ('.$invite->email.').'
                );
            }
        });
    }

    /**
     * Parse an uploaded "bulk agents" file (if any) and merge its rows into
     * the `agents` payload BEFORE validation runs, so a bad email typed into
     * the spreadsheet surfaces through the normal agents.* rules exactly
     * like a manually-typed row would.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->hasFile('agents_file') || !$this->file('agents_file')->isValid()) {
            return;
        }

        $file = $this->file('agents_file');

        // Gate BEFORE Excel::toArray() ever touches the file: prepareForValidation()
        // runs ahead of the rules() validator, so the 'max:1024' / 'mimes:xlsx,csv'
        // rules cannot stop an oversized/wrong-type file from being parsed here.
        // PhpSpreadsheet loading a large file is a real memory-exhaustion risk, and
        // a PHP OOM fatal is NOT a catchable \Throwable — so an oversized upload
        // must be rejected on size/extension alone, without ever calling
        // Excel::toArray(). Leave $agents untouched; the existing agents_file
        // rules still run afterwards and will produce the validation error.
        if ($file->getSize() > 1024 * 1024 || !in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'csv'], true)) {
            return;
        }

        try {
            $sheets = Excel::toArray(new AgentsFileImport, $file);
            $rows = $sheets[0] ?? [];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['agents_file' => 'Could not read the file']);
        }

        // Row 0 is the header ("Name | Email | Phone | Amadeus Sign") — always
        // skipped. Row 1 is skipped too, but ONLY if it's the untouched example
        // row (matches AgentsTemplateExport's example email exactly) — a real
        // agent happening to be the first data row must not be dropped.
        $dataRows = array_slice($rows, 1);
        if (isset($dataRows[0]) && trim((string) ($dataRows[0][1] ?? '')) === AgentsTemplateExport::EXAMPLE_EMAIL) {
            array_shift($dataRows);
        }

        $agents = $this->input('agents', []);

        foreach ($dataRows as $row) {
            $email = trim((string) ($row[1] ?? ''));
            if ($email === '') {
                continue;   // drop empty-email rows
            }

            $agents[] = [
                'name' => trim((string) ($row[0] ?? '')),
                'email' => $email,
                'phone' => trim((string) ($row[2] ?? '')) ?: null,
                'amadeus_id' => trim((string) ($row[3] ?? '')) ?: null,
            ];
        }

        $this->merge(['agents' => $agents]);
    }

    public function rules(): array
    {
        $rules = [
            'company_name' => 'required|string|max:255|unique:companies,name',
            'company_code' => 'required|string|max:100|unique:companies,code',
            'country_id' => 'required|integer|exists:countries,id',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'company_email' => 'required|email|max:255',
            'logo' => 'nullable|image|max:2048',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
            'owner_password' => 'required|string|min:8|confirmed',
            'iata_code' => 'nullable|string|max:50',
            'gds_office_id' => 'nullable|string|max:50',
            'iata_client_id' => 'nullable|string|max:255',
            'iata_client_secret' => 'nullable|string|max:255',
            'agents' => 'nullable|array|max:50',
            'agents.*.name' => 'required_with:agents.*.email|string|max:255',
            'agents.*.email' => 'nullable|email|distinct|unique:users,email',
            'agents.*.phone' => 'nullable|string|max:30',
            'agents.*.amadeus_id' => 'nullable|string|max:30',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'integer|exists:suppliers,id',
            'gateways' => 'nullable|array|max:10',
            'gateways.*.name' => 'required_with:gateways.*.api_key|string|max:50',
            'gateways.*.api_key' => 'nullable|string|max:500',
            'gds_pccs' => 'nullable|array|max:20',
            'gds_pccs.*.gds' => 'required_with:gds_pccs.*.pcc|in:Amadeus,Galileo,Sabre',
            'gds_pccs.*.pcc' => 'required_with:gds_pccs.*.gds|string|max:30',
            'agents_file' => 'nullable|file|mimes:xlsx,csv|max:1024',
        ];

        if (!app()->environment('local', 'testing')) {
            $rules['g-recaptcha-response'] = ['required', 'recaptchav3:company_register,0.7'];
        }

        return $rules;
    }
}
