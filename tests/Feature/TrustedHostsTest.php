<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Middleware\TrustHosts;

class TrustedHostsTest extends TestCase
{
    public function test_trusted_hosts_match_only_the_configured_application_host(): void
    {
        config(['app.url' => 'https://project.example.test']);

        $trustedHosts = app(TrustHosts::class)->hosts();

        $this->assertSame(['^project\.example\.test$'], $trustedHosts);
    }
}
