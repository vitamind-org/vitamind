<?php

namespace VitaminD\Plugins\Workspace\Traits;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\Core\Models\User;

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
