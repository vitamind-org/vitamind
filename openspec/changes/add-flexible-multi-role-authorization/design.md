## Context

VitaminD's authorization today is split across two mechanisms that don't compose:

- `is_admin` (boolean, `vitamind-core`): binary system-admin flag, used by `MustBeAdminMiddleware` and `UserPolicy`. Out of scope for this change — left untouched.
- `UserRole` enum (`OWNER`/`ADMIN`/`USER`, `vitamind-core`), but only meaningful through `vitamind-workspace-plugin`: stored as a single scalar `role` column on the `user_workspace` pivot, enforced as a strict hierarchy (`OWNER ⊇ ADMIN ⊇ USER`) via `HasRolePolicies` (`hasReadAccess`/`hasWriteAccess`/`hasOwnerAccess`), consumed only by `WorkspacePolicy`. The same enum is also reused, unrelated to workspace roles, by `vitamind-core`'s own `CreateUser`/`UpdateUser` admin-panel actions to derive `is_admin` — a coupling this change also has to unwind before the enum can be deleted (see Decision 7).

This tier mechanism has no existence outside the optional, feature-flagged workspace plugin — a single-tenant app has zero role primitives beyond `is_admin`. Even where it exists, a single scalar column cannot express a user holding more than one role at once, and a 3-level hierarchy cannot express peer, non-hierarchical functional roles (e.g. an "Admin Gudang" and a "Keuangan" role are not "higher/lower" than each other — they cover different domains). Developers building domain-specific apps on the boilerplate (the concrete motivating case: Super User / Bisnis Owner / Admin Gudang / Keuangan / Sales) currently have no supported primitive for this and would have to build one from scratch per app.

The existing codebase has an established convention for "developer declares X in code, framework aggregates it": `RegisterPage`/`RegisterPageGroup` (`vitamind-plugin-sdk`) — a fluent builder with a static in-memory registry, populated from each plugin/app's `boot()`. This change follows that convention for role *definition*, and introduces one new convention — an interface with a default binding, overridable via the container — for role *scope resolution*, since that problem (pick the one active strategy) doesn't fit the accumulative registry shape.

## Goals / Non-Goals

**Goals:**
- One role mechanism that works identically whether or not `vitamind-workspace-plugin` (or any future tenancy-like plugin) is installed.
- Roles are declared in code by the developer (`RegisterRole`), not configured at runtime via DB/admin-UI.
- A user can hold more than one role at once (many-to-many).
- `vitamind-workspace-plugin`'s existing `OWNER`/`ADMIN`/`USER` tier is retired in favor of plain ownership data plus the generic mechanism (see Decision 5), while its observable business rules (one owner, owner cannot be removed) are preserved.
- Zero impact on apps that don't touch this feature — a fresh single-tenant app gets a working, empty role system with no configuration.

**Non-Goals:**
- Granular per-role permissions (`RegisterRole::permissions([...])`, ability strings, etc.). Explicitly deferred — this iteration is role-name checks only (`hasRole('admin-gudang')`).
- Merging or replacing `is_admin`. It remains a separate, parallel primitive; no built-in "super-admin" role is introduced.
- Runtime/DB-configurable roles (an admin UI to create roles). Roles are fixed at deploy time, defined in code.
- Support for more than one *kind* of scope active at a time (see Risks) — only one `RoleScopeResolver` binding is active per request.

## Decisions

### 1. `RegisterRole` lives in `vitamind-plugin-sdk`, mirrors `RegisterPage`/`RegisterPageGroup`, and auto-prefixes its key by the registering package

Fluent builder (`RegisterRole::make('owner')->title('Workspace Owner')->register()`), static `$registry` array, `get()`/`find()`/`flush()`. No `permissions()` method in this iteration (see Non-Goals). Placing it alongside `RegisterPage`/`RegisterPageGroup` keeps every "developer declares a static, code-defined thing" concern in one package with one consistent shape, rather than inventing a second convention for the same kind of problem.

