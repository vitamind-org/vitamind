## Context

VitaminD punya tiga sumber plugin — Local (`app/Plugins/*`), GitHub (`storage/plugins/*`), dan Composer (`vendor/vitamind/*`, dikembangkan di monorepo ini lewat `dev-packages/` via path repository dengan `symlink: true`) — didefinisikan oleh `VitaminD\Core\Actions\Plugins\DiscoverPlugins`. Backend-nya seragam dan matang: tiap plugin adalah `ServiceProvider` yang meregistrasi migration, route beratribut, middleware, dan Inertia shared-data lewat pola yang konsisten.

Frontend-nya tidak seragam. Satu-satunya jalur yang benar-benar berfungsi lintas ketiga sumber adalah sistem `dynamic-page` (backend mendeklarasikan `RegisterPage`/`RegisterDataTable`, satu komponen generik `resources/js/pages/plugins/dynamic-page.tsx` merender CRUD-nya). Begitu plugin butuh UI kustom:

- Mekanisme `@plugin/{name}` yang sudah ada di `vite.config.ts` (`getPluginAliases()`) dan `resources/js/app.tsx` (cabang resolver `@plugin/`) menunjuk ke `app/Plugins/Local/{Vendor}/{Name}/resources/js` — convention yang sudah digantikan struktur flat `app/Plugins/{Name}/` sejak migrasi Phase 1 (`MIGRATION_GUIDE.md`, `docs/local-plugins.md`). Tidak ada plugin yang match; kode ini mati.
- `vitamind/workspace-plugin` (halaman invite/onboarding/index workspace) dan `vitamind/realtime-plugin` (hook `useSocketEvents`/`useBroadcastChannel`, `echo.ts`, `socket-store.ts`) punya UI kustom nyata, tapi source-nya hidup di `resources/js/` milik HOST, bukan di package masing-masing di `dev-packages/`.
- Tidak ada scan Tailwind (`@source`) yang mencakup `dev-packages/`/`vendor/vitamind/*`.

Pendorong nyata perubahan ini: proyek `wakuwaku`, konsumen dogfooding ke-4 (di luar BukuWarga/LembarUji/UangKas yang tercatat di gate `stabilize-vitamind-packages`), fork boilerplate ini dan **resync berkelanjutan** dengan `dev-packages/` — bukan model fork-sekali-lalu-divergen yang didokumentasikan `MIGRATION_GUIDE.md` untuk titik ekstraksi awal `vitamind/core`/`vitamind/workspace-plugin`. wakuwaku menunggu keputusan ini merge ke `dev` sebelum mulai kerja UI realtime-data mereka.

## Goals / Non-Goals

**Goals:**
- Local plugin punya jalur FE yang jelas: tidak ada mekanisme khusus — ditulis langsung sebagai kode host.
- Plugin Composer/GitHub ("distributed" tier) bisa membawa halaman Inertia dan hook/komponen kustom, di-resolve lewat mekanisme yang sudah ada di repo tapi salah target (`@plugin/{name}`), diperbaiki menunjuk ke lokasi yang benar.
- className Tailwind di komponen plugin distributed menghasilkan CSS nyata, tanpa plugin pernah membawa stylesheet/token sendiri.
- Cara host **memanggil** kode plugin (halaman maupun hook) stabil terhadap perubahan mekanisme resolusi di masa depan — tidak memaksa migrasi ulang situs pemanggilan ketika (atau jika) sebuah plugin pindah ke distribusi bundle mandiri.
- Retroaktif: `vitamind/workspace-plugin` dan `vitamind/realtime-plugin` pindah dari "FE hidup di host" ke "FE hidup di package-nya sendiri", tanpa mengubah perilaku user-facing maupun API publik PHP-nya.

**Non-Goals:**
- Membangun pipeline build terpisah per-plugin (bundle mandiri, externalize React/ReactDOM/Inertia lewat runtime global, self-registration wiring, script-tag injection) untuk konsumsi Packagist arms-length. Ini target arsitektur jangka panjang yang sah, tapi biayanya (kehilangan HMR satu-proses, perlu proses watch paralel) jatuh sekarang ke tim yang justru butuh kecepatan iterasi (vitamind-org sendiri, dan wakuwaku), untuk risiko (drift versi dependency) yang masih hipotetis.
- Mengubah kebijakan publish `stabilize-vitamind-packages`/`publish-vitamind-packages` atau daftar proyek gate-nya. Status wakuwaku sebagai konsumen ke-4 dicatat di sini sebagai konteks, keputusan rekonsiliasi gate ada di tangan pemilik proyek.
- Mengubah requirement `plugin-folder-standardization` atau `plugin-installation-system` — keduanya mengatur struktur folder dan siklus hidup PHP, tidak tersentuh oleh perubahan frontend ini.

