# Registering a Plugin's Menu Entry

A plugin adds itself to the app's navigation exclusively through
`VitaminD\PluginSdk\RegisterPage`, called from the plugin's own `boot()`.
There is no other sanctioned mechanism.

**Plugins never edit `resources/js/components/app-sidebar.tsx`,
`resources/js/layouts/admin/layout.tsx`, or
`resources/js/layouts/settings/layout.tsx` to add themselves.** Those three
files render `pluginPages` generically — every entry any plugin registers
already reaches them without any code change on the plugin's side. If you
find yourself about to edit one of those files to add a menu item, stop:
you're looking for `RegisterPage` instead.

## The two registration modes

`RegisterPage` supports two mutually exclusive modes. Pick one per page —
calling both `tabs()` and `route()`/`href()` on the same `RegisterPage`
throws an `InvalidArgumentException` at `register()` time.

### Tabs mode — generic CRUD, no frontend code

Use this when the plugin just needs a paginated create/edit/delete screen
over one or more Eloquent models. It needs zero frontend code: the built-in
`plugins/dynamic-page` screen (served by `PluginPageController` at the
`plugins.page` route) renders it for you from the `RegisterDataTable`
config.

```php
use VitaminD\PluginSdk\Column;
use VitaminD\PluginSdk\DTOs\DynamicField;
use VitaminD\PluginSdk\DTOs\DynamicForm;
use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\RegisterPage;

$form = DynamicForm::make([
    DynamicField::make('title')->text()->label('Title')
        ->rules(['required', 'string', 'max:255']),
    DynamicField::make('is_done')->checkbox()->label('Done')
        ->default(false)->rules(['required', 'boolean']),
]);

RegisterPage::make('todo-plugin')
    ->title('Todo Plugin')
    ->icon('check-square')
    ->tabs([
        'todos' => RegisterDataTable::make('todos')
            ->model(Todo::class)
            ->columns([
                Column::text('title')->label('Title')->sortable()->searchable(),
                Column::badge('is_done', [1 => 'Done', 0 => 'Pending'])->label('Status'),
            ])
            ->form($form),
    ])
    ->adminOnly(false)
    ->register();
```

This is the full pattern used by `vitamind-todo-plugin`
(`dev-packages/vitamind-todo-plugin/src/Plugin.php`) — copy it as a
starting point for any plugin whose data is a flat, single-model list.

### Custom-link mode — the plugin owns its own route and page

