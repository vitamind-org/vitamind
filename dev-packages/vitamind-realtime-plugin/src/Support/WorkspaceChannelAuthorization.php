<?php

namespace VitaminD\Plugins\Realtime\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Workspace-membership check for use inside an implementor's own
 * `Broadcast::channel()` authorization callbacks — the Layer 2 "sugar" from
 * design.md's D2/D3. Deliberately convention-based, the same way
 * `VitaminD\PluginSdk\Concerns\BelongsToWorkspace` is: it knows the
 * `vitamin-d.features.workspaces` flag and the `user_workspace` table's
 * `user_id`/`workspace_id` columns, but never imports anything from
 * `vitamind/workspace-plugin` — this package has no dependency on it.
 *
 * Unlike `BelongsToWorkspace` (which trusts the user's already-verified
 * `current_workspace_id`), this checks *actual* membership in whatever
 * workspace ID the channel name carries — a subscriber can request any
 * channel, not just their currently active workspace, so trusting
 * `current_workspace_id` alone would be the wrong check here.
 */
final class WorkspaceChannelAuthorization
{
    /**
     * Returns false (never grants) when the workspaces feature is off — a
     * caller that invokes this without first confirming the feature is
     * enabled gets a safe default, not a silent bypass.
     */
    public static function check(?Authenticatable $user, int|string $workspaceId): bool
    {
        if (! Config::get('vitamin-d.features.workspaces', false)) {
            return false;
        }

        if (! $user) {
            return false;
        }

        return DB::table('user_workspace')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('workspace_id', (int) $workspaceId)
            ->exists();
    }
}
