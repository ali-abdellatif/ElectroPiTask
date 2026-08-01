<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    public function test_task_endpoints_require_authentication(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks")->assertUnauthorized();
        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", ['title' => 'X'])->assertUnauthorized();
        $this->getJson("/api/v1/tasks/{$task->id}")->assertUnauthorized();
        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertUnauthorized();
    }

    public function test_a_user_can_create_a_task_in_their_project(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'title' => 'Write the docs',
                'description' => 'Cover every endpoint.',
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => '2026-12-31',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Write the docs')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.due_date', '2026-12-31')
            ->assertJsonPath('data.project_id', $this->project->id);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Write the docs',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_a_task_created_without_optional_fields_uses_defaults(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", ['title' => 'Bare task'])
            ->assertCreated()
            ->assertJsonPath('data.priority', TaskPriority::Medium->value)
            ->assertJsonPath('data.status', TaskStatus::Todo->value)
            ->assertJsonPath('data.due_date', null)
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_creating_a_task_validates_its_input(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'priority' => 'urgent',
                'status' => 'blocked',
                'due_date' => 'someday',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority', 'status', 'due_date']);
    }

    public function test_creating_a_task_in_a_missing_project_returns_not_found(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/projects/999999/tasks', ['title' => 'X'])
            ->assertNotFound();
    }

    public function test_a_user_can_read_update_and_delete_their_own_task(): void
    {
        $task = Task::factory()->for($this->project)->create([
            'title' => 'Original',
            'priority' => TaskPriority::High,
        ]);

        $acting = $this->actingAs($this->user, 'sanctum');

        $acting->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);

        $acting->putJson("/api/v1/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.title', 'Original')
            ->assertJsonPath('data.priority', 'high');

        $acting->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

        $this->assertSoftDeleted($task);
    }

    public function test_a_due_date_can_be_cleared_on_update(): void
    {
        $task = Task::factory()->for($this->project)->create(['due_date' => '2026-12-31']);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}", ['due_date' => null])
            ->assertOk()
            ->assertJsonPath('data.due_date', null);

        $this->assertNull($task->fresh()->due_date);
    }

    public function test_updating_a_task_validates_its_input(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}", ['title' => '', 'status' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'status']);
    }

    /**
     * A task must not be moved into a project by sending project_id.
     */
    public function test_a_task_cannot_be_moved_to_another_project(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $otherProject = Project::factory()->for(User::factory())->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}", ['project_id' => $otherProject->id])
            ->assertOk();

        $this->assertSame($this->project->id, $task->fresh()->project_id);
    }

    public function test_a_user_cannot_touch_tasks_in_another_users_project(): void
    {
        $stranger = User::factory()->create();
        $strangerProject = Project::factory()->for($stranger)->create();
        $strangerTask = Task::factory()->for($strangerProject)->create(['title' => 'Not Yours']);

        $acting = $this->actingAs($this->user, 'sanctum');

        $acting->getJson("/api/v1/projects/{$strangerProject->id}/tasks")->assertForbidden();
        $acting->postJson("/api/v1/projects/{$strangerProject->id}/tasks", ['title' => 'Intruder'])->assertForbidden();
        $acting->getJson("/api/v1/tasks/{$strangerTask->id}")->assertForbidden();
        $acting->putJson("/api/v1/tasks/{$strangerTask->id}", ['title' => 'Hijacked'])->assertForbidden();
        $acting->deleteJson("/api/v1/tasks/{$strangerTask->id}")->assertForbidden();

        $this->assertSame('Not Yours', $strangerTask->fresh()->title);
        $this->assertSame(1, $strangerProject->tasks()->count());
    }

    public function test_the_index_lists_only_the_projects_own_tasks(): void
    {
        Task::factory()->count(3)->for($this->project)->create();
        Task::factory()->count(2)->for(Project::factory()->for($this->user))->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_the_index_hides_soft_deleted_tasks(): void
    {
        $tasks = Task::factory()->count(3)->for($this->project)->create();
        $tasks->first()->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_index_paginates(): void
    {
        Task::factory()->count(20)->for($this->project)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?per_page=5")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 4);
    }

    /**
     * Build a fixed matrix: one task per (status, priority) pair, so every
     * filter combination has a distinct expected count.
     */
    private function seedFilterMatrix(): void
    {
        foreach (TaskStatus::cases() as $status) {
            foreach (TaskPriority::cases() as $priority) {
                Task::factory()->for($this->project)->create([
                    'title' => "{$status->value} {$priority->value}",
                    'status' => $status,
                    'priority' => $priority,
                ]);
            }
        }
    }

    public function test_tasks_can_be_filtered_by_status(): void
    {
        $this->seedFilterMatrix();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?status=todo")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_tasks_can_be_filtered_by_priority(): void
    {
        $this->seedFilterMatrix();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?priority=high")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    /**
     * The filters must narrow the same query rather than replacing each other.
     */
    public function test_status_priority_and_search_can_be_combined(): void
    {
        $this->seedFilterMatrix();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?status=todo&priority=high&search=todo");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'todo high')
            ->assertJsonPath('data.0.status', 'todo')
            ->assertJsonPath('data.0.priority', 'high');
    }

    public function test_a_combination_that_matches_nothing_returns_an_empty_page(): void
    {
        $this->seedFilterMatrix();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?status=done&search=todo")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_search_matches_part_of_a_title_regardless_of_case(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Build the API endpoints']);
        Task::factory()->for($this->project)->create(['title' => 'Document the API']);
        Task::factory()->for($this->project)->create(['title' => 'Refactor the parser']);

        $acting = $this->actingAs($this->user, 'sanctum');

        $acting->getJson("/api/v1/projects/{$this->project->id}/tasks?search=api")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $acting->getJson("/api/v1/projects/{$this->project->id}/tasks?search=API")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_a_blank_search_term_is_treated_as_no_filter(): void
    {
        Task::factory()->count(3)->for($this->project)->create();

        $acting = $this->actingAs($this->user, 'sanctum');

        $acting->getJson("/api/v1/projects/{$this->project->id}/tasks?search=")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $acting->getJson("/api/v1/projects/{$this->project->id}/tasks?search=%20%20")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    /**
     * An unknown filter value is a client error, not an empty result set.
     */
    public function test_unknown_filter_values_are_rejected(): void
    {
        $acting = $this->actingAs($this->user, 'sanctum');
        $url = "/api/v1/projects/{$this->project->id}/tasks";

        $acting->getJson("{$url}?status=blah")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $acting->getJson("{$url}?priority=urgent")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $acting->getJson("{$url}?status=blah&priority=urgent")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'priority']);

        $acting->getJson("{$url}?status=TODO")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_the_search_term_length_is_capped(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$this->project->id}/tasks?search=".str_repeat('a', 300))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_the_overdue_flag_reflects_the_due_date_and_status(): void
    {
        $overdue = Task::factory()->for($this->project)->create([
            'due_date' => today()->subDay(),
            'status' => TaskStatus::Todo,
        ]);
        $lateButDone = Task::factory()->for($this->project)->create([
            'due_date' => today()->subDay(),
            'status' => TaskStatus::Done,
        ]);
        $dueToday = Task::factory()->for($this->project)->create([
            'due_date' => today(),
            'status' => TaskStatus::Todo,
        ]);

        $acting = $this->actingAs($this->user, 'sanctum');

        $acting->getJson("/api/v1/tasks/{$overdue->id}")->assertJsonPath('data.is_overdue', true);
        $acting->getJson("/api/v1/tasks/{$lateButDone->id}")->assertJsonPath('data.is_overdue', false);
        $acting->getJson("/api/v1/tasks/{$dueToday->id}")->assertJsonPath('data.is_overdue', false);
    }
}
