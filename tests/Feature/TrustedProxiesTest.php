<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

class TrustedProxiesTest extends TestCase
{
    public function test_a_listed_proxy_lets_the_client_address_come_from_the_forwarded_header(): void
    {
        TrustProxies::at([]);
        config(['app.trusted_proxies' => '10.0.0.9, 10.0.0.10']);
        (new AppServiceProvider($this->app))->boot();

        $request = $this->forwardedRequest('10.0.0.9');
        (new TrustProxies)->handle($request, static fn (Request $request): Request => $request);

        $this->assertSame('203.0.113.7', $request->ip());
    }

    public function test_without_a_configured_proxy_the_forwarded_header_is_ignored(): void
    {
        TrustProxies::at([]);
        config(['app.trusted_proxies' => null]);
        (new AppServiceProvider($this->app))->boot();

        $request = $this->forwardedRequest('10.0.0.9');
        (new TrustProxies)->handle($request, static fn (Request $request): Request => $request);

        $this->assertSame('10.0.0.9', $request->ip());
    }

    private function forwardedRequest(string $remoteAddress): Request
    {
        return Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
        ]);
    }
}
