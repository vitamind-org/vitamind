## 1. Package Scaffolding

- [x] 1.1 Create `dev-packages/vitamind-realtime-plugin` with `composer.json` (`vitamind/realtime-plugin`, requiring `vitamind/core` + `vitamind/plugin-sdk`, PSR-4 `VitaminD\Plugins\Realtime\` → `src/`), matching `vitamind-workspace-plugin`'s shape.
- [x] 1.2 Add the path-repo entry for `vitamind/realtime-plugin` to root `composer.json` (`repositories` + `require`), symlinked like the other dev-packages.
- [x] 1.3 Create `RealtimeServiceProvider` with `register()`/`boot()` gated on `vitamin-d.features.websocket`, early-returning when off (mirror `WorkspaceServiceProvider`'s pattern), and register it in `composer.json`'s `extra.laravel.providers`.
- [x] 1.4 Add `laravel/reverb` as a dependency of the plugin (or document it as a root dev dependency if Reverb's install command expects to run against the host app — confirm during implementation).

## 2. Server-Side Broadcasting Infrastructure

- [x] 2.1 Add `config/broadcasting.php` (published/vendored by the plugin) configuring the `reverb` connection, only loaded when the feature flag is on.
- [x] 2.2 Add the Reverb env vars to `.env.example` (`BROADCAST_CONNECTION`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`), commented out or defaulted so they have zero effect while the flag is off.
- [x] 2.3 Register the Sanctum-aware broadcasting authorization route within the gated service provider (not the host app's `routes/channels.php`), following the same self-registration approach `WorkspaceServiceProvider::registerRoutes()` uses for its own controllers.
- [x] 2.4 Verify the channel-auth route against a real authenticated request (Sanctum session cookie) before proceeding — this is the risk flagged in design.md as unverified in this codebase. Write a test that asserts an authenticated user can successfully authorize a private channel and an unauthenticated request is rejected. **Found and fixed a real integration bug**: `vitamind-workspace-plugin`'s globally-applied `EnsureWorkspaceOnboarded` middleware redirected zero-membership authenticated users away from `/broadcasting/auth` before this route was excluded from it.

## 3. Frontend Echo Integration

- [x] 3.1 Add `laravel-echo` + `pusher-js` (Echo's dedicated `reverb` broadcaster type is implemented on top of the Pusher wire protocol, so `pusher-js` is a required peer even though we never point it at Pusher's own SaaS — verified via `npm view laravel-echo peerDependencies`) to root `package.json`.
- [x] 3.2 Add an Echo client bootstrap module (`resources/js/lib/echo.ts`), reading the Reverb connection config from `VITE_REVERB_*` env vars, lazily instantiated and returning `null` when unconfigured.
- [x] 3.3 Add a reusable React hook (`resources/js/hooks/use-broadcast-channel.ts`, `useBroadcastChannel`) that subscribes to a given channel and invalidates a TanStack Query key per an implementor-supplied event-to-query-key mapping.
- [x] 3.4 Ensure the hook cleans up its channel subscription on unmount (leave the channel) — verified via code inspection and `tsc --noEmit` (no frontend test runner exists in this repo to add a component test to; deferred to the manual browser check in task 8).

## 4. Workspace Channel-Authorization Helper (Layer 2)

- [x] 4.1 Implement the workspace-membership authorization helper in `vitamind/realtime-plugin`, reading `vitamin-d.features.workspaces` and the authenticated user's workspace memberships — no dependency on `vitamind/workspace-plugin` as a package.
- [x] 4.2 Write unit tests: member of the workspace → authorized; non-member → denied; feature flag off → denied (per the spec's explicit no-silent-grant scenario).
- [x] 4.3 Document the helper's exact convention (which config key, which relation/column it expects) so it stays aligned with `BelongsToWorkspace`'s conventions even though the code is separate.

## 5. End-to-End Example

- [x] 5.1 Add one example broadcast event (`WorkspacePing`) with a channel and an authorization callback using the Layer 2 helper.
- [x] 5.2 Add a feature test that dispatches the example event and asserts the channel authorization callback grants access to an authorized (workspace-member) user and denies an unauthorized one, per spec's "Package includes one proven end-to-end example" requirement.
- [x] 5.3 Confirm the example works with both `ShouldBroadcastNow` and (separately, or via comment/doc) `ShouldBroadcast`, to validate the design's "implementor picks the timing" decision isn't accidentally broken by the plugin's own wiring.

## 6. Migrate Existing Frontend Socket Scaffold (design.md D6)

- [x] 6.1 Rewrite `resources/js/stores/socket-store.ts` to wrap the Echo/Reverb connection instead of a raw token-handshake `WebSocket`: dropped `requestEventsToken`/`events.token` and the hand-rolled reconnect backoff (pusher-js reconnects natively); `status` is derived from `echo.connector.pusher.connection`'s `state_change` event.
- [x] 6.2 Update `resources/js/hooks/use-socket-events.ts` so `useSocketEvents()` keeps its existing `{status, reconnect}` return shape (no call-site changes needed downstream) while sourcing both from the rewritten Echo-backed store, connecting when `auth` and `features.websocket` are both truthy.
- [x] 6.3 Update `resources/js/components/app-header.tsx`: replaced the `route().has('events.token')`-based `isWebSocketEnabled` check with `!!(features && features.websocket)`, mirroring `isWorkspacesEnabled` immediately above it.
- [x] 6.4 Moved `resources/js/layouts/app/layout.tsx`'s `bootstrap.invalidated` handling onto `useBroadcastChannel('bootstrap', { 'bootstrap.invalidated': fetchBootstrap }, { private: false })` — the **public** channel `vitamind-realtime-plugin` already broadcasts `BootstrapInvalidated` to (design.md's corrected D6; bootstrap config is app-wide, not tenant-scoped).
- [x] 6.5 Removed the now-unused `SOCKET_EVENT` window `CustomEvent` bus, `useSocketListener`, `useRealtime`, and `useRealtimeRecord` — they no longer exist in the rewritten `use-socket-events.ts`/`socket-store.ts`.
- [x] 6.6 Confirmed via `grep -rn` across `resources/js` that nothing imports the removed exports, and `npx tsc --noEmit` + `npm run build` both pass clean.

## 7. Documentation

- [x] 7.1 Write the plugin README: how to enable the flag, how to define a broadcast event, local dev (`php artisan reverb:start`), and production process-supervision notes (supervisor/systemd — documentation only).
- [x] 7.2 Document the channel-topology trade-off (per-resource vs. workspace-wide vs. both via multi-channel `broadcastOn()`) with a worked example, per design.md's D5, referencing the `bootstrap.invalidated` migration (task 6.4) as the shipped public-channel example.
- [x] 7.3 Document the broadcast-timing trade-off (`ShouldBroadcastNow` vs. queued `ShouldBroadcast`, and the `QUEUE_CONNECTION=database` polling-lag caveat from design.md's risks) with a recommendation for latency-sensitive events.
- [x] 7.4 Document the mono-tenant path explicitly: implementors without `vitamind/workspace-plugin` use Laravel's own default `App.Models.User.{id}` channel convention and need nothing from Layer 2.

## 8. Verification

- [x] 8.1 Ran the full test suite with `VITAMIND_FEATURE_WEBSOCKET=false`: 116/116 passing, zero behavioral change to the existing app.
- [x] 8.2 Ran the full test suite with the flag enabled: 125/125 passing (116 existing + 9 new).
- [x] 8.3 Verified against a **real** `php artisan reverb:start` process (not just PHPUnit's simulated HTTP kernel): confirmed `config('broadcasting.default')` resolves to `reverb` with real `.env` values, then used the project's own installed `pusher-js` from a throwaway Node script to connect, subscribe to the public `bootstrap` channel, and receive a live `BootstrapInvalidated` broadcast end-to-end after dispatching it from `php artisan tinker`. No browser-automation tool was available in this session to click through the actual `AppHeader`/dialog UI, so the private-channel path relies on task 2.4's HTTP-kernel test rather than an additional live browser run — noted as a gap, not silently skipped.
- [x] 8.4 Not independently browser-verified for the same reason as 8.3 (no browser tool available); covered instead by `npx tsc --noEmit` and `npm run build` both passing clean against the migrated `app-header.tsx`/`layout.tsx`, plus the `{status, reconnect}` contract those components read being deliberately preserved unchanged by design (D6).
