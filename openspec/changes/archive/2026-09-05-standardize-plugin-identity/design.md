## Context

`RegisterRole::register()` (`dev-packages/vitamind-plugin-sdk/src/RegisterRole.php`) determines a role's registry-key prefix by inspecting the immediate caller's namespace via `debug_backtrace()` + a `prefixFromClass()` convention (`...\Plugins\{Name}\...` → kebab-case `{Name}`). This is a guess, not a real identity, and it's the only automatic-derivation mechanism anywhere in the plugin-sdk — every other `Register*` class (`RegisterPage`, `RegisterPageGroup`, `RegisterCommand`, `RegisterViews`) takes a fully manual key with no isolation at all.

VitaminD has two structurally different plugin systems:
1. **Composer-package plugins** (`vitamind-workspace-plugin`, `vitamind-archive-plugin`, `vitamind-realtime-plugin`): plain Laravel `ServiceProvider` subclasses, loaded via Composer's `extra.laravel.providers` auto-discovery. They have **no identity concept at all** today — no `Plugin` DB record, no name/description surface, nothing.
2. **Dynamically-installed plugins** (`AbstractPlugin`, e.g. `vitamind-todo-plugin`, `App\Plugins\HelloWorld`): installable from local folders, GitHub, or Composer, discovered by `DiscoverPlugins` into a `Plugin` DB model, booted by `BootPlugins`/`GetPluginInstance`. These already have `$name`/`$description`/`getName()`/`getDescription()` on `AbstractPlugin`, but that metadata is never accessible from *inside* a lifecycle method (`boot()` is called with zero arguments), and the `Plugin` DB model's own `namespace`/`folder` fields are likewise inaccessible from inside the plugin instance itself.

An earlier design iteration explored making the *dynamic* system's `Plugin` DB row (adding a `slug` column, populated from `composer.json`'s `name` field) accessible to a booting plugin via a container-bound/static "current plugin" context set by `BootPlugins` and friends. That approach was **abandoned** in favor of the one below: rather than deriving identity indirectly (DB row → ambient context → consulted by `RegisterRole`), each plugin **declares its own identity directly, in code, on itself** — simpler, requires no database changes, and naturally unifies both plugin systems under one small contract instead of solving the problem twice.

## Goals / Non-Goals

**Goals:**
- One explicit, required way for *any* VitaminD plugin — Composer-package `ServiceProvider` or dynamically-installed `AbstractPlugin` — to declare its own identity (`key`, `name`, `description`).
- `RegisterRole` keys off that declared identity directly — no guessing, no ambient state, no database lookup.
- Plugin authors never retype their own key at each call site: a `registerRole()` convenience method (shared by both plugin kinds via one trait) threads the declared key through automatically.
- Full consistency: the same mechanism, the same contract, for every plugin regardless of how it's installed (local, GitHub, Composer) or which base class it extends.

