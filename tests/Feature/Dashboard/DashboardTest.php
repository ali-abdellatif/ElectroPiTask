<?php

namespace Tests\Feature\Dashboard;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * A fixture with hand-checked totals:
     *   projects  4 (2 active, 1 completed, 1 archived)
     *   tasks    14 = 5 done + 4 open-and-on-time + 5 overdue
     *   overdue   5 (two further past-due tasks are done, so they do not count)
     */
    private function seedKnownFixture(): void
    {
        $activeOne = Project::factory()->active()->for($this->user)->create();
        $activeTwo = Project::factory()->active()->for($this->user)->create();
        $completed = Project::factory()->completed()->for($this->user)->create();
        $archived = Project::factory()->archived()->for($this->user)->create();

        // done, never overdue
        Task::factory()->count(3)->for($activeOne)->done()->withoutDueDate()->create();

        // open but not late: future date, no date, and due today
        Task::factory()->count(2)->for($activeOne)->todo()->create(['due_date' => today()->addWeek()]);
        Task::factory()->for($activeTwo)->inProgress()->withoutDueDate()->create();
        Task::factory()->for($activeTwo)->todo()->create(['due_date' => today()]);

        // overdue, including work inside completed and archived projects
        Task::factory()->count(3)->for($activeOne)->todo()->create(['due_date' => today()->subDay()]);
        Task::factory()->for($completed)->inProgress()->create(['due_date' => today()->subMonth()]);
        Task::factory()->for($archived)->todo()->create(['due_date' => today()->subYear()]);

        // past due but finished — the case a naive overdue count gets wrong
        Task::factory()->count(2)->for($activeOne)->completedLate()->create();
    }

    public function test_the_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_it_reports_the_expected_figures(): void
    {
        $this->seedKnownFixture();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'total_projects' => 4,
                    'active_projects' => 2,
                    'total_tasks' => 14,
                    'completed_tasks' => 5,
                    'pending_tasks' => 9,
                    'overdue_tasks' => 5,
                ],
            ]);
    }

    /**
     * A past-due task that is already done is not overdue. Counting by date
     * alone would report 7 here instead of 5.
     */
    public function test_completed_tasks_are_never_counted_as_overdue(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->count(3)->for($project)->overdue()->create();
        Task::factory()->count(4)->for($project)->completedLate()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.overdue_tasks', 3)
            ->assertJsonPath('data.completed_tasks', 4);
    }

    public function test_a_task_due_today_is_not_yet_overdue(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->for($project)->todo()->create(['due_date' => today()]);
        Task::factory()->for($project)->todo()->create(['due_date' => today()->subDay()]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.overdue_tasks', 1);
    }

    public function test_tasks_without_a_due_date_are_never_overdue(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->count(5)->for($project)->todo()->withoutDueDate()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.pending_tasks', 5)
            ->assertJsonPath('data.overdue_tasks', 0);
    }

    public function test_it_ignores_other_users_data(): void
    {
        $this->seedKnownFixture();

        $stranger = User::factory()->create();
        $strangerProject = Project::factory()->active()->for($stranger)->create();
        Task::factory()->count(30)->for($strangerProject)->overdue()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_projects', 4)
            ->assertJsonPath('data.total_tasks', 14)
            ->assertJsonPath('data.overdue_tasks', 5);
    }

    public function test_soft_deleted_projects_and_their_tasks_drop_out(): void
    {
        $kept = Project::factory()->active()->for($this->user)->create();
        $removed = Project::factory()->active()->for($this->user)->create();
        Task::factory()->count(2)->for($kept)->todo()->withoutDueDate()->create();
        Task::factory()->count(6)->for($removed)->overdue()->create();

        $removed->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_projects', 1)
            ->assertJsonPath('data.total_tasks', 2)
            ->assertJsonPath('data.overdue_tasks', 0);
    }

    public function test_soft_deleted_tasks_drop_out(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $tasks = Task::factory()->count(4)->for($project)->overdue()->create();
        $tasks->first()->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_tasks', 3)
            ->assertJsonPath('data.overdue_tasks', 3);
    }

    public function test_a_user_with_no_data_gets_zeros(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'total_projects' => 0,
                    'active_projects' => 0,
                    'total_tasks' => 0,
                    'completed_tasks' => 0,
                    'pending_tasks' => 0,
                    'overdue_tasks' => 0,
                ],
            ]);
    }

    /**
     * The dashboard must agree with what the listing endpoints report, or the
     * same data would tell two different stories.
     */
    public function test_its_figures_agree_with_the_listing_endpoints(): void
    {
        $this->seedKnownFixture();

        $acting = $this->actingAs($this->user, 'sanctum');
        $dashboard = $acting->getJson('/api/v1/dashboard')->json('data');

        $this->assertSame(
            $acting->getJson('/api/v1/projects?per_page=100')->json('meta.total'),
            $dashboard['total_projects'],
        );

        $this->assertSame(
            $acting->getJson('/api/v1/tasks?per_page=100')->json('meta.total'),
            $dashboard['total_tasks'],
        );

        $this->assertSame(
            $acting->getJson('/api/v1/tasks?status=done&per_page=100')->json('meta.total'),
            $dashboard['completed_tasks'],
        );

        // The count must match the rows the listing itself flags as overdue,
        // which is what keeps scopeOverdue() and isOverdue() honest.
        $flagged = collect($acting->getJson('/api/v1/tasks?per_page=100')->json('data'))
            ->where('is_overdue', true)
            ->count();

        $this->assertSame($flagged, $dashboard['overdue_tasks']);
    }
}
