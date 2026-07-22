## ADDED Requirements

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

The core package SHALL use `VitaminD\Core\*` namespace for all its classes. Boilerplate-specific application code SHALL remain in `App\*` namespace, allowing developers to extend without namespace conflicts.

#### Scenario: Core and App models coexist
- **WHEN** developer creates `App\Models\User` in boilerplate
- **THEN** it MAY extend `VitaminD\Core\Models\User` or use composition
- **AND** no namespace collisions occur
- **AND** both namespaces are registered in autoloader via separate PSR-4 prefixes

### Requirement: Core migrations register separately from boilerplate

Core database migrations SHALL be stored in `dev-packages/vitamind-core/database/migrations/` and registered with Laravel's migrator independently from boilerplate migrations.

#### Scenario: Core migrations run before app migrations
- **WHEN** developer runs `php artisan migrate`
- **THEN** core migrations (users, plugins, personal_access_tokens, plugin_errors tables) run
- **AND** then boilerplate-specific migrations run
- **AND** no foreign key constraint errors occur
- **AND** migrations are idempotent (can run multiple times safely)

### Requirement: Core provides service provider hooks for plugin registration

Core SHALL expose service provider hooks and middleware registries so that external packages (like workspace plugin) can register their services without core knowing about them.

#### Scenario: Workspace plugin registers own middleware
- **WHEN** workspace plugin is installed and feature flag enabled
- **THEN** it registers its middleware via core's middleware registry
- **AND** core does not have direct dependency on workspace package
- **AND** workspace can be removed without breaking core boot sequence

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

