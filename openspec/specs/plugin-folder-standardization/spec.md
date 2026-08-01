# plugin-folder-standardization Specification

## Purpose

Plugin folder standardization defines the on-disk structure every VitaminD plugin must follow, based on its source. Local plugins (`app/Plugins/`) are flat, with no `src/` folder, and rely on the boilerplate's own `App\Plugins\` PSR-4 autoload entry. GitHub and Composer plugins place all class code under `src/`, following PSR-4 conventions declared in their own `composer.json`. Database and route files stay outside `src/` in both cases. This standard keeps discovery, autoloading, generators, and documentation consistent across all plugin sources.

## Requirements

### Requirement: Local plugin class code has no src/ folder

Local plugins (`app/Plugins/`) SHALL place their PHP class code directly at the
plugin folder root, with no `src/` subdirectory. This includes the Plugin
class, Models, Controllers, Actions, Resources, etc. Namespace is always
`App\Plugins\{PluginName}`, resolved from the folder name — not declared via a
per-plugin `composer.json` PSR-4 entry.

#### Scenario: Local plugin Plugin.php is at the plugin root
- **WHEN** the plugin system loads a local plugin
- **THEN** it expects the Plugin class at `app/Plugins/{PluginName}/Plugin.php`
- **AND** namespace is `App\Plugins\{PluginName}`
- **EXAMPLE**: `app/Plugins/HelloWorld/Plugin.php` → `namespace App\Plugins\HelloWorld;` class `Plugin`

### Requirement: GitHub and Composer plugin class code is in src/ folder

GitHub-installed plugins (`storage/plugins/`) and Composer plugins
(`vendor/vitamind/*`) SHALL place their PHP class code in a `src/` folder,
following PSR-4 namespace conventions declared in their own `composer.json`.
This includes Plugin class, Models, Controllers, Actions, Resources, etc.

#### Scenario: Plugin namespace maps to src/ folder via composer.json
- **WHEN** a GitHub or Composer plugin is discovered
- **THEN** its namespace is read from `composer.json`'s `autoload.psr-4` map, not guessed from the folder name
- **EXAMPLE**: `composer.json` declares `"VitaminD\\Plugins\\Acme\\HelloWorld\\": "src/"` → class `Plugin` resolves to file `src/Plugin.php`

#### Scenario: Plugin Plugin.php is at src/Plugin.php
- **WHEN** the plugin system loads a GitHub or Composer plugin
- **THEN** it expects the Plugin class at `src/Plugin.php` relative to the plugin root
- **AND** discovery skips the folder (does not register it as a plugin) if no PSR-4 prefix in `composer.json` points to a `Plugin.php` file

### Requirement: Database and route files remain outside src/

Plugin migrations, seeds, and route files SHALL remain in dedicated folders
outside `src/` (or outside the plugin root, for local plugins), following
Laravel conventions.

#### Scenario: GitHub/Composer plugin folder structure is standardized
- **WHEN** a developer creates a new GitHub or Composer plugin
- **THEN** folder structure is:
  ```
  plugin-name/
  ├── src/
  │   ├── Plugin.php
  │   ├── Models/
  │   ├── Controllers/
  │   ├── Actions/
  │   └── ...
  ├── database/
  │   ├── migrations/
  │   └── seeders/
  ├── routes/
  │   └── web.php
  ├── composer.json
  └── ...
  ```

#### Scenario: Local plugin folder structure is standardized
- **WHEN** a developer creates a new local plugin
- **THEN** folder structure is:
  ```
  app/Plugins/MyPlugin/
  ├── Plugin.php
  ├── Models/
  ├── Controllers/
  ├── Resources/
  ├── routes/
  └── database/
      └── migrations/
  ```

### Requirement: Existing Local Plugins are migrated to the flat structure

Existing local plugins (like MockProduct) SHALL be migrated from the old
`app/Plugins/Local/{Vendor}/{Name}/` nesting to the flat `app/Plugins/{Name}/`
structure as part of Phase 1.

#### Scenario: MockProduct is migrated to the flat structure
- **WHEN** Phase 1 is complete
- **THEN** `app/Plugins/MockProduct/` has the following structure:
  ```
  MockProduct/
  ├── Plugin.php
  ├── Models/MockProduct.php
  ├── Models/MockCategory.php
  ├── Controllers/
  ├── Resources/
  └── database/
      └── migrations/
  ```
- **AND** namespace is `App\Plugins\MockProduct` (was `App\Plugins\Local\Acme\MockProduct`)

#### Scenario: Local plugins after migration still work
- **WHEN** the application boots with migrated local plugins
- **THEN** the plugin is discovered correctly
- **AND** the plugin can be enabled/disabled
- **AND** no functionality is lost from the migration

### Requirement: Plugin generator creates the correct structure per source

Plugin generator (future tooling) SHALL scaffold new plugins with the `src/`
structure for GitHub/Composer plugins, and the flat (no `src/`) structure for
local plugins, by default.

#### Scenario: New local plugin scaffold has no src/ folder
- **WHEN** a developer creates a new local plugin via `php artisan plugin:make MyPlugin`
- **THEN** the generated structure has `Plugin.php` at the plugin root, with no `src/` folder

#### Scenario: New GitHub/Composer plugin scaffold includes src/ folder
- **WHEN** a developer scaffolds a new distributable plugin package
- **THEN** the generated structure includes a `src/` folder
- **AND** `Plugin.php` is generated at `src/Plugin.php`

### Requirement: Plugin autoload configuration is consistent per source

GitHub and Composer plugins SHALL declare their PSR-4 namespace in
`composer.json` with a consistent pattern pointing to `src/`. Local plugins
rely on the boilerplate's own `App\Plugins\` autoload entry — no per-plugin
`composer.json` is needed.

#### Scenario: Composer/GitHub plugin has standard autoload
- **WHEN** a Composer or GitHub plugin's `composer.json` is examined
- **THEN** its autoload section contains:
  ```json
  {
    "autoload": {
      "psr-4": {
        "VitaminD\\Plugins\\[Vendor]\\[PluginName]\\": "src/"
      }
    }
  }
  ```
- **AND** this allows `DiscoverPlugins` to resolve the plugin's namespace and, for GitHub plugins (which aren't part of the Composer autoloader), register it for autoloading at runtime

#### Scenario: Local plugin autoload is registered in boilerplate
- **WHEN** the boilerplate `composer.json` is examined
- **THEN** it includes the autoload rule:
  ```json
  {
    "autoload": {
      "psr-4": {
        "App\\Plugins\\": "app/Plugins/"
      }
    }
  }
  ```
- **AND** any new folder under `app/Plugins/{PluginName}/` is auto-discovered on the next request, with no `composer dump-autoload` required for local plugins added after the initial one

### Requirement: Documentation and tests follow src/ location

Plugin documentation (README), configuration files, and tests SHALL be located
at the plugin root or in dedicated folders, not mixed within `src/` (where
applicable).

#### Scenario: Plugin structure is clean and organized
- **WHEN** a GitHub or Composer plugin is examined
- **THEN** `src/` contains only PHP classes
- **AND** test files are in `tests/` (if included)
- **AND** README.md is at the plugin root
- **AND** documentation for developers is clear
