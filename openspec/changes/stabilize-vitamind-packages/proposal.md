## Why

`phase1-standalone-boilerplate` telah menyelesaikan ekstraksi teknis VitaminD Core dan Workspace Plugin menjadi package standalone (§1–13: extraction, plugin system, docs — semua selesai), namun closure-nya (§15) digantungkan ke §16 (Stability Validation): gate yang mensyaratkan 4 proyek konsumen eksternal (BukuWarga, LembarUji, UangKas, wakuwaku) berhasil meng-extend boilerplate ini tanpa breaking change. Timeline keempat proyek itu berada di luar kendali langsung repo ini, sehingga menggantungkan closure phase1 pada gate tersebut membuat phase1 tidak pernah bisa ditutup meski pekerjaan ekstraksinya sudah selesai secara struktural.

Change ini memisahkan validasi stabilitas dari `phase1-standalone-boilerplate`, mengikuti pola yang sama seperti pemisahan publishing sebelumnya (§14 → `publish-vitamind-packages`): §16 bukan pekerjaan ekstraksi, melainkan gate kepercayaan yang layak jadi unit kerja sendiri dengan timeline sendiri.

## What Changes

- Memindahkan §16 (Stability Validation / Dogfooding) dari `phase1-standalone-boilerplate/tasks.md` ke change ini, tanpa mengubah isi task (BukuWarga, LembarUji, UangKas, gate check) — wakuwaku ditambahkan belakangan sebagai proyek ke-4, lihat §Impact.
- Mendefinisikan gate stabilitas sebagai capability tersendiri (`package-stability-gate`) sehingga change lain bisa merujuk ke sini alih-alih ke `phase1-standalone-boilerplate §16`.
- Update rujukan di `publish-vitamind-packages` (proposal.md "Gate Masuk" dan spec `package-distribution`) dari "`phase1-standalone-boilerplate` §16" menjadi "`stabilize-vitamind-packages`" (dieksekusi sebagai bagian dari pemisahan ini, di luar change ini sendiri).
- Tidak ada perubahan kode aplikasi — change ini murni proses validasi dogfooding via 4 proyek konsumen nyata di luar repo ini.

## Capabilities

### New Capabilities
- `package-stability-gate`: mendefinisikan syarat gate stabilitas (≥2 dari 4 proyek konsumen berhasil extend boilerplate via `dev-packages/` path repository tanpa breaking change pada `vitamind/core` atau `vitamind/workspace-plugin`) yang harus dipenuhi sebelum `publish-vitamind-packages` boleh dieksekusi.

### Modified Capabilities
(tidak ada — perubahan rujukan di `package-distribution` milik `publish-vitamind-packages` dilakukan sebagai bagian dari pekerjaan pemisahan ini, dicatat di change tersebut)

## Impact

- **Dependencies**: change ini baru dieksekusi setelah `phase1-standalone-boilerplate` closed secara struktural (§1–13 selesai). Tidak memblokir closure phase1 — sebaliknya, pemisahan ini yang membuka jalan closure tersebut.
- **Downstream**: `publish-vitamind-packages` gate masuknya berubah rujukan dari `phase1-standalone-boilerplate §16` ke change ini.
- **Proyek eksternal**: BukuWarga, LembarUji, UangKas, wakuwaku — masing-masing perlu meng-extend boilerplate ini via `dev-packages/` path repository dan melaporkan hasilnya (berhasil tanpa breaking change, atau ditemukan breaking change yang perlu di-fix di `vitamind/core`/`vitamind/workspace-plugin`). wakuwaku ditambahkan belakangan (proyek dogfooding ke-4) — use case realtime-data-nya (`vitamind/realtime-plugin`) terblokir sampai change `fix-plugin-frontend-distribution` merge ke `dev`.
- **Tidak ada breaking change** pada kode boilerplate/package — change ini adalah proses validasi, bukan pekerjaan implementasi.
