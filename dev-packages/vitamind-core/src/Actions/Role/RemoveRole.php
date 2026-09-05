<?php

namespace VitaminD\Core\Actions\Role;

use VitaminD\Core\Models\User;
use VitaminD\Core\Models\UserRoleAssignment;

class RemoveRole
{
    public function remove(User $user, string $role, ?string $scopeType = null, ?int $scopeId = null): void
    {
        UserRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->delete();
    }
}
