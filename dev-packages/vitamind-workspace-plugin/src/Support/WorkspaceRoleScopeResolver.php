<?php

namespace VitaminD\Plugins\Workspace\Support;

use VitaminD\Core\Contracts\RoleScopeResolver;
use VitaminD\Core\Models\User;

/**
 * Bound in place of `NullRoleScopeResolver` by `WorkspaceServiceProvider`
 * whenever `VITAMIND_FEATURE_WORKSPACES=true` and no `vitamin-d.role_scope_resolver`
 * config override is set: resolves a `hasRole()` call with no explicit
 * scope to the user's current workspace.
 */
class WorkspaceRoleScopeResolver implements RoleScopeResolver
{
    public function resolve(User $user): array
    {
        return [
            'scope_type' => 'workspace',
            'scope_id' => $user->current_workspace_id,
        ];
    }
}
