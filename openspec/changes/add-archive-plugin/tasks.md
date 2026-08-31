## 1. Package scaffolding

- [ ] 1.1 Create `dev-packages/vitamind-archive-plugin/` with `composer.json` (name `vitamind/archive-plugin`, PSR-4 `VitaminD\Plugins\Archive\` → `src/`, requires `vitamind/core`, `vitamind/plugin-sdk`, `laravel/framework`, `inertiajs/inertia-laravel`; no `vitamind/workspace-plugin`).
- [ ] 1.2 Wire the package into the root `composer.json` path repositories, matching `vitamind-workspace-plugin`/`vitamind-realtime-plugin`.
- [ ] 1.3 Add `ArchiveServiceProvider` under `src/Providers/`, registered via `composer.json`'s `extra.laravel.providers`, following `WorkspaceServiceProvider`'s pattern for self-registering routes/migrations (`registerRoutes()`, `registerMigrations()`), but NOT gated behind `vitamin-d.features.workspaces` (the plugin as a whole is independent of that flag; only its `workspace` visibility tier depends on it).
- [ ] 1.4 Add a plugin config file (`config/archive-plugin.php`) with `disk` (default `local`) and an extension allow-list.

## 2. Database

- [ ] 2.1 Migration: `folders` table — `id`, `uuid` (unique), `name`, `parent_id` (nullable, self-referencing FK, `nullOnDelete`), `owner_id` (FK to `users`), `workspace_id` (nullable, unconstrained), `visibility` (enum: user/workspace/app/public, default `user`), timestamps, `SoftDeletes`.
- [ ] 2.2 Migration: `files` table — `id`, `uuid` (unique), `original_name`, `extension`, `mime_type`, `size`, `disk`, `path`, `folder_id` (nullable FK to `folders`, `nullOnDelete`), `owner_id` (FK to `users`), `workspace_id` (nullable, unconstrained), `visibility` (enum, default `user`), timestamps, `SoftDeletes`.
- [ ] 2.3 `Folder` model (extends `VitaminD\Core\Models\AbstractModel`): `$fillable` excludes `owner_id`/`workspace_id`; `uuid` auto-generated on `creating`; relations `parent()`, `children()`, `files()`, `owner()`.
- [ ] 2.4 `File` model: `$fillable` excludes `owner_id`/`workspace_id`; `uuid` auto-generated on `creating`; relation `folder()`, `owner()`; `deleting` model event that calls `Storage::disk($this->disk)->delete($this->path)` only when `$this->isForceDeleting()`.

## 3. Authorization

- [ ] 3.1 Shared visibility-check logic (trait or base policy) implementing the `user`/`workspace`/`app`/`public` match from design.md D3, used by both `FolderPolicy` and `FilePolicy`. Depends on `VitaminD\PluginSdk\Support\WorkspaceMembership::check()` from `add-workspace-membership-check` — confirm that change has landed before starting this task.
- [ ] 3.2 `FolderPolicy` (`view`, `update`, `delete` — `update`/`delete` restricted to owner regardless of visibility tier) and `FilePolicy` (same shape).
- [ ] 3.3 Register both policies in `ArchiveServiceProvider`.

## 4. Upload handling

- [ ] 4.1 Form request validating: `required|file|max:<configured size>`, extension against the plugin's allow-list.
- [ ] 4.2 Content-based MIME detection (`$file->getMimeType()`) cross-checked against the claimed extension; reject on mismatch.
- [ ] 4.3 Store via `$file->storeAs(shardPath($uuid), "{$uuid}.{$ext}", config('archive-plugin.disk'))`; persist the `File` row with `owner_id` = authenticated user, `workspace_id` = acting user's `current_workspace_id` (or null), `visibility` defaulted from the destination folder.

## 5. Controllers & routes

- [ ] 5.1 `FolderController`: create, rename/update visibility, delete (reject if non-empty per design.md D8), list children (folders + files) of a given folder (or root when no `parent_id`).
- [ ] 5.2 `FileController`: create (upload, via the form request from section 4), update visibility, delete (soft delete; physical removal only on `forceDelete()`).
- [ ] 5.3 `FileDownloadController`: `GET` by file `uuid`, runs policy `view` check, returns `Storage::disk($file->disk)->response($file->path, $file->original_name)`. No route uses `Storage::url()`/`temporaryUrl()`.
- [ ] 5.4 Register routes via Spatie route attributes on the controllers (matching `WorkspaceSwitchController`'s pattern), auto-discovered by `ArchiveServiceProvider::registerRoutes()`.

## 6. Frontend

- [ ] 6.1 `resources/js/pages/index.tsx`: lists a folder's immediate children (subfolders, files) as a simple list/grid — no drag-drop, no nested tree component. Breadcrumb built from `parent_id` chain data passed from the controller.
- [ ] 6.2 Plain upload form (`<input type="file">` + visibility select), create-folder form, and per-item visibility-change control.
- [ ] 6.3 Confirm the page renders via `Inertia::render('@plugin/archive-plugin/index', [...])` and is reachable through the existing `@plugin/{name}` alias mechanism with no host Vite/Inertia config changes needed.

## 7. Tests

- [ ] 7.1 Policy tests: one case per visibility tier per scenario in `specs/vitamind-archive-plugin/spec.md` (owner-only, workspace-member vs non-member, authenticated-any, public/unauthenticated, workspace-visibility-with-null-workspace, visibility-changed-away-from-public-revokes-access).
- [ ] 7.2 Upload validation tests: disallowed extension rejected, extension/MIME mismatch rejected, valid upload stores under a UUID-derived path unrelated to the original filename.
- [ ] 7.3 Folder delete tests: empty folder deletes successfully; non-empty folder (child folder or file) is rejected.
- [ ] 7.4 Mass-assignment tests: `owner_id`/`workspace_id` in request payloads are ignored in favor of server-assigned values.
- [ ] 7.5 No-workspace-plugin tests: migrations run and `user`/`app`/`public` tiers behave correctly with `vitamin-d.features.workspaces` disabled and no `workspaces` table present.
- [ ] 7.6 Download endpoint tests: confirms no `Storage::url()`/signed-URL path is used, and that policy authorization runs on every request.
