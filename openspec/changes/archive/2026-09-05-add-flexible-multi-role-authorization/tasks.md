## 1. Core: role assignment data model — done, matches final design

- [x] 1.1 Add `user_roles` migration to `vitamind-core` (`user_id`, `role` string, nullable `scope_type` string, nullable `scope_id` unsigned integer, timestamps; unique composite index on `user_id`+`role`+`scope_type`+`scope_id`)
- [x] 1.2 Add role-assignment model/query layer for `user_roles`
- [x] 1.3 Add `RoleScopeResolver` contract to `vitamind-core`'s `Contracts/`
- [x] 1.4 Add `NullRoleScopeResolver` default implementation (always resolves `[null, null]`)
- [x] 1.5 Add `role_scope_resolver` key (nullable class-name string, default `null`) to `vitamind-core`'s shipped `config/vitamin-d.php`
- [x] 1.6 Bind `RoleScopeResolver` in `CoreServiceProvider` via a closure that resolves `config('vitamin-d.role_scope_resolver') ?? NullRoleScopeResolver::class`
- [x] 1.7 Add `User::hasRole($role, ?scopeType = null, ?scopeId = null)`, falling back to the bound `RoleScopeResolver` when scope args are omitted
- [x] 1.8 Add a role-assignment action/helper (assign, remove) enforcing the uniqueness constraint

## 2. SDK: code-defined role registration — done, matches final design

- [x] 2.1 Add `RegisterRole` to `vitamind-plugin-sdk` (`make(shortKey)`, `title()`, `register()`, static `get()`/`find()`/`flush()`)
- [x] 2.2 Automatic key prefixing derived from the immediate caller's namespace at `register()` time
- [x] 2.3 Test coverage for `RegisterRole` registry lifecycle, including prefix derivation and non-colliding short keys across packages

## 3. Workspace plugin: refactor to owner-as-data and no built-in roles

The current implementation (`WorkspaceRoles::OWNER`/`ADMIN`/`MEMBER` as three built-in `RegisterRole` entries, tier-based `HasRolePolicies`) predates the final design (Decision 5) and needs to be refactored, not extended.

- [x] 3.1 Add a migration adding `owner_id` (unsigned big integer, FK to `users`) to the `workspaces` table
- [x] 3.2 Update `CreateWorkspace` to set `owner_id` to the creating user instead of assigning a `workspace.owner` role via `user_roles`
- [x] 3.3 Remove the built-in role registrations (`workspace.owner`/`workspace.admin`/`workspace.member`) from `WorkspaceServiceProvider::boot()` — workspace-plugin registers no roles of its own
- [x] 3.4 Remove the tier constants and `tierKeyFor()` from `WorkspaceRoles`; keep (or relocate) `clearFor()` if still needed to clear a member's workspace-scoped role assignment(s) when their membership ends
- [x] 3.5 Rewrite `HasRolePolicies` (or fold directly into `WorkspacePolicy`) to two checks with no role lookup: view = a `user_workspace` membership row exists for that workspace; update/delete/deleteUser = the user's id matches the workspace's `owner_id`
- [x] 3.6 Update `WorkspaceUserController::destroy`'s owner-removal-protection check to compare against `workspace->owner_id` instead of `hasRole(WorkspaceRoles::OWNER, ...)`
- [x] 3.7 Make `InviteToWorkspace`'s role selection optional (currently `required`) — a valid invitation may select the Admin option, a registered role, or neither; drop the "exclude workspace-owner" filtering in `invitableRoleKeys()` since no built-in role exists to exclude
- [x] 3.8 Update `AcceptWorkspaceInvite` (and the post-registration auto-accept path) to handle the "neither Admin nor a role was selected" case — the invitee becomes a plain member with no role assignment and no `is_admin` grant
- [x] 3.9 Update the invite form UI (`resources/js/pages/components/invite.tsx`) so role selection is optional rather than a mandatory dropdown value
- [x] 3.10 Audit remaining `Workspace::role()`/`Workspace::hasRoles()` (or equivalent) call sites across the plugin and update or remove them to match the `owner_id`-based checks

## 4. Core: CreateUser/UpdateUser gain real multi-role assignment

The current implementation validates `role` as a plain `Rule::in(['admin', 'user'])` string that only ever toggles `is_admin` — this needs to be replaced, not extended, per the final design (Decision 7).

- [x] 4.1 Change `CreateUser`/`UpdateUser`'s `role` input from a single string to an array, validating each element against the current `RegisterRole` registry keys (e.g. `Rule::in(array_keys(RegisterRole::get()))` per element)
- [x] 4.2 Add a separate, independent `is_admin` boolean input, decoupled from the `role` array's values
- [x] 4.3 On create/update, assign each submitted role via the role-assignment helper (`user_roles`), scoped through the currently bound `RoleScopeResolver`, and set `is_admin` directly from the new independent input
- [x] 4.4 Update the admin-panel user form UI: a multi-select role field driven by the live registry, plus a separate Admin checkbox

## 5. Tests

- [x] 5.1 Unit tests for `user_roles` uniqueness constraint and multi-role-per-user assignment
- [x] 5.2 Unit tests for `RoleScopeResolver` resolution order (default, plugin-bound automatic default, config override)
- [x] 5.3 Unit tests for `User::hasRole()` — implicit scope resolution vs explicit scope argument
- [x] 5.4 Update workspace feature tests for the refactored authorization: creating a workspace sets `owner_id` (no role assigned); a non-owner member (with or without an app-registered role) cannot update/delete the workspace or manage membership; the owner cannot be removed; invite accepts Admin, a registered role, or neither; leaving a workspace clears any role assignment held there
- [x] 5.5 Feature test confirming an app-registered role appears in the invite dropdown and is assignable through the same flow, with no workspace-plugin code change
- [x] 5.6 Feature tests for `CreateUser`/`UpdateUser`'s reworked role handling: multiple roles assigned in one submission; an unregistered role key rejected; `is_admin` and role selection independent of each other (both, either, or neither); scope of the assignment follows the currently bound `RoleScopeResolver`

## 6. Documentation

- [x] 6.1 Add/update `docs/plugin-development/` with a role-registration guide covering `RegisterRole`, `hasRole()`, and `RoleScopeResolver` via config
- [x] 6.2 Update the guide (or workspace-plugin's own docs) to reflect that workspace ownership is plain data (`owner_id`), not a role, and that the plugin registers no built-in roles
