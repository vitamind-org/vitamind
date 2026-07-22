## ADDED Requirements

### Requirement: Plugins can be installed from Composer packages

VitaminD SHALL support installing 1st-party plugins as Composer packages from Packagist, allowing developers to `composer require vitamind-org/plugin-*` to install plugins like workspace, custom domains, etc.

#### Scenario: Developer installs Composer plugin
- **WHEN** developer runs `composer require vitamind-org/plugin-example`
- **THEN** plugin package is downloaded and installed
- **AND** plugin service provider is auto-discovered by Laravel
- **AND** plugin appears in admin plugin list as "installed"
- **AND** plugin can be enabled/disabled via UI

#### Scenario: Plugin is discovered after Composer installation
- **WHEN** `DiscoverPlugins` action runs (at boot or manually)
- **THEN** it scans vendor/vitamind-org/ directory
- **AND** identifies all installed plugin packages
- **AND** registers them in plugin registry database

### Requirement: Plugins can be installed from GitHub repositories

VitaminD SHALL support installing plugins directly from GitHub repositories (both vitamind-org and community), allowing developers to test or use uncommercial plugins without Packagist publishing.

#### Scenario: Developer installs GitHub plugin
- **WHEN** developer runs `php artisan plugin:install-github vitamind-org/plugin-custom`
- **THEN** repository is cloned into `storage/plugins/` directory
- **AND** plugin is discovered and registered
- **AND** plugin can be enabled/disabled like Composer plugins

#### Scenario: GitHub plugin folder structure is validated
- **WHEN** GitHub plugin is cloned
- **THEN** system checks for valid plugin structure (`src/Plugin.php`)
- **AND** if invalid, installation fails with clear error message
- **AND** if valid, plugin is added to registry

#### Scenario: Developer can specify GitHub branch or tag
- **WHEN** developer runs `php artisan plugin:install-github vitamind-org/plugin-custom --branch=dev`
- **THEN** specified branch is cloned instead of main/master
- **AND** plugin version reflects branch name

### Requirement: Local plugins remain discoverable and usable

VitaminD SHALL continue to support local plugins in `app/Plugins/Local/` for development and application-specific features, with seamless discovery alongside Composer and GitHub plugins.

#### Scenario: Local plugin is discovered at boot
- **WHEN** application boots
- **THEN** `DiscoverPlugins` scans `app/Plugins/Local/`
- **AND** finds plugin class files matching namespace convention
- **AND** registers local plugins in registry

#### Scenario: Local plugins do not interfere with Composer/GitHub plugins
- **WHEN** developer has both local and Composer plugins installed
- **THEN** all three types are listed together in admin UI
- **AND** enable/disable works for all types
- **AND** no namespace collisions or loading errors

### Requirement: Plugin registry tracks plugin source

Plugin registry SHALL track where each plugin originated (Composer, GitHub, Local), enabling UI to display source and provide appropriate management actions.

#### Scenario: Plugin registry stores source metadata
- **WHEN** plugin is installed from any source
- **THEN** registry records: name, namespace, version, source (composer/github/local), path
- **AND** admin UI displays source badge (e.g., "From Packagist", "GitHub: vitamind-org/plugin-x")

#### Scenario: Uninstall action depends on source
- **WHEN** plugin is Composer-based and uninstalled
- **THEN** user is instructed to `composer remove` package
- **WHEN** plugin is GitHub-based and uninstalled
- **THEN** system deletes from `storage/plugins/`
- **WHEN** plugin is Local and uninstalled
- **THEN** system deletes from `app/Plugins/Local/`

### Requirement: Plugin dependencies are validated before installation

System SHALL check plugin dependencies before enabling a plugin, ensuring all required dependencies are installed and enabled.

#### Scenario: Plugin with unsatisfied dependencies cannot be enabled
- **WHEN** developer attempts to enable plugin that requires another plugin
- **THEN** system checks if dependency is installed and enabled
- **AND** if not, shows error message listing missing dependencies
- **AND** plugin remains disabled

#### Scenario: Plugin dependencies are listed in plugin metadata
- **WHEN** plugin is displayed in admin UI
- **THEN** "Dependencies" section shows required plugins
- **AND** if dependency is not met, it is highlighted as unavailable

### Requirement: Plugin installation is atomic and recoverable

Plugin installation (from any source) SHALL be atomic: either completely succeed or completely fail without partial state.

#### Scenario: Plugin installation failure is recoverable
- **WHEN** plugin installation fails (e.g., invalid Plugin.php, migration error)
- **THEN** system rolls back any partial installations
- **AND** database changes are reverted
- **AND** files are cleaned up
- **AND** clear error message explains what went wrong

