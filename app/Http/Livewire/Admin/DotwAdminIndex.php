<?php

namespace App\Http\Livewire\Admin;

use App\Models\CompanyDotwCredential;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DotwAdminIndex extends Component
{
    public string $activeTab = 'dashboard';

    // Credentials form fields
    public string $dotw_username = '';

    public string $dotw_password = '';

    public string $dotw_company_code = '';

    public string $markup_percent = '20';

    protected array $rules = [
        'dotw_username'     => ['required', 'string', 'max:100'],
        'dotw_password'     => ['required', 'string', 'max:200'],
        'dotw_company_code' => ['required', 'string', 'max:50'],
        'markup_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
    ];

    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    public function mount(string $tab = 'dashboard'): void
    {
        $this->activeTab = $tab;
        $this->loadCredentials();
    }

    private function loadCredentials(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId === null) {
            return;
        }

        $credential = CompanyDotwCredential::where('company_id', $companyId)->first();
        if ($credential) {
            // dotw_username and dotw_password are decrypted by model accessors.
            // Do NOT pre-fill username/password — never expose credentials in form fields.
            $this->dotw_company_code = $credential->dotw_company_code ?? '';
            $this->markup_percent    = (string) $credential->markup_percent;
        }
    }

    private function resolveCompanyId(): ?int
    {
        if ($this->isSuperAdmin()) {
            // Super admin: no default company — form is company-specific.
            // Super admin should use the credential API or per-company pages.
            return null;
        }

        return Auth::user()->company?->id;
    }

    public function saveCredentials(): void
    {
        $this->validate();

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            $this->addError('dotw_username', 'Super Admin cannot save credentials from this page. Use the API endpoint.');

            return;
        }

        CompanyDotwCredential::updateOrCreate(
            ['company_id' => $companyId],
            [
                'dotw_username'     => $this->dotw_username,
                'dotw_password'     => $this->dotw_password,
                'dotw_company_code' => $this->dotw_company_code,
                'markup_percent'    => (float) ($this->markup_percent ?: 20),
                'is_active'         => true,
            ]
        );

        // Clear credential fields after save — never persist in component state.
        $this->dotw_username = '';
        $this->dotw_password = '';

        session()->flash('credentials_saved', 'DOTW credentials saved successfully.');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.dotw-admin-index', [
            'isSuperAdmin' => $this->isSuperAdmin(),
            'companyId'    => $this->resolveCompanyId(),
        ]);
    }
}
