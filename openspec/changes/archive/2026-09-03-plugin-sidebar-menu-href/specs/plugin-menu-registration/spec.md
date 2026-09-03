## ADDED Requirements

### Requirement: A plugin registers a main-sidebar entry without editing boilerplate code
A plugin SHALL be able to add an entry to the application's main sidebar exclusively through `VitaminD\PluginSdk\RegisterPage`, called from the plugin's own `boot()`. No sidebar entry SHALL require a change to any file under `resources/js/components/` on the plugin's part.

#### Scenario: Plugin registers a page and it appears in the sidebar
- **WHEN** a plugin calls `RegisterPage::make($key)->title(...)->icon(...)->register()` (in either tabs mode or custom-link mode, see below) during its `boot()`
- **AND** the entry is not `adminOnly()` or the current user is an admin
- **THEN** the entry appears in the rendered main sidebar without any change to `resources/js/components/app-sidebar.tsx` or any other boilerplate file

#### Scenario: Plugin author attempts to add a sidebar entry by editing app-sidebar.tsx
- **WHEN** a plugin needs a sidebar entry
- **THEN** editing `resources/js/components/app-sidebar.tsx` (or any other boilerplate component) is never a required or documented step to achieve it

### Requirement: `RegisterPage` supports a custom-link registration mode alongside the existing tabs mode
`RegisterPage` SHALL support two mutually exclusive registration modes: **tabs mode**, where `tabs()` supplies one or more `RegisterDataTable` definitions rendered by the generic `plugins.page` route, and **custom-link mode**, where `route(string $name, array $params = [])` or `href(string $url)` supplies a link to a route the plugin owns. `register()` SHALL throw if both `tabs()` and `route()`/`href()` are set on the same instance.

#### Scenario: Plugin registers in tabs mode (unchanged existing behavior)
- **WHEN** a plugin calls `RegisterPage::make($key)->tabs([...])->register()`, exactly as `vitamind-todo-plugin` already does
- **THEN** the entry's link resolves to `route('plugins.page', $key)`, identical to current behavior
- **AND** no code change is required in the plugin to keep this working

#### Scenario: Plugin registers in custom-link mode
- **WHEN** a plugin calls `RegisterPage::make($key)->route('archive.index')->register()`
- **THEN** the entry's link resolves to the URL produced by Laravel's `route('archive.index')`, not to `plugins.page`
- **AND** navigating that sidebar entry loads the plugin's own route and its own Inertia page, not the generic `dynamic-page` CRUD screen

#### Scenario: Plugin registers with a pre-resolved href
- **WHEN** a plugin calls `RegisterPage::make($key)->href('/archive')->register()`
- **THEN** the entry's link is exactly that string, with no route-name resolution performed

#### Scenario: Plugin mistakenly combines both modes
- **WHEN** a plugin calls both `->tabs([...])` and `->route(...)` (or `->href(...)`) on the same `RegisterPage` instance before calling `register()`
- **THEN** `register()` throws an `InvalidArgumentException` identifying the conflicting call, rather than silently choosing one mode

### Requirement: The `pluginPages` Inertia prop carries a pre-resolved link for every entry
`RegisterPage::toArray()`, as serialized into the `pluginPages` Inertia prop by `HandleInertiaRequests`, SHALL include an `href` field holding the fully resolved link for that entry (in either registration mode), computed at request time rather than at `register()` time.

#### Scenario: Route registered by another provider is still resolvable
- **WHEN** a plugin registers a custom-link page in `boot()` pointing at a route name that is fully registered by the time any request reaches `HandleInertiaRequests` (regardless of provider boot order between plugins)
- **THEN** `toArray()`'s `href` resolves correctly for every request, because resolution happens per-request, not during `boot()`

#### Scenario: Frontend renders any entry generically
- **WHEN** `resources/js/components/app-sidebar.tsx` renders an item from `pluginPages`
- **THEN** it uses that entry's `title`, `icon`, `admin_only`, and `href` fields directly, with no branching on which registration mode produced the entry

