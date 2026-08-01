## 1. Repository Setup & Package Structure

- [x] 1.1 Create `dev-packages/` directory structure in vitamin-d root
- [x] 1.2 Create `dev-packages/vitamind-core/` with composer.json template
- [x] 1.3 Create `dev-packages/vitamind-workspace-plugin/` with composer.json template
- [x] 1.4 Update root `composer.json` to include path repositories for dev-packages
- [x] 1.5 Create .gitignore entries for dev-packages/*/vendor, dev-packages/*/.env
- [x] 1.6 Setup GitHub organization structure: create repos for `vitamind-core` and `vitamind-workspace-plugin` (future mirrors)

---

## 2. Extract VitaminD Core Package

- [x] 2.1 Move `app/Actions/` to `dev-packages/vitamind-core/src/Actions/` (all non-workspace action classes)
- [x] 2.2 Move `app/Models/User.php`, `app/Models/Plugin.php`, `app/Models/PluginError.php`, `app/Models/PersonalAccessToken.php` to `dev-packages/vitamind-core/src/Models/`
- [x] 2.3 Move `app/Http/Controllers/` (excluding workspace controllers) to `dev-packages/vitamind-core/src/Http/Controllers/`
- [x] 2.4 Move `app/Http/Middleware/` to `dev-packages/vitamind-core/src/Http/Middleware/`
- [x] 2.5 Move `app/Http/Resources/` to `dev-packages/vitamind-core/src/Http/Resources/`
- [x] 2.6 Move `app/Policies/UserPolicy.php` to `dev-packages/vitamind-core/src/Policies/`
- [x] 2.7 Move `app/Enums/UserRole.php` to `dev-packages/vitamind-core/src/Enums/`
- [x] 2.8 Move `app/Contracts/AppEnum.php` to `dev-packages/vitamind-core/src/Contracts/`
- [x] 2.9 Move `app/Traits/HasRolePolicies.php`, `HasTimezoneTimestamps.php` to `dev-packages/vitamind-core/src/Traits/`
- [x] 2.10 Move `app/Support/helpers.php` to `dev-packages/vitamind-core/src/Support/helpers.php`
- [x] 2.11 Move `app/Providers/AppServiceProvider.php`, `PluginsServiceProvider.php` to `dev-packages/vitamind-core/src/Providers/`
- [x] 2.12 Move `database/migrations/` (core migrations only) to `dev-packages/vitamind-core/database/migrations/`
- [x] 2.13 Create `dev-packages/vitamind-core/src/CoreServiceProvider.php` that bootstraps all core services
- [x] 2.14 Update namespace declarations in all moved files to use `VitaminD\Core\*`
- [x] 2.15 Update PSR-4 autoload in `dev-packages/vitamind-core/composer.json`: `VitaminD\Core\ → src/`
- [x] 2.16 Add `composer.json` requirements: laravel/framework, laravel/fortify, laravel/sanctum, spatie/laravel-route-attributes, etc.
- [x] 2.17 Create `dev-packages/vitamind-core/README.md` with installation and usage instructions

---

## 3. Extract Workspace Plugin Package

- [x] 3.1 Create `dev-packages/vitamind-workspace-plugin/src/` structure
- [x] 3.2 Move `app/Actions/Workspaces/` to `dev-packages/vitamind-workspace-plugin/src/Actions/`
- [x] 3.3 Move `app/Models/Workspace.php`, `app/Models/UserWorkspace.php` to `dev-packages/vitamind-workspace-plugin/src/Models/`
- [x] 3.4 Move `app/Http/Controllers/Workspace/` to `dev-packages/vitamind-workspace-plugin/src/Http/Controllers/`
- [x] 3.5 Move `app/Http/Controllers/Admin/WorkspaceController.php` to plugin
- [x] 3.6 Move `app/Http/Middleware/HasWorkspace.php`, `CanSeeWorkspace.php` to `dev-packages/vitamind-workspace-plugin/src/Http/Middleware/`
- [x] 3.7 Move `app/Policies/WorkspacePolicy.php` to `dev-packages/vitamind-workspace-plugin/src/Policies/`
- [x] 3.8 Move workspace migrations to `dev-packages/vitamind-workspace-plugin/database/migrations/`
- [x] 3.9 Update namespace declarations to `VitaminD\Plugins\Workspace\*`
- [x] 3.10 Create `dev-packages/vitamind-workspace-plugin/src/WorkspaceServiceProvider.php` with feature flag gating
- [x] 3.11 In WorkspaceServiceProvider: Register routes only if feature flag enabled
- [x] 3.12 In WorkspaceServiceProvider: Register Inertia shared data via core's registry (if feature enabled)
- [x] 3.13 Add `composer.json` requirement: `vitamind/core`
- [x] 3.14 Configure PSR-4 autoload for workspace plugin
- [x] 3.15 Create `dev-packages/vitamind-workspace-plugin/README.md` with feature flag instructions

