## Context

**Current State:**
- VitaminD boilerplate (`vitamin-d/`) contains monolithic application code dalam `app/` folder
- Plugin SDK (`vitamind/plugin-sdk`) sudah extracted ke `dev-packages/vitamind-plugin-sdk/` (Phase 5)
- Workspace system ada dalam core app, terikat dengan non-workspace applications
- Local plugins (MockProduct, HelloWorld) ada di `app/Plugins/Local/Acme/{Name}/`
- GitHub organization (`vitamind-org`) exists tetapi belum fully utilized

**Development Phase Context:**
- VitaminD masih dalam fase development (belum mencapai alpha)
- Tidak perlu backward compatibility dengan boilerplate sebelumnya
- Breaking changes acceptable karena ini adalah pre-release extraction
- Focus pada clean architecture, bukan migration paths

---

## Goals / Non-Goals

**Goals:**
1. Extract VitaminD Core (user auth, admin, plugin engine) menjadi standalone `vitamind/core` package yang can be required di fresh Laravel installation
2. Separate Workspace system menjadi optional `vitamind/workspace-plugin` sehingga non-multitenant apps tidak memiliki database overhead
3. Standardize plugin folder structure (semua class code di `src/`) across Composer, GitHub, dan Local plugins
4. Implement robust plugin installation system dari 3 sumber: Composer packages, GitHub repositories, Local storage
5. Centralize all VitaminD ecosystem repositories di `vitamind-org` GitHub organization
6. Ensure TypeScript types auto-generation works across package boundaries via `spatie/laravel-typescript-transformer`

**Non-Goals:**
- WebSocket real-time system (Phase 7)
- Public plugin registry / marketplace (future phase)
- Automated plugin publishing to Packagist (manual for now)
- Breaking changes to plugin SDK contracts

---

## Decisions

### D1: Package Extraction Strategy

**Decision**: Extract core functionality via **local path repositories** (`dev-packages/`) for development, then publish to Packagist for production consumption.

**Timing update (revised)**: The move to Packagist is deferred behind a stability gate — at least 2 of 3 dogfooding projects (BukuWarga, LembarUji, UangKas) must successfully extend the boilerplate via path repositories without requiring a breaking change to `vitamind/core` or `vitamind/workspace-plugin` (tracked as a separate change, `stabilize-vitamind-packages`, so it does not block this phase's closure). Until the gate passes, the `vitamind-org` GitHub repos are mirror-only (code pushed, no tags/releases, no Packagist registration). Publishing itself is executed as a separate change, `publish-vitamind-packages`, once the gate is met.

**Rationale:**
- Developers dapat `require vitamind/core` dari Packagist instantly
- During development, symlink to local `dev-packages/vitamind-core` untuk hot edits
- Monorepo structure (VitaminD/vitamin-d contains all dev-packages) simplifies CI/CD
- Aligns dengan current plugin-sdk setup

**Alternatives Considered:**
- A) Separate GitHub repos immediately → Harder to maintain coherence during Phase 1
- B) Nested composer.json only → No separation of concerns, harder to extract later
- C) Monorepo with workspaces → Added complexity; path repos sufficient for now

**Structure:**
```
VitaminD/vitamin-d/                    ← Main boilerplate repo
├── dev-packages/
│   ├── vitamind-core/
│   ├── vitamind-workspace-plugin/
│   └── [future plugins]
├── packages/
│   └── plugin-sdk/                    ← Already extracted (Phase 5)
├── composer.json
│   └── repositories.path dev-packages/*
└── ...
```

---

### D2: Workspace as Separate Plugin

**Decision**: Extract Workspace (multi-tenancy) into `vitamind/workspace-plugin` with **feature flag conditioning** at middleware/service provider level.

**Rationale:**
- Workspace is fundamentally optional (some apps never need multi-tenancy)
- Keeping it in core creates migration tables, middleware, and config burden for single-tenant apps
- Plugin architecture already proven with plugin-sdk; workspace fits naturally as a 1st-party plugin
- Feature flag (`config('vitamin-d.features.workspaces')`) gates all workspace-related functionality

**Implementation Approach:**
- Core provides `InertiaSharedDataRegistry` + middleware hooks for plugins to inject data
- Workspace plugin registers itself via service provider when feature enabled
- Workspace migrations only run if feature flag true
- Controllers/Actions remain in workspace plugin, not core

