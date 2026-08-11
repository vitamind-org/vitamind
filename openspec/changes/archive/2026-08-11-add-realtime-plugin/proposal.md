## Why

Phase 1 (`phase1-standalone-boilerplate`) explicitly deferred "WebSocket real-time system" as a Non-Goal to a future phase. That deferral is no longer free: **WakuWaku**, an external dogfooding consumer of `vitamind/core` + `vitamind/plugin-sdk` + `vitamind/workspace-plugin` (same path-repo pattern as BukuWarga/LembarUji/UangKas), needs realtime today. Its WhatsApp QR-login dialog fetches the QR code once and polls connection status every 3 seconds (`resources/js/pages/whatsapp-numbers/components/link-number-dialog.tsx`), so a QR whose `qr_duration` expires before the user scans it goes silently stale, and its inbound webhook controller (`app/Http/Controllers/API/WhatsAppWebhookController.php`) already receives GoWA's QR/status events but only logs them — nothing acts on them. A planned follow-up feature (live WhatsApp message send/receive) needs the same underlying capability.

Building this as a one-off inside WakuWaku would bake channel-naming and authorization decisions into a single app. Building it once as first-party VitaminD infra — extensible by any implementor, not prescriptive about how they use it — serves WakuWaku now and every future multi-tenant or single-tenant consumer later.

## What Changes

- New optional package `dev-packages/vitamind-realtime-plugin`, distributed via composer path-repo/symlink exactly like `vitamind-workspace-plugin` and `vitamind-todo-plugin` (Packagist later, independent of the existing `stabilize-vitamind-packages`/`publish-vitamind-packages` gates, which are scoped only to `vitamind/core` and `vitamind/workspace-plugin`).
- Activates the existing, previously-unused `vitamin-d.features.websocket` flag (`VITAMIND_FEATURE_WEBSOCKET`, already defined in `config/vitamin-d.php` and `dev-packages/vitamind-core/config/vitamin-d.php`, default `false`) as the plugin's gate — no new flag is introduced.
- **Layer 1 — broadcasting infra (every implementor, any tenancy)**: Laravel Reverb wired as the broadcaster (config, service provider registration, Sanctum-aware channel-auth route), Laravel Echo added to the frontend toolchain, and a reusable React hook that feeds incoming broadcast events into the existing TanStack Query cache rather than a parallel Zustand store.
- **Layer 2 — workspace channel-authorization sugar (active only when `vitamin-d.features.workspaces` is also on)**: a convention-based helper, mirroring `VitaminD\PluginSdk\Concerns\BelongsToWorkspace`'s relaxed/no-hard-dependency style, that answers "is this user a member of workspace `{id}`?" for implementors to call inside their own `Broadcast::channel()` callbacks. Single-tenant implementors need nothing from this layer — Laravel's own default `App.Models.User.{id}` private-channel convention already covers them, and the plugin doesn't add a special case for it.
- Deliberately **does not** prescribe channel-naming topology (per-resource vs. workspace-wide vs. both) or broadcast timing (`ShouldBroadcastNow` vs. queued `ShouldBroadcast`) — both stay per-event implementor decisions, documented with worked examples rather than enforced by the plugin.
- One end-to-end example event/channel within the plugin (or its test suite) proving the pattern actually works, not just infra scaffolding.
- Implementor-facing docs: wiring a broadcast event, local dev (`php artisan reverb:start`), and production process-supervision notes (documentation only — no new deployment infrastructure is built as part of this change).

No breaking changes for any in-repository consumer: fully additive to the host app, opt-in via a flag that already defaults to `false`. This does remove frontend APIs from the pre-existing, inert Phase 3 socket scaffold — `SOCKET_EVENT`, `useSocketListener`, `useRealtime`, `useRealtimeRecord`, and the `events.token` protocol (see D6/Impact below) — but a repo-wide search found no page-level caller of any of them, so nothing in this codebase breaks. An external consumer that had already started building against those specific symbols would need to migrate to `useBroadcastChannel`.

## Capabilities

### New Capabilities
- `vitamind-realtime-plugin`: optional WebSocket/broadcasting plugin providing Reverb infra, frontend Echo wiring, and a workspace-scoped channel-authorization convention, gated by `vitamin-d.features.websocket`.

### Modified Capabilities
(none — this does not change the requirements of `vitamind-core` or `vitamind-workspace-plugin`; it mirrors `BelongsToWorkspace`'s pattern for a new concern without modifying that trait or its spec)

## Impact

- **New package**: `dev-packages/vitamind-realtime-plugin`, added to root `composer.json` as a path repository (`vitamind/realtime-plugin`), same shape as the existing `vitamind/workspace-plugin` entry.
- **New frontend dependency**: `laravel-echo` (+ a Reverb-compatible transport) in `package.json`.
- **New env vars** (inactive unless `VITAMIND_FEATURE_WEBSOCKET=true`): `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`.
- **New runtime process**: Reverb server (`php artisan reverb:start`) alongside PHP-FPM — documented, not orchestrated, by this change.
- **Migrates existing frontend code**: `resources/js/stores/socket-store.ts` and `resources/js/hooks/use-socket-events.ts` already implement a complete-but-inert hand-rolled WebSocket protocol from an earlier phase (predating Phase 1's Non-Goal), wired into `resources/js/components/app-header.tsx` (connection-status indicator) and `resources/js/layouts/app/layout.tsx` (`bootstrap.invalidated` refresh). This change replaces that protocol with Reverb/Echo underneath, preserving the `{status, reconnect}` contract those two components already consume — see design.md's D6.
- **Downstream**: WakuWaku becomes the first real consumer. Its own work — updating `WaNumber.status` from the webhook, broadcasting QR/status/message events, wiring the frontend dialog to the new hook — happens in that repo as a separate, follow-on change and is out of scope here.
- **No impact** to `stabilize-vitamind-packages` or `publish-vitamind-packages` gates: this is a new, independent package, not a modification to `vitamind/core` or `vitamind/workspace-plugin`.
