## Why

A plugin with several related pages (e.g. a WhatsApp plugin with "My Numbers" and "Contacts") currently has no way to group them — each `RegisterPage` entry becomes its own top-level main-sidebar icon. Plugins want to group such pages under one main-sidebar entry that opens a dedicated second-nav, exactly like the built-in Settings and Admin sections already behave. Today that grouping mechanism (a main-sidebar entry that owns a second-nav) is hardcoded to just those two sections and isn't available to plugins.

## What Changes

- Add `RegisterPageGroup`: a new SDK entity a plugin registers once (key, title, icon, optional `hidden()`, optional `order()`) that produces one main-sidebar entry owning its own second-nav.
- Add `RegisterPage::group(string $key)`: a page joins a group instead of (or via) `placement()`. The group's main-sidebar entry links to its first-registered member page.
- Add `RegisterPage::hidden(Closure $callback)` and `RegisterPageGroup::hidden(Closure $callback)`: a no-arg closure, evaluated lazily per-request (same timing as `route()`), that excludes the entry from the `pluginPages` payload entirely when it returns `true` — not just a client-side CSS hide. **BREAKING (payload contents)**: entries with a truthy `hidden()` no longer appear in `pluginPages` at all (previously all native/nav visibility gating was client-side and never went through this payload).
- Add `RegisterPage::order(int $order)` / `RegisterPageGroup::order(int $order)` for deterministic nav ordering; defaults to registration order.
- Add `RegisterPage::external(bool $external = true)` so an entry can render as an outbound link (needed for footer entries like Repository/Documentation).
- Add `'footer'` as a new flat `placement()` value (alongside the existing `main`/`admin`/`settings`), for entries that render in the sidebar footer region rather than the main rail or a second-nav.
- Keep `placement('settings')` / `placement('admin')` working unchanged as sugar over `group('settings')` / `group('admin')` — no changes required in `vitamind-todo-plugin`, `vitamind-archive-plugin`, or `vitamind-workspace-plugin`.
- Migrate the app's native nav entries (Dashboard, the Settings section, the Admin section, and footer entries Horizon Dashboard/Logs/Repository/Documentation) to be registered the same way, by core, instead of hardcoded in `app-sidebar.tsx` / `settings/layout.tsx` / `admin/layout.tsx`.
- Add a single generic frontend layout (`SectionLayout`) that renders a main-sidebar-entry's second-nav from `pluginPages` filtered by group key, replacing the near-duplicate `SettingsLayout` and `AdminLayout`, and reusable by any plugin's own group pages.
- Update `app-sidebar.tsx` to collapse same-group `pluginPages` entries into one main-sidebar icon, render `placement === 'footer'` entries in the sidebar footer, and sort all of the above by `order`.

Explicitly out of scope: nested sub-menus within a second-nav (`NavItem.children`) — deferred to a future change.

## Capabilities

### New Capabilities
(none — this extends the existing plugin-menu-registration capability rather than introducing a new domain)

### Modified Capabilities
- `plugin-menu-registration`: adds `RegisterPageGroup`, `group()`, `hidden()`, `order()`, `external()`, the `footer` placement value, the exclude-when-hidden payload behavior, and the native-item migration (Dashboard/Settings/Admin/footer now registered through the same mechanism instead of hardcoded).

## Impact

- `dev-packages/vitamind-plugin-sdk/src/RegisterPage.php` — new builder methods, `toArray()` shape changes (`group`, `order`, `external` fields added; `hidden` entries filtered out before serialization).
- `dev-packages/vitamind-plugin-sdk/src/RegisterPageGroup.php` — new file.
- `dev-packages/vitamind-core/src/Http/Middleware/HandleInertiaRequests.php` — filter registry by resolved visibility before mapping to `pluginPages`.
- A new core service provider (or existing core boot path) registering Dashboard, the `settings`/`admin` groups and their native member pages, and the footer entries.
- `resources/js/types/index.d.ts` — `pluginPages` shape gains `group`, `order`, `external`.
- `resources/js/components/app-sidebar.tsx` — group-collapsing, footer-region rendering, `order`-based sorting.
- `resources/js/layouts/settings/layout.tsx`, `resources/js/layouts/admin/layout.tsx` — replaced by a shared `SectionLayout` (new file, likely `resources/js/layouts/section/layout.tsx`).
- `docs/plugin-development/menu-registration.md` — documentation update for the new builder methods and the group model.
- Existing feature tests asserting `pluginPages` shape (`ArchiveMenuRegistrationTest`, `WorkspaceMenuRegistrationTest`, `HandleInertiaRequestsPluginPagesTest`) — should continue passing unchanged given the back-compat sugar, but new tests are needed for groups, `hidden()` exclusion, ordering, and footer placement.
