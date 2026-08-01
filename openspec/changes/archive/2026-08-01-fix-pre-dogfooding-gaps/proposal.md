## Why

`phase1-standalone-boilerplate` mengklaim `vitamind/core` "dapat di-require di fresh Laravel installation" (Goal #1), dan `stabilize-vitamind-packages` sudah membuka gate dogfooding ke 3 proyek konsumen nyata (BukuWarga, LembarUji, UangKas) berdasarkan asumsi itu. Sesi eksplorasi (`/opsx:explore`) yang menyelidiki laporan "User model termodifikasi + `database/migrations` kosong" menemukan bahwa klaim itu belum pernah diverifikasi end-to-end — dan begitu ditelusuri lewat pembacaan kode langsung, ditemukan 5 gap konkret yang akan menggagalkan instalasi fresh Laravel sungguhan, bukan cuma risiko teoritis.

`stabilize-vitamind-packages` sendiri secara eksplisit mengecualikan perubahan kode pada `vitamind/core`/`vitamind/workspace-plugin` dari scope-nya (Non-Goals). Kelima gap ini semuanya butuh perubahan kode di kedua package tersebut, sehingga tidak bisa ditangani di dalam `stabilize-vitamind-packages` — perlu change terpisah yang selesai **sebelum** proyek konsumen mulai dogfooding, supaya mereka tidak menghabiskan waktu menabrak bug yang sudah diketahui.

## What Changes

- Hapus 3 migration (`create_users_table`, `create_cache_table`, `create_jobs_table`) dari `vitamind-core` — identik byte-per-byte dengan skeleton Laravel, tidak pernah dimodifikasi, dan akan bentrok dengan migration bawaan fresh install
- Dokumentasikan larangan eksplisit menjalankan `php artisan install:api` di samping `vitamind/core` (mencegah kolisi migration `personal_access_tokens`)
- **BREAKING**: Ganti hardcode `use App\Models\User;` di ~20 titik dalam `vitamind-core`/`vitamind-workspace-plugin` — titik type-hint/policy/docblock diarahkan ke `VitaminD\Core\Models\User`, titik instantiation/query (`User::create()`, `User::query()`) diarahkan resolve dinamis lewat `config('auth.providers.users.model')`
- Package `vitamind-core` men-`mergeConfigFrom()` `config/vitamin-d.php` default miliknya sendiri, supaya `vitamin-d.features.workspaces` (dan feature flag lain) selalu punya nilai default meski app konsumen belum publish config filenya sendiri
- Hapus gate manual usang di `bootstrap/providers.php` boilerplate yang meng-conditional-kan registrasi `WorkspaceServiceProvider` — sudah redundan sejak package itu auto-discovered lewat `composer.json`
- Hapus file bangkai `VitaminD\Core\Providers\AppServiceProvider` (tidak terdaftar di manapun, isinya salah — akan jadi jebakan kalau tidak sengaja diaktifkan)
- Update `MIGRATION_GUIDE.md`, `README.md`, `CONTRIBUTING.md` supaya instruksi instalasi fresh Laravel mencerminkan kenyataan kode (lihat design.md untuk detail per-dokumen)
- Verifikasi end-to-end: benar-benar jalankan `laravel new` + `composer require vitamind/core` (+ workspace plugin) di direktori terpisah, bukan cuma percaya hasil pembacaan kode

## Capabilities

### New Capabilities

_(tidak ada — change ini memperbaiki perilaku yang sudah didokumentasikan, bukan menambah kapabilitas baru)_

### Modified Capabilities

- `vitamind-core`: Requirement "Core migrations register separately from boilerplate" — hapus users/cache/jobs dari migration list yang disebut; Requirement "Core namespace is VitaminD\Core and does not conflict with App namespace" — ubah "MAY extend" jadi "MUST extend" karena hardcode internal mewajibkannya
- `vitamind-workspace-plugin`: Requirement "Workspace Plugin is optional and feature-flagged" — skenario "Feature flag enables workspace functionality" perlu syarat tambahan bahwa `vitamind/core` men-supply default config, bukan mengandalkan app konsumen mem-publish `config/vitamin-d.php` sendiri

## Impact

- **Kode**: `dev-packages/vitamind-core/database/migrations/` (hapus 3 file), `dev-packages/vitamind-core/src/**` (~20 file, ganti import User), `dev-packages/vitamind-core/src/Providers/CoreServiceProvider.php` (tambah mergeConfigFrom), `dev-packages/vitamind-core/config/vitamin-d.php` (file baru), `dev-packages/vitamind-core/src/Providers/AppServiceProvider.php` (hapus), `dev-packages/vitamind-workspace-plugin/src/**` (file yang pakai App\Models\User), `bootstrap/providers.php` (hapus gate manual)
- **Dokumentasi**: `MIGRATION_GUIDE.md`, `README.md`, `CONTRIBUTING.md`
- **Testing**: `php artisan test` di boilerplate ini (regresi), plus instalasi fresh Laravel terpisah untuk verifikasi klaim "dapat diinisialisasi dari fresh Laravel installation" secara nyata
- **Dependencies**: Tidak ada package baru; ini murni perbaikan internal
- **Blocker/prasyarat untuk**: `stabilize-vitamind-packages` §0 Gate Check — dogfooding ke BukuWarga/LembarUji/UangKas sebaiknya menunggu change ini selesai, supaya proyek konsumen tidak menemukan bug yang sudah diketahui di sini
