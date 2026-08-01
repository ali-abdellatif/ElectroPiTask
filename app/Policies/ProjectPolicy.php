<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * A user may only ever act on a project they own.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    private function owns(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }
}
