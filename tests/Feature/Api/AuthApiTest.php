<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_issue_a_bearer_token(): void
    {
        $user = $this->teacher(['email' => 'teacher@example.com']);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'Teacher@Example.com',
            'password' => 'password',
            'device_name' => 'iPhone',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email', 'role']]])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'teacher')
            ->assertJsonMissingPath('data.user.password');

        $this->assertSame('iPhone', $user->tokens()->first()->name);
        $this->assertNotNull($user->tokens()->first()->expires_at);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'teacher@example.com');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->teacher(['email' => 'teacher@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'teacher@example.com', 'password' => 'nope', 'device_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    public function test_login_payload_is_validated(): void
    {
        $this->postJson('/api/v1/auth/token', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_inactive_accounts_cannot_obtain_tokens(): void
    {
        User::factory()->teacher()->inactive()->create(['email' => 'gone@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'gone@example.com', 'password' => 'password', 'device_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'This account has been deactivated.');
    }

    public function test_token_requests_are_rate_limited(): void
    {
        $this->teacher(['email' => 'teacher@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/token', ['email' => 'teacher@example.com', 'password' => 'bad', 'device_name' => 'x']);
        }

        $this->postJson('/api/v1/auth/token', ['email' => 'teacher@example.com', 'password' => 'password', 'device_name' => 'x'])
            ->assertTooManyRequests();
    }

    public function test_authenticated_endpoints_are_rate_limited(): void
    {
        config(['school.api.rate_limit' => 3]);
        Sanctum::actingAs($this->teacher());

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/auth/me')->assertOk();
        }

        $this->getJson('/api/v1/auth/me')->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_requests_without_a_token_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/students')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->teacher();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertNoContent();

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = $this->teacher();
        $token = $user->createToken('old', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_tokens_of_deactivated_users_stop_working(): void
    {
        $user = $this->teacher();
        Sanctum::actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/auth/me')->assertForbidden();
    }
}
