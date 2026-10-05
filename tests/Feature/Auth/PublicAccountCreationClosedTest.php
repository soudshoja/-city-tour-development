<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;
use Tests\TestCase;

/**
 * Security hotfix: no anonymous request may create an account or a session.
 *
 * Defect 1: POST /register (RegisteredUserController::storeAdmin) created a role_id=1 (ADMIN)
 *           account for any address on a hard-coded domain with no mailbox proof. The routes were
 *           only registered when APP_ENV=local, which a deployed development site can have, so
 *           this class boots the application as `local` to reproduce the deployed condition.
 * Defect 2: POST /check_email signed anyone in, with no password, as any existing user whose
 *           first_login flag was false (the normal state after the first sign-in).
 */
class PublicAccountCreationClosedTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private ?string $previousEnv = null;

    protected function setUp(): void
    {
        // Routes are registered at boot, so the environment must be `local` BEFORE the app is built.
        $this->previousEnv = getenv('APP_ENV') === false ? null : getenv('APP_ENV');
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';

        parent::setUp();

        $this->assertSame('local', $this->app->environment(), 'harness must boot the app as local');

        // Outside `testing` CSRF is enforced; an attacker simply fetches a token first, so it is
        // not a defence. Disable it so these tests reach the code under test.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousEnv === null) {
            putenv('APP_ENV');
            unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
        } else {
            putenv('APP_ENV='.$this->previousEnv);
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $this->previousEnv;
        }
    }

    public function test_anonymous_post_register_creates_no_user(): void
    {
        Mail::fake();
        RecaptchaV3::shouldReceive('verify')->andReturn(1.0);

        $response = $this->post('/register', [
            'name' => 'Anyone',
            'email' => 'nobody-owns-this@example.com',
            'password' => 'Chosen-by-the-visitor-1',
            'password_confirmation' => 'Chosen-by-the-visitor-1',
            'g-recaptcha-response' => 'token',
        ]);

        $this->assertNull(
            User::where('email', 'nobody-owns-this@example.com')->first(),
            'an anonymous POST /register created a user'
        );
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 404], true)
                || ($response->isRedirect() && str_contains((string) $response->headers->get('Location'), '/login')),
            'unexpected response '.$response->getStatusCode()
        );
    }

    public function test_the_register_page_is_not_served_to_an_anonymous_visitor(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_no_register_routes_exist_in_any_environment(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.admin'));
    }

    public function test_the_invite_gated_company_registration_still_exists(): void
    {
        $paths = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->all();
        $this->assertContains('register/company/{token}', $paths);
    }

    public function test_check_email_never_signs_anyone_in_without_a_password(): void
    {
        $user = User::factory()->create(['email' => 'victim@example.org', 'first_login' => false]);

        $this->assertGuest();
        $this->post('/check_email', ['email' => 'victim@example.org']);

        $this->assertGuest();
        $this->assertNull(session('login_web_'.sha1(\Illuminate\Auth\SessionGuard::class)));
        $this->assertSame($user->id, User::where('email', 'victim@example.org')->value('id'));
    }

    public function test_check_email_for_a_first_login_user_still_routes_to_the_password_step(): void
    {
        User::factory()->create(['email' => 'newbie@example.org', 'first_login' => true]);

        $this->post('/check_email', ['email' => 'newbie@example.org'])
            ->assertRedirect(route('password'));
        $this->assertGuest();
    }
}
