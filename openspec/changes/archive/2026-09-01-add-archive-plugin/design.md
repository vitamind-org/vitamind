## Context

`desain-modul-arsip.md` specifies the domain design for a simple file/folder storage feature: folders and files, visibility tiers per item (originally `user`/`workspace`/`app`/`public`; `app` and `public` are deferred out of this change — see Non-Goals), UUID-named physical storage on a non-web-exposed disk, and a single authorization-checking controller for all access.

This change turns that design into `vitamind/archive-plugin`, a first-party Composer plugin, adapted to conventions already locked in this repo:
- Composer 1st-party plugin placement (`dev-packages/vitamind-archive-plugin/` → `vendor/vitamind/archive-plugin`), per `phase1_namespace_architecture`.
- `workspace_id` columns must be nullable and unconstrained — no plugin may require a `workspaces` table to exist (`plugin-workspace-scoping`).
- No plugin depends on `vitamind/workspace-plugin` directly; workspace integration is convention-based via config flag + raw pivot-table queries.
- This repo's `config/filesystems.php` `local` disk has `'serve' => true` (Laravel 11+ signed-URL serving) — a mechanism this plugin must deliberately avoid for its own files, since it authorizes by signed-URL possession rather than live policy checks.
- The generic `RegisterPage`/`RegisterDataTable` plugin-sdk system (`PluginPageController` + `dynamic-page.tsx`) has no per-record ownership/visibility model and a non-customizable frontend renderer — unsuitable for user-owned, visibility-scoped resources, so this plugin does not use it.
- Depends on `add-workspace-membership-check` landing first, which adds `VitaminD\PluginSdk\Support\WorkspaceMembership::check()` — this plugin's `workspace`-visibility authorization consumes that primitive rather than duplicating it.

## Goals / Non-Goals

**Goals:**
- Folders and files, each independently visibility-scoped (`user`/`workspace`), with per-request authorization for every access, download included.
- Physical storage that is never web-reachable directly: UUID-named files on a disk without `serve => true`/`public` visibility, sharded to avoid huge flat directories.
- A custom Inertia listing UI (folder/file browser) distinct from a rigid generic data table — no drag-drop upload, no nested cascading tree view; a flat-per-folder listing with basic navigation (breadcrumb via `parent_id` walk) is sufficient.
- Clean install and operation with no `vitamind/workspace-plugin` present (single-tenant mode): `workspace` visibility becomes unreachable/unavailable rather than erroring.

**Non-Goals:**
- No `app` (any authenticated user) or `public` (unauthenticated) visibility tiers in this change — **deferred**. They're deliberately workspace-independent (reachable from any workspace, or with no auth at all), which cuts against the "browsing = this workspace's archive" scoping the rest of the plugin relies on (see `ArchiveScope::scopeToCurrentWorkspace()`), and `public` additionally needs a guest-reachable download path the `user`/`workspace`-only design doesn't. Only `user` and `workspace` ship now; revisit as a dedicated follow-up change.
- No online editing, versioning, or sync.
- No drag-drop upload UI, no complex nested folder-tree component — plain forms and a simple listing view.
- No public-link expiry (`expires_at`) — visibility change is the only revocation mechanism, per the original design doc.
- No upload quota enforcement in this change (the `size` column makes it computable later).
- No reuse of `RegisterPage`/`RegisterDataTable` — this plugin ships its own controllers, policies, and frontend page.

## Decisions