## Decisions

### D1 — Local plugin: tanpa mekanisme khusus, manifest host adalah sumber kebenaran
Local plugin (`app/Plugins/*`) adalah kode aplikasi, bukan unit distribusi (`docs/local-plugins.md` sudah menyatakan ini untuk PHP). Keputusan ini memperluas prinsip yang sama ke FE: developer menulis halaman/komponen langsung di `resources/js/pages/...`, dikompilasi sebagai bagian build host biasa. **Konsekuensi**: `getPluginAliases()` (bagian yang scan `app/Plugins/Local/*`) dan cabang `@plugin/` di `app.tsx` yang menunjuk convention itu **dihapus**, bukan diperbaiki — target-nya sudah tidak ada.

*Alternatif yang ditolak*: memperbaiki glob supaya menunjuk `app/Plugins/{Name}/resources/js` (struktur flat yang berlaku sekarang). Ditolak karena menambah mekanisme untuk kasus yang sudah punya solusi lebih sederhana (tulis langsung di `resources/js/pages`), dan Local plugin secara definisi tidak pernah "didistribusikan" — tidak ada manfaat isolasi yang didapat dari alias khusus.

### D2 — Plugin distributed (Composer/GitHub): repoint mekanisme `@plugin/` yang ada, bukan bikin baru
`vendor/vitamind/*` adalah symlink ke `dev-packages/vitamind-*` (composer path repository, `symlink: true`). Karena itu, source FE plugin yang ditaruh di `dev-packages/vitamind-{plugin}/resources/js/` tetap terlihat oleh proses Vite host yang sama — tidak perlu build terpisah. `getPluginAliases()` diarahkan ulang untuk scan `vendor/vitamind/*/resources/js` (alih-alih `app/Plugins/Local/*`), menghasilkan alias `@plugin/{kebab-name}` seperti sebelumnya. Resolver halaman di `app.tsx` untuk pola `@plugin/{name}/{page}` di-glob dari `vendor/vitamind/*/resources/js/pages/**`.

*Kenapa bukan build terpisah dari awal*: lihat Non-Goals — biaya tooling (proses watch paralel, kehilangan HMR satu-proses) tidak sepadan dengan manfaatnya selama semua konsumen aktif (vitamind-org sendiri, wakuwaku) masih berada dalam model "resync + kompilasi bareng host", bukan Packagist arms-length murni.

*Cakupan GitHub-source plugin (`storage/plugins/*`)*: **di luar scope perbaikan mekanisme build-time** di change ini. Plugin GitHub diinstal saat runtime (setelah `vite build` terakhir), sehingga tidak mungkin ter-glob oleh proses build manapun tanpa rebuild — batasan struktural yang didokumentasikan di eksplorasi sebelum change ini, bukan sesuatu yang diselesaikan di sini. Plugin GitHub tetap terbatas pada sistem `dynamic-page` sampai ada keputusan terpisah soal rebuild-on-install atau bundle mandiri.

### D3 — Interface pemanggilan stabil: `usePlugin()` dan resolver hybrid
Situs pemanggil (`layout.tsx`, dsb.) tidak boleh bergantung langsung pada BENTUK resolusi (glob vs. registry runtime) — cuma pada API-nya. Dua bagian:
- **Hook/utilitas non-halaman**: helper `usePlugin('nama-plugin')` mengembalikan objek berisi hook/fungsi yang diekspor plugin. Implementasinya sekarang membaca dari peta hasil `import.meta.glob` (mode build-bareng); kelak boleh diperluas membaca dari registry runtime tanpa mengubah situs pemanggil.
- **Halaman Inertia**: `resolve()` di `app.tsx` untuk pola `@plugin/{name}/{page}` dibuat hybrid — cari di peta glob dulu, siapkan (boleh kosong dulu) jalur fallback ke registry runtime untuk dipakai nanti.

*Alternatif yang ditolak*: import statis langsung ke path plugin di setiap situs pemanggil. Ditolak karena mengikat bentuk pemanggilan ke mekanisme resolusi saat ini — migrasi ke mode bundle mandiri nanti akan memaksa mengubah ulang setiap situs pemanggil, persis risiko "migrasi ganda" yang ingin dihindari change ini.

