# Local Plugins

Plugins that live in `app/Plugins/` are **application code**, not distributable
packages. They're discovered automatically by
`VitaminD\Core\Actions\Plugins\DiscoverPlugins` on every boot — no registration
step needed, just create the folder.

## Folder structure (no `src/`)

```
app/Plugins/MyPlugin/
├── Plugin.php              ← required, must extend AbstractPlugin
├── Models/
├── Controllers/
├── Resources/
├── routes/
└── database/
    └── migrations/
```

`Plugin.php` sits at the **root** of the plugin folder, not inside a `src/`
subdirectory. Namespace is always `App\Plugins\{FolderName}`, resolved by folder
name — no `composer.json` or PSR-4 declaration required, since `App\Plugins\` is
already mapped to `app/Plugins/` in the boilerplate's own `composer.json`.

```php
<?php

namespace App\Plugins\MyPlugin;

use VitaminD\PluginSdk\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    protected string $name = 'My Plugin';
    protected string $description = 'What this plugin does.';

    public function boot(): void { /* runs on every request when enabled */ }
    public function install(): void { /* one-time setup */ }
    public function enable(): void {}
    public function disable(): void {}
    public function uninstall(): void {}
}
```

## Why no `src/` here?

Local plugins are part of your application, not a package someone else installs.
Adding a `src/` layer would just be an extra folder to navigate for code that's
never going to be autoloaded any other way. It also keeps local plugins visually
consistent with the rest of `app/` (`app/Models/`, `app/Http/`, …), which already
has no `src/`.

**GitHub and Composer plugins are different.** Those *are* distributed packages,
so they follow the standard PSR-4 convention instead:

```
storage/plugins/some-plugin/      (GitHub)     vendor/vitamind/some-plugin/   (Composer)
├── src/
│   ├── Plugin.php
│   └── ...
├── database/migrations/
└── composer.json                              ← namespace read from here
```

For those two sources, `DiscoverPlugins` reads the PSR-4 `autoload.psr-4` map
from `composer.json` to resolve the `Plugin` class — it doesn't guess the
namespace from the folder name, since the folder name for a GitHub/Composer
plugin isn't guaranteed to match its namespace.

For a live example of this `src/` layout, see this repo's own `vitamind-core`
and `vitamind-workspace-plugin` packages. Their source currently lives under
`dev-packages/` — that's *not* a general plugin location, just this monorepo's
temporary development home for packages that are still being built out (wired
into `vendor/vitamind/*` via a Composer path repository while in development).
Once published, they're consumed exactly like any other Composer dependency, at
`vendor/vitamind/{package}/`.

## Menu registration

A plugin adds itself to the app's navigation (main sidebar, Admin
second-nav, or Settings second-nav) through `RegisterPage`, called from its
own `boot()` — never by editing `app-sidebar.tsx`, `admin/layout.tsx`, or
`settings/layout.tsx` directly. See
[`docs/plugin-development/menu-registration.md`](plugin-development/menu-registration.md)
for the full API (`tabs()` vs. `route()`/`href()`, `placement()`,
`description()`).

## Frontend

Local plugins have no dedicated frontend mechanism — there is no alias, no
glob, nothing plugin-specific to configure. If a Local plugin needs a custom
Inertia page or component, write it directly under the host's
`resources/js/pages/...` (or wherever it fits among the host's existing
`resources/js` conventions), exactly as you would for any other host
feature. It gets compiled as part of the host's normal Vite build — no
extra step, no extra alias.

This is a deliberate consequence of Local plugins being application code:
since they never leave this repo, there's no boundary for a dedicated
frontend mechanism to enforce. Contrast this with GitHub and Composer
plugins below, which *are* distributed and do have one (see
[`@plugin/{name}` and `usePlugin()`](#distributed-plugin-frontend) further
down).

For the CRUD-style admin UI most Local plugins actually need, prefer the
backend-driven `dynamic-page` system (`RegisterPage`, `RegisterDataTable`,
`Column`, `Filter` in `vitamind-plugin-sdk`) over a custom page — it needs
no frontend code at all, distributed or otherwise.

## Distributed plugin frontend

Composer plugins can ship custom Inertia pages, hooks, and components,
resolved through a build-time `@plugin/{kebab-name}` alias
(`vite.config.ts`'s `getPluginAliases()`, scanning `vendor/vitamind/*`) —
compiled together with the host in the same Vite build, so there's no
separate build step and no risk of bundling a second copy of React.

GitHub plugins do **not** get this — `getPluginAliases()`, the page glob in
`app.tsx`, `usePlugin()`, the Tailwind `@source`, and the Blade manifest
lookup all scan `vendor/vitamind/*` only, never `storage/plugins/*` (where
GitHub plugins actually install). This isn't a timing issue a rebuild would
fix — `storage/plugins` isn't a scanned root at all. GitHub plugins are
limited to the `dynamic-page` system above for their UI until that's
addressed as its own piece of work.

Put frontend source under the plugin's own `resources/js/`, mirroring the
host's own layout:

```text
vendor/vitamind/my-plugin/          (or dev-packages/vitamind-my-plugin/
resources/js/                        in this monorepo's dev setup)
├── pages/
│   └── index.tsx                   → resolved as `@plugin/my-plugin/index`
├── hooks/
├── lib/
└── stores/
```

**Pages** (anything under `pages/`) are rendered from PHP with
`Inertia::render('@plugin/my-plugin/index', [...])`, resolved lazily by
`resources/js/app.tsx`'s `resolve()` — same code-splitting behavior as any
host page.

**Hooks and other non-page exports** (`hooks/`, `lib/`, `stores/`) are
consumed by host code through `usePlugin('my-plugin')`
(`resources/js/lib/use-plugin.ts`), never imported by their file path
directly:

```tsx
const { useMyHook } = usePlugin('my-plugin');
```

`usePlugin()` returns an empty object — never throws — if the plugin isn't
installed, so host code that optionally depends on a plugin (like
`resources/js/layouts/app/layout.tsx` does for `vitamind/realtime-plugin`)
should fall back to a no-op rather than assume the hook exists. Define the
type contract for what you expect from the plugin in your own host-side
types file (see `resources/js/types/realtime-plugin.ts` for an example) —
`import type` directly from `@plugin/{name}/...` doesn't type-check (the
alias is an ambient wildcard module, which can't carry precise named
types), and reaching into a plugin's internal types would recouple the
"stable interface" this indirection exists to avoid anyway.

Tailwind classes used in plugin components are picked up automatically
(`resources/css/app.css`'s `@source '../../vendor/vitamind';`) and
generate real CSS from the *host's* design tokens — plugins never ship
their own stylesheet.

React UI primitives (`Button`, `Dialog`, `Popover`, `Command`, etc.) and the
`cn()` class-merge helper are imported through `@vitamind/ui/*`, not the
host's `@/components/ui/*` — e.g. `import { Button } from
'@vitamind/ui/button'`, `import { cn } from '@vitamind/ui/cn'`. This keeps
plugin source from statically depending on the host's own `@/` namespace,
mirroring how `vitamind/plugin-sdk` is a leaf dependency on the backend
side rather than something plugins reach for through `vitamind/core`. Host
code uses the same `@vitamind/ui/*` alias for these primitives too, so
there's one name for the shared components regardless of which side is
importing them. **`@vitamind/ui` is a Vite/tsconfig alias today, not an
independently versioned npm package** — it resolves to the same physical
`resources/js/components/ui/` and `resources/js/lib/utils.ts` files, still
compiled together with the host in one Vite process. Per-plugin
`package.json` dependency declarations against a real `@vitamind/ui`
package are a deferred, conditional follow-up (see
`openspec/changes/add-per-plugin-npm-packages`), not something in place
yet.

When writing feature tests against a plugin page, pass `shouldExist: false`
to Inertia's testing assertion — `assertInertia(fn ($page) => $page
->component('@plugin/my-plugin/index', false))`. By default that assertion
also checks the component file exists on disk, but it only knows about
`resources/js/pages/` (`config('inertia.testing.ensure_pages_exist')`), not
the `@plugin/` alias — without the explicit `false` it fails with "Inertia
page component file [...] does not exist" even though the page renders
correctly.

See the note above: this is Composer-only today. A GitHub plugin needs
either a scanned `storage/plugins` root or a self-contained bundle
mechanism before it can use any of this — neither exists yet.

## Extracting a local plugin into a standalone package

Once a local plugin is generic enough to reuse across projects (or you want to
publish it for others), pull it out of `app/Plugins/` and restructure it as a
standalone package:

1. Create a new repository for the package (or, if you're contributing it back
   to this monorepo rather than publishing independently, a `dev-packages/{name}`
   folder wired up as a path repository the same way `vitamind-core` and
   `vitamind-workspace-plugin` are — see the root `composer.json`'s `repositories`
   block. This is specific to developing *within* this monorepo, not something a
   consumer of your plugin needs to know about).
2. Move the plugin's code into a `src/` subdirectory of the new package.
3. Add a `composer.json` declaring the PSR-4 autoload, e.g.:
   ```json
   {
       "name": "your-vendor/my-plugin",
       "autoload": {
           "psr-4": { "VitaminD\\Plugins\\YourVendor\\MyPlugin\\": "src/" }
       }
   }
   ```
4. Update the namespace in every moved file from `App\Plugins\MyPlugin\*` to the
   new PSR-4 prefix (e.g. `VitaminD\Plugins\YourVendor\MyPlugin\*`).
5. If the local plugin had any custom frontend (pages under
   `resources/js/pages/`, or hooks/components living anywhere else in the
   host's `resources/js/`), move that into a `resources/js/` folder inside
   the new package instead — see [Distributed plugin
   frontend](#distributed-plugin-frontend) above for the layout and how
   host code should consume it (`@plugin/{name}` for pages, `usePlugin()`
   for everything else). Update any host code that referenced the old
   `@/pages/...`/`@/hooks/...` path to go through those instead.
6. Delete the original `app/Plugins/MyPlugin/` folder — `DiscoverPlugins` will stop
   finding it there and pick it up from `vendor/{vendor}/{package}` (if required
   via Composer) or `storage/plugins/{package}` (if installed via
   `php artisan plugin:install-github` or the admin UI) instead.
7. Run `composer require your-vendor/my-plugin` (or `plugin:install-github`) in
   any project that wants to use it.

There's no in-place "convert" command — the `src/` restructuring only happens
at the point you decide to publish, which is why local plugins don't carry that
overhead until it's actually needed.
