## Why

Plugins are not allowed to edit boilerplate code such as `resources/js/components/app-sidebar.tsx` — their only sanctioned extension points are `extends`/config-style SDK mechanisms. Today the only such mechanism, `RegisterPage`, can add a sidebar entry solely for pages rendered by the generic `plugins.page` route (`PluginPageController` → the `dynamic-page` DataTable/CRUD UI). A plugin that ships its own routes and its own Inertia frontend — like `vitamind/archive-plugin`, whose folder/file hierarchy has no sensible `RegisterDataTable` representation — has no config-driven way to appear in the main sidebar, which forces exactly the boilerplate edit the architecture forbids.

## What Changes

- Extend `VitaminD\PluginSdk\RegisterPage` to accept a plugin-owned `href`/route name as an alternative to the built-in `plugins.page` destination, alongside the existing `tabs()`-based DataTable mode. A page registers either mode; both continue to serialize into the same `pluginPages` Inertia prop.
- Update `dev-packages/vitamind-core/src/Http/Middleware/HandleInertiaRequests.php` (or `RegisterPage::toArray()`) so the serialized shape carries the resolved link for either mode, without the frontend needing to know which mode produced it.
- Update `resources/js/components/app-sidebar.tsx` — as an official boilerplate change, not a plugin edit — to consume that resolved link generically for every `pluginPages` entry, replacing the current hardcoded `route('plugins.page', p.key)`.
- Update `dev-packages/vitamind-archive-plugin` to register its "Archive" sidebar entry through the new custom-href mode, pointing at its own `archive.index` route, instead of any direct edit to sidebar boilerplate.
- Add a `RegisterPage::description(string $description): self` method so a plugin can supply the heading description shown on its `plugins/dynamic-page` screen, replacing the hardcoded `Manage plugin database records for {title}.` string in `resources/js/pages/plugins/dynamic-page.tsx` when a plugin sets one — falling back to that same default text when it doesn't, so existing plugins render unchanged.
- Add a `RegisterPage::placement(string $target): self` method (`'main' | 'admin' | 'settings'`) so a plugin can explicitly choose which of the app's three plugin-aware navs its entry appears in. When not called, the resolved placement stays exactly what `adminOnly()` already implies today (`adminOnly(false)` → `main`, `adminOnly(true)` → `admin`) — a pure additive escape hatch, not a behavior change for existing registrations.
- Fix `resources/js/layouts/admin/layout.tsx`'s Admin second-nav loop, which hardcodes `route('plugins.page', p.key)` exactly like the old `app-sidebar.tsx` did — it gets the same generic `p.href` treatment, so an admin-only custom-link page isn't silently broken there while working in the main sidebar.
- Add the same generic `pluginPages` consumption, newly, to `resources/js/layouts/settings/layout.tsx`'s Settings second-nav — today that layout has no plugin awareness at all — filtered to entries whose resolved placement is `settings`.
- Document the full menu-registration API at `docs/plugin-development/menu-registration.md` (`docs/plugin-development/` is new), written to be directly usable by both a developer and an AI coding agent implementing a plugin's sidebar entry without needing to read the SDK source.
- No change to the existing `tabs()`-based mode's external behavior — `vitamind-todo-plugin` and any other DataTable-backed plugin continue to register and render exactly as before.

## Capabilities

### New Capabilities
- `plugin-menu-registration`: The contract by which a plugin registers a menu entry — either a `tabs()`-based generic CRUD page or a plugin-owned custom route — targeting one of the app's three plugin-aware navs (main sidebar, Admin second-nav, Settings second-nav) via `RegisterPage`'s `placement()`, and how that registration flows through the Inertia `pluginPages` prop into boilerplate, without the plugin touching any nav-rendering code directly.

### Modified Capabilities
- `vitamind-archive-plugin`: Adds the requirement that the plugin registers its own "Archive" main-sidebar entry (via the new custom-href `RegisterPage` mode) pointing at `archive.index`, rather than requiring a boilerplate edit.

## Impact

- **`dev-packages/vitamind-plugin-sdk/src/RegisterPage.php`**: new custom-href registration mode, new `description()` method, and new `placement()` method, existing `tabs()`/`adminOnly()` mode unchanged.
- **`dev-packages/vitamind-core/src/Http/Middleware/HandleInertiaRequests.php`**: `pluginPages` prop shape gains a resolved-link field, a `description` field, and a resolved `placement` field.
- **`resources/js/components/app-sidebar.tsx`**: generic link consumption for `pluginPages`, filtered to `placement === 'main'`, replacing the `plugins.page`-only assumption and the raw `!p.admin_only` check. This is a boilerplate/core change made once, by this proposal — not a precedent for plugins editing it directly.
- **`resources/js/layouts/admin/layout.tsx`**: same generic `href` consumption, filtered to `placement === 'admin'` (was: raw `p.admin_only` check with hardcoded `plugins.page` link).
- **`resources/js/layouts/settings/layout.tsx`**: new `pluginPages` consumption, filtered to `placement === 'settings'` — previously had none.
- **`resources/js/pages/plugins/dynamic-page.tsx`**: heading `description` sourced from `page.description`, falling back to the current hardcoded string when a plugin hasn't set one.
- **`resources/js/types/index.d.ts`** (or wherever the `pluginPages`/page prop is typed): gains `description?: string` and `placement: 'main' | 'admin' | 'settings'` fields.
- **`docs/plugin-development/menu-registration.md`** (new file, new directory): reference documentation for the whole menu-registration API, aimed at both human developers and AI coding agents extending a plugin.
- **`dev-packages/vitamind-archive-plugin/src/Providers/ArchiveServiceProvider.php`** (or a `Plugin` class if introduced): registers the "Archive" menu entry.
- **`dev-packages/vitamind-todo-plugin`**: no code change required; existing registration keeps working unmodified.
- Tests: `dev-packages/vitamind-plugin-sdk/tests/RegisterPageTest.php` gains coverage for the new mode; a frontend/feature check that the sidebar renders a custom-href plugin entry correctly.
