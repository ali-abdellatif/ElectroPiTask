<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_project_endpoints_require_authentication(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $this->getJson('/api/v1/projects')->assertUnauthorized();
        $this->postJson('/api/v1/projects', ['name' => 'X'])->assertUnauthorized();
        $this->getJson("/api/v1/projects/{$project->id}")->assertUnauthorized();
        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/v1/projects/{$project->id}")->assertUnauthorized();
    }

    public function test_index_returns_only_the_authenticated_users_projects(): void
    {
        Project::factory()->count(2)->for($this->user)->create();
        Project::factory()->count(3)->for(User::factory())->create();

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_exposes_a_task_count_without_loading_tasks(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->count(4)->for($project)->create();

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonPath('data.0.tasks_count', 4)
            ->assertJsonMissingPath('data.0.tasks');
    }

    public function test_index_hides_soft_deleted_projects(): void
    {
        $projects = Project::factory()->count(3)->for($this->user)->create();
        $projects->first()->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_paginates_with_a_default_page_size(): void
    {
        Project::factory()->count(20)->for($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 2);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function perPageProvider(): array
    {
        return [
            'explicit page size' => ['per_page=5', 5],
            'capped at the maximum' => ['per_page=9999', 20],
            'non-numeric falls back to default' => ['per_page=abc', 15],
            'zero is raised to one' => ['per_page=0', 1],
            'negative is raised to one' => ['per_page=-10', 1],
        ];
    }

    #[DataProvider('perPageProvider')]
    public function test_index_clamps_the_requested_page_size(string $query, int $expected): void
    {
        Project::factory()->count(20)->for($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects?{$query}")
            ->assertOk()
            ->assertJsonCount($expected, 'data');
    }

    public function test_a_user_can_create_a_project(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/projects', [
            'name' => 'Website Redesign',
            'description' => 'Rebuild the marketing site.',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Website Redesign')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.tasks_count', 0);

        $this->assertDatabaseHas('projects', [
            'name' => 'Website Redesign',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_a_created_project_defaults_to_active(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/projects', ['name' => 'No status given'])
            ->assertCreated()
            ->assertJsonPath('data.status', ProjectStatus::Active->value);
    }

    public function test_creating_a_project_validates_its_input(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/projects', ['status' => 'on-hold'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'status']);
    }

    /**
     * The owner comes from the authenticated user, never from the payload.
     */
    public function test_a_project_cannot_be_created_on_behalf_of_another_user(): void
    {
        $victim = User::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/projects', ['name' => 'Planted', 'user_id' => $victim->id])
            ->assertCreated();

        $this->assertDatabaseHas('projects', ['name' => 'Planted', 'user_id' => $this->user->id]);
        $this->assertSame(0, $victim->projects()->count());
    }

    public function test_a_user_can_view_their_own_project(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id);
    }

    public function test_viewing_a_missing_project_returns_not_found(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects/999999')
            ->assertNotFound();
    }

    public function test_a_user_can_update_their_own_project(): void
    {
        $project = Project::factory()->for($this->user)->create([
            'name' => 'Original Name',
            'status' => ProjectStatus::Active,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.name', 'Original Name');

        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);
    }

    public function test_updating_a_project_validates_its_input(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_a_project_cannot_be_reassigned_to_another_user(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $attacker = User::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}", ['user_id' => $attacker->id])
            ->assertOk();

        $this->assertSame($this->user->id, $project->fresh()->user_id);
    }

    public function test_a_user_can_delete_their_own_project(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($project);
    }

    public function test_deleting_a_project_keeps_its_tasks_restorable(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_a_user_cannot_read_or_change_another_users_project(): void
    {
        $stranger = User::factory()->create();
        $project = Project::factory()->for($stranger)->create(['name' => 'Not Yours']);

        $acting = $this->actingAs($this->user, 'sanctum');

        $acting->getJson("/api/v1/projects/{$project->id}")->assertForbidden();
        $acting->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hijacked'])->assertForbidden();
        $acting->deleteJson("/api/v1/projects/{$project->id}")->assertForbidden();

        $this->assertSame('Not Yours', $project->fresh()->name);
        $this->assertNotSoftDeleted($project);
    }
}