*Detail yang muncul saat implementasi*: `import type { X } from '@plugin/{name}/...'` (menembus ke tipe internal plugin lewat ambient wildcard `declare module '@plugin/*';`) gagal type-check (`TS2709: Cannot use namespace as a type` — dikonfirmasi lewat reproduksi terisolasi). Ambient module kosong cuma cocok untuk import VALUE yang opaque, bukan ekstraksi named type presisi. Solusinya sekaligus memperkuat prinsip D3: host mendefinisikan kontrak tipenya SENDIRI (`resources/js/types/realtime-plugin.ts` — `SocketStatus`, `RealtimePlugin`), bukan mengimpor tipe dari source plugin. Ini pola yang sama untuk dipakai plugin lain ke depan: situs pemanggil di host selalu punya kontrak tipe miliknya sendiri, cocok secara struktural dengan tipe asli plugin (TypeScript structural typing menjamin ini aman) tapi tidak pernah type-import langsung dari `@plugin/...`.

### D4 — CSS: plugin tidak pernah membawa stylesheet sendiri; host menambah satu `@source`
Token/brand tetap 100% domain host (selaras `formalize-design-system-tokens`, sistem tier system/brand). Plugin memakai className Tailwind biasa di JSX-nya; `resources/css/app.css` menambah `@source '../../vendor/vitamind';` — bentuk direktori polos, mengikuti pola `@source '../views'` yang sudah ada, bukan glob eksplisit seperti baris vendor Blade Laravel (`@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';`) — dikonfirmasi lewat build empiris (lihat tasks.md §2–3) bahwa bentuk glob `*/resources/js/**` tidak match, sementara bentuk direktori polos bekerja. Kasus animasi kustom yang tidak tercakup vocabulary Tailwind diselesaikan lewat penambahan `@keyframes`/`@theme` yang disengaja di host, atau `style={{}}` inline di komponen plugin — bukan lewat plugin membawa CSS sendiri.

### D5 — Retroaktif: pindahkan FE `workspace-plugin` dan `realtime-plugin` sekarang, bukan nanti
Dua bentuk footprint yang berbeda penanganannya:
- **Halaman utuh** (`workspace-plugin`: `pages/workspaces/{index,onboarding}.tsx` + komponennya) → pindah apa adanya ke `dev-packages/vitamind-workspace-plugin/resources/js/pages/`.
- **Infrastruktur lintas-halaman** (`realtime-plugin`: hook yang dipanggil di `layouts/app/layout.tsx`) → pindah ke `dev-packages/vitamind-realtime-plugin/resources/js/{hooks,lib,stores}/`, `layout.tsx` memanggilnya lewat `usePlugin('realtime-plugin')` (D3), bukan import langsung.

Satu bentuk **tidak** dipindah: suntikan data `pendingInvite` dari `WorkspaceServiceProvider::registerInertiaSharedData()` ke `resources/js/pages/auth/register.tsx` — halaman itu tetap milik host, ini kontrak data lintas boundary, bukan kepemilikan UI. Cukup diverifikasi masih berfungsi setelah migrasi.

*Kenapa sekarang, bukan ditunda*: aman terhadap gate stabilitas — FE didistribusikan fork-copy-no-live-sync untuk BukuWarga/LembarUji/UangKas (`MIGRATION_GUIDE.md`), jadi reorganisasi file di repo ini tidak menyentuh mereka sama sekali; API publik PHP kedua package tidak berubah. Menunda migrasi `realtime-plugin` secara khusus ditolak karena wakuwaku adalah konsumen nyata yang terblokir, bukan hipotetis.

### D6 — Kebijakan rilis Packagist masa depan (dicatat, tidak dieksekusi di change ini)
Ketika `publish-vitamind-packages` akhirnya berjalan, tarball rilis sebaiknya tidak membuang `resources/js` mentah walau nanti juga ada `dist/` bundle matang — supaya opsi "resync + kompilasi bareng host" tetap terbuka untuk konsumen mana pun yang tidak butuh isolasi penuh. Ini catatan kebijakan untuk change `publish-vitamind-packages` nanti, bukan task di sini.

## Risks / Trade-offs

