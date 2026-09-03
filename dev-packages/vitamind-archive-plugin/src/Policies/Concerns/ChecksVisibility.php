<?php

namespace VitaminD\Plugins\Archive\Policies\Concerns;

use VitaminD\Core\Models\User;
use VitaminD\PluginSdk\Support\WorkspaceMembership;

/**
 * Shared `view` semantics for FolderPolicy/FilePolicy — folders and files
 * use identical visibility rules (design.md D3), so this is factored out
 * rather than duplicated.
 *
 * Only `user` and `workspace` tiers exist here — `app`/`public` are
 * deferred (design.md Non-Goals).
 *
 * The owner short-circuit above the tier match is deliberate: per
 * spec.md's "workspace-visibility item with no workspace is inaccessible"
 * scenario, an item can end up unreachable via its own visibility tier
 * (e.g. `workspace` visibility with a null `workspace_id`) — the owner
 * must still be able to reach their own item regardless, "through the
 * owner's `user`-level access". For `workspace` visibility this
 * short-circuit changes nothing for a genuine member anyway; `user`
 * visibility is *only* satisfied by this short-circuit (there's no
 * explicit `user` arm in the match below — anyone else falls to
 * `default => false`).
 *
 * WorkspaceMembership::check() already returns false when the workspaces
 * feature is off, so a `workspace`-visibility item simply becomes
 * unreachable to non-owners on a single-tenant install — no separate
 * feature-flag branch is needed here.
 */
trait ChecksVisibility
{
    protected function canView(?User $user, object $item): bool
    {
        if ($user && $user->id === $item->owner_id) {
            return true;
        }

        return match ($item->visibility) {
            'workspace' => $item->workspace_id !== null
                && WorkspaceMembership::check($user, $item->workspace_id),
            default => false,
        };
    }
}
