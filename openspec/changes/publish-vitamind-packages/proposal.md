## Why

`phase1-standalone-boilerplate` awalnya menyatukan ekstraksi package (`vitamind/core`, `vitamind/workspace-plugin`) dengan publishing-nya ke Packagist. Setelah ekstraksi berjalan, diputuskan bahwa publishing perlu ditunda: boilerplate masih dalam masa iterasi cepat dan belum divalidasi oleh pemakai nyata di luar repo ini. Memaksakan publish sekarang berisiko mengunci API/struktur package sebelum stabil.

Change ini memisahkan pekerjaan publishing dari `phase1-standalone-boilerplate` supaya:
- `phase1-standalone-boilerplate` bisa ditutup begitu boilerplate + plugin system stabil, tanpa menunggu Packagist.
- Publishing punya gate masuk yang eksplisit dan dieksekusi sebagai unit kerja terpisah, kapan pun gate itu terpenuhi.

## Gate Masuk (Prasyarat)

Change ini **tidak dieksekusi** sampai change `stabilize-vitamind-packages` memenuhi gate stabilitasnya (`package-stability-gate`):

- Minimal 2 dari 3 proyek konsumen berikut berhasil meng-extend boilerplate ini via `dev-packages/` path repository tanpa memerlukan breaking change pada `vitamind/core` atau `vitamind/workspace-plugin`:
  - BukuWarga
  - LembarUji
  - UangKas

Gate ini sebelumnya ditrack sebagai `phase1-standalone-boilerplate` §16, kini dipisah jadi change tersendiri (`stabilize-vitamind-packages`) supaya closure `phase1-standalone-boilerplate` tidak tergantung pada timeline 3 proyek eksternal tersebut.

Selama gate belum terpenuhi:
- Repo `vitamind-org/vitamind-core` dan `vitamind-org/vitamind-workspace-plugin` di GitHub tetap berfungsi sebagai **mirror saja** (kode di-push, tanpa version tag, tanpa GitHub release).
- Tidak ada registrasi ke Packagist.

## What Changes

- Menyiapkan `vitamind/core` dan `vitamind/workspace-plugin` untuk publish resmi ke Packagist (versioning, tag rilis, metadata composer.json final).
- Registrasi kedua package di Packagist.
- Membuat GitHub release tags `v0.1.0` untuk kedua package.
- Verifikasi instalasi fresh project murni via Packagist (bukan path repository), sebagai pengganti verifikasi dev-mode yang sudah dilakukan di `phase1-standalone-boilerplate` (task 12.9).
- Update dokumentasi organisasi `vitamind-org` untuk mendaftar seluruh package yang tersedia.

## Impact

- **Dependencies**: `phase1-standalone-boilerplate` harus berstatus closed, dan gate stabilitas di change `stabilize-vitamind-packages` harus lulus, sebelum change ini mulai dieksekusi.
- **Konsumen existing** (BukuWarga, LembarUji, UangKas): setelah publish, proyek-proyek ini bisa migrasi dari path repository ke versi Packagist resmi (opsional, tidak wajib langsung).
- **Breaking changes**: tidak ada — publishing tidak mengubah kode, hanya cara distribusinya.
