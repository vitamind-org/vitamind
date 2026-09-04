## ADDED Requirements

### Requirement: A plugin groups multiple pages under one main-sidebar entry with its own second-nav
`VitaminD\PluginSdk\RegisterPageGroup` SHALL let a plugin register a group once — `RegisterPageGroup::make(string $key)->title(string)->icon(string)->register()` — and any number of `RegisterPage` entries SHALL be able to join it via `RegisterPage::group(string $key)`. The group SHALL appear as exactly one entry in the main sidebar, resolving to the `href` of its first-registered member page. A `RegisterPage::group()` call referencing a key with no corresponding `RegisterPageGroup::register()` call SHALL throw `InvalidArgumentException` when the registry is serialized.

#### Scenario: Plugin registers a group with two member pages
- **WHEN** a plugin calls `RegisterPageGroup::make('whatsapp')->title('WhatsApp')->icon('message-circle')->register()`, then registers `RegisterPage::make('whatsapp-numbers')->title('My Numbers')->group('whatsapp')->route(...)->register()` and `RegisterPage::make('whatsapp-contacts')->title('Contacts')->group('whatsapp')->route(...)->register()`
- **THEN** exactly one "WhatsApp" entry appears in the main sidebar
- **AND** that entry's `href` resolves to the "My Numbers" page's URL (the first-registered member)
- **AND** navigating into the group shows a second-nav listing "My Numbers" and "Contacts"

#### Scenario: Plugin references an unregistered group
- **WHEN** a plugin calls `RegisterPage::make($key)->group('nonexistent')->register()` and no `RegisterPageGroup` with key `nonexistent` was ever registered
- **THEN** serializing the registry (building the `pluginPages` prop) throws `InvalidArgumentException` identifying the missing group

### Requirement: Nav entry and group visibility can be gated by a server-evaluated closure that excludes hidden entries from the payload
`RegisterPage` and `RegisterPageGroup` SHALL both support `hidden(Closure $callback): self`, where `$callback` takes no arguments and is evaluated lazily, once per request, at the same point `route()` is resolved (not at `boot()` time). When the closure returns `true` for a given request, that entry SHALL be excluded entirely from the `pluginPages` Inertia prop for that request — not merely marked hidden for client-side rendering. When a `RegisterPageGroup` is hidden, every `RegisterPage` that joined it SHALL also be excluded from `pluginPages` for that request, since no nav path to reach them exists. An entry with no `hidden()` call SHALL always be included (default: not hidden).

#### Scenario: Hidden entry is excluded for a user who fails the check
- **WHEN** a plugin registers `RegisterPage::make($key)->hidden(fn () => ! auth()->user()->can('view-x'))->register()`
- **AND** the requesting user does not have the `view-x` permission
- **THEN** `pluginPages` for that request does not contain an entry with that key

#### Scenario: Same entry is included for a user who passes the check
- **WHEN** the same page is registered as above
- **AND** the requesting user does have the `view-x` permission
- **THEN** `pluginPages` for that request contains the entry, with its `href` resolved normally

#### Scenario: Hiding a group also excludes its member pages
- **WHEN** a plugin registers `RegisterPageGroup::make('whatsapp')->hidden(fn () => ! auth()->user()->hasFeature('whatsapp'))->register()` with member pages joined via `group('whatsapp')`
- **AND** the requesting user lacks the `whatsapp` feature
- **THEN** `pluginPages` for that request contains neither the group's own entry nor any of its member pages

