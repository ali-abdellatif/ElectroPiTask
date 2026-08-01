<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\User;

class DashboardService
{
    /**
     * Summarise a user's workload.
     *
     * Every figure is scoped to the given user: projects through the ownership
     * relation, tasks through it as well, so another user's rows can never be
     * counted here.
     *
     * @return array{
     *     total_projects: int,
     *     active_projects: int,
     *     total_tasks: int,
     *     completed_tasks: int,
     *     pending_tasks: int,
     *     overdue_tasks: int
     * }
     */
    public function statsFor(User $user): array
    {
        $totalProjects = $user->projects()->count();
        $activeProjects = $user->projects()->where('status', ProjectStatus::Active)->count();

        $totalTasks = $user->tasks()->count();
        $completedTasks = $user->tasks()->where('tasks.status', TaskStatus::Done)->count();
        $overdueTasks = $user->tasks()->overdue()->count();

        return [
            'total_projects' => $totalProjects,
            'active_projects' => $activeProjects,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            // Anything not done is still outstanding, so this needs no query of
            // its own and cannot drift out of step with the completed count.
            'pending_tasks' => $totalTasks - $completedTasks,
            'overdue_tasks' => $overdueTasks,
        ];
    }
}
