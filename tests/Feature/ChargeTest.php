<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Charge;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\Role;
use App\Models\Account;
use App\Models\AgentType;
use App\Models\PaymentMethod;
use App\Models\Country;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ChargeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $company;
    protected $companyUser;
    protected $branch;
    protected $branchUser;
    protected $agent;
    protected $agentUser;
    protected $country;
    protected $bankAccount;
    protected $feeAccount;
    protected $bankFeeAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::ADMIN]);
        // $this->admin->assignRole('admin');
        
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        $this->country = Country::factory()->create();

        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id,
            'country_id' => $this->country->id,
        ]);

        // $this->companyUser->assignRole('company');

        CoaSeeder::run($this->company->id);

        $this->branchUser = User::create([
            'name' => 'Branch User',
            'email' => 'branch@yahoo.com',
            'password' => bcrypt('password123'),
            'role_id' => Role::BRANCH,
            'company_id' => $this->company->id,
            'country_id' => $this->country->id,
        ]);

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'city' => 'Metropolis',
            'country_id' => $this->country->id,
            'phone' => '+1234567890',
            'email' => 'branch@yahoo.com',
            'user_id' => $this->branchUser->id,
            'company_id' => $this->company->id,
        ]);

        // $this->branchUser->assignRole('branch');

        AgentType::factory()->create([
            'id' => 1,
            'name' => 'Commission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        AgentType::factory()->create([
            'id' => 2,
            'name' => 'Salary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        AgentType::factory()->create([
            'id' => 3,
            'name' => 'Both',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $this->agent = Agent::factory()->create([
            'user_id' => $this->agentUser->id,
            'branch_id' => $this->branch->id,
            'type_id' => 1
        ]);

        // Seed chart of accounts for the company
        CoaSeeder::run($this->company->id);

        // Get accounts from COA
        $this->bankAccount = Account::where('company_id', $this->company->id)
            ->where('name', 'Kuwait International Bank')
            ->first();
        
        $this->feeAccount = Account::where('company_id', $this->company->id)
            ->where('name', 'Commission & Service Fee Income')
            ->first();
        
        $this->bankFeeAccount = Account::where('company_id', $this->company->id)
            ->where('name', 'Payment Gateway Charges')
            ->first();

        // $this->agentUser->assignRole('agent');

    }

    public function test_admin_can_create_system_gateway()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('charges.store'), [
            'name' => 'Tap',
            'type' => 'Payment Gateway',
            'description' => 'Tap Payment Gateway',
            'charge_type' => 'Percent',
            'amount' => 2.5,
            'paid_by' => 'Client',
            'is_active' => true,
            'can_generate_link' => true,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('charges', [
            'name' => 'Tap',
            'is_system_default' => true,
            'can_be_deleted' => false,
            'enabled_by' => 'admin',
        ]);
    }

    public function test_company_cannot_create_system_gateway()
    {
        $this->actingAs($this->companyUser);

        $response = $this->post(route('charges.store'), [
            'name' => 'MyFatoorah',
            'type' => 'Payment Gateway',
            'description' => 'MyFatoorah Gateway',
            'charge_type' => 'Percent',
            'amount' => 3,
            'paid_by' => 'Client',
            'is_active' => true,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response->assertStatus(403); // Forbidden
    }

    public function test_company_can_create_custom_gateway()
    {
        $this->actingAs($this->companyUser);

        $response = $this->post(route('charges.store'), [
            'name' => 'CustomGateway',
            'type' => 'Payment Gateway',
            'description' => 'Custom Payment Gateway',
            'charge_type' => 'Flat Rate',
            'amount' => 5,
            'paid_by' => 'Company',
            'is_active' => true,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('charges', [
            'name' => 'CustomGateway',
            'is_system_default' => false,
            'can_be_deleted' => true,
            'enabled_by' => 'company',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_can_update_all_fields_of_system_gateway()
    {
        $this->actingAs($this->admin);

        $charge = Charge::factory()->create([
            'name' => 'UPayment',
            'is_system_default' => true,
            'company_id' => $this->company->id,
            'amount' => 2,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->put(route('charges.update', $charge->id), [
            'name' => 'UPayment',
            'description' => 'Updated UPayment Gateway',
            'amount' => 3.5,
            'self_charge' => 1.5,
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
            'is_active' => true,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'description' => 'Updated UPayment Gateway',
            'amount' => '3.50', // Formatted
            'self_charge' => 1.5,
        ]);
    }

    public function test_company_can_only_update_limited_fields_of_system_gateway()
    {
        $this->actingAs($this->companyUser);

        $charge = Charge::factory()->create([
            'name' => 'Hesabe',
            'is_system_default' => true,
            'company_id' => $this->company->id,
            'amount' => 2,
            'description' => 'Original Description',
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->put(route('charges.update', $charge->id), [
            'self_charge' => 2.5,
            'description' => 'Company Updated Description',
            'amount' => 10, // Should be ignored
            'name' => 'NewName', // Should be ignored
            'company_id' => $this->company->id,

        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'self_charge' => 2.5,
            'description' => 'Company Updated Description',
            'amount' => '2.00',
            'name' => 'Hesabe',
        ]);
    }

    public function test_company_can_update_all_fields_of_custom_gateway()
    {
        $this->actingAs($this->companyUser);

        $charge = Charge::factory()->create([
            'name' => 'CustomGateway',
            'is_system_default' => false,
            'company_id' => $this->company->id,
            'amount' => 5,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->put(route('charges.update', $charge->id), [
            'name' => 'UpdatedCustomGateway',
            'description' => 'Updated Custom Gateway',
            'amount' => 7,
            'charge_type' => 'Flat Rate',
            'paid_by' => 'Company',
            'is_active' => false,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'name' => 'UpdatedCustomGateway',
            'amount' => '7.00',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_update_api_credentials_of_any_gateway()
    {
        $this->actingAs($this->admin);

        $charge = Charge::factory()->create([
            'name' => 'Tap',
            'is_system_default' => true,
            'company_id' => $this->company->id,
            'api_key' => 'old_key',
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->put(route('charges.credentials.update', $charge->id), [
            'api_key' => 'new_tap_api_key',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'api_key' => 'new_tap_api_key',
        ]);
    }

    public function test_company_cannot_update_api_credentials_of_system_gateway()
    {
        $this->actingAs($this->companyUser);

        $charge = Charge::factory()->create([
            'name' => 'MyFatoorah',
            'is_system_default' => true,
            'company_id' => $this->company->id,
            'api_key' => 'original_key',
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->put(route('charges.credentials.update', $charge->id), [
            'api_key' => 'hacked_key',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'api_key' => 'original_key',
        ]);
    }

    public function test_company_can_update_api_credentials_of_custom_gateway()
    {
        $this->actingAs($this->companyUser);

        $charge = Charge::factory()->create([
            'name' => 'CustomGateway',
            'is_system_default' => false,
            'company_id' => $this->company->id,
            'api_key' => 'old_custom_key',
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->put(route('charges.credentials.update', $charge->id), [
            'api_key' => 'new_custom_key',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'api_key' => 'new_custom_key',
        ]);
    }

    public function test_admin_can_delete_any_gateway()
    {
        $this->actingAs($this->admin);

        // Create custom gateway
        $charge = Charge::factory()->create([
            'name' => 'CustomGateway',
            'is_system_default' => false,
            'can_be_deleted' => true,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->delete(route('charges.destroy', $charge->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('charges', ['id' => $charge->id]);
    }

    public function test_company_can_delete_custom_gateway()
    {
        $this->actingAs($this->companyUser);

        $charge = Charge::factory()->create([
            'name' => 'CustomGateway',
            'is_system_default' => false,
            'can_be_deleted' => true,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->delete(route('charges.destroy', $charge->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('charges', ['id' => $charge->id]);
    }

    public function test_company_cannot_delete_system_gateway()
    {
        $this->actingAs($this->companyUser);

        $charge = Charge::factory()->create([
            'name' => 'Tap',
            'is_system_default' => true,
            'can_be_deleted' => false,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->delete(route('charges.destroy', $charge->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('charges', ['id' => $charge->id]);
    }

    public function test_admin_cannot_delete_system_gateway()
    {
        $this->actingAs($this->admin);

        $charge = Charge::factory()->create([
            'name' => 'MyFatoorah',
            'is_system_default' => true,
            'can_be_deleted' => false,
            'company_id' => $this->company->id,
            'acc_bank_id' => $this->bankAccount->id,
            'acc_fee_id' => $this->feeAccount->id,
            'acc_fee_bank_id' => $this->bankFeeAccount->id,
        ]);

        $response = $this->delete(route('charges.destroy', $charge->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('charges', ['id' => $charge->id]);
    }

    public function test_has_api_implementation_returns_true_for_implemented_gateways()
    {
        $tap = Charge::factory()->create(['name' => 'Tap', 'company_id' => $this->company->id]);
        $myFatoorah = Charge::factory()->create(['name' => 'MyFatoorah', 'company_id' => $this->company->id]);
        $hesabe = Charge::factory()->create(['name' => 'Hesabe', 'company_id' => $this->company->id]);
        $upayment = Charge::factory()->create(['name' => 'UPayment', 'company_id' => $this->company->id]);

        $this->assertTrue($tap->hasApiImplementation());
        $this->assertTrue($myFatoorah->hasApiImplementation());
        $this->assertTrue($hesabe->hasApiImplementation());
        $this->assertTrue($upayment->hasApiImplementation());
    }

    public function test_has_api_implementation_returns_false_for_custom_gateways()
    {
        $custom = Charge::factory()->create(['name' => 'CustomGateway']);

        $this->assertFalse($custom->hasApiImplementation());
    }

    public function test_can_generate_payment_link_requires_both_api_implementation_and_permission()
    {
        // Has implementation AND permission
        $tap = Charge::factory()->create([
            'name' => 'Tap',
            'can_generate_link' => true,
        ]);
        $this->assertTrue($tap->canGeneratePaymentLink());

        // Has implementation but NO permission
        $myFatoorah = Charge::factory()->create([
            'name' => 'MyFatoorah',
            'can_generate_link' => false,
        ]);
        $this->assertFalse($myFatoorah->canGeneratePaymentLink());

        // NO implementation but has permission
        $custom = Charge::factory()->create([
            'name' => 'CustomGateway',
            'can_generate_link' => true,
        ]);
        $this->assertFalse($custom->canGeneratePaymentLink());

        // NO implementation and NO permission
        $custom2 = Charge::factory()->create([
            'name' => 'AnotherCustom',
            'can_generate_link' => false,
        ]);
        $this->assertFalse($custom2->canGeneratePaymentLink());
    }
    
    public function test_charge_index_shows_system_gateway_badge()
    {
        $this->actingAs($this->admin);

        $systemCharge = Charge::factory()->create([
            'name' => 'Tap',
            'is_system_default' => true,
            'company_id' => $this->company->id,
        ]);

        $customCharge = Charge::factory()->create([
            'name' => 'CustomGateway',
            'is_system_default' => false,
            'company_id' => $this->company->id,
        ]);

        $response = $this->get(route('charges.index'));

        $response->assertStatus(200);
        $response->assertSee('System Gateway'); // Badge for Tap
        $response->assertSee('Tap');
        $response->assertSee('CustomGateway');
    }

    public function test_charge_seeder_marks_system_gateways_correctly()
    {
        // Run seeder
        $this->artisan('db:seed', ['--class' => 'ChargeSeeder']);

        // Verify system gateways are marked
        $systemGateways = ['Tap', 'MyFatoorah', 'UPayment', 'Hesabe'];
        
        foreach ($systemGateways as $gateway) {
            $charge = Charge::where('name', $gateway)->first();
            
            if ($charge) {
                $this->assertTrue($charge->is_system_default);
                $this->assertFalse($charge->can_be_deleted);
                $this->assertEquals('admin', $charge->enabled_by);
            }
        }
    }
}
