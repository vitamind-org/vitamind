<?php

namespace VitaminD\Plugins\Workspace\Support;

use Illuminate\Support\Facades\DB;

/**
 * Workspace-plugin registers no roles of its own — ownership is tracked as
 * plain data (`workspaces.owner_id`), not a role. This class only cleans up
 * role assignments an application chose to grant through the invite flow,
 * scoped to a workspace, when a membership ends.
 */
final class WorkspaceRoles
{
    /**
     * Removes every role assignment a user holds scoped to a given
     * workspace — used when a membership ends (removal or leaving), so a
     * stale role assignment doesn't outlive the membership it came from.
     */
    public static function clearFor(int $userId, int $workspaceId): void
    {
        DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('scope_type', 'workspace')
            ->where('scope_id', $workspaceId)
            ->delete();
    }
}
