# Registering a Plugin's Menu Entry

A plugin adds itself to the app's navigation exclusively through
`VitaminD\PluginSdk\RegisterPage` (and, for grouped entries,
`VitaminD\PluginSdk\RegisterPageGroup`), called from the plugin's own
`boot()`. There is no other sanctioned mechanism.

**Plugins never edit `resources/js/components/app-sidebar.tsx` or
`resources/js/layouts/section/layout.tsx` to add themselves.** Those files
render `pluginPages` generically — every entry any plugin registers already
reaches them without any code change on the plugin's side. If you find
yourself about to edit one of those files to add a menu item, stop: you're
looking for `RegisterPage`/`RegisterPageGroup` instead. (There's no more
`settings/layout.tsx` or `admin/layout.tsx` — Settings and Admin are
themselves just two more groups, rendered by the same generic
`section/layout.tsx` any plugin's own group uses; see
["Grouping pages under one sidebar entry"](#grouping-pages-under-one-sidebar-entry-registerpagegroup)
below.)

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

Every plugin-aware nav region renders `pluginPages` generically: the **main
sidebar rail** (`app-sidebar.tsx`, one icon per ungrouped entry or per
group), the **sidebar footer** (also `app-sidebar.tsx`), and any **group's
second-nav** (`layouts/section/layout.tsx`, shared by every group — Settings,
Admin, and a plugin's own). Which one an entry reaches is chosen by its
resolved `placement`.

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

Call `placement()` explicitly to reach **Settings**, which nothing implies
automatically:

```php
RegisterPage::make('my-plugin-settings')
    ->title('My Plugin')
    ->icon('sliders')
    ->route('my-plugin.settings.index')
    ->placement('settings')
    ->adminOnly(false)
    ->register();
```

`placement()` accepts `'main'`, `'admin'`, `'settings'`, or `'footer'` —
anything else throws `InvalidArgumentException`. It can also override the
`adminOnly()`-implied default for `main`/`admin` if you ever need to (e.g.
an admin-only entry that should still show in the main sidebar), though
that's an unusual case — most plugins never need to call it at all.

Under the hood, `placement('settings')` and `placement('admin')` are sugar
for joining the two built-in `settings`/`admin` groups core registers (see
below) — you don't need to know that to use them, and nothing changes about
how they behave. `placement('footer')` is different: it's flat, like
`main` — a footer entry never has a second-nav of its own (see
["Footer entries and external links"](#footer-entries-and-external-links)).

## Grouping pages under one sidebar entry: `RegisterPageGroup`

A plugin with several related pages doesn't have to give each one its own
main-sidebar icon. `RegisterPageGroup` collapses any number of `RegisterPage`
entries under **one** main-sidebar entry that owns its own second-nav —
exactly the same shape Settings and Admin already have, just plugin-defined.

Say a WhatsApp plugin has two pages, "My Numbers" and "Contacts". Register
the group once, then have each page join it via `group()`:

```php
use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterPageGroup;

RegisterPageGroup::make('whatsapp')
    ->title('WhatsApp')
    ->icon('message-circle')
    ->register();

RegisterPage::make('whatsapp-numbers')
    ->title('My Numbers')
    ->icon('phone')
    ->route('whatsapp.numbers.index')
    ->group('whatsapp')
    ->adminOnly(false)
    ->register();

RegisterPage::make('whatsapp-contacts')
    ->title('Contacts')
    ->icon('users')
    ->route('whatsapp.contacts.index')
    ->group('whatsapp')
    ->adminOnly(false)
    ->register();
```

This renders one "WhatsApp" icon in the main sidebar — never two — whose
link resolves to the first-registered member ("My Numbers" here; see
["Ordering entries with `order()`"](#ordering-entries-with-order) if you
need to control which member wins that slot). Activating it opens a
second-nav titled "WhatsApp" listing both "My Numbers" and "Contacts".

A page's own frontend component (in custom-link mode) renders that
second-nav by using the same shared layout Settings/Admin use, passing its
own group key:

```tsx
import SectionLayout from '@/layouts/section/layout';

export default function WhatsappNumbers() {
  return (
    <SectionLayout title="WhatsApp" groupKey="whatsapp">
      {/* page content */}
    </SectionLayout>
  );
}
```

Groups only ever render in the **main** sidebar — there's no way to nest a
group inside another group's second-nav, or inside the footer. A page
referencing a `group()` key with no matching `RegisterPageGroup::register()`
call throws `InvalidArgumentException` once the registry is resolved (i.e.
on the next request, not at `boot()` time — the same lazy-resolution timing
`route()` uses).

`RegisterPageGroup` supports `title()`, `icon()`, `hidden()`, and `order()`
— the same methods described below for `RegisterPage`, with the same
meaning: hide the whole group (and every page in it) or control where it
sorts among other main-sidebar entries.

Nested sub-menus **within** a single second-nav (one entry expanding into
its own child items, as opposed to a whole separate group) aren't supported
yet — that's a deliberately separate, not-yet-built concern.

## Controlling visibility with `hidden()`

Both `RegisterPage` and `RegisterPageGroup` support `hidden(Closure $callback)`
— a no-arg closure, evaluated lazily once per request (the same timing as
`route()`), that decides whether the entry is visible *for this request*:

```php
RegisterPage::make('whatsapp-numbers')
    ->title('My Numbers')
    ->route('whatsapp.numbers.index')
    ->hidden(fn () => ! auth()->user()->hasFeature('whatsapp-numbers'))
    ->register();
```

Call whatever helpers you need inside the closure — `auth()->user()`,
`request()`, `Route::has(...)`, a feature-flag check — there's no special
argument passed in. When the closure returns `true`, the entry is
**excluded entirely** from the `pluginPages` Inertia prop for that request —
not merely flagged for the client to skip rendering. This matters for
anything permission-gated: a client-side-only hide would still leak the
entry's title and `href` to a user who isn't allowed to see it; excluding it
server-side doesn't.

Hiding a `RegisterPageGroup` hides every page that joined it too — there
would be no nav path left to reach them otherwise:

```php
RegisterPageGroup::make('whatsapp')
    ->title('WhatsApp')
    ->hidden(fn () => ! auth()->user()->can('viewWhatsappPlugin'))
    ->register();
```

An entry with no `hidden()` call is always visible — this is purely opt-in.

## Ordering entries with `order()`

Both `RegisterPage` and `RegisterPageGroup` support `order(int $order)`.
Entries sharing the same nav region — the main rail, the footer, or one
group's second-nav — render sorted ascending by `order`, ties broken by
registration order. An entry that never calls `order()` defaults to `0`, so
plugins that don't care about ordering behave exactly as before: entries
interleave in registration order.

Core reserves a **high `order` band** (roughly `1000`+) for the built-in
Settings/Admin groups and the sidebar footer, so they keep sorting after a
typical plugin's default-`order()` main-sidebar entries. You don't need to
do anything to get this — it's already how core registers them — but if you
ever want your own entry to sort even later than Settings/Admin, use an
`order()` value higher than `1000`.

## Footer entries and external links

`placement('footer')` puts an entry in the sidebar footer instead of the
main rail — a flat region, like `main`: a footer entry never gets a
second-nav or joins a group. Combine it with `external(bool $external = true)`
for an outbound link (opened as a plain anchor rather than an Inertia
navigation):

```php
RegisterPage::make('my-plugin-docs')
    ->title('Docs')
    ->icon('book-open')
    ->href('https://example.com/my-plugin/docs')
    ->placement('footer')
    ->external(true)
    ->register();
```

`external()` defaults to `false` — omit it for an internal footer link (a
route your plugin owns, rendered as a normal Inertia navigation).

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
| `placement(string)` | no | `'main'` \| `'admin'` \| `'settings'` \| `'footer'`. Defaults from `adminOnly()` if omitted. |
| `adminOnly(bool = true)` | no (defaults `true`) | Gates both visibility (enforced by `PluginPageController` on the underlying route) and, absent an explicit `placement()`, which nav the entry defaults into. |
| `group(string)` | no | Joins a `RegisterPageGroup`; validated against its registry per-request. |
| `hidden(Closure)` | no | No-arg closure; `true` excludes the entry from `pluginPages` entirely for that request. |
| `order(int)` | no (defaults `0`) | Sort key within whichever region the entry renders in. |
| `external(bool = true)` | no (defaults `false`) | Renders as a plain outbound link — meaningful for `placement('footer')` entries. |

Call `register()` last to actually add the page to the registry — nothing
before that point is visible anywhere.

`RegisterPageGroup` has its own, smaller builder: `make(string $key)`,
`title(string)`, `icon(string)`, `hidden(Closure)`, `order(int)`, and
`register()` — see
["Grouping pages under one sidebar entry"](#grouping-pages-under-one-sidebar-entry-registerpagegroup).

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
`RegisterPageGroup` gets the same flush-then-restore treatment, but
unconditionally — a group carries no `tabs()`-equivalent database-bound
closures, so replaying it is always safe.