Use this when the plugin ships its own routes and its own Inertia frontend
(a `@plugin/{name}/...` page, per
[`docs/local-plugins.md`'s Distributed plugin frontend](../local-plugins.md#distributed-plugin-frontend))
instead of the generic CRUD screen — e.g. the data isn't a flat model list,
or the UI needs custom interaction the `dynamic-page` system can't express.

```php
use VitaminD\PluginSdk\RegisterPage;

RegisterPage::make('archive')
    ->title('Archive')
    ->icon('archive')
    ->route('archive.index')   // a route the plugin registered itself
    ->adminOnly(false)
    ->register();
```

`route(string $name, array $params = [])` resolves lazily via Laravel's
`route()` helper at serialize time (once per request, inside
`HandleInertiaRequests`) — not at `boot()` time — so registration order
between plugins never matters, only that the named route exists by the
time a request is actually served.

If the destination isn't a named route (rare — e.g. a static path), use
`href(string $url)` instead for a pre-resolved link, verbatim, with no
route resolution:

```php
RegisterPage::make('archive')->title('Archive')->icon('archive')
    ->href('/archive')->adminOnly(false)->register();
```

See `dev-packages/vitamind-archive-plugin/src/Providers/ArchiveServiceProvider.php`
(`registerMenu()`) for the live example this pattern was built for: a
folder/file hierarchy with per-item visibility, which has no meaningful
`RegisterDataTable` shape.

## Choosing where the entry appears: `placement()`

Three navs read `pluginPages`: the **main sidebar**
(`app-sidebar.tsx`), the **Admin second-nav** (`admin/layout.tsx`, visible
under `/admin`), and the **Settings second-nav** (`settings/layout.tsx`,
visible under `/settings`). Each entry appears in exactly one, chosen by
its resolved `placement`.

By default — without calling `placement()` at all — the destination is
implied by `adminOnly()`, exactly the rule every plugin already followed
before `placement()` existed:

| `adminOnly()` | Resolved placement (default) |
|---|---|
| `false` (or unset) | `main` |
| `true` | `admin` |

This means `vitamind-todo-plugin` and `vitamind-archive-plugin`'s
registrations above need no `placement()` call at all — `main` is already
what they get.

Call `placement()` explicitly only to reach the **third** destination,
Settings, which nothing implies automatically:

```php
RegisterPage::make('my-plugin-settings')
    ->title('My Plugin')
    ->icon('sliders')
    ->route('my-plugin.settings.index')
    ->placement('settings')
    ->adminOnly(false)
    ->register();
```

`placement()` accepts exactly `'main'`, `'admin'`, or `'settings'` —
anything else throws `InvalidArgumentException`. It can also override the
`adminOnly()`-implied default for `main`/`admin` if you ever need to (e.g.
an admin-only entry that should still show in the main sidebar), though
that's an unusual case — most plugins never need to call it at all.

## Customizing the heading description

Tabs-mode pages render on `plugins/dynamic-page`, whose heading defaults to
`Manage plugin database records for {title}.`. Override it with
`description()`:

```php
RegisterPage::make('archive-like-plugin')
    ->title('Widgets')
    ->description('Manage your uploaded widgets.')
    ->tabs([...])
    ->register();
```

Optional — omit it and the default text above is used, unchanged.

## Full builder reference

| Method | Required? | Effect |
|---|---|---|
| `title(string)` | yes | Label shown in the nav. |
| `icon(string)` | yes | A kebab-case `lucide-react` icon name (e.g. `check-square` → `CheckSquareIcon`), resolved client-side; falls back to a generic package icon if unmatched. |
| `tabs(array)` | one of `tabs()`/`route()`/`href()` | Map of tab key → `RegisterDataTable`. Renders via the generic `plugins/dynamic-page` screen. |
| `route(string, array)` | one of `tabs()`/`route()`/`href()` | Named route the plugin owns; resolved per-request. |
| `href(string)` | one of `tabs()`/`route()`/`href()` | Pre-resolved URL, used verbatim. |
| `description(string)` | no | Overrides the `dynamic-page` heading description (tabs mode only). |
| `placement(string)` | no | `'main'` \| `'admin'` \| `'settings'`. Defaults from `adminOnly()` if omitted. |
| `adminOnly(bool = true)` | no (defaults `true`) | Gates both visibility (enforced by `PluginPageController` on the underlying route) and, absent an explicit `placement()`, which nav the entry defaults into. |

Call `register()` last to actually add the page to the registry — nothing
before that point is visible anywhere.

## Testing a page registration

Feature tests that assert on `pluginPages` should hit a real route through
the full HTTP stack and read the Inertia response, rather than only
constructing a `RegisterPage` in isolation — that's what actually proves
the entry reaches the frontend. See
`dev-packages/vitamind-archive-plugin/tests/Feature/ArchiveMenuRegistrationTest.php`
for the pattern:

```php
$response = $this->actingAs($user)->get('/archive');

$response->assertInertia(fn ($page) => $page
    ->where('pluginPages', fn ($pages) => $pages->contains(
        fn ($p) => $p['key'] === 'archive'
            && $p['href'] === route('archive.index')
            && $p['placement'] === 'main'
    ))
);
```

If your plugin's own `boot()` is a plain `Illuminate\Support\ServiceProvider`
registered via Composer's package auto-discovery (like `ArchiveServiceProvider`
— not a `VitaminD\PluginSdk\AbstractPlugin`-based `Plugin` class driven by
Core's install/enable registry), be aware that `tests/TestCase.php`
specifically preserves that kind of "always-on" registration across its
per-test `RegisterPage` flush, but only for pages with no `tabs()` (see
that file's docblock). A `tabs()`-mode page always needs
`RefreshDatabase` in the test to be re-registered correctly.
