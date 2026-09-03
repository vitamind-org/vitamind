## Context

Plugins register a main-sidebar entry today through `VitaminD\PluginSdk\RegisterPage` (`dev-packages/vitamind-plugin-sdk/src/RegisterPage.php`), called from a plugin's `boot()`. Each registered page is serialized (`toArray()`) into the `pluginPages` Inertia prop by `HandleInertiaRequests` (`dev-packages/vitamind-core/src/Http/Middleware/HandleInertiaRequests.php:47`), and `resources/js/components/app-sidebar.tsx` renders one menu item per non-admin-only entry — but it hardcodes the destination as `route('plugins.page', p.key)`, the generic route served by `PluginPageController`, which only knows how to render `plugins/dynamic-page`: a paginated CRUD table over one Eloquent model, built from `RegisterDataTable`/`RegisterPage::tabs()`.

`vitamind/archive-plugin` (and, per `plugin-frontend-distribution`'s existing "distributed plugin" tier, any plugin symlinked under `vendor/vitamind/*`) can ship its own routes and its own `@plugin/{name}/...` Inertia pages. Archive's folder/file hierarchy with per-item visibility has no meaningful `RegisterDataTable` shape, so it uses this custom-route path already (`archive.index` etc., `Inertia::render('@plugin/archive-plugin/index', ...)`). It currently has no config-driven way to get a sidebar entry, because `RegisterPage` only ever points at `plugins.page`.

Governing constraint (architectural rule, not just this proposal's preference): plugins do not edit boilerplate files such as `app-sidebar.tsx`. Every plugin-specific behavior must flow through an SDK extension point or config. This design closes that gap for menu registration specifically.

## Goals / Non-Goals

**Goals:**
- Let a plugin register a sidebar entry that links to a route it owns, with the same `RegisterPage` builder API plugins already use.
- Keep today's `tabs()`-based registration (Todo Plugin's flow) working with zero code changes on the plugin side.
- Keep `app-sidebar.tsx`'s consumption of `pluginPages` generic — it should not need to know how a given entry's link was produced.
- Make `vitamind-archive-plugin` register its own "Archive" entry through this mechanism.
- Let a plugin customize the heading description shown on its own `plugins/dynamic-page` screen, instead of that text being hardcoded per-plugin-agnostic in the page component.
- Let a plugin choose which of the app's three plugin-aware navs (main sidebar, Admin second-nav, Settings second-nav) its entry appears in, without breaking the existing implicit rule (`adminOnly()` alone already picks between main and admin today).
- Fix `admin/layout.tsx`'s independent hardcoding of `route('plugins.page', p.key)` — the same coupling this proposal removes from `app-sidebar.tsx` — so custom-link admin-only pages resolve correctly there too.

**Non-Goals:**
- Redesigning `RegisterDataTable`/the `dynamic-page` CRUD system itself.
- Supporting nested/sub-menu entries, badges, or ordering hints — out of scope; entries append in registration order as they do today.
- A general "any plugin custom href" security model beyond what Laravel's own named-route resolution already guarantees (a plugin can only resolve routes that actually exist).
- Extending this to GitHub-installed plugins without a host rebuild — `plugin-frontend-distribution` already scopes custom Inertia pages to the build-time `@plugin/{name}` alias tier; this design does not change that boundary. A GitHub plugin without a rebuild can still only use `tabs()`-based registration.

## Decisions

### 1. Extend `RegisterPage` with a `href()`/`route()` method rather than a new SDK class

`RegisterPage::route(string $name, array $params = [])` (or `href(string $url)` for a fully pre-resolved link — both are supported, `route()` resolving lazily at serialization time via Laravel's `route()` helper) sets an internal `$link` in addition to the existing `$tabs`. A page is registered in exactly one of two mutually exclusive modes:
- **Tabs mode** (existing): `->tabs([...])`, no `route()`/`href()` call. `toArray()`'s link resolves to the existing `plugins.page` route, unchanged.
- **Custom-link mode** (new): `->route('archive.index')` or `->href('/archive')`, no `tabs()` call. `toArray()`'s link resolves to that route/URL instead.

Calling both `tabs()` and `route()`/`href()` on the same `RegisterPage` throws (`InvalidArgumentException`) at `register()` time — it's a plugin-author mistake to catch early, not a runtime ambiguity to silently resolve.

**Alternative considered**: a separate `RegisterMenuItem` class, independent of `RegisterPage`. Rejected — it would fragment the registry (`HandleInertiaRequests` would need to merge two collections into `pluginPages`) and plugin authors would have two similar-looking APIs to choose between for what is conceptually the same concept (an entry in the plugin pages registry with a title/icon/admin_only/link).

### 2. `toArray()` resolves the link server-side; the frontend consumes one `href` field

`RegisterPage::toArray()` gains an `href` key, computed at serialization time:
- Tabs mode: `route('plugins.page', $this->key)` (identical string to what the frontend used to build itself).
- Custom-link mode: `route($this->routeName, $this->routeParams)` if `route()` was used, or the raw string if `href()` was used.

`app-sidebar.tsx` changes from `route('plugins.page', p.key)` to `p.href`, and drops its own route-building for plugin entries entirely. This keeps the frontend contract to "render `title`, `icon`, `href`, `admin_only`" — it never needs to know which registration mode produced the entry.

**Alternative considered**: resolve the link client-side, passing a `mode`/`key` pair and letting `app-sidebar.tsx` branch on it. Rejected — it would re-introduce exactly the coupling this change removes (frontend needing to know about `plugins.page` as a special case), and route-name resolution belongs server-side where Laravel's router lives.

### 3. `vitamind-archive-plugin` registers via `ArchiveServiceProvider::boot()`, no new `Plugin` class

Archive currently has no `AbstractPlugin`-extending `Plugin` class (unlike `vitamind-todo-plugin`) — it's a plain `Illuminate\Support\ServiceProvider`. `RegisterPage` has no dependency on `AbstractPlugin`; it's called directly. So the registration call is added to `ArchiveServiceProvider::boot()` alongside its existing `registerPolicies()`/`registerRoutes()` calls, guarded the same way the rest of the provider is (no feature flag — per the file's existing doc comment, the plugin as a whole is always available):

```php
RegisterPage::make('archive')
    ->title('Archive')
    ->icon('archive')
    ->route('archive.index')
    ->adminOnly(false)
    ->register();
```

No new class introduced; consistent with the provider's existing shape.

### 4. `RegisterPage::description()` overrides the hardcoded heading text, with a preserved default fallback

`dynamic-page.tsx:261` currently hardcodes `` `Manage plugin database records for ${page.title}.` `` as the heading description for every `tabs()`-mode page, regardless of plugin. `RegisterPage` gains `description(string $description): self`, storing an internal nullable `$description` (default `null`). `toArray()` includes it as a `description` key — `null`/omitted when never set.

The frontend changes to:
```tsx
description={page.description || `Manage plugin database records for ${page.title}.`}
```
so a plugin that never calls `description()` (e.g. `vitamind-todo-plugin`, unchanged) keeps rendering exactly the same text it does today, while a plugin that does call it — including a custom-link page that also happens to use `plugins/dynamic-page` indirectly, or any future `tabs()`-mode plugin — gets its own wording.

**Alternative considered**: compute a smarter default (e.g. summarizing the tabs) instead of a plugin-supplied string. Rejected as unnecessary scope for this change — a plain override with the existing string as fallback is sufficient and keeps the description field optional.

### 5. `RegisterPage::placement()` is an explicit override; the implicit `adminOnly()` rule stays the default

Three places already read `pluginPages`, each filtering a different way: `app-sidebar.tsx` includes entries where `!p.admin_only`; `admin/layout.tsx` includes entries where `p.admin_only`; `settings/layout.tsx` reads `pluginPages` not at all. That's an implicit two-way placement rule with no way to reach the third destination.

`RegisterPage` gains `placement(string $target): self`, accepting `'main' | 'admin' | 'settings'` (anything else throws `InvalidArgumentException` at `register()` time — same fail-fast posture as the rest of the builder). Internally, an nullable `$placement` is stored. `toArray()`'s resolution rule:

```php
$resolvedPlacement = $this->placement ?? ($this->adminOnly ? 'admin' : 'main');
```

So a plugin that never calls `placement()` — every existing/planned call site (`vitamind-todo-plugin`, `vitamind-archive-plugin`) — keeps resolving exactly as `adminOnly()` already implied, byte-for-byte identical to current behavior. A plugin that wants a Settings-tab entry calls `->placement('settings')` explicitly; `adminOnly()` remains available alongside it as a pure visibility gate (still enforced by `PluginPageController`'s own admin check on the underlying route), now decoupled from *where* the entry renders.

Each of the three consumers filters on the same resolved field:
- `app-sidebar.tsx`: `pluginPages.filter(p => p.placement === 'main')`
- `admin/layout.tsx`: `pluginPages.filter(p => p.placement === 'admin')` — and switches from its own hardcoded `route('plugins.page', p.key)` to `p.href`, the same fix `app-sidebar.tsx` gets (see Decision 2)
- `settings/layout.tsx`: `pluginPages.filter(p => p.placement === 'settings')` — new, this layout had no plugin-page awareness before

**Alternative considered**: keep placement implicit forever (boolean `adminOnly()` only, no `settings` destination) and instead give `SettingsLayout` its own separate registration API (e.g. `RegisterSettingsTab`). Rejected — it would be a second, parallel registry for the same underlying concept (title/icon/href/admin_only), the same fragmentation problem Decision 1 already rejected for the main-vs-admin split. A single `placement` enum on the existing `RegisterPage` keeps one registry, one mental model, and generalizes to a fourth destination later without a new class.

**Alternative considered**: derive `settings` placement from a separate boolean like `adminOnly()`, e.g. `settingsPage(bool $settings = true)`. Rejected — placement is not binary (there are three destinations, likely more later), so a small closed string enum scales better than accumulating one boolean per destination.

## Risks / Trade-offs

- **[Risk]** A plugin calls `route()`/`href()` with a route name that doesn't exist yet at serialization time (e.g. route registered by a provider that boots after the one calling `RegisterPage`) → `route()` throws. **Mitigation**: `RegisterPage::toArray()` is only invoked per-request from `HandleInertiaRequests`, long after all providers have booted and all routes are registered — not from within `register()` itself, so registration-order between plugins doesn't matter, only that the route exists by request time.
- **[Risk]** A plugin mistakenly calls both `tabs()` and `route()` → ambiguous entry. **Mitigation**: `register()` throws eagerly (decision 1), fails fast in development/tests rather than silently picking one.
- **[Trade-off]** `app-sidebar.tsx`, `admin/layout.tsx`, and `settings/layout.tsx` are still boilerplate files this proposal edits once each. That's accepted as the correct place for the generic rendering contract to live — the constraint is "plugins don't edit boilerplate," not "boilerplate never changes." After this change, no further plugin needs to touch any of them to get an entry into any of the three navs.
- **[Risk]** A plugin passes an invalid string to `placement()` (typo, e.g. `'setting'`) → silently never renders anywhere if unvalidated. **Mitigation**: `register()` validates against the closed `['main', 'admin', 'settings']` set and throws `InvalidArgumentException` on anything else, same fail-fast posture as the rest of the builder.

## Migration Plan

1. Land the `RegisterPage` extension + tests in `vitamind-plugin-sdk` (additive, no existing call sites change).
2. Land the `HandleInertiaRequests`/`toArray()` link-resolution change in `vitamind-core`.
3. Land the `app-sidebar.tsx` change to consume `p.href`, filtered on resolved `placement === 'main'` (behaviorally identical for existing `tabs()`-mode entries, since both `href` and `placement` resolve to the same values their current hardcoded logic already produces).
4. Land the `archive-plugin` registration call.
5. Land the `description()` field end-to-end (SDK method → `toArray()` → `dynamic-page.tsx` fallback) — independent of steps 1-4, safe to land in any order relative to them since it touches a disjoint code path (the `tabs()`-mode renderer, not the sidebar or custom-link routing).
6. Land the `admin/layout.tsx` fix (`p.href`, filtered on `placement === 'admin'`) — same reasoning as step 3, behaviorally identical for today's registrations.
7. Land the new `settings/layout.tsx` consumption (filtered on `placement === 'settings'`) — purely additive, no existing entry resolves to that placement today, so `SettingsLayout`'s current hardcoded items are unaffected.
8. No data migration, no config flag, no rollback complexity beyond a standard revert — the change is additive at every layer until step 3/6, and both preserve byte-identical URLs and placement for existing entries; step 5 preserves byte-identical heading text for every plugin that doesn't opt in.

## Open Questions

- Should `icon()` gain plugin-supplied custom SVG/URL support at the same time, or stay limited to the existing `lucide-react` name-lookup convention? Left as-is (out of scope) — `archive` already resolves via the existing PascalCase-name lookup (`ArchiveIcon` in `lucide-react`), so nothing new is required for this change.
