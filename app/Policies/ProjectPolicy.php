<?php

namespace App\Policies;

use App\Domain\Projects\Models\Project;
use App\Models\User;

/**
 * v1.0: the single owner can do everything. Member/Viewer rules arrive in v1.1.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    /**
     * Edit the .env (write-only).
     */
    public function manageEnvironment(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    /**
     * Start a deployment (DEP-1).
     */
    public function deploy(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    /**
     * Run Initialize on the server.
     */
    public function initialize(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->role->canManageInfrastructure();
    }
}
