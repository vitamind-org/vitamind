## Why

Backend plugin VitaminD (Local, GitHub, Composer) sudah matang lewat `DiscoverPlugins` dan pola `ServiceProvider` yang konsisten. Tapi frontend-nya timpang: satu-satunya jalur UI yang benar-benar berfungsi untuk ketiga sumber plugin adalah sistem `dynamic-page` generik (CRUD berbasis `RegisterPage`/`RegisterDataTable`). Begitu sebuah plugin butuh UI kustom (halaman Inertia sendiri, atau hook client-side lintas-halaman), tidak ada mekanisme resmi:

- `getPluginAliases()` di `vite.config.ts` dan cabang resolver `@plugin/` di `resources/js/app.tsx` masih menunjuk ke convention `app/Plugins/Local/{Vendor}/{Name}/resources/js` — convention ini sudah dimigrasikan ke struktur flat `app/Plugins/{Name}/` sejak Phase 1 (lihat `MIGRATION_GUIDE.md`, `docs/local-plugins.md`). Kode ini tidak pernah match apa pun; ini sisa mati dari sebelum migrasi.
- Halaman React milik `vitamind/workspace-plugin` (invite, onboarding, index workspace) di-hardcode langsung di `resources/js/pages/workspaces/` milik host, bukan di package `dev-packages/vitamind-workspace-plugin` — bukan karena keputusan desain, tapi karena tidak ada jalur lain.
- Hook client-side `vitamind/realtime-plugin` (`echo.ts`, `use-socket-events.ts`, `use-broadcast-channel.ts`, `socket-store.ts`) dianyam langsung ke `resources/js/layouts/app/layout.tsx` milik host, dipakai setiap halaman lewat `AppHeader`.
- Tidak ada mekanisme scan Tailwind untuk source di `dev-packages/`/`vendor/vitamind/*` — `@source` di `resources/css/app.css` cuma mencakup `resources/views` dan vendor Blade Laravel.

Kebutuhan ini bukan lagi hipotetis: proyek `wakuwaku` — konsumen dogfooding ke-4 di luar tiga yang tercatat di gate `stabilize-vitamind-packages` (BukuWarga, LembarUji, UangKas) — sudah fork boilerplate ini dan resync berkelanjutan dengan `dev-packages/`, dan menunggu keputusan ini merge ke `dev` sebelum mulai kerja realtime-data mereka sendiri.

## What Changes

- **Hapus** mekanisme `@plugin/` lama yang menunjuk ke convention `app/Plugins/Local/*` yang sudah tidak berlaku (`vite.config.ts`, `resources/js/app.tsx`). Local plugin sejak Phase 1 adalah kode aplikasi biasa — FE-nya cukup ditulis langsung di `resources/js/pages/...` seperti fitur host lain, tanpa mekanisme glob/alias khusus.
- **Bangun ulang** mekanisme `@plugin/{kebab-name}` supaya menunjuk ke `vendor/vitamind/*/resources/js` (symlink ke `dev-packages/vitamind-*` di setup path-repository repo ini) — dipakai jalur Composer/GitHub ("distributed" tier). Host dan plugin tetap dikompilasi dalam satu proses Vite yang sama; tidak ada build terpisah, HMR tetap jalan.
- Tambah satu baris `@source` di `resources/css/app.css` menunjuk ke `vendor/vitamind/*/resources/js/**`, mengikuti pola yang sudah ada untuk vendor Blade Laravel, supaya className Tailwind di komponen plugin menghasilkan CSS nyata dari token host.
- Tambah helper pemanggilan stabil (`usePlugin('nama-plugin')`) untuk hook/utilitas non-halaman, dan buat `resolve()` Inertia di `app.tsx` hybrid (coba peta hasil glob dulu, siapkan struktur fallback registry runtime untuk dipakai nanti) — supaya kelak kalau sebuah plugin pindah ke distribusi bundle mandiri murni, cara host **memanggil** tidak perlu berubah.
- **BREAKING (internal, tidak memengaruhi API publik)**: pindahkan halaman `vitamind/workspace-plugin` yang sudah ada dari `resources/js/pages/workspaces/*` ke `dev-packages/vitamind-workspace-plugin/resources/js/pages/`, dan hook `vitamind/realtime-plugin` dari lokasi host ke `dev-packages/vitamind-realtime-plugin/resources/js/`.
- Update `docs/local-plugins.md` (tambahkan bagian FE untuk Local plugin, dan catatan migrasi FE saat ekstraksi ke package) dan `MIGRATION_GUIDE.md` (perjelas bahwa pernyataan "Core does not ship views/frontend assets" berlaku historis untuk titik ekstraksi 2 paket awal, bukan kebijakan permanen untuk seluruh plugin distributed ke depan).
- **Eksplisit di luar scope**: pipeline build terpisah per-plugin, externalize React/ReactDOM lewat global runtime, self-registration wiring, dan script-tag injection untuk konsumsi Packagist murni/arms-length. Ditunda sampai ada bukti kebutuhan nyata (drift versi dependency yang benar-benar menyebabkan bug, atau konsumen eksternal non-monorepo sungguhan) — lihat `design.md` untuk detail trade-off.

