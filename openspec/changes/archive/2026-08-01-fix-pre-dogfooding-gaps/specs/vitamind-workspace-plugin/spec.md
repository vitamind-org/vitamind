## MODIFIED Requirements

### Requirement: Workspace Plugin is optional and feature-flagged

Workspace Plugin SHALL be an optional plugin (`vitamind/workspace-plugin`) that adds multi-tenancy workspace/project support. It SHALL only activate when environment variable `VITAMIND_FEATURE_WORKSPACES=true` is set, and SHALL have zero impact on single-tenant applications. The plugin's service provider SHALL be registered purely through Composer package auto-discovery (`extra.laravel.providers`) — no manual edit to the host application's `bootstrap/providers.php` SHALL be required to enable or disable the plugin. The feature flag it reads (`vitamin-d.features.workspaces`) SHALL resolve to a real boolean (from `vitamind/core`'s shipped default configuration) even when the host application has not published or created its own `config/vitamin-d.php`.

#### Scenario: Developer installs workspace plugin
- **WHEN** developer runs `composer require vitamind/workspace-plugin`
- **THEN** plugin is installed and its service provider is auto-discovered
- **AND** no database tables are created until feature flag is enabled
- **AND** no routes or middleware are registered yet
- **AND** the host application does not need to edit `bootstrap/providers.php`

#### Scenario: Feature flag enables workspace functionality on a fresh installation
- **WHEN** developer sets `VITAMIND_FEATURE_WORKSPACES=true` in `.env` and runs migrations, in a project that has not published or created its own `config/vitamin-d.php`
- **THEN** the flag still resolves to `true` (via `vitamind/core`'s shipped default config, which reads the same environment variable)
- **AND** workspace tables (workspaces, user_workspaces) are created
- **AND** workspace middleware and routes become active
- **AND** workspace switcher appears in UI

#### Scenario: Feature flag disabled removes workspace functionality
- **WHEN** developer leaves `VITAMIND_FEATURE_WORKSPACES=false` (default)
- **THEN** workspace tables are not created
- **AND** workspace routes are not registered
- **AND** application behaves as single-tenant
- **AND** zero workspace-related database overhead