**Alternatives Considered:**
- A) Keep in core but make truly optional via feature gate → Still has hidden overhead (models, migrations)
- B) Fully separate package (not in dev-packages) → Harder to maintain during Phase 1
- C) Dynamic plugin loading → Too complex; workspace needs to be discoverable at install time

---

### D3: Plugin Folder Standardization & Namespace Convention (Option B: Pragmatic)

**Decision**: 
- **Local plugins** (`app/Plugins/`): NO `src/` subfolder (pragmatic, simpler for app code)
- **GitHub & Composer plugins**: WITH `src/` folder (standard for distributed packages)
- Namespace by source:
  - **Local plugins**: `App\Plugins\{PluginName}`
  - **GitHub plugins**: Read from composer.json PSR-4 autoload (cached)
  - **Composer plugins**: Read from composer.json PSR-4 autoload

**Rationale:**
- Local plugins are application code (not packages), no need for src/ convention
- External plugins (GitHub/Composer) are packages, src/ is standard PSR-4
- Simpler dev experience: developers create app code as they normally would
- Fewer nested folders in app/ (keeps app directory flatter)
- No confusion: "why src/ in application code?"
- Consistent with Laravel app/ folder conventions
- Future extractions: local → packages can re-structure during publish

**Namespace Convention:**
```
Local Plugins (APP CODE):
  Location: app/Plugins/{PluginName}/
  Namespace: App\Plugins\{PluginName}
  Structure: Plugin.php at root (no src/ subfolder)
  Example: app/Plugins/UserKyc/
           ├── Plugin.php
           ├── Models/
           ├── Controllers/
           └── database/

GitHub Installed (PACKAGES):
  Location: storage/plugins/{repo-name}/
  Namespace: VitaminD\{Vendor}\{Name}Plugin (read from composer.json)
  Structure: src/ folder for classes
  Example: storage/plugins/ecommerce/
           ├── src/
           │   ├── Plugin.php
           │   ├── Models/
           │   └── ...
           └── database/

Composer 1st-Party (PACKAGES):
  Location: vendor/vitamind/{package}/
  Namespace: VitaminD\{Name}Plugin (read from composer.json)
  Structure: src/ folder for classes
  Exceptions: VitaminD\Core, VitaminD\PluginSdk
  Example: vendor/vitamind/ecommerce/
           ├── src/
           │   ├── Plugin.php
           │   └── ...
           └── database/
```

**Breaking Change (Development Phase):**
- Current structure: `app/Plugins/Local/Acme/MockProduct/` → NEW: `app/Plugins/MockProduct/`
- No migration path needed (pre-alpha development phase)
- Clean slate for Phase 1

---

### D4: Plugin Installation System & Discovery

**Decision**: Three-tier plugin installation with consistent discovery:
1. **Local plugins**: `app/Plugins/{PluginName}/` (application-specific, versioned)
2. **GitHub plugins**: `storage/plugins/{name}/` (installed via admin UI/artisan, writable)
3. **Composer plugins**: `vendor/vitamind/{package}/` (installed via composer, immutable)

**Rationale:**
- Local: Application-specific features, versioned with project, fastest feedback loop
- GitHub: Community/experimental plugins, installed at runtime, writable for updates
- Composer: 1st-party plugins, distributed, versioned, production-grade

**Namespace Resolution (with Caching):**
- **Local plugins**: `App\Plugins\{PluginName}` (hardcoded, no parsing needed)
- **GitHub & Composer**: Read namespace from `composer.json` PSR-4 autoload
- **Caching strategy**: Plugin metadata cached with TTL (30 days) + invalidated on file changes
  - Cache key includes file mtime hash to auto-invalidate on updates
  - First discovery: parse composer.json, cache result
  - Subsequent discoveries: cache hit (negligible overhead)
  - Explicit invalidation when plugin installed/enabled/disabled
- **Performance**: ~1-2ms (cached) vs ~15-30ms (uncached) per discovery cycle

**Implementation:**
- `DiscoverPlugins` enhanced to scan: `app/Plugins/`, `storage/plugins/`, `vendor/vitamind/`
- Extend `PluginCache` class to store plugin metadata (namespace, source, path, version)
- New artisan commands: `php artisan plugin:install-github {org}/{name}`
- Admin UI for GitHub plugin installation
- Plugin registry tracks source + metadata

