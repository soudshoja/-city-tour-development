<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanyProvisioner;
use App\Support\CompanyRegistrationData;
use Illuminate\Console\Command;

class ProvisionCompany extends Command
{
    protected $signature = 'company:provision {--company= : Existing company id} {--repair : Re-run idempotent provisioning steps}';

    protected $description = 'Provision or repair a tenant company (roles, COA, branch, sequences, settings, storage)';

    public function handle(CompanyProvisioner $provisioner): int
    {
        $companyId = $this->option('company');

        if (!$companyId || !$this->option('repair')) {
            $this->error('Usage: company:provision --company=ID --repair (wizard handles first-time provisioning)');
            return self::FAILURE;
        }

        $company = Company::find($companyId);
        if (!$company) {
            $this->error("Company {$companyId} not found.");
            return self::FAILURE;
        }

        $data = CompanyRegistrationData::fromArray([
            'company_name' => $company->name,
            'company_code' => $company->code,
            'country_id' => $company->country_id,
            'address' => $company->address,
            'phone' => $company->phone,
            'company_email' => $company->email,
            'owner_name' => $company->user?->name ?? $company->name,
            'owner_email' => $company->user?->email ?? $company->email,
            'owner_password' => str()->random(16),   // ignored: user already exists (firstOrCreate)
            'currency' => $company->currency ?? 'KWD',
        ]);

        $provisioner->provision($data);
        $this->info("Company {$company->id} ({$company->name}) provisioned/repaired.");

        return self::SUCCESS;
    }
}
