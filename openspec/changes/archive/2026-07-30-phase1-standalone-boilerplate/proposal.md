## Why

VitaminD telah berkembang menjadi framework SPA yang matang dengan arsitektur plugin-based, namun masih terikat pada struktur monolitik boilerplate. **Phase 1 aims to extract VitaminD menjadi reusable, production-ready boilerplate framework** yang dapat diintegrasikan ke proyek Laravel baru dengan mudah. Ini memerlukan pemisahan core functionality menjadi standalone packages, standardisasi plugin architecture, dan robust plugin installation mechanisms dari berbagai sumber (Composer, GitHub, Local).

---

## What Changes

### Core Architecture
- **VitaminD Core** diekstrak dari `app/` menjadi standalone Composer package (`vitamind/core`) yang dapat di-require di fresh Laravel installation
- **Workspace System** dipisahkan dari core menjadi optional plugin (`vitamind/workspace-plugin`) agar aplikasi tanpa multi-tenancy tetap lean dan tidak ada overhead database
- **Plugin SDK** (sudah selesai di Phase 5) tetap sebagai foundation untuk plugin development

### Plugin System Enhancement
- **Plugin Installation** mendukung 3 sumber: Composer packages (1st-party), GitHub repositories, dan Local plugins
- **Plugin Folder Standardization** — semua plugin class code harus berada di `src/` folder, mengikuti PSR-4 standards dan best practices
- **GitHub Plugin Registry** (phase lanjutan) — namespace registry untuk mudah discover plugins dari GitHub

### Distribution Model
- **Development Mode (aktif saat ini)**: Core dan plugins hidup di `dev-packages/` untuk development dengan symlink path repositories. Ini mode yang dipakai selama masa stabilisasi.
- **GitHub Organization (mirror only)**: Repos di `vitamind-org` (core, workspace-plugin) menerima push kode sebagai mirror selama masa stabilisasi — tanpa version tag, tanpa GitHub release.
- **Production Mode (deferred)**: Publish ke Packagist ditunda sampai stability gate terpenuhi — minimal 2 dari 3 proyek konsumen (BukuWarga, LembarUji, UangKas) berhasil meng-extend boilerplate ini tanpa breaking change pada core/workspace-plugin (lihat change `stabilize-vitamind-packages`). Setelah gate lulus, publishing dieksekusi lewat change terpisah `publish-vitamind-packages`.

---

## Capabilities

### New Capabilities

- **`vitamind-core`**: Standalone Composer package berisi user management, authentication (Fortify/Sanctum), admin panel infrastructure, plugin engine core, REST API, bootstrap system, dan Inertia shared data configuration. Dapat di-require di fresh Laravel installation tanpa workspace overhead.

- **`vitamind-workspace-plugin`**: Optional plugin yang menyediakan multi-tenancy workspace/project system. Hanya di-load jika feature flag `vitamin-d.features.workspaces` enabled. Terpisah dari core sehingga aplikasi single-tenant tidak membawa database overhead.

- **`plugin-installation-system`**: Robust plugin installation mechanism yang support:
  - Composer packages (vitamind-org/* packages dari Packagist)
  - GitHub repositories (dari vitamind-org atau 3rd-party)
  - Local plugins (development/custom plugins di storage atau app/)

- **`plugin-folder-standardization`**: Standardisasi struktur semua plugin (baik Composer, GitHub, maupun Local) dengan menempatkan semua class code di `src/` folder, mengikuti PSR-4 namespace conventions.

### Modified Capabilities

- `plugin-sdk`: TypeScript auto-generation untuk DTOs sudah berjalan (Phase 5), Phase 1 hanya maintain + ensure compatibility dengan ecosystem changes

---

## Impact

### Code Changes
- **Extract `app/` to packages**: User management, auth, admin controllers, plugin actions, models → `dev-packages/vitamind-core/`
- **Extract Workspace**: Workspace models, actions, controllers, migrations → `dev-packages/vitamind-workspace-plugin/`
- **Update Boilerplate**: `vitamin-d` repository menjadi starter kit yang `require vitamind/core` + optional workspace plugin
- **Refactor Local Plugins**: MockProduct dan semua local plugins restructure dengan `src/` folder standar

### Configuration
- **Feature Flags**: Workspace tetap opt-in via `config('vitamin-d.features.workspaces')`
- **Plugin Discovery**: Config untuk plugin paths (Composer autoload, GitHub registry, storage paths)
- **TypeScript Transformer**: Update untuk scan plugins di package namespaces

### Dependencies
- **New Packages**: `vitamind/core`, `vitamind/workspace-plugin` (dan semua 1st-party plugins)
- **Publishing**: Ditunda (deferred) sampai stability gate terpenuhi — gate ditrack di change `stabilize-vitamind-packages`, publishing dieksekusi di change `publish-vitamind-packages`
- **Organization**: Centralize di GitHub `vitamind-org` (mirror only selama masa stabilisasi)

### Breaking Changes
- **BREAKING**: Plugin folder structure — existing local plugins harus migrate ke `src/` structure
- **BREAKING**: App namespace usage — setelah extraction, app-specific code tetap di boilerplate `app/`, core logic hanya di packages

