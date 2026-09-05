# multi-role-authorization Specification

## Purpose

Multi-role authorization is the mechanism that lets plugins and host applications declare custom roles in code and assign more than one role to a user at a time, independent of the `is_admin` toggle and of any tenancy plugin. It provides a `RegisterRole` builder for declaring roles, a `user_roles` table for holding assignments, and a pluggable `RoleScopeResolver` for determining which scope (global or tenant-scoped) a role check applies to.

## Requirements

### Requirement: Developers define roles in code
`vitamind-plugin-sdk` SHALL provide a `RegisterRole` builder that lets a plugin or host application declare a role by a short, package-local string key and a display title, registered from the declaring package's `boot()` into a static, per-process registry. The registry key SHALL be prefixed with the registering plugin's own declared identity (`pluginDetails()['key']`), supplied explicitly by the caller — via the shared `registerRole()` helper, which reads the caller's own `pluginDetails()` — and never guessed from the caller's namespace or call stack, so that role keys chosen independently by different packages cannot collide. Roles SHALL NOT be creatable or editable at runtime (no database-backed or admin-UI-driven role definition).

#### Scenario: Plugin registers a role
- **WHEN** a plugin's service provider (declaring `pluginDetails()['key'] === 'warehouse'`) calls `$this->registerRole('admin-gudang', 'Admin Gudang')` during `boot()`
- **THEN** the role is available in the registry, under the key `warehouse.admin-gudang`, for the lifetime of the process
- **AND** no database write occurs as a result of registration

#### Scenario: Two packages register roles independently
- **WHEN** two different plugins each register a role with a distinct key during their own `boot()`
- **THEN** both roles exist in the registry simultaneously
- **AND** neither registration affects the other

#### Scenario: Two packages register a role with the same short key
- **WHEN** two different plugins each call `registerRole('owner', ...)` with no coordination between them
- **THEN** both registrations succeed, because each key is prefixed by its own explicitly declared plugin identity
- **AND** the two roles remain distinguishable and independently checkable via `hasRole()`

### Requirement: Role assignment supports multiple roles per user
`vitamind-core` SHALL provide a `user_roles` table, present in every installation regardless of any feature flag, associating a user with a role key and an optional scope (`scope_type`, `scope_id`), such that a single user MAY hold more than one role assignment at the same time.

#### Scenario: Single-tenant app assigns a role with no scope
- **WHEN** a user is assigned the role `sales` with no scope specified
- **THEN** a `user_roles` row is created with `scope_type` and `scope_id` both null

#### Scenario: A user holds two roles at once
- **WHEN** a user already holds the role `admin-gudang` and is additionally assigned the role `sales`, in the same scope
- **THEN** both role assignments exist as separate rows
- **AND** the user is recognized as holding both roles

#### Scenario: Duplicate assignment is rejected
- **WHEN** a role assignment is attempted for a user, role, and scope combination that already exists
- **THEN** the duplicate assignment is rejected by a uniqueness constraint on (user, role, scope type, scope id)

#### Scenario: Fresh single-tenant installation has role support without any plugin installed
- **WHEN** a developer installs only `vitamind-core` (no tenancy-related plugin) and runs migrations
- **THEN** the `user_roles` table exists and role assignment/checking works immediately, scoped to null (global)

### Requirement: Role checks resolve the current scope automatically, with a pluggable resolver
`vitamind-core` SHALL provide a `RoleScopeResolver` contract responsible for resolving the current scope (`scope_type`, `scope_id`) for a given user, with a default binding that resolves to a global (null) scope. Which implementation is active SHALL be selectable in two ways, in priority order: an explicit class name configured by the host application (`vitamin-d.role_scope_resolver`), taking priority when set; otherwise the implementation automatically bound by whichever plugin is active. `User::hasRole()` SHALL accept an optional explicit scope, falling back to the resolved `RoleScopeResolver` when omitted.

#### Scenario: Default resolver returns a global scope
- **WHEN** no plugin has bound its own `RoleScopeResolver` and no config override is set
- **THEN** resolving a user's current scope returns null scope type and null scope id
- **AND** `$user->hasRole('sales')` checks the role assignment with null scope

#### Scenario: A plugin overrides the scope resolver automatically
- **WHEN** a plugin's service provider binds its own `RoleScopeResolver` implementation into the container, and no config override is set
- **THEN** subsequent `hasRole()` calls without an explicit scope use that implementation's resolved scope instead of the default global scope

#### Scenario: A developer overrides the scope resolver via config
- **WHEN** the host application sets `vitamin-d.role_scope_resolver` to a custom class name
- **THEN** that class is used to resolve the current scope for `hasRole()` calls without an explicit scope
- **AND** this takes priority even when a plugin (e.g. the workspace plugin) would otherwise have bound its own resolver automatically

#### Scenario: Config override left unset falls back to the automatic default
- **WHEN** `vitamin-d.role_scope_resolver` is left unset (the default)
- **THEN** scope resolution falls back to whichever resolver was automatically bound by the active plugin(s), or the global (null) default when none is active

#### Scenario: Explicit scope overrides the resolved scope
- **WHEN** `hasRole()` is called with an explicit scope type and scope id
- **THEN** the check uses the explicit scope
- **AND** no `RoleScopeResolver` resolution is consulted
