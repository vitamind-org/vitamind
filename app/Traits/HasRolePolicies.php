<?php

namespace App\Traits;

use App\Enums\UserRole;
use App\Models\Workspace;
use App\Models\User;

trait HasRolePolicies
{
    protected function hasReadAccess(User $user, Workspace $workspace): bool
    {
        return $workspace->hasRoles($user, [
            UserRole::OWNER,
            UserRole::ADMIN,
            UserRole::USER,
        ]);
    }

    protected function hasWriteAccess(User $user, Workspace $workspace): bool
    {
        return $workspace->hasRoles($user, [
            UserRole::OWNER,
            UserRole::ADMIN,
        ]);
    }

    protected function hasOwnerAccess(User $user, Workspace $workspace): bool
    {
        return $workspace->hasRoles($user, [
            UserRole::OWNER,
        ]);
    }
}
