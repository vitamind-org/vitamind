<?php

namespace VitaminD\PluginSdk\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Shared workspace-membership check for any plugin that needs to authorize
 * access to a resource scoped to an *arbitrary* workspace — as opposed to
 * `VitaminD\PluginSdk\Concerns\BelongsToWorkspace`, which only scopes a
 * model to the acting user's own current workspace.
 *
 * Deliberately convention-based, the same way `BelongsToWorkspace` is: it
 * knows only the `vitamin-d.features.workspaces` flag and the
 * `user_workspace` table's `user_id`/`workspace_id` columns, and never
 * imports anything from `vitamind/workspace-plugin` — this package has no
 * dependency on it.
 *
 * Unlike `BelongsToWorkspace` (which trusts the user's already-verified
 * `current_workspace_id`), this checks *actual* membership in whatever
 * workspace ID the caller names — a resource's workspace may not be the
 * viewer's currently active one, so trusting `current_workspace_id` alone
 * would be the wrong check here.
 *
 * Alternative considered and rejected: a `WorkspaceResolver` interface
 * (`resolve($workspaceId): ?Workspace`) bound to a concrete implementation
 * by `vitamind/workspace-plugin`, instead of this raw query. Rejected
 * because:
 *  - Any interface returning a `Workspace`-shaped type forces plugin-sdk to
 *    know workspaces are entities with a certain shape, which is *more*
 *    coupling than knowing one config key and one table name — the opposite
 *    of the goal.
 *  - No current caller needs the full entity; every consumer (this class,
 *    `archive-plugin`'s `FilePolicy`) only ever needs a boolean. Resolving
 *    a full model (and inviting `$resolved->users->contains(...)`-style
 *    relation access) reintroduces the exact Eloquent-relation coupling
 *    `WorkspaceMembership` was written to avoid.
 *  - plugin-sdk ships no service provider today; supporting this safely on
 *    a single-tenant install would require adding one solely to bind a
 *    null-object default, versus a static call that's safe by construction
 *    with zero setup.
 * Revisit only if a real caller needs the full `Workspace` record (e.g. to
 * display its name) from code that must stay dependency-free — that is a
 * different, additive need from this membership check, not a replacement
 * for it.
 */
final class WorkspaceMembership
{
    /**
     * Returns false (never grants) when the workspaces feature is off — a
     * caller that invokes this without first confirming the feature is
     * enabled gets a safe default, not a silent bypass.
     */
    public static function check(?Authenticatable $user, int|string $workspaceId): bool
    {
        $workspaceId = (string) $workspaceId;

        // A caller may pass a loosely-typed value (e.g. from a channel name
        // or request input), so "1x", "1.0", and "01" would otherwise
        // silently cast to (int) 1 below and check membership for the
        // wrong, distinct workspace.
        if (! preg_match('/^[1-9][0-9]*$/', $workspaceId) || (string) (int) $workspaceId !== $workspaceId) {
            return false;
        }

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
