## Context

`bootstrap/app.php` registers Laravel's built-in `\Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets` in the global `web` middleware group, with no `$limit` argument:

```php
$middleware->web(append: [
    \VitaminD\Core\Http\Middleware\HandleInertiaRequests::class,
    \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
]);
```

This middleware (`vendor/laravel/framework/.../AddLinkHeadersForPreloadedAssets.php`) inspects `Vite::preloadedAssets()` after the response is built and, if non-empty, joins every entry into a single `Link` response header — unbounded, since no limit was passed at registration.

`Vite::preloadedAssets()` is populated as a side effect of Blade's `@vite(...)` directive resolving entry points through the manifest. `resources/views/app.blade.php` calls it like this:

```php
@vite(array_values(array_filter(['resources/js/app.tsx', $pageComponentPath])))
```

`@vite()` walks each entry's manifest `imports`/`css` recursively and registers every resolved chunk as a preload candidate. Measured directly against `public/build/manifest.json`, the shared `resources/js/app.tsx` entry alone resolves to **92 JS chunks + 1 CSS file** — before adding whatever the specific page component itself imports (data tables, dialogs, forms, icon sets, etc. push heavier pages further).

Crucially, `app.blade.php` — and therefore `@vite()` — only renders on a **full, non-XHR** Inertia response. Client-side `<Link>` navigation sends `X-Inertia: true`; Inertia's middleware short-circuits straight to a JSON payload and never touches the Blade view, so `Vite::preloadedAssets()` stays empty and the `Link` header is never added. This is why menu-link navigation always worked while refresh/direct-URL/new-tab navigation to the same route did not.

On a full page load, the resulting `Link` header exceeds nginx's fastcgi header buffer, and nginx returns 502 with `upstream sent too big header while reading response header from upstream` instead of relaying PHP's actual response. Confirmed against `~/.valet/Log/nginx-error.log`: 14 occurrences of this exact error between 2026-07-30 and 2026-08-17, on both `vitamin-d.localhost` and `wakuwaku.localhost`, exclusively on routes with richer page components (`/settings/profile`, `/settings/api-keys`, `/settings/whatsapp-numbers`, `/admin/provisioners`, `/whatsapp/numbers`). `/` and `/dashboard` never appear in the log because their chunk count stays under the buffer ceiling — which is also why "log in redirects to dashboard" looked like proof the bug was elsewhere; it wasn't a session problem, it was a page-weight problem.

Ruled out during investigation (kept here so the reasoning isn't re-derived later):
- **Session/cookie handling** — the guest-middleware redirect from `/login` to `/dashboard` for an already-authenticated user is correct Laravel behavior, not a symptom.
- **Inertia SSR** — `INERTIA_SSR_ENABLED=true` with no compiled `bootstrap/ssr` bundle. `vendor/inertiajs/inertia-laravel/src/Ssr/HttpGateway.php::dispatch()` checks `bundleExists()` first and returns `null` immediately when it's missing (no HTTP call attempted), and even a live connection failure is caught and degrades gracefully unless `inertia.ssr.throw_on_error` is set (it isn't). SSR cannot be the crash source here.
- **nginx/Valet configuration itself** — bumping `fastcgi_buffer_size` would raise the ceiling but not remove it; the header's size is proportional to however many chunks the union of installed plugin pages produces, which only grows as VitaminD's plugin ecosystem grows. A buffer bump is not durable and doesn't travel with the repo to other machines/environments.

## Goals / Non-Goals

