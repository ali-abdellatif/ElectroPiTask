<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalTaskListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/tasks')->assertUnauthorized();
    }

    public function test_it_spans_every_project_the_user_owns(): void
    {
        Task::factory()->count(2)->for(Project::factory()->for($this->user))->create();
        Task::factory()->count(3)->for(Project::factory()->for($this->user))->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_it_excludes_other_users_tasks(): void
    {
        Task::factory()->count(2)->for(Project::factory()->for($this->user))->create();
        Task::factory()->count(7)->for(Project::factory()->for(User::factory()))->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_hides_soft_deleted_tasks(): void
    {
        $tasks = Task::factory()->count(3)->for(Project::factory()->for($this->user))->create();
        $tasks->first()->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * The listing joins through projects, so work under a deleted project drops
     * out of the cross-project view even though the task rows still exist.
     */
    public function test_it_hides_tasks_whose_project_was_deleted(): void
    {
        $kept = Project::factory()->for($this->user)->create();
        $removed = Project::factory()->for($this->user)->create();
        Task::factory()->count(2)->for($kept)->create();
        Task::factory()->count(4)->for($removed)->create();

        $removed->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertSame(4, Task::where('project_id', $removed->id)->count());
    }

    public function test_it_embeds_the_owning_project_of_each_task(): void
    {
        $project = Project::factory()->for($this->user)->create(['name' => 'Website Redesign']);
        Task::factory()->for($project)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.project.id', $project->id)
            ->assertJsonPath('data.0.project.name', 'Website Redesign');
    }

    public function test_it_paginates(): void
    {
        Task::factory()->count(20)->for(Project::factory()->for($this->user))->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/tasks?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 4);
    }

    /**
     * The same filters as the per-project listing, applied across projects.
     */
    public function test_filters_and_search_combine_across_projects(): void
    {
        $alpha = Project::factory()->for($this->user)->create();
        $beta = Project::factory()->for($this->user)->create();

        Task::factory()->for($alpha)->create([
            'title' => 'Ship the API', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::High,
        ]);
        Task::factory()->for($beta)->create([
            'title' => 'Ship the docs', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::High,
        ]);
        Task::factory()->for($beta)->create([
            'title' => 'Ship the API v2', 'status' => TaskStatus::Done, 'priority' => TaskPriority::High,
        ]);
        Task::factory()->for($alpha)->create([
            'title' => 'Ship the API v3', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Low,
        ]);

        $acting = $this->actingAs($this->user, 'sanctum');

        $acting->getJson('/api/v1/tasks?search=Ship')->assertJsonPath('meta.total', 4);
        $acting->getJson('/api/v1/tasks?search=API')->assertJsonPath('meta.total', 3);
        $acting->getJson('/api/v1/tasks?status=todo')->assertJsonPath('meta.total', 3);
        $acting->getJson('/api/v1/tasks?priority=high')->assertJsonPath('meta.total', 3);
        $acting->getJson('/api/v1/tasks?status=todo&priority=high')->assertJsonPath('meta.total', 2);

        // Narrows to the single alpha task, proving the filters stack across projects.
        $acting->getJson('/api/v1/tasks?status=todo&priority=high&search=API')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Ship the API')
            ->assertJsonPath('data.0.project.id', $alpha->id);
    }

    public function test_unknown_filter_values_are_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/tasks?status=blah&priority=urgent')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'priority']);
    }
}
