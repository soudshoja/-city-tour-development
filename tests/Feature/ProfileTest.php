<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\AgentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $companyUser;
    protected $company;
    protected $adminRole;
    protected $companyRole;
    protected $branchRole;
    protected $agentRole;
    protected $agentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        $this->company = Company::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        $this->adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $this->companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $this->branchRole = Role::create([
            'name' => 'branch',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $this->agentRole = Role::create([
            'name' => 'agent',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $this->agentType = AgentType::create(['name' => 'salary']);

        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $response = $this
            ->actingAs($this->adminUser)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $response = $this
            ->actingAs($this->adminUser)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->adminUser->refresh();

        $this->assertSame('Test User', $this->adminUser->name);
        $this->assertSame('test@example.com', $this->adminUser->email);
        $this->assertNull($this->adminUser->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $response = $this
            ->actingAs($this->adminUser)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $this->adminUser->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNotNull($this->adminUser->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $response = $this
            ->actingAs($this->adminUser)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($this->adminUser->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $response = $this
            ->actingAs($this->adminUser)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($this->adminUser->fresh());
    }

    public function test_company_user_profile_update_also_updates_company_information(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Original Company User',
            'email' => 'original.company@example.com',
        ]);

        $user->assignRole($this->companyRole);

        $company = Company::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Company Name',
            'email' => 'original.company@example.com',
            'phone' => '123-456-7890',
            'address' => 'Original Address',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Company User',
                'email' => 'updated.company@example.com',
                'phone' => '987-654-3210',
                'address' => 'Updated Address',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Company User', $user->name);
        $this->assertSame('updated.company@example.com', $user->email);

        $company->refresh();
        $this->assertSame('Updated Company User', $company->name);
        $this->assertSame('updated.company@example.com', $company->email);
        $this->assertSame('987-654-3210', $company->phone);
        $this->assertSame('Updated Address', $company->address);
    }

    public function test_branch_user_profile_update_also_updates_branch_information(): void
    {
        $userCompany = User::factory()->create([
            'role_id' => $this->companyRole->id,
            'name' => 'Company User',
            'email' => 'company@gmail.com'
        ]);
        $userCompany->assignRole($this->companyRole);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id,
            'name' => 'Company Name',
            'email' => 'company@gmail.com'
        ]);

        $user = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Original Branch User',
            'email' => 'original.branch@example.com',
        ]);
        $user->assignRole($this->branchRole);

        $branch = Branch::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => 'Original Branch Name',
            'email' => 'original.branch@example.com',
            'phone' => '111-222-3333',
            'address' => 'Original Branch Address',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Branch User',
                'email' => 'updated.branch@example.com',
                'phone' => '444-555-6666',
                'address' => 'Updated Branch Address',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Branch User', $user->name);
        $this->assertSame('updated.branch@example.com', $user->email);

        $branch->refresh();
        $this->assertSame('Updated Branch User', $branch->name);
        $this->assertSame('updated.branch@example.com', $branch->email);
        $this->assertSame('444-555-6666', $branch->phone);
        $this->assertSame('Updated Branch Address', $branch->address);
    }

    public function test_agent_user_profile_update_also_updates_agent_information(): void
    {
        $this->companyUser->assignRole($this->companyRole);

        $userBranch = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Branch User',
            'email' => 'branch@gmail.com'
        ]);
        $userBranch->assignRole($this->branchRole);

        $branch = Branch::factory()->create([
            'user_id' => $userBranch->id,
            'company_id' => $this->company->id,
            'name' => 'Branch Name',
            'email' => 'branch@gmail.com'
        ]);

        $user = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'Original Agent User',
            'email' => 'original.agent@example.com',
        ]);
        $user->assignRole($this->agentRole);

        $agent = Agent::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type_id' => $this->agentType->id,
            'name' => 'Original Agent Name',
            'email' => 'original.agent@example.com',
            'phone_number' => '777-888-9999',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Agent User',
                'email' => 'updated.agent@example.com',
                'phone' => '000-111-2222',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Agent User', $user->name);
        $this->assertSame('updated.agent@example.com', $user->email);

        $agent->refresh();
        $this->assertSame('Updated Agent User', $agent->name);
        $this->assertSame('updated.agent@example.com', $agent->email);
        $this->assertSame('000-111-2222', $agent->phone_number);
    }

    public function test_profile_update_handles_user_without_associated_role_entities(): void
    {
        $user = User::factory()->create([
            'role_id' => $this->companyRole->id,
            'name' => 'Orphaned User',
            'email' => 'orphaned@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Orphaned User',
                'email' => 'updated.orphaned@example.com',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Orphaned User', $user->name);
        $this->assertSame('updated.orphaned@example.com', $user->email);
    }

    public function test_profile_update_only_updates_changed_fields(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@example.com',
        ]);
        $user->assignRole($this->companyRole);

        $company = Company::factory()->create([
            'user_id' => $user->id,
            'name' => 'Company User',
            'email' => 'company@example.com',
            'phone' => '123-456-7890',
            'address' => 'Original Address',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Company User',
                'email' => 'company@example.com',
                'phone' => '999-888-7777',
                'address' => 'Original Address',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $company->refresh();
        $this->assertSame('Company User', $company->name);
        $this->assertSame('company@example.com', $company->email);
        $this->assertSame('999-888-7777', $company->phone);
        $this->assertSame('Original Address', $company->address);
    }

    public function test_company_profile_updates_when_user_data_changes(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Original Company User',
            'email' => 'original@example.com',
        ]);
        $user->assignRole($this->companyRole);

        $company = Company::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Company User',
            'email' => 'original@example.com',
            'phone' => '123-456-7890',
            'address' => 'Original Address',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Company User',
                'email' => 'updated@example.com',
                'phone' => '999-888-7777',
                'address' => 'Updated Address',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Company User', $user->name);
        $this->assertSame('updated@example.com', $user->email);

        $company->refresh();
        $this->assertSame('Updated Company User', $company->name);
        $this->assertSame('updated@example.com', $company->email);
        $this->assertSame('999-888-7777', $company->phone);
        $this->assertSame('Updated Address', $company->address);
    }

    public function test_profile_validation_errors_are_handled_correctly(): void
    {
        $anotherUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'email' => 'another@example.com'
        ]);

        $response = $this
            ->actingAs($this->adminUser)
            ->patch('/profile', [
                'name' => '',
                'email' => 'another@example.com',
            ]);

        $response->assertSessionHasErrors(['name', 'email']);

        $this->adminUser->refresh();
        $this->assertNotEmpty($this->adminUser->name);
        $this->assertNotSame('another@example.com', $this->adminUser->email);
    }

    public function test_email_verification_reset_when_email_changes_for_role_users(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'original@example.com',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($this->companyRole);

        $company = Company::factory()->create([
            'user_id' => $user->id,
            'email' => 'original@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Company User',
                'email' => 'newemail@example.com',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('newemail@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        $company->refresh();
        $this->assertSame('newemail@example.com', $company->email);
    }
}
