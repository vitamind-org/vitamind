<?php

namespace VitaminD\Core\Actions\Role;

use VitaminD\Core\Contracts\RoleScopeResolver;
use VitaminD\Core\Models\User;
use VitaminD\Core\Models\UserRoleAssignment;

/**
 * Makes a user's role assignments in one scope match exactly the given set
 * of role keys — assigning roles not currently held and removing held roles
 * that are no longer in the set.
 *
 * Scope defaults to the currently bound `RoleScopeResolver`, resolved
 * against `$scopeContext` (the acting/authenticated user, i.e. "whose
 * active workspace this action is happening in") rather than `$user` (the
 * target receiving the roles) — a newly created target has no ambient
 * context of its own (e.g. `current_workspace_id` is always null for a
 * brand-new user), so resolving against it would silently scope the
 * assignment to nothing reachable by any real workspace check.
 */
class SyncRoles
{
    /**
     * @param  array<int, string>  $roles
     */
    public function sync(
        User $user,
        array $roles,
        ?User $scopeContext = null,
        ?string $scopeType = null,
        ?int $scopeId = null,
    ): void {
        if ($scopeType === null && $scopeId === null) {
            $resolved = app(RoleScopeResolver::class)->resolve($scopeContext ?? $user);
            $scopeType = $resolved['scope_type'] ?? null;
            $scopeId = $resolved['scope_id'] ?? null;
        }

        $current = UserRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->pluck('role')
            ->all();

        foreach (array_diff($roles, $current) as $role) {
            app(AssignRole::class)->assign($user, $role, $scopeType, $scopeId);
        }

        foreach (array_diff($current, $roles) as $role) {
            app(RemoveRole::class)->remove($user, $role, $scopeType, $scopeId);
        }
    }
}
