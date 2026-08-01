<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_record_does_not_reveal_the_model_behind_the_route(): void
    {
        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/projects/999999');

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);

        $this->assertStringNotContainsString('App\\Models', $response->getContent());
        $this->assertStringNotContainsString('No query results', $response->getContent());
    }

    public function test_an_unknown_api_route_returns_the_same_shape(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    /**
     * A client that forgets the Accept header still gets JSON, not an HTML page.
     */
    public function test_api_errors_are_json_without_an_accept_header(): void
    {
        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->get('/api/v1/projects/999999');

        $response->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_a_forbidden_action_returns_a_uniform_message(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);
    }

    public function test_an_unsupported_method_returns_405(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson('/api/v1/projects')
            ->assertStatus(405)
            ->assertExactJson(['message' => 'Method not allowed.']);
    }

    public function test_unauthenticated_requests_still_return_401(): void
    {
        $this->getJson('/api/v1/projects')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * The validation shape must survive the custom handlers untouched.
     */
    public function test_validation_errors_keep_their_message_and_errors_keys(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/projects', ['description' => 'no name'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_an_unexpected_failure_leaks_nothing_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::middleware('api')->get('/api/v1/testing-only-boom', function (): never {
            throw new RuntimeException('database credentials are hunter2');
        });

        $response = $this->getJson('/api/v1/testing-only-boom');

        $response->assertStatus(500)
            ->assertExactJson(['message' => 'Server error.']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('hunter2', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('trace', $body);
    }

    /**
     * Locally the real error must still surface, or debugging becomes guesswork.
     */
    public function test_an_unexpected_failure_is_still_detailed_when_debug_is_on(): void
    {
        config(['app.debug' => true]);
        $this->withoutExceptionHandling([RuntimeException::class]);

        Route::middleware('api')->get('/api/v1/testing-only-boom', function (): never {
            throw new RuntimeException('the real cause');
        });

        $response = $this->getJson('/api/v1/testing-only-boom');

        $response->assertStatus(500);
        $this->assertStringContainsString('the real cause', $response->getContent());
    }
}
