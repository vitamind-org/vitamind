# vitamind/realtime-plugin

Optional WebSocket broadcasting for VitaminD, built on [Laravel Reverb](https://laravel.com/docs/reverb) (self-hosted, no third-party SaaS account required) and [Laravel Echo](https://laravel.com/docs/broadcasting#client-side-installation) on the frontend.

It ships two things:

1. **Infra** (config, the `/broadcasting/auth` route, an Echo bootstrap, and a `useBroadcastChannel` React hook) — works for every implementor, single-tenant or multi-tenant.
2. **A workspace-scoped channel-authorization helper** (`WorkspaceChannelAuthorization`) — additive sugar, only relevant when `vitamind/workspace-plugin` is also installed and enabled.

It does **not** decide your channel topology or broadcast timing for you. Those are your call, per event — see below.

## Enabling it

```
VITAMIND_FEATURE_WEBSOCKET=true
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

All of this is already present, commented out, in the root `.env.example`.

## Local development

Reverb is a long-running process, separate from PHP-FPM/`php artisan serve`:

```
php artisan reverb:start
```

Run it alongside your usual `npm run dev` / `php artisan serve`. Nothing in this package starts it for you.

## Production

There's no orchestration shipped here — VitaminD's boilerplate doesn't assume a specific deployment target. Run `php artisan reverb:start` under a process supervisor (systemd, supervisord) so it restarts on crash/reboot, same as you would `queue:work`. See Laravel's own [Reverb production docs](https://laravel.com/docs/reverb#production) for supervisor/systemd unit examples and horizontal-scaling notes (Redis-backed pub/sub across multiple Reverb instances) if you outgrow a single process.

## Defining your own broadcast event

Nothing here is special — use Laravel's own contracts directly:

```php
class WaNumberStatusUpdated implements ShouldBroadcastNow // or ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public WaNumber $number) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->number->workspace_id}.wa-number.{$this->number->id}")];
    }
}

// In the channel's authorization callback, wherever you register it:
Broadcast::channel('workspace.{workspaceId}.wa-number.{id}', function ($user, $workspaceId, $id) {
    return WorkspaceChannelAuthorization::check($user, $workspaceId)
        && WaNumber::where('id', $id)->where('workspace_id', $workspaceId)->exists();
});
```

On the frontend:

```tsx
useBroadcastChannel(`workspace.${workspaceId}.wa-number.${numberId}`, {
  'status.updated': ['wa-numbers', numberId], // a TanStack Query key — invalidated on receipt
});
```

See `VitaminD\Plugins\Realtime\Events\WorkspacePing` and `RealtimeServiceProvider::registerChannels()` in this package for a complete, tested worked example (`tests/Feature/ChannelAuthorizationTest.php`).

## Choosing a channel topology — this package doesn't choose for you

Laravel's `broadcastOn()` can return more than one channel, so this isn't even an app-wide either/or — it's a per-event choice:

- **Per-resource** (`workspace.{id}.wa-number.{id}`): precise authorization (can check "is this user allowed to see *this* number," not just "are they in the workspace"), no client-side filtering needed, but a component subscribes/unsubscribes as it mounts/unmounts. Good fit for a focused UI like a single QR-login dialog that only cares about one resource.
- **Public / workspace-wide** (one channel, every relevant event multiplexed onto it): a single subscription serves an entire session regardless of which page is open — good for cross-cutting notifications. This package's own `BootstrapInvalidated` event (see `RealtimeServiceProvider::registerEventListeners()`, consumed by `resources/js/layouts/app/layout.tsx`) is a real, shipped example: it's broadcast on a **public** channel (`bootstrap`) rather than a workspace-scoped private one, because the config it signals a change to is app-wide, not tenant-scoped — a deliberate choice made by the *implementor* (`vitamind/core`, via this plugin), not something the plugin enforced.

Nothing stops a single event from doing both — return a per-resource channel *and* a workspace-wide one from the same `broadcastOn()` if you want a focused UI update and a cross-cutting notification from one occurrence.

## Choosing broadcast timing — also not this package's call

- `ShouldBroadcastNow` — dispatched synchronously, in-request. Use this for anything latency-sensitive (e.g. a QR code that expires in seconds — WakuWaku's motivating case for this whole package).
- `ShouldBroadcast` — queued. **Caveat**: the boilerplate's default `QUEUE_CONNECTION=database` is a polling queue. A latency-sensitive event queued through it can lag by however long your worker's polling interval is, which usually defeats the point of broadcasting it at all. Either use `ShouldBroadcastNow` for anything time-sensitive, or point that specific event at a push-based queue connection (e.g. Redis) via its `$connection` property.

Both paths go through the exact same infra this package sets up — see `tests/Feature/BroadcastTimingTest.php`.

## Single-tenant (no `vitamind/workspace-plugin`) apps

You don't need anything from `WorkspaceChannelAuthorization`. Use Laravel's own default per-user private channel convention instead:

```php
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

This ships in every fresh Laravel install already — nothing in this package needs to be involved for a mono-tenant app to broadcast to "just this user."