### Requirement: Nav entry and group order is explicit and deterministic
`RegisterPage` and `RegisterPageGroup` SHALL both support `order(int $order): self`. Entries sharing the same nav region (main sidebar, a given group's second-nav, or the footer) SHALL render sorted ascending by `order`, with ties broken by registration order. An entry with no `order()` call SHALL default to its registration order (so plugin entries interleave with each other exactly as they do today when none set an explicit order).

#### Scenario: Entries without explicit order render in registration order
- **WHEN** two plugin pages both target `placement('main')` without calling `order()`
- **THEN** they render in the main sidebar in the order their `register()` calls executed

#### Scenario: Explicit order overrides registration order
- **WHEN** a plugin page calls `->order(100)` and another unrelated page in the same region registers later without an explicit order
- **THEN** the page with `order(100)` renders after the other, regardless of which `register()` call ran first

### Requirement: A footer entry can be marked external and renders as an outbound link
`RegisterPage` SHALL support `external(bool $external = true): self`. `toArray()`'s `external` field SHALL reflect this value (default `false`). `placement('footer')` (see the placement requirement) is the intended destination for such entries; the frontend footer region SHALL render an entry with `external: true` as a plain outbound link (`target="_blank"`) rather than an Inertia `Link`.

#### Scenario: Plugin registers an outbound footer link
- **WHEN** a plugin calls `RegisterPage::make($key)->title('Docs')->href('https://example.com/docs')->placement('footer')->external(true)->register()`
- **THEN** `pluginPages` includes that entry with `external: true`
- **AND** the sidebar footer renders it as a plain anchor to that URL, not an Inertia navigation

### Requirement: Core's own native nav entries are registered through the same mechanism as plugin entries
Dashboard, the `settings` and `admin` `RegisterPageGroup`s (and their built-in member pages Profile/API Keys/Users/Plugins), and the sidebar footer entries (Horizon Dashboard, Logs, Repository, Documentation) SHALL be registered by core via `RegisterPage`/`RegisterPageGroup`, exactly as a plugin would register its own entries. `resources/js/components/app-sidebar.tsx`, `resources/js/layouts/admin/layout.tsx`, and `resources/js/layouts/settings/layout.tsx` SHALL NOT hardcode these entries — they SHALL be derived entirely from `pluginPages`.

#### Scenario: Non-admin user's payload excludes admin-only native entries
- **WHEN** a non-admin user requests any Inertia page
- **THEN** `pluginPages` does not contain the `admin` group entry, nor its `Users`/`Plugins` members, nor the `Horizon Dashboard`/`Logs` footer entries
- **AND** it does contain `Dashboard`, the `settings` group entry, and the `Repository`/`Documentation` footer entries

#### Scenario: Admin user's payload includes all native entries
- **WHEN** an admin user requests any Inertia page
- **THEN** `pluginPages` contains the `admin` group entry and its members, and the `Horizon Dashboard`/`Logs` footer entries, in addition to everything a non-admin user sees

### Requirement: A generic second-nav layout renders any group's members
A single frontend layout component SHALL compute a group's second-nav (`secondNavItems`, `secondNavTitle`) from `pluginPages` filtered by that group's key, sorted by `order`, and SHALL be reusable both by core (for the `settings`/`admin` groups) and by any plugin's own custom-link page for its own group — without either needing a bespoke layout file duplicating that filtering/rendering logic.

#### Scenario: Plugin's own group page reuses the shared layout
- **WHEN** a plugin's custom-link page for a page in the `whatsapp` group renders itself
- **THEN** it does so using the shared second-nav layout component, passing its own group key, without defining a new layout file that reimplements the `pluginPages`-filtering logic

#### Scenario: Settings and Admin render via the same shared layout as any plugin group
- **WHEN** the Settings or Admin section renders
- **THEN** it uses the same shared layout component a plugin group would use, configured with the `settings`/`admin` group key, rather than a section-specific layout implementation

## MODIFIED Requirements

### Requirement: A plugin chooses which nav its entry appears in via an explicit or implied placement, or joins a group
`RegisterPage` SHALL support an optional `placement(string $target): self` method accepting `'main'`, `'admin'`, `'settings'`, or `'footer'`; `register()` SHALL throw `InvalidArgumentException` for any other value. When `placement()` is not called, the resolved placement SHALL be `'admin'` if `adminOnly(true)` was set, otherwise `'main'` — identical to the filtering every existing consumer already performs. `adminOnly()` SHALL remain an independent visibility gate (enforced on the underlying `plugins.page` route) regardless of which placement is resolved or set.

`placement('settings')` and `placement('admin')` SHALL behave as sugar for `group('settings')` and `group('admin')` against the two core-registered groups of those keys — existing plugins calling `placement('settings')`/relying on the implicit `admin` default SHALL continue to work with no code changes. The `pluginPages` payload's `placement` field SHALL be derived from group membership: no group → `'main'`, `group('settings')` → `'settings'`, `group('admin')` → `'admin'`, `placement('footer')` → `'footer'`, any other custom group → `'main'`.

#### Scenario: Placement defaults to today's implicit rule when unset
- **WHEN** a plugin registers a page without calling `placement()`, exactly as `vitamind-todo-plugin` and `vitamind-archive-plugin` already do
- **THEN** the resolved placement is `'main'` if the page is not `adminOnly()`, or `'admin'` if it is — identical to current behavior
- **AND** no code change is required in either plugin to keep this working

#### Scenario: Plugin explicitly targets the Settings second-nav
- **WHEN** a plugin calls `RegisterPage::make($key)->placement('settings')->register()`, exactly as documented today
- **THEN** the resolved placement is `'settings'`, regardless of the page's `adminOnly()` value
- **AND** the entry appears in the Settings second-nav (now rendered as the `settings` group's members), not the main sidebar or the Admin second-nav
- **AND** no code change is required in the plugin to keep this working

#### Scenario: Plugin targets the footer
- **WHEN** a plugin calls `RegisterPage::make($key)->placement('footer')->register()`
- **THEN** the resolved placement is `'footer'`
- **AND** the entry renders in the sidebar footer region, not the main rail or any second-nav

#### Scenario: Plugin passes an invalid placement value
- **WHEN** a plugin calls `->placement('sidebar')` (or any string outside `main`/`admin`/`settings`/`footer`)
- **THEN** `register()` throws an `InvalidArgumentException` rather than silently resolving to no placement

### Requirement: All plugin-aware navs consume `pluginPages` generically, filtered by resolved placement or group
`resources/js/components/app-sidebar.tsx`'s main rail SHALL render one entry per distinct `group` (or per ungrouped `placement === 'main'` entry) and one entry per `placement === 'footer'` entry in its footer region, all sorted by `order`. The shared second-nav layout (see "A generic second-nav layout renders any group's members") SHALL render a group's members when that group's main-rail entry is active. None of these consumers SHALL hardcode `route('plugins.page', p.key)`, branch on `admin_only` to decide placement, or hardcode any native entry (Dashboard, Settings, Admin, footer links) outside of what core registers through `pluginPages`.

#### Scenario: Admin second-nav renders a custom-link admin-only entry correctly
- **WHEN** a plugin registers `RegisterPage::make($key)->route('my-plugin.admin.index')->adminOnly(true)->register()`
- **THEN** the entry's resolved placement is `'admin'` (implied default, i.e. `group('admin')`) and its `href` resolves to `my-plugin.admin.index`'s URL
- **AND** the Admin section's second-nav renders it linking to that URL, not to `plugins.page`

#### Scenario: Settings second-nav renders a plugin entry
- **WHEN** a plugin registers `RegisterPage::make($key)->route('my-plugin.settings.index')->placement('settings')->register()`
- **THEN** the Settings section's second-nav renders an entry for it alongside the built-in Profile/API Keys items (now themselves `pluginPages` entries registered by core), linking to that URL

#### Scenario: A plugin's own group renders as one main-rail entry with its own second-nav
- **WHEN** a plugin registers a `RegisterPageGroup` and two member pages as in the WhatsApp example
- **THEN** the main rail shows one "WhatsApp" entry (not two), sorted among other entries by `order`
- **AND** activating it renders that group's own second-nav with both member pages, independently of the Settings/Admin second-navs
