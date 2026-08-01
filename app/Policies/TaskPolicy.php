<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Tasks carry no owner of their own — a user may act on a task only when
     * they own the project it belongs to.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->ownsParentProject($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->ownsParentProject($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->ownsParentProject($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->ownsParentProject($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->ownsParentProject($user, $task);
    }

    /**
     * Read the owner straight off the parent project.
     *
     * `withTrashed()` matters here: deleting a project leaves its tasks intact,
     * so without it a task under a soft-deleted project would resolve to no
     * project at all and the check would misbehave rather than simply deny.
     */
    private function ownsParentProject(User $user, Task $task): bool
    {
        return $user->id === $task->project()->withTrashed()->value('user_id');
    }
}
