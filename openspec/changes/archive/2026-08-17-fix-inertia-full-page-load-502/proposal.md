## Why

Authenticated users get a nginx 502 ("upstream sent too big header") whenever they refresh or directly navigate (typed URL, bookmark, new tab) to an Inertia page — even though the exact same route works fine when reached via in-app menu link clicks and the session itself is valid. Full-page Inertia renders emit an unbounded `Link: rel=modulepreload` response header (one entry per Vite-resolved JS/CSS chunk) via the framework's `AddLinkHeadersForPreloadedAssets` middleware, and that header grows past nginx's fastcgi header buffer as soon as a page pulls in enough chunks. This is already happening in the wild — 14 occurrences logged locally between 2026-07-30 and today across `vitamin-d.localhost` and `wakuwaku.localhost`, on `/settings/profile`, `/settings/api-keys`, `/settings/whatsapp-numbers`, `/admin/provisioners`, and `/whatsapp/numbers` — and will only get worse as more plugins ship more pages.

## What Changes

- Remove `\Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class` from the global `web` middleware group in `bootstrap/app.php`, eliminating the unbounded `Link` preload header on full Inertia page renders.
- No replacement/capped variant is introduced: for this Vite + Inertia SPA, the browser already receives equivalent `<link rel="modulepreload">` tags inline in the rendered `<head>` via `@vite()`, so the response-header form of preloading is redundant here and not worth re-adding in a bounded form.

## Capabilities

### New Capabilities
- `inertia-full-page-rendering`: Governs how the host app renders a full (non-XHR) Inertia page response — specifically, that the response must stay within limits reverse proxies (nginx/fastcgi) impose on upstream response headers, for any page regardless of how many Vite-built chunks it depends on.

### Modified Capabilities
_(none — no existing spec file covers this behavior; see `openspec/specs/` capability list, none address page-response/header delivery)_

## Impact

- **Code**: `bootstrap/app.php` (`withMiddleware` closure) — one middleware registration removed.
- **Behavior**: Full page loads no longer send a `Link` preload header. No functional loss expected — Vite/Inertia's inline `<head>` modulepreload tags (emitted by `@vite()` in `resources/views/app.blade.php`) already give the browser the same preloading signal; this app has no HTTP/2 server-push infrastructure that would have benefited from the response-header form.
- **Systems**: Fixes the failure for every deployment sitting behind a reverse proxy with a default/modest fastcgi header buffer (local Valet today; any nginx-fronted staging/production environment would hit the same ceiling once enough plugins/chunks accumulate).
- **No changes** to session/auth handling, Inertia SSR configuration, or nginx/Valet configuration — all confirmed unrelated to the root cause during investigation.
