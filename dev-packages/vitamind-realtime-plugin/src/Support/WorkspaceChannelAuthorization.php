<?php

namespace VitaminD\Plugins\Realtime\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use VitaminD\PluginSdk\Support\WorkspaceMembership;

/**
 * Workspace-membership check for use inside an implementor's own
 * `Broadcast::channel()` authorization callbacks — the Layer 2 "sugar" from
 * design.md's D2/D3.
 *
 * Delegates to `VitaminD\PluginSdk\Support\WorkspaceMembership`, the shared
 * convention-based primitive this class was the original inline
 * implementation of — promoted to `plugin-sdk` once `vitamind/archive-plugin`
 * needed the identical check for its own workspace-visibility tier. This
 * class stays as a thin, named wrapper so existing `Broadcast::channel()`
 * call sites in consumers of this package are unaffected.
 */
final class WorkspaceChannelAuthorization
{
    public static function check(?Authenticatable $user, int|string $workspaceId): bool
    {
        return WorkspaceMembership::check($user, $workspaceId);
    }
}
