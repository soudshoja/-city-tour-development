<?php

namespace Database\Factories;

use App\Models\SupplierCompany;
use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierCompanyFactory extends Factory
{
    protected $model = SupplierCompany::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_id' => Supplier::factory(),
            'is_active' => true, // Default to active
            'account_id' => null, // Optional
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
