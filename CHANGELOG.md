# Changelog

Semua perubahan signifikan pada boilerplate ini dicatat di file ini. Format longgar mengikuti [Keep a Changelog](https://keepachangelog.com/), namun proyek ini belum mengikuti Semantic Versioning karena `vitamind/core` dan `vitamind/workspace-plugin` belum dipublikasikan ke Packagist (lihat bagian "Belum dilakukan" di bawah).

## [Unreleased] — Phase 1: Standalone Boilerplate & Plugin System

Struktural extraction VitaminD dari monolithic boilerplate menjadi reusable packages, selesai (change `phase1-standalone-boilerplate`, 130/134 task struktural — sisanya closure).

### Ditambahkan
- **`vitamind/core`** — package standalone berisi user management, auth (Fortify/Sanctum), admin panel infra, plugin engine core, REST API, dan Inertia shared data, siap di-`require` di fresh Laravel install (lihat `dev-packages/vitamind-core/`).
- **`vitamind/workspace-plugin`** — optional multi-tenancy plugin, hanya aktif via `VITAMIND_FEATURE_WORKSPACES=true`; aplikasi single-tenant tidak membawa overhead database workspace.
- **`vitamind/plugin-sdk`** — `BelongsToWorkspace` concern: scoping otomatis model plugin per workspace (global scope + auto-stamp `workspace_id`), convention-based tanpa dependency ke workspace-plugin.
- **Plugin installation system** — instalasi plugin dari 3 sumber: Local (`app/Plugins/`), GitHub (`storage/plugins/`, via `plugin:install-github`), dan Composer (`vendor/vitamind/*`), dengan plugin metadata caching (TTL 30 hari, mtime-hashed) dan rollback otomatis saat instalasi gagal.
- **Plugin folder standardization** — semua local plugin migrasi ke struktur `app/Plugins/{PluginName}/` tanpa `src/` (lihat `docs/local-plugins.md`); struktur `src/` tetap dipakai untuk plugin yang didistribusikan (GitHub/Composer).
- **`php artisan plugin:enable {folder}`** dan flag `--enable|-e` pada `plugin:install-github` untuk install+migrate+enable dalam satu langkah; enable kini otomatis menjalankan migration plugin yang bersangkutan.
- Dokumentasi baru: `MIGRATION_GUIDE.md`, `CONTRIBUTING.md`, `docs/local-plugins.md`.

### Diperbaiki
- `PluginPageController::destroy()` — parameter `$request` yang hilang.
- `InstallPluginFromGithub` — PSR-4 autoload plugin yang baru di-clone tidak pernah diregistrasi sebelum instansiasi, menyebabkan instalasi pertama plugin GitHub baru selalu gagal class-not-found.

### Breaking Changes
- Struktur folder local plugin berubah — plugin lama di `app/Plugins/Local/{Vendor}/{Plugin}/` harus dipindah ke `app/Plugins/{Plugin}/`.
- Namespace core/workspace berubah dari `App\*` ke `VitaminD\Core\*` / `VitaminD\Plugins\Workspace\*`.

### Belum dilakukan (deferred, tracked di change terpisah)
- **Publishing ke Packagist** — ditunda sampai stability gate terpenuhi, lihat `stabilize-vitamind-packages` (dogfooding di 4 proyek konsumen: BukuWarga, LembarUji, UangKas, wakuwaku) dan dieksekusi di `publish-vitamind-packages`.
- Sampai gate lulus, repo `vitamind-org` di GitHub berfungsi sebagai mirror kode saja (tanpa tag/release).

---

Entri untuk rilis publik pertama (setelah `publish-vitamind-packages`) akan ditambahkan dengan nomor versi begitu Packagist registration selesai.
