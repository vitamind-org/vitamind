## Context

`fix-plugin-frontend-distribution` (archived, `6b42275`) sudah membenahi arah dependency FE untuk dua footprint: **halaman utuh** (`@plugin/{name}/{page}`, resolver hybrid di `app.tsx`) dan **hook/utilitas lintas-halaman** (`usePlugin('{name}')`, degradasi graceful lewat `{}` kalau plugin tak terpasang). Keduanya diverifikasi lengkap di `openspec/specs/plugin-frontend-distribution/spec.md`.

Satu footprint belum tersentuh: **primitives UI React** (`Button`, `Dialog`, `Popover`, `Command`, dst. di `resources/js/components/ui/*`, plus `cn()` di `resources/js/lib/utils.ts`). Grep saat ini menunjukkan 18 file lintas dua plugin (`vitamind-workspace-plugin`, `vitamind-archive-plugin`) meng-import langsung dari `@/components/ui/*` — alias `resources/js` milik HOST. Ini sudah dianggap benar/diterima untuk **token Tailwind** (`plugin-frontend-distribution`'s requirement "Plugin Tailwind classes are generated from host design tokens" — plugin memang harus pakai className host, tidak boleh bawa token sendiri), tapi *belum pernah* dibuat keputusan eksplisit yang setara untuk KOMPONEN React-nya sendiri (bukan cuma className-nya).

Kontras dengan backend: `vitamind/core` dan setiap plugin composer.json sama-sama `require: "vitamind/plugin-sdk": "dev-master"` — leaf package yang tidak dimiliki `core` maupun plugin manapun, di-require oleh siapa pun yang butuh. Frontend hari ini tidak punya padanan itu untuk UI primitives; plugin reach-into host langsung, searah berlawanan dengan pola backend.

Pemicu nyata perubahan ini: diskusi arsitektur (bukan bug/insiden) yang menyimpulkan primitives UI seharusnya sejajar `plugin-sdk` — leaf, dibutuhkan siapa pun yang butuh, bukan menempel ke `vitamind/core` (lihat Decisions D1) maupun tetap "milik host". Tidak ada konsumen yang terblokir; ini didokumentasikan sekarang supaya penalaran tidak hilang, dieksekusi kapan pun ada kapasitas.

## Goals / Non-Goals

**Goals:**
- Plugin distributed mengakses primitives UI lewat alias netral (`@vitamind/ui/*`) yang me-resolve ke lokasi fisik yang sama baik dipanggil dari host maupun dari plugin — bukan lewat `@/*` milik host.
- Host sendiri juga migrasi ke alias yang sama (`@vitamind/ui/*` menggantikan `@/components/ui/*` untuk primitives, sisa `@/*` tetap dipakai untuk kode host lain seperti `pages/`, `hooks/`, `types/`) — supaya tidak ada dua sumber kebenaran (host pakai `@/components/ui`, plugin pakai `@vitamind/ui` yang keduanya menunjuk folder sama tapi nama beda untuk konsumen yang beda itu sendiri membingungkan).
- Tetap di dalam model kompilasi single-Vite yang sudah berjalan — tidak ada proses watch/build terpisah baru.

**Non-Goals:**
- Ekstraksi `@vitamind/ui` jadi npm package sungguhan (folder `node_modules`, `package.json` sendiri, semver, publish ke registry). Itu prasyarat untuk "tiap plugin declare dependency versi sendiri", yang sengaja didorong ke proposal terpisah (lihat companion change `add-per-plugin-npm-dependency-declarations` atau nama serupa) — bersyarat pada kebutuhan distribusi arms-length yang belum terbukti, mengikuti preseden Non-Goals `fix-plugin-frontend-distribution` yang sama.
- Mengubah token/CSS apa pun. `resources/css/base.css`, `formalize-design-system-tokens` tidak tersentuh — token warna tetap 100% domain host.
- Memindahkan file fisik `components/ui/*` ke folder baru. Lokasi fisik tetap `resources/js/components/ui/`; yang berubah cuma nama alias yang dipakai untuk mengaksesnya (lihat D2).

## Decisions

### D1 — `@vitamind/ui` bukan bagian dari `vitamind/core`
Kalau primitives UI menempel ke `core`, plugin ringan yang sengaja TIDAK depend ke `core` (mis. `vitamind/todo-plugin`, yang composer.json-nya cuma `require: vitamind/plugin-sdk`) terpaksa ikut depend ke seluruh infrastruktur `core` (auth, admin, user management) hanya untuk dapat `Button`. Itu coupling yang tidak perlu antara dua kebutuhan orthogonal: "butuh primitives UI" vs. "butuh infrastruktur app penuh".

*Alternatif yang ditolak*: bundling primitives ke `vitamind/core` lalu plugin akses lewat `@plugin/core/...`. Ditolak karena memaksa setiap plugin yang butuh UI (hampir semua plugin dengan halaman kustom) juga menambahkan dependency composer ke `core`, padahal kebutuhan backend dan frontend mereka berbeda.

### D2 — Alias baru, bukan pindah lokasi fisik
`@vitamind/ui/*` di `vite.config.ts` dan `tsconfig.json`'s `paths` di-map ke lokasi fisik yang SAMA dengan `@/components/ui` dan `@/lib/utils` hari ini (`resources/js/components/ui/`, `resources/js/lib/utils.ts`). Host sendiri diupdate memakai alias baru ini juga (bukan cuma plugin), supaya tidak ada dua alias berbeda menunjuk folder yang sama dari dua konsumen berbeda — itu sendiri sumber kebingungan baru yang berlawanan dengan tujuan change ini.

*Alternatif yang ditolak*: memindahkan `components/ui/*` secara fisik ke folder baru netral (mis. `resources/js/ui-kit/`) sekaligus. Ditolak untuk change ini karena menambah risiko (broken relative import di puluhan file host) yang tidak perlu — pemisahan ALIAS sudah cukup untuk menyatakan arah dependency yang benar; pemisahan LOKASI FISIK cuma relevan kalau/ketika `@vitamind/ui` benar-benar diekstrak jadi package terpisah (Non-Goals, lihat companion change).

### D3 — Cakupan migrasi: seluruh plugin distributed yang ada, host mengikuti
18 file di `vitamind-workspace-plugin` dan `vitamind-archive-plugin` (grep `@/components/ui\|@/lib/utils` di `dev-packages/*/resources/js`) diupdate importnya ke `@vitamind/ui/*`. Host sendiri (`resources/js/**` di luar `dev-packages/`) juga diupdate ke alias yang sama untuk primitives UI, supaya alias `@vitamind/ui` konsisten dipakai semua pihak — bukan cuma migrasi satu arah plugin-ke-host.

*Kenapa host juga ikut, bukan cuma plugin*: kalau host tetap pakai `@/components/ui/button` sementara plugin pakai `@vitamind/ui/button` untuk file fisik yang sama, itu dua nama untuk satu hal — membingungkan pembaca kode dan menghilangkan sinyal "ini leaf bersama" yang justru menjadi tujuan change ini.

## Risks / Trade-offs

- **[Risk]** Migrasi import di 18+ file plugin bisa lolos satu-dua referensi (pola yang sama pernah terjadi di `fix-plugin-frontend-distribution`'s task 5.3, `workspace-switch.tsx` lolos dari pencarian awal). **Mitigasi**: grep akhir `@/components/ui\|@/lib/utils` di `dev-packages/` harus nol hasil sebelum change ini dianggap selesai, sama seperti pola verifikasi yang sudah dipakai change sebelumnya.
- **[Trade-off]** Menunda ekstraksi npm package sungguhan (Non-Goals) berarti `@vitamind/ui` tetap cuma alias di dalam satu proses kompilasi — tidak benar-benar independen/reusable di luar monorepo ini. Diterima sadar: lihat companion change untuk kapan ini layak dieksekusi (bersyarat kebutuhan arms-length nyata, bukan diprediksi sekarang).
- **[Risk]** Nama `@vitamind/ui` mirip penamaan npm scope (`@vitamind/...`) padahal saat ini BUKAN package npm sungguhan — berpotensi menyesatkan pembaca yang mengira ada `package.json` terpisah. **Mitigasi**: didokumentasikan eksplisit di `docs/local-plugins.md` bahwa ini alias Vite/tsconfig, bukan package npm, sampai companion change (kalau dieksekusi) mengubahnya jadi package sungguhan.

## Migration Plan

1. Tambah `@vitamind/ui/*` di `vite.config.ts`'s `resolve.alias` dan `tsconfig.json`'s `paths`, menunjuk ke lokasi fisik `resources/js/components/ui` dan `resources/js/lib/utils.ts` yang sudah ada (tidak ada file yang dipindah).
2. Update seluruh import di host (`resources/js/**` di luar `dev-packages/`) dari `@/components/ui/*`/`@/lib/utils` ke `@vitamind/ui/*`.
3. Update 18 file di `dev-packages/vitamind-workspace-plugin` dan `dev-packages/vitamind-archive-plugin` dengan pola yang sama.
4. Update `docs/local-plugins.md`'s bagian "Distributed plugin frontend" untuk mendokumentasikan `@vitamind/ui/*` sebagai jalur primitives UI, dan catatan bahwa ini alias-level (bukan npm package sungguhan).
5. Verifikasi: `npm run types`, `npm run build`, grep akhir nol hasil untuk pola lama.

Rollback: revert per-langkah aman, karena setiap langkah independen dan cuma mengubah string import + satu entri alias, tidak ada migrasi data/skema.

## Open Questions

- Apakah `@vitamind/ui` juga perlu mencakup non-primitive shared utilities lain (mis. `resources/js/hooks/use-initials.ts`, yang dipakai `workspace-switch.tsx` lewat `@/hooks/use-initials` dan dibiarkan sebagai host-alias di change `extract-frontend-ui-kit`'s pendahulu)? Belum diputuskan — diusulkan dibahas terpisah kalau pola serupa (hook generik non-plugin-specific yang dipakai banyak plugin) terbukti berulang, bukan diputuskan sekarang dari satu kasus.
