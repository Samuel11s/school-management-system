<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

/**
 * Behind a TLS-terminating proxy (Render, load balancers) the application
 * must generate https URLs, or browsers block assets and forms as mixed
 * content.
 */
class TrustedProxyTest extends TestCase
{
    private const FORWARDED = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'school.example.org',
        'X-Forwarded-Port' => '443',
    ];

    protected function tearDown(): void
    {
        putenv('TRUSTED_PROXIES');
        TrustProxies::flushState();

        parent::tearDown();
    }

    private function bootWithTrustedProxies(?string $value): void
    {
        putenv($value === null ? 'TRUSTED_PROXIES' : "TRUSTED_PROXIES={$value}");
        TrustProxies::flushState();

        // Re-run bootstrap/app.php so the middleware reads the new value.
        $this->refreshApplication();
        $this->withoutVite();
    }

    public function test_wildcard_trusts_the_forwarded_https_scheme(): void
    {
        $this->bootWithTrustedProxies('*');

        $this->withHeaders(self::FORWARDED)->get('/login')
            ->assertOk()
            ->assertSee('action="https://school.example.org/login"', false);
    }

    public function test_forwarded_headers_are_ignored_when_no_proxy_is_trusted(): void
    {
        $this->bootWithTrustedProxies(null);

        $this->withHeaders(self::FORWARDED)->get('/login')
            ->assertOk()
            ->assertSee('action="http://', false)
            ->assertDontSee('school.example.org');
    }
}
