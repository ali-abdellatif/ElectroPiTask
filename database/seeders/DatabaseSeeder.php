<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates a fixed demo account whose data covers every status, priority, and
     * due-date case the API exposes, plus a handful of random users so that
     * ownership scoping is exercised against a populated database.
     */
    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        $this->seedProjectsFor($demo);

        User::factory()
            ->count(4)
            ->create()
            ->each(fn (User $user) => $this->seedProjectsFor($user));

        $this->command?->info(sprintf(
            'Seeded %d users, %d projects, %d tasks. Demo login: demo@example.com / password',
            User::count(),
            Project::count(),
            Task::count(),
        ));
    }

    /**
     * Give a user one project per status, each with a realistic spread of tasks.
     */
    private function seedProjectsFor(User $user): void
    {
        $active = Project::factory()->active()->for($user)->create();
        $completed = Project::factory()->completed()->for($user)->create();
        $archived = Project::factory()->archived()->for($user)->create();

        // An active project in mid-flight: work in every state, including
        // overdue tasks and one that was finished after its due date.
        Task::factory()->count(4)->todo()->for($active)->create();
        Task::factory()->count(3)->inProgress()->for($active)->create();
        Task::factory()->count(3)->done()->for($active)->create();
        Task::factory()->count(2)->overdue()->for($active)->create();
        Task::factory()->completedLate()->for($active)->create();
        Task::factory()->withoutDueDate()->priority(TaskPriority::Low)->for($active)->create();

        // A finished project: everything done, nothing overdue.
        Task::factory()->count(5)->done()->for($completed)->create();

        // An archived project left with stale open work.
        Task::factory()->count(2)->todo()->for($archived)->create();
        Task::factory()->overdue()->priority(TaskPriority::High)->for($archived)->create();
    }
}
