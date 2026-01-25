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
    protected $companyUser;
    protected $company;

    public function test_profile_page_is_displayed(): void
    {   
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the admin role first
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the admin role first
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(); // ProfileController uses redirect()->back()

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
                // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the admin role first
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(); // ProfileController uses redirect()->back()

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the admin role first
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the admin role first
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    // ===== NEW COMPREHENSIVE TESTS FOR PROFILE UPDATE FUNCTIONALITY =====

    public function test_company_user_profile_update_also_updates_company_information(): void
    {
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the company role
        $companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        // Create a company user
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Original Company User',
            'email' => 'original.company@example.com',
        ]);

        $user->assignRole($companyRole);

        // Create associated company
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Company Name',
            'email' => 'original.company@example.com',
            'phone' => '123-456-7890',
            'address' => 'Original Address',
        ]);

        // Update profile
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Company User',
                'email' => 'updated.company@example.com',
                'phone' => '987-654-3210',
                'address' => 'Updated Address',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        // Assert user was updated
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
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        $companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $userCompany = User::factory()->create([
            'role_id' => $companyRole->id,
            'name' => 'Company User',
            'email' => 'company@gmail.com'
        ]);

        $userCompany->assignRole($companyRole);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id,
            'name' => 'Company Name',
            'email' => 'company@gmail.com'
        ]);

        // Create the branch role
        $branchRole = Role::create([
            'name' => 'branch',
            'guard_name' => 'web',
            'company_id' => $company->id
        ]);

        // Create a branch user
        $user = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Original Branch User',
            'email' => 'original.branch@example.com',
        ]);

        $user->assignRole($branchRole);

        // Create associated company and branch
        $branch = Branch::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => 'Original Branch Name',
            'email' => 'original.branch@example.com',
            'phone' => '111-222-3333',
            'address' => 'Original Branch Address',
        ]);

        // Update profile
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Branch User',
                'email' => 'updated.branch@example.com',
                'phone' => '444-555-6666',
                'address' => 'Updated Branch Address',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        // Assert user was updated
        $user->refresh();
        $this->assertSame('Updated Branch User', $user->name);
        $this->assertSame('updated.branch@example.com', $user->email);

        // Assert branch was also updated
        $branch->refresh();
        $this->assertSame('Updated Branch User', $branch->name);
        $this->assertSame('updated.branch@example.com', $branch->email);
        $this->assertSame('444-555-6666', $branch->phone);
        $this->assertSame('Updated Branch Address', $branch->address);
    }

    public function test_agent_user_profile_update_also_updates_agent_information(): void
    {
        $agentType = AgentType::create([
            'name' => 'salary',
        ]);

        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        $companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $this->companyUser->assignRole($companyRole);

        $branchRole = Role::create([
            'name' => 'branch',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $userBranch = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Branch User',
            'email' => 'branch@gmail.com'
        ]);

        $userBranch->assignRole($branchRole);

        $branch = Branch::factory()->create([
            'user_id' => $userBranch->id,
            'company_id' => $this->company->id,
            'name' => 'Branch Name',
            'email' => 'branch@gmail.com'
        ]);

        // Create the agent role
        $agentRole = Role::create([
            'name' => 'agent',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        // Create an agent user
        $user = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'Original Agent User',
            'email' => 'original.agent@example.com',
        ]);

        $user->assignRole($agentRole);

        // Create associated company, branch, and agent
        $agent = Agent::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'name' => 'Original Agent Name',
            'email' => 'original.agent@example.com',
            'phone_number' => '777-888-9999',
        ]);

        // Update profile
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Agent User',
                'email' => 'updated.agent@example.com',
                'phone' => '000-111-2222',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        // Assert user was updated
        $user->refresh();
        $this->assertSame('Updated Agent User', $user->name);
        $this->assertSame('updated.agent@example.com', $user->email);

        // Assert agent was also updated
        $agent->refresh();
        $this->assertSame('Updated Agent User', $agent->name);
        $this->assertSame('updated.agent@example.com', $agent->email);
        $this->assertSame('000-111-2222', $agent->phone_number);
    }

    public function test_profile_update_handles_user_without_associated_role_entities(): void
    {
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create a company role
        $companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        // Create a user with company role but no associated company entity
        $user = User::factory()->create([
            'role_id' => $companyRole->id,
            'name' => 'Orphaned User',
            'email' => 'orphaned@example.com',
        ]);

        // Update profile (should not fail even without associated company)
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Orphaned User',
                'email' => 'updated.orphaned@example.com',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        // Assert user was updated
        $user->refresh();
        $this->assertSame('Updated Orphaned User', $user->name);
        $this->assertSame('updated.orphaned@example.com', $user->email);
    }

    public function test_profile_update_only_updates_changed_fields(): void
    {   
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the company role
        $companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        // Create a company user
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@example.com',
        ]);

        $user->assignRole($companyRole);

        // Create associated company
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'name' => 'Company User', // Should match user's name initially
            'email' => 'company@example.com', // Should match user's email initially
            'phone' => '123-456-7890',
            'address' => 'Original Address',
        ]);

        // Update only the phone field (keep name and email same)
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Company User', // Same name - company name should stay same
                'email' => 'company@example.com', // Same email - company email should stay same
                'phone' => '999-888-7777', // Changed phone - company phone should update
                'address' => 'Original Address', // Same address - company address should stay same
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        // Verify only phone was updated in company (name and email unchanged because user data unchanged)
        $company->refresh();
        $this->assertSame('Company User', $company->name); // Should remain unchanged (same as user)
        $this->assertSame('company@example.com', $company->email); // Should remain unchanged (same as user)
        $this->assertSame('999-888-7777', $company->phone); // Should be updated
        $this->assertSame('Original Address', $company->address); // Should remain unchanged
    }

    public function test_company_profile_updates_when_user_data_changes(): void
    {   
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the company role
        $companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        // Create a company user
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Original Company User',
            'email' => 'original@example.com',
        ]);

        $user->assignRole($companyRole);

        // Create associated company
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Company User',
            'email' => 'original@example.com',
            'phone' => '123-456-7890',
            'address' => 'Original Address',
        ]);

        // Update user data - this should also update company data
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Company User', // Changed name
                'email' => 'updated@example.com', // Changed email
                'phone' => '999-888-7777', // Changed phone
                'address' => 'Updated Address', // Changed address
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        // Verify user was updated
        $user->refresh();
        $this->assertSame('Updated Company User', $user->name);
        $this->assertSame('updated@example.com', $user->email);

        // Verify company data was also updated to match user data
        $company->refresh();
        $this->assertSame('Updated Company User', $company->name); // Should be updated to match user
        $this->assertSame('updated@example.com', $company->email); // Should be updated to match user
        $this->assertSame('999-888-7777', $company->phone); // Should be updated
        $this->assertSame('Updated Address', $company->address); // Should be updated
    }

    public function test_profile_validation_errors_are_handled_correctly(): void
    {
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the admin role first
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $anotherUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'another@example.com'
        ]);

        // Try to update with invalid data
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => '', // Required field
                'email' => 'another@example.com', // Email already exists
            ]);

        $response->assertSessionHasErrors(['name', 'email']);
        
        // User should not be updated
        $user->refresh();
        $this->assertNotEmpty($user->name);
        $this->assertNotSame('another@example.com', $user->email);
    }

    public function test_email_verification_reset_when_email_changes_for_role_users(): void
    {   
        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create the company role
        $companyRole = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id
        ]);

        // Create a company user with verified email
        $user = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'original@example.com',
            'email_verified_at' => now(),
        ]);

        $user->assignRole($companyRole);

        // Create associated company
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'email' => 'original@example.com',
        ]);

        // Update email
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Company User',
                'email' => 'newemail@example.com',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        // Assert email verification was reset
        $user->refresh();
        $this->assertSame('newemail@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        
        // Assert company email was also updated
        $company->refresh();
        $this->assertSame('newemail@example.com', $company->email);
    }
}