---

## 4. Update Boilerplate to Require Core & Optional Workspace

- [x] 4.1 Update boilerplate `composer.json` to `require vitamind/core` via path repository
- [x] 4.2 Add workspace plugin as optional: comment out or make conditional in composer.json
- [x] 4.3 Remove core/workspace classes from boilerplate `app/` (already moved to packages)
- [x] 4.4 Create `app/Models/User.php` in boilerplate that extends `VitaminD\Core\Models\User` (if app-specific behavior needed)
- [x] 4.5 Update `bootstrap/providers.php` to load core and workspace (if enabled) service providers
- [x] 4.6 Update `.env.example` to include `VITAMIND_FEATURE_WORKSPACES=false`
- [x] 4.7 Update `config/vitamin-d.php` workspace feature flag to check env var
- [x] 4.8 Run `composer dump-autoload` to verify all namespaces load correctly

---

## 5. Standardize Local Plugin Folder Structure (Option B: No src/)

### 5.1 Local Plugins (app/Plugins/)
- [x] 5.1.1 Migrate MockProduct: `app/Plugins/Local/Acme/MockProduct/` → `app/Plugins/MockProduct/`
- [x] 5.1.2 Move `Plugin.php` to root of `app/Plugins/MockProduct/` (NOT in src/)
- [x] 5.1.3 Move `Models/`, `Controllers/`, `Resources/` directly into plugin folder
- [x] 5.1.4 Move `database/` folder to plugin root (migrations at `app/Plugins/MockProduct/database/migrations/`)
- [x] 5.1.5 Update namespace in all files: `App\Plugins\MockProduct` (was App\Plugins\Local\Acme\MockProduct)
- [x] 5.1.6 Create HelloWorld plugin with same structure: `app/Plugins/HelloWorld/`
- [x] 5.1.7 Migrate HelloWorld to new structure

### 5.2 Autoloading Configuration
- [x] 5.2.1 Update PSR-4 autoload in boilerplate `composer.json`:
  ```json
  "App\Plugins\": "app/Plugins/"
  ```