The key passed to `make()` is a short, package-local name; the key actually stored in the registry (and later in `user_roles.role`) is automatically prefixed with an identifier for whichever package called `register()` — e.g. `workspace-plugin`'s `RegisterRole::make('owner')` resolves to a registry key like `workspace.owner`. The prefix is derived from the immediate caller's namespace at `register()` time (a `debug_backtrace()` lookup, resolved once when `register()` runs), not passed explicitly by the developer — resolving the earlier open question ("does key naming need to avoid collisions manually?") by making collision-avoidance automatic rather than a convention developers have to remember. Two packages can each register a role called `owner` without colliding, and workspace-plugin's own built-in roles (Decision 5) get this for free rather than needing a bespoke naming scheme.

**Alternative considered**: a role registry inside `vitamind-core` instead of `vitamind-plugin-sdk`. Rejected — `RegisterPage`/`RegisterPageGroup` already established that developer-facing declarative registration belongs in the SDK package that plugins/apps depend on directly; `vitamind-core` holds the runtime/data side (the `user_roles` table, the resolver contract), matching the existing split between "SDK for declaring things" and "core for running the app".

**Alternative considered**: require the caller to pass an explicit prefix/namespace argument to `make()` instead of deriving it automatically. Rejected per explicit direction — the prefix must come from the registering package's own definition, not be re-typed by the developer at each call site.

### 2. `user_roles` table in `vitamind-core`, with generic `scope_type`/`scope_id` instead of `workspace_id`

Columns: `user_id`, `role` (string, a `RegisterRole` key), `scope_type` (nullable string), `scope_id` (nullable unsigned integer), timestamps. A unique composite index on (`user_id`, `role`, `scope_type`, `scope_id`) both prevents duplicate assignment and serves as the lookup index for the common query shape (`WHERE user_id = ? AND scope_type = ? AND scope_id = ?`) — no separate index is needed alongside it. Migration always runs — not gated behind any feature flag — so single-tenant apps get it for free.

`scope_type`/`scope_id` is deliberately generic rather than a `workspace_id` foreign key, because `vitamind-workspace-plugin`'s own spec requires core to have no knowledge of workspace concepts ("Workspace Plugin is independent from Core" — core must not import or reference workspace classes). A `workspace_id` column, or an FK to `workspaces`, would invert that dependency. A generic pair of columns lets core stay blind to what a scope *means* while still storing it.

**Alternative considered**: `workspace_id` nullable column directly on `user_roles`. Simpler to query/index, but ties a core table to a concept core is explicitly required not to know about, and forecloses any future non-workspace scoping dimension without another migration.

### 3. `RoleScopeResolver` contract, with a config override layered on top of plugin-determined default binding

