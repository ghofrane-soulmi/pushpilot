<?php

namespace App\Policies;

use App\Domain\Servers\Models\Server;
use App\Models\User;

/**
 * v1.0: the single owner can do everything. Member/Viewer rules arrive in v1.1.
 */
class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Server $server): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Server $server): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Server $server): bool
    {
        return $this->canManage($user);
    }

    /**
     * Test connection and check requirements.
     */
    public function connect(User $user, Server $server): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->role->canManageInfrastructure();
    }
}
