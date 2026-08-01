<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\OverdueTasksNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyUserOfOverdueTasks implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $user) {}

    /**
     * The overdue set is read here rather than passed in, so a task finished
     * between queueing and running does not produce a stale reminder.
     */
    public function handle(): void
    {
        $tasks = $this->user
            ->tasks()
            ->with('project')
            ->overdue()
            ->orderBy('tasks.due_date')
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $this->user->notify(new OverdueTasksNotification($tasks));
    }
}
