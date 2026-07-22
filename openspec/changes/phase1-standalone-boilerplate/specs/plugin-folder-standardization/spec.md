## ADDED Requirements

### Requirement: All plugin class code is in src/ folder

All VitaminD plugins (Composer, GitHub, Local) SHALL place their PHP class code in `src/` folder, following PSR-4 namespace conventions. This includes Plugin class, Models, Controllers, Actions, Resources, etc.

#### Scenario: Plugin namespace maps to src/ folder
- **WHEN** plugin is structured with `src/` folder
- **THEN** PSR-4 autoloading correctly maps namespace to files
- **EXAMPLE**: `namespace VitaminD\Plugins\Acme\HelloWorld;` class `Plugin` → file `src/Plugin.php`
- **AND** all plugin classes follow this pattern

#### Scenario: Plugin Plugin.php is at src/Plugin.php
- **WHEN** plugin system loads plugin
- **THEN** it expects Plugin class at `src/Plugin.php` relative to plugin root
- **AND** plugin loading fails with clear error if Plugin.php not found in expected location

### Requirement: Database and route files remain outside src/

Plugin migrations, seeds, and route files SHALL remain in dedicated folders outside `src/`, following Laravel conventions.

#### Scenario: Plugin folder structure is standardized
- **WHEN** developer creates new plugin
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
- **AND** this structure works for Composer, GitHub, and Local plugins

### Requirement: Existing Local Plugins are migrated to src/ structure

Existing local plugins (like MockProduct) SHALL be migrated from flat structure to `src/` structure as part of Phase 1.

#### Scenario: MockProduct is migrated to src/ structure
- **WHEN** Phase 1 is complete
- **THEN** `app/Plugins/Local/Acme/MockProduct/` has following structure:
  ```
  MockProduct/
  ├── src/
  │   ├── Plugin.php
  │   ├── Models/MockProduct.php
  │   ├── Models/MockCategory.php
  │   ├── Controllers/
  │   └── Resources/
  ├── database/
  │   └── migrations/
  └── ...
  ```
- **AND** PSR-4 autoloading works without issues

#### Scenario: Local plugins after migration still work
- **WHEN** application boots with migrated local plugins
- **THEN** plugin is discovered correctly
- **AND** plugin can be enabled/disabled
- **AND** no functionality is lost from migration

### Requirement: Plugin generator creates src/ structure

Plugin generator (future tooling) SHALL scaffold new plugins with `src/` structure by default, helping developers follow standardization from the start.

#### Scenario: New plugin scaffold includes src/ folder
- **WHEN** developer creates new plugin via `php artisan plugin:make MyPlugin`
- **THEN** generated structure includes `src/` folder
- **AND** Plugin.php is generated at `src/Plugin.php`
- **AND** Models, Controllers etc. are scaffolded in appropriate `src/` subdirectories

### Requirement: Plugin autoload configuration is consistent

All plugins (Composer, GitHub, Local) SHALL declare their PSR-4 namespace in `composer.json` with consistent pattern pointing to `src/` folder.

#### Scenario: Composer plugin has standard autoload
- **WHEN** Composer plugin `composer.json` is examined
- **THEN** autoload section contains:
  ```json
  {
    "autoload": {
      "psr-4": {
        "VitaminD\\Plugins\\[Vendor]\\[PluginName]\\": "src/"
      }
    }
  }
  ```
- **AND** this allows boilerplate to auto-discover and load plugin classes

#### Scenario: Local plugin autoload is registered in boilerplate
- **WHEN** boilerplate `composer.json` configures local plugins
- **THEN** it includes autoload rule:
  ```json
  {
    "autoload": {
      "psr-4": {
        "App\\Plugins\\Local\\": "app/Plugins/Local/"
      }
    }
  }
  ```
- **AND** local plugins are auto-discovered on `composer dump-autoload`

### Requirement: Documentation and tests follow src/ location

Plugin documentation (README), configuration files, and tests SHALL be located at plugin root or in dedicated folders, not mixed within `src/`.

#### Scenario: Plugin structure is clean and organized
- **WHEN** plugin is examined
- **THEN** `src/` contains only PHP classes
- **AND** test files are in `tests/` (if included)
- **AND** README.md is at plugin root
- **AND** documentation for developers is clear

