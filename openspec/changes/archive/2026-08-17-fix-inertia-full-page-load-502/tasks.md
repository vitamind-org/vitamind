## 1. Fix

- [x] 1.1 Remove `\Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class` from the `web` middleware group in `bootstrap/app.php`.

## 2. Verify

- [x] 2.1 With local Valet running, full-page load (hard refresh / paste URL directly, not link-click) a previously-affected route (e.g. `/settings/api-keys`, `/admin/provisioners`) as an authenticated user and confirm HTTP 200, no nginx 502.
- [x] 2.2 Inspect the response headers on that same full-page load and confirm no `Link` header is present.
- [x] 2.3 Click through in-app menu navigation to the same route and confirm it still works unchanged (client-side Inertia navigation, JSON response, no `Link` header either — same as before the fix).
- [x] 2.4 Tail `~/.valet/Log/nginx-error.log` while performing 2.1–2.3 and confirm no new "upstream sent too big header" entries are produced.
- [x] 2.5 Spot-check that page load still renders styles/scripts correctly (no missing assets) with the header removed, confirming `@vite()`'s inline `<head>` tags are sufficient without the response header.

## 3. Housekeeping

- [x] 3.1 Run `vendor/bin/pint` on `bootstrap/app.php` before committing.
