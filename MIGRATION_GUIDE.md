# Migration Guide

This guide covers two scenarios:

1. [Installing `vitamind/core` in a fresh Laravel project](#installing-vitamindcore-in-a-fresh-laravel-project)
2. [Upgrading an existing pre-Phase-1 Vitamin-D project](#upgrading-a-pre-phase-1-project)

---

## Installing `vitamind/core` in a fresh Laravel project

`vitamind/core` is a standard Composer package — no special install command is
needed, but two things are specific to its current pre-release status:

1. **It isn't on Packagist yet.** Point Composer at the source directly with a
   [path repository](https://getcomposer.org/doc/05-repositories.md#path):

   ```json
   {
       "repositories": [
           { "type": "path", "url": "/path/to/vitamin-d/dev-packages/vitamind-core" },
           { "type": "path", "url": "/path/to/vitamin-d/dev-packages/vitamind-plugin-sdk" }
       ]
   }
   ```

   Or via the CLI:

   ```bash
   composer config repositories.vitamind-core path /path/to/vitamin-d/dev-packages/vitamind-core
   composer config repositories.vitamind-plugin-sdk path /path/to/vitamin-d/dev-packages/vitamind-plugin-sdk
   ```

   Once published, this step goes away — `composer require vitamind/core` will
   resolve from Packagist like any other package.

2. **It's `dev-*` stability.** Without a tagged release yet, Composer needs to
   be told dev packages are acceptable:

   ```bash
   composer config minimum-stability dev
   composer config prefer-stable true
   ```

Then require it:

```bash
composer require vitamind/core:@dev inertiajs/inertia-laravel
php artisan migrate
```

`CoreServiceProvider` is auto-discovered (no `bootstrap/providers.php` edit
needed), and it registers its own routes, migrations, and middleware.

**Required — create `App\Models\User`.** This step is not optional and not
specific to the workspace plugin: core's own controllers, policies, and
actions (registration, admin user management, API keys, 2FA) call methods
that only exist on `App\Models\User`, not on the bare package model. Every
installation needs it, even a single-tenant one with no plugins at all:

```php
<?php

namespace App\Models;

use VitaminD\Core\Models\User as CoreUser;

class User extends CoreUser
{
    //
}
```

**Also required — create `App\Models\PersonalAccessToken`.** Core registers
Sanctum against this exact class (`Sanctum::usePersonalAccessTokenModel(...)`
in `CoreServiceProvider`) so that created API tokens keep any app-layer
behavior mixed in, the same reasoning as the User model above. Without it,
anything that creates a token (API key settings page, `$user->createToken()`)
fails with `Class "App\Models\PersonalAccessToken" not found`:

```php
<?php

namespace App\Models;

use VitaminD\Core\Models\PersonalAccessToken as CorePersonalAccessToken;

class PersonalAccessToken extends CorePersonalAccessToken
{
    //
}
```

> **Don't also run `php artisan install:api`.** Sanctum's own installer
> publishes a `personal_access_tokens` migration that collides with the one
> `vitamind/core` already ships — running both leaves you with two migrations
> claiming the same table. Core already wires up Sanctum for you
> (`Sanctum::usePersonalAccessTokenModel(...)` in `CoreServiceProvider`), so
> `install:api` is redundant here regardless of the collision.

Verify with:

```bash
php artisan route:list   # settings/profile, settings/api-keys, admin/*, api/health, ...
curl -i http://localhost/api/health   # {"success":true,"version":null}
```

Core does **not** ship views/frontend assets — it's a backend package. If you
want the full Inertia + React experience, use the `vitamin-d` boilerplate
itself as a starting point instead of requiring the packages piecemeal.

### Optional: workspace plugin

Multi-tenancy is a separate, optional package. Skip this entirely for
single-tenant apps — it adds zero overhead (no tables, no routes) until
enabled.

```bash
composer config repositories.vitamind-workspace-plugin path /path/to/vitamin-d/dev-packages/vitamind-workspace-plugin
composer require vitamind/workspace-plugin:@dev
```

Just like core, `WorkspaceServiceProvider` is auto-discovered via Composer
(`extra.laravel.providers` in the plugin's `composer.json`) — no
`bootstrap/providers.php` edit needed here either. Then in `.env`:

```env
VITAMIND_FEATURE_WORKSPACES=true
```

`vitamind/core` ships its own default `config/vitamin-d.php`, so this flag
resolves to `true` even if your app hasn't published/created a
`config/vitamin-d.php` of its own — no config file is required just to flip
the flag.

And run migrations again — `workspaces` and `user_workspace` tables are
created only now, gated by the flag:

```bash
php artisan migrate
```

Your `App\Models\User` (already created in the step above) needs the
workspace relations mixed in, since `vitamind/core`'s own `User` model stays
workspace-agnostic by design:

```php
use VitaminD\Core\Models\User as CoreUser;
use VitaminD\Plugins\Workspace\Concerns\HasWorkspaces;

class User extends CoreUser
{
    use HasWorkspaces;
}
```

Setting `VITAMIND_FEATURE_WORKSPACES=false` (the default) later removes the
routes and skips the migrations again — it does **not** drop existing
workspace tables. Run `php artisan migrate:rollback` first if you need a clean
teardown.

---

## Upgrading a pre-Phase-1 project

If you have an existing project built on an earlier `vitamin-d` boilerplate
(monolithic `app/` folder, no `dev-packages/`), Phase 1 is a breaking change —
there is no automated upgrade path. Pre-alpha, breaking changes are expected;
plan for a manual merge rather than a drop-in update.

### What moved where

| Old location | New location |
| :--- | :--- |
| `app/Actions/*` (except Workspace) | `vitamind/core` (`VitaminD\Core\Actions\*`) |
| `app/Actions/Workspaces/*` | `vitamind/workspace-plugin` (`VitaminD\Plugins\Workspace\Actions\*`) |
| `app/Models/User.php`, `PersonalAccessToken.php` | `vitamind/core`, extended by your `App\Models\*` |
| `app/Models/Workspace.php`, `UserWorkspace.php` | `vitamind/workspace-plugin` |
| `app/Models/AbstractModel.php` | `vitamind/core` (`VitaminD\Core\Models\AbstractModel`) |
| `app/Http/Controllers/*` (except your own) | `vitamind/core` / `vitamind/workspace-plugin` |
| `app/Http/Middleware/*` | same split as controllers |
| `app/Enums/UserRole.php` | `vitamind/core` (`VitaminD\Core\Enums\UserRole`) |
| `app/Plugins/Local/{Vendor}/{Name}/` | `app/Plugins/{Name}/` (flat, no `src/`) — see [`docs/local-plugins.md`](docs/local-plugins.md) |

### Steps

1. Pull in the packages as path repositories (see above), or copy
   `dev-packages/` from this repo if you're not consuming it as a dependency.
2. Delete your project's duplicated copies of anything in the table above.
3. Update `bootstrap/providers.php` to remove manual registrations for classes
   that moved — they're auto-discovered now.
4. Rewrite `app/Models/User.php` to `extend VitaminD\Core\Models\User` (plus
   `HasWorkspaces` if you use workspaces), rather than redeclaring auth logic.
5. Move each local plugin from `app/Plugins/Local/{Vendor}/{Name}/` to
   `app/Plugins/{Name}/`, updating its namespace from
   `App\Plugins\Local\{Vendor}\{Name}` to `App\Plugins\{Name}`.
6. Update `config/route-attributes.php` to drop any manual scanning of core/
   workspace controller directories — both packages register their own routes
   now. Keep only your own `app/Http/Controllers` entries.
7. Run `composer dump-autoload && php artisan migrate && php artisan test`
   and work through whatever the test suite surfaces — namespace typos are the
   most common issue at this step.