- **[Risk]** `layout.tsx` memanggil `usePlugin('realtime-plugin')` tapi sebuah fork tidak menginstal `realtime-plugin` sama sekali → build/runtime error. **Mitigasi**: `usePlugin()` mengembalikan objek kosong/no-op kalau plugin tidak ditemukan di peta glob (bukan throw), dan pemanggil (`layout.tsx`) menangani hasil kosong secara graceful (mis. status socket dianggap "disconnected" alih-alih crash).
- **[Risk]** Symlink `vendor/vitamind/*` → `dev-packages/vitamind-*` mungkin tidak konsisten ditelusuri oleh glob engine Vite 8 (kelas masalah yang sama seperti yang sudah pernah ditambal dengan `realpath()` di `CoreServiceProvider::registerRoutes()` untuk sisi PHP). **Mitigasi**: diverifikasi empiris sebagai bagian task implementasi sebelum dianggap selesai (lihat tasks.md); kalau bermasalah, solusinya sama seperti sisi PHP — resolve symlink eksplisit sebelum di-glob.
- ~~**[Risk]** Memindahkan file FE `workspace-plugin`/`realtime-plugin` bisa lolos mengubah path yang direferensikan `Inertia::render(...)`...~~ **Terjadi, dalam bentuk lebih luas dari dugaan**: `Inertia::render()` sendiri diupdate tanpa masalah, tapi `resources/views/app.blade.php`'s `@vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])` ternyata TIDAK mengasumsikan lokasi halaman dengan benar untuk `@plugin/...` — melempar `ViteException` ("Unable to locate file in Vite manifest") persis seperti diduga, tapi dari sisi Blade, bukan sisi controller. Diperbaiki dengan `realpath()` sebelum lookup manifest (detail di tasks.md §5.4), mengikuti pola symlink-fix yang sama seperti `CoreServiceProvider::registerRoutes()`. Ini konfirmasi empiris bahwa smoke test manual (bukan cuma `npm run build`/`tsc`) benar-benar diperlukan — bug ini tidak akan tertangkap oleh build/type-check saja.
- **[Trade-off]** Menunda pipeline build-terpisah (Non-Goals) berarti drift versi dependency antara fork konsumen dan vitamind-org tetap mungkin terjadi selama fase ini. Diterima secara sadar — lihat Non-Goals untuk alasannya; dipantau, bukan diabaikan permanen.
- ~~**[Risk]** wakuwaku mungkin cuma resync `dev-packages/`, bukan file level host...~~ **Terselesaikan**: dikonfirmasi pemilik proyek bahwa wakuwaku melakukan *full resync* (mencakup file level host), jadi tidak ada langkah manual tambahan yang diperlukan di pihak mereka.

## Migration Plan

Urutan implementasi (detail lengkap di `tasks.md`):
1. Hapus mekanisme `@plugin/` lama yang menunjuk `app/Plugins/Local/*` (D1).
2. Bangun ulang mekanisme `@plugin/{name}` menunjuk `vendor/vitamind/*/resources/js` (D2), verifikasi symlink+glob bekerja dengan plugin dummy sebelum dipakai plugin nyata.
3. Tambah `@source` Tailwind (D4).
4. Bangun `usePlugin()` dan resolver hybrid (D3).
5. Pindahkan FE `workspace-plugin` (D5), verifikasi tiap halaman.
6. Pindahkan FE `realtime-plugin` (D5), verifikasi status koneksi socket masih tampil di `AppHeader`.
7. Update dokumentasi (`docs/local-plugins.md`, `MIGRATION_GUIDE.md`).

Tidak ada migrasi data/database — perubahan murni pada source frontend dan konfigurasi build. Rollback berarti mengembalikan file ke lokasi lama di host (git revert per langkah), aman dilakukan per-tahap karena setiap langkah independen dapat diverifikasi sebelum lanjut.

## Open Questions

- ~~Apakah mekanisme resync wakuwaku menarik ulang file level host...~~ **Terjawab (pemilik proyek)**: wakuwaku melakukan *full resync* — mencakup `vite.config.ts`, `resources/js/app.tsx`, `resources/css/app.css`, `resources/views/app.blade.php`, bukan cuma isi `dev-packages/`. Tidak ada langkah tempel-manual yang diperlukan; mereka otomatis dapat seluruh perubahan change ini lewat resync.
- ~~Apakah gate `stabilize-vitamind-packages` perlu direkonsiliasi...~~ **Terjawab (pemilik proyek)**: ya. Eksekusinya (menambahkan wakuwaku sebagai proyek dogfooding ke-4 di gate `package-stability-gate`) tetap dilakukan lewat perubahan pada change `stabilize-vitamind-packages` sendiri, bukan sebagai bagian implementasi change ini — dicatat sebagai item koordinasi di tasks.md §8.
- Kapan tepatnya "bukti kebutuhan nyata" (Non-Goals) dianggap terpenuhi untuk mulai mengerjakan pipeline build-terpisah? Belum ada kriteria eksplisit — perlu diputuskan pemilik proyek saat gejalanya muncul, bukan diprediksi sekarang.
