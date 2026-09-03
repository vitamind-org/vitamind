# vitamind-archive-plugin Specification

## Purpose

Archive Plugin (`vitamind/archive-plugin`) is the first-party plugin that provides simple, secure folder/file storage — upload, organize into folders, download, delete — with per-item visibility control. Each folder and file carries its own `user` (owner-only) or `workspace` (genuine workspace members only) visibility, enforced by a single authorization check evaluated fresh on every access. Physical file content is stored under a server-generated name on a disk that is never directly web-reachable, and all downloads are served through one authorized controller rather than signed or predictable URLs. The plugin does not depend on `vitamind/workspace-plugin` and functions correctly, with `workspace` visibility simply inaccessible, on single-tenant installs where workspaces are absent.

## Requirements

### Requirement: Folders and files support independent visibility tiers

Each folder and file SHALL carry its own `visibility` value, independently of its parent folder's visibility: `user` (owner only) or `workspace` (genuine members of the item's `workspace_id`). Visibility SHALL default to the visibility of the destination folder at creation time as a UI convenience, but SHALL remain independently editable per item thereafter.

`app` (any authenticated user) and `public` (anyone, including unauthenticated requests) are **deferred** — not part of this capability. Workspace-independent visibility (reachable regardless of which workspace is being browsed, or without authentication at all) needs deliberate design against the workspace-scoped browsing model this capability establishes, and is left to a dedicated future change.

#### Scenario: New item defaults to its destination folder's visibility
- **WHEN** a user uploads a file into a folder with visibility `workspace`
- **THEN** the new file's `visibility` defaults to `workspace`

#### Scenario: Item visibility can be changed independently of its folder
- **WHEN** the owner changes a file's visibility to `public` while its parent folder remains `user`
- **THEN** the file becomes accessible per the `public` rules described below, unaffected by its parent folder's visibility

### Requirement: Per-visibility-tier authorization is enforced on every access

A single authorization check SHALL govern every read of a folder or file, evaluated fresh on each request — never cached or determined once at link-generation time.

#### Scenario: `user`-visibility item is accessible only to its owner
- **WHEN** a user other than the owner requests a folder or file with `visibility = user`
- **THEN** access is denied

#### Scenario: `workspace`-visibility item is accessible only to real members of its workspace
- **WHEN** a user requests a folder or file with `visibility = workspace`
- **THEN** access is granted only if the user is a genuine member of the item's `workspace_id`, verified via the shared workspace-membership check — not merely because the workspace matches the user's currently active workspace

#### Scenario: `workspace`-visibility item with no workspace is inaccessible
- **WHEN** a folder or file has `visibility = workspace` but a null `workspace_id`
- **THEN** access is denied to everyone except through the owner's `user`-level access, if applicable

#### Scenario: Changing visibility away from `workspace` immediately revokes access
- **WHEN** a `workspace` file's visibility is changed to `user`
- **THEN** subsequent requests for that file (including via a previously shared link) are denied to non-owners, with no separate revocation step required

### Requirement: Physical file storage is never directly web-reachable

Uploaded file content SHALL be stored under a server-generated UUID-derived name and path, on a disk that is not configured for direct web serving (no `serve => true` local-disk auto-route, no `public` disk/`storage:link`). The physical path SHALL NOT be derived from any user-supplied name.

#### Scenario: Physical filename is not the original filename
- **WHEN** a file with original name `laporan Q3.pdf` is uploaded
- **THEN** its stored physical filename is `{generated-uuid}.pdf`, not `laporan Q3.pdf` or any transformation of it

#### Scenario: Uploaded content is unreachable by guessing or constructing a direct URL
- **WHEN** a request is made directly to the configured storage disk's path for a file, bypassing the plugin's download endpoint
- **THEN** the disk configuration does not expose the file at any predictable web URL

#### Scenario: All downloads are served through one authorized endpoint
- **WHEN** a file is downloaded or viewed, regardless of its visibility tier
- **THEN** the response is produced by the plugin's own controller after an authorization check, streamed from storage — never by a signed URL or direct disk URL generated ahead of time

