<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TaskRuleConfiguration;
use App\Models\TaskRules;
use App\Models\Task;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Country;
use App\Enums\TaskRuleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskRuleConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected TaskRuleConfiguration $ruleConfig;
    protected Company $company;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleConfig = new TaskRuleConfiguration();
        
        // Create required dependencies
        $user = User::factory()->create();
        $country = Country::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $user->id, 'country_id' => $country->id]);
        $this->supplier = Supplier::factory()->create(['country_id' => $country->id]);
    }

    public function test_default_rule_does_not_modify_data()
    {
        TaskRules::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'name' => TaskRuleEnum::DEFAULT->value,
            'description' => 'Default behavior',
        ]);

        $taskData = [
            'reference' => 'TEST-001',
            'price' => 100.00,
            'total' => 120.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData);

        $this->assertEquals($taskData, $result);
    }

    public function test_minus_existing_rule_with_existing_task()
    {
        TaskRules::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'name' => TaskRuleEnum::MINUS_EXISTING->value,
            'description' => 'Subtract existing price',
            'column' => 'price',
        ]);

        $existingTask = Task::factory()->create([
            'price' => 50.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $taskData = [
            'reference' => 'TEST-001',
            'price' => 100.00,
            'total' => 120.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData, $existingTask);

        $this->assertEquals(50.00, $result['price']);
        $this->assertEquals(120.00, $result['total']);
    }

    public function test_minus_existing_rule_without_existing_task()
    {
        TaskRules::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'name' => TaskRuleEnum::MINUS_EXISTING->value,
            'description' => 'Subtract existing price',
            'column' => 'price',
        ]);

        $taskData = [
            'reference' => 'TEST-001',
            'price' => 100.00,
            'total' => 120.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData, null);

        $this->assertEquals($taskData, $result);
    }

    public function test_multiple_rules_applied()
    {
        TaskRules::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'name' => TaskRuleEnum::MINUS_EXISTING->value,
            'description' => 'Subtract existing price',
            'column' => 'price',
        ]);

        TaskRules::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'name' => TaskRuleEnum::MINUS_EXISTING->value,
            'description' => 'Subtract existing total',
            'column' => 'total',
        ]);

        $existingTask = Task::factory()->create([
            'price' => 30.00,
            'total' => 40.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $taskData = [
            'reference' => 'TEST-001',
            'price' => 100.00,
            'total' => 120.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData, $existingTask);

        $this->assertEquals(70.00, $result['price']);
        $this->assertEquals(80.00, $result['total']);
    }

    public function test_no_rules_for_supplier_returns_original_data()
    {
        // Create a different supplier with no rules
        $otherSupplier = Supplier::factory()->create(['country_id' => $this->supplier->country_id]);
        
        $taskData = [
            'reference' => 'TEST-001',
            'price' => 100.00,
            'total' => 120.00,
            'company_id' => $this->company->id,
            'supplier_id' => $otherSupplier->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData);

        $this->assertEquals($taskData, $result);
    }

    public function test_supplier_with_rules_applies_them()
    {
        // Create another supplier with specific rules
        $otherSupplier = Supplier::factory()->create(['country_id' => $this->supplier->country_id]);
        
        // Supplier-specific rule
        TaskRules::create([
            'company_id' => $this->company->id,
            'supplier_id' => $otherSupplier->id,
            'name' => TaskRuleEnum::MINUS_EXISTING->value,
            'description' => 'Supplier specific rule',
            'column' => 'price',
        ]);

        $existingTask = Task::factory()->create([
            'price' => 30.00,
            'company_id' => $this->company->id,
            'supplier_id' => $otherSupplier->id,
        ]);

        $taskData = [
            'price' => 100.00,
            'total' => 120.00,
            'company_id' => $this->company->id,
            'supplier_id' => $otherSupplier->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData, $existingTask);

        $this->assertEquals(70.00, $result['price']); // 100 - 30 = 70 (supplier rule applied)
        $this->assertEquals(120.00, $result['total']); // unchanged
    }

    public function test_supplier_without_rules_uses_default_behavior()
    {
        // Create another supplier without any rules
        $supplierWithoutRules = Supplier::factory()->create(['country_id' => $this->supplier->country_id]);
        
        // No rules created for this supplier

        $existingTask = Task::factory()->create([
            'total' => 20.00,
            'company_id' => $this->company->id,
            'supplier_id' => $supplierWithoutRules->id,
        ]);

        $taskData = [
            'price' => 100.00,
            'total' => 120.00,
            'company_id' => $this->company->id,
            'supplier_id' => $supplierWithoutRules->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData, $existingTask);

        // Should return unchanged (default behavior)
        $this->assertEquals($taskData, $result);
    }

    public function test_throws_exception_when_task_missing_company_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Task data must have a company_id to apply rules.');

        $taskData = [
            'price' => 100.00,
            'supplier_id' => $this->supplier->id,
        ];

        $this->ruleConfig->applyRules($taskData);
    }

    public function test_throws_exception_when_task_missing_supplier_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Task data must have a supplier_id to apply rules.');

        $taskData = [
            'price' => 100.00,
            'company_id' => $this->company->id,
        ];

        $this->ruleConfig->applyRules($taskData);
    }

    public function test_updating_tax_using_tax_calculate_rule()
    {
        TaskRules::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'name' => TaskRuleEnum::TAX_CALCULATED->value,
            'description' => 'Calculate by subtracting price from total',
            'column' => 'tax',
        ]);

        $taskData = [
            'reference' => 'TEST-002',
            'price' => 200.00,
            'total' => 220.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ];

        $result = $this->ruleConfig->applyRules($taskData);

        $this->assertEquals(20.00, $result['tax']);

        $secondTaskData = [
            'reference' => 'TEST-003',
            'price' => 150.00,
            'total' => 180.00,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ];

        $secondResult = $this->ruleConfig->applyRules($secondTaskData);

        $this->assertEquals(30.00, $secondResult['tax']);
    }
}