- [x] 5.2.2 Run `composer dump-autoload` to verify both plugins are loadable
- [x] 5.2.3 Verify no conflicts with existing `App\` autoload

### 5.3 Verification & Testing
- [x] 5.3.1 Verify MockProduct plugin still works after migration
- [x] 5.3.2 Verify HelloWorld plugin boots and is discoverable
- [x] 5.3.3 Test plugin enable/disable functionality
- [x] 5.3.4 Create documentation: local plugin folder structure (no src/ vs external plugins)
- [x] 5.3.5 Document: when/why to extract local plugin → Composer package (re-structure with src/ at publish time)

---

## 6. Update Plugin Discovery & Installation System

### 6.1 Plugin Discovery (3 sources)
- [x] 6.1.1 Enhance `DiscoverPlugins` to scan three locations:
  - `app/Plugins/` for local plugins (namespace: App\Plugins\{PluginName})
  - `storage/plugins/` for GitHub-installed plugins
  - `vendor/vitamind/` for Composer packages
- [x] 6.1.2 For each discovered plugin, extract namespace from `composer.json` PSR-4 autoload (GitHub & Composer)
- [x] 6.1.3 For local plugins, use hardcoded namespace: `App\Plugins\{FolderName}`

### 6.2 Plugin Metadata Caching
- [x] 6.2.1 Extend `PluginCache` class to cache plugin metadata (not just active plugins)
- [x] 6.2.2 Add cache key strategy: `plugin_meta:{source}_{name}_{mtime_hash}`
- [x] 6.2.3 Cache stores: namespace, source, path, version, name, description
- [x] 6.2.4 Set cache TTL to 30 days (auto-invalidate)
- [x] 6.2.5 Cache validation: detect file changes via mtime (cache key includes hash)
- [x] 6.2.6 Add cache clear hooks in DiscoverPlugins (before discovery) if needed (not needed: mtime-hashed keys self-invalidate)

### 6.3 Plugin Installation & Registry
- [x] 6.3.1 Add source tracking to Plugin model: add `source` column (enum: composer/github/local) and `installed_at` timestamp
- [x] 6.3.2 Run migration to add source tracking columns to plugins table
- [x] 6.3.3 Update plugin registry logic to populate source field on discovery
- [x] 6.3.4 Create `InstallPluginFromGithub` action to clone GitHub repositories
- [x] 6.3.5 Create artisan command `plugin:install-github {org}/{name}` that uses InstallPluginFromGithub
- [x] 6.3.6 Add validation in InstallPluginFromGithub: check for valid `src/Plugin.php` and `composer.json`
- [x] 6.3.7 Create rollback logic for failed plugin installations (clean up partial state)

### 6.4 Cache Invalidation
- [x] 6.4.1 Clear plugin metadata cache when plugin is installed: `PluginCache::clear()`
- [x] 6.4.2 Clear cache when plugin is enabled: call within `EnablePlugin` action
- [x] 6.4.3 Clear cache when plugin is disabled: call within `DisablePlugin` action
- [x] 6.4.4 Clear cache when composer.json is modified manually: add explicit method call (mtime-hashed keys make this automatic; no manual step needed)

---

## 7. Update TypeScript Generation Configuration

- [x] 7.1 Update `config/typescript-transformer.php` to scan `dev-packages/*/src/DTOs/` paths (implemented in `TypeScriptTransformerServiceProvider::configure()`, this project's actual config surface)
- [x] 7.2 Add `vendor/vitamind/core/src/DTOs` to searching_paths
- [x] 7.3 Add `vendor/vitamind/workspace-plugin/src/DTOs` to searching_paths (if workspace plugin has DTOs)
- [x] 7.4 Test TypeScript generation: run `php artisan typescript:transform` and verify generated.d.ts includes VitaminD namespace types
- [x] 7.5 Verify DTOs from both core and workspace plugin are included in generated types (neither currently defines any TS-transformable DTO — Core's `GitHub\*Dto` classes are plain internal PHP, not `spatie/laravel-data` objects — so there's nothing to include yet; paths are wired for when one is added)
- [x] 7.6 Update frontend type imports if needed to reference new namespaced types (none found referencing stale namespaces)

---

## 8. Frontend Updates for Workspace & Plugin Changes

- [x] 8.1 Update React components to import workspace types from generated namespace (no changes needed — backend namespace renames don't leak into JSON prop shapes)
- [x] 8.2 Update plugin page component (`pages/plugins/dynamic-page.tsx`) if namespace changes affect it (no namespace references in this file, unaffected)
- [x] 8.3 Test frontend type checking: run `npm run types` and verify no TypeScript errors
- [x] 8.4 Update workspace switcher component if workspace plugin namespace changed (`auth.currentWorkspace` shape unchanged, verified via `WorkspaceTest` + `npm run types`)

---

## 9. Database Migrations & Schema

- [x] 9.1 Verify core migrations run before boilerplate migrations (migration ordering)
- [x] 9.2 Test fresh migration from scratch: `php artisan migrate:fresh`
- [x] 9.3 Test with workspace enabled: set `VITAMIND_FEATURE_WORKSPACES=true` and migrate
- [x] 9.4 Test workspace disabled: set `VITAMIND_FEATURE_WORKSPACES=false` and verify no workspace tables created
- [x] 9.5 Add workspace migration rollback test: disable feature, rollback, verify tables removed

---

## 10. Configuration & Environment

- [x] 10.1 Update `.env.example` with all new config variables
- [x] 10.2 Document all config options in `.env.example` comments
- [x] 10.3 Update `config/vitamin-d.php` to include workspace feature flag
- [x] 10.4 Create `config/typescript-transformer.php` if not exists, or update paths (project uses `TypeScriptTransformerServiceProvider` instead of a config file; paths updated there, now documented)
- [x] 10.5 Verify plugin discovery config: storage paths, vendor paths, all documented (see `docs/local-plugins.md` and `DiscoverPlugins` docblocks)

---

## 11. Bug Fixes & Issues

- [x] 11.1 Fix PluginPageController.php line 189: add missing `$request` parameter to `destroy()` method
- [x] 11.2 Test CRUD operations for plugins (create, read, update, delete) via dynamic page
- [x] 11.3 Test admin controllers authorization checks after namespace migration

---

## 12. Testing & Verification

- [x] 12.1 Run full test suite: `php artisan test`
- [x] 12.2 Test user login flow end-to-end
- [x] 12.3 Test admin panel access (users, plugins, settings)
- [x] 12.4 Test plugin enable/disable via UI
- [x] 12.5 Test local plugin discovery and boot
- [x] 12.6 Test workspace creation and switching (if feature enabled)
- [x] 12.7 Test workspace disable scenario: app works without workspace overhead
- [x] 12.8 Run TypeScript build: `npm run build` and verify success
- [x] 12.9 Manual testing: Fresh Laravel install + require vitamind/core (simulate real usage) — found & fixed: missing `extra.laravel.providers` auto-discovery, missing self-registered routes (consumer's route-attributes config can't know package internals), stale `vitamind/plugin-sdk` version constraint. Verified `api/health` returns 200 in a bare `laravel/laravel` project with just `composer require` + a path repo.
- [x] 12.10 Create demo plugin `TodoPlugin` — authored in `dev-packages/vitamind-todo-plugin/` (`composer.json`, `src/Plugin.php`, `src/Models/Todo.php`, migration), following the GitHub/Composer plugin structure from §5/§6. Pushed as a mirror to GitHub repo `vitamind-org/todo-plugin`; the Composer package name is `vitamind/todo-plugin` (vendor `vitamind`, matching how `DiscoverPlugins` scans `vendor/vitamind/*` — the GitHub org name and the Composer vendor name are intentionally different, same as `vitamind/core` living at `vitamind-org/vitamind-core`)
- [x] 12.11 Test GitHub plugin installation: `php artisan plugin:install-github vitamind-org/todo-plugin` — verified end-to-end (clone → discover → install → `migrate` → enable → boot, zero plugin errors, full test suite green). Found & fixed a real bug in `InstallPluginFromGithub`: it never registered the freshly cloned plugin's PSR-4 autoload before instantiating it, so the very first install of any brand-new GitHub plugin always failed class-not-found and rolled back. Fixed by registering the autoload prefix right after validation succeeds (mirrors what `DiscoverPlugins` does on the next boot). Cleaned up the test install afterward (uninstalled, `storage/plugins/TodoPlugin` removed).
- [x] 12.12 Test Composer plugin installation: `composer require vitamind/todo-plugin` (previous wording said `vitamind-org/todo-plugin`, which was wrong — that's the GitHub org/repo, not the Composer package name). Since Packagist publishing is still deferred, verified via a temporary `"type": "vcs"` repository pointing at the `vitamind-org/todo-plugin` mirror rather than Packagist — this is consistent with the "GitHub org as mirror" model already in place. Discover → install → enable all succeeded with zero plugin errors. Cleaned up afterward: `composer remove vitamind/todo-plugin`, removed the temporary VCS repository entry, dropped the test-only `todos` table, refreshed `composer.lock`. Full test suite green throughout (74/74).
- [x] 12.13 Found via real usage (installing `TodoPlugin` manually): enabling a plugin left its migrations merely *registered* with the migrator (via `DiscoverPlugins`), not actually *run* — first page load hit `SQLSTATE[42S02]: Base table or view not found`. Fixed by having `EnablePlugin` run the plugin's own pending migrations (scoped to its `database/migrations` path via `artisan migrate --path --realpath`) before flipping `is_enabled`. Deliberately NOT mirrored on `DisablePlugin` — migrate is additive/safe, rollback is destructive, and `UninstallPlugin` already treats plugin data as sticky (never drops tables), so disable staying non-destructive keeps the model consistent. Also added `php artisan plugin:enable {folder}` (mirrors `plugin:install-github`'s UX) and a `--enable|-e` flag on `plugin:install-github` for one-shot install+enable. Verified end-to-end: fresh enable auto-creates `todos`, fresh `install-github --enable` installs+migrates+enables in one command, zero plugin errors, full test suite green (74/74).
- [x] 12.14 Found via real usage (todos leaked across workspaces): plugin models had no notion of tenancy. Added `VitaminD\PluginSdk\Concerns\BelongsToWorkspace` (global read scope + `creating` stamp), applied it to `TodoPlugin`'s `Todo` with a nullable unconstrained `workspace_id` column. Key finding: because `PluginPageController` drives all plugin CRUD through plain Eloquent, scoping at the *model* layer secures the entire generic CRUD surface — including `findOrFail`, so other workspaces' rows can't be read/updated/deleted, not merely hidden from listings. No changes needed to `RegisterPage`/`RegisterDataTable`/`Column`. Deliberately **relaxed**: with the workspaces feature off every hook is a no-op, so a scoped plugin still installs on single-tenant projects. Trait lives in `plugin-sdk` and stays convention-based (config key + `current_workspace_id` + `workspace_id`, zero workspace-plugin class references) — see D7 in `design.md` for the alternatives rejected (trait in workspace-plugin, typed `workspace()` relation, `requiredFeatures` enable-gate, a third "support" package). `plugin-sdk` now requires `illuminate/database`. Verified live across 2 real workspaces (isolation confirmed, cross-workspace `find()` blocked) plus 9 new SDK tests; full suite 83/83.

---

## 13. Documentation & Migration Guide

- [x] 13.1 Create MIGRATION_GUIDE.md for developers upgrading projects
- [x] 13.2 Document how to install VitaminD Core in fresh Laravel project
- [x] 13.3 Document workspace plugin opt-in process
- [x] 13.4 Document plugin folder structure changes and migration steps
- [x] 13.5 Document plugin installation methods (Composer, GitHub, Local)
- [x] 13.6 Create CONTRIBUTING.md for developers building plugins
- [x] 13.7 Update main README.md with Phase 1 completion summary

---

## 14. Publishing & Organization Setup — DEFERRED

Publishing ditunda sampai stability gate terpenuhi (lihat change `stabilize-vitamind-packages`). GitHub org repos (`vitamind-org`) selama ini berfungsi sebagai mirror only (push kode, tanpa tag/release, tanpa Packagist). Item yang sudah selesai di sini (README, LICENSE, repo dibuat, kode di-push) tidak diulang. Sisa pekerjaan publishing dipindah ke change terpisah: **`publish-vitamind-packages`** (dieksekusi setelah gate di `stabilize-vitamind-packages` lulus).

- [x] 14.3 Create README.md in each package with clear usage instructions
- [x] 14.4 Add LICENSE file to both packages
- [x] 14.5 Create GitHub repos in vitamind-org for core and workspace plugin (mirror)
- [x] 14.6 Push package code to GitHub repositories (mirror)
- Sisanya (prep Packagist, register, org docs) → lihat `publish-vitamind-packages`

---

## 15. Final Checks & Phase 1 Closure

- [x] 15.1 Run all tests one final time (83/83 passed, 191 assertions)
- [x] 15.2 Verify fresh installation works via dev-packages path repository (covered by 12.9 — Packagist-based verification moved to `publish-vitamind-packages`)
- [x] 15.5 Update CHANGELOG.md noting Phase 1 (boilerplate + plugin system) stabilization complete (publishing not yet done)
- [x] 15.6 Mark phase1-standalone-boilerplate as complete in OpenSpec (structural extraction work only — real-world stability validation across external consumer projects is tracked separately in `stabilize-vitamind-packages` and does not block this closure)
- [x] 15.7 Plan Phase 2 focus, incl. monitoring `stabilize-vitamind-packages` dogfooding progress toward the publish gate (see "Phase 2 Outlook" in `design.md`)

