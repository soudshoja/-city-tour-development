<?php

namespace Tests\Feature;

use App\Http\Controllers\SupplierCompanyController;
use App\Models\Task;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * W6.S "Hold/confirmed follow-up lifecycle" (w6-brief.md, owner addition 2026-08-28).
 * REWRITTEN for this sub-wave: `tasks:process-expired-confirmed` no longer hard-codes a
 * Jazeera-only `confirmed` -> `void` flip (ct-void-map.md §7 bug 8); it is now a thin CLI wrapper
 * over TaskStatusService::expire(), which runs for ALL suppliers and flips eligible tasks to the
 * NEW `expired` status (never `void`). The old assertions here ("becomes void", Jazeera-only,
 * `--dry-run`) tested behaviour this sub-wave deliberately replaces -- see
 * tests/Unit/Services/TaskStatusServiceTest.php for the fuller table-driven/lifecycle coverage
 * (grace hours, hold_auto_expire option, audit row, zero ledger rows). This file keeps
 * command-level (Artisan::call) coverage only.
 */
class ExpiredConfirmedTasksTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $country = Country::factory()->create();

        return Company::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
        ]);
    }

    public function test_expired_confirmed_task_becomes_expired_for_any_supplier()
    {
        $company = $this->makeCompany();
        CoaSeeder::run($company->id);

        // Deliberately NOT Jazeera -- proves the new command is not supplier-name-gated.
        $supplier = Supplier::factory()->create(['name' => 'Generic AI Import Supplier']);
        SupplierCompany::firstOrCreate([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $task = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST001',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(),
        ]);

        Artisan::call('tasks:process-expired-confirmed');

        $task->refresh();
        $this->assertEquals('expired', $task->status);
    }

    public function test_multiple_expired_confirmed_tasks_become_expired()
    {
        $company = $this->makeCompany();

        $supplier = Supplier::factory()->create(['name' => 'jazeera']);
        SupplierCompany::firstOrCreate([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $task1 = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST002',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(),
        ]);

        $task2 = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST003',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subMinutes(30),
        ]);

        Artisan::call('tasks:process-expired-confirmed');

        $task1->refresh();
        $task2->refresh();
        $this->assertEquals('expired', $task1->status);
        $this->assertEquals('expired', $task2->status);
    }

    public function test_non_expired_confirmed_tasks_are_not_processed()
    {
        $company = $this->makeCompany();

        $supplier = Supplier::factory()->create(['name' => 'jazeera']);
        SupplierCompany::firstOrCreate([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $task = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST004',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->addHour(),
        ]);

        Artisan::call('tasks:process-expired-confirmed');

        $task->refresh();
        $this->assertEquals('confirmed', $task->status);
    }

    public function test_issued_tasks_are_ignored()
    {
        $company = $this->makeCompany();

        $supplier = Supplier::factory()->create(['name' => 'jazeera']);
        SupplierCompany::firstOrCreate([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $task = Task::factory()->create([
            'status' => 'issued',
            'reference' => 'TEST005',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(),
        ]);

        Artisan::call('tasks:process-expired-confirmed');

        $task->refresh();
        $this->assertEquals('issued', $task->status);
    }

    public function test_company_id_option_scopes_to_one_company()
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();

        $supplier = Supplier::factory()->create(['name' => 'jazeera']);
        foreach ([$companyA, $companyB] as $company) {
            SupplierCompany::firstOrCreate([
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'is_active' => true,
            ]);
        }

        $taskA = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TESTA',
            'company_id' => $companyA->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(),
        ]);
        $taskB = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TESTB',
            'company_id' => $companyB->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(),
        ]);

        Artisan::call('tasks:process-expired-confirmed', ['--company-id' => $companyA->id]);

        $taskA->refresh();
        $taskB->refresh();
        $this->assertEquals('expired', $taskA->status);
        $this->assertEquals('confirmed', $taskB->status, 'company B was not requested and must be untouched');
    }
}