**Non-Goals:**
- No database schema change. Identity is resolved from the plugin object itself, at the moment it registers something — not persisted, not looked up.
- No global uniqueness validation for `key` (consistent with `RegisterPage` and friends, which don't validate key uniqueness either).
- No change to `DiscoverPlugins`, `PluginCache`, `BootPlugins`, `InstallPluginFromGithub`, or `Github\InstallGithubPlugin` — none of these need to know about plugin identity under this design.
- No permission/authorization semantics — this is purely an identity-declaration mechanism, orthogonal to (and already consumed by) `RegisterRole`'s existing role-assignment behavior from the `multi-role-authorization` work.

## Decisions

### 1. One shared contract, `HasPluginDetails`, consumed by both plugin kinds

`VitaminD\PluginSdk\Interfaces\HasPluginDetails` (plugin-sdk's existing `Interfaces/` convention): a single method, `pluginDetails(): array{key: string, name: string, description: string}`. `PluginInterface` (implemented by `AbstractPlugin`) now `extends HasPluginDetails`; the new `PluginBase` (for Composer-package `ServiceProvider`s) `implements HasPluginDetails` directly, since it doesn't share `PluginInterface`'s lifecycle contract (`boot/enable/disable/install/uninstall` vs. `ServiceProvider`'s `register/boot`).

**Alternative considered**: two separate, unrelated identity methods (one per base class). Rejected — defeats the consistency goal; `RegisterRole`/`RegistersOwnRole` would need to special-case both shapes instead of relying on one contract.

### 2. `pluginDetails()` consolidates existing metadata, replacing `$name`/`$description`/`getName()`/`getDescription()`

Rather than adding `key` as a fourth, disconnected property alongside the existing two, `AbstractPlugin`'s `$name`/`$description` properties and `getName()`/`getDescription()` methods are removed entirely — `pluginDetails()` becomes the one place a plugin declares all of its own metadata. `InstallPlugin`/`EnablePlugin` (the only two call sites of `getName()`/`getDescription()` outside tests, confirmed by repo-wide search) are updated to read `pluginDetails()['name']`/`['description']` instead.

**Alternative considered**: keep `$name`/`$description` as-is, add `pluginDetails()` purely for `key`. Rejected per explicit direction — an array with a single meaningful key is an awkward API, and having two separate metadata surfaces on the same class invites drift between them.

### 3. Shared trait `RegistersOwnRole`, not duplicated logic

`AbstractPlugin` and `PluginBase` share no common ancestor (one has no parent, implements `PluginInterface`; the other extends Laravel's `ServiceProvider`), so the `registerRole()`/`pluginKey()` convenience logic can't live on a common base class. A trait (`VitaminD\PluginSdk\Concerns\RegistersOwnRole`, plugin-sdk's existing `Concerns/` convention) is `use`'d by both:

```php
trait RegistersOwnRole
{
    abstract public function pluginDetails(): array;

    protected function registerRole(string $shortKey, string $title): void
    {
        RegisterRole::make($shortKey)->title($title)->register($this->pluginKey());
    }

    protected function pluginKey(): string
    {
        $key = $this->pluginDetails()['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new \LogicException(static::class.'::pluginDetails() must return a non-empty "key".');
        }

        return $key;
    }
}
```

The `pluginKey()` guard turns a malformed `pluginDetails()` implementation into a clear `LogicException` at the point of use, rather than a cryptic "undefined array key" notice surfacing later.

### 4. `RegisterRole::register()` takes an explicit, required `$pluginKey` — the namespace-guessing fallback is deleted, not kept

```php
public function register(string $pluginKey): void
{
    $this->key = $pluginKey.'.'.$this->shortKey;
    self::$registry[$this->key] = $this;
}
```

`prefixFromClass()`, the `debug_backtrace()` call, and the now-unused `Illuminate\Support\Str` import are removed from `RegisterRole.php` outright. This is viable *now*, safely, because after the prior `multi-role-authorization` change removed workspace-plugin's built-in role registrations, **`RegisterRole::make(...)->register()` has exactly one production caller left** (`vitamind-todo-plugin`'s demo `Plugin`) plus test fixtures built specifically to exercise namespace-guessing — both are migrated as part of this change, so nothing is left depending on the old fallback path.

**Alternative considered**: keep `debug_backtrace()` as a fallback for callers that haven't adopted `pluginDetails()`/`registerRole()` yet, for a softer migration. Rejected per explicit direction — with zero remaining production callers of the old path, a fallback would only mask a caller that forgot to migrate, not help anyone still using it legitimately.

### 5. `pluginDetails()` is abstract (hard-required), not defaulted

Both `AbstractPlugin::pluginDetails()` and `PluginBase::pluginDetails()` are declared `abstract` — any plugin class not implementing it fails to instantiate (PHP fatal error on `new $namespace` for the dynamic system, or on Laravel's provider boot for the Composer-package system). This is a breaking change accepted deliberately: the project is pre-release (`dev-master` across all packages), so it's cheaper to require this now than to carry a permissive default indefinitely. For the dynamic system specifically, `GetPluginInstance::handle()` already wraps instantiation in a `try/catch (Throwable)` that records a `PluginError` rather than crashing the request — so a third-party plugin that hasn't been updated degrades to "shows an error in the admin plugin list," not a hard failure.

### 6. Composer-package plugins get real identity for the first time

`WorkspaceServiceProvider`, `ArchiveServiceProvider`, `RealtimeServiceProvider` are migrated from `extends ServiceProvider` to `extends PluginBase`, each implementing `pluginDetails()` (key/name/description sourced from their own `composer.json`, which already carries this metadata). None of the three currently calls `registerRole()` — workspace-plugin's own built-in roles were already removed in the prior `multi-role-authorization` change — so this migration is establishing the standard uniformly ahead of need, not fixing an active bug.

## Risks / Trade-offs

- **[Risk]** Any third-party plugin already built against the published `vitamind/plugin-sdk` `AbstractPlugin`/`PluginInterface` contract (`getName()`/`getDescription()`, no `pluginDetails()`) breaks on upgrade. → **Mitigation**: accepted deliberately (pre-1.0, `dev-master`); the dynamic-plugin loader already isolates a broken plugin's fatal error into a logged `PluginError` rather than crashing the host app.
- **[Risk]** Removing the `debug_backtrace()` fallback entirely means any *future* ad-hoc caller of `RegisterRole::make(...)->register()` outside the two sanctioned base classes gets a hard type error (missing required argument) instead of a silent guess. → **Mitigation**: this is the intended behavior — role registration is now scoped to "declared plugins" by design, and a clear missing-argument error is preferable to a silent, possibly-wrong guessed prefix.
- **[Trade-off]** `pluginDetails()` returning a plain `array` (not a typed DTO) trades static type safety for matching the exact shape requested and staying consistent with how `AbstractPlugin` already exposed metadata (loose properties, not a value object). Revisit if plugin-sdk moves toward typed DTOs more broadly (it already has a `DTOs/` folder for other concerns).

## Migration Plan

No database migration, no data backfill — this is a pure code-contract change. Rollout order (also the task order):
1. `vitamind-plugin-sdk`: add `HasPluginDetails`, `RegistersOwnRole`; update `PluginInterface`, `AbstractPlugin`; add `PluginBase`; update `RegisterRole`.
2. `vitamind-core`: update `InstallPlugin`, `EnablePlugin` to read `pluginDetails()`.
3. Migrate the three Composer-package `ServiceProvider`s (`workspace`/`archive`/`realtime`-plugin) to `PluginBase`.
4. Migrate `vitamind-todo-plugin`'s `Plugin` and `App\Plugins\HelloWorld\Plugin` to `pluginDetails()`/`registerRole()`.
5. Rewrite `vitamind-plugin-sdk` tests (`AbstractPluginTest`, `RegisterRoleTest`; add `PluginBaseTest`; remove the now-pointless `Fixtures/Plugins/PackageA`/`PackageB` namespace-simulation fixtures).
6. Update `docs/plugin-development/role-registration.md`.

Rollback: revert the commit(s) — there's no persisted state to unwind.

## Open Questions

None outstanding — all four fork points (scope across both plugin systems, hard-require vs. default, metadata consolidation, fallback removal) were resolved explicitly before this design was written.
