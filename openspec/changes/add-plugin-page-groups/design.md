## Context

`plugin-menu-registration` today lets a plugin add exactly one flat entry to one of three fixed navs (`main`, `admin`, `settings`) via `RegisterPage::placement()`. The `admin` and `settings` destinations already render as "a main-sidebar entry that owns a second-nav" — but that second-nav mechanism is hardcoded twice (`admin/layout.tsx`, `settings/layout.tsx`) and unavailable to a plugin that wants the same shape for itself (e.g. one "WhatsApp" sidebar entry fanning out to "My Numbers" / "Contacts" pages).

Separately, the app's own native nav entries (Dashboard, the Settings/Admin sections themselves, and the sidebar footer's Horizon/Logs/Repository/Documentation links) are hardcoded directly in `app-sidebar.tsx` and the two layout files, with visibility computed client-side (`hidden: !page.props.auth.user?.is_admin`, `hasRoute(...)`). This is a second, parallel nav-definition mechanism that duplicates concerns `RegisterPage` already solves for plugins.

This change unifies both: a general-purpose grouping primitive for plugins, and a migration of the app's native entries onto the same primitive, so there is exactly one way any nav entry — native or plugin — gets defined.

## Goals / Non-Goals

**Goals:**
- Let a plugin group several pages under one main-sidebar entry with its own second-nav.
- Let visibility be declared once, server-side, as a closure — and have a hidden entry disappear from the `pluginPages` payload entirely, not just be CSS-hidden client-side.
- Let entry order be explicit and deterministic once native entries (Dashboard, Settings, Admin, footer links) live in the same registry as plugin entries.
- Collapse `SettingsLayout`/`AdminLayout` duplication into one generic, reusable layout.
- Preserve full backward compatibility for `vitamind-todo-plugin`, `vitamind-archive-plugin`, and `vitamind-workspace-plugin` — none of them call the new methods and none should need to change.