`interface RoleScopeResolver { resolve(User $user): array{scope_type: ?string, scope_id: ?int}; }` (in `vitamind-core`'s `Contracts/`, alongside the existing `AppEnum`).

Resolution has two layers, in priority order:

1. **Explicit config override** — `vitamin-d.role_scope_resolver` (nullable string, a fully-qualified class name), shipped in `vitamind-core`'s default `config/vitamin-d.php` as `null`. When a host application sets this (via its own published config), that class is used, full stop — regardless of which plugins are installed or active. This follows the same shape already used elsewhere in this codebase for "config holds a class name, with a hardcoded fallback default": Laravel's own `config/auth.php` (`'model' => env('AUTH_MODEL', User::class)`), which `vitamind-workspace-plugin` already reads via `config('auth.providers.users.model')` in `Workspace::registeredUsers()` and `UserWorkspace::user()`.
2. **Plugin-determined automatic default** — when the config value is unset, each provider that binds `RoleScopeResolver` falls back to its own default class: `NullRoleScopeResolver` (always `[null, null]`) for `CoreServiceProvider`, or `WorkspaceRoleScopeResolver` (resolving `['workspace', $user->current_workspace_id]`) for `WorkspaceServiceProvider`, bound only when `VITAMIND_FEATURE_WORKSPACES=true` — matching the plugin's existing "zero impact when disabled" contract. `current_workspace_id` already exists as a column on `vitamind-core`'s `User` model, so no new column or cross-package model reference is needed.

Both providers bind `RoleScopeResolver::class` to a closure that checks the config value *first*, falling back to their own default class only when it's unset:

```
bind(RoleScopeResolver::class, fn ($app) =>
    $app->make(config('vitamin-d.role_scope_resolver') ?? <ProviderOwnDefault>::class)
);
```

Because every provider's closure independently checks config before falling back to its own default, resolution stays correct regardless of which provider's binding ends up active last — an explicit config override always wins, and when it's absent, whichever provider registered last still supplies the right automatic default. This doesn't introduce a new boot-order dependency beyond what the design already assumed (`vitamind-workspace-plugin` registers after `vitamind-core`, same as any Composer dependency ordering).

This remains a new pattern for this codebase in one respect: every existing plugin-extension point (`RegisterPage`, `RegisterPageGroup`, `InertiaSharedData::extend()`) is *accumulative* — many plugins each contribute to one shared collection — whereas scope resolution needs exactly one active strategy at a time. The config-override layer softens this: a host developer no longer needs to write a service provider and touch the container directly just to plug in a custom scoping strategy (e.g. per-branch instead of per-workspace) — setting one config value is enough, mirroring the already-familiar `AUTH_MODEL`-style override.

**Alternatives considered**:
- Container rebind only, no config layer (the original iteration of this decision) — works, but forces a host developer to write a service provider for even a simple override, and gives them no way to override a plugin's binding short of out-competing its boot order.
- An accumulative static registry of scope-resolver candidates, resolved by trying each until one returns non-null. Rejected for this iteration: with only one scoping dimension (workspace) in play, this adds a resolution-order problem for no present benefit. Revisit if a second scoping plugin materializes (see Risks).

### 4. `User::hasRole($role, ?scopeType = null, ?scopeId = null)`

Omitted scope args fall back to the bound `RoleScopeResolver`; explicit args allow checking a role in a scope other than the current one. This keeps the common call site (`$user->hasRole('sales')`) simple while not closing off less common cross-scope checks.

### 5. `vitamind-workspace-plugin` registers no built-in roles; ownership is plain data, not a role

This decision was revised after an initial implementation (`WorkspaceRoles::OWNER`/`ADMIN`/`MEMBER` as three built-in `RegisterRole` entries, tier-based `HasRolePolicies`) — that implementation is being refactored to the shape below.

Workspace-plugin registers **no built-in roles at all**. A workspace's owner is tracked as plain data: an `owner_id` column on `workspaces` (nullable at the schema level for symmetry, but always set by `CreateWorkspace`), entirely independent of `user_roles`/`RegisterRole`. `user_workspace` is a pure membership table — a row means "this user belongs to this workspace," nothing more; it has no `role` column.

`HasRolePolicies`'s three tiers collapse to two plain checks, with no role lookup involved:
- **View**: the user has a `user_workspace` membership row for that workspace.
- **Update / delete / manage membership**: the user's id matches the workspace's `owner_id`.

There is deliberately no built-in "workspace admin" tier between member and owner — an app that wants a member to have elevated workspace-management capability without being the owner must register its own role and write its own policy check for it; the workspace plugin itself only ever recognizes owner-or-not. `is_admin` plays no part in this — workspace-level authorization stays fully independent of it, consistent with `is_admin` never merging into the role mechanism (see Non-Goals). This is a real reduction from the previously-implemented behavior (which let the `ADMIN` tier invite/manage members); accepted as the intended shape per explicit direction (see Risks).

Business rules that must survive the refactor unchanged:
- A workspace has exactly one owner, set once at creation (`CreateWorkspace`) and not reassignable through any existing flow.
- The owner cannot be removed from their own workspace (`WorkspaceUserController::destroy`).

**Alternative considered**: the previously-implemented shape (three built-in `RegisterRole` tiers, `hasRole()`-based policy checks). Superseded — conflating "owns this workspace" with "holds a role" made the owner indistinguishable, in mechanism, from any app-defined role, when it is really a structural property of the workspace itself (exactly one, set at creation, never granted by invite).

### 6. Workspace invitation offers an optional, single choice between granting system Admin and any registered role

`InviteToWorkspace`'s role selection is a single, **optional** dropdown combining: (a) a fixed **Admin** option, which — instead of assigning a workspace-scoped role — sets the invited user's `is_admin` flag to `true` on acceptance; and (b) every role currently in the `RegisterRole` registry (no exclusion needed — workspace-plugin registers none of its own per Decision 5), each assigned scoped to the invited workspace (`scope_type='workspace'`, `scope_id=$workspace->id`) on acceptance. The two are mutually exclusive per invitation. Choosing **neither** is valid: the invitee becomes a plain member (a `user_workspace` row only) with no role assignment and no `is_admin` grant — this is what keeps invitation useful for an app that hasn't registered any roles yet.

This directly serves the motivating use case for this whole change (Super User/Admin Gudang/Keuangan/Sales-style setups): once an app registers domain-specific roles via `RegisterRole`, they become selectable from the workspace invite screen with no further plugin-specific UI work — the invite form's role list is driven entirely by the registry rather than a hardcoded enum.

The invitation record (`user_workspace`, `user_id` NULL until accepted) carries which of the three was chosen — a role key, an Admin-grant flag, or neither — applied at acceptance time for both an already-registered invitee (attached immediately) and a not-yet-registered one (applied when they complete registration and the invitation auto-accepts). The already-implemented `invited_role`/`is_admin_grant` columns on `user_workspace` (added by `2026_09_05_000000_replace_role_column_on_user_workspace_table`) already model this three-way choice; only the validation rule needs to change from requiring one of the two to allowing neither.

`is_admin` itself remains untouched as a mechanism (the original Non-Goal still holds — no merge, no built-in role for it); only the invite *form* surfaces it alongside registered roles, for the inviter's convenience.

**Alternative considered**: keep Admin-granting entirely inside `vitamind-core`'s existing `CreateUser`/`UpdateUser` admin-panel flow, leaving the workspace invite screen to offer only registered roles. Simpler, and avoids a workspace-scoped action having a global (cross-workspace) effect, but was set aside per explicit direction to fold both into the same invite dropdown — worth revisiting if that global-effect-from-a-workspace-scoped-screen shape turns out to be confusing in practice.

**Deferred UX question**: whether the invite screen should warn the inviter that choosing Admin grants access outside the current workspace (`/admin/*` panel routes, every workspace) — a copy/UX concern, not an architectural one, left to implementation.

### 7. `vitamind-core`'s `CreateUser`/`UpdateUser` gain real, multi-select role assignment alongside an independent `is_admin` toggle

This decision was revised after an initial, narrower implementation (decoupling from the `UserRole` enum via a plain `Rule::in(['admin', 'user'])`, with `role` still only ever toggling `is_admin`) — that implementation is being reworked to the shape below.

`CreateUser`/`UpdateUser`'s `role` input becomes an **array**, validated so every element must be a key that currently exists in the `RegisterRole` registry (`Rule::in(array_keys(RegisterRole::get()))` applied per element, or equivalent) — not a fixed two-value set. Submitting one or more roles performs real assignments via `user_roles` for the created/updated user, one row per role, scoped through whichever `RoleScopeResolver` is currently bound (so, for example, if `vitamind-workspace-plugin` is active and there's a current workspace in context, the assignment is scoped to that workspace; otherwise it's global, matching `NullRoleScopeResolver`).

Separately, the form keeps an independent `is_admin` toggle (a plain boolean, not part of the role array) — orthogonal to role selection: a user can be given roles, `is_admin`, both, or neither in the same submission. This is deliberately not modeled as another "Admin option inside the same dropdown" the way Decision 6 does for the workspace invite screen — those are two different form shapes for two different actions, and there's no reason to force them into the same pattern.

**Alternative considered**: model `is_admin` as one more selectable item mixed into the same role array (mirroring Decision 6's single-dropdown-with-Admin-option shape). Rejected — `is_admin` is not mutually exclusive with holding roles here (unlike the workspace invite's single-choice dropdown), so cramming it into the same list would need an artificial "not really a role" carve-out for no benefit over a separate, plainly-boolean field.

**Alternative considered** (superseded, from the original narrower decision): a plain `Rule::in(['admin', 'user'])` string with no real role assignment, keeping `role` as pure `is_admin` sugar. Superseded per explicit direction — once the role registry exists, having this form validate against a fixed two-value set unrelated to it was inconsistent with every other place roles are selected in the app.

## Risks / Trade-offs

- **[Risk]** Only one `RoleScopeResolver` binding can be active at a time; a second scoping-style plugin installed alongside `vitamind-workspace-plugin` would have to win or lose the binding outright, with no built-in composition. → **Mitigation**: not a real constraint today (only one scoping dimension exists); flagged here so a future second scoping plugin doesn't silently overwrite this one without a deliberate design pass.
- **[Risk]** Business rules that were only enforced in code (owner protection) have no historical spec coverage, so the refactor could silently drop one. → **Mitigation**: these are captured explicitly as requirements in this change's spec delta for `vitamind-workspace-plugin`, so they're testable rather than implicit.
- **[Risk]** Choosing "Admin" on a workspace invite grants a global privilege (`is_admin`, effective outside that workspace) from a screen that is otherwise entirely workspace-scoped. → **Mitigation**: the invitation record, acceptance logic, and spec scenarios treat this as a distinct, explicit branch (never inferred or defaulted); the deferred UX warning noted in Decision 6 is worth resolving before shipping the UI.
- **[Risk]** Removing the built-in "workspace admin" tier (Decision 5) is a real behavior reduction from what was already implemented: a member other than the owner can no longer invite/remove/update the workspace unless an app builds its own role and policy for that. → **Mitigation**: accepted deliberately per explicit direction, not an oversight; documented here and in the spec delta so it reads as an intentional scope boundary of the workspace plugin, not a regression to fix later.
- **[Trade-off]** No permission layer means every plugin that wants role-gated behavior checks role *names* directly (`hasRole('admin-gudang')`), coupling that plugin's code to specific role keys an app developer chose. Accepted for this iteration per the stated Non-Goal; revisit if/when multiple independent plugins need to reason about the same role without agreeing on its exact key.

## Migration Plan

There is no production data or deployed installation to preserve, so no backfill is needed anywhere in this plan — including for the `owner_id` column introduced by this revision. An initial iteration of steps 1–4 below is already implemented against the earlier (superseded) version of this design; the plan below reflects the target state, with steps 3–4 now a refactor of that existing code rather than new work:

1. `vitamind-core`: `user_roles` migration, `RoleScopeResolver` contract, `NullRoleScopeResolver` default binding, `User::hasRole()`, `role_scope_resolver` config key. **Done** — matches this design.
2. `vitamind-plugin-sdk`: `RegisterRole` with automatic key prefixing. **Done** — matches this design.
3. `vitamind-workspace-plugin` (refactor): add the `owner_id` migration on `workspaces`; remove the `WorkspaceRoles` built-in-tier registrations and the `RegisterRole` calls in `WorkspaceServiceProvider::boot()`; rewrite `HasRolePolicies`/`WorkspacePolicy` against membership-exists (view) and `owner_id` match (update/delete/deleteUser) instead of `hasRole()` tiers; update `CreateWorkspace` to set `owner_id` instead of assigning a role; make `InviteToWorkspace`'s role selection optional; update `WorkspaceUserController`'s owner-removal check to compare against `owner_id`.
4. `vitamind-core` (refactor): rework `CreateUser`/`UpdateUser` from the already-implemented `Rule::in(['admin', 'user'])` toggle into a multi-select `role` array validated against the live `RegisterRole` registry, performing real `user_roles` assignments, plus a separate, independent `is_admin` toggle.

## Open Questions

None outstanding. The two questions this design previously left open are resolved: `RegisterRole` key naming is handled by automatic package-based prefixing (Decision 1), and `user_roles` indexing is covered by the unique composite index alone, with no separate index needed (Decision 2).
