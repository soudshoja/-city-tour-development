<?php
// app/Services/SupplierActivationService.php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;

class SupplierActivationService
{
    /**
     * Activate a supplier for a company. Caller owns the DB transaction.
     * Mirrors the legacy SupplierCompanyController::activateSupplierProcess.
     */
    public function activate(Supplier $supplier, Company $company): array
    {
        $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->first();

        if ($supplierCompany) {
            $supplierCompany->is_active = true;
            $supplierCompany->update();

            return ['status' => 'success', 'message' => 'Supplier is already activated for this company.'];
        }

        $credentials = SupplierCredential::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->exists();

        if (!$credentials) {
            SupplierCredential::create([
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'environment' => env('APP_ENV') == 'production' ? 'production' : 'sandbox',
                'type' => 'basic',
                'username' => 'test',
                'password' => 'test',
                'client_id' => null,
                'client_secret' => null,
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
            ]);
        }

        $types = [
            'has_flight' => ['payable' => 'Suppliers (Flights)', 'cost' => 'Flights Cost'],
            'has_hotel' => ['payable' => 'Suppliers (Hotels)', 'cost' => 'Hotels Cost'],
            'has_visa' => ['payable' => 'Suppliers (Visas)', 'cost' => 'Visa Cost'],
            'has_insurance' => ['payable' => 'Suppliers (Insurance)', 'cost' => 'Insurance Cost'],
            'has_tour' => ['payable' => 'Suppliers (Tour)', 'cost' => 'Tour Cost'],
            'has_cruise' => ['payable' => 'Suppliers (Cruise)', 'cost' => 'Cruise Cost'],
            'has_car' => ['payable' => 'Suppliers (Car)', 'cost' => 'Car Cost'],
            'has_rail' => ['payable' => 'Suppliers (Rail)', 'cost' => 'Rail Cost'],
            'has_esim' => ['payable' => 'Suppliers (Esim)', 'cost' => 'Esim Cost'],
            'has_event' => ['payable' => 'Suppliers (Event)', 'cost' => 'Event Cost'],
            'has_lounge' => ['payable' => 'Suppliers (Lounge)', 'cost' => 'Lounge Cost'],
            'has_ferry' => ['payable' => 'Suppliers (Ferry)', 'cost' => 'Ferry Cost'],
        ];

        $hasAtLeastOne = false;

        foreach ($types as $field => $accounts) {
            if (!$supplier->$field) {
                continue;
            }

            $hasAtLeastOne = true;

            $accountPayable = Account::where('name', $accounts['payable'])
                ->where('company_id', $company->id)
                ->first();

            if (!$accountPayable) {
                throw new \Exception("Account Payable group '{$accounts['payable']}' not found.");
            }

            $costAccount = Account::where('name', $accounts['cost'])
                ->where('company_id', $company->id)
                ->first();

            if (!$costAccount) {
                throw new \Exception("Supplier cost account '{$accounts['cost']}' not found.");
            }

            $supplierCompany = SupplierCompany::firstOrCreate([
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'is_active' => true,
            ]);

            $data = [
                'name' => $supplier->name,
                'level' => 4,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $company->id,
                'supplier_company_id' => $supplierCompany->id,
            ];

            $newPayableCode = (int) $accountPayable->code + 1;
            $newCostCode = (int) $costAccount->code + 1;

            Account::create($data + [
                'parent_id' => $accountPayable->id,
                'root_id' => $accountPayable->root_id,
                'code' => (string) $newPayableCode,
            ]);

            Account::create($data + [
                'parent_id' => $costAccount->id,
                'root_id' => $costAccount->root_id,
                'code' => (string) $newCostCode,
            ]);
        }

        if (!$hasAtLeastOne) {
            throw new \Exception('Supplier must have at least one category checked.');
        }

        return ['status' => 'success', 'message' => 'Supplier activated successfully.'];
    }
}
