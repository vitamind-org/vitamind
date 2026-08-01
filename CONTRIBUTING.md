# Contributing / Building Plugins

This covers building a plugin for Vitamin-D. For contributing to Vitamin-D
itself (the boilerplate or the `vitamind/core`/`workspace-plugin`/`plugin-sdk`
packages), the same test-suite/PR expectations below apply — there's just no
`Plugin.php` involved.

## Quick start

Every plugin — local, GitHub, or Composer — is a class extending
`VitaminD\PluginSdk\AbstractPlugin`:

```php
<?php

namespace App\Plugins\MyPlugin;

use VitaminD\PluginSdk\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    protected string $name = 'My Plugin';
    protected string $description = 'What this plugin does.';
    protected array $dependencies = []; // FQCNs of other plugins' Plugin classes, if any

    public function boot(): void
    {
        // Runs on every request while the plugin is enabled.
        // Register pages, data tables, routes, views, etc. here.
    }

    public function install(): void   { /* one-time setup, e.g. seed data */ }
    public function uninstall(): void { /* clean up what install() created */ }
    public function enable(): void    { /* runs each time the plugin is turned on */ }
    public function disable(): void   { /* runs each time the plugin is turned off */ }
}
```

Where that file lives depends on how the plugin will be distributed — see
[`docs/local-plugins.md`](docs/local-plugins.md) for the full breakdown:

- **Local** (app-specific, not shared): `app/Plugins/{Name}/Plugin.php`, no `src/`.
- **GitHub / Composer** (a real package): `src/Plugin.php`, with a
  `composer.json` PSR-4 entry so `DiscoverPlugins` can resolve the namespace.

## Registering UI

Most plugins register a metadata-driven admin page in `boot()`:

```php
use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\Column;

RegisterPage::make('my-plugin')
    ->title('My Plugin')
    ->icon('package')
    ->adminOnly(false)
    ->tabs([
        'items' => RegisterDataTable::make('items')
            ->model(MyModel::class)
            ->columns([
                Column::text('name')->label('Name')->sortable()->searchable(),
            ]),
    ])
    ->register();
```

This is rendered by the single generic React page
(`resources/js/pages/plugins/dynamic-page.tsx`) — you don't write any frontend
code for standard CRUD tables/forms. See `app/Plugins/MockProduct/` for a
complete worked example (forms, filters, relations, a custom option provider).

## Testing your plugin

- Put plugin migrations in `database/migrations/` relative to the plugin root
  and register them from `boot()`:
  ```php
  if (app()->runningInConsole()) {
      app('migrator')->path(__DIR__.'/database/migrations');
  }
  ```
- Write PHPUnit tests the same way the boilerplate does (see
  `tests/Feature/DynamicPageCrudTest.php` for a CRUD example against a local
  plugin). If your test needs the plugin actually booted (registered pages,
  routes), extend `Tests\TestCase` with `RefreshDatabase` — its `setUp()`
  already re-runs plugin discovery/boot against the fresh schema and flushes
  `RegisterPage`'s registry between tests, so plugin state doesn't leak across
  test cases.
- Run the full suite before opening a PR: `php artisan test`. For frontend
  changes, also run `npm run types` and `npm run build`.

## Pull requests

- Keep changes scoped — a plugin feature and an unrelated refactor should be
  separate PRs.
- Explain the *why* in the PR description; the diff already shows the *what*.
- If you're changing a package under `dev-packages/`, note which downstream
  consumers (the boilerplate itself, other plugins) you verified still pass.