**D1: Package shape follows `vitamind-workspace-plugin`/`vitamind-realtime-plugin` exactly.**
`dev-packages/vitamind-archive-plugin/` with `src/` (PSR-4 `VitaminD\Plugins\Archive\`), `database/migrations/`, `resources/js/`, `tests/`, `composer.json` declaring `extra.laravel.providers` for a self-registering `ArchiveServiceProvider`. `composer.json` requires `vitamind/core` and `vitamind/plugin-sdk`, but not `vitamind/workspace-plugin`.

**D2: `workspace_id` is nullable and unconstrained on both `folders` and `files`.**
```php
$table->foreignId('workspace_id')->nullable();
```
No `->constrained('workspaces')`, per `plugin-workspace-scoping`. The plugin never joins against a `workspaces` table; it only ever compares `workspace_id` as a plain integer (via `WorkspaceMembership::check()`) or reads it for display.

*Alternative considered*: the design doc's original `->constrained('workspaces')->nullOnDelete()`. Rejected — would make the migration fail outright on any install without `vitamind/workspace-plugin`, contradicting the plugin's own goal of working standalone.

**D3: Visibility authorization is a policy that never touches Eloquent relations to `Workspace`.**
`FolderPolicy`/`FilePolicy::view()` (shared logic factored into a small trait or base class, since folders and files use identical visibility semantics):
```php
return match ($item->visibility) {
    'workspace' => $item->workspace_id !== null
                    && WorkspaceMembership::check($user, $item->workspace_id),
    default     => false,
};
```
(`user` visibility isn't a separate arm: it's handled entirely by the owner short-circuit above this match, and falls to `default => false` for anyone else. `app`/`public` are deferred — see Non-Goals — so there's no arm for either.)

`WorkspaceMembership::check()` (from `add-workspace-membership-check`) already returns `false` when the workspaces feature is disabled or `$user` is null, so no separate feature-flag branch is needed here — a `workspace`-visibility item simply becomes unreachable by anyone when the feature is off, which is the correct behavior (there's no membership to have).

*Alternative considered*: checking `$item->workspace_id === $user->current_workspace_id` (matching `BelongsToWorkspace`'s convention). Rejected per the earlier exploration: that only proves the workspace matches the viewer's *currently active* workspace, not that they're a genuine member of the file's workspace — a user could switch away and back, or the file could belong to a workspace they're not even in, depending on how `workspace_id` was set at creation.

**D4: `owner_id` and `workspace_id` are never mass-assignable.**
Both columns are excluded from `$fillable` on `Folder`/`File` and set explicitly in the controller (owner_id = acting user; workspace_id = acting user's `current_workspace_id` at creation time, or null), mirroring the mass-assignment protection already required of `BelongsToWorkspace` consumers in `plugin-workspace-scoping`.

**D5: Physical storage: dedicated disk config key, not hardcoded to `local`.**
The plugin reads its target disk from a plugin-owned config value (e.g. `config('archive-plugin.disk', 'local')`) rather than hardcoding `'local'`, so an operator can point it at a different non-served disk (e.g. a private S3 bucket) without code changes — consistent with the design doc's "disk choice is `.env`-only" principle. The plugin's own install/boot step SHOULD assert (or at minimum document) that the configured disk does not have `serve => true` or `visibility => 'public'` set, since either would reintroduce the signed-URL/public-read bypass this design exists to avoid.

**D6: File serving goes through one custom controller, never `Storage::url()`/`temporaryUrl()`.**
```php
Route::get('/archive/f/{file:uuid}', [FileDownloadController::class, 'show']);
```
The controller loads the `File` by `uuid`, runs `Gate::authorize('view', $file)` (or the policy directly), then returns `Storage::disk($file->disk)->response($file->path, $file->original_name)`. This is a request-time policy check on every access — matching the design doc's principle that changing an item's visibility kills existing links immediately, which Laravel's built-in signed-URL serving mechanism (D-context above) cannot guarantee. With `app`/`public` deferred, both remaining tiers (`user`, `workspace`) require an authenticated user, so the route sits behind the `auth` middleware like the rest of the plugin — no guest-reachable download path exists in this change.

**D7: Upload validation order: whitelist extension → validate `max` size → detect real MIME from content → cross-check claimed extension against detected MIME → reject on mismatch.**
Extension whitelist is a plugin config array (denies `php`, `phtml`, `phar`, `exe`, `sh`, `htaccess`, and anything not explicitly allowed), enforced in addition to, not instead of, storing outside any served disk (defense in depth per the design doc).

**D8: Folder delete requires the folder to be empty.**
Deleting a non-empty folder (has child folders or files) is rejected with a validation error; no recursive-delete method is implemented in this change. This matches the simplicity goal — the design doc offers this as the "avoid recursion entirely" alternative, and it's the one adopted here.

*Alternative considered*: recursive delete (`deleteFolderRecursive` walking children). Rejected for this change to keep the initial implementation and its test surface small; can be added later as a separate, explicit capability if empty-folder-only proves too restrictive in practice.

**D9: Frontend is a custom Inertia page via the existing `@plugin/archive-plugin/...` mechanism, listing only — no drag-drop, no tree view.**
`resources/js/pages/index.tsx` renders the current folder's immediate children (subfolders and files) as a simple list/grid with a breadcrumb built by walking `parent_id` client-side from data the controller already provides; upload is a plain `<input type="file">` form, not a drag-drop zone. This satisfies "custom listing, not a rigid generic table" without building interaction complexity that wasn't asked for.

**D10: Soft deletes on both `folders` and `files`.**
Matches the design doc's "cheap safety net" recommendation — `SoftDeletes` trait, physical file removal deferred to a `forceDelete()`-triggered model event (`deleting` on force-delete only, checked via `$model->isForceDeleting()`).

## Risks / Trade-offs

- [Operator points the plugin's configured disk at one that has `serve => true` or `public` visibility, silently reopening direct access] → Mitigation: D5's boot-time assertion/documentation; the plugin's own README states this explicitly as a hard requirement, not a suggestion.
- [Empty-folder-only delete (D8) is less convenient than recursive delete for users with deep hierarchies] → Accepted trade-off per explicit simplicity preference; revisit as a separate change if it becomes a real pain point.
- [`workspace` visibility becomes fully inert when `vitamind/workspace-plugin` is absent, with no user-facing explanation beyond "you can't see this"] → Mitigation: the folder/file creation UI SHOULD only offer the `workspace` visibility option when `config('vitamin-d.features.workspaces')` is enabled, so the dead option isn't presented as choosable in single-tenant installs. (Follow-up detail, not a blocking risk.)
- [Depends on `add-workspace-membership-check` landing first] → Mitigation: that change is small, self-contained, and behavior-preserving for its existing consumer (realtime-plugin), so it's low-risk to land ahead of this one.
- [Deferring `app`/`public` means a single-tenant install (no `vitamind/workspace-plugin`) has no way to share an item with all users — every item is effectively `user`-only there, since `workspace` is unreachable without a workspace] → Accepted trade-off; `ArchiveScope::defaultVisibility()` falls back to `user` (not `app`) when the workspaces feature is off. Revisit once `app`/`public` are reintroduced.

## Migration Plan

1. Land `add-workspace-membership-check` first (separate change).
2. Scaffold `dev-packages/vitamind-archive-plugin/` package structure and wire it into the root `composer.json` path repositories.
3. Migrations for `folders`, `files`.
4. Models, policies, controllers, form requests (upload validation).
5. Routes + service provider registration.
6. Frontend listing page + upload form.
7. Tests (policy scenarios per visibility tier, upload validation, folder delete-when-empty, download authorization).

No production data migration involved (net-new tables); rollback is dropping the two new tables and removing the package.

## Open Questions

- Whether `workspace` visibility should be hidden from the creation UI when the workspaces feature is off (noted as a mitigation above) — deferred to implementation-time UI polish, not blocking.
- Whether a future change should add recursive folder delete — explicitly deferred by the original design doc and not reopened here.
- When `app`/`public` are reintroduced (separate change): how the workspace-independence they require interacts with `ArchiveScope::scopeToCurrentWorkspace()` — e.g. whether an `app`/`public` item should surface in every workspace's browse listing (it's reachable from all of them) or only in the workspace it was created under, with direct links still working everywhere either way. Not resolved here; the two-tier (`user`/`workspace`) model in this change sidesteps the question entirely.
