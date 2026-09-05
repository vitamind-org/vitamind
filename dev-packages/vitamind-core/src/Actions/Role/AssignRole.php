<?php

namespace VitaminD\Core\Actions\Role;

use VitaminD\Core\Models\User;
use VitaminD\Core\Models\UserRoleAssignment;

class AssignRole
{
    /**
     * Idempotent by design (`firstOrCreate`): assigning a role the user
     * already holds in that scope is a no-op rather than a duplicate row,
     * which is also what the `user_roles` unique constraint enforces at the
     * database level for any caller that inserts directly.
     */
    public function assign(User $user, string $role, ?string $scopeType = null, ?int $scopeId = null): UserRoleAssignment
    {
        return UserRoleAssignment::query()->firstOrCreate([
            'user_id' => $user->id,
            'role' => $role,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
    }
}
