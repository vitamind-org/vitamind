## Why

`extract-frontend-ui-kit` (proposal terpisah, sama-sama menunggu di `openspec/changes/`) memperbaiki ARAH import primitives UI plugin lewat alias netral `@vitamind/ui/*` — tapi tetap di dalam model kompilasi satu-proses-Vite yang sekarang: `@vitamind/ui` cuma alias, bukan package npm sungguhan dengan `package.json`/semver sendiri, dan plugin tidak punya cara men-declare "saya butuh `@vitamind/ui` versi X" secara eksplisit — sama seperti composer.json mereka men-declare `"vitamind/plugin-sdk": "dev-master"`.

Ini fase lanjutan yang BERSYARAT: layak dieksekusi kalau/ketika distribusi FE VitaminD berpindah dari model "fork + resync" (semua konsumen dogfooding — BukuWarga, LembarUji, UangKas, wakuwaku — mengkompilasi source plugin bersama host dalam satu proses Vite) ke model "arms-length" sungguhan (plugin dipasang dari registry npm/Packagist tanpa akses ke source host, dengan risiko drift versi dependency antar-plugin yang perlu dikelola eksplisit). Sampai saat ini belum ada bukti kebutuhan itu — `fix-plugin-frontend-distribution`'s Non-Goals maupun `extract-frontend-ui-kit`'s Non-Goals sudah menolak investasi tooling ini untuk alasan yang sama, dan proposal ini SENGAJA ditulis sekarang (selagi penalarannya masih segar dari diskusi arsitektur) untuk dieksekusi NANTI, bukan sekarang.

## What Changes

- **(Bersyarat, tidak dieksekusi sampai ada trigger nyata — lihat design.md)** Ekstrak `@vitamind/ui` (hasil `extract-frontend-ui-kit`) jadi npm package sungguhan dengan `package.json`, versi semver, dan build/publish step sendiri.
- Setiap plugin distributed (`dev-packages/vitamind-*`) mendapat `package.json` sendiri, men-declare dependency eksplisit ke `@vitamind/ui` (dan dependency FE lain yang relevan, mis. `react`, `@inertiajs/react` sebagai peer dependency) dengan constraint versi — padanan FE dari apa yang `composer.json` sudah lakukan untuk `vitamind/plugin-sdk`.
- Perkenalkan tooling monorepo FE (npm/pnpm workspaces, atau setara) untuk mengelola banyak `package.json` dalam satu repo tanpa kehilangan single-Vite-compile untuk konsumen yang masih fork+resync.
- **BREAKING (bersyarat)**: kalau dieksekusi, ini mengubah cara plugin baru diinisialisasi (perlu `package.json` sejak awal) — perlu diselaraskan dengan `docs/local-plugins.md`'s panduan "Extracting a local plugin into a standalone package".
- **Eksplisit di luar scope sampai trigger terpenuhi**: publish sungguhan ke npm registry publik, versioning independen per-plugin yang benar-benar dirilis, CI untuk build per-package. Itu langkah setelah proposal ini, bukan bagian dari proposal ini.

## Capabilities

### New Capabilities
- `plugin-npm-package-distribution`: Mendefinisikan bentuk TARGET (bukan status hari ini) untuk bagaimana plugin distributed men-declare dependency FE-nya secara eksplisit dan ter-versi lewat `package.json` sendiri — padanan FE dari kontrak `composer.json` yang sudah ada di sisi backend. Requirement di capability ini berlaku begitu proposal ini dieksekusi; sebelum itu, status implementasinya "belum ada" (lihat design.md's trigger condition).

### Modified Capabilities
(tidak ada untuk saat ini — lihat Open Questions di design.md. `plugin-frontend-distribution` kemungkinan besar akan butuh delta lagi kalau proposal ini dieksekusi, tapi requirement spesifiknya sengaja belum ditulis sampai trigger di design.md benar-benar terjadi, supaya spec tidak mendahului kebutuhan nyata)

## Impact

- **Affected code**: berpotensi seluruh `dev-packages/vitamind-*` (tambah `package.json` masing-masing), root `package.json`/`vite.config.ts` (workspace tooling), `docs/local-plugins.md`.
- **Dependencies**: BERGANTUNG pada `extract-frontend-ui-kit` selesai lebih dulu (perlu ada `@vitamind/ui` sebagai alias yang jelas sebelum diekstrak jadi package sungguhan). Tidak memblokir apa pun saat ini — status "menunggu trigger", bukan "menunggu giliran dikerjakan".
- **Breaking changes**: signifikan kalau dieksekusi (lihat What Changes), tapi nol dampak selama status proposal ini "deferred/bersyarat" tanpa trigger terpenuhi.