**Non-Goals:**
- Nested sub-menus within a second-nav (`NavItem.children` gaining a registration API). Deferred; the frontend primitive already exists but no registration surface is added for it in this change.
- Groups appearing anywhere other than the main sidebar (e.g. a group nested inside another group's second-nav, or inside the footer).
- Changing how `adminOnly()` gates the underlying `plugins.page` route — that access-control behavior is untouched; only presentation/visibility in navs changes.

## Decisions

### D1: `RegisterPageGroup` is an explicit entity, not metadata repeated on each member page
A group is registered once — `RegisterPageGroup::make('whatsapp')->title('WhatsApp')->icon('message-circle')->register()` — and pages join it by key via `RegisterPage::group('whatsapp')`. Considered instead: letting each member page repeat `->group('whatsapp', title: '...', icon: '...')`. Rejected because it has no single source of truth (two pages could disagree on the group's title/icon) and can't express a group-level `hidden()`/`order()` cleanly. The explicit-entity shape also mirrors how Laravel itself defines things once by name (routes, gates) rather than inline at every call site — chosen deliberately to match that convention.

A page referencing an unregistered group key throws `InvalidArgumentException` at serialization time (same failure posture as `placement()`'s value validation) rather than silently falling back to an ungrouped main entry.

### D2: Group landing target is always the first-registered member page
The group's own main-sidebar entry needs an `href`. Rather than requiring a separate "landing page" concept, it resolves to the first `RegisterPage` that joined the group, in registration order. Simple, requires no new API, and matches the mental model of "the group is just its members, presented as one rail entry."

### D3: `hidden(Closure $callback)` excludes the entry from `pluginPages` entirely, evaluated lazily per-request
Both `RegisterPage` and `RegisterPageGroup` get `hidden(Closure $callback): self`. The closure takes no arguments — plugin code calls `auth()->user()`, `request()`, etc. inside it, exactly as `RegisterPage::route()` already resolves lazily via Laravel's `route()` helper rather than at `boot()` time. `HandleInertiaRequests` evaluates every registered entry's `hidden()` (default: not hidden) per-request and drops hidden entries before mapping the registry to the `pluginPages` prop.

Considered instead: keeping the existing client-side `hidden: boolean` field on `NavItem` (entry stays in the payload, frontend decides not to render it) — this is what native items already do today (Admin link, Horizon, Logs). Rejected for the closure-driven case specifically because these gates are typically permission-based; leaving a hidden entry's `title`/`href` in the Inertia payload/DOM lets an unauthorized user discover a page's existence even though they can't reach it. Excluding server-side closes that gap. This is a deliberate, net-new behavior for entries that use `hidden()` — it does not retroactively change any existing entry, since nothing today calls a closure-based hidden.

### D4: `order(int $order)` for deterministic sort, defaulting to registration order
Once Dashboard/Settings/Admin/footer entries live in the same registry as arbitrary plugin entries, their fixed visual position (Settings/Admin pinned last; footer always at the bottom) can no longer rely on hardcoded array construction order in `app-sidebar.tsx`. `order()` is added to both `RegisterPage` and `RegisterPageGroup`; core registers `settings`/`admin` groups and footer entries with high `order` values so they keep sorting after plugin-registered entries, which default to registration order (effectively `0`, stable-sorted).

### D5: `placement()` stays as sugar over `group()`; `'footer'` is added as a fourth flat value
`placement('settings')` / `placement('admin')` continue to work unchanged, now implemented as `group('settings')` / `group('admin')` under the hood against the two core-registered groups. The `placement` field remains in the `pluginPages` payload (derived: no group → `'main'`, `group('settings')` → `'settings'`, `group('admin')` → `'admin'`, `placement('footer')` → `'footer'`, any other custom group → `'main'` since it still renders as a main-rail entry). This keeps `ArchiveMenuRegistrationTest`/`WorkspaceMenuRegistrationTest`/`HandleInertiaRequestsPluginPagesTest` passing unchanged.

`'footer'` is flat (no second-nav, no grouping) because footer entries render in `SidebarFooter` of the icon-only rail — a third region, structurally closer to a flat `main` entry than to a group. `RegisterPage::external(bool $external = true)` is added so footer entries like Repository/Documentation (outbound links) serialize an `external` flag the frontend already knows how to render (`NavItem.external`).

### D6: One generic `SectionLayout` replaces `SettingsLayout` and `AdminLayout`
```
SectionLayout({ title, groupKey, coreItems, children }):
  items = [...coreItems, ...pluginPages.filter(p => p.group === groupKey)].sort(by order)
  → <Layout secondNavItems={items} secondNavTitle={title}>{children}</Layout>
```
`coreItems` stays a plain array computed by the caller (as `SettingsLayout`/`AdminLayout` already do with `useMemo`, including their conditional `hasRoute()` logic) — the abstraction only removes the duplicated "merge core items with filtered `pluginPages` and render `Layout`" plumbing, it does not need to know how `coreItems` were computed. Once Dashboard/Settings/Admin/footer are migrated into the registry (D7), `SettingsLayout`/`AdminLayout`'s own `coreItems` (Profile/API Keys, Users/Plugins) also come from `pluginPages` rather than being locally hardcoded, and a plugin's own group page uses `SectionLayout` directly with `coreItems={[]}`.

### D7: Native nav entries (Dashboard, Settings group, Admin group, footer) move into the same registry, owned by core
A core service provider registers these at `boot()`, using the exact same `RegisterPage`/`RegisterPageGroup` API a plugin would use:
- Dashboard: flat `RegisterPage`, `placement` unset (main), low `order`.
- `settings` / `admin`: `RegisterPageGroup`, `admin`'s `hidden()` closure checks `auth()->user()->is_admin`, both with high `order`.
- Profile, API Keys → `group('settings')`; Users, Plugins → `group('admin')`.
- Horizon Dashboard, Logs: `placement('footer')`, `hidden()` closure checks both `Route::has(...)` (package installed) and `is_admin`.
- Repository, Documentation: `placement('footer')`, `external(true)`, no `hidden()`.

Considered instead: leaving native items hardcoded and only building the group primitive for plugins. Rejected per explicit product decision — the duplication between "how plugins declare nav visibility/order" and "how core declares its own" was judged not worth carrying forward once the closure-based `hidden()`/`order()` primitives exist; better to have exactly one mechanism.

## Risks / Trade-offs

- **[Risk] Regression in native nav rendering** (Dashboard/Settings/Admin/footer are exercised by every session; a mistake here is high-blast-radius) → Mitigation: add feature tests asserting `pluginPages` contains the expected native entries with correct `group`/`placement`/`order`/`hidden` resolution for both admin and non-admin users, plus a full-page Inertia render check per section, before touching `app-sidebar.tsx`'s consumption logic.
- **[Risk] `hasRoute()`-style checks move from client (Ziggy) to server (`Route::has()`)** for Horizon/Logs — behaves the same in practice (a route either exists or doesn't, on both sides) but is a new code path → Mitigation: cover both "package installed" and "package absent" cases in tests.
- **[Risk] Ordering regressions** if a plugin's default (registration-order) `order` value ever collides with or exceeds core's pinned high values → Mitigation: document the convention (e.g. core reserves a high numeric band) in `menu-registration.md` rather than leaving it as an implicit contract.
- **[Trade-off] `RegisterPageGroup` registry needs the same test-isolation `flush()` as `RegisterPage`** (see existing footgun documented for tabs-mode pages and `RefreshDatabase` in `menu-registration.md`) — adds one more piece of registry state `TestCase` must reset between tests.

## Migration Plan

Single-deploy change (no feature flag / gradual rollout needed — this is an internal boilerplate/SDK mechanism, not a user-facing data migration). Sequencing within the change:
1. Ship `RegisterPageGroup`, and the new `RegisterPage` methods (`group`, `hidden`, `order`, `external`), plus the `hidden()`-filtering in `HandleInertiaRequests` and the `footer` placement — additive, nothing consumes them yet.
2. Ship the frontend `SectionLayout` and update `app-sidebar.tsx` to read `group`/`order`/`placement === 'footer'` — additive, still no native items registered through it yet, so rendering is unchanged.
3. Register native items (Dashboard/Settings/Admin/footer) through core's provider, and delete the hardcoded arrays from `app-sidebar.tsx`/`settings/layout.tsx`/`admin/layout.tsx` in the same PR so there is never a window where both mechanisms are simultaneously live for the same entries.
4. Update `docs/plugin-development/menu-registration.md`.

Rollback: revert the PR; nothing in this change touches persisted data (registry state is process-local, rebuilt every `boot()`), so rollback is a plain code revert with no cleanup step.

## Open Questions

- Exact core service-provider file/location for the native registrations (`dev-packages/vitamind-core/src/Providers/...`) — an implementation detail to resolve in `tasks.md`, not a blocking design question.
- Whether `order` ties are broken by registration order (stable sort) or by key — leaning stable-sort-by-registration-order for predictability; confirm during implementation if it matters in practice (unlikely to ever collide since core reserves a high band).
