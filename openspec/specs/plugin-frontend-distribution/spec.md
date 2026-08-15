# plugin-frontend-distribution Specification

## Purpose

Defines how frontend source (Inertia pages, hooks, components) belonging to Local, GitHub, and Composer plugins is resolved and built by the host application — including file-location conventions, the alias/glob resolution mechanism, Tailwind scan coverage, and the stable calling contract host code uses so that a later change in resolution strategy (e.g. build-time glob to runtime registry) does not require call-site rewrites.

## Requirements

### Requirement: Local plugin frontend has no dedicated resolution mechanism
Local plugins (`app/Plugins/*`) SHALL NOT have any dedicated Vite alias, glob, or resolver mechanism for their frontend code. Frontend for a Local plugin SHALL be authored directly under the host's `resources/js/` tree, compiled as ordinary host application code.

#### Scenario: Local plugin author adds a custom page
- **WHEN** a developer wants a custom Inertia page for a Local plugin
- **THEN** they add it directly under `resources/js/pages/...`, the same way as any other host page
- **AND** no plugin-specific alias or glob configuration is required

#### Scenario: Retired Local-plugin alias mechanism is absent
- **WHEN** the codebase is inspected for a `@plugin/` alias or glob targeting `app/Plugins/Local/{Vendor}/{Name}/resources/js`
- **THEN** no such mechanism exists in `vite.config.ts` or `resources/js/app.tsx`

### Requirement: Distributed plugin frontend is resolved via a build-time `@plugin/{name}` alias
Composer and GitHub-sourced plugins (the "distributed" tier per `docs/local-plugins.md`) whose source is reachable under `vendor/vitamind/*` at host build time SHALL have their `resources/js/` content resolvable through a `@plugin/{kebab-name}` alias, scanned and compiled together with the host in the same Vite build.

#### Scenario: Plugin page resolves via the alias
- **WHEN** a page component exists at `vendor/vitamind/{plugin}/resources/js/pages/{page}.tsx` (symlinked from `dev-packages/vitamind-{plugin}/resources/js/pages/{page}.tsx`)
- **AND** Inertia resolves the page name `@plugin/{plugin}/{page}`
- **THEN** the host's `resolve()` function in `resources/js/app.tsx` returns that component

#### Scenario: Plugin hook or utility resolves via the alias
- **WHEN** a hook exists at `vendor/vitamind/{plugin}/resources/js/hooks/{hook}.ts`
- **THEN** host code can import it via `@plugin/{plugin}/hooks/{hook}`

### Requirement: GitHub-sourced plugins are not covered by the build-time alias mechanism
Plugins installed at runtime from GitHub (`storage/plugins/*`, after the host's last `vite build`) SHALL NOT be assumed reachable by the `@plugin/{name}` alias or any build-time glob. Their custom-UI capability remains limited to the `dynamic-page` system until a separate mechanism (rebuild-on-install or self-contained bundle distribution) is designed.

#### Scenario: GitHub plugin without a prior host rebuild
- **WHEN** a plugin is installed via the GitHub-install flow into `storage/plugins/{folder}`
- **AND** no host rebuild has occurred since installation
- **THEN** the plugin's custom Inertia pages, if any exist in its source, are not resolvable via `@plugin/{name}`
- **AND** the plugin can still register `dynamic-page` CRUD screens via `RegisterPage`/`RegisterDataTable`

### Requirement: Plugin Tailwind classes are generated from host design tokens
The host's Tailwind configuration SHALL scan `vendor/vitamind/*/resources/js/**` as an additional `@source`, so utility classes used inside distributed-plugin components produce real CSS rules. Plugins SHALL NOT ship their own stylesheet or design tokens.

#### Scenario: Plugin component uses a host Tailwind utility class
- **WHEN** a plugin component under `vendor/vitamind/{plugin}/resources/js/` uses a Tailwind utility class (e.g. `text-primary`)
- **THEN** the host's compiled CSS includes the corresponding rule, generated from the host's own token values
- **AND** the plugin ships no `.css` file of its own

#### Scenario: Plugin needs an animation outside Tailwind's vocabulary
- **WHEN** a plugin component requires a keyframe animation not covered by Tailwind's utility classes or the host's existing `@theme`/`@keyframes`
- **THEN** the animation is either added deliberately to the host's shared CSS (reviewed, becomes available to all plugins) or applied via an inline `style` prop in the plugin component
- **AND** the plugin still ships no stylesheet of its own

### Requirement: Host code calls plugin-provided pages and hooks through a stable interface
Call sites in host code (e.g. shared layouts) SHALL NOT statically import a distributed plugin's frontend module by its raw resolution path. They SHALL go through a stable calling interface (`usePlugin('{plugin}')` for hooks/utilities; the `resolve()` function for pages) whose shape does not change if the underlying resolution strategy later changes from build-time glob to a runtime registry.

#### Scenario: Host layout consumes a plugin hook through the stable interface
- **WHEN** `resources/js/layouts/app/layout.tsx` needs a hook provided by `realtime-plugin`
- **THEN** it calls `usePlugin('realtime-plugin')` rather than importing the hook's file path directly

#### Scenario: `usePlugin()` degrades gracefully when the plugin is absent
- **WHEN** a fork of the boilerplate does not have `realtime-plugin` installed under `vendor/vitamind/`
- **THEN** `usePlugin('realtime-plugin')` returns an empty/no-op result instead of throwing a build or runtime error
- **AND** the calling code (e.g. `layout.tsx`) handles that empty result without crashing

### Requirement: `vitamind/workspace-plugin` frontend is owned by its own package
The Inertia pages belonging to `vitamind/workspace-plugin` (workspace index, onboarding, invite management, and their sub-components) SHALL live under `dev-packages/vitamind-workspace-plugin/resources/js/pages/`, resolved via the `@plugin/workspace-plugin/...` alias, not hardcoded under the host's `resources/js/pages/workspaces/`.

#### Scenario: Workspace pages render from the package location
- **WHEN** a user navigates to a workspace-plugin-owned page (e.g. workspace onboarding)
- **THEN** the rendered component's source resolves from `dev-packages/vitamind-workspace-plugin/resources/js/pages/`, not from the host's `resources/js/pages/workspaces/`

#### Scenario: Shared-data injection into a host-owned page remains unchanged
- **WHEN** `WorkspaceServiceProvider::registerInertiaSharedData()` injects the `pendingInvite` prop into the registration page
- **THEN** `resources/js/pages/auth/register.tsx` remains a host-owned page (not moved into the plugin package) and continues to read that prop correctly

### Requirement: `vitamind/realtime-plugin` frontend is owned by its own package
The client-side hooks and supporting modules belonging to `vitamind/realtime-plugin` (`echo.ts`, `use-socket-events`, `use-broadcast-channel`, the socket store) SHALL live under `dev-packages/vitamind-realtime-plugin/resources/js/`, consumed by the host's shared layout through `usePlugin('realtime-plugin')` rather than direct imports from host-owned hook files.

#### Scenario: Layout consumes realtime hooks from the plugin package
- **WHEN** `resources/js/layouts/app/layout.tsx` renders
- **THEN** the socket connection status shown in `AppHeader` is sourced from hooks resolved via `usePlugin('realtime-plugin')`, whose implementation lives in `dev-packages/vitamind-realtime-plugin/resources/js/`
