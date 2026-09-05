<?php

namespace VitaminD\Plugins\Workspace\Traits;

use VitaminD\Core\Models\User;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Workspace-plugin registers no built-in roles (see
 * `VitaminD\Plugins\Workspace\Support\WorkspaceRoles`) — read access is
 * plain membership, and every write-level action requires being the
 * workspace's owner (`workspaces.owner_id`). There is no delegated "admin"
 * tier: an application that wants a non-owner member to manage a workspace
 * must implement that itself via its own registered role and policy logic.
 * `is_admin` is never consulted here — workspace-level authorization stays
 * fully independent of it.
 */
trait HasRolePolicies
{
    protected function hasReadAccess(User $user, Workspace $workspace): bool
    {
        return $workspace->users()->where('user_id', $user->id)->exists();
    }

    protected function hasWriteAccess(User $user, Workspace $workspace): bool
    {
        return $this->hasOwnerAccess($user, $workspace);
    }

    protected function hasOwnerAccess(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