**Goals:**
- Eliminate the unbounded `Link` preload header on full Inertia page renders so no page — regardless of how many Vite chunks it pulls in — can trip a reverse-proxy header-size limit.
- Fix this at the application level so the fix travels with the repo (every developer's Valet instance, CI, staging, production) rather than requiring per-environment nginx tuning.

**Non-Goals:**
- Re-implementing a bounded/capped version of response-header preloading (e.g. `AddLinkHeadersForPreloadedAssets::using($limit)`). Rejected — see Decisions.
- Changing nginx/Valet buffer configuration. Out of scope: local-machine-only, doesn't fix other environments, and isn't needed once the header is removed.
- Any change to `@vite()`'s inline `<head>` output, Vite code-splitting/chunking strategy, or SSR configuration — none of these are implicated in the root cause.

## Decisions

### Decision: Remove `AddLinkHeadersForPreloadedAssets` entirely rather than cap it with `::using($limit)`

Two viable fixes were on the table:
1. **Cap it**: `AddLinkHeadersForPreloadedAssets::using(15)` — keep the header but truncate to N assets.
2. **Remove it**: drop the middleware from the `web` group entirely.

Chosen: **remove**. Rationale:
- The `Link: rel=modulepreload` response header is a resource hint whose main practical benefit is letting an HTTP/2-push-aware intermediary or a very early-parsing browser start fetching before it reaches the response body. This app has no HTTP/2 server-push infrastructure. Vite's `@vite()` already inlines equivalent `<link rel="modulepreload">` tags directly in the rendered `<head>` (visible in `resources/views/app.blade.php`), so the browser gets the same preload signal from the HTML itself, just a few milliseconds later in the parse — not measurably different in practice for this app's request path.
- A cap only raises the threshold; it doesn't remove the failure mode. VitaminD's architecture is explicitly plugin-extensible (`vitamind/workspace-plugin`, `vitamind/realtime-plugin`, `vitamind/todo-plugin`, and more to come per `docs/local-plugins.md`), so the chunk count for the union of installed plugin pages will keep growing. A cap of 15 today could still be wrong for a page with 20 heavy plugin-provided components tomorrow, reintroducing the exact same bug class with no compile-time or CI signal to catch it.
- Removing it fully collapses the failure mode to zero rather than "less likely."

Alternatives considered and rejected:
- *Cap at a conservative number*: rejected for the reason above — it's a threshold shift, not a fix, on a value that's structurally unbounded.
- *Raise nginx/Valet `fastcgi_buffer_size` for all environments*: rejected as non-goal — infra config doesn't travel with the repo, must be replicated per developer machine and per deployment target, and still has a ceiling.
- *Move preload logic to only include the current page's own chunks, excluding the shared `app.tsx` graph*: not pursued — `app.tsx`'s 92-chunk graph is already close to typical buffer limits on its own, so this would reduce but not eliminate risk, and adds bespoke logic to maintain for a marginal, unmeasured benefit.

## Risks / Trade-offs

- **[Risk] Losing a real performance benefit from response-header preloading** → **Mitigation**: `@vite()`'s inline `<head>` `<link rel="modulepreload">` tags already provide the same signal to the browser; the response-header form is redundant in this stack (no HTTP/2 push front-end). No user-facing performance regression expected.
- **[Risk] A different, unrelated large-response-header issue could still trip the same class of 502 in the future (e.g. an oversized `Set-Cookie`, a verbose custom header added elsewhere)** → **Mitigation**: out of scope for this change since no such source exists today (repo-wide grep confirmed `AddLinkHeadersForPreloadedAssets` was the only registration and only source of unbounded headers on the full-page-render path). Documented here so a future large-header 502 report is investigated as a *new* instance rather than assumed to be this same bug recurring.

## Migration Plan

Single-line removal in `bootstrap/app.php`; no data migration, no config flag, no deployment sequencing concerns. Rollback is equally trivial (re-add the line) if some unforeseen dependency on the `Link` header is discovered, though none is expected — nothing in the codebase reads or relies on that header (confirmed via repo-wide grep for `AddLinkHeadersForPreloadedAssets`/`preloadedAssets`, which found only the single registration point).

## Open Questions

None outstanding — root cause is confirmed via code inspection and corroborated by live nginx error log evidence, and the fix is a single, low-risk removal.
