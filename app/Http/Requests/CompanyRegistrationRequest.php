<?php

namespace App\Http\Requests;

use App\Exports\AgentsTemplateExport;
use App\Imports\AgentsFileImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class CompanyRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // token gate happens in the controller
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