**Alternatives Considered:**
- A) Hardcode namespace from repo/package name → Inflexible, developers can't control namespace
- B) Parse composer.json without cache → Overhead on every discovery (~15-30ms per 10 plugins)
- C) Repository-name-parsing-only → Fast but not flexible, breaks if naming conventions change

---

### D5: TypeScript Generation Across Packages

**Decision**: Maintain centralized `config/typescript-transformer.php` scanning `dev-packages/*/src/DTOs` (via path repos) plus `vendor/vitamind/*/src/DTOs` (installed packages).

**Rationale:**
- DTOs in packages (not in app/) need TypeScript mirrors for frontend
- `spatie/laravel-typescript-transformer` already supports custom paths
- Single generated file (`resources/js/types/generated.d.ts`) keeps frontend types consistent
- Transformer auto-discovers package DTOs through autoloading

**Config Logic:**
```php
'searching_paths' => [
    base_path('vendor/vitamind/core/src/DTOs'),
    base_path('vendor/vitamind/workspace-plugin/src/DTOs'),
    base_path('dev-packages/vitamind-core/src/DTOs'),    // dev path
    base_path('dev-packages/vitamind-workspace-plugin/src/DTOs'),
    // ... future packages
    app_path('Plugins/Local'),
],
```

---

### D6: Workspace Plugin Activation

**Decision**: Workspace plugin is **opt-in** via environment variable `VITAMIND_FEATURE_WORKSPACES=false` (default).

**Rationale:**
- Backwards compatible; existing apps continue without workspace
- Operators control feature via `.env` at deployment time
- Service provider checks flag before binding workspace-related services

**Activation Flow:**
```
1. Developer `require vitamind/workspace-plugin` (composer install)
2. Set VITAMIND_FEATURE_WORKSPACES=true in .env
3. Run migrations (workspace tables created)
4. Feature fully active
```

---

### D7: Workspace-Scoped Plugin Data (relaxed, convention-based)

**Context**: Discovered by using `TodoPlugin` for real — todos leaked across workspaces, because a plugin model is a plain Eloquent model with no notion of tenancy.

**Decision**: Plugin models opt into workspace scoping with a single trait, `VitaminD\PluginSdk\Concerns\BelongsToWorkspace`, which adds a global read scope plus a `creating` hook that stamps `workspace_id`. Plugins add a nullable, **unconstrained** `workspace_id` column.

**Where scoping lives — the model, not the SDK page/table registry or the controller.** `PluginPageController` drives CRUD for every plugin through plain Eloquent (`$modelClass::query()`, `::create()`, `::findOrFail()`). Putting the scope on the model therefore secures the whole generic CRUD surface for free — including `findOrFail`, so a record from another workspace can't be read, updated, or deleted (the lookup misses outright, rather than merely being hidden from the list). No changes to `RegisterPage` / `RegisterDataTable` / `Column` were needed.

**Relaxed, not gated.** With `vitamin-d.features.workspaces` disabled, every hook is a no-op and the model behaves as an ordinary global model. So a workspace-aware plugin still installs and works on a single-tenant project — it *adapts* instead of demanding multi-tenancy.