## Capabilities

### New Capabilities
- `plugin-frontend-distribution`: Mendefinisikan bagaimana source frontend (halaman Inertia, hook, komponen) milik plugin Local/GitHub/Composer di-resolve dan di-build oleh host, termasuk konvensi lokasi file, mekanisme alias/glob, cakupan scan Tailwind, dan kontrak pemanggilan yang stabil lintas mode resolusi.

### Modified Capabilities
(tidak ada — `plugin-folder-standardization` dan `plugin-installation-system` mengatur struktur folder dan siklus hidup PHP plugin; change ini murni menambahkan lapisan frontend yang sebelumnya tidak diatur spec apa pun, tidak mengubah requirement yang sudah ada di kedua spec tersebut)

## Impact

- **Affected code**: `vite.config.ts`, `resources/js/app.tsx`, `resources/css/app.css`, `resources/js/layouts/app/layout.tsx`; seluruh isi `resources/js/pages/workspaces/**` dan `resources/js/{lib/echo.ts,hooks/use-socket-events.ts,hooks/use-broadcast-channel.ts,stores/socket-store.ts}` berpindah lokasi ke `dev-packages/vitamind-workspace-plugin/resources/js/` dan `dev-packages/vitamind-realtime-plugin/resources/js/`.
- **Affected docs**: `docs/local-plugins.md`, `MIGRATION_GUIDE.md`.
- **Dependencies**: tidak memblokir dan tidak diblokir oleh `stabilize-vitamind-packages` (gate stabilitas backend tetap berjalan independen), tapi wakuwaku (konsumen resync `dev-packages/`) terblokir mulai kerja realtime-data sampai change ini merge ke `dev`. Selaras dengan prinsip system/brand token di `formalize-design-system-tokens` (belum di-commit) — plugin tidak pernah ship CSS/token sendiri.
- **Konsumen existing**: BukuWarga/LembarUji/UangKas (fork sekali, tanpa live-sync) tidak terpengaruh sama sekali oleh reorganisasi file ini. wakuwaku (resync berkelanjutan) mendapat manfaat begitu merge ke `dev`, dengan catatan mungkin perlu menempel manual perubahan di file level host (`vite.config.ts`, `app.tsx`, `app.blade.php`) satu kali jika mekanisme resync mereka hanya mencakup `dev-packages/`.
- **Breaking changes**: tidak ada breaking change pada API publik `vitamind/core`/`vitamind/workspace-plugin`/`vitamind/realtime-plugin` — perpindahan file FE murni reorganisasi internal repo ini, aman terhadap model fork-copy-no-live-sync yang sudah didokumentasikan.
