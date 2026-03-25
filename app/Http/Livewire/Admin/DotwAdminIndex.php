<?php

namespace App\Http\Livewire\Admin;

use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DotwAdminIndex extends Component
{
    public string $activeTab = 'dashboard';

    // Credentials form fields
    public ?int $selected_company_id = null;

    public string $dotw_username = '';

    public string $dotw_password = '';

    public string $dotw_company_code = '';

    public string $markup_percent = '20';

    public bool $b2b_enabled = true;

    public bool $b2c_enabled = false;

    public bool $is_active = true;

    protected array $rules = [
        'dotw_username'     => ['required', 'string', 'max:100'],
        'dotw_password'     => ['required', 'string', 'max:200'],
        'dotw_company_code' => ['required', 'string', 'max:50'],
        'markup_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        'b2b_enabled'       => ['boolean'],
        'b2c_enabled'       => ['boolean'],
        'is_active'         => ['boolean'],
    ];

    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    public function mount(string $tab = 'dashboard'): void
    {
        $this->activeTab = $tab;
        if ($tab !== 'documentation') {
            $this->loadCredentials();
        }
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'selected_company_id' && $this->isSuperAdmin()) {
            $this->loadCredentialsForCompany();
        }
    }

    private function loadCredentialsForCompany(): void
    {
        if (!$this->selected_company_id) {
            // Reset form
            $this->dotw_username = '';
            $this->dotw_password = '';
            $this->dotw_company_code = '';
            $this->markup_percent = '20';
            $this->b2b_enabled = true;
            $this->b2c_enabled = false;
            $this->is_active = true;

            return;
        }

        $credential = CompanyDotwCredential::where('company_id', $this->selected_company_id)->first();
        if ($credential) {
            // dotw_username and dotw_password are decrypted by model accessors.
            // Do NOT pre-fill username/password — never expose credentials in form fields.
            $this->dotw_company_code = $credential->dotw_company_code ?? '';
            $this->markup_percent = (string) $credential->markup_percent;
            $this->b2b_enabled = $credential->b2b_enabled ?? true;
            $this->b2c_enabled = $credential->b2c_enabled ?? false;
            $this->is_active = $credential->is_active ?? true;
        } else {
            // New company — reset to defaults
            $this->dotw_company_code = '';
            $this->markup_percent = '20';
            $this->b2b_enabled = true;
            $this->b2c_enabled = false;
            $this->is_active = true;
        }
    }

    private function loadCredentials(): void
    {
        if ($this->isSuperAdmin()) {
            // Super admin: no default company selected
            return;
        }

        $companyId = Auth::user()->company?->id;
        if ($companyId === null) {
            return;
        }

        $this->selected_company_id = $companyId;
        $this->loadCredentialsForCompany();
    }

    private function resolveCompanyId(): ?int
    {
        if ($this->isSuperAdmin()) {
            return $this->selected_company_id;
        }

        return Auth::user()->company?->id;
    }

    public function saveCredentials(): void
    {
        $this->validate();

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            $this->addError('dotw_username', 'Please select a company.');

            return;
        }

        CompanyDotwCredential::updateOrCreate(
            ['company_id' => $companyId],
            [
                'dotw_username'     => $this->dotw_username,
                'dotw_password'     => $this->dotw_password,
                'dotw_company_code' => $this->dotw_company_code,
                'markup_percent'    => (float) ($this->markup_percent ?: 20),
                'is_active'         => $this->is_active,
                'b2b_enabled'       => $this->b2b_enabled,
                'b2c_enabled'       => $this->b2c_enabled,
            ]
        );

        // Clear credential fields after save — never persist in component state.
        $this->dotw_username = '';
        $this->dotw_password = '';

        session()->flash('credentials_saved', 'DOTW credentials saved successfully.');
    }

    public function render(): \Illuminate\View\View
    {
        $companies = $this->isSuperAdmin()
            ? Company::orderBy('name')->get()
            : collect();

        return view('livewire.admin.dotw-admin-index', [
            'isSuperAdmin' => $this->isSuperAdmin(),
            'companyId'    => $this->resolveCompanyId(),
            'companies'    => $companies,
        ]);
    }
}
