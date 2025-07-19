<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // Fake mail to prevent actual email sending
        Mail::fake();

        $response = $this->post(route('register.admin'), [
            'name' => 'Test User',
            'email' => 'test@example.com', // example.com is in allowed domains
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Since storeAdmin redirects to login, not dashboard, and doesn't auto-authenticate
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success', 'Admin registered successfully!');
        
        // Check that user was created in database
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role_id' => 1, // Admin role
        ]);
    }

    public function test_registration_rejects_invalid_domain(): void
    {
        // Fake mail to prevent actual email sending
        Mail::fake();

        $response = $this->post(route('register.admin'), [
            'name' => 'Test User',
            'email' => 'test@invalid-domain.com', // Not in allowed domains
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'The email domain is not allowed for admin registration.']);
        
        // Check that user was NOT created in database
        $this->assertDatabaseMissing('users', [
            'email' => 'test@invalid-domain.com',
        ]);
    }

    public function test_registration_allows_valid_domains(): void
    {
        // Test all allowed domains
        $allowedDomains = ['example.com', 'test.com', 'citytravelers.co'];
        
        foreach ($allowedDomains as $index => $domain) {
            // Fake mail to prevent actual email sending
            Mail::fake();

            $response = $this->post(route('register.admin'), [
                'name' => "Test User {$index}",
                'email' => "test{$index}@{$domain}",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertRedirect(route('login'));
            $response->assertSessionHas('success', 'Admin registered successfully!');
            
            // Check that user was created in database
            $this->assertDatabaseHas('users', [
                'email' => "test{$index}@{$domain}",
                'role_id' => 1,
            ]);
        }
    }

    public function test_registration_requires_password_confirmation(): void
    {
        // Fake mail to prevent actual email sending
        Mail::fake();

        $response = $this->post(route('register.admin'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password', // Mismatched confirmation
        ]);

        $response->assertSessionHasErrors(['password']);
        
        // Check that user was NOT created
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_prevents_duplicate_emails(): void
    {
        // Fake mail to prevent actual email sending
        Mail::fake();

        // Create a user first
        $response1 = $this->post(route('register.admin'), [
            'name' => 'First User',
            'email' => 'duplicate@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response1->assertRedirect(route('login'));

        // Try to register with same email
        $response2 = $this->post(route('register.admin'), [
            'name' => 'Second User',
            'email' => 'duplicate@example.com', // Same email
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response2->assertSessionHasErrors(['email']);
        
        // Check only one user exists with this email
        $this->assertEquals(1, \App\Models\User::where('email', 'duplicate@example.com')->count());
    }
}