### Requirement: Upload validation rejects disallowed and mismatched file types

An upload SHALL be rejected unless its claimed extension is on an explicit allow-list, and the server-detected MIME type (derived from file content, not the client-supplied extension or content-type header) SHALL be cross-checked against the claimed extension, with a mismatch causing rejection.

#### Scenario: Disallowed extension is rejected
- **WHEN** a file with extension `.php` (or `.phtml`, `.phar`, `.exe`, `.sh`, `.htaccess`) is uploaded
- **THEN** the upload is rejected before storage

#### Scenario: Extension disguising content is rejected
- **WHEN** a file's real content is detected as a non-image MIME type but its claimed extension is `.jpg`
- **THEN** the upload is rejected

### Requirement: Folder deletion requires the folder to be empty

Deleting a folder that still contains any child folder or file SHALL be rejected. No cascading/recursive delete is performed by this capability.

#### Scenario: Deleting an empty folder succeeds
- **WHEN** the owner deletes a folder with no child folders and no files
- **THEN** the folder is removed (soft-deleted)

#### Scenario: Deleting a non-empty folder is rejected
- **WHEN** a user attempts to delete a folder that still contains at least one child folder or file
- **THEN** the deletion is rejected with a validation error, and the folder and its contents remain unchanged

### Requirement: `owner_id` and `workspace_id` are not client-settable

Requests to create or update a folder or file SHALL NOT be able to set `owner_id` or `workspace_id` directly; both are assigned server-side (owner from the authenticated user, workspace from the acting user's current workspace at creation time, or left null).

#### Scenario: Request body cannot assign a different owner
- **WHEN** a create request includes an `owner_id` value for a different user
- **THEN** the value is ignored and the record is stamped with the authenticated user's id

#### Scenario: Request body cannot assign an arbitrary workspace
- **WHEN** a create request includes a `workspace_id` value
- **THEN** the value is ignored; the record's `workspace_id` is set from the acting user's current workspace context, or left null

### Requirement: The plugin operates with no workspace plugin installed

`vitamind/archive-plugin` SHALL install, enable, and function on a single-tenant project where `vitamind/workspace-plugin` is absent and the workspaces feature is disabled. It SHALL NOT declare `vitamind/workspace-plugin` as a Composer dependency.

#### Scenario: Migrations succeed with no `workspaces` table present
- **WHEN** the plugin's migrations run on a database with no `workspaces` table
- **THEN** the `folders` and `files` tables are created successfully, with `workspace_id` nullable and unconstrained

#### Scenario: `workspace`-visibility items are simply inaccessible, not erroring
- **WHEN** the workspaces feature is disabled and a `workspace`-visibility item is requested
- **THEN** access is denied cleanly (as no membership can exist), with no exception thrown

#### Scenario: `user` tier works fully without the workspace plugin
- **WHEN** the workspaces feature is disabled
- **THEN** folders and files with `user` visibility behave exactly as on a workspace-enabled install

### Requirement: The plugin registers its own main-sidebar entry

The plugin SHALL register an "Archive" entry in the application's main sidebar via `VitaminD\PluginSdk\RegisterPage` in custom-link mode, pointing at its own `archive.index` route. This registration SHALL be the plugin's only means of appearing in the sidebar — the plugin SHALL NOT require any change to `resources/js/components/app-sidebar.tsx` or other host boilerplate to do so.

#### Scenario: Archive appears in the sidebar for a non-admin user
- **WHEN** the plugin is installed and enabled
- **AND** the current user is authenticated (not necessarily an admin)
- **THEN** an "Archive" entry appears in the main sidebar
- **AND** navigating it loads the plugin's own `archive.index` route (`FolderController::index`), rendering `@plugin/archive-plugin/index`, not the generic `dynamic-page` CRUD screen

#### Scenario: Registration uses custom-link mode, not tabs mode
- **WHEN** the plugin's service provider registers the "Archive" page
- **THEN** it calls `RegisterPage::make(...)->route('archive.index')->register()`
- **AND** it does not call `->tabs(...)`, since the folder/file hierarchy has no `RegisterDataTable`-representable shape
