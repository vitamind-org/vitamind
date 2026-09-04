## Context

Companion proposal `extract-frontend-ui-kit` memindahkan primitives UI ke alias netral `@vitamind/ui/*`, tapi tetap di dalam model kompilasi single-Vite yang sudah berjalan — semua plugin distributed (`dev-packages/vitamind-*`) dikompilasi BERSAMA host dalam satu proses, tanpa `package.json` sendiri, tanpa versi eksplisit. Ini sudah cukup untuk menyelesaikan masalah ARAH dependency (plugin tidak lagi reach-into host), tapi belum menyelesaikan masalah KONTRAK: tidak ada tempat plugin men-declare "saya butuh `@vitamind/ui` versi sekian", `react` versi sekian, dst. — padanan yang sudah ada di sisi backend lewat `composer.json`'s `require`.

Model distribusi FE VitaminD hari ini (didokumentasikan di `fix-plugin-frontend-distribution` dan `formalize-design-system-tokens`) adalah **fork + resync**: konsumen dogfooding (BukuWarga, LembarUji, UangKas — fork sekali, tanpa live-sync; wakuwaku — resync berkelanjutan penuh termasuk file level host) semuanya mengkompilasi source plugin bersama host. Tidak ada konsumen yang memasang plugin secara **arms-length** (dari registry, tanpa akses source host) hari ini.

## Goals / Non-Goals

**Goals:**
- Mendefinisikan BENTUK TARGET kontrak dependency FE per-plugin (analog `composer.json`), supaya kalau/ketika dieksekusi, tim tidak mendesain ulang dari nol.
- Mendefinisikan trigger yang jelas dan dapat diverifikasi untuk KAPAN proposal ini layak diangkat dari status "deferred" ke "dikerjakan" — supaya keputusan "belum sekarang" tidak menjadi "tidak pernah dievaluasi ulang".

**Non-Goals:**
- Mengeksekusi apa pun dari proposal ini sekarang. Tidak ada `package.json` baru dibuat, tidak ada tooling workspace dipasang, sampai trigger di bawah terpenuhi dan seseorang secara sadar mengangkat proposal ini.
- Menentukan tooling spesifik (npm workspaces vs. pnpm vs. Turborepo/Nx) — itu keputusan implementasi yang dibuat SAAT proposal ini dieksekusi, dengan konteks kebutuhan nyata yang tersedia saat itu, bukan diprediksi sekarang.

## Decisions

