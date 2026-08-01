# vitamind-workspace-plugin Specification

## Purpose

Workspace Plugin (`vitamind/workspace-plugin`) is the optional, feature-flagged package that adds multi-tenancy workspace/project support on top of VitaminD Core. It depends on core but core has no knowledge of it, so single-tenant applications can omit the plugin entirely with zero database or runtime overhead. When enabled, it provides workspace CRUD, membership management, a workspace switcher UI, access-control middleware, and Inertia shared data so the frontend can render workspace-aware views.

## Requirements

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

### Requirement: Workspace Plugin is independent from Core

Workspace Plugin SHALL depend on `vitamind/core` but core SHALL have no knowledge of workspace plugin. Plugin registration uses event-based or registry pattern so that core remains lean.

#### Scenario: Core bootstraps without workspace knowledge
- **WHEN** core boots
- **THEN** it does not import or reference workspace classes
- **AND** plugin is loaded separately via its own service provider
- **AND** core can function completely without workspace package installed

### Requirement: Workspace CRUD operations are fully functional

Workspace Plugin SHALL provide complete CRUD (Create, Read, Update, Delete) operations for workspaces, including user invitations, leave workspace, and user removal.

#### Scenario: Admin creates a new workspace
- **WHEN** admin accesses workspace management page and creates new workspace
- **THEN** workspace record is created in database
- **AND** creating user is set as workspace owner
- **AND** workspace is immediately accessible from workspace switcher

#### Scenario: User is invited to workspace
- **WHEN** workspace owner invites user by email
- **THEN** invitation record is created
- **AND** user receives invitation (via future notification system)
- **AND** user can accept invitation and join workspace

#### Scenario: User leaves workspace
- **WHEN** user clicks "Leave Workspace"
- **THEN** user is removed from workspace
- **AND** user is redirected to dashboard or another workspace
- **AND** user's tasks/permissions in that workspace are cleaned up

### Requirement: Workspace Switcher is accessible in UI

Workspace Plugin SHALL provide UI component for switching between workspaces, visible in app header when feature enabled.

#### Scenario: User switches between workspaces
- **WHEN** user with multiple workspace memberships clicks workspace switcher
- **THEN** list of workspaces user is member of is displayed
- **AND** clicking a workspace loads it and switches current_workspace context
- **AND** page reloads with new workspace data

### Requirement: Workspace middleware prevents unauthorized access

Workspace Plugin SHALL enforce that authenticated requests have an active workspace context, preventing access to other workspaces' data.

#### Scenario: Request without active workspace is blocked
- **WHEN** authenticated user makes request without active workspace context
- **THEN** system redirects to workspace selection or home
- **AND** data is not leaked across workspace boundaries

#### Scenario: User cannot access workspace data without membership
- **WHEN** user attempts to access resources from workspace they are not member of
- **THEN** system returns 403 Forbidden
- **AND** no data is exposed

### Requirement: Workspace is shared with Inertia frontend

Workspace Plugin SHALL share current active workspace data with React frontend via Inertia shared props, enabling frontend to render workspace-aware UI.

#### Scenario: Frontend receives current workspace data
- **WHEN** page is rendered via Inertia
- **THEN** `auth.currentWorkspace` is available in props
- **AND** frontend components can display workspace name, member count, etc.
- **AND** workspace context persists across page navigation
