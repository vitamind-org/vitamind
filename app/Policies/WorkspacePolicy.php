<?php

namespace App\Policies;

use App\Models\Workspace;
use App\Models\User;
use App\Traits\HasRolePolicies;

class WorkspacePolicy
{
    use HasRolePolicies;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->hasReadAccess($user, $workspace);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->hasWriteAccess($user, $workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->hasOwnerAccess($user, $workspace);
    }

    public function deleteUser(User $user, Workspace $workspace): bool
    {
        return $this->hasOwnerAccess($user, $workspace);
    }
}
