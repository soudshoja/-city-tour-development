<?php

namespace App\Http\Livewire\Admin;

use App\Models\Company;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ResailaiSettingsIndex extends Component
{
    public string $activeTab = 'settings';

    public string $webhook_url = '';

    public bool $enabled = false;

    public ?int $selectedCompanyId = null;

    protected array $rules = [
        'webhook_url' => ['nullable', 'url', 'max:500'],
        'enabled' => ['boolean'],
        'selectedCompanyId' => ['nullable', 'integer', 'exists:companies,id'],
    ];

    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    public function mount(string $tab = 'settings'): void
    {
        $this->activeTab = $tab;
        $this->loadSettings();
    }

    public function updatedSelectedCompanyId(): void
    {
        $this->loadCompanySettings();
    }

    private function loadSettings(): void
    {
        $companyId = $this->resolveCompanyId();
        // If no company selected yet (for Super Admin), load the first company
        if ($companyId === null) {
            if ($this->isSuperAdmin() && $this->selectedCompanyId === null) {
                $firstCompany = Company::orderBy('name')->first();
                if ($firstCompany) {
                    $this->selectedCompanyId = $firstCompany->id;
                }
            }

            return;
        }

        $webhookUrl = Setting::where('company_id', $companyId)
            ->where('key', 'resailai_webhook_url')
            ->first();

        $enabled = Setting::where('company_id', $companyId)
            ->where('key', 'resailai_enabled')
            ->first();

        $this->webhook_url = $webhookUrl ? $webhookUrl->value : '';
        $this->enabled = $enabled ? filter_var($enabled->value, FILTER_VALIDATE_BOOLEAN) : false;
    }

    public function loadCompanySettings(): void
    {
        if ($this->selectedCompanyId === null) {
            return;
        }

        $webhookUrl = Setting::where('company_id', $this->selectedCompanyId)
            ->where('key', 'resailai_webhook_url')
            ->first();

        $enabled = Setting::where('company_id', $this->selectedCompanyId)
            ->where('key', 'resailai_enabled')
            ->first();

        $this->webhook_url = $webhookUrl ? $webhookUrl->value : '';
        $this->enabled = $enabled ? filter_var($enabled->value, FILTER_VALIDATE_BOOLEAN) : false;
    }

    private function resolveCompanyId(): ?int
    {
        if ($this->isSuperAdmin()) {
            // Super admin: use selected company or null if none selected
            return $this->selectedCompanyId;
        }

        return Auth::user()->company?->id;
    }

    public function saveSettings(): void
    {
        $this->validate();

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            $this->addError('selectedCompanyId', 'Please select a company first.');

            return;
        }

        Setting::updateOrCreate(
            ['company_id' => $companyId, 'key' => 'resailai_webhook_url'],
            ['value' => $this->webhook_url]
        );

        Setting::updateOrCreate(
            ['company_id' => $companyId, 'key' => 'resailai_enabled'],
            ['value' => $this->enabled ? '1' : '0']
        );

        session()->flash('resailai_settings_saved', 'ResailAI settings saved successfully for selected company.');
    }

    public function render(): \Illuminate\View\View
    {
        $companies = $this->isSuperAdmin() ? Company::orderBy('name')->get() : collect();

        return view('livewire.admin.resailai-settings-index', [
            'isSuperAdmin' => $this->isSuperAdmin(),
            'companyId' => $this->resolveCompanyId(),
            'companies' => $companies,
        ]);
    }
}
