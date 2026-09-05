# Plugin Identity: `pluginDetails()`

Every VitaminD plugin — regardless of which of the two plugin systems it
belongs to — declares its own identity through one required method,
`pluginDetails()`. This page covers that contract on its own; see
[`role-registration.md`](role-registration.md) for the one thing that
currently *consumes* it (`RegisterRole`).

## The two plugin systems, one identity contract

VitaminD has two structurally different kinds of plugin:

| | Base class | Distribution | Examples |
|---|---|---|---|
| Composer-package plugin | `VitaminD\PluginSdk\PluginBase` (extends Laravel's `ServiceProvider`) | Always-installed dependency, loaded via Composer auto-discovery | `vitamind-workspace-plugin`, `vitamind-archive-plugin`, `vitamind-realtime-plugin` |
| Dynamically-installed plugin | `VitaminD\PluginSdk\AbstractPlugin` | Local (`app/Plugins/`), GitHub (`storage/plugins/`), or Composer (`vendor/vitamind/`), toggled on/off at runtime | `vitamind-todo-plugin`, `App\Plugins\HelloWorld` |

These two have no common ancestor — one is a Laravel `ServiceProvider`, the
other implements VitaminD's own `PluginInterface` (`boot`/`enable`/`disable`/
`install`/`uninstall`). Both, however, require the same one-method contract:

```php
namespace VitaminD\PluginSdk\Interfaces;

interface HasPluginDetails
{
    /**
     * @return array{key: string, name: string, description: string}
     */
    public function pluginDetails(): array;
}
```

- **`key`** — a short, stable, unique-to-your-plugin identifier (e.g.
  `workspace`, `warehouse`, `todo-plugin`). Used to scope anything the
  plugin registers under its own name — today, that's `RegisterRole` (see
  [`role-registration.md`](role-registration.md)).
- **`name`** — a human-readable display name.
- **`description`** — a one-line summary.

`pluginDetails()` is `abstract` on both `AbstractPlugin` and `PluginBase` — a
plugin class that doesn't implement it cannot be instantiated. There is no
default and no fallback derived from the class's namespace or folder name:
identity is always explicit, never guessed.

## Declaring it

```php
// Dynamically-installed plugin (local/GitHub/Composer)
namespace App\Plugins\Warehouse;

use VitaminD\PluginSdk\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function pluginDetails(): array
    {
        return [
            'key' => 'warehouse',
            'name' => 'Warehouse Plugin',
            'description' => 'Inventory management for VitaminD.',
        ];
    }

    public function boot(): void { /* ... */ }
}
```

```php
// Composer-package plugin (a first-party or third-party ServiceProvider)
namespace Acme\WarehousePlugin\Providers;

use VitaminD\PluginSdk\PluginBase;

class WarehouseServiceProvider extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'key' => 'warehouse',
            'name' => 'Warehouse Plugin',
            'description' => 'Inventory management for VitaminD.',
        ];
    }

    public function register(): void { /* ... */ }
    public function boot(): void { /* ... */ }
}
```

Both base classes `use VitaminD\PluginSdk\Concerns\RegistersOwnRole`, which
reads `pluginDetails()['key']` for you — see
[`role-registration.md`](role-registration.md#declaring-a-role-registerrole)
for `registerRole()`, the helper this makes available.

## What replaced

Before this contract existed, `AbstractPlugin` exposed `$name`/`$description`
properties and `getName()`/`getDescription()` methods, and
`PluginBase`/Composer-package `ServiceProvider`s had **no identity concept at
all**. `pluginDetails()` consolidates all of that into one method — if
you're updating an older plugin, replace:

```php
protected string $name = 'My Plugin';
protected string $description = 'What this plugin does.';
```

with:

```php
public function pluginDetails(): array
{
    return [
        'key' => 'my-plugin',
        'name' => 'My Plugin',
        'description' => 'What this plugin does.',
    ];
}
```

`vitamind-core`'s dynamic-plugin install/enable lifecycle
(`Actions/Plugins/InstallPlugin.php`, `EnablePlugin.php`) reads the stored
`Plugin` record's display `name`/`description` from here too — nothing
changes about what an admin sees in the plugin list, only where that text
comes from.

## Testing

See `dev-packages/vitamind-plugin-sdk/tests/AbstractPluginTest.php` and
`PluginBaseTest.php` for the pattern: an anonymous class extending the
relevant base, implementing `pluginDetails()`, asserting both the returned
array and that `registerRole()` scopes correctly under its declared `key`.