### D1 — Trigger eksekusi: bukti kebutuhan nyata, bukan jadwal
Proposal ini dianggap layak dieksekusi kalau SALAH SATU dari berikut ini terjadi (mengikuti preseden persis `fix-plugin-frontend-distribution`'s Non-Goals, yang memakai kriteria sama untuk build pipeline terpisah):
- **Drift versi dependency nyata**: sebuah konsumen (dogfooding atau eksternal) mengalami bug yang disebabkan ketidakcocokan versi `react`/`@inertiajs/react`/dependency FE lain antara host dan sebuah plugin, yang tidak bisa dideteksi karena tidak ada kontrak versi eksplisit.
- **Konsumen non-monorepo sungguhan**: ada pihak yang ingin memasang satu plugin VitaminD (mis. `vitamind/workspace-plugin`) ke aplikasi Laravel+Inertia yang BUKAN fork boilerplate ini — perlu instalasi arms-length lewat npm/Packagist tanpa akses ke source host.
- **Pemilik proyek memutuskan** proposal ini dikerjakan terlepas dua kondisi di atas, mis. untuk kerapian arsitektur jangka panjang.

*Kenapa bukan "kerjakan saja sekarang selagi masih ingat"*: biaya nyata (tooling monorepo FE, kehilangan sebagian kemudahan HMR satu-proses, setiap plugin baru butuh setup `package.json` sejak hari pertama) jatuh ke SEMUA kontributor sekarang, untuk manfaat yang baru terasa kalau salah satu trigger di atas benar-benar terjadi. Ini persis penalaran yang sudah dipakai & terbukti berhasil di `fix-plugin-frontend-distribution`.

### D2 — Bentuk kontrak: `package.json` per-plugin, bukan bagian dari `composer.json`
Dependency FE dan BE tetap dua file terpisah (`package.json` vs `composer.json`) per plugin — bukan mencoba menyatukan keduanya ke satu manifest. Ini mengikuti konvensi ekosistem JS/PHP yang sudah mapan (tidak ada satu pun package Composer real yang mendeklarasikan dependency npm di dalam `composer.json`-nya), dan menghindari perlu menulis tooling kustom untuk "manifest gabungan" yang tidak dipahami tooling standar (`npm install`, Composer, IDE) di kedua ekosistem.

*Alternatif yang ditolak*: extra field kustom di `composer.json` (mis. `extra.npm-dependencies`) yang dibaca oleh skrip build kustom. Ditolak karena tidak dikenali tooling npm standar sama sekali — kontributor baru harus belajar mekanisme khusus VitaminD, bukan konvensi umum yang mereka sudah tahu.

### D3 — Workspace tooling: keputusan implementasi, ditunda ke saat eksekusi
Pilihan antara npm workspaces (paling minim setup, konsisten dengan `npm` yang sudah dipakai repo ini), pnpm workspaces, atau Turborepo/Nx (untuk caching build lintas-package) TIDAK diputuskan di proposal ini. Keputusan itu bergantung pada skala aktual saat proposal ini dieksekusi (berapa banyak plugin punya `package.json` sendiri saat itu, apakah caching build lintas-package benar-benar jadi bottleneck).

## Risks / Trade-offs

- **[Risk]** Proposal ini didokumentasikan sekarang tapi trigger-nya (D1) mungkin tidak pernah terjadi, membuatnya jadi dokumen mati yang tidak relevan lagi kalau arsitektur berubah drastis sebelum trigger terpenuhi. **Mitigasi**: tidak ada mitigasi khusus diperlukan — ini konsekuensi yang diterima sadar dari mendokumentasikan keputusan bersyarat; kalau kelak jadi tidak relevan, proposal ini cukup ditutup/diarsipkan tanpa dieksekusi, bukan kegagalan.
- **[Trade-off]** Menunda ini (bersama `extract-frontend-ui-kit`'s Non-Goals yang sama) berarti selama fase ini, plugin distributed TETAP tidak punya kontrak versi FE eksplisit — risiko drift versi yang jadi salah satu trigger D1 tetap ada selama fase menunggu. Diterima sadar, dipantau lewat trigger yang sama.
- **[Risk]** Kalau proposal ini dieksekusi, ada beban migrasi nyata untuk SEMUA plugin distributed yang sudah ada (tambah `package.json`, pasang workspace tooling, uji ulang build). **Mitigasi**: bukan risiko yang perlu dimitigasi sekarang — jadi bagian tasks.md's estimation SAAT proposal ini diangkat untuk dieksekusi, dengan konteks jumlah plugin yang ada saat itu.

## Migration Plan

Tidak ada migrasi dijalankan sebagai bagian proposal ini. Kalau/ketika diangkat untuk dieksekusi, migration plan ditulis ulang saat itu dengan konteks trigger spesifik yang memicunya (drift versi nyata punya urgensi/scope berbeda dari permintaan konsumen arms-length baru).

## Open Questions

- Apakah `plugin-sdk` (leaf backend) perlu padanan versi FE tersendiri (mis. tipe TypeScript bersama untuk kontrak plugin), atau cukup `@vitamind/ui` + `react`/`@inertiajs/react` sebagai dependency FE yang di-declare? Belum diputuskan — bergantung pada bentuk trigger nyata yang muncul.
- Kalau trigger D1's "konsumen non-monorepo" terjadi, apakah `@vitamind/ui` publish ke npm registry PUBLIK atau cukup registry privat/GitHub Packages? Keputusan bisnis yang tidak bisa diprediksi dari sini — didorong ke pemilik proyek saat trigger nyata muncul.
