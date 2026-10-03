<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_EMAIL = 'ops@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('log-viewer.allowed_emails', [self::ALLOWED_EMAIL]);
    }

    public function test_guests_are_redirected_to_login_from_the_log_viewer(): void
    {
        $this->get('/log-viewer')->assertRedirect(route('login'));
    }

    public function test_logged_in_users_outside_the_allowlist_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/log-viewer')
            ->assertForbidden();
    }

    public function test_allowed_users_can_read_the_log_viewer(): void
    {
        $this->actingAs(User::factory()->create(['email' => self::ALLOWED_EMAIL]))
            ->get('/log-viewer')
            ->assertOk();
    }

    /**
     * The UI reads the entries through the package API, which runs its own session middleware.
     */
    public function test_allowed_users_can_read_the_log_viewer_api_with_their_session(): void
    {
        $this->actingAs(User::factory()->create(['email' => self::ALLOWED_EMAIL]))
            ->getJson('/log-viewer/api/folders')
            ->assertOk();
    }

    /**
     * Regression: the API middleware order decides whether the session cookie is honoured. A real
     * login is replayed here because `actingAs` would short circuit the whole session handling.
     */
    public function test_the_log_viewer_api_accepts_a_real_browser_session(): void
    {
        config()->set('session.driver', 'database');
        config()->set('log-viewer.api_stateful_domains', ['localhost']);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        $user = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $cookieName = config('session.cookie');
        $response->assertRedirect()->assertCookie($cookieName);
        $cookie = $response->getCookie($cookieName, false);
        $this->assertNotNull($cookie);

        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        $this->withUnencryptedCookie($cookieName, $cookie->getValue())
            ->withCredentials()
            ->withHeader('referer', 'http://localhost/log-viewer')
            ->getJson('/log-viewer/api/folders')
            ->assertOk();
    }

    public function test_the_log_viewer_api_rejects_guests(): void
    {
        $this->getJson('/log-viewer/api/folders')->assertUnauthorized();
    }

    public function test_the_log_viewer_api_rejects_users_outside_the_allowlist(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/log-viewer/api/folders')
            ->assertForbidden();
    }
}
