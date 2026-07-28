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
5. Delete the original `app/Plugins/MyPlugin/` folder — `DiscoverPlugins` will stop
   finding it there and pick it up from `vendor/{vendor}/{package}` (if required
   via Composer) or `storage/plugins/{package}` (if installed via
   `php artisan plugin:install-github` or the admin UI) instead.
6. Run `composer require your-vendor/my-plugin` (or `plugin:install-github`) in
   any project that wants to use it.

There's no in-place "convert" command — the `src/` restructuring only happens
at the point you decide to publish, which is why local plugins don't carry that
overhead until it's actually needed.