**Where the trait lives — `plugin-sdk`, kept convention-based.** The trait touches only three names: the `vitamin-d.features.workspaces` config key (which belongs to the consuming app's `config/vitamin-d.php`, not to any package), the `current_workspace_id` attribute on the authenticated user, and the model's own `workspace_id` column. It references no workspace-plugin class, so `plugin-sdk` gains **zero dependency** on `vitamind/workspace-plugin` — the same way Laravel's `SoftDeletes` knows the name `deleted_at` without knowing any domain model.

**Alternatives rejected:**
- **Trait in `vitamind-workspace-plugin`** → any workspace-aware plugin would have to `require vitamind/workspace-plugin` to autoload it, dragging workspace models and migrations into single-tenant installs. That directly contradicts D2's "single-tenant apps stay lean, no database overhead".
- **A typed `workspace()` BelongsTo relation on the trait** → the one thing that *would* force a workspace-plugin class reference. Dropped from the trait; a plugin that wants it declares it on its own model and opts into the dependency itself.
- **An enable-time gate (`getRequiredFeatures() = ['workspaces']`)** → would block installing the plugin at all when workspaces are off. Wrong tool for an *adaptive* plugin like TodoPlugin. Such a gate is only meaningful for a plugin inherently *about* workspaces (e.g. per-workspace billing); no such plugin exists yet, so building the gate now would be speculation with no real case to shape it. Deferred until one appears.
- **A third "support" package for shared plugin classes** → not warranted: `plugin-sdk` (the foundation every plugin already depends on) and `workspace-plugin` (home of everything workspace-specific) between them already cover it. A third package only adds another dependency every plugin author must learn.

**Trade-offs accepted:**
- `plugin-sdk` now requires `illuminate/database` (it did not before) because the trait targets Eloquent. In practice free — `laravel/framework` already provides it.
- No foreign key on `workspace_id`, so no cascade delete when a workspace is removed: a single-tenant install has no `workspaces` table for the key to reference. Cleanup of orphaned plugin rows is left to the workspace deletion path.
- Unauthenticated contexts (console commands, queued jobs) are **unscoped rather than empty**. Scoping to a null workspace would compare against SQL NULL and silently match nothing, so an artisan command would quietly see an empty table instead of the data it exists to process.
- Rows predating the column keep `workspace_id = NULL` and become invisible once the feature is on. A plugin carrying real data across this change must decide where those rows belong and backfill them.

---

## Risks / Trade-offs

| Risk | Mitigation |
|------|-----------|
| **Dev-packages symlink breaks in CI/CD** | Use conditional path repos in CI config; test both path and Packagist resolution |
| **TypeScript generation misses package DTOs** | Test transformer with populated dev-packages (vitamind-plugin-sdk); document required config |
| **Namespace conflicts** (e.g., App\Models\User vs Core\Models\User) | Boilerplate User extends Core BaseUser; clear documentation on namespacing |
| **Workspace plugin migrations interfere with core** | Plugin migrations scoped to workspace; feature flag prevents loader registration |
| **Three different folder structures** (local vs GitHub vs Composer) | Document each structure clearly; plugins CLI/generator will create correct structure |
| **GitHub plugin discovery/registry not built yet** | Document manual GitHub clone steps; registry a future phase, not Phase 1 blocker |
| **Package publishing coordination** | Designate maintainer; automate Packagist publishing via GitHub Actions (future) |

---

## Migration Plan

### Phase 1 Execution Order

1. **Setup Repository Structure** (1-2 days)
   - Create `dev-packages/vitamind-core/` and `dev-packages/vitamind-workspace-plugin/` directories
   - Copy `packages/plugin-sdk/composer.json` as template; adjust namespaces

2. **Extract Core (vitamind/core)** (5-7 days)
   - Move `app/Actions/`, `app/Models/User.php`, `app/Http/Controllers/`, `app/Providers/`, `app/Enums/`, `app/Policies/` → `dev-packages/vitamind-core/src/`
   - Create service providers in core for bootstrapping
   - Update boilerplate `composer.json` to require core via path

3. **Extract Workspace Plugin** (3-4 days)
   - Move `app/Actions/Workspaces/`, `app/Models/Project.php`, `app/Models/UserProject.php`, `app/Http/Controllers/Project/` → plugin `src/`
   - Create workspace service provider with feature flag gating
   - Plugin registers via `boot()` only if feature enabled

4. **Standardize Plugin Folder Structure** (2-3 days)
   - Migrate MockProduct: `app/Plugins/Local/Acme/MockProduct/` → `src/` subdirectory
   - Update autoload in `composer.json` for local plugins

5. **Enhance Plugin Installation** (3-4 days)
   - Update `DiscoverPlugins` action to scan dev-packages, vendor, local storage
   - Add GitHub plugin installation command
   - Update plugin registry schema if needed

6. **Verify TypeScript Generation** (1-2 days)
   - Test transformer scans package DTOs
   - Ensure `generated.d.ts` includes both core and workspace plugin types

7. **Testing & Documentation** (3-4 days)
   - Test fresh Laravel install + require vitamind/core
   - Test workspace plugin enable/disable
   - Document migration path for existing projects

**Total**: ~3-4 weeks of focused work

---

## Decisions Locked ✅ (All 6 Decisions + 4 Open Questions)

**D1-D6 (Architectural)**
- Package extraction via path repositories (dev-packages for dev, Packagist for production)
- Workspace as separate optional plugin with feature flag gating
- Folder standardization: local plugins NO src/, external plugins WITH src/ (Option B: Pragmatic)
- Three-tier plugin installation (Composer, GitHub, Local) with caching
- TypeScript generation across packages via spatie/laravel-typescript-transformer
- Workspace plugin opt-in activation via VITAMIND_FEATURE_WORKSPACES env var

**Plugin Folder Structure & Namespace Convention** (D3 - REVISED)
- Local plugins: `app/Plugins/{PluginName}/` (NO src/) → `App\Plugins\{PluginName}`
- GitHub plugins: `storage/plugins/{name}/` (WITH src/) → namespace from composer.json
- Composer plugins: `vendor/vitamind/{package}/` (WITH src/) → namespace from composer.json
- Rationale: Pragmatic, local code doesn't need src/ convention

**Plugin Installation & Discovery** (D4)
- Three sources: Local (app/), GitHub (storage/, writable), Composer (vendor/, immutable)
- Namespace resolved from composer.json PSR-4 autoload (GitHub & Composer)
- Metadata cached with TTL 30 days + mtime-based invalidation
- Performance: ~1-2ms (cached) vs ~15-30ms (uncached)

---

## Open Questions → DECISIONS LOCKED ✅

**OQ1: Package Versioning**
- **LOCKED**: Use **0.1.0** for first release
- Rationale: Pre-alpha development phase, semantic versioning can follow after stabilization

**OQ2: GitHub Publishing Automation**
- **LOCKED**: **Defer** GitHub Actions auto-publish; not priority for Phase 1
- Manual publish sufficient initially
- Rationale: Stabilize core first, automate after workflow proven

**OQ3: Workspace Plugin Default**
- **LOCKED**: Keep **fully optional**
- User must explicitly `composer require vitamind/workspace-plugin` to install
- Rationale: Non-workspace apps should have zero overhead
- Workspace is opt-in via VITAMIND_FEATURE_WORKSPACES env var

**OQ4: Database Migrations in Plugins**
- **LOCKED**: Handle via **plugin-sdk**
- Plugin-sdk manages plugin migrations auto-discovery
- Rationale: Plugin system is plugin-sdk's responsibility
- Plugins declare migrations, plugin-sdk ensures they run at boot time

---

## Phase 2 Outlook (planned at Phase 1 closure)

Phase 1's structural extraction (§1–13) is done and verified (83/83 tests). Closure (§14–15) intentionally split the *not-yet-controllable* parts into their own changes rather than blocking on them:

- **`stabilize-vitamind-packages`** — dogfooding gate (0/3 proyek konsumen selesai per closure Phase 1: BukuWarga, LembarUji, UangKas belum mulai extend boilerplate ini). Ini yang membuka `publish-vitamind-packages`.
- **`publish-vitamind-packages`** — Packagist registration, tag/release, org docs; diblokir oleh gate di atas.

**Phase 2 focus** (setelah Phase 1 closed):
1. **Monitor dogfooding** — pantau progres 3 proyek konsumen di `stabilize-vitamind-packages` tasks.md §1; setiap proyek yang selesai extend tanpa breaking change dicatat di sana. Begitu ≥2/3 lulus, gate §2 terbuka untuk `publish-vitamind-packages`.
2. **Triage breaking changes dari dogfooding** — jika sebuah proyek konsumen menemukan kebutuhan breaking change pada `vitamind/core`/`vitamind/workspace-plugin`, evaluasi itu sebagai change tersendiri (bukan reopen Phase 1), lalu retry integrasi proyek tersebut.
3. **GitHub plugin registry** (disebut di proposal.md sebagai "phase lanjutan") — belum dibangun; namespace/discovery registry untuk memudahkan menemukan plugin pihak ketiga dari GitHub, di luar scope Phase 1.
4. **Tidak menambah scope baru ke core/workspace-plugin** sebelum gate stabilitas lulus — perubahan API selama masa dogfooding meningkatkan risiko proyek konsumen harus retry integrasi.

---

**STATUS**: ALL DECISIONS LOCKED ✅ Ready for `/opsx:apply`

