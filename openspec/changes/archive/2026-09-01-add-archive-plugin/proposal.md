## Why

VitaminD needs a simple, secure file/folder storage feature (upload, organize into folders, download, delete) with per-item visibility control. `desain-modul-arsip.md` already specifies the domain design; this change turns it into a first-party plugin (`vitamind/archive-plugin`) that follows this repo's locked plugin conventions, rather than a bespoke implementation.

## What Changes

- New first-party Composer plugin `vitamind/archive-plugin` (developed at `dev-packages/vitamind-archive-plugin/`, namespace `VitaminD\Plugins\Archive\`) providing folders and files with two visibility tiers per item: `user` (owner only), `workspace` (genuine members of the item's workspace). **Deferred**: `app` (any authenticated user) and `public` (unauthenticated) tiers, originally planned as part of this change, are cut — see "Deferred" below.
- Physical files are always stored on a non-web-exposed disk (`local` or equivalent, never Laravel's `public` disk / `storage:link`), named by generated UUID, sharded into subfolders — physical path never derived from user-supplied names.
- All file access goes through one custom, `auth`-gated controller that checks authorization at request time via `Storage::disk($file->disk)->response()` — deliberately not Laravel's built-in `serve => true` / `temporaryUrl()` signed-URL mechanism, which authorizes by possession of a once-generated signature rather than live visibility state.
- Custom backend (`FolderController`, `FileController`, `FolderPolicy`, `FilePolicy`) and a custom Inertia frontend (folder/file listing, not a data grid) served via the existing `@plugin/archive-plugin/...` distributed-plugin frontend mechanism — the plugin does **not** use the generic `RegisterPage`/`RegisterDataTable` system, since that system has no per-record ownership/visibility model (only page-level admin-only vs. everyone) and its frontend renderer can't be customized.
- `workspace_id` on `folders`/`files` is nullable and **unconstrained** (no `->constrained('workspaces')`), so the plugin installs cleanly with no `workspaces` table present. `vitamind/archive-plugin` does not `require` `vitamind/workspace-plugin`.
- **BREAKING (new capability, no prior behavior to break)**: n/a — this is net-new functionality.

**Out of scope, split into a prerequisite change (`add-workspace-membership-check`)**: the `workspace` visibility tier needs a real workspace-membership check (is the acting user actually a member of the file's workspace — not just whether it matches their `current_workspace_id`, which is only valid for scoping data the user owns). Rather than build that inline here, it's being promoted to a shared `vitamind/plugin-sdk` primitive (companion to `BelongsToWorkspace`) in its own change, since `vitamind/realtime-plugin`'s `WorkspaceChannelAuthorization` already has the identical inline check and becomes its second consumer. `add-archive-plugin` depends on that change landing first and consumes the resulting primitive directly — it does not duplicate the check locally.

**Deferred**: `app` and `public` visibility tiers. Post-implementation, fixing a real workspace-isolation leak in the folder/file browser (items the viewer was merely *authorized* to see — as owner, or as a member of another workspace — surfacing while browsing an unrelated workspace) surfaced that these two tiers are meaningfully harder to get right than assumed: they're deliberately workspace-independent (by design, reachable from anywhere), which cuts against the "browsing = this workspace's archive" model the rest of the plugin now relies on, and `public` additionally requires a guest-reachable download path that the `user`/`workspace`-only design no longer needs. Both are cut from this change rather than shipped half-reasoned-through; revisit as a separate, dedicated change once that interaction is designed deliberately.

## Capabilities

### New Capabilities
- `vitamind-archive-plugin`: folder/file storage plugin — folder and file CRUD, two-tier (`user`/`workspace`) per-item visibility authorization, secure UUID-based physical storage independent of any web-reachable disk, upload validation (whitelist mime/extension cross-check), and a single authorized download/view endpoint.

## Impact

- **Depends on** the `add-workspace-membership-check` change landing first (adds the shared workspace-membership primitive to `vitamind/plugin-sdk` that this plugin's `workspace` visibility tier consumes).
- New package: `dev-packages/vitamind-archive-plugin/` (`src/`, `database/migrations/`, `resources/js/`, `tests/`), wired into the root `composer.json` path repositories the same way `vitamind-workspace-plugin`/`vitamind-realtime-plugin` are.
- New database tables: `folders`, `files` (owner-scoped, optionally workspace-scoped, soft-deletable).
- New frontend: `dev-packages/vitamind-archive-plugin/resources/js/` (pages, consumed via the existing `@plugin/archive-plugin/...` alias and `usePlugin()` mechanisms — no changes needed to the host's Vite/Inertia plumbing).
- No changes to local plugins (`app/Plugins/`) or the generic dynamic-page (`RegisterPage`/`RegisterDataTable`) system.
