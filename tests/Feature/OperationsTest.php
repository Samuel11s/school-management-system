<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_readiness_endpoint_reports_dependency_checks(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.queue', 'ok')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_readiness_endpoint_fails_without_leaking_details(): void
    {
        // Point the cache at an unreachable Redis server.
        config([
            'database.redis.cache' => ['host' => '127.0.0.1', 'port' => 1, 'password' => 'secret-pass', 'database' => 0, 'timeout' => 0.2],
            'cache.default' => 'redis',
        ]);

        $response = $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'fail');

        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_responses_carry_a_request_id(): void
    {
        $this->get('/login')->assertHeader('X-Request-Id');

        $this->withHeader('X-Request-Id', 'abc-123-trace')->get('/login')->assertHeader('X-Request-Id', 'abc-123-trace');
        // Malformed ids are replaced rather than echoed back.
        $generated = $this->withHeader('X-Request-Id', '<bad id>')->get('/login')->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($generated));
    }

    public function test_maintenance_tasks_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

        $this->assertStringContainsString('model:prune', $commands);
        $this->assertStringContainsString('sanctum:prune-expired', $commands);
        $this->assertStringContainsString('queue:prune-failed', $commands);
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('school:create-admin', ['email' => 'Head@Example.com', '--name' => 'Head Teacher'])
            ->expectsQuestion('Password (min. 10 characters, mixed case and a number)', 'weak')
            ->assertFailed();

        $this->artisan('school:create-admin', ['email' => 'head@example.com'])
            ->expectsQuestion('Password (min. 10 characters, mixed case and a number)', 'Str0ngPassword')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'head@example.com')->firstOrFail()->isAdmin());

        $this->artisan('school:create-admin', ['email' => 'head@example.com', '--send-reset-link' => true])->assertFailed();
    }
}
