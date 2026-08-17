## ADDED Requirements

### Requirement: Realtime Plugin is optional and feature-flagged

Realtime Plugin (`vitamind/realtime-plugin`) SHALL be an optional package that adds WebSocket broadcasting (Reverb server config, channel-auth route, frontend Echo wiring) on top of VitaminD Core. It SHALL only activate when the existing `vitamin-d.features.websocket` flag (`VITAMIND_FEATURE_WEBSOCKET`) resolves to `true`, and SHALL have zero runtime impact when it is `false` (the flag's existing default). The plugin's service provider SHALL be registered purely through Composer package auto-discovery (`extra.laravel.providers`) — no manual edit to the host application's `bootstrap/providers.php` SHALL be required to enable or disable it.

#### Scenario: Developer installs realtime plugin
- **WHEN** developer runs `composer require vitamind/realtime-plugin`
- **THEN** the plugin is installed and its service provider is auto-discovered
- **AND** no broadcasting routes, config, or channel-auth endpoint are registered until the feature flag is enabled
- **AND** the host application does not need to edit `bootstrap/providers.php`

#### Scenario: Feature flag enables broadcasting
- **WHEN** developer sets `VITAMIND_FEATURE_WEBSOCKET=true` in `.env`
- **THEN** the Reverb broadcaster configuration is registered
- **AND** the channel-authorization route becomes active
- **AND** the frontend Echo client can connect

#### Scenario: Feature flag disabled removes broadcasting entirely
- **WHEN** developer leaves `VITAMIND_FEATURE_WEBSOCKET=false` (default)
- **THEN** no broadcasting routes are registered
- **AND** no Reverb configuration is loaded
- **AND** the application behaves exactly as it did before the plugin was installed

### Requirement: Broadcasting infrastructure works regardless of tenancy

Realtime Plugin SHALL provide a working Reverb broadcaster, Sanctum-aware channel-authorization route, and frontend Echo integration that function identically whether or not `vitamind/workspace-plugin` is installed or `vitamin-d.features.workspaces` is enabled. It SHALL NOT require the workspace feature or package to be present for its core broadcasting capability to work.

#### Scenario: Single-tenant application enables realtime without workspaces
- **WHEN** an application has `vitamin-d.features.websocket=true` and `vitamin-d.features.workspaces=false` (or `vitamind/workspace-plugin` not installed)
- **THEN** the Reverb broadcaster, channel-auth route, and Echo client all function
- **AND** an implementor can define and broadcast their own events using Laravel's default per-user private-channel convention (`App.Models.User.{id}`)

#### Scenario: Multi-tenant application enables realtime alongside workspaces
- **WHEN** an application has both `vitamin-d.features.websocket=true` and `vitamin-d.features.workspaces=true`
- **THEN** the same broadcasting infrastructure functions
- **AND** the workspace-authorization helper described below becomes available in addition to it

### Requirement: Realtime Plugin does not prescribe channel topology or broadcast timing

Realtime Plugin SHALL NOT enforce or default to a specific channel-naming convention (per-resource, workspace-wide, or otherwise), and SHALL NOT enforce a specific broadcast-timing strategy (immediate vs. queued). Both SHALL remain decisions made per-event by the implementor, using Laravel's own `ShouldBroadcastNow`/`ShouldBroadcast` and `broadcastOn()` contracts.

#### Scenario: Implementor chooses immediate broadcast for a latency-sensitive event
- **WHEN** an implementor defines an event implementing `ShouldBroadcastNow`
- **THEN** the plugin's infrastructure broadcasts it synchronously within the request, without requiring any plugin-specific configuration to allow this

#### Scenario: Implementor chooses queued broadcast for a non-urgent event
- **WHEN** an implementor defines an event implementing `ShouldBroadcast` with a specific queue connection
- **THEN** the plugin's infrastructure broadcasts it through that queue, without requiring any plugin-specific configuration to allow this

#### Scenario: Implementor broadcasts one event to multiple channels
- **WHEN** an implementor's event returns more than one channel from `broadcastOn()` (for example, a resource-specific channel and a workspace-wide channel)
- **THEN** the plugin does not block, warn on, or otherwise treat this differently from a single-channel broadcast

### Requirement: Workspace-scoped channel authorization helper is available when workspaces are active

When `vitamin-d.features.workspaces` is enabled, Realtime Plugin SHALL expose a convention-based helper that implementors can call from their own `Broadcast::channel()` authorization callbacks to determine whether the authenticated user belongs to a given workspace ID. This helper SHALL follow the same relaxed-coupling convention as `VitaminD\PluginSdk\Concerns\BelongsToWorkspace` (reading only the `vitamin-d.features.workspaces` flag and the user's workspace memberships) and SHALL NOT require `vitamind/workspace-plugin` as a hard package dependency of `vitamind/realtime-plugin`.

#### Scenario: User is a member of the requested workspace channel
- **WHEN** an authenticated user who belongs to workspace `7` subscribes to a channel whose authorization callback uses the helper to check membership in workspace `7`
- **THEN** the subscription is authorized

#### Scenario: User is not a member of the requested workspace channel
- **WHEN** an authenticated user who does not belong to workspace `7` subscribes to a channel whose authorization callback uses the helper to check membership in workspace `7`
- **THEN** the subscription is rejected
- **AND** no channel data is sent to that user

#### Scenario: Helper is called with workspaces feature disabled
- **WHEN** `vitamin-d.features.workspaces` is `false` and an implementor's own code calls the workspace-authorization helper anyway
- **THEN** the helper denies authorization rather than silently granting it, since no workspace membership exists to check

### Requirement: Frontend realtime updates integrate with existing server-state cache

Realtime Plugin SHALL ship a frontend integration (an Echo client bootstrap plus at least one reusable React hook) that applies incoming broadcast events to the application's existing TanStack Query cache (via `setQueryData` or `invalidateQueries`), rather than introducing a separate, parallel client-side state store for realtime data.

#### Scenario: Broadcast event updates a query the UI already renders
- **WHEN** a subscribed channel receives an event mapped by the implementor to an existing TanStack Query key
- **THEN** the corresponding query cache entry is updated or invalidated
- **AND** components reading that query re-render with the new data without a manual page reload

### Requirement: Package includes one proven end-to-end example

Realtime Plugin SHALL include at least one complete, working example — a broadcast event, its channel, an authorization callback, and an automated test — demonstrating the full path from server-side broadcast to an authorized subscriber, so the pattern is verified rather than left as untested configuration.

#### Scenario: Example test suite runs
- **WHEN** the plugin's test suite is run
- **THEN** a test asserts that the example channel's authorization callback grants access to an authorized user
- **AND** a test asserts that it denies access to an unauthorized user
- **AND** a test asserts that broadcasting the example event delivers the correct event name and payload to that authorized user's channel
