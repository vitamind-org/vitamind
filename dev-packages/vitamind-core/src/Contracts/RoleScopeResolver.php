<?php

namespace VitaminD\Core\Contracts;

use VitaminD\Core\Models\User;

/**
 * Resolves the scope (`scope_type`/`scope_id`) a role check should apply to
 * for a given user, when the caller doesn't specify one explicitly. Bound in
 * the container by `CoreServiceProvider` (default: `NullRoleScopeResolver`,
 * a global/unscoped check) and may be rebound by a tenancy-like plugin (e.g.
 * `vitamind-workspace-plugin`'s `WorkspaceRoleScopeResolver`) or overridden
 * outright via the `vitamin-d.role_scope_resolver` config key.
 */
interface RoleScopeResolver
{
    /**
     * @return array{scope_type: ?string, scope_id: ?int}
     */
    public function resolve(User $user): array;
}
