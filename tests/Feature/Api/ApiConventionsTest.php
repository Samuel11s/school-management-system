<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiConventionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_resources_use_the_error_envelope(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/students/999999')->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        $this->getJson('/api/v1/does-not-exist')->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_unsupported_methods_return_405(): void
    {
        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/v1/enrollments')->assertStatus(405)->assertExactJson(['message' => 'Method not allowed.']);
    }

    public function test_api_responses_are_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/students')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    }

    public function test_openapi_document_is_generated(): void
    {
        $this->actingAs($this->admin())
            ->get('/docs/api.json')
            ->assertOk()
            ->assertJsonPath('info.version', '1.0.0')
            ->assertJsonStructure(['openapi', 'paths' => ['/auth/token', '/students', '/enrollments', '/grades', '/attendance']]);
    }
}