### Requirement: A plugin chooses which nav its entry appears in via an explicit or implied placement
`RegisterPage` SHALL support an optional `placement(string $target): self` method accepting `'main'`, `'admin'`, or `'settings'`; `register()` SHALL throw `InvalidArgumentException` for any other value. When `placement()` is not called, the resolved placement SHALL be `'admin'` if `adminOnly(true)` was set, otherwise `'main'` — identical to the filtering every existing consumer already performs. `adminOnly()` SHALL remain an independent visibility gate (enforced on the underlying `plugins.page` route) regardless of which placement is resolved or set.

#### Scenario: Placement defaults to today's implicit rule when unset
- **WHEN** a plugin registers a page without calling `placement()`, exactly as `vitamind-todo-plugin` and `vitamind-archive-plugin` already do
- **THEN** the resolved placement is `'main'` if the page is not `adminOnly()`, or `'admin'` if it is — identical to current behavior
- **AND** no code change is required in either plugin to keep this working

#### Scenario: Plugin explicitly targets the Settings second-nav
- **WHEN** a plugin calls `RegisterPage::make($key)->placement('settings')->register()`
- **THEN** the resolved placement is `'settings'`, regardless of the page's `adminOnly()` value
- **AND** the entry appears in the Settings second-nav, not the main sidebar or the Admin second-nav

#### Scenario: Plugin passes an invalid placement value
- **WHEN** a plugin calls `->placement('sidebar')` (or any string outside `main`/`admin`/`settings`)
- **THEN** `register()` throws an `InvalidArgumentException` rather than silently resolving to no placement

### Requirement: All three plugin-aware navs consume `pluginPages` generically, filtered by resolved placement
`resources/js/components/app-sidebar.tsx` (main), `resources/js/layouts/admin/layout.tsx` (admin), and `resources/js/layouts/settings/layout.tsx` (settings) SHALL each render `pluginPages` entries whose resolved `placement` matches their own destination, using that entry's `href` field directly. None of the three SHALL hardcode `route('plugins.page', p.key)` or branch on `admin_only` to decide placement.

#### Scenario: Admin second-nav renders a custom-link admin-only entry correctly
- **WHEN** a plugin registers `RegisterPage::make($key)->route('my-plugin.admin.index')->adminOnly(true)->register()`
- **THEN** the entry's resolved placement is `'admin'` (implied default) and its `href` resolves to `my-plugin.admin.index`'s URL
- **AND** `admin/layout.tsx` renders it linking to that URL, not to `plugins.page`

#### Scenario: Settings second-nav renders a plugin entry
- **WHEN** a plugin registers `RegisterPage::make($key)->route('my-plugin.settings.index')->placement('settings')->register()`
- **THEN** `settings/layout.tsx` renders an entry for it alongside the built-in Profile/Workspaces/API Keys items, linking to that URL

### Requirement: A plugin can supply a custom description for its `dynamic-page` heading
`RegisterPage` SHALL support an optional `description(string $description): self` method. When set, the resulting `plugins/dynamic-page` screen's heading SHALL show that description instead of the built-in default text. When not set, the heading SHALL show the same default text it shows today, unchanged.

#### Scenario: Plugin sets a custom description
- **WHEN** a plugin calls `RegisterPage::make($key)->description('Manage your uploaded folders and files.')->register()`
- **THEN** navigating to that page's `plugins/dynamic-page` screen shows that exact text as the heading description

#### Scenario: Plugin does not set a description (unchanged default)
- **WHEN** a plugin calls `RegisterPage::make($key)->tabs([...])->register()` without calling `description()`, exactly as `vitamind-todo-plugin` already does
- **THEN** the heading description on `plugins/dynamic-page` reads "Manage plugin database records for {title}.", identical to current behavior
- **AND** no code change is required in the plugin to keep this working
