## Context

VitaminD's plugin ecosystem already has a working precedent for "optional capability, relaxed coupling": `vitamind-workspace-plugin` requires `vitamind/core` + `vitamind/plugin-sdk`, gates its own `ServiceProvider::register()`/`boot()` behind `vitamin-d.features.workspaces`, and no-ops entirely when that flag is off. `VitaminD\PluginSdk\Concerns\BelongsToWorkspace` goes further: it lives in the neutral `plugin-sdk` package (not in `workspace-plugin` itself) and reads only two conventions — the `vitamin-d.features.workspaces` flag and `current_workspace_id` on the authenticated user — so any model can adopt it without a hard dependency on the workspace package being installed at all.

The unused `vitamin-d.features.websocket` flag (`VITAMIND_FEATURE_WEBSOCKET`) already exists in `config/vitamin-d.php`. The stack is Laravel 13 + Inertia v3 + React 19 + TanStack Query 5; there is currently no `config/broadcasting.php`, no `routes/channels.php`, and no Echo client in `package.json` — the *backend* is greenfield.

**The frontend is not greenfield.** `resources/js/stores/socket-store.ts` and `resources/js/hooks/use-socket-events.ts` (added in commit `5f38842`, "Phase 3 - Workspace System", 2026-07-04 — predating Phase 1's Non-Goal declaration) already implement a complete, working-but-inert client for a *different*, hand-rolled protocol: a Sanctum-authenticated `POST route('events.token')` handshake returning `{token, url}`, then a raw `new WebSocket(url + '?token=' + token)` connection multiplexing every event for the user's active workspace over one socket (`{type: 'connected'|'subscribed'|'event'|'error', ...}` envelopes, workspace switch via an in-band `{type:'subscribe', workspace_id}` message), with reconnect backoff and a `useRealtime`/`useRealtimeRecord` consumer API using `{prefix}.created/updated/deleted` event names and client-side `scope` filtering. It is genuinely wired in today: `AppHeader` renders a WiFi connected/connecting/disconnected indicator with a reconnect button off `useSocketEvents()`, and `Layout` listens for a `bootstrap.invalidated` event to refresh a global config cache. Both are inert only because no `events.token` route exists server-side yet — `route().has('events.token')` guards every connection attempt.

This was discovered mid-implementation and conflicts with D1 (Reverb/Echo, not a hand-rolled protocol) and D4 (TanStack Query, not Zustand/local state) as originally written. Asked directly, the decision is to **proceed with Reverb + Echo and migrate the existing frontend onto it** (see D6) rather than build a server for the hand-rolled protocol — see D6 for what that migration touches and why.

The motivating consumer, WakuWaku (external repo, consumes `vitamind/core` + `vitamind/plugin-sdk` + `vitamind/workspace-plugin` via the same dev-packages path-repo pattern), has a concrete, already-broken UX: its QR-login dialog fetches a QR code once (no refresh before `qr_duration` expiry) and polls connection status every 3 seconds, while its webhook controller already receives the events needed to fix this but only logs them.

## Goals / Non-Goals

**Goals:**
- Ship a working, self-hosted broadcasting stack (Reverb + Echo + channel auth) that any implementor can turn on via one existing flag.
- Give multi-tenant implementors a workspace-membership authorization primitive for their own channels, without forcing that concept on single-tenant implementors.
- Prove the pattern with one real end-to-end example, not just configuration scaffolding.
- Keep every channel-naming and broadcast-timing decision in the implementor's hands.

**Non-Goals:**
- Building WakuWaku's own realtime features (QR/status broadcasting, message inbox) — that is downstream, follow-on work in WakuWaku's own repo.
- A managed/hosted broadcaster option (Pusher, Ably) — out of scope for this change; Reverb is the only broadcaster shipped.
- Introducing a general-purpose queued-jobs pattern to the boilerplate (none exists today). This change documents the queue-vs-sync trade-off but does not build job infrastructure.
- A notification center / activity feed UI. That would be one possible *use* of the workspace-wide channel topology this design deliberately leaves to implementors.

## Decisions

### D1: Broadcaster = Laravel Reverb (self-hosted), not Pusher/Ably/Soketi

Reverb is Laravel's first-party broadcaster: zero extra client library beyond Echo, works natively with the Sanctum session/cookie auth VitaminD already uses for the channel-auth endpoint, and needs no external account or recurring cost — consistent with the boilerplate's self-hosted ethos (an implementor shouldn't need a Pusher account just to unlock the flag). Soketi was considered (also self-hosted, Pusher-protocol-compatible) but adds a Node runtime dependency to a PHP-first boilerplate for no benefit over Reverb, which is now Laravel's maintained default.

**Trade-off accepted**: Reverb is a long-running process separate from PHP-FPM. This change documents `php artisan reverb:start` for local dev and supervisor/systemd notes for production; it does not build orchestration, since VitaminD's boilerplate has no existing deployment/process-management story to hook into (unlike WakuWaku, which already runs systemd + Caddy for its GoWA instances).

### D2: Two-layer package, not a single monolithic plugin

Layer 1 (Reverb config, channel-auth route, Echo frontend wiring) has zero workspace awareness and works for any implementor. Layer 2 (workspace-membership channel-authorization helper) is additive sugar, active only when `vitamin-d.features.workspaces` is also on. Splitting them avoids forcing single-tenant implementors to reason about a multi-tenancy concept they don't have, and mirrors the exact shape of `BelongsToWorkspace`: convention-based, no hard dependency, no-ops cleanly when the workspace flag is off. A single-layer design was considered and rejected — it would either force a hard dependency on `vitamind/workspace-plugin` (breaking mono-tenant installs) or leave workspace scoping entirely undocumented (forcing every multi-tenant implementor, including WakuWaku, to reinvent the same membership check).

### D3: Workspace channel-auth helper lives in `vitamind-realtime-plugin`, depends on `vitamind/plugin-sdk` — not added to `BelongsToWorkspace` itself

`BelongsToWorkspace` is an Eloquent model concern (boot hooks, global scopes) — its shape doesn't fit a channel-authorization closure, which is a plain callable receiving `($user, ...$params)`, not a model lifecycle hook. Rather than bending that trait to a second purpose, the new package defines its own helper reading the *same* two conventions (`vitamin-d.features.workspaces` flag, `current_workspace_id`), following the identical philosophy without touching `plugin-sdk`'s existing, already-consumed API. `vitamind-realtime-plugin` requires `vitamind/core` (for the `User` model conventions the Sanctum-aware channel-auth route relies on) and `vitamind/plugin-sdk` (shared foundation, matching `vitamind-workspace-plugin`'s own dependency list) — but explicitly **not** `vitamind/workspace-plugin`, preserving the no-hard-dependency property.

### D4: Frontend hook writes into TanStack Query's cache, not a new Zustand store

The app already treats TanStack Query as the source of truth for server state. A realtime event is, semantically, a server-state change arriving out-of-band — so the example hook calls `queryClient.setQueryData` / `invalidateQueries` against the same query keys REST calls already populate, rather than introducing a second, parallel state store that the rest of the UI would need to reconcile against. Zustand remains available for genuinely client-only state (UI toggles, etc.), unaffected by this change.

### D5: No enforced channel-naming convention or broadcast-timing rule

Both were raised and explicitly rejected as plugin-level policy during design discussion: Laravel's `broadcastOn()` already supports returning multiple channels per event, so an implementor can mix per-resource and workspace-wide channels per event rather than being forced into one topology app-wide; and `ShouldBroadcastNow` vs. queued `ShouldBroadcast` is already a native per-event choice that shouldn't be second-guessed by this package. The plugin documents both trade-offs with worked examples (drawn from the WakuWaku QR case) instead of codifying either as a rule.

### D6: Migrate the existing Phase 3 socket scaffold onto Reverb/Echo, preserving its UI contract

`socket-store.ts`'s hand-rolled protocol is replaced entirely — no server is built for the `events.token`/raw-`WebSocket` handshake. `useSocketEvents()` is rewritten to wrap an Echo/Reverb connection instead of a raw socket, but **keeps its existing `{status, reconnect}` return shape**, so `AppHeader` and `Layout` need call-site updates, not redesigns. Concretely:

- `AppHeader`'s `isWebSocketEnabled` check switches from `route().has('events.token')` (a proxy that only worked because that route never existed) to `!!(features && features.websocket)`, reading the same Inertia-shared `features` prop `isWorkspacesEnabled` already reads one line above it (`config('vitamin-d.features')` is shared in full by `HandleInertiaRequests::share()`, so `features.websocket` requires no backend change — it's already there).
- Connection status (connected/connecting/disconnected) is derived from Echo's underlying Pusher-protocol connector state (`echo.connector.pusher.connection.bind('state_change', ...)`) instead of a hand-rolled `WebSocket.onopen`/`onclose` + manual exponential-backoff loop — Echo/Pusher-js already reconnects on its own, so the bespoke backoff logic in the old store is deleted, not ported.
- `Layout`'s `bootstrap.invalidated` listener becomes a genuine first consumer of the topology-freedom D5 leaves open: bootstrap config (`GetBootstrap`'s `server_provider`/`dns_provider`/`plugins.views`) is app-wide, not tenant-scoped, so `vitamind-realtime-plugin` broadcasts `BootstrapInvalidated` on a **public** channel (`Channel('bootstrap')`, no private-channel auth needed) rather than forcing it through a workspace-scoped private channel it doesn't semantically belong on. The plugin listens for core's *existing* `PluginStateChanged` event (already dispatched by `InstallPlugin`/`EnablePlugin`/`DisablePlugin`/`UninstallPlugin`, already the trigger for `GetBootstrap::forgetVersion()`) and re-broadcasts — mirroring exactly how `WorkspaceServiceProvider::registerEventListeners()` reacts to core's `UserRemoving`/`Registered` events without core knowing workspace-plugin exists. Core itself never gains a broadcasting dependency; the listener lives entirely in the optional plugin.
- `useRealtime`/`useRealtimeRecord` (defined but, per a repo-wide search, never actually called by any page) are replaced by the new TanStack-Query-backed hook from D4 rather than ported forward, since nothing depends on their exact old signature.
- The global `window` `CustomEvent` bus (`SOCKET_EVENT`/`useSocketListener`) is dropped in favor of components subscribing to Echo channels directly (or via the new hook) — it existed to decouple consumers from the raw-`WebSocket` singleton, a problem Echo's own channel API already solves.

**Alternative considered**: build a real backend for the existing hand-rolled protocol instead, preserving 100% of the frontend as-is. Rejected — it would mean maintaining a bespoke WebSocket protocol (auth handshake, reconnection, message framing) in parallel with, or instead of, the Laravel-standard Reverb/Echo/Pusher-protocol tooling this change was designed around, for a protocol that (per the repo search above) has no real page-level consumer yet to justify preserving verbatim.

## Risks / Trade-offs

- **Reverb is a new long-running process** → not viable on pure shared hosting. Mitigation: document supervisor/systemd setup explicitly in the package README; this is inherent to self-hosted WebSocket servers generally, not specific to this design.
- **`QUEUE_CONNECTION=database` is polling-based** → an implementor who chooses queued `ShouldBroadcast` for a latency-sensitive event (like WakuWaku's QR updates) will see multi-second lag that defeats the purpose. Mitigation: call this out explicitly in docs, recommending `ShouldBroadcastNow` for latency-sensitive events or a push-based queue driver (Redis) otherwise.
- **No enforced channel convention → possible cross-workspace data leak** if an implementor writes a channel-authorization callback that trusts a client-supplied workspace ID without checking membership. Mitigation: the one shipped example explicitly demonstrates the correct membership check via the Layer 2 helper, with an inline comment on why skipping it is unsafe.
- **Sanctum + Reverb channel-auth interplay is unverified** in this codebase (no prior broadcasting route has existed here). Risk that the default `channels.php` broadcasting-auth route doesn't compose cleanly with the existing `web` middleware/session stack. Mitigation: first implementation task explicitly verifies this against a real authenticated request before building anything on top.
- **Migrating already-shipped UI** (`AppHeader`'s WiFi indicator, `Layout`'s bootstrap-invalidation refresh) risks a visible regression if the Echo-based replacement doesn't preserve the exact `{status, reconnect}` contract those components read. Mitigation: D6 keeps that return shape unchanged and the migration is manually verified in-browser (task 7.3) before considering it done.

## Migration Plan

1. Scaffold `dev-packages/vitamind-realtime-plugin` (composer.json requiring `vitamind/core` + `vitamind/plugin-sdk`, PSR-4 `VitaminD\Plugins\Realtime\`), add path-repo entry to root `composer.json`, matching `vitamind-workspace-plugin`'s setup.
2. Add Reverb server-side: `config/broadcasting.php`, Reverb service provider registration, env vars — all gated behind `vitamin-d.features.websocket` in the plugin's own `RealtimeServiceProvider::register()/boot()`, mirroring `WorkspaceServiceProvider`'s early-return pattern.
3. Add the Sanctum-aware channel-auth route and verify it against a real authenticated request (addresses the Sanctum/Reverb risk above) before proceeding.
4. Add frontend: Echo client dependency in `package.json`, Echo bootstrap, and the example React hook wired to TanStack Query.
5. Add the Layer 2 workspace-authorization helper.
6. Add one end-to-end example (event + channel + authorization + a test asserting the auth callback grants/denies correctly) proving the whole path works.
7. Migrate the existing Phase 3 socket scaffold per D6: rewrite `use-socket-events.ts`/`socket-store.ts` onto Echo, update `AppHeader`'s feature check, move `Layout`'s `bootstrap.invalidated` handling onto a real Echo subscription, remove the dead `events.token`-shaped code paths.
8. Write implementor-facing docs: wiring an event, local dev, production process notes, the queue-timing and channel-topology trade-offs from D5.

**Rollback**: the flag defaults to `false`; removing the composer path-repo entry and the package directory fully reverts the change with no data migration involved (no new persisted tables are introduced by this package itself).

## Open Questions

- ~~Exact Echo transport package~~ — resolved: use `laravel-echo`'s dedicated `broadcaster: 'reverb'` connector, not `pusher` pointed at a third-party SaaS. Verified against the published package (`npm view laravel-echo peerDependencies`) that `pusher-js` is still a required peer even for the `reverb` broadcaster — Reverb speaks the Pusher wire protocol, so Echo's Reverb connector is implemented on top of it. The earlier draft of this note claimed no `pusher-js` dependency; that was wrong and is corrected here.
- Whether the shipped example should demonstrate a workspace-wide channel in addition to a per-resource one, to make the "multiple channels per event" capability from D5 concrete rather than just described. Resolved implicitly by D6: `Layout`'s `bootstrap.invalidated` migration *is* the workspace-wide example in practice, so the plugin's own shipped example can stay single-topology (per-resource) without leaving the multi-channel case undemonstrated in the codebase as a whole.
