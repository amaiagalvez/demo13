<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Http\Middleware\EnsureUserIsActive;

class LogViewerAccessTest extends TestCase
{
    private const ALLOWED_EMAIL = 'ops@example.test';

    private const LOG_VIEWER_PATH = '/log-viewer';

    private const LOG_VIEWER_API_PATH = '/log-viewer/api/folders';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('log-viewer.allowed_emails', [self::ALLOWED_EMAIL]);
    }

    public function test_guests_are_redirected_to_login_from_the_log_viewer(): void
    {
        $this->get(self::LOG_VIEWER_PATH)->assertRedirect(route('login'));
    }

    public function test_logged_in_users_outside_the_allowlist_are_forbidden(): void
    {
        $this->withoutMiddleware(EnsureUserIsActive::class)
            ->actingAs(User::factory()->make()->forceFill(['id' => 1]))
            ->get(self::LOG_VIEWER_PATH)
            ->assertForbidden();
    }

    public function test_allowed_users_can_read_the_log_viewer(): void
    {
        $this->withoutMiddleware(EnsureUserIsActive::class)
            ->actingAs(User::factory()->make(['email' => self::ALLOWED_EMAIL])->forceFill(['id' => 1]))
            ->get(self::LOG_VIEWER_PATH)
            ->assertOk();
    }

    /**
     * The UI reads the entries through the package API, which runs its own session middleware.
     */
    public function test_allowed_users_can_read_the_log_viewer_api(): void
    {
        $this->withoutMiddleware(EnsureUserIsActive::class)
            ->actingAs(User::factory()->make(['email' => self::ALLOWED_EMAIL])->forceFill(['id' => 1]))
            ->getJson(self::LOG_VIEWER_API_PATH)
            ->assertOk();
    }

    public function test_the_log_viewer_api_rejects_guests(): void
    {
        $this->getJson(self::LOG_VIEWER_API_PATH)->assertUnauthorized();
    }

    public function test_the_log_viewer_api_rejects_users_outside_the_allowlist(): void
    {
        $this->withoutMiddleware(EnsureUserIsActive::class)
            ->actingAs(User::factory()->make()->forceFill(['id' => 1]))
            ->getJson(self::LOG_VIEWER_API_PATH)
            ->assertForbidden();
    }
}
