# Defining and Checking Roles

VitaminD's role mechanism lets a plugin or host application declare roles in
code and check them against a user, with or without any tenancy plugin
(`vitamind-workspace-plugin` or otherwise) installed. Three pieces make this
up, mirroring the `RegisterPage`/`RegisterPageGroup` pattern described in
[menu-registration.md](menu-registration.md):

- `VitaminD\PluginSdk\RegisterRole` — declares a role in code (`vitamind-plugin-sdk`).
- `user_roles` (`vitamind-core`) — the table a role assignment lives in, present in every installation regardless of any feature flag.
- `User::hasRole()` / `VitaminD\Core\Contracts\RoleScopeResolver` — the check API and the pluggable strategy that resolves *which* scope a check applies to when the caller doesn't say.

There is no admin UI or database-backed role editor — roles are fixed at
deploy time, defined in code, exactly like pages and page groups.

## Declaring a role: `RegisterRole`

Call `RegisterRole::make(...)->title(...)->register()` from your plugin's
(or app's) `boot()`:

```php
use VitaminD\PluginSdk\RegisterRole;

RegisterRole::make('admin-gudang')
    ->title('Admin Gudang')
    ->register();
```

### Automatic key prefixing

The key you pass to `make()` is a short, package-local name. The key
actually stored in the registry (and later written to `user_roles.role`) is
automatically prefixed with an identifier derived from **whichever package
called `register()`** — resolved once, at `register()` time, via the
caller's namespace. You never pass this prefix yourself.

For a class living under `...\Plugins\{Name}\...` — every VitaminD plugin,
whether shipped in `dev-packages/` (`VitaminD\Plugins\{Name}\...`) or a local
app plugin (`App\Plugins\{Name}\...`) — the identifier is `{Name}`,
kebab-cased. So `vitamind-workspace-plugin`'s own
`RegisterRole::make('owner')` (called from
`VitaminD\Plugins\Workspace\Providers\WorkspaceServiceProvider`) resolves to
the registry key `workspace.owner`.

This means two packages can each register a role called `owner` — or any
other short key — without coordinating or colliding:

```php
// Called from VitaminD\Plugins\Workspace\Providers\WorkspaceServiceProvider
RegisterRole::make('owner')->title('Workspace Owner')->register();
// → registry key: workspace.owner

// Called from App\Plugins\Warehouse\Providers\WarehouseServiceProvider
RegisterRole::make('owner')->title('Warehouse Owner')->register();
// → registry key: warehouse.owner
```

Registration is pure in-memory bookkeeping — `register()` never writes to
the database. Assigning a role to a user (below) is a separate step.

### Builder reference

| Method | Required? | Effect |
|---|---|---|
| `make(string $key)` | — | Starts a builder for the given short key. |
| `title(string)` | yes | Display title (e.g. shown in the workspace invite dropdown). |
| `register()` | yes | Adds the role to the registry under its auto-prefixed key. Call last. |
| `getShortKey()` | — | The short key passed to `make()`. |
| `getTitle()` | — | The title. |
| `getKey()` | — | The auto-prefixed registry key — only non-null after `register()` has run. |
| `RegisterRole::get()` | — | `static`, returns every registered role, keyed by prefixed key. |
| `RegisterRole::find(string $key)` | — | `static`, looks up one role by its prefixed key. |
| `RegisterRole::flush()` | — | `static`, clears the registry — for test isolation, same footgun as `RegisterPage::flush()` (see menu-registration.md). |

## Assigning and checking roles

### The `user_roles` table

`vitamind-core` ships a `user_roles` migration that always runs, regardless
of any feature flag — a fresh single-tenant app gets working role
assignment/checking with zero configuration. Each row is one assignment:
`user_id`, `role` (a `RegisterRole` key), `scope_type` (nullable string),
`scope_id` (nullable unsigned integer). A user can hold any number of roles
at once — including several in the same scope — because each assignment is
its own row, not a single scalar column.

`scope_type`/`scope_id` is deliberately generic rather than e.g.
`workspace_id`: core has no knowledge of what a scope *means*, only that one
might exist. An app-registered role assigned through `vitamind-workspace-plugin`'s
invite flow is scoped to `scope_type = 'workspace'`, `scope_id = $workspace->id`;
a single-tenant app with no tenancy plugin just leaves both null (a global,
unscoped assignment).

### Assigning a role: `AssignRole`/`RemoveRole`

```php
use VitaminD\Core\Actions\Role\AssignRole;
use VitaminD\Core\Actions\Role\RemoveRole;

app(AssignRole::class)->assign($user, 'warehouse.admin-gudang', 'workspace', $workspace->id);

// Global, unscoped assignment — omit the scope args:
app(AssignRole::class)->assign($user, 'sales');

app(RemoveRole::class)->remove($user, 'warehouse.admin-gudang', 'workspace', $workspace->id);
```

`AssignRole::assign()` is idempotent (`firstOrCreate` under the hood) —
assigning a role a user already holds in that scope is a no-op rather than
an error. The underlying `user_roles` table also enforces a uniqueness
constraint on (`user_id`, `role`, `scope_type`, `scope_id`) directly, for
any caller that inserts outside this action.

### Checking a role: `User::hasRole()`

```php
if ($user->hasRole('warehouse.admin-gudang', 'workspace', $workspace->id)) {
    // ...
}
```

Passing both scope arguments checks exactly that scope — no resolver is
consulted. Omit them both to let the currently-bound `RoleScopeResolver`
supply the scope instead:

```php
if ($user->hasRole('sales')) {
    // scope resolved automatically
}
```

## `RoleScopeResolver`: picking the scope automatically

`RoleScopeResolver` is a one-method contract (`vitamind-core`'s
`Contracts/`) responsible for answering "what scope does an unscoped
`hasRole()` call mean, for this user, right now?":

```php
interface RoleScopeResolver
{
    /** @return array{scope_type: ?string, scope_id: ?int} */
    public function resolve(User $user): array;
}
```

Resolution has two layers, in priority order:

1. **An explicit config override** — `vitamin-d.role_scope_resolver`, a
   fully-qualified class name, `null` by default. When a host application
   sets this, that class is used, full stop, regardless of which plugins are
   installed. This is the same shape Laravel's own
   `config('auth.providers.users.model')` already uses for "config holds a
   class name, with a fallback default".
2. **Whichever plugin bound its own default automatically** — when the
   config value is unset, each provider that binds `RoleScopeResolver` falls
   back to its own default class. `vitamind-core`'s `CoreServiceProvider`
   binds `NullRoleScopeResolver` (always resolves a global, unscoped `[null,
   null]`); `vitamind-workspace-plugin`'s `WorkspaceServiceProvider` rebinds
   it to `WorkspaceRoleScopeResolver` (resolves `['workspace',
   $user->current_workspace_id]`), but only when
   `VITAMIND_FEATURE_WORKSPACES=true`.

A fresh single-tenant install with no tenancy plugin gets
`NullRoleScopeResolver` for free — `$user->hasRole('sales')` checks a
global, unscoped assignment with no configuration required.

### Providing your own `RoleScopeResolver`

Implement the interface and point `vitamin-d.role_scope_resolver` at it —
no service provider or container call required on your end:

```php
namespace App\Support;

use VitaminD\Core\Contracts\RoleScopeResolver;
use VitaminD\Core\Models\User;

class BranchRoleScopeResolver implements RoleScopeResolver
{
    public function resolve(User $user): array
    {
        return ['scope_type' => 'branch', 'scope_id' => $user->current_branch_id];
    }
}
```

```php
// config/vitamin-d.php (published copy)
'role_scope_resolver' => \App\Support\BranchRoleScopeResolver::class,
```

This wins over any plugin-bound default automatically — set it once and
every unscoped `hasRole()` call in the app resolves against your own
scoping concept instead.

## Worked example: workspace invitations consume the registry, but the plugin owns no roles

`vitamind-workspace-plugin` is the canonical consumer of this mechanism, but
it deliberately registers **no roles of its own**. A workspace's owner is
plain data — `workspaces.owner_id`, set once at creation — not a role
assignment, and there is no built-in "workspace admin" tier: only the owner
(or a global `is_admin`) can manage a workspace, full stop. `HasRolePolicies`
reflects this directly, with no role lookup at all:

```php
protected function hasOwnerAccess(User $user, Workspace $workspace): bool
{
    return $workspace->owner_id === $user->id;
}
```

What the plugin *does* do is make every currently registered role
selectable when inviting someone to a workspace. The invite dropdown
(`resources/js/pages/components/invite.tsx`) is driven entirely by the live
`RegisterRole` registry, plus a fixed "Admin" option that grants `is_admin`
instead of a workspace-scoped role. Selecting neither is valid too — the
invitee just becomes a plain member. So an app that registers its own
domain-specific role — say `RegisterRole::make('admin-gudang')` from an
`App\Plugins\Warehouse` provider — becomes selectable on the workspace
invite screen and assignable, scoped to that workspace, through the exact
same accept-invitation flow, with no plugin-specific UI work and nothing
for the workspace plugin to exclude or special-case.

## Testing a role registration

Mirror the pattern in
`dev-packages/vitamind-plugin-sdk/tests/RegisterRoleTest.php`: flush the
registry in `setUp()`, register through a fixture class under a realistic
namespace (so prefix derivation is exercised for real), and assert on
`RegisterRole::get()`/`find()`. For assignment/check behavior, see
`dev-packages/vitamind-core/tests/Unit/UserRoleAssignmentTest.php` and
`UserHasRoleTest.php`, and `tests/Feature/WorkspaceRoleAssignmentTest.php`
for the full HTTP-level invite → accept → role-assigned flow.
