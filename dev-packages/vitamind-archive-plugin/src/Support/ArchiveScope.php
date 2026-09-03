<?php

namespace VitaminD\Plugins\Archive\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Archive is app/workspace-first shared storage, not a personal drawer —
 * this centralizes "what does the current scope look like" so
 * FolderController/FileController don't duplicate it.
 *
 * Deliberately queries the `workspaces` table directly via the query
 * builder rather than an Eloquent relation into vitamind/workspace-plugin's
 * models — the same convention WorkspaceMembership (plugin-sdk) uses — so
 * this plugin stays free of a hard dependency on workspace-plugin.
 */
final class ArchiveScope
{
    /**
     * The visibility new folders/files at the archive root default to.
     * `workspace` when the workspaces feature is on — this is shared
     * workspace storage first, not a personal space — falling back to
     * `user` when the feature is off: there is no workspace to default
     * into, and the broader "any signed-in user" default (`app`) that
     * would otherwise make sense here is deferred (design.md Non-Goals),
     * so a single-tenant install has no shared-by-default tier available.
     */
    public static function defaultVisibility(): string
    {
        return config('vitamin-d.features.workspaces') ? 'workspace' : 'user';
    }

    /**
     * Root breadcrumb label: "<Workspace name> Archive" when the acting
     * user has a resolvable current workspace, "App Archive" otherwise
     * (feature off, no authenticated user, or no workspace resolved yet).
     */
    public static function rootLabel(): string
    {
        if (config('vitamin-d.features.workspaces')) {
            $workspaceId = user()?->current_workspace_id;

            $name = $workspaceId
                ? DB::table('workspaces')->where('id', $workspaceId)->value('name')
                : null;

            if ($name) {
                return "{$name} Archive";
            }
        }

        return 'App Archive';
    }

    /**
     * Restricts a folder/file *listing* query to the workspace currently
     * being browsed (or unscoped items, `workspace_id IS NULL`).
     *
     * This is deliberately separate from `ChecksVisibility::canView()`,
     * which governs whether a given user may open a given item at all
     * (direct links, downloads) and is intentionally *not* limited to the
     * viewer's current workspace — a `workspace`-visibility item is
     * reachable by any genuine member regardless of which workspace they
     * currently have active, and an owner can always reach their own
     * `user`-visibility item (design.md D3).
     *
     * Without this, the archive *browser* silently mixes in items the
     * viewer merely happens to be authorized to see (their own items, or
     * items in any other workspace they belong to) even though those
     * items live in a different workspace than the one being browsed —
     * the root cause of folders/files from one workspace appearing while
     * browsing another.
     */
    public static function scopeToCurrentWorkspace(Builder $query): Builder
    {
        $workspaceId = user()?->current_workspace_id;

        return $query->where(function (Builder $scoped) use ($workspaceId): void {
            $scoped->whereNull('workspace_id');

            if ($workspaceId) {
                $scoped->orWhere('workspace_id', $workspaceId);
            }
        });
    }
}
