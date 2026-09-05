## Why

`RegisterRole::register()` (`dev-packages/vitamind-plugin-sdk`) currently derives a role's registry-key prefix by inspecting the calling class's namespace via `debug_backtrace()` — a guess based on naming convention, not a real identity. There is no standardized way for a plugin, of either kind VitaminD supports (Composer-package ServiceProviders like `vitamind-workspace-plugin`, or dynamically-installed `AbstractPlugin` plugins covering local/GitHub/Composer sources), to declare who it is. This blocks giving `RegisterRole` — and any future plugin-scoped mechanism — a real, explicit identity to key off, and leaves two disconnected, inconsistent metadata surfaces (`$name`/`$description` on `AbstractPlugin`, nothing at all on plain `ServiceProvider`-based plugins).

## What Changes

- Add `VitaminD\PluginSdk\Interfaces\HasPluginDetails`: a one-method contract (`pluginDetails(): array{key, name, description}`) every plugin, of either kind, must implement.
- **BREAKING**: `AbstractPlugin` (dynamically-installed plugins: local/GitHub/Composer) now requires `pluginDetails()` and drops `$name`/`$description`/`getName()`/`getDescription()` entirely — consolidated into one method.
- **BREAKING**: `PluginInterface` drops `getName(): string`/`getDescription(): string` from its contract, gains `HasPluginDetails` instead.
- Add `VitaminD\PluginSdk\PluginBase`: a new abstract `ServiceProvider` base class for Composer-package plugins (`vitamind-workspace-plugin`, `vitamind-archive-plugin`, `vitamind-realtime-plugin`, and any future one), also requiring `pluginDetails()` — giving this class of plugin a real identity for the first time (it had none before).
- Add `VitaminD\PluginSdk\Concerns\RegistersOwnRole`: a shared trait (used by both `AbstractPlugin` and `PluginBase`) providing `registerRole(string $shortKey, string $title)` — plugin authors call this instead of `RegisterRole` directly, and their declared `pluginDetails()['key']` is threaded through automatically.
- **BREAKING**: `RegisterRole::register()` now requires an explicit `string $pluginKey` argument. The `debug_backtrace()`/namespace-guessing fallback is removed entirely — no implicit resolution path remains.
- Migrate `WorkspaceServiceProvider`, `ArchiveServiceProvider`, `RealtimeServiceProvider` to extend `PluginBase` and implement `pluginDetails()`.
- Migrate `vitamind-todo-plugin`'s `Plugin` (dynamic, demo) and `App\Plugins\HelloWorld\Plugin` (local) to `pluginDetails()` and the new `registerRole()` helper. Neither's resulting role/behavior changes observably (`todo-plugin.manager` stays the same key).
- Update `InstallPlugin`/`EnablePlugin` (the dynamic-plugin lifecycle actions in `vitamind-core`) to read plugin metadata from `pluginDetails()` instead of `getName()`/`getDescription()`.

## Capabilities

### New Capabilities
- `plugin-identity-declaration`: every VitaminD plugin (Composer-package ServiceProvider or dynamically-installed) declares its own identity (`key`, `name`, `description`) via a required `pluginDetails()` method, consumed by `RegisterRole` and by the dynamic-plugin install/enable lifecycle for display metadata.

### Modified Capabilities
(none — no existing archived capability spec currently documents `RegisterRole`'s prefixing behavior or `AbstractPlugin`'s `getName()`/`getDescription()` contract; this is net-new specification, not a change to a previously-specified requirement)

## Impact

- **New**: `vitamind-plugin-sdk` — `Interfaces/HasPluginDetails.php`, `Concerns/RegistersOwnRole.php`, `PluginBase.php`.
- **Changed (breaking)**: `vitamind-plugin-sdk` — `AbstractPlugin.php`, `Interfaces/PluginInterface.php`, `RegisterRole.php`. `vitamind-core` — `Actions/Plugins/InstallPlugin.php`, `Actions/Plugins/EnablePlugin.php`. `vitamind-workspace-plugin`, `vitamind-archive-plugin`, `vitamind-realtime-plugin` — their `*ServiceProvider.php`. `vitamind-todo-plugin` — `src/Plugin.php`. `app/Plugins/HelloWorld/Plugin.php`.
- **Tests**: `vitamind-plugin-sdk`'s `AbstractPluginTest.php`, `RegisterRoleTest.php` rewritten; new `PluginBaseTest.php`; `tests/Fixtures/Plugins/PackageA/` and `PackageB/` (existed only to simulate namespace-guessing) removed.
- **Docs**: `docs/plugin-development/role-registration.md` — "Automatic key prefixing" section rewritten.
- **Unaffected**: `DiscoverPlugins`, `PluginCache`, `BootPlugins`, `InstallPluginFromGithub`, `Github\InstallGithubPlugin` — no database schema change, no plugin-record identity tracking needed; identity is resolved purely from the plugin object itself at the point it registers a role.
