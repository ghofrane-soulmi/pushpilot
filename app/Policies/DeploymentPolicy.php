<?php

namespace App\Policies;

use App\Domain\Deployments\Models\Deployment;
use App\Models\User;

/**
 * v1.0: the single owner can do everything. Member/Viewer rules arrive in v1.1.
 */
class DeploymentPolicy
{
    public function view(User $user, Deployment $deployment): bool
    {
        return $user->role->canManageInfrastructure();
    }

    public function cancel(User $user, Deployment $deployment): bool
    {
        return $user->role->canManageInfrastructure();
    }
}
