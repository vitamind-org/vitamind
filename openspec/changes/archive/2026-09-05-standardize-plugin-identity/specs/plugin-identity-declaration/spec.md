## ADDED Requirements

### Requirement: Every plugin declares its own identity via a required method
Both VitaminD plugin systems — Composer-package `ServiceProvider`-based plugins (extending `VitaminD\PluginSdk\PluginBase`) and dynamically-installed plugins (extending `VitaminD\PluginSdk\AbstractPlugin`, covering local, GitHub, and Composer sources) — SHALL implement a required `pluginDetails(): array` method returning at least a `key`, a `name`, and a `description`. A plugin class that does not implement this method SHALL fail to instantiate.

#### Scenario: A ServiceProvider-based plugin declares its identity
- **WHEN** a Composer-package plugin's `ServiceProvider` extends `PluginBase` and implements `pluginDetails()`
- **THEN** the returned array's `key`, `name`, and `description` are available to that plugin's own code

#### Scenario: A dynamically-installed plugin declares its identity
- **WHEN** a plugin's `Plugin` class extends `AbstractPlugin` and implements `pluginDetails()`
- **THEN** the returned array's `key`, `name`, and `description` are available to that plugin's own code, regardless of whether it was installed from a local folder, GitHub, or Composer

#### Scenario: A plugin class omits pluginDetails()
- **WHEN** a class extends `AbstractPlugin` or `PluginBase` without implementing `pluginDetails()`
- **THEN** the class cannot be instantiated (it remains abstract)

### Requirement: Plugins register roles through their own declared identity, not a guessed namespace
Both `AbstractPlugin` and `PluginBase` SHALL provide a `registerRole(string $shortKey, string $title)` helper (via a shared trait) that registers a role scoped to the plugin's own declared `pluginDetails()['key']`, without the plugin author having to repeat that key at the call site. `VitaminD\PluginSdk\RegisterRole::register()` SHALL require an explicit plugin key argument and SHALL NOT infer it from the caller's namespace or call stack.

#### Scenario: A plugin registers a role via the helper
- **WHEN** a plugin whose `pluginDetails()` returns `key => 'acme'` calls `$this->registerRole('manager', 'Manager')`
- **THEN** the role is registered under the key `acme.manager`

#### Scenario: Two different plugins register a role with the same short key
- **WHEN** a plugin with `key => 'acme'` and a plugin with `key => 'other'` each call `registerRole('manager', ...)`
- **THEN** both roles are registered independently, as `acme.manager` and `other.manager`, without colliding

#### Scenario: Registering a role without an explicit plugin key is rejected
- **WHEN** code calls `RegisterRole::make(...)->register()` without supplying a plugin key
- **THEN** the call fails (missing required argument) rather than silently guessing a prefix from the calling code's namespace

### Requirement: Plugin metadata for installation and display comes from the declared identity
The dynamic-plugin installation lifecycle (`InstallPlugin`, `EnablePlugin` in `vitamind-core`) SHALL read a plugin's display `name` and `description` from its `pluginDetails()` return value, not from separate getter methods.

#### Scenario: Installing a plugin records its declared name and description
- **WHEN** a dynamically-installed plugin is installed
- **THEN** the stored plugin record's `name` and `description` match the values returned by that plugin's `pluginDetails()`

#### Scenario: Enabling a plugin refreshes its declared name and description
- **WHEN** a dynamically-installed plugin is enabled
- **THEN** the stored plugin record's `name` and `description` are refreshed from that plugin's current `pluginDetails()` return value
