# vitamind-core Specification

## Purpose

VitaminD Core is the standalone Composer package (`vitamind/core`) that provides the foundational functionality shared by every VitaminD-based application: user management, authentication (Fortify/Sanctum), admin panel routing infrastructure, the plugin engine, the REST bootstrap API, and Inertia shared-data wiring. It is installable into any fresh Laravel 13+ project independent of the VitaminD boilerplate, and it has no knowledge of optional packages (such as the workspace plugin) that extend it.

## Requirements

### Requirement: VitaminD Core is installable as standalone Composer package

VitaminD Core SHALL be extractable as a standalone Composer package (`vitamind/core`) that can be required in any fresh Laravel 13+ installation without the boilerplate. It SHALL contain user management, authentication, admin panel infrastructure, plugin engine, REST API, and bootstrap system.

#### Scenario: Developer installs VitaminD Core in fresh Laravel
- **WHEN** developer runs `composer require vitamind/core` in a fresh Laravel 13+ project
- **THEN** package is installed with all dependencies (Fortify, Sanctum, Route Attributes, etc.)
- **AND** core service providers are auto-discovered and registered
- **AND** core database migrations are available via `php artisan migrate`
- **AND** core routes, controllers, and models are accessible in application

#### Scenario: Core functionality works without boilerplate
- **WHEN** core is installed standalone
- **THEN** user authentication flows work (login, register, password reset)
- **AND** admin panel routes are accessible at `/admin/`
- **AND** plugin system can discover and boot plugins
- **AND** API endpoints (`/api/health`, `/api/bootstrap`) respond correctly

### Requirement: Core namespace is VitaminD\Core and does not conflict with App namespace

The core package SHALL use `VitaminD\Core\*` namespace for all its classes. Boilerplate-specific application code SHALL remain in `App\*` namespace, allowing developers to extend without namespace conflicts. Core's own internal code SHALL NOT hardcode the concrete `App\Models\User` (or `App\Models\PersonalAccessToken`) class name at call sites that instantiate or query the model (e.g. `Model::create()`, `Model::query()`); such call sites SHALL resolve the active model class dynamically via `config('auth.providers.users.model')` (or the equivalent Sanctum swappable-model registration) so that application-layer mixins are preserved. Call sites that only type-hint or match a class for authorization purposes (policy method signatures, `Gate::policy()`, `Rule::unique()`) MAY reference `VitaminD\Core\Models\User` directly, since Laravel's policy resolution and PHP type covariance already resolve subclasses correctly.

#### Scenario: Core and App models coexist
- **WHEN** developer creates `App\Models\User` in boilerplate
- **THEN** it MUST extend `VitaminD\Core\Models\User` for core's authentication, admin, and API-key flows to function (calling `isAdmin()`, `HasApiTokens`, `TwoFactorAuthenticatable` behavior core depends on)
- **AND** no namespace collisions occur
- **AND** both namespaces are registered in autoloader via separate PSR-4 prefixes

#### Scenario: Creating a user through core preserves application-layer mixins
- **WHEN** a new user is created through a core-provided flow (registration, admin user creation) in an application whose `App\Models\User` mixes in additional behavior (e.g. `HasWorkspaces`)
- **THEN** the created instance is of the application's configured model class (`config('auth.providers.users.model')`), not the bare `VitaminD\Core\Models\User`
- **AND** application-layer relations/methods (e.g. `workspaces()`) are available on the returned instance without an extra re-fetch

### Requirement: Core migrations register separately from boilerplate

Core database migrations SHALL be stored in `dev-packages/vitamind-core/database/migrations/` and registered with Laravel's migrator independently from boilerplate migrations. Core SHALL NOT ship migrations that recreate tables already provided by a fresh Laravel installation's own default migrations (`users`, `cache`, `jobs`, and their companion tables `password_reset_tokens`, `sessions`, `cache_locks`, `job_batches`, `failed_jobs`); it SHALL only ship migrations for tables/columns it introduces itself, using incremental `Schema::table()` alterations when extending a table the host application already owns.

#### Scenario: Core migrations run alongside a fresh Laravel installation's own migrations
- **WHEN** developer runs `php artisan migrate` in a fresh Laravel project that already ran its own default migrations (users, cache, jobs)
- **THEN** core migrations (plugins, plugin_errors, personal_access_tokens, passkeys tables, plus `add_auth_fields_to_users_table` and `add_two_factor_columns_to_users_table` alterations) run without attempting to recreate `users`, `cache`, or `jobs`
- **AND** no "table already exists" errors occur
- **AND** migrations are idempotent (can run multiple times safely)

#### Scenario: Core migrations run before app migrations
- **WHEN** developer runs `php artisan migrate`
- **THEN** core's own tables (plugins, personal_access_tokens, plugin_errors, passkeys) are created
- **AND** core's `users`-table alterations run only after the host application's own `users` table exists
- **AND** then boilerplate-specific migrations run
- **AND** no foreign key constraint errors occur

### Requirement: Core provides service provider hooks for plugin registration

Core SHALL expose service provider hooks and middleware registries so that external packages (like workspace plugin) can register their services without core knowing about them. Core SHALL ship default configuration for the feature flags it and its optional plugins read, so that a fresh installation with no application-published config still resolves feature flags to their documented defaults rather than to `null`.

#### Scenario: Workspace plugin registers own middleware
- **WHEN** workspace plugin is installed and feature flag enabled
- **THEN** it registers its middleware via core's middleware registry
- **AND** core does not have direct dependency on workspace package
- **AND** workspace can be removed without breaking core boot sequence

#### Scenario: Feature flags resolve without an application-published config file
- **WHEN** `vitamind/core` is installed in a fresh Laravel project that has not published or created its own `config/vitamin-d.php`
- **THEN** `config('vitamin-d.features.workspaces')` (and other `vitamin-d.features.*` keys) resolve to core's shipped defaults rather than `null`
- **AND** setting the corresponding `VITAMIND_FEATURE_*` environment variable changes the resolved value, because core's default config file reads from `env()`

### Requirement: Core maintains Fortify and Sanctum configuration

Core SHALL include sensible defaults for Laravel Fortify (authentication) and Laravel Sanctum (API tokens) but allow boilerplate to override via config publishing.

#### Scenario: 2FA and password rules work out of the box
- **WHEN** user logs in and 2FA is enabled
- **THEN** 2FA challenge flow works without additional boilerplate configuration
- **AND** password validation rules (Fortify defaults) are applied
- **AND** developers can customize via `config/fortify.php` override

### Requirement: Core provides Admin panel routing infrastructure

Core SHALL provide base routing structure for admin area (`/admin/*`) with admin-only middleware and authorization checks. Boilerplate and plugins extend this with specific admin pages.

#### Scenario: Admin routes are registered and protected
- **WHEN** admin controller extends `VitaminD\Core\Http\Controllers\AdminController`
- **THEN** routes are registered under `/admin/` prefix
- **AND** `MustBeAdmin` middleware is automatically applied
- **AND** non-admin users receive 403 Forbidden on admin endpoints
