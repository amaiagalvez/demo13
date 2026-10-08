<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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

    /**
     * Reading entries is the only operation the viewer allows. The package authorizes downloads,
     * folder downloads and deletions through gates it resolves before acting, so each one stays
     * closed even for an allowed user.
     */
    public function test_allowed_users_cannot_download_or_delete_logs(): void
    {
        $user = User::factory()->make(['email' => self::ALLOWED_EMAIL])->forceFill(['id' => 1]);

        foreach (['downloadLogFile', 'downloadLogFolder', 'deleteLogFile', 'deleteLogFolder'] as $ability) {
            $this->assertFalse(
                Gate::forUser($user)->allows($ability),
                "[{$ability}] must stay closed to every user.",
            );
        }

        $this->assertTrue(Gate::forUser($user)->allows('viewLogViewer'));
    }
}
