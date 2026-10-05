<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;
use Tests\TestCase;

/**
 * Public self-registration is closed. It used to create an ADMIN account for any address on a
 * hard-coded domain with no mailbox proof (security hotfix). See also
 * PublicAccountCreationClosedTest, which boots the app as `local` to cover the old route gate.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    public function test_registration_screen_is_not_served(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_anonymous_post_cannot_create_a_user_on_any_formerly_allowed_domain(): void
    {
        Mail::fake();
        RecaptchaV3::shouldReceive('verify')->andReturn(1.0);

        foreach (['example.com', 'test.com', 'citytravelers.co'] as $i => $domain) {
            $this->post('/register', [
                'name' => "Test User {$i}",
                'email' => "test{$i}@{$domain}",
                'password' => 'password',
                'password_confirmation' => 'password',
                'g-recaptcha-response' => 'test-token',
            ])->assertNotFound();

            $this->assertDatabaseMissing('users', ['email' => "test{$i}@{$domain}"]);
        }

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }
}